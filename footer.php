<?php 
// Detect if we are rendering the welcome page to toggle layout container bounds
$isWelcomePage = (basename($_SERVER['PHP_SELF']) == 'welcome.php');
?>

<?php if (!$isWelcomePage): ?> <!-- Safely hide dashboard div closes if on welcome page -->
</div><!-- end #main-content -->
</div><!-- end #main-wrapper -->
<?php endif; ?>

<style>
@media print {
    /* Default: hide everything, then reveal only the table marked for printing */
    body.printing-table * { visibility: hidden; }
    body.printing-table .print-target,
    body.printing-table .print-target * { visibility: visible; }
    body.printing-table .print-target {
        position: absolute; top: 0; left: 0; width: 100%;
        border: none; box-shadow: none;
    }
    /* Branded header/footer injected by exportPDF() */
    body.printing-table #pdf-table-header,
    body.printing-table #pdf-table-header * { visibility: visible !important; }
    body.printing-table #pdf-table-footer,
    body.printing-table #pdf-table-footer * { visibility: visible !important; }
    body.printing-table #pdf-table-header { position: absolute; top: 0; left: 0; width: 100%; }
    /* Hide the Actions column (always last) when printing a table */
    body.printing-table .print-target th:last-child,
    body.printing-table .print-target td:last-child { display: none; }

    body.printing-table .modern-table thead tr th {
        background: #1e3a5f !important; color: #fff !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    body.printing-table .modern-table tbody tr:nth-child(even) td { background: #f8fafc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    @page { margin: 1.5cm; size: landscape; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
<script>
/* ══════════════════════════════════════
   GLOBAL EXPORT DROPDOWN SYSTEM
   ══════════════════════════════════════ */
function toggleExportMenu(menuId, btnEl) {
    const menu = document.getElementById(menuId);
    document.querySelectorAll('.export-menu, .modal-export-menu').forEach(m => {
        if (m.id !== menuId) { m.classList.remove('show'); if(m._btn) m._btn.classList.remove('open'); }
    });
    const open = menu.classList.toggle('show');
    btnEl.classList.toggle('open', open);
    menu._btn = btnEl;
}
/* ── Table PDF / Print export ──
   Injects a branded clinic header + footer above/below the table,
   hides everything else, prints, then removes the injected elements. */
function exportPDF(tableSelector) {
    const tbl = document.querySelector(tableSelector || '.modern-table');
    if (!tbl) return;

    document.querySelectorAll('.modern-table').forEach(t => t.classList.remove('print-target'));
    tbl.classList.add('print-target');

    // ── Detect module title from page heading ──
    const headingEl = document.querySelector('.page-title, h1.module-title, .topbar-title, h4');
    const moduleTitle = headingEl ? headingEl.innerText.trim() : document.title.replace('Heartside Vet — ','');
    const dateStr = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    const rowCount = tbl.querySelectorAll('tbody tr').length;

    // ── Build branded header ──
    const hdr = document.createElement('div');
    hdr.id = 'pdf-table-header';
    hdr.innerHTML = `
      <div style="text-align:center;padding:18px 24px 14px;border-bottom:2px dashed #e2e8f0;margin-bottom:12px;">
        <img src="logo1.png" alt="Heartside Vet" style="width:52px;height:52px;object-fit:contain;margin-bottom:6px;display:block;margin-left:auto;margin-right:auto;">
        <div style="font-size:20px;font-weight:800;color:#1e3a5f;letter-spacing:.4px;line-height:1.2;">Heartside Vet Clinic</div>
        <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px;margin-top:4px;font-weight:600;">${moduleTitle}</div>
        <div style="font-size:11px;color:#64748b;margin-top:6px;padding-top:6px;border-top:1px solid #f1f5f9;">
          Generated: <strong style="color:#1e3a5f;">${dateStr}</strong>
          &nbsp;·&nbsp; <strong style="color:#1e3a5f;">${rowCount}</strong> record${rowCount !== 1 ? 's' : ''}
        </div>
      </div>`;
    tbl.parentNode.insertBefore(hdr, tbl);

    // ── Build branded footer ──
    const ftr = document.createElement('div');
    ftr.id = 'pdf-table-footer';
    ftr.innerHTML = `
      <div style="border-top:2px dashed #e2e8f0;margin-top:16px;padding:10px 0 4px;text-align:center;">
        <div style="font-size:10px;color:#94a3b8;letter-spacing:.3px;">Heartside Vet Clinic Management System &nbsp;·&nbsp; ${dateStr}</div>
        <div style="font-size:10px;color:#cbd5e1;margin-top:2px;">This document is computer-generated and valid without a signature.</div>
      </div>`;
    tbl.parentNode.insertBefore(ftr, tbl.nextSibling);

    document.body.classList.add('printing-table');
    setTimeout(() => window.print(), 50);
}
window.addEventListener('afterprint', function() {
    document.body.classList.remove('printing-table');
    // Clean up injected header/footer
    ['pdf-table-header','pdf-table-footer'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.remove();
    });
});
document.addEventListener('click', function(e) {
    if (!e.target.closest('.export-dropdown-wrap') && !e.target.closest('.modal-export-wrap')) {
        document.querySelectorAll('.export-menu, .modal-export-menu').forEach(m => m.classList.remove('show'));
        document.querySelectorAll('.btn-export, .btn-modal-export').forEach(b => b.classList.remove('open'));
    }
});
function tableToArray(tbl) {
    const rows = [];
    tbl.querySelectorAll('tr').forEach(tr => {
        const cells = [];
        tr.querySelectorAll('th, td').forEach(td => cells.push(td.innerText.trim()));
        rows.push(cells);
    });
    const headers = rows[0] || [];
    const actIdx = headers.findIndex(h => h.toLowerCase() === 'actions');
    if (actIdx >= 0) rows.forEach(r => r.splice(actIdx, 1));
    return rows;
}
function exportCSV(tableSelector, filename) {
    const tbl = document.querySelector(tableSelector || '.modern-table');
    if (!tbl) return;
    const data = tableToArray(tbl);
    const csv = data.map(r => r.map(c => '"' + c.replace(/"/g,'""') + '"').join(',')).join('\n');
    downloadBlob(csv, (filename||'export')+'.csv', 'text/csv');
}
function exportXLSX(tableSelector, filename, fmtXls) {
    const tbl = document.querySelector(tableSelector || '.modern-table');
    if (!tbl) return;
    const ws = XLSX.utils.aoa_to_sheet(tableToArray(tbl));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Sheet1');
    XLSX.writeFile(wb, (filename||'export') + (fmtXls ? '.xls' : '.xlsx'));
}
function exportDOCX(tableSelector, filename, title) {
    const tbl = document.querySelector(tableSelector || '.modern-table');
    if (!tbl) return;
    const data = tableToArray(tbl);
    const headers = data[0] || [];
    const body = data.slice(1);
    const docTitle = escXml(title || filename || 'Export');
    const dateStr = new Date().toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
    const rowCount = body.length;
    const thCells = headers.map(h => `<th style="background:#1e3a5f;color:#ffffff;font-size:10pt;font-weight:bold;padding:6px 8px;border:1px solid #cbd5e1;text-align:left;">${escXml(h)}</th>`).join('');
    const bodyRows = body.map((row,i) => `<tr style="${i % 2 ? 'background:#f8fafc;' : ''}">${headers.map((_,j) => `<td style="font-size:9pt;padding:5px 8px;border:1px solid #cbd5e1;">${escXml(row[j]||'')}</td>`).join('')}</tr>`).join('');
    // Word-compatible HTML document — consistent with PDF/print header style
    const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<title>${docTitle}</title>
<!--[if gte mso 9]>
<xml>
<w:WordDocument>
<w:View>Print</w:View>
<w:Zoom>100</w:Zoom>
<w:DoNotOptimizeForBrowser/>
</w:WordDocument>
</xml>
<![endif]-->
<style>
@page { size: 21cm 29.7cm; margin: 1.5cm; }
body { font-family: Calibri, Arial, sans-serif; color:#0f172a; }
table { border-collapse: collapse; width: 100%; margin-top: 10px; }
.clinic-name { text-align:center; color:#1e3a5f; font-size:18pt; font-weight:800; margin:0; letter-spacing:.4px; }
.report-type { text-align:center; color:#94a3b8; font-size:9pt; font-weight:600; text-transform:uppercase; letter-spacing:1.5px; margin:4px 0 0; }
.meta { text-align:center; color:#64748b; font-size:9pt; margin:6px 0 0; padding-top:6px; border-top:1px solid #f1f5f9; }
.header-wrap { padding-bottom:14px; border-bottom:2px dashed #e2e8f0; margin-bottom:12px; text-align:center; }
.record-count { color:#1e3a5f; font-weight:700; }
.footer-note { text-align:center; color:#94a3b8; font-size:8pt; margin-top:16px; padding-top:10px; border-top:2px dashed #e2e8f0; }
.footer-sub { text-align:center; color:#cbd5e1; font-size:8pt; margin-top:2px; }
</style>
</head>
<body>
<div class="header-wrap">
  <div class="clinic-name">Heartside Vet Clinic</div>
  <div class="report-type">${docTitle}</div>
  <div class="meta">Generated: <span class="record-count">${dateStr}</span> &nbsp;·&nbsp; <span class="record-count">${rowCount}</span> record${rowCount !== 1 ? 's' : ''}</div>
</div>
<table>
<thead><tr>${thCells}</tr></thead>
<tbody>${bodyRows}</tbody>
</table>
<div class="footer-note">Heartside Vet Clinic Management System &nbsp;·&nbsp; ${dateStr}</div>
<div class="footer-sub">This document is computer-generated and valid without a signature.</div>
</body>
</html>`;
    downloadBlob('\ufeff' + html, (filename||'export')+'.doc', 'application/msword');
}
function escXml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function downloadBlob(content, filename, mime) {
    const blob = new Blob([content], { type: mime });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click();
    setTimeout(() => { document.body.removeChild(a); URL.revokeObjectURL(url); }, 500);
}
</script>
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