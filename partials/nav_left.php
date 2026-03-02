<aside class="hidden lg:flex flex-col border-r border-white/10 bg-slate-900/60 sticky top-0 h-screen">
  <?php
    // Active view/gateway for styling/expand
    $gw = strtolower($_GET['gateway'] ?? '');
    $isCheckers = ($view ?? '') === 'checkers';
    $authGates  = ['stripe','braintree','fastspring'];
    $authOpen   = $isCheckers && in_array($gw, $authGates, true);
    $chargeOpen = $isCheckers && ($gw === 'paypal'); // open when PayPal selected
  ?>

  <!-- Profile -->
  <a href="#" class="p-4 flex items-center gap-3 hover:bg-white/5 transition">
    <img src="<?= $u_pic ? htmlspecialchars($u_pic, ENT_QUOTES) : 'https://api.dicebear.com/7.x/identicon/svg?seed='.urlencode($unameSafe)?>"
         onerror="this.onerror=null;this.src='https://api.dicebear.com/7.x/identicon/svg?seed=<?=urlencode($unameSafe)?>';"
         class="w-10 h-10 rounded-xl object-cover" alt="">
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2">
        <div class="font-semibold truncate max-w-[130px]"><?= $nameSafe ?></div>
        <?= roleBadge($u_status) ?>
      </div>
      <div class="text-xs text-slate-400 truncate">@<?= htmlspecialchars($u_username, ENT_QUOTES) ?></div>
    </div>
    <span class="text-[11px] px-2 py-1 rounded-md bg-white/10 text-slate-300">View</span>
  </a>

  <nav class="px-2 text-sm space-y-1">
    <a data-view="dashboard" class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 <?= ($view==='dashboard')?'bg-white/5':''?>" href="?view=dashboard">
      <svg class="w-4 h-4 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3l9 8h-3v9H6v-9H3l9-8z"/></svg>
      <span>Dashboard</span>
    </a>

    <a data-view="deposit" class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-amber-500/10 <?= ($view==='deposit')?'text-amber-300':''?>" href="?view=deposit">
      <svg class="w-4 h-4 <?= ($view==='deposit')?'text-amber-300':'opacity-80'?>" viewBox="0 0 24 24" fill="currentColor"><path d="M3 7l4 4 5-8 5 8 4-4v10H3V7z"/></svg>
      <span class="font-medium">Deposit XCoin</span>
    </a>

    <a data-view="buy" class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-amber-500/10 <?= ($view==='buy')?'text-amber-300':''?>" href="?view=buy">
      <svg class="w-4 h-4 <?= ($view==='buy')?'text-amber-300':'opacity-80'?>" viewBox="0 0 24 24" fill="currentColor"><path d="M3 7l4 4 5-8 5 8 4-4v10H3V7z"/></svg>
      <span class="font-medium">Buy Premium</span>
    </a>

    <a data-view="redeem" class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 <?= ($view==='redeem')?'bg-white/5':''?>" href="?view=redeem">
      <svg class="w-4 h-4 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6H4a2 2 0 00-2 2v3h20V8a2 2 0 00-2-2zM2 21a2 2 0 002 2h16a2 2 0 002-2v-8H2v8z"/></svg>
      <span>Redeem</span>
    </a>

    <!-- CHECKERS root -->
    <a data-view="checkers" class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 <?= $isCheckers?'bg-white/5':''?>" href="?view=checkers">
      <svg class="w-4 h-4 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2l-3.5-3.5L4 14.2l5 5 11-11-1.5-1.5z"/></svg>
      <span>Checkers</span>
    </a>
    
    <a data-view="autohitters" class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 <?= ($view==='autohitters')?'bg-white/5':''?>" href="?view=autohitters">
      <svg class="w-4 h-4 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6H4a2 2 0 00-2 2v3h20V8a2 2 0 00-2-2zM2 21a2 2 0 002 2h16a2 2 0 002-2v-8H2v8z"/></svg>
      <span>Auto Hitter</span>
    </a>
    
    <a data-view="killers" class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 <?= ($view==='killers')?'bg-white/5':''?>" href="?view=killers">
      <svg class="w-4 h-4 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6H4a2 2 0 00-2 2v3h20V8a2 2 0 00-2-2zM2 21a2 2 0 002 2h16a2 2 0 002-2v-8H2v8z"/></svg>
      <span>CC Killer</span>
    </a>

  <div class="mt-auto p-4">
    <a href="/logout.php" class="w-full inline-flex items-center justify-center rounded-xl bg-rose-500/15 text-rose-300 px-3 py-2 hover:bg-rose-500/20">
      <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="currentColor"><path d="M16 13v-2H7V8l-5 4 5 4v-3h9zM20 3h-8v2h8v14h-8v2h8a2 2 0 002-2V5a2 2 0 00-2-2z"/></svg>Logout
    </a>
  </div>

  <!-- Subnav styles -->
  <style>
    .cx-subnav{
      display:flex;align-items:center;gap:.5rem;
      padding:.5rem .6rem;border-radius:.6rem;margin:.15rem 0;
      color:#cbd5e1;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)
    }
    .cx-subnav:hover{ background:rgba(255,255,255,.08) }
    .cx-subnav.is-active{
      color:#fff;background:linear-gradient(135deg,#3b82f6,#06b6d4);border-color:transparent
    }
  </style>

  <!-- Accordion JS -->
  <script>
    (function(){
      const key='cx.nav.checkers.open';
      const $$=s=>Array.from(document.querySelectorAll(s));
      function openOnly(id){
        $$('.cx-acc-btn').forEach(btn=>{
          const t=btn.dataset.acc, panel=document.getElementById(t), arr=btn.querySelector('.cx-acc-arrow');
          const on=(t===id && id);
          panel?.classList.toggle('hidden', !on);
          btn.setAttribute('aria-expanded', String(!!on));
          arr?.classList.toggle('rotate-180', !!on);
        });
        localStorage.setItem(key, id||'');
      }
      $$('.cx-acc-btn').forEach(btn=>btn.addEventListener('click', ()=>{
        const on = btn.getAttribute('aria-expanded')==='true';
        openOnly(on ? '' : btn.dataset.acc);
      }));
      const last = localStorage.getItem(key);
      if(last && document.getElementById(last)?.classList.contains('hidden')) openOnly(last);
    })();
  </script>
</aside>
