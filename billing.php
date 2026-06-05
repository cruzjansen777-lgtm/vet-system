<?php
include('dbconnect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $total   = floatval($_POST['TotalAmount']);
    $paid    = floatval($_POST['AmountPaid']);
    $method  = $_POST['PaymentMethod'];
    $status  = $_POST['PaymentStatus'];
    $notes   = $_POST['Notes'];

    if ($action === 'add') {
        $cid  = intval($_POST['ClientID']);
        $pid  = $_POST['PetID'] ? intval($_POST['PetID']) : null;
        $date = $_POST['BillingDate'];
        $stmt = $conn->prepare("INSERT INTO billing (ClientID,PetID,BillingDate,TotalAmount,AmountPaid,PaymentMethod,PaymentStatus,Notes) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iisddsss", $cid,$pid,$date,$total,$paid,$method,$status,$notes);
        $stmt->execute(); $stmt->close();
        header("Location: billing.php?msg=Bill added successfully."); exit;
    }
    if ($action === 'update') {
        $id  = intval($_POST['BillingID']);
        $cid = intval($_POST['ClientID']);
        $pid = $_POST['PetID'] ? intval($_POST['PetID']) : null;
        $date = $_POST['BillingDate'];
        $stmt = $conn->prepare("UPDATE billing SET ClientID=?,PetID=?,BillingDate=?,TotalAmount=?,AmountPaid=?,PaymentMethod=?,PaymentStatus=?,Notes=? WHERE BillingID=?");
        $stmt->bind_param("iisddsssi", $cid,$pid,$date,$total,$paid,$method,$status,$notes,$id);
        $stmt->execute(); $stmt->close();
        header("Location: billing.php?msg=Bill updated."); exit;
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Block deletion if invoice is still Pending or Partial
    $check = $conn->prepare("SELECT PaymentStatus FROM billing WHERE BillingID=? AND IsDeleted=0");
    $check->bind_param("i", $id);
    $check->execute();
    $checkResult = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$checkResult) {
        header("Location: billing.php?err=Invoice not found."); exit;
    }
    if (in_array($checkResult['PaymentStatus'], ['Pending', 'Partial'])) {
        header("Location: billing.php?err=Cannot delete invoice — payment is still " . urlencode($checkResult['PaymentStatus']) . ". Settle the balance first."); exit;
    }

    $stmt = $conn->prepare("UPDATE billing SET IsDeleted=1 WHERE BillingID=?");
    $stmt->bind_param("i", $id);
    $stmt->execute(); $stmt->close();
    header("Location: billing.php?msg=Invoice deleted successfully."); exit;
} 

include('header.php');

// Filter statistics out to exclude soft-deleted data records
$pending   = $conn->query("SELECT COALESCE(SUM(TotalAmount-AmountPaid),0) FROM billing WHERE PaymentStatus!='Paid' AND IsDeleted=0")->fetch_row()[0];
$paidToday = $conn->query("SELECT COALESCE(SUM(AmountPaid),0) FROM billing WHERE BillingDate=CURDATE() AND IsDeleted=0")->fetch_row()[0];
$mtdRev    = $conn->query("SELECT COALESCE(SUM(AmountPaid),0) FROM billing WHERE MONTH(BillingDate)=MONTH(CURDATE()) AND YEAR(BillingDate)=YEAR(CURDATE()) AND IsDeleted=0")->fetch_row()[0];
$totalInvoices = $conn->query("SELECT COUNT(*) FROM billing WHERE IsDeleted=0")->fetch_row()[0];
$paidCount = $conn->query("SELECT COUNT(*) FROM billing WHERE PaymentStatus='Paid' AND IsDeleted=0")->fetch_row()[0];
$overdueCount = $conn->query("SELECT COUNT(*) FROM billing WHERE PaymentStatus!='Paid' AND IsDeleted=0")->fetch_row()[0];

$clientsRes = $conn->query("SELECT ClientID,CONCAT(FirstName,' ',LastName) AS Name FROM clients WHERE IsActive=1");
$clientsList = [];
while ($c = $clientsRes->fetch_assoc()) $clientsList[] = $c;

$petsRes = $conn->query("SELECT PetID,PetName FROM pets WHERE IsActive=1");
$petsList = [];
while ($p = $petsRes->fetch_assoc()) $petsList[] = $p;

$payMethods  = ['Cash','Card','GCash','PayMaya','Bank Transfer','Other'];
$payStatuses = ['Pending','Partial','Paid'];
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
<div class="alert-modern alert-success-modern">
    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_GET['msg']) ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
<div class="alert-modern" style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; border-radius:12px; padding:12px 18px; margin-bottom:16px; display:flex; align-items:center; gap:10px;">
    <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($_GET['err']) ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4; color:#16a34a;"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-value" style="font-size:20px;">₱<?= number_format($mtdRev,0) ?></div>
            <div class="stat-label">MTD Revenue</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fffbeb; color:#d97706;"><i class="bi bi-exclamation-circle-fill"></i></div>
            <div class="stat-value" style="font-size:20px;">₱<?= number_format($pending,0) ?></div>
            <div class="stat-label">Outstanding</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#eff6ff; color:#2563eb;"><i class="bi bi-receipt-cutoff"></i></div>
            <div class="stat-value"><?= $paidCount ?></div>
            <div class="stat-label">Paid Invoices</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef2f2; color:#dc2626;"><i class="bi bi-clock-history"></i></div>
            <div class="stat-value"><?= $overdueCount ?></div>
            <div class="stat-label">Overdue / Pending</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-header-title"><i class="bi bi-receipt-cutoff me-2" style="color:#ef4444;"></i>Invoice Records</span>
        <span style="font-size:12px; color:var(--muted);"><?= $totalInvoices ?> total invoices</span>
    </div>
    <div class="card-body p-0">
        <table class="modern-table">
            <thead>
                <tr><th>Invoice</th><th>Date</th><th>Client</th><th>Pet</th><th>Total</th><th>Paid</th><th>Balance</th><th>Method</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php
            // Checked and updated to exclude soft-deleted items from matching logs
            $res = $conn->query("SELECT b.*,CONCAT(c.FirstName,' ',c.LastName) AS Client, p.PetName
                                 FROM billing b JOIN clients c ON b.ClientID=c.ClientID LEFT JOIN pets p ON b.PetID=p.PetID
                                 WHERE b.IsDeleted=0
                                 ORDER BY b.CreatedAt DESC");
            $highlightID = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
            if ($res->num_rows == 0) echo '<tr class="empty-row"><td colspan="10"><i class="bi bi-receipt" style="font-size:28px; display:block; margin-bottom:10px;"></i>No billing records yet.</td></tr>';
            while ($row = $res->fetch_assoc()):
                $balance = $row['TotalAmount'] - $row['AmountPaid'];
                $bClass = match($row['PaymentStatus']) {
                    'Paid' => 'badge-paid', 'Pending' => 'badge-pending', 'Partial' => 'badge-partial', default => 'badge-pending'
                };
            ?>
            <tr id="row-bill-<?= $row['BillingID'] ?>" class="<?= ($highlightID && $highlightID==$row['BillingID']) ? 'search-highlight-row' : '' ?>">
                <td>
                    <div style="font-weight:700; font-size:12px; color:var(--teal);">INV-<?= str_pad($row['BillingID'],4,'0',STR_PAD_LEFT) ?></div>
                </td>
                <td style="font-size:12px; color:var(--muted);"><?= date('M d, Y', strtotime($row['BillingDate'])) ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($row['Client']) ?></td>
                <td style="font-size:13px; color:var(--muted);"><?= htmlspecialchars($row['PetName'] ?? '—') ?></td>
                <td style="font-weight:700;">₱<?= number_format($row['TotalAmount'],2) ?></td>
                <td style="color:var(--green); font-weight:600;">₱<?= number_format($row['AmountPaid'],2) ?></td>
                <td style="<?= $balance>0?'color:var(--red); font-weight:600;':'' ?>">₱<?= number_format($balance,2) ?></td>
                <td><span class="cat-badge"><?= $row['PaymentMethod'] ?></span></td>
                <td><span class="badge-modern <?= $bClass ?>"><?= $row['PaymentStatus'] ?></span></td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="btn-icon btn-icon-view" onclick="viewBill(<?= htmlspecialchars(json_encode($row)) ?>)" title="View">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn-icon btn-icon-edit" onclick="openEdit(<?= htmlspecialchars(json_encode($row)) ?>)" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if ($row['PaymentStatus'] === 'Paid'): ?>
                        <a href="billing.php?delete=<?= $row['BillingID'] ?>" class="btn-icon btn-icon-delete" title="Delete invoice"
                           onclick="return confirm('Delete this paid invoice? This cannot be undone.')">
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php else: ?>
                        <button class="btn-icon" disabled
                            title="Cannot delete — invoice is <?= $row['PaymentStatus'] ?>. Settle payment first."
                            style="background:#f1f5f9; color:#cbd5e1; border:1px solid #e2e8f0; cursor:not-allowed;">
                            <i class="bi bi-lock-fill"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="billModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">New Invoice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="BillingID" id="billID">
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Client</label>
                    <select name="ClientID" id="fClientID" class="form-select" required>
                        <option value="">— Select Client —</option>
                        <?php foreach ($clientsList as $c): ?>
                        <option value="<?= $c['ClientID'] ?>"><?= htmlspecialchars($c['Name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Pet (optional)</label>
                    <select name="PetID" id="fPetID" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($petsList as $p): ?>
                        <option value="<?= $p['PetID'] ?>"><?= htmlspecialchars($p['PetName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Billing Date</label>
                    <input type="date" name="BillingDate" id="fDate" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Total Amount (₱)</label>
                    <input type="number" name="TotalAmount" id="fTotal" class="form-control" step="0.01" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Amount Paid (₱)</label>
                    <input type="number" name="AmountPaid" id="fPaid" class="form-control" step="0.01" value="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Method</label>
                    <select name="PaymentMethod" id="fMethod" class="form-select">
                        <?php foreach ($payMethods as $m): ?>
                        <option value="<?= $m ?>"><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Status</label>
                    <select name="PaymentStatus" id="fStatus" class="form-select">
                        <?php foreach ($payStatuses as $s): ?>
                        <option value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="Notes" id="fNotes" class="form-control" rows="2"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-main btn-green" id="submitBtn">Save Invoice</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="viewBillModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-receipt-cutoff me-2" style="color:var(--green);"></i>Invoice Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
            <div class="row g-3">
                <div class="col-6"><div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:11px;">Invoice #</div><div id="vBillID" style="font-weight:700;color:var(--teal);">—</div></div>
                <div class="col-6"><div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:11px;">Status</div><div id="vBillStatus">—</div></div>
                <div class="col-6"><div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:11px;">Date</div><div id="vBillDate" style="font-size:14px;">—</div></div>
                <div class="col-6"><div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:11px;">Payment Method</div><div id="vBillMethod" style="font-size:14px;">—</div></div>
                <div class="col-6"><div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:11px;">Client</div><div id="vBillClient" style="font-size:14px;font-weight:700;">—</div></div>
                <div class="col-6"><div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:11px;">Pet</div><div id="vBillPet" style="font-size:14px;">—</div></div>
                <div class="col-4"><div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:11px;">Total Amount</div><div id="vBillTotal" style="font-size:15px;font-weight:700;">—</div></div>
                <div class="col-4"><div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:11px;">Amount Paid</div><div id="vBillPaid" style="font-size:15px;font-weight:700;color:var(--green);">—</div></div>
                <div class="col-4"><div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:11px;">Balance</div><div id="vBillBalance" style="font-size:15px;font-weight:700;">—</div></div>
                <div class="col-12"><div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:11px;">Notes</div><div id="vBillNotes" style="font-size:14px;color:var(--muted);">—</div></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
  </div>
</div>

<script>
const statusClasses = { Paid:'badge-paid', Pending:'badge-pending', Partial:'badge-partial' };

function viewBill(r) {
    const fmt = d => d ? new Date(d).toLocaleDateString('en-US',{month:'short',day:'2-digit',year:'numeric'}) : '—';
    const balance = parseFloat(r.TotalAmount) - parseFloat(r.AmountPaid);
    document.getElementById('vBillID').textContent      = 'INV-' + String(r.BillingID).padStart(4,'0');
    document.getElementById('vBillDate').textContent    = fmt(r.BillingDate);
    document.getElementById('vBillClient').textContent  = r.Client || '—';
    document.getElementById('vBillPet').textContent     = r.PetName || '—';
    document.getElementById('vBillMethod').textContent  = r.PaymentMethod || '—';
    document.getElementById('vBillTotal').textContent   = '₱' + parseFloat(r.TotalAmount).toFixed(2);
    document.getElementById('vBillPaid').textContent    = '₱' + parseFloat(r.AmountPaid).toFixed(2);
    document.getElementById('vBillBalance').textContent = '₱' + balance.toFixed(2);
    document.getElementById('vBillBalance').style.color = balance > 0 ? 'var(--red)' : 'var(--green)';
    document.getElementById('vBillNotes').textContent   = r.Notes || '—';
    document.getElementById('vBillStatus').innerHTML    = `<span class="badge-modern ${statusClasses[r.PaymentStatus]||'badge-pending'}">${r.PaymentStatus}</span>`;
    new bootstrap.Modal(document.getElementById('viewBillModal')).show();
}

function openAdd() {
    document.getElementById('modalTitle').textContent = 'New Invoice';
    document.getElementById('formAction').value = 'add';
    document.getElementById('submitBtn').textContent = 'Save Invoice';
    ['billID','fClientID','fPetID','fTotal','fNotes'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('fPaid').value = '0';
    document.getElementById('fDate').value = '<?= date('Y-m-d') ?>';
}

function openEdit(row) {
    document.getElementById('modalTitle').textContent = 'Edit Invoice #' + row.BillingID;
    document.getElementById('formAction').value = 'update';
    document.getElementById('submitBtn').textContent = 'Update Invoice';
    document.getElementById('billID').value    = row.BillingID;
    document.getElementById('fClientID').value = row.ClientID;
    document.getElementById('fPetID').value    = row.PetID ?? '';
    document.getElementById('fDate').value     = row.BillingDate ? row.BillingDate.substring(0,10) : '';
    document.getElementById('fTotal').value    = row.TotalAmount;
    document.getElementById('fPaid').value     = row.AmountPaid;
    document.getElementById('fMethod').value   = row.PaymentMethod;
    document.getElementById('fStatus').value   = row.PaymentStatus;
    document.getElementById('fNotes').value    = row.Notes ?? '';
    new bootstrap.Modal(document.getElementById('billModal')).show();
}
</script>

<script>
// Highlight handled by universal header.php script
</script>
<?php include('footer.php'); ?>