<?php
include('dbconnect.php');

$statusOptions = ['Pending', 'Scheduled', 'Completed', 'Cancelled', 'No-Show'];
$timeSlots = [];
for ($t = strtotime('09:30'); $t <= strtotime('18:30'); $t += 1800)
    $timeSlots[] = date('H:i', $t);

/* POST: ADD / UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $petID     = intval($_POST['PetID']);
    $clientID  = intval($_POST['ClientID']);
    $serviceID = $_POST['ServiceID'] ? intval($_POST['ServiceID']) : null;
    $date      = $_POST['AppointmentDate'] ?: ($_POST['_dateFallback'] ?? '');
    $time      = $_POST['AppointmentTime'] ?: ($_POST['_timeFallback'] ?? '');
    $reason    = $_POST['Reason'];
    $status    = $_POST['Status'] ?: 'Pending';

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
        $stmt = $conn->prepare("INSERT INTO appointments (PetID,ClientID,ServiceID,AppointmentDate,AppointmentTime,Reason,Status) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("iiissss", $petID, $clientID, $serviceID, $date, $time, $reason, $status);
        $stmt->execute();
        $stmt->close();
        header("Location: appointments.php?msg=Appointment added.");
        exit;
    }

    if ($action === 'update') {
        $stmt = $conn->prepare("UPDATE appointments SET PetID=?,ClientID=?,ServiceID=?,AppointmentDate=?,AppointmentTime=?,Reason=?,Status=? WHERE AppointmentID=?");
        $stmt->bind_param("iiissssi", $petID, $clientID, $serviceID, $date, $time, $reason, $status, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: appointments.php?msg=Appointment updated.");
        exit;
    }
}

if (isset($_GET['booked_slots'])) {
    $date = $conn->real_escape_string($_GET['date'] ?? '');
    $excludeID = intval($_GET['exclude'] ?? 0);
    $rows = $conn->query("SELECT AppointmentTime FROM appointments WHERE AppointmentDate='$date' AND COALESCE(IsDeleted,0)=0 AND AppointmentID!=$excludeID AND Status NOT IN ('Cancelled','No-Show')")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(array_column($rows, 'AppointmentTime'));
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
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Appointments</h1>
        <p>Manage clinic appointments</p>
    </div>
    <div class="page-header-actions">
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
            ['Total',     $total,     'bi-calendar2',      '#ffffb2', '#5d6405'], // [Label, Val, Icon, BgColor, TextColor]
            ['Scheduled', $scheduled, 'bi-calendar-check',  '#e0e7ff', '#6366f1'],
            ['Completed', $completed, 'bi-check-circle-fill', '#f0fdf4', '#10b981'],
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
                    <th>#</th>
                    <th>Date/Time</th>
                    <th>Pet</th>
                    <th>Owner</th>
                    <th>Service</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $res = $conn->query("SELECT a.*, p.PetName, CONCAT(c.FirstName,' ',c.LastName) AS Owner, s.ServiceName FROM appointments a JOIN pets p ON a.PetID=p.PetID JOIN clients c ON a.ClientID=c.ClientID LEFT JOIN services s ON a.ServiceID=s.ServiceID WHERE a.IsDeleted=0 ORDER BY a.AppointmentDate DESC");
                if ($res->num_rows == 0) echo '<tr class="empty-row"><td colspan="8">No appointments found.</td></tr>';

                $highlightApptID = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
                $badgeMap = ['Scheduled' => 'badge-scheduled', 'Completed' => 'badge-completed', 'Cancelled' => 'badge-cancelled', 'No-Show' => 'badge-noshow'];
                while ($row = $res->fetch_assoc()):
                    $status = $row['Status'] ?: 'Pending';
                ?>
                    <tr id="row-appt-<?= $row['AppointmentID'] ?>" class="<?= ($highlightApptID && $highlightApptID == $row['AppointmentID']) ? 'search-highlight-row' : '' ?>">
                        <td>#<?= $row['AppointmentID'] ?></td>
                        <td><?= date('M d, Y', strtotime($row['AppointmentDate'])) ?><br><?= date('h:i A', strtotime($row['AppointmentTime'])) ?></td>
                        <td><?= htmlspecialchars($row['PetName']) ?></td>
                        <td><?= htmlspecialchars($row['Owner']) ?></td>
                        <td><?= $row['ServiceName'] ?: '—' ?></td>
                        <td><?= htmlspecialchars($row['Reason']) ?></td>
                        <td><span class="badge-modern <?= $badgeMap[$status] ?? 'badge-pending' ?>"><?= $status ?></span></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn-icon btn-icon-view" onclick='viewAppt(<?= json_encode($row) ?>)' title="View Details"><i class="bi bi-eye"></i></button>
                                <button class="btn-icon btn-icon-edit" onclick='openEdit(<?= json_encode($row) ?>)'><i class="bi bi-pencil"></i></button>
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
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar2-check-fill me-2" style="color:var(--teal);"></i>Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 p-3 rounded mb-3" style="background:#f8fafc; border-left:4px solid var(--teal);">
                    <div class="col-6">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase;">Appointment #</div>
                        <div style="font-weight:700; font-size:15px; color:var(--teal);" id="vApptID">—</div>
                    </div>
                    <div class="col-6">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase;">Status</div>
                        <div id="vApptStatus">—</div>
                    </div>
                    <div class="col-6">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase;">Date</div>
                        <div style="font-weight:600; font-size:15px;" id="vApptDate">—</div>
                    </div>
                    <div class="col-6">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase;">Time</div>
                        <div style="font-weight:600; font-size:15px;" id="vApptTime">—</div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;"><i class="bi bi-heart-pulse me-1"></i>Pet</div>
                        <div id="vApptPet" style="font-size:14px;">—</div>
                    </div>
                    <div class="col-6">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;"><i class="bi bi-person me-1"></i>Owner</div>
                        <div id="vApptOwner" style="font-size:14px;">—</div>
                    </div>
                    <div class="col-12">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;"><i class="bi bi-scissors me-1"></i>Service</div>
                        <div id="vApptService" style="font-size:14px;">—</div>
                    </div>
                    <div class="col-12">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;"><i class="bi bi-chat-left-text me-1"></i>Reason</div>
                        <div id="vApptReason" class="p-3 rounded" style="background:#f1f5f9; font-size:13px;">—</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="apptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="appointmentMainForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="AppointmentID" id="apptID">
                <input type="hidden" id="fDateFallback" name="_dateFallback">
                <input type="hidden" id="fTimeFallback" name="_timeFallback">

                <div class="modal-body">
                    <div id="apptWarningBanner" class="alert alert-danger d-none" style="font-size:13px; font-weight:600;">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Warning: The selected schedule slot is booked or unavailable!
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Pet</label>
                            <select name="PetID" id="fPetID" class="form-select" required onchange="document.getElementById('fClientID').value = this.options[this.selectedIndex].dataset.client;">
                                <option value="">— Select Pet —</option>
                                <?php foreach ($conn->query("SELECT p.PetID, p.PetName, p.ClientID, CONCAT(c.FirstName,' ',c.LastName) AS Owner FROM pets p JOIN clients c ON p.ClientID=c.ClientID WHERE p.IsActive=1")->fetch_all(MYSQLI_ASSOC) as $p): ?>
                                    <option value="<?= $p['PetID'] ?>" data-client="<?= $p['ClientID'] ?>"><?= htmlspecialchars($p['PetName']) ?> (<?= htmlspecialchars($p['Owner']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Owner</label>
                            <select name="ClientID" id="fClientID" class="form-select" required>
                                <option value="">— Select Owner —</option>
                                <?php foreach ($conn->query("SELECT ClientID, CONCAT(FirstName,' ',LastName) AS Name FROM clients WHERE IsActive=1")->fetch_all(MYSQLI_ASSOC) as $c): ?>
                                    <option value="<?= $c['ClientID'] ?>"><?= htmlspecialchars($c['Name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12" id="changeDateToggleRow" style="display:none;">
                            <button type="button" class="btn-main btn-outline btn-sm" id="changeDateBtn" onclick="toggleDateChange()">
                                <i class="bi bi-calendar-event me-1"></i> Select New Date &amp; Time
                            </button>
                        </div>
                        <div class="col-md-6" id="dateFieldWrap">
                            <label class="form-label">Appointment Date</label>
                            <input type="date" name="AppointmentDate" id="fDate" class="form-control" required>
                        </div>
                        <div class="col-md-6" id="timeFieldWrap">
                            <label class="form-label">Time</label>
                            <select name="AppointmentTime" id="fTime" class="form-select" required>
                                <option value="">— Select Time —</option>
                                <?php foreach ($timeSlots as $slot): ?>
                                    <option value="<?= $slot ?>"><?= date('h:i A', strtotime($slot)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="Status" id="fStatus" class="form-select">
                                <?php foreach ($statusOptions as $s): ?>
                                    <option value="<?= $s ?>"><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reason</label>
                            <textarea name="Reason" id="fReason" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-main btn-teal" id="submitBtn">Save Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let dateChangeOpen = false;
    let currentApptID = 0;

    async function refreshTimeSlots(date) {
        if (!date) return;
        const res = await fetch(`appointments.php?booked_slots=1&date=${date}&exclude=${currentApptID}`);
        const booked = await res.json();
        const sel = document.getElementById('fTime');
        for (let opt of sel.options) {
            if (!opt.value) continue;
            const taken = booked.includes(opt.value);
            opt.disabled = taken;

            let timeLabel = opt.value.replace(/(\d+):(\d+)/, (_, h, m) => {
                const d = new Date();
                d.setHours(h, m);
                return d.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            });

            opt.textContent = taken ? `${timeLabel} — Booked` : timeLabel;
        }
    }
    document.getElementById('fDate').addEventListener('change', function() {
        refreshTimeSlots(this.value);
    });

    document.getElementById('appointmentMainForm').addEventListener('submit', function(e) {
        const isUpdate = document.getElementById('formAction').value === 'update';
        const timeSel = document.getElementById('fTime');
        const selectedOpt = timeSel.options[timeSel.selectedIndex];

        if ((!isUpdate || dateChangeOpen) && selectedOpt && selectedOpt.disabled) {
            e.preventDefault();
            const banner = document.getElementById('apptWarningBanner');
            banner.classList.remove('d-none');
            banner.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
            alert("Warning: The selected time slot is already booked! Please pick an available time slot before saving.");
        }
    });

    function showDateFields(show) {
        document.getElementById('dateFieldWrap').style.display = document.getElementById('timeFieldWrap').style.display = show ? '' : 'none';
        document.getElementById('fDate').required = document.getElementById('fTime').required = show;
    }

    function toggleDateChange() {
        dateChangeOpen = !dateChangeOpen;
        showDateFields(dateChangeOpen);
        const btn = document.getElementById('changeDateBtn');
        if (dateChangeOpen) {
            btn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Cancel Change';
            btn.classList.replace('btn-outline', 'btn-danger-outline');
        } else {
            btn.innerHTML = '<i class="bi bi-calendar-event me-1"></i> Select New Date &amp; Time';
            btn.classList.replace('btn-danger-outline', 'btn-outline');
            document.getElementById('fDate').value = document.getElementById('fTime').value = '';
        }
    }

    function viewAppt(r) {
        const badgeMap = {
            'Scheduled': 'badge-scheduled',
            'Completed': 'badge-completed',
            'Cancelled': 'badge-cancelled',
            'No-Show': 'badge-noshow',
            'Pending': 'badge-pending'
        };
        const status = r.Status || 'Pending';
        document.getElementById('vApptID').textContent = '#' + r.AppointmentID;
        document.getElementById('vApptStatus').innerHTML = `<span class="badge-modern ${badgeMap[status] || 'badge-pending'}">${status}</span>`;
        document.getElementById('vApptDate').textContent = r.AppointmentDate ?
            new Date(r.AppointmentDate + 'T00:00:00').toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: '2-digit'
            }) :
            '—';
        document.getElementById('vApptTime').textContent = r.AppointmentTime ?
            new Date('1970-01-01T' + r.AppointmentTime).toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            }) :
            '—';
        document.getElementById('vApptPet').textContent = r.PetName || '—';
        document.getElementById('vApptOwner').textContent = r.Owner || '—';
        document.getElementById('vApptService').textContent = r.ServiceName || '—';
        document.getElementById('vApptReason').textContent = r.Reason || '—';
        new bootstrap.Modal(document.getElementById('viewApptModal')).show();
    }

    function openAdd() {
        currentApptID = 0;
        document.getElementById('modalTitle').textContent = 'New Appointment';
        document.getElementById('formAction').value = 'add';
        document.getElementById('submitBtn').textContent = 'Save Appointment';
        document.getElementById('apptWarningBanner').classList.add('d-none');
        document.querySelector('#apptModal form').reset();
        document.getElementById('changeDateToggleRow').style.display = 'none';
        showDateFields(true);
    }

    function openEdit(row) {
        currentApptID = row.AppointmentID;
        document.getElementById('modalTitle').textContent = 'Edit Appointment #' + row.AppointmentID;
        document.getElementById('formAction').value = 'update';
        document.getElementById('submitBtn').textContent = 'Update Appointment';
        document.getElementById('apptWarningBanner').classList.add('d-none');
        document.getElementById('apptID').value = row.AppointmentID;
        document.getElementById('fPetID').value = row.PetID;
        document.getElementById('fClientID').value = row.ClientID;
        document.getElementById('fReason').value = row.Reason;
        document.getElementById('fDateFallback').value = row.AppointmentDate;
        document.getElementById('fTimeFallback').value = row.AppointmentTime;
        document.getElementById('fStatus').value = row.Status || 'Pending';

        document.getElementById('changeDateToggleRow').style.display = '';
        dateChangeOpen = false;
        showDateFields(false);

        document.getElementById('fDate').value = row.AppointmentDate;
        refreshTimeSlots(row.AppointmentDate).then(() => {
            document.getElementById('fTime').value = row.AppointmentTime;
        });

        const btn = document.getElementById('changeDateBtn');
        btn.innerHTML = '<i class="bi bi-calendar-event me-1"></i> Select New Date &amp; Time';
        btn.className = btn.className.replace('btn-danger-outline', 'btn-outline');
        new bootstrap.Modal(document.getElementById('apptModal')).show();
    }
</script>
<?php include('footer.php'); ?>