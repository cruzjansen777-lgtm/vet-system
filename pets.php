<?php
include('dbconnect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name   = $_POST['PetName'];
    $cid    = intval($_POST['ClientID']);
    $species= $_POST['Species'];
    $breed  = $_POST['Breed'];
    $gender = $_POST['Gender'];
    $dob    = $_POST['DateOfBirth'] ?: null;
    $color  = $_POST['Color'];
    $weight = $_POST['Weight'] ?: null;

    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO pets (ClientID,PetName,Species,Breed,Gender,DateOfBirth,Color,Weight) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issssssd", $cid,$name,$species,$breed,$gender,$dob,$color,$weight);
        $stmt->execute(); $stmt->close();
        header("Location: pets.php?msg=Pet added successfully."); exit;
    }
    if ($action === 'update') {
        $id = intval($_POST['PetID']);
        $isActive = intval($_POST['IsActive'] ?? 1);
        $stmt = $conn->prepare("UPDATE pets SET ClientID=?,PetName=?,Species=?,Breed=?,Gender=?,DateOfBirth=?,Color=?,Weight=?,IsActive=? WHERE PetID=?");
        $stmt->bind_param("issssssdii", $cid,$name,$species,$breed,$gender,$dob,$color,$weight,$isActive,$id);
        $stmt->execute(); $stmt->close();
        header("Location: pets.php?msg=Pet updated successfully."); exit;
    }
}
if (isset($_GET['delete'])) {
    $conn->query("UPDATE pets SET IsActive=0 WHERE PetID=".intval($_GET['delete']));
    header("Location: pets.php?msg=Pet deactivated. To permanently delete, remove the owner first."); exit;
}


include('header.php');

$clientsResult = $conn->query("SELECT ClientID, CONCAT(FirstName,' ',LastName) AS Name FROM clients WHERE IsActive=1 ORDER BY FirstName");
$clientsList = [];
while ($c = $clientsResult->fetch_assoc()) $clientsList[] = $c;
$totalPets = $conn->query("SELECT COUNT(*) FROM pets WHERE IsActive=1 AND IsDeleted=0")->fetch_row()[0];

$speciesColors = ['Dog'=>'#eff6ff:#2563eb','Cat'=>'#fdf2f8:#db2777','Bird'=>'#fffbeb:#d97706','Rabbit'=>'#f5f3ff:#7c3aed','Other'=>'#f1f5f9:#64748b'];
function speciesIcon($s) {
    $icons = ['Dog'=>'🐕','Cat'=>'🐈','Bird'=>'🐦','Rabbit'=>'🐇','Snake'=>'🐍','Mouse'=>'🐁'];
    return $icons[$s] ?? '🐾';
}
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Patients / Pets</h1>
        <p><?= $totalPets ?> active patients registered</p>
    </div>
    <div class="page-header-actions">
        <button class="btn-main btn-teal" data-bs-toggle="modal" data-bs-target="#petModal" onclick="openAdd()">
            <i class="bi bi-plus-lg"></i> Add Pet
        </button>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="alert-modern alert-success-modern">
    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_GET['msg']) ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-header-title"><i class="bi bi-heart-fill me-2" style="color:#db2777;"></i>Pet Records</span>
    </div>
    <div class="card-body p-0">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Pet ID</th>
                    <th>Pet</th>
                    <th>Owner</th>
                    <th>Breed / Age</th>
                    <th>Gender</th>
                    <th>Weight</th>
                    <th>Date Registered</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $res = $conn->query("SELECT p.*, CONCAT(c.FirstName,' ',c.LastName) AS OwnerName FROM pets p JOIN clients c ON p.ClientID=c.ClientID WHERE p.IsDeleted=0 ORDER BY p.CreatedAt DESC");
            $highlightPetID = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
            if ($res->num_rows == 0) echo '<tr class="empty-row"><td colspan="9"><i class="bi bi-heart" style="font-size:28px; display:block; margin-bottom:10px;"></i>No pets registered yet.</td></tr>';
            while ($row = $res->fetch_assoc()):
                $age = $row['DateOfBirth'] ? floor((time() - strtotime($row['DateOfBirth'])) / 31536000) . 'y' : '—';
                $isActive = $row['IsActive'];
                $formattedPetId = 'PET-' . sprintf('%05d', $row['PetID']);
            ?>
            <tr id="row-pet-<?= $row['PetID'] ?>" class="<?= ($highlightPetID && $highlightPetID==$row['PetID']) ? 'search-highlight-row' : '' ?>">
                <td style="font-weight: 600; color: var(--muted); font-size: 13px;"><?= $formattedPetId ?></td>
                <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:38px; height:38px; background:#f0fdf4; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0;">
                            <?= speciesIcon($row['Species']) ?>
                        </div>
                        <div>
                            <div style="font-weight:700;"><?= htmlspecialchars($row['PetName']) ?></div>
                            <div style="font-size:11px; color:var(--light-muted);"><?= htmlspecialchars($row['Species']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <div style="font-weight:500;"><?= htmlspecialchars($row['OwnerName']) ?></div>
                </td>
                <td>
                    <div style="font-size:13px;"><?= htmlspecialchars($row['Breed'] ?: '—') ?></div>
                    <div style="font-size:11px; color:var(--light-muted);"><?= $age ?></div>
                </td>
                <td>
                    <span class="cat-badge"><?= $row['Gender'] ?></span>
                </td>
                <td style="font-size:13px; color:var(--muted);">
                    <?= $row['Weight'] ? $row['Weight'].' kg' : '—' ?>
                </td>
                <td style="font-size:13px; color:var(--muted);">
                    <?= $row['CreatedAt'] ? date('M d, Y', strtotime($row['CreatedAt'])) : '—' ?>
                </td>
                <td>
                    <?php if ($isActive): ?>
                        <span class="badge-modern badge-active">Active</span>
                    <?php else: ?>
                        <span class="badge-modern" style="background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="btn-icon btn-icon-view" onclick="viewPet(<?= htmlspecialchars(json_encode($row)) ?>)" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn-icon btn-icon-edit" onclick="openEdit(<?= htmlspecialchars(json_encode($row)) ?>)" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="pets.php?delete=<?= $row['PetID'] ?>" class="btn-icon btn-icon-delete" title="Remove"
                           onclick="return confirm('Remove this pet?')"><i class="bi bi-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- VIEW PET MODAL -->
<div class="modal fade" id="viewPetModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-heart-fill me-2" style="color:#db2777;"></i>Pet Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="row g-3 p-3 rounded mb-3" style="background:#f8fafc; border-left:4px solid #db2777;">
          <div class="col-2" style="display:flex; align-items:center; justify-content:center;">
            <div style="font-size:36px;" id="vPetIcon">🐾</div>
          </div>
          <div class="col-10">
            <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase;">Pet Name</div>
            <div style="font-weight:800; font-size:18px;" id="vPetName">—</div>
            <div style="font-size:12px; color:var(--muted);" id="vPetSpecies">—</div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-6">
            <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;">Pet ID</div>
            <div style="font-weight:700; color:var(--teal);" id="vPetID">—</div>
          </div>
          <div class="col-6">
            <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;">Status</div>
            <div id="vPetStatus">—</div>
          </div>
          <div class="col-6">
            <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;">Owner</div>
            <div id="vPetOwner" style="font-size:14px; font-weight:600;">—</div>
          </div>
          <div class="col-6">
            <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;">Gender</div>
            <div id="vPetGender" style="font-size:14px;">—</div>
          </div>
          <div class="col-6">
            <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;">Breed</div>
            <div id="vPetBreed" style="font-size:14px;">—</div>
          </div>
          <div class="col-6">
            <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;">Date of Birth</div>
            <div id="vPetDOB" style="font-size:14px;">—</div>
          </div>
          <div class="col-6">
            <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;">Color / Markings</div>
            <div id="vPetColor" style="font-size:14px;">—</div>
          </div>
          <div class="col-6">
            <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;">Weight</div>
            <div id="vPetWeight" style="font-size:14px;">—</div>
          </div>
          <div class="col-6">
            <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;">Date Registered</div>
            <div id="vPetRegistered" style="font-size:14px;">—</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ADD / EDIT PET MODAL -->
<div class="modal fade" id="petModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Add New Pet</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="PetID" id="petID">
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Pet Name</label>
                    <input type="text" name="PetName" id="fPetName" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner</label>
                    <select name="ClientID" id="fClientID" class="form-select" required>
                        <option value="">— Select Owner —</option>
                        <?php foreach ($clientsList as $c): ?>
                        <option value="<?= $c['ClientID'] ?>"><?= htmlspecialchars($c['Name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Species</label>
                    <input type="text" name="Species" id="fSpecies" class="form-control" placeholder="Dog, Cat...">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Breed</label>
                    <input type="text" name="Breed" id="fBreed" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select name="Gender" id="fGender" class="form-select">
                        <option value="Unknown">Unknown</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="DateOfBirth" id="fDOB" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Color / Markings</label>
                    <input type="text" name="Color" id="fColor" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Weight (kg)</label>
                    <input type="number" name="Weight" id="fWeight" class="form-control" step="0.01">
                </div>
                <div class="col-md-4" id="petStatusField" style="display:none;">
                    <label class="form-label">Status</label>
                    <select name="IsActive" id="fIsActive" class="form-select">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-main btn-teal" id="submitBtn">Save Pet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const speciesIconMap = {Dog:'🐕',Cat:'🐈',Bird:'🐦',Rabbit:'🐇',Snake:'🐍',Mouse:'🐁'};

function viewPet(row) {
    document.getElementById('vPetIcon').textContent     = speciesIconMap[row.Species] || '🐾';
    document.getElementById('vPetName').textContent     = row.PetName || '—';
    document.getElementById('vPetSpecies').textContent  = row.Species || '—';
    document.getElementById('vPetID').textContent       = 'PET-' + String(row.PetID).padStart(5, '0');
    document.getElementById('vPetOwner').textContent    = row.OwnerName || '—';
    document.getElementById('vPetGender').textContent   = row.Gender || '—';
    document.getElementById('vPetBreed').textContent    = row.Breed || '—';
    document.getElementById('vPetDOB').textContent      = row.DateOfBirth || '—';
    document.getElementById('vPetColor').textContent    = row.Color || '—';
    document.getElementById('vPetWeight').textContent   = row.Weight ? row.Weight + ' kg' : '—';
    document.getElementById('vPetStatus').innerHTML     = row.IsActive == 1
        ? '<span class="badge-modern badge-active">Active</span>'
        : '<span class="badge-modern badge-inactive">Inactive</span>';
    document.getElementById('vPetRegistered').textContent = row.CreatedAt
        ? new Date(row.CreatedAt).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' })
        : '—';
    new bootstrap.Modal(document.getElementById('viewPetModal')).show();
}

function openAdd() {
    document.getElementById('modalTitle').textContent = 'Add New Pet';
    document.getElementById('formAction').value = 'add';
    document.getElementById('submitBtn').textContent = 'Save Pet';
    ['petID','fPetName','fSpecies','fBreed','fDOB','fColor','fWeight'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('fGender').value = 'Unknown';
    document.getElementById('fClientID').value = '';
    document.getElementById('petStatusField').style.display = 'none';
}
function openEdit(row) {
    document.getElementById('modalTitle').textContent = 'Edit Pet';
    document.getElementById('formAction').value = 'update';
    document.getElementById('submitBtn').textContent = 'Update Pet';
    document.getElementById('petID').value     = row.PetID;
    document.getElementById('fPetName').value  = row.PetName;
    document.getElementById('fClientID').value = row.ClientID;
    document.getElementById('fSpecies').value  = row.Species;
    document.getElementById('fBreed').value    = row.Breed;
    document.getElementById('fGender').value   = row.Gender;
    document.getElementById('fDOB').value      = row.DateOfBirth ?? '';
    document.getElementById('fColor').value    = row.Color;
    document.getElementById('fWeight').value   = row.Weight ?? '';
    document.getElementById('fIsActive').value = row.IsActive;
    document.getElementById('petStatusField').style.display = 'block';
    new bootstrap.Modal(document.getElementById('petModal')).show();
}
</script>

<?php include('footer.php'); ?>