<?php
require_once 'guard.php';
date_default_timezone_set('Asia/Manila');
include('dbconnect.php');

// ── Parameters ──
$type     = $_GET['type']  ?? 'analytics'; // clients | pets | appointments | consultations | billing | services | lodging | analytics
$dateFrom = $_GET['from']  ?? date('Y-m-01');
$dateTo   = $_GET['to']    ?? date('Y-m-d');
$range    = $_GET['range'] ?? '30';

// ── Module metadata ──
$moduleMap = [
    'clients'       => ['label' => 'Clients / Owners',           'icon' => '👥', 'color' => '#3b82f6'],
    'pets'          => ['label' => 'Pets / Patients',            'icon' => '🐾', 'color' => '#ec4899'],
    'appointments'  => ['label' => 'Appointments',               'icon' => '📅', 'color' => '#6366f1'],
    'consultations' => ['label' => 'Medical History',            'icon' => '📋', 'color' => '#10b981'],
    'billing'       => ['label' => 'Billing & Payments',         'icon' => '🧾', 'color' => '#f97316'],
    'services'      => ['label' => 'Clinic Services',            'icon' => '📦', 'color' => '#7c3aed'],
    'lodging'       => ['label' => 'Lodging & Boarding',         'icon' => '🏠', 'color' => '#7c3aed'],
    'analytics'     => ['label' => 'Analytics & Performance',    'icon' => '📊', 'color' => '#ef4444'],
];
$meta = $moduleMap[$type] ?? $moduleMap['analytics'];

// ── Fetch data based on type ──
$rows = [];
$columns = [];

switch ($type) {
    case 'clients':
        $columns = ['Client ID', 'Full Name', 'Email', 'Phone', 'Address', 'Date Registered'];
        $res = $conn->query("SELECT CONCAT('CLT-',LPAD(ClientID,4,'0')) AS ClientID,
            CONCAT(FirstName,' ',LastName) AS FullName,
            Email, Phone, Address,
            DATE_FORMAT(CreatedAt,'%Y-%m-%d') AS DateRegistered
            FROM clients WHERE IsDeleted=0 ORDER BY CreatedAt DESC");
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        break;

    case 'pets':
        $columns = ['Pet ID', 'Pet Name', 'Owner', 'Species', 'Breed', 'Gender', 'Date of Birth', 'Weight (kg)'];
        $res = $conn->query("SELECT CONCAT('PET-',LPAD(p.PetID,4,'0')) AS PetID, p.PetName,
            CONCAT(c.FirstName,' ',c.LastName) AS Owner,
            p.Species, p.Breed, p.Gender, p.DateOfBirth, p.Weight
            FROM pets p JOIN clients c ON p.ClientID=c.ClientID
            WHERE p.IsDeleted=0 ORDER BY p.PetName");
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        break;

    case 'appointments':
        $columns = ['ID', 'Date', 'Time', 'Pet', 'Owner', 'Service', 'Reason', 'Status'];
        $df = $conn->real_escape_string($dateFrom);
        $dt = $conn->real_escape_string($dateTo);
        $res = $conn->query("SELECT CONCAT('APT-',LPAD(a.AppointmentID,4,'0')) AS AppointmentID,
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
            AND a.AppointmentDate BETWEEN '$df' AND '$dt'
            ORDER BY a.AppointmentDate DESC, a.AppointmentTime DESC");
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        break;

    case 'consultations':
        $columns = ['Consult ID', 'Date', 'Pet', 'Owner', 'Veterinarian', 'Chief Complaint', 'Diagnosis', 'Treatment', 'Prescription', 'Follow-Up'];
        $df = $conn->real_escape_string($dateFrom);
        $dt = $conn->real_escape_string($dateTo);
        $res = $conn->query("SELECT CONCAT('CON-',LPAD(con.ConsultationID,4,'0')) AS ConsultationID,
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
            AND DATE(con.ConsultationDate) BETWEEN '$df' AND '$dt'
            ORDER BY con.ConsultationDate DESC");
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        break;

    case 'billing':
        $columns = ['Bill ID', 'Date', 'Client', 'Pet', 'Total', 'Paid', 'Balance', 'Method', 'Status', 'Notes'];
        $df = $conn->real_escape_string($dateFrom);
        $dt = $conn->real_escape_string($dateTo);
        $res = $conn->query("SELECT CONCAT('INV-',LPAD(b.BillingID,4,'0')) AS BillingID,
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
            AND b.BillingDate BETWEEN '$df' AND '$dt'
            ORDER BY b.BillingDate DESC");
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        break;

    case 'services':
        $columns = ['Service ID', 'Name', 'Category', 'Price', 'Duration (min)', 'Description', 'Status'];
        $res = $conn->query("SELECT CONCAT('SVC-',LPAD(ServiceID,4,'0')) AS ServiceID, ServiceName, Category,
            Price, Duration, Description,
            IF(IsActive=1,'Active','Inactive') AS Status
            FROM services WHERE IsDeleted=0 ORDER BY ServiceName");
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        break;

    case 'lodging':
        $columns = ['Lodging ID', 'Pet', 'Owner', 'Check-In', 'Check-Out', 'Cage #', 'Daily Rate', 'Status', 'Instructions'];
        $res = $conn->query("SELECT CONCAT('LDG-',LPAD(l.LodgingID,4,'0')) AS LodgingID,
            p.PetName,
            CONCAT(c.FirstName,' ',c.LastName) AS Owner,
            l.CheckInDate, l.CheckOutDate,
            l.CageNumber, l.DailyRate, l.Status, l.SpecialInstructions
            FROM lodging l
            JOIN pets p ON l.PetID=p.PetID
            JOIN clients c ON l.ClientID=c.ClientID
            WHERE l.IsDeleted=0 ORDER BY l.CheckInDate DESC");
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        break;

    case 'analytics':
    default:
        // Full analytics report (original behaviour kept for backward compat)
        break;
}

// ── Analytics-only data (only when type=analytics) ──
if ($type === 'analytics') {
    $dateApptTo = date('Y-m-d', strtotime("+{$range} days"));
    $rangeLabel = match($range) {
        '7' => 'Last 7 Days', '30' => 'Last 30 Days', '90' => 'Last 90 Days',
        '365' => 'Last 12 Months', 'custom' => 'Custom Range',
        default => "Last {$range} Days"
    };

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
        SELECT COALESCE(SUM(AmountPaid),0) as totalRevenue,
            COALESCE(SUM(CASE WHEN PaymentStatus!='Paid' THEN TotalAmount-AmountPaid END),0) as totalPending,
            COUNT(*) as totalBills,
            COUNT(CASE WHEN PaymentStatus='Paid' THEN 1 END) as paidBills
        FROM billing WHERE BillingDate BETWEEN '$dateFrom' AND '$dateTo'
    ")->fetch_assoc();
    extract($kpi);
    extract($billingStats);
    $avgRevPerAppt  = ($completedAppt > 0) ? round($totalRevenue / $completedAppt, 2) : 0;
    $collectionRate = ($totalRevenue + $totalPending > 0) ? round($totalRevenue / ($totalRevenue + $totalPending) * 100, 1) : 0;
    $completionRate = ($totalAppt > 0) ? round($completedAppt / $totalAppt * 100, 1) : 0;
    $apptStats  = $conn->query("SELECT Status, COUNT(*) AS cnt FROM appointments WHERE AppointmentDate BETWEEN '$dateFrom' AND '$dateApptTo' AND COALESCE(IsDeleted,0)=0 GROUP BY Status")->fetch_all(MYSQLI_ASSOC);
    $apptByDay  = $conn->query("SELECT DAYNAME(AppointmentDate) AS day, COUNT(*) AS cnt FROM appointments WHERE AppointmentDate BETWEEN '$dateFrom' AND '$dateApptTo' AND COALESCE(IsDeleted,0)=0 GROUP BY DAYNAME(AppointmentDate), DAYOFWEEK(AppointmentDate) ORDER BY DAYOFWEEK(AppointmentDate)")->fetch_all(MYSQLI_ASSOC);
    $species    = $conn->query("SELECT Species, COUNT(*) AS cnt FROM pets WHERE IsActive=1 GROUP BY Species ORDER BY cnt DESC")->fetch_all(MYSQLI_ASSOC);
    $payStatus  = $conn->query("SELECT PaymentStatus, COUNT(*) AS cnt, COALESCE(SUM(TotalAmount),0) AS total FROM billing WHERE BillingDate BETWEEN '$dateFrom' AND '$dateTo' GROUP BY PaymentStatus")->fetch_all(MYSQLI_ASSOC);
    $payMethods = $conn->query("SELECT PaymentMethod, COUNT(*) AS cnt, COALESCE(SUM(AmountPaid),0) AS total FROM billing WHERE BillingDate BETWEEN '$dateFrom' AND '$dateTo' AND PaymentMethod IS NOT NULL AND PaymentMethod != '' GROUP BY PaymentMethod ORDER BY total DESC")->fetch_all(MYSQLI_ASSOC);
    $topSvcs    = $conn->query("SELECT s.ServiceName, s.Price, COUNT(a.AppointmentID) AS cnt FROM appointments a JOIN services s ON a.ServiceID=s.ServiceID WHERE COALESCE(a.IsDeleted,0)=0 AND a.AppointmentDate BETWEEN '$dateFrom' AND '$dateTo' GROUP BY s.ServiceID ORDER BY cnt DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
    $topClients = $conn->query("SELECT CONCAT(c.FirstName,' ',c.LastName) AS Client, c.Email, c.Phone, COUNT(b.BillingID) AS bills, COALESCE(SUM(b.AmountPaid),0) AS spent FROM billing b JOIN clients c ON b.ClientID=c.ClientID WHERE b.BillingDate BETWEEN '$dateFrom' AND '$dateTo' GROUP BY b.ClientID ORDER BY spent DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
    $recentBilling = $conn->query("SELECT b.*, CONCAT(c.FirstName,' ',c.LastName) AS Client, p.PetName, p.Species FROM billing b JOIN clients c ON b.ClientID=c.ClientID LEFT JOIN pets p ON b.PetID=p.PetID WHERE b.BillingDate BETWEEN '$dateFrom' AND '$dateTo' ORDER BY b.CreatedAt DESC LIMIT 30");
    $revenueData = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $label = date('M Y', strtotime("-$i months"));
        $row = $conn->query("SELECT COALESCE(SUM(AmountPaid),0) as col, COALESCE(SUM(TotalAmount),0) as bil FROM billing WHERE DATE_FORMAT(BillingDate,'%Y-%m')='$month'")->fetch_assoc();
        $revenueData[] = ['label'=>$label, 'collected'=>floatval($row['col']), 'billed'=>floatval($row['bil'])];
    }

    // ── New Client Trend ──
    $newClientData = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $label = date('M Y', strtotime("-$i months"));
        $val   = $conn->query("SELECT COUNT(*) FROM clients WHERE DATE_FORMAT(CreatedAt,'%Y-%m')='$month' AND IsActive=1")->fetch_row()[0];
        $newClientData[] = ['label' => $label, 'value' => intval($val)];
    }

    // ── Consultation Analytics ──
    $totalConsults = intval($conn->query("SELECT COUNT(*) FROM consultations WHERE IsDeleted=0 AND DATE(ConsultationDate) BETWEEN '$dateFrom' AND '$dateTo'")->fetch_row()[0]);
    $followUpCount = intval($conn->query("SELECT COUNT(*) FROM consultations WHERE IsDeleted=0 AND FollowUpDate IS NOT NULL AND DATE(ConsultationDate) BETWEEN '$dateFrom' AND '$dateTo'")->fetch_row()[0]);
    $followUpRate  = ($totalConsults > 0) ? round($followUpCount / $totalConsults * 100, 1) : 0;

    // Top diagnoses
    $diagRaw = $conn->query("SELECT Diagnosis FROM consultations WHERE IsDeleted=0 AND Diagnosis IS NOT NULL AND Diagnosis != '' AND DATE(ConsultationDate) BETWEEN '$dateFrom' AND '$dateTo'")->fetch_all(MYSQLI_ASSOC);
    $diagCounts = [];
    foreach ($diagRaw as $d) {
        $parts = preg_split('/[,;\/]|\s+and\s+|\s+with\s+/i', $d['Diagnosis']);
        foreach ($parts as $part) {
            $clean = trim($part);
            if (strlen($clean) < 3) continue;
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

    // Top vets
    $topVets = $conn->query("SELECT VetName, COUNT(*) AS cnt FROM consultations WHERE IsDeleted=0 AND VetName IS NOT NULL AND VetName != '' AND DATE(ConsultationDate) BETWEEN '$dateFrom' AND '$dateTo' GROUP BY VetName ORDER BY cnt DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

    // Monthly consultation trend (last 6 months)
    $consultTrend = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $label = date('M Y', strtotime("-$i months"));
        $val   = $conn->query("SELECT COUNT(*) FROM consultations WHERE IsDeleted=0 AND DATE_FORMAT(ConsultationDate,'%Y-%m')='$month'")->fetch_row()[0];
        $consultTrend[] = ['label' => $label, 'value' => intval($val)];
    }

    // ── Smart Insights ──
    $DAILY_APPT_CAPACITY = 50; // max active patients / daily appointment capacity
    $BOARDING_CAPACITY   = 10; // max boarding cages (C1–C10)
    $insights = [];

    // 1. Today's appointment load
    $todayAppts = intval($conn->query("
        SELECT COUNT(*) FROM appointments
        WHERE AppointmentDate = CURDATE()
        AND Status NOT IN ('Cancelled','No-Show')
        AND COALESCE(IsDeleted,0)=0
    ")->fetch_row()[0]);
    $apptLoadPct = ($DAILY_APPT_CAPACITY > 0) ? round($todayAppts / $DAILY_APPT_CAPACITY * 100) : 0;
    if ($apptLoadPct >= 100) {
        $insights[] = ['type'=>'danger',  'title'=>'Fully booked today — '.$todayAppts.' / '.$DAILY_APPT_CAPACITY.' appointments ('.$apptLoadPct.'%)', 'msg'=>'No more appointment slots available today. Redirect new booking requests to tomorrow or the next available date.'];
    } elseif ($apptLoadPct >= 75) {
        $insights[] = ['type'=>'warning', 'title'=>'Heavy schedule today — '.$todayAppts.' / '.$DAILY_APPT_CAPACITY.' appointments ('.$apptLoadPct.'%)', 'msg'=>'Only '.($DAILY_APPT_CAPACITY-$todayAppts).' slot(s) remaining today. Consider managing walk-in expectations.'];
    } else {
        $insights[] = ['type'=>'success', 'title'=>'Schedule is manageable — '.$todayAppts.' appointment(s) today ('.$apptLoadPct.'% of daily capacity)', 'msg'=>($DAILY_APPT_CAPACITY-$todayAppts).' slot(s) still open today. Ready to accept walk-ins or new bookings.'];
    }

    // 1b. Boarding cage occupancy
    $boardingPct = ($BOARDING_CAPACITY > 0) ? round($activeLodging / $BOARDING_CAPACITY * 100) : 0;
    if ($boardingPct >= 100)
        $insights[] = ['type'=>'danger',  'title'=>'All cages occupied — '.$activeLodging.' / '.$BOARDING_CAPACITY.' cages in use (100%)', 'msg'=>'No boarding cages available. Decline new boarding requests until a pet checks out.'];
    elseif ($boardingPct >= 60)
        $insights[] = ['type'=>'warning', 'title'=>'Boarding nearly full — '.$activeLodging.' / '.$BOARDING_CAPACITY.' cages in use ('.$boardingPct.'%)', 'msg'=>'Only '.($BOARDING_CAPACITY-$activeLodging).' cage(s) remaining. Consider a waitlist.'];
    else
        $insights[] = ['type'=>'success', 'title'=>'Boarding capacity available — '.$activeLodging.' / '.$BOARDING_CAPACITY.' cages in use ('.$boardingPct.'%)', 'msg'=>($BOARDING_CAPACITY-$activeLodging).' cage(s) free. Ready to accept new boarding pets.'];
    $noShowCnt = 0; $cancelledCnt = 0;
    foreach ($apptStats as $as) {
        if ($as['Status'] === 'No-Show')   $noShowCnt   = intval($as['cnt']);
        if ($as['Status'] === 'Cancelled') $cancelledCnt = intval($as['cnt']);
    }
    $badApptPct = ($totalAppt > 0) ? round(($noShowCnt + $cancelledCnt) / $totalAppt * 100, 1) : 0;
    if ($totalAppt > 0) {
        if ($badApptPct >= 20)
            $insights[] = ['type'=>'danger',  'title'=>$badApptPct.'% no-show / cancellation — '.($noShowCnt+$cancelledCnt).' of '.$totalAppt.' appointments lost', 'msg'=>'Contact clients 24 hrs before their appointment.'];
        elseif ($badApptPct >= 10)
            $insights[] = ['type'=>'warning', 'title'=>$badApptPct.'% no-show / cancellation rate ('.($noShowCnt+$cancelledCnt).' appointments)', 'msg'=>'Send reminders to all upcoming Scheduled appointments.'];
        else
            $insights[] = ['type'=>'success', 'title'=>'Good attendance — '.$badApptPct.'% no-show / cancellation rate', 'msg'=>$completionRate.'% of appointments completed this period.'];
    }
    if ($totalBills > 0) {
        if ($collectionRate < 60)
            $insights[] = ['type'=>'danger',  'title'=>'Low collection rate — only '.$collectionRate.'% of billed amount collected', 'msg'=>'Follow up with clients who have outstanding balances above ₱500.'];
        elseif ($collectionRate < 80)
            $insights[] = ['type'=>'warning', 'title'=>'Collection rate at '.$collectionRate.'% — '.($totalBills-$paidBills).' unpaid bill(s) · ₱'.number_format($totalPending,0).' outstanding', 'msg'=>'Collect outstanding balances before the period closes.'];
        else
            $insights[] = ['type'=>'success', 'title'=>'Strong collection rate — '.$collectionRate.'% · ₱'.number_format($totalRevenue,0).' collected this period', 'msg'=>$paidBills.' of '.$totalBills.' bills fully paid.'];
    }
    $topSpeciesCnt  = !empty($species) ? intval($species[0]['cnt']) : 0;
    $topSpeciesName = !empty($species) ? $species[0]['Species'] : '';
    $topSpeciesPct  = ($totalPets > 0 && $topSpeciesCnt > 0) ? round($topSpeciesCnt / $totalPets * 100) : 0;
    if ($topSpeciesPct >= 60 && $totalPets >= 2)
        $insights[] = ['type'=>'info', 'title'=>$topSpeciesName.'s make up '.$topSpeciesPct.'% of registered patients', 'msg'=>'Consider promotions for cats, exotic pets, or other species to diversify your patient base.'];
    $topSvcCnt   = !empty($topSvcs) ? intval($topSvcs[0]['cnt']) : 0;
    $topSvcTotal = array_sum(array_column($topSvcs, 'cnt'));
    $topSvcPct   = ($topSvcTotal > 0 && $topSvcCnt > 0) ? round($topSvcCnt / $topSvcTotal * 100) : 0;
    if ($topSvcPct >= 50 && $topSvcTotal >= 2)
        $insights[] = ['type'=>'info', 'title'=>'"'.htmlspecialchars($topSvcs[0]['ServiceName'] ?? '').'" is '.$topSvcPct.'% of all service bookings', 'msg'=>'Bundle with lower-booked services at a slight discount to balance demand.'];

    // New client growth vs last month
    $prevMonthClients = intval($conn->query("SELECT COUNT(*) FROM clients WHERE DATE_FORMAT(CreatedAt,'%Y-%m')='".date('Y-m', strtotime('-1 month'))."' AND IsActive=1")->fetch_row()[0]);
    if ($newClients === 0 && $prevMonthClients > 0) {
        $insights[] = ['type'=>'warning', 'title'=>'No new clients registered in this period', 'msg'=>'Last month had '.$prevMonthClients.' new clients. Consider a referral promotion or verify the registration workflow.'];
    } elseif ($prevMonthClients > 0 && $newClients >= $prevMonthClients * 1.2) {
        $growthPct = round(($newClients - $prevMonthClients) / $prevMonthClients * 100);
        $insights[] = ['type'=>'success', 'title'=>'New client intake up '.$growthPct.'% vs last month ('.$newClients.' vs '.$prevMonthClients.')', 'msg'=>'Strong growth trend. Confirm appointment slots can absorb the increase.'];
    } elseif ($newClients > 0) {
        $insights[] = ['type'=>'info', 'title'=>$newClients.' new client(s) registered this period', 'msg'=>'Steady intake. '.($prevMonthClients > 0 ? 'Last month had '.$prevMonthClients.' new clients.' : 'Keep tracking month-over-month to spot trends.')];
    }

    // Most frequent diagnosis
    if (!empty($topDiagnoses)) {
        $topDiagName  = array_key_first($topDiagnoses);
        $topDiagCount = $topDiagnoses[$topDiagName];
        $diagShare    = ($totalConsults > 0) ? round($topDiagCount / $totalConsults * 100) : 0;
        if ($diagShare >= 40)
            $insights[] = ['type'=>'danger',  'title'=>'Outbreak alert — "'.ucwords($topDiagName).'" is the most frequent diagnosis ('.$topDiagCount.' cases, '.$diagShare.'% of consultations)', 'msg'=>'Ensure adequate medication stock. Consider posting a pet health advisory and tracking whether it is spreading across species or households.'];
        elseif ($diagShare >= 20)
            $insights[] = ['type'=>'warning', 'title'=>'"'.ucwords($topDiagName).'" is the most common diagnosis — '.$topDiagCount.' cases ('.$diagShare.'% of consultations)', 'msg'=>'Review treatment protocols, confirm adequate inventory for related medications, and consider wellness reminders for at-risk pets.'];
        else
            $insights[] = ['type'=>'info',    'title'=>'Top diagnosis this period: "'.ucwords($topDiagName).'" — '.$topDiagCount.' case(s) ('.$diagShare.'% of consultations)', 'msg'=>'No alarming concentration. '.count($topDiagnoses).' distinct diagnosis types recorded this period.'];
    }

    // Follow-up rate
    if ($totalConsults > 0) {
        if ($followUpRate >= 50)
            $insights[] = ['type'=>'warning', 'title'=>'High follow-up rate — '.$followUpRate.'% of consultations ('.$followUpCount.' of '.$totalConsults.') require a follow-up visit', 'msg'=>'A high rate may indicate recurring or unresolved conditions. Review common diagnoses driving follow-ups.'];
        elseif ($followUpRate >= 20)
            $insights[] = ['type'=>'info',    'title'=>$followUpRate.'% follow-up rate — '.$followUpCount.' of '.$totalConsults.' consultations have a scheduled return', 'msg'=>'Normal follow-up rate. Confirm that follow-up dates are being tracked and clients are receiving reminders.'];
        else
            $insights[] = ['type'=>'success', 'title'=>'Low follow-up rate — '.$followUpRate.'% ('.$followUpCount.' of '.$totalConsults.' consultations)', 'msg'=>'Most cases resolve in a single visit. Monitor whether low follow-up correlates with treatment success or unreported return visits.'];
    }

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Heartside Vet — <?= htmlspecialchars($meta['label']) ?> Report</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #1e293b; background: #fff; }
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    color-adjust: exact !important;
  }

  @media screen {
    body { background: #f1f5f9; }
    .page { background: #fff; max-width: 960px; margin: 24px auto; padding: 40px 48px; box-shadow: 0 4px 24px rgba(0,0,0,.10); border-radius: 10px; }
    .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #0f172a; color: #fff; padding: 12px 32px; display: flex; align-items: center; justify-content: space-between; z-index: 100; font-size: 13px; }
    .print-btn { background: #10b981; color: #fff; border: none; padding: 8px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
    .print-btn:hover { background: #059669; }
    .back-link { color: #94a3b8; text-decoration: none; font-size: 13px; }
    .back-link:hover { color: #fff; }
    body { padding-top: 52px; }
  }
  @media print {
    .print-bar { display: none !important; }
    .page { padding: 0; }
    body { background: #fff; }
    .page-break { page-break-before: always; }
    .bar-fill, .bar-track, .badge, .badge-paid, .badge-pending, .badge-partial,
    .kpi-card, .summary-banner, .rev-box, thead {
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }
    .summary-banner { background: linear-gradient(135deg, #0ea5e9, #10b981) !important; }
    .badge-paid    { background: #dcfce7 !important; color: #15803d !important; }
    .badge-pending { background: #fef9c3 !important; color: #a16207 !important; }
    .badge-partial { background: #e0f2fe !important; color: #0369a1 !important; }
    thead { background: #f8fafc !important; }
    tr:nth-child(even) td { background: #fafafa !important; }
  }

  /* ── Header ── */
  .report-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid <?= $meta['color'] ?>; padding-bottom: 16px; margin-bottom: 24px; }
  .report-title { font-size: 22px; font-weight: 800; color: #0f172a; }
  .report-subtitle { font-size: 13px; color: <?= $meta['color'] ?>; margin-top: 4px; font-weight: 600; }
  .report-meta { text-align: right; font-size: 11px; color: #64748b; line-height: 1.6; }
  .report-meta strong { color: #0f172a; font-size: 13px; }

  /* ── Module badge ── */
  .module-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: <?= $meta['color'] ?>18;
    border: 1px solid <?= $meta['color'] ?>44;
    border-radius: 8px;
    padding: 6px 14px;
    font-size: 13px; font-weight: 700; color: <?= $meta['color'] ?>;
    margin-bottom: 20px;
  }

  /* ── Record count summary ── */
  .rec-summary {
    display: flex; align-items: center; gap: 16px;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 12px 18px; margin-bottom: 20px;
  }
  .rec-count { font-size: 28px; font-weight: 800; color: <?= $meta['color'] ?>; }
  .rec-label { font-size: 11px; color: #64748b; font-weight: 500; }

  /* ── Section Labels ── */
  .section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin: 20px 0 10px; padding-bottom: 4px; border-bottom: 1px solid #e2e8f0; }

  /* ── Tables ── */
  table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 4px; }
  thead { background: #f8fafc; }
  th { padding: 8px 10px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; border-bottom: 2px solid <?= $meta['color'] ?>44; }
  td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  tr:nth-child(even) td { background: #fafafa; }

  /* ── Status badges ── */
  .badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 10px; font-weight: 600; }
  .badge-active   { background: #dcfce7; color: #15803d; }
  .badge-inactive { background: #f1f5f9; color: #475569; }
  .badge-paid     { background: #dcfce7; color: #15803d; }
  .badge-pending  { background: #fef9c3; color: #a16207; }
  .badge-partial  { background: #e0f2fe; color: #0369a1; }
  .badge-cancelled{ background: #fef2f2; color: #dc2626; }
  .badge-scheduled{ background: #eff6ff; color: #1d4ed8; }
  .badge-completed{ background: #dcfce7; color: #15803d; }
  .badge-boarding { background: #f5f3ff; color: #7c3aed; }
  .badge-noshow   { background: #fef9c3; color: #a16207; }

  /* ── KPI Grid (analytics only) ── */
  .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 8px; }
  .kpi-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; }
  .kpi-val  { font-size: 20px; font-weight: 800; color: #0f172a; }
  .kpi-lbl  { font-size: 11px; color: #64748b; margin-top: 2px; }
  .kpi-trend { font-size: 10px; color: #10b981; margin-top: 4px; }

  /* ── Revenue Grid (analytics only) ── */
  .rev-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .rev-box  { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; }
  .rev-box-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #94a3b8; margin-bottom: 10px; }

  /* ── Bar (inline chart replacement) ── */
  .bar-row { display: flex; align-items: center; gap: 8px; margin-bottom: 7px; }
  .bar-label { width: 90px; font-size: 11px; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-shrink: 0; }
  .bar-track { flex: 1; background: #e2e8f0 !important; border-radius: 4px; height: 10px; overflow: hidden; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
  .bar-fill  { height: 100%; border-radius: 4px; display: block; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
  .bar-val   { width: 64px; font-size: 11px; font-weight: 600; text-align: right; color: #1e293b; flex-shrink: 0; }

  /* ── Summary Banner (analytics only) ── */
  .summary-banner { background: linear-gradient(135deg, #0ea5e9, #10b981); border-radius: 10px; padding: 20px 24px; color: #fff; margin-top: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
  .summary-big   { font-size: 24px; font-weight: 800; }
  .summary-small { font-size: 11px; opacity: .8; margin-top: 4px; }
  .summary-stats { display: flex; gap: 24px; }
  .sum-stat-val  { font-size: 18px; font-weight: 700; }
  .sum-stat-lbl  { font-size: 10px; opacity: .7; }

  /* ── Insight Cards ── */
  .insight-block { margin-bottom: 20px; }
  .insight-row { display: flex; align-items: flex-start; gap: 10px; border-radius: 7px; padding: 9px 13px; margin-bottom: 6px; border-left: 3px solid; }
  .insight-row.danger  { background: #fef2f2; border-color: #dc2626; }
  .insight-row.warning { background: #fffbeb; border-color: #d97706; }
  .insight-row.success { background: #f0fdf4; border-color: #16a34a; }
  .insight-row.info    { background: #eff6ff; border-color: #2563eb; }
  .insight-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
  .insight-row.danger  .insight-dot { background: #dc2626; }
  .insight-row.warning .insight-dot { background: #d97706; }
  .insight-row.success .insight-dot { background: #16a34a; }
  .insight-row.info    .insight-dot { background: #2563eb; }
  .insight-title { font-size: 11px; font-weight: 700; color: #0f172a; margin-bottom: 2px; }
  .insight-msg   { font-size: 10px; color: #475569; line-height: 1.5; }

  .footer-note { text-align: center; font-size: 10px; color: #94a3b8; margin-top: 20px; padding-top: 12px; border-top: 1px solid #e2e8f0; }

  /* ── Date range chip ── */
  .date-range-chip {
    background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px;
    padding: 4px 10px; font-size: 11px; font-weight: 600; color: #1d4ed8;
    display: inline-block;
  }
</style>
</head>
<body>

<!-- ── Print Bar (screen only) ── -->
<div class="print-bar">
  <a href="reports.php" class="back-link">← Back to Reports</a>
  <span style="color:#94a3b8;">Heartside Vet · <?= htmlspecialchars($meta['label']) ?> Report</span>
  <button class="print-btn" onclick="window.print()">⬇ Download / Print PDF</button>
</div>

<div class="page">

  <!-- ── Report Header ── -->
  <div class="report-header">
    <div>
      <div class="report-title">Heartside Vet</div>
      <div class="report-subtitle"><?= htmlspecialchars($meta['label']) ?> Report</div>
    </div>
    <div class="report-meta">
      <strong>Generated: <?= date('M d, Y g:i A') ?></strong><br>
      <?php if ($type !== 'analytics' && $type !== 'clients' && $type !== 'pets' && $type !== 'services' && $type !== 'lodging'): ?>
        Date Range: <?= date('M d, Y', strtotime($dateFrom)) ?> – <?= date('M d, Y', strtotime($dateTo)) ?><br>
      <?php elseif ($type === 'analytics'): ?>
        Period: <?= date('M d, Y', strtotime($dateFrom)) ?> – <?= date('M d, Y', strtotime($dateTo)) ?><br>
      <?php else: ?>
        All records as of today<br>
      <?php endif; ?>
      Heartside Veterinary Clinic
    </div>
  </div>

<?php if ($type !== 'analytics'): ?>

  <!-- ── Module Badge ── -->
  <div class="module-badge"><?= $meta['icon'] ?> <?= htmlspecialchars($meta['label']) ?></div>

  <!-- ── Record Count ── -->
  <div class="rec-summary">
    <div class="rec-count"><?= number_format(count($rows)) ?></div>
    <div>
      <div style="font-size:13px;font-weight:700;color:#0f172a;">Total Records</div>
      <div class="rec-label">
        <?php if (in_array($type, ['appointments','consultations','billing'])): ?>
          <?= date('M d, Y', strtotime($dateFrom)) ?> – <?= date('M d, Y', strtotime($dateTo)) ?>
        <?php else: ?>
          All records included
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Data Table ── -->
  <table>
    <thead>
      <tr>
        <?php foreach ($columns as $col): ?>
          <th><?= htmlspecialchars($col) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="<?= count($columns) ?>" style="text-align:center;color:#94a3b8;padding:24px;">No records found<?= in_array($type, ['appointments','consultations','billing']) ? ' for this date range.' : '.' ?></td></tr>
    <?php else: foreach ($rows as $row):
        $vals = array_values($row); ?>
      <tr>
        <?php foreach ($vals as $i => $val):
            // Smart formatting by column name
            $colName = $columns[$i] ?? '';
            $cellStyle = '';
            $displayVal = htmlspecialchars($val ?? '—');

            // ID columns
            if (stripos($colName, ' ID') !== false) {
                $cellStyle = 'color:#94a3b8;font-size:11px;';
                $displayVal = '#' . $displayVal;
            }
            // Name columns
            if (in_array($colName, ['Full Name','Pet Name','Client','Owner','Veterinarian'])) {
                $cellStyle = 'font-weight:600;';
            }
            // Money columns
            if (in_array($colName, ['Price','Total','Paid','Balance','Daily Rate','Weight (kg)'])) {
                if (is_numeric($val) && $val > 0) {
                    $cellStyle = 'font-weight:700;color:#15803d;';
                    $displayVal = '₱' . number_format(floatval($val), 2);
                } elseif ($colName === 'Balance' && is_numeric($val) && $val > 0) {
                    $cellStyle = 'font-weight:700;color:#dc2626;';
                    $displayVal = '₱' . number_format(floatval($val), 2);
                }
            }
            // Status column — render badge
            if ($colName === 'Status') {
                $statusClass = [
                    'Active' => 'badge-active', 'Inactive' => 'badge-inactive',
                    'Paid' => 'badge-paid', 'Pending' => 'badge-pending', 'Partial' => 'badge-partial',
                    'Cancelled' => 'badge-cancelled', 'Scheduled' => 'badge-scheduled',
                    'Completed' => 'badge-completed', 'Checked Out' => 'badge-inactive',
                    'No-Show' => 'badge-noshow',
                ][$val] ?? 'badge-inactive';
                $displayVal = '<span class="badge ' . $statusClass . '">' . htmlspecialchars($val ?? '—') . '</span>';
                $cellStyle = '';
            }
        ?>
          <td style="<?= $cellStyle ?>"><?= $displayVal ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

<?php else: // ── ANALYTICS (full report, original) ── ?>

  <!-- ── Analytics Date Range Chip ── -->
  <div style="margin-bottom:18px;">
    <span class="module-badge">📊 Analytics &amp; Performance</span>
    <span class="date-range-chip" style="margin-left:10px;">
      📅 <?= date('M d, Y', strtotime($dateFrom)) ?> – <?= date('M d, Y', strtotime($dateTo)) ?>
    </span>
  </div>

  <!-- ── KPIs ── -->
  <div class="section-label">Key Performance Indicators</div>
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-val"><?= number_format($totalClients) ?></div>
      <div class="kpi-lbl">Active Clients</div>
      <div class="kpi-trend">+<?= $newClients ?> new this period</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val"><?= number_format($totalPets) ?></div>
      <div class="kpi-lbl">Registered Active Pets</div>
      <div class="kpi-trend"><?= $activeLodging ?> currently boarding</div>
    </div>
    <div class="kpi-card" style="border-color:<?= $apptLoadPct >= 100 ? '#fca5a5' : ($apptLoadPct >= 75 ? '#fde68a' : '#bbf7d0') ?>;">
      <div class="kpi-val" style="color:<?= $apptLoadPct >= 100 ? '#dc2626' : ($apptLoadPct >= 75 ? '#d97706' : '#15803d') ?>;"><?= $todayAppts ?> <span style="font-size:13px;font-weight:400;color:#94a3b8;">/ <?= $DAILY_APPT_CAPACITY ?></span></div>
      <div class="kpi-lbl">Today's Appointments</div>
      <div class="kpi-trend" style="color:<?= $apptLoadPct >= 100 ? '#dc2626' : ($apptLoadPct >= 75 ? '#d97706' : '#10b981') ?>;"><?= $apptLoadPct ?>% of daily capacity</div>
    </div>
    <div class="kpi-card" style="border-color:#ede9fe;">
      <div class="kpi-val" style="color:#7c3aed;"><?= $activeLodging ?> <span style="font-size:13px;font-weight:400;color:#94a3b8;">/ <?= $BOARDING_CAPACITY ?></span></div>
      <div class="kpi-lbl">Active Boarding (Cages)</div>
      <div class="kpi-trend" style="color:<?= $boardingPct >= 100 ? '#ef4444' : ($boardingPct >= 60 ? '#f59e0b' : '#7c3aed') ?>;"><?= $boardingPct ?>% cage occupancy</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val"><?= number_format($totalAppt) ?></div>
      <div class="kpi-lbl">Appointments</div>
      <div class="kpi-trend"><?= $completionRate ?>% completed</div>
    </div>
    <div class="kpi-card" style="border-color:#bbf7d0;">
      <div class="kpi-val" style="color:#15803d;">₱<?= number_format($totalRevenue, 0) ?></div>
      <div class="kpi-lbl">Revenue Collected</div>
      <div class="kpi-trend"><?= $collectionRate ?>% collection rate</div>
    </div>
    <div class="kpi-card" style="border-color:#fde68a;">
      <div class="kpi-val" style="color:#b45309;">₱<?= number_format($totalPending, 0) ?></div>
      <div class="kpi-lbl">Outstanding Balance</div>
      <div class="kpi-trend"><?= $totalBills - $paidBills ?> unpaid bills</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val">₱<?= number_format($avgRevPerAppt, 0) ?></div>
      <div class="kpi-lbl">Avg. Per Completed Visit</div>
      <div class="kpi-trend"><?= $completedAppt ?> completed visits</div>
    </div>
  </div>

  <!-- ── Clinic Insights ── -->
  <?php if (!empty($insights)): ?>
  <div class="section-label" style="margin-top:20px;">Clinic Insights &amp; Recommendations</div>
  <div class="insight-block">
  <?php
  foreach ($insights as $ins):
  ?>
  <div class="insight-row <?= $ins['type'] ?>">
    <div class="insight-dot"></div>
    <div>
      <div class="insight-title"><?= $ins['title'] ?></div>
      <div class="insight-msg"><?= $ins['msg'] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── Revenue Breakdown ── -->
  <div class="section-label">Revenue Overview</div>
  <div class="rev-grid">
    <div class="rev-box">
      <div class="rev-box-title">Monthly Revenue — Last 6 Months</div>
      <?php
      $maxRev = max(array_column($revenueData, 'billed')) ?: 1;
      foreach ($revenueData as $r):
        $billPct = round($r['billed']/$maxRev*100);
      ?>
      <div class="bar-row">
        <div class="bar-label"><?= $r['label'] ?></div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $billPct ?>%;background:#e2e8f0;"></div></div>
        <div class="bar-val">₱<?= number_format($r['collected'], 0) ?></div>
      </div>
      <?php endforeach; ?>
      <div style="font-size:10px;color:#94a3b8;margin-top:6px;">Bars show billed; values show collected</div>
    </div>
    <div class="rev-box">
      <div class="rev-box-title">Payment Status Breakdown</div>
      <?php
      $totalBilledAmt = array_sum(array_column($payStatus, 'total'));
      foreach ($payStatus as $ps):
        $pct = $totalBilledAmt > 0 ? round($ps['total'] / $totalBilledAmt * 100, 1) : 0;
        $color = ['Paid'=>'#10b981','Pending'=>'#f59e0b','Partial'=>'#0ea5e9'][$ps['PaymentStatus']] ?? '#94a3b8';
      ?>
      <div class="bar-row">
        <div class="bar-label"><?= htmlspecialchars($ps['PaymentStatus']) ?></div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div></div>
        <div class="bar-val">₱<?= number_format($ps['total'], 0) ?></div>
      </div>
      <div style="font-size:10px;color:#94a3b8;margin-bottom:6px;"><?= $ps['cnt'] ?> bills · <?= $pct ?>%</div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Appointment Analytics ── -->
  <div class="section-label">Appointment Analytics</div>
  <div class="rev-grid">
    <div class="rev-box">
      <div class="rev-box-title">By Status</div>
      <?php foreach ($apptStats as $as):
        $pct = $totalAppt > 0 ? round($as['cnt'] / $totalAppt * 100, 1) : 0;
        $color = ['Completed'=>'#10b981','Scheduled'=>'#0ea5e9','Cancelled'=>'#ef4444','No-Show'=>'#f59e0b','No-show'=>'#f59e0b','Pending'=>'#94a3b8'][$as['Status']] ?? '#94a3b8';
      ?>
      <div class="bar-row">
        <div class="bar-label"><?= htmlspecialchars($as['Status']) ?></div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div></div>
        <div class="bar-val"><?= $as['cnt'] ?> (<?= $pct ?>%)</div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="rev-box">
      <div class="rev-box-title">By Day of Week</div>
      <?php
      $maxDay = max(array_column($apptByDay, 'cnt')) ?: 1;
      foreach ($apptByDay as $d):
        $pct = round($d['cnt'] / $maxDay * 100);
      ?>
      <div class="bar-row">
        <div class="bar-label"><?= substr($d['day'], 0, 3) ?></div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#6366f1;"></div></div>
        <div class="bar-val"><?= $d['cnt'] ?> appts</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Top Services ── -->
  <div class="section-label">Top Services by Appointments</div>
  <table>
    <thead><tr><th>#</th><th>Service Name</th><th>Appointments</th><th>Unit Price</th><th>Share</th></tr></thead>
    <tbody>
    <?php if (empty($topSvcs)): ?>
      <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:16px;">No service data for this period.</td></tr>
    <?php else:
      $maxSvc = max(array_column($topSvcs, 'cnt')) ?: 1;
      foreach ($topSvcs as $i => $s):
        $pct = round($s['cnt'] / $totalAppt * 100, 1);
    ?>
    <tr>
      <td style="font-weight:700;color:<?= $i===0?'#a16207':($i===1?'#475569':($i===2?'#b45309':'#94a3b8')) ?>;"><?= $i+1 ?></td>
      <td style="font-weight:600;"><?= htmlspecialchars($s['ServiceName']) ?></td>
      <td><?= $s['cnt'] ?></td>
      <td>₱<?= number_format($s['Price'], 2) ?></td>
      <td><?= $pct ?>%</td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

  <!-- ── Patients by Species ── -->
  <div class="section-label">Patients by Species</div>
  <table>
    <thead><tr><th>Species</th><th>Count</th><th>Share</th></tr></thead>
    <tbody>
    <?php foreach ($species as $sp):
      $pct = $totalPets > 0 ? round($sp['cnt'] / $totalPets * 100, 1) : 0;
    ?>
    <tr>
      <td style="font-weight:600;"><?= htmlspecialchars($sp['Species']) ?></td>
      <td><?= $sp['cnt'] ?></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px;">
          <div class="bar-track" style="width:80px;flex:none;"><div class="bar-fill" style="width:<?= $pct ?>%;background:#0ea5e9;"></div></div>
          <?= $pct ?>%
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="page-break"></div>

  <!-- ── Top Clients ── -->
  <div class="section-label" style="margin-top:20px;">Top Clients by Spending</div>
  <table>
    <thead><tr><th>#</th><th>Client</th><th>Email</th><th>Bills</th><th>Total Spent</th></tr></thead>
    <tbody>
    <?php if (empty($topClients)): ?>
      <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:16px;">No billing data for this period.</td></tr>
    <?php else:
      foreach ($topClients as $ci => $cl):
    ?>
    <tr>
      <td style="font-weight:700;color:<?= $ci===0?'#a16207':($ci===1?'#475569':($ci===2?'#b45309':'#94a3b8')) ?>;"><?= $ci+1 ?></td>
      <td style="font-weight:600;"><?= htmlspecialchars($cl['Client']) ?></td>
      <td style="color:#64748b;"><?= htmlspecialchars($cl['Email'] ?? '—') ?></td>
      <td><?= $cl['bills'] ?></td>
      <td style="font-weight:700;color:#15803d;">₱<?= number_format($cl['spent'], 2) ?></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

  <!-- ── Payment Methods ── -->
  <?php if (!empty($payMethods)): ?>
  <div class="section-label">Payment Methods</div>
  <table>
    <thead><tr><th>Method</th><th>Transactions</th><th>Total Collected</th><th>Share</th></tr></thead>
    <tbody>
    <?php
    $maxPay = max(array_column($payMethods, 'total')) ?: 1;
    foreach ($payMethods as $pm):
      $pct = round($pm['total'] / $maxPay * 100);
    ?>
    <tr>
      <td style="font-weight:600;"><?= htmlspecialchars($pm['PaymentMethod']) ?></td>
      <td><?= $pm['cnt'] ?></td>
      <td style="font-weight:700;">₱<?= number_format($pm['total'], 2) ?></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px;">
          <div class="bar-track" style="width:80px;flex:none;"><div class="bar-fill" style="width:<?= $pct ?>%;background:#10b981;"></div></div>
          <?= $pct ?>%
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- ── Billing Transaction Log ── -->
  <div class="section-label">Billing Transaction Log</div>
  <table>
    <thead>
      <tr><th>Date</th><th>Client</th><th>Pet</th><th>Species</th><th>Billed</th><th>Paid</th><th>Balance</th><th>Method</th><th>Status</th></tr>
    </thead>
    <tbody>
    <?php if ($recentBilling->num_rows == 0): ?>
      <tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:16px;">No transactions for this period.</td></tr>
    <?php endif;
    while ($row = $recentBilling->fetch_assoc()):
      $bal = $row['TotalAmount'] - $row['AmountPaid'];
      $bClass = match($row['PaymentStatus']) { 'Paid' => 'badge-paid', 'Pending' => 'badge-pending', default => 'badge-partial' };
    ?>
    <tr>
      <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($row['BillingDate'])) ?></td>
      <td style="font-weight:600;"><?= htmlspecialchars($row['Client']) ?></td>
      <td><?= htmlspecialchars($row['PetName'] ?? '—') ?></td>
      <td style="color:#64748b;"><?= htmlspecialchars($row['Species'] ?? '—') ?></td>
      <td style="font-weight:700;">₱<?= number_format($row['TotalAmount'], 2) ?></td>
      <td style="color:#15803d;font-weight:600;">₱<?= number_format($row['AmountPaid'], 2) ?></td>
      <td style="<?= $bal > 0 ? 'color:#dc2626;' : 'color:#94a3b8;' ?>"><?= $bal > 0 ? '₱'.number_format($bal, 2) : '—' ?></td>
      <td style="font-size:11px;"><?= htmlspecialchars($row['PaymentMethod'] ?? '—') ?></td>
      <td><span class="badge <?= $bClass ?>"><?= htmlspecialchars($row['PaymentStatus']) ?></span></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <div class="page-break"></div>

  <!-- ── Consultation Analytics ── -->
  <div class="section-label" style="margin-top:20px;">Consultation Analytics</div>
  <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px;">
    <div class="kpi-card">
      <div class="kpi-val"><?= number_format($totalConsults) ?></div>
      <div class="kpi-lbl">Total Consultations</div>
      <div class="kpi-trend">This period</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val"><?= number_format($followUpCount) ?></div>
      <div class="kpi-lbl">Follow-Ups Scheduled</div>
      <div class="kpi-trend"><?= $followUpRate ?>% of consultations</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val"><?= count($topDiagnoses) ?></div>
      <div class="kpi-lbl">Distinct Diagnoses</div>
      <div class="kpi-trend">Unique conditions recorded</div>
    </div>
  </div>

  <!-- Consult Trend + Top Vets -->
  <div class="rev-grid">
    <div class="rev-box">
      <div class="rev-box-title">Monthly Consultation Trend — Last 6 Months</div>
      <?php
      $maxConsult = max(array_column($consultTrend, 'value')) ?: 1;
      foreach ($consultTrend as $ct):
        $pct = round($ct['value'] / $maxConsult * 100);
      ?>
      <div class="bar-row">
        <div class="bar-label"><?= $ct['label'] ?></div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#6366f1;"></div></div>
        <div class="bar-val"><?= $ct['value'] ?> visits</div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="rev-box">
      <div class="rev-box-title">Top Veterinarians</div>
      <?php if (empty($topVets)): ?>
        <div style="color:#94a3b8;font-size:11px;padding:8px 0;">No vet data for this period.</div>
      <?php else:
        $maxVet = max(array_column($topVets, 'cnt')) ?: 1;
        foreach ($topVets as $vt):
          $pct = round($vt['cnt'] / $maxVet * 100);
      ?>
      <div class="bar-row">
        <div class="bar-label"><?= htmlspecialchars($vt['VetName']) ?></div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#0ea5e9;"></div></div>
        <div class="bar-val"><?= $vt['cnt'] ?> consults</div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Top Diagnoses + Top Chief Complaints -->
  <div class="rev-grid" style="margin-top:10px;">
    <div class="rev-box">
      <div class="rev-box-title">Top Diagnoses</div>
      <?php if (empty($topDiagnoses)): ?>
        <div style="color:#94a3b8;font-size:11px;padding:8px 0;">No diagnosis data for this period.</div>
      <?php else:
        $maxDiag = max($topDiagnoses) ?: 1;
        foreach ($topDiagnoses as $diagName => $diagCnt):
          $pct = round($diagCnt / $maxDiag * 100);
      ?>
      <div class="bar-row">
        <div class="bar-label" style="width:120px;"><?= htmlspecialchars(ucwords($diagName)) ?></div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#f97316;"></div></div>
        <div class="bar-val"><?= $diagCnt ?> case<?= $diagCnt > 1 ? 's' : '' ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
    <div class="rev-box">
      <div class="rev-box-title">Top Chief Complaints</div>
      <?php if (empty($topComplaints)): ?>
        <div style="color:#94a3b8;font-size:11px;padding:8px 0;">No complaint data for this period.</div>
      <?php else:
        $maxCC = max($topComplaints) ?: 1;
        foreach ($topComplaints as $ccName => $ccCnt):
          $pct = round($ccCnt / $maxCC * 100);
      ?>
      <div class="bar-row">
        <div class="bar-label" style="width:120px;"><?= htmlspecialchars(ucwords($ccName)) ?></div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#f59e0b;"></div></div>
        <div class="bar-val"><?= $ccCnt ?> case<?= $ccCnt > 1 ? 's' : '' ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- ── New Client Trend ── -->
  <div class="section-label" style="margin-top:20px;">New Client Registrations — Last 6 Months</div>
  <div class="rev-box" style="margin-bottom:12px;">
    <?php
    $maxNC = max(array_column($newClientData, 'value')) ?: 1;
    foreach ($newClientData as $nc):
      $pct = round($nc['value'] / $maxNC * 100);
    ?>
    <div class="bar-row">
      <div class="bar-label"><?= $nc['label'] ?></div>
      <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:#0ea5e9;"></div></div>
      <div class="bar-val"><?= $nc['value'] ?> client<?= $nc['value'] !== 1 ? 's' : '' ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── Summary Banner ── -->
  <div class="summary-banner">
    <div>
      <div style="font-size:11px;opacity:.7;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Period Summary</div>
      <div class="summary-big">₱<?= number_format($totalRevenue, 2) ?> collected</div>
      <div class="summary-small"><?= $collectionRate ?>% collection rate · <?= $totalBills ?> total bills</div>
    </div>
    <div class="summary-stats">
      <div>
        <div class="sum-stat-val"><?= $totalAppt ?></div>
        <div class="sum-stat-lbl">Appointments</div>
      </div>
      <div>
        <div class="sum-stat-val"><?= $totalClients ?></div>
        <div class="sum-stat-lbl">Active Clients</div>
      </div>
      <div>
        <div class="sum-stat-val"><?= $totalPets ?></div>
        <div class="sum-stat-lbl">Active Pets</div>
      </div>
    </div>
  </div>

<?php endif; // end analytics ?>

  <div class="footer-note">
    Heartside Vet · <?= htmlspecialchars($meta['label']) ?> Report · Generated on <?= date('F d, Y \a\t g:i A') ?>
    <?php if (in_array($type, ['appointments','consultations','billing','analytics'])): ?>
      · Period: <?= date('M d, Y', strtotime($dateFrom)) ?> – <?= date('M d, Y', strtotime($dateTo)) ?>
    <?php endif; ?>
  </div>

</div><!-- .page -->
</body>
</html>