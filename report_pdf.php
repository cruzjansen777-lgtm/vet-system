<?php
date_default_timezone_set('Asia/Manila');
include('dbconnect.php');

$type     = $_GET['type']  ?? 'clients';
$dateFrom = $_GET['from']  ?? date('Y-m-01');
$dateTo   = $_GET['to']    ?? date('Y-m-d');
$dfrom_s  = $conn->real_escape_string($dateFrom);
$dto_s    = $conn->real_escape_string($dateTo);

$fmtDate  = fn($d) => $d ? date('M d, Y', strtotime($d)) : '—';
$fmtMoney = fn($v) => '₱' . number_format(floatval($v), 2);

// ── Title & data per type ──
$titles = [
    'clients'       => 'Clients / Owners Directory',
    'pets'          => 'Pets / Patients Registry',
    'appointments'  => 'Appointments Report',
    'consultations' => 'Medical History Report',
    'billing'       => 'Billing & Payments Ledger',
    'services'      => 'Clinic Services Catalog',
    'lodging'       => 'Lodging & Boarding Records',
];
$reportTitle = $titles[$type] ?? 'Report';
$hasRange    = in_array($type, ['appointments','consultations','billing']);
$rangeLabel  = $hasRange
    ? date('M d, Y', strtotime($dateFrom)) . ' – ' . date('M d, Y', strtotime($dateTo))
    : 'All Records';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Heartside Vet — <?= htmlspecialchars($reportTitle) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important; }
body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #1e293b; background: #fff; }

@media screen {
    body { background: #f1f5f9; padding-top: 54px; }
    .page { background: #fff; max-width: 960px; margin: 24px auto; padding: 40px 48px;
            box-shadow: 0 4px 24px rgba(0,0,0,.10); border-radius: 10px; }
    .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #0f172a; color: #fff;
                 padding: 10px 28px; display: flex; align-items: center;
                 justify-content: space-between; z-index: 100; font-size: 13px; }
    .print-btn  { background: #10b981; color: #fff; border: none; padding: 7px 18px;
                  border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
    .back-link  { color: #94a3b8; text-decoration: none; font-size: 13px; }
    .back-link:hover { color: #fff; }
}
@media print {
    .print-bar { display: none !important; }
    body { background: #fff; }
    .page-break { page-break-before: always; }
}

.rpt-header { display: flex; justify-content: space-between; align-items: flex-start;
              border-bottom: 2.5px solid #0ea5e9; padding-bottom: 14px; margin-bottom: 22px; }
.rpt-title  { font-size: 20px; font-weight: 800; color: #0f172a; }
.rpt-sub    { font-size: 11px; color: #64748b; margin-top: 3px; }
.rpt-meta   { text-align: right; font-size: 11px; color: #64748b; line-height: 1.7; }
.rpt-meta strong { color: #0f172a; font-size: 13px; display: block; }

.badge-pill { display: inline-block; padding: 2px 9px; border-radius: 99px;
              font-size: 10px; font-weight: 700; }
.badge-paid     { background: #dcfce7 !important; color: #15803d !important; }
.badge-partial  { background: #e0f2fe !important; color: #0369a1 !important; }
.badge-pending  { background: #fef9c3 !important; color: #a16207 !important; }
.badge-active   { background: #dcfce7 !important; color: #15803d !important; }
.badge-inactive { background: #f1f5f9 !important; color: #64748b !important; }
.badge-scheduled{ background: #eff6ff !important; color: #1d4ed8 !important; }
.badge-completed{ background: #dcfce7 !important; color: #15803d !important; }
.badge-cancelled{ background: #fee2e2 !important; color: #b91c1c !important; }
.badge-noshow   { background: #fef9c3 !important; color: #a16207 !important; }

table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 4px; }
thead { background: #f8fafc !important; }
th { padding: 8px 10px; text-align: left; font-size: 10px; font-weight: 700;
     text-transform: uppercase; letter-spacing: .4px; color: #64748b;
     border-bottom: 1.5px solid #e2e8f0; white-space: nowrap; }
td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:nth-child(even) td { background: #fafafa !important; }

.summary-strip { background: linear-gradient(135deg,#0ea5e9,#10b981) !important;
                 border-radius: 8px; padding: 16px 22px; margin-top: 24px;
                 display: flex; justify-content: space-between; align-items: center; color: #fff; }
.sum-big  { font-size: 22px; font-weight: 800; }
.sum-small{ font-size: 11px; opacity: .8; margin-top: 2px; }
.sum-stats{ display: flex; gap: 28px; }
.sum-stat-v { font-size: 18px; font-weight: 800; }
.sum-stat-l { font-size: 10px; opacity: .75; text-transform: uppercase; letter-spacing: .5px; }

.footer-note { text-align: center; font-size: 10px; color: #94a3b8; margin-top: 18px; padding-top: 10px; border-top: 1px solid #f1f5f9; }
.no-data { text-align: center; color: #94a3b8; padding: 20px; font-style: italic; }
</style>
</head>
<body>

<!-- Print bar (screen only) -->
<div class="print-bar">
    <a href="reports.php" class="back-link"><i>←</i> Back to Reports</a>
    <span style="font-weight:600;"><?= htmlspecialchars($reportTitle) ?></span>
    <button class="print-btn" onclick="window.print()">🖨 Print / Save PDF</button>
</div>

<div class="page">

<!-- Header -->
<div class="rpt-header">
    <div>
        <div class="rpt-title">Heartside Veterinary Clinic</div>
        <div class="rpt-sub"><?= htmlspecialchars($reportTitle) ?></div>
    </div>
    <div class="rpt-meta">
        <strong><?= htmlspecialchars($rangeLabel) ?></strong>
        Generated: <?= date('M d, Y \a\t g:i A') ?>
    </div>
</div>

<?php

// ════════════════════════════════════════════════
// CLIENTS
// ════════════════════════════════════════════════
if ($type === 'clients'):
    $rows = $conn->query("SELECT ClientID,
        CONCAT(FirstName,' ',LastName) AS FullName,
        Email, Phone, Address,
        DATE_FORMAT(CreatedAt,'%b %d, %Y') AS DateRegistered
        FROM clients WHERE IsDeleted=0 ORDER BY LastName, FirstName");
    $total = $rows->num_rows;
?>
<p style="font-size:11px;color:#64748b;margin-bottom:10px;">
    Total records: <strong style="color:#0f172a;"><?= $total ?></strong>
</p>
<table>
    <thead><tr>
        <th>#</th><th>Client ID</th><th>Full Name</th><th>Email</th>
        <th>Phone</th><th>Address</th><th>Date Registered</th>
    </tr></thead>
    <tbody>
    <?php if ($total === 0): ?>
        <tr><td colspan="7" class="no-data">No client records found.</td></tr>
    <?php else: $i=0; while ($r = $rows->fetch_assoc()): $i++; ?>
    <tr>
        <td style="color:#94a3b8;"><?= $i ?></td>
        <td style="font-weight:700;color:#0ea5e9;"><?= $r['ClientID'] ?></td>
        <td style="font-weight:600;"><?= htmlspecialchars($r['FullName']) ?></td>
        <td style="color:#64748b;"><?= htmlspecialchars($r['Email'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['Phone'] ?? '—') ?></td>
        <td style="color:#64748b;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['Address'] ?? '—') ?></td>
        <td style="white-space:nowrap;"><?= $r['DateRegistered'] ?></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>

<?php
// ════════════════════════════════════════════════
// PETS
// ════════════════════════════════════════════════
elseif ($type === 'pets'):
    $rows = $conn->query("SELECT p.PetID, p.PetName,
        CONCAT(c.FirstName,' ',c.LastName) AS Owner,
        p.Species, p.Breed, p.Gender, p.DateOfBirth, p.Weight,
        IF(p.IsActive=1,'Active','Inactive') AS Status
        FROM pets p JOIN clients c ON p.ClientID=c.ClientID
        WHERE p.IsDeleted=0 ORDER BY p.PetName");
    $total = $rows->num_rows;
?>
<p style="font-size:11px;color:#64748b;margin-bottom:10px;">
    Total records: <strong style="color:#0f172a;"><?= $total ?></strong>
</p>
<table>
    <thead><tr>
        <th>#</th><th>Pet ID</th><th>Pet Name</th><th>Owner</th>
        <th>Species</th><th>Breed</th><th>Gender</th><th>Date of Birth</th><th>Weight</th><th>Status</th>
    </tr></thead>
    <tbody>
    <?php if ($total === 0): ?>
        <tr><td colspan="10" class="no-data">No pet records found.</td></tr>
    <?php else: $i=0; while ($r = $rows->fetch_assoc()): $i++; ?>
    <tr>
        <td style="color:#94a3b8;"><?= $i ?></td>
        <td style="font-weight:700;color:#10b981;"><?= $r['PetID'] ?></td>
        <td style="font-weight:600;"><?= htmlspecialchars($r['PetName']) ?></td>
        <td><?= htmlspecialchars($r['Owner']) ?></td>
        <td><?= htmlspecialchars($r['Species']) ?></td>
        <td style="color:#64748b;"><?= htmlspecialchars($r['Breed'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['Gender'] ?? '—') ?></td>
        <td style="white-space:nowrap;"><?= $r['DateOfBirth'] ? date('M d, Y', strtotime($r['DateOfBirth'])) : '—' ?></td>
        <td><?= $r['Weight'] ? $r['Weight'] . ' kg' : '—' ?></td>
        <td><span class="badge-pill <?= $r['Status']==='Active'?'badge-active':'badge-inactive' ?>"><?= $r['Status'] ?></span></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>

<?php
// ════════════════════════════════════════════════
// APPOINTMENTS
// ════════════════════════════════════════════════
elseif ($type === 'appointments'):
    $rows = $conn->query("SELECT a.AppointmentID,
        DATE_FORMAT(a.AppointmentDate,'%b %d, %Y') AS Date,
        DATE_FORMAT(a.AppointmentTime,'%h:%i %p') AS Time,
        p.PetName,
        CONCAT(c.FirstName,' ',c.LastName) AS Owner,
        COALESCE(s.ServiceName, a.SavedServiceName,'—') AS Service,
        a.Reason, a.Status
        FROM appointments a
        JOIN pets p ON a.PetID=p.PetID
        JOIN clients c ON a.ClientID=c.ClientID
        LEFT JOIN services s ON a.ServiceID=s.ServiceID
        WHERE a.IsDeleted=0
        AND a.AppointmentDate BETWEEN '$dfrom_s' AND '$dto_s'
        ORDER BY a.AppointmentDate DESC, a.AppointmentTime DESC");
    $total = $rows->num_rows;
    $statusBadge = ['Scheduled'=>'badge-scheduled','Completed'=>'badge-completed','Cancelled'=>'badge-cancelled','No-Show'=>'badge-noshow'];
?>
<p style="font-size:11px;color:#64748b;margin-bottom:10px;">
    <?= $total ?> record(s) from <strong><?= date('M d, Y', strtotime($dateFrom)) ?></strong>
    to <strong><?= date('M d, Y', strtotime($dateTo)) ?></strong>
</p>
<table>
    <thead><tr>
        <th>#</th><th>ID</th><th>Date</th><th>Time</th><th>Pet</th>
        <th>Owner</th><th>Service</th><th>Reason</th><th>Status</th>
    </tr></thead>
    <tbody>
    <?php if ($total === 0): ?>
        <tr><td colspan="9" class="no-data">No appointments found in this range.</td></tr>
    <?php else: $i=0; while ($r = $rows->fetch_assoc()): $i++; ?>
    <tr>
        <td style="color:#94a3b8;"><?= $i ?></td>
        <td style="font-weight:700;color:#8b5cf6;">#<?= $r['AppointmentID'] ?></td>
        <td style="white-space:nowrap;"><?= $r['Date'] ?></td>
        <td style="white-space:nowrap;"><?= $r['Time'] ?></td>
        <td style="font-weight:600;"><?= htmlspecialchars($r['PetName']) ?></td>
        <td><?= htmlspecialchars($r['Owner']) ?></td>
        <td style="color:#64748b;"><?= htmlspecialchars($r['Service']) ?></td>
        <td style="color:#64748b;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['Reason'] ?? '—') ?></td>
        <td><span class="badge-pill <?= $statusBadge[$r['Status']] ?? '' ?>"><?= $r['Status'] ?></span></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>

<?php
// ════════════════════════════════════════════════
// CONSULTATIONS / MEDICAL HISTORY
// ════════════════════════════════════════════════
elseif ($type === 'consultations'):
    $rows = $conn->query("SELECT con.ConsultationID,
        DATE_FORMAT(con.ConsultationDate,'%b %d, %Y') AS Date,
        p.PetName,
        CONCAT(c.FirstName,' ',c.LastName) AS Owner,
        con.VetName, con.ChiefComplaint, con.Diagnosis,
        con.Treatment, con.Prescription,
        con.FollowUpDate
        FROM consultations con
        JOIN pets p ON con.PetID=p.PetID
        JOIN clients c ON con.ClientID=c.ClientID
        WHERE con.IsDeleted=0
        AND DATE(con.ConsultationDate) BETWEEN '$dfrom_s' AND '$dto_s'
        ORDER BY con.ConsultationDate DESC");
    $total = $rows->num_rows;
?>
<p style="font-size:11px;color:#64748b;margin-bottom:10px;">
    <?= $total ?> record(s) from <strong><?= date('M d, Y', strtotime($dateFrom)) ?></strong>
    to <strong><?= date('M d, Y', strtotime($dateTo)) ?></strong>
</p>
<table>
    <thead><tr>
        <th>#</th><th>ID</th><th>Date</th><th>Pet</th><th>Owner</th>
        <th>Veterinarian</th><th>Diagnosis</th><th>Treatment</th><th>Prescription</th><th>Follow-Up</th>
    </tr></thead>
    <tbody>
    <?php if ($total === 0): ?>
        <tr><td colspan="10" class="no-data">No consultations found in this range.</td></tr>
    <?php else: $i=0; while ($r = $rows->fetch_assoc()): $i++; ?>
    <tr>
        <td style="color:#94a3b8;"><?= $i ?></td>
        <td style="font-weight:700;color:#f97316;">#<?= $r['ConsultationID'] ?></td>
        <td style="white-space:nowrap;"><?= $r['Date'] ?></td>
        <td style="font-weight:600;"><?= htmlspecialchars($r['PetName']) ?></td>
        <td><?= htmlspecialchars($r['Owner']) ?></td>
        <td><?= htmlspecialchars($r['VetName']) ?></td>
        <td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['Diagnosis'] ?: '—') ?></td>
        <td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#64748b;"><?= htmlspecialchars($r['Treatment'] ?: '—') ?></td>
        <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#64748b;"><?= htmlspecialchars($r['Prescription'] ?: '—') ?></td>
        <td style="white-space:nowrap;"><?= $r['FollowUpDate'] ? date('M d, Y', strtotime($r['FollowUpDate'])) : '—' ?></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>

<?php
// ════════════════════════════════════════════════
// BILLING
// ════════════════════════════════════════════════
elseif ($type === 'billing'):
    $rows = $conn->query("SELECT b.BillingID,
        DATE_FORMAT(b.BillingDate,'%b %d, %Y') AS Date,
        CONCAT(c.FirstName,' ',c.LastName) AS Client,
        COALESCE(p.PetName,'—') AS Pet,
        b.TotalAmount, b.AmountPaid,
        (b.TotalAmount - b.AmountPaid) AS Balance,
        b.PaymentMethod, b.PaymentStatus, b.Notes
        FROM billing b
        JOIN clients c ON b.ClientID=c.ClientID
        LEFT JOIN pets p ON b.PetID=p.PetID
        WHERE b.IsDeleted=0
        AND b.BillingDate BETWEEN '$dfrom_s' AND '$dto_s'
        ORDER BY b.BillingDate DESC");
    $total    = $rows->num_rows;
    $allRows  = $rows->fetch_all(MYSQLI_ASSOC);
    $totBilled = array_sum(array_column($allRows,'TotalAmount'));
    $totPaid   = array_sum(array_column($allRows,'AmountPaid'));
    $totBal    = $totBilled - $totPaid;
?>
<p style="font-size:11px;color:#64748b;margin-bottom:10px;">
    <?= $total ?> invoice(s) from <strong><?= date('M d, Y', strtotime($dateFrom)) ?></strong>
    to <strong><?= date('M d, Y', strtotime($dateTo)) ?></strong>
</p>
<table>
    <thead><tr>
        <th>#</th><th>Bill ID</th><th>Date</th><th>Client</th><th>Pet</th>
        <th>Total</th><th>Paid</th><th>Balance</th><th>Method</th><th>Status</th>
    </tr></thead>
    <tbody>
    <?php if (empty($allRows)): ?>
        <tr><td colspan="10" class="no-data">No billing records found in this range.</td></tr>
    <?php else: foreach ($allRows as $i => $r):
        $bal = floatval($r['Balance']);
        $bClass = match($r['PaymentStatus']) { 'Paid'=>'badge-paid','Partial'=>'badge-partial',default=>'badge-pending' };
    ?>
    <tr>
        <td style="color:#94a3b8;"><?= $i+1 ?></td>
        <td style="font-weight:700;color:#10b981;">INV-<?= str_pad($r['BillingID'],4,'0',STR_PAD_LEFT) ?></td>
        <td style="white-space:nowrap;"><?= $r['Date'] ?></td>
        <td style="font-weight:600;"><?= htmlspecialchars($r['Client']) ?></td>
        <td style="color:#64748b;"><?= htmlspecialchars($r['Pet']) ?></td>
        <td style="font-weight:700;">₱<?= number_format($r['TotalAmount'],2) ?></td>
        <td style="color:#15803d;font-weight:600;">₱<?= number_format($r['AmountPaid'],2) ?></td>
        <td style="<?= $bal>0?'color:#dc2626;':'color:#94a3b8;' ?>"><?= $bal>0 ? '₱'.number_format($bal,2) : '—' ?></td>
        <td><?= htmlspecialchars($r['PaymentMethod'] ?? '—') ?></td>
        <td><span class="badge-pill <?= $bClass ?>"><?= $r['PaymentStatus'] ?></span></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<?php if (!empty($allRows)): ?>
<div class="summary-strip">
    <div>
        <div class="sum-small">Period Summary</div>
        <div class="sum-big">₱<?= number_format($totPaid,2) ?> collected</div>
        <div class="sum-small"><?= $total ?> invoices · ₱<?= number_format($totBilled,2) ?> billed</div>
    </div>
    <div class="sum-stats">
        <div><div class="sum-stat-v">₱<?= number_format($totBal,2) ?></div><div class="sum-stat-l">Outstanding</div></div>
        <div><div class="sum-stat-v"><?= count(array_filter($allRows,fn($r)=>$r['PaymentStatus']==='Paid')) ?></div><div class="sum-stat-l">Paid</div></div>
        <div><div class="sum-stat-v"><?= count(array_filter($allRows,fn($r)=>$r['PaymentStatus']!=='Paid')) ?></div><div class="sum-stat-l">Unpaid</div></div>
    </div>
</div>
<?php endif; ?>

<?php
// ════════════════════════════════════════════════
// SERVICES
// ════════════════════════════════════════════════
elseif ($type === 'services'):
    $rows = $conn->query("SELECT ServiceID, ServiceName, Category,
        Price, Duration, Description,
        IF(IsActive=1,'Active','Inactive') AS Status
        FROM services WHERE IsDeleted=0 ORDER BY Category, ServiceName");
    $total = $rows->num_rows;
?>
<p style="font-size:11px;color:#64748b;margin-bottom:10px;">
    Total records: <strong style="color:#0f172a;"><?= $total ?></strong>
</p>
<table>
    <thead><tr>
        <th>#</th><th>ID</th><th>Service Name</th><th>Category</th>
        <th>Price</th><th>Duration</th><th>Description</th><th>Status</th>
    </tr></thead>
    <tbody>
    <?php if ($total === 0): ?>
        <tr><td colspan="8" class="no-data">No services found.</td></tr>
    <?php else: $i=0; while ($r = $rows->fetch_assoc()): $i++; ?>
    <tr>
        <td style="color:#94a3b8;"><?= $i ?></td>
        <td style="font-weight:700;color:#f59e0b;"><?= $r['ServiceID'] ?></td>
        <td style="font-weight:600;"><?= htmlspecialchars($r['ServiceName']) ?></td>
        <td style="color:#64748b;"><?= htmlspecialchars($r['Category']) ?></td>
        <td style="font-weight:700;">₱<?= number_format($r['Price'],2) ?></td>
        <td><?= $r['Duration'] ? $r['Duration'].' min' : '—' ?></td>
        <td style="color:#64748b;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['Description'] ?? '—') ?></td>
        <td><span class="badge-pill <?= $r['Status']==='Active'?'badge-active':'badge-inactive' ?>"><?= $r['Status'] ?></span></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>

<?php
// ════════════════════════════════════════════════
// LODGING
// ════════════════════════════════════════════════
elseif ($type === 'lodging'):
    $rows = $conn->query("SELECT l.LodgingID,
        p.PetName,
        CONCAT(c.FirstName,' ',c.LastName) AS Owner,
        DATE_FORMAT(l.CheckInDate,'%b %d, %Y %h:%i %p') AS CheckIn,
        COALESCE(DATE_FORMAT(l.CheckOutDate,'%b %d, %Y %h:%i %p'),'Still boarding') AS CheckOut,
        l.CageNumber, l.DailyRate, l.Status, l.SpecialInstructions
        FROM lodging l
        JOIN pets p ON l.PetID=p.PetID
        JOIN clients c ON l.ClientID=c.ClientID
        WHERE l.IsDeleted=0 ORDER BY l.CheckInDate DESC");
    $total = $rows->num_rows;
    $statusBadge = ['Active'=>'badge-active','Completed'=>'badge-completed','Cancelled'=>'badge-cancelled'];
?>
<p style="font-size:11px;color:#64748b;margin-bottom:10px;">
    Total records: <strong style="color:#0f172a;"><?= $total ?></strong>
</p>
<table>
    <thead><tr>
        <th>#</th><th>ID</th><th>Pet</th><th>Owner</th><th>Check-In</th>
        <th>Check-Out</th><th>Cage #</th><th>Daily Rate</th><th>Status</th><th>Instructions</th>
    </tr></thead>
    <tbody>
    <?php if ($total === 0): ?>
        <tr><td colspan="10" class="no-data">No lodging records found.</td></tr>
    <?php else: $i=0; while ($r = $rows->fetch_assoc()): $i++; ?>
    <tr>
        <td style="color:#94a3b8;"><?= $i ?></td>
        <td style="font-weight:700;color:#ec4899;">#<?= $r['LodgingID'] ?></td>
        <td style="font-weight:600;"><?= htmlspecialchars($r['PetName']) ?></td>
        <td><?= htmlspecialchars($r['Owner']) ?></td>
        <td style="white-space:nowrap;font-size:10px;"><?= $r['CheckIn'] ?></td>
        <td style="white-space:nowrap;font-size:10px;"><?= $r['CheckOut'] ?></td>
        <td><?= htmlspecialchars($r['CageNumber'] ?? '—') ?></td>
        <td style="font-weight:700;">₱<?= number_format($r['DailyRate'],2) ?>/day</td>
        <td><span class="badge-pill <?= $statusBadge[$r['Status']] ?? '' ?>"><?= $r['Status'] ?></span></td>
        <td style="color:#64748b;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['SpecialInstructions'] ?? '—') ?></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
</table>

<?php endif; ?>

<div class="footer-note">
    Heartside Veterinary Clinic &nbsp;·&nbsp; <?= htmlspecialchars($reportTitle) ?>
    &nbsp;·&nbsp; Generated <?= date('F d, Y \a\t g:i A') ?>
</div>

</div><!-- .page -->
</body>
</html>