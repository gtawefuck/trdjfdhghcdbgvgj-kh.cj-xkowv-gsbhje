/* util */
const esc = (s) => (s ?? '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const nf  = (n) => new Intl.NumberFormat().format(n|0);

/* system clock */
function tickClock(){ const el=document.getElementById('liveClock'); if(el){ el.textContent=new Date().toLocaleTimeString(); } }
tickClock(); setInterval(tickClock, 1000);

/* mobile menu */
const mSide = document.getElementById('mSide');
const mOverlay = document.getElementById('mOverlay');
function openMenu(){ mSide?.classList.add('open'); if(mOverlay){ mOverlay.classList.remove('hidden'); mOverlay.classList.add('show','z-40'); } }
function closeMenu(){ mSide?.classList.remove('open'); if(mOverlay){ mOverlay.classList.remove('show'); setTimeout(()=>mOverlay.classList.add('hidden'),180); } }
document.getElementById('dockMenuBtn')?.addEventListener('click', (e)=>{ e.preventDefault(); if (mSide?.classList.contains('open')) closeMenu(); else openMenu(); });
mOverlay?.addEventListener('click', closeMenu);
window.addEventListener('keydown',(e)=>{ if(e.key==='Escape') closeMenu(); });

/* heartbeat */
const heartbeat = () => fetch('/api/heartbeat.php',{credentials:'same-origin'}).catch(()=>{});
heartbeat(); setInterval(heartbeat, 30000);

/* notifications (safe) */
const notifBtn=document.getElementById('btnNotif'), notifPanel=document.getElementById('notifPanel'), notifClose=document.getElementById('notifClose');
notifBtn?.addEventListener('click', async () => {
  notifPanel?.classList.toggle('hidden');
  if (notifPanel && !notifPanel.classList.contains('hidden')) {
    try {
      const r = await fetch('/api/notifications.php',{credentials:'same-origin'});
      if (!r.ok) return;
      const j = await r.json(); const box=document.getElementById('notifList');
      if (j?.ok && Array.isArray(j.items) && j.items.length) {
        box.innerHTML = j.items.map(n => `<div class="rounded-xl bg-white/5 p-3">
          <div class="text-sm font-medium">${esc(n.title||'Notification')}</div>
          <div class="text-xs text-slate-400 mt-1">${esc(n.body||'')}</div>
          <div class="text-[11px] text-slate-500 mt-1">${esc(n.time||'')}</div>
        </div>`).join('');
      } else { box.innerHTML = `<div class="text-slate-400 text-sm">No notifications yet.</div>`; }
    } catch(e){}
  }
});
notifClose?.addEventListener('click', ()=>notifPanel?.classList.add('hidden'));

/* right column refreshers */
function badge(status){
  status=(status||'').toLowerCase();
  const m={admin:['bg-rose-500/15','text-rose-300','ADMIN'],premium:['bg-amber-500/15','text-amber-300','PREMIUM'],banned:['bg-rose-500/15','text-rose-300','BANNED'],free:['bg-slate-500/20','text-slate-300','FREE']}[status]||['bg-slate-500/20','text-slate-300','FREE'];
  return `<span class="inline-flex items-center rounded-md px-2 py-[2px] text-[10px] font-semibold ${m[0]} ${m[1]}">${m[2]}</span>`;
}
function tileOnline(u){
  const img = u.profile ? u.profile : 'https://api.dicebear.com/7.x/shapes/svg?seed='+encodeURIComponent(u.username||'user');
  return `
    <div class="user-tile flex items-center gap-3">
      <div class="relative shrink-0">
        <img src="${esc(img)}" class="w-10 h-10 rounded-lg object-cover" alt="">
        <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full ${u.is_online?'bg-emerald-400':'bg-slate-500'} ring-2 ring-slate-900"></span>
      </div>
      <div class="min-w-0 flex-1">
        <div class="text-sm font-medium truncate">${esc(u.full_name||u.username||'user')}</div>
        <div class="text-xs text-slate-400 truncate">@${esc(u.username||'user')}</div>
      </div>
      ${badge(u.status)}
    </div>`;
}
function tileTop(u){
  const img = u.profile ? u.profile : 'https://api.dicebear.com/7.x/shapes/svg?seed='+encodeURIComponent(u.username||'user');
  return `
    <div class="user-tile flex items-center gap-3">
      <div class="relative shrink-0">
        <img src="${esc(img)}" class="w-10 h-10 rounded-lg object-cover" alt="">
        ${u.is_online ? `<span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-slate-900"></span>` : ``}
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <div class="text-sm font-medium truncate">${esc(u.full_name||u.username||'user')}</div>
          ${badge(u.status)}
        </div>
        <div class="text-xs text-slate-400 truncate">@${esc(u.username||'user')}</div>
      </div>
      <div class="text-xs rounded-md px-2 py-0.5 bg-violet-500/15 text-violet-300 font-medium">${nf(u.hits||0)} Hits</div>
    </div>`;
}
async function fetchUsers(scope, targetId) {
  try {
    const r = await fetch('/api/online_users.php?scope='+encodeURIComponent(scope)+'&limit=100',{credentials:'same-origin'});
    const j = await r.json(); if (!j.ok) return;
    if (scope==='online') {
      const nowEls = [document.getElementById('rightOnlineNow'), document.getElementById('modalOnlineNow')];
      nowEls.forEach(el => { if (el) el.textContent = j.count; });
    }
    const box = document.getElementById(targetId);
    if (!box) return;
    box.innerHTML = j.users.map(u => scope==='top' ? tileTop(u) : tileOnline(u)).join('') ||
      `<div class="text-sm text-slate-500">${scope==='top'?'No data.':'No one online.'}</div>`;
  } catch(e){}
}
function bootRightLists(){
  if (document.getElementById('rightOnlineList')) {
    fetchUsers('online','rightOnlineList'); fetchUsers('top','rightTopList');
    if (!window.__rightTimer) window.__rightTimer = setInterval(()=>{ fetchUsers('online','rightOnlineList'); fetchUsers('top','rightTopList'); },15000);
  }
}

/* stats + me */
async function fetchStats() {
  try {
    const r = await fetch('/api/stats.php',{credentials:'same-origin'});
    const j = await r.json(); if (!j.ok) return;
    const map={gTotalUsers:'total_users', gLiveCards:'live_cards', gChargeCards:'charge_cards', gTotalHits:'total_hits'};
    Object.entries(map).forEach(([id,key])=>{ const el=document.getElementById(id); if(el) el.textContent = nf(j.data[key]); });
    const rightNow = document.getElementById('rightOnlineNow'); if (rightNow) rightNow.textContent = j.data.online_now;
  } catch(e){}
}
async function fetchMe(){
  try{
    const r = await fetch('/api/me.php',{credentials:'same-origin'}); const j = await r.json(); if(!j.ok) return;
    const map={meCredits:j.data.credits, meCash:'$'+nf(j.data.cash), meLives:j.data.lives, meCharges:j.data.charges, meHits:j.data.hits};
    Object.entries(map).forEach(([id,val])=>{ const el=document.getElementById(id); if(el) el.textContent = typeof val==='number'?nf(val):val; });
  }catch(e){}
}

/* MODALS */
const topModal = document.getElementById('topModal');
const onlineModal = document.getElementById('onlineModal');
document.getElementById('dockTop')?.addEventListener('click', () => { topModal?.classList.remove('hidden'); fetchUsers('top','topModalList'); });
document.getElementById('dockOnline')?.addEventListener('click', () => { onlineModal?.classList.remove('hidden'); fetchUsers('online','onlineModalList'); });
document.querySelectorAll('[data-close]')?.forEach(btn => btn.addEventListener('click', () => document.getElementById(btn.dataset.close)?.classList.add('hidden')));
[topModal, onlineModal].forEach(m => m?.addEventListener('click', (e) => { if (e.target === m.firstElementChild) m.classList.add('hidden'); }));

/* ---- Smooth page switching (partial load) ---- */
const center = document.getElementById('center');
async function loadView(url, push=true){
  try{
    const res = await fetch(url + (url.includes('?')?'&':'?') + 'partial=1', {credentials:'same-origin'});
    const html = await res.text();
    center.innerHTML = html;
    if (push) history.pushState({url}, '', url);
    // re-run per-view bootstraps
    tickClock(); fetchStats(); fetchMe(); bootRightLists();
    // close mobile menu if open
    closeMenu();
  }catch(e){}
}
// hijack nav links
document.addEventListener('click', (e)=>{
  const a = e.target.closest('a[data-nav]');
  if (!a) return;
  const href = a.getAttribute('href');
  if (!href || !href.startsWith('/app/')) return;
  e.preventDefault();
  loadView(href, true);
});
// back/forward
window.addEventListener('popstate', (e)=>{
  const url = (e.state && e.state.url) ? e.state.url : location.pathname;
  if (url.startsWith('/app/')) loadView(url, false);
});

// initial boot
fetchStats(); fetchMe(); bootRightLists();
