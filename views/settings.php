<?php
// views/settings.php
declare(strict_types=1);
if (!isset($u_username)) { http_response_code(500); exit; }
$esc = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES);
?>
<div class="space-y-6 overflow-x-hidden">

  <!-- Top tabs: General / Security / Proxy -->
  <div class="flex flex-wrap items-center gap-2 text-sm">
    <a href="/app/settings" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10">General</a>
    <a href="/app/settings" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10">Security</a>
    <span class="px-3 py-2 rounded-xl bg-violet-500/20 text-violet-200 border border-violet-400/30">
      <span class="inline-flex items-center gap-2">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M4 5h16v3H4V5zm0 5h16v4H4v-4zm0 6h16v3H4v-3z"/></svg>
        Proxy
      </span>
    </span>
  </div>

  <!-- Header card -->
  <div class="rounded-2xl border border-violet-400/20 bg-gradient-to-br from-violet-500/10 to-transparent p-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div class="text-slate-300 text-sm flex items-center gap-2">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h18v4H3V3zm0 7h18v4H3v-4zm0 7h18v4H3v-4z"/></svg>
        <span class="font-medium">Advanced Proxy Checker</span>
        <span class="hidden sm:inline text-xs text-slate-400">— Live / Dead + Geo & Fraud analysis</span>
      </div>
      <div class="flex items-center gap-2">
        <button id="btnTestAll" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-sm">Test All</button>
        <button id="btnExport"  class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-sm">Export</button>
        <!-- NEW: Delete All -->
        <button id="btnDelAll" class="px-3 py-1.5 rounded-lg bg-rose-600/80 hover:bg-rose-600 text-white text-sm">
          Delete All
        </button>
      </div>
    </div>
  </div>

  <!-- Proxy stats + list -->
  <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
      <div class="text-slate-300 text-sm">
        Proxy List <span id="plCount" class="text-slate-500"></span>
        <span id="limitNote" class="ml-2 text-xs text-slate-400"></span>
      </div>
      <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="px-2 py-1 rounded-md bg-emerald-500/15 text-emerald-300">Live: <span id="stLive">0</span></span>
        <span class="px-2 py-1 rounded-md bg-rose-500/15 text-rose-300">Dead: <span id="stDead">0</span></span>
        <span class="px-2 py-1 rounded-md bg-amber-500/15 text-amber-300">Past: <span id="stPast">0</span></span>
        <span class="px-2 py-1 rounded-md bg-slate-500/20 text-slate-300">Untested: <span id="stUntested">0</span></span>
      </div>
    </div>

    <div id="proxyList" class="space-y-2">
      <!-- rows injected by JS -->
    </div>
  </div>

  <!-- Add single proxy -->
  <div class="rounded-2xl border border-violet-400/20 bg-gradient-to-br from-violet-500/10 to-transparent p-4">
    <div class="text-slate-300 font-medium mb-3 flex items-center gap-2">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M11 4h2v7h7v2h-7v7h-2v-7H4v-2h7z"/></svg>
      Add New Proxy
      <span class="text-xs text-slate-400 font-normal">(Limit: 15 per user)</span>
    </div>
    <div class="grid md:grid-cols-6 gap-3">
      <input id="pName" class="md:col-span-2 px-3 py-2 rounded-lg bg-slate-900/60 border border-white/10 w-full" placeholder="Name (optional)">
      <input id="pHost" class="md:col-span-2 px-3 py-2 rounded-lg bg-slate-900/60 border border-white/10 w-full" placeholder="Host / IP *">
      <input id="pPort" class="px-3 py-2 rounded-lg bg-slate-900/60 border border-white/10 w-full" placeholder="Port *" inputmode="numeric">
      <select id="pType" class="px-3 py-2 rounded-lg bg-slate-900/60 border border-white/10 w-full">
        <option value="http">HTTP</option><option value="https">HTTPS</option>
        <option value="socks4">SOCKS4</option><option value="socks5">SOCKS5</option>
      </select>
      <input id="pUser" class="md:col-span-2 px-3 py-2 rounded-lg bg-slate-900/60 border border-white/10 w-full" placeholder="Username (optional)">
      <input id="pPass" class="md:col-span-2 px-3 py-2 rounded-lg bg-slate-900/60 border border-white/10 w-full" placeholder="Password (optional)">
      <button id="btnAdd" class="md:col-span-2 px-4 py-2 rounded-xl bg-emerald-600/80 hover:bg-emerald-600 text-white">Add Proxy</button>
    </div>
  </div>

  <!-- Bulk import -->
  <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
    <div class="text-slate-300 font-medium mb-3 flex items-center gap-2">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M5 4h14v2H5zM5 18h14v2H5zM5 9h14v6H5z"/></svg>
      Bulk Import
      <span class="text-xs text-slate-400 font-normal">host:port OR host:port:username:password (one per line)</span>
    </div>
    <textarea id="bulkText" rows="6" class="w-full px-3 py-2 rounded-lg bg-slate-900/60 border border-white/10"
      placeholder="192.168.1.100:8080
10.0.0.50:3128
res.proxy.com:8200:user:pass"></textarea>
    <div class="mt-3 flex flex-col sm:flex-row items-start sm:items-center gap-2">
      <select id="bulkType" class="px-3 py-2 rounded-lg bg-slate-900/60 border border-white/10">
        <option value="">Auto (per line)</option>
        <option value="http">HTTP</option><option value="https">HTTPS</option>
        <option value="socks4">SOCKS4</option><option value="socks5">SOCKS5</option>
      </select>
      <button id="btnImport" class="px-4 py-2 rounded-xl bg-indigo-600/80 hover:bg-indigo-600 text-white">Import Proxies</button>
      <div id="importMsg" class="text-xs text-slate-400"></div>
    </div>
  </div>

</div>

<!-- ========== Confirm Modal (single & bulk delete) ========== -->
<div id="confirmModal" class="fixed inset-0 z-[200] hidden">
  <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
  <div class="relative min-h-full flex items-end sm:items-center justify-center p-4">
    <div id="cmCard" class="w-full max-w-md translate-y-4 sm:translate-y-0 opacity-0 transition-all duration-200">
      <div class="rounded-2xl border border-white/10 bg-gradient-to-b from-slate-900 to-slate-800 shadow-2xl">
        <div class="px-5 py-4 border-b border-white/10">
          <h3 id="cmTitle" class="text-lg font-semibold">Delete proxy?</h3>
          <p id="cmDesc" class="text-xs text-slate-400 mt-0.5">
            This action is permanent and cannot be undone.
          </p>
        </div>
        <div class="px-5 py-4 space-y-2">
          <label class="text-xs text-slate-400">
            Type <b id="cmPhrase" class="text-slate-200">DELETE</b> to confirm
          </label>
          <input id="cmInput" class="w-full px-3 py-2 rounded-lg bg-slate-900/70 border border-white/10 text-sm"
                 placeholder="Type here">
        </div>
        <div class="px-5 pb-5 flex items-center justify-end gap-2">
          <button id="cmCancel"  class="px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-sm">Cancel</button>
          <button id="cmConfirm" class="px-4 py-2 rounded-lg bg-rose-600/80 hover:bg-rose-600 text-white text-sm disabled:opacity-50" disabled>
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const $ = s => document.querySelector(s);
  const esc = s => (s ?? '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const nf  = n => new Intl.NumberFormat().format(n|0);
  const LIMIT = 15;

  function pill(status){
    status=(status||'').toLowerCase();
    const map={live:['bg-emerald-500/15','text-emerald-300','Live'],dead:['bg-rose-500/15','text-rose-300','Dead'],past:['bg-amber-500/15','text-amber-300','Past'],testing:['bg-slate-500/20','text-slate-300','Testing']};
    const m=map[status]||map.testing;
    return `<span class="px-2 py-0.5 rounded-md text-xs ${m[0]} ${m[1]}">${m[2]}</span>`;
  }

  // row renderer (DELETE বাটনে data-label যোগ)
  function row(p){
    const last = p.last_check ? new Date(p.last_check).toLocaleString() : '—';
    const lat  = p.latency_ms ? `${p.latency_ms}ms` : '—';
    const cred = (p.username ? 'yes' : 'no');
    const d    = p.meta || {};
    const geo  = (d.country || d.country_code) ? `${esc(d.country||'')} ${d.country_code? '('+esc(d.country_code)+')':''}` : '—';
    const coord= (d.latitude && d.longitude) ? `${d.latitude}, ${d.longitude}` : '—';
    const fr   = (d.fraud_score ?? d.fraud_score === 0) ? d.fraud_score : '—';

    return `
      <div class="rounded-xl border border-white/10 bg-slate-900/40 p-3">
        <div class="flex flex-col lg:flex-row lg:items-center gap-2 lg:gap-3">
          <div class="flex items-center gap-2 min-w-0">
            <div class="px-2 py-1 rounded-md text-[11px] bg-white/5 border border-white/10 shrink-0">${esc(p.type?.toUpperCase()||'HTTP')}</div>
            <div class="font-medium truncate">${esc(p.name||'Unnamed')}</div>
            <div class="shrink-0">${pill(p.status)}</div>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-2 text-xs text-slate-300 w-full">
            <div class="truncate"><span class="text-slate-400">Endpoint:</span> ${esc(p.host)}:${esc(p.port)}</div>
            <div class="truncate"><span class="text-slate-400">Auth:</span> ${cred}</div>
            <div class="truncate"><span class="text-slate-400">Latency:</span> ${lat}</div>
            <div class="truncate"><span class="text-slate-400">Checked:</span> ${esc(last)}</div>
            <div class="truncate"><span class="text-slate-400">Exit IP:</span> ${esc(d.exit_ip || '—')}</div>
          </div>
          <div class="flex items-center gap-2 lg:ml-auto">
            <button class="px-2 py-1 rounded-md bg-white/10 hover:bg-white/20 text-xs" data-test="${p.id}">Test</button>
            <button class="px-2 py-1 rounded-md bg-white/10 hover:bg-white/20 text-xs" data-toggle="det-${p.id}">Details</button>
            <button class="px-2 py-1 rounded-md bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 text-xs"
                    data-del="${p.id}" data-label="${esc(p.host)}:${esc(p.port)}">Delete</button>
          </div>
        </div>
        <div id="det-${p.id}" class="hidden mt-3 rounded-lg border border-white/10 bg-slate-900/70 p-3">
          <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 text-xs text-slate-300">
            <div><span class="text-slate-400">Country:</span> ${esc(geo)}</div>
            <div><span class="text-slate-400">City:</span> ${esc(d.city || '—')}</div>
            <div><span class="text-slate-400">Region:</span> ${esc(d.region || '—')}</div>
            <div><span class="text-slate-400">ISP/Org:</span> ${esc(d.isp || d.org || '—')}</div>
            <div><span class="text-slate-400">ASN:</span> ${esc(d.asn || '—')}</div>
            <div><span class="text-slate-400">Timezone:</span> ${esc(d.timezone || '—')}</div>
            <div><span class="text-slate-400">Coordinates:</span> ${esc(coord)}</div>
            <div><span class="text-slate-400">Fraud score:</span> ${esc(fr)}</div>
            <div><span class="text-slate-400">Source:</span> ${esc(d.source || 'ipwho.is')}</div>
          </div>
        </div>
      </div>`;
  }

  async function loadList(){
    const r = await fetch('/api/proxies.php?fn=list',{credentials:'same-origin'});
    const j = await r.json();
    if(!j.ok){ return; }
    $('#proxyList').innerHTML = j.items.map(row).join('') || `<div class="text-sm text-slate-500">No proxies added yet.</div>`;
    $('#plCount').textContent = `(${j.items.length})`;
    $('#stLive').textContent = j.stats.live;
    $('#stDead').textContent = j.stats.dead;
    $('#stPast').textContent = j.stats.past;
    $('#stUntested').textContent = j.stats.untested;

    // limit UI
    const left = Math.max(0, LIMIT - j.items.length);
    const note = left === 0
      ? `Limit reached (${LIMIT}/${LIMIT}). Delete some to add more.`
      : `You can add ${left} more.`;
    $('#limitNote').textContent = note;
    $('#btnAdd').disabled = (left === 0);
    $('#btnImport').disabled = (left === 0);

    document.querySelectorAll('[data-test]').forEach(b=>b.onclick=()=>testOne(b.dataset.test));
    document.querySelectorAll('[data-toggle]').forEach(b=>b.onclick=()=>document.getElementById(b.dataset.toggle)?.classList.toggle('hidden'));
    document.querySelectorAll('[data-del]').forEach(b=>b.onclick=()=>delOne(b.dataset.del, b.dataset.label));
  }

  async function addOne(){
    const payload = new FormData();
    payload.set('fn','add');
    payload.set('name',$('#pName').value);
    payload.set('host',$('#pHost').value.trim());
    payload.set('port',$('#pPort').value.trim());
    payload.set('type',$('#pType').value);
    payload.set('username',$('#pUser').value);
    payload.set('password',$('#pPass').value);
    const r = await fetch('/api/proxies.php',{method:'POST',body:payload,credentials:'same-origin'});
    const j = await r.json();
    if(!j.ok){
      if (j.error === 'LIMIT') { alert(`Limit reached (15). Delete some to add new.`); return; }
      alert(j.error||'Failed'); return;
    }
    $('#pName').value=$('#pHost').value=$('#pPort').value=$('#pUser').value=$('#pPass').value='';
    loadList();
  }

  async function importBulk(){
    const payload = new FormData();
    payload.set('fn','import');
    payload.set('type',$('#bulkType').value);
    payload.set('bulk',$('#bulkText').value);
    const r = await fetch('/api/proxies.php',{method:'POST',body:payload,credentials:'same-origin'});
    const j = await r.json();
    if(!j.ok){
      if (j.error === 'LIMIT') { $('#importMsg').textContent = 'Limit reached (15).'; return; }
      $('#importMsg').textContent=j.error||'Failed'; return;
    }
    $('#importMsg').textContent = `Imported ${j.inserted} • Skipped ${j.skipped}` + (j.limit_note ? ` • ${j.limit_note}` : '');
    $('#bulkText').value=''; loadList();
  }

  // ======= Confirm modal helpers =======
  const $cm = document.getElementById('confirmModal');
  const $cmCard = document.getElementById('cmCard');
  const $cmTitle= document.getElementById('cmTitle');
  const $cmDesc = document.getElementById('cmDesc');
  const $cmPhrase = document.getElementById('cmPhrase');
  const $cmInput  = document.getElementById('cmInput');
  const $cmCancel = document.getElementById('cmCancel');
  const $cmConfirm= document.getElementById('cmConfirm');
  const REQUIRE_PHRASE = true;
  let _onConfirm = null, _phrase = 'DELETE';

  function openConfirm({ title, desc, phrase='DELETE', confirmText='Delete', onConfirm }) {
    _onConfirm = onConfirm; _phrase = phrase;
    if ($cmTitle)  $cmTitle.textContent = title || 'Are you sure?';
    if ($cmDesc)   $cmDesc.innerHTML    = desc || 'This action cannot be undone.';
    if ($cmPhrase) $cmPhrase.textContent= phrase;
    if ($cmConfirm)$cmConfirm.textContent= confirmText || 'Delete';

    if ($cmInput) {
      $cmInput.value = '';
      $cmInput.placeholder = REQUIRE_PHRASE ? 'Type here' : '';
    }
    if ($cmConfirm) $cmConfirm.disabled = !!REQUIRE_PHRASE;

    $cm.classList.remove('hidden');
    requestAnimationFrame(() => {
      $cmCard.classList.remove('translate-y-4','opacity-0');
      $cmCard.classList.add('translate-y-0','opacity-100');
      if ($cmInput && REQUIRE_PHRASE) $cmInput.focus();
    });
  }
  function closeConfirm() {
    $cmCard.classList.add('translate-y-4','opacity-0');
    setTimeout(() => $cm.classList.add('hidden'), 180);
    _onConfirm = null;
  }
  $cmCancel?.addEventListener('click', closeConfirm);
  $cm?.addEventListener('click', (e) => { if (e.target === $cm) closeConfirm(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$cm.classList.contains('hidden')) closeConfirm(); });
  $cmInput?.addEventListener('input', () => {
    if (!REQUIRE_PHRASE) { $cmConfirm.disabled = false; return; }
    $cmConfirm.disabled = ($cmInput.value.trim() !== _phrase);
  });
  $cmConfirm?.addEventListener('click', async () => { try{ await _onConfirm?.(); }catch(_){} closeConfirm(); });

  // ======= Actions =======
  async function delOne(id, label='this proxy'){
    openConfirm({
      title: 'Delete proxy?',
      desc: `You are about to permanently remove <code>${esc(label)}</code>.`,
      phrase: 'DELETE',
      confirmText: 'Delete',
      onConfirm: async () => {
        const r = await fetch('/api/proxies.php?fn=delete&id='+encodeURIComponent(id),{credentials:'same-origin'});
        const j = await r.json(); if(!j.ok){ alert(j.error||'Failed'); return; }
        loadList();
      }
    });
  }
  async function testOne(id){
    const btn = document.querySelector(`[data-test="${id}"]`);
    if(btn){ btn.disabled=true; btn.textContent='Testing…'; }
    const r = await fetch('/api/proxies.php?fn=test&id='+encodeURIComponent(id),{credentials:'same-origin'});
    const j = await r.json();
    if(btn){ btn.disabled=false; btn.textContent='Test'; }
    if(!j.ok){ alert(j.error||'Failed'); return; }
    loadList();
  }
  async function testAll(){
    if(!confirm('Test all proxies now?')) return;
    const r = await fetch('/api/proxies.php?fn=test_all',{credentials:'same-origin'});
    await r.json(); loadList();
  }
  function exportTxt(){ window.open('/api/proxies.php?fn=export','_blank'); }

  // Delete All with modal
  document.getElementById('btnDelAll')?.addEventListener('click', () => {
    openConfirm({
      title: 'Delete ALL proxies?',
      desc: 'This will remove <b>all</b> saved proxies from your account. This cannot be undone.',
      phrase: 'DELETE ALL',
      confirmText: 'Delete All',
      onConfirm: async () => {
        const r = await fetch('/api/proxies.php?fn=delete_all',{credentials:'same-origin'});
        const j = await r.json(); if(!j.ok){ alert(j.error||"Failed"); return; }
        loadList();
      }
    });
  });

  $('#btnAdd').onclick = addOne;
  $('#btnImport').onclick = importBulk;
  $('#btnTestAll').onclick = testAll;
  $('#btnExport').onclick = exportTxt;

  loadList();
})();
</script>
