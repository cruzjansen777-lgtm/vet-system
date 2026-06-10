<?php
require_once 'guard.php';
include('dbconnect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_service') {
        $s = $conn->prepare("INSERT INTO services (ServiceName,Category,Price,Duration,Description) VALUES (?,?,?,?,?)");
        $s->bind_param("ssdis", $_POST['ServiceName'], $_POST['Category'], $_POST['Price'], $_POST['Duration'], $_POST['Description']);
        $s->execute(); $s->close();
        header("Location: services.php?msg=Service added."); exit;
    }

    if ($action === 'update_service') {
        $id           = intval($_POST['ServiceID']);
        $p            = floatval($_POST['Price']);
        $d            = intval($_POST['Duration']);
        $a            = intval($_POST['IsActive']);
        $updateReason = trim($_POST['UpdateReason'] ?? '');
        $s  = $conn->prepare("UPDATE services SET ServiceName=?,Category=?,Price=?,Duration=?,Description=?,IsActive=?,UpdateReason=? WHERE ServiceID=?");
        $s->bind_param("ssdisisi", $_POST['ServiceName'], $_POST['Category'], $p, $d, $_POST['Description'], $a, $updateReason, $id);
        $s->execute(); $s->close();
        header("Location: services.php?msg=Service updated."); exit;
    }

    if ($action === 'add_lodging') {
        $cage = trim($_POST['CageNumber']);
        if ($cage) {
            $occupied = $conn->query("SELECT LodgingID FROM lodging WHERE CageNumber='$cage' AND Status='Active' AND IsDeleted=0")->num_rows;
            if ($occupied) {
                header("Location: services.php?tab=lodging&err=Cage $cage is currently occupied. Please select an available cage.");
                exit;
            }
        }
        $s = $conn->prepare("INSERT INTO lodging (PetID,ClientID,CheckInDate,CageNumber,DailyRate,SpecialInstructions) VALUES (?,?,?,?,?,?)");
        $s->bind_param("iissds", $_POST['PetID'], $_POST['ClientID'], $_POST['CheckInDate'], $cage, $_POST['DailyRate'], $_POST['SpecialInstructions']);
        $s->execute(); $s->close();
        header("Location: services.php?tab=lodging&msg=Pet checked in."); exit;
    }

    if ($action === 'update_lodging') {
        $lid          = intval($_POST['LodgingID']);
        $cage         = trim($_POST['CageNumber']);
        $cout         = $_POST['CheckOutDate'] ?: null;
        $rate         = floatval($_POST['DailyRate']);
        $newStatus    = $_POST['Status'];
        $updateReason = trim($_POST['UpdateReason'] ?? '');
        // Only block if cage is taken by a DIFFERENT active record
        if ($cage && $newStatus === 'Active') {
            $occupied = $conn->query("SELECT LodgingID FROM lodging WHERE CageNumber='$cage' AND Status='Active' AND IsDeleted=0 AND LodgingID!=$lid")->num_rows;
            if ($occupied) {
                header("Location: services.php?tab=lodging&err=Cage $cage is currently occupied. Please select an available cage.");
                exit;
            }
        }
        $s    = $conn->prepare("UPDATE lodging SET PetID=?,ClientID=?,CheckInDate=?,CheckOutDate=?,CageNumber=?,DailyRate=?,SpecialInstructions=?,Status=?,UpdateReason=? WHERE LodgingID=?");
        $s->bind_param("iisssdsssi", $_POST['PetID'], $_POST['ClientID'], $_POST['CheckInDate'], $cout, $cage, $rate, $_POST['SpecialInstructions'], $newStatus, $updateReason, $lid);
        $s->execute(); $s->close();
        header("Location: services.php?tab=lodging&msg=Lodging record updated."); exit;
    }
}

if (isset($_GET['delete_service'])) {
    $id       = intval($_GET['delete_service']);
    $isActive = $conn->query("SELECT IsActive FROM services WHERE ServiceID=$id")->fetch_assoc()['IsActive'];
    if ($isActive) {
        header("Location: services.php?err=Service must be set to Inactive before it can be removed."); exit;
    }
    $conn->query("UPDATE services SET IsDeleted=1 WHERE ServiceID=$id");
    header("Location: services.php?msg=Service removed from the list."); exit;
}

if (isset($_GET['delete_lodging'])) {
    $id     = intval($_GET['delete_lodging']);
    $status = $conn->query("SELECT Status FROM lodging WHERE LodgingID=$id")->fetch_assoc()['Status'];
    if ($status === 'Active') {
        header("Location: services.php?tab=lodging&err=Pet must be checked out first before removing the record."); exit;
    }
    $conn->query("UPDATE lodging SET IsDeleted=1 WHERE LodgingID=$id");
    header("Location: services.php?tab=lodging&msg=Lodging record removed."); exit;
}

if (isset($_GET['checkout'])) {
    $today = date('Y-m-d');
    $conn->query("UPDATE lodging SET CheckOutDate='$today',Status='Checked Out' WHERE LodgingID=" . intval($_GET['checkout']));
    header("Location: services.php?tab=lodging&msg=Pet checked out on $today."); exit;
}

// ── AJAX: pet lodging history ──
if (isset($_GET['pet_lodging_history'])) {
    $petID = intval($_GET['pet_lodging_history']);
    $rows  = $conn->query("
        SELECT l.LodgingID, l.CheckInDate, l.CheckOutDate, l.CageNumber,
               l.DailyRate, l.Status, l.SpecialInstructions,
               CONCAT(c.FirstName,' ',c.LastName) AS Owner
        FROM lodging l
        JOIN clients c ON l.ClientID = c.ClientID
        WHERE l.PetID = $petID AND l.IsDeleted = 0
        ORDER BY l.CheckInDate DESC, l.LodgingID DESC
    ")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    exit(json_encode($rows));
}

// ── AJAX: occupied cages ──
if (isset($_GET['occupied_cages'])) {
    $excludeID = intval($_GET['exclude'] ?? 0);
    $rows = $conn->query("SELECT CageNumber FROM lodging WHERE Status='Active' AND IsDeleted=0 AND LodgingID!=$excludeID")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    exit(json_encode(array_column($rows, 'CageNumber')));
}

include('header.php');

$categories    = ['Consultation', 'Grooming', 'Surgery', 'Vaccination', 'Lodging', 'Laboratory', 'Other'];
$petsList      = $conn->query("SELECT p.PetID, p.PetName, p.ClientID, CONCAT(c.FirstName,' ',c.LastName) AS Owner FROM pets p JOIN clients c ON p.ClientID=c.ClientID WHERE p.IsActive=1")->fetch_all(MYSQLI_ASSOC);
$clientsList   = $conn->query("SELECT ClientID, CONCAT(FirstName,' ',LastName) AS Name FROM clients WHERE IsActive=1")->fetch_all(MYSQLI_ASSOC);
$activeTab     = $_GET['tab'] ?? 'services';
$totalSvcs     = $conn->query("SELECT COUNT(*) FROM services WHERE IsActive=1 AND IsDeleted=0")->fetch_row()[0];
$activeLodging = $conn->query("SELECT COUNT(*) FROM lodging WHERE Status='Active'")->fetch_row()[0];

$catColors = [
    'Consultation' => '#eff6ff:#2563eb',
    'Grooming'     => '#fdf2f8:#db2777',
    'Surgery'      => '#fef2f2:#dc2626',
    'Vaccination'  => '#f0fdf4:#16a34a',
    'Lodging'      => '#f5f3ff:#7c3aed',
    'Laboratory'   => '#fffbeb:#d97706',
    'Other'        => '#f1f5f9:#64748b',
];
function catStyle($cat, $colors) {
    $p = explode(':', $colors[$cat] ?? '#f1f5f9:#64748b');
    return "background:{$p[0]};color:{$p[1]};";
}

function vfield($label, $id, $extraClass = '', $col = 6) {
    echo "<div class='col-{$col}'><div class='vm-label'>{$label}</div><div id='{$id}' class='vm-value {$extraClass}'>—</div></div>";
}
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Services & Lodging</h1>
        <p><?= $totalSvcs ?> active services · <?= $activeLodging ?> pets boarding</p>
    </div>
    <div class="page-header-actions">
        <?php if ($activeTab === 'services'): ?>
        <button class="btn-main btn-teal" data-bs-toggle="modal" data-bs-target="#svcModal" onclick="openAddSvc()"><i class="bi bi-plus-lg"></i> Add Service</button>
        <?php else: ?>
        <button class="btn-main" style="background:#7c3aed;color:#fff;" data-bs-toggle="modal" data-bs-target="#lodgeModal"><i class="bi bi-house-heart-fill"></i> Check In Pet</button>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="alert-modern alert-success-modern"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
<div class="alert-modern alert-danger-modern"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($_GET['err']) ?></div>
<?php endif; ?>

<div class="nav-tabs-modern">
    <a class="nav-tab <?= $activeTab === 'services' ? 'active' : '' ?>" href="?tab=services"><i class="bi bi-box-seam-fill me-1"></i> Clinic Services</a>
    <a class="nav-tab <?= $activeTab === 'lodging'  ? 'active' : '' ?>" href="?tab=lodging"><i class="bi bi-house-heart-fill me-1"></i> Lodging & Boarding</a>
</div>

<?php if ($activeTab === 'services'): ?>
<div class="card">
    <div class="card-header">
        <span class="card-header-title"><i class="bi bi-box-seam-fill me-2" style="color:#f59e0b;"></i>Service Catalog</span>
        <span style="font-size:12px;color:var(--muted);"><?= $totalSvcs ?> active</span>
    </div>
    <div class="card-body p-0">
        <table class="modern-table">
            <thead><tr><th>Service #</th><th>Service Name</th><th>Category</th><th>Price</th><th>Duration</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $res = $conn->query("SELECT * FROM services WHERE IsDeleted=0 ORDER BY Category, ServiceName");
            $highlightSvcID = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
            if (!$res->num_rows) echo '<tr class="empty-row"><td colspan="8"><i class="bi bi-box-seam" style="font-size:28px;display:block;margin-bottom:10px;"></i>No services found. Add your first service!</td></tr>';
            while ($row = $res->fetch_assoc()):
                $json = htmlspecialchars(json_encode($row));
            ?>
            <tr id="row-svc-<?= $row['ServiceID'] ?>" class="<?= ($highlightSvcID && $highlightSvcID == $row['ServiceID']) ? 'search-highlight-row' : '' ?>">
                <td><div style="font-weight:700; font-size:13px; color:var(--teal);">SVC-<?= str_pad($row['ServiceID'], 4, '0', STR_PAD_LEFT) ?></div></td>
                <td style="font-weight:700;"><?= htmlspecialchars($row['ServiceName']) ?></td>
                <td><span class="cat-badge" style="<?= catStyle($row['Category'], $catColors) ?>"><?= $row['Category'] ?></span></td>
                <td style="font-weight:700;color:var(--green);">₱<?= number_format($row['Price'], 2) ?></td>
                <td style="color:var(--muted);"><?= $row['Duration'] ? $row['Duration'] . ' min' : '—' ?></td>
                <td style="font-size:12px;color:var(--muted);max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($row['Description']) ?></td>
                <td><span class="badge-modern <?= $row['IsActive'] ? 'badge-active' : 'badge-inactive' ?>"><?= $row['IsActive'] ? 'Active' : 'Inactive' ?></span></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button class="btn-icon btn-icon-view" onclick="viewSvc(<?= $json ?>)" title="View"><i class="bi bi-eye"></i></button>
                        <button class="btn-icon btn-icon-edit" onclick="openEditSvc(<?= $json ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                        <a href="services.php?delete_service=<?= $row['ServiceID'] ?>" class="btn-icon btn-icon-delete" title="Remove"
                           onclick="return confirm('Remove this service?')"><i class="bi bi-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="card-header">
        <span class="card-header-title"><i class="bi bi-house-heart-fill me-2" style="color:#7c3aed;"></i>Boarding Records</span>
        <span style="font-size:12px;color:var(--muted);"><?= $activeLodging ?> currently boarding</span>
    </div>
    <div class="card-body p-0">
        <table class="modern-table">
            <thead><tr><th>Lodging #</th><th>Pet</th><th>Owner</th><th>Check-In</th><th>Check-Out</th><th>Cage</th><th>Daily Rate</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $res = $conn->query("SELECT l.*, p.PetName, CONCAT(c.FirstName,' ',c.LastName) AS Owner FROM lodging l JOIN pets p ON l.PetID=p.PetID JOIN clients c ON l.ClientID=c.ClientID WHERE l.IsDeleted=0 ORDER BY l.CheckInDate DESC");
            if (!$res->num_rows) echo '<tr class="empty-row"><td colspan="9"><i class="bi bi-house-heart" style="font-size:28px;display:block;margin-bottom:10px;"></i>No lodging records found.</td></tr>';
            while ($row = $res->fetch_assoc()):
                $json       = htmlspecialchars(json_encode($row));
                $lodgeBadge = $row['Status'] === 'Active' ? 'badge-boarding' : ($row['Status'] === 'Checked Out' ? 'badge-inactive' : 'badge-cancelled');
            ?>
            <tr id="row-ldg-<?= $row['LodgingID'] ?>" class="<?= (isset($_GET['highlight']) && intval($_GET['highlight']) == $row['LodgingID']) ? 'search-highlight-row' : '' ?>">
                <td><div style="font-weight:700; font-size:13px; color:var(--teal);">LDG-<?= str_pad($row['LodgingID'], 4, '0', STR_PAD_LEFT) ?></div></td>
                <td style="font-weight:700;"><?= htmlspecialchars($row['PetName']) ?></td>
                <td style="color:var(--muted);"><?= htmlspecialchars($row['Owner']) ?></td>
                <td style="font-size:13px;"><?= date('M d, Y', strtotime($row['CheckInDate'])) ?></td>
                <td style="font-size:13px;"><?= $row['CheckOutDate'] ? date('M d, Y', strtotime($row['CheckOutDate'])) : '<span class="badge-modern badge-boarding">Still boarding</span>' ?></td>
                <td><span class="cat-badge"><?= htmlspecialchars($row['CageNumber'] ?: '—') ?></span></td>
                <td style="font-weight:600;color:var(--green);">₱<?= number_format($row['DailyRate'], 2) ?>/day</td>
                <td><span class="badge-modern <?= $lodgeBadge ?>"><?= $row['Status'] ?></span></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button class="btn-icon btn-icon-view" onclick="viewLodge(<?= $json ?>)" title="View"><i class="bi bi-eye"></i></button>
                        <button class="btn-icon" style="background:rgba(99,102,241,.1);color:#6366f1;" data-pet-id="<?= $row['PetID'] ?>" data-pet-name="<?= htmlspecialchars($row['PetName'], ENT_QUOTES) ?>" onclick="viewPetLodgingHistory(this.dataset.petId, this.dataset.petName)" title="Pet Lodging History"><i class="bi bi-clock-history"></i></button>
                        <button class="btn-icon btn-icon-edit" onclick="openEditLodge(<?= $json ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                        <?php if ($row['Status'] === 'Active'): ?>
                        <a href="services.php?checkout=<?= $row['LodgingID'] ?>" class="btn-icon btn-icon-green" title="Check Out"
                           onclick="return confirm('Check out <?= htmlspecialchars(addslashes($row['PetName'])) ?>?\nThis will set today as the check-out date.')"><i class="bi bi-box-arrow-right"></i></a>
                        <?php endif; ?>
                        <a href="services.php?delete_lodging=<?= $row['LodgingID'] ?>&tab=lodging" class="btn-icon btn-icon-delete" title="Remove"
                           onclick="return confirm('Remove this lodging record?')"><i class="bi bi-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- SERVICE MODAL (Add/Edit) -->
<div class="modal fade" id="svcModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="svcModalTitle">Add Service</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="post">
        <input type="hidden" name="action"    id="svcAction" value="add_service">
        <input type="hidden" name="ServiceID" id="svcID">
        <div class="modal-body"><div class="row g-3">
            <div class="col-12"><label class="form-label">Service Name <span style="color:#ef4444;">*</span></label><input type="text" name="ServiceName" id="fSvcName" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Category</label>
                <select name="Category" id="fCategory" class="form-select">
                    <?php foreach ($categories as $cat): ?><option value="<?= $cat ?>"><?= $cat ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Price (₱) <span style="color:#ef4444;">*</span></label><input type="number" name="Price" id="fPrice" class="form-control" step="0.01" required></div>
            <div class="col-md-3"><label class="form-label">Duration (mins)</label><input type="number" name="Duration" id="fDuration" class="form-control"></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="Description" id="fDesc" class="form-control" rows="2"></textarea></div>
            <div class="col-md-6" id="statusField" style="display:none;"><label class="form-label">Status</label>
                <select name="IsActive" id="fIsActive" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select>
            </div>
            <div class="col-md-8" id="svcUpdateReasonField" style="display:none;">
                <label class="form-label"><i class="bi bi-pencil-square me-1" style="color:var(--teal);"></i>Reason for Updating <span style="color:#ef4444;">*</span></label>
                <input type="text" name="UpdateReason" id="fSvcUpdateReason" class="form-control" placeholder="e.g. Price adjustment, description updated…">
            </div>
            <div class="col-md-4" id="svcUpdatedAtField" style="display:none;">
                <label class="form-label"><i class="bi bi-clock me-1" style="color:var(--muted);"></i>Date Updated</label>
                <input type="text" id="fSvcUpdatedAt" class="form-control" readonly style="background:#f8fafc; color:var(--muted); font-size:13px;">
            </div>
        </div></div>
        <div class="modal-footer">
            <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-main btn-teal" id="svcSubmitBtn">Save Service</button>
        </div>
    </form>
</div></div></div>

<!-- LODGING CHECK-IN MODAL -->
<div class="modal fade" id="lodgeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title">Check In Pet</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="post">
        <input type="hidden" name="action" value="add_lodging">
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Pet <span style="color:#ef4444;">*</span></label>
                <select name="PetID" class="form-select" required onchange="autoFillOwner(this,'lodgeOwner')">
                    <option value="">— Select Pet —</option>
                    <?php foreach ($petsList as $p): ?><option value="<?= $p['PetID'] ?>" data-client="<?= $p['ClientID'] ?>"><?= htmlspecialchars($p['PetName'] . ' (' . $p['Owner'] . ')') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Owner <span style="color:#ef4444;">*</span></label>
                <select name="ClientID" id="lodgeOwner" class="form-select" required>
                    <option value="">— Select Pet first —</option>
                    <?php foreach ($clientsList as $c): ?><option value="<?= $c['ClientID'] ?>"><?= htmlspecialchars($c['Name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Check-In Date <span style="color:#ef4444;">*</span></label><input type="date" name="CheckInDate" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-3">
                <label class="form-label">Cage # <span style="color:#ef4444;">*</span></label>
                <select name="CageNumber" id="addCageSelect" class="form-select" required>
                    <option value="">— Select Cage —</option>
                </select>
                <div id="addCageHint" style="font-size:11px;color:var(--muted);margin-top:4px;"></div>
            </div>
            <div class="col-md-3"><label class="form-label">Daily Rate (₱)</label><input type="number" name="DailyRate" class="form-control" step="0.01" value="450"></div>
            <div class="col-12"><label class="form-label">Special Instructions</label><textarea name="SpecialInstructions" class="form-control" rows="2" placeholder="Dietary needs, medication, allergies..."></textarea></div>
        </div></div>
        <div class="modal-footer">
            <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-main" style="background:#7c3aed;color:#fff;">Check In</button>
        </div>
    </form>
</div></div></div>

<!-- EDIT LODGING MODAL -->
<div class="modal fade" id="editLodgeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-house-heart-fill me-2" style="color:#7c3aed;"></i>Edit Lodging Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="post">
        <input type="hidden" name="action"    value="update_lodging">
        <input type="hidden" name="LodgingID" id="eLodgingID">
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Pet <span style="color:#ef4444;">*</span></label>
                <select name="PetID" id="eLodgePetID" class="form-select" required onchange="autoFillOwner(this,'eLodgeOwner')">
                    <option value="">— Select Pet —</option>
                    <?php foreach ($petsList as $p): ?><option value="<?= $p['PetID'] ?>" data-client="<?= $p['ClientID'] ?>"><?= htmlspecialchars($p['PetName'] . ' (' . $p['Owner'] . ')') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Owner <span style="color:#ef4444;">*</span></label>
                <select name="ClientID" id="eLodgeOwner" class="form-select" required>
                    <option value="">— Select Pet first —</option>
                    <?php foreach ($clientsList as $c): ?><option value="<?= $c['ClientID'] ?>"><?= htmlspecialchars($c['Name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Check-In Date <span style="color:#ef4444;">*</span></label><input type="date" name="CheckInDate" id="eLodgeCheckIn" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Check-Out Date</label><input type="date" name="CheckOutDate" id="eLodgeCheckOut" class="form-control"></div>
            <div class="col-md-4">
                <label class="form-label">Cage #</label>
                <select name="CageNumber" id="eLodgeCage" class="form-select">
                    <option value="">— Select Cage —</option>
                </select>
                <div id="editCageHint" style="font-size:11px;color:var(--muted);margin-top:4px;"></div>
            </div>
            <div class="col-md-4"><label class="form-label">Daily Rate (₱)</label><input type="number" name="DailyRate" id="eLodgeRate" class="form-control" step="0.01"></div>
            <div class="col-md-4"><label class="form-label">Status</label>
                <select name="Status" id="eLodgeStatus" class="form-select">
                    <option value="Active">Active</option>
                    <option value="Checked Out">Checked Out</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-12"><label class="form-label">Special Instructions</label><textarea name="SpecialInstructions" id="eLodgeInstructions" class="form-control" rows="2"></textarea></div>
            <div class="col-md-8">
                <label class="form-label"><i class="bi bi-pencil-square me-1" style="color:#7c3aed;"></i>Reason for Updating <span style="color:#ef4444;">*</span></label>
                <input type="text" name="UpdateReason" id="eLodgeUpdateReason" class="form-control" placeholder="e.g. Extended stay, rate change…" required>
            </div>
            <div class="col-md-4">
                <label class="form-label"><i class="bi bi-clock me-1" style="color:var(--muted);"></i>Date Updated</label>
                <input type="text" id="eLodgeUpdatedAt" class="form-control" readonly style="background:#f8fafc; color:var(--muted); font-size:13px;">
            </div>
        </div></div>
        <div class="modal-footer">
            <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-main" style="background:#7c3aed;color:#fff;">Update Record</button>
        </div>
    </form>
</div></div></div>

<!-- VIEW SERVICE MODAL -->
<div class="modal fade" id="viewSvcModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content" style="border-radius:14px;overflow:hidden;">

    <!-- Custom header with print button -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:#fff;border-bottom:1px solid var(--border);">
        <span style="font-weight:700;font-size:14px;color:var(--dark);display:flex;align-items:center;gap:8px;">
            <i class="bi bi-box-seam-fill" style="color:var(--teal);"></i> Service Details
        </span>
        <div style="display:flex;gap:8px;align-items:center;">
            <button type="button" onclick="printSvcCard()" style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:5px 12px;border-radius:6px;border:1px solid var(--border);background:#f8fafc;color:var(--dark);cursor:pointer;">
                <i class="bi bi-printer"></i> Print
            </button>
            <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size:11px;"></button>
        </div>
    </div>

    <div class="modal-body p-0" id="svcPrintArea">
        <!-- Print-only header -->
        <div class="svc-print-header" style="display:none;padding:18px 24px 14px;border-bottom:2px dashed #e2e8f0;text-align:center;margin-bottom:4px;">
            <img src="logo1.png" alt="Heartside Vet" style="width:48px;height:48px;object-fit:contain;margin-bottom:6px;display:block;margin-left:auto;margin-right:auto;">
            <div style="font-size:18px;font-weight:800;color:#1e3a5f;letter-spacing:.3px;">Heartside Vet Clinic</div>
            <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-top:2px;">Service Record</div>
            <div style="font-size:13px;color:#334155;margin-top:6px;">Service: <strong id="svcPrintName"></strong> &nbsp;·&nbsp; Category: <strong id="svcPrintCategory"></strong></div>
        </div>

        <div style="padding:16px 20px;">
            <div class="row g-3">
                <?php vfield('Service Name', 'vSvcName', 'vm-value-lg', 12); ?>
                <?php vfield('Category', 'vSvcCategory'); vfield('Status', 'vSvcStatus'); ?>
                <?php vfield('Price', 'vSvcPrice', 'vm-value-money'); vfield('Duration', 'vSvcDuration'); ?>
                <?php vfield('Description', 'vSvcDesc', '', 12); ?>
                <div class="col-6" id="vSvcUpdatedWrap" style="display:none;"><div class="vm-label"><i class="bi bi-clock-history me-1"></i>Last Updated</div><div class="vm-value" id="vSvcUpdated" style="font-size:13px;">—</div></div>
                <div class="col-6" id="vSvcReasonWrap" style="display:none;"><div class="vm-label"><i class="bi bi-pencil-square me-1"></i>Reason for Update</div><div class="vm-notes-block" id="vSvcReason" style="font-size:13px;">—</div></div>
            </div>
        </div>

        <!-- Print-only footer -->
        <div class="svc-print-footer" style="display:none;border-top:2px dashed #e2e8f0;margin:16px 20px 0;padding:10px 0;text-align:center;">
            <div style="font-size:10px;color:#94a3b8;">Printed from Heartside Vet Clinic Management Portal &nbsp;·&nbsp; <span id="svcPrintDate"></span></div>
        </div>
    </div>

    <div class="modal-footer"><button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<style>
@media print {
  body * { visibility: hidden; }
  #svcPrintArea, #svcPrintArea * { visibility: visible; }
  #svcPrintArea { position: fixed; top: 0; left: 0; width: 100%; padding: 32px; }
  .svc-print-header, .svc-print-footer { display: block !important; }
  #lodgePrintArea, #lodgePrintArea * { visibility: visible; }
  #lodgePrintArea { position: fixed; top: 0; left: 0; width: 100%; padding: 32px; }
  .lodge-print-header, .lodge-print-footer { display: block !important; }
  #plhPrintArea, #plhPrintArea * { visibility: visible; }
  #plhPrintArea { position: fixed; top: 0; left: 0; width: 100%; padding: 32px; }
  .plh-print-header, .plh-print-footer { display: block !important; }
  .modal, .modal-dialog, .modal-content { box-shadow: none !important; border: none !important; }
}
</style>

<!-- PET LODGING HISTORY MODAL -->
<div class="modal fade" id="petLodgingHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);border-bottom:1px solid #ddd6fe;display:flex;align-items:flex-start;justify-content:space-between;">
                <div style="flex:1;">
                    <h5 class="modal-title mb-0" style="color:var(--dark);font-weight:700;">
                        <i class="bi bi-house-heart-fill me-2" style="color:#7c3aed;"></i>Lodging History
                    </h5>
                    <div style="font-size:12px;color:var(--muted);margin-top:2px;">
                        Patient: <strong id="plhPetName" style="color:#7c3aed;"></strong>
                        &nbsp;·&nbsp; <span id="plhRecordCount"></span>
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;margin-left:12px;">
                    <button type="button" onclick="printPetLodgingHistory()" style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:5px 12px;border-radius:6px;border:1px solid var(--border);background:#fff;color:var(--dark);cursor:pointer;">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0" id="plhPrintArea">
                <!-- Print-only header -->
                <div class="plh-print-header" style="display:none;padding:18px 24px 14px;border-bottom:2px dashed #e2e8f0;text-align:center;margin-bottom:16px;">
                    <img src="logo1.png" alt="Heartside Vet" style="width:48px;height:48px;object-fit:contain;margin-bottom:6px;display:block;margin-left:auto;margin-right:auto;">
                    <div style="font-size:18px;font-weight:800;color:#1e3a5f;letter-spacing:.3px;">Heartside Vet Clinic</div>
                    <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-top:2px;">Pet Lodging History</div>
                    <div style="font-size:13px;color:#334155;margin-top:6px;">Patient: <strong id="plhPrintPetName"></strong></div>
                </div>

                <div id="plhLoading" class="text-center p-5 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" style="color:#7c3aed;"></div> Loading records…
                </div>
                <div id="plhEmpty" class="text-center p-5 text-muted" style="display:none;">
                    <i class="bi bi-house-heart" style="font-size:2rem;opacity:.4;display:block;margin-bottom:8px;"></i>
                    No lodging records found for this pet.
                </div>
                <div id="plhContent" style="display:none;">
                    <table class="modern-table mb-0" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th style="width:80px;">Lodging #</th>
                                <th style="width:110px;">Check-In</th>
                                <th style="width:110px;">Check-Out</th>
                                <th style="width:70px;">Cage</th>
                                <th style="width:100px;">Daily Rate</th>
                                <th style="width:100px;">Total Stay</th>
                                <th>Instructions</th>
                                <th style="width:110px;">Status</th>
                            </tr>
                        </thead>
                        <tbody id="plhRows"></tbody>
                    </table>
                </div>

                <!-- Print-only footer -->
                <div class="plh-print-footer" style="display:none;border-top:2px dashed #e2e8f0;margin-top:20px;padding:10px 18px;text-align:center;">
                    <div style="font-size:10px;color:#94a3b8;">Printed from Heartside Vet Clinic Management Portal &nbsp;·&nbsp; <span id="plhPrintDate"></span></div>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid var(--border);">
                <div id="plhSummary" style="font-size:12px;color:var(--muted);flex:1;text-align:left;"></div>
                <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- VIEW LODGING MODAL -->
<div class="modal fade" id="viewLodgeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:#fff;border-bottom:1px solid var(--border);">
        <span style="font-weight:700;font-size:14px;color:var(--dark);display:flex;align-items:center;gap:8px;">
            <i class="bi bi-house-heart-fill" style="color:#7c3aed;"></i> Lodging Details
        </span>
        <div style="display:flex;gap:8px;align-items:center;">
            <button type="button" onclick="printLodgeCard()" style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:5px 12px;border-radius:6px;border:1px solid var(--border);background:#f8fafc;color:var(--dark);cursor:pointer;">
                <i class="bi bi-printer"></i> Print
            </button>
            <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size:11px;"></button>
        </div>
    </div>
    <div class="modal-body p-0" id="lodgePrintArea">
        <!-- Print-only header -->
        <div class="lodge-print-header" style="display:none;padding:18px 24px 14px;border-bottom:2px dashed #e2e8f0;text-align:center;margin-bottom:4px;">
            <img src="logo1.png" alt="Heartside Vet" style="width:48px;height:48px;object-fit:contain;margin-bottom:6px;display:block;margin-left:auto;margin-right:auto;">
            <div style="font-size:18px;font-weight:800;color:#1e3a5f;letter-spacing:.3px;">Heartside Vet Clinic</div>
            <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-top:2px;">Lodging Record</div>
            <div style="font-size:13px;color:#334155;margin-top:6px;">Record: <strong id="lodgePrintID"></strong> &nbsp;·&nbsp; Patient: <strong id="lodgePrintPet"></strong></div>
        </div>

        <div style="padding:16px 20px;"><div class="row g-3">
            <?php vfield('Pet',   'vLodgePet',   'vm-value-lg'); vfield('Owner', 'vLodgeOwner'); ?>
            <?php vfield('Check-In Date',  'vLodgeCheckIn');  vfield('Check-Out Date', 'vLodgeCheckOut'); ?>
            <?php vfield('Cage #', 'vLodgeCage'); vfield('Daily Rate', 'vLodgeRate', 'font-size:14px;font-weight:700;color:var(--green);'); ?>
            <?php vfield('Status', 'vLodgeStatus'); vfield('Special Instructions', 'vLodgeInstructions', 'font-size:14px;color:var(--muted);', 12); ?>
            <div class="col-6" id="vLodgeUpdatedWrap" style="display:none;"><div class="vm-label"><i class="bi bi-clock-history me-1"></i>Last Updated</div><div class="vm-value" id="vLodgeUpdated" style="font-size:13px;">—</div></div>
            <div class="col-6" id="vLodgeReasonWrap" style="display:none;"><div class="vm-label"><i class="bi bi-pencil-square me-1"></i>Reason for Update</div><div class="vm-notes-block" id="vLodgeReason" style="font-size:13px;">—</div></div>
        </div></div>

        <!-- Print-only footer -->
        <div class="lodge-print-footer" style="display:none;border-top:2px dashed #e2e8f0;margin:16px 20px 0;padding:10px 0;text-align:center;">
            <div style="font-size:10px;color:#94a3b8;">Printed from Heartside Vet Clinic Management Portal &nbsp;·&nbsp; <span id="lodgePrintDate"></span></div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<script>
const $ = id => document.getElementById(id);

function printSvcCard() {
    document.getElementById('svcPrintName').textContent     = document.getElementById('vSvcName').textContent;
    document.getElementById('svcPrintCategory').textContent = document.getElementById('vSvcCategory').textContent;
    document.getElementById('svcPrintDate').textContent     = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    window.print();
}

function printPetLodgingHistory() {
    document.getElementById('plhPrintPetName').textContent = document.getElementById('plhPetName').textContent;
    document.getElementById('plhPrintDate').textContent    = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    window.print();
}

function viewPetLodgingHistory(petID, petName) {
    document.getElementById('plhPetName').textContent     = petName;
    document.getElementById('plhRecordCount').textContent = '';
    document.getElementById('plhSummary').textContent     = '';
    document.getElementById('plhLoading').style.display   = '';
    document.getElementById('plhContent').style.display   = 'none';
    document.getElementById('plhEmpty').style.display     = 'none';
    document.getElementById('plhRows').innerHTML          = '';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('petLodgingHistoryModal')).show();

    const sm = { Active:'badge-boarding', 'Checked Out':'badge-inactive', Cancelled:'badge-cancelled' };

    fetch(`services.php?pet_lodging_history=${petID}`)
        .then(r => r.json())
        .then(rows => {
            document.getElementById('plhLoading').style.display = 'none';
            if (!rows.length) { document.getElementById('plhEmpty').style.display = ''; return; }

            document.getElementById('plhRecordCount').textContent =
                rows.length + ' record' + (rows.length !== 1 ? 's' : '') + ' on file';

            const tbody = document.getElementById('plhRows');
            let totalNights = 0;
            rows.forEach((r, i) => {
                const checkIn  = r.CheckInDate  ? new Date(r.CheckInDate  + 'T00:00:00') : null;
                const checkOut = r.CheckOutDate ? new Date(r.CheckOutDate + 'T00:00:00') : null;
                const fmtIn    = checkIn  ? checkIn.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';
                const fmtOut   = checkOut ? checkOut.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '<span class="badge-modern badge-boarding">Still boarding</span>';
                let nights = 0, totalCost = '—';
                if (checkIn && checkOut) {
                    nights = Math.round((checkOut - checkIn) / 86400000);
                    totalNights += nights;
                    totalCost = '₱' + (nights * parseFloat(r.DailyRate || 0)).toLocaleString('en-PH',{minimumFractionDigits:2});
                }
                const badge = sm[r.Status] || 'badge-inactive';
                tbody.insertAdjacentHTML('beforeend', `
                    <tr style="${i===0?'background:#f5f3ff;':''}">
                        <td><span style="font-weight:700;font-size:12px;color:var(--teal);">LDG-${String(r.LodgingID).padStart(4,'0')}</span>${i===0?'<br><span style="font-size:9px;color:var(--teal);font-weight:700;">LATEST</span>':''}</td>
                        <td style="font-weight:600;white-space:nowrap;">${fmtIn}</td>
                        <td style="white-space:nowrap;">${fmtOut}</td>
                        <td><span class="cat-badge">${r.CageNumber || '—'}</span></td>
                        <td style="font-weight:600;color:var(--green);">₱${parseFloat(r.DailyRate||0).toFixed(2)}/day</td>
                        <td style="font-weight:700;">${nights ? nights + ' night' + (nights!==1?'s':'') + '<br><span style="color:var(--green);font-size:11px;">'+totalCost+'</span>' : totalCost}</td>
                        <td style="font-size:12px;color:var(--muted);max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${r.SpecialInstructions || '—'}</td>
                        <td><span class="badge-modern ${badge}">${r.Status}</span></td>
                    </tr>`);
            });

            document.getElementById('plhSummary').innerHTML =
                `${rows.length} stay${rows.length!==1?'s':''} &nbsp;·&nbsp; Total nights: <strong style="color:#7c3aed;">${totalNights}</strong>`;
            document.getElementById('plhContent').style.display = '';
        })
        .catch(() => {
            document.getElementById('plhLoading').style.display = 'none';
            document.getElementById('plhEmpty').style.display   = '';
            document.getElementById('plhEmpty').innerHTML       =
                '<i class="bi bi-exclamation-circle text-danger" style="font-size:2rem;display:block;margin-bottom:8px;"></i>Failed to load records.';
        });
}

function openAddSvc() {
    $('svcModalTitle').textContent        = 'Add Service';
    $('svcAction').value                  = 'add_service';
    $('svcSubmitBtn').textContent         = 'Save Service';
    $('statusField').style.display        = 'none';
    $('svcUpdateReasonField').style.display = 'none';
    $('svcUpdatedAtField').style.display    = 'none';
    $('fSvcUpdateReason').required = false;
    ['svcID','fSvcName','fPrice','fDuration','fDesc'].forEach(id => $(id).value = '');
    $('fCategory').selectedIndex = 0;
}

function openEditSvc(r) {
    $('svcModalTitle').textContent = 'Edit Service';
    $('svcAction').value           = 'update_service';
    $('svcSubmitBtn').textContent  = 'Update Service';
    $('statusField').style.display = '';
    $('svcID').value       = r.ServiceID;
    $('fSvcName').value    = r.ServiceName;
    $('fCategory').value   = r.Category;
    $('fPrice').value      = r.Price;
    $('fDuration').value   = r.Duration;
    $('fDesc').value       = r.Description;
    $('fIsActive').value   = r.IsActive;
    $('svcUpdateReasonField').style.display = '';
    $('svcUpdatedAtField').style.display    = '';
    $('fSvcUpdateReason').value   = '';
    $('fSvcUpdateReason').required = true;
    $('fSvcUpdatedAt').value = new Date().toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
    bootstrap.Modal.getOrCreateInstance($('svcModal')).show();
}

function viewSvc(r) {
    $('vSvcName').textContent     = r.ServiceName || '—';
    $('vSvcCategory').textContent = r.Category    || '—';
    $('vSvcPrice').textContent    = r.Price ? '₱' + parseFloat(r.Price).toFixed(2) : '—';
    $('vSvcDuration').textContent = r.Duration ? r.Duration + ' min' : '—';
    $('vSvcDesc').textContent     = r.Description || '—';
    $('vSvcStatus').innerHTML     = r.IsActive == 1
        ? '<span class="badge-modern badge-active">Active</span>'
        : '<span class="badge-modern badge-inactive">Inactive</span>';
    const svcUpdWrap    = $('vSvcUpdatedWrap');
    const svcReasonWrap = $('vSvcReasonWrap');
    if (r.UpdatedAt && r.UpdateReason) {
        $('vSvcUpdated').textContent = new Date(r.UpdatedAt).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
        $('vSvcReason').textContent  = r.UpdateReason;
        svcUpdWrap.style.display    = '';
        svcReasonWrap.style.display = '';
    } else {
        svcUpdWrap.style.display    = 'none';
        svcReasonWrap.style.display = 'none';
    }
    bootstrap.Modal.getOrCreateInstance($('viewSvcModal')).show();
}

function viewLodge(r) {
    const fmt = d => d ? new Date(d).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit' }) : '—';
    const sm  = { Active:'badge-boarding', 'Checked Out':'badge-inactive', Cancelled:'badge-cancelled' };
    $('vLodgePet').textContent          = r.PetName  || '—';
    $('vLodgeOwner').textContent        = r.Owner    || '—';
    $('vLodgeCheckIn').textContent      = fmt(r.CheckInDate);
    $('vLodgeCheckOut').textContent     = r.CheckOutDate ? fmt(r.CheckOutDate) : 'Still boarding';
    $('vLodgeCage').textContent         = r.CageNumber || '—';
    $('vLodgeRate').textContent         = r.DailyRate ? '₱' + parseFloat(r.DailyRate).toFixed(2) + '/day' : '—';
    $('vLodgeInstructions').textContent = r.SpecialInstructions || '—';
    $('vLodgeStatus').innerHTML         = `<span class="badge-modern ${sm[r.Status] || 'badge-inactive'}">${r.Status}</span>`;
    const lodgeUpdWrap    = $('vLodgeUpdatedWrap');
    const lodgeReasonWrap = $('vLodgeReasonWrap');
    if (r.UpdatedAt && r.UpdateReason) {
        $('vLodgeUpdated').textContent = new Date(r.UpdatedAt).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
        $('vLodgeReason').textContent  = r.UpdateReason;
        lodgeUpdWrap.style.display    = '';
        lodgeReasonWrap.style.display = '';
    } else {
        lodgeUpdWrap.style.display    = 'none';
        lodgeReasonWrap.style.display = 'none';
    }
    bootstrap.Modal.getOrCreateInstance($('viewLodgeModal')).show();
    $('viewLodgeModal')._lodgingID = r.LodgingID;
    $('viewLodgeModal')._petName   = r.PetName;
}

function printLodgeCard() {
    const modal = $('viewLodgeModal');
    $('lodgePrintID').textContent  = 'LDG-' + String(modal._lodgingID || 0).padStart(4, '0');
    $('lodgePrintPet').textContent = modal._petName || $('vLodgePet').textContent;
    $('lodgePrintDate').textContent = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    window.print();
}

// ── CAGE DEFINITIONS ──
const CAGES = [
    { id:'C1',  size:'Big',   label:'C1 — Big'   },
    { id:'C2',  size:'Big',   label:'C2 — Big'   },
    { id:'C3',  size:'Big',   label:'C3 — Big'   },
    { id:'C4',  size:'Big',   label:'C4 — Big'   },
    { id:'C5',  size:'Big',   label:'C5 — Big'   },
    { id:'C6',  size:'Small', label:'C6 — Small' },
    { id:'C7',  size:'Small', label:'C7 — Small' },
    { id:'C8',  size:'Small', label:'C8 — Small' },
    { id:'C9',  size:'Small', label:'C9 — Small' },
    { id:'C10', size:'Small', label:'C10 — Small'},
];

async function loadCageOptions(selectEl, hintEl, currentCage, excludeID) {
    selectEl.innerHTML = '<option value="">— Loading cages… —</option>';
    const occupied = await fetch(`services.php?occupied_cages=1&exclude=${excludeID || 0}`)
        .then(r => r.json()).catch(() => []);

    selectEl.innerHTML = '<option value="">— Select Cage —</option>';

    let bigGroup  = document.createElement('optgroup'); bigGroup.label  = '🐕 Big Cages (C1–C5)';
    let smGroup   = document.createElement('optgroup'); smGroup.label   = '🐈 Small Cages (C6–C10)';

    CAGES.forEach(c => {
        const taken = occupied.includes(c.id) && c.id !== currentCage;
        const opt   = document.createElement('option');
        opt.value   = c.id;
        opt.textContent = taken ? c.label + '  ✕ Occupied' : c.label;
        if (taken) { opt.style.color = '#b0b8c8'; opt.style.fontStyle = 'italic'; opt.disabled = true; }
        if (c.id === currentCage) opt.selected = true;
        (c.size === 'Big' ? bigGroup : smGroup).appendChild(opt);
    });

    selectEl.appendChild(bigGroup);
    selectEl.appendChild(smGroup);

    const freeCount = CAGES.filter(c => !occupied.includes(c.id) || c.id === currentCage).length;
    hintEl.textContent = freeCount + ' of 10 cage' + (freeCount !== 1 ? 's' : '') + ' available';
    hintEl.style.color = freeCount === 0 ? '#ef4444' : 'var(--muted)';
}

// Auto-load cages when Check-In modal opens
document.getElementById('lodgeModal').addEventListener('show.bs.modal', () => {
    loadCageOptions($('addCageSelect'), $('addCageHint'), '', 0);
});

function openEditLodge(r) {
    $('eLodgingID').value         = r.LodgingID;
    $('eLodgePetID').value        = r.PetID;
    $('eLodgeOwner').value        = r.ClientID;
    $('eLodgeCheckIn').value      = r.CheckInDate  ? r.CheckInDate.substring(0, 10)  : '';
    $('eLodgeCheckOut').value     = r.CheckOutDate ? r.CheckOutDate.substring(0, 10) : '';
    $('eLodgeRate').value         = r.DailyRate    || '';
    $('eLodgeStatus').value       = r.Status       || 'Active';
    $('eLodgeInstructions').value = r.SpecialInstructions || '';
    $('eLodgeUpdateReason').value = '';
    $('eLodgeUpdatedAt').value = new Date().toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
    loadCageOptions($('eLodgeCage'), $('editCageHint'), r.CageNumber || '', r.LodgingID);
    bootstrap.Modal.getOrCreateInstance($('editLodgeModal')).show();
}

function autoFillOwner(sel, targetId) {
    const cid = sel.options[sel.selectedIndex].dataset.client;
    for (let o of $(targetId).options) o.selected = (o.value == cid);
}
</script>

<?php include('footer.php'); ?>