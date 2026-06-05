<?php
include('dbconnect.php');

/* =========================
   ADD / UPDATE CLIENT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO clients (FirstName,LastName,Email,Phone,Address) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssss", $_POST['FirstName'], $_POST['LastName'], $_POST['Email'], $_POST['Phone'], $_POST['Address']);
        $stmt->execute();
        $stmt->close();
        header("Location: clients.php?msg=Client added successfully.");
        exit;
    }

    if ($action === 'update') {
        $id = intval($_POST['ClientID']);
        $isActive = intval($_POST['IsActive'] ?? 1);
        $stmt = $conn->prepare("UPDATE clients SET FirstName=?,LastName=?,Email=?,Phone=?,Address=?,IsActive=? WHERE ClientID=?");
        $stmt->bind_param("sssssii", $_POST['FirstName'], $_POST['LastName'], $_POST['Email'], $_POST['Phone'], $_POST['Address'], $isActive, $id);
        $stmt->execute();
        $stmt->close();
        $conn->query("UPDATE pets SET IsActive=$isActive WHERE ClientID=$id");
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

        header("Location: clients.php?msg=Inactive client and associated pets removed successfully.");
        exit;
    }
}

/* =========================
   PAGE DATA
========================= */
include('header.php');
$totalClients = $conn->query("SELECT COUNT(*) FROM clients WHERE IsActive=1 AND IsDeleted=0")->fetch_row()[0];
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Clients / Owners</h1>
        <p><?= $totalClients ?> active clients registered</p>
    </div>
    <div class="page-header-actions"><button class="btn-main btn-teal" data-bs-toggle="modal" data-bs-target="#clientModal" onclick="openAdd()"><i class="bi bi-plus-lg"></i> Add Client</button></div>
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
                $res = $conn->query("SELECT * FROM clients WHERE IsDeleted = 0 ORDER BY ClientID DESC");
                $highlightID = isset($_GET['highlight']) ? intval($_GET['highlight']) : 0;
                if ($res->num_rows == 0) {
                    echo "<tr><td colspan='8'>No clients found</td></tr>";
                }
                while ($row = $res->fetch_assoc()): $formattedId = 'CLN-' . sprintf('%05d', $row['ClientID']);
                ?>
                    <tr id="row-cln-<?= $row['ClientID'] ?>" class="<?= ($highlightID && $highlightID==$row['ClientID']) ? 'search-highlight-row' : '' ?>">
                        <td><?= $formattedId ?></td>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-lines-fill me-2" style="color:var(--teal);"></i>Client Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 p-3 rounded mb-3" style="background:#f8fafc; border-left:4px solid var(--teal);">
                    <div class="col-6">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase;">Client ID</div>
                        <div style="font-weight:700; font-size:15px; color:var(--teal);" id="vClientID">—</div>
                    </div>
                    <div class="col-6">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase;">Status</div>
                        <div id="vClientStatus">—</div>
                    </div>
                    <div class="col-12">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase;">Full Name</div>
                        <div style="font-weight:700; font-size:17px;" id="vClientName">—</div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;"><i class="bi bi-envelope me-1"></i>Email</div>
                        <div id="vClientEmail" style="font-size:14px;">—</div>
                    </div>
                    <div class="col-12">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;"><i class="bi bi-telephone me-1"></i>Phone</div>
                        <div id="vClientPhone" style="font-size:14px;">—</div>
                    </div>
                    <div class="col-12">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;"><i class="bi bi-geo-alt me-1"></i>Address</div>
                        <div id="vClientAddress" class="p-3 rounded" style="background:#f1f5f9; font-size:13px;">—</div>
                    </div>
                    <div class="col-12">
                        <div style="font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; margin-bottom:4px;"><i class="bi bi-calendar me-1"></i>Date Registered</div>
                        <div id="vClientRegistered" style="font-size:14px;">—</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<!-- ADD / EDIT CLIENT MODAL -->
<div class="modal fade" id="clientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Client</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" id="formAction" value="add"><input type="hidden" name="ClientID" id="clientID">
                <div class="modal-body">
                    <input type="text" name="FirstName" id="fFirstName" placeholder="First Name" class="form-control mb-2">
                    <input type="text" name="LastName" id="fLastName" placeholder="Last Name" class="form-control mb-2">
                    <input type="email" name="Email" id="fEmail" placeholder="Email" class="form-control mb-2">
                    <input type="text" name="Phone" id="fPhone" placeholder="Phone" class="form-control mb-2">
                    <textarea name="Address" id="fAddress" placeholder="Address" class="form-control mb-2"></textarea>
                    <div id="statusField" style="display:none;"><select name="IsActive" id="fIsActive" class="form-select">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn-main btn-outline" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-main btn-teal" id="submitBtn">Save</button></div>
            </form>
        </div>
    </div>
</div>

<script>
    function openAdd() {
        document.getElementById('formAction').value = 'add';
        document.getElementById('modalTitle').innerText = 'Add Client';
        document.getElementById('submitBtn').innerText = 'Save Client';
        document.getElementById('statusField').style.display = 'none';
        ['clientID', 'fFirstName', 'fLastName', 'fEmail', 'fPhone', 'fAddress'].forEach(id => {
            document.getElementById(id).value = '';
        });
    }

    function openEdit(r) {
        document.getElementById('formAction').value = 'update';
        document.getElementById('modalTitle').innerText = 'Edit Client';
        document.getElementById('submitBtn').innerText = 'Update Client';
        document.getElementById('statusField').style.display = 'block';
        document.getElementById('clientID').value = r.ClientID;
        document.getElementById('fFirstName').value = r.FirstName;
        document.getElementById('fLastName').value = r.LastName;
        document.getElementById('fEmail').value = r.Email;
        document.getElementById('fPhone').value = r.Phone;
        document.getElementById('fAddress').value = r.Address;
        document.getElementById('fIsActive').value = r.IsActive;
        new bootstrap.Modal(document.getElementById('clientModal')).show();
    }

    function viewClient(r) {
        document.getElementById('vClientID').textContent = 'CLN-' + String(r.ClientID).padStart(5, '0');
        document.getElementById('vClientName').textContent = r.FirstName + ' ' + r.LastName;
        document.getElementById('vClientEmail').textContent = r.Email || '—';
        document.getElementById('vClientPhone').textContent = r.Phone || '—';
        document.getElementById('vClientAddress').textContent = r.Address || '—';
        document.getElementById('vClientStatus').innerHTML = r.IsActive == 1 ?
            '<span class="badge-modern badge-active">Active</span>' :
            '<span class="badge-modern badge-inactive">Inactive</span>';
        document.getElementById('vClientRegistered').textContent = r.CreatedAt ?
            new Date(r.CreatedAt).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: '2-digit'
            }) :
            '—';
        new bootstrap.Modal(document.getElementById('viewClientModal')).show();
    }
</script>
<?php include('footer.php'); ?>