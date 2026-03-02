<?php declare(strict_types=1);
/**
 * Deposit XCoin (1 XCoin = 1 USDT)
 * Step 1: choose amount (plan/suggest/custom)
 * Step 2: show all payment addresses and live conversions
 *
 * Uses public CoinGecko API (CORS ok) to convert USDT -> TRX/BTC/LTC in real time.
 */

$plans = [
  ['label'=>'Silver',   'price'=>10],
  ['label'=>'Gold',     'price'=>20],
  ['label'=>'Platinum', 'price'=>30],
  ['label'=>'Diamond',  'price'=>70],
];
$suggest = [10,20,30,100,250];

$nf = fn($n)=>number_format((int)$n);

/* Telegram bot (for "Knock Admin") */
$botU = $_ENV['TELEGRAM_ADMIN_USERNAME'] ?? '';
$knockUrl = $botU ? ('https://t.me/'.ltrim($botU,'@')) : '#';

/* ---- Payment endpoints (your ones) ---- */
$pay = [
  ['name'=>'BINANCE ID / PAY','addr'=>'753175553'],
  ['name'=>'BTC (Bitcoin)','addr'=>'1GNgQcMHfAYS3XVmAFhck959vGb3T1B86t','ticker'=>'btc'],
  ['name'=>'USDT (TRC20)','addr'=>'TGcizrCAjTvvLCAakd1KojTVWGZEC9eEm9','fixed_usdt'=>true],
  ['name'=>'USDT (BEP20)','addr'=>'0xcd76a1fddfc20c89b223442e9ea655d9ab3b0950','fixed_usdt'=>true],
  ['name'=>'LTC (Litecoin)','addr'=>'LRgnqqufbX2euvmiyhBU26EaMZVWMicq9A','ticker'=>'ltc'],
  ['name'=>'TRX (Tron)','addr'=>'TGcizrCAjTvvLCAakd1KojTVWGZEC9eEm9','ticker'=>'trx'],
];
?>
<div class="space-y-6">

  <!-- Hero -->
  <div class="rounded-2xl border border-white/10 bg-gradient-to-r from-cyan-500/10 via-blue-500/10 to-emerald-500/10 p-5 md:p-6 shadow-2xl">
    <div class="flex items-start justify-between gap-4">
      <div>
        <div class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-3 py-1 text-xs text-slate-300">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zM7.5 12l2.5 2.5L16.5 8l1.5 1.5-8 8L6 13.5 7.5 12z"/></svg>
          Deposit XCoin
        </div>
        <h2 class="mt-2 text-xl md:text-2xl font-semibold">Add balance with crypto</h2>
        <p class="mt-1 text-sm text-slate-400">
          1 <b>XCoin</b> = 1 <b>USDT</b>. Select amount first, then pay to any address shown below.
        </p>
      </div>
      <div class="hidden md:flex items-center gap-3 rounded-xl bg-white/10 px-3 py-2">
        <div class="text-xs text-slate-300">Your XCoin</div>
        <div class="text-base font-semibold">$<?= $nf($u_cash ?? 0) ?></div>
      </div>
    </div>
  </div>

  <!-- Step 1 -->
  <div id="step1" class="space-y-4">
    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
      <div class="text-sm text-slate-300 mb-3">Plan amounts</div>
      <div class="flex flex-wrap gap-2">
        <?php foreach ($plans as $p): ?>
          <button class="amt-btn rounded-lg bg-white/10 hover:bg-white/15 px-3 py-1.5 text-sm" data-amt="<?=$p['price']?>">
            <?=$p['label']?> — $<?=$p['price']?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
      <div class="text-sm text-slate-300 mb-3">Suggestions</div>
      <div class="flex flex-wrap gap-2">
        <?php foreach ($suggest as $s): ?>
          <button class="amt-btn rounded-lg bg-white/10 hover:bg-white/15 px-3 py-1.5 text-sm" data-amt="<?=$s?>">$<?=$s?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
      <div class="text-sm text-slate-300 mb-2">Custom amount</div>
      <div class="flex items-center gap-2">
        <input id="customAmt" type="number" min="1" step="1" placeholder="Enter amount (XCoin / USDT)"
               class="w-48 rounded-lg bg-slate-900 border border-white/10 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500/40">
        <button id="nextBtn" class="rounded-lg bg-emerald-600/90 hover:bg-emerald-600 px-4 py-2 text-sm font-semibold">Next</button>
      </div>
      <div id="amtHint" class="text-xs text-slate-400 mt-2">Minimum $1.</div>
    </div>
  </div>

  <!-- Step 2 -->
  <div id="step2" class="hidden space-y-4">
    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="text-sm text-slate-300">Selected amount</div>
        <div class="text-base font-semibold">$<span id="chosenAmt">0</span> <span class="text-xs text-slate-400">(XCoin / USDT)</span></div>
      </div>
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
      <div class="text-sm text-slate-300 mb-3">Pay to any one of these addresses</div>

      <div class="space-y-3" id="payList">
        <?php foreach ($pay as $m): ?>
          <div class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-slate-900/60 px-3 py-3">
            <div class="min-w-0">
              <div class="text-sm font-medium"><?=htmlspecialchars($m['name'],ENT_QUOTES)?></div>
              <div class="text-xs text-slate-400 truncate"><?=htmlspecialchars($m['addr'],ENT_QUOTES)?></div>
              <?php if (!empty($m['ticker'])): ?>
                <div class="text-[11px] text-slate-400 mt-1">
                  ≈ <span class="live-needed" data-ticker="<?=htmlspecialchars($m['ticker'],ENT_QUOTES)?>">—</span> <?=strtoupper($m['ticker'])?> needed
                  <span class="text-slate-500">(updates live)</span>
                </div>
              <?php elseif (!empty($m['fixed_usdt'])): ?>
                <div class="text-[11px] text-emerald-300 mt-1">Send exactly <b>$<span class="live-usdt">0</span> USDT</b></div>
              <?php endif; ?>
            </div>
            <div class="shrink-0 flex items-center gap-2">
              <button class="copy-btn rounded-md bg-white/10 hover:bg-white/20 px-2 py-1 text-xs"
                      data-copy="<?=htmlspecialchars($m['addr'],ENT_QUOTES)?>">Copy</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <ul class="mt-4 text-xs text-slate-400 list-disc pl-5 space-y-1">
        <li><b>1 XCoin = 1 USDT.</b> For BTC/LTC/TRX we show the live equivalent based on market price.</li>
        <li>Send the exact or higher amount (network fee is on sender).</li>
        <li>Wrong asset/network may cause loss of funds—double-check before sending.</li>
        <li>After payment, click <b>Knock Admin</b> and send the transaction screenshot with your Telegram ID.</li>
      </ul>

      <div class="mt-4 flex items-center gap-2">
        <a href="<?=htmlspecialchars($knockUrl,ENT_QUOTES)?>" target="_blank" class="rounded-lg bg-violet-600/90 hover:bg-violet-600 px-3 py-2 text-xs font-semibold">Knock Admin</a>
        <a href="/app/buy" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-2 text-xs font-semibold">Back to Buy Premium</a>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const $=s=>document.querySelector(s), $$=s=>Array.from(document.querySelectorAll(s));
  const step1=$("#step1"), step2=$("#step2"), chosen=$("#chosenAmt"), input=$("#customAmt"), hint=$("#amtHint");

  let amountUSDT = 0; // == XCoin

  function pick(v){
    const n = parseInt(v,10);
    if(!Number.isFinite(n) || n<1){ hint.textContent='Enter at least $1.'; hint.classList.replace('text-slate-400','text-rose-300'); return; }
    amountUSDT = n;
    chosen.textContent = n.toLocaleString();
    $$(".live-usdt").forEach(el=>el.textContent=n.toLocaleString());
    hint.textContent='Great! Proceed with the addresses below.'; hint.classList.replace('text-rose-300','text-slate-400');
    step1.classList.add('hidden'); step2.classList.remove('hidden');
    updateRates(); window.scrollTo({top:0, behavior:'smooth'});
  }

  $$('.amt-btn').forEach(b=>b.addEventListener('click',()=>pick(b.dataset.amt)));
  $("#nextBtn").addEventListener('click',()=>pick(input.value));

  // Copy buttons
  $$('.copy-btn').forEach(btn=>btn.addEventListener('click',async ()=>{
    try{ await navigator.clipboard.writeText(btn.dataset.copy); btn.textContent='Copied'; setTimeout(()=>btn.textContent='Copy',900);}catch(_){}
  }));

  // Live rates (CoinGecko). We use vs=usdt; fallback to usd if needed.
  const API='https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,litecoin,tron&vs_currencies=usdt,usd';
  async function fetchRates(){
    try{
      const r = await fetch(API, {cache:'no-store'});
      const j = await r.json();
      const get = (id) => (j?.[id]?.usdt ?? j?.[id]?.usd ?? null);
      return {
        btc: get('bitcoin'),   // price in USDT for 1 BTC
        ltc: get('litecoin'),  // price in USDT for 1 LTC
        trx: get('tron'),      // price in USDT for 1 TRX
      };
    }catch(_){ return null; }
  }

  async function updateRates(){
    if(!amountUSDT || amountUSDT<1) return;
    const p = await fetchRates();
    if(!p) return;

    const need = {
      btc: (p.btc ? (amountUSDT / p.btc) : null),
      ltc: (p.ltc ? (amountUSDT / p.ltc) : null),
      trx: (p.trx ? (amountUSDT / p.trx) : null),
    };
    $$('.live-needed').forEach(el=>{
      const t = el.dataset.ticker;
      const v = need[t];
      el.textContent = (v ? (v>100 ? v.toFixed(2) : v>1 ? v.toFixed(4) : v.toFixed(6)) : '—');
    });
  }

  // refresh every 30s
  setInterval(updateRates, 5000);
})();
</script>
