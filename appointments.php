<?php
require_once 'guard.php';
include('dbconnect.php');

$statusOptions = ['Scheduled', 'Completed', 'Cancelled', 'No-Show'];
$timeSlots = [];
for ($t = strtotime('09:30'); $t < strtotime('19:00'); $t += 1800) {
    $start = date('H:i', $t);
    $end   = date('H:i', $t + 1800);
    $timeSlots[] = ['value' => $start, 'label' => date('g:i A', $t) . '–' . date('g:i A', $t + 1800)];
}

/* POST: ADD / UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $petID     = intval($_POST['PetID']);
    $clientID  = intval($_POST['ClientID']);
    $date      = $_POST['AppointmentDate'] ?: ($_POST['_dateFallback'] ?? '');
    $time      = $_POST['AppointmentTime'] ?: ($_POST['_timeFallback'] ?? '');
    $reason    = trim($_POST['Reason'] ?? '');     // stores only the service name (no price)
    $serviceID = $_POST['ServiceID'] ? intval($_POST['ServiceID']) : null;
    $notes     = trim($_POST['Notes'] ?? '');
    $status    = $_POST['Status'] ?: 'Scheduled';

    $id = isset($_POST['AppointmentID']) ? intval($_POST['AppointmentID']) : 0;

    if ($action === 'add' || $action === 'update') {
        $clientChk = $conn->prepare("SELECT AppointmentID FROM appointments WHERE ClientID = ? AND AppointmentDate = ? AND AppointmentTime = ? AND IsDeleted = 0 AND AppointmentID != ? AND Status NOT IN ('Cancelled','No-Show')");
        $clientChk->bind_param("issi", $clientID, $date, $time, $id);
        $clientChk->execute();
        $clientDoubleBooked = $clientChk->get_result()->fetch_assoc();
        $clientChk->close();

        if ($clientDoubleBooked) {
            header("Location: appointments.php?err=Warning: This client already has an active appointment booked at this time slot.");
            exit;
        }

        $slotChk = $conn->prepare("SELECT AppointmentID FROM appointments WHERE AppointmentDate = ? AND AppointmentTime = ? AND IsDeleted = 0 AND AppointmentID != ? AND Status NOT IN ('Cancelled','No-Show')");
        $slotChk->bind_param("ssi", $date, $time, $id);
        $slotChk->execute();
        $slotTaken = $slotChk->get_result()->fetch_assoc();
        $slotChk->close();

        if ($slotTaken) {
            header("Location: appointments.php?err=Warning: The requested time slot is already booked by another appointment.");
            exit;
        }
    }

    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO appointments (PetID,ClientID,ServiceID,AppointmentDate,AppointmentTime,Reason,Notes,Status) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iiisssss", $petID, $clientID, $serviceID, $date, $time, $reason, $notes, $status);
        $stmt->execute();
        $stmt->close();
        header("Location: appointments.php?msg=Appointment added.");
        exit;
    }

    if ($action === 'update') {
        $updateReason = trim($_POST['UpdateReason'] ?? '');
        $stmt = $conn->prepare("UPDATE appointments SET PetID=?,ClientID=?,ServiceID=?,AppointmentDate=?,AppointmentTime=?,Reason=?,Notes=?,Status=?,UpdateReason=? WHERE AppointmentID=?");
        $stmt->bind_param("iiissssssi", $petID, $clientID, $serviceID, $date, $time, $reason, $notes, $status, $updateReason, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: appointments.php?msg=Appointment updated.");
        exit;
    }
}

// ── AJAX: pet appointment history ──
if (isset($_GET['pet_appt_history'])) {
    $petID = intval($_GET['pet_appt_history']);
    $rows  = $conn->query("
        SELECT a.AppointmentID, a.AppointmentDate, a.AppointmentTime, a.Status,
               a.Reason, a.Notes, s.ServiceName,
               CONCAT(c.FirstName,' ',c.LastName) AS Owner
        FROM appointments a
        JOIN clients c ON a.ClientID = c.ClientID
        LEFT JOIN services s ON a.ServiceID = s.ServiceID
        WHERE a.PetID = $petID AND a.IsDeleted = 0
        ORDER BY a.AppointmentDate DESC, a.AppointmentID DESC
    ")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    exit(json_encode($rows));
}

if (isset($_GET['booked_slots'])) {
    $date = $conn->real_escape_string($_GET['date'] ?? '');
    $excludeID = intval($_GET['exclude'] ?? 0);
    $rows = $conn->query("SELECT AppointmentTime FROM appointments WHERE AppointmentDate='$date' AND COALESCE(IsDeleted,0)=0 AND AppointmentID!=$excludeID AND Status NOT IN ('Cancelled','No-Show')")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(array_map(fn($t) => substr($t, 0, 5), array_column($rows, 'AppointmentTime')));
    exit;
}

if (isset($_GET['delete'])) {
    $id  = intval($_GET['delete']);
    $row = $conn->query("SELECT Status FROM appointments WHERE AppointmentID=$id")->fetch_assoc();
    if ($row && $row['Status'] === 'Scheduled') {
        header("Location: appointments.php?err=Scheduled appointments cannot be removed. Change the status first.");
        exit;
    }
    $conn->query("UPDATE appointments SET IsDeleted=1 WHERE AppointmentID=$id");
    header("Location: appointments.php?msg=Appointment removed.");
    exit;
}
include('header.php');
?>

<?php
$total     = $conn->query("SELECT COUNT(*) FROM appointments WHERE IsDeleted=0")->fetch_row()[0];
$scheduled = $conn->query("SELECT COUNT(*) FROM appointments WHERE IsDeleted=0 AND Status='Scheduled'")->fetch_row()[0];
$completed = $conn->query("SELECT COUNT(*) FROM appointments WHERE IsDeleted=0 AND Status='Completed'")->fetch_row()[0];
$today     = $conn->query("SELECT COUNT(*) FROM appointments WHERE IsDeleted=0 AND AppointmentDate=CURDATE()")->fetch_row()[0];

// Fetch services with prices for the dropdown
$servicesList = $conn->query("SELECT ServiceID, ServiceName, Price FROM services WHERE IsActive=1 AND IsDeleted=0 ORDER BY ServiceName")->fetch_all(MYSQLI_ASSOC);

// Date range filter (filters by Appointment Date)
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$dateSql  = '';
if ($dateFrom !== '') $dateSql .= " AND DATE(a.AppointmentDate) >= '" . $conn->real_escape_string($dateFrom) . "'";
if ($dateTo   !== '') $dateSql .= " AND DATE(a.AppointmentDate) <= '" . $conn->real_escape_string($dateTo) . "'";
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Appointments</h1>
        <p>Manage clinic appointments</p>
    </div>
    <div class="page-header-actions">
        <form method="get" class="date-filter-form">
            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="date-filter-input" title="Appointment date from">
            <span class="date-filter-sep">to</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="date-filter-input" title="Appointment date to">
            <button type="submit" class="btn-date-filter" title="Filter by appointment date"><i class="bi bi-funnel-fill"></i></button>
            <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
            <a href="appointments.php" class="btn-date-clear" title="Clear date filter"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
        <div class="export-dropdown-wrap">
            <button class="btn-export" onclick="toggleExportMenu('exportMenuAppts',this)">
                <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
            </button>
            <div class="export-menu" id="exportMenuAppts">
                <div class="export-menu-label">Export As</div>
                <div class="export-menu-item" onclick="exportCSV('.modern-table','appointments_list');document.getElementById('exportMenuAppts').classList.remove('show');">
                    <div class="ei-icon ei-csv"><i class="bi bi-filetype-csv"></i></div> CSV
                </div>
                <div class="export-menu-item" onclick="exportXLSX('.modern-table','appointments_list',true);document.getElementById('exportMenuAppts').classList.remove('show');">
                    <div class="ei-icon ei-xls"><i class="bi bi-file-earmark-spreadsheet"></i></div> XLS
                </div>
                <div class="export-menu-item" onclick="exportXLSX('.modern-table','appointments_list',false);document.getElementById('exportMenuAppts').classList.remove('show');">
                    <div class="ei-icon ei-xlsx"><i class="bi bi-file-earmark-spreadsheet-fill"></i></div> XLSX
                </div>
                <div class="export-menu-item" onclick="exportDOCX('.modern-table','appointments_list','Appointments List');document.getElementById('exportMenuAppts').classList.remove('show');">
                    <div class="ei-icon ei-docx"><i class="bi bi-file-earmark-word"></i></div> DOCX
                </div>
                <div class="export-menu-item" onclick="exportPDF('.modern-table');document.getElementById('exportMenuAppts').classList.remove('show');">
                    <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF
                </div>
            </div>
        </div>
        <button class="btn-main btn-teal" data-bs-toggle="modal" data-bs-target="#apptModal" onclick="openAdd()">
            <i class="bi bi-plus-lg"></i> New Appointment
        </button>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert-modern alert-success-modern"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
    <div class="alert-modern alert-danger-modern"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($_GET['err']) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach (
        [
            ['Total',     $total,     'bi-calendar2',       '#ffffb2', '#5d6405'],
            ['Scheduled', $scheduled, 'bi-calendar-check',  '#e0e7ff', '#6366f1'],
            ['Completed', $completed, 'bi-check-circle-fill','#f0fdf4', '#10b981'],
            ['Today',     $today,     'bi-calendar-day',    '#fffbeb', '#f59e0b'],
        ] as [$label, $val, $icon, $bg_color, $color]
    ): ?>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: <?= $bg_color ?>; color: <?= $color ?>;">
                    <i class="bi <?= $icon ?>"></i>
                </div>
                <div class="stat-value" style="font-size: 20px;"><?= $val ?></div>
                <div class="stat-label"><?= $label ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><span class="card-header-title"><i class="bi bi-calendar-check-fill me-2" style="color:#6366f1;"></i>Appointment List</span></div>
    <div class="card-body p-0">
        <table class="modern-table">
            <thead>
                <tr>
                    <th> Appointment #</th>
                    <th>Date/Time</th>
                    <th>Pet</th>
                    <th>Owner</th>
                    <th>Service</th>
                    <th>Notes</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $res = $conn->query("SELECT a.*, p.PetName, CONCAT(c.FirstName,' ',c.LastName) AS Owner, s.ServiceName,
                                            con.ConsultationID AS LinkedConsultID
                                     FROM appointments a 
                                     JOIN pets p ON a.PetID=p.PetID 
                                     JOIN clients c ON a.ClientID=c.ClientID 
                                     LEFT JOIN services s ON a.ServiceID=s.ServiceID 
                                     LEFT JOIN consultations con ON con.AppointmentID=a.AppointmentID AND con.IsDeleted=0
                                     WHERE a.IsDeleted=0 $dateSql ORDER BY a.AppointmentID DESC");
                if ($res->num_rows == 0) echo '<tr class="empty-row"><td colspan="8">No appointments found.</td></tr>';

                $highlightApptID = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
                $badgeMap = ['Scheduled' => 'badge-scheduled', 'Completed' => 'badge-completed', 'Cancelled' => 'badge-cancelled', 'No-Show' => 'badge-noshow'];
                while ($row = $res->fetch_assoc()):
                    $status = $row['Status'] ?: 'Scheduled';
                ?>
                    <tr id="row-appt-<?= $row['AppointmentID'] ?>" class="<?= ($highlightApptID && $highlightApptID == $row['AppointmentID']) ? 'search-highlight-row' : '' ?>">
                        <td>
                            <div style="font-weight:700; font-size:13px; color:var(--teal);">
                                APT-<?= str_pad($row['AppointmentID'], 4, '0', STR_PAD_LEFT) ?>
                            </div>
                        </td>
                        <td><?= date('M d, Y', strtotime($row['AppointmentDate'])) ?><br><?php $tS = strtotime($row['AppointmentTime']); echo date('g:i A', $tS).'–'.date('g:i A', $tS+1800); ?></td>
                        <td><?= htmlspecialchars($row['PetName']) ?></td>
                        <td><?= htmlspecialchars($row['Owner']) ?></td>
                        <td><?= htmlspecialchars($row['Reason'] ?: ($row['ServiceName'] ?: '—')) ?></td>
                        <td style="font-size:12px;color:var(--muted);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($row['Notes'] ?? '—') ?></td>
                        <td>
                            <span class="badge-modern <?= $badgeMap[$status] ?? 'badge-scheduled' ?>"><?= $status ?></span>
                            <?php if (!empty($row['LinkedConsultID'])): ?>
                            <div style="margin-top:4px;">
                                <span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:4px;padding:1px 6px;">
                                    <i class="bi bi-clipboard2-pulse-fill"></i> Consulted
                                </span>
                                <div style="font-size:10px;color:var(--muted);margin-top:1px;">CON-<?= str_pad($row['LinkedConsultID'], 4, '0', STR_PAD_LEFT) ?></div>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn-icon btn-icon-view" data-row="<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>" onclick="viewAppt(JSON.parse(this.dataset.row))" title="View Details"><i class="bi bi-eye"></i></button>
                                <button class="btn-icon" style="background:rgba(99,102,241,.1);color:#6366f1;" data-pet-id="<?= $row['PetID'] ?>" data-pet-name="<?= htmlspecialchars($row['PetName'], ENT_QUOTES) ?>" data-owner="<?= htmlspecialchars($row['Owner'], ENT_QUOTES) ?>" onclick="viewPetApptHistory(this.dataset.petId, this.dataset.petName, this.dataset.owner)" title="Pet Appointment History"><i class="bi bi-clock-history"></i></button>
                                <button class="btn-icon btn-icon-edit" data-row="<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>" onclick="openEdit(JSON.parse(this.dataset.row))"><i class="bi bi-pencil"></i></button>
                                <a href="appointments.php?delete=<?= $row['AppointmentID'] ?>" class="btn-icon btn-icon-delete" onclick="return confirm('Remove this appointment?')"><i class="bi bi-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- VIEW APPOINTMENT MODAL -->
<div class="modal fade" id="viewApptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;">

            <!-- Custom header with print button -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:#fff;border-bottom:1px solid var(--border);">
                <span style="font-weight:700;font-size:14px;color:var(--dark);display:flex;align-items:center;gap:8px;">
                    <i class="bi bi-calendar2-check-fill" style="color:var(--teal);"></i> Appointment Details
                </span>
                <div style="display:flex;gap:8px;align-items:center;">
                    <div class="modal-export-wrap">
                        <button type="button" class="btn-modal-export" onclick="toggleExportMenu('apptViewExportMenu',this)">
                            <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="modal-export-menu" id="apptViewExportMenu">
                            <div class="export-menu-label">Export As</div>
                            <div class="export-menu-item" onclick="window.print();document.getElementById('apptViewExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF / Print
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size:11px;"></button>
                </div>
            </div>

            <div class="modal-body p-0" id="apptPrintArea">
                <!-- Print-only header -->
                <div class="appt-print-header" style="display:none;padding:22px 28px 16px;border-bottom:2px dashed #e2e8f0;text-align:center;margin-bottom:8px;">
                    <img src="logo1.png" alt="Heartside Vet" style="width:56px;height:56px;object-fit:contain;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;">
                    <div style="font-size:20px;font-weight:800;color:#1e3a5f;letter-spacing:.4px;line-height:1.2;">Heartside Vet Clinic</div>
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px;margin-top:4px;font-weight:600;">Appointment Record</div>
                    <div style="font-size:13px;color:#334155;margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;">Appointment: <strong id="apptPrintID" style="color:#1e3a5f;"></strong> &nbsp;&nbsp;·&nbsp;&nbsp; Patient: <strong id="apptPrintPet" style="color:#1e3a5f;"></strong></div>
                </div>

                <div style="padding:16px 20px;">
                    <div class="vm-header-band row g-3">
                        <div class="col-6">
                            <div class="vm-label">Appointment #</div>
                            <div class="vm-value-id" id="vApptID">—</div>
                        </div>
                        <div class="col-6">
                            <div class="vm-label">Status</div>
                            <div id="vApptStatus">—</div>
                        </div>
                        <div class="col-6">
                            <div class="vm-label">Date</div>
                            <div class="vm-value-lg" id="vApptDate">—</div>
                        </div>
                        <div class="col-6">
                            <div class="vm-label">Time</div>
                            <div class="vm-value-lg" id="vApptTime">—</div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="vm-label"><i class="bi bi-heart-pulse me-1"></i>Pet</div>
                            <div class="vm-value" id="vApptPet">—</div>
                        </div>
                        <div class="col-6">
                            <div class="vm-label"><i class="bi bi-person me-1"></i>Owner</div>
                            <div class="vm-value" id="vApptOwner">—</div>
                        </div>
                        <div class="col-12">
                            <div class="vm-label"><i class="bi bi-scissors me-1"></i>Requested Service</div>
                            <div class="vm-value" id="vApptService">—</div>
                        </div>
                        <div class="col-12">
                            <div class="vm-label"><i class="bi bi-chat-left-text me-1"></i>Additional Notes</div>
                            <div class="vm-notes-block" id="vApptNotes">—</div>
                        </div>
                        <div class="col-12" id="vApptConsultWrap" style="display:none;">
                            <div class="vm-label"><i class="bi bi-clipboard2-pulse-fill me-1" style="color:#16a34a;"></i>Linked Consultation</div>
                            <div>
                                <span style="font-weight:700;font-size:13px;color:#16a34a;" id="vApptConsultID">—</span>
                                <span style="margin-left:8px;font-size:10px;font-weight:700;color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:4px;padding:2px 6px;"><i class="bi bi-clipboard2-pulse-fill me-1"></i>Consulted</span>
                            </div>
                        </div>
                        <div class="col-6" id="vApptUpdatedWrap" style="display:none;">
                            <div class="vm-label"><i class="bi bi-clock-history me-1"></i>Last Updated</div>
                            <div class="vm-value" id="vApptUpdated" style="font-size:13px;">—</div>
                        </div>
                        <div class="col-6" id="vApptReasonWrap" style="display:none;">
                            <div class="vm-label"><i class="bi bi-pencil-square me-1"></i>Reason for Update</div>
                            <div class="vm-notes-block" id="vApptReason" style="font-size:13px;">—</div>
                        </div>
                    </div>
                </div>

                <!-- Print-only footer -->
                <div class="appt-print-footer" style="display:none;border-top:2px dashed #e2e8f0;margin:20px 24px 0;padding:12px 0 4px;text-align:center;">
                    <div style="font-size:10px;color:#94a3b8;letter-spacing:.3px;">Heartside Vet Clinic Management System &nbsp;·&nbsp; <span id="apptPrintDate"></span></div>
                    <div style="font-size:10px;color:#cbd5e1;margin-top:2px;">This document is computer-generated and valid without a signature.</div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    /* ── Reset ── */
    body * { visibility: hidden; }
    body { margin: 0; padding: 0; background: #fff; font-size: 13px; font-family: 'Inter', system-ui, sans-serif; }

    /* ── Appointment Detail print ── */
    #apptPrintArea, #apptPrintArea * { visibility: visible; }
    #apptPrintArea {
        position: fixed; top: 0; left: 0; width: 100%;
        padding: 40px; background: #fff; box-sizing: border-box;
    }
    .appt-print-header, .appt-print-footer { display: block !important; }

    /* ── Pet Appointment History print ── */
    #pahPrintArea, #pahPrintArea * { visibility: visible; }
    #pahPrintArea {
        position: fixed; top: 0; left: 0; width: 100%;
        padding: 40px; background: #fff; box-sizing: border-box;
    }
    .pah-print-header, .pah-print-footer { display: block !important; }

    /* ── Shared modal cleanup ── */
    .modal, .modal-dialog, .modal-content {
        box-shadow: none !important; border: none !important;
    }
    .vm-header-band { border: none !important; background: transparent !important; }
    .badge-modern { border: 1px solid #ccc !important; }
}
</style>

<!-- PET APPOINTMENT HISTORY MODAL -->
<div class="modal fade" id="petApptHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);border-bottom:1px solid #ddd6fe;display:flex;align-items:flex-start;justify-content:space-between;">
                <div style="flex:1;">
                    <h5 class="modal-title mb-0" style="color:var(--dark);font-weight:700;">
                        <i class="bi bi-clock-history me-2" style="color:#6366f1;"></i>Appointment History
                    </h5>
                    <div style="font-size:12px;color:var(--muted);margin-top:2px;">
                        Patient: <strong id="pahPetName" style="color:#6366f1;"></strong>
                        &nbsp;·&nbsp; Owner: <strong id="pahOwnerName" style="color:var(--dark);"></strong>
                        &nbsp;·&nbsp; <span id="pahRecordCount"></span>
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;margin-left:12px;">
                    <div class="modal-export-wrap">
                        <button type="button" class="btn-modal-export" onclick="toggleExportMenu('apptHistExportMenu',this)">
                            <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="modal-export-menu" id="apptHistExportMenu">
                            <div class="export-menu-label">Export As</div>
                            <div class="export-menu-item" onclick="exportCSV('#pahPrintArea table','pet_appt_history');document.getElementById('apptHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-csv"><i class="bi bi-filetype-csv"></i></div> CSV
                            </div>
                            <div class="export-menu-item" onclick="exportXLSX('#pahPrintArea table','pet_appt_history',true);document.getElementById('apptHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-xls"><i class="bi bi-file-earmark-spreadsheet"></i></div> XLS
                            </div>
                            <div class="export-menu-item" onclick="exportXLSX('#pahPrintArea table','pet_appt_history',false);document.getElementById('apptHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-xlsx"><i class="bi bi-file-earmark-spreadsheet-fill"></i></div> XLSX
                            </div>
                            <div class="export-menu-item" onclick="exportDOCX('#pahPrintArea table','pet_appt_history','Pet Appointment History');document.getElementById('apptHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-docx"><i class="bi bi-file-earmark-word"></i></div> DOCX
                            </div>
                            <div class="export-menu-item" onclick="window.print();document.getElementById('apptHistExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF / Print
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0" id="pahPrintArea">
                <!-- Print-only header -->
                <div class="pah-print-header" style="display:none;padding:22px 28px 16px;border-bottom:2px dashed #e2e8f0;text-align:center;margin-bottom:16px;">
                    <img src="logo1.png" alt="Heartside Vet" style="width:56px;height:56px;object-fit:contain;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;">
                    <div style="font-size:20px;font-weight:800;color:#1e3a5f;letter-spacing:.4px;line-height:1.2;">Heartside Vet Clinic</div>
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px;margin-top:4px;font-weight:600;">Pet Appointment History</div>
                    <div style="font-size:13px;color:#334155;margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;">Patient: <strong id="pahPrintPetName" style="color:#1e3a5f;"></strong> &nbsp;&nbsp;·&nbsp;&nbsp; Owner: <strong id="pahPrintOwner" style="color:#1e3a5f;"></strong></div>
                </div>

                <div id="pahLoading" class="text-center p-5 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" style="color:#6366f1;"></div> Loading records…
                </div>
                <div id="pahEmpty" class="text-center p-5 text-muted" style="display:none;">
                    <i class="bi bi-calendar-x" style="font-size:2rem;opacity:.4;display:block;margin-bottom:8px;"></i>
                    No appointment records found for this pet.
                </div>
                <div id="pahContent" style="display:none;">
                    <table class="modern-table mb-0" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th style="width:110px;">Appt #</th>
                                <th style="width:110px;">Date</th>
                                <th style="width:90px;">Time</th>
                                <th>Service / Reason</th>
                                <th>Notes</th>
                                <th style="width:110px;">Status</th>
                            </tr>
                        </thead>
                        <tbody id="pahRows"></tbody>
                    </table>
                </div>

                <!-- Print-only footer -->
                <div class="pah-print-footer" style="display:none;border-top:2px dashed #e2e8f0;margin-top:20px;padding:12px 18px 4px;text-align:center;">
                    <div style="font-size:10px;color:#94a3b8;letter-spacing:.3px;">Heartside Vet Clinic Management System &nbsp;·&nbsp; <span id="pahPrintDate"></span></div>
                    <div style="font-size:10px;color:#cbd5e1;margin-top:2px;">This document is computer-generated and valid without a signature.</div>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid var(--border);">
                <div id="pahSummary" style="font-size:12px;color:var(--muted);flex:1;text-align:left;"></div>
                <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ADD / EDIT APPOINTMENT MODAL -->
<div class="modal fade" id="apptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable" style="max-height:95vh;">
        <div class="modal-content" style="max-height:90vh;display:flex;flex-direction:column;">
            <div class="modal-header" style="flex-shrink:0;">
                <h5 class="modal-title" id="modalTitle">
                    <i class="bi bi-calendar-plus me-2" style="color:var(--teal);"></i>New appointment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="appointmentMainForm" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="AppointmentID" id="apptID">
                <input type="hidden" id="fDateFallback" name="_dateFallback">
                <input type="hidden" id="fTimeFallback" name="_timeFallback">
                <!-- hidden: stores the ServiceID for DB relation -->
                <input type="hidden" name="ServiceID" id="fServiceID">

                <div class="modal-body" style="overflow-y:auto;flex:1;">
                    <div id="apptWarningBanner" class="alert alert-danger d-none" style="font-size:13px;font-weight:600;">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Warning: The selected schedule slot is booked or unavailable!
                    </div>

                    <div class="row g-3">
                        <!-- PET -->
                        <div class="col-md-6">
                            <label class="form-label">Pet <span style="color:#ef4444;">*</span></label>
                            <select name="PetID" id="fPetID" class="form-select" required
                                onchange="onPetChange(this)">
                                <option value="">— select pet —</option>
                                <?php foreach ($conn->query("SELECT p.PetID, p.PetName, p.ClientID, CONCAT(c.FirstName,' ',c.LastName) AS Owner FROM pets p JOIN clients c ON p.ClientID=c.ClientID WHERE p.IsActive=1 ORDER BY p.PetName")->fetch_all(MYSQLI_ASSOC) as $p): ?>
                                    <option value="<?= $p['PetID'] ?>" data-client="<?= $p['ClientID'] ?>" data-owner="<?= htmlspecialchars($p['Owner']) ?>">
                                        <?= htmlspecialchars($p['PetName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- OWNER (auto-filled, read-only) -->
                        <div class="col-md-6">
                            <label class="form-label">Owner</label>
                            <input type="hidden" name="ClientID" id="fClientID">
                            <input type="text" id="fOwnerDisplay" class="form-control" readonly placeholder="—" style="background:#f8fafc;">
                        </div>

                        <!-- DATE -->
                        <div class="col-md-6" id="dateFieldWrap">
                            <label class="form-label">Date <span style="color:#ef4444;">*</span></label>
                            <input type="date" name="AppointmentDate" id="fDate" class="form-control" required
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        <!-- TIME -->
                        <div class="col-md-6" id="timeFieldWrap">
                            <label class="form-label">Time <span style="color:#ef4444;">*</span></label>
                            <select name="AppointmentTime" id="fTime" class="form-select" required>
                                <option value="">— select time —</option>
                                <?php foreach ($timeSlots as $slot): ?>
                                    <option value="<?= $slot['value'] ?>"><?= $slot['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- REASON / SERVICE (shows price in dropdown, saves only name) -->
                        <div class="col-12">
                            <label class="form-label">Reason for Visit <span style="color:#ef4444;">*</span></label>
                            <select id="fReasonSelect" class="form-select" onchange="onReasonChange(this)">
                                <option value="">— choose reason —</option>
                                <?php foreach ($servicesList as $svc): ?>
                                    <option value="<?= $svc['ServiceID'] ?>"
                                            data-name="<?= htmlspecialchars($svc['ServiceName']) ?>"
                                            data-price="<?= number_format($svc['Price'], 0) ?>">
                                        <?= htmlspecialchars($svc['ServiceName']) ?> — ₱<?= number_format($svc['Price'], 0) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <!-- hidden text input that stores only the service name -->
                            <input type="hidden" name="Reason" id="fReason">
                        </div>

                        <!-- REQUESTED SERVICE display (readonly, shows after selection) -->
                        <div class="col-12" id="requestedServiceRow" style="display:none;">
                            <label class="form-label">Requested Service</label>
                            <input type="text" id="fRequestedServiceDisplay" class="form-control" readonly style="background:#f0fdf4;border-color:#bbf7d0;font-weight:600;">
                        </div>

                        <!-- STATUS (hidden for new, visible for edit) -->
                        <div class="col-md-6" id="statusFieldWrap" style="display:none;">
                            <label class="form-label">Status</label>
                            <select name="Status" id="fStatus" class="form-select">
                                <?php foreach ($statusOptions as $s): ?>
                                    <option value="<?= $s ?>"><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- UPDATE REASON (edit only) -->
                        <div class="col-12" id="apptUpdateReasonField" style="display:none;">
                            <label class="form-label"></i>Reason for Updating <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="UpdateReason" id="fApptUpdateReason" class="form-control" placeholder="e.g. Rescheduled, status change…">
                        </div>
                        <div class="col-md-6" id="apptUpdatedAtField" style="display:none;">
                            <label class="form-label"></i>Date Updated</label>
                            <input type="text" id="fApptUpdatedAt" class="form-control" readonly style="background:#f8fafc; color:var(--muted); font-size:13px;">
                        </div>
                        <!-- ADDITIONAL NOTES -->
                        <div class="col-12">
                            <label class="form-label" style="display:flex;align-items:center;gap:6px;">
                                </i> Additional Notes
                            </label>
                            <textarea name="Notes" id="fNotes" class="form-control" rows="2"
                                      placeholder="e.g. first visit, bring vaccination history…"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-main btn-teal" id="submitBtn">
                        <i class="bi bi-check-lg me-1"></i> Save appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let dateChangeOpen = false;
    let currentApptID  = 0;

    // Services map for edit prefill: { serviceID: {name, price} }
    const servicesMap = <?= json_encode(array_column($servicesList, null, 'ServiceID')) ?>;

    function onPetChange(sel) {
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('fClientID').value   = opt.dataset.client || '';
        document.getElementById('fOwnerDisplay').value = opt.dataset.owner || '';
    }

    function onReasonChange(sel) {
        const opt    = sel.options[sel.selectedIndex];
        const name   = opt.dataset.name  || '';
        const svcID  = opt.value || '';
        document.getElementById('fReason').value    = name;   // save only name
        document.getElementById('fServiceID').value = svcID;
        const row = document.getElementById('requestedServiceRow');
        const disp = document.getElementById('fRequestedServiceDisplay');
        if (name) {
            disp.value  = name;
            row.style.display = '';
        } else {
            row.style.display = 'none';
            disp.value = '';
        }
    }

    // All possible slots: value → display label
    const ALL_SLOTS = <?php
        $out = [];
        for ($t = strtotime('09:30'); $t < strtotime('19:00'); $t += 1800)
            $out[date('H:i', $t)] = date('g:i A', $t) . '–' . date('g:i A', $t + 1800);
        echo json_encode($out);
    ?>;

    let bookedSlots = [];

    async function refreshTimeSlots(date, keepValue) {
        if (!date) return;
        // Normalize keepValue to HH:MM in case DB returns HH:MM:SS
        const keep = keepValue ? keepValue.substring(0, 5) : null;
        const res  = await fetch(`appointments.php?booked_slots=1&date=${date}&exclude=${currentApptID}`);
        bookedSlots = await res.json();
        const sel  = document.getElementById('fTime');
        sel.innerHTML = '<option value="">— select time —</option>';
        for (const [val, label] of Object.entries(ALL_SLOTS)) {
            const taken = bookedSlots.includes(val);
            const opt   = document.createElement('option');
            opt.value   = val;
            opt.textContent = taken ? label + '  ✕ Already Booked' : label;
            if (taken) {
                opt.style.color     = '#b0b8c8';
                opt.style.fontStyle = 'italic';
                opt.dataset.booked  = '1';
            }
            sel.appendChild(opt);
        }
        if (keep) sel.value = keep;
        checkSlotWarning(sel);
    }

    function checkSlotWarning(sel) {
        const chosen = sel.options[sel.selectedIndex];
        const banner = document.getElementById('apptWarningBanner');
        if (chosen && chosen.dataset.booked === '1') {
            banner.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i> This time slot is already booked. Please choose another.';
            banner.classList.remove('d-none');
        } else {
            banner.classList.add('d-none');
        }
    }

    document.getElementById('fTime').addEventListener('change', function () {
        checkSlotWarning(this);
    });

    document.getElementById('fDate').addEventListener('change', function () {
        refreshTimeSlots(this.value);
    });

    document.getElementById('appointmentMainForm').addEventListener('submit', function (e) {
        const timeSel = document.getElementById('fTime');
        const chosen  = timeSel.options[timeSel.selectedIndex];
        if (!timeSel.value) {
            e.preventDefault();
            alert("Please select a time slot.");
            return;
        }
        // Only block if the slot is explicitly marked as booked by another appointment
        if (chosen && chosen.dataset.booked === '1') {
            e.preventDefault();
            const banner = document.getElementById('apptWarningBanner');
            banner.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i> This time slot is already booked. Please choose another.';
            banner.classList.remove('d-none');
            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });

    function showDateFields(show) {
        document.getElementById('dateFieldWrap').style.display =
        document.getElementById('timeFieldWrap').style.display = show ? '' : 'none';
        document.getElementById('fDate').required =
        document.getElementById('fTime').required = show;
    }

    function viewAppt(r) {
        const badgeMap = { 'Scheduled':'badge-scheduled','Completed':'badge-completed','Cancelled':'badge-cancelled','No-Show':'badge-noshow' };
        const status   = r.Status || 'Scheduled';
        document.getElementById('vApptID').textContent = 'APT-' + String(r.AppointmentID).padStart(4, '0');            
        document.getElementById('vApptStatus').innerHTML = `<span class="badge-modern ${badgeMap[status]||'badge-scheduled'}">${status}</span>`;
        document.getElementById('vApptDate').textContent = r.AppointmentDate
            ? new Date(r.AppointmentDate + 'T00:00:00').toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit' }) : '—';
        document.getElementById('vApptTime').textContent = r.AppointmentTime
            ? new Date('1970-01-01T' + r.AppointmentTime).toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' }) : '—';
        document.getElementById('vApptPet').textContent     = r.PetName    || '—';
        document.getElementById('vApptOwner').textContent   = r.Owner      || '—';
        document.getElementById('vApptService').textContent = r.Reason     || r.ServiceName || '—';
        document.getElementById('vApptNotes').textContent   = r.Notes      || '—';
        // Show linked consultation if any
        const consultBadgeWrap = document.getElementById('vApptConsultWrap');
        if (consultBadgeWrap) {
            if (r.LinkedConsultID) {
                document.getElementById('vApptConsultID').textContent = 'CON-' + String(r.LinkedConsultID).padStart(4,'0');
                consultBadgeWrap.style.display = '';
            } else {
                consultBadgeWrap.style.display = 'none';
            }
        }
        const apptUpdWrap    = document.getElementById('vApptUpdatedWrap');
        const apptReasonWrap = document.getElementById('vApptReasonWrap');
        if (r.UpdatedAt && r.UpdateReason) {
            document.getElementById('vApptUpdated').textContent = new Date(r.UpdatedAt).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
            document.getElementById('vApptReason').textContent  = r.UpdateReason;
            apptUpdWrap.style.display    = '';
            apptReasonWrap.style.display = '';
        } else {
            apptUpdWrap.style.display    = 'none';
            apptReasonWrap.style.display = 'none';
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('viewApptModal')).show();
    }

    function printApptCard() {
        document.getElementById('apptPrintID').textContent  = document.getElementById('vApptID').textContent;
        document.getElementById('apptPrintPet').textContent = document.getElementById('vApptPet').textContent;
        document.getElementById('apptPrintDate').textContent = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
        window.print();
    }

    function printPetApptHistory() {
        document.getElementById('pahPrintPetName').textContent = document.getElementById('pahPetName').textContent;
        document.getElementById('pahPrintOwner').textContent   = document.getElementById('pahOwnerName').textContent;
        document.getElementById('pahPrintDate').textContent    = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
        window.print();
    }

    function viewPetApptHistory(petID, petName, owner) {
        document.getElementById('pahPetName').textContent     = petName;
        document.getElementById('pahOwnerName').textContent   = owner;
        document.getElementById('pahRecordCount').textContent = '';
        document.getElementById('pahSummary').textContent     = '';
        document.getElementById('pahLoading').style.display   = '';
        document.getElementById('pahContent').style.display   = 'none';
        document.getElementById('pahEmpty').style.display     = 'none';
        document.getElementById('pahRows').innerHTML          = '';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('petApptHistoryModal')).show();

        const badgeMap = { Scheduled:'badge-scheduled', Completed:'badge-completed', Cancelled:'badge-cancelled', 'No-Show':'badge-noshow' };

        fetch(`appointments.php?pet_appt_history=${petID}`)
            .then(r => r.json())
            .then(rows => {
                document.getElementById('pahLoading').style.display = 'none';
                if (!rows.length) { document.getElementById('pahEmpty').style.display = ''; return; }

                document.getElementById('pahRecordCount').textContent =
                    rows.length + ' record' + (rows.length !== 1 ? 's' : '') + ' on file';

                const tbody = document.getElementById('pahRows');
                const counts = { Scheduled:0, Completed:0, Cancelled:0, 'No-Show':0 };
                rows.forEach((r, i) => {
                    counts[r.Status] = (counts[r.Status] || 0) + 1;
                    const date = r.AppointmentDate
                        ? new Date(r.AppointmentDate + 'T00:00:00').toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })
                        : '—';
                    const time = r.AppointmentTime
                        ? new Date('1970-01-01T' + r.AppointmentTime).toLocaleTimeString('en-US', { hour:'numeric', minute:'2-digit' })
                        : '—';
                    const badge = badgeMap[r.Status] || 'badge-scheduled';
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr style="${i===0?'background:#f5f3ff;':''}">
                            <td><span style="font-weight:700;font-size:12px;color:#6366f1;">APT-${String(r.AppointmentID).padStart(4,'0')}</span>${i===0?'<br><span style="font-size:9px;color:#6366f1;font-weight:700;">LATEST</span>':''}</td>
                            <td style="font-weight:600;white-space:nowrap;">${date}</td>
                            <td style="white-space:nowrap;">${time}</td>
                            <td style="font-size:12px;">${r.Reason || r.ServiceName || '—'}</td>
                            <td style="font-size:12px;color:var(--muted);max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${r.Notes || '—'}</td>
                            <td><span class="badge-modern ${badge}">${r.Status}</span></td>
                        </tr>`);
                });

                const parts = Object.entries(counts).filter(([,v])=>v>0).map(([k,v])=>`${v} ${k}`).join(' · ');
                document.getElementById('pahSummary').innerHTML =
                    `${rows.length} appointment${rows.length!==1?'s':''} &nbsp;·&nbsp; ${parts}`;
                document.getElementById('pahContent').style.display = '';
            })
            .catch(() => {
                document.getElementById('pahLoading').style.display = 'none';
                document.getElementById('pahEmpty').style.display   = '';
                document.getElementById('pahEmpty').innerHTML       =
                    '<i class="bi bi-exclamation-circle text-danger" style="font-size:2rem;display:block;margin-bottom:8px;"></i>Failed to load records.';
            });
    }

    function openAdd() {
        currentApptID = 0;
        document.getElementById('modalTitle').innerHTML = '<i class="bi bi-calendar-plus me-2" style="color:var(--teal);"></i>New appointment';
        document.getElementById('formAction').value = 'add';
        document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Save appointment';
        document.getElementById('apptWarningBanner').classList.add('d-none');
        document.getElementById('appointmentMainForm').reset();
        document.getElementById('fOwnerDisplay').value = '';
        document.getElementById('fClientID').value     = '';
        document.getElementById('fReason').value       = '';
        document.getElementById('fServiceID').value    = '';
        document.getElementById('requestedServiceRow').style.display = 'none';
        document.getElementById('statusFieldWrap').style.display     = 'none';
        document.getElementById('apptUpdateReasonField').style.display = 'none';
        document.getElementById('apptUpdatedAtField').style.display    = 'none';
        document.getElementById('fApptUpdateReason').required = false;
        document.getElementById('fDate').value = '<?= date('Y-m-d') ?>';
        showDateFields(true);
        refreshTimeSlots('<?= date('Y-m-d') ?>');
    }

    function openEdit(row) {
        currentApptID = row.AppointmentID;
        document.getElementById('modalTitle').innerHTML = '</i>Edit Appointment #' + row.AppointmentID;
        document.getElementById('formAction').value    = 'update';
        document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-lg me-1"></i> Update Appointment';
        document.getElementById('apptWarningBanner').classList.add('d-none');
        document.getElementById('apptID').value        = row.AppointmentID;
        document.getElementById('fPetID').value        = row.PetID;
        document.getElementById('fClientID').value     = row.ClientID;
        document.getElementById('fOwnerDisplay').value = row.Owner || '';
        document.getElementById('fNotes').value        = row.Notes || '';
        document.getElementById('fStatus').value       = row.Status || 'Scheduled';

        // Normalize time to HH:MM (DB may return HH:MM:SS)
        const existingTime = (row.AppointmentTime || '').substring(0, 5);
        document.getElementById('fDateFallback').value = row.AppointmentDate;
        document.getElementById('fTimeFallback').value = existingTime;

        // Prefill reason / service
        document.getElementById('fReason').value    = row.Reason || '';
        document.getElementById('fServiceID').value = row.ServiceID || '';
        const rSel = document.getElementById('fReasonSelect');
        let matched = false;
        for (let opt of rSel.options) {
            if (opt.value && opt.value == row.ServiceID) { rSel.value = opt.value; matched = true; break; }
        }
        if (!matched) rSel.value = '';
        const dispRow = document.getElementById('requestedServiceRow');
        const dispInp = document.getElementById('fRequestedServiceDisplay');
        if (row.Reason) { dispInp.value = row.Reason; dispRow.style.display = ''; }
        else { dispRow.style.display = 'none'; }

        // Show date/time fields pre-filled
        document.getElementById('statusFieldWrap').style.display = '';
        dateChangeOpen = true;
        showDateFields(true);
        document.getElementById('fDate').value = row.AppointmentDate;

        // Show update reason fields
        document.getElementById('apptUpdateReasonField').style.display = 'block';
        document.getElementById('apptUpdatedAtField').style.display    = 'block';
        document.getElementById('fApptUpdateReason').value   = '';
        document.getElementById('fApptUpdateReason').required = true;
        document.getElementById('fApptUpdatedAt').value = new Date().toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });

        // Open modal immediately, then load slots async and select existing time
        bootstrap.Modal.getOrCreateInstance(document.getElementById('apptModal')).show();
        refreshTimeSlots(row.AppointmentDate, existingTime);
    }
</script>
<?php include('footer.php'); ?>