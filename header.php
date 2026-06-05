<?php
$userRole     = 'Admin';
$userName     = 'Clinic Staff';
$userInitials = 'CS';

// Detect if we are rendering the welcome page to toggle layout structure
$isWelcomePage = (basename($_SERVER['PHP_SELF']) == 'welcome.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Heartside Vet - Clinic Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-w: 260px;
            --header-h: 64px;
            --teal:      #0ea5e9;
            --teal-dark: #0284c7;
            --teal-xd:   #0369a1;
            --green:     #10b981;
            --amber:     #f59e0b;
            --red:       #ef4444;
            --purple:    #8b5cf6;
            --indigo:    #6366f1;
            --bg:        #f0f4f8;
            --surface:   #ffffff;
            --sidebar-bg:#ffffff;
            --sidebar-hover: rgba(255,255,255,.06);
            --sidebar-active: rgba(14,165,233,.15);
            --border:    #e2e8f0;
            --text:      #0f172a;
            --muted:     #64748b;
            --light-muted:#94a3b8;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            --shadow:    0 4px 16px rgba(0,0,0,.08);
            --shadow-lg: 0 10px 40px rgba(0,0,0,.12);
            --radius:    12px;
            --radius-sm: 8px;
            --radius-lg: 16px;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Welcome Page View Alignment Context */
        .welcome-layout-body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        /* ── Sidebar ── */
        #sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-w); height: 100vh;
            background: var(--sidebar-bg);
            display: flex; flex-direction: column;
            z-index: 1000;
            transition: transform .3s cubic-bezier(.4,0,.2,1);
            overflow: hidden;
        }
        #sidebar.collapsed { transform: translateX(-100%); }

       .sidebar-brand {
            display: flex; align-items: center; gap: 10px;
            padding: 0 20px;
            height: var(--header-h);
            border-bottom: 1px solid var(--border);
            border-right: 1px solid var(--border);
            text-decoration: none;
            flex-shrink: 0;
        }
        .brand-name { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 15px; color: #0f172a; letter-spacing: -.3px; }
        .brand-sub  { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }

        .sidebar-nav { flex: 1; padding: 12px 10px; overflow-y: auto; }
        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 4px; }
        .nav-section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: #94a3b8; padding: 14px 12px 6px; }
        .nav-item-link {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 12px; border-radius: 8px;
            color: #475569; font-size: 13.5px; font-weight: 500;
            text-decoration: none; transition: background .15s, color .15s; margin-bottom: 2px;
        }
        .nav-item-link i { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }
        .nav-item-link:hover { background: rgba(173, 177, 179, 0.1); color: #594f4f; }
        .nav-item-link.active { background: rgba(14,165,233,.1);; color: var(--teal); font-weight: 600; }
        .nav-item-link .badge-dot {
            margin-left: auto; width: 18px; height: 18px;
            background: var(--teal); border-radius: 50%;
            font-size: 10px; font-weight: 700; color: #080101;
            display: flex; align-items: center; justify-content: center;
        }

        .sidebar-footer { padding: 14px 16px; border-top: 1px solid rgba(255,255,255,.06); }
        .sidebar-user {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 10px; background: #f1f5f9; border-radius: 8px;
        }
        .sidebar-avatar {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, var(--teal), var(--indigo));
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; color: #fff; flex-shrink: 0;
        }
        .sidebar-user-name { font-size: 12px; font-weight: 600; color: #0f172a; }
        .sidebar-user-role { font-size: 10px; color: #64748b; }
        .sidebar-logout {
            margin-left: auto; width: 26px; height: 26px;
            background: rgba(255,255,255,.06); border: none; border-radius: 6px;
            color: rgb(0, 0, 0); cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-size: 13px;
            text-decoration: none; transition: all .15s;
        }
        .sidebar-logout:hover { background: rgba(238, 235, 235, 0.2); color: #ef4444; }
        .clinic-status {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 10px; background: #f8fafc; border-radius: 8px;
            margin-bottom: 10px;
        }
        .status-dot { width: 7px; height: 7px; background: var(--green); border-radius: 50%; box-shadow: 0 0 0 2px rgba(16,185,129,.25); }
        .clinic-status span { font-size: 12px; color: #64748b; }
        .clinic-status strong { color: #0f172a; font-size: 12px; }

        /* ── Topbar ── */
        #topbar {
            position: fixed; top: 0; left: var(--sidebar-w); right: 0;
            height: var(--header-h); background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; padding: 0 24px; gap: 12px;
            z-index: 900; transition: left .3s cubic-bezier(.4,0,.2,1);
            }
            #topbar.full { left: 0; }

        .hamburger {
            width: 36px; height: 36px; border: none; background: transparent; border-radius: 8px;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
            cursor: pointer; transition: background .15s; flex-shrink: 0;
        }
        .hamburger:hover { background: var(--bg); }
        .hamburger span { display: block; width: 18px; height: 2px; background: var(--muted); border-radius: 2px; transition: all .25s; }

        .topbar-title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: 16px; color: var(--text); }
        .topbar-sub   { font-size: 12px; color: var(--light-muted); }

        /* ── Search ── */
        .topbar-search { flex: 1; max-width: 380px; position: relative; margin: 0 auto 0 16px; }
        .topbar-search > i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--light-muted); font-size: 14px; pointer-events: none; }
        .topbar-search input {
            width: 100%; padding: 8px 12px 8px 36px;
            border: 1px solid var(--border); border-radius: 8px;
            font-size: 13px; background: var(--bg); color: var(--text);
            outline: none; transition: border-color .15s, box-shadow .15s;
        }
        .topbar-search input:focus { border-color: var(--teal); box-shadow: 0 0 0 3px rgba(14,165,233,.1); background: #fff; }

        /* Search dropdown */
        #searchDropdown {
            position: absolute; top: calc(100% + 6px); left: 0; right: 0;
            background: #fff; border: 1px solid var(--border); border-radius: 12px;
            box-shadow: var(--shadow-lg); z-index: 9999; max-height: 380px;
            overflow-y: auto; display: none;
        }
        #searchDropdown.show { display: block; }
        .search-section-label {
            padding: 8px 14px 4px;
            font-size: 10px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .6px; color: var(--light-muted);
            border-bottom: 1px solid #f1f5f9;
        }
        .search-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; cursor: pointer; transition: background .1s;
            font-size: 13px; color: var(--text); text-decoration: none;
        }
        .search-item:hover { background: #f8fafc; color: var(--text); }
        .search-item .si-icon {
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0;
        }
        .search-item .si-main { font-weight: 600; font-size: 13px; }
        .search-item .si-sub  { font-size: 11px; color: var(--muted); }
        .search-filter-bar {
            display: flex; gap: 4px; padding: 8px 14px;
            border-bottom: 1px solid #f1f5f9; background: #fafcff;
        }
        .sf-btn {
            padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
            border: 1px solid var(--border); background: #fff; cursor: pointer; color: var(--muted);
            transition: all .15s;
        }
        .sf-btn.active { background: var(--teal); color: #fff; border-color: var(--teal); }
        #searchEmpty { padding: 28px; text-align: center; color: var(--light-muted); font-size: 13px; }

        /* ── Topbar actions ── */
        .topbar-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; }
        .icon-btn {
            width: 36px; height: 36px; border: 1px solid var(--border); background: var(--surface);
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 15px; color: var(--muted); text-decoration: none;
            transition: all .15s; position: relative;
        }
        .icon-btn:hover { background: var(--bg); color: var(--text); }
        .icon-btn .notif-badge {
            position: absolute; top: -4px; right: -4px;
            width: 16px; height: 16px; background: var(--red);
            border-radius: 50%; font-size: 9px; font-weight: 700; color: #fff;
            display: flex; align-items: center; justify-content: center; border: 2px solid #fff;
        }
        .btn-primary-action {
            display: flex; align-items: center; gap: 6px; padding: 8px 14px;
            background: var(--teal); color: #fff; border: none; border-radius: 8px;
            font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
            transition: all .15s;
        }
        .btn-primary-action:hover { background: var(--teal-dark); color: #fff; transform: translateY(-1px); }
        .avatar-btn {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--teal), var(--indigo));
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; color: #fff; cursor: pointer; border: none;
        }

        /* ── Main Wrapper ── */
        #main-wrapper { margin-left: var(--sidebar-w); padding-top: var(--header-h); min-height: 100vh; transition: margin-left .3s; }
        #main-wrapper.full { margin-left: 0; }
        #main-content { padding: 28px; }

        /* ── Page Header ── */
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .page-header-left h1 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 22px; font-weight: 800; color: var(--text); margin: 0; }
        .page-header-left p  { font-size: 13px; color: var(--muted); margin: 2px 0 0; }
        .page-header-actions { display: flex; gap: 8px; align-items: center; }

        /* ── Cards ── */
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); }
        .card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; border-radius: var(--radius) var(--radius) 0 0 !important; background: var(--surface) !important; }
        .card-header-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 700; color: var(--text); }
        .card-body { padding: 20px; }
        .card-body.p-0 { padding: 0; }

        /* ── Stat Cards ── */
        .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow-sm); transition: all .2s; }
        .stat-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
        .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 14px; }
        .stat-value { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 26px; font-weight: 800; color: var(--text); line-height: 1; }
        .stat-label { font-size: 12px; color: var(--muted); margin-top: 4px; font-weight: 500; }
        .stat-trend { font-size: 11px; font-weight: 600; margin-top: 8px; }
        .stat-trend.up { color: var(--green); }
        .stat-trend.warn { color: var(--amber); }

        /* ── Tables ── */
        .modern-table { width: 100%; border-collapse: collapse; }
        .modern-table thead tr th { padding: 11px 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: var(--light-muted); background: #f8fafc; border-bottom: 1px solid var(--border); white-space: nowrap; }
        .modern-table tbody tr td { padding: 13px 16px; font-size: 13.5px; border-bottom: 1px solid #f1f5f9; color: var(--text); vertical-align: middle; }
        .modern-table tbody tr:last-child td { border-bottom: none; }
        .modern-table tbody tr:hover td { background: #f8fafc; }
        .modern-table .empty-row td { text-align: center; padding: 40px; color: var(--light-muted); font-size: 13px; }

        /* ── Badges ── */
        .badge-modern { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 600; line-height: 1; }
        .badge-modern::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
        .badge-scheduled   { background: #eff6ff; color: #2563eb; }
        .badge-completed   { background: #f0fdf4; color: #16a34a; }
        .badge-cancelled   { background: #fef2f2; color: #dc2626; }
        .badge-noshow      { background: #fef9c3; color: #b45309; }
        .badge-pending     { background: #f9ebff; color: #c406d9; }
        .badge-paid        { background: #f0fdf4; color: #16a34a; }
        .badge-partial     { background: #eff6ff; color: #2563eb; }
        .badge-active      { background: #f0fdf4; color: #16a34a; }
        .badge-inactive    { background: #f1f5f9; color: #64748b; }
        .badge-boarding    { background: #f5f3ff; color: #7c3aed; }
        .cat-badge { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; background: #f1f5f9; color: var(--muted); }

        /* ── Buttons ── */
        .btn-main { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; transition: all .15s; }
        .btn-main:hover { transform: translateY(-1px); }
        .btn-teal     { background: var(--teal); color: #fff; }
        .btn-teal:hover { background: var(--teal-dark); color: #fff; }
        .btn-green   { background: var(--green); color: #fff; }
        .btn-green:hover { background: #059669; color: #fff; }
        .btn-amber   { background: var(--amber); color: #fff; }
        .btn-amber:hover { background: #d97706; color: #fff; }
        .btn-red     { background: var(--red); color: #fff; }
        .btn-red:hover { background: #dc2626; color: #fff; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--muted); }
        .btn-outline:hover { background: var(--bg); color: var(--text); }
        .btn-icon { width: 32px; height: 32px; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; border: 1px solid transparent; cursor: pointer; text-decoration: none; transition: all .15s; }
        .btn-icon-edit   { background: #fffbeb; color: var(--amber); border-color: #fde68a; }
        .btn-icon-edit:hover   { background: #fef3c7; }
        .btn-icon-delete { background: #fef2f2; color: var(--red); border-color: #fecaca; }
        .btn-icon-delete:hover { background: #fee2e2; }
        .btn-icon-view   { background: #eff6ff; color: var(--teal); border-color: #bfdbfe; }
        .btn-icon-view:hover   { background: #dbeafe; }
        .btn-icon-green  { background: #f0fdf4; color: var(--green); border-color: #bbf7d0; }
        .btn-icon-green:hover  { background: #dcfce7; }

        /* ── Modals ── */
        .modal-content { border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); }
        .modal-header { padding: 20px 24px 16px; border-bottom: 1px solid var(--border); background: var(--surface) !important; border-radius: var(--radius-lg) var(--radius-lg) 0 0; }
        .modal-title  { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: 16px; color: var(--text); }
        .modal-body   { padding: 20px 24px; }
        .modal-footer { padding: 14px 24px; border-top: 1px solid var(--border); }

        /* ── Forms ── */
        .form-label { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px; }
        .form-control, .form-select { border: 1px solid var(--border); border-radius: 8px; padding: 9px 12px; font-size: 13.5px; color: var(--text); background: var(--bg); transition: border-color .15s, box-shadow .15s; outline: none; }
        .form-control:focus, .form-select:focus { border-color: var(--teal); box-shadow: 0 0 0 3px rgba(14,165,233,.12); background: #fff; }
        textarea.form-control { resize: vertical; }

        /* ── Alerts ── */
        .alert-modern { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 20px; }
        .alert-success-modern { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .alert-danger-modern   { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }

        /* ── Overlay ── */
        #sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 999; }
        #sidebar-overlay.show { display: block; }

        /* ── Nav tabs ── */
        .nav-tabs-modern { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 20px; }
        .nav-tab { padding: 9px 18px; border-radius: 8px 8px 0 0; font-size: 13px; font-weight: 600; color: var(--muted); cursor: pointer; text-decoration: none; transition: all .15s; border: 1px solid transparent; border-bottom: none; background: transparent; }
        .nav-tab:hover { color: var(--text); background: var(--bg); }
        .nav-tab.active { color: var(--teal); background: var(--surface); border-color: var(--border); border-bottom-color: var(--surface); margin-bottom: -1px; }

        /* ── Search highlight row ── */
        .search-highlight-row { background: #e7e77a !important; outline: 2px solid #dddbcec0; outline-offset: -2px; transition: background 0.6s ease, outline 0.6s ease; }
        .search-highlight-row td { background: #e9dc7c41 !important; transition: background 0.6s ease; }

        /* ── Appointment modal: current status display ── */
        .current-status-display {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
        }
        .current-status-label {
            font-size: 0.85rem;
            color: var(--muted);
            font-weight: 500;
            white-space: nowrap;
        }
        .btn-danger-outline {
            background: transparent;
            border: 1px solid #fecaca;
            color: var(--red);
        }
        .btn-danger-outline:hover { background: #fef2f2; color: var(--red); }
        input[type="date"]:disabled,
        select:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            background: #f8fafc;
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.open { transform: translateX(0); }
            #topbar { left: 0 !important; }
            #main-wrapper { margin-left: 0 !important; }
            #main-content { padding: 16px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
        }
    </style>
</head>
<body class="<?= $isWelcomePage ? 'welcome-layout-body' : '' ?>"> <!-- Dynamically inject styling hook if loading welcome page -->

<?php if (!$isWelcomePage): ?> <!-- Safely hide operational dashboard views if on welcome page -->
<div id="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<nav id="sidebar">
    <a href="welcome.php" class="sidebar-brand"> <!-- Route back to welcome page upon clicking logo -->
        <div class="brand-icon">
            <img src="logo1.png" alt="PawCare Logo" style="width: 38px; height: 38px; object-fit: contain;">
        </div>
        <div class="sidebar-brand-text">
            <div class="brand-name">Heartside Vet</div>
            <div class="brand-sub">Clinic Portal</div>
        </div>
    </a>

    <div class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <a href="index.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='index.php'?'active':'' ?>">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>

        <div class="nav-section-label">Modules</div>
        <a href="clients.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='clients.php'?'active':'' ?>">
            <i class="bi bi-people-fill"></i> Clients / Owners
        </a>
        <a href="pets.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='pets.php'?'active':'' ?>">
            <i class="bi bi-heart-fill"></i> Patients / Pets
        </a>
        <a href="appointments.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='appointments.php'?'active':'' ?>">
            <i class="bi bi-calendar-check-fill"></i> Appointments
        </a>
        <a href="consultations.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='consultations.php'?'active':'' ?>">
            <i class="bi bi-clipboard2-pulse-fill"></i> Medical Records
        </a>
        <a href="billing.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='billing.php'?'active':'' ?>">
            <i class="bi bi-receipt-cutoff"></i> Billing & Payments
        </a>
        <a href="services.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='services.php'?'active':'' ?>">
            <i class="bi bi-box-seam-fill"></i> Services & Lodging
        </a>
        <a href="reports.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='reports.php'?'active':'' ?>">
            <i class="bi bi-bar-chart-fill"></i> Reports & Analytics
        </a>
    </div>

    <div class="sidebar-footer">
    <div class="clinic-status">
        <div class="status-dot"></div>
        <div><strong>Open</strong><span> · 9AM–7PM</span></div>
    </div>
    <div class="sidebar-user">
        <div class="sidebar-avatar"><?= $userInitials ?></div>
        <div>
            <div class="sidebar-user-name"><?= htmlspecialchars($userName) ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($userRole) ?></div>
        </div>
        <a href="logout.php" class="sidebar-logout" title="Logout" style="display:none;"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</div>
</nav>

<!-- Top Header -->
<header id="topbar">
    <button class="hamburger" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
    </button>
    <div>
        <div class="topbar-title"><?php
            $titles = ['index.php'=>'Dashboard','clients.php'=>'Clients / Owners','pets.php'=>'Patients / Pets','appointments.php'=>'Appointments','consultations.php'=>'Medical Records','billing.php'=>'Billing & Payments','services.php'=>'Services & Lodging','reports.php'=>'Analytics'];
            echo $titles[basename($_SERVER['PHP_SELF'])] ?? 'PawCare Vet';
        ?></div>
        <div class="topbar-sub"><?= date('l, F d, Y') ?></div>
    </div>

    <!-- SEARCH BAR -->
    <div class="topbar-search">
        <i class="bi bi-search"></i>
        <input type="text" id="globalSearch" placeholder="Search pets, owners, records..." autocomplete="off"
               oninput="runSearch(this.value)" onfocus="if(this.value.length>1)showDrop()" onblur="setTimeout(hideDrop,200)">
        <div id="searchDropdown">
            <div class="search-filter-bar">
                <button class="sf-btn active" id="sf-all"          onclick="setSF('all')">All</button>
                <button class="sf-btn"        id="sf-clients"      onclick="setSF('clients')">Clients</button>
                <button class="sf-btn"        id="sf-pets"         onclick="setSF('pets')">Pets</button>
                <button class="sf-btn"        id="sf-appointments" onclick="setSF('appointments')">Appointments</button>
                <button class="sf-btn"        id="sf-medical"      onclick="setSF('medical')">Medical</button>
                <button class="sf-btn"        id="sf-billing"      onclick="setSF('billing')">Billing</button>
                <button class="sf-btn"        id="sf-services"     onclick="setSF('services')">Services</button>
            </div>
            <div id="searchResults"></div>
            <div id="searchEmpty" style="display:none;"><i class="bi bi-search" style="font-size:24px;display:block;margin-bottom:8px;"></i>No results found</div>
        </div>
    </div>

    <div class="topbar-actions">
        <div class="icon-btn" title="Notifications"><i class="bi bi-bell"></i><span class="notif-badge">3</span></div>
        <button class="avatar-btn" title="<?= htmlspecialchars($userName) ?>"><?= $userInitials ?></button>
    </div>
</header>

<div id="main-wrapper">
<div id="main-content">
<?php endif; ?>