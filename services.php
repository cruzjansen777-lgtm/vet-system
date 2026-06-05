<?php
include('dbconnect.php');

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_service') {
        $s = $conn->prepare("INSERT INTO services (ServiceName,Category,Price,Duration,Description) VALUES (?,?,?,?,?)");
        $s->bind_param("ssdis", $_POST['ServiceName'],$_POST['Category'],$_POST['Price'],$_POST['Duration'],$_POST['Description']);
        $s->execute(); $s->close();
        header("Location: services.php?msg=Service added."); exit;
    }
    if ($action === 'update_service') {
        $id=$_POST['ServiceID']; $p=floatval($_POST['Price']); $d=intval($_POST['Duration']); $a=intval($_POST['IsActive']);
        $s = $conn->prepare("UPDATE services SET ServiceName=?,Category=?,Price=?,Duration=?,Description=?,IsActive=? WHERE ServiceID=?");
        $s->bind_param("ssdisii", $_POST['ServiceName'],$_POST['Category'],$p,$d,$_POST['Description'],$a,$id);
        $s->execute(); $s->close();
        header("Location: services.php?msg=Service updated."); exit;
    }
    if ($action === 'add_lodging') {
        $s = $conn->prepare("INSERT INTO lodging (PetID,ClientID,CheckInDate,CageNumber,DailyRate,SpecialInstructions) VALUES (?,?,?,?,?,?)");
        $s->bind_param("iissds", $_POST['PetID'],$_POST['ClientID'],$_POST['CheckInDate'],$_POST['CageNumber'],$_POST['DailyRate'],$_POST['SpecialInstructions']);
        $s->execute(); $s->close();
        header("Location: services.php?tab=lodging&msg=Pet checked in."); exit;
    }
    if ($action === 'update_lodging') {
        $lid=intval($_POST['LodgingID']); $cout=$_POST['CheckOutDate']?:null; $rate=floatval($_POST['DailyRate']);
        $s = $conn->prepare("UPDATE lodging SET PetID=?,ClientID=?,CheckInDate=?,CheckOutDate=?,CageNumber=?,DailyRate=?,SpecialInstructions=?,Status=? WHERE LodgingID=?");
        $s->bind_param("iisssdssi", $_POST['PetID'],$_POST['ClientID'],$_POST['CheckInDate'],$cout,$_POST['CageNumber'],$rate,$_POST['SpecialInstructions'],$_POST['Status'],$lid);
        $s->execute(); $s->close();
        header("Location: services.php?tab=lodging&msg=Lodging record updated."); exit;
    }
}

// GET handlers
if (isset($_GET['delete_service'])) {
    $id = intval($_GET['delete_service']);
    $isActive = $conn->query("SELECT IsActive FROM services WHERE ServiceID=$id")->fetch_assoc()['IsActive'];
    if ($isActive) {
        header("Location: services.php?err=Service must be set to Inactive before it can be removed."); exit;
    } else {
        $conn->query("UPDATE services SET IsDeleted=1 WHERE ServiceID=$id");
        header("Location: services.php?msg=Service removed from the list."); exit;
    }
}
if (isset($_GET['delete_lodging']))  {
if (isset($_GET['delete_lodging'])) {
    $id = intval($_GET['delete_lodging']);
    $status = $conn->query("SELECT Status FROM lodging WHERE LodgingID=$id")->fetch_assoc()['Status'];
    } if ($status === 'Active') {
        header("Location: services.php?tab=lodging&err=Pet must be checked out first before removing the record."); exit;
    } else {
        $conn->query("UPDATE lodging SET IsActive=0 WHERE LodgingID=$id");
        header("Location: services.php?tab=lodging&msg=Lodging record removed."); exit;
    }
}
if (isset($_GET['checkout'])) {
    $today = date('Y-m-d');
    $conn->query("UPDATE lodging SET CheckOutDate='$today',Status='Checked Out' WHERE LodgingID=".intval($_GET['checkout']));
    header("Location: services.php?tab=lodging&msg=Pet checked out on $today."); exit;
}

include('header.php');

$categories  = ['Consultation','Grooming','Surgery','Vaccination','Lodging','Laboratory','Other'];
$petsList    = $conn->query("SELECT p.PetID,p.PetName,p.ClientID,CONCAT(c.FirstName,' ',c.LastName) AS Owner FROM pets p JOIN clients c ON p.ClientID=c.ClientID WHERE p.IsActive=1")->fetch_all(MYSQLI_ASSOC);
$clientsList = $conn->query("SELECT ClientID,CONCAT(FirstName,' ',LastName) AS Name FROM clients WHERE IsActive=1")->fetch_all(MYSQLI_ASSOC);
$activeTab   = $_GET['tab'] ?? 'services';
$totalSvcs   = $conn->query("SELECT COUNT(*) FROM services WHERE IsActive=1 AND IsDeleted=0")->fetch_row()[0];
$activeLodging = $conn->query("SELECT COUNT(*) FROM lodging WHERE Status='Active' AND IsActive=1")->fetch_row()[0];

$catColors = ['Consultation'=>'#eff6ff:#2563eb','Grooming'=>'#fdf2f8:#db2777','Surgery'=>'#fef2f2:#dc2626',
              'Vaccination'=>'#f0fdf4:#16a34a','Lodging'=>'#f5f3ff:#7c3aed','Laboratory'=>'#fffbeb:#d97706','Other'=>'#f1f5f9:#64748b'];
function catStyle($cat, $colors) { $p=explode(':',$colors[$cat]??'#f1f5f9:#64748b'); return "background:{$p[0]};color:{$p[1]};"; }

// Helper: render a view-modal field row
function vfield($label,$id,$style='font-size:14px;',$col=6) {
    echo "<div class='col-{$col}'><div style='font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;margin-bottom:4px;'>{$label}</div><div id='{$id}' style='{$style}'>—</div></div>";
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
    <a class="nav-tab <?= $activeTab==='services'?'active':'' ?>" href="?tab=services"><i class="bi bi-box-seam-fill me-1"></i> Clinic Services</a>
    <a class="nav-tab <?= $activeTab==='lodging'?'active':'' ?>" href="?tab=lodging"><i class="bi bi-house-heart-fill me-1"></i> Lodging & Boarding</a>
</div>

<?php if ($activeTab === 'services'): ?>
<div class="card">
    <div class="card-header">
        <span class="card-header-title"><i class="bi bi-box-seam-fill me-2" style="color:#f59e0b;"></i>Service Catalog</span>
        <span style="font-size:12px;color:var(--muted);"><?= $totalSvcs ?> active</span>
    </div>
    <div class="card-body p-0">
        <table class="modern-table">
            <thead><tr><th>#</th><th>Service Name</th><th>Category</th><th>Price</th><th>Duration</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $res = $conn->query("SELECT * FROM services WHERE IsDeleted=0 ORDER BY Category,ServiceName");
            $highlightSvcID = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
            if (!$res->num_rows) echo '<tr class="empty-row"><td colspan="8"><i class="bi bi-box-seam" style="font-size:28px;display:block;margin-bottom:10px;"></i>No services found. Add your first service!</td></tr>';
            while ($row = $res->fetch_assoc()):
            $json = htmlspecialchars(json_encode($row));
            ?>
            <tr id="row-svc-<?= $row['ServiceID'] ?>" class="<?= ($highlightSvcID && $highlightSvcID==$row['ServiceID']) ? 'search-highlight-row' : '' ?>">
                <td style="color:var(--light-muted);font-size:12px;">#<?= $row['ServiceID'] ?></td>
                <td style="font-weight:700;"><?= htmlspecialchars($row['ServiceName']) ?></td>
                <td><span class="cat-badge" style="<?= catStyle($row['Category'],$catColors) ?>"><?= $row['Category'] ?></span></td>
                <td style="font-weight:700;color:var(--green);">₱<?= number_format($row['Price'],2) ?></td>
                <td style="color:var(--muted);"><?= $row['Duration'] ? $row['Duration'].' min' : '—' ?></td>
                <td style="font-size:12px;color:var(--muted);max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($row['Description']) ?></td>
                <td><span class="badge-modern <?= $row['IsActive']?'badge-active':'badge-inactive' ?>"><?= $row['IsActive']?'Active':'Inactive' ?></span></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button class="btn-icon btn-icon-view" onclick="viewSvc(<?= $json ?>)" title="View"><i class="bi bi-eye"></i></button>
                        <button class="btn-icon btn-icon-edit" onclick="openEditSvc(<?= $json ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                        <?php if ($row['IsActive']): ?>
                        <a href="services.php?delete_service=<?= $row['ServiceID'] ?>" class="btn-icon btn-icon-delete" title="Remove"
                           onclick="return confirm('Remove this service?')"><i class="bi bi-trash"></i></a>
                        <?php else: ?>
                        <a href="services.php?delete_service=<?= $row['ServiceID'] ?>" class="btn-icon btn-icon-delete" title="Remove"
                           onclick="return confirm('Remove this service?')"><i class="bi bi-trash"></i></a>
                        <?php endif; ?>
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
            <thead><tr><th>#</th><th>Pet</th><th>Owner</th><th>Check-In</th><th>Check-Out</th><th>Cage</th><th>Daily Rate</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $res = $conn->query("SELECT l.*,p.PetName,CONCAT(c.FirstName,' ',c.LastName) AS Owner FROM lodging l JOIN pets p ON l.PetID=p.PetID JOIN clients c ON l.ClientID=c.ClientID WHERE l.IsActive=1 ORDER BY l.CheckInDate DESC");
            if (!$res->num_rows) echo '<tr class="empty-row"><td colspan="9"><i class="bi bi-house-heart" style="font-size:28px;display:block;margin-bottom:10px;"></i>No lodging records found.</td></tr>';
            while ($row = $res->fetch_assoc()):
                $json = htmlspecialchars(json_encode($row));
                $lodgeBadge = $row['Status']==='Active' ? 'badge-boarding' : ($row['Status']==='Checked Out' ? 'badge-inactive' : 'badge-cancelled');
            ?>
            <tr>
                <td style="color:var(--light-muted);font-size:12px;">#<?= $row['LodgingID'] ?></td>
                <td style="font-weight:700;"><?= htmlspecialchars($row['PetName']) ?></td>
                <td style="color:var(--muted);"><?= htmlspecialchars($row['Owner']) ?></td>
                <td style="font-size:13px;"><?= date('M d, Y', strtotime($row['CheckInDate'])) ?></td>
                <td style="font-size:13px;"><?= $row['CheckOutDate'] ? date('M d, Y', strtotime($row['CheckOutDate'])) : '<span class="badge-modern badge-boarding">Still boarding</span>' ?></td>
                <td><span class="cat-badge"><?= htmlspecialchars($row['CageNumber']?:'—') ?></span></td>
                <td style="font-weight:600;color:var(--green);">₱<?= number_format($row['DailyRate'],2) ?>/day</td>
                <td><span class="badge-modern <?= $lodgeBadge ?>"><?= $row['Status'] ?></span></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button class="btn-icon btn-icon-view" onclick="viewLodge(<?= $json ?>)" title="View"><i class="bi bi-eye"></i></button>
                        <button class="btn-icon btn-icon-edit" onclick="openEditLodge(<?= $json ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                        <?php if ($row['Status']==='Active'): ?>
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
        <input type="hidden" name="action" id="svcAction" value="add_service">
        <input type="hidden" name="ServiceID" id="svcID">
        <div class="modal-body"><div class="row g-3">
            <div class="col-12"><label class="form-label">Service Name</label><input type="text" name="ServiceName" id="fSvcName" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Category</label>
                <select name="Category" id="fCategory" class="form-select">
                    <?php foreach ($categories as $cat): ?><option value="<?= $cat ?>"><?= $cat ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Price (₱)</label><input type="number" name="Price" id="fPrice" class="form-control" step="0.01" required></div>
            <div class="col-md-3"><label class="form-label">Duration(mins)</label><input type="number" name="Duration" id="fDuration" class="form-control"></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="Description" id="fDesc" class="form-control" rows="2"></textarea></div>
            <div class="col-md-6" id="statusField" style="display:none"><label class="form-label">Status</label>
                <select name="IsActive" id="fIsActive" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select>
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
            <div class="col-md-6"><label class="form-label">Pet</label>
                <select name="PetID" class="form-select" required onchange="autoFillOwner(this,'lodgeOwner')">
                    <option value="">— Select Pet —</option>
                    <?php foreach ($petsList as $p): ?><option value="<?= $p['PetID'] ?>" data-client="<?= $p['ClientID'] ?>"><?= htmlspecialchars($p['PetName'].' ('.$p['Owner'].')') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Owner</label>
                <select name="ClientID" id="lodgeOwner" class="form-select" required>
                    <option value="">— Select Pet first —</option>
                    <?php foreach ($clientsList as $c): ?><option value="<?= $c['ClientID'] ?>"><?= htmlspecialchars($c['Name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Check-In Date</label><input type="date" name="CheckInDate" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-3"><label class="form-label">Cage #</label><input type="text" name="CageNumber" class="form-control"></div>
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
        <input type="hidden" name="action" value="update_lodging">
        <input type="hidden" name="LodgingID" id="eLodgingID">
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Pet</label>
                <select name="PetID" id="eLodgePetID" class="form-select" required onchange="autoFillOwner(this,'eLodgeOwner')">
                    <option value="">— Select Pet —</option>
                    <?php foreach ($petsList as $p): ?><option value="<?= $p['PetID'] ?>" data-client="<?= $p['ClientID'] ?>"><?= htmlspecialchars($p['PetName'].' ('.$p['Owner'].')') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Owner</label>
                <select name="ClientID" id="eLodgeOwner" class="form-select" required>
                    <option value="">— Select Pet first —</option>
                    <?php foreach ($clientsList as $c): ?><option value="<?= $c['ClientID'] ?>"><?= htmlspecialchars($c['Name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Check-In Date</label><input type="date" name="CheckInDate" id="eLodgeCheckIn" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Check-Out Date</label><input type="date" name="CheckOutDate" id="eLodgeCheckOut" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Cage #</label><input type="text" name="CageNumber" id="eLodgeCage" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Daily Rate (₱)</label><input type="number" name="DailyRate" id="eLodgeRate" class="form-control" step="0.01"></div>
            <div class="col-md-4"><label class="form-label">Status</label>
                <select name="Status" id="eLodgeStatus" class="form-select">
                    <option value="Active">Active</option><option value="Checked Out">Checked Out</option><option value="Cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-12"><label class="form-label">Special Instructions</label><textarea name="SpecialInstructions" id="eLodgeInstructions" class="form-control" rows="2"></textarea></div>
        </div></div>
        <div class="modal-footer">
            <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-main" style="background:#7c3aed;color:#fff;">Update Record</button>
        </div>
    </form>
</div></div></div>

<!-- VIEW SERVICE MODAL -->
<div class="modal fade" id="viewSvcModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-box-seam-fill me-2" style="color:var(--teal);"></i>Service Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-4"><div class="row g-3">
        <?php vfield('Service Name','vSvcName','font-size:16px;font-weight:700;',12); ?>
        <?php vfield('Category','vSvcCategory'); vfield('Status','vSvcStatus'); ?>
        <?php vfield('Price','vSvcPrice','font-size:14px;font-weight:700;color:var(--green);'); vfield('Duration','vSvcDuration'); ?>
        <?php vfield('Description','vSvcDesc','font-size:14px;color:var(--muted);',12); ?>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<!-- VIEW LODGING MODAL -->
<div class="modal fade" id="viewLodgeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-house-heart-fill me-2" style="color:#7c3aed;"></i>Lodging Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body p-4"><div class="row g-3">
        <?php vfield('Pet','vLodgePet','font-size:15px;font-weight:700;'); vfield('Owner','vLodgeOwner'); ?>
        <?php vfield('Check-In Date','vLodgeCheckIn'); vfield('Check-Out Date','vLodgeCheckOut'); ?>
        <?php vfield('Cage #','vLodgeCage'); vfield('Daily Rate','vLodgeRate','font-size:14px;font-weight:700;color:var(--green);'); ?>
        <?php vfield('Status','vLodgeStatus'); vfield('Special Instructions','vLodgeInstructions','font-size:14px;color:var(--muted);',12); ?>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<script>
const $ = id => document.getElementById(id);

function openAddSvc() {
    $('svcModalTitle').textContent = 'Add Service';
    $('svcAction').value = 'add_service';
    $('svcSubmitBtn').textContent = 'Save Service';
    $('statusField').style.display = 'none';
    ['svcID','fSvcName','fPrice','fDuration','fDesc'].forEach(id => $(id).value = '');
    $('fCategory').selectedIndex = 0;
}
function openEditSvc(r) {
    $('svcModalTitle').textContent = 'Edit Service';
    $('svcAction').value = 'update_service';
    $('svcSubmitBtn').textContent = 'Update Service';
    $('statusField').style.display = '';
    $('svcID').value=r.ServiceID; $('fSvcName').value=r.ServiceName; $('fCategory').value=r.Category;
    $('fPrice').value=r.Price; $('fDuration').value=r.Duration; $('fDesc').value=r.Description; $('fIsActive').value=r.IsActive;
    new bootstrap.Modal($('svcModal')).show();
}
function viewSvc(r) {
    $('vSvcName').textContent     = r.ServiceName||'—';
    $('vSvcCategory').textContent = r.Category||'—';
    $('vSvcPrice').textContent    = r.Price ? '₱'+parseFloat(r.Price).toFixed(2) : '—';
    $('vSvcDuration').textContent = r.Duration ? r.Duration+' min' : '—';
    $('vSvcDesc').textContent     = r.Description||'—';
    $('vSvcStatus').innerHTML     = r.IsActive==1 ? '<span class="badge-modern badge-active">Active</span>' : '<span class="badge-modern badge-inactive">Inactive</span>';
    new bootstrap.Modal($('viewSvcModal')).show();
}
function viewLodge(r) {
    const fmt = d => d ? new Date(d).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'2-digit'}) : '—';
    const sm  = {Active:'badge-boarding','Checked Out':'badge-inactive',Cancelled:'badge-cancelled'};
    $('vLodgePet').textContent          = r.PetName||'—';
    $('vLodgeOwner').textContent        = r.Owner||'—';
    $('vLodgeCheckIn').textContent      = fmt(r.CheckInDate);
    $('vLodgeCheckOut').textContent     = r.CheckOutDate ? fmt(r.CheckOutDate) : 'Still boarding';
    $('vLodgeCage').textContent         = r.CageNumber||'—';
    $('vLodgeRate').textContent         = r.DailyRate ? '₱'+parseFloat(r.DailyRate).toFixed(2)+'/day' : '—';
    $('vLodgeInstructions').textContent = r.SpecialInstructions||'—';
    $('vLodgeStatus').innerHTML         = `<span class="badge-modern ${sm[r.Status]||'badge-inactive'}">${r.Status}</span>`;
    new bootstrap.Modal($('viewLodgeModal')).show();
}
function openEditLodge(r) {
    $('eLodgingID').value         = r.LodgingID;
    $('eLodgePetID').value        = r.PetID;
    $('eLodgeOwner').value        = r.ClientID;
    $('eLodgeCheckIn').value      = r.CheckInDate ? r.CheckInDate.substring(0,10) : '';
    $('eLodgeCheckOut').value     = r.CheckOutDate ? r.CheckOutDate.substring(0,10) : '';
    $('eLodgeCage').value         = r.CageNumber||'';
    $('eLodgeRate').value         = r.DailyRate||'';
    $('eLodgeStatus').value       = r.Status||'Active';
    $('eLodgeInstructions').value = r.SpecialInstructions||'';
    new bootstrap.Modal($('editLodgeModal')).show();
}
function autoFillOwner(sel, targetId) {
    const cid = sel.options[sel.selectedIndex].dataset.client;
    for (let o of $(targetId).options) o.selected = (o.value == cid);
}
</script>
                    
<script>
// Highlight handled by universal header.php script
</script>
<?php include('footer.php'); ?>