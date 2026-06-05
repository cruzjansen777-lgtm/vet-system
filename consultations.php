<?php
include('dbconnect.php');

// Generate time slots efficiently using a short loop
$timeSlots = [];
for ($t = strtotime('09:30'); $t <= strtotime('18:30'); $t += 1800) { $timeSlots[] = date('H:i', $t); }

// Live AJAX endpoint to check slot availability
if (isset($_GET['get_booked_slots'])) {
    $date = $conn->real_escape_string($_GET['date'] ?? '');
    $rows = $conn->query("SELECT AppointmentTime FROM appointments WHERE AppointmentDate='$date' AND IsDeleted=0 AND Status NOT IN ('Cancelled','No-Show')")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    exit(json_encode(array_column($rows, 'AppointmentTime')));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid = intval($_POST['PetID']); $cid = intval($_POST['ClientID']);
    $appt = $_POST['AppointmentID'] ? intval($_POST['AppointmentID']) : null;
    $date = $_POST['ConsultationDate']; $vet = $_POST['VetName'];
    $cc = $_POST['ChiefComplaint']; $diag = $_POST['Diagnosis'];
    $trtm = $_POST['Treatment']; $presc = $_POST['Prescription']; $notes = $_POST['Notes'] ?? '';
    
    $hasFU = isset($_POST['EnableFollowUp']);
    $fu = ($hasFU && $_POST['FollowUpDate']) ? $_POST['FollowUpDate'] : null;
    $fuTime = ($hasFU && $_POST['FollowUpTime']) ? $_POST['FollowUpTime'] : null;
    $fuService = ($hasFU && $_POST['FollowUpServiceID']) ? intval($_POST['FollowUpServiceID']) : null;

    if ($fu && $fuTime) {
        $slotCheck = $conn->query("SELECT AppointmentID FROM appointments WHERE AppointmentDate='$fu' AND AppointmentTime='$fuTime' AND IsDeleted=0 AND Status NOT IN ('Cancelled','No-Show')")->num_rows;
        $clientCheck = $conn->query("SELECT AppointmentID FROM appointments WHERE ClientID=$cid AND AppointmentDate='$fu' AND AppointmentTime='$fuTime' AND IsDeleted=0 AND Status NOT IN ('Cancelled','No-Show')")->num_rows;
        if ($slotCheck > 0 || $clientCheck > 0) {
            header("Location: consultations.php?err=The selected follow-up time slot (" . date('h:i A', strtotime($fuTime)) . ") on " . date('M d, Y', strtotime($fu)) . " is already booked.");
            exit;
        }
    }

    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO consultations (PetID,ClientID,AppointmentID,ConsultationDate,VetName,ChiefComplaint,Diagnosis,Treatment,Prescription,Notes,FollowUpDate) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iiissssssss",$pid,$cid,$appt,$date,$vet,$cc,$diag,$trtm,$presc,$notes,$fu);
        $stmt->execute(); $stmt->close();
        
        if ($fu && $fuTime) {
            $existing = $conn->query("SELECT AppointmentID FROM appointments WHERE PetID=$pid AND AppointmentDate='$fu' AND Status='Scheduled' AND IsDeleted=0")->num_rows;
            if (!$existing) {
                $ins = $conn->prepare("INSERT INTO appointments (PetID,ClientID,ServiceID,AppointmentDate,AppointmentTime,Reason,Status,IsDeleted) VALUES (?,?,?,?,?,'Follow-up consultation','Scheduled',0)");
                $ins->bind_param("iiiss", $pid, $cid, $fuService, $fu, $fuTime); $ins->execute(); $ins->close();
            }
        }
        header("Location: consultations.php?msg=Medical record added."); exit;
    }
    
    if ($action === 'update') {
        $id = intval($_POST['ConsultationID']);
        $old = $conn->query("SELECT FollowUpDate, PetID, ClientID FROM consultations WHERE ConsultationID = $id")->fetch_assoc();

        // Allow pet/client update
        $stmt = $conn->prepare("UPDATE consultations SET PetID=?,ClientID=?,ConsultationDate=?,VetName=?,FollowUpDate=?,ChiefComplaint=?,Diagnosis=?,Treatment=?,Prescription=?,Notes=? WHERE ConsultationID=?");
        $stmt->bind_param("iissssssssi", $pid,$cid,$date,$vet,$fu,$cc,$diag,$trtm,$presc,$notes,$id);
        $stmt->execute(); $stmt->close();
        
        if ($old) {
            if ($old['FollowUpDate'] && $old['FollowUpDate'] !== $fu) {
                $conn->query("UPDATE appointments SET IsDeleted=1 WHERE PetID={$old['PetID']} AND AppointmentDate='{$old['FollowUpDate']}' AND Reason='Follow-up consultation' AND Status='Scheduled'");
            }
            if ($fu && $fuTime && !$conn->query("SELECT AppointmentID FROM appointments WHERE PetID=$pid AND AppointmentDate='$fu' AND Status='Scheduled' AND IsDeleted=0")->num_rows) {
                $ins = $conn->prepare("INSERT INTO appointments (PetID,ClientID,ServiceID,AppointmentDate,AppointmentTime,Reason,Status,IsDeleted) VALUES (?,?,?,?,?,'Follow-up consultation','Scheduled',0)");
                $ins->bind_param("iiiss", $pid, $cid, $fuService, $fu, $fuTime); $ins->execute(); $ins->close();
            }
        }
        header("Location: consultations.php?msg=Medical record updated."); exit;
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($con = $conn->query("SELECT FollowUpDate, PetID FROM consultations WHERE ConsultationID=$id")->fetch_assoc()) {
        if ($con['FollowUpDate'] && $conn->query("SELECT AppointmentID FROM appointments WHERE PetID={$con['PetID']} AND AppointmentDate='{$con['FollowUpDate']}' AND Status='Scheduled' AND IsDeleted=0")->num_rows) {
            header("Location: consultations.php?err=Cannot remove: cancel the scheduled follow-up appointment on " . date('M d, Y', strtotime($con['FollowUpDate'])) . " first."); exit;
        }
    }
    $conn->query("UPDATE consultations SET IsDeleted=1 WHERE ConsultationID=$id");
    header("Location: consultations.php?msg=Record removed."); exit;
}

include('header.php');

// Fetch database records efficiently using native multi-dimensional array mapping calls
$petsList = $conn->query("SELECT PetID, PetName, ClientID FROM pets WHERE IsActive=1 ORDER BY PetName")->fetch_all(MYSQLI_ASSOC);
$clientsList = array_column($conn->query("SELECT ClientID, CONCAT(FirstName,' ',LastName) AS Name FROM clients WHERE IsActive=1")->fetch_all(MYSQLI_ASSOC), 'Name', 'ClientID');
$apptList = $conn->query("SELECT a.AppointmentID, p.PetName, a.AppointmentDate FROM appointments a JOIN pets p ON a.PetID=p.PetID WHERE a.Status NOT IN ('Cancelled','No-Show','Completed') AND a.IsDeleted=0 ORDER BY a.AppointmentDate DESC")->fetch_all(MYSQLI_ASSOC);
$servicesList = $conn->query("SELECT ServiceID, ServiceName FROM services WHERE IsActive=1 ORDER BY ServiceName ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Consultations & History</h1>
        <p>Manage checkups and historical medical trends.</p>
    </div>
    <div class="page-header-actions">
        <button class="btn-main btn-teal" data-bs-toggle="modal" data-bs-target="#consultModal" onclick="openAdd()"><i class="bi bi-plus-lg"></i> New Record</button>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?><div class="alert-modern alert-success-modern"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
<?php if (isset($_GET['err'])): ?>
<div class="alert-modern alert-danger-modern" style="background-color:#fff5f5; color:#e53e3e; border:1px solid #fed7d7; padding:1rem; border-radius:6px; margin-bottom:1.5rem;"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($_GET['err']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><span class="card-header-title"><i class="bi bi-clipboard2-pulse-fill me-2" style="color:#0ea5e9;"></i>Historical Records Log</span></div>
    <div class="card-body p-0">
        <table class="modern-table">
            <thead><tr><th>Date</th><th>Patient / Owner</th><th>Attending Vet</th><th>Diagnosis Overview</th><th>Follow-Up</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $highlightID = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
            $qStr = "SELECT con.*, p.PetName FROM consultations con JOIN pets p ON con.PetID=p.PetID WHERE con.IsDeleted=0 " . (isset($_GET['view']) ? " AND con.ConsultationID=".intval($_GET['view']) : " ORDER BY con.ConsultationDate DESC, con.ConsultationID DESC");
            $res = $conn->query($qStr);
            if ($res->num_rows == 0) echo '<tr><td colspan="6" class="text-center p-4 text-muted">No clinical history matched on file.</td></tr>';
            
            while ($row = $res->fetch_assoc()):
                $ownerName = $clientsList[$row['ClientID']] ?? '—';
                $apptTimeStr = "—"; $linkedServiceId = ""; $tRes = null;
                
                if ($row['FollowUpDate']) {
                    $tRes = $conn->query("SELECT AppointmentTime, ServiceID, Status FROM appointments WHERE PetID={$row['PetID']} AND AppointmentDate='{$row['FollowUpDate']}' AND IsDeleted=0 LIMIT 1")->fetch_assoc();
                    if ($tRes) {
                        $apptTimeStr = date('h:i A', strtotime($tRes['AppointmentTime']));
                        $linkedServiceId = $tRes['ServiceID'] ?? '';
                    }
                }
                $fuStatus = $tRes['Status'] ?? null;
                $row['OwnerName'] = $ownerName;
                $row['FollowUpTime'] = ($tRes && isset($tRes['AppointmentTime'])) ? date('H:i', strtotime($tRes['AppointmentTime'])) : '';
                $row['FollowUpServiceID'] = ($tRes && isset($tRes['ServiceID'])) ? $tRes['ServiceID'] : '';
                $row['FollowUpStatus'] = $fuStatus ?? '';
            ?>
            <tr id="row-con-<?= $row['ConsultationID'] ?>" class="<?= ($highlightID && $highlightID==$row['ConsultationID']) ? 'search-highlight-row' : '' ?>">
                <td style="font-weight:600;"><?= date('M d, Y', strtotime($row['ConsultationDate'])) ?></td>
                <td>
                    <div style="font-weight:700; color:var(--dark);"><?= htmlspecialchars($row['PetName']) ?></div>
                    <div style="font-size:11px; color:var(--muted);">Owner: <?= htmlspecialchars($ownerName) ?></div>
                </td>
                <td>Dr. <?= htmlspecialchars($row['VetName']) ?></td>
                <td><div style="font-weight:600; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($row['Diagnosis']) ?></div></td>
                <td><?php
                    if ($row['FollowUpDate']) {
                        $fuBadgeMap = [
                            'Scheduled' => ['badge-scheduled',   'bi-bell-fill'],
                            'Completed' => ['badge-completed',   'bi-check-circle-fill'],
                            'Cancelled' => ['badge-cancelled',   'bi-x-circle-fill'],
                            'No-Show'   => ['badge-noshow',      'bi-dash-circle-fill'],
                            'Pending'   => ['badge-pending',     'bi-clock-fill'],
                        ];
                        [$fuClass, $fuIcon] = $fuBadgeMap[$fuStatus] ?? ['badge-pending', 'bi-clock-fill'];
                        echo '<span class="badge-modern '.$fuClass.'"><i class="bi '.$fuIcon.'"></i> '.date('M d', strtotime($row['FollowUpDate'])).' at '.$apptTimeStr.'</span>';
                    } else {
                        echo '<span style="color:var(--light-muted);">None</span>';
                    }
                ?></td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="btn-icon" style="background:rgba(0,128,128,0.08);color:var(--teal);" onclick='viewRecord(<?= json_encode($row, JSON_HEX_APOS) ?>)'><i class="bi bi-eye"></i></button>
                        <button class="btn-icon btn-icon-edit" onclick='openEdit(<?= json_encode($row, JSON_HEX_APOS) ?>)'><i class="bi bi-pencil"></i></button>
                        <a href="consultations.php?delete=<?= $row['ConsultationID'] ?>" class="btn-icon btn-icon-delete" onclick="return confirm('Permanently remove record?')"><i class="bi bi-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="consultModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">New Examination Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="consultationForm">
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="ConsultationID" id="consultID">
        <div class="modal-body">
            <div id="modalWarningBanner" class="alert alert-danger d-none" style="font-size:13px; font-weight:600;"><i class="bi bi-exclamation-triangle-fill me-2"></i> Warning: Selected follow-up time slot is already booked!</div>
            
            <!-- Pet / Owner / Appointment row — shown for both Add and Edit -->
            <div class="row g-3 mb-3">
                <div class="col-md-4" id="addPetField">
                    <label class="form-label">Patient / Pet</label>
                    <select name="PetID" id="fPetID" class="form-select" onchange="const opt = this.options[this.selectedIndex]; document.getElementById('fClientID').value = opt.dataset.client || ''; document.getElementById('fOwnerName').value = opt.dataset.owner || '';">
                        <option value="">— Select Pet —</option>
                        <?php foreach($petsList as $p): ?>
                        <option value="<?= $p['PetID'] ?>" data-client="<?= $p['ClientID'] ?>" data-owner="<?= htmlspecialchars($clientsList[$p['ClientID']] ?? 'Unknown') ?>"><?= htmlspecialchars($p['PetName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4" id="addOwnerField">
                    <label class="form-label">Pet Owner</label>
                    <input type="hidden" name="ClientID" id="fClientID">
                    <input type="text" id="fOwnerName" class="form-control" readonly placeholder="Auto-populated owner">
                </div>
                <div class="col-md-4" id="addApptField">
                    <label class="form-label">Linked Appointment</label>
                    <select name="AppointmentID" id="fApptID" class="form-select">
                        <option value="">— Independent / Walk-In —</option>
                        <?php foreach($apptList as $a): ?><option value="<?= $a['AppointmentID'] ?>"><?= htmlspecialchars($a['PetName'].' ('.date('M d', strtotime($a['AppointmentDate'])).')') ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label">Checkup Date</label><input type="date" name="ConsultationDate" id="fDate" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Attending Veterinarian</label><input type="text" name="VetName" id="fVet" class="form-control" placeholder="Dr. Name" required></div>
            </div>

            <div class="col-12 my-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="EnableFollowUp" id="fEnableFollowUp" onchange="toggleFollowUpFields(this.checked)">
                    <label class="form-check-label fw-bold text-dark" for="fEnableFollowUp" style="cursor: pointer;">Schedule a follow-up appointment?</label>
                </div>
            </div>

            <div id="followUpFieldsSection" class="row g-3 mb-3" style="display: none;">
                <div class="col-md-4"><label class="form-label">Follow-up Date</label><input type="date" name="FollowUpDate" id="fFollowUp" class="form-control"></div>
                <div class="col-md-4">
                    <label class="form-label">Follow-up Time</label>
                    <select name="FollowUpTime" id="fFollowUpTime" class="form-select">
                        <option value="">— Select Time —</option>
                        <?php foreach($timeSlots as $slot): ?><option value="<?= $slot ?>"><?= date('h:i A', strtotime($slot)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Requested Service</label>
                    <select name="FollowUpServiceID" id="fFollowUpServiceID" class="form-select">
                        <option value="">— Select Service —</option>
                        <?php foreach($servicesList as $s): ?><option value="<?= $s['ServiceID'] ?>"><?= htmlspecialchars($s['ServiceName']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3"><label class="form-label">Chief Complaint / Vital Symptoms</label><textarea name="ChiefComplaint" id="fCC" class="form-control" rows="2" required></textarea></div>
            <div class="mb-3"><label class="form-label">Clinical Diagnosis</label><textarea name="Diagnosis" id="fDiag" class="form-control" rows="2" required></textarea></div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label">Administered Procedures</label><textarea name="Treatment" id="fTreatment" class="form-control" rows="2"></textarea></div>
                <div class="col-md-6"><label class="form-label">Prescribed Medications</label><textarea name="Prescription" id="fPresc" class="form-control" rows="2"></textarea></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-main btn-teal" id="submitBtn">Save Record</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title" style="font-weight:700;"><i class="bi bi-file-earmark-medical me-2 text-teal"></i>Medical Examination Case File</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
          <div class="row g-3 mb-4 p-3 rounded" style="background-color: #f8f9fa; border-left: 4px solid var(--teal);">
              <div class="col-md-4"><span class="text-muted d-block small" style="font-size:11px; font-weight:600;">PATIENT / PET</span><strong id="vPetName" style="font-size:16px; color:var(--dark);">—</strong></div>
              <div class="col-md-4"><span class="text-muted d-block small" style="font-size:11px; font-weight:600;">PET OWNER</span><strong id="vOwnerName">—</strong></div>
              <div class="col-md-4"><span class="text-muted d-block small" style="font-size:11px; font-weight:600;">ATTENDING VETERINARIAN</span><strong id="vVetName" style="color:var(--dark);">—</strong></div>
          </div>
          <div class="row g-3 mb-4">
              <div class="col-md-6"><span class="text-muted d-block small" style="font-size:11px; font-weight:600;">CHECKUP DATE</span><span id="vDate" class="badge bg-secondary text-white p-2 mt-1" style="font-size:13px;">—</span></div>
              <div class="col-md-6"><span class="text-muted d-block small" style="font-size:11px; font-weight:600;">FOLLOW-UP SCHEDULE</span><span id="vFollowUp" class="badge-modern badge-scheduled" style="font-size:12px; padding:6px 12px;">—</span></div>
          </div>
          <hr>
          <div class="mb-3"><h6 class="fw-bold text-dark"><i class="bi bi-heart-pulse me-2 text-danger"></i>Chief Complaint & Symptoms</h6><div id="vCC" class="p-3 rounded border bg-light" style="white-space: pre-wrap;">—</div></div>
          <div class="mb-3"><h6 class="fw-bold text-dark"><i class="bi bi-activity me-2 text-primary"></i>Clinical Diagnosis</h6><div id="vDiag" class="p-3 rounded border fw-bold bg-light" style="white-space: pre-wrap;">—</div></div>
          <div class="row g-3">
              <div class="col-md-6"><h6 class="fw-bold text-dark"><i class="bi bi-capsule me-2 text-success"></i>Treatment</h6><div id="vTreatment" class="p-3 rounded border bg-light" style="min-height:70px; white-space: pre-wrap;">—</div></div>
              <div class="col-md-6"><h6 class="fw-bold text-dark"><i class="bi bi-journal-medical me-2 text-warning"></i>Prescribed Medications</h6><div id="vPrescription" class="p-3 rounded border bg-light" style="min-height:70px; white-space: pre-wrap;">—</div></div>
          </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleFollowUpFields(isChecked) {
    const c = document.getElementById('followUpFieldsSection'), d = document.getElementById('fFollowUp'), t = document.getElementById('fFollowUpTime'), s = document.getElementById('fFollowUpServiceID');
    c.style.display = isChecked ? 'flex' : 'none';
    d.required = t.required = isChecked;
    if (!isChecked) { d.value = t.value = s.value = ''; }
}

async function refreshFollowUpSlots(date) {
    if (!date) return;
    const res = await fetch(`consultations.php?get_booked_slots=1&date=${date}`), booked = await res.json(), selectEl = document.getElementById('fFollowUpTime');
    for (let opt of selectEl.options) {
        if (!opt.value) continue;
        const taken = booked.includes(opt.value); opt.disabled = taken;
        let lbl = opt.value.replace(/(\d+):(\d+)/, (_, h, m) => {
            const d = new Date(); d.setHours(h, m); return d.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
        });
        opt.textContent = taken ? `${lbl} — Booked` : lbl;
    }
}

document.getElementById('fFollowUp').addEventListener('change', function() { refreshFollowUpSlots(this.value); });
document.getElementById('consultationForm').addEventListener('submit', function(e) {
    const ts = document.getElementById('fFollowUpTime'), opt = ts.options[ts.selectedIndex];
    if (document.getElementById('fEnableFollowUp').checked && opt?.disabled) {
        e.preventDefault();
        const banner = document.getElementById('modalWarningBanner'); banner.classList.remove('d-none');
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        alert("Warning: Selected follow-up time slot is already booked! Please pick an open time slot.");
    }
});

function viewRecord(row) {
    document.getElementById('vPetName').textContent = row.PetName || '—';
    document.getElementById('vOwnerName').textContent = row.OwnerName || '—';
    document.getElementById('vVetName').textContent = 'Dr. ' + (row.VetName || '—');
    document.getElementById('vDate').textContent = row.ConsultationDate ? new Date(row.ConsultationDate).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '—';
    const fuEl = document.getElementById('vFollowUp');
    const fuStatusMap = {
        'Scheduled': ['badge-scheduled', 'bi-bell-fill'],
        'Completed': ['badge-completed', 'bi-check-circle-fill'],
        'Cancelled': ['badge-cancelled', 'bi-x-circle-fill'],
        'No-Show':   ['badge-noshow',    'bi-dash-circle-fill'],
        'Pending':   ['badge-pending',   'bi-clock-fill'],
    };
    if (row.FollowUpDate) {
        const fuDate = new Date(row.FollowUpDate).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
        const fuTime = row.FollowUpTime ? ' at ' + (document.querySelector(`#fFollowUpTime option[value="${row.FollowUpTime}"]`)?.textContent || row.FollowUpTime) : '';
        const [fuClass, fuIcon] = fuStatusMap[row.FollowUpStatus] || ['badge-pending', 'bi-clock-fill'];
        fuEl.className = 'badge-modern ' + fuClass;
        fuEl.innerHTML = `<i class="bi ${fuIcon}"></i> ${fuDate}${fuTime}`;
    } else {
        fuEl.className = 'badge-modern badge-pending';
        fuEl.innerHTML = 'None Scheduled';
    }
    document.getElementById('vCC').textContent = row.ChiefComplaint || '—';
    document.getElementById('vDiag').textContent = row.Diagnosis || '—';
    document.getElementById('vTreatment').textContent = row.Treatment || 'None documented.';
    document.getElementById('vPrescription').textContent = row.Prescription || 'No medications prescribed.';
    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

// Highlight handled by universal header.php script

function openAdd() {
    document.getElementById('modalTitle').textContent = 'New Examination Record';
    document.getElementById('formAction').value = 'add';
    document.getElementById('submitBtn').textContent = 'Save Record';
    document.getElementById('modalWarningBanner').classList.add('d-none');
    document.getElementById('fEnableFollowUp').checked = false; toggleFollowUpFields(false);
    ['addPetField','addOwnerField','addApptField'].forEach(id => document.getElementById(id).style.display = '');
    ['consultID','fPetID','fClientID','fOwnerName','fApptID','fVet','fCC','fDiag','fTreatment','fPresc'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('fDate').value = '<?= date('Y-m-d') ?>';
}

function openEdit(row) {
    document.getElementById('modalTitle').textContent = 'Edit Medical Record';
    document.getElementById('formAction').value = 'update';
    document.getElementById('submitBtn').textContent = 'Update Record';
    document.getElementById('modalWarningBanner').classList.add('d-none');

    // Show pet/owner/appointment fields so user can correct a wrong pet
    ['addPetField','addOwnerField','addApptField'].forEach(id => document.getElementById(id).style.display = '');

    // Pre-fill pet and owner
    document.getElementById('consultID').value  = row.ConsultationID;
    document.getElementById('fPetID').value     = row.PetID;
    document.getElementById('fClientID').value  = row.ClientID;
    document.getElementById('fOwnerName').value = row.OwnerName || '';

    // Pre-fill linked appointment if any
    document.getElementById('fApptID').value    = row.AppointmentID || '';

    document.getElementById('fDate').value      = row.ConsultationDate;
    document.getElementById('fVet').value       = row.VetName;
    document.getElementById('fCC').value        = row.ChiefComplaint;
    document.getElementById('fDiag').value      = row.Diagnosis;
    document.getElementById('fTreatment').value = row.Treatment;
    document.getElementById('fPresc').value     = row.Prescription;
    
    if (row.FollowUpDate) {
        document.getElementById('fEnableFollowUp').checked = true; toggleFollowUpFields(true);
        document.getElementById('fFollowUp').value = row.FollowUpDate;
        refreshFollowUpSlots(row.FollowUpDate).then(() => {
            document.getElementById('fFollowUpTime').value      = row.FollowUpTime || '';
            document.getElementById('fFollowUpServiceID').value = row.FollowUpServiceID || '';
        });
    } else {
        document.getElementById('fEnableFollowUp').checked = false; toggleFollowUpFields(false);
    }

    // Show modal and scroll to top so pet dropdown is visible immediately
    const modal = new bootstrap.Modal(document.getElementById('consultModal'));
    modal.show();
    document.getElementById('consultModal').addEventListener('shown.bs.modal', function () {
        this.querySelector('.modal-body').scrollTop = 0;
    }, { once: true });
}
</script>
<?php include('footer.php'); ?>