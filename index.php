<?php
require_once('guard.php');
include('dbconnect.php');
include('header.php'); ?>

<?php
$totalClients   = $conn->query("SELECT COUNT(*) FROM clients WHERE IsActive=1")->fetch_row()[0];
$totalPets      = $conn->query("SELECT COUNT(*) FROM pets WHERE IsActive=1")->fetch_row()[0];
$todayAppt      = $conn->query("SELECT COUNT(*) FROM appointments WHERE AppointmentDate=CURDATE() AND COALESCE(IsDeleted,0)=0 AND Status NOT IN ('Cancelled','No-Show')")->fetch_row()[0];
$pendingBills   = $conn->query("SELECT COUNT(*) FROM billing WHERE PaymentStatus != 'Paid'")->fetch_row()[0];
$monthlyRevenue = $conn->query("SELECT COALESCE(SUM(AmountPaid),0) FROM billing WHERE MONTH(BillingDate)=MONTH(CURDATE()) AND YEAR(BillingDate)=YEAR(CURDATE())")->fetch_row()[0];
$activeLodging  = $conn->query("SELECT COUNT(*) FROM lodging WHERE Status='Active'")->fetch_row()[0];
$revenueToday   = $conn->query("SELECT COALESCE(SUM(AmountPaid),0) FROM billing WHERE BillingDate=CURDATE()")->fetch_row()[0];
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Dashboard</h1>
        <p>Welcome back — here's what's happening at the clinic today.</p>
    </div>
    <!--<div class="page-header-actions">
        <a href="appointments.php" class="btn-main btn-teal"><i class="bi bi-plus-lg"></i> New Appointment</a>
    </div>-->
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#eff6ff; color:#2563eb;"><i class="bi bi-people-fill"></i></div>
            <div class="stat-value"><?= $totalClients ?></div>
            <div class="stat-label">Total Clients</div>
            <div class="stat-trend up"><i class="bi bi-arrow-up-short"></i>+12% this month</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fdf2f8; color:#db2777;"><i class="bi bi-heart-fill"></i></div>
            <div class="stat-value"><?= $totalPets ?></div>
            <div class="stat-label">Registered Pets</div>
            <div class="stat-trend up"><i class="bi bi-arrow-up-short"></i>Active patients</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#eff6ff; color:#2563eb;"><i class="bi bi-calendar-check-fill"></i></div>
            <div class="stat-value"><?= $todayAppt ?></div>
            <div class="stat-label">Today's Appts</div>
            <div class="stat-trend up"><i class="bi bi-clock"></i> Scheduled</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fffbeb; color:#d97706;"><i class="bi bi-receipt-cutoff"></i></div>
            <div class="stat-value"><?= $pendingBills ?></div>
            <div class="stat-label">Pending Bills</div>
            <div class="stat-trend warn"><i class="bi bi-exclamation-circle"></i> Needs attention</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4; color:#16a34a;"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-value" style="font-size:20px;">₱<?= number_format($monthlyRevenue, 0) ?></div>
            <div class="stat-label">Monthly Revenue</div>
            <div class="stat-trend up"><i class="bi bi-arrow-up-short"></i>+18% vs last month</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f5f3ff; color:#7c3aed;"><i class="bi bi-house-heart-fill"></i></div>
            <div class="stat-value"><?= $activeLodging ?></div>
            <div class="stat-label">Boarding Now</div>
            <div class="stat-trend up"><i class="bi bi-house-check"></i> Active stays</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- Revenue Chart -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <span class="card-header-title">Weekly Revenue Trend</span>
                <span style="font-size:12px; color:var(--green); font-weight:600;"><i class="bi bi-arrow-up-short"></i> +22.4%</span>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="80"></canvas>
            </div>
        </div>
    </div>
    <!-- Patient Mix -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <span class="card-header-title">Patient Mix</span>
                <span style="font-size:11px; color:var(--muted);">This month</span>
            </div>
            <div class="card-body">
                <canvas id="mixChart" height="160"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Today's Appointments -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <span class="card-header-title"><i class="bi bi-calendar-check me-2" style="color:var(--teal);"></i>Today's Appointments</span>
                <a href="appointments.php" style="font-size:12px; color:var(--teal); text-decoration:none; font-weight:600;">View all →</a>
            </div>
            <div class="card-body p-0">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Pet & Owner</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT a.AppointmentDate, a.AppointmentTime, a.Reason, a.Status,
                                   p.PetName, CONCAT(c.FirstName,' ',c.LastName) AS Owner, s.ServiceName
                            FROM appointments a
                            JOIN pets p ON a.PetID = p.PetID
                            JOIN clients c ON a.ClientID = c.ClientID
                            LEFT JOIN services s ON a.ServiceID = s.ServiceID
                            WHERE a.AppointmentDate = CURDATE() AND a.Status NOT IN ('Cancelled','No-Show') AND COALESCE(a.IsDeleted,0)=0
                            ORDER BY a.AppointmentDate, a.AppointmentTime LIMIT 8";
                        $res = $conn->query($sql);
                        if ($res->num_rows == 0):
                        ?>
                            <tr class="empty-row">
                                <td colspan="4"><i class="bi bi-calendar-x" style="font-size:24px; display:block; margin-bottom:8px;"></i>No upcoming appointments</td>
                            </tr>
                        <?php endif;
                        while ($row = $res->fetch_assoc()):
                            $badgeMap = ['Scheduled'=>'badge-scheduled','Completed'=>'badge-completed','No-Show'=>'badge-noshow','Pending'=>'badge-pending'];
                            $statusBadge = $badgeMap[$row['Status']] ?? 'badge-pending';
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight:700; font-size:13px;"><?= date('h:i A', strtotime($row['AppointmentTime'])) ?></div>
                                    <div style="font-size:11px; color:var(--light-muted);"><?= date('M d', strtotime($row['AppointmentDate'])) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars($row['PetName']) ?></div>
                                    <div style="font-size:12px; color:var(--muted);"><?= htmlspecialchars($row['Owner']) ?></div>
                                </td>
                                <td><span style="font-size:12px; color:var(--muted);"><?= htmlspecialchars($row['ServiceName'] ?? $row['Reason']) ?></span></td>
                                <td><span class="badge-modern <?= $statusBadge ?>"><?= $row['Status'] ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Billing -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <span class="card-header-title"><i class="bi bi-receipt me-2" style="color:var(--green);"></i>Recent Billing</span>
                <a href="billing.php" style="font-size:12px; color:var(--teal); text-decoration:none; font-weight:600;">View all →</a>
            </div>
            <div class="card-body p-0">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT CONCAT(c.FirstName,' ',c.LastName) AS Client, b.TotalAmount, b.PaymentStatus, b.BillingDate
                            FROM billing b JOIN clients c ON b.ClientID = c.ClientID
                            ORDER BY b.CreatedAt DESC LIMIT 8";
                        $res = $conn->query($sql);
                        if ($res->num_rows == 0):
                        ?>
                            <tr class="empty-row">
                                <td colspan="3">No billing records</td>
                            </tr>
                        <?php endif;
                        while ($row = $res->fetch_assoc()):
                            $bClass = $row['PaymentStatus'] == 'Paid' ? 'badge-paid' : ($row['PaymentStatus'] == 'Pending' ? 'badge-pending' : 'badge-partial');
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars($row['Client']) ?></div>
                                    <div style="font-size:11px; color:var(--light-muted);"><?= date('M d, Y', strtotime($row['BillingDate'])) ?></div>
                                </td>
                                <td style="font-weight:700; font-size:14px;">₱<?= number_format($row['TotalAmount'], 2) ?></td>
                                <td><span class="badge-modern <?= $bClass ?>"><?= $row['PaymentStatus'] ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Revenue Today Banner -->
    <div class="col-12">
        <div class="card" style="background: linear-gradient(135deg, #0ea5e9, #10b981); border:none;">
            <div class="card-body" style="display:flex; align-items:center; justify-content:space-between; padding:20px 28px;">
                <div>
                    <div style="color:rgba(255,255,255,.7); font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Revenue Today</div>
                    <div style="color:#fff; font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; font-size:28px;">₱<?= number_format($revenueToday, 2) ?></div>
                    <div style="color:rgba(255,255,255,.7); font-size:12px; margin-top:4px;"><?= date('l, F d, Y') ?></div>
                </div>
                <!-- <div style="display:flex; gap:16px;">
                    <a href="billing.php" class="btn-main" style="background:rgba(255,255,255,.2); color:#fff; border:1px solid rgba(255,255,255,.3);">
                        <i class="bi bi-plus-lg"></i> Add Bill
                    </a>
                    <a href="reports.php" class="btn-main" style="background:rgba(255,255,255,.15); color:#fff; border:1px solid rgba(255,255,255,.2);">
                        <i class="bi bi-bar-chart-fill"></i> Analytics
                    </a>
                </div> -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
    // Revenue line chart
    const rLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const rData = [140, 0, 160, 200, 1000, 500, 0];
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: rLabels,
            datasets: [{
                label: 'Revenue (₱)',
                data: rData,
                borderColor: '#0ea5e9',
                backgroundColor: 'rgba(14,165,233,.08)',
                borderWidth: 2.5,
                fill: true,
                tension: .4,
                pointBackgroundColor: '#0ea5e9',
                pointRadius: 4,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f1f5f9'
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: {
                            size: 11
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });

    // Patient mix doughnut
    new Chart(document.getElementById('mixChart'), {
        type: 'bar',
        data: {
            labels: ['Dogs', 'Cats', 'Birds', 'Rabbits', 'Other'],
            datasets: [{
                data: [2, 3, 0, 0, 0],
                backgroundColor: ['#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#94a3b8'],
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f1f5f9'
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: {
                            size: 11
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
</script>

<?php include('footer.php'); ?>