<?php
header('Content-Type: application/json');
include('dbconnect.php');

$q      = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';

// Normalize filter aliases
if ($filter === 'vetname') $filter = 'medical';
if ($filter === 'invoice') $filter = 'billing';

if (strlen($q) < 2) {
    echo json_encode(['clients'=>[],'pets'=>[],'appointments'=>[],'medical'=>[],'billing'=>[],'services'=>[]]);
    exit;
}

$safe = '%' . $conn->real_escape_string($q) . '%';
$result = ['clients'=>[], 'pets'=>[], 'appointments'=>[], 'medical'=>[], 'billing'=>[], 'services'=>[]];

/* ── CLIENTS ── */
if (in_array($filter, ['all','clients'])) {
    $res = $conn->query("
        SELECT ClientID, CONCAT(FirstName,' ',LastName) AS name, Email AS email, Phone AS phone
        FROM clients WHERE IsDeleted=0
          AND (FirstName LIKE '$safe' OR LastName LIKE '$safe'
               OR CONCAT(FirstName,' ',LastName) LIKE '$safe'
               OR Email LIKE '$safe' OR Phone LIKE '$safe')
        ORDER BY FirstName LIMIT 8");
    while ($r = $res->fetch_assoc()) $result['clients'][] = $r;
}

/* ── PETS ── */
if (in_array($filter, ['all','pets'])) {
    $res = $conn->query("
        SELECT p.PetID, p.PetName AS name, p.Species AS species,
               CONCAT(c.FirstName,' ',c.LastName) AS owner
        FROM pets p JOIN clients c ON p.ClientID=c.ClientID
        WHERE p.IsDeleted=0
          AND (p.PetName LIKE '$safe' OR p.Species LIKE '$safe'
               OR p.Breed LIKE '$safe'
               OR CONCAT(c.FirstName,' ',c.LastName) LIKE '$safe')
        ORDER BY p.PetName LIMIT 8");
    while ($r = $res->fetch_assoc()) $result['pets'][] = $r;
}

/* ── APPOINTMENTS ── */
if (in_array($filter, ['all','appointments'])) {
    $res = $conn->query("
        SELECT a.AppointmentID AS id, p.PetName AS pet,
               CONCAT(c.FirstName,' ',c.LastName) AS owner,
               DATE_FORMAT(a.AppointmentDate,'%b %d, %Y') AS date,
               a.Status AS status, s.ServiceName AS service
        FROM appointments a
        JOIN pets p ON a.PetID=p.PetID
        JOIN clients c ON a.ClientID=c.ClientID
        LEFT JOIN services s ON a.ServiceID=s.ServiceID
        WHERE a.IsDeleted=0
          AND (p.PetName LIKE '$safe'
               OR CONCAT(c.FirstName,' ',c.LastName) LIKE '$safe'
               OR a.Reason LIKE '$safe'
               OR s.ServiceName LIKE '$safe'
               OR a.Status LIKE '$safe')
        ORDER BY a.AppointmentDate DESC LIMIT 8");
    while ($r = $res->fetch_assoc()) $result['appointments'][] = $r;
}

/* ── MEDICAL RECORDS ── */
if (in_array($filter, ['all','medical'])) {
    $res = $conn->query("
        SELECT con.ConsultationID AS id, p.PetName AS pet,
               DATE_FORMAT(con.ConsultationDate,'%b %d, %Y') AS date,
               con.Diagnosis AS diagnosis, con.VetName AS doctor
        FROM consultations con
        JOIN pets p ON con.PetID=p.PetID
        JOIN clients c ON con.ClientID=c.ClientID
        WHERE con.IsDeleted=0
          AND (con.Diagnosis LIKE '$safe' OR con.ChiefComplaint LIKE '$safe'
               OR p.PetName LIKE '$safe'
               OR CONCAT(c.FirstName,' ',c.LastName) LIKE '$safe'
               OR con.VetName LIKE '$safe')
        ORDER BY con.ConsultationDate DESC LIMIT 8");
    while ($r = $res->fetch_assoc()) $result['medical'][] = $r;
}

/* ── BILLING (invoice number, client, amount) ── */
if (in_array($filter, ['all','billing'])) {
    $res = $conn->query("
        SELECT b.BillingID, CONCAT('INV-',LPAD(b.BillingID,4,'0')) AS invoice,
               CONCAT(c.FirstName,' ',c.LastName) AS client,
               b.TotalAmount, b.PaymentStatus,
               DATE_FORMAT(b.BillingDate,'%b %d, %Y') AS date
        FROM billing b JOIN clients c ON b.ClientID=c.ClientID
        WHERE b.IsDeleted=0
          AND (CONCAT('INV-',LPAD(b.BillingID,4,'0')) LIKE '$safe'
               OR CONCAT(c.FirstName,' ',c.LastName) LIKE '$safe'
               OR c.Email LIKE '$safe')
        ORDER BY b.BillingDate DESC LIMIT 8");
    while ($r = $res->fetch_assoc()) $result['billing'][] = $r;
}

/* ── SERVICES ── */
if (in_array($filter, ['all','services'])) {
    $res = $conn->query("
        SELECT ServiceID AS id, ServiceName AS name, Category AS category,
               Price AS price
        FROM services
        WHERE IsDeleted=0
          AND (ServiceName LIKE '$safe' OR Category LIKE '$safe'
               OR Description LIKE '$safe')
        ORDER BY ServiceName LIMIT 8");
    while ($r = $res->fetch_assoc()) $result['services'][] = $r;
}

echo json_encode($result);