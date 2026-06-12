<?php
require_once 'guard.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$userName     = isset($_SESSION['full_name'])  ? $_SESSION['full_name']  : 'Clinic Staff';
$userRole     = isset($_SESSION['role'])       ? $_SESSION['role']       : 'Staff';
$userInitials = '';
if (!empty($_SESSION['first_name']) && !empty($_SESSION['last_name'])) {
    $userInitials = strtoupper(substr($_SESSION['first_name'],0,1) . substr($_SESSION['last_name'],0,1));
} else {
    $userInitials = 'CS';
}

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

            /* ── Module theme colors ── */
            --clr-clients:       #3b82f6;
            --clr-clients-bg:    #eff6ff;
            --clr-pets:          #ec4899;
            --clr-pets-bg:       #fdf2f8;
            --clr-appointments:  #6366f1;
            --clr-appointments-bg:#eef2ff;
            --clr-consultations: #10b981;
            --clr-consultations-bg:#f0fdf4;
            --clr-billing:       #f97316;
            --clr-billing-bg:    #fff7ed;
            --clr-services:      #7c3aed;
            --clr-services-bg:   #f5f3ff;
            --clr-reports:       #ef4444;
            --clr-reports-bg:    #fef2f2;
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


        /* ══ UNIVERSAL VIEW-MODAL & TABLE STANDARDIZATION ══ */
        /* Field label (ALL view modals) */
        .vm-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: var(--muted); margin-bottom: 4px;
        }
        /* Field value (ALL view modals) */
        .vm-value {
            font-size: 14px; color: var(--text); font-weight: 500;
        }
        .vm-value-lg {
            font-size: 16px; font-weight: 700; color: var(--text);
        }
        .vm-value-id {
            font-size: 15px; font-weight: 700; color: var(--teal);
        }
        .vm-value-money {
            font-size: 14px; font-weight: 700; color: var(--green);
        }
        /* Header banner inside view modals */
        .vm-header-band {
            background: #f8fafc; border-left: 4px solid var(--teal);
            border-radius: 8px; padding: 14px 16px; margin-bottom: 16px;
        }
        /* Notes / pre-wrap block */
        .vm-notes-block {
            background: #f1f5f9; border-radius: 8px; padding: 12px 14px;
            font-size: 13px; color: var(--text); white-space: pre-wrap;
            min-height: 44px;
        }
        /* Table cell font normalisation (overrides scattered inline styles) */
        .modern-table tbody tr td { font-size: 13.5px; }
        .modern-table tbody tr td .cell-main  { font-weight: 700; font-size: 13.5px; color: var(--text); }
        .modern-table tbody tr td .cell-sub   { font-size: 11px;  color: var(--muted); margin-top: 1px; }
        .modern-table tbody tr td .cell-muted { font-size: 12px;  color: var(--muted); }
        .modal-body .row .col-6 .vm-label,
        .modal-body .row .col-12 .vm-label,
        .modal-body .row .col-md-4 .vm-label,
        .modal-body .row .col-md-6 .vm-label { margin-bottom: 4px; }
        /* ── Responsive ── */
        @media (max-width: 768px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.open { transform: translateX(0); }
            #topbar { left: 0 !important; }
            #main-wrapper { margin-left: 0 !important; }
            #main-content { padding: 16px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
        }

        /* ── Export Dropdown ── */
        .export-dropdown-wrap { position: relative; display: inline-flex; }
        /* ── Date range filter ── */
        .date-filter-form { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .date-filter-input {
            padding: 7px 10px; border: 1px solid var(--border); border-radius: 8px;
            font-size: 12.5px; background: var(--surface); color: var(--text);
            height: 36px;
        }
        .date-filter-sep { color: var(--light-muted); font-size: 12px; }
        .btn-date-filter, .btn-date-clear {
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border: 1px solid var(--border); background: var(--surface);
            border-radius: 8px; color: var(--muted); cursor: pointer; transition: all .15s; text-decoration: none;
        }
        .btn-date-filter:hover { background: var(--bg); color: var(--teal); border-color: #cbd5e1; }
        .btn-date-clear:hover  { background: var(--bg); color: var(--red); border-color: #cbd5e1; }
        .btn-export {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 14px; border-radius: 8px; font-size: 13px; font-weight: 600;
            border: 1.5px solid var(--border); background: #fff; color: var(--text);
            cursor: pointer; transition: all .15s; white-space: nowrap;
        }
        .btn-export:hover { background: var(--bg); border-color: #cbd5e1; transform: translateY(-1px); }
        .btn-export .chevron { font-size: 10px; margin-left: 2px; transition: transform .2s; }
        .btn-export.open .chevron { transform: rotate(180deg); }
        .export-menu {
            display: none; position: absolute; top: calc(100% + 6px); right: 0;
            background: #fff; border: 1px solid var(--border); border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,.10), 0 2px 8px rgba(0,0,0,.06);
            min-width: 180px; z-index: 9999; overflow: hidden; padding: 6px 0;
        }
        .export-menu.show { display: block; }
        .export-menu-label {
            font-size: 10px; font-weight: 700; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 1px;
            padding: 8px 14px 4px;
        }
        .export-menu-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 14px; font-size: 13px; font-weight: 500;
            color: var(--text); cursor: pointer; transition: background .1s;
            text-decoration: none;
        }
        .export-menu-item:hover { background: var(--bg); color: var(--text); }
        .export-menu-item .ei-icon {
            width: 28px; height: 28px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; flex-shrink: 0;
        }
        .ei-csv  { background: #f0fdf4; color: #16a34a; }
        .ei-xls  { background: #f0fdf4; color: #15803d; }
        .ei-xlsx { background: #f0fdf4; color: #166534; }
        .ei-docx { background: #eff6ff; color: #2563eb; }
        .ei-pdf  { background: #fef2f2; color: #dc2626; }

        /* Modal export dropdown (replaces print button) */
        .modal-export-wrap { position: relative; display: inline-flex; }
        .btn-modal-export {
            display: flex; align-items: center; gap: 5px;
            font-size: 12px; font-weight: 600; padding: 5px 12px;
            border-radius: 6px; border: 1px solid var(--border);
            background: #f8fafc; color: var(--dark); cursor: pointer;
            transition: all .15s; white-space: nowrap;
        }
        .btn-modal-export:hover { background: var(--bg); border-color: #cbd5e1; }
        .btn-modal-export .chevron { font-size: 9px; margin-left: 2px; transition: transform .2s; }
        .btn-modal-export.open .chevron { transform: rotate(180deg); }
        .modal-export-menu {
            display: none; position: absolute; top: calc(100% + 5px); right: 0;
            background: #fff; border: 1px solid var(--border); border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
            min-width: 170px; z-index: 10999; overflow: hidden; padding: 5px 0;
        }
        .modal-export-menu.show { display: block; }
        .modal-export-menu .export-menu-label { padding: 6px 12px 3px; }
        .modal-export-menu .export-menu-item  { padding: 7px 12px; font-size: 12px; }
        .modal-export-menu .ei-icon { width: 24px; height: 24px; font-size: 12px; }
    </style>
<script>
// ── Global Search ──────────────────────────────────────────────
let _sfActive = 'all', _searchTimer = null;

function setSF(f) {
    _sfActive = f;
    document.querySelectorAll('.sf-btn').forEach(b => b.classList.remove('active'));
    const btn = document.getElementById('sf-' + f);
    if (btn) btn.classList.add('active');
    const q = document.getElementById('globalSearch').value;
    if (q.length >= 2) runSearch(q);
}

function showDrop() { document.getElementById('searchDropdown').classList.add('show'); }
function hideDrop()  { document.getElementById('searchDropdown').classList.remove('show'); }

function runSearch(q) {
    clearTimeout(_searchTimer);
    if (q.length < 1) { hideDrop(); return; }
    _searchTimer = setTimeout(() => {
        fetch('search_ajax.php?q=' + encodeURIComponent(q) + '&filter=' + _sfActive)
            .then(r => r.json())
            .then(data => renderResults(data, q))
            .catch(() => {});
    }, 220);
}

function renderResults(data, q) {
    const box   = document.getElementById('searchResults');
    const empty = document.getElementById('searchEmpty');
    let html = '';

    // ── Highlight matched query text inside a string ──
    function hl(text, q) {
        if (!text) return '';
        const escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return String(text).replace(new RegExp(`(${escaped})`, 'gi'),
            `<mark style="background:#fef9c3;border-radius:2px;padding:0 1px;">$1</mark>`);
    }

    // ── ID: raw highlighted text (si-main carries bold+color) ──
    function idTag(id, q) { return hl(id, q); }

    // ── Status badge ──
    function statusBadge(s) {
        const map = {
            'Pending':     'badge-pending',
            'Partial':     'badge-partial',
            'Paid':        'badge-paid',
            'Scheduled':   'badge-scheduled',
            'Completed':   'badge-completed',
            'Cancelled':   'badge-cancelled',
            'No-Show':     'badge-noshow',
            'Active':      'badge-active',
            'Checked Out': 'badge-inactive',
        };
        const cls = map[s] || 'badge-inactive';
        return `<span class="badge-modern ${cls}" style="padding:2px 8px;font-size:10px;">${s}</span>`;
    }

    // ── CLIENTS ──
    if (data.clients && data.clients.length) {
        html += `<div class="search-section-label"><i class="bi bi-people-fill me-1"></i>Clients</div>`;
        data.clients.forEach(r => {
            html += `<a class="search-item" href="clients.php?highlight=${r.ClientID}">
                <div class="si-icon" style="background:#eff6ff;color:#3b82f6;"><i class="bi bi-person-fill"></i></div>
                <div>
                    <div class="si-main" style="color:var(--teal);">${idTag(r.client_id, q)} <span style="font-size:11px;color:var(--muted);">· ${hl(r.name, q)}</span></div>
                    <div class="si-sub">${hl(r.email ?? '', q)} ${r.phone ? '· ' + hl(r.phone, q) : ''}</div>
                </div>
            </a>`;
        });
    }

    // ── PETS ──
    if (data.pets && data.pets.length) {
        html += `<div class="search-section-label"><i class="bi bi-heart-fill me-1"></i>Pets</div>`;
        data.pets.forEach(r => {
            html += `<a class="search-item" href="pets.php?highlight=${r.PetID}">
                <div class="si-icon" style="background:#fdf2f8;color:#ec4899;"><i class="bi bi-heart-fill"></i></div>
                <div>
                    <div class="si-main" style="color:var(--teal);">${idTag(r.pet_id, q)} <span style="font-size:11px;color:var(--muted);">· ${hl(r.name, q)}</span></div>
                    <div class="si-sub">${hl(r.species, q)} · Owner: ${hl(r.owner, q)}</div>
                </div>
            </a>`;
        });
    }

    // ── APPOINTMENTS ──
    if (data.appointments && data.appointments.length) {
        html += `<div class="search-section-label"><i class="bi bi-calendar-check-fill me-1"></i>Appointments</div>`;
        data.appointments.forEach(r => {
            html += `<a class="search-item" href="appointments.php?highlight=${r.id}">
                <div class="si-icon" style="background:#eef2ff;color:#6366f1;"><i class="bi bi-calendar-check-fill"></i></div>
                <div>
                    <div class="si-main" style="color:var(--teal);">${idTag(r.appt_id, q)} <span style="font-size:11px;color:var(--muted);">· ${hl(r.pet, q)} · ${hl(r.owner, q)}</span></div>
                    <div class="si-sub">${r.date}${r.service ? ' · ' + hl(r.service, q) : ''} · ${statusBadge(r.status)}</div>
                </div>
            </a>`;
        });
    }

    // ── MEDICAL ──
    if (data.medical && data.medical.length) {
        html += `<div class="search-section-label"><i class="bi bi-clipboard2-pulse-fill me-1"></i>Medical Records</div>`;
        data.medical.forEach(r => {
            html += `<a class="search-item" href="consultations.php?highlight=${r.id}">
                <div class="si-icon" style="background:#f0fdf4;color:#10b981;"><i class="bi bi-clipboard2-pulse-fill"></i></div>
                <div>
                    <div class="si-main" style="color:var(--teal);">${idTag(r.con_id, q)} <span style="font-size:11px;color:var(--muted);">· ${hl(r.pet, q)}</span></div>
                    <div class="si-sub">${r.date} · Dr. ${hl(r.doctor, q)}${r.diagnosis ? ' · ' + hl(r.diagnosis, q) : ''}</div>
                </div>
            </a>`;
        });
    }

    // ── BILLING ──
    if (data.billing && data.billing.length) {
        html += `<div class="search-section-label"><i class="bi bi-receipt-cutoff me-1"></i>Billing</div>`;
        data.billing.forEach(r => {
            html += `<a class="search-item" href="billing.php?highlight=${r.BillingID}">
                <div class="si-icon" style="background:#fff7ed;color:#f97316;"><i class="bi bi-receipt-cutoff"></i></div>
                <div>
                    <div class="si-main" style="color:var(--teal);">${idTag(r.invoice, q)} <span style="font-size:11px;color:var(--muted);">· ${hl(r.client, q)}</span></div>
                    <div class="si-sub">${r.date} · ₱${parseFloat(r.TotalAmount).toLocaleString()} ${statusBadge(r.PaymentStatus)}</div>
                </div>
            </a>`;
        });
    }

    // ── LODGING ──
    if (data.lodging && data.lodging.length) {
        html += `<div class="search-section-label"><i class="bi bi-house-heart-fill me-1"></i>Lodging</div>`;
        data.lodging.forEach(r => {
            html += `<a class="search-item" href="services.php?tab=lodging&highlight=${r.LodgingID}">
                <div class="si-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="bi bi-house-heart-fill"></i></div>
                <div>
                    <div class="si-main" style="color:var(--teal);">${idTag(r.ldg_id, q)} <span style="font-size:11px;color:var(--muted);">· ${hl(r.pet, q)} · ${hl(r.owner, q)}</span></div>
                    <div class="si-sub">Cage ${hl(r.CageNumber, q)} · Check-in: ${r.checkin} · ${statusBadge(r.Status)}</div>
                </div>
            </a>`;
        });
    }

    // ── SERVICES ──
    if (data.services && data.services.length) {
        html += `<div class="search-section-label"><i class="bi bi-box-seam-fill me-1"></i>Services</div>`;
        data.services.forEach(r => {
            html += `<a class="search-item" href="services.php?tab=services&highlight=${r.id}">
                <div class="si-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="bi bi-box-seam-fill"></i></div>
                <div>
                    <div class="si-main" style="color:var(--teal);">${idTag(r.svc_id, q)} <span style="font-size:11px;color:var(--muted);">· ${hl(r.name, q)}</span></div>
                    <div class="si-sub">${hl(r.category, q)} · ₱${parseFloat(r.price).toLocaleString()}</div>
                </div>
            </a>`;
        });
    }

    const hasResults = html.length > 0;
    box.innerHTML = html;
    empty.style.display = hasResults ? 'none' : 'block';
    showDrop();
}

// Close dropdown when clicking outside
document.addEventListener('click', e => {
    if (!document.querySelector('.topbar-search').contains(e.target)) hideDrop();
});
</script>



<!-- Sidebar -->
<nav id="sidebar">
    <a href="index.php" class="sidebar-brand"> <!-- Route back to welcome page upon clicking logo -->
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
        <a href="clients.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='clients.php'?'active':'' ?>" style="<?= basename($_SERVER['PHP_SELF'])=='clients.php' ? 'color:#3b82f6;background:#3b82f618;' : '' ?>">
            <i class="bi bi-people-fill" style="color:#3b82f6;"></i> Clients / Owners
        </a>
        <a href="pets.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='pets.php'?'active':'' ?>" style="<?= basename($_SERVER['PHP_SELF'])=='pets.php' ? 'color:#ec4899;background:#ec489918;' : '' ?>">
            <i class="bi bi-heart-fill" style="color:#ec4899;"></i> Patients / Pets
        </a>
        <a href="appointments.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='appointments.php'?'active':'' ?>" style="<?= basename($_SERVER['PHP_SELF'])=='appointments.php' ? 'color:#6366f1;background:#6366f118;' : '' ?>">
            <i class="bi bi-calendar-check-fill" style="color:#6366f1;"></i> Appointments
        </a>
        <a href="consultations.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='consultations.php'?'active':'' ?>" style="<?= basename($_SERVER['PHP_SELF'])=='consultations.php' ? 'color:#10b981;background:#10b98118;' : '' ?>">
            <i class="bi bi-clipboard2-pulse-fill" style="color:#10b981;"></i> Consultations & Medical Records
        </a>
        <a href="billing.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='billing.php'?'active':'' ?>" style="<?= basename($_SERVER['PHP_SELF'])=='billing.php' ? 'color:#f97316;background:#f9731618;' : '' ?>">
            <i class="bi bi-receipt-cutoff" style="color:#f97316;"></i> Billing & Payments
        </a>
        <a href="services.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='services.php'?'active':'' ?>" style="<?= basename($_SERVER['PHP_SELF'])=='services.php' ? 'color:#7c3aed;background:#7c3aed18;' : '' ?>">
            <i class="bi bi-box-seam-fill" style="color:#7c3aed;"></i> Services & Lodging
        </a>
        <a href="reports.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF'])=='reports.php'?'active':'' ?>" style="<?= basename($_SERVER['PHP_SELF'])=='reports.php' ? 'color:#ef4444;background:#ef444418;' : '' ?>">
            <i class="bi bi-bar-chart-fill" style="color:#ef4444;"></i> Reports & Analytics
        </a>
    </div>

    <div class="sidebar-footer">
    <div class="clinic-status">
        <div class="status-dot"></div>
        <div><strong>Open</strong><span> · 9AM–7PM</span></div>
    </div>
    <div class="sidebar-user">
        <div class="sidebar-avatar"><?= $userInitials ?></div>
        <div style="flex:1;min-width:0;">
            <div class="sidebar-user-name"><?= htmlspecialchars($userName) ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($userRole) ?></div>
        </div>
        <a href="logout.php" title="Sign Out" style="
            display:flex;align-items:center;justify-content:center;
            width:28px;height:28px;border-radius:7px;flex-shrink:0;
            color:#64748b;text-decoration:none;transition:all .15s;
            background:rgba(0,0,0,.04);
        " onmouseover="this.style.background='#fee2e2';this.style.color='#ef4444';"
           onmouseout="this.style.background='rgba(0,0,0,.04)';this.style.color='#64748b';">
            <i class="bi bi-box-arrow-right" style="font-size:14px;"></i>
        </a>
    </div>
</div>
</nav>

<!-- Top Header -->
<header id="topbar">
    <button class="hamburger" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
    </button>
    <!-- SEARCH BAR -->
    <div class="topbar-search">
        <i class="bi bi-search"></i>
        <input type="text" id="globalSearch" placeholder="Search pets, owners, records..." autocomplete="off"
               oninput="runSearch(this.value)" onfocus="if(this.value.length>0)showDrop()" onblur="setTimeout(hideDrop,200)">
        <div id="searchDropdown">
            <div class="search-filter-bar">
                <button class="sf-btn active" id="sf-all"          onclick="setSF('all')">All</button>
                <button class="sf-btn"        id="sf-clients"      onclick="setSF('clients')">Clients</button>
                <button class="sf-btn"        id="sf-pets"         onclick="setSF('pets')">Pets</button>
                <button class="sf-btn"        id="sf-appointments" onclick="setSF('appointments')">Appointments</button>
                <button class="sf-btn"        id="sf-medical"      onclick="setSF('medical')">Medical</button>
                <button class="sf-btn"        id="sf-billing"      onclick="setSF('billing')">Billing</button>
                <button class="sf-btn"        id="sf-lodging"      onclick="setSF('lodging')">Lodging</button>
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
<?php