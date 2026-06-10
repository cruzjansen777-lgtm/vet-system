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
            $res = $conn->query("SELECT b.*,CONCAT(c.FirstName,' ',c.LastName) AS Client, p.PetName FROM billing b JOIN clients c ON b.ClientID=c.ClientID LEFT JOIN pets p ON b.PetID=p.PetID WHERE b.IsDeleted=0 ORDER BY b.CreatedAt DESC");
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
                <label class="form-label"><i class="bi bi-clipboard2-pulse me-1" style="color:var(--teal);"></i> Source <span style="font-size:10px;font-weight:400;color:var(--muted);">— select completed consultation to load services</span></label>
                <select id="fConsultSource" name="ConsultationID" class="form-select" onchange="onConsultChange(this)">
                    <option value="">— manual entry —</option>
                    <?php foreach ($consultList as $con): ?>
                    <option value="<?= $con['ConsultationID'] ?>">
                        CON-<?= str_pad($con['ConsultationID'], 4, '0', STR_PAD_LEFT) ?> · <?= substr($con['ConsultationDate'], 0, 10) ?> · <?= htmlspecialchars($con['PetName']) ?> / <?= htmlspecialchars($con['Client']) ?> — ₱<?= number_format($con['Total'],2) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
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
    body * { visibility: hidden; }
    #invoicePrintArea, #invoicePrintArea * { visibility: visible; }
    #invoicePrintArea { position: fixed; top: 0; left: 0; width: 100%; padding: 32px; background: #fff; }
    #cphPrintArea, #cphPrintArea * { visibility: visible; }
    #cphPrintArea { position: fixed; top: 0; left: 0; width: 100%; padding: 32px; }
    .cph-print-header, .cph-print-footer { display: block !important; }
    .modal, .modal-dialog, .modal-content { box-shadow: none !important; border: none !important; }
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
                    <button type="button" onclick="printClientPayHistory()" style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:5px 12px;border-radius:6px;border:1px solid var(--border);background:#fff;color:var(--dark);cursor:pointer;">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0" id="cphPrintArea">
                <!-- Print-only header -->
                <div class="cph-print-header" style="display:none;padding:18px 24px 14px;border-bottom:2px dashed #e2e8f0;text-align:center;margin-bottom:16px;">
                    <img src="logo1.png" alt="Heartside Vet" style="width:48px;height:48px;object-fit:contain;margin-bottom:6px;display:block;margin-left:auto;margin-right:auto;">
                    <div style="font-size:18px;font-weight:800;color:#1e3a5f;letter-spacing:.3px;">Heartside Vet Clinic</div>
                    <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-top:2px;">Client Payment History</div>
                    <div style="font-size:13px;color:#334155;margin-top:6px;">Client: <strong id="cphPrintClientName"></strong></div>
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
                <div class="cph-print-footer" style="display:none;border-top:2px dashed #e2e8f0;margin-top:20px;padding:10px 18px;text-align:center;">
                    <div style="font-size:10px;color:#94a3b8;">Printed from Heartside Vet Clinic Management Portal &nbsp;·&nbsp; <span id="cphPrintDate"></span></div>
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
                <button type="button" onclick="window.print()" style="display:flex;align-items:center;gap:5px;font-size:11px;font-weight:600;padding:4px 10px;border-radius:6px;border:1px solid var(--border);background:#f8fafc;color:var(--dark);cursor:pointer;">
                    <i class="bi bi-printer"></i> Print
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size:11px;"></button>
            </div>
        </div>
        <div class="modal-body p-0" id="invoicePrintArea" style="background:#fff;">
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
                <div style="font-size:11px;font-weight:700;color:#1e3a5f;">Heartside Vet</div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px;">Thank you for trusting us with your pet's care.</div>
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

async function onConsultChange(sel) {
    const cid = sel.value;
    if (!cid) { clearConsultSource(); return; }
    const res  = await fetch(`billing.php?load_consult=1&id=${cid}`);
    const data = await res.json();
    if (!data) { clearConsultSource(); return; }
    document.getElementById('fClientID').value = data.ClientID;
    filterPets(data.PetID);
    document.getElementById('fDate').value = data.ConsultationDate ? data.ConsultationDate.substring(0, 10) : '<?= date('Y-m-d') ?>';
    document.getElementById('sourceLoadedText').textContent = `Services loaded from Consultation #${data.ConsultationID}`;
    document.getElementById('sourceLoadedBanner').classList.remove('d-none');
    clearBillRows();
    if (data.items && data.items.length) {
        data.items.forEach(it => addBillRow(it.ServiceID, it.UnitPrice, it.Quantity, it.Description));
    }
}

function clearConsultSource() {
    document.getElementById('fConsultSource').value = '';
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

    // Prefill the consultation source dropdown and show banner when linked
    const consultSel = document.getElementById('fConsultSource');
    if (row.ConsultationID) {
        consultSel.value = row.ConsultationID;
        // If the option is missing (edge case), add a ghost entry
        if (consultSel.value != row.ConsultationID) {
            const ghost = document.createElement('option');
            ghost.value = row.ConsultationID;
            ghost.textContent = 'CON-' + String(row.ConsultationID).padStart(4, '0') + ' (linked)';
            consultSel.appendChild(ghost);
            consultSel.value = row.ConsultationID;
        }
        document.getElementById('sourceLoadedText').textContent = 'Linked to Consultation #' + row.ConsultationID;
        document.getElementById('sourceLoadedBanner').classList.remove('d-none');
    } else {
        consultSel.value = '';
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