<?php
require_once 'guard.php';
include('dbconnect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $paid   = floatval($_POST['AmountPaid']);
    $method = $_POST['PaymentMethod'];
    $notes  = $_POST['Notes'];

    if ($action === 'add') {
        $cid   = intval($_POST['ClientID']);
        $pid   = $_POST['PetID']           ? intval($_POST['PetID'])           : null;
        $conID = $_POST['ConsultationID']  ? intval($_POST['ConsultationID'])  : null;
        $date  = $_POST['BillingDate'];
        $disc  = floatval($_POST['Discount'] ?? 0);

        // Compute total from line items
        $total     = 0;
        $lineItems = [];
        if (!empty($_POST['svc_id'])) {
            foreach ($_POST['svc_id'] as $i => $sid) {
                $sid   = $sid ? intval($sid) : null;
                $desc  = trim($_POST['svc_desc'][$i]  ?? '');
                $qty   = max(1, intval($_POST['svc_qty'][$i]   ?? 1));
                $price = floatval($_POST['svc_price'][$i] ?? 0);
                $sub   = $qty * $price;
                $total += $sub;
                $lineItems[] = [$sid, $desc, $qty, $price, $sub];
            }
        } else {
            $total = floatval($_POST['TotalAmount'] ?? 0);
        }
        $total -= $disc;

        $status = 'Pending';
        if ($paid >= $total && $total > 0) $status = 'Paid';
        elseif ($paid > 0)                 $status = 'Partial';

        $stmt = $conn->prepare("INSERT INTO billing (ClientID,PetID,ConsultationID,BillingDate,TotalAmount,Discount,AmountPaid,PaymentMethod,PaymentStatus,Notes) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iiisdddsss", $cid, $pid, $conID, $date, $total, $disc, $paid, $method, $status, $notes);
        $stmt->execute();
        $newBID = $stmt->insert_id;
        $stmt->close();

        foreach ($lineItems as [$sid, $desc, $qty, $price, $sub]) {
            $si = $conn->prepare("INSERT INTO billing_items (BillingID,ServiceID,Description,Quantity,UnitPrice,Subtotal) VALUES (?,?,?,?,?,?)");
            $si->bind_param("iisidd", $newBID, $sid, $desc, $qty, $price, $sub);
            $si->execute();
            $si->close();
        }

        header("Location: billing.php?msg=Invoice INV-" . str_pad($newBID, 4, '0', STR_PAD_LEFT) . " saved.");
        exit;
    }

    if ($action === 'update') {
        $id           = intval($_POST['BillingID']);
        $cid          = intval($_POST['ClientID']);
        $pid          = $_POST['PetID'] ? intval($_POST['PetID']) : null;
        $date         = $_POST['BillingDate'];
        $disc         = floatval($_POST['Discount'] ?? 0);
        $total        = floatval($_POST['TotalAmount']);
        $updateReason = trim($_POST['UpdateReason'] ?? '');

        $status = 'Pending';
        if ($paid >= $total && $total > 0) $status = 'Paid';
        elseif ($paid > 0)                 $status = 'Partial';

        $stmt = $conn->prepare("UPDATE billing SET ClientID=?,PetID=?,BillingDate=?,TotalAmount=?,Discount=?,AmountPaid=?,PaymentMethod=?,PaymentStatus=?,Notes=?,UpdateReason=? WHERE BillingID=?");
        $stmt->bind_param("iisdddssssi", $cid, $pid, $date, $total, $disc, $paid, $method, $status, $notes, $updateReason, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: billing.php?msg=Invoice updated.");
        exit;
    }
}

// AJAX: live consultation search (for billing source dropdown)
if (isset($_GET['search_consults'])) {
    $q    = trim($_GET['q'] ?? '');
    $safe = '%' . $conn->real_escape_string($q) . '%';
    $numericID = null;
    if (preg_match('/^CON-?0*(\d+)$/i', $q, $m)) $numericID = (int)$m[1];
    elseif (ctype_digit($q)) $numericID = (int)$q;
    $idCond = $numericID !== null ? "OR con.ConsultationID = $numericID" : "";

    $rows = $conn->query("
        SELECT con.ConsultationID, con.ConsultationDate, p.PetName,
               CONCAT(c.FirstName,' ',c.LastName) AS Client,
               COALESCE(SUM(cs.Subtotal),0) AS Total
        FROM consultations con
        JOIN pets p    ON con.PetID=p.PetID
        JOIN clients c ON con.ClientID=c.ClientID
        LEFT JOIN consultation_services cs ON cs.ConsultationID=con.ConsultationID
        WHERE con.IsDeleted=0
          AND NOT EXISTS (
              SELECT 1 FROM billing b
              WHERE b.ConsultationID = con.ConsultationID
                AND b.IsDeleted = 0
                AND b.PaymentStatus = 'Paid'
          )
          AND (p.PetName LIKE '$safe'
               OR CONCAT(c.FirstName,' ',c.LastName) LIKE '$safe'
               OR CONCAT('CON-',LPAD(con.ConsultationID,4,'0')) LIKE '$safe'
               $idCond)
        GROUP BY con.ConsultationID
        ORDER BY con.ConsultationDate DESC LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    exit(json_encode($rows));
}

// AJAX: load consultation data for billing pre-fill
if (isset($_GET['load_consult'])) {
    $cid = intval($_GET['id']);
    $row = $conn->query("SELECT con.ConsultationID, con.PetID, con.ClientID, con.ConsultationDate,
                                p.PetName, CONCAT(c.FirstName,' ',c.LastName) AS ClientName
                         FROM consultations con
                         JOIN pets p ON con.PetID=p.PetID
                         JOIN clients c ON con.ClientID=c.ClientID
                         WHERE con.ConsultationID=$cid AND con.IsDeleted=0 LIMIT 1")->fetch_assoc();
    if ($row) {
        $items = $conn->query("SELECT cs.ServiceID, cs.ServiceName AS Description, cs.Category,
                                      cs.Quantity, cs.UnitPrice, cs.Subtotal
                               FROM consultation_services cs
                               WHERE cs.ConsultationID=$cid")->fetch_all(MYSQLI_ASSOC);
        $row['items'] = $items;
    }
    header('Content-Type: application/json');
    echo json_encode($row ?: null);
    exit;
}

// AJAX: client payment history
if (isset($_GET['client_pay_history'])) {
    $clientID = intval($_GET['client_pay_history']);
    $rows = $conn->query("
        SELECT b.BillingID, b.BillingDate, b.TotalAmount, b.Discount, b.AmountPaid,
               b.PaymentMethod, b.PaymentStatus, b.Notes, b.ConsultationID,
               p.PetName
        FROM billing b
        LEFT JOIN pets p ON b.PetID = p.PetID
        WHERE b.ClientID = $clientID AND b.IsDeleted = 0
        ORDER BY b.BillingDate DESC, b.BillingID DESC
    ")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    exit(json_encode($rows));
}

// AJAX: billing items for view modal
if (isset($_GET['get_items'])) {
    $id    = intval($_GET['id']);
    $items = $conn->query("SELECT bi.Description, bi.Quantity, bi.UnitPrice, bi.Subtotal FROM billing_items bi WHERE bi.BillingID=$id")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    exit(json_encode($items));
}

if (isset($_GET['delete'])) {
    $id    = intval($_GET['delete']);
    $check = $conn->prepare("SELECT PaymentStatus FROM billing WHERE BillingID=? AND IsDeleted=0");
    $check->bind_param("i", $id);
    $check->execute();
    $cr = $check->get_result()->fetch_assoc();
    $check->close();
    if (!$cr) { header("Location: billing.php?err=Invoice not found."); exit; }
    if (in_array($cr['PaymentStatus'], ['Pending', 'Partial'])) {
        header("Location: billing.php?err=Cannot delete invoice — payment is still " . urlencode($cr['PaymentStatus']) . ". Settle the balance first.");
        exit;
    }
    $stmt = $conn->prepare("UPDATE billing SET IsDeleted=1 WHERE BillingID=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: billing.php?msg=Invoice deleted successfully.");
    exit;
}

include('header.php');

$pending       = $conn->query("SELECT COALESCE(SUM(TotalAmount-AmountPaid),0) FROM billing WHERE PaymentStatus!='Paid' AND IsDeleted=0")->fetch_row()[0];
$paidToday     = $conn->query("SELECT COALESCE(SUM(AmountPaid),0) FROM billing WHERE BillingDate=CURDATE() AND IsDeleted=0")->fetch_row()[0];
$mtdRev        = $conn->query("SELECT COALESCE(SUM(AmountPaid),0) FROM billing WHERE MONTH(BillingDate)=MONTH(CURDATE()) AND YEAR(BillingDate)=YEAR(CURDATE()) AND IsDeleted=0")->fetch_row()[0];
$totalInvoices = $conn->query("SELECT COUNT(*) FROM billing WHERE IsDeleted=0")->fetch_row()[0];
$paidCount     = $conn->query("SELECT COUNT(*) FROM billing WHERE PaymentStatus='Paid' AND IsDeleted=0")->fetch_row()[0];
$overdueCount  = $conn->query("SELECT COUNT(*) FROM billing WHERE PaymentStatus!='Paid' AND IsDeleted=0")->fetch_row()[0];

$clientsList = $conn->query("SELECT ClientID,CONCAT(FirstName,' ',LastName) AS Name FROM clients WHERE IsActive=1")->fetch_all(MYSQLI_ASSOC);
$petsList     = $conn->query("SELECT PetID, PetName, ClientID FROM pets WHERE IsActive=1")->fetch_all(MYSQLI_ASSOC);
$consultList  = $conn->query("SELECT con.ConsultationID, con.ConsultationDate, p.PetName,
                                      CONCAT(c.FirstName,' ',c.LastName) AS Client,
                                      COALESCE(SUM(cs.Subtotal),0) AS Total
                               FROM consultations con
                               JOIN pets p    ON con.PetID=p.PetID
                               JOIN clients c ON con.ClientID=c.ClientID
                               LEFT JOIN consultation_services cs ON cs.ConsultationID=con.ConsultationID
                               WHERE con.IsDeleted=0
                               GROUP BY con.ConsultationID
                               ORDER BY con.ConsultationDate DESC")->fetch_all(MYSQLI_ASSOC);

$servicesList = $conn->query("SELECT ServiceID, ServiceName, Category, Price FROM services WHERE IsActive=1 AND IsDeleted=0 ORDER BY ServiceName")->fetch_all(MYSQLI_ASSOC);
$payMethods   = ['Cash','GCash'];

// Date range filter (filters by Billing Date)
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$dateSql  = '';
if ($dateFrom !== '') $dateSql .= " AND DATE(b.BillingDate) >= '" . $conn->real_escape_string($dateFrom) . "'";
if ($dateTo   !== '') $dateSql .= " AND DATE(b.BillingDate) <= '" . $conn->real_escape_string($dateTo) . "'";

// Pending invoice from consultation redirect
$pendingInvoice = null;
if (isset($_GET['from_consult'])) {
    $cid = intval($_GET['from_consult']);
    $pendingInvoice = $conn->query("SELECT b.*, CONCAT(c.FirstName,' ',c.LastName) AS Client, p.PetName
                                    FROM billing b
                                    JOIN clients c ON b.ClientID=c.ClientID
                                    LEFT JOIN pets p ON b.PetID=p.PetID
                                    WHERE b.ConsultationID=$cid AND b.PaymentStatus='Pending' AND b.IsDeleted=0
                                    LIMIT 1")->fetch_assoc();
}
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Billing & Payments</h1>
        <p>Invoices and revenue management</p>
    </div>
    <div class="page-header-actions">
        <form method="get" class="date-filter-form">
            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="date-filter-input" title="Billing date from">
            <span class="date-filter-sep">to</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="date-filter-input" title="Billing date to">
            <button type="submit" class="btn-date-filter" title="Filter by billing date"><i class="bi bi-funnel-fill"></i></button>
            <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
            <a href="billing.php" class="btn-date-clear" title="Clear date filter"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
        <div class="export-dropdown-wrap">
            <button class="btn-export" onclick="toggleExportMenu('exportMenuBilling',this)">
                <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
            </button>
            <div class="export-menu" id="exportMenuBilling">
                <div class="export-menu-label">Export As</div>
                <div class="export-menu-item" onclick="exportCSV('.modern-table','billing_list');document.getElementById('exportMenuBilling').classList.remove('show');">
                    <div class="ei-icon ei-csv"><i class="bi bi-filetype-csv"></i></div> CSV
                </div>
                <div class="export-menu-item" onclick="exportXLSX('.modern-table','billing_list',true);document.getElementById('exportMenuBilling').classList.remove('show');">
                    <div class="ei-icon ei-xls"><i class="bi bi-file-earmark-spreadsheet"></i></div> XLS
                </div>
                <div class="export-menu-item" onclick="exportXLSX('.modern-table','billing_list',false);document.getElementById('exportMenuBilling').classList.remove('show');">
                    <div class="ei-icon ei-xlsx"><i class="bi bi-file-earmark-spreadsheet-fill"></i></div> XLSX
                </div>
                <div class="export-menu-item" onclick="exportDOCX('.modern-table','billing_list','Billing &amp; Invoices');document.getElementById('exportMenuBilling').classList.remove('show');">
                    <div class="ei-icon ei-docx"><i class="bi bi-file-earmark-word"></i></div> DOCX
                </div>
                <div class="export-menu-item" onclick="exportPDF('.modern-table');document.getElementById('exportMenuBilling').classList.remove('show');">
                    <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF
                </div>
            </div>
        </div>
        <button class="btn-main btn-teal" data-bs-toggle="modal" data-bs-target="#billModal" onclick="openAdd()">
            <i class="bi bi-plus-lg"></i> Generate Invoice
        </button>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="alert-modern alert-success-modern"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
<div class="alert-modern" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:12px;padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
    <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($_GET['err']) ?>
</div>
<?php endif; ?>

<?php if ($pendingInvoice): ?>
<div class="alert-modern" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;border-radius:12px;padding:14px 18px;margin-bottom:16px;gap:12px;">
    <i class="bi bi-receipt-cutoff" style="font-size:18px;"></i>
    <div>
        <strong>Pending invoice detected</strong> — INV-<?= str_pad($pendingInvoice['BillingID'],4,'0',STR_PAD_LEFT) ?> for <?= htmlspecialchars($pendingInvoice['Client']) ?> (<?= htmlspecialchars($pendingInvoice['PetName'] ?? '—') ?>), ₱<?= number_format($pendingInvoice['TotalAmount'],2) ?> is awaiting payment.
        <button class="btn-main btn-green ms-3" style="padding:5px 14px;font-size:12px;" onclick='openEdit(<?= htmlspecialchars(json_encode($pendingInvoice)) ?>)'>
            <i class="bi bi-pencil-fill me-1"></i> Update payment
        </button>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#f0fdf4;color:#16a34a;"><i class="bi bi-graph-up-arrow"></i></div><div class="stat-value" style="font-size:20px;">₱<?= number_format($mtdRev,0) ?></div><div class="stat-label">MTD Revenue</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#fffbeb;color:#d97706;"><i class="bi bi-exclamation-circle-fill"></i></div><div class="stat-value" style="font-size:20px;">₱<?= number_format($pending,0) ?></div><div class="stat-label">Outstanding</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#eff6ff;color:#2563eb;"><i class="bi bi-receipt-cutoff"></i></div><div class="stat-value"><?= $paidCount ?></div><div class="stat-label">Paid Invoices</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#fef2f2;color:#dc2626;"><i class="bi bi-clock-history"></i></div><div class="stat-value"><?= $overdueCount ?></div><div class="stat-label">Overdue / Pending</div></div></div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-header-title"><i class="bi bi-receipt-cutoff me-2" style="color:#ef4444;"></i>Invoice Records</span>
        <span style="font-size:12px;color:var(--muted);"><?= $totalInvoices ?> total invoices</span>
    </div>
    <div class="card-body p-0">
        <table class="modern-table">
            <thead><tr><th>Invoice</th><th>Date</th><th>Client</th><th>Pet</th><th>Total</th><th>Paid</th><th>Balance</th><th>Method</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $res = $conn->query("SELECT b.*,CONCAT(c.FirstName,' ',c.LastName) AS Client, p.PetName FROM billing b JOIN clients c ON b.ClientID=c.ClientID LEFT JOIN pets p ON b.PetID=p.PetID WHERE b.IsDeleted=0 $dateSql ORDER BY b.CreatedAt DESC");
            $highlightID = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
            if ($res->num_rows == 0) echo '<tr class="empty-row"><td colspan="10"><i class="bi bi-receipt" style="font-size:28px;display:block;margin-bottom:10px;"></i>No billing records yet.</td></tr>';
            while ($row = $res->fetch_assoc()):
                $balance = $row['TotalAmount'] - $row['AmountPaid'];
                $bClass  = match($row['PaymentStatus']) { 'Paid'=>'badge-paid','Pending'=>'badge-pending','Partial'=>'badge-partial', default=>'badge-pending' };
            ?>
            <tr id="row-bill-<?= $row['BillingID'] ?>" class="<?= ($highlightID && $highlightID==$row['BillingID']) ? 'search-highlight-row' : '' ?>">
                <td>
                    <div style="font-weight:700;font-size:12px;color:var(--teal);">INV-<?= str_pad($row['BillingID'],4,'0',STR_PAD_LEFT) ?></div>
                    <?php if ($row['ConsultationID']): ?><div style="font-size:10px;color:var(--muted);">Ref: <?= 'CON-' . str_pad($row['ConsultationID'], 4, '0', STR_PAD_LEFT) ?></div><?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--muted);"><?= date('M d, Y', strtotime($row['BillingDate'])) ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($row['Client']) ?></td>
                <td style="font-size:13px;color:var(--muted);"><?= htmlspecialchars($row['PetName'] ?? '—') ?></td>
                <td style="font-weight:700;">₱<?= number_format($row['TotalAmount'],2) ?></td>
                <td style="color:var(--green);font-weight:600;">₱<?= number_format($row['AmountPaid'],2) ?></td>
                <td style="<?= $balance>0?'color:var(--red);font-weight:600;':'' ?>">₱<?= number_format($balance,2) ?></td>
                <td><span class="cat-badge"><?= $row['PaymentMethod'] ?></span></td>
                <td><span class="badge-modern <?= $bClass ?>"><?= $row['PaymentStatus'] ?></span></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button class="btn-icon btn-icon-view" onclick="viewBill(<?= htmlspecialchars(json_encode($row)) ?>)" title="View"><i class="bi bi-eye"></i></button>
                        <button class="btn-icon" style="background:rgba(99,102,241,.1);color:#6366f1;" data-client-id="<?= $row['ClientID'] ?>" data-client-name="<?= htmlspecialchars($row['Client'], ENT_QUOTES) ?>" onclick="viewClientPayHistory(this.dataset.clientId, this.dataset.clientName)" title="Client Payment History"><i class="bi bi-clock-history"></i></button>
                        <button class="btn-icon btn-icon-edit" onclick="openEdit(<?= htmlspecialchars(json_encode($row)) ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                        <?php if ($row['PaymentStatus'] === 'Paid'): ?>
                        <a href="billing.php?delete=<?= $row['BillingID'] ?>" class="btn-icon btn-icon-delete" onclick="return confirm('Delete this paid invoice?')"><i class="bi bi-trash"></i></a>
                        <?php else: ?>
                        <button class="btn-icon" disabled title="Cannot delete — invoice is <?= $row['PaymentStatus'] ?>. Settle payment first." style="background:#f1f5f9;color:#cbd5e1;border:1px solid #e2e8f0;cursor:not-allowed;"><i class="bi bi-lock-fill"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- INVOICE MODAL -->
<div class="modal fade" id="billModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" style="max-height:95vh;">
    <div class="modal-content" style="max-height:90vh;display:flex;flex-direction:column;">
      <div class="modal-header" style="flex-shrink:0;">
        <h5 class="modal-title" id="modalTitle"><i class="bi bi-receipt-cutoff me-2" style="color:var(--green);"></i>New invoice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="billForm" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
        <input type="hidden" name="action"      id="formAction" value="add">
        <input type="hidden" name="BillingID"   id="billID">
        <input type="hidden" name="TotalAmount" id="fTotalHidden">

        <div class="modal-body" style="overflow-y:auto;flex:1;">
            <!-- CONSULTATION SOURCE -->
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-clipboard2-pulse me-1" style="color:var(--teal);"></i> Source <span style="font-size:10px;font-weight:400;color:var(--muted);">— type to search consultations, or leave blank for manual entry</span></label>
                <div style="position:relative;">
                    <div style="position:relative;">
                        <i class="bi bi-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none;"></i>
                        <input type="text" id="fConsultSearchInput" class="form-control" placeholder="Search by CON-####, pet name, or client…" autocomplete="off"
                            style="padding-left:34px;padding-right:36px;"
                            oninput="consultSearchInput(this.value)" onfocus="consultSearchInput(this.value)" onblur="setTimeout(hideConsultDrop,200)">
                        <button type="button" id="fConsultClearBtn" onclick="clearConsultSource()" title="Clear"
                            style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:15px;padding:0;line-height:1;">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </div>
                    <div id="consultSearchDrop" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:1060;max-height:240px;overflow-y:auto;">
                        <div id="consultSearchResults"></div>
                        <div id="consultSearchEmpty" style="display:none;padding:12px 14px;font-size:13px;color:var(--muted);text-align:center;"><i class="bi bi-clipboard2-x me-1"></i> No open consultations found</div>
                        <div id="consultSearchLoading" style="display:none;padding:12px 14px;font-size:13px;color:var(--muted);text-align:center;"><span class="spinner-border spinner-border-sm me-2" style="color:var(--teal);"></span>Searching…</div>
                    </div>
                </div>
                <input type="hidden" name="ConsultationID" id="fConsultSource">
            </div>

            <div id="sourceLoadedBanner" class="d-none mb-3 p-3 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0;font-size:13px;">
                <span style="color:#15803d;font-weight:600;"><i class="bi bi-check-circle-fill me-1"></i> <span id="sourceLoadedText">Services loaded</span></span>
                <button type="button" class="btn-main btn-outline ms-3" style="padding:3px 10px;font-size:12px;" onclick="clearConsultSource()">Clear</button>
            </div>

            <!-- CLIENT + PET -->
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Client <span style="color:#ef4444;">*</span></label>
                    <select name="ClientID" id="fClientID" class="form-select" required onchange="filterPets()">
                        <option value="">— Select Client —</option>
                        <?php foreach ($clientsList as $c): ?>
                        <option value="<?= $c['ClientID'] ?>"><?= htmlspecialchars($c['Name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Pet</label>
                    <select name="PetID" id="fPetID" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($petsList as $p): ?>
                        <option value="<?= $p['PetID'] ?>" data-client="<?= $p['ClientID'] ?>"><?= htmlspecialchars($p['PetName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- DATE + METHOD -->
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Billing Date <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="BillingDate" id="fDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Method</label>
                    <select name="PaymentMethod" id="fMethod" class="form-select">
                        <?php foreach ($payMethods as $m): ?><option value="<?= $m ?>"><?= $m ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- SERVICES / ITEMS TABLE -->
            <div class="mb-3">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <label class="form-label mb-0"><i class="bi bi-grid-fill me-1" style="color:var(--indigo);"></i> Services / Items</label>
                    <button type="button" class="btn-main btn-outline" style="padding:5px 12px;font-size:12px;" onclick="addBillRow()">
                        <i class="bi bi-plus-lg me-1"></i> + Add row
                    </button>
                </div>
                <div style="border:1px solid var(--border);border-radius:8px;overflow:hidden;">
                    <table class="modern-table" id="billItemsTable">
                        <thead>
                            <tr>
                                <th style="width:40%;">Service</th>
                                <th style="width:90px;">Unit Price (₱)</th>
                                <th style="width:60px;">Qty</th>
                                <th style="width:90px;">Subtotal</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="billRows">
                            <tr id="noBillRow"><td colspan="5" style="text-align:center;color:var(--muted);padding:14px;font-size:13px;">Select a consultation or add rows manually</td></tr>
                        </tbody>
                    </table>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 16px;background:#f8fafc;border-top:1px solid var(--border);border-radius:0 0 8px 8px;">
                    <span style="font-size:13px;color:var(--muted);">Subtotal</span>
                    <span id="billSubtotal" style="font-weight:700;">₱0.00</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 16px;border-top:1px solid var(--border);">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="font-size:13px;color:var(--muted);">Discount (₱)</span>
                        <input type="number" name="Discount" id="fDiscount" class="form-control form-control-sm" value="0" min="0" step="0.01" style="width:90px;" oninput="recalcBillTotal()">
                    </div>
                    <div style="font-weight:800;font-size:16px;">Total amount <span id="billTotal" style="color:var(--green);">₱0.00</span></div>
                </div>
            </div>

            <!-- PAYMENT -->
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Amount Paid (₱)</label>
                    <input type="number" name="AmountPaid" id="fPaid" class="form-control" step="0.01" value="0" oninput="recalcPayStatus()">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Balance</label>
                    <input type="text" id="fBalance" class="form-control" readonly style="background:#f8fafc;font-weight:700;" value="₱0.00">
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Payment Status</label>
                    <select name="PaymentStatus" id="fStatus" class="form-select" style="background:#f8fafc;">
                        <option value="Pending">Pending</option>
                        <option value="Partial">Partial</option>
                        <option value="Paid">Paid</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Total (computed)</label>
                    <input type="text" id="fTotalDisplay" class="form-control" readonly style="background:#f8fafc;font-weight:700;" value="₱0.00">
                </div>
            </div>

            <!-- UPDATE REASON (edit only) -->
            <div class="mb-3" id="billUpdateReasonField" style="display:none;">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label"><i class="bi bi-pencil-square me-1" style="color:var(--teal);"></i>Reason for Updating <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="UpdateReason" id="fBillUpdateReason" class="form-control" placeholder="e.g. Payment received, amount corrected…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-clock me-1" style="color:var(--muted);"></i>Date Updated</label>
                        <input type="text" id="fBillUpdatedAt" class="form-control" readonly style="background:#f8fafc; color:var(--muted); font-size:13px;">
                    </div>
                </div>
            </div>
            <!-- NOTES -->
            <div class="mb-2">
                <label class="form-label">Notes</label>
                <textarea name="Notes" id="fNotes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
            </div>
        </div>

        <div class="modal-footer" style="flex-shrink:0;">
            <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-main btn-green" id="submitBtn">
                <i class="bi bi-check-lg me-1"></i> Save invoice
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- VIEW INVOICE MODAL -->
<style>
@media print {
    /* ── Reset ── */
    body * { visibility: hidden; }
    body { margin: 0; padding: 0; background: #fff; font-size: 13px; font-family: 'Inter', system-ui, sans-serif; }

    /* ── Invoice / Receipt print ── */
    #invoicePrintArea, #invoicePrintArea * { visibility: visible; }
    #invoicePrintArea {
        position: fixed; top: 0; left: 0; width: 100%;
        padding: 40px; background: #fff; box-sizing: border-box;
    }
    .invoice-print-header, .invoice-print-footer { display: block !important; }

    /* ── Client Payment History print ── */
    #cphPrintArea, #cphPrintArea * { visibility: visible; }
    #cphPrintArea {
        position: fixed; top: 0; left: 0; width: 100%;
        padding: 40px; background: #fff; box-sizing: border-box;
    }
    .cph-print-header, .cph-print-footer { display: block !important; }

    /* ── Shared modal cleanup ── */
    .modal, .modal-dialog, .modal-content {
        box-shadow: none !important; border: none !important;
    }
    .badge-modern { border: 1px solid #ccc !important; }
}
</style>

<!-- CLIENT PAYMENT HISTORY MODAL -->
<div class="modal fade" id="clientPayHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-bottom:1px solid #bbf7d0;display:flex;align-items:flex-start;justify-content:space-between;">
                <div style="flex:1;">
                    <h5 class="modal-title mb-0" style="color:var(--dark);font-weight:700;">
                        <i class="bi bi-clock-history me-2" style="color:#16a34a;"></i>Payment History
                    </h5>
                    <div style="font-size:12px;color:var(--muted);margin-top:2px;">
                        Client: <strong id="cphClientName" style="color:#16a34a;"></strong>
                        &nbsp;·&nbsp; <span id="cphRecordCount"></span>
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;margin-left:12px;">
                    <div class="modal-export-wrap">
                        <button type="button" class="btn-modal-export" onclick="toggleExportMenu('billHistExportMenu',this)">
                            <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="modal-export-menu" id="billHistExportMenu">
                            <div class="export-menu-label">Export As</div>
                            <div class="export-menu-item" onclick="exportCSV('#cphPrintArea table','client_payment_history');document.getElementById('billHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-csv"><i class="bi bi-filetype-csv"></i></div> CSV
                            </div>
                            <div class="export-menu-item" onclick="exportXLSX('#cphPrintArea table','client_payment_history',true);document.getElementById('billHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-xls"><i class="bi bi-file-earmark-spreadsheet"></i></div> XLS
                            </div>
                            <div class="export-menu-item" onclick="exportXLSX('#cphPrintArea table','client_payment_history',false);document.getElementById('billHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-xlsx"><i class="bi bi-file-earmark-spreadsheet-fill"></i></div> XLSX
                            </div>
                            <div class="export-menu-item" onclick="exportDOCX('#cphPrintArea table','client_payment_history','Client Payment History');document.getElementById('billHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-docx"><i class="bi bi-file-earmark-word"></i></div> DOCX
                            </div>
                            <div class="export-menu-item" onclick="window.print();document.getElementById('billHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF / Print
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0" id="cphPrintArea">
                <!-- Print-only header -->
                <div class="cph-print-header" style="display:none;padding:22px 28px 16px;border-bottom:2px dashed #e2e8f0;text-align:center;margin-bottom:16px;">
                    <img src="logo1.png" alt="Heartside Vet" style="width:56px;height:56px;object-fit:contain;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;">
                    <div style="font-size:20px;font-weight:800;color:#1e3a5f;letter-spacing:.4px;line-height:1.2;">Heartside Vet Clinic</div>
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px;margin-top:4px;font-weight:600;">Client Payment History</div>
                    <div style="font-size:13px;color:#334155;margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;">Client: <strong id="cphPrintClientName" style="color:#1e3a5f;"></strong></div>
                </div>

                <div id="cphLoading" class="text-center p-5 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" style="color:#16a34a;"></div> Loading records…
                </div>
                <div id="cphEmpty" class="text-center p-5 text-muted" style="display:none;">
                    <i class="bi bi-receipt" style="font-size:2rem;opacity:.4;display:block;margin-bottom:8px;"></i>
                    No billing records found for this client.
                </div>
                <div id="cphContent" style="display:none;">
                    <table class="modern-table mb-0" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th style="width:100px;">Invoice #</th>
                                <th style="width:100px;">Date</th>
                                <th>Pet</th>
                                <th style="width:90px;">Total</th>
                                <th style="width:90px;">Paid</th>
                                <th style="width:90px;">Balance</th>
                                <th style="width:80px;">Method</th>
                                <th style="width:90px;">Status</th>
                            </tr>
                        </thead>
                        <tbody id="cphRows"></tbody>
                    </table>
                </div>

                <!-- Print-only footer -->
                <div class="cph-print-footer" style="display:none;border-top:2px dashed #e2e8f0;margin-top:20px;padding:12px 18px 4px;text-align:center;">
                    <div style="font-size:10px;color:#94a3b8;letter-spacing:.3px;">Heartside Vet Clinic Management System &nbsp;·&nbsp; <span id="cphPrintDate"></span></div>
                    <div style="font-size:10px;color:#cbd5e1;margin-top:2px;">This document is computer-generated and valid without a signature.</div>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid var(--border);">
                <div id="cphSummary" style="font-size:12px;color:var(--muted);flex:1;text-align:left;"></div>
                <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="viewBillModal" tabindex="-1">
  <div class="modal-dialog" style="max-width:420px;">
    <div class="modal-content" style="border-radius:14px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.18);">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:#fff;border-bottom:1px solid var(--border);">
            <span style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">Invoice Preview</span>
            <div style="display:flex;gap:8px;align-items:center;">
                <div class="modal-export-wrap">
                    <button type="button" class="btn-modal-export" onclick="toggleExportMenu('invoiceExportMenu',this)">
                        <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
                    </button>
                    <div class="modal-export-menu" id="invoiceExportMenu">
                        <div class="export-menu-label">Export As</div>
                        <div class="export-menu-item" onclick="window.print();document.getElementById('invoiceExportMenu').classList.remove('show');">
                            <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF / Print
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size:11px;"></button>
            </div>
        </div>
        <div class="modal-body p-0" id="invoicePrintArea" style="background:#fff;">
            <!-- Print-only branding header -->
            <div class="invoice-print-header" style="display:none;padding:22px 28px 16px;border-bottom:2px dashed #e2e8f0;text-align:center;margin-bottom:0;">
                <img src="logo1.png" alt="Heartside Vet" style="width:56px;height:56px;object-fit:contain;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;">
                <div style="font-size:20px;font-weight:800;color:#1e3a5f;letter-spacing:.4px;line-height:1.2;">Heartside Vet Clinic</div>
                <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px;margin-top:4px;font-weight:600;">Official Receipt</div>
            </div>
            <!-- Screen receipt header (also visible in print, supplementary) -->
            <div style="padding:20px 20px 14px;text-align:center;border-bottom:2px dashed #e2e8f0;">
                <img src="logo1.png" alt="Heartside Vet" style="width:52px;height:52px;object-fit:contain;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;">
                <div style="font-size:17px;font-weight:800;color:#1e3a5f;letter-spacing:.3px;">Heartside Vet</div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px;text-transform:uppercase;letter-spacing:.8px;">Official Receipt</div>
            </div>
            <div style="padding:12px 20px;display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #f1f5f9;">
                <div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:2px;">Invoice No.</div>
                    <div id="vBillID" style="font-size:16px;font-weight:800;color:var(--teal);">—</div>
                    <div id="vBillConsult" style="font-size:10px;color:var(--muted);margin-top:1px;"></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:4px;">Date</div>
                    <div id="vBillDate" style="font-size:12px;font-weight:600;">—</div>
                    <div id="vBillStatus" style="margin-top:5px;">—</div>
                </div>
            </div>
            <div style="padding:10px 20px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;gap:10px;">
                <div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:3px;">Billed To</div>
                    <div id="vBillClient" style="font-size:13px;font-weight:700;color:var(--dark);">—</div>
                    <div style="font-size:11px;color:var(--muted);margin-top:1px;">Patient: <span id="vBillPet" style="font-weight:600;color:var(--dark);">—</span></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:3px;">Payment</div>
                    <div id="vBillMethod" style="font-size:12px;font-weight:600;">—</div>
                </div>
            </div>
            <div style="padding:10px 20px 0;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Services</div>
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="padding:6px 8px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);">Item</th>
                            <th style="padding:6px 4px;text-align:center;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);width:32px;">Qty</th>
                            <th style="padding:6px 4px;text-align:right;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);width:80px;">Price</th>
                            <th style="padding:6px 8px;text-align:right;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);width:80px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="vBillItems">
                        <tr><td colspan="4" style="padding:14px;text-align:center;color:var(--muted);font-size:12px;">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
            <div style="padding:8px 20px 12px;display:flex;justify-content:flex-end;">
                <div style="min-width:200px;">
                    <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;border-top:1px solid #e2e8f0;">
                        <span style="color:var(--muted);">Subtotal</span>
                        <span id="vBillSubtotal" style="font-weight:600;">—</span>
                    </div>
                    <div id="vBillDiscountRow" style="display:none;justify-content:space-between;padding:4px 0;font-size:12px;border-top:1px solid #f1f5f9;">
                        <span style="color:var(--muted);">Discount</span>
                        <span id="vBillDiscount" style="color:#ef4444;font-weight:600;">—</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:7px 0;border-top:2px solid var(--dark);border-bottom:2px solid var(--dark);margin:4px 0;">
                        <span style="font-size:13px;font-weight:800;">TOTAL</span>
                        <span id="vBillTotal" style="font-size:14px;font-weight:800;color:var(--teal);">—</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;">
                        <span style="color:var(--muted);">Amount Paid</span>
                        <span id="vBillPaid" style="font-weight:600;color:var(--green);">—</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;">
                        <span style="color:var(--muted);">Balance Due</span>
                        <span id="vBillBalance" style="font-weight:700;">—</span>
                    </div>
                </div>
            </div>
            <div id="vBillNotesWrap" style="display:none;margin:0 20px 12px;padding:8px 12px;background:#f8fafc;border-radius:6px;border:1px solid var(--border);">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:3px;">Notes</div>
                <div id="vBillNotes" style="font-size:12px;color:var(--dark);">—</div>
            </div>
            <div style="border-top:2px dashed #e2e8f0;margin:0 20px;padding:12px 0;text-align:center;">
                <div style="font-size:11px;font-weight:700;color:#1e3a5f;">Heartside Vet Clinic</div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px;">Thank you for trusting us with your pet's care.</div>
            </div>
            <!-- Print-only footer -->
            <div class="invoice-print-footer" style="display:none;border-top:1px solid #f1f5f9;margin:8px 24px 0;padding:10px 0 4px;text-align:center;">
                <div style="font-size:10px;color:#94a3b8;letter-spacing:.3px;">Heartside Vet Clinic Management System &nbsp;·&nbsp; <span id="invoicePrintDate"></span></div>
                <div style="font-size:10px;color:#cbd5e1;margin-top:2px;">This document is computer-generated and valid without a signature.</div>
            </div>
            <div id="vBillUpdateWrap" style="display:none;margin:0 20px 12px;padding:8px 12px;background:#fffbeb;border-radius:6px;border:1px solid #fde68a;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#92400e;margin-bottom:4px;"><i class="bi bi-pencil-square me-1"></i>Last Updated</div>
                <div style="font-size:11px;color:#78350f;">Updated on <span id="vBillUpdated" style="font-weight:600;">—</span></div>
                <div style="font-size:11px;color:#78350f;margin-top:2px;">Reason: <span id="vBillUpdateReason" style="font-weight:600;">—</span></div>
            </div>
        </div>
        <div class="modal-footer" style="padding:10px 16px;border-top:1px solid var(--border);">
            <button type="button" class="btn-main btn-outline btn-sm" data-bs-dismiss="modal" style="font-size:12px;">Close</button>
        </div>
    </div>
  </div>
</div>

<script>
const ALL_SVCS = <?= json_encode($servicesList) ?>;
const svcMapB  = {};
ALL_SVCS.forEach(s => svcMapB[s.ServiceID] = s);
let billRowIdx    = 0;
let currentTotalB = 0;

function buildSvcOptions(selectedID) {
    let opts = '<option value="">— custom —</option>';
    ALL_SVCS.forEach(s => {
        opts += `<option value="${s.ServiceID}" data-price="${s.Price}" ${s.ServiceID == selectedID ? 'selected' : ''}>${s.ServiceName}</option>`;
    });
    return opts;
}

function addBillRow(svcID = '', price = 0, qty = 1, desc = '') {
    const idx   = billRowIdx++;
    const tbody = document.getElementById('billRows');
    const noRow = document.getElementById('noBillRow');
    if (noRow) noRow.remove();
    const pr  = svcID && svcMapB[svcID] ? svcMapB[svcID].Price : price;
    const sub = (qty * pr).toFixed(2);
    const tr  = document.createElement('tr');
    tr.id = 'bill-row-' + idx;
    tr.innerHTML = `
        <td>
            <select name="svc_id[]" class="form-select form-select-sm" onchange="onBillSvcChange(this, ${idx})">
                ${buildSvcOptions(svcID)}
            </select>
            <input type="hidden" name="svc_desc[]" id="bdesc_${idx}" value="${desc || (svcID && svcMapB[svcID] ? svcMapB[svcID].ServiceName : '')}">
        </td>
        <td><input type="number" name="svc_price[]" id="bprice_${idx}" class="form-control form-control-sm" value="${pr}" step="0.01" oninput="recalcBillRow(${idx})"></td>
        <td><input type="number" name="svc_qty[]"   id="bqty_${idx}"   class="form-control form-control-sm" value="${qty}" min="1" oninput="recalcBillRow(${idx})"></td>
        <td><span id="bsub_${idx}" style="font-weight:600;">₱${sub}</span></td>
        <td><button type="button" class="btn-icon btn-icon-delete" style="width:26px;height:26px;font-size:11px;" onclick="removeBillRow(${idx})"><i class="bi bi-trash"></i></button></td>`;
    tbody.appendChild(tr);
    recalcBillTotal();
}

function onBillSvcChange(sel, idx) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('bprice_' + idx).value = parseFloat(opt.dataset.price || 0);
    document.getElementById('bdesc_'  + idx).value = opt.textContent.trim();
    recalcBillRow(idx);
}

function recalcBillRow(idx) {
    const q = parseFloat(document.getElementById('bqty_'   + idx).value || 0);
    const p = parseFloat(document.getElementById('bprice_' + idx).value || 0);
    document.getElementById('bsub_' + idx).textContent = '₱' + (q * p).toFixed(2);
    recalcBillTotal();
}

function removeBillRow(idx) {
    const r = document.getElementById('bill-row-' + idx);
    if (r) r.remove();
    if (!document.querySelectorAll('#billRows tr[id^="bill-row-"]').length) {
        const noRow = document.createElement('tr');
        noRow.id = 'noBillRow';
        noRow.innerHTML = '<td colspan="5" style="text-align:center;color:var(--muted);padding:14px;font-size:13px;">Select a consultation or add rows manually</td>';
        document.getElementById('billRows').appendChild(noRow);
    }
    recalcBillTotal();
}

function recalcBillTotal() {
    let sub = 0;
    document.querySelectorAll('#billRows tr[id^="bill-row-"]').forEach(tr => {
        const idx = tr.id.replace('bill-row-', '');
        const q   = parseFloat(document.getElementById('bqty_'   + idx)?.value || 0);
        const p   = parseFloat(document.getElementById('bprice_' + idx)?.value || 0);
        sub += q * p;
    });
    const disc  = parseFloat(document.getElementById('fDiscount').value || 0);
    const total = Math.max(0, sub - disc);
    currentTotalB = total;
    document.getElementById('billSubtotal').textContent = '₱' + sub.toFixed(2);
    document.getElementById('billTotal').textContent    = '₱' + total.toFixed(2);
    document.getElementById('fTotalDisplay').value      = '₱' + total.toFixed(2);
    document.getElementById('fTotalHidden').value       = total.toFixed(2);
    recalcPayStatus();
}

function recalcPayStatus() {
    const paid = parseFloat(document.getElementById('fPaid').value || 0);
    const bal  = Math.max(0, currentTotalB - paid);
    document.getElementById('fBalance').value = '₱' + bal.toFixed(2);
    let st = 'Pending';
    if (paid >= currentTotalB && currentTotalB > 0) st = 'Paid';
    else if (paid > 0) st = 'Partial';
    document.getElementById('fStatus').value = st;
}

function clearBillRows() {
    document.getElementById('billRows').innerHTML = '<tr id="noBillRow"><td colspan="5" style="text-align:center;color:var(--muted);padding:14px;font-size:13px;">Select a consultation or add rows manually</td></tr>';
    recalcBillTotal();
}

    let consultSearchTimer = null;
    function consultSearchInput(val) {
        clearTimeout(consultSearchTimer);
        showConsultDrop();
        document.getElementById('consultSearchLoading').style.display = '';
        document.getElementById('consultSearchEmpty').style.display = 'none';
        consultSearchTimer = setTimeout(() => doConsultSearch(val), 220);
    }
    async function doConsultSearch(q) {
        const res  = await fetch(`billing.php?search_consults=1&q=${encodeURIComponent(q)}`);
        const rows = await res.json();
        document.getElementById('consultSearchLoading').style.display = 'none';
        const el = document.getElementById('consultSearchResults');
        if (!rows.length) { el.innerHTML=''; document.getElementById('consultSearchEmpty').style.display=''; return; }
        document.getElementById('consultSearchEmpty').style.display = 'none';
        el.innerHTML = rows.map(c => {
            const conID  = 'CON-' + String(c.ConsultationID).padStart(4,'0');
            const date   = c.ConsultationDate ? c.ConsultationDate.substring(0,10) : '';
            const total  = '₱' + parseFloat(c.Total||0).toLocaleString('en-PH',{minimumFractionDigits:2});
            return `<div class="consult-search-item" style="padding:9px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:13px;display:flex;align-items:center;gap:10px;"
                        onmousedown="selectConsultItem(${c.ConsultationID}, '${conID}')">
                        <div style="min-width:72px;font-weight:700;color:#16a34a;font-size:12px;">${conID}</div>
                        <div>
                            <div style="font-weight:600;color:var(--dark);">${c.PetName} <span style="font-weight:400;color:var(--muted);">· ${c.Client}</span></div>
                            <div style="font-size:11px;color:var(--muted);">${date} · ${total}</div>
                        </div>
                    </div>`;
        }).join('');
    }
    function showConsultDrop() { document.getElementById('consultSearchDrop').style.display=''; }
    function hideConsultDrop() { document.getElementById('consultSearchDrop').style.display='none'; }
    function selectConsultItem(id, label) {
        document.getElementById('fConsultSearchInput').value = label;
        document.getElementById('fConsultClearBtn').style.display = '';
        document.getElementById('consultSearchResults').innerHTML = '';
        hideConsultDrop();
        onConsultChange(id);
    }
    // Hover highlight
    document.addEventListener('mouseover', e => {
        const item = e.target.closest('.consult-search-item');
        if (item) item.style.background = '#f0fdf4';
    });
    document.addEventListener('mouseout', e => {
        const item = e.target.closest('.consult-search-item');
        if (item) item.style.background = '';
    });

    async function onConsultChange(cid) {
        if (!cid) { clearConsultSource(); return; }
        const res  = await fetch(`billing.php?load_consult=1&id=${cid}`);
        const data = await res.json();
        if (!data) { clearConsultSource(); return; }
        document.getElementById('fConsultSource').value = cid;
        document.getElementById('fClientID').value = data.ClientID;
        filterPets(data.PetID);
        document.getElementById('fDate').value = data.ConsultationDate ? data.ConsultationDate.substring(0, 10) : '<?= date('Y-m-d') ?>';
        document.getElementById('sourceLoadedText').textContent = `Services loaded from CON-${String(cid).padStart(4,'0')}`;
        document.getElementById('sourceLoadedBanner').classList.remove('d-none');
        clearBillRows();
        if (data.items && data.items.length) {
            data.items.forEach(it => addBillRow(it.ServiceID, it.UnitPrice, it.Quantity, it.Description));
        }
    }

    function clearConsultSource() {
        document.getElementById('fConsultSource').value = '';
        document.getElementById('fConsultSearchInput').value = '';
        document.getElementById('fConsultClearBtn').style.display = 'none';
        document.getElementById('sourceLoadedBanner').classList.add('d-none');
        clearBillRows();
    }

function filterPets(selectPetID = null) {
    const cid = document.getElementById('fClientID').value;
    const sel = document.getElementById('fPetID');
    for (let opt of sel.options) {
        if (!opt.value) { opt.hidden = false; continue; }
        opt.hidden = cid ? (opt.dataset.client != cid) : false;
    }
    if (selectPetID) sel.value = selectPetID;
}

const statusClasses = { Paid: 'badge-paid', Pending: 'badge-pending', Partial: 'badge-partial' };

async function viewBill(r) {
    const fmt  = d => d ? new Date(d).toLocaleDateString('en-US',{month:'short',day:'2-digit',year:'numeric'}) : '—';
    const peso = v => '₱' + parseFloat(v||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
    const balance  = parseFloat(r.TotalAmount) - parseFloat(r.AmountPaid);
    const discount = parseFloat(r.Discount || 0);

    document.getElementById('vBillID').textContent       = 'INV-' + String(r.BillingID).padStart(4,'0');
    document.getElementById('vBillDate').textContent     = fmt(r.BillingDate);
    document.getElementById('vBillClient').textContent   = r.Client     || '—';
    document.getElementById('vBillPet').textContent      = r.PetName    || '—';
    document.getElementById('vBillMethod').textContent   = r.PaymentMethod || '—';
    document.getElementById('vBillTotal').textContent    = peso(r.TotalAmount);
    document.getElementById('vBillSubtotal').textContent = peso(parseFloat(r.TotalAmount) + discount);
    document.getElementById('vBillPaid').textContent     = peso(r.AmountPaid);
    document.getElementById('vBillBalance').textContent  = peso(balance);
    document.getElementById('vBillBalance').style.color  = balance > 0 ? 'var(--red)' : 'var(--green)';
    document.getElementById('vBillStatus').innerHTML     = `<span class="badge-modern ${statusClasses[r.PaymentStatus]||'badge-pending'}">${r.PaymentStatus}</span>`;
    document.getElementById('vBillConsult').textContent  = r.ConsultationID ? `Consult #${r.ConsultationID}` : '';

    const discRow = document.getElementById('vBillDiscountRow');
    if (discount > 0) {
        document.getElementById('vBillDiscount').textContent = '− ' + peso(discount);
        discRow.style.display = 'flex';
    } else {
        discRow.style.display = 'none';
    }

    const notesWrap = document.getElementById('vBillNotesWrap');
    if (r.Notes && r.Notes.trim()) {
        document.getElementById('vBillNotes').textContent = r.Notes;
        notesWrap.style.display = '';
    } else {
        notesWrap.style.display = 'none';
    }

    const tbody = document.getElementById('vBillItems');
    tbody.innerHTML = '<tr><td colspan="4" style="padding:14px;text-align:center;color:var(--muted);font-size:12px;">Loading…</td></tr>';
    // Update history
    const billUpdWrap = document.getElementById('vBillUpdateWrap');
    if (billUpdWrap) {
        if (r.UpdatedAt && r.UpdateReason) {
            document.getElementById('vBillUpdated').textContent      = new Date(r.UpdatedAt).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
            document.getElementById('vBillUpdateReason').textContent = r.UpdateReason;
            billUpdWrap.style.display = '';
        } else {
            billUpdWrap.style.display = 'none';
        }
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('viewBillModal')).show();

    try {
        const res   = await fetch(`billing.php?get_items=1&id=${r.BillingID}`);
        const items = await res.json();
        if (!items || !items.length) {
            tbody.innerHTML = '<tr><td colspan="4" style="padding:14px;text-align:center;color:var(--muted);font-size:12px;">No line items recorded.</td></tr>';
            return;
        }
        tbody.innerHTML = items.map(it => `
            <tr style="border-top:1px solid #f1f5f9;">
                <td style="padding:7px 8px;font-size:12px;">${it.Description || '—'}</td>
                <td style="padding:7px 4px;text-align:center;font-size:12px;">${it.Quantity}</td>
                <td style="padding:7px 4px;text-align:right;font-size:12px;">₱${parseFloat(it.UnitPrice).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
                <td style="padding:7px 8px;text-align:right;font-size:12px;font-weight:600;">₱${parseFloat(it.Subtotal).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
            </tr>`).join('');
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="4" style="padding:14px;text-align:center;color:var(--muted);">Could not load items.</td></tr>';
    }
}

function printInvoiceCard() {
    const el = document.getElementById('invoicePrintDate');
    if (el) el.textContent = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    window.print();
}

function printClientPayHistory() {
    document.getElementById('cphPrintClientName').textContent = document.getElementById('cphClientName').textContent;
    document.getElementById('cphPrintDate').textContent       = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    window.print();
}

function viewClientPayHistory(clientID, clientName) {
    document.getElementById('cphClientName').textContent   = clientName;
    document.getElementById('cphRecordCount').textContent  = '';
    document.getElementById('cphSummary').textContent      = '';
    document.getElementById('cphLoading').style.display    = '';
    document.getElementById('cphContent').style.display    = 'none';
    document.getElementById('cphEmpty').style.display      = 'none';
    document.getElementById('cphRows').innerHTML           = '';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('clientPayHistoryModal')).show();

    const statusCls = { Paid:'badge-paid', Pending:'badge-pending', Partial:'badge-partial' };
    const peso = v => '₱' + parseFloat(v||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});

    fetch(`billing.php?client_pay_history=${clientID}`)
        .then(r => r.json())
        .then(rows => {
            document.getElementById('cphLoading').style.display = 'none';
            if (!rows.length) { document.getElementById('cphEmpty').style.display = ''; return; }

            document.getElementById('cphRecordCount').textContent =
                rows.length + ' invoice' + (rows.length !== 1 ? 's' : '') + ' on file';

            const tbody = document.getElementById('cphRows');
            let grandPaid = 0, grandTotal = 0;
            rows.forEach((r, i) => {
                const date    = r.BillingDate ? new Date(r.BillingDate).toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'}) : '—';
                const total   = parseFloat(r.TotalAmount || 0);
                const paid    = parseFloat(r.AmountPaid  || 0);
                const balance = total - paid;
                grandTotal += total;
                grandPaid  += paid;
                const badge = statusCls[r.PaymentStatus] || 'badge-pending';
                tbody.insertAdjacentHTML('beforeend', `
                    <tr style="${i===0?'background:#f0fdf4;':''}">
                        <td><span style="font-weight:700;font-size:12px;color:var(--teal);">INV-${String(r.BillingID).padStart(4,'0')}</span>${r.ConsultationID?`<br><span style="font-size:10px;color:var(--muted);">Ref: CON-${String(r.ConsultationID).padStart(4,'0')}</span>`:''}${i===0?'<br><span style="font-size:9px;color:#16a34a;font-weight:700;">LATEST</span>':''}</td>
                        <td style="font-weight:600;white-space:nowrap;">${date}</td>
                        <td style="font-size:12px;">${r.PetName || '—'}</td>
                        <td style="font-weight:700;">${peso(total)}</td>
                        <td style="color:var(--green);font-weight:600;">${peso(paid)}</td>
                        <td style="font-weight:700;${balance>0?'color:var(--red);':''}">${peso(balance)}</td>
                        <td><span class="cat-badge">${r.PaymentMethod || '—'}</span></td>
                        <td><span class="badge-modern ${badge}">${r.PaymentStatus}</span></td>
                    </tr>`);
            });

            const grandBalance = grandTotal - grandPaid;
            document.getElementById('cphSummary').innerHTML =
                `${rows.length} invoice${rows.length!==1?'s':''} &nbsp;·&nbsp; Total billed: <strong style="color:var(--teal);">${peso(grandTotal)}</strong> &nbsp;·&nbsp; Total paid: <strong style="color:var(--green);">${peso(grandPaid)}</strong> &nbsp;·&nbsp; Balance: <strong style="${grandBalance>0?'color:var(--red)':'color:var(--green)'}">${peso(grandBalance)}</strong>`;
            document.getElementById('cphContent').style.display = '';
        })
        .catch(() => {
            document.getElementById('cphLoading').style.display = 'none';
            document.getElementById('cphEmpty').style.display   = '';
            document.getElementById('cphEmpty').innerHTML       =
                '<i class="bi bi-exclamation-circle text-danger" style="font-size:2rem;display:block;margin-bottom:8px;"></i>Failed to load records.';
        });
}

function openAdd() {
    document.getElementById('formAction').value  = 'add';
    document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Save invoice';
    document.getElementById('billForm').reset();
    document.getElementById('billID').value    = '';
    document.getElementById('fDate').value     = '<?= date('Y-m-d') ?>';
    document.getElementById('fDiscount').value = '0';
    document.getElementById('fConsultSource').value = '';
    document.getElementById('fConsultSearchInput').value = '';
    document.getElementById('fConsultClearBtn').style.display = 'none';
    document.getElementById('sourceLoadedBanner').classList.add('d-none');
    document.getElementById('billUpdateReasonField').style.display = 'none';
    document.getElementById('fBillUpdateReason').required = false;
    clearBillRows();
    currentTotalB = 0;
}

function openEdit(row) {
    document.getElementById('modalTitle').innerHTML = '</i>Edit Invoice #' + row.BillingID;
    document.getElementById('formAction').value  = 'update';
    document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Update Invoice';
    document.getElementById('billID').value     = row.BillingID;
    document.getElementById('fClientID').value  = row.ClientID;
    filterPets(row.PetID);
    document.getElementById('fDate').value      = row.BillingDate ? row.BillingDate.substring(0,10) : '';
    document.getElementById('fMethod').value    = row.PaymentMethod;
    document.getElementById('fNotes').value     = row.Notes   ?? '';
    document.getElementById('fDiscount').value  = row.Discount || '0';
    document.getElementById('fPaid').value      = row.AmountPaid;

    // Prefill the consultation source search input and show banner when linked
    const consultHidden = document.getElementById('fConsultSource');
    const consultInp    = document.getElementById('fConsultSearchInput');
    const consultClear  = document.getElementById('fConsultClearBtn');
    if (row.ConsultationID) {
        consultHidden.value = row.ConsultationID;
        consultInp.value    = 'CON-' + String(row.ConsultationID).padStart(4, '0') + ' (linked)';
        consultClear.style.display = '';
        document.getElementById('sourceLoadedText').textContent = 'Linked to CON-' + String(row.ConsultationID).padStart(4, '0');
        document.getElementById('sourceLoadedBanner').classList.remove('d-none');
    } else {
        consultHidden.value = '';
        consultInp.value    = '';
        consultClear.style.display = 'none';
        document.getElementById('sourceLoadedBanner').classList.add('d-none');
    }

    clearBillRows();
    currentTotalB = parseFloat(row.TotalAmount);
    document.getElementById('billSubtotal').textContent = '₱' + parseFloat(row.TotalAmount).toFixed(2);
    document.getElementById('billTotal').textContent    = '₱' + parseFloat(row.TotalAmount).toFixed(2);
    document.getElementById('fTotalDisplay').value      = '₱' + parseFloat(row.TotalAmount).toFixed(2);
    document.getElementById('fTotalHidden').value       = parseFloat(row.TotalAmount).toFixed(2);
    recalcPayStatus();

    // Load billing items via the built-in AJAX endpoint
    fetch(`billing.php?get_items=1&id=${row.BillingID}`)
        .then(r => r.json())
        .then(items => {
            if (items && items.length) items.forEach(it => addBillRow(it.ServiceID, it.UnitPrice, it.Quantity, it.Description));
        }).catch(() => {});

    // Show update reason fields
    document.getElementById('billUpdateReasonField').style.display = 'block';
    document.getElementById('fBillUpdateReason').value   = '';
    document.getElementById('fBillUpdateReason').required = true;
    document.getElementById('fBillUpdatedAt').value = new Date().toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('billModal')).show();
}
</script>
<?php include('footer.php'); ?>