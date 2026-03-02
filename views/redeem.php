<?php declare(strict_types=1); ?>
<div class="space-y-6">
  <!-- Hero -->
  <div class="rounded-2xl border border-white/10 bg-gradient-to-r from-slate-900/70 to-slate-800/60 p-5 md:p-6 shadow-2xl">
    <div class="flex items-start justify-between gap-4">
      <div>
        <div class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-3 py-1 text-xs text-slate-300">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M11 15h2v2h-2zm0-8h2v6h-2z"/></svg>
          Apply a valid code
        </div>
        <h2 class="mt-2 text-xl md:text-2xl font-semibold">Redeem a code</h2>
        <p class="mt-1 text-sm text-slate-400">
          Apply a valid code to add credits and, if premium, extend your plan until the code’s expiry.
        </p>
      </div>
      <a href="/app/buy" class="hidden md:inline-flex items-center gap-2 rounded-xl bg-white/10 hover:bg-white/20 px-3 py-2 text-sm">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 7l4 4 5-8 5 8 4-4v10H3V7z"/></svg>
        Need a code? Buy a plan
      </a>
    </div>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <!-- Left: form -->
    <div class="rounded-2xl bg-white/5 border border-white/10 p-5 md:p-6">
      <div class="text-sm font-medium mb-3 text-slate-300">Enter redeem code</div>

      <div class="flex gap-2">
        <input id="rdCode" type="text" inputmode="latin"
               placeholder="CYBORX-XXXX-YYYY-PREMIUM"
               class="w-full rounded-xl bg-slate-900/70 border border-white/10 px-4 py-3 outline-none focus:border-violet-400"
               autocomplete="off" autocapitalize="characters" />
        <button id="btnRedeem" class="rounded-xl bg-emerald-600/90 hover:bg-emerald-600 px-4 py-3 text-sm font-semibold">
          Redeem
        </button>
      </div>

      <div id="rdMsg" class="mt-3 hidden rounded-xl border px-3 py-2 text-sm"></div>

      <ul class="mt-4 space-y-2 text-xs text-slate-400">
        <li class="flex items-center gap-2">
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
          Codes are case-insensitive; hyphens allowed.
        </li>
        <li class="flex items-center gap-2">
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
          Expired or already used codes won’t apply.
        </li>
      </ul>
    </div>

    <!-- Right: history -->
    <div class="rounded-2xl bg-white/5 border border-white/10 p-5 md:p-6">
      <div class="flex items-center justify-between mb-3">
        <div class="text-sm font-medium text-slate-300" id="histTitle">My Redeems</div>
        <div id="histMeta" class="text-xs rounded-lg px-2 py-0.5 bg-white/10 text-slate-300">—</div>
      </div>

      <div class="overflow-x-auto no-scrollbar">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-slate-400">
              <th class="py-2 pr-3">Code</th>
              <th class="py-2 pr-3">Credits</th>
              <th class="py-2 pr-3">Type</th>
              <th class="py-2 pr-3">Code Expiry</th>
              <th class="py-2">Created</th>
            </tr>
          </thead>
          <tbody id="redeemHistory">
            <tr><td class="py-3 text-slate-500" colspan="5">Loading…</td></tr>
          </tbody>
        </table>
      </div>

      <div class="mt-2 text-xs text-slate-500">Shows only the codes you have redeemed.</div>
    </div>
  </div>
</div>

<script>
(function(){
  const elCode = document.getElementById('rdCode');
  const elBtn  = document.getElementById('btnRedeem');
  const elMsg  = document.getElementById('rdMsg');
  const elTbd  = document.getElementById('redeemHistory');
  const elMeta = document.getElementById('histMeta');
  const nf = (n)=> new Intl.NumberFormat().format(n|0);

  function showMsg(type, text){
    elMsg.classList.remove('hidden');
    elMsg.className = 'mt-3 rounded-xl border px-3 py-2 text-sm ' + (type==='ok'
      ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'
      : 'border-rose-500/30 bg-rose-500/10 text-rose-200');
    elMsg.textContent = text;
  }

  async function loadHistory(){
    try{
      const r = await fetch('/api/redeem_history.php',{credentials:'same-origin'});
      const j = await r.json();
      if(!j.ok){ elTbd.innerHTML='<tr><td class="py-3 text-slate-500" colspan="5">No data.</td></tr>'; return; }

      elMeta.textContent  = (j.items||[]).length + ' recent';

      if(!j.items || !j.items.length){
        elTbd.innerHTML='<tr><td class="py-3 text-slate-500" colspan="5">No data.</td></tr>';
        return;
      }
      elTbd.innerHTML = j.items.map(r=>{
        const isPrem = String(r.status||'').toLowerCase()==='premium';
        return `
        <tr class="border-t border-white/5">
          <td class="py-2 pr-3 font-mono text-slate-200">${r.code}</td>
          <td class="py-2 pr-3">${nf(r.credits)}</td>
          <td class="py-2 pr-3">
            <span class="inline-flex items-center rounded-md px-2 py-[2px] text-[10px] font-semibold
              ${isPrem?'bg-amber-500/15 text-amber-300':'bg-slate-500/20 text-slate-300'}">
              ${isPrem?'premium':'free'}
            </span>
          </td>
          <td class="py-2 pr-3">${r.expiry_date || '—'}</td>
          <td class="py-2">${(r.created_at||'').replace('T',' ').replace('Z','')}</td>
        </tr>`;
      }).join('');
    }catch(e){
      elTbd.innerHTML='<tr><td class="py-3 text-slate-500" colspan="5">Failed to load.</td></tr>';
    }
  }

  async function doRedeem(){
    const raw = (elCode.value||'').trim();
    if(!raw){ showMsg('err','Please enter a code.'); return; }
    elBtn.disabled = true;
    try{
      const form = new URLSearchParams(); form.set('code', raw);
      const r = await fetch('/api/redeem_submit.php',{method:'POST', credentials:'same-origin',
                   headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: form.toString()});
      const j = await r.json();
      if(j.ok){
        showMsg('ok', `Applied ${j.data.code}. +${j.data.credits_added} credits${j.data.new_status==='premium'?' • Premium active until '+(j.data.new_expiry||'—'):''}.`);
        elCode.value='';
        try{ const me = await fetch('/api/me.php',{credentials:'same-origin'}); const mj=await me.json();
             if(mj.ok){ const map={meCredits:'credits',meCash:'cash',meLives:'lives',meCharges:'charges',meHits:'hits'};
               Object.entries(map).forEach(([id,k])=>{ const e=document.getElementById(id); if(e) e.textContent = (k==='cash'?'$':'')+new Intl.NumberFormat().format(mj.data[k]|0); });
             }}catch(_){}
        loadHistory();
      }else{
        const m = {NOT_FOUND:'Code not found.', EXPIRED:'This code is expired.', ALREADY_USED:'This code was already used.',
                   ALREADY_YOU:'You have already redeemed this code.', DAILY_LIMIT:'Daily limit reached (max 2 codes per day). Try again tomorrow.', SERVER:'Server error.', EMPTY:'Please enter a code.'}[j.error] || 'Failed.';
        showMsg('err', m);
      }
    }catch(e){ showMsg('err','Network error.'); }
    finally{ elBtn.disabled=false; }
  }

  elBtn.addEventListener('click', doRedeem);
  elCode.addEventListener('keydown', e=>{ if(e.key==='Enter') doRedeem(); });

  loadHistory();
})();
</script>
