<?php
declare(strict_types=1);
/**
 * Center content for Dashboard
 * Uses helper funcs b(), roleBadge() provided by router.php
 * Expects variables from router: $u_username,$u_name,$u_pic,$u_status,$u_credits,$u_cash,$u_lives,$u_charges,$u_hits,$u_lastlogin,$u_expiry,$expDays
 */
?>
<style>
  .tile{border-radius:16px;padding:16px;color:#e5e7eb;box-shadow:0 14px 30px rgba(0,0,0,.28);border:1px solid rgba(255,255,255,.10)}
  .tile-green{background:linear-gradient(135deg,#16a34a,#10b981)}
  .tile-red{background:linear-gradient(135deg,#ef4444,#f97316)}
  .tile-blue{background:linear-gradient(135deg,#3b82f6,#8b5cf6)}
  .tile-purple{background:linear-gradient(135deg,#8b5cf6,#ec4899)}
  .tile .k{font-size:30px;font-weight:700;line-height:1.1;margin-top:6px}
  .tile .sub{font-size:12px;opacity:.9}
  .icon-pill{width:28px;height:28px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2)}
</style>

<!-- WELCOME -->
<div class="glass card p-5">
  <div class="flex items-start gap-4 flex-wrap">
    <?php
      $unameSafe = htmlspecialchars($u_username ?? '', ENT_QUOTES);
      $nameSafe  = htmlspecialchars($u_name ?? $u_username ?? '', ENT_QUOTES);
      $picSrc = !empty($u_pic)
        ? htmlspecialchars($u_pic, ENT_QUOTES)
        : 'https://api.dicebear.com/7.x/identicon/svg?seed='.urlencode($u_username ?? 'user');
    ?>
    <img src="<?= $picSrc ?>" class="w-14 h-14 rounded-2xl object-cover" alt=""
         onerror="this.onerror=null;this.src='https://api.dicebear.com/7.x/identicon/svg?seed=<?=urlencode($u_username??'user')?>'">
    <div class="flex-1 min-w-[220px]">
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2 flex-wrap">
          <h2 class="text-xl font-semibold truncate max-w-[260px] sm:max-w-none"><?= $nameSafe ?></h2>
          <?= roleBadge($u_status ?? 'free') ?>
        </div>
      </div>
      <div class="text-sm text-slate-400 mt-1">Welcome back! Your dashboard is ready.</div>
      <div class="mt-2 text-xs text-slate-400">Last login: <span class="text-slate-300"><?= htmlspecialchars($u_lastlogin ?? '—', ENT_QUOTES) ?></span></div>
    </div>

    <!-- Credits / Cash -->
    <div class="grid grid-cols-3 gap-2 ml-auto">
      <div class="rounded-xl bg-white/10 px-3 py-2 text-sm text-center">
        <div class="text-slate-300">Credits</div>
        <div id="meCredits" class="font-semibold"><?= b($u_credits ?? 0) ?></div>
      </div>
      <div class="rounded-xl bg-white/10 px-3 py-2 text-sm text-center">
        <div class="text-slate-300">KCoin</div>
        <div id="meKcoin" class="font-semibold"><?= b($u_kcoin ?? 0) ?></div>
      </div>
      <div class="rounded-xl bg-white/10 px-3 py-2 text-sm text-center">
        <div class="text-slate-300">XCoin</div>
        <div id="meCash" class="font-semibold">$<?= b($u_cash ?? 0) ?></div>
      </div>
    </div>
  </div>
</div>


<!-- DAILY CLAIM CARD -->
<style>
  .claim-card{border-radius:18px;overflow:hidden;border:1px solid rgba(255,255,255,.10);
    background: radial-gradient(1200px 300px at 10% -10%, rgba(34,197,94,.20), transparent 40%),
                radial-gradient(1000px 300px at 110% 0%, rgba(59,130,246,.18), transparent 40%),
                linear-gradient(180deg, rgba(2,6,23,.75), rgba(2,6,23,.55));
    box-shadow:0 18px 40px rgba(0,0,0,.35);
  }
  .claim-glow{position:absolute;inset:-2px;border-radius:22px;
    background:conic-gradient(from 180deg, rgba(34,197,94,.35), rgba(59,130,246,.35), rgba(236,72,153,.3), rgba(34,197,94,.35));
    filter:blur(18px);opacity:.35;pointer-events:none}
  .claim-btn{transition:transform .15s ease, box-shadow .2s ease}
  .claim-btn:hover{transform:translateY(-1px)}
  .claim-chip{font-size:12px;padding:.25rem .5rem;border-radius:.5rem;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.06)}
  .claim-count{font-variant-numeric:tabular-nums}
</style>

<div class="mt-6 relative claim-card p-5">
  <div class="claim-glow"></div>

  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 relative">
    <div class="flex items-start gap-3">
      <div class="shrink-0 w-11 h-11 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1l3 7h7l-5.5 4 2 7-6.5-4.5L5.5 19l2-7L2 8h7z"/></svg>
      </div>
      <div>
        <div class="text-lg font-semibold">Daily Credit — Claim +50</div>
        <div id="claimMsg" class="text-xs text-slate-400 mt-0.5">You can claim once every calendar day.</div>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <span class="claim-chip">Reward: <b>+50</b> credits</span>
          <span id="chipState" class="claim-chip">Status: <b>Checking…</b></span>
          <span id="chipReset" class="claim-chip hidden">Resets in: <b class="claim-count" id="resetTimer">—</b></span>
        </div>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <button id="btnClaim" class="claim-btn rounded-xl px-4 py-2.5 font-semibold text-slate-900
              bg-gradient-to-r from-emerald-400 to-cyan-300
              hover:from-emerald-300 hover:to-cyan-200
              shadow-md shadow-emerald-900/30 disabled:opacity-50 disabled:cursor-not-allowed">
        Claim now
      </button>
    </div>
  </div>
</div>



<!-- SUMMARY TILES -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
  <div class="tile tile-blue">
    <div class="flex items-center gap-2">
      <span class="icon-pill">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 7a5 5 0 105 5 5.006 5.006 0 00-5-5zm0-5a10 10 0 11-10 10A10 10 0 0112 2zm1 9h3v2h-5V6h2z"/>
        </svg>
      </span>
      <span class="label">Total Hits</span>
    </div>
    <div id="meHits" class="k"><?= b($u_hits ?? 0) ?></div>
    <div class="sub">All time</div>
  </div>

  <div class="tile tile-red">
    <div class="flex items中心 gap-2">
      <span class="icon-pill">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z"/>
        </svg>
      </span>
      <span class="label">Total Charge Cards</span>
    </div>
    <div id="meCharges" class="k"><?= b($u_charges ?? 0) ?></div>
    <div class="sub">Successful charge cards</div>
  </div>

  <div class="tile tile-green">
    <div class="flex items-center gap-2">
      <span class="icon-pill">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 13h3l2-6 4 12 2-6h5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <span class="label">Total Live Cards</span>
    </div>
    <div id="meLives" class="k"><?= b($u_lives ?? 0) ?></div>
    <div class="sub">Active valid cards</div>
  </div>

  <div class="tile tile-purple">
    <div class="flex items-center gap-2">
      <span class="icon-pill">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M7 11h5V6h2v5h5v2h-5v5h-2v-5H7z"/></svg>
      </span>
      <span class="label">Expiry Date</span>
    </div>
    <div class="k">
      <?= !empty($u_expiry) ? htmlspecialchars((new DateTime($u_expiry))->format('d/m/Y'), ENT_QUOTES) : '—' ?>
    </div>
    <div class="sub"><?= ($expDays!==null)? b(max(0,(int)$expDays)) : '0' ?> days remaining</div>
  </div>
</div>

<!-- GLOBAL STATISTICS -->
<div class="gs-panel mt-6">
  <div class="gs-head">
    <div class="gs-chip">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M5 3h2v18H5V3zm6 6h2v12h-2V9zm6-4h2v16h-2V5z"/></svg>
    </div>
    <div>
      <div class="gs-title">Global Statistics</div>
      <div class="gs-sub">Platform-wide performance metrics</div>
    </div>
  </div>

  <div class="gs-grid">
    <div class="gs-card gs-blue">
      <div class="gs-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M16 11c1.66 0 3-1.57 3-3.5S17.66 4 16 4s-3 1.57-3 3.5S14.34 11 16 11zM8 11c1.66 0 3-1.57 3-3.5S9.66 4 8 4 5 5.57 5 7.5 6.34 11 8 11zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.89 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
        </svg>
      </div>
      <div id="gTotalUsers" class="gs-num">—</div>
      <div class="gs-label">Total Users</div>
    </div>

    <div class="gs-card gs-purple">
      <div class="gs-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 7a5 5 0 105 5 5.006 5.006 0 00-5-5zm0-5a10 10 0 11-10 10A10 10 0 0112 2zm1 9h3v2h-5V6h2z"/>
        </svg>
      </div>
      <div id="gTotalHits" class="gs-num">—</div>
      <div class="gs-label">Total Hits</div>
    </div>

    <div class="gs-card gs-red">
      <div class="gs-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z"/>
        </svg>
      </div>
      <div id="gChargeCards" class="gs-num">—</div>
      <div class="gs-label">Charge Cards</div>
    </div>

    <div class="gs-card gs-green">
      <div class="gs-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 13h3l2-6 4 12 2-6h5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div id="gLiveCards" class="gs-num">—</div>
      <div class="gs-label">Live Cards</div>
    </div>
  </div>
</div>



<script>
(() => {
  const $ = s=>document.querySelector(s);
  const nf = n=>new Intl.NumberFormat().format(n|0);
  const btn   = $('#btnClaim');
  const msg   = $('#claimMsg');
  const chipS = $('#chipState');
  const chipR = $('#chipReset');
  const timer = $('#resetTimer');
  const meCredits = $('#meCredits');

  let nextReset = null;
  let tickTimer = null;

  function setState({claimed, credits, next_reset}){
    if (claimed) {
      chipS.innerHTML = 'Status: <b>Claimed</b> ✅';
      msg.textContent = 'You have already claimed today. Come back after reset.';
      btn.disabled = true;
      chipR.classList.remove('hidden');
    } else {
      chipS.innerHTML = 'Status: <b>Available</b> ✨';
      msg.textContent = 'Tap the button to add +50 credits to your balance.';
      btn.disabled = false;
      chipR.classList.remove('hidden');
    }
    if (typeof credits === 'number' && meCredits) meCredits.textContent = nf(credits);

    nextReset = next_reset || null;
    startCountdown();
  }

  function startCountdown(){
    if (!nextReset) return;
    if (tickTimer) clearInterval(tickTimer);

    function render(){
      const end = new Date(nextReset).getTime();
      const now = Date.now();
      const diff = Math.max(0, Math.floor((end - now)/1000));
      const h = String(Math.floor(diff/3600)).padStart(2,'0');
      const m = String(Math.floor((diff%3600)/60)).padStart(2,'0');
      const s = String(diff%60).padStart(2,'0');
      if (timer) timer.textContent = `${h}:${m}:${s}`;
      if (diff===0) loadState();
    }
    render();
    tickTimer = setInterval(render, 1000);
  }

  async function loadState(){
    try{
      const r = await fetch('/api/claim_daily.php?fn=state',{credentials:'same-origin'});
      const j = await r.json();
      if(!j.ok) throw new Error(j.error||'Failed');
      setState(j);
    }catch(e){
      chipS.innerHTML = 'Status: <b>Error</b>';
      msg.textContent = 'Could not fetch claim state.';
      btn.disabled = true;
    }
  }

  async function doClaim(){
    btn.disabled = true;
    btn.textContent = 'Claiming…';
    try{
      const r = await fetch('/api/claim_daily.php',{method:'POST',credentials:'same-origin'});
      const j = await r.json();
      if(!j.ok){
        if (j.error === 'BANNED') {
          msg.textContent = j.message || "You're banned from CyborX";
        } else if (j.error === 'ALREADY') {
          msg.textContent = 'Already claimed for today.';
        } else {
          msg.textContent = 'Failed to claim.';
        }
        setState(j);
        return;
      }
      msg.textContent = `Claim successful! +${j.amount} credits added.`;
      if (meCredits) meCredits.textContent = nf(j.credits);
      setState(j);
    }catch(_){
      msg.textContent = 'Network error. Try again.';
      btn.disabled = false;
      btn.textContent = 'Claim now';
      return;
    }finally{
      btn.textContent = 'Claim now';
    }
  }

  btn?.addEventListener('click', doClaim);
  loadState();
})();
</script>
