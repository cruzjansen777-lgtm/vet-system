<?php 
include('dbconnect.php'); 
include('header.php'); 

// ── Date range filter ──
$range     = $_GET['range'] ?? '30';
$dateFrom  = $_GET['from'] ?? date('Y-m-d', strtotime("-{$range} days"));
$dateTo    = $_GET['to']   ?? date('Y-m-d');                          // billing/revenue: up to today
$dateApptTo = date('Y-m-d', strtotime("+{$range} days"));             // appointments: include future scheduled
if ($range === 'custom') {
    $dateFrom   = $_GET['from'] ?? date('Y-m-01');
    $dateTo     = $_GET['to']   ?? date('Y-m-d');
    $dateApptTo = $_GET['to']   ?? date('Y-m-d');
}

// ── Optimized Consolidated KPI & Metrics Fetching ──
$kpi = $conn->query("SELECT
    (SELECT COUNT(*) FROM clients WHERE IsActive=1) as totalClients,
    (SELECT COUNT(*) FROM pets WHERE IsActive=1) as totalPets,
    (SELECT COUNT(*) FROM appointments WHERE COALESCE(IsDeleted,0)=0 AND AppointmentDate BETWEEN '$dateFrom' AND '$dateApptTo') as totalAppt,
    (SELECT COUNT(*) FROM appointments WHERE COALESCE(IsDeleted,0)=0 AND Status='Completed' AND AppointmentDate BETWEEN '$dateFrom' AND '$dateApptTo') as completedAppt,
    (SELECT COUNT(*) FROM appointments WHERE COALESCE(IsDeleted,0)=0 AND Status='Cancelled' AND AppointmentDate BETWEEN '$dateFrom' AND '$dateApptTo') as cancelledAppt,
    (SELECT COUNT(*) FROM lodging WHERE Status='Active') as activeLodging,
    (SELECT COUNT(*) FROM clients WHERE IsActive=1 AND CreatedAt BETWEEN '$dateFrom' AND '$dateTo') as newClients
")->fetch_assoc();
$billingStats = $conn->query("
    SELECT 
        COALESCE(SUM(AmountPaid),0) as totalRevenue,
        COALESCE(SUM(CASE WHEN PaymentStatus!='Paid' THEN TotalAmount-AmountPaid END),0) as totalPending,
        COUNT(*) as totalBills,
        COUNT(CASE WHEN PaymentStatus='Paid' THEN 1 END) as paidBills
    FROM billing 
    WHERE BillingDate BETWEEN '$dateFrom' AND '$dateTo'
")->fetch_assoc();

// Extract variables into scope to preserve chart/UI variable bindings
extract($kpi);
extract($billingStats);

// ── Historical Aggregations (Last 6 Months & Last 14 Days) ──
$revenueData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $row = $conn->query("SELECT COALESCE(SUM(AmountPaid),0) as col, COALESCE(SUM(TotalAmount),0) as bil FROM billing WHERE DATE_FORMAT(BillingDate,'%Y-%m')='$month'")->fetch_assoc();
    $revenueData[] = ['label'=>$label, 'collected'=>floatval($row['col']), 'billed'=>floatval($row['bil'])];
}

$dailyRevData = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $lbl = date('M d', strtotime("-$i days"));
    $val = $conn->query("SELECT COALESCE(SUM(AmountPaid),0) FROM billing WHERE BillingDate='$day'")->fetch_row()[0];
    $dailyRevData[] = ['label'=>$lbl, 'value'=>floatval($val)];
}

$newClientData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $val   = $conn->query("SELECT COUNT(*) FROM clients WHERE DATE_FORMAT(CreatedAt,'%Y-%m')='$month' AND IsActive=1")->fetch_row()[0];
    $newClientData[] = ['label'=>$label, 'value'=>intval($val)];
}

// ── Multi-Row Analytical Lists ──
$apptStats  = $conn->query("SELECT Status, COUNT(*) AS cnt FROM appointments WHERE AppointmentDate BETWEEN '$dateFrom' AND '$dateApptTo' AND COALESCE(IsDeleted,0)=0 GROUP BY Status")->fetch_all(MYSQLI_ASSOC);
$apptByDay  = $conn->query("SELECT DAYNAME(AppointmentDate) AS day, COUNT(*) AS cnt FROM appointments WHERE AppointmentDate BETWEEN '$dateFrom' AND '$dateApptTo' AND COALESCE(IsDeleted,0)=0 GROUP BY DAYNAME(AppointmentDate), DAYOFWEEK(AppointmentDate) ORDER BY DAYOFWEEK(AppointmentDate)")->fetch_all(MYSQLI_ASSOC);
$species    = $conn->query("SELECT Species, COUNT(*) AS cnt FROM pets WHERE IsActive=1 GROUP BY Species ORDER BY cnt DESC")->fetch_all(MYSQLI_ASSOC);
$payStatus  = $conn->query("SELECT PaymentStatus, COUNT(*) AS cnt, COALESCE(SUM(TotalAmount),0) AS total FROM billing WHERE BillingDate BETWEEN '$dateFrom' AND '$dateTo' GROUP BY PaymentStatus")->fetch_all(MYSQLI_ASSOC);
$payMethods = $conn->query("SELECT PaymentMethod, COUNT(*) AS cnt, COALESCE(SUM(AmountPaid),0) AS total FROM billing WHERE BillingDate BETWEEN '$dateFrom' AND '$dateTo' AND PaymentMethod IS NOT NULL AND PaymentMethod != '' GROUP BY PaymentMethod ORDER BY total DESC")->fetch_all(MYSQLI_ASSOC);

$topSvcs = $conn->query("SELECT s.ServiceName, s.Price, COUNT(a.AppointmentID) AS cnt FROM appointments a JOIN services s ON a.ServiceID=s.ServiceID WHERE COALESCE(a.IsDeleted,0)=0 AND a.AppointmentDate BETWEEN '$dateFrom' AND '$dateTo' GROUP BY s.ServiceID ORDER BY cnt DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$topClients = $conn->query("SELECT CONCAT(c.FirstName,' ',c.LastName) AS Client, c.Email, c.Phone, COUNT(b.BillingID) AS bills, COALESCE(SUM(b.AmountPaid),0) AS spent FROM billing b JOIN clients c ON b.ClientID=c.ClientID WHERE b.BillingDate BETWEEN '$dateFrom' AND '$dateTo' GROUP BY b.ClientID ORDER BY spent DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$recentBilling = $conn->query("SELECT b.*, CONCAT(c.FirstName,' ',c.LastName) AS Client, p.PetName, p.Species FROM billing b JOIN clients c ON b.ClientID=c.ClientID LEFT JOIN pets p ON b.PetID=p.PetID WHERE b.BillingDate BETWEEN '$dateFrom' AND '$dateTo' ORDER BY b.CreatedAt DESC LIMIT 20");

// ── Computed Calculations ──
$avgRevPerAppt  = ($completedAppt > 0) ? round($totalRevenue / $completedAppt, 2) : 0;
$collectionRate = ($totalRevenue + $totalPending > 0) ? round($totalRevenue / ($totalRevenue + $totalPending) * 100, 1) : 0;
$completionRate = ($totalAppt > 0) ? round($completedAppt / $totalAppt * 100, 1) : 0;
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Reports & Analytics</h1>
        <p>Comprehensive insights into clinic performance and financials.</p>
    </div>
    <div class="page-header-actions" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <form method="GET" style="display:flex; gap:8px; align-items:center;">
            <select name="range" class="filter-select" onchange="this.form.submit()">
                <?php foreach(['7'=>'Last 7 days', '30'=>'Last 30 days', '90'=>'Last 90 days', '365'=>'Last 12 months', 'custom'=>'Custom range'] as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $range == $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($range === 'custom'): ?>
                <input type="date" name="from" value="<?= $dateFrom ?>" class="filter-select">
                <input type="date" name="to"   value="<?= $dateTo ?>"   class="filter-select">
                <button type="submit" class="btn-main btn-teal" style="padding:8px 14px;">Apply</button>
            <?php endif; ?>
        </form>
        <a href="report_pdf.php?range=<?= $range ?>&from=<?= $dateFrom ?>&to=<?= $dateTo ?>" target="_blank" class="btn-main" style="background:#1D9E75;color:#fff;border:none;display:flex;align-items:center;gap:6px;"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
    </div>
</div>

<style>
.filter-select { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 8px 12px; font-size: 13px; color: var(--text); font-family: 'DM Sans', sans-serif; outline: none; cursor: pointer; }
.filter-select:focus { border-color: var(--teal); }
.report-section-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin: 8px 0 12px; }
.progress-bar-wrap { background: #f1f5f9; border-radius: 99px; height: 6px; overflow: hidden; }
.progress-bar-fill  { height: 100%; border-radius: 99px; transition: width .4s; }
.metric-row { display:flex; align-items:center; justify-content:space-between; padding: 10px 0; border-bottom: 1px solid var(--border); }
.metric-row:last-child { border-bottom: none; }
.metric-label { font-size: 13px; color: var(--muted); }
.metric-value { font-size: 14px; font-weight: 700; color: var(--text); }
.rank-num { width:24px; height:24px; border-radius:6px; background:var(--bg); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:var(--muted); flex-shrink:0; }
.rank-num.gold { background:#fef9c3; color:#a16207; }
.rank-num.silver { background:#f1f5f9; color:#475569; }
.rank-num.bronze { background:#fef3e2; color:#b45309; }
</style>

<p class="report-section-label"><i class="bi bi-grid-3x3-gap me-1"></i> Key Performance Indicators · <?= date('M d', strtotime($dateFrom)) ?> – <?= date('M d, Y', strtotime($dateTo)) ?></p>
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['icon' => 'bi-people-fill', 'bg' => '#eff6ff', 'color' => '#2563eb', 'val' => $totalClients, 'lbl' => 'Active Clients', 'trend' => "<i class='bi bi-person-plus'></i> +{$newClients} new", 'tClass' => 'up'],
        ['icon' => 'bi-heart-fill', 'bg' => '#fdf2f8', 'color' => '#db2777', 'val' => $totalPets, 'lbl' => 'Registered Pets', 'trend' => '<i class=\'bi bi-shield-check\'></i> Active patients', 'tClass' => 'up'],
        ['icon' => 'bi-calendar-check-fill', 'bg' => '#eff6ff', 'color' => '#2563eb', 'val' => $totalAppt, 'lbl' => 'Appointments', 'trend' => "<i class='bi bi-check-circle'></i> {$completionRate}% completed", 'tClass' => 'up'],
        ['icon' => 'bi-graph-up-arrow', 'bg' => '#f0fdf4', 'color' => '#16a34a', 'val' => '₱'.number_format($totalRevenue,0), 'lbl' => 'Revenue Collected', 'trend' => "<i class='bi bi-arrow-up-short'></i>{$collectionRate}% collection rate", 'tClass' => 'up'],
        ['icon' => 'bi-receipt-cutoff', 'bg' => '#fffbeb', 'color' => '#d97706', 'val' => '₱'.number_format($totalPending,0), 'lbl' => 'Outstanding', 'trend' => "<i class='bi bi-exclamation-circle'></i> ".($totalBills - $paidBills)." unpaid bills", 'tClass' => 'warn'],
        ['icon' => 'bi-cash-coin', 'bg' => '#f5f3ff', 'color' => '#7c3aed', 'val' => '₱'.number_format($avgRevPerAppt,0), 'lbl' => 'Avg. Per Completed Visit', 'trend' => "<i class='bi bi-clipboard-check'></i> {$completedAppt} visits", 'tClass' => 'up']
    ];
    foreach ($cards as $c): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>;"><i class="<?= $c['icon'] ?>"></i></div>
            <div class="stat-value" style="<?= strlen($c['val']) > 6 ? 'font-size:18px;' : '' ?>"><?= $c['val'] ?></div>
            <div class="stat-label"><?= $c['lbl'] ?></div>
            <div class="stat-trend <?= $c['tClass'] ?>"><?= $c['trend'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<p class="report-section-label"><i class="bi bi-bar-chart-line me-1"></i> Revenue Trends</p>
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <span class="card-header-title">Monthly Revenue — Last 6 Months</span>
                <span style="display:flex;gap:14px;font-size:11px;font-weight:600;">
                    <span style="color:var(--teal);"><span style="display:inline-block;width:10px;height:10px;background:var(--teal);border-radius:2px;margin-right:4px;"></span>Collected</span>
                    <span style="color:var(--light-muted);"><span style="display:inline-block;width:10px;height:10px;background:#e2e8f0;border-radius:2px;margin-right:4px;"></span>Billed</span>
                </span>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;justify-content:center;"><canvas id="monthlyRevenueChart" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><span class="card-header-title">Payment Status Breakdown</span><span style="font-size:11px;color:var(--muted);">Period total</span></div>
            <div class="card-body" style="display:flex;gap:24px;align-items:center;flex-wrap:wrap;">
                <div style="flex:0 0 160px;"><canvas id="payStatusChart" width="160" height="160"></canvas></div>
                <div style="flex:1;min-width:140px;">
                    <?php foreach($payStatus as $ps):
                        $pct = ($totalRevenue+$totalPending>0) ? round($ps['total']/($totalRevenue+$totalPending)*100,1) : 0;
                        $c = ['Paid'=>'var(--green)','Pending'=>'var(--amber)','Partial'=>'var(--teal)'][$ps['PaymentStatus']] ?? 'var(--muted)';
                    ?>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                            <span style="font-size:12px;color:var(--muted);"><?= $ps['PaymentStatus'] ?></span>
                            <span style="font-size:12px;font-weight:700;color:<?= $c ?>;"><?= $pct ?>%</span>
                        </div>
                        <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= $pct ?>%;background:<?= $c ?>;"></div></div>
                        <div style="font-size:11px;color:var(--light-muted);margin-top:2px;">₱<?= number_format($ps['total'],0) ?> · <?= $ps['cnt'] ?> bills</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3"><div class="col-12"><div class="card"><div class="card-header"><span class="card-header-title">Daily Revenue — Last 14 Days</span><span style="font-size:12px;color:var(--green);font-weight:600;"><i class="bi bi-circle-fill" style="font-size:8px;margin-right:4px;"></i>Live data</span></div><div class="card-body"><canvas id="dailyRevenueChart" height="55"></canvas></div></div></div></div>

<p class="report-section-label"><i class="bi bi-calendar3 me-1"></i> Appointment Analytics</p>
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><span class="card-header-title">By Status</span><span style="font-size:11px;color:var(--muted);"><?= $totalAppt ?> total</span></div>
            <div class="card-body">
                <canvas id="apptStatusChart" height="160"></canvas>
                <div style="margin-top:16px;">
                    <?php foreach($apptStats as $as):
                        $pct = $totalAppt > 0 ? round($as['cnt']/$totalAppt*100,1) : 0;
                        $c = ['Completed'=>'var(--green)','Scheduled'=>'var(--teal)','Cancelled'=>'var(--red)','No-Show'=>'var(--amber)','No-show'=>'var(--amber)','Pending'=>'var(--muted)'][$as['Status']] ?? 'var(--muted)';
                    ?>
                    <div style="margin-bottom:8px;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:3px;">
                            <span style="font-size:12px;color:var(--muted);"><?= $as['Status'] ?></span>
                            <span style="font-size:12px;font-weight:700;"><?= $as['cnt'] ?> <span style="color:var(--light-muted);font-weight:400;"><?= $pct ?>%</span></span>
                        </div>
                        <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= $pct ?>%;background:<?= $c ?>;"></div></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><span class="card-header-title">By Day of Week</span><span style="font-size:11px;color:var(--muted);">Busiest days</span></div><div class="card-body" style="display:flex;flex-direction:column;justify-content:center;"><canvas id="apptDayChart" height="200"></canvas></div></div></div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><span class="card-header-title">Appointment Metrics</span></div>
            <div class="card-body">
                <?php
                $metrics = [
                    ['lbl' => 'Total Appointments', 'val' => $totalAppt, 'style' => ''],
                    ['lbl' => 'Completed', 'val' => $completedAppt, 'style' => 'color:var(--green);'],
                    ['lbl' => 'Cancelled', 'val' => $cancelledAppt, 'style' => 'color:var(--red);'],
                    ['lbl' => 'Completion Rate', 'val' => $completionRate.'%', 'style' => ''],
                    ['lbl' => 'Cancellation Rate', 'val' => ($totalAppt>0 ? round($cancelledAppt/$totalAppt*100,1) : 0).'%', 'style' => ''],
                    ['lbl' => 'Active Lodging', 'val' => $activeLodging, 'style' => 'color:var(--purple);'],
                    ['lbl' => 'Avg. Revenue / Completed Visit', 'val' => '₱'.number_format($avgRevPerAppt,2), 'style' => ''],
                    ['lbl' => 'Collection Rate', 'val' => $collectionRate.'%', 'style' => 'color:var(--green);']
                ];
                foreach ($metrics as $m): ?>
                <div class="metric-row"><span class="metric-label"><?= $m['lbl'] ?></span><span class="metric-value" style="<?= $m['style'] ?>"><?= $m['val'] ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<p class="report-section-label"><i class="bi bi-box-seam me-1"></i> Services & Patient Mix</p>
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><span class="card-header-title"><i class="bi bi-star-fill me-2" style="color:var(--amber);"></i>Top Services by Appointments</span><span style="font-size:11px;color:var(--muted);">Period ranking</span></div>
            <div class="card-body p-0">
                <table class="modern-table">
                    <thead><tr><th>#</th><th>Service</th><th>Appts</th><th>Price</th><th>Share</th></tr></thead>
                    <tbody>
                    <?php if(empty($topSvcs)): ?>
                        <tr class="empty-row"><td colspan="5"><i class="bi bi-inbox" style="font-size:22px;display:block;margin-bottom:6px;"></i>No service data for period</td></tr>
                    <?php else:
                        $maxSvc = max(array_column($topSvcs,'cnt')) ?: 1;
                        foreach($topSvcs as $i => $s):
                            $rankClass = $i===0 ? 'gold' : ($i===1 ? 'silver' : ($i===2 ? 'bronze' : ''));
                    ?>
                    <tr>
                        <td><div class="rank-num <?= $rankClass ?>"><?= $i+1 ?></div></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($s['ServiceName']) ?></td>
                        <td><span class="badge-modern badge-scheduled"><?= $s['cnt'] ?></span></td>
                        <td style="font-size:12px;color:var(--muted);">₱<?= number_format($s['Price'],2) ?></td>
                        <td style="width:100px;"><div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= round($s['cnt']/$maxSvc*100) ?>%;background:var(--teal);"></div></div></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><span class="card-header-title"><i class="bi bi-heart-fill me-2" style="color:#db2777;"></i>Patients by Species</span><span style="font-size:11px;color:var(--muted);"><?= $totalPets ?> total</span></div>
            <div class="card-body" style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">
                <div style="flex:0 0 auto;"><canvas id="speciesChart" width="180" height="180"></canvas></div>
                <div style="flex:1;min-width:130px;padding-top:4px;">
                <?php
                $speciesColors=['#0ea5e9','#10b981','#f59e0b','#8b5cf6','#ef4444','#ec4899','#94a3b8'];
                foreach($species as $si => $sp):
                    $pct = $totalPets>0 ? round($sp['cnt']/$totalPets*100,1) : 0;
                ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                    <div style="width:10px;height:10px;background:<?= $speciesColors[$si % count($speciesColors)] ?>;border-radius:3px;flex-shrink:0;"></div>
                    <span style="font-size:13px;flex:1;"><?= htmlspecialchars($sp['Species']) ?></span>
                    <span style="font-size:12px;font-weight:700;"><?= $sp['cnt'] ?></span>
                    <span style="font-size:11px;color:var(--light-muted);width:36px;text-align:right;"><?= $pct ?>%</span>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<p class="report-section-label"><i class="bi bi-people me-1"></i> Client Intelligence & Payment Breakdown</p>
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><span class="card-header-title"><i class="bi bi-trophy-fill me-2" style="color:var(--amber);"></i>Top Clients by Spending</span><a href="clients.php" style="font-size:12px;color:var(--teal);text-decoration:none;font-weight:600;">View all →</a></div>
            <div class="card-body p-0">
                <table class="modern-table">
                    <thead><tr><th>#</th><th>Client</th><th>Bills</th><th>Total Spent</th></tr></thead>
                    <tbody>
                    <?php if(empty($topClients)): ?>
                        <tr class="empty-row"><td colspan="4">No billing data for period</td></tr>
                    <?php else:
                        $maxSpent = max(array_column($topClients,'spent')) ?: 1;
                        foreach($topClients as $ci => $cl):
                            $rankClass = $ci===0?'gold':($ci===1?'silver':($ci===2?'bronze':''));
                    ?>
                    <tr>
                        <td><div class="rank-num <?= $rankClass ?>"><?= $ci+1 ?></div></td>
                        <td><div style="font-weight:600;"><?= htmlspecialchars($cl['Client']) ?></div><div style="font-size:11px;color:var(--light-muted);"><?= htmlspecialchars($cl['Email']??'') ?></div></td>
                        <td><span class="badge-modern badge-scheduled"><?= $cl['bills'] ?></span></td>
                        <td>
                            <div style="font-weight:700;font-size:14px;color:var(--green);">₱<?= number_format($cl['spent'],2) ?></div>
                            <div class="progress-bar-wrap" style="margin-top:4px;"><div class="progress-bar-fill" style="width:<?= round($cl['spent']/$maxSpent*100) ?>%;background:var(--green);"></div></div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><span class="card-header-title"><i class="bi bi-credit-card-fill me-2" style="color:var(--indigo);"></i>Payment Methods & Client Growth</span></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:20px;">
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--light-muted);margin-bottom:10px;">Payment Methods</div>
                    <?php if(empty($payMethods)): ?>
                        <p style="color:var(--muted);font-size:13px;">No payment data for period.</p>
                    <?php else:
                        $maxPay = max(array_column($payMethods,'total')) ?: 1;
                        foreach($payMethods as $pm):
                            $c = ['Cash'=>'var(--green)','GCash'=>'var(--teal)','Card'=>'var(--indigo)','Bank Transfer'=>'var(--purple)'][$pm['PaymentMethod']] ?? 'var(--muted)';
                    ?>
                    <div style="margin-bottom:10px;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                            <span style="font-size:13px;font-weight:600;"><?= htmlspecialchars($pm['PaymentMethod']) ?></span>
                            <span style="font-size:12px;color:var(--muted);"><?= $pm['cnt'] ?> txns · <strong style="color:var(--text);">₱<?= number_format($pm['total'],0) ?></strong></span>
                        </div>
                        <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= round($pm['total']/$maxPay*100) ?>%;background:<?= $c ?>;"></div></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <hr style="border-color:var(--border);margin:0;">
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--light-muted);margin-bottom:10px;">New Clients per Month</div>
                    <canvas id="newClientsChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<p class="report-section-label"><i class="bi bi-table me-1"></i> Billing Transaction Log</p>
<div class="row g-3 mb-4"><div class="col-12"><div class="card"><div class="card-header"><span class="card-header-title"><i class="bi bi-receipt me-2" style="color:var(--green);"></i>All Transactions · <?= date('M d', strtotime($dateFrom)) ?> – <?= date('M d, Y', strtotime($dateTo)) ?></span><span style="font-size:11px;color:var(--muted);"><?= $totalBills ?> records</span></div><div class="card-body p-0">
    <table class="modern-table">
        <thead><tr><th>Date</th><th>Client</th><th>Pet</th><th>Species</th><th>Billed</th><th>Paid</th><th>Balance</th><th>Method</th><th>Status</th></tr></thead>
        <tbody>
        <?php if($recentBilling->num_rows == 0): ?>
            <tr class="empty-row"><td colspan="9"><i class="bi bi-receipt-cutoff" style="font-size:24px;display:block;margin-bottom:8px;"></i>No transactions for this period</td></tr>
        <?php endif;
        while($row = $recentBilling->fetch_assoc()):
            $bal = $row['TotalAmount'] - $row['AmountPaid'];
            $bClass = match($row['PaymentStatus']){ 'Paid'=>'badge-paid', 'Pending'=>'badge-pending', default=>'badge-scheduled' };
        ?>
        <tr>
            <td><div style="font-weight:600;font-size:13px;"><?= date('M d', strtotime($row['BillingDate'])) ?></div><div style="font-size:11px;color:var(--light-muted);"><?= date('Y', strtotime($row['BillingDate'])) ?></div></td>
            <td><div style="font-weight:600;"><?= htmlspecialchars($row['Client']) ?></div></td>
            <td><?= htmlspecialchars($row['PetName'] ?? '—') ?></td>
            <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($row['Species'] ?? '—') ?></td>
            <td style="font-weight:700;">₱<?= number_format($row['TotalAmount'],2) ?></td>
            <td style="font-weight:600;color:var(--green);">₱<?= number_format($row['AmountPaid'],2) ?></td>
            <td style="font-weight:600;<?= $bal>0 ? 'color:var(--red);' : 'color:var(--light-muted);' ?>"><?= $bal>0 ? '₱'.number_format($bal,2) : '—' ?></td>
            <td style="font-size:12px;"><?= htmlspecialchars($row['PaymentMethod'] ?? '—') ?></td>
            <td><span class="badge-modern <?= $bClass ?>"><?= $row['PaymentStatus'] ?></span></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div></div></div></div>

<div class="row g-3 mb-4"><div class="col-12"><div class="card" style="background:linear-gradient(135deg,#0ea5e9,#10b981);border:none;"><div class="card-body" style="display:flex;align-items:center;justify-content:space-between;padding:20px 28px;flex-wrap:wrap;gap:16px;">
    <div>
        <div style="color:rgba(255,255,255,.7);font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Period Summary</div>
        <div style="color:#fff;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:26px;">₱<?= number_format($totalRevenue,2) ?> collected</div>
        <div style="color:rgba(255,255,255,.7);font-size:12px;margin-top:4px;"><?= $totalAppt ?> appts · <?= $totalBills ?> bills · <?= $collectionRate ?>% collected · <?= $totalPets ?> active pets</div>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="billing.php" class="btn-main" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3);"><i class="bi bi-receipt-cutoff"></i> Billing</a>
        <a href="appointments.php" class="btn-main" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.2);"><i class="bi bi-calendar-check"></i> Appointments</a>
        <a href="clients.php" class="btn-main" style="background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.15);"><i class="bi bi-people"></i> Clients</a>
    </div>
</div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
const teal = '#0ea5e9', green = '#10b981', amber = '#f59e0b', red = '#ef4444', purple = '#8b5cf6', indigo = '#6366f1', gridColor = '#f1f5f9', tickColor = '#94a3b8', tickFont = { size: 11 };

const makeChart = (id, type, data, options) => new Chart(document.getElementById(id), { type, data, options });
const baseScales = { y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, font: tickFont } }, x: { grid: { display: false }, ticks: { color: tickColor, font: tickFont } } };

// 1. Monthly Revenue
makeChart('monthlyRevenueChart', 'bar', {
    labels: <?= json_encode(array_column($revenueData,'label')) ?>,
    datasets: [
        { data: <?= json_encode(array_column($revenueData,'billed')) ?>, backgroundColor: '#e2e8f0', borderRadius: 6, borderSkipped: false },
        { data: <?= json_encode(array_column($revenueData,'collected')) ?>, backgroundColor: teal, borderRadius: 6, borderSkipped: false }
    ]
}, { responsive: true, plugins: { legend: { display: false } }, scales: { ...baseScales, y: { ...baseScales.y, ticks: { ...tickFont, callback: v => '₱' + v.toLocaleString() } } } });

// 2. Daily Revenue
makeChart('dailyRevenueChart', 'line', {
    labels: <?= json_encode(array_column($dailyRevData,'label')) ?>,
    datasets: [{ data: <?= json_encode(array_column($dailyRevData,'value')) ?>, borderColor: green, backgroundColor: 'rgba(16,185,129,.08)', borderWidth: 2.5, fill: true, tension: .4, pointBackgroundColor: green, pointRadius: 4, pointHoverRadius: 6 }]
}, { responsive: true, plugins: { legend: { display: false } }, scales: { ...baseScales, y: { ...baseScales.y, ticks: { ...tickFont, callback: v => '₱' + v.toLocaleString() } } } });

// 3. Appointment Status
const asLabels = <?= json_encode(array_column($apptStats, 'Status')) ?>;
makeChart('apptStatusChart', 'doughnut', {
    labels: asLabels,
    datasets: [{ data: <?= json_encode(array_map('intval', array_column($apptStats, 'cnt'))) ?>, backgroundColor: asLabels.map(l => ({'Completed':green,'Scheduled':teal,'Cancelled':red,'No-Show':amber,'Pending':'#94a3b8'}[l] || '#cbd5e1')), borderWidth: 0, hoverOffset: 6 }]
}, { responsive: true, cutout: '70%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } });

// 4. Appointment by Day
makeChart('apptDayChart', 'bar', {
    labels: <?= json_encode(array_map(fn($d) => substr($d, 0, 3), array_column($apptByDay, 'day'))) ?>,
    datasets: [{ data: <?= json_encode(array_map('intval', array_column($apptByDay, 'cnt'))) ?>, backgroundColor: indigo, borderRadius: 6, borderSkipped: false }]
}, { responsive: true, plugins: { legend: { display: false } }, scales: baseScales });

// 5. Payment Status
const psLabels = <?= json_encode(array_column($payStatus, 'PaymentStatus')) ?>;
makeChart('payStatusChart', 'doughnut', {
    labels: psLabels,
    datasets: [{ data: <?= json_encode(array_map('floatval', array_column($payStatus, 'total'))) ?>, backgroundColor: psLabels.map(l => ({'Paid':green,'Pending':amber,'Partial':teal}[l] || '#94a3b8')), borderWidth: 0, hoverOffset: 6 }]
}, { responsive: false, cutout: '65%', plugins: { legend: { display: false } } });

// 6. Species
makeChart('speciesChart', 'doughnut', {
    labels: <?= json_encode(array_column($species, 'Species')) ?>,
    datasets: [{ data: <?= json_encode(array_map('intval', array_column($species, 'cnt'))) ?>, backgroundColor: [teal, green, amber, purple, red, '#ec4899', '#94a3b8'].slice(0, <?= count($species) ?>), borderWidth: 0, hoverOffset: 6 }]
}, { responsive: false, cutout: '60%', plugins: { legend: { display: false } } });

// 7. New Clients
makeChart('newClientsChart', 'line', {
    labels: <?= json_encode(array_column($newClientData,'label')) ?>,
    datasets: [{ data: <?= json_encode(array_column($newClientData,'value')) ?>, borderColor: teal, backgroundColor: 'rgba(14,165,233,.08)', borderWidth: 2.5, fill: true, tension: .4, pointBackgroundColor: teal, pointRadius: 4, pointHoverRadius: 6 }]
}, { responsive: true, plugins: { legend: { display: false } }, scales: { ...baseScales, y: { ...baseScales.y, ticks: { stepSize: 1 } } } });
</script>

<?php include('footer.php'); ?>