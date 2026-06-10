<?php 
// Detect if we are rendering the welcome page to toggle layout container bounds
$isWelcomePage = (basename($_SERVER['PHP_SELF']) == 'welcome.php');
?>

<?php if (!$isWelcomePage): ?> <!-- Safely hide dashboard div closes if on welcome page -->
</div><!-- end #main-content -->
</div><!-- end #main-wrapper -->
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Keep script initializations contained only within working application frames
<?php if (!$isWelcomePage): ?>
let sidebarOpen = true;
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const wrapper = document.getElementById('main-wrapper');
    const topbar  = document.getElementById('topbar');
    const overlay = document.getElementById('sidebar-overlay');

    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    } else {
        sidebarOpen = !sidebarOpen;
        sidebar.classList.toggle('collapsed', !sidebarOpen);
        wrapper.classList.toggle('full', !sidebarOpen);
        topbar.classList.toggle('full', !sidebarOpen);
    }
}
// ── GLOBAL SEARCH ──
let sfActive = 'all';
let searchTimer = null;

function setSF(f) {
    sfActive = f;
    ['all','clients','pets','appointments','medical','billing','services','lodging'].forEach(k => {
        document.getElementById('sf-'+k).classList.toggle('active', k===f);
    });
    const q = document.getElementById('globalSearch').value;
    if (q.length > 1) runSearch(q);
}

function showDrop() { document.getElementById('searchDropdown').classList.add('show'); }
function hideDrop() { document.getElementById('searchDropdown').classList.remove('show'); }

function runSearch(q) {
    clearTimeout(searchTimer);
    if (q.length < 2) { hideDrop(); return; TYPE; }
    showDrop();
    searchTimer = setTimeout(() => doSearch(q), 220);
}

async function doSearch(q) {
    const res = await fetch(`search_ajax.php?q=${encodeURIComponent(q)}&filter=${sfActive}`);
    const data = await res.json();
    renderResults(data, q);
}

function renderResults(data, q) {
    const el    = document.getElementById('searchResults');
    const empty = document.getElementById('searchEmpty');
    const total = (data.clients||[]).length + (data.pets||[]).length + (data.appointments||[]).length + (data.medical||[]).length + (data.billing||[]).length + (data.services||[]).length + (data.lodging||[]).length;

    if (total === 0) { el.innerHTML=''; empty.style.display='block'; return; }
    empty.style.display = 'none';

    let html = '';
    if ((data.clients||[]).length && ['all','clients'].includes(sfActive)) {
        html += `<div class="search-section-label">Clients</div>`;
        data.clients.forEach(c => {
            html += `<a class="search-item" href="clients.php?highlight=${c.ClientID}">
                <div class="si-icon" style="background:#eff6ff;color:#2563eb;"><i class="bi bi-person-fill"></i></div>
                <div><div class="si-main"><span style="font-size:11px;font-weight:700;color:#2563eb;margin-right:6px;">${hl(c.client_id, q)}</span>${hl(c.name, q)}</div><div class="si-sub">${c.email||''} · ${c.phone||''}</div></div>
            </a>`;
        });
    }
    if ((data.pets||[]).length && ['all','pets'].includes(sfActive)) {
        html += `<div class="search-section-label">Pets</div>`;
        data.pets.forEach(p => {
            html += `<a class="search-item" href="pets.php?highlight=${p.PetID}">
                <div class="si-icon" style="background:#fdf2f8;color:#db2777;"><i class="bi bi-heart-fill"></i></div>
                <div><div class="si-main"><span style="font-size:11px;font-weight:700;color:#db2777;margin-right:6px;">${hl(p.pet_id, q)}</span>${hl(p.name, q)}</div><div class="si-sub">${p.species||''} · Owner: ${p.owner||''}</div></div>
            </a>`;
        });
    }
    if ((data.appointments||[]).length && ['all','appointments'].includes(sfActive)) {
        html += `<div class="search-section-label">Appointments</div>`;
        const apptStatusColor = {Scheduled:'#2563eb',Completed:'#16a34a',Cancelled:'#dc2626','No-Show':'#b45309'};
        data.appointments.forEach(a => {
            html += `<a class="search-item" href="appointments.php?highlight=${a.id}">
                <div class="si-icon" style="background:#eff6ff;color:#6366f1;"><i class="bi bi-calendar-check-fill"></i></div>
                <div><div class="si-main"><span style="font-size:11px;font-weight:700;color:#6366f1;margin-right:6px;">${hl(a.appt_id, q)}</span>${hl(a.pet, q)} <span style="font-size:11px;color:var(--muted);">· ${a.owner||''}</span></div><div class="si-sub">${a.date} · ${hl(a.service||'—',q)} <span style="color:${apptStatusColor[a.status]||'#94a3b8'};font-weight:600;">${a.status}</span></div></div>
            </a>`;
        });
    }
    if ((data.medical||[]).length && ['all','medical'].includes(sfActive)) {
        html += `<div class="search-section-label">Medical Records</div>`;
        data.medical.forEach(m => {
            html += `<a class="search-item" href="consultations.php?highlight=${m.id}">
                <div class="si-icon" style="background:#f0fdf4;color:#16a34a;"><i class="bi bi-clipboard2-pulse-fill"></i></div>
                <div><div class="si-main"><span style="font-size:11px;font-weight:700;color:#16a34a;margin-right:6px;">${hl(m.con_id, q)}</span>${hl(m.pet, q)} <span style="font-size:11px;color:var(--muted);">· Dr. ${hl(m.doctor||'', q)}</span></div><div class="si-sub">${m.date} · ${hl(m.diagnosis||'', q)}</div></div>
            </a>`;
        });
    }
    if ((data.billing||[]).length && ['all','billing'].includes(sfActive)) {
        html += `<div class="search-section-label">Billing</div>`;
        data.billing.forEach(b => {
            const stMap = {Paid:'#16a34a', Pending:'#d97706', Partial:'#0ea5e9'};
            html += `<a class="search-item" href="billing.php?highlight=${b.BillingID}">
                <div class="si-icon" style="background:#f0fdf4;color:#16a34a;"><i class="bi bi-receipt-cutoff"></i></div>
                <div><div class="si-main">${hl(b.invoice, q)} <span style="font-size:11px;color:var(--muted);">· ${b.client}</span></div><div class="si-sub">${b.date} · ₱${parseFloat(b.TotalAmount).toLocaleString()} <span style="color:${stMap[b.PaymentStatus]||'#94a3b8'};font-weight:600;">${b.PaymentStatus}</span></div></div>
            </a>`;
        });
    }
    if ((data.services||[]).length && ['all','services'].includes(sfActive)) {
        html += `<div class="search-section-label">Services</div>`;
        data.services.forEach(s => {
            html += `<a class="search-item" href="services.php?highlight=${s.id}">
                <div class="si-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="bi bi-grid-fill"></i></div>
                <div><div class="si-main"><span style="font-size:11px;font-weight:700;color:#7c3aed;margin-right:6px;">${hl(s.svc_id, q)}</span>${hl(s.name, q)}</div><div class="si-sub">${s.category||''} · ₱${parseFloat(s.price).toLocaleString()}</div></div>
            </a>`;
        });
    }

    if ((data.lodging||[]).length && ['all','lodging'].includes(sfActive)) {
        html += `<div class="search-section-label">Lodging</div>`;
        const ldgStatusColor = {Checked_In:'#2563eb', Checked_Out:'#16a34a', Reserved:'#d97706'};
        data.lodging.forEach(l => {
            html += `<a class="search-item" href="services.php?tab=lodging&highlight=${l.LodgingID}">
                <div class="si-icon" style="background:#fff7ed;color:#ea580c;"><i class="bi bi-house-heart-fill"></i></div>
                <div><div class="si-main"><span style="font-size:11px;font-weight:700;color:#ea580c;margin-right:6px;">${hl(l.ldg_id, q)}</span>${hl(l.pet, q)} <span style="font-size:11px;color:var(--muted);">· ${l.owner||''}</span></div><div class="si-sub">${l.checkin} · Cage ${l.CageNumber||'—'} <span style="color:${ldgStatusColor[l.Status]||'#94a3b8'};font-weight:600;">${l.Status}</span></div></div>
            </a>`;
        });
    }
    el.innerHTML = html;
}

function hl(text, q) {
    if (!text) return '';
    const re = new RegExp('('+q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')', 'gi');
    return text.replace(re, '<mark style="background:#fef9c3;border-radius:2px;padding:0 1px;">$1</mark>');
}

/* ── Universal highlight handler ── */
(function() {
    const params = new URLSearchParams(location.search);
    const hid = params.get('highlight');
    if (!hid) return;

    const page = location.pathname.split('/').pop();
    const tab  = new URLSearchParams(location.search).get('tab') || '';
    const prefixMap = {
        'clients.php':       'row-cln-',
        'pets.php':          'row-pet-',
        'appointments.php':  'row-appt-',
        'consultations.php': 'row-con-',
        'billing.php':       'row-bill-',
        'services.php':      tab === 'lodging' ? 'row-ldg-' : 'row-svc-',
    };
    const prefix = prefixMap[page];
    if (!prefix) return;

    const row = document.getElementById(prefix + hid);
    if (!row) return;

    setTimeout(() => row.scrollIntoView({ behavior: 'smooth', block: 'center' }), 300);
    row.classList.add('search-highlight-row');

    const autoTimer = setTimeout(() => row.classList.remove('search-highlight-row'), 4000);

    function dismissOnClick() {
        clearTimeout(autoTimer);
        row.classList.remove('search-highlight-row');
        document.removeEventListener('click', dismissOnClick);
    }
    setTimeout(() => document.addEventListener('click', dismissOnClick), 400);
})();
<?php endif; ?>
</script>
</body>
</html>