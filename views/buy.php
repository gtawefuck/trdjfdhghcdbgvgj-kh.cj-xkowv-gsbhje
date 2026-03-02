<?php declare(strict_types=1);
/**
 * Buy Center (Tabs)
 * Tabs: Buy Premium Plans, Buy Credits, Buy Killer Credits
 * Requires: $u_credits, $u_kcoin, $u_cash (xcoin)
 */
$plans = [
  // ✅ Only 4 plans with new price & credits
  'silver'   => ['label'=>'Silver 🥈','price'=>10, 'credits'=>800,  'bonus'=>1, 'days'=>7],
  'gold'     => ['label'=>'Gold 🥇','price'=>20, 'credits'=>1500, 'bonus'=>2, 'days'=>15],
  'platinum' => ['label'=>'Platinum 🏅','price'=>30, 'credits'=>3000, 'bonus'=>3, 'days'=>30],
  'diamond'  => ['label'=>'Diamond 💎','price'=>70, 'credits'=>10000,'bonus'=>5, 'days'=>90],
];
/** Credit packs (cost in XCoin => credits added) */
$creditPacks = [
  'c1' => ['label'=>'1 XCoin → 100 credits', 'price'=>1, 'credits'=>100, 'days'=>30],
  'c5' => ['label'=>'5 XCoin → 600 credits', 'price'=>5, 'credits'=>600, 'days'=>30],
  'c10'=> ['label'=>'10 XCoin → 1300 credits','price'=>10, 'credits'=>1300, 'days'=>30],
  'c15'=> ['label'=>'15 XCoin → 2000 credits','price'=>15, 'credits'=>2000, 'days'=>30],
  'c30'=> ['label'=>'30 XCoin → 4000 credits','price'=>30, 'credits'=>4000, 'days'=>30],
  'c50'=> ['label'=>'50 XCoin → 7000 credits','price'=>50, 'credits'=>7000, 'days'=>30],
];
/** Killer Credit packs (cost in XCoin => kcoin added) */
$killerPacks = [
  'k1' => ['label'=>'1 XCoin → 50 kcoin', 'price'=>1, 'kcoin'=>50, 'days'=>1],
  'k5' => ['label'=>'5 XCoin → 300 kcoin', 'price'=>5, 'kcoin'=>300, 'days'=>4],
  'k10' => ['label'=>'10 XCoin → 650 kcoin', 'price'=>10, 'kcoin'=>650, 'days'=>7],
  'k15' => ['label'=>'15 XCoin → 1000 kcoin', 'price'=>15, 'kcoin'=>1000, 'days'=>10],
  'k30' => ['label'=>'30 XCoin → 2000 kcoin', 'price'=>30, 'kcoin'=>2000, 'days'=>15],
  'k50' => ['label'=>'50 XCoin → 3500 kcoin', 'price'=>50, 'kcoin'=>3500, 'days'=>30],
];
$nf = fn($n)=>number_format((int)$n);
?>
<style>
  .tab-btn{--tw-ring-color:transparent}
  .tab-btn[aria-selected="true"]{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.18)}
</style>
<div class="space-y-6">
  <!-- Hero / Balances -->
  <div class="rounded-2xl border border-white/10 bg-gradient-to-r from-indigo-500/10 via-violet-500/10 to-fuchsia-500/10 p-5 md:p-6 shadow-2xl">
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div>
        <div class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-3 py-1 text-xs text-slate-300">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zM7.5 12l2.5 2.5L16.5 8l1.5 1.5-8 8L6 13.5 7.5 12z"/></svg>
          Upgrade & top-up using XCoin
        </div>
        <h2 class="mt-2 text-xl md:text-2xl font-semibold">Buy Center</h2>
        <p class="mt-1 text-sm text-slate-400">Use your <strong>XCoin</strong> to upgrade plan or convert into credits or kcoin.</p>
      </div>
      <div class="flex flex-col items-end gap-2 w-full sm:w-auto">
        <div class="grid grid-cols-3 gap-2">
          <div class="rounded-xl bg-white/10 px-3 py-2 text-xs text-center">
            <div class="text-slate-300">Credits</div>
            <div id="buyCredits" class="text-base font-semibold"><?= $nf($u_credits ?? 0) ?></div>
          </div>
          <div class="rounded-xl bg-white/10 px-3 py-2 text-xs text-center">
            <div class="text-slate-300">Killer Credits (kcoin)</div>
            <div id="buyKcoin" class="text-base font-semibold"><?= $nf($u_kcoin ?? 0) ?></div>
          </div>
          <div class="rounded-xl bg-white/10 px-3 py-2 text-xs text-center">
            <div class="text-slate-300">Your XCoin</div>
            <div id="buyXcoin" class="text-base font-semibold">$<?= $nf($u_cash ?? 0) ?></div>
          </div>
        </div>
        <a href="/app/deposit" class="self-end rounded-lg bg-emerald-600/90 hover:bg-emerald-600 px-3 py-1.5 text-xs font-semibold">Deposit XCoin</a>
      </div>
    </div>
  </div>
  <!-- Desktop Success / Error banner (mobile-এ modal দেখাব) -->
  <div id="buyMsg" class="hidden rounded-2xl border px-4 py-3 text-sm"></div>
  <!-- Tabs -->
  <div class="rounded-2xl border border-white/10 bg-white/5 p-2">
    <div class="flex gap-2 flex-wrap">
      <button class="tab-btn rounded-xl border border-white/10 px-3 py-2 text-sm font-medium" data-tab="plans" aria-selected="true">Buy Premium Plans</button>
      <button class="tab-btn rounded-xl border border-white/10 px-3 py-2 text-sm font-medium" data-tab="credits">Buy Credits</button>
      <button class="tab-btn rounded-xl border border-white/10 px-3 py-2 text-sm font-medium" data-tab="killer">Buy Killer Credits (kcoin)</button>
    </div>
    <!-- Tab: Plans -->
    <div id="tab-plans" class="mt-4">
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        <?php foreach ($plans as $key => $p): ?>
          <div class="relative rounded-2xl border border-white/10 bg-white/5 p-5 shadow-xl hover:shadow-2xl transition">
            <div class="absolute -top-3 -right-3 rounded-lg px-2 py-1 text-[11px] font-semibold bg-gradient-to-r from-fuchsia-500/30 to-violet-500/30 border border-white/10 text-fuchsia-200 shadow">
              <?= htmlspecialchars($p['label'],ENT_QUOTES) ?>
            </div>
            <div class="space-y-2">
              <div class="text-3xl font-extrabold tracking-tight">$<?= $p['price'] ?><span class="ml-1 text-xs font-medium text-slate-400">XCoin</span></div>
              <div class="text-sm text-slate-300">Credits: <span class="font-semibold"><?= $nf($p['credits']) ?></span></div>
              <div class="text-sm text-emerald-300">Bonus XCoin: <span class="font-semibold">+<?= $p['bonus'] ?></span></div>
              <div class="text-sm text-violet-300">Premium: <span class="font-semibold"><?= $p['days'] ?> days</span></div>
            </div>
            <div class="mt-4 flex items-center justify-between">
              <div class="text-xs text-slate-400">No payment methods. XCoin only.</div>
              <button
                data-type="plan"
                data-plan="<?= htmlspecialchars($key,ENT_QUOTES) ?>"
                data-label="<?= htmlspecialchars($p['label'],ENT_QUOTES) ?>"
                data-price="<?= (int)$p['price'] ?>"
                data-credits="<?= (int)$p['credits'] ?>"
                data-bonus="<?= (int)$p['bonus'] ?>"
                data-days="<?= (int)$p['days'] ?>"
                class="buy-btn rounded-xl bg-emerald-600/90 hover:bg-emerald-600 px-3 py-2 text-sm font-semibold">
                Purchase
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <!-- Tab: Credits -->
    <div id="tab-credits" class="mt-4 hidden">
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        <?php foreach ($creditPacks as $key => $p): ?>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-xl hover:shadow-2xl transition">
            <div class="text-3xl font-extrabold tracking-tight">$<?= $p['price'] ?><span class="ml-1 text-xs font-medium text-slate-400">XCoin</span></div>
            <div class="mt-2 text-sm text-slate-300">You will get <b><?= $nf($p['credits']) ?></b> credits.</div>
            <div class="text-sm text-violet-300">Validity: <span class="font-semibold"><?= $p['days'] ?> days</span></div>
            <div class="mt-4 flex items-center justify-between">
              <div class="text-xs text-slate-400">XCoin → Credits (one-time)</div>
              <button
                data-type="credits"
                data-pack="<?= htmlspecialchars($key,ENT_QUOTES) ?>"
                data-label="<?= htmlspecialchars($p['label'],ENT_QUOTES) ?>"
                data-price="<?= (int)$p['price'] ?>"
                data-credits="<?= (int)$p['credits'] ?>"
                data-days="<?= (int)$p['days'] ?>"
                class="buy-btn rounded-xl bg-emerald-600/90 hover:bg-emerald-600 px-3 py-2 text-sm font-semibold">
                Purchase
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <!-- Tab: Killer Credits -->
    <div id="tab-killer" class="mt-4 hidden">
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        <?php foreach ($killerPacks as $key => $p): ?>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-xl hover:shadow-2xl transition">
            <div class="text-3xl font-extrabold tracking-tight">$<?= $p['price'] ?><span class="ml-1 text-xs font-medium text-slate-400">XCoin</span></div>
            <div class="mt-2 text-sm text-slate-300">You will get <b><?= $nf($p['kcoin']) ?></b> kcoin.</div>
            <div class="text-sm text-violet-300">Validity: <span class="font-semibold"><?= $p['days'] ?> days</span></div>
            <div class="mt-4 flex items-center justify-between">
              <div class="text-xs text-slate-400">XCoin → Killer Credits (one-time)</div>
              <button
                data-type="kcoin"
                data-pack="<?= htmlspecialchars($key,ENT_QUOTES) ?>"
                data-label="<?= htmlspecialchars($p['label'],ENT_QUOTES) ?>"
                data-price="<?= (int)$p['price'] ?>"
                data-kcoin="<?= (int)$p['kcoin'] ?>"
                data-days="<?= (int)$p['days'] ?>"
                class="buy-btn rounded-xl bg-emerald-600/90 hover:bg-emerald-600 px-3 py-2 text-sm font-semibold">
                Purchase
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="text-xs text-slate-500">
    All purchases are final (non-refundable).
  </div>
</div>
<!-- Reusable Modal (Confirm / Result) -->
<div id="cxModal" class="fixed inset-0 z-[100] hidden">
  <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
  <div class="relative mx-auto max-w-md w-[92%] sm:w-[28rem] mt-[12vh]">
    <div class="rounded-2xl border border-white/10 bg-slate-900 text-slate-100 shadow-2xl overflow-hidden">
      <div id="cxModalHeader" class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div id="cxModalIcon" class="w-8 h-8 grid place-items-center rounded-lg bg-white/10">
            <!-- icon inject -->
          </div>
          <h3 id="cxModalTitle" class="text-base font-semibold">Confirm</h3>
        </div>
        <button id="cxModalClose" class="p-2 rounded-lg hover:bg-white/10" aria-label="Close modal">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.3 5.71 12 12.01l-6.29-6.3-1.42 1.42L10.59 13.4l-6.3 6.29 1.42 1.42L12 14.83l6.29 6.29 1.42-1.42-6.3-6.29 6.3-6.29z"/></svg>
        </button>
      </div>
      <div id="cxModalBody" class="px-5 py-4 text-sm">
        <!-- content inject -->
      </div>
      <div id="cxModalFooter" class="px-5 py-4 border-t border-white/10 flex items-center justify-end gap-2">
        <!-- buttons inject -->
      </div>
    </div>
  </div>
</div>
<!-- Success sound -->
<audio id="buySuccessSound" src="/assets/sounds/charge.mp3" preload="auto"></audio>
<script>
(() => {
  const $ = s=>document.querySelector(s), $$=s=>Array.from(document.querySelectorAll(s));
  const nf=n=>new Intl.NumberFormat().format(n|0);
  const msgBox=$("#buyMsg"), xBox=$("#buyXcoin"), cBox=$("#buyCredits"), kBox=$("#buyKcoin");
  const isMobile = () => window.matchMedia('(max-width: 768px)').matches;
  /* ---------- Tabs ---------- */
  const tabs = $$(".tab-btn");
  const views = { plans: "#tab-plans", credits: "#tab-credits", killer: "#tab-killer" };
  tabs.forEach(btn=>{
    const name = btn.dataset.tab;
    if(!name) return;
    btn.addEventListener('click', () => {
      tabs.forEach(b=>b.setAttribute('aria-selected','false'));
      btn.setAttribute('aria-selected','true');
      Object.values(views).forEach(sel=>{ const el=$(sel); if(el) el.classList.add('hidden'); });
      $(views[name])?.classList.remove('hidden');
    });
  });
  /* ---------- Modal helpers ---------- */
  const modal = $("#cxModal");
  const mTitle = $("#cxModalTitle");
  const mIconBox = $("#cxModalIcon");
  const mBody = $("#cxModalBody");
  const mFooter = $("#cxModalFooter");
  const closeBtn = $("#cxModalClose");
  const buySound = document.getElementById('buySuccessSound');
  function openModal({title, iconSVG, bodyHTML, buttonsHTML, tone='info'}){
    mTitle.textContent = title || 'Message';
    mIconBox.innerHTML = iconSVG || '';
    mBody.innerHTML = bodyHTML || '';
    mFooter.innerHTML = buttonsHTML || '';
    mIconBox.className = 'w-8 h-8 grid place-items-center rounded-lg ' + (
      tone==='success' ? 'bg-emerald-500/20 text-emerald-300' :
      tone==='error' ? 'bg-rose-500/20 text-rose-300' :
      tone==='warn' ? 'bg-amber-500/20 text-amber-300' : 'bg-white/10 text-slate-200'
    );
    modal.classList.remove('hidden');
    modal.style.opacity = 0;
    requestAnimationFrame(()=>{ modal.style.transition='opacity .15s ease-out'; modal.style.opacity = 1; });
  }
  function closeModal(){ modal.style.opacity = 0; setTimeout(()=>{ modal.classList.add('hidden'); }, 150); }
  closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e)=>{ if(e.target === modal) closeModal(); });
/* ---------- Desktop banner (kept), Mobile modal ---------- */
function showMsg(type, html){
  if (isMobile()) {
    const icon = type==='ok'
      ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2l-3.5-3.5L4 14.2l5 5 11-11-1.5-1.5z"/></svg>'
      : '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M11 15h2v2h-2zm0-8h2v6h-2zM1 21h22L12 2 1 21z"/></svg>';

    openModal({
      title: type==='ok' ? 'Success' : 'Failed',
      iconSVG: icon,
      bodyHTML: `<div class="text-sm leading-6">${html}</div>`,
      buttonsHTML: `<button class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700" id="mOk">OK</button>`,
      tone: type==='ok' ? 'success' : 'error'
    });
    document.getElementById('mOk')?.addEventListener('click', closeModal);
    return; // ✅ মোবাইলে শুধু মডালই দেখাবে
  }

  // Desktop banner
  msgBox.classList.remove('hidden');
  msgBox.className = 'rounded-2xl border px-4 py-3 text-sm ' + (
    type==='ok'
      ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'
      : 'border-rose-500/30 bg-rose-500/10 text-rose-200'
  );
  msgBox.innerHTML = html;
}
  /* ---------- Confirm before purchase ---------- */
  function confirmPurchase(info, onYes){
    const icon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M11 15h2v2h-2zm0-8h2v6h-2zM1 21h22L12 2 1 21z"/></svg>';
    const rows = [];
    if(info.type==='plan'){
      rows.push(`<div>Price</div><div class="text-right"><b>$${info.price} XCoin</b></div>`);
      rows.push(`<div>Credits</div><div class="text-right"><b>${nf(info.credits)}</b></div>`);
      rows.push(`<div>Bonus</div><div class="text-right"><b>+${nf(info.bonus)} XCoin</b></div>`);
      rows.push(`<div>Premium</div><div class="text-right"><b>${info.days} days</b></div>`);
    } else if(info.type==='credits'){
      rows.push(`<div>Price</div><div class="text-right"><b>$${info.price} XCoin</b></div>`);
      rows.push(`<div>Credits</div><div class="text-right"><b>${nf(info.credits)}</b></div>`);
      rows.push(`<div>Validity</div><div class="text-right"><b>${info.days} days</b></div>`);
    } else if(info.type==='kcoin'){
      rows.push(`<div>Price</div><div class="text-right"><b>$${info.price} XCoin</b></div>`);
      rows.push(`<div>Killer Credits</div><div class="text-right"><b>${nf(info.kcoin)}</b></div>`);
      rows.push(`<div>Validity</div><div class="text-right"><b>${info.days} days</b></div>`);
    }
    openModal({
      title: 'Confirm Purchase', iconSVG: icon,
      bodyHTML: `<div class="text-sm space-y-2"><div>You're about to buy: <b>${info.label}</b></div><div class="grid grid-cols-2 gap-y-1 text-slate-300">${rows.join('')}</div></div>`,
      buttonsHTML: `<button id="mCancel" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700">Cancel</button><button id="mYes" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 font-semibold">Yes, Purchase</button>`,
      tone: 'warn'
    });
    $("#mCancel")?.addEventListener('click', closeModal);
    $("#mYes")?.addEventListener('click', async () => {
      const yesBtn = $("#mYes"); yesBtn.disabled = true; yesBtn.textContent = 'Processing…';
      await onYes(info, yesBtn);
    });
  }
  /* ---------- API call ---------- */
  async function callBuy(info){
    const body = new URLSearchParams();
    if(info.type==='plan'){
      body.set('plan', info.plan);
    } else if(info.type==='credits'){
      body.set('action','credits');
      body.set('pack', info.pack);
    } else if(info.type==='kcoin'){
      body.set('action','kcoin');
      body.set('pack', info.pack);
    }
    const r = await fetch('/api/buy_plan.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() });
    return await r.json();
  }
  async function runPurchase(info, yesBtnEl){
    try{
      const j = await callBuy(info);
      if(!j.ok){
        const map = { NO_PLAN:'Invalid plan.', INSUFFICIENT:'Not enough XCoin.', SERVER:'Server error.', NO_PACK:'Invalid pack.', UNSUPPORTED:'Unavailable.' };
        showMsg('err', map[j.error]||'Failed.');
        return;
      }
    // success UI
    try { const s = document.getElementById('buySuccessSound'); if (s){ s.currentTime=0; await s.play(); } } catch(_){}
    
    const d = j.data || {};
    const receiptHTML = d.receipt_id
      ? `<div class="mt-2 text-[11px] text-slate-400">Receipt: <code>${d.receipt_id}</code></div>`
      : '';
    
    const html = `
      <div class="flex items-start gap-3">
        <div class="mt-[2px] shrink-0 rounded-lg bg-emerald-500/20 border border-emerald-500/30 p-1.5">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2l-3.5-3.5L4 14.2l5 5 11-11-1.5-1.5z"/></svg>
        </div>
        <div>
          <div class="font-semibold">Purchase successful!</div>
          <div class="text-xs mt-0.5">${d.summary || ''}</div>
          ${receiptHTML}
        </div>
      </div>
    `;
    
    showMsg('ok', html);
    // নতুনটা দিন:
    if (!isMobile()) {
      // ডেস্কটপে আমরা ব্যানার দেখাই, তাই কনফার্ম মডালটা বন্ধ করে দিচ্ছি
      closeModal();
    }
      // Update local counters
      if(typeof d.new_xcoin==='number' && xBox) xBox.textContent = '$'+nf(d.new_xcoin);
      if(typeof d.new_credits==='number' && cBox) cBox.textContent = nf(d.new_credits);
      if(typeof d.new_kcoin==='number' && kBox) kBox.textContent = nf(d.new_kcoin);
      // refresh header counters (if exists)
      try{
        const mr=await fetch('/api/me.php',{credentials:'same-origin'});
        const mj=await mr.json();
        if(mj?.ok){
          const map={meCredits:'credits',meCash:'cash',meLives:'lives',meCharges:'charges',meHits:'hits',meKcoin:'kcoin'};
          Object.entries(map).forEach(([id,k])=>{ const el=document.getElementById(id); if(el) el.textContent=(k==='cash'?'$':'')+nf(mj.data[k]); });
        }
      }catch(_){ }
    }catch(_){ showMsg('err','Network error.'); }
    finally{ if(yesBtnEl){ yesBtnEl.disabled=false; yesBtnEl.textContent='Yes, Purchase'; } }
  }
  /* ---------- Wire buttons ---------- */
  $$(".buy-btn").forEach(b=>{
    b.addEventListener('click',()=>{
      const info = { type: b.dataset.type };
      if(info.type==='plan'){
        info.plan = b.dataset.plan;
        info.label = b.dataset.label;
        info.price = Number(b.dataset.price||0);
        info.credits= Number(b.dataset.credits||0);
        info.bonus = Number(b.dataset.bonus||0);
        info.days = Number(b.dataset.days||0);
      } else if(info.type==='credits'){
        info.pack = b.dataset.pack;
        info.label = b.dataset.label;
        info.price = Number(b.dataset.price||0);
        info.credits= Number(b.dataset.credits||0);
        info.days = Number(b.dataset.days||0);
      } else if(info.type==='kcoin'){
        info.pack = b.dataset.pack;
        info.label = b.dataset.label;
        info.price = Number(b.dataset.price||0);
        info.kcoin = Number(b.dataset.kcoin||0);
        info.days = Number(b.dataset.days||0);
      } else {
        return; // unsupported
      }
      confirmPurchase(info, runPurchase);
    });
  });
})();
</script>
