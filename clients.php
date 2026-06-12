<?php
require_once 'guard.php';
include('dbconnect.php');

/* =========================
   ADD / UPDATE CLIENT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $phone = trim($_POST['Phone'] ?? '');
        if ($phone !== '' && !preg_match('/^[0-9]{11}$/', $phone)) {
            header("Location: clients.php?msg_err=" . urlencode("Phone number must be exactly 11 digits (e.g. 09xxxxxxxxx)."));
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO clients (FirstName,LastName,Email,Phone,Address) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssss", $_POST['FirstName'], $_POST['LastName'], $_POST['Email'], $_POST['Phone'], $_POST['Address']);
        $stmt->execute();
        $stmt->close();
        header("Location: clients.php?msg=Client added successfully.");
        exit;
    }

    if ($action === 'update') {
        $phone = trim($_POST['Phone'] ?? '');
        if ($phone !== '' && !preg_match('/^[0-9]{11}$/', $phone)) {
            header("Location: clients.php?msg_err=" . urlencode("Phone number must be exactly 11 digits (e.g. 09xxxxxxxxx)."));
            exit;
        }
        $id           = intval($_POST['ClientID']);
        $isActive     = intval($_POST['IsActive'] ?? 1);
        $updateReason = trim($_POST['UpdateReason'] ?? '');
        $stmt = $conn->prepare("UPDATE clients SET FirstName=?,LastName=?,Email=?,Phone=?,Address=?,IsActive=?,UpdateReason=? WHERE ClientID=?");
        $stmt->bind_param("sssssisi", $_POST['FirstName'], $_POST['LastName'], $_POST['Email'], $_POST['Phone'], $_POST['Address'], $isActive, $updateReason, $id);
        $stmt->execute();
        $stmt->close();
        $conn->query("UPDATE pets SET IsActive=$isActive WHERE ClientID=$id");

        // If client is being set to Inactive, cancel their scheduled appointments
        if ($isActive === 0) {
            $conn->query("UPDATE appointments SET Status='Cancelled' WHERE ClientID=$id AND Status='Scheduled' AND IsDeleted=0");
        }

        header("Location: clients.php?msg=Client updated successfully.");
        exit;
    }
}

/* =========================
   SOFT DELETE CLIENT (ALLOW ONLY INACTIVE)
========================= */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Check client status
    $check = $conn->query("SELECT IsActive FROM clients WHERE ClientID=$id");

    if ($check && $check->num_rows > 0) {
        $client = $check->fetch_assoc();

        // Prevent deleting active clients
        if (intval($client['IsActive']) === 1) {
            header("Location: clients.php?msg_err=" . urlencode("Cannot remove an active client. Set the client to Inactive first."));
            exit;
        }

        // Soft delete client
        $conn->query("UPDATE clients SET IsDeleted=1 WHERE ClientID=$id");

        // Soft delete all associated pets
        $conn->query("UPDATE pets SET IsDeleted=1 WHERE ClientID=$id");

        // Cancel all scheduled appointments
        $conn->query("UPDATE appointments SET Status='Cancelled' WHERE ClientID=$id AND Status='Scheduled' AND IsDeleted=0");

        header("Location: clients.php?msg=Client removed. Scheduled appointments have been cancelled and their time slots are now available.");
        exit;
    }
}

/* =========================
   AJAX: GET PETS BY CLIENT
========================= */
if (isset($_GET['get_pets'])) {
    include('dbconnect.php');
    $cid  = intval($_GET['cid']);
    $pets = $conn->query("SELECT PetID, PetName, Species, Breed, Gender, DateOfBirth, Weight, IsActive FROM pets WHERE ClientID=$cid AND IsDeleted=0 ORDER BY PetName")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    exit(json_encode($pets));
}

/* =========================
   PAGE DATA
========================= */
include('header.php');
$totalClients = $conn->query("SELECT COUNT(*) FROM clients WHERE IsActive=1 AND IsDeleted=0")->fetch_row()[0];

// Date range filter (filters by Date Registered)
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$dateSql  = '';
if ($dateFrom !== '') $dateSql .= " AND DATE(CreatedAt) >= '" . $conn->real_escape_string($dateFrom) . "'";
if ($dateTo   !== '') $dateSql .= " AND DATE(CreatedAt) <= '" . $conn->real_escape_string($dateTo) . "'";
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Clients / Owners</h1>
        <p><?= $totalClients ?> active clients registered</p>
    </div>
    <div class="page-header-actions">
        <form method="get" class="date-filter-form">
            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="date-filter-input" title="Date registered from">
            <span class="date-filter-sep">to</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="date-filter-input" title="Date registered to">
            <button type="submit" class="btn-date-filter" title="Filter by date registered"><i class="bi bi-funnel-fill"></i></button>
            <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
            <a href="clients.php" class="btn-date-clear" title="Clear date filter"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
        <div class="export-dropdown-wrap">
            <button class="btn-export" onclick="toggleExportMenu('exportMenuClients',this)">
                <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
            </button>
            <div class="export-menu" id="exportMenuClients">
                <div class="export-menu-label">Export As</div>
                <div class="export-menu-item" onclick="exportCSV('.modern-table','clients_list');document.getElementById('exportMenuClients').classList.remove('show');">
                    <div class="ei-icon ei-csv"><i class="bi bi-filetype-csv"></i></div> CSV
                </div>
                <div class="export-menu-item" onclick="exportXLSX('.modern-table','clients_list',true);document.getElementById('exportMenuClients').classList.remove('show');">
                    <div class="ei-icon ei-xls"><i class="bi bi-file-earmark-spreadsheet"></i></div> XLS
                </div>
                <div class="export-menu-item" onclick="exportXLSX('.modern-table','clients_list',false);document.getElementById('exportMenuClients').classList.remove('show');">
                    <div class="ei-icon ei-xlsx"><i class="bi bi-file-earmark-spreadsheet-fill"></i></div> XLSX
                </div>
                <div class="export-menu-item" onclick="exportDOCX('.modern-table','clients_list','Clients List');document.getElementById('exportMenuClients').classList.remove('show');">
                    <div class="ei-icon ei-docx"><i class="bi bi-file-earmark-word"></i></div> DOCX
                </div>
                <div class="export-menu-item" onclick="exportPDF('.modern-table');document.getElementById('exportMenuClients').classList.remove('show');">
                    <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF
                </div>
            </div>
        </div>
        <button class="btn-main btn-teal" data-bs-toggle="modal" data-bs-target="#clientModal" onclick="openAdd()"><i class="bi bi-plus-lg"></i> Add Client</button></div>
</div>

<?php if (isset($_GET['msg'])): ?><div class="alert-modern alert-success-modern"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
<?php if (isset($_GET['msg_err'])): ?><div class="alert-modern" style="background:#f8d7da; color:#842029; border:1px solid #f5c2c7; padding:1rem; border-radius:4px; margin-bottom:1.5rem; display:flex; align-items:center; gap:10px;"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($_GET['msg_err']) ?></div><?php endif; ?>

<div class="card">
<div class="card-header"><span class="card-header-title"><i class="bi bi-people-fill me-2" style="color:#10b981;"></i>Clients List</span></div>    <div class="card-body p-0">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Client ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Date Registered</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $res = $conn->query("SELECT * FROM clients WHERE IsDeleted = 0 $dateSql ORDER BY ClientID DESC");
                $highlightID = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
                if ($res->num_rows == 0) {
                    echo "<tr><td colspan='8'>No clients found</td></tr>";
                }
                while ($row = $res->fetch_assoc()): $formattedId = 'CLT-' . sprintf('%04d', $row['ClientID']);
                ?>
                    <tr id="row-cln-<?= $row['ClientID'] ?>" class="<?= ($highlightID && $highlightID==$row['ClientID']) ? 'search-highlight-row' : '' ?>">
                        <td><div style="font-weight:700; font-size:13px; color:var(--teal);"><?= $formattedId ?></div></td>
                        <td><?= htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']) ?></td>
                        <td><?= htmlspecialchars($row['Email']) ?></td>
                        <td><?= htmlspecialchars($row['Phone']) ?></td>
                        <td><?= htmlspecialchars($row['Address']) ?></td>
                        <td style="font-size:13px; color:var(--muted);">
                            <?= $row['CreatedAt'] ? date('M d, Y', strtotime($row['CreatedAt'])) : '—' ?>
                        </td>
                        <td><?= $row['IsActive'] ? '<span class="badge-modern badge-active">Active</span>' : '<span class="badge-modern badge-inactive">Inactive</span>' ?></td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <button class="btn-icon btn-icon-view" onclick="viewClient(<?= htmlspecialchars(json_encode($row)) ?>)" title="View Details"><i class="bi bi-eye"></i></button>
                                <button class="btn-icon btn-icon-edit" onclick="openEdit(<?= htmlspecialchars(json_encode($row)) ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                                <a href="clients.php?delete=<?= $row['ClientID'] ?>" class="btn-icon btn-icon-delete" title="Remove" onclick="return confirm('Remove this client?')"><i class="bi bi-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- VIEW CLIENT MODAL -->
<div class="modal fade" id="viewClientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;">

            <!-- Custom header with print button -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:#fff;border-bottom:1px solid var(--border);">
                <span style="font-weight:700;font-size:14px;color:var(--dark);display:flex;align-items:center;gap:8px;">
                    <i class="bi bi-person-lines-fill" style="color:var(--teal);"></i> Client Details
                </span>
                <div style="display:flex;gap:8px;align-items:center;">
                    <div class="modal-export-wrap">
                        <button type="button" class="btn-modal-export" onclick="toggleExportMenu('clientViewExportMenu',this)">
                            <i class="bi bi-download"></i> Export <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="modal-export-menu" id="clientViewExportMenu">
                            <div class="export-menu-label">Export As</div>
                            <div class="export-menu-item" onclick="window.print();document.getElementById('clientViewExportMenu').classList.remove('show');">
                                <div class="ei-icon ei-pdf"><i class="bi bi-file-earmark-pdf"></i></div> PDF / Print
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size:11px;"></button>
                </div>
            </div>

            <div class="modal-body p-0" id="clientPrintArea">
                <!-- Print-only header (hidden on screen) -->
                <div class="client-print-header" style="display:none;padding:22px 28px 16px;border-bottom:2px dashed #e2e8f0;text-align:center;margin-bottom:8px;">
                    <img src="logo1.png" alt="Heartside Vet" style="width:56px;height:56px;object-fit:contain;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;">
                    <div style="font-size:20px;font-weight:800;color:#1e3a5f;letter-spacing:.4px;line-height:1.2;">Heartside Vet Clinic</div>
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px;margin-top:4px;font-weight:600;">Client Record</div>
                    <div style="font-size:13px;color:#334155;margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;">Client: <strong id="clientPrintName" style="color:#1e3a5f;"></strong> &nbsp;&nbsp;·&nbsp;&nbsp; ID: <strong id="clientPrintID" style="color:#1e3a5f;"></strong></div>
                </div>

                <!-- Client info band -->
                <div class="vm-header-band row g-3" style="margin:0;padding:16px 20px;">
                    <div class="col-6">
                        <div class="vm-label">Client ID</div>
                        <div class="vm-value-id" id="vClientID">—</div>
                    </div>
                    <div class="col-6">
                        <div class="vm-label">Status</div>
                        <div id="vClientStatus">—</div>
                    </div>
                    <div class="col-12">
                        <div class="vm-label">Full Name</div>
                        <div class="vm-value-lg" id="vClientName">—</div>
                    </div>
                </div>

                <!-- Contact details -->
                <div class="row g-3" style="margin:0;padding:0 20px 16px;">
                    <div class="col-md-6">
                        <div class="vm-label"><i class="bi bi-envelope me-1"></i>Email</div>
                        <div class="vm-value" id="vClientEmail">—</div>
                    </div>
                    <div class="col-md-6">
                        <div class="vm-label"><i class="bi bi-telephone me-1"></i>Phone</div>
                        <div class="vm-value" id="vClientPhone">—</div>
                    </div>
                    <div class="col-12">
                        <div class="vm-label"><i class="bi bi-geo-alt me-1"></i>Address</div>
                        <div class="vm-notes-block" id="vClientAddress">—</div>
                    </div>
                    <div class="col-md-6">
                        <div class="vm-label"><i class="bi bi-calendar me-1"></i>Date Registered</div>
                        <div class="vm-value" id="vClientRegistered">—</div>
                    </div>
                    <!-- UPDATE HISTORY -->
                    <div class="col-6" id="vClientUpdatedWrap" style="display:none;">
                        <div class="vm-label"><i class="bi bi-clock-history me-1"></i>Last Updated</div>
                        <div class="vm-value" id="vClientUpdated" style="font-size:13px;">—</div>
                    </div>
                    <div class="col-6" id="vClientReasonWrap" style="display:none;">
                        <div class="vm-label"><i class="bi bi-pencil-square me-1"></i>Reason for Update</div>
                        <div class="vm-notes-block" id="vClientReason" style="font-size:13px;">—</div>
                    </div>
                </div>

                <!-- Pets section -->
                <div style="border-top:1px solid var(--border);padding:14px 20px 18px;">
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:10px;">
                        <i class="bi bi-heart-fill me-1" style="color:#db2777;"></i> Registered Pets
                    </div>
                    <div id="vClientPets">
                        <div style="text-align:center;padding:16px;color:var(--muted);font-size:13px;">Loading…</div>
                    </div>
                </div>

                <!-- Print footer -->
                <div class="client-print-footer" style="display:none;border-top:2px dashed #e2e8f0;margin:20px 24px 0;padding:12px 0 4px;text-align:center;">
                    <div style="font-size:10px;color:#94a3b8;letter-spacing:.3px;">Heartside Vet Clinic Management System &nbsp;·&nbsp; <span id="clientPrintDate"></span></div>
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

    /* ── Client Record print ── */
    #clientPrintArea, #clientPrintArea * { visibility: visible; }
    #clientPrintArea {
        position: fixed; top: 0; left: 0; width: 100%;
        padding: 40px; background: #fff; box-sizing: border-box;
    }
    .client-print-header, .client-print-footer { display: block !important; }

    /* ── Shared modal cleanup ── */
    .modal, .modal-dialog, .modal-content {
        box-shadow: none !important; border: none !important;
    }
    .vm-header-band { border: none !important; background: transparent !important; }
    .badge-modern { border: 1px solid #ccc !important; }
}
</style>

<!-- ADD / EDIT CLIENT MODAL -->
<div class="modal fade" id="clientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="ClientID" id="clientID">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="FirstName" id="fFirstName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="LastName" id="fLastName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span style="color:#ef4444;">*</span></label>
                            <input type="email" name="Email" id="fEmail" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone <span style="color:#ef4444;">*</span></label>
                            <input type="tel" name="Phone" id="fPhone" class="form-control"
                                   maxlength="11" inputmode="numeric"
                                   pattern="[0-9]{11}" title="Phone number must be exactly 11 digits (e.g. 09xxxxxxxxx)"
                                   placeholder="09xxxxxxxxx" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address <span style="color:#ef4444;">*</span></label>
                            <textarea name="Address" id="fAddress" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-md-4" id="statusField" style="display:none;">
                            <label class="form-label">Status</label>
                            <select name="IsActive" id="fIsActive" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <!-- EDIT-ONLY FIELDS -->
                        <div class="col-md-8" id="updateReasonField" style="display:none;">
                            <label class="form-label"><i class="bi bi-pencil-square me-1" style="color:var(--teal);"></i>Reason for Updating <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="UpdateReason" id="fUpdateReason" class="form-control" placeholder="e.g. Changed phone number, address correction…">
                        </div>
                        <div class="col-md-4" id="updatedAtField" style="display:none;">
                            <label class="form-label"><i class="bi bi-clock me-1" style="color:var(--muted);"></i>Date Updated</label>
                            <input type="text" id="fUpdatedAt" class="form-control" readonly style="background:#f8fafc; color:var(--muted); font-size:13px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-main btn-teal" id="submitBtn">Save Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // ── Phone digit-only filter ──
    (function() {
        const ph = document.getElementById('fPhone');
        if (!ph) return;
        ph.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
        });
        ph.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text');
            this.value = (this.value + pasted).replace(/[^0-9]/g, '').slice(0, 11);
        });
    })();

    function openAdd() {
        document.getElementById('modalTitle').textContent = 'Add Client';
        document.getElementById('formAction').value = 'add';
        document.getElementById('submitBtn').textContent = 'Save Client';
        ['clientID', 'fFirstName', 'fLastName', 'fEmail', 'fPhone', 'fAddress'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('statusField').style.display = 'none';
        document.getElementById('updateReasonField').style.display = 'none';
        document.getElementById('updatedAtField').style.display = 'none';
        document.getElementById('fUpdateReason').required = false;
    }

    function openEdit(r) {
        document.getElementById('modalTitle').textContent = 'Edit Client';
        document.getElementById('formAction').value = 'update';
        document.getElementById('submitBtn').textContent = 'Update Client';
        document.getElementById('clientID').value   = r.ClientID;
        document.getElementById('fFirstName').value = r.FirstName;
        document.getElementById('fLastName').value  = r.LastName;
        document.getElementById('fEmail').value     = r.Email;
        document.getElementById('fPhone').value     = r.Phone;
        document.getElementById('fAddress').value   = r.Address;
        document.getElementById('fIsActive').value  = r.IsActive;
        document.getElementById('statusField').style.display = 'block';

        // Update reason + date
        document.getElementById('updateReasonField').style.display = 'block';
        document.getElementById('updatedAtField').style.display    = 'block';
        document.getElementById('fUpdateReason').value   = '';
        document.getElementById('fUpdateReason').required = true;
        document.getElementById('fUpdatedAt').value = new Date().toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });

        bootstrap.Modal.getOrCreateInstance(document.getElementById('clientModal')).show();
    }

    const speciesIconMap = {Dog:'🐕',Cat:'🐈',Bird:'🐦',Rabbit:'🐇',Snake:'🐍',Mouse:'🐁'};

    async function viewClient(r) {
        document.getElementById('vClientID').textContent = 'CLT-' + String(r.ClientID).padStart(4, '0');
        document.getElementById('vClientName').textContent = r.FirstName + ' ' + r.LastName;
        document.getElementById('vClientEmail').textContent = r.Email || '—';
        document.getElementById('vClientPhone').textContent = r.Phone || '—';
        document.getElementById('vClientAddress').textContent = r.Address || '—';
        document.getElementById('vClientStatus').innerHTML = r.IsActive == 1 ?
            '<span class="badge-modern badge-active">Active</span>' :
            '<span class="badge-modern badge-inactive">Inactive</span>';
        document.getElementById('vClientRegistered').textContent = r.CreatedAt ?
            new Date(r.CreatedAt).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' }) : '—';

        // Show update history if available
        const updWrap    = document.getElementById('vClientUpdatedWrap');
        const reasonWrap = document.getElementById('vClientReasonWrap');
        if (r.UpdatedAt && r.UpdateReason) {
            document.getElementById('vClientUpdated').textContent = new Date(r.UpdatedAt).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
            document.getElementById('vClientReason').textContent  = r.UpdateReason;
            updWrap.style.display    = '';
            reasonWrap.style.display = '';
        } else {
            updWrap.style.display    = 'none';
            reasonWrap.style.display = 'none';
        }

        // Load pets
        const petsEl = document.getElementById('vClientPets');
        petsEl.innerHTML = '<div style="text-align:center;padding:16px;color:var(--muted);font-size:13px;">Loading…</div>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('viewClientModal')).show();

        try {
            const res  = await fetch(`clients.php?get_pets=1&cid=${r.ClientID}`);
            const pets = await res.json();
            if (!pets || !pets.length) {
                petsEl.innerHTML = '<div style="text-align:center;padding:14px;color:var(--muted);font-size:13px;"><i class="bi bi-heart" style="display:block;font-size:22px;margin-bottom:6px;"></i>No pets registered for this client.</div>';
                return;
            }
            petsEl.innerHTML = pets.map(p => {
                const icon = speciesIconMap[p.Species] || '🐾';
                const age  = p.DateOfBirth
                    ? Math.floor((Date.now() - new Date(p.DateOfBirth)) / 31536000000) + ' yrs'
                    : '—';
                const statusBadge = p.IsActive == 1
                    ? '<span class="badge-modern badge-active" style="font-size:10px;">Active</span>'
                    : '<span class="badge-modern badge-inactive" style="font-size:10px;">Inactive</span>';
                return `
                <div style="display:flex;align-items:center;gap:12px;padding:9px 12px;border:1px solid var(--border);border-radius:10px;margin-bottom:7px;background:#fafbfc;">
                    <div style="width:40px;height:40px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">${icon}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;font-size:13px;color:var(--dark);">${p.PetName} ${statusBadge}</div>
                        <div style="font-size:11px;color:var(--muted);margin-top:1px;">${p.Species || '—'} · ${p.Breed || '—'} · ${p.Gender || '—'}</div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:11px;color:var(--muted);">Age</div>
                        <div style="font-size:13px;font-weight:600;color:var(--dark);">${age}</div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:11px;color:var(--muted);">Weight</div>
                        <div style="font-size:13px;font-weight:600;color:var(--dark);">${p.Weight ? p.Weight + ' kg' : '—'}</div>
                    </div>
                </div>`;
            }).join('');
        } catch(e) {
            petsEl.innerHTML = '<div style="text-align:center;padding:14px;color:var(--muted);font-size:13px;">Could not load pets.</div>';
        }
    }

    function printClientCard() {
        document.getElementById('clientPrintName').textContent = document.getElementById('vClientName').textContent;
        document.getElementById('clientPrintID').textContent   = document.getElementById('vClientID').textContent;
        document.getElementById('clientPrintDate').textContent = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
        window.print();
    }
</script>
<?php include('footer.php'); ?>