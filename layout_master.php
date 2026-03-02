<?php
declare(strict_types=1);
/**
 * Master layout: fixed Header + Left + Right
 * Center content comes from $viewFile (set in router.php)
 * Variables expected from router.php:
 *   $title, $u_* (u_username,u_name,u_pic,u_status,u_credits,u_cash,u_lives,u_charges,u_hits,u_lastlogin),
 *   $bannerHtml,$showBanner,$onlineSSR,$topSSR,$view (or $pageKey)
 */
$pageKey = $pageKey ?? ($view ?? 'dashboard');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($title)? htmlspecialchars($title, ENT_QUOTES) : 'CyborX' ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>

  <style>
    html, body { height: 100%; font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial; }
    body { overflow: hidden; }

    .glass { backdrop-filter: blur(10px); background: rgba(255,255,255,0.04); }
    .card  { border-radius: 1rem; padding: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,.25); border: 1px solid rgba(255,255,255,.08); }

    .mainGrid { height: 100vh; }
    .scrollCenter { height: 100vh; overflow-y: auto; -webkit-overflow-scrolling: touch; }

    /* Hide scrollbars globally but keep scrolling */
    .no-scrollbar { overflow-y: auto; -webkit-overflow-scrolling: touch; -ms-overflow-style: none; scrollbar-width: none; }
    .no-scrollbar::-webkit-scrollbar { width: 0 !important; height: 0 !important; display: none !important; background: transparent !important; }
    .no-scrollbar::-webkit-scrollbar-thumb { background: transparent !important; }

    /* Right panel / modal lists explicit */
    #rightTopList, #rightOnlineList, #topModalList, #onlineModalList {
      overflow-y: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; -ms-overflow-style: none;
    }
    #rightTopList::-webkit-scrollbar,
    #rightOnlineList::-webkit-scrollbar,
    #topModalList::-webkit-scrollbar,
    #onlineModalList::-webkit-scrollbar { width:0!important; height:0!important; background:transparent!important; display:none!important; }

    /* Outer cards never show their own scrollbar */
    #cardTopUsers, #cardOnlineUsers {
      overflow: hidden; -ms-overflow-style:none; scrollbar-width:none;
    }
    #cardTopUsers::-webkit-scrollbar, #cardOnlineUsers::-webkit-scrollbar { width:0!important;height:0!important;display:none!important;background:transparent!important; }

    /* Mobile dock (fixed bottom) */
    .mobile-dock {
      position: fixed; left: 0; right: 0; bottom: 0; z-index: 50;
      padding: 10px 14px calc(10px + env(safe-area-inset-bottom));
      background: rgba(15, 23, 42, .82);
      backdrop-filter: blur(18px) saturate(140%);
      border-top: 1px solid rgba(255,255,255,.08);
      box-shadow: 0 -8px 24px rgba(0,0,0,.35);
    }
    .dock-btn { flex: 1 1 0; display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 8px 6px; border-radius: 14px; color: #cbd5e1; }
    .dock-btn:hover { background: rgba(255,255,255,.06); }
    .dock-icon { width: 22px; height: 22px; opacity: .95; }

    /* Pro user tile (gradient border) */
    .user-tile {
      position: relative; border-radius: 12px; padding: 10px;
      background: linear-gradient(#0b1220, #0b1220) padding-box,
                  linear-gradient(135deg, #6d28d9, #06b6d4) border-box;
      border: 1px solid transparent;
    }

    /* Mobile slide menu */
    #mSide { transform: translateX(-100%); transition: transform .28s ease; }
    #mSide.open { transform: translateX(0%); }
    #mOverlay { opacity: 0; transition: opacity .2s ease; }
    #mOverlay.show { opacity: .55; }

    /* --- Global Statistics styles --- */
    .gs-panel{
      border-radius:16px; padding:16px 16px 18px;
      background: radial-gradient(1200px 600px at -20% -40%, rgba(59,130,246,.10), transparent 60%),
                  radial-gradient(1200px 600px at 120% -40%, rgba(16,185,129,.10), transparent 60%),
                  rgba(255,255,255,.03);
      border:1px solid rgba(255,255,255,.10);
      box-shadow: 0 12px 24px rgba(0,0,0,.25);
    }
    .gs-head{display:flex;align-items:center;gap:10px;margin-bottom:14px}
    .gs-chip{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;
      background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.25)}
    .gs-title{font-weight:600;color:#e5e7eb}
    .gs-sub{font-size:12px;color:#9aa4b2;margin-top:2px}
    .gs-grid{display:grid;gap:16px}
    @media (min-width:640px){.gs-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media (min-width:1280px){.gs-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}

    .gs-card{
      position:relative;border-radius:14px;padding:18px 16px;
      border:1px solid rgba(255,255,255,.08);
      box-shadow: 0 10px 20px rgba(0,0,0,.24);
      color:#e6e9ee;
    }
    .gs-card .gs-icon{
      width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;
      margin-bottom:10px;border:1px solid rgba(255,255,255,.18)
    }
    .gs-card .gs-icon svg{width:18px;height:18px;display:block;opacity:.95}
    .gs-num{font-weight:800;font-size:28px;line-height:1}
    .gs-label{font-size:12px;color:#cbd5e1;margin-top:6px}
    .gs-blue   { background:linear-gradient(135deg, rgba(30,58,138,.7), rgba(30,41,59,.65)); }
    .gs-green  { background:linear-gradient(135deg, rgba(6,95,70,.75), rgba(15,118,110,.65)); }
    .gs-red    { background:linear-gradient(135deg, rgba(88,28,28,.78), rgba(124,45,18,.65)); }
    .gs-purple { background:linear-gradient(135deg, rgba(76,29,149,.75), rgba(88,28,135,.65)); }
    
    /* ===== HARD GLOBAL SCROLLBAR HIDE (Chrome/Edge/Firefox/IE compatible) ===== */
    html, body, .scrollCenter,
    #rightTopList, #rightOnlineList, #topModalList, #onlineModalList,
    aside, .no-scrollbar {
      -ms-overflow-style: none;   /* IE/Edge */
      scrollbar-width: none;      /* Firefox */
    }
    
    html::-webkit-scrollbar,
    body::-webkit-scrollbar,
    .scrollCenter::-webkit-scrollbar,
    #rightTopList::-webkit-scrollbar,
    #rightOnlineList::-webkit-scrollbar,
    #topModalList::-webkit-scrollbar,
    #onlineModalList::-webkit-scrollbar,
    aside::-webkit-scrollbar,
    .no-scrollbar::-webkit-scrollbar{
      width: 0 !important;
      height: 0 !important;
      display: none !important;
      background: transparent !important;
    }
    
    .no-scrollbar { overflow-y: auto; -webkit-overflow-scrolling: touch; }
    /* ===== END ===== */
    
  </style>
</head>
<body class="bg-slate-950 text-slate-100">

<!-- MOBILE DOCK -->
<nav class="mobile-dock lg:hidden">
  <div class="flex items-center gap-2">
    <button id="dockMenu" class="dock-btn" aria-label="Menu">
      <svg class="dock-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M3 6h18v2H3zM3 11h18v2H3zM3 16h18v2H3z"/></svg>
    </button>
    <button id="dockTop" class="dock-btn" aria-label="Top Users">
      <svg class="dock-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6 6 .9-4.5 4.2 1.1 6.1L12 16.8 6.4 19.2 7.5 13.1 3 8.9l6-.9z"/></svg>
    </button>
    <button id="dockOnline" class="dock-btn" aria-label="Online Users">
      <svg class="dock-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M9 12c2.21 0 4-1.79 4-4S11.21 4 9 4 5 5.79 5 8s1.79 4 4 4zm8 0c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zM9 14c-2.67 0-8 1.34-8 4v2h10.09A7 7 0 009 14zm8 0c-1.3 0-2.53.2-3.6.56A6.97 6.97 0 0119 20h5v-2c0-2.66-5.33-4-8-4z"/></svg>
    </button>
  </div>
</nav>

<!-- MOBILE OVERLAY (for sidebar) -->
<div id="mOverlay" class="fixed inset-0 bg-black hidden z-40"></div>

<div class="mainGrid grid grid-cols-1 lg:grid-cols-[260px_1fr_360px]">

  <!-- LEFT sidebar (desktop) -->
  <aside class="hidden lg:flex flex-col border-r border-white/10 bg-slate-900/60 sticky top-0 h-screen">
    <?php
      $unameSafe = htmlspecialchars($u_username ?? '', ENT_QUOTES);
      $nameSafe  = htmlspecialchars($u_name ?? '', ENT_QUOTES);
      $picSrc = !empty($u_pic) ? htmlspecialchars($u_pic, ENT_QUOTES)
              : 'https://api.dicebear.com/7.x/identicon/svg?seed='.urlencode($unameSafe);
      $roleHtml = function($status){
        $m = [
          'admin'   => ['bg'=>'bg-rose-500/15','text'=>'text-rose-300','label'=>'ADMIN'],
          'premium' => ['bg'=>'bg-amber-500/15','text'=>'text-amber-300','label'=>'PREMIUM'],
          'free'    => ['bg'=>'bg-slate-500/20','text'=>'text-slate-300','label'=>'FREE'],
          'banned'  => ['bg'=>'bg-rose-500/15','text'=>'text-rose-300','label'=>'BANNED'],
        ][$GLOBALS['u_status'] ?? 'free'] ?? ['bg'=>'bg-slate-500/20','text'=>'text-slate-300','label'=>'FREE'];
        return "<span class=\"inline-flex items-center rounded-md px-2 py-[2px] text-[10px] font-semibold {$m['bg']} {$m['text']}\">{$m['label']}</span>";
      };
    ?>
    <a href="/app/settings" class="p-4 flex items-center gap-3 hover:bg-white/5 transition">
      <img src="<?= $picSrc ?>" class="w-10 h-10 rounded-xl object-cover" alt=""
           onerror="this.onerror=null;this.src='https://api.dicebear.com/7.x/identicon/svg?seed=<?=urlencode($unameSafe)?>'">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <div class="font-semibold truncate max-w-[130px]"><?= $nameSafe ?></div>
          <?= $roleHtml($u_status ?? 'free') ?>
        </div>
        <div class="text-xs text-slate-400 truncate">@<?= $unameSafe ?></div>
      </div>
      <span class="text-[11px] px-2 py-1 rounded-md bg-white/10 text-slate-300">View</span>
    </a>

    <nav class="px-2 text-sm space-y-1">
      <a class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='dashboard'?'bg-white/5':'' ?>" href="/app/dashboard"><svg class="w-4 h-4 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3l9 8h-3v9H6v-9H3l9-8z"/></svg><span>Dashboard</span></a>
      <a class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-amber-500/10 text-amber-300 <?= $pageKey==='deposit'?'bg-amber-500/10':'' ?>" href="/app/deposit"><svg class="w-4 h-4 text-amber-300" viewBox="0 0 24 24" fill="currentColor"><path d="M3 7l4 4 5-8 5 8 4-4v10H3V7z"/></svg><span class="font-medium">Deposit XCoin</span></a>
      <a class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-amber-500/10 text-amber-300 <?= $pageKey==='buy'?'bg-amber-500/10':'' ?>" href="/app/buy"><svg class="w-4 h-4 text-amber-300" viewBox="0 0 24 24" fill="currentColor"><path d="M3 7l4 4 5-8 5 8 4-4v10H3V7z"/></svg><span class="font-medium">Buy Premium</span></a>
      <a class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='redeem'?'bg-white/5':'' ?>" href="/app/redeem"><svg class="w-4 h-4 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6H4a2 2 0 00-2 2v3h20V8a2 2 0 00-2-2zM2 21a2 2 0 002 2h16a2 2 0 002-2v-8H2v8z"/></svg><span>Redeem</span></a>
      <a class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='checkers'?'bg-white/5':'' ?>" href="/app/checkers"><svg class="w-4 h-4 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2l-3.5-3.5L4 14.2l5 5 11-11-1.5-1.5z"/></svg><span>Checkers</span></a>
      <a class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='autohitters'?'bg-white/5':'' ?>" href="/app/autohitters"><svg class="w-4 h-4 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2l-3.5-3.5L4 14.2l5 5 11-11-1.5-1.5z"/></svg><span>Auto Hitters</span></a>
      <a class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='killers'?'bg-white/5':'' ?>" href="/app/killers"><svg class="w-4 h-4 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M14.7 6.3l3 3L9 18H6v-3l8.7-8.7zM17 2l5 5-2 2-5-5 2-2z"/></svg><span>CC Killer</span></a>
      <a class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='settings'?'bg-white/5':'' ?>" href="/app/settings"><svg class="w-4 h-4 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94a8 8 0 000-1.88l2.03-1.58-1.92-3.32-2.39.96a8.06 8.06 0 00-1.63-.94L14.5 2h-5l-.7 2.18c-.57.23-1.12.53-1.63.94l-2.39-.96L2.86 7.5l2.03 1.58a8.6 8.6 0 000 1.84L2.86 12.5l1.92 3.32 2.39-.96c.5.4 1.05.71 1.63.94L9.5 22h5l.7-2.18c.57-.23 1.12-.53 1.63-.94l2.39.96 1.92-3.32-2.03-1.58z"/></svg><span>Settings</span></a>
      <?php if (($u_status ?? '') === 'admin'): ?>
      <a class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='admin'?'bg-white/5':'' ?>" href="/app/admin"><svg class="w-4 h-4 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l9 4v6c0 5-3.8 9.7-9 11-5.2-1.3-9-6-9-11V5l9-4zm0 6a3 3 0 00-3 3v2h6V10a3 3 0 00-3-3z"/></svg><span>Admin Panel</span></a>
      <?php endif; ?>
    </nav>

    <div class="mt-auto p-4">
      <a href="/logout.php" class="w-full inline-flex items-center justify-center rounded-xl bg-rose-500/15 text-rose-300 px-3 py-2 hover:bg-rose-500/20">
        <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="currentColor"><path d="M16 13v-2H7V8l-5 4 5 4v-3h9zM20 3h-8v2h8v14h-8v2h8a2 2 0 002-2V5a2 2 0 00-2-2z"/></svg>Logout
      </a>
    </div>
  </aside>

  <!-- MOBILE SIDEBAR (all pages) -->
  <aside id="mSide" class="lg:hidden fixed inset-y-0 left-0 w-72 z-50 bg-slate-900/95 border-r border-white/10 p-4 no-scrollbar">
    <a href="/app/settings" class="flex items-center gap-3 mb-4">
      <img src="<?= $picSrc ?>" class="w-9 h-9 rounded-lg object-cover" alt=""
           onerror="this.onerror=null;this.src='https://api.dicebear.com/7.x/identicon/svg?seed=<?=urlencode($unameSafe)?>'">
      <div class="min-w-0">
        <div class="font-semibold truncate max-w-[130px]"><?= $nameSafe ?></div>
        <div class="text-xs text-slate-400 truncate">@<?= $unameSafe ?></div>
      </div>
      <?= $roleHtml($u_status ?? 'free') ?>
    </a>
    <nav class="px-1 text-sm space-y-1">
      <a class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='dashboard'?'bg-white/5':'' ?>" href="/app/dashboard">Dashboard</a>
      <a class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='deposit'?'bg-white/5':'' ?>" href="/app/deposit">Deposit XCoin</a>
      <a class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='buy'?'bg-white/5':'' ?>" href="/app/buy">Buy Premium</a>
      <a class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='redeem'?'bg-white/5':'' ?>" href="/app/redeem">Redeem</a>
      <a class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='checkers'?'bg-white/5':'' ?>" href="/app/checkers">Checkers</a>
      <a class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='autohitters'?'bg-white/5':'' ?>" href="/app/autohitters">Auto Hitters</a>
      <a class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='killers'?'bg-white/5':'' ?>" href="/app/killers">CC Killers</a>
      <a class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='settings'?'bg-white/5':'' ?>" href="/app/settings">Settings</a>
      <?php if (($u_status ?? '') === 'admin'): ?>
      <a class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $pageKey==='admin'?'bg-white/5':'' ?>" href="/app/admin">Admin Panel</a>
      <?php endif; ?>
      <div class="pt-6">
        <a href="/logout.php" class="w-full inline-flex items-center justify-center rounded-xl bg-rose-500/15 text-rose-300 px-3 py-2 hover:bg-rose-500/20">Logout</a>
      </div>
    </nav>
  </aside>

  <!-- CENTER -->
  <main class="scrollCenter">
    <!-- Header (fixed) -->
    <header class="sticky top-0 z-10 bg-slate-950/80 backdrop-blur border-b border-white/10">
      <div class="px-4 lg:px-6 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-500 to-emerald-400 flex items-center justify-center shadow">
            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zM7.5 12l2.5 2.5L16.5 8l1.5 1.5-8 8L6 13.5 7.5 12z"/></svg>
          </div>
          <div class="font-semibold">CyborX</div>
        </div>

        <!-- live clock (kept) -->
        <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs text-slate-200 font-mono">
          <svg class="w-4 h-4 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M12 20a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm.5-12h-1v5l4 2 .5-.9-3.5-1.8V8z"/></svg>
          <span id="liveClock">--:--:--</span>
        </div>
      </div>

      <?php if (!empty($showBanner)): ?>
      <div class="px-4 lg:px-6 pb-3">
        <div class="rounded-2xl border border-amber-400/30 bg-gradient-to-r from-amber-500/10 to-rose-500/10 text-amber-200 px-4 py-3 flex items-start gap-3">
          <svg class="w-5 h-5 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
          <div class="text-sm leading-relaxed"><?= $bannerHtml ?></div>
        </div>
      </div>
      <?php endif; ?>
    </header>

    <!-- CENTER CONTENT -->
    <section class="p-4 lg:p-6 space-y-6 pb-28 lg:pb-6">
      <?php if (isset($viewFile) && is_file($viewFile)) { include $viewFile; } ?>
    </section>
  </main>

  <!-- RIGHT column -->
  <aside class="hidden xl:flex flex-col border-l border-white/10 bg-slate-900/60 sticky top-0 h-screen p-4 space-y-6 overflow-x-hidden">
    <!-- Online Users -->
    <div id="cardOnlineUsers" class="rounded-2xl bg-white/5 p-4 h-[45%]">
      <div class="flex items-center justify-between mb-2">
        <div class="text-slate-300 text-sm">Online Users</div>
        <div class="text-xs rounded-lg px-2 py-0.5 bg-emerald-500/15 text-emerald-300">
          Online Now: <span id="rightOnlineNow"><?= isset($onlineSSR) ? count($onlineSSR) : 0 ?></span>
        </div>
      </div>
      <div id="rightOnlineList" class="no-scrollbar h-[calc(100%_-_1.75rem)] space-y-2 pr-1">
        <?php if (!empty($onlineSSR)): foreach ($onlineSSR as $r):
          $full = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')) ?: ($r['username'] ?? 'user');
          $usern = $r['username'] ?? 'user';
          $img = !empty($r['profile_picture']) ? htmlspecialchars($r['profile_picture'], ENT_QUOTES) : 'https://api.dicebear.com/7.x/shapes/svg?seed='.urlencode($usern);
        ?>
          <div class="user-tile flex items-center gap-3">
            <div class="relative shrink-0">
              <img src="<?= $img ?>" class="w-10 h-10 rounded-lg object-cover" alt=""
                   onerror="this.onerror=null;this.src='https://api.dicebear.com/7.x/shapes/svg?seed=<?=urlencode($usern)?>'">
              <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-slate-900"></span>
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium truncate"><?= htmlspecialchars($full, ENT_QUOTES) ?></div>
              <div class="text-xs text-slate-400 truncate">@<?= htmlspecialchars($usern, ENT_QUOTES) ?></div>
            </div>
            <?php
              $st = strtolower((string)($r['status'] ?? 'free'));
              $m = ['admin'=>['bg'=>'bg-rose-500/15','text'=>'text-rose-300','label'=>'ADMIN'],
                    'premium'=>['bg'=>'bg-amber-500/15','text'=>'text-amber-300','label'=>'PREMIUM'],
                    'free'=>['bg'=>'bg-slate-500/20','text'=>'text-slate-300','label'=>'FREE'],
                    'banned'=>['bg'=>'bg-rose-500/15','text'=>'text-rose-300','label'=>'BANNED']][$st] ?? ['bg'=>'bg-slate-500/20','text'=>'text-slate-300','label'=>'FREE'];
              echo "<span class=\"inline-flex items-center rounded-md px-2 py-[2px] text-[10px] font-semibold {$m['bg']} {$m['text']}\">{$m['label']}</span>";
            ?>
          </div>
        <?php endforeach; else: ?>
          <div class="text-sm text-slate-500">No one online.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Top Users -->
    <div id="cardTopUsers" class="rounded-2xl bg-white/5 p-4 h-[45%]">
      <div class="text-slate-300 text-sm mb-2">Top Users</div>
      <div id="rightTopList" class="no-scrollbar h-[calc(100%_-_1.5rem)] space-y-2 pr-1">
        <?php if (!empty($topSSR)): foreach ($topSSR as $r):
          $full  = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')) ?: ($r['username'] ?? 'user');
          $usern = $r['username'] ?? 'user';
          $img   = !empty($r['profile_picture']) ? htmlspecialchars($r['profile_picture'], ENT_QUOTES) : 'https://api.dicebear.com/7.x/shapes/svg?seed='.urlencode($usern);
          $isOn  = !empty($r['is_online']);
          $st    = strtolower((string)($r['status'] ?? 'free'));
          $m = ['admin'=>['bg'=>'bg-rose-500/15','text'=>'text-rose-300','label'=>'ADMIN'],
                'premium'=>['bg'=>'bg-amber-500/15','text'=>'text-amber-300','label'=>'PREMIUM'],
                'free'=>['bg'=>'bg-slate-500/20','text'=>'text-slate-300','label'=>'FREE'],
                'banned'=>['bg'=>'bg-rose-500/15','text'=>'text-rose-300','label'=>'BANNED']][$st] ?? ['bg'=>'bg-slate-500/20','text'=>'text-slate-300','label'=>'FREE'];
        ?>
          <div class="user-tile flex items-center gap-3">
            <div class="relative shrink-0">
              <img src="<?= $img ?>" class="w-10 h-10 rounded-lg object-cover" alt=""
                   onerror="this.onerror=null;this.src='https://api.dicebear.com/7.x/shapes/svg?seed=<?=urlencode($usern)?>'">
              <?php if ($isOn): ?><span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-slate-900"></span><?php endif; ?>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <div class="text-sm font-medium truncate"><?= htmlspecialchars($full, ENT_QUOTES) ?></div>
                <span class="inline-flex items-center rounded-md px-2 py-[2px] text-[10px] font-semibold <?= $m['bg'] ?> <?= $m['text'] ?>"><?= $m['label'] ?></span>
              </div>
              <div class="text-xs text-slate-400 truncate">@<?= htmlspecialchars($usern, ENT_QUOTES) ?></div>
            </div>
            <div class="text-xs rounded-md px-2 py-0.5 bg-violet-500/15 text-violet-300 font-medium"><?= number_format((int)($r['hits'] ?? 0)) ?> Hits</div>
          </div>
        <?php endforeach; else: ?>
          <div class="text-sm text-slate-500">No data.</div>
        <?php endif; ?>
      </div>
    </div>
  </aside>
</div>

<!-- MODALS -->
<div id="topModal" class="fixed inset-0 z-50 hidden"><div class="absolute inset-0 bg-black/60"></div>
  <div class="absolute inset-x-4 md:inset-x-20 top-10 bottom-10 rounded-2xl bg-slate-900 border border-white/10 flex flex-col">
    <div class="flex items-center justify-between p-4 border-b border-white/10">
      <div class="font-semibold">Top Users</div>
      <button data-close="topModal" class="rounded-md bg-white/10 hover:bg-white/20 p-2" aria-label="Close">
        <!-- FIX: visible cross icon -->
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
    <div class="p-4 flex-1 overflow-hidden">
      <div id="topModalList" class="no-scrollbar h-full space-y-2 pr-1"></div>
    </div>
  </div>
</div>

<div id="onlineModal" class="fixed inset-0 z-50 hidden"><div class="absolute inset-0 bg-black/60"></div>
  <div class="absolute inset-x-4 md:inset-x-20 top-10 bottom-10 rounded-2xl bg-slate-900 border border-white/10 flex flex-col">
    <div class="flex items-center justify-between p-4 border-b border-white/10">
      <div class="font-semibold">Online Users • <span id="modalOnlineNow">—</span></div>
      <button data-close="onlineModal" class="rounded-md bg-white/10 hover:bg-white/20 p-2" aria-label="Close">
        <!-- FIX: visible cross icon -->
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
    <div class="p-4 flex-1 overflow-hidden">
      <div id="onlineModalList" class="no-scrollbar h-full space-y-2 pr-1"></div>
    </div>
  </div>
</div>

<script>
(function(w, d){
  if (w.__CX_INIT__) return; w.__CX_INIT__ = true;
  const $ = sel => d.querySelector(sel);
  const nf = n => new Intl.NumberFormat().format(n|0);

  // Live clock
  const tickClock = () => { const el=$("#liveClock"); if(el) el.textContent=new Date().toLocaleTimeString(); };
  tickClock(); setInterval(tickClock, 1000);

  // Mobile sidebar open/close
  const mSide = $("#mSide");
  const mOverlay = $("#mOverlay");
  function openMenu(){ if(!mSide) return; mSide.classList.add('open'); if(mOverlay){ mOverlay.classList.remove('hidden'); mOverlay.classList.add('show','z-40'); } }
  function closeMenu(){ if(!mSide) return; mSide.classList.remove('open'); if(mOverlay){ mOverlay.classList.remove('show'); setTimeout(()=>mOverlay.classList.add('hidden'), 180); } }
  $("#dockMenu")?.addEventListener('click', () => { if (mSide?.classList.contains('open')) closeMenu(); else openMenu(); });
  mOverlay?.addEventListener('click', closeMenu);
  w.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeMenu(); });

  // Heartbeat
  const heartbeat = () => fetch('/api/heartbeat.php',{credentials:'same-origin'}).catch(()=>{});
  heartbeat(); setInterval(heartbeat, 30000);
  
  // guard so it doesn't attach twice
  if (!window.__CX_IDLE__) {
    window.__CX_IDLE__ = true;

    (function () {
      const LIMIT = 2880 * 60 * 1000; // 10 minutes
      let tm;

      function resetTimer() {
        clearTimeout(tm);
        tm = setTimeout(() => {
          // call server logout, then go to login with expired flag
          fetch('/logout.php?timeout=1', { credentials: 'include' })
            .finally(() => { location.href = '/?expired=1'; });
        }, LIMIT);
      }

      ['click','mousemove','keydown','scroll','touchstart','visibilitychange']
        .forEach(ev => document.addEventListener(ev, resetTimer, { passive:true }));

      resetTimer();
    })();
  }

  // Stats (if present)
  async function fetchStats() {
    try {
      const r = await fetch('/api/stats.php', {credentials:'same-origin'}); if (!r.ok) return;
      const j = await r.json(); if (!j.ok) return;
      const map = { gTotalUsers:'total_users', gLiveCards:'live_cards', gChargeCards:'charge_cards', gTotalHits:'total_hits' };
      Object.entries(map).forEach(([id,key])=>{ const el=d.getElementById(id); if (el) el.textContent = nf(j.data[key]); });
      const rightNow = d.getElementById('rightOnlineNow'); if (rightNow) rightNow.textContent = j.data.online_now;
    } catch(e){}
  }
  fetchStats(); setInterval(fetchStats, 10000);

  // Tiles builder
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
          <img src="${img}" class="w-10 h-10 rounded-lg object-cover" alt="">
          <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full ${u.is_online?'bg-emerald-400':'bg-slate-500'} ring-2 ring-slate-900"></span>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-sm font-medium truncate">${(u.full_name||u.username||'user')}</div>
          <div class="text-xs text-slate-400 truncate">@${(u.username||'user')}</div>
        </div>
        ${badge(u.status)}
      </div>`;
  }
  function tileTop(u){
    const img = u.profile ? u.profile : 'https://api.dicebear.com/7.x/shapes/svg?seed='+encodeURIComponent(u.username||'user');
    return `
      <div class="user-tile flex items-center gap-3">
        <div class="relative shrink-0">
          <img src="${img}" class="w-10 h-10 rounded-lg object-cover" alt="">
          ${u.is_online ? `<span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-slate-900"></span>` : ``}
        </div>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <div class="text-sm font-medium truncate">${(u.full_name||u.username||'user')}</div>
            ${badge(u.status)}
          </div>
          <div class="text-xs text-slate-400 truncate">@${(u.username||'user')}</div>
        </div>
        <div class="text-xs rounded-md px-2 py-0.5 bg-violet-500/15 text-violet-300 font-medium">${nf(u.hits||0)} Hits</div>
      </div>`;
  }

  async function fetchUsers(scope, targetId) {
    try {
      const r = await fetch('/api/online_users.php?scope='+encodeURIComponent(scope)+'&limit=100', {credentials:'same-origin'});
      const j = await r.json(); if (!j.ok) return;
      if (scope==='online') { const els=[d.getElementById('rightOnlineNow'), d.getElementById('modalOnlineNow')]; els.forEach(el=>{ if(el) el.textContent = j.count ?? (j.users?.length||0); }); }
      const box = d.getElementById(targetId); if (!box) return;
      const arr = Array.isArray(j.users)? j.users : [];
      box.innerHTML = arr.map(u => scope==='top' ? tileTop(u) : tileOnline(u)).join('') ||
        `<div class="text-sm text-slate-500">${scope==='top'?'No data.':'No one online.'}</div>`;
    } catch(e){}
  }

  if (d.getElementById('rightOnlineList')) {
    fetchUsers('online','rightOnlineList'); fetchUsers('top','rightTopList');
    setInterval(()=>{ fetchUsers('online','rightOnlineList'); fetchUsers('top','rightTopList'); },15000);
  }

  // Modals (mobile)
  const topModal = d.getElementById('topModal');
  const onlineModal = d.getElementById('onlineModal');
  d.getElementById('dockTop')?.addEventListener('click', () => { topModal?.classList.remove('hidden'); fetchUsers('top','topModalList'); });
  d.getElementById('dockOnline')?.addEventListener('click', () => { onlineModal?.classList.remove('hidden'); fetchUsers('online','onlineModalList'); });
  d.querySelectorAll('[data-close]')?.forEach(btn => btn.addEventListener('click', () => d.getElementById(btn.dataset.close)?.classList.add('hidden')));
  [topModal, onlineModal].forEach(m => m?.addEventListener('click', (e) => { if (e.target === m.firstElementChild) m.classList.add('hidden'); }));
})(window, document);
</script>
</body>
</html>
