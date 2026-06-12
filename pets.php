<?php
require_once 'guard.php';
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
        $updateReason = trim($_POST['UpdateReason'] ?? '');
        $stmt = $conn->prepare("UPDATE pets SET ClientID=?,PetName=?,Species=?,Breed=?,Gender=?,DateOfBirth=?,Color=?,Weight=?,IsActive=?,UpdateReason=? WHERE PetID=?");
        $stmt->bind_param("issssssdisi", $cid,$name,$species,$breed,$gender,$dob,$color,$weight,$isActive,$updateReason,$id);
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

// Date range filter (filters by Date Registered)
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$dateSql  = '';
if ($dateFrom !== '') $dateSql .= " AND DATE(p.CreatedAt) >= '" . $conn->real_escape_string($dateFrom) . "'";
if ($dateTo   !== '') $dateSql .= " AND DATE(p.CreatedAt) <= '" . $conn->real_escape_string($dateTo) . "'";
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Patients / Pets</h1>
        <p><?= $totalPets ?> active patients registered</p>
    </div>
    <div class="page-header-actions">
        <form method="get" class="date-filter-form">
            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="date-filter-input" title="Date registered from">
            <span class="date-filter-sep">to</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="date-filter-input" title="Date registered to">
            <button type="submit" class="btn-date-filter" title="Filter by date registered"><i class="bi bi-funnel-fill"></i></button>
            <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
            <a href="pets.php" class="btn-date-clear" title="Clear date filter"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
        <div class="export-dropdown-wrap">
            <button class="btn-export" onclick="toggleExportMenu('exportMenuPets',this)">
                <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
            </button>
            <div class="export-menu" id="exportMenuPets">
                <div class="export-menu-label">Export As</div>
                <div class="export-menu-item" onclick="exportCSV('.modern-table','pets_list');document.getElementById('exportMenuPets').classList.remove('show');">
                    <div class="ei-icon ei-csv"><i class="bi bi-filetype-csv"></i></div> CSV
                </div>
                <div class="export-menu-item" onclick="exportXLSX('.modern-table','pets_list',true);document.getElementById('exportMenuPets').classList.remove('show');">
                    <div class="ei-icon ei-xls"><i class="bi bi-file-earmark-spreadsheet"></i></div> XLS
                </div>
                <div class="export-menu-item" onclick="exportXLSX('.modern-table','pets_list',false);document.getElementById('exportMenuPets').classList.remove('show');">
                    <div class="ei-icon ei-xlsx"><i class="bi bi-file-earmark-spreadsheet-fill"></i></div> XLSX
                </div>
                <div class="export-menu-item" onclick="exportDOCX('.modern-table','pets_list','Pets / Patients List');document.getElementById('exportMenuPets').classList.remove('show');">
                    <div class="ei-icon ei-docx"><i class="bi bi-file-earmark-word"></i></div> DOCX
                </div>
                <div class="export-menu-item" onclick="exportPDF('.modern-table');document.getElementById('exportMenuPets').classList.remove('show');">
                    <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF
                </div>
            </div>
        </div>
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
            $res = $conn->query("SELECT p.*, CONCAT(c.FirstName,' ',c.LastName) AS OwnerName FROM pets p JOIN clients c ON p.ClientID=c.ClientID WHERE p.IsDeleted=0 $dateSql ORDER BY p.CreatedAt DESC");
            $highlightPetID = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
            if ($res->num_rows == 0) echo '<tr class="empty-row"><td colspan="9"><i class="bi bi-heart" style="font-size:28px; display:block; margin-bottom:10px;"></i>No pets registered yet.</td></tr>';
            while ($row = $res->fetch_assoc()):
                $age = $row['DateOfBirth'] ? floor((time() - strtotime($row['DateOfBirth'])) / 31536000) . 'y' : '—';
                $isActive = $row['IsActive'];
                $formattedPetId = 'PET-' . sprintf('%04d', $row['PetID']);
            ?>
            <tr id="row-pet-<?= $row['PetID'] ?>" class="<?= ($highlightPetID && $highlightPetID==$row['PetID']) ? 'search-highlight-row' : '' ?>">
                <td><div style="font-weight:700; font-size:13px; color:var(--teal);"><?= $formattedPetId ?></div></td>
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
    <div class="modal-content" style="border-radius:14px;overflow:hidden;">

      <!-- Custom header with print button -->
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:#fff;border-bottom:1px solid var(--border);">
        <span style="font-weight:700;font-size:14px;color:var(--dark);display:flex;align-items:center;gap:8px;">
          <i class="bi bi-heart-fill" style="color:#db2777;"></i> Pet Details
        </span>
        <div style="display:flex;gap:8px;align-items:center;">
          <div class="modal-export-wrap">
            <button type="button" class="btn-modal-export" onclick="toggleExportMenu('petViewExportMenu',this)">
                <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
            </button>
            <div class="modal-export-menu" id="petViewExportMenu">
                <div class="export-menu-label">Export As</div>
                <div class="export-menu-item" onclick="window.print();document.getElementById('petViewExportMenu').classList.remove('show');">
                    <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF / Print
                </div>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size:11px;"></button>
        </div>
      </div>

      <div class="modal-body p-0" id="petPrintArea">
        <!-- Print-only header -->
        <div class="pet-print-header" style="display:none;padding:22px 28px 16px;border-bottom:2px dashed #e2e8f0;text-align:center;margin-bottom:8px;">
          <img src="logo1.png" alt="Heartside Vet" style="width:56px;height:56px;object-fit:contain;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;">
          <div style="font-size:20px;font-weight:800;color:#1e3a5f;letter-spacing:.4px;line-height:1.2;">Heartside Vet Clinic</div>
          <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px;margin-top:4px;font-weight:600;">Patient Record</div>
          <div style="font-size:13px;color:#334155;margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;">Patient: <strong id="petPrintName" style="color:#1e3a5f;"></strong> &nbsp;&nbsp;·&nbsp;&nbsp; Owner: <strong id="petPrintOwner" style="color:#1e3a5f;"></strong></div>
        </div>

        <div style="padding:16px 20px;">
          <!-- Pet name band -->
          <div class="vm-header-band" style="border-left-color:#db2777;margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:14px;">
              <div style="font-size:38px;line-height:1;" id="vPetIcon">🐾</div>
              <div>
                <div class="vm-label">Pet Name</div>
                <div class="vm-value-lg" id="vPetName">—</div>
                <div class="vm-label mt-1" id="vPetSpecies" style="font-size:12px;text-transform:none;letter-spacing:0;">—</div>
              </div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-6"><div class="vm-label">Pet ID</div><div class="vm-value-id" id="vPetID">—</div></div>
            <div class="col-6"><div class="vm-label">Status</div><div id="vPetStatus">—</div></div>
            <div class="col-6"><div class="vm-label">Owner</div><div class="vm-value" style="font-weight:600;" id="vPetOwner">—</div></div>
            <div class="col-6"><div class="vm-label">Gender</div><div class="vm-value" id="vPetGender">—</div></div>
            <div class="col-6"><div class="vm-label">Breed</div><div class="vm-value" id="vPetBreed">—</div></div>
            <div class="col-6"><div class="vm-label">Date of Birth</div><div class="vm-value" id="vPetDOB">—</div></div>
            <div class="col-6"><div class="vm-label">Color / Markings</div><div class="vm-value" id="vPetColor">—</div></div>
            <div class="col-6"><div class="vm-label">Weight</div><div class="vm-value" id="vPetWeight">—</div></div>
            <div class="col-6"><div class="vm-label">Date Registered</div><div class="vm-value" id="vPetRegistered">—</div></div>
            <div class="col-6" id="vPetUpdatedWrap" style="display:none;"><div class="vm-label"><i class="bi bi-clock-history me-1"></i>Last Updated</div><div class="vm-value" id="vPetUpdated" style="font-size:13px;">—</div></div>
            <div class="col-6" id="vPetReasonWrap" style="display:none;"><div class="vm-label"><i class="bi bi-pencil-square me-1"></i>Reason for Update</div><div class="vm-notes-block" id="vPetReason" style="font-size:13px;">—</div></div>
          </div>
        </div>

        <!-- Print-only footer -->
        <div class="pet-print-footer" style="display:none;border-top:2px dashed #e2e8f0;margin:20px 24px 0;padding:12px 0 4px;text-align:center;">
          <div style="font-size:10px;color:#94a3b8;letter-spacing:.3px;">Heartside Vet Clinic Management System &nbsp;·&nbsp; <span id="petPrintDate"></span></div>
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

    /* ── Patient Record print ── */
    #petPrintArea, #petPrintArea * { visibility: visible; }
    #petPrintArea {
        position: fixed; top: 0; left: 0; width: 100%;
        padding: 40px; background: #fff; box-sizing: border-box;
    }
    .pet-print-header, .pet-print-footer { display: block !important; }

    /* ── Shared modal cleanup ── */
    .modal, .modal-dialog, .modal-content {
        box-shadow: none !important; border: none !important;
    }
    .vm-header-band { border: none !important; background: transparent !important; }
    .badge-modern { border: 1px solid #ccc !important; }
}
</style>

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
                    <label class="form-label">Pet Name <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="PetName" id="fPetName" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner <span style="color:#ef4444;">*</span></label>
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
                <div class="col-md-8" id="petUpdateReasonField" style="display:none;">
                    <label class="form-label"><i class="bi bi-pencil-square me-1" style="color:var(--teal);"></i>Reason for Updating <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="UpdateReason" id="fPetUpdateReason" class="form-control" placeholder="e.g. Weight updated, breed corrected…">
                </div>
                <div class="col-md-4" id="petUpdatedAtField" style="display:none;">
                    <label class="form-label"><i class="bi bi-clock me-1" style="color:var(--muted);"></i>Date Updated</label>
                    <input type="text" id="fPetUpdatedAt" class="form-control" readonly style="background:#f8fafc; color:var(--muted); font-size:13px;">
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
    document.getElementById('vPetID').textContent       = 'PET-' + String(row.PetID).padStart(4, '0');
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
    // Show update history
    const updWrap    = document.getElementById('vPetUpdatedWrap');
    const reasonWrap = document.getElementById('vPetReasonWrap');
    if (row.UpdatedAt && row.UpdateReason) {
        document.getElementById('vPetUpdated').textContent = new Date(row.UpdatedAt).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
        document.getElementById('vPetReason').textContent  = row.UpdateReason;
        updWrap.style.display    = '';
        reasonWrap.style.display = '';
    } else {
        updWrap.style.display    = 'none';
        reasonWrap.style.display = 'none';
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('viewPetModal')).show();
}

function printPetCard() {
    document.getElementById('petPrintName').textContent  = document.getElementById('vPetName').textContent;
    document.getElementById('petPrintOwner').textContent = document.getElementById('vPetOwner').textContent;
    document.getElementById('petPrintDate').textContent  = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    window.print();
}

function openAdd() {
    document.getElementById('modalTitle').textContent = 'Add New Pet';
    document.getElementById('formAction').value = 'add';
    document.getElementById('submitBtn').textContent = 'Save Pet';
    ['petID','fPetName','fSpecies','fBreed','fDOB','fColor','fWeight'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('fGender').value = 'Unknown';
    document.getElementById('fClientID').value = '';
    document.getElementById('petStatusField').style.display = 'none';
    document.getElementById('petUpdateReasonField').style.display = 'none';
    document.getElementById('petUpdatedAtField').style.display    = 'none';
    document.getElementById('fPetUpdateReason').required = false;
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
    document.getElementById('petUpdateReasonField').style.display = 'block';
    document.getElementById('petUpdatedAtField').style.display    = 'block';
    document.getElementById('fPetUpdateReason').value   = '';
    document.getElementById('fPetUpdateReason').required = true;
    document.getElementById('fPetUpdatedAt').value = new Date().toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
    bootstrap.Modal.getOrCreateInstance(document.getElementById('petModal')).show();
}
</script>

<?php include('footer.php'); ?>