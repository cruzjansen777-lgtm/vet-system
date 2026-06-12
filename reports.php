<?php
require_once 'guard.php';
include('dbconnect.php');
date_default_timezone_set('Asia/Manila');

// ── EXPORT HELPERS (CSV / XLS / XLSX / DOCX) ──
function reports_export_data($conn, $target, $dateFrom, $dateTo) {
    $headers = [];
    $rows    = [];

    switch ($target) {
        case 'clients':
            $headers = ['Client ID', 'Full Name', 'Email', 'Phone', 'Address', 'Date Registered'];
            $res = $conn->query("SELECT ClientID,
                CONCAT(FirstName,' ',LastName) AS FullName,
                Email, Phone, Address,
                DATE_FORMAT(CreatedAt,'%Y-%m-%d') AS DateRegistered
                FROM clients WHERE IsDeleted=0 ORDER BY CreatedAt DESC");
            $rows = $res->fetch_all(MYSQLI_NUM);
            break;

        case 'pets':
            $headers = ['Pet ID', 'Pet Name', 'Owner', 'Species', 'Breed', 'Gender', 'Date of Birth', 'Weight (kg)'];
            $res = $conn->query("SELECT p.PetID, p.PetName,
                CONCAT(c.FirstName,' ',c.LastName) AS Owner,
                p.Species, p.Breed, p.Gender, p.DateOfBirth, p.Weight
                FROM pets p JOIN clients c ON p.ClientID=c.ClientID
                WHERE p.IsDeleted=0 ORDER BY p.PetName");
            $rows = $res->fetch_all(MYSQLI_NUM);
            break;

        case 'appointments':
            $headers = ['ID', 'Date', 'Time', 'Pet', 'Owner', 'Service', 'Reason', 'Status'];
            $res = $conn->query("SELECT a.AppointmentID,
                a.AppointmentDate, a.AppointmentTime,
                p.PetName,
                CONCAT(c.FirstName,' ',c.LastName) AS Owner,
                COALESCE(s.ServiceName, a.SavedServiceName,'—') AS Service,
                a.Reason, a.Status
                FROM appointments a
                JOIN pets p ON a.PetID=p.PetID
                JOIN clients c ON a.ClientID=c.ClientID
                LEFT JOIN services s ON a.ServiceID=s.ServiceID
                WHERE a.IsDeleted=0
                AND a.AppointmentDate BETWEEN '$dateFrom' AND '$dateTo'
                ORDER BY a.AppointmentDate DESC, a.AppointmentTime DESC");
            $rows = $res->fetch_all(MYSQLI_NUM);
            break;

        case 'consultations':
            $headers = ['Consult ID', 'Date', 'Pet', 'Owner', 'Veterinarian', 'Chief Complaint', 'Diagnosis', 'Treatment', 'Prescription', 'Follow-Up'];
            $res = $conn->query("SELECT con.ConsultationID,
                DATE_FORMAT(con.ConsultationDate,'%Y-%m-%d') AS Date,
                p.PetName,
                CONCAT(c.FirstName,' ',c.LastName) AS Owner,
                con.VetName, con.ChiefComplaint, con.Diagnosis,
                con.Treatment, con.Prescription,
                COALESCE(DATE_FORMAT(con.FollowUpDate,'%Y-%m-%d'),'—') AS FollowUp
                FROM consultations con
                JOIN pets p ON con.PetID=p.PetID
                JOIN clients c ON con.ClientID=c.ClientID
                WHERE con.IsDeleted=0
                AND DATE(con.ConsultationDate) BETWEEN '$dateFrom' AND '$dateTo'
                ORDER BY con.ConsultationDate DESC");
            $rows = $res->fetch_all(MYSQLI_NUM);
            break;

        case 'billing':
            $headers = ['Bill ID', 'Date', 'Client', 'Pet', 'Total', 'Paid', 'Balance', 'Method', 'Status', 'Notes'];
            $res = $conn->query("SELECT b.BillingID,
                b.BillingDate,
                CONCAT(c.FirstName,' ',c.LastName) AS Client,
                COALESCE(p.PetName,'—') AS Pet,
                b.TotalAmount, b.AmountPaid,
                (b.TotalAmount - b.AmountPaid) AS Balance,
                b.PaymentMethod, b.PaymentStatus, b.Notes
                FROM billing b
                JOIN clients c ON b.ClientID=c.ClientID
                LEFT JOIN pets p ON b.PetID=p.PetID
                WHERE b.IsDeleted=0
                AND b.BillingDate BETWEEN '$dateFrom' AND '$dateTo'
                ORDER BY b.BillingDate DESC");
            $rows = $res->fetch_all(MYSQLI_NUM);
            break;

        case 'services':
            $headers = ['Service ID', 'Name', 'Category', 'Price', 'Duration (min)', 'Description', 'Status'];
            $res = $conn->query("SELECT ServiceID, ServiceName, Category,
                Price, Duration, Description,
                IF(IsActive=1,'Active','Inactive') AS Status
                FROM services WHERE IsDeleted=0 ORDER BY ServiceName");
            $rows = $res->fetch_all(MYSQLI_NUM);
            break;

        case 'lodging':
            $headers = ['Lodging ID', 'Pet', 'Owner', 'Check-In', 'Check-Out', 'Cage #', 'Daily Rate', 'Status', 'Instructions'];
            $res = $conn->query("SELECT l.LodgingID,
                p.PetName,
                CONCAT(c.FirstName,' ',c.LastName) AS Owner,
                l.CheckInDate, l.CheckOutDate,
                l.CageNumber, l.DailyRate, l.Status, l.SpecialInstructions
                FROM lodging l
                JOIN pets p ON l.PetID=p.PetID
                JOIN clients c ON l.ClientID=c.ClientID
                WHERE l.IsDeleted=0 ORDER BY l.CheckInDate DESC");
            $rows = $res->fetch_all(MYSQLI_NUM);
            break;

        case 'analytics':
            $headers = ['Metric', 'Value'];
            $k = $conn->query("SELECT
                (SELECT COUNT(*) FROM clients WHERE IsActive=1) as totalClients,
                (SELECT COUNT(*) FROM pets WHERE IsActive=1) as totalPets,
                (SELECT COUNT(*) FROM appointments WHERE COALESCE(IsDeleted,0)=0 AND AppointmentDate BETWEEN '$dateFrom' AND '$dateTo') as totalAppt,
                (SELECT COUNT(*) FROM appointments WHERE COALESCE(IsDeleted,0)=0 AND Status='Completed' AND AppointmentDate BETWEEN '$dateFrom' AND '$dateTo') as completedAppt,
                (SELECT COUNT(*) FROM appointments WHERE COALESCE(IsDeleted,0)=0 AND Status='Cancelled' AND AppointmentDate BETWEEN '$dateFrom' AND '$dateTo') as cancelledAppt,
                (SELECT COUNT(*) FROM lodging WHERE Status='Active') as activeLodging,
                (SELECT COUNT(*) FROM clients WHERE IsActive=1 AND CreatedAt BETWEEN '$dateFrom' AND '$dateTo') as newClients
            ")->fetch_assoc();
            $b = $conn->query("
                SELECT
                    COALESCE(SUM(AmountPaid),0) as totalRevenue,
                    COALESCE(SUM(CASE WHEN PaymentStatus!='Paid' THEN TotalAmount-AmountPaid END),0) as totalPending,
                    COUNT(*) as totalBills,
                    COUNT(CASE WHEN PaymentStatus='Paid' THEN 1 END) as paidBills
                FROM billing
                WHERE BillingDate BETWEEN '$dateFrom' AND '$dateTo'
            ")->fetch_assoc();
            $collectionRate = $b['totalBills'] > 0 ? round($b['paidBills'] / $b['totalBills'] * 100, 1) : 0;
            $rows = [
                ['Report Period', $dateFrom . ' to ' . $dateTo],
                ['Active Clients', $k['totalClients']],
                ['New Clients (Period)', $k['newClients']],
                ['Active Pets', $k['totalPets']],
                ['Total Appointments', $k['totalAppt']],
                ['Completed Appointments', $k['completedAppt']],
                ['Cancelled Appointments', $k['cancelledAppt']],
                ['Active Lodging', $k['activeLodging']],
                ['Total Revenue Collected', number_format((float) $b['totalRevenue'], 2)],
                ['Total Pending Balance', number_format((float) $b['totalPending'], 2)],
                ['Total Bills', $b['totalBills']],
                ['Paid Bills', $b['paidBills']],
                ['Collection Rate', $collectionRate . '%'],
            ];
            break;
    }

    return [$headers, $rows];
}

function reports_esc_xml($str) {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

// ── Shared module branding (kept in sync with report_pdf.php $moduleMap) ──
const REPORT_MODULE_META = [
    'clients'       => ['label' => 'Clients / Owners',        'color' => '#3b82f6'],
    'pets'          => ['label' => 'Pets / Patients',         'color' => '#ec4899'],
    'appointments'  => ['label' => 'Appointments',            'color' => '#6366f1'],
    'consultations' => ['label' => 'Medical History',         'color' => '#10b981'],
    'billing'       => ['label' => 'Billing & Payments',      'color' => '#f97316'],
    'services'      => ['label' => 'Clinic Services',         'color' => '#7c3aed'],
    'lodging'       => ['label' => 'Lodging & Boarding',      'color' => '#7c3aed'],
    'analytics'     => ['label' => 'Analytics & Performance', 'color' => '#ef4444'],
];

// ── Embed clinic logo as base64 for branded DOCX/XLSX headers ──
function reports_logo_data_uri() {
    static $uri = null;
    if ($uri === null) {
        $path = __DIR__ . '/logo1.png';
        $uri  = (is_file($path))
            ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($path))
            : '';
    }
    return $uri;
}

function reports_export_csv($headers, $rows, $filenameBase) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filenameBase . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
}

function reports_export_excel($headers, $rows, $filenameBase, $format, $sheetName, $title = '', $dateFrom = '', $dateTo = '', $hasDateRange = false) {
    $mime = ($format === 'xlsx')
        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        : 'application/vnd.ms-excel';
    header("Content-Type: $mime; charset=utf-8");
    header('Content-Disposition: attachment; filename=' . $filenameBase . '.' . $format);

    $generated = date('M d, Y h:i A');
    $cols = max(count($headers), 1);

    echo "<?xml version=\"1.0\"?>\n";
    echo "<?mso-application progid=\"Excel.Sheet\"?>\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    echo '<Styles>'
       . '<Style ss:ID="hdrClinic"><Font ss:Bold="1" ss:Size="14"/></Style>'
       . '<Style ss:ID="hdrTitle"><Font ss:Bold="1" ss:Size="12" ss:Color="#0F172A"/></Style>'
       . '<Style ss:ID="hdrMeta"><Font ss:Color="#64748B" ss:Size="9"/></Style>'
       . '<Style ss:ID="thStyle"><Interior ss:Color="#F8FAFC" ss:Pattern="Solid"/><Font ss:Bold="1" ss:Color="#475569" ss:Size="9"/></Style>'
       . '</Styles>';
    echo '<Worksheet ss:Name="' . reports_esc_xml($sheetName) . '"><Table>';

    // ── Branded header block ──
    echo '<Row><Cell ss:StyleID="hdrClinic"><Data ss:Type="String">Heartside Veterinary Clinic</Data></Cell></Row>';
    echo '<Row><Cell ss:StyleID="hdrTitle"><Data ss:Type="String">' . reports_esc_xml($title) . '</Data></Cell></Row>';
    $metaLine = 'Generated: ' . $generated;
    if ($hasDateRange) {
        $metaLine .= '   |   Period: ' . date('M d, Y', strtotime($dateFrom)) . ' - ' . date('M d, Y', strtotime($dateTo));
    }
    echo '<Row><Cell ss:StyleID="hdrMeta"><Data ss:Type="String">' . reports_esc_xml($metaLine) . '</Data></Cell></Row>';
    echo '<Row></Row>'; // spacer

    echo '<Row>';
    foreach ($headers as $h) {
        echo '<Cell ss:StyleID="thStyle"><Data ss:Type="String">' . reports_esc_xml($h) . '</Data></Cell>';
    }
    echo '</Row>';

    foreach ($rows as $r) {
        echo '<Row>';
        foreach ($r as $cell) {
            $isNumber = is_numeric($cell) && $cell !== '' && !preg_match('/^0[0-9]/', (string) $cell);
            $type = $isNumber ? 'Number' : 'String';
            echo '<Cell><Data ss:Type="' . $type . '">' . reports_esc_xml($cell) . '</Data></Cell>';
        }
        echo '</Row>';
    }

    echo '</Table></Worksheet></Workbook>';
}

function reports_export_docx($headers, $rows, $filenameBase, $title, $color = '#0ea5e9', $moduleLabel = '', $dateFrom = '', $dateTo = '', $hasDateRange = false) {
    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filenameBase . '.doc');

    $generated = date('M d, Y h:i A');
    $logo = reports_logo_data_uri();

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title></head>';
    echo '<body style="font-family:Calibri,Arial,sans-serif;color:#1e293b;">';

    // ── Branded header (logo + clinic name + report meta), matching PDF report-header ──
    echo '<table width="100%" cellpadding="0" cellspacing="0" style="border:none;border-bottom:3px solid ' . htmlspecialchars($color) . ';margin-bottom:18px;">';
    echo '<tr>';
    echo '<td style="vertical-align:middle;padding-bottom:12px;width:60px;">';
    if ($logo) {
        echo '<img src="' . $logo . '" width="46" height="46" style="display:block;">';
    }
    echo '</td>';
    echo '<td style="vertical-align:middle;padding-bottom:12px;">';
    echo '<div style="font-size:20px;font-weight:800;color:#0f172a;">Heartside Vet</div>';
    echo '<div style="font-size:12px;font-weight:600;color:' . htmlspecialchars($color) . ';margin-top:2px;">' . htmlspecialchars($moduleLabel ?: $title) . ' Report</div>';
    echo '</td>';
    echo '<td style="vertical-align:middle;text-align:right;padding-bottom:12px;font-size:10px;color:#64748b;line-height:1.6;">';
    echo '<strong style="color:#0f172a;font-size:12px;">Generated: ' . $generated . '</strong><br>';
    if ($hasDateRange) {
        echo 'Date Range: ' . date('M d, Y', strtotime($dateFrom)) . ' &ndash; ' . date('M d, Y', strtotime($dateTo)) . '<br>';
    } else {
        echo 'All records as of today<br>';
    }
    echo 'Heartside Veterinary Clinic';
    echo '</td>';
    echo '</tr>';
    echo '</table>';

    // ── Module badge ──
    echo '<div style="display:inline-block;background:' . htmlspecialchars($color) . '18;border:1px solid ' . htmlspecialchars($color) . '44;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:700;color:' . htmlspecialchars($color) . ';margin-bottom:14px;">' . htmlspecialchars($moduleLabel ?: $title) . '</div>';

    // ── Record summary ──
    echo '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 16px;margin-bottom:14px;font-size:11px;color:#64748b;">';
    echo '<strong style="font-size:18px;color:' . htmlspecialchars($color) . ';">' . number_format(count($rows)) . '</strong> total record' . (count($rows) === 1 ? '' : 's');
    if ($hasDateRange) {
        echo '&nbsp;&middot;&nbsp;' . date('M d, Y', strtotime($dateFrom)) . ' &ndash; ' . date('M d, Y', strtotime($dateTo));
    }
    echo '</div>';

    // ── Data table ──
    echo '<table border="1" cellspacing="0" cellpadding="6" style="border-collapse:collapse;width:100%;font-size:11px;border-color:#e2e8f0;">';
    echo '<tr style="background:#f8fafc;">';
    foreach ($headers as $h) echo '<th style="padding:8px 10px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;border-bottom:2px solid ' . htmlspecialchars($color) . '44;">' . htmlspecialchars((string) $h) . '</th>';
    echo '</tr>';
    if (empty($rows)) {
        echo '<tr><td colspan="' . max(count($headers),1) . '" style="text-align:center;color:#94a3b8;padding:20px;">No records found' . ($hasDateRange ? ' for this date range.' : '.') . '</td></tr>';
    } else {
        foreach ($rows as $i => $r) {
            $bg = ($i % 2 === 1) ? ' background:#fafafa;' : '';
            echo '<tr>';
            foreach ($r as $cell) echo '<td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;color:#1e293b;' . $bg . '">' . htmlspecialchars((string) $cell) . '</td>';
            echo '</tr>';
        }
    }
    echo '</table>';

    // ── Footer note (matches PDF footer-note) ──
    echo '<div style="text-align:center;font-size:9px;color:#94a3b8;margin-top:18px;padding-top:10px;border-top:1px solid #e2e8f0;">';
    echo 'Heartside Vet &middot; ' . htmlspecialchars($moduleLabel ?: $title) . ' Report &middot; Generated on ' . date('F d, Y \a\t g:i A');
    if ($hasDateRange) {
        echo ' &middot; Period: ' . date('M d, Y', strtotime($dateFrom)) . ' &ndash; ' . date('M d, Y', strtotime($dateTo));
    }
    echo '</div>';

    echo '</body></html>';
}

// ── EXPORT ROUTER (CSV / XLS / XLSX / DOCX) ──
if (isset($_GET['export'])) {
    $target   = $_GET['export'];
    $format   = $_GET['format'] ?? 'csv';
    $dateFrom = $_GET['from'] ?? date('Y-m-01');
    $dateTo   = $_GET['to']   ?? date('Y-m-d');

    list($headers, $rows) = reports_export_data($conn, $target, $dateFrom, $dateTo);

    $filenameBase = 'heartside_' . $target . '_' . date('Ymd_His');
    $meta         = REPORT_MODULE_META[$target] ?? ['label' => ucfirst($target), 'color' => '#0ea5e9'];
    $title        = 'Heartside Veterinary Clinic — ' . $meta['label'] . ' Report';
    $hasDateRange = in_array($target, ['appointments', 'consultations', 'billing', 'analytics']);

    switch ($format) {
        case 'xls':
        case 'xlsx':
            reports_export_excel($headers, $rows, $filenameBase, $format, ucfirst($target), $title, $dateFrom, $dateTo, $hasDateRange);
            break;
        case 'docx':
            reports_export_docx($headers, $rows, $filenameBase, $title, $meta['color'], $meta['label'], $dateFrom, $dateTo, $hasDateRange);
            break;
        case 'csv':
        default:
            reports_export_csv($headers, $rows, $filenameBase);
            break;
    }
    exit;
}

// ── AJAX: record counts for date-filtered modules ──
if (isset($_GET['get_count'])) {
    $module   = $_GET['get_count'];
    $dateFrom = $conn->real_escape_string($_GET['from'] ?? date('Y-m-01'));
    $dateTo   = $conn->real_escape_string($_GET['to']   ?? date('Y-m-d'));
    $count = 0;
    switch ($module) {
        case 'appointments':
            $count = $conn->query("SELECT COUNT(*) FROM appointments WHERE IsDeleted=0 AND AppointmentDate BETWEEN '$dateFrom' AND '$dateTo'")->fetch_row()[0];
            break;
        case 'consultations':
            $count = $conn->query("SELECT COUNT(*) FROM consultations WHERE IsDeleted=0 AND DATE(ConsultationDate) BETWEEN '$dateFrom' AND '$dateTo'")->fetch_row()[0];
            break;
        case 'billing':
            $count = $conn->query("SELECT COUNT(*) FROM billing WHERE IsDeleted=0 AND BillingDate BETWEEN '$dateFrom' AND '$dateTo'")->fetch_row()[0];
            break;
    }
    header('Content-Type: application/json');
    echo json_encode(['count' => intval($count)]);
    exit;
}

// ── EXPORT DROPDOWN RENDERER (CSV / XLS / XLSX / DOCX / PDF) ──
function render_export_dropdown($id, $target, $from = null, $to = null, $small = false, $extraQs = '') {
    $qs = ($from !== null && $to !== null) ? '&from=' . $from . '&to=' . $to : '';
    $qs .= $extraQs;
    $menuId = 'exportMenu' . ucfirst($id);
    $btnExtra = $small ? ' style="font-size:12px;padding:6px 10px;"' : '';
    ob_start();
    ?>
    <div class="export-dropdown-wrap">
        <button class="btn-export" onclick="toggleExportMenu('<?= $menuId ?>',this)"<?= $btnExtra ?>>
            <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
        </button>
        <div class="export-menu" id="<?= $menuId ?>">
            <div class="export-menu-label">Export As</div>
            <a class="export-menu-item" id="csv-<?= $id ?>" href="reports.php?export=<?= $target ?>&format=csv<?= $qs ?>" onclick="document.getElementById('<?= $menuId ?>').classList.remove('show');">
                <div class="ei-icon ei-csv"><i class="bi bi-filetype-csv"></i></div> CSV
            </a>
            <a class="export-menu-item" id="xls-<?= $id ?>" href="reports.php?export=<?= $target ?>&format=xls<?= $qs ?>" onclick="document.getElementById('<?= $menuId ?>').classList.remove('show');">
                <div class="ei-icon ei-xls"><i class="bi bi-file-earmark-spreadsheet"></i></div> XLS
            </a>
            <a class="export-menu-item" id="xlsx-<?= $id ?>" href="reports.php?export=<?= $target ?>&format=xlsx<?= $qs ?>" onclick="document.getElementById('<?= $menuId ?>').classList.remove('show');">
                <div class="ei-icon ei-xlsx"><i class="bi bi-file-earmark-spreadsheet-fill"></i></div> XLSX
            </a>
            <a class="export-menu-item" id="docx-<?= $id ?>" href="reports.php?export=<?= $target ?>&format=docx<?= $qs ?>" onclick="document.getElementById('<?= $menuId ?>').classList.remove('show');">
                <div class="ei-icon ei-docx"><i class="bi bi-file-earmark-word"></i></div> DOCX
            </a>
            <a class="export-menu-item" id="pdf-<?= $id ?>" target="_blank" href="report_pdf.php?type=<?= $target ?>&back=reports<?= $qs ?>" onclick="document.getElementById('<?= $menuId ?>').classList.remove('show');">
                <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF
            </a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

include('header.php');

// ── Static counts (for Reports tab) ──
$countClients = $conn->query("SELECT COUNT(*) FROM clients WHERE IsDeleted=0")->fetch_row()[0];
$countPets    = $conn->query("SELECT COUNT(*) FROM pets WHERE IsDeleted=0")->fetch_row()[0];
$countSvc     = $conn->query("SELECT COUNT(*) FROM services WHERE IsDeleted=0")->fetch_row()[0];
$countLodging = $conn->query("SELECT COUNT(*) FROM lodging WHERE IsDeleted=0")->fetch_row()[0];
$defaultFrom  = date('Y-m-01');
$defaultTo    = date('Y-m-d');

// ── Analytics: date range filter ──
$range      = $_GET['range'] ?? '30';
$dateFrom   = $_GET['from']  ?? date('Y-m-d', strtotime("-{$range} days"));
$dateTo     = $_GET['to']    ?? date('Y-m-d');
$dateApptTo = date('Y-m-d', strtotime("+{$range} days"));
if ($range === 'custom') {
    $dateFrom   = $_GET['from'] ?? date('Y-m-01');
    $dateTo     = $_GET['to']   ?? date('Y-m-d');
    $dateApptTo = $_GET['to']   ?? date('Y-m-d');
}

// ── Analytics KPIs ──
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
extract($kpi);
extract($billingStats);

// ── Historical Aggregations ──
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

// ── Analytical Lists ──
$apptStats  = $conn->query("SELECT Status, COUNT(*) AS cnt FROM appointments WHERE AppointmentDate BETWEEN '$dateFrom' AND '$dateApptTo' AND COALESCE(IsDeleted,0)=0 GROUP BY Status")->fetch_all(MYSQLI_ASSOC);
$apptByDay  = $conn->query("SELECT DAYNAME(AppointmentDate) AS day, COUNT(*) AS cnt FROM appointments WHERE AppointmentDate BETWEEN '$dateFrom' AND '$dateApptTo' AND COALESCE(IsDeleted,0)=0 GROUP BY DAYNAME(AppointmentDate), DAYOFWEEK(AppointmentDate) ORDER BY DAYOFWEEK(AppointmentDate)")->fetch_all(MYSQLI_ASSOC);
$species    = $conn->query("SELECT Species, COUNT(*) AS cnt FROM pets WHERE IsActive=1 GROUP BY Species ORDER BY cnt DESC")->fetch_all(MYSQLI_ASSOC);
$payStatus  = $conn->query("SELECT PaymentStatus, COUNT(*) AS cnt, COALESCE(SUM(TotalAmount),0) AS total FROM billing WHERE BillingDate BETWEEN '$dateFrom' AND '$dateTo' GROUP BY PaymentStatus")->fetch_all(MYSQLI_ASSOC);
$payMethods = $conn->query("SELECT PaymentMethod, COUNT(*) AS cnt, COALESCE(SUM(AmountPaid),0) AS total FROM billing WHERE BillingDate BETWEEN '$dateFrom' AND '$dateTo' AND PaymentMethod IS NOT NULL AND PaymentMethod != '' GROUP BY PaymentMethod ORDER BY total DESC")->fetch_all(MYSQLI_ASSOC);
$topSvcs    = $conn->query("SELECT s.ServiceName, s.Price, COUNT(a.AppointmentID) AS cnt FROM appointments a JOIN services s ON a.ServiceID=s.ServiceID WHERE COALESCE(a.IsDeleted,0)=0 AND a.AppointmentDate BETWEEN '$dateFrom' AND '$dateTo' GROUP BY s.ServiceID ORDER BY cnt DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$topClients = $conn->query("SELECT CONCAT(c.FirstName,' ',c.LastName) AS Client, c.Email, c.Phone, COUNT(b.BillingID) AS bills, COALESCE(SUM(b.AmountPaid),0) AS spent FROM billing b JOIN clients c ON b.ClientID=c.ClientID WHERE b.BillingDate BETWEEN '$dateFrom' AND '$dateTo' GROUP BY b.ClientID ORDER BY spent DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$recentBilling = $conn->query("SELECT b.*, CONCAT(c.FirstName,' ',c.LastName) AS Client, p.PetName, p.Species FROM billing b JOIN clients c ON b.ClientID=c.ClientID LEFT JOIN pets p ON b.PetID=p.PetID WHERE b.BillingDate BETWEEN '$dateFrom' AND '$dateTo' ORDER BY b.CreatedAt DESC LIMIT 20");

// ── Consultation / Diagnosis Analytics ──
$totalConsults   = intval($conn->query("SELECT COUNT(*) FROM consultations WHERE IsDeleted=0 AND DATE(ConsultationDate) BETWEEN '$dateFrom' AND '$dateTo'")->fetch_row()[0]);
$followUpCount   = intval($conn->query("SELECT COUNT(*) FROM consultations WHERE IsDeleted=0 AND FollowUpDate IS NOT NULL AND DATE(ConsultationDate) BETWEEN '$dateFrom' AND '$dateTo'")->fetch_row()[0]);
$followUpRate    = ($totalConsults > 0) ? round($followUpCount / $totalConsults * 100, 1) : 0;

// Top diagnoses — split comma-separated multi-diagnosis entries and count each keyword
$diagRaw = $conn->query("SELECT Diagnosis FROM consultations WHERE IsDeleted=0 AND Diagnosis IS NOT NULL AND Diagnosis != '' AND DATE(ConsultationDate) BETWEEN '$dateFrom' AND '$dateTo'")->fetch_all(MYSQLI_ASSOC);
$diagCounts = [];
foreach ($diagRaw as $d) {
    // Split on common delimiters: comma, semicolon, slash, " and ", " with "
    $parts = preg_split('/[,;\/]|\s+and\s+|\s+with\s+/i', $d['Diagnosis']);
    foreach ($parts as $part) {
        $clean = trim($part);
        if (strlen($clean) < 3) continue; // skip noise
        $key = strtolower($clean);
        $diagCounts[$key] = ($diagCounts[$key] ?? 0) + 1;
    }
}
arsort($diagCounts);
$topDiagnoses = array_slice($diagCounts, 0, 10, true);

// Top chief complaints
$ccRaw = $conn->query("SELECT ChiefComplaint FROM consultations WHERE IsDeleted=0 AND ChiefComplaint IS NOT NULL AND ChiefComplaint != '' AND DATE(ConsultationDate) BETWEEN '$dateFrom' AND '$dateTo'")->fetch_all(MYSQLI_ASSOC);
$ccCounts = [];
foreach ($ccRaw as $d) {
    $parts = preg_split('/[,;\/]|\s+and\s+|\s+with\s+/i', $d['ChiefComplaint']);
    foreach ($parts as $part) {
        $clean = trim($part);
        if (strlen($clean) < 3) continue;
        $key = strtolower($clean);
        $ccCounts[$key] = ($ccCounts[$key] ?? 0) + 1;
    }
}
arsort($ccCounts);
$topComplaints = array_slice($ccCounts, 0, 8, true);

// Top vets by consultation count
$topVets = $conn->query("SELECT VetName, COUNT(*) AS cnt FROM consultations WHERE IsDeleted=0 AND VetName IS NOT NULL AND VetName != '' AND DATE(ConsultationDate) BETWEEN '$dateFrom' AND '$dateTo' GROUP BY VetName ORDER BY cnt DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

// Monthly consultation trend (last 6 months)
$consultTrend = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $val   = $conn->query("SELECT COUNT(*) FROM consultations WHERE IsDeleted=0 AND DATE_FORMAT(ConsultationDate,'%Y-%m')='$month'")->fetch_row()[0];
    $consultTrend[] = ['label' => $label, 'value' => intval($val)];
}

// ── Computed ──
$avgRevPerAppt  = ($completedAppt > 0) ? round($totalRevenue / $completedAppt, 2) : 0;
$collectionRate = ($totalRevenue + $totalPending > 0) ? round($totalRevenue / ($totalRevenue + $totalPending) * 100, 1) : 0;
$completionRate = ($totalAppt > 0) ? round($completedAppt / $totalAppt * 100, 1) : 0;

// ── Smart Insights ──
$DAILY_APPT_CAPACITY = 50; // max active patients / daily appointment capacity
$BOARDING_CAPACITY   = 10; // max boarding cages (C1–C10)
$insights = []; // kept for legacy compat
$moduleInsights = [
    'appointments' => [],
    'boarding'     => [],
    'billing'      => [],
    'clients'      => [],
    'pets'         => [],
    'consultations'=> [],
];

// 1. Today's appointment load (real-time clinical capacity metric)
$todayAppts = intval($conn->query("
    SELECT COUNT(*) FROM appointments
    WHERE AppointmentDate = CURDATE()
    AND Status NOT IN ('Cancelled','No-Show')
    AND COALESCE(IsDeleted,0)=0
")->fetch_row()[0]);
$apptLoadPct = ($DAILY_APPT_CAPACITY > 0) ? round($todayAppts / $DAILY_APPT_CAPACITY * 100) : 0;
if ($apptLoadPct >= 100) {
    $moduleInsights['appointments'][] = ['type'=>'danger', 'icon'=>'bi-exclamation-octagon-fill',
        'title' => 'Fully booked today — ' . $todayAppts . ' / ' . $DAILY_APPT_CAPACITY . ' appointments (' . $apptLoadPct . '%)',
        'msg'   => 'No more appointment slots available today. Redirect new booking requests to tomorrow or the next available date.'];
} elseif ($apptLoadPct >= 75) {
    $moduleInsights['appointments'][] = ['type'=>'warning', 'icon'=>'bi-exclamation-triangle-fill',
        'title' => 'Heavy schedule today — ' . $todayAppts . ' / ' . $DAILY_APPT_CAPACITY . ' appointments (' . $apptLoadPct . '%)',
        'msg'   => 'Only ' . ($DAILY_APPT_CAPACITY - $todayAppts) . ' slot(s) remaining today. Consider managing walk-in expectations.'];
} else {
    $moduleInsights['appointments'][] = ['type'=>'success', 'icon'=>'bi-heart-pulse-fill',
        'title' => 'Schedule is manageable — ' . $todayAppts . ' appointment(s) today (' . $apptLoadPct . '% of daily capacity)',
        'msg'   => ($DAILY_APPT_CAPACITY - $todayAppts) . ' slot(s) still open today. Ready to accept walk-ins or new bookings.'];
}

// 1b. Boarding cage occupancy
$boardingPct = ($BOARDING_CAPACITY > 0) ? round($activeLodging / $BOARDING_CAPACITY * 100) : 0;
if ($boardingPct >= 100) {
    $moduleInsights['boarding'][] = ['type'=>'danger', 'icon'=>'bi-house-fill',
        'title' => 'All cages occupied — ' . $activeLodging . ' / ' . $BOARDING_CAPACITY . ' cages in use (100%)',
        'msg'   => 'No boarding cages available. Decline new boarding requests until a pet checks out.'];
} elseif ($boardingPct >= 60) {
    $moduleInsights['boarding'][] = ['type'=>'warning', 'icon'=>'bi-house-fill',
        'title' => 'Boarding nearly full — ' . $activeLodging . ' / ' . $BOARDING_CAPACITY . ' cages in use (' . $boardingPct . '%)',
        'msg'   => 'Only ' . ($BOARDING_CAPACITY - $activeLodging) . ' cage(s) remaining. Consider a waitlist for new boarding inquiries.'];
} else {
    $moduleInsights['boarding'][] = ['type'=>'success', 'icon'=>'bi-house-fill',
        'title' => 'Boarding capacity available — ' . $activeLodging . ' / ' . $BOARDING_CAPACITY . ' cages in use (' . $boardingPct . '%)',
        'msg'   => ($BOARDING_CAPACITY - $activeLodging) . ' cage(s) free. Ready to accept new boarding pets.'];
}

// 2. No-shows / cancellations — always fires if appointments exist
$noShowCnt = 0; $cancelledCnt = 0;
foreach ($apptStats as $as) {
    if ($as['Status'] === 'No-Show')   $noShowCnt   = intval($as['cnt']);
    if ($as['Status'] === 'Cancelled') $cancelledCnt = intval($as['cnt']);
}
$badApptPct = ($totalAppt > 0) ? round(($noShowCnt + $cancelledCnt) / $totalAppt * 100, 1) : 0;
if ($totalAppt > 0) {
    if ($badApptPct >= 20) {
        $moduleInsights['appointments'][] = ['type'=>'danger', 'icon'=>'bi-calendar-x-fill',
            'title' => $badApptPct . '% no-show / cancellation rate — ' . ($noShowCnt + $cancelledCnt) . ' of ' . $totalAppt . ' appointments lost',
            'msg'   => 'Call or message clients 24 hrs before their appointment. Export the Appointments CSV and follow up on recurring no-shows.'];
    } elseif ($badApptPct >= 10) {
        $moduleInsights['appointments'][] = ['type'=>'warning', 'icon'=>'bi-calendar-x-fill',
            'title' => $badApptPct . '% no-show / cancellation rate (' . ($noShowCnt + $cancelledCnt) . ' appointments)',
            'msg'   => 'Slightly above ideal. Send a reminder to all upcoming Scheduled appointments this week.'];
    } else {
        $moduleInsights['appointments'][] = ['type'=>'success', 'icon'=>'bi-calendar-check-fill',
            'title' => 'Good attendance — ' . $badApptPct . '% no-show / cancellation rate this period',
            'msg'   => $completionRate . '% of appointments completed. Client reliability is strong.'];
    }
}

// 3. Collection rate — always fires if billing exists
if ($totalBills > 0) {
    if ($collectionRate < 60) {
        $moduleInsights['billing'][] = ['type'=>'danger', 'icon'=>'bi-cash-stack',
            'title' => 'Low collection rate — only ' . $collectionRate . '% of billed amount collected',
            'msg'   => 'Export the Billing CSV filtered to Pending/Partial. Follow up with clients who have balances above ₱500.'];
    } elseif ($collectionRate < 80) {
        $moduleInsights['billing'][] = ['type'=>'warning', 'icon'=>'bi-cash-stack',
            'title' => 'Collection rate at ' . $collectionRate . '% — ' . ($totalBills - $paidBills) . ' unpaid bill(s) · ₱' . number_format($totalPending, 0) . ' outstanding',
            'msg'   => 'Review the billing log below and collect outstanding balances before the period closes.'];
    } else {
        $moduleInsights['billing'][] = ['type'=>'success', 'icon'=>'bi-cash-coin',
            'title' => 'Strong collection rate — ' . $collectionRate . '% collected · ₱' . number_format($totalRevenue, 0) . ' this period',
            'msg'   => $paidBills . ' of ' . $totalBills . ' bills fully paid. Billing is well-managed.'];
    }
}

// 4. Species skew — always fires if pets exist
$topSpeciesCnt  = !empty($species) ? intval($species[0]['cnt']) : 0;
$topSpeciesName = !empty($species) ? $species[0]['Species'] : '';
$topSpeciesPct  = ($totalPets > 0 && $topSpeciesCnt > 0) ? round($topSpeciesCnt / $totalPets * 100) : 0;
if ($totalPets === 0) {
    $moduleInsights['pets'][] = ['type'=>'info', 'icon'=>'bi-heart-pulse-fill',
        'title' => 'No active patients registered yet',
        'msg'   => 'Start adding patients to begin tracking species distribution, trends, and patient demographics.'];
} elseif ($topSpeciesPct >= 60 && $totalPets >= 2) {
    $moduleInsights['pets'][] = ['type'=>'warning', 'icon'=>'bi-pie-chart-fill',
        'title' => $topSpeciesName . 's make up ' . $topSpeciesPct . '% of all registered patients',
        'msg'   => 'Patient base is concentrated in one species. Consider targeted promotions for cats, exotic pets, or other animals to diversify and reduce revenue concentration.'];
} else {
    // Build a species summary string
    $speciesSummary = implode(', ', array_map(fn($s) => $s['Species'] . ' (' . $s['cnt'] . ')', array_slice($species, 0, 4)));
    $moduleInsights['pets'][] = ['type'=>'success', 'icon'=>'bi-heart-pulse-fill',
        'title' => 'Healthy patient mix — ' . $totalPets . ' active patient' . ($totalPets > 1 ? 's' : '') . ' across ' . count($species) . ' species',
        'msg'   => 'Species breakdown: ' . $speciesSummary . '. A diverse patient base reduces dependency on any single species demographic.'];
}

// 5. Service concentration — fires with 2+ bookings
$topSvcCnt   = !empty($topSvcs) ? intval($topSvcs[0]['cnt']) : 0;
$topSvcTotal = array_sum(array_column($topSvcs, 'cnt'));
$topSvcPct   = ($topSvcTotal > 0 && $topSvcCnt > 0) ? round($topSvcCnt / $topSvcTotal * 100) : 0;
if ($topSvcPct >= 50 && $topSvcTotal >= 2) {
    $moduleInsights['appointments'][] = ['type'=>'info', 'icon'=>'bi-grid-fill',
        'title' => '"' . htmlspecialchars($topSvcs[0]['ServiceName']) . '" is ' . $topSvcPct . '% of all service bookings this period',
        'msg'   => 'Revenue is concentrated in one service. Bundle it with lower-booked services at a slight discount to balance demand.'];
}

// 6. New client growth vs last month
$prevMonthClients = intval($conn->query("SELECT COUNT(*) FROM clients WHERE DATE_FORMAT(CreatedAt,'%Y-%m')='".date('Y-m', strtotime('-1 month'))."' AND IsActive=1")->fetch_row()[0]);
if ($newClients === 0 && $prevMonthClients > 0) {
    $moduleInsights['clients'][] = ['type'=>'warning', 'icon'=>'bi-person-x-fill',
        'title' => 'No new clients registered in this period',
        'msg'   => 'Last month had ' . $prevMonthClients . ' new clients. Consider running a referral promotion or check if the registration workflow is working.'];
} elseif ($prevMonthClients > 0 && $newClients >= $prevMonthClients * 1.2) {
    $growthPct = round(($newClients - $prevMonthClients) / $prevMonthClients * 100);
    $moduleInsights['clients'][] = ['type'=>'success', 'icon'=>'bi-graph-up-arrow',
        'title' => 'New client intake up ' . $growthPct . '% vs last month (' . $newClients . ' vs ' . $prevMonthClients . ')',
        'msg'   => 'Strong growth trend. Ask new clients how they found you and check if appointment slots can absorb the increase.'];
} elseif ($newClients > 0) {
    $moduleInsights['clients'][] = ['type'=>'info', 'icon'=>'bi-person-plus-fill',
        'title' => $newClients . ' new client(s) registered this period',
        'msg'   => 'Steady intake. ' . ($prevMonthClients > 0 ? 'Last month had ' . $prevMonthClients . ' new clients.' : 'Keep tracking month-over-month to spot trends.')];
}

// 7. Most rampant diagnosis — fires if consultations exist
if (!empty($topDiagnoses)) {
    $topDiagName  = array_key_first($topDiagnoses);
    $topDiagCount = $topDiagnoses[$topDiagName];
    $diagShare    = ($totalConsults > 0) ? round($topDiagCount / $totalConsults * 100) : 0;

    if ($diagShare >= 40) {
        $moduleInsights['consultations'][] = ['type' => 'danger', 'icon' => 'bi-activity',
            'title' => 'Outbreak alert — "' . ucwords($topDiagName) . '" is the most frequent diagnosis (' . $topDiagCount . ' cases, ' . $diagShare . '% of consultations)',
            'msg'   => 'This condition is highly prevalent this period. Ensure adequate medication stock, consider posting a pet health advisory for clients, and track whether it is spreading across multiple species or households.'];
    } elseif ($diagShare >= 20) {
        $moduleInsights['consultations'][] = ['type' => 'warning', 'icon' => 'bi-activity',
            'title' => '"' . ucwords($topDiagName) . '" is the most common diagnosis — ' . $topDiagCount . ' cases (' . $diagShare . '% of consultations)',
            'msg'   => 'Elevated frequency for this condition. Review treatment protocols, confirm adequate inventory for related medications, and consider a wellness reminder to clients whose pets may be at risk.'];
    } else {
        $moduleInsights['consultations'][] = ['type' => 'info', 'icon' => 'bi-activity',
            'title' => 'Top diagnosis this period: "' . ucwords($topDiagName) . '" — ' . $topDiagCount . ' case(s) (' . $diagShare . '% of consultations)',
            'msg'   => 'No alarming concentration. ' . count($topDiagnoses) . ' distinct diagnosis types recorded this period. Frequency is within normal range.'];
    }
}
// 8. Follow-up rate insight
if ($totalConsults > 0) {
    if ($followUpRate >= 50) {
        $moduleInsights['consultations'][] = ['type' => 'warning', 'icon' => 'bi-arrow-repeat',
            'title' => 'High follow-up rate — ' . $followUpRate . '% of consultations (' . $followUpCount . ' of ' . $totalConsults . ') require a follow-up visit',
            'msg'   => 'A high rate may indicate recurring or unresolved conditions. Review the most common diagnoses driving follow-ups and assess if treatment protocols need adjustment.'];
    } elseif ($followUpRate >= 20) {
        $moduleInsights['consultations'][] = ['type' => 'info', 'icon' => 'bi-arrow-repeat',
            'title' => $followUpRate . '% follow-up rate — ' . $followUpCount . ' follow-up(s) scheduled from ' . $totalConsults . ' consultation(s)',
            'msg'   => 'Moderate follow-up rate. Ensure all scheduled follow-up appointments have been booked and remind clients at least 2 days before their follow-up date.'];
    } elseif ($totalConsults >= 5) {
        $moduleInsights['consultations'][] = ['type' => 'success', 'icon' => 'bi-patch-check-fill',
            'title' => 'Low follow-up rate — ' . $followUpRate . '% (' . $followUpCount . ' of ' . $totalConsults . ' consultations)',
            'msg'   => 'Most cases are being resolved in a single visit. This suggests effective treatment. Continue monitoring for any uptick in recurring conditions.'];
    }
}
?>

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

/* ── Date filter row inside card body (Reports tab) ── */
.rpt-date-row {
    display: flex; align-items: center; gap: 8px;
    font-size: 12px; color: var(--muted);
    flex-wrap: wrap;
}
.rpt-date-row label { font-weight: 600; font-size: 11px; color: var(--muted); }
.rpt-date-input {
    border: 1.5px solid var(--border); border-radius: 6px;
    padding: 5px 9px; font-size: 12px; color: var(--text);
    background: var(--bg); cursor: pointer; font-family: inherit;
}
.rpt-date-input:focus { outline: none; border-color: var(--teal); background: #fff; }
.rpt-apply-btn {
    padding: 5px 14px; border-radius: 6px; font-size: 12px; font-weight: 600;
    background: var(--text); color: #fff; border: none; cursor: pointer;
    transition: background .15s; font-family: inherit;
}
.rpt-apply-btn:hover { background: #1e293b; }
.rpt-stats-row { display: flex; gap: 14px; margin-top: 12px; flex-wrap: wrap; align-items: center; }
.rpt-stat-box {
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 10px 16px; min-width: 130px;
}
.rpt-stat-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--light-muted); }
.rpt-stat-val   { font-size: 22px; font-weight: 800; color: var(--text); margin-top: 2px; line-height: 1; }
.rpt-stat-val.blue   { color: var(--teal); }
.rpt-stat-val.green  { color: var(--green); }
.rpt-stat-val.amber  { color: var(--amber); }
.rpt-stat-val.purple { color: var(--purple); }
.rpt-stat-val.rose   { color: #f43f5e; }
.rpt-cols-row { margin-top: 10px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.rpt-col-tag {
    background: var(--bg); border: 1px solid var(--border);
    border-radius: 5px; font-size: 10px; font-weight: 600; color: var(--muted); padding: 2px 8px;
}
.rpt-cols-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--light-muted); letter-spacing: .5px; }
.rpt-date-range-badge {
    background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px;
    padding: 6px 12px; font-size: 11px; font-weight: 600; color: #1d4ed8; white-space: nowrap;
}
.rpt-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 13px; border-radius: 7px; font-size: 12px; font-weight: 600;
    border: 1.5px solid; cursor: pointer; text-decoration: none;
    transition: all .15s; white-space: nowrap; font-family: inherit;
}
.rpt-btn-pdf  { border-color: var(--teal); color: var(--teal); background: transparent; }
.rpt-btn-pdf:hover  { background: var(--teal); color: #fff; }
.rpt-btn-csv  { border-color: var(--green); color: var(--green); background: transparent; }
.rpt-btn-csv:hover  { background: var(--green); color: #fff; }
.rpt-label-all { font-size: 11px; color: var(--muted); white-space: nowrap; }

/* ── Stat-card insight bell ── */
.stat-card { position: relative; }
.stat-card .insight-bell {
    position: absolute; top: 10px; right: 10px;
    width: 28px; height: 28px; border-radius: 50%;
    background: #fffbeb; border: 1.5px solid #fcd34d;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; opacity: 0; transition: opacity .2s, transform .15s;
    font-size: 13px; color: #d97706; z-index: 2;
}
.stat-card .insight-bell.has-danger  { background:#fef2f2; border-color:#fca5a5; color:#dc2626; }
.stat-card .insight-bell.has-warning { background:#fffbeb; border-color:#fcd34d; color:#d97706; }
.stat-card .insight-bell.has-info    { background:#eff6ff; border-color:#93c5fd; color:#2563eb; }
.stat-card .insight-bell.has-success { background:#f0fdf4; border-color:#86efac; color:#16a34a; }
.stat-card:hover .insight-bell { opacity: 1; transform: scale(1.08); }

/* ── Insight modal overlay ── */
#insightModal {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(15,23,42,.35); backdrop-filter: blur(3px);
    align-items: center; justify-content: center; padding: 20px;
}
#insightModal.open { display: flex; }
#insightModalBox {
    background: #fff; border-radius: 14px; width: 100%; max-width: 440px;
    box-shadow: 0 20px 60px rgba(0,0,0,.18); overflow: hidden;
    animation: modalPop .2s ease;
}
@keyframes modalPop { from { transform: scale(.93); opacity: 0; } to { transform: scale(1); opacity: 1; } }
#insightModalHeader {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px 10px; border-bottom: 1px solid var(--border);
}
#insightModalTitle { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); display: flex; align-items: center; gap: 6px; }
#insightModalClose { background: none; border: none; cursor: pointer; font-size: 18px; color: var(--light-muted); line-height:1; padding: 0; }
#insightModalClose:hover { color: var(--text); }
#insightModalBody { padding: 14px 18px; max-height: 60vh; overflow-y: auto; }
#insightModalFooter { padding: 12px 18px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; align-items: center; }
.insight-export-opt {
    display: flex; align-items: center; gap: 9px;
    width: 100%; padding: 9px 14px; background: none; border: none;
    border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600;
    color: var(--text); cursor: pointer; text-align: left; font-family: inherit;
    transition: background .12s;
}
.insight-export-opt:hover { background: var(--bg); }
.insight-item {
    display: flex; align-items: flex-start; gap: 11px;
    border-radius: 9px; padding: 11px 13px; margin-bottom: 8px; border: 1px solid;
}
.insight-item:last-child { margin-bottom: 0; }
.insight-item-title { font-size: 13px; font-weight: 700; margin-bottom: 3px; line-height: 1.35; }
.insight-item-msg   { font-size: 12px; opacity: .85; line-height: 1.5; }

</style>

<script>
// ── Tab switching (isolated so chart errors don't break tabs) ──
function switchTab(tab) {
    document.getElementById('paneReports').style.display   = tab === 'reports'   ? '' : 'none';
    document.getElementById('paneAnalytics').style.display = tab === 'analytics' ? '' : 'none';
    document.getElementById('tab-reports-link').classList.toggle('active',   tab === 'reports');
    document.getElementById('tab-analytics-link').classList.toggle('active', tab === 'analytics');
    document.getElementById('analytics-header-actions').style.display = tab === 'analytics' ? 'flex' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_GET['tab']) && $_GET['tab'] === 'analytics'): ?>
    switchTab('analytics');
    <?php else: ?>
    switchTab('reports');
    <?php endif; ?>

    // Date filter counts (Reports tab)
    const filterModules = ['appointments', 'consultations', 'billing'];
    filterModules.forEach(m => applyFilter(m));
});
</script>

<!-- ── PAGE HEADER ── -->
<div class="page-header">
    <div class="page-header-left">
        <h1>Reports &amp; Analytics</h1>
        <p>Export records and view clinic performance insights.</p>
    </div>
    <div class="page-header-actions" id="analytics-header-actions" style="display:none;gap:10px;align-items:center;flex-wrap:wrap;">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="tab" value="analytics">
            <select name="range" class="filter-select" onchange="this.form.submit()">
                <?php foreach(['7'=>'Last 7 days','30'=>'Last 30 days','90'=>'Last 90 days','365'=>'Last 12 months','custom'=>'Custom range'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= $range==$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($range==='custom'): ?>
                <input type="date" name="from" value="<?= $dateFrom ?>" class="filter-select">
                <input type="date" name="to"   value="<?= $dateTo ?>"   class="filter-select">
                <button type="submit" class="btn-main btn-teal" style="padding:8px 14px;">Apply</button>
            <?php endif; ?>
        </form>
        <?= render_export_dropdown('analytics', 'analytics', $dateFrom, $dateTo, false, '&range=' . $range) ?>
    </div>
</div>

<!-- ── TABS (matches services.php nav-tabs-modern) ── -->
<div class="nav-tabs-modern">
    <a class="nav-tab active" href="#" id="tab-reports-link" onclick="switchTab('reports'); return false;">
        <i class="bi bi-folder-symlink me-1"></i> Reports
    </a>
    <a class="nav-tab" href="#" id="tab-analytics-link" onclick="switchTab('analytics'); return false;">
        <i class="bi bi-graph-up-arrow me-1"></i> Analytics
    </a>
</div>

<!-- ══════════════════════════════════════
     TAB 1 — REPORTS
══════════════════════════════════════ -->
<div id="paneReports">
<div class="d-flex flex-column gap-3">

    <!-- ── 1. Clients / Owners ── -->
    <div class="card">
        <div class="card-header">
            <span class="card-header-title">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:#eff6ff;margin-right:10px;">
                    <i class="bi bi-people-fill" style="color:#3b82f6;font-size:14px;"></i>
                </span>
                Clients / Owners
                <span style="font-size:11px;font-weight:400;color:var(--muted);margin-left:8px;">All registered client records</span>
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="rpt-label-all">All records</span>
                <?= render_export_dropdown('clients', 'clients') ?>
            </div>
        </div>
        <div class="card-body">
            <div class="rpt-stats-row">
                <div class="rpt-stat-box">
                    <div class="rpt-stat-label">Total Records</div>
                    <div class="rpt-stat-val blue"><?= number_format($countClients) ?></div>
                </div>
            </div>
            <div class="rpt-cols-row mt-3">
                <span class="rpt-cols-lbl">Exported Columns</span>
                <?php foreach (['Client ID','Full Name','Email','Phone','Address','Date Registered'] as $col): ?>
                    <span class="rpt-col-tag"><?= $col ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── 2. Pets / Patients ── -->
    <div class="card">
        <div class="card-header">
            <span class="card-header-title">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:#f0fdf4;margin-right:10px;">
                    <i class="bi bi-heart-pulse-fill" style="color:#22c55e;font-size:14px;"></i>
                </span>
                Pets / Patients
                <span style="font-size:11px;font-weight:400;color:var(--muted);margin-left:8px;">All registered animal patient records</span>
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="rpt-label-all">All records</span>
                <?= render_export_dropdown('pets', 'pets') ?>
            </div>
        </div>
        <div class="card-body">
            <div class="rpt-stats-row">
                <div class="rpt-stat-box">
                    <div class="rpt-stat-label">Total Records</div>
                    <div class="rpt-stat-val green"><?= number_format($countPets) ?></div>
                </div>
            </div>
            <div class="rpt-cols-row mt-3">
                <span class="rpt-cols-lbl">Exported Columns</span>
                <?php foreach (['Pet ID','Pet Name','Owner','Species','Breed','Gender','Date of Birth','Weight (kg)'] as $col): ?>
                    <span class="rpt-col-tag"><?= $col ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── 3. Appointments ── -->
    <div class="card" id="card-appointments">
        <div class="card-header">
            <span class="card-header-title">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:#faf5ff;margin-right:10px;">
                    <i class="bi bi-calendar-check-fill" style="color:var(--purple);font-size:14px;"></i>
                </span>
                Appointments
                <span style="font-size:11px;font-weight:400;color:var(--muted);margin-left:8px;">Appointment records filtered by date range</span>
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <?= render_export_dropdown('appointments', 'appointments', $defaultFrom, $defaultTo) ?>
            </div>
        </div>
        <div class="card-body">
            <div class="rpt-date-row">
                <label>From</label>
                <input type="date" class="rpt-date-input" id="from-appointments" value="<?= $defaultFrom ?>">
                <label>To</label>
                <input type="date" class="rpt-date-input" id="to-appointments" value="<?= $defaultTo ?>">
                <button class="rpt-apply-btn" onclick="applyFilter('appointments')">Apply</button>
            </div>
            <div class="rpt-stats-row mt-3">
                <div class="rpt-stat-box">
                    <div class="rpt-stat-label">Records in Range</div>
                    <div class="rpt-stat-val purple" id="count-appointments">—</div>
                </div>
                <div class="rpt-date-range-badge" id="range-appointments">
                    <?= date('M d', strtotime($defaultFrom)) ?> – <?= date('M d, Y', strtotime($defaultTo)) ?>
                </div>
            </div>
            <div class="rpt-cols-row mt-3">
                <span class="rpt-cols-lbl">Exported Columns</span>
                <?php foreach (['ID','Date','Time','Pet','Owner','Service','Reason','Status'] as $col): ?>
                    <span class="rpt-col-tag"><?= $col ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── 4. Medical History (Consultations) ── -->
    <div class="card" id="card-consultations">
        <div class="card-header">
            <span class="card-header-title">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:#fff7ed;margin-right:10px;">
                    <i class="bi bi-clipboard2-pulse-fill" style="color:#f97316;font-size:14px;"></i>
                </span>
                Medical History
                <span style="font-size:11px;font-weight:400;color:var(--muted);margin-left:8px;">Consultation &amp; examination records</span>
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <?= render_export_dropdown('consultations', 'consultations', $defaultFrom, $defaultTo) ?>
            </div>
        </div>
        <div class="card-body">
            <div class="rpt-date-row">
                <label>From</label>
                <input type="date" class="rpt-date-input" id="from-consultations" value="<?= $defaultFrom ?>">
                <label>To</label>
                <input type="date" class="rpt-date-input" id="to-consultations" value="<?= $defaultTo ?>">
                <button class="rpt-apply-btn" onclick="applyFilter('consultations')">Apply</button>
            </div>
            <div class="rpt-stats-row mt-3">
                <div class="rpt-stat-box">
                    <div class="rpt-stat-label">Records in Range</div>
                    <div class="rpt-stat-val amber" id="count-consultations">—</div>
                </div>
                <div class="rpt-date-range-badge" id="range-consultations">
                    <?= date('M d', strtotime($defaultFrom)) ?> – <?= date('M d, Y', strtotime($defaultTo)) ?>
                </div>
            </div>
            <div class="rpt-cols-row mt-3">
                <span class="rpt-cols-lbl">Exported Columns</span>
                <?php foreach (['Consult ID','Date','Pet','Owner','Veterinarian','Diagnosis','Treatment','Prescription','Follow-Up'] as $col): ?>
                    <span class="rpt-col-tag"><?= $col ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── 5. Billing & Payments ── -->
    <div class="card" id="card-billing">
        <div class="card-header">
            <span class="card-header-title">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:#f0fdf4;margin-right:10px;">
                    <i class="bi bi-receipt-cutoff" style="color:var(--green);font-size:14px;"></i>
                </span>
                Billing &amp; Payments
                <span style="font-size:11px;font-weight:400;color:var(--muted);margin-left:8px;">Invoice &amp; payment records filtered by billing date</span>
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <?= render_export_dropdown('billing', 'billing', $defaultFrom, $defaultTo) ?>
            </div>
        </div>
        <div class="card-body">
            <div class="rpt-date-row">
                <label>From</label>
                <input type="date" class="rpt-date-input" id="from-billing" value="<?= $defaultFrom ?>">
                <label>To</label>
                <input type="date" class="rpt-date-input" id="to-billing" value="<?= $defaultTo ?>">
                <button class="rpt-apply-btn" onclick="applyFilter('billing')">Apply</button>
            </div>
            <div class="rpt-stats-row mt-3">
                <div class="rpt-stat-box">
                    <div class="rpt-stat-label">Records in Range</div>
                    <div class="rpt-stat-val green" id="count-billing">—</div>
                </div>
                <div class="rpt-date-range-badge" id="range-billing">
                    <?= date('M d', strtotime($defaultFrom)) ?> – <?= date('M d, Y', strtotime($defaultTo)) ?>
                </div>
            </div>
            <div class="rpt-cols-row mt-3">
                <span class="rpt-cols-lbl">Exported Columns</span>
                <?php foreach (['Bill ID','Date','Client','Pet','Total','Paid','Balance','Method','Status','Notes'] as $col): ?>
                    <span class="rpt-col-tag"><?= $col ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── 6. Clinic Services ── -->
    <div class="card">
        <div class="card-header">
            <span class="card-header-title">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:#fff7ed;margin-right:10px;">
                    <i class="bi bi-box-seam-fill" style="color:var(--amber);font-size:14px;"></i>
                </span>
                Clinic Services
                <span style="font-size:11px;font-weight:400;color:var(--muted);margin-left:8px;">All services in the service catalog</span>
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="rpt-label-all">All records</span>
                <?= render_export_dropdown('services', 'services') ?>
            </div>
        </div>
        <div class="card-body">
            <div class="rpt-stats-row">
                <div class="rpt-stat-box">
                    <div class="rpt-stat-label">Total Records</div>
                    <div class="rpt-stat-val amber"><?= number_format($countSvc) ?></div>
                </div>
            </div>
            <div class="rpt-cols-row mt-3">
                <span class="rpt-cols-lbl">Exported Columns</span>
                <?php foreach (['Service ID','Name','Category','Price','Duration','Description','Status'] as $col): ?>
                    <span class="rpt-col-tag"><?= $col ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── 7. Lodging & Boarding ── -->
    <div class="card">
        <div class="card-header">
            <span class="card-header-title">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:#fdf2f8;margin-right:10px;">
                    <i class="bi bi-house-heart-fill" style="color:#ec4899;font-size:14px;"></i>
                </span>
                Lodging &amp; Boarding
                <span style="font-size:11px;font-weight:400;color:var(--muted);margin-left:8px;">All lodging and boarding records</span>
            </span>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="rpt-label-all">All records</span>
                <?= render_export_dropdown('lodging', 'lodging') ?>
            </div>
        </div>
        <div class="card-body">
            <div class="rpt-stats-row">
                <div class="rpt-stat-box">
                    <div class="rpt-stat-label">Total Records</div>
                    <div class="rpt-stat-val rose"><?= number_format($countLodging) ?></div>
                </div>
            </div>
            <div class="rpt-cols-row mt-3">
                <span class="rpt-cols-lbl">Exported Columns</span>
                <?php foreach (['Lodging ID','Pet','Owner','Check-In','Check-Out','Cage #','Daily Rate','Status','Instructions'] as $col): ?>
                    <span class="rpt-col-tag"><?= $col ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div><!-- /flex col -->
</div><!-- /paneReports -->



<!-- ══════════════════════════════════════
     TAB 2 — ANALYTICS
══════════════════════════════════════ -->
<div id="paneAnalytics" style="display:none;">


<p class="report-section-label"><i class="bi bi-grid-3x3-gap me-1"></i> Key Performance Indicators · <?= date('M d', strtotime($dateFrom)) ?> – <?= date('M d, Y', strtotime($dateTo)) ?></p>
<div class="row g-3 mb-4">

    <?php
    // ── Appointment status counts for card
    $apptStatusMap = [];
    foreach ($apptStats as $as) $apptStatusMap[$as['Status']] = intval($as['cnt']);
    $apptCompleted  = $apptStatusMap['Completed'] ?? 0;
    $apptScheduled  = $apptStatusMap['Scheduled'] ?? 0;
    $apptCancelled  = $apptStatusMap['Cancelled'] ?? 0;
    $apptNoShow     = $apptStatusMap['No-Show'] ?? ($apptStatusMap['No-show'] ?? 0);

    // ── Billing status counts for card
    $billStatusMap = [];
    foreach ($payStatus as $ps) $billStatusMap[$ps['PaymentStatus']] = intval($ps['cnt']);
    $billPaid    = $billStatusMap['Paid'] ?? 0;
    $billPending = $billStatusMap['Pending'] ?? 0;
    $billPartial = $billStatusMap['Partial'] ?? 0;

    // ── Top diagnosis
    $topDiagLabel = !empty($topDiagnoses) ? ucwords(array_key_first($topDiagnoses)) : '—';
    $topDiagCnt   = !empty($topDiagnoses) ? reset($topDiagnoses) : 0;
    $diagShare    = ($totalConsults > 0 && $topDiagCnt > 0) ? round($topDiagCnt / $totalConsults * 100) : 0;

    // ── Boarding
    $boardingFree = $BOARDING_CAPACITY - $activeLodging;
    $bPctCard     = ($BOARDING_CAPACITY > 0) ? round($activeLodging / $BOARDING_CAPACITY * 100) : 0;
    $bColorCard   = $bPctCard >= 100 ? '#ef4444' : ($bPctCard >= 60 ? '#f59e0b' : '#7c3aed');
    ?>

    <!-- 1. CLIENTS -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <?php if (!empty($moduleInsights['clients'])): $bt = $moduleInsights['clients'][0]['type']; ?>
            <button class="insight-bell has-<?= $bt ?>" onclick="openInsightModal('clients')" title="View Insight"><i class="bi bi-bell-fill"></i></button>
            <?php endif; ?>
            <div class="stat-icon" style="background:#eff6ff;color:#2563eb;"><i class="bi bi-people-fill"></i></div>
            <div class="stat-value"><?= $totalClients ?></div>
            <div class="stat-label">Clients</div>
            <div class="stat-trend up"><i class="bi bi-person-check"></i> Active clients</div>
            <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                <span style="font-size:10px;background:#eff6ff;color:#2563eb;border-radius:5px;padding:2px 7px;font-weight:600;"><i class="bi bi-person-plus" style="margin-right:3px;"></i>+<?= $newClients ?> new</span>
            </div>
        </div>
    </div>

    <!-- 2. PETS -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <?php if (!empty($moduleInsights['pets'])): $bt = $moduleInsights['pets'][0]['type']; ?>
            <button class="insight-bell has-<?= $bt ?>" onclick="openInsightModal('pets')" title="View Insight"><i class="bi bi-bell-fill"></i></button>
            <?php endif; ?>
            <div class="stat-icon" style="background:#fdf2f8;color:#db2777;"><i class="bi bi-heart-fill"></i></div>
            <div class="stat-value"><?= $totalPets ?></div>
            <div class="stat-label">Pets</div>
            <div class="stat-trend up"><i class="bi bi-shield-check"></i> Active patients</div>
            <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                <?php foreach (array_slice($species, 0, 2) as $sp): ?>
                <span style="font-size:10px;background:#fdf2f8;color:#db2777;border-radius:5px;padding:2px 7px;font-weight:600;"><?= htmlspecialchars($sp['Species']) ?> <?= $sp['cnt'] ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 3. APPOINTMENTS -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <?php if (!empty($moduleInsights['appointments'])): $bt = $moduleInsights['appointments'][0]['type']; foreach($moduleInsights['appointments'] as $mi){ if($mi['type']==='danger'){$bt='danger';break;} if($mi['type']==='warning'){$bt='warning';} } ?>
            <button class="insight-bell has-<?= $bt ?>" onclick="openInsightModal('appointments')" title="View Insight"><i class="bi bi-bell-fill"></i></button>
            <?php endif; ?>
            <div class="stat-icon" style="background:#eff6ff;color:#0ea5e9;"><i class="bi bi-calendar-check-fill"></i></div>
            <div class="stat-value"><?= $totalAppt ?></div>
            <div class="stat-label">Appointments</div>
            <div class="stat-trend up"><i class="bi bi-check-circle"></i> <?= $completionRate ?>% completed</div>
            <div style="margin-top:8px;display:flex;gap:5px;flex-wrap:wrap;">
                <?php if ($apptScheduled > 0): ?><span style="font-size:10px;background:#eff6ff;color:#0ea5e9;border-radius:5px;padding:2px 7px;font-weight:600;"><?= $apptScheduled ?> sched</span><?php endif; ?>
                <?php if ($apptCancelled + $apptNoShow > 0): ?><span style="font-size:10px;background:#fef2f2;color:#dc2626;border-radius:5px;padding:2px 7px;font-weight:600;"><?= $apptCancelled + $apptNoShow ?> lost</span><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 4. CONSULTATION — Top Diagnosis -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <?php if (!empty($moduleInsights['consultations'])): $bt = $moduleInsights['consultations'][0]['type']; foreach($moduleInsights['consultations'] as $mi){ if($mi['type']==='danger'){$bt='danger';break;} if($mi['type']==='warning'){$bt='warning';} } ?>
            <button class="insight-bell has-<?= $bt ?>" onclick="openInsightModal('consultations')" title="View Insight"><i class="bi bi-bell-fill"></i></button>
            <?php endif; ?>
            <div class="stat-icon" style="background:#faf5ff;color:#7c3aed;"><i class="bi bi-activity"></i></div>
            <div class="stat-value" style="font-size:<?= strlen($topDiagLabel) > 14 ? '13px' : (strlen($topDiagLabel) > 9 ? '15px' : '22px') ?>;line-height:1.2;"><?= htmlspecialchars($topDiagLabel) ?></div>
            <div class="stat-label">Top Diagnosis</div>
            <?php if ($topDiagCnt > 0): ?>
            <div class="stat-trend <?= $diagShare >= 40 ? 'warn' : 'up' ?>"><i class="bi bi-bar-chart-fill"></i> <?= $topDiagCnt ?> case<?= $topDiagCnt > 1 ? 's' : '' ?> · <?= $diagShare ?>%</div>
            <?php else: ?>
            <div class="stat-trend up"><i class="bi bi-clipboard2-pulse"></i> <?= $totalConsults ?> consults</div>
            <?php endif; ?>
            <div style="margin-top:8px;">
                <span style="font-size:10px;background:#faf5ff;color:#7c3aed;border-radius:5px;padding:2px 7px;font-weight:600;"><?= $totalConsults ?> total consults</span>
            </div>
        </div>
    </div>

    <!-- 5. BILLING — Status -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <?php if (!empty($moduleInsights['billing'])): $bt = $moduleInsights['billing'][0]['type']; ?>
            <button class="insight-bell has-<?= $bt ?>" onclick="openInsightModal('billing')" title="View Insight"><i class="bi bi-bell-fill"></i></button>
            <?php endif; ?>
            <div class="stat-icon" style="background:#fffbeb;color:#d97706;"><i class="bi bi-receipt-cutoff"></i></div>
            <div class="stat-value"><?= $totalBills ?></div>
            <div class="stat-label">Billing</div>
            <div class="stat-trend <?= $collectionRate >= 80 ? 'up' : 'warn' ?>"><i class="bi bi-cash-stack"></i> <?= $collectionRate ?>% collected</div>
            <div style="margin-top:8px;display:flex;gap:5px;flex-wrap:wrap;">
                <?php if ($billPaid > 0): ?><span style="font-size:10px;background:#f0fdf4;color:#16a34a;border-radius:5px;padding:2px 7px;font-weight:600;"><?= $billPaid ?> paid</span><?php endif; ?>
                <?php if ($billPending + $billPartial > 0): ?><span style="font-size:10px;background:#fffbeb;color:#d97706;border-radius:5px;padding:2px 7px;font-weight:600;"><?= $billPending + $billPartial ?> unpaid</span><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 6. BOARDING — Capacity -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <?php if (!empty($moduleInsights['boarding'])): $bt = $moduleInsights['boarding'][0]['type']; ?>
            <button class="insight-bell has-<?= $bt ?>" onclick="openInsightModal('boarding')" title="View Insight"><i class="bi bi-bell-fill"></i></button>
            <?php endif; ?>
            <div class="stat-icon" style="background:#f0fdf4;color:#16a34a;"><i class="bi bi-house-heart-fill"></i></div>
            <div class="stat-value" style="color:<?= $bColorCard ?>;"><?= $activeLodging ?><span style="font-size:14px;font-weight:500;color:var(--muted);">/<?= $BOARDING_CAPACITY ?></span></div>
            <div class="stat-label">Boarding</div>
            <div class="stat-trend <?= $bPctCard >= 100 ? 'warn' : 'up' ?>"><i class="bi bi-house-fill"></i> <?= $bPctCard ?>% occupied</div>
            <div style="margin-top:8px;">
                <div style="background:#f1f5f9;border-radius:99px;height:5px;overflow:hidden;">
                    <div style="width:<?= min(100,$bPctCard) ?>%;height:100%;border-radius:99px;background:<?= $bColorCard ?>;transition:width .4s;"></div>
                </div>
                <div style="font-size:10px;color:var(--muted);margin-top:4px;"><?= $boardingFree ?> cage<?= $boardingFree != 1 ? 's' : '' ?> free</div>
            </div>
        </div>
    </div>

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
                    ['lbl' => 'Active Lodging', 'val' => $activeLodging . ' / ' . $BOARDING_CAPACITY . ' cages', 'style' => 'color:var(--purple);'],
                    ['lbl' => 'Avg. Revenue / Completed Visit', 'val' => '₱'.number_format($avgRevPerAppt,2), 'style' => ''],
                    ['lbl' => 'Collection Rate', 'val' => $collectionRate.'%', 'style' => 'color:var(--green);']
                ];
                foreach ($metrics as $m): ?>
                <div class="metric-row"><span class="metric-label"><?= $m['lbl'] ?></span><span class="metric-value" style="<?= $m['style'] ?>"><?= $m['val'] ?></span></div>
                <?php endforeach; ?>
                <?php
                $bPct = min(100, $boardingPct);
                $bColor = $bPct >= 100 ? '#ef4444' : ($bPct >= 60 ? '#f59e0b' : '#7c3aed');
                ?>
                <div style="margin-top:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:4px;">
                        <span>Cage Occupancy</span>
                        <span style="font-weight:700;color:<?= $bColor ?>;"><?= $boardingPct ?>%</span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" style="width:<?= $bPct ?>%;background:<?= $bColor ?>;"></div>
                    </div>
                    <div style="font-size:10px;color:var(--muted);margin-top:3px;"><?= $BOARDING_CAPACITY - $activeLodging ?> of <?= $BOARDING_CAPACITY ?> cages free</div>
                </div>
                <?php
                $cPct2 = min(100, $capacityPct);
                $cColor = $cPct2 >= 100 ? '#ef4444' : ($cPct2 >= 75 ? '#f59e0b' : '#10b981');
                ?>
                <div style="margin-top:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:4px;">
                        <span>Patient Capacity</span>
                        <span style="font-weight:700;color:<?= $cColor ?>;"><?= $capacityPct ?>%</span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" style="width:<?= $cPct2 ?>%;background:<?= $cColor ?>;"></div>
                    </div>
                    <div style="font-size:10px;color:var(--muted);margin-top:3px;"><?= $totalPets ?> / <?= $CLINIC_CAPACITY ?> active patients</div>
                </div>
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

<p class="report-section-label"><i class="bi bi-clipboard2-pulse-fill me-1" style="color:#6366f1;"></i> Consultation &amp; Diagnosis Intelligence · <?= date('M d', strtotime($dateFrom)) ?> – <?= date('M d, Y', strtotime($dateTo)) ?></p>

<?php if ($totalConsults === 0): ?>
<div class="row g-3 mb-4"><div class="col-12">
    <div class="card"><div class="card-body text-center" style="padding:40px;color:var(--muted);">
        <i class="bi bi-clipboard2-x" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:.4;"></i>
        No consultation records found for this period.
    </div></div>
</div></div>
<?php else: ?>

<!-- KPI strip -->
<div class="row g-3 mb-3">
    <?php
    $consultKpis = [
        ['icon'=>'bi-clipboard2-pulse-fill','bg'=>'#eff6ff','color'=>'#6366f1','val'=>$totalConsults,'lbl'=>'Consultations','sub'=>'This period'],
        ['icon'=>'bi-activity','bg'=>'#fef2f2','color'=>'#dc2626','val'=>count($topDiagnoses),'lbl'=>'Unique Diagnoses','sub'=>'Distinct conditions'],
        ['icon'=>'bi-arrow-repeat','bg'=>'#fffbeb','color'=>'#d97706','val'=>$followUpCount,'lbl'=>'Follow-Ups Scheduled','sub'=>$followUpRate.'% of consultations'],
        ['icon'=>'bi-person-badge-fill','bg'=>'#f0fdf4','color'=>'#16a34a','val'=>count($topVets),'lbl'=>'Attending Vets','sub'=>'Active this period'],
    ];
    foreach ($consultKpis as $k): ?>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:<?= $k['bg'] ?>;color:<?= $k['color'] ?>;"><i class="bi <?= $k['icon'] ?>"></i></div>
            <div class="stat-value"><?= $k['val'] ?></div>
            <div class="stat-label"><?= $k['lbl'] ?></div>
            <div class="stat-trend up" style="font-size:10px;"><?= $k['sub'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <!-- Most Common Diagnoses -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <span class="card-header-title">
                    <i class="bi bi-activity me-2" style="color:#dc2626;"></i>Most Frequent Diagnoses
                </span>
                <span style="font-size:11px;color:var(--muted);"><?= $totalConsults ?> consultations</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topDiagnoses)): ?>
                <div class="text-center p-4" style="color:var(--muted);font-size:13px;">No diagnosis data recorded.</div>
                <?php else:
                    $maxDiag = max($topDiagnoses) ?: 1;
                    $diagColors = ['#dc2626','#ea580c','#d97706','#ca8a04','#65a30d','#16a34a','#0891b2','#2563eb','#7c3aed','#be185d'];
                    $rank = 0;
                    foreach ($topDiagnoses as $diagName => $diagCnt):
                        $pct      = round($diagCnt / $totalConsults * 100, 1);
                        $barPct   = round($diagCnt / $maxDiag * 100);
                        $color    = $diagColors[$rank] ?? '#94a3b8';
                        $rankCls  = $rank===0 ? 'gold' : ($rank===1 ? 'silver' : ($rank===2 ? 'bronze' : ''));
                        $severity = $pct >= 30 ? 'badge-danger' : ($pct >= 15 ? 'badge-scheduled' : 'badge-active');
                        $sevLabel = $pct >= 30 ? 'High' : ($pct >= 15 ? 'Moderate' : 'Low');
                ?>
                <div style="padding:10px 16px;border-bottom:1px solid #f1f5f9;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:5px;">
                        <div class="rank-num <?= $rankCls ?>" style="flex-shrink:0;"><?= $rank+1 ?></div>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                                <span style="font-size:13px;font-weight:700;color:var(--dark);text-transform:capitalize;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars(ucwords($diagName)) ?></span>
                                <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                                    <span class="badge-modern <?= $severity ?>" style="font-size:10px;"><?= $sevLabel ?></span>
                                    <span style="font-size:12px;font-weight:700;color:var(--dark);"><?= $diagCnt ?> <span style="color:var(--muted);font-weight:400;">case<?= $diagCnt!==1?'s':'' ?></span></span>
                                    <span style="font-size:11px;color:var(--muted);width:38px;text-align:right;"><?= $pct ?>%</span>
                                </div>
                            </div>
                            <div class="progress-bar-wrap" style="margin-top:5px;">
                                <div class="progress-bar-fill" style="width:<?= $barPct ?>%;background:<?= $color ?>;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php $rank++; endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Most Common Chief Complaints -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <span class="card-header-title">
                    <i class="bi bi-heart-pulse me-2" style="color:#db2777;"></i>Most Reported Symptoms / Complaints
                </span>
                <span style="font-size:11px;color:var(--muted);">Chief complaints</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topComplaints)): ?>
                <div class="text-center p-4" style="color:var(--muted);font-size:13px;">No complaint data recorded.</div>
                <?php else:
                    $maxCC = max($topComplaints) ?: 1;
                    $ccColors = ['#db2777','#be185d','#9d174d','#7c3aed','#6d28d9','#0891b2','#0e7490','#065f46'];
                    $rank = 0;
                    foreach ($topComplaints as $ccName => $ccCnt):
                        $pct    = round($ccCnt / $totalConsults * 100, 1);
                        $barPct = round($ccCnt / $maxCC * 100);
                        $color  = $ccColors[$rank] ?? '#94a3b8';
                        $rankCls = $rank===0?'gold':($rank===1?'silver':($rank===2?'bronze':''));
                ?>
                <div style="padding:10px 16px;border-bottom:1px solid #f1f5f9;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:5px;">
                        <div class="rank-num <?= $rankCls ?>" style="flex-shrink:0;"><?= $rank+1 ?></div>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                                <span style="font-size:13px;font-weight:700;color:var(--dark);text-transform:capitalize;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars(ucwords($ccName)) ?></span>
                                <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                                    <span style="font-size:12px;font-weight:700;color:var(--dark);"><?= $ccCnt ?> <span style="color:var(--muted);font-weight:400;">report<?= $ccCnt!==1?'s':'' ?></span></span>
                                    <span style="font-size:11px;color:var(--muted);width:38px;text-align:right;"><?= $pct ?>%</span>
                                </div>
                            </div>
                            <div class="progress-bar-wrap" style="margin-top:5px;">
                                <div class="progress-bar-fill" style="width:<?= $barPct ?>%;background:<?= $color ?>;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php $rank++; endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- Monthly Consultation Trend -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <span class="card-header-title"><i class="bi bi-graph-up me-2" style="color:#6366f1;"></i>Consultation Volume — Last 6 Months</span>
            </div>
            <div class="card-body"><canvas id="consultTrendChart" height="130"></canvas></div>
        </div>
    </div>

    <!-- Top Attending Vets -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <span class="card-header-title"><i class="bi bi-person-badge-fill me-2" style="color:#16a34a;"></i>Top Attending Vets</span>
                <span style="font-size:11px;color:var(--muted);">By consultations</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topVets)): ?>
                <div class="text-center p-4" style="color:var(--muted);font-size:13px;">No vet data recorded.</div>
                <?php else:
                    $maxVet = max(array_column($topVets,'cnt')) ?: 1;
                    foreach ($topVets as $vi => $vet):
                        $vPct    = round($vet['cnt'] / $totalConsults * 100, 1);
                        $vBar    = round($vet['cnt'] / $maxVet * 100);
                        $vRankCls = $vi===0?'gold':($vi===1?'silver':($vi===2?'bronze':''));
                ?>
                <div style="padding:10px 16px;border-bottom:1px solid #f1f5f9;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="rank-num <?= $vRankCls ?>" style="flex-shrink:0;"><?= $vi+1 ?></div>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                                <span style="font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($vet['VetName']) ?></span>
                                <span style="font-size:12px;font-weight:700;flex-shrink:0;margin-left:8px;"><?= $vet['cnt'] ?> <span style="color:var(--muted);font-weight:400;">(<?= $vPct ?>%)</span></span>
                            </div>
                            <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= $vBar ?>%;background:#16a34a;"></div></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Follow-Up & Consultation Summary -->
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-header"><span class="card-header-title"><i class="bi bi-arrow-repeat me-2" style="color:#d97706;"></i>Follow-Up Summary</span></div>
            <div class="card-body">
                <!-- Follow-up rate donut -->
                <div style="display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
                    <div style="position:relative;width:100px;height:100px;">
                        <canvas id="followUpChart" width="100" height="100"></canvas>
                        <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                            <span style="font-size:20px;font-weight:800;color:var(--dark);"><?= $followUpRate ?>%</span>
                            <span style="font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">follow-up</span>
                        </div>
                    </div>
                </div>
                <div class="metric-row"><span class="metric-label">Total Consultations</span><span class="metric-value"><?= $totalConsults ?></span></div>
                <div class="metric-row"><span class="metric-label">With Follow-Up</span><span class="metric-value" style="color:var(--amber);"><?= $followUpCount ?></span></div>
                <div class="metric-row"><span class="metric-label">No Follow-Up</span><span class="metric-value" style="color:var(--green);"><?= $totalConsults - $followUpCount ?></span></div>
                <div class="metric-row"><span class="metric-label">Unique Conditions</span><span class="metric-value" style="color:#6366f1;"><?= count($topDiagnoses) ?></span></div>
            </div>
        </div>
    </div>
</div>

<!-- Diagnosis Recommendations Panel -->
<?php if (!empty($topDiagnoses)): ?>
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card" style="border:1.5px solid #e0e7ff;">
            <div class="card-header" style="background:linear-gradient(135deg,#eff6ff,#f5f3ff);">
                <span class="card-header-title">
                    <i class="bi bi-lightbulb-fill me-2" style="color:#6366f1;"></i>
                    Diagnosis-Based Recommendations
                </span>
                <span style="font-size:11px;color:#6366f1;font-weight:600;">Assisted clinical insights</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                <?php
                // Generate a recommendation per top diagnosis (up to 6)
                $diagRecs = [
                    // common vet conditions → targeted advice
                    'skin'          => ['icon'=>'bi-droplet-fill','color'=>'#db2777','rec'=>'Skin conditions are prevalent. Review grooming hygiene protocols, check for environmental allergens, and consider recommending medicated shampoos or flea/tick preventives to at-risk clients.'],
                    'dermatitis'    => ['icon'=>'bi-droplet-fill','color'=>'#db2777','rec'=>'Dermatitis cases are high. Ensure adequate antihistamine and corticosteroid stock. Advise owners on diet-related triggers and environmental exposure.'],
                    'diarrhea'      => ['icon'=>'bi-exclamation-triangle-fill','color'=>'#ea580c','rec'=>'Gastrointestinal complaints are frequent. Review diet advice given to pet owners, check for potential food-borne contamination or sudden diet changes, and ensure probiotics are in stock.'],
                    'vomiting'      => ['icon'=>'bi-exclamation-triangle-fill','color'=>'#ea580c','rec'=>'Vomiting is a recurring complaint. Rule out dietary indiscretion, toxic ingestion, and parasitic load. Advise clients to keep poisonous plants and foods away from pets.'],
                    'fever'         => ['icon'=>'bi-thermometer-high','color'=>'#dc2626','rec'=>'Elevated fever cases detected. Investigate for infectious agents, ensure antipyretic supplies are adequate, and monitor for potential disease cluster across households.'],
                    'infection'     => ['icon'=>'bi-bug-fill','color'=>'#dc2626','rec'=>'Infections are a top diagnosis. Verify antibiotic stock levels, review culture sensitivity patterns, and ensure proper wound care protocols are followed.'],
                    'ear'           => ['icon'=>'bi-ear-fill','color'=>'#7c3aed','rec'=>'Ear conditions are recurring. Recommend regular ear cleaning to pet owners, check for mite infestations, and ensure otoscopes and ear treatment supplies are stocked.'],
                    'eye'           => ['icon'=>'bi-eye-fill','color'=>'#2563eb','rec'=>'Ophthalmic conditions are frequent. Keep eye drops and ointments stocked. Advise owners to monitor for discharge, redness, or squinting and bring pets in early.'],
                    'wound'         => ['icon'=>'bi-bandaid-fill','color'=>'#ea580c','rec'=>'Wound-related cases are significant. Ensure wound care supplies (antiseptics, bandages, E-collars) are well-stocked. Remind owners to monitor healing and avoid licking.'],
                    'fracture'      => ['icon'=>'bi-bandaid-fill','color'=>'#dc2626','rec'=>'Trauma/fracture cases are notable. Review X-ray equipment readiness, ensure orthopedic support materials are available, and advise owners on safe confinement post-procedure.'],
                    'parasite'      => ['icon'=>'bi-bug-fill','color'=>'#65a30d','rec'=>'Parasitic conditions are prevalent. Push preventive deworming and flea/tick control to clients, especially those with outdoor pets or multiple animals.'],
                    'fleas'         => ['icon'=>'bi-bug-fill','color'=>'#65a30d','rec'=>'Flea infestation is a recurring issue. Recommend monthly topical or oral flea prevention, advise household treatment, and stock broad-spectrum antiparasitics.'],
                    'dental'        => ['icon'=>'bi-circle-fill','color'=>'#0891b2','rec'=>'Dental disease is commonly diagnosed. Consider promoting annual dental check-ups, recommend dental chews or water additives, and ensure dental equipment and anesthesia supplies are ready.'],
                    'obesity'       => ['icon'=>'bi-scale','color'=>'#d97706','rec'=>'Obesity is a noted condition. Counsel owners on proper portion sizing and low-calorie diets. Consider offering weight management follow-up consultations as a service.'],
                    'diabetes'      => ['icon'=>'bi-droplet-half','color'=>'#2563eb','rec'=>'Diabetic cases are present. Ensure insulin and glucose monitoring supplies are stocked. Educate owners on home glucose monitoring and dietary management.'],
                    'respiratory'   => ['icon'=>'bi-lungs-fill','color'=>'#0891b2','rec'=>'Respiratory issues are elevated. Ventilate boarding and treatment areas well, screen incoming boarders, and stock bronchodilators and nebulization equipment.'],
                    'cough'         => ['icon'=>'bi-lungs-fill','color'=>'#0891b2','rec'=>'Coughing is frequently reported. Rule out kennel cough, heart disease, or respiratory infections. Ensure isolation protocols are followed for suspected contagious cases.'],
                    'urinary'       => ['icon'=>'bi-droplet-fill','color'=>'#7c3aed','rec'=>'Urinary conditions are common. Advise increased water intake and diet changes. Keep urinalysis kits and urinary acidifiers stocked.'],
                    'arthritis'     => ['icon'=>'bi-person-walking','color'=>'#475569','rec'=>'Arthritis/joint pain is recurring. Stock NSAIDs and joint supplements. Recommend weight management and gentle exercise for affected pets.'],
                    'allergy'       => ['icon'=>'bi-wind','color'=>'#d97706','rec'=>'Allergy cases are elevated. Consider seasonal patterns, stock antihistamines and steroid options, and advise owners on identifying and eliminating allergen sources.'],
                    'default'       => ['icon'=>'bi-clipboard2-pulse','color'=>'#6366f1','rec'=>'This condition is among the most frequently diagnosed this period. Ensure treatment supplies are well-stocked, review existing clinical protocols, and monitor for any increase in frequency.'],
                ];

                $shownRecs = 0;
                foreach (array_slice($topDiagnoses, 0, 6, true) as $dName => $dCnt):
                    // Match rec by keyword
                    $recKey = 'default';
                    foreach (array_keys($diagRecs) as $keyword) {
                        if ($keyword !== 'default' && stripos($dName, $keyword) !== false) {
                            $recKey = $keyword;
                            break;
                        }
                    }
                    $rec  = $diagRecs[$recKey];
                    $dPct = ($totalConsults > 0) ? round($dCnt / $totalConsults * 100, 1) : 0;
                    $urgencyStyle = $dPct >= 30 ? 'background:#fef2f2;border:1px solid #fca5a5;' : ($dPct >= 15 ? 'background:#fffbeb;border:1px solid #fcd34d;' : 'background:#f8fafc;border:1px solid var(--border);');
                ?>
                <div class="col-md-6">
                    <div style="border-radius:10px;padding:14px 16px;<?= $urgencyStyle ?>">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <i class="bi <?= $rec['icon'] ?>" style="color:<?= $rec['color'] ?>;font-size:16px;flex-shrink:0;margin-top:1px;"></i>
                            <div>
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                                    <span style="font-size:13px;font-weight:800;color:var(--dark);text-transform:capitalize;"><?= htmlspecialchars(ucwords($dName)) ?></span>
                                    <span style="font-size:11px;font-weight:700;color:<?= $rec['color'] ?>;"><?= $dCnt ?> case<?= $dCnt!==1?'s':'' ?> · <?= $dPct ?>%</span>
                                </div>
                                <p style="font-size:12px;color:var(--muted);line-height:1.6;margin:0;"><?= $rec['rec'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php $shownRecs++; endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; // end $totalConsults > 0 ?>

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

</div><!-- /paneAnalytics -->

<!-- ── Insight Modal ── -->
<div id="insightModal" onclick="if(event.target===this)closeInsightModal()">
    <div id="insightModalBox">
        <div id="insightModalHeader">
            <span id="insightModalTitle"><i class="bi bi-bell-fill" style="color:#f59e0b;"></i> Insight &amp; Recommendation</span>
            <button id="insightModalClose" onclick="closeInsightModal()">&times;</button>
        </div>
        <div id="insightModalBody"></div>
        <div id="insightModalFooter">
            <div style="position:relative;display:inline-block;" id="insightExportWrap">
                <button class="rpt-btn rpt-btn-csv" onclick="toggleInsightExportMenu(event)" style="font-size:12px;gap:6px;">
                    <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down" style="font-size:10px;"></i>
                </button>
                <div id="insightExportMenu" style="display:none;position:absolute;bottom:calc(100% + 6px);left:0;background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);min-width:160px;overflow:hidden;z-index:10;">
                    <div style="padding:8px 12px 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--light-muted);">Export As</div>
                    <button class="insight-export-opt" onclick="exportInsight('csv')"><img src="https://img.icons8.com/color/20/csv.png" style="width:18px;height:18px;"> CSV</button>
                    <button class="insight-export-opt" onclick="exportInsight('xls')"><img src="https://img.icons8.com/color/20/xls.png" style="width:18px;height:18px;"> XLS</button>
                    <button class="insight-export-opt" onclick="exportInsight('xlsx')"><img src="https://img.icons8.com/color/20/xlsx.png" style="width:18px;height:18px;"> XLSX</button>
                    <button class="insight-export-opt" onclick="exportInsight('docx')"><img src="https://img.icons8.com/color/20/docx.png" style="width:18px;height:18px;"> DOCX</button>
                    <button class="insight-export-opt" onclick="exportInsight('pdf')" style="border-bottom:none;"><img src="https://img.icons8.com/color/20/pdf.png" style="width:18px;height:18px;"> PDF</button>
                </div>
            </div>
            <button class="rpt-apply-btn" onclick="closeInsightModal()">Close</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
const teal = '#0ea5e9', green = '#10b981', amber = '#f59e0b', red = '#ef4444', purple = '#8b5cf6', indigo = '#6366f1', gridColor = '#f1f5f9', tickColor = '#94a3b8', tickFont = { size: 11 };
const makeChart = (id, type, data, options) => new Chart(document.getElementById(id), { type, data, options });
const baseScales = { y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, font: tickFont } }, x: { grid: { display: false }, ticks: { color: tickColor, font: tickFont } } };

makeChart('monthlyRevenueChart', 'bar', {
    labels: <?= json_encode(array_column($revenueData,'label')) ?>,
    datasets: [
        { data: <?= json_encode(array_column($revenueData,'billed')) ?>, backgroundColor: '#e2e8f0', borderRadius: 6, borderSkipped: false },
        { data: <?= json_encode(array_column($revenueData,'collected')) ?>, backgroundColor: teal, borderRadius: 6, borderSkipped: false }
    ]
}, { responsive: true, plugins: { legend: { display: false } }, scales: { ...baseScales, y: { ...baseScales.y, ticks: { ...tickFont, callback: v => '₱' + v.toLocaleString() } } } });

makeChart('dailyRevenueChart', 'line', {
    labels: <?= json_encode(array_column($dailyRevData,'label')) ?>,
    datasets: [{ data: <?= json_encode(array_column($dailyRevData,'value')) ?>, borderColor: green, backgroundColor: 'rgba(16,185,129,.08)', borderWidth: 2.5, fill: true, tension: .4, pointBackgroundColor: green, pointRadius: 4, pointHoverRadius: 6 }]
}, { responsive: true, plugins: { legend: { display: false } }, scales: { ...baseScales, y: { ...baseScales.y, ticks: { ...tickFont, callback: v => '₱' + v.toLocaleString() } } } });

const asLabels = <?= json_encode(array_column($apptStats, 'Status')) ?>;
makeChart('apptStatusChart', 'doughnut', {
    labels: asLabels,
    datasets: [{ data: <?= json_encode(array_map('intval', array_column($apptStats, 'cnt'))) ?>, backgroundColor: asLabels.map(l => ({'Completed':green,'Scheduled':teal,'Cancelled':red,'No-Show':amber,'Pending':'#94a3b8'}[l] || '#cbd5e1')), borderWidth: 0, hoverOffset: 6 }]
}, { responsive: true, cutout: '70%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } });

makeChart('apptDayChart', 'bar', {
    labels: <?= json_encode(array_map(fn($d) => substr($d, 0, 3), array_column($apptByDay, 'day'))) ?>,
    datasets: [{ data: <?= json_encode(array_map('intval', array_column($apptByDay, 'cnt'))) ?>, backgroundColor: indigo, borderRadius: 6, borderSkipped: false }]
}, { responsive: true, plugins: { legend: { display: false } }, scales: baseScales });

const psLabels = <?= json_encode(array_column($payStatus, 'PaymentStatus')) ?>;
makeChart('payStatusChart', 'doughnut', {
    labels: psLabels,
    datasets: [{ data: <?= json_encode(array_map('floatval', array_column($payStatus, 'total'))) ?>, backgroundColor: psLabels.map(l => ({'Paid':green,'Pending':amber,'Partial':teal}[l] || '#94a3b8')), borderWidth: 0, hoverOffset: 6 }]
}, { responsive: false, cutout: '65%', plugins: { legend: { display: false } } });

makeChart('speciesChart', 'doughnut', {
    labels: <?= json_encode(array_column($species, 'Species')) ?>,
    datasets: [{ data: <?= json_encode(array_map('intval', array_column($species, 'cnt'))) ?>, backgroundColor: [teal, green, amber, purple, red, '#ec4899', '#94a3b8'].slice(0, <?= count($species) ?>), borderWidth: 0, hoverOffset: 6 }]
}, { responsive: false, cutout: '60%', plugins: { legend: { display: false } } });

makeChart('newClientsChart', 'line', {
    labels: <?= json_encode(array_column($newClientData,'label')) ?>,
    datasets: [{ data: <?= json_encode(array_column($newClientData,'value')) ?>, borderColor: teal, backgroundColor: 'rgba(14,165,233,.08)', borderWidth: 2.5, fill: true, tension: .4, pointBackgroundColor: teal, pointRadius: 4, pointHoverRadius: 6 }]
}, { responsive: true, plugins: { legend: { display: false } }, scales: { ...baseScales, y: { ...baseScales.y, ticks: { stepSize: 1 } } } });

// ── Consultation Trend Chart ──
const consultTrendEl = document.getElementById('consultTrendChart');
if (consultTrendEl) {
    makeChart('consultTrendChart', 'bar', {
        labels: <?= json_encode(array_column($consultTrend,'label')) ?>,
        datasets: [{ data: <?= json_encode(array_column($consultTrend,'value')) ?>, backgroundColor: indigo, borderRadius: 6, borderSkipped: false }]
    }, { responsive: true, plugins: { legend: { display: false } }, scales: { ...baseScales, y: { ...baseScales.y, ticks: { stepSize: 1 } } } });
}

// ── Follow-Up Rate Donut ──
const followUpEl = document.getElementById('followUpChart');
if (followUpEl) {
    const fuRate = <?= $followUpRate ?>;
    new Chart(followUpEl, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [fuRate, Math.max(0, 100 - fuRate)],
                backgroundColor: [fuRate >= 50 ? '#f59e0b' : fuRate >= 20 ? '#0ea5e9' : '#10b981', '#f1f5f9'],
                borderWidth: 0
            }]
        },
        options: { responsive: false, cutout: '72%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
    });
}

// ── Insight Modal Logic ──
const insightStyles = {
    danger:  { bg:'#fef2f2', border:'#fca5a5', icon:'#dc2626', text:'#991b1b' },
    warning: { bg:'#fffbeb', border:'#fcd34d', icon:'#d97706', text:'#92400e' },
    info:    { bg:'#eff6ff', border:'#93c5fd', icon:'#2563eb', text:'#1e40af' },
    success: { bg:'#f0fdf4', border:'#86efac', icon:'#16a34a', text:'#14532d' },
};

const moduleInsightsData = <?= json_encode($moduleInsights) ?>;

let _insightReady = true;

function openInsightModal(module) {
    const items = moduleInsightsData[module] || [];
    if (!items.length) return;
    window._currentInsightItems = items;
    window._currentInsightModule = module;
    const body = document.getElementById('insightModalBody');
    let html = '';
    items.forEach((ins, i) => {
        const s = insightStyles[ins.type] || insightStyles.info;
        html += `<div class="insight-item" style="background:${s.bg};border-color:${s.border};">
            <i class="bi ${ins.icon}" style="color:${s.icon};font-size:16px;flex-shrink:0;margin-top:2px;"></i>
            <div>
                <div class="insight-item-title" style="color:${s.text};">${ins.title}</div>
                <div class="insight-item-msg" style="color:${s.text};">${ins.msg}</div>
            </div>
        </div>`;
    });
    body.innerHTML = html;
    document.getElementById('insightModal').classList.add('open');
}

function closeInsightModal() {
    document.getElementById('insightModal').classList.remove('open');
}

function toggleInsightExportMenu(e) {
    e.stopPropagation();
    const menu = document.getElementById('insightExportMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', () => {
    const m = document.getElementById('insightExportMenu');
    if (m) m.style.display = 'none';
});

function exportInsight(format) {
    document.getElementById('insightExportMenu').style.display = 'none';
    const items = window._currentInsightItems || [];
    const module = window._currentInsightModule || 'insight';
    const filename = 'insight_' + module + '_' + Date.now();

    if (format === 'csv') {
        let csv = '"Type","Title","Recommendation"\n';
        items.forEach(ins => {
            csv += `"${ins.type}","${ins.title.replace(/"/g,'""')}","${ins.msg.replace(/"/g,'""')}"\n`;
        });
        downloadBlob(csv, filename + '.csv', 'text/csv');
    } else if (format === 'xls' || format === 'xlsx') {
        // Build simple XML-based spreadsheet (opens in Excel)
        let xml = `<?xml version="1.0"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Insights"><Table><Row><Cell><Data ss:Type="String">Type</Data></Cell><Cell><Data ss:Type="String">Title</Data></Cell><Cell><Data ss:Type="String">Recommendation</Data></Cell></Row>`;
        items.forEach(ins => {
            xml += `<Row><Cell><Data ss:Type="String">${escXml(ins.type)}</Data></Cell><Cell><Data ss:Type="String">${escXml(ins.title)}</Data></Cell><Cell><Data ss:Type="String">${escXml(ins.msg)}</Data></Cell></Row>`;
        });
        xml += `</Table></Worksheet></Workbook>`;
        downloadBlob(xml, filename + '.' + format, 'application/vnd.ms-excel');
    } else if (format === 'docx') {
        // RTF disguised as docx — opens in Word
        let rtf = '{\\rtf1\\ansi\\deff0{\\fonttbl{\\f0 Calibri;}}{\\colortbl ;\\red0\\green0\\blue0;}\\f0\\fs24\\b Clinic Insights\\b0\\line Module: ' + module.toUpperCase() + '\\line Generated: ' + new Date().toLocaleString().replace(/[<>&]/g,'') + '\\par\\par ';
        items.forEach((ins, i) => {
            rtf += '\\b ' + (i+1) + '. [' + ins.type.toUpperCase() + '] ' + ins.title.replace(/[\\{}]/g,'') + '\\b0\\line ' + ins.msg.replace(/[\\{}]/g,'') + '\\par ';
        });
        rtf += '}';
        downloadBlob(rtf, filename + '.doc', 'application/msword');
    } else if (format === 'pdf') {
        // Build a print-friendly HTML window and trigger browser print-to-PDF
        const s = { danger:'#991b1b', warning:'#92400e', info:'#1e40af', success:'#14532d' };
        const bg = { danger:'#fef2f2', warning:'#fffbeb', info:'#eff6ff', success:'#f0fdf4' };
        let body = `<html><head><title>Insight Report</title><style>body{font-family:Arial,sans-serif;padding:32px;color:#1e293b;}h2{margin-bottom:4px;}p.meta{font-size:12px;color:#64748b;margin-top:0;}
        .item{border-radius:8px;padding:14px 16px;margin-bottom:12px;}.title{font-weight:700;font-size:14px;margin-bottom:4px;}.msg{font-size:13px;line-height:1.6;}</style></head><body>
        <h2>Clinic Insights — ${module.toUpperCase()}</h2><p class="meta">Generated: ${new Date().toLocaleString()}</p>`;
        items.forEach(ins => {
            body += `<div class="item" style="background:${bg[ins.type]||'#f8fafc'};border:1px solid ${ins.type==='danger'?'#fca5a5':ins.type==='warning'?'#fcd34d':ins.type==='success'?'#86efac':'#93c5fd'};">
            <div class="title" style="color:${s[ins.type]||'#1e40af'};">[${ins.type.toUpperCase()}] ${ins.title}</div>
            <div class="msg" style="color:${s[ins.type]||'#1e40af'};">${ins.msg}</div></div>`;
        });
        body += '</body></html>';
        const w = window.open('', '_blank', 'width=700,height=600');
        w.document.write(body);
        w.document.close();
        w.focus();
        setTimeout(() => w.print(), 400);
    }
}

function escXml(str) { return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function downloadBlob(content, filename, mime) {
    const blob = new Blob([content], { type: mime });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeInsightModal(); });

// ── Date filter logic (Reports tab) ──
function applyFilter(module) {
    const from = document.getElementById('from-' + module).value;
    const to   = document.getElementById('to-'   + module).value;
    if (!from || !to) return;
    document.getElementById('pdf-' + module).href = `report_pdf.php?type=${module}&back=reports&from=${from}&to=${to}`;
    ['csv','xls','xlsx','docx'].forEach(fmt => {
        document.getElementById(fmt + '-' + module).href = `reports.php?export=${module}&format=${fmt}&from=${from}&to=${to}`;
    });
    const fmtDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
    document.getElementById('range-' + module).textContent = fmtDate(from) + ' – ' + fmtDate(to);
    document.getElementById('count-' + module).textContent = '…';
    fetch(`reports.php?get_count=${module}&from=${from}&to=${to}`)
        .then(r => r.json())
        .then(d => { document.getElementById('count-' + module).textContent = d.count; })
        .catch(() => { document.getElementById('count-' + module).textContent = '—'; });
}


</script>

<?php include('footer.php'); ?>