<?php
require_once 'guard.php';
include('dbconnect.php');

// ── AJAX: booked slots ──
if (isset($_GET['get_booked_slots'])) {
    $date      = $conn->real_escape_string($_GET['date'] ?? '');
    $excludeID = intval($_GET['exclude'] ?? 0);
    $rows = $conn->query("SELECT AppointmentTime FROM appointments WHERE AppointmentDate='$date' AND IsDeleted=0 AND AppointmentID!=$excludeID AND Status NOT IN ('Cancelled','No-Show')")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    exit(json_encode(array_map(fn($t) => substr($t['AppointmentTime'], 0, 5), $rows)));
}

// ── AJAX: live appointment search (for consultation dropdown) ──
if (isset($_GET['search_appts'])) {
    $q    = trim($_GET['q'] ?? '');
    $safe = '%' . $conn->real_escape_string($q) . '%';
    // Also handle APT-XXXX prefix
    $numericID = null;
    if (preg_match('/^APT-?0*(\d+)$/i', $q, $m)) $numericID = (int)$m[1];
    elseif (ctype_digit($q)) $numericID = (int)$q;
    $idCond = $numericID !== null ? "OR a.AppointmentID = $numericID" : "";

    $rows = $conn->query("
        SELECT a.AppointmentID, p.PetName, CONCAT(c.FirstName,' ',c.LastName) AS Owner,
               a.AppointmentDate, s.ServiceName
        FROM appointments a
        JOIN pets p ON a.PetID=p.PetID
        JOIN clients c ON a.ClientID=c.ClientID
        LEFT JOIN services s ON a.ServiceID=s.ServiceID
        WHERE a.Status='Scheduled' AND a.IsDeleted=0
          AND NOT EXISTS (
              SELECT 1 FROM consultations con
              WHERE con.AppointmentID = a.AppointmentID AND con.IsDeleted=0
          )
          AND (p.PetName LIKE '$safe'
               OR CONCAT(c.FirstName,' ',c.LastName) LIKE '$safe'
               OR CONCAT('APT-',LPAD(a.AppointmentID,4,'0')) LIKE '$safe'
               OR s.ServiceName LIKE '$safe'
               $idCond)
        ORDER BY a.AppointmentDate ASC LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    exit(json_encode($rows));
}

// ── AJAX: appointment details (for auto-fill) ──
if (isset($_GET['appt_detail'])) {
    $id  = intval($_GET['appt_detail']);
    $row = $conn->query("SELECT a.AppointmentID, a.PetID, a.ClientID, a.AppointmentDate, a.ServiceID,
                                s.ServiceName, s.Price, s.Category,
                                CONCAT(c.FirstName,' ',c.LastName) AS OwnerName, p.PetName
                         FROM appointments a
                         JOIN pets p ON a.PetID=p.PetID
                         JOIN clients c ON a.ClientID=c.ClientID
                         LEFT JOIN services s ON a.ServiceID=s.ServiceID
                         WHERE a.AppointmentID=$id")->fetch_assoc();
    header('Content-Type: application/json');
    exit(json_encode($row ?: []));
}

// ── AJAX: load consultation services (for edit) ──
if (isset($_GET['load_svc'])) {
    $id   = intval($_GET['load_svc']);
    $rows = $conn->query("SELECT * FROM consultation_services WHERE ConsultationID=$id")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    exit(json_encode($rows));
}

// ── AJAX: pet consultation history ──
if (isset($_GET['pet_history'])) {
    $petID = intval($_GET['pet_history']);
    $rows  = $conn->query("
        SELECT con.ConsultationID, con.ConsultationDate, con.VetName,
               con.ChiefComplaint, con.Diagnosis, con.Treatment,
               con.Prescription, con.Notes, con.FollowUpDate,
               con.UpdateReason, con.UpdatedAt,
               COALESCE((SELECT SUM(cs.Subtotal) FROM consultation_services cs WHERE cs.ConsultationID = con.ConsultationID), 0) AS Total
        FROM consultations con
        WHERE con.PetID = $petID AND con.IsDeleted = 0
        ORDER BY con.ConsultationDate DESC, con.ConsultationID DESC
    ")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    exit(json_encode($rows));
}

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid    = intval($_POST['PetID']);
    $cid    = intval($_POST['ClientID']);
    $appt   = $_POST['AppointmentID'] ? intval($_POST['AppointmentID']) : null;
    $date   = $_POST['ConsultationDate'];
    $vet    = trim($_POST['VetName'] ?? $_POST['VetNameCustom'] ?? '');
    $cc     = $_POST['ChiefComplaint'];
    $diag   = $_POST['Diagnosis'];
    $trtm   = $_POST['Treatment'];
    $presc  = $_POST['Prescription'];
    $notes  = $_POST['Notes'] ?? '';

    $hasFU   = isset($_POST['EnableFollowUp']);
    $fu      = ($hasFU && $_POST['FollowUpDate'])      ? $_POST['FollowUpDate']             : null;
    $fuTime  = ($hasFU && $_POST['FollowUpTime'])      ? $_POST['FollowUpTime']              : null;
    $fuSvcID = ($hasFU && $_POST['FollowUpServiceID']) ? intval($_POST['FollowUpServiceID']) : null;

    // Validate follow-up slot
    if ($fu && $fuTime) {
        $slotBusy   = $conn->query("SELECT AppointmentID FROM appointments WHERE AppointmentDate='$fu' AND AppointmentTime='$fuTime' AND IsDeleted=0 AND Status NOT IN ('Cancelled','No-Show')")->num_rows;
        $clientBusy = $conn->query("SELECT AppointmentID FROM appointments WHERE ClientID=$cid AND AppointmentDate='$fu' AND AppointmentTime='$fuTime' AND IsDeleted=0 AND Status NOT IN ('Cancelled','No-Show')")->num_rows;
        if ($slotBusy || $clientBusy) {
            header("Location: consultations.php?err=The selected follow-up time slot is already booked.");
            exit;
        }
    }

    // Build line items from form
    $svcIDs    = $_POST['svc_id']    ?? [];
    $svcNames  = $_POST['svc_name']  ?? [];
    $svcCats   = $_POST['svc_cat']   ?? [];
    $svcQtys   = $_POST['svc_qty']   ?? [];
    $svcPrices = $_POST['svc_price'] ?? [];
    $grandTotal = 0;
    $lineItems  = [];
    foreach ($svcNames as $i => $nm) {
        if (!trim($nm)) continue;
        $qty    = max(1, intval($svcQtys[$i] ?? 1));
        $price  = floatval($svcPrices[$i] ?? 0);
        $sub    = $qty * $price;
        $grandTotal += $sub;
        $lineItems[] = [
            'svc_id'   => intval($svcIDs[$i] ?? 0) ?: null,
            'name'     => trim($nm),
            'cat'      => trim($svcCats[$i] ?? ''),
            'qty'      => $qty,
            'price'    => $price,
            'subtotal' => $sub,
        ];
    }

    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO consultations
            (PetID,ClientID,AppointmentID,ConsultationDate,VetName,ChiefComplaint,Diagnosis,Treatment,Prescription,Notes,FollowUpDate,FollowUpTime,FollowUpServiceID)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iiisssssssssi", $pid, $cid, $appt, $date, $vet, $cc, $diag, $trtm, $presc, $notes, $fu, $fuTime, $fuSvcID);
        $stmt->execute();
        $conID = $stmt->insert_id;
        $stmt->close();

        // Save line items
        foreach ($lineItems as $li) {
            $stmt2 = $conn->prepare("INSERT INTO consultation_services
                (ConsultationID,ServiceID,ServiceName,Category,Quantity,UnitPrice,Subtotal)
                VALUES (?,?,?,?,?,?,?)");
            $stmt2->bind_param("iissids", $conID, $li['svc_id'], $li['name'], $li['cat'], $li['qty'], $li['price'], $li['subtotal']);
            $stmt2->execute();
            $stmt2->close();
        }

        // Mark linked appointment as Completed
        if ($appt) {
            $conn->query("UPDATE appointments SET Status='Completed' WHERE AppointmentID=$appt AND IsDeleted=0");
        }

        // Schedule follow-up appointment if requested
        if ($fu && $fuTime) {
            $existing = $conn->query("SELECT AppointmentID FROM appointments WHERE PetID=$pid AND AppointmentDate='$fu' AND Status='Scheduled' AND IsDeleted=0")->num_rows;
            if (!$existing) {
                $ins = $conn->prepare("INSERT INTO appointments (PetID,ClientID,ServiceID,AppointmentDate,AppointmentTime,Reason,Status,IsDeleted) VALUES (?,?,?,?,?,'Follow-up consultation','Scheduled',0)");
                $ins->bind_param("iiiss", $pid, $cid, $fuSvcID, $fu, $fuTime);
                $ins->execute();
                $ins->close();
            }
        }

        header("Location: consultations.php?msg=Record saved.");
        exit;
    }

    if ($action === 'update') {
        $id  = intval($_POST['ConsultationID']);
        $updateReason = trim($_POST['UpdateReason'] ?? '');
        $old = $conn->query("SELECT FollowUpDate,PetID,ClientID FROM consultations WHERE ConsultationID=$id")->fetch_assoc();

        $stmt = $conn->prepare("UPDATE consultations
            SET PetID=?,ClientID=?,ConsultationDate=?,VetName=?,FollowUpDate=?,ChiefComplaint=?,Diagnosis=?,Treatment=?,Prescription=?,Notes=?,UpdateReason=?,UpdatedAt=NOW()
            WHERE ConsultationID=?");
        $stmt->bind_param("iisssssssssi", $pid, $cid, $date, $vet, $fu, $cc, $diag, $trtm, $presc, $notes, $updateReason, $id);
        $stmt->execute();
        $stmt->close();

        // Replace line items
        $conn->query("DELETE FROM consultation_services WHERE ConsultationID=$id");
        foreach ($lineItems as $li) {
            $stmt2 = $conn->prepare("INSERT INTO consultation_services
                (ConsultationID,ServiceID,ServiceName,Category,Quantity,UnitPrice,Subtotal)
                VALUES (?,?,?,?,?,?,?)");
            $stmt2->bind_param("iissids", $id, $li['svc_id'], $li['name'], $li['cat'], $li['qty'], $li['price'], $li['subtotal']);
            $stmt2->execute();
            $stmt2->close();
        }

        // Sync linked invoice total
        $conn->query("UPDATE billing SET TotalAmount=$grandTotal WHERE ConsultationID=$id AND IsDeleted=0");

        // Follow-up handling
        if ($old) {
            if ($old['FollowUpDate'] && $old['FollowUpDate'] !== $fu) {
                $conn->query("UPDATE appointments SET IsDeleted=1 WHERE PetID={$old['PetID']} AND AppointmentDate='{$old['FollowUpDate']}' AND Reason='Follow-up consultation' AND Status='Scheduled'");
            }
            if ($fu && $fuTime) {
                $existing = $conn->query("SELECT AppointmentID FROM appointments WHERE PetID=$pid AND AppointmentDate='$fu' AND Status='Scheduled' AND IsDeleted=0")->num_rows;
                if (!$existing) {
                    $ins = $conn->prepare("INSERT INTO appointments (PetID,ClientID,ServiceID,AppointmentDate,AppointmentTime,Reason,Status,IsDeleted) VALUES (?,?,?,?,?,'Follow-up consultation','Scheduled',0)");
                    $ins->bind_param("iiiss", $pid, $cid, $fuSvcID, $fu, $fuTime);
                    $ins->execute();
                    $ins->close();
                }
            }
        }

        header("Location: consultations.php?msg=Record updated.");
        exit;
    }
}

// ── DELETE ──
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($con = $conn->query("SELECT FollowUpDate,PetID FROM consultations WHERE ConsultationID=$id")->fetch_assoc()) {
        if ($con['FollowUpDate'] && $conn->query("SELECT AppointmentID FROM appointments WHERE PetID={$con['PetID']} AND AppointmentDate='{$con['FollowUpDate']}' AND Status='Scheduled' AND IsDeleted=0")->num_rows) {
            header("Location: consultations.php?err=Cancel the follow-up appointment on " . date('M d, Y', strtotime($con['FollowUpDate'])) . " first.");
            exit;
        }
    }
    $conn->query("UPDATE consultations SET IsDeleted=1 WHERE ConsultationID=$id");
    header("Location: consultations.php?msg=Record removed.");
    exit;
}

include('header.php');

$timeSlots = [];
for ($t = strtotime('09:30'); $t < strtotime('19:00'); $t += 1800)
    $timeSlots[] = ['value' => date('H:i', $t), 'label' => date('g:i A', $t) . '–' . date('g:i A', $t + 1800)];

$petsList    = $conn->query("SELECT PetID,PetName,ClientID FROM pets WHERE IsActive=1 ORDER BY PetName")->fetch_all(MYSQLI_ASSOC);
$clientsList = array_column($conn->query("SELECT ClientID,CONCAT(FirstName,' ',LastName) AS Name FROM clients WHERE IsActive=1")->fetch_all(MYSQLI_ASSOC), 'Name', 'ClientID');

$apptList = $conn->query("SELECT a.AppointmentID, p.PetName, CONCAT(c.FirstName,' ',c.LastName) AS Owner, a.AppointmentDate, s.ServiceName
                          FROM appointments a
                          JOIN pets p ON a.PetID=p.PetID
                          JOIN clients c ON a.ClientID=c.ClientID
                          LEFT JOIN services s ON a.ServiceID=s.ServiceID
                          WHERE a.Status='Scheduled' AND a.IsDeleted=0
                          ORDER BY a.AppointmentDate ASC")->fetch_all(MYSQLI_ASSOC);

$servicesList = $conn->query("SELECT ServiceID,ServiceName,Category,Price FROM services WHERE IsActive=1 ORDER BY ServiceName")->fetch_all(MYSQLI_ASSOC);

$vetsList = $conn->query("SELECT DISTINCT VetName FROM consultations WHERE IsDeleted=0 AND VetName IS NOT NULL ORDER BY VetName")->fetch_all(MYSQLI_ASSOC);

// Date range filter (filters by Consultation Date)
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$dateSql  = '';
if ($dateFrom !== '') $dateSql .= " AND DATE(con.ConsultationDate) >= '" . $conn->real_escape_string($dateFrom) . "'";
if ($dateTo   !== '') $dateSql .= " AND DATE(con.ConsultationDate) <= '" . $conn->real_escape_string($dateTo) . "'";
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Consultations & Medical Records</h1>
        <p>Manage checkups and historical medical records.</p>
    </div>
    <div class="page-header-actions">
        <form method="get" class="date-filter-form">
            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="date-filter-input" title="Consultation date from">
            <span class="date-filter-sep">to</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="date-filter-input" title="Consultation date to">
            <button type="submit" class="btn-date-filter" title="Filter by consultation date"><i class="bi bi-funnel-fill"></i></button>
            <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
            <a href="consultations.php" class="btn-date-clear" title="Clear date filter"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
        <div class="export-dropdown-wrap">
            <button class="btn-export" onclick="toggleExportMenu('exportMenuConsults',this)">
                <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
            </button>
            <div class="export-menu" id="exportMenuConsults">
                <div class="export-menu-label">Export As</div>
                <div class="export-menu-item" onclick="exportCSV('.modern-table','consultations_list');document.getElementById('exportMenuConsults').classList.remove('show');">
                    <div class="ei-icon ei-csv"><i class="bi bi-filetype-csv"></i></div> CSV
                </div>
                <div class="export-menu-item" onclick="exportXLSX('.modern-table','consultations_list',true);document.getElementById('exportMenuConsults').classList.remove('show');">
                    <div class="ei-icon ei-xls"><i class="bi bi-file-earmark-spreadsheet"></i></div> XLS
                </div>
                <div class="export-menu-item" onclick="exportXLSX('.modern-table','consultations_list',false);document.getElementById('exportMenuConsults').classList.remove('show');">
                    <div class="ei-icon ei-xlsx"><i class="bi bi-file-earmark-spreadsheet-fill"></i></div> XLSX
                </div>
                <div class="export-menu-item" onclick="exportDOCX('.modern-table','consultations_list','Medical Records');document.getElementById('exportMenuConsults').classList.remove('show');">
                    <div class="ei-icon ei-docx"><i class="bi bi-file-earmark-word"></i></div> DOCX
                </div>
                <div class="export-menu-item" onclick="exportPDF('.modern-table');document.getElementById('exportMenuConsults').classList.remove('show');">
                    <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF
                </div>
            </div>
        </div>
        <button class="btn-main btn-teal" data-bs-toggle="modal" data-bs-target="#consultModal" onclick="openAdd()">
            <i class="bi bi-plus-lg"></i> New Record
        </button>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?><div class="alert-modern alert-success-modern"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
<?php if (isset($_GET['err'])): ?><div class="alert-modern alert-danger-modern"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($_GET['err']) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><span class="card-header-title"><i class="bi bi-clipboard2-pulse-fill me-2" style="color:#0ea5e9;"></i>Consultation Records Log</span></div>
    <div class="card-body p-0">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Consult #</th>
                    <th>Date</th>
                    <th>Patient / Owner</th>
                    <th>Attending Vet</th>
                    <th>Diagnosis</th>
                    <th>Total</th>
                    <th>Billing</th>
                    <th>Follow-Up</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $highlightID = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
                $res = $conn->query("SELECT con.*, p.PetName,
                                            b.BillingID, b.PaymentStatus AS BillingPaymentStatus,
                                            CONCAT('INV-',LPAD(b.BillingID,4,'0')) AS InvoiceNo
                                     FROM consultations con
                                     JOIN pets p ON con.PetID=p.PetID
                                     LEFT JOIN billing b ON b.ConsultationID=con.ConsultationID AND b.IsDeleted=0
                                     WHERE con.IsDeleted=0 $dateSql
                                     ORDER BY con.ConsultationDate DESC, con.ConsultationID DESC");
                if ($res->num_rows == 0) echo '<tr><td colspan="9" class="text-center p-4 text-muted">No records on file.</td></tr>';
                while ($row = $res->fetch_assoc()):
                    $ownerName = $clientsList[$row['ClientID']] ?? '—';
                    $apptTime  = '—';
                    $tRes      = null;
                    if ($row['FollowUpDate']) {
                        $tRes = $conn->query("SELECT AppointmentID,AppointmentTime,ServiceID,Status FROM appointments WHERE PetID={$row['PetID']} AND AppointmentDate='{$row['FollowUpDate']}' AND IsDeleted=0 LIMIT 1")->fetch_assoc();
                        if ($tRes) $apptTime = date('h:i A', strtotime($tRes['AppointmentTime']));
                    }
                    $fuStatus = $tRes['Status'] ?? null;
                    $row['OwnerName']         = $ownerName;
                    $row['FollowUpTime']      = $tRes ? date('H:i', strtotime($tRes['AppointmentTime'])) : '';
                    $row['FollowUpServiceID'] = $tRes['ServiceID'] ?? '';
                    $row['FollowUpApptID']    = $tRes['AppointmentID'] ?? 0;
                    $row['FollowUpStatus']    = $fuStatus ?? '';
                    $row['UpdateReason']      = $row['UpdateReason'] ?? '';
                    $row['UpdatedAt']         = $row['UpdatedAt'] ?? '';

                    $totalRes = $conn->query("SELECT COALESCE(SUM(Subtotal),0) FROM consultation_services WHERE ConsultationID={$row['ConsultationID']}")->fetch_row();
                    $conTotal = floatval($totalRes[0]);
                ?>
                    <tr id="row-con-<?= $row['ConsultationID'] ?>" class="<?= ($highlightID && $highlightID == $row['ConsultationID']) ? 'search-highlight-row' : '' ?>">
                        <td>
                            <div style="font-weight:700;font-size:12px;color:var(--teal);">CON-<?= str_pad($row['ConsultationID'],4,'0',STR_PAD_LEFT) ?></div>
                            <?php if ($row['AppointmentID']): ?><div style="font-size:10px;color:var(--muted);">Ref: <?= 'APT-' . str_pad($row['AppointmentID'], 4, '0', STR_PAD_LEFT) ?></div><?php endif; ?>
                        </td>
                        <td style="font-weight:600;"><?= date('M d, Y', strtotime($row['ConsultationDate'])) ?></td>
                        <td>
                            <div style="font-weight:700;color:var(--dark);"><?= htmlspecialchars($row['PetName']) ?></div>
                            <div style="font-size:11px;color:var(--muted);">Owner: <?= htmlspecialchars($ownerName) ?></div>
                        </td>
                        <td><?= htmlspecialchars($row['VetName']) ?></td>
                        <td><div style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($row['Diagnosis'] ?: '—') ?></div></td>
                        <td style="font-weight:700;color:var(--green);"><?= $conTotal > 0 ? '₱' . number_format($conTotal, 2) : '—' ?></td>
                        <td><?php
                            $bStatus = $row['BillingPaymentStatus'] ?? null;
                            $invNo   = $row['InvoiceNo'] ?? null;
                            if ($bStatus === 'Paid') {
                                echo '<span class="badge-modern badge-paid"><i class="bi bi-check-circle-fill me-1"></i>Paid</span>';
                                if ($invNo) echo '<div style="font-size:10px;color:var(--muted);margin-top:2px;">' . htmlspecialchars($invNo) . '</div>';
                            } elseif ($bStatus === 'Partial') {
                                echo '<span class="badge-modern badge-partial"><i class="bi bi-clock-history me-1"></i>Partial</span>';
                                if ($invNo) echo '<div style="font-size:10px;color:var(--muted);margin-top:2px;">' . htmlspecialchars($invNo) . '</div>';
                            } elseif ($bStatus === 'Pending') {
                                echo '<span class="badge-modern badge-pending"><i class="bi bi-hourglass-split me-1"></i>Pending</span>';
                                if ($invNo) echo '<div style="font-size:10px;color:var(--muted);margin-top:2px;">' . htmlspecialchars($invNo) . '</div>';
                            } else {
                                echo '<span style="font-size:11px;color:var(--muted);">No invoice</span>';
                            }
                        ?></td>
                        <td><?php
                            if ($row['FollowUpDate']) {
                                $fuMap = ['Scheduled'=>['badge-scheduled','bi-bell-fill'],'Completed'=>['badge-completed','bi-check-circle-fill'],'Cancelled'=>['badge-cancelled','bi-x-circle-fill'],'No-Show'=>['badge-noshow','bi-dash-circle-fill'],'Pending'=>['badge-pending','bi-clock-fill']];
                                [$fuClass, $fuIcon] = $fuMap[$fuStatus] ?? ['badge-pending', 'bi-clock-fill'];
                                echo '<span class="badge-modern ' . $fuClass . '"><i class="bi ' . $fuIcon . '"></i> ' . date('M d', strtotime($row['FollowUpDate'])) . ' at ' . $apptTime . '</span>';
                            } else echo '<span style="color:var(--light-muted);">None</span>';
                        ?></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn-icon" style="background:rgba(0,128,128,.08);color:var(--teal);" data-row="<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>" onclick="viewRecord(JSON.parse(this.dataset.row))" title="View Record"><i class="bi bi-eye"></i></button>
                                <button class="btn-icon" style="background:rgba(99,102,241,.1);color:#6366f1;" data-pet-id="<?= $row['PetID'] ?>" data-pet-name="<?= htmlspecialchars($row['PetName'], ENT_QUOTES) ?>" onclick="viewPetHistory(this.dataset.petId, this.dataset.petName)" title="Pet Consultation History"><i class="bi bi-clock-history"></i></button>
                                <button class="btn-icon btn-icon-edit" data-row="<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>" onclick="openEdit(JSON.parse(this.dataset.row))"><i class="bi bi-pencil"></i></button>
                                <a href="consultations.php?delete=<?= $row['ConsultationID'] ?>" class="btn-icon btn-icon-delete" onclick="return confirm('Remove record?')"><i class="bi bi-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="consultModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" style="max-height:95vh;">
        <div class="modal-content" style="max-height:90vh;display:flex;flex-direction:column;">
            <div class="modal-header" style="flex-shrink:0;">
                <h5 class="modal-title" id="modalTitle">Consultation record</h5>
                <span id="linkedBadge" class="badge bg-success ms-auto me-2" style="font-size:11px;display:none;"></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="consultationForm" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="ConsultationID" id="consultID">
                <div class="modal-body" style="padding:20px 24px;overflow-y:auto;flex:1;">

                    <div id="modalWarningBanner" class="alert alert-danger d-none mb-3" style="font-size:13px;font-weight:600;"><i class="bi bi-exclamation-triangle-fill me-2"></i> Follow-up slot is already booked!</div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);">
                            <i class="bi bi-link-45deg me-1" style="color:var(--teal);"></i>
                            Linked Appointment — <em style="font-weight:400;text-transform:none;">auto-fills patient, services &amp; date</em>
                        </label>
                        <div style="position:relative;">
                            <div style="position:relative;">
                                <i class="bi bi-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none;"></i>
                                <input type="text" id="fLinkedApptSearch" class="form-control" placeholder="Search by APT-####, pet name, owner or service…" autocomplete="off"
                                    style="padding-left:34px;padding-right:36px;"
                                    oninput="apptSearchInput(this.value)" onfocus="apptSearchInput(this.value)" onblur="setTimeout(hideApptDrop,200)">
                                <button type="button" id="fLinkedApptClearBtn" onclick="clearApptLink()" title="Clear"
                                    style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:15px;padding:0;line-height:1;">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                            <div id="apptSearchDrop" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:1060;max-height:240px;overflow-y:auto;">
                                <div id="apptSearchResults"></div>
                                <div id="apptSearchEmpty" style="display:none;padding:12px 14px;font-size:13px;color:var(--muted);text-align:center;"><i class="bi bi-calendar-x me-1"></i> No unlinked appointments found</div>
                                <div id="apptSearchLoading" style="display:none;padding:12px 14px;font-size:13px;color:var(--muted);text-align:center;"><span class="spinner-border spinner-border-sm me-2" style="color:var(--teal);"></span>Searching…</div>
                            </div>
                        </div>
                        <input type="hidden" name="AppointmentID" id="fApptIDHidden">
                    </div>

                    <div id="autoFillBanner" class="d-none mb-3 p-3 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <i class="bi bi-check-circle-fill" style="color:#10b981;font-size:16px;"></i>
                            <span style="font-size:13px;color:#15803d;font-weight:600;" id="autoFillMsg">Auto-filled from Appointment</span>
                            <button type="button" class="btn-main btn-outline btn-sm ms-auto" onclick="clearApptLink()" style="font-size:11px;padding:4px 12px;">Clear</button>
                        </div>
                    </div>

                    <div id="manualPetRow" class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);">Patient / Pet <span style="color:#ef4444;">*</span></label>
                            <select name="PetID" id="fPetID" class="form-select" onchange="onManualPetChange(this)">
                                <option value="">— Select Pet —</option>
                                <?php foreach ($petsList as $p): ?>
                                    <option value="<?= $p['PetID'] ?>" data-client="<?= $p['ClientID'] ?>" data-owner="<?= htmlspecialchars($clientsList[$p['ClientID']] ?? '') ?>">
                                        <?= htmlspecialchars($p['PetName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);">Pet Owner</label>
                            <input type="hidden" name="ClientID" id="fClientID">
                            <input type="text" id="fOwnerDisplay" class="form-control" readonly placeholder="Auto-populated" style="background:#f8fafc;color:var(--muted);">
                        </div>
                    </div>

                    <input type="hidden" name="ConsultationDate" id="fDate" value="<?= date('Y-m-d') ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);">Attending Vet <span style="color:#ef4444;">*</span></label>
                            <select name="VetName" id="fVet" class="form-select">
                                <option value="">— Select or type —</option>
                                <?php foreach ($vetsList as $v): ?>
                                    <option value="<?= htmlspecialchars($v['VetName']) ?>"><?= htmlspecialchars($v['VetName']) ?></option>
                                <?php endforeach; ?>
                                <option value="__custom__">+ Add new vet name…</option>
                            </select>
                            <input type="text" id="fVetCustom" class="form-control mt-2" placeholder="Enter vet name" style="display:none;">
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="EnableFollowUp" id="fEnableFollowUp" onchange="toggleFollowUpFields(this.checked)">
                                <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);">Schedule a follow-up appointment?</label>
                            </div>
                        </div>
                    </div>

                    <div id="followUpFieldsSection" class="row g-3 mb-3" style="display:none;">
                        <div class="col-md-4"><label class="form-label">Follow-up Date</label><input type="date" name="FollowUpDate" id="fFollowUpDate" class="form-control"></div>
                        <div class="col-md-4">
                            <label class="form-label">Follow-up Time</label>
                            <select name="FollowUpTime" id="fFollowUpTime" class="form-select">
                                <option value="">— Select Time —</option>
                                <?php foreach ($timeSlots as $slot): ?><option value="<?= $slot['value'] ?>"><?= $slot['label'] ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Requested Service</label>
                            <select name="FollowUpServiceID" id="fFollowUpServiceID" class="form-select">
                                <option value="">— Select Service —</option>
                                <?php foreach ($servicesList as $s): ?><option value="<?= $s['ServiceID'] ?>"><?= htmlspecialchars($s['ServiceName']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);"><i class="bi bi-heart-pulse me-1"></i> Chief Complaint &amp; Symptoms <span style="color:#ef4444;">*</span></label>
                        <textarea name="ChiefComplaint" id="fCC" class="form-control" rows="2" placeholder="Describe symptoms..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);"><i class="bi bi-activity me-1"></i> Clinical Diagnosis</label>
                        <textarea name="Diagnosis" id="fDiag" class="form-control" rows="2" placeholder="Diagnosis..."></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);"><i class="bi bi-capsule me-1"></i> Treatment</label>
                            <textarea name="Treatment" id="fTreatment" class="form-control" rows="2" placeholder="Treatment given..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);"><i class="bi bi-journal-medical me-1"></i> Prescribed Medications</label>
                            <textarea name="Prescription" id="fPresc" class="form-control" rows="2" placeholder="Medications..."></textarea>
                        </div>
                    </div>

                    <div class="mb-1 d-flex align-items-center justify-content-between">
                        <label class="form-label fw-semibold mb-0" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);"><i class="bi bi-grid me-1" style="color:var(--teal);"></i> Services Availed <span id="fromApptTag" style="display:none;" class="badge-modern badge-active ms-2" style="font-size:10px;">FROM APPT</span></label>
                        <button type="button" class="btn-main btn-outline btn-sm" onclick="addServiceRow()" style="font-size:12px;padding:4px 12px;"><i class="bi bi-plus-sm me-1"></i> Add</button>
                    </div>
                    <div class="mb-3" style="border:1px solid var(--border);border-radius:8px;overflow:hidden;">
                        <table class="modern-table mb-0" id="svcTable">
                            <thead>
                                <tr style="background:#f8fafc;">
                                    <th style="font-size:11px;">Service</th>
                                    <th style="font-size:11px;">Category</th>
                                    <th style="font-size:11px;width:60px;">Qty</th>
                                    <th style="font-size:11px;width:90px;">Price</th>
                                    <th style="font-size:11px;width:90px;">Subtotal</th>
                                    <th style="width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="svcRows"></tbody>
                        </table>
                        <div id="svcEmpty" style="padding:16px;text-align:center;color:var(--muted);font-size:13px;">Link an appointment or add services above</div>
                        <div style="padding:10px 14px;text-align:right;background:#f8fafc;border-top:1px solid var(--border);">
                            <strong style="font-size:14px;">Total&nbsp;&nbsp;<span style="color:var(--teal);" id="svcTotal">₱0.00</span></strong>
                        </div>
                    </div>

                    <div class="col-12" id="consultUpdateReasonField" style="display:none;">
                        <label class="form-label">Reason for Updating <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="UpdateReason" id="fConsultUpdateReason" class="form-control" placeholder="e.g. Corrected diagnosis, updated treatment…">
                    </div>
                    <div class="col-md-6" id="consultUpdatedAtField" style="display:none;">
                        <label class="form-label">Date Updated</label>
                        <input type="text" id="fConsultUpdatedAt" class="form-control" readonly style="background:#f8fafc;color:var(--muted);font-size:13px;">
                    </div>

                    <input type="hidden" name="Notes" id="fNotes">
                </div>

                <div class="modal-footer" style="flex-shrink:0;">
                    <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-main btn-teal" id="submitBtn"><i class="bi bi-check-lg me-1"></i> Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;">

            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:#fff;border-bottom:1px solid var(--border);">
                <span style="font-weight:700;font-size:14px;color:var(--dark);display:flex;align-items:center;gap:8px;">
                    <i class="bi bi-file-earmark-medical" style="color:var(--teal);"></i> Medical Case File
                </span>
                <div style="display:flex;gap:8px;align-items:center;">
                    <div class="modal-export-wrap">
                        <button type="button" class="btn-modal-export" onclick="toggleExportMenu('consultViewExportMenu',this)">
                            <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="modal-export-menu" id="consultViewExportMenu">
                            <div class="export-menu-label">Export As</div>
                            <div class="export-menu-item" onclick="window.print();document.getElementById('consultViewExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF / Print
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size:11px;"></button>
                </div>
            </div>

            <div class="modal-body" id="consultPrintArea">
                <div class="consult-print-header" style="display:none;padding:22px 28px 16px;border-bottom:2px dashed #e2e8f0;text-align:center;margin-bottom:16px;">
                    <img src="logo1.png" alt="Heartside Vet" style="width:56px;height:56px;object-fit:contain;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;">
                    <div style="font-size:20px;font-weight:800;color:#1e3a5f;letter-spacing:.4px;line-height:1.2;">Heartside Vet Clinic</div>
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px;margin-top:4px;font-weight:600;">Medical Case File</div>
                    <div style="font-size:13px;color:#334155;margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;">Consult: <strong id="consultPrintNum" style="color:#1e3a5f;"></strong> &nbsp;&nbsp;·&nbsp;&nbsp; Patient: <strong id="consultPrintPet" style="color:#1e3a5f;"></strong></div>
                </div>

                <div class="vm-header-band row g-3 mb-3">
                    <div class="col-md-4"><div class="vm-label">Patient</div><div class="vm-value-lg" id="vPetName">—</div></div>
                    <div class="col-md-4"><div class="vm-label">Owner</div><div class="vm-value" style="font-weight:600;" id="vOwnerName">—</div></div>
                    <div class="col-md-4"><div class="vm-label">Attending Vet</div><div class="vm-value" id="vVetName">—</div></div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="vm-label">Consultation #</div>
                        <div class="vm-value" style="font-weight:700;color:var(--teal);font-size:14px;" id="vConsultNum">—</div>
                    </div>
                    <div class="col-md-3" id="vLinkedApptWrap">
                        <div class="vm-label">Linked Appointment</div>
                        <div class="vm-value" style="font-size:13px;color:var(--muted);" id="vLinkedAppt">—</div>
                    </div>
                    <div class="col-md-3"><div class="vm-label">Date</div><div class="vm-value" id="vDate">—</div></div>
                    <div class="col-md-3"><div class="vm-label">Follow-Up</div><span id="vFollowUp" class="badge-modern badge-scheduled">—</span></div>
                </div>
                <hr style="border-color:var(--border);margin:4px 0 16px;">
                <div class="mb-3">
                    <div class="vm-label"><i class="bi bi-heart-pulse me-1 text-danger"></i>Chief Complaint</div>
                    <div class="vm-notes-block" id="vCC">—</div>
                </div>
                <div class="mb-3">
                    <div class="vm-label"><i class="bi bi-activity me-1 text-primary"></i>Diagnosis</div>
                    <div class="vm-notes-block" style="font-weight:600;" id="vDiag">—</div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="vm-label"><i class="bi bi-capsule me-1 text-success"></i>Treatment</div>
                        <div class="vm-notes-block" id="vTreatment">—</div>
                    </div>
                    <div class="col-md-6">
                        <div class="vm-label"><i class="bi bi-journal-medical me-1 text-warning"></i>Prescriptions</div>
                        <div class="vm-notes-block" id="vPrescription">—</div>
                    </div>
                    <div class="col-md-6" id="vConUpdatedWrap" style="display:none;">
                        <div class="vm-label"><i class="bi bi-clock-history me-1"></i>Last Updated</div>
                        <div class="vm-value" id="vConUpdated" style="font-size:13px;">—</div>
                    </div>
                    <div class="col-md-6" id="vConReasonWrap" style="display:none;">
                        <div class="vm-label"><i class="bi bi-pencil-square me-1"></i>Reason for Update</div>
                        <div class="vm-notes-block" id="vConReason" style="font-size:13px;">—</div>
                    </div>
                </div>

                <div class="consult-print-footer" style="display:none;border-top:2px dashed #e2e8f0;margin:20px 0 0;padding:12px 0 4px;text-align:center;">
                    <div style="font-size:10px;color:#94a3b8;letter-spacing:.3px;">Heartside Vet Clinic Management System &nbsp;·&nbsp; <span id="consultPrintDate"></span></div>
                    <div style="font-size:10px;color:#cbd5e1;margin-top:2px;">This document is computer-generated and valid without a signature.</div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<style>
@media print {
    /* ── Reset ── */
    body * { visibility: hidden; }
    body { margin: 0; padding: 0; background: #fff; font-size: 13px; font-family: 'Inter', system-ui, sans-serif; }

    /* ── Medical Case File print ── */
    #consultPrintArea, #consultPrintArea * { visibility: visible; }
    #consultPrintArea {
        position: fixed; top: 0; left: 0; width: 100%;
        padding: 40px; background: #fff; box-sizing: border-box;
    }
    .consult-print-header, .consult-print-footer { display: block !important; }

    /* ── Patient History Log print ── */
    #historyPrintArea, #historyPrintArea * { visibility: visible; }
    #historyPrintArea {
        position: fixed; top: 0; left: 0; width: 100%;
        padding: 40px; background: #fff; box-sizing: border-box;
    }
    .history-print-header, .history-print-footer { display: block !important; }

    /* ── Shared modal cleanup ── */
    .modal, .modal-dialog, .modal-content {
        box-shadow: none !important; border: none !important;
    }
    .vm-header-band { border: none !important; background: transparent !important; }
    .badge-modern { border: 1px solid #ccc !important; }
}
</style>

<script>
    const servicesCatalog = <?= json_encode($servicesList) ?>;
    let svcRowCount = 0;

    function svcCatalogOptions(selectedID) {
        let opts = '<option value="">— Select Service —</option>';
        servicesCatalog.forEach(s => {
            const sel = (s.ServiceID == selectedID) ? 'selected' : '';
            opts += `<option value="${s.ServiceID}" data-price="${s.Price}" data-cat="${s.Category}" ${sel}>${s.ServiceName}</option>`;
        });
        return opts;
    }

    function addServiceRow(svcID, svcName, cat, qty, price) {
        const tbody = document.getElementById('svcRows');
        const idx = svcRowCount++;
        const q = qty || 1;
        const p = price || 0;
        const sub = (q * p).toFixed(2);
        const row = document.createElement('tr');
        row.id = 'svc-row-' + idx;
        row.innerHTML = `
        <td style="min-width:160px;">
            <select class="form-select form-select-sm svc-select" name="svc_id[]" onchange="onSvcSelect(this,${idx})">
                ${svcCatalogOptions(svcID)}
            </select>
            <input type="hidden" name="svc_name[]" id="svc_name_${idx}" value="${svcName || ''}">
        </td>
        <td><input type="text" class="form-control form-control-sm" name="svc_cat[]" id="svc_cat_${idx}" value="${cat||''}" readonly style="background:#f8fafc;font-size:12px;min-width:90px;"></td>
        <td><input type="number" class="form-control form-control-sm" name="svc_qty[]" id="svc_qty_${idx}" value="${q}" min="1" style="width:56px;" oninput="recalcRow(${idx})"></td>
        <td><input type="number" class="form-control form-control-sm" name="svc_price[]" id="svc_price_${idx}" value="${p}" step="0.01" style="width:82px;" oninput="recalcRow(${idx})"></td>
        <td><span id="svc_sub_${idx}" style="font-weight:700;font-size:13px;color:var(--teal);">₱${parseFloat(sub).toLocaleString('en-PH',{minimumFractionDigits:2})}</span><input type="hidden" name="svc_subtotal[]" id="svc_subtotalhidden_${idx}" value="${sub}"></td>
        <td><button type="button" class="btn-icon btn-icon-delete" style="width:28px;height:28px;font-size:12px;" onclick="removeSvcRow(${idx})"><i class="bi bi-trash"></i></button></td>`;
        tbody.appendChild(row);
        document.getElementById('svcEmpty').style.display = 'none';
        recalcTotal();
    }

    function onSvcSelect(sel, idx) {
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('svc_name_'  + idx).value = opt.textContent.trim();
        document.getElementById('svc_cat_'   + idx).value = opt.dataset.cat || '';
        document.getElementById('svc_price_' + idx).value = parseFloat(opt.dataset.price || 0);
        recalcRow(idx);
    }

    function recalcRow(idx) {
        const qty = parseFloat(document.getElementById('svc_qty_'   + idx).value) || 0;
        const prc = parseFloat(document.getElementById('svc_price_' + idx).value) || 0;
        const sub = qty * prc;
        document.getElementById('svc_sub_' + idx).textContent = '₱' + sub.toLocaleString('en-PH', { minimumFractionDigits: 2 });
        document.getElementById('svc_subtotalhidden_' + idx).value = sub.toFixed(2);
        recalcTotal();
    }

    function recalcTotal() {
        let t = 0;
        document.querySelectorAll('[id^="svc_sub_"]').forEach(el => {
            t += parseFloat(el.textContent.replace(/[₱,]/g, '')) || 0;
        });
        document.getElementById('svcTotal').textContent = '₱' + t.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    }

    function removeSvcRow(idx) {
        const row = document.getElementById('svc-row-' + idx);
        if (row) row.remove();
        recalcTotal();
        if (!document.getElementById('svcRows').children.length)
            document.getElementById('svcEmpty').style.display = '';
    }

    let apptSearchTimer = null;
    function apptSearchInput(val) {
        clearTimeout(apptSearchTimer);
        showApptDrop();
        document.getElementById('apptSearchLoading').style.display = '';
        document.getElementById('apptSearchEmpty').style.display = 'none';
        apptSearchTimer = setTimeout(() => doApptSearch(val), 220);
    }
    async function doApptSearch(q) {
        const res  = await fetch(`consultations.php?search_appts=1&q=${encodeURIComponent(q)}`);
        const rows = await res.json();
        document.getElementById('apptSearchLoading').style.display = 'none';
        const el = document.getElementById('apptSearchResults');
        if (!rows.length) { el.innerHTML=''; document.getElementById('apptSearchEmpty').style.display=''; return; }
        document.getElementById('apptSearchEmpty').style.display = 'none';
        el.innerHTML = rows.map(a => {
            const aptID  = 'APT-' + String(a.AppointmentID).padStart(4,'0');
            const date   = a.AppointmentDate ? a.AppointmentDate.substring(0,10) : '';
            const svc    = a.ServiceName ? ` · ${a.ServiceName}` : '';
            return `<div class="appt-search-item" style="padding:9px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:13px;display:flex;align-items:center;gap:10px;"
                        onmousedown="selectApptItem(${a.AppointmentID}, '${aptID}')">
                        <div style="min-width:72px;font-weight:700;color:var(--teal);font-size:12px;">${aptID}</div>
                        <div>
                            <div style="font-weight:600;color:var(--dark);">${a.PetName} <span style="font-weight:400;color:var(--muted);">· ${a.Owner}</span></div>
                            <div style="font-size:11px;color:var(--muted);">${date}${svc}</div>
                        </div>
                    </div>`;
        }).join('');
    }
    function showApptDrop() { document.getElementById('apptSearchDrop').style.display=''; }
    function hideApptDrop() { document.getElementById('apptSearchDrop').style.display='none'; }
    function selectApptItem(id, label) {
        document.getElementById('fLinkedApptSearch').value = label;
        document.getElementById('fLinkedApptClearBtn').style.display = '';
        document.getElementById('apptSearchResults').innerHTML = '';
        hideApptDrop();
        onApptLink(id);
    }
    // Hover highlight for search items
    document.addEventListener('mouseover', e => {
        const item = e.target.closest('.appt-search-item');
        if (item) item.style.background = '#f0fdf4';
    });
    document.addEventListener('mouseout', e => {
        const item = e.target.closest('.appt-search-item');
        if (item) item.style.background = '';
    });

    async function onApptLink(id) {
        document.getElementById('fApptIDHidden').value = id;
        if (!id) { clearApptLink(); return; }

        const res  = await fetch(`consultations.php?appt_detail=${id}`);
        const data = await res.json();
        if (!data.AppointmentID) return;

        document.getElementById('fPetID').value      = data.PetID;
        document.getElementById('fClientID').value   = data.ClientID;
        document.getElementById('fDate').value        = data.AppointmentDate;
        document.getElementById('fOwnerDisplay').value = data.OwnerName;
        document.getElementById('autoFillMsg').textContent = `Auto-filled from APT-${String(id).padStart(4,'0')} · ${data.PetName} / ${data.OwnerName} · ${data.AppointmentDate}`;
        document.getElementById('autoFillBanner').classList.remove('d-none');
        document.getElementById('linkedBadge').style.display = '';

        clearSvcRows();
        if (data.ServiceID && data.ServiceName) {
            document.getElementById('fromApptTag').style.display = '';
            addServiceRow(data.ServiceID, data.ServiceName, data.Category, 1, data.Price);
        }
    }

    function clearApptLink() {
        document.getElementById('fLinkedApptSearch').value = '';
        document.getElementById('fLinkedApptClearBtn').style.display = 'none';
        document.getElementById('fApptIDHidden').value = '';
        document.getElementById('autoFillBanner').classList.add('d-none');
        document.getElementById('linkedBadge').style.display = 'none';
        document.getElementById('fromApptTag').style.display  = 'none';
        document.getElementById('fPetID').value        = '';
        document.getElementById('fClientID').value     = '';
        document.getElementById('fOwnerDisplay').value = '';
        clearSvcRows();
    }

    function clearSvcRows() {
        document.getElementById('svcRows').innerHTML = '';
        document.getElementById('svcEmpty').style.display = '';
        document.getElementById('svcTotal').textContent = '₱0.00';
        svcRowCount = 0;
    }

    function onManualPetChange(sel) {
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('fClientID').value    = opt.dataset.client || '';
        document.getElementById('fOwnerDisplay').value = opt.dataset.owner || '';
    }

    function toggleFollowUpFields(checked) {
        const sec = document.getElementById('followUpFieldsSection');
        sec.style.display = checked ? 'flex' : 'none';
        document.getElementById('fFollowUpDate').required = checked;
        document.getElementById('fFollowUpTime').required = checked;
        if (!checked) {
            document.getElementById('fFollowUpDate').value      = '';
            document.getElementById('fFollowUpTime').value      = '';
            document.getElementById('fFollowUpServiceID').value = '';
        }
    }

    document.getElementById('fVet').addEventListener('change', function () {
        document.getElementById('fVetCustom').style.display = this.value === '__custom__' ? '' : 'none';
    });

    const FU_ALL_SLOTS = <?php
        $out = [];
        for ($t = strtotime('09:30'); $t < strtotime('19:00'); $t += 1800)
            $out[date('H:i', $t)] = date('g:i A', $t) . '–' . date('g:i A', $t + 1800);
        echo json_encode($out);
    ?>;

    let fuBookedSlots   = [];
    let currentFuApptID = 0;

    async function refreshFollowUpSlots(date, keepValue) {
        if (!date) return;
        const res = await fetch(`consultations.php?get_booked_slots=1&date=${date}&exclude=${currentFuApptID}`);
        fuBookedSlots = await res.json();
        const sel = document.getElementById('fFollowUpTime');
        sel.innerHTML = '<option value="">— Select Time —</option>';
        for (const [val, label] of Object.entries(FU_ALL_SLOTS)) {
            const taken = fuBookedSlots.includes(val);
            const opt   = document.createElement('option');
            opt.value   = val;
            opt.textContent = taken ? label + '  ✕ Booked' : label;
            if (taken) { opt.style.color = '#b0b8c8'; opt.style.fontStyle = 'italic'; opt.dataset.booked = '1'; }
            sel.appendChild(opt);
        }
        if (keepValue) sel.value = keepValue;
        checkFollowUpWarning(sel);
    }

    function checkFollowUpWarning(sel) {
        const chosen = sel.options[sel.selectedIndex];
        const banner = document.getElementById('modalWarningBanner');
        if (chosen && chosen.dataset.booked === '1') {
            banner.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i> This follow-up time slot is already booked. Please choose another.';
            banner.classList.remove('d-none');
        } else {
            banner.classList.add('d-none');
        }
    }

    document.getElementById('fFollowUpTime').addEventListener('change', function () { checkFollowUpWarning(this); });
    document.getElementById('fFollowUpDate').addEventListener('change', function () { refreshFollowUpSlots(this.value); });

    document.getElementById('consultationForm').addEventListener('submit', function (e) {
        const vetSel = document.getElementById('fVet');
        if (vetSel.value === '__custom__') {
            const customVal = document.getElementById('fVetCustom').value.trim();
            if (!customVal) { e.preventDefault(); alert('Please enter a vet name.'); return; }
            const newOpt = new Option(customVal, customVal, true, true);
            vetSel.add(newOpt);
            vetSel.value = customVal;
        }
        if (document.getElementById('fEnableFollowUp').checked) {
            const ts  = document.getElementById('fFollowUpTime');
            const opt = ts.options[ts.selectedIndex];
            if (opt && opt.dataset.booked === '1') {
                e.preventDefault();
                const banner = document.getElementById('modalWarningBanner');
                banner.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i> This follow-up time slot is already booked. Please choose another.';
                banner.classList.remove('d-none');
                banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    });

    function openAdd() {
        document.getElementById('modalTitle').textContent = 'Consultation record';
        document.getElementById('formAction').value = 'add';
        document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Save Record';
        document.getElementById('modalWarningBanner').classList.add('d-none');
        document.getElementById('linkedBadge').style.display = 'none';
        document.getElementById('fLinkedApptSearch').value = '';
        document.getElementById('fLinkedApptClearBtn').style.display = 'none';
        clearApptLink();
        currentFuApptID = 0;
        document.getElementById('fEnableFollowUp').checked = false;
        toggleFollowUpFields(false);
        ['consultID','fPetID','fClientID','fOwnerDisplay','fCC','fDiag','fTreatment','fPresc','fNotes'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('fDate').value = '<?= date('Y-m-d') ?>';
        document.getElementById('fVet').value = '';
        document.getElementById('fVetCustom').value = '';
        document.getElementById('fVetCustom').style.display = 'none';
        document.getElementById('consultUpdateReasonField').style.display = 'none';
        document.getElementById('consultUpdatedAtField').style.display    = 'none';
        document.getElementById('fConsultUpdateReason').required = false;
    }

    function openEdit(row) {
        document.getElementById('modalTitle').textContent = 'Edit Medical Record';
        document.getElementById('formAction').value = 'update';
        document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Update Record';
        document.getElementById('linkedBadge').style.display = 'none';
        document.getElementById('modalWarningBanner').classList.add('d-none');

        document.getElementById('consultID').value      = row.ConsultationID;
        document.getElementById('fPetID').value          = row.PetID;
        document.getElementById('fClientID').value       = row.ClientID;
        document.getElementById('fOwnerDisplay').value   = row.OwnerName || '';
        document.getElementById('fDate').value            = row.ConsultationDate;

        const vetSel = document.getElementById('fVet');
        vetSel.value = row.VetName || '';
        if (!vetSel.value && row.VetName) {
            vetSel.add(new Option(row.VetName, row.VetName, true, true));
            vetSel.value = row.VetName;
        }
        document.getElementById('fVetCustom').style.display = 'none';

        document.getElementById('fCC').value         = row.ChiefComplaint || '';
        document.getElementById('fDiag').value       = row.Diagnosis      || '';
        document.getElementById('fTreatment').value  = row.Treatment      || '';
        document.getElementById('fPresc').value      = row.Prescription   || '';

        document.getElementById('fApptIDHidden').value = row.AppointmentID || '';
        const apptSearchInp = document.getElementById('fLinkedApptSearch');
        const clearBtn = document.getElementById('fLinkedApptClearBtn');
        if (row.AppointmentID) {
            const aptLabel = 'APT-' + String(row.AppointmentID).padStart(4, '0') + ' (linked)';
            apptSearchInp.value = aptLabel;
            clearBtn.style.display = '';
            document.getElementById('linkedBadge').style.display = '';
            document.getElementById('autoFillMsg').textContent = 'Linked to APT-' + String(row.AppointmentID).padStart(4, '0');
            document.getElementById('autoFillBanner').classList.remove('d-none');
        } else {
            apptSearchInp.value = '';
            clearBtn.style.display = 'none';
            document.getElementById('autoFillBanner').classList.add('d-none');
        }

        clearSvcRows();
        fetch(`consultations.php?load_svc=${row.ConsultationID}`)
            .then(r => r.json())
            .then(items => items.forEach(item => addServiceRow(item.ServiceID, item.ServiceName, item.Category, item.Quantity, item.UnitPrice)));

        if (row.FollowUpDate) {
            document.getElementById('fEnableFollowUp').checked = true;
            toggleFollowUpFields(true);
            document.getElementById('fFollowUpDate').value = row.FollowUpDate;
            currentFuApptID = row.FollowUpApptID || 0;
            refreshFollowUpSlots(row.FollowUpDate, row.FollowUpTime || '').then(() => {
                document.getElementById('fFollowUpServiceID').value = row.FollowUpServiceID || '';
            });
        } else {
            document.getElementById('fEnableFollowUp').checked = false;
            toggleFollowUpFields(false);
        }

        document.getElementById('consultUpdateReasonField').style.display = 'block';
        document.getElementById('consultUpdatedAtField').style.display    = 'block';
        document.getElementById('fConsultUpdateReason').value    = '';
        document.getElementById('fConsultUpdateReason').required = true;
        document.getElementById('fConsultUpdatedAt').value = new Date().toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });

        bootstrap.Modal.getOrCreateInstance(document.getElementById('consultModal')).show();
    }

    function viewRecord(row) {
        document.getElementById('vPetName').textContent   = row.PetName   || '—';
        document.getElementById('vOwnerName').textContent = row.OwnerName || '—';
        document.getElementById('vVetName').textContent   = row.VetName   || '—';

        // Consultation number
        document.getElementById('vConsultNum').textContent = row.ConsultationID
            ? 'CON-' + String(row.ConsultationID).padStart(4, '0') : '—';

        // Linked appointment (show/hide)
        const vLinkedApptWrap = document.getElementById('vLinkedApptWrap');
        if (row.AppointmentID) {
            document.getElementById('vLinkedAppt').textContent = 'APT-' + String(row.AppointmentID).padStart(4, '0');
            vLinkedApptWrap.style.display = '';
        } else {
            vLinkedApptWrap.style.display = 'none';
        }

        document.getElementById('vDate').textContent      = row.ConsultationDate
            ? new Date(row.ConsultationDate).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })
            : '—';
        const fuEl = document.getElementById('vFollowUp');
        if (row.FollowUpDate) {
            fuEl.className = 'badge-modern badge-scheduled';
            const fuDate = new Date(row.FollowUpDate).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
            let fuTime = '';
            if (row.FollowUpTime) {
                const [h, m] = row.FollowUpTime.split(':');
                const t = new Date(); t.setHours(parseInt(h), parseInt(m));
                fuTime = ' at ' + t.toLocaleTimeString('en-US', { hour:'numeric', minute:'2-digit', hour12:true });
            }
            fuEl.textContent = fuDate + fuTime;
        } else {
            fuEl.className   = 'badge-modern badge-pending';
            fuEl.textContent = 'None Scheduled';
        }
        document.getElementById('vCC').textContent          = row.ChiefComplaint || '—';
        document.getElementById('vDiag').textContent        = row.Diagnosis      || '—';
        document.getElementById('vTreatment').textContent   = row.Treatment      || 'None documented.';
        document.getElementById('vPrescription').textContent = row.Prescription  || 'No medications prescribed.';

        const vConUpdWrap    = document.getElementById('vConUpdatedWrap');
        const vConReasonWrap = document.getElementById('vConReasonWrap');
        if (row.UpdatedAt && row.UpdateReason) {
            document.getElementById('vConUpdated').textContent = new Date(row.UpdatedAt).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
            document.getElementById('vConReason').textContent  = row.UpdateReason;
            vConUpdWrap.style.display    = '';
            vConReasonWrap.style.display = '';
        } else {
            vConUpdWrap.style.display    = 'none';
            vConReasonWrap.style.display = 'none';
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal')).show();
    }

    function printConsultCard() {
        document.getElementById('consultPrintNum').textContent = document.getElementById('vConsultNum').textContent;
        document.getElementById('consultPrintPet').textContent = document.getElementById('vPetName').textContent;
        document.getElementById('consultPrintDate').textContent = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
        window.print();
    }
    
    // Print Driver function for the entire Medical History table
    function printHistoryCard() {
        document.getElementById('historyPrintDate').textContent = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
        window.print();
    }

    // ── PET HISTORY ──
    function viewPetHistory(petID, petName) {
        document.getElementById('phPetName').textContent     = petName;
        document.getElementById('phPrintPetName').textContent= petName; // Sync data label to hidden print-header
        document.getElementById('phRecordCount').textContent = '';
        document.getElementById('phLoading').style.display   = '';
        document.getElementById('phContent').style.display   = 'none';
        document.getElementById('phEmpty').style.display     = 'none';
        document.getElementById('phRows').innerHTML          = '';
        document.getElementById('phSummary').textContent     = '';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('petHistoryModal')).show();

        fetch(`consultations.php?pet_history=${petID}`)
            .then(r => r.json())
            .then(rows => {
                document.getElementById('phLoading').style.display = 'none';
                if (!rows.length) {
                    document.getElementById('phEmpty').style.display = '';
                    return;
                }

                document.getElementById('phRecordCount').textContent =
                    rows.length + ' record' + (rows.length !== 1 ? 's' : '') + ' on file';

                const tbody = document.getElementById('phRows');
                let grandTotal = 0;
                rows.forEach((r, i) => {
                    const date = r.ConsultationDate
                        ? new Date(r.ConsultationDate).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })
                        : '—';
                    const total = parseFloat(r.Total || 0);
                    grandTotal += total;
                    const totalFmt = total > 0
                        ? '₱' + total.toLocaleString('en-PH', { minimumFractionDigits:2, maximumFractionDigits:2 })
                        : '—';

                    let fuHtml = '<span style="color:var(--light-muted);font-size:11px;">None</span>';
                    if (r.FollowUpDate) {
                        const fuDate = new Date(r.FollowUpDate).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
                        fuHtml = `<span class="badge-modern badge-scheduled" style="font-size:10px;">${fuDate}</span>`;
                    }

                    let updateHtml = '';
                    if (r.UpdatedAt && r.UpdateReason) {
                        const d  = new Date(r.UpdatedAt);
                        const df = d.toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
                        updateHtml = `<div style="font-size:10px;color:#92400e;margin-top:3px;"><i class="bi bi-pencil-square" style="color:#f59e0b;"></i> ${df} · <em>${r.UpdateReason}</em></div>`;
                    }

                    tbody.insertAdjacentHTML('beforeend', `
                        <tr id="ph-row-${r.ConsultationID}" style="${i===0 ? 'background:#f0fdf4;' : ''}">
                            <td>
                            <div style="font-weight:700;font-size:12px;color:var(--teal)">
                                CON-${String(r.ConsultationID).padStart(4,'0')}
                            </div>
                                ${i===0 ? '<span style="font-size:9px;color:#10b981;font-weight:700;">LATEST</span>' : ''}
                                ${updateHtml}
                            </td>
                            <td style="font-weight:600;white-space:nowrap;">${date}</td>
                            <td style="white-space:nowrap;">${r.VetName || '—'}</td>
                            <td style="max-width:150px;white-space:normal;font-size:12px;color:var(--dark);">${r.ChiefComplaint || '—'}</td>
                            <td style="max-width:150px;white-space:normal;font-size:12px;">${r.Diagnosis || '—'}</td>
                            <td style="max-width:150px;white-space:normal;font-size:12px;">${r.Treatment || '—'}</td>
                            <td style="font-weight:700;color:var(--teal);white-space:nowrap;">${totalFmt}</td>
                            <td>${fuHtml}</td>
                        </tr>
                    `);
                });

                const gtFmt = '₱' + grandTotal.toLocaleString('en-PH', { minimumFractionDigits:2, maximumFractionDigits:2 });
                document.getElementById('phSummary').innerHTML =
                    `${rows.length} consultation${rows.length !== 1 ? 's' : ''} &nbsp;·&nbsp; Lifetime total: <strong style="color:var(--teal);">${gtFmt}</strong>`;

                document.getElementById('phContent').style.display = '';
            })
            .catch(() => {
                document.getElementById('phLoading').style.display = 'none';
                document.getElementById('phEmpty').style.display   = '';
                document.getElementById('phEmpty').innerHTML       =
                    '<i class="bi bi-exclamation-circle text-danger" style="font-size:2rem;display:block;margin-bottom:8px;"></i>Failed to load records.';
            });
    }
</script>

<div class="modal fade" id="petHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border-bottom:1px solid #bae6fd;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <h5 class="modal-title mb-0" style="color:var(--dark);font-weight:700;">
                        <i class="bi bi-clock-history me-2" style="color:#6366f1;"></i>Medical History
                    </h5>
                    <div style="font-size:12px;color:var(--muted);margin-top:2px;">
                        Patient: <strong id="phPetName" style="color:var(--teal);"></strong>
                        &nbsp;·&nbsp; <span id="phRecordCount"></span>
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <div class="modal-export-wrap">
                        <button type="button" class="btn-modal-export" onclick="toggleExportMenu('consultHistExportMenu',this)">
                            <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="modal-export-menu" id="consultHistExportMenu">
                            <div class="export-menu-label">Export As</div>
                            <div class="export-menu-item" onclick="exportCSV('#historyPrintArea table','patient_history');document.getElementById('consultHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-csv"><i class="bi bi-filetype-csv"></i></div> CSV
                            </div>
                            <div class="export-menu-item" onclick="exportXLSX('#historyPrintArea table','patient_history',true);document.getElementById('consultHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-xls"><i class="bi bi-file-earmark-spreadsheet"></i></div> XLS
                            </div>
                            <div class="export-menu-item" onclick="exportXLSX('#historyPrintArea table','patient_history',false);document.getElementById('consultHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-xlsx"><i class="bi bi-file-earmark-spreadsheet-fill"></i></div> XLSX
                            </div>
                            <div class="export-menu-item" onclick="exportDOCX('#historyPrintArea table','patient_history','Patient Medical History');document.getElementById('consultHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-docx"><i class="bi bi-file-earmark-word"></i></div> DOCX
                            </div>
                            <div class="export-menu-item" onclick="window.print();document.getElementById('consultHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF / Print
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="margin:0;"></button>
                </div>
            </div>
            <div class="modal-body p-0" id="historyPrintArea">
                <div class="history-print-header" style="display:none;padding:22px 28px 16px;border-bottom:2px dashed #e2e8f0;text-align:center;margin-bottom:16px;">
                    <img src="logo1.png" alt="Heartside Vet" style="width:56px;height:56px;object-fit:contain;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;">
                    <div style="font-size:20px;font-weight:800;color:#1e3a5f;letter-spacing:.4px;line-height:1.2;">Heartside Vet Clinic</div>
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px;margin-top:4px;font-weight:600;">Complete Patient History Log</div>
                    <div style="font-size:13px;color:#334155;margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;">Patient: <strong id="phPrintPetName" style="color:#1e3a5f;"></strong></div>
                </div>

                <div id="phLoading" class="text-center p-5 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" style="color:var(--teal);"></div> Loading records…
                </div>
                <div id="phEmpty" class="text-center p-5 text-muted" style="display:none;">
                    <i class="bi bi-folder2-open" style="font-size:2rem;opacity:.4;display:block;margin-bottom:8px;"></i>
                    No consultation records found for this pet.
                </div>
                <div id="phContent" style="display:none;">
                    <table class="modern-table mb-0" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th style="width:130px;">Consult #</th>
                                <th style="width:100px;">Date</th>
                                <th>Attending Vet</th>
                                <th>Chief Complaint</th>
                                <th>Diagnosis</th>
                                <th>Treatment</th>
                                <th style="width:90px;">Total</th>
                                <th style="width:110px;">Follow-Up</th>
                            </tr>
                        </thead>
                        <tbody id="phRows"></tbody>
                    </table>
                </div>

                <div class="history-print-footer" style="display:none;border-top:2px dashed #e2e8f0;margin-top:20px;padding:12px 18px 4px;text-align:center;">
                    <div style="font-size:10px;color:#94a3b8;letter-spacing:.3px;">Heartside Vet Clinic Management System &nbsp;·&nbsp; <span id="historyPrintDate"></span></div>
                    <div style="font-size:10px;color:#cbd5e1;margin-top:2px;">This document is computer-generated and valid without a signature.</div>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid var(--border);">
                <div id="phSummary" style="font-size:12px;color:var(--muted);flex:1;text-align:left;"></div>
                <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>