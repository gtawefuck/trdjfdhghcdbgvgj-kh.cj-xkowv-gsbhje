<?php
declare(strict_types=1);
/**
 * Admin Panel (Center content only)
 * - Tabs: Users • Redeem Codes • System
 * - Users: search by username/name/ID/telegram_id (backend must support), edit (status/credits/xcoin/kcoin)
 * - Redeem: credits-only generator (with status + expiry), list + search + 50/page, export current page (clipboard/.txt)
 *
 * Assumes APIs:
 *   GET  /api/admin/users.php?q=&page=&limit=50
 *   POST /api/admin/user_update.php      (user_id|telegram_id, status?, delta_credits?, delta_xcoin?, adjust_kcoin?)
 *   GET  /api/admin/redeem_codes.php?q=&page=&limit=50
 *   POST /api/admin/generate_code.php    (credits, count, status=FREE|PREMIUM, expiry_date? | expiry_days?)
 *   GET  /api/stats.php                  (optional quick stats)
 */
?>
<section class="space-y-6">
  <!-- Hero / Title -->
  <div class="rounded-2xl border border-white/10 bg-gradient-to-br from-slate-900/70 to-slate-900/40 p-5 shadow-xl">
    <div class="flex items-center justify-between gap-4 flex-wrap">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-violet-500 to-cyan-400 flex items-center justify-center shadow">
          <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M5 20h14l1-9-5 3-3-7-3 7-5-3 1 9zm-3 2h20v2H2z"/>
          </svg>
        </div>
        <div>
          <h1 class="text-xl font-semibold">Admin Panel</h1>
          <p class="text-xs text-slate-400">Manage users, generate credits codes, and tweak system settings.</p>
        </div>
      </div>

      <!-- Quick stats (optional) -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 min-w-[220px]">
        <div class="rounded-xl bg-white/5 px-3 py-2">
          <div class="text-[11px] text-slate-400">Users</div>
          <div id="admStatUsers" class="text-sm font-semibold">—</div>
        </div>
        <div class="rounded-xl bg-white/5 px-3 py-2">
          <div class="text-[11px] text-slate-400">Hits</div>
          <div id="admStatHits" class="text-sm font-semibold">—</div>
        </div>
        <div class="rounded-xl bg-white/5 px-3 py-2">
          <div class="text-[11px] text-slate-400">Live</div>
          <div id="admStatLive" class="text-sm font-semibold">—</div>
        </div>
        <div class="rounded-xl bg-white/5 px-3 py-2">
          <div class="text-[11px] text-slate-400">Charge</div>
          <div id="admStatCharge" class="text-sm font-semibold">—</div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="mt-5 flex items-center gap-2 overflow-x-auto no-scrollbar">
      <button data-tab="tabUsers" class="adm-tab active">Users</button>
      <button data-tab="tabRedeem" class="adm-tab">Redeem Codes</button>
      <button data-tab="tabSystem" class="adm-tab">System</button>
    </div>
  </div>

  <!-- USERS TAB -->
  <div id="tabUsers" class="adm-tabpanel block">
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-2">
      <div class="flex-1 min-w-[260px]">
        <label class="sr-only" for="uSearch">Search users</label>
        <div class="relative">
          <input id="uSearch" type="text" placeholder="Search by username / name / ID / telegram_id"
                 class="w-full rounded-xl bg-white/5 border border-white/10 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-violet-500/40">
          <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M21 20l-5.8-5.8a7 7 0 10-1.4 1.4L20 21l1-1zM4 10a6 6 0 1112 0A6 6 0 014 10z"/></svg>
          </div>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button id="uRefresh" class="btn-soft">Refresh</button>
      </div>
    </div>

    <!-- Table -->
    <div class="mt-3 rounded-2xl border border-white/10 bg-white/5 overflow-hidden">
      <div class="overflow-x-auto no-scrollbar">
        <table class="min-w-full text-sm">
          <thead class="bg-white/5 text-slate-300">
            <tr>
              <th class="th">User</th>
              <th class="th">Status</th>
              <th class="th">XCoin</th>
              <th class="th">Killer Credits (kcoin)</th>
              <th class="th">Credits</th>
              <th class="th">Hits</th>
              <th class="th">Plan</th>
              <th class="th">Expiry</th>
              <th class="th text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="uBody" class="divide-y divide-white/5"></tbody>
        </table>
      </div>

      <!-- Pager -->
      <div class="flex items-center justify-between gap-3 px-3 py-2 bg-white/5 border-t border-white/10">
        <div class="text-xs text-slate-400">Max 50 per page</div>
        <div class="flex items-center gap-2">
          <button id="uPrev" class="btn-soft">Prev</button>
          <div class="text-xs text-slate-300"><span id="uPage">1</span> / <span id="uPages">1</span></div>
          <button id="uNext" class="btn-soft">Next</button>
        </div>
      </div>
    </div>
  </div>

  <!-- REDEEM TAB -->
  <div id="tabRedeem" class="adm-tabpanel hidden">
    <div class="grid grid-cols-1 xl:grid-cols-[360px_1fr] gap-4">
      <!-- Credits-only Generator (with status + expiry) -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-4 h-max">
        <div class="flex items-center gap-2 mb-2">
          <div class="chip">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L1 21h22L12 2zM12 6l7.53 13H4.47L12 6z"/></svg>
          </div>
          <div class="font-medium">Generate Credit Codes</div>
        </div>

        <div class="space-y-3 text-sm">
          <label class="block">
            <span class="text-slate-400 text-xs">Credit Amount</span>
            <input id="gCredits" type="number" min="0" value="100" class="inp" placeholder="e.g. 100">
          </label>

          <label class="block">
            <span class="text-slate-400 text-xs">Status</span>
            <select id="gStatus" class="inp">
              <option value="FREE">FREE</option>
              <option value="PREMIUM">PREMIUM</option>
            </select>
          </label>

          <div class="grid grid-cols-2 gap-2">
            <label class="block">
              <span class="text-slate-400 text-xs">Expiry date (optional)</span>
              <input id="gExpiryDate" type="date" class="inp">
            </label>
            <label class="block">
              <span class="text-slate-400 text-xs">Expiry in days (alt)</span>
              <input id="gExpiryDays" type="number" min="0" value="0" class="inp">
            </label>
          </div>

          <label class="block">
            <span class="text-slate-400 text-xs">How many codes? (max 50)</span>
            <input id="gCount" type="number" min="1" max="50" value="1" class="inp">
          </label>

          <button id="gCreate" class="btn-primary w-full mt-2">Generate</button>
          <div id="gMsg" class="text-xs text-slate-400"></div>
        </div>
      </div>

      <!-- Code list -->
      <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
        <div class="flex flex-wrap items-center gap-2">
          <div class="flex-1 min-w-[220px]">
            <input id="rSearch" type="text" placeholder="Search code / user / telegram_id" class="inp">
          </div>
          <button id="rExportCopy" class="btn-soft">Export (Copy)</button>
          <button id="rExportTxt" class="btn-soft">Export (.txt)</button>
          <button id="rRefresh" class="btn-soft">Refresh</button>
        </div>

        <div class="mt-3 rounded-xl border border-white/10 overflow-hidden">
          <div class="overflow-x-auto no-scrollbar">
            <table class="min-w-full text-sm">
              <thead class="bg-white/5 text-slate-300">
                <tr>
                  <th class="th">Code</th>
                  <th class="th">Credits</th>
                  <th class="th">Expiry</th>
                  <th class="th">Status</th>
                  <th class="th">Redeemed By</th>
                  <th class="th text-right">Actions</th>
                </tr>
              </thead>
              <tbody id="rBody" class="divide-y divide-white/5"></tbody>
            </table>
          </div>
          <div class="flex items-center justify-between gap-3 px-3 py-2 bg-white/5 border-t border-white/10">
            <div class="text-xs text-slate-400">Max 50 per page</div>
            <div class="flex items-center gap-2">
              <button id="rPrev" class="btn-soft">Prev</button>
              <div class="text-xs text-slate-300"><span id="rPage">1</span> / <span id="rPages">1</span></div>
              <button id="rNext" class="btn-soft">Next</button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- SYSTEM TAB -->
  <div id="tabSystem" class="adm-tabpanel hidden">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
        <div class="flex items-center gap-2 mb-2">
          <div class="chip">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M11 2h2v20h-2zM2 11h20v2H2z"/></svg>
          </div>
          <div class="font-medium">Quick Actions</div>
        </div>
        <div class="space-y-2 text-sm">
          <button id="sysCache" class="btn-soft w-full">Clear server cache (demo)</button>
          <button id="sysPing" class="btn-soft w-full">Ping DB (demo)</button>
        </div>
        <div id="sysMsg" class="text-xs text-slate-400 mt-2"></div>
      </div>

      <div class="rounded-2xl border border-white/10 bg-white/5 p-4 lg:col-span-2">
        <div class="flex items-center gap-2 mb-2">
          <div class="chip">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M5 3h2v18H5V3zm6 6h2v12h-2V9zm6-4h2v16h-2V5z"/></svg>
          </div>
          <div class="font-medium">Live Overview</div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="stat-tile"><div class="label">Total Users</div><div id="stUsers" class="value">—</div></div>
          <div class="stat-tile"><div class="label">Total Hits</div><div id="stHits" class="value">—</div></div>
          <div class="stat-tile"><div class="label">Live Cards</div><div id="stLive" class="value">—</div></div>
          <div class="stat-tile"><div class="label">Charge Cards</div><div id="stCharge" class="value">—</div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
  .no-scrollbar{-ms-overflow-style:none;scrollbar-width:none}
  .no-scrollbar::-webkit-scrollbar{display:none}
  .adm-tab{
    --ring: rgba(139,92,246,.35);
    padding:.6rem .9rem;border-radius:.9rem;background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.08); color:#cbd5e1; font-weight:600; font-size:.9rem
  }
  .adm-tab.active{ background:linear-gradient(135deg,#8b5cf6,#06b6d4); border-color:transparent; color:white; box-shadow:0 8px 20px rgba(0,0,0,.25) }
  .adm-tabpanel{ animation: admfade .18s ease }
  @keyframes admfade{from{opacity:.6; transform:translateY(4px)} to{opacity:1; transform:none}}
  .btn-soft{padding:.5rem .8rem;border-radius:.75rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1)}
  .btn-soft:hover{background:rgba(255,255,255,.12)}
  .btn-primary{padding:.6rem .9rem;border-radius:.9rem;background:linear-gradient(135deg,#8b5cf6,#06b6d4); color:white; font-weight:600}
  .th{ text-align:left; font-weight:600; padding:.7rem .75rem; white-space:nowrap }
  .td{ padding:.65rem .75rem; vertical-align:middle }
  .chip{width:30px;height:30px;border-radius:.7rem;display:flex;align-items:center;justify-content:center;background:rgba(139,92,246,.18);border:1px solid rgba(139,92,246,.35)}
  .inp{ width:100%; border-radius:.8rem; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); padding:.55rem .8rem; outline:none }
  .inp:focus{ box-shadow:0 0 0 3px rgba(139,92,246,.25) }
  .badge{font-size:.68rem; padding:.15rem .4rem; border-radius:.45rem; font-weight:700}
  .stat-tile{border-radius:1rem; padding:.9rem; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1)}
  .stat-tile .label{font-size:.72rem; color:#94a3b8}
  .stat-tile .value{font-size:1.1rem; font-weight:800}
  .act{ display:inline-flex; align-items:center; gap:.45rem; padding:.45rem .6rem; border-radius:.6rem; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1) }
  .act:hover{ background:rgba(255,255,255,.12) }
</style>

<!-- Edit User Modal (no Set Plan) -->
<div id="uEditModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/60"></div>
  <div class="absolute inset-x-4 md:inset-x-20 top-10 bottom-10 rounded-2xl bg-slate-900 border border-white/10 flex flex-col max-w-3xl mx-auto">
    <div class="flex items-center justify-between p-4 border-b border-white/10">
      <div class="font-semibold">Edit User <span id="uEditTitle" class="text-slate-400"></span></div>
      <button data-close="uEditModal" class="act" aria-label="Close">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M6 18L18 6M6 6l12 12"/></svg>
        Close
      </button>
    </div>
    <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4 overflow-y-auto no-scrollbar">
      <div class="rounded-xl bg-white/5 p-3 space-y-2">
        <div id="uEditIdTg" class="text-xs text-slate-400"></div>
        <label class="block">
          <span class="text-xs text-slate-400">Status</span>
          <select id="eStatus" class="inp">
            <option value="free">FREE</option>
            <option value="premium">PREMIUM</option>
            <option value="banned">BANNED</option>
            <option value="admin">ADMIN</option>
          </select>
        </label>
        <label class="block">
          <span class="text-xs text-slate-400">Adjust Credits (±)</span>
          <input id="eCredit" type="number" class="inp" value="0">
        </label>
        <label class="block">
          <span class="text-xs text-slate-400">Adjust XCoin (±)</span>
          <input id="eXcoin" type="number" class="inp" value="0">
        </label>
        <label class="block">
          <span class="text-xs text-slate-400">Adjust Killer Credits (kcoin) (±)</span>
          <input id="eKcoin" type="number" class="inp" value="0">
        </label>
        <div class="text-xs text-slate-500">Positive = add, Negative = subtract</div>
      </div>
      <div class="rounded-xl bg-white/5 p-3 space-y-2">
        <div class="text-sm font-medium">Notes</div>
        <ul class="list-disc pl-5 text-xs text-slate-400 space-y-1">
          <li>Plan quick-set is removed as requested.</li>
          <li>If both XCoin & kcoin are changed together, the app will save using two quick requests.</li>
        </ul>
      </div>
    </div>
    <div class="p-4 border-t border-white/10 flex items-center justify-end gap-2">
      <button data-close="uEditModal" class="btn-soft">Cancel</button>
      <button id="eSave" class="btn-primary">Save</button>
    </div>
  </div>
</div>

<script>
(function(){
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));
  const esc = s => (s??'').toString().replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const nf  = n => new Intl.NumberFormat().format(n|0);
  const d   = document;

  /* ---------- one shared debounce helper ---------- */
  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  // ------------ Tabs ------------
  $$('.adm-tab').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      $$('.adm-tab').forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      const id = btn.dataset.tab;
      $$('.adm-tabpanel').forEach(p=>p.classList.add('hidden'));
      $('#'+id)?.classList.remove('hidden');
      $('#'+id)?.classList.add('block');
    });
  });

  // ------------ Stats (optional) ------------
  async function fillStats(){
    try{
      const r = await fetch('/api/stats.php',{credentials:'same-origin'});
      const j = await r.json(); if(!j.ok) return;
      (['admStatUsers','stUsers']).forEach(id=>{ const el = $('#'+id); if(el) el.textContent = nf(j.data.total_users); });
      (['admStatHits','stHits']).forEach(id=>{ const el = $('#'+id); if(el) el.textContent = nf(j.data.total_hits); });
      (['admStatLive','stLive']).forEach(id=>{ const el = $('#'+id); if(el) el.textContent = nf(j.data.live_cards); });
      (['admStatCharge','stCharge']).forEach(id=>{ const el = $('#'+id); if(el) el.textContent = nf(j.data.charge_cards); });
    }catch(e){}
  }
  fillStats(); setInterval(fillStats, 10000);

  // ============== USERS ==============
  let uQ = '', uPage = 1, uPages = 1;
  const uLimit = 50;
  const uSearch = $('#uSearch');

  function badge(status){
    status=(status||'free').toLowerCase();
    const m={
      admin:['bg-rose-500/15','text-rose-300','ADMIN'],
      premium:['bg-amber-500/15','text-amber-300','PREMIUM'],
      banned:['bg-rose-500/15','text-rose-300','BANNED'],
      free:['bg-slate-500/20','text-slate-300','FREE']
    }[status]||['bg-slate-500/20','text-slate-300','FREE'];
    return `<span class="badge ${m[0]} ${m[1]}">${m[2]}</span>`;
  }
  function userRow(u){
    const img = u.avatar || ('https://api.dicebear.com/7.x/shapes/svg?seed='+encodeURIComponent(u.username||('user'+u.id)));
    const name = esc(u.name||u.username||('user'+u.id));
    const uname = esc(u.username||('user'+u.id));
    const plan = esc(u.plan||'—');
    const expiry = u.expiry ? esc(u.expiry) : '—';
    const tg = esc(u.telegram_id || '—');
    const xcoin = nf(u.xcoin||0);
    const kcoin = (u.kcoin!=null) ? nf(u.kcoin) : '—';
    return `<tr>
      <td class="td">
        <div class="flex items-center gap-3 min-w-[260px]">
          <img src="${img}" class="w-9 h-9 rounded-lg object-cover" alt="">
          <div>
            <div class="font-medium">${name}</div>
            <div class="text-xs text-slate-400">@${uname} • ID ${u.id}${tg!=='—' ? ' • TG '+tg : ''}</div>
          </div>
        </div>
      </td>
      <td class="td">${badge(u.status)}</td>
      <td class="td">${xcoin}</td>
      <td class="td">${kcoin}</td>
      <td class="td">${nf(u.credits||0)}</td>
      <td class="td">${nf(u.hits||0)}</td>
      <td class="td">${plan}</td>
      <td class="td">${expiry}</td>
      <td class="td">
        <div class="flex justify-end gap-1">
          <button class="act" data-act="edit" data-id="${u.id}" data-name="${name}" data-tg="${tg}">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.8 9.95l-3.75-3.75L3 17.25zM20.7 7.05c.39-.39.39-1.02 0-1.41L18.36 3.3a.9959.9959 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
            Edit
          </button>
        </div>
      </td>
    </tr>`;
  }

  async function fetchUsers(goPage=uPage){
    try{
      const r = await fetch(`/api/admin/users.php?q=${encodeURIComponent(uQ)}&page=${goPage}&limit=${uLimit}`, {credentials:'same-origin'});
      const j = await r.json(); if(!j.ok) return;
      uPage = j.page||1; uPages = j.pages||1;
      $('#uPage').textContent = uPage;
      $('#uPages').textContent = uPages;
      $('#uBody').innerHTML = (j.users||[]).map(userRow).join('') || `<tr><td class="td" colspan="9"><div class="text-slate-400">No users found.</div></td></tr>`;
    }catch(e){}
  }
  const doSearch = debounce(()=>{ uQ = uSearch.value.trim(); fetchUsers(1); }, 350);
  uSearch.addEventListener('input', doSearch);
  $('#uRefresh').addEventListener('click', ()=>fetchUsers());
  $('#uPrev').addEventListener('click', ()=>{ if(uPage>1) fetchUsers(uPage-1); });
  $('#uNext').addEventListener('click', ()=>{ if(uPage<uPages) fetchUsers(uPage+1); });

  // Edit modal
  const uEditModal = $('#uEditModal');
  const uEditTitle = $('#uEditTitle');
  const uEditIdTg = $('#uEditIdTg');
  let currentUserId = 0;
  let currentTelegramId = '';

  function openEdit(uid, name, tg){
    currentUserId = uid;
    currentTelegramId = tg && tg !== '—' ? tg : '';
    uEditTitle.textContent = `#${uid} • ${name||''}`;
    uEditIdTg.innerHTML = `User ID: <b>${uid}</b>${currentTelegramId?` • Telegram ID: <b>${currentTelegramId}</b>`:''}`;
    uEditModal.classList.remove('hidden');
  }
  function closeEdit(){ uEditModal.classList.add('hidden'); }
  document.querySelectorAll('[data-close="uEditModal"]').forEach(b=>b.addEventListener('click', closeEdit));

  // row actions
  document.addEventListener('click', (e)=>{
    const b = e.target.closest('button.act'); if(!b) return;
    const id = parseInt(b.dataset.id||'0',10);
    const act = b.dataset.act;
    if(act==='edit'){ openEdit(id, b.dataset.name||'', b.dataset.tg||''); return; }
  });

  // Save edit
  $('#eSave').addEventListener('click', async ()=>{
    if(!currentUserId) return;
    const st = $('#eStatus').value;
    const dCredits = parseInt($('#eCredit').value||'0',10);
    const dXcoin   = parseInt($('#eXcoin').value||'0',10);
    const dKcoin   = parseInt($('#eKcoin').value||'0',10);

    // First call: status + credits + XCoin
    const fd1 = new FormData();
    fd1.append('user_id', currentUserId);
    fd1.append('status', st);
    fd1.append('delta_credits', dCredits);
    fd1.append('delta_xcoin', dXcoin);

    try{
      let okAll = true;
      let r = await fetch('/api/admin/user_update.php', {method:'POST', body:fd1, credentials:'same-origin'});
      let j = await r.json().catch(()=>({ok:false}));
      if(!j.ok) { okAll = false; toast(j.msg||'Update failed', true); }

      // Second call only if killer credits also need change
      if (dKcoin !== 0) {
        const fd2 = new FormData();
        fd2.append('user_id', currentUserId);
        fd2.append('adjust_kcoin', dKcoin);
        r = await fetch('/api/admin/user_update.php', {method:'POST', body:fd2, credentials:'same-origin'});
        j = await r.json().catch(()=>({ok:false}));
        if(!j.ok) { okAll = false; toast(j.msg||'Kcoin update failed', true); }
      }

      if(okAll){ toast('User updated'); closeEdit(); fetchUsers(uPage); }
    }catch(e){ toast('Server error', true); }
  });

  // Initial load
  fetchUsers(1);

  // ============== REDEEM ==============
  let rQ = '', rPage = 1, rPages = 1, rLimit = 50;
  let rItems = [];
  const rSearch = $('#rSearch');

  async function fetchCodes(goPage=rPage){
    try{
      const r = await fetch(`/api/admin/redeem_codes.php?q=${encodeURIComponent(rQ)}&page=${goPage}&limit=${rLimit}`, {credentials:'same-origin'});
      const j = await r.json(); if(!j.ok) return;
      rPage = j.page||1; rPages=j.pages||1;
      rItems = j.items||[];
      $('#rPage').textContent=rPage; $('#rPages').textContent=rPages;
      $('#rBody').innerHTML = rItems.map(codeRow).join('') || `<tr><td class="td" colspan="6"><div class="text-slate-400">No codes.</div></td></tr>`;
    }catch(e){}
  }
  function codeRow(x){
    const who = x.redeemed_by
      ? `#${x.redeemed_by}${x.username?(' @'+esc(x.username)) : ''}${x.telegram_id?(' • '+esc(x.telegram_id)) : ''}`
      : '—';
    const isPremium = String(x.status || '').toLowerCase() === 'premium';
    const statusBadge = isPremium
      ? `<span class="badge bg-amber-500/15 text-amber-300">PREMIUM</span>`
      : `<span class="badge bg-slate-500/20 text-slate-300">FREE</span>`;
    const exp = x.expiry_date ? esc(x.expiry_date) : '—';

    return `<tr>
      <td class="td"><code class="text-slate-200">${esc(x.code)}</code></td>
      <td class="td">${nf(x.credits||0)}</td>
      <td class="td">${exp}</td>
      <td class="td">${statusBadge}</td>
      <td class="td">${who}</td>
      <td class="td">
        <div class="flex justify-end gap-1">
          <button class="act" data-copy="${esc(x.code)}">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4a2 2 0 00-2 2v12h2V3h12V1zm3 4H8a2 2 0 00-2 2v13a2 2 0 002 2h11a2 2 0 002-2V7a2 2 0 00-2-2zm0 15H8V7h11v13z"/></svg>
            Copy
          </button>
        </div>
      </td>
    </tr>`;
  }

  const doRSearch = debounce(()=>{ rQ=rSearch.value.trim(); fetchCodes(1); }, 350);
  rSearch.addEventListener('input', doRSearch);
  $('#rRefresh').addEventListener('click', ()=>fetchCodes());
  $('#rPrev').addEventListener('click', ()=>{ if(rPage>1) fetchCodes(rPage-1); });
  $('#rNext').addEventListener('click', ()=>{ if(rPage<rPages) fetchCodes(rPage+1); });

  // Copy button (delegation)
  d.addEventListener('click', e=>{
    const btn = e.target.closest('[data-copy]'); if(!btn) return;
    const txt = btn.dataset.copy||'';
    navigator.clipboard.writeText(txt).then(()=>toast('Copied')).catch(()=>toast('Copy failed', true));
  });

  // Generate codes (credits-only, with status + expiry)
  $('#gCreate').addEventListener('click', async ()=>{
    const count = Math.max(1, Math.min(50, parseInt($('#gCount').value||'1',10)));
    const credits = Math.max(0, parseInt($('#gCredits').value||'0',10));
    const status  = $('#gStatus').value || 'FREE';
    const expiry_date = ($('#gExpiryDate').value||'').trim();
    const expiry_days = Math.max(0, parseInt($('#gExpiryDays').value||'0',10));

    const fd = new FormData();
    fd.append('credits', credits);
    fd.append('count', count);
    fd.append('status', status);
    if (expiry_date) fd.append('expiry_date', expiry_date);
    else if (expiry_days>0) fd.append('expiry_days', String(expiry_days));

    $('#gCreate').disabled = true; $('#gMsg').textContent='';
    try{
      const r = await fetch('/api/admin/generate_code.php', {method:'POST', body:fd, credentials:'same-origin'});
      const j = await r.json();
      if(j.ok){ toast(`Generated ${j.count} code(s)`); $('#gMsg').textContent = (j.codes||[]).join(', '); fetchCodes(1); }
      else { toast(j.msg||'Generate failed', true); }
    }catch(e){ toast('Network error', true); }
    $('#gCreate').disabled = false;
  });

  // Export current page
  // Format:
  // CYBORX-XXXXXXXXX-CREDITS
  // {credits} + { expiry date }
// --- Export builder (REPLACE your old one) ---
    function buildExportText(items){
    // কোডের শেষে -FREE / -PREMIUM থাকলে -CREDITS করে দেই
    const toCreditsCode = (code) => {
    if (!code) return '';
    return code;
    };
    
    const toInt = (v) => {
    const n = parseInt(v, 10);
    return Number.isFinite(n) ? n : 0;
    };
    
    const lines = [];
    (items || []).forEach(it => {
    const raw = (it.code || '').trim();
    if (!raw) return;
    
    const code   = toCreditsCode(raw);                 // ← এখানে আসল কোড বসবে
    const credit = toInt(it.credits);                  // ক্রেডিট সংখ্যা
    const expiry = (it.expiry_date && String(it.expiry_date).trim() !== '')
                    ? String(it.expiry_date)           // যেমন: 2025-08-26 00:00:00
                    : '—';                             // না থাকলে ড্যাশ
    
    lines.push(code);
    lines.push(`${credit} + ${expiry}`);
    });
    
    return lines.join('\n');
    }
  $('#rExportCopy').addEventListener('click', ()=>{
    const txt = buildExportText(rItems);
    if(!txt){ toast('Nothing to export', true); return; }
    navigator.clipboard.writeText(txt).then(()=>toast('Export copied')).catch(()=>toast('Copy failed', true));
  });
  $('#rExportTxt').addEventListener('click', ()=>{
    const txt = buildExportText(rItems);
    if(!txt){ toast('Nothing to export', true); return; }
    const blob = new Blob([txt], {type:'text/plain;charset=utf-8'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = `credits_export_page_${rPage}.txt`;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(url), 1000);
  });

  // initial codes load
  fetchCodes(1);

  // ============== System dummies ==============
  $('#sysCache')?.addEventListener('click', ()=>toast('Cache cleared (demo)'));
  $('#sysPing')?.addEventListener('click', ()=>toast('DB OK (demo)'));

  // Toast
  let tdiv;
  function toast(msg, err=false){
    if(!tdiv){
      tdiv = document.createElement('div');
      tdiv.className = 'fixed bottom-4 left-1/2 -translate-x-1/2 z-50';
      document.body.appendChild(tdiv);
    }
    const el = document.createElement('div');
    el.className = 'mt-2 px-3 py-2 rounded-xl text-sm '+(err?'bg-rose-500/90':'bg-emerald-500/90')+' text-white';
    el.textContent = msg;
    tdiv.appendChild(el);
    setTimeout(()=>{ el.style.opacity='0'; el.style.transform='translateY(6px)'; el.style.transition='all .2s'; setTimeout(()=>el.remove(),200); }, 1600);
  }
})();
</script>
