<a href="#" class="flex items-center gap-3 mb-4">
  <img src="<?= $u_pic ? htmlspecialchars($u_pic, ENT_QUOTES) : 'https://api.dicebear.com/7.x/identicon/svg?seed='.urlencode($unameSafe)?>" class="w-9 h-9 rounded-lg object-cover" alt="">
  <div class="min-w-0">
    <div class="font-semibold truncate max-w-[130px]"><?= $nameSafe ?></div>
    <div class="text-xs text-slate-400 truncate">@<?= htmlspecialchars($u_username, ENT_QUOTES) ?></div>
  </div>
  <?= roleBadge($u_status) ?>
</a>
<nav class="px-1 text-sm space-y-1">
  <a data-view="dashboard" class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $view==='dashboard'?'bg-white/5':''?>" href="?view=dashboard">Dashboard</a>
  <a data-view="buy" class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $view==='buy'?'bg-white/5':''?>" href="?view=buy">Buy Premium</a>
  <a data-view="redeem" class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $view==='redeem'?'bg-white/5':''?>" href="?view=redeem">Redeem</a>
  <a data-view="checkers" class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $view==='checkers'?'bg-white/5':''?>" href="?view=checkers">Checkers</a>
  <a data-view="autohitters" class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $view==='autohitters'?'bg-white/5':''?>" href="?view=autohitters">Auto Hitters</a>
  <a data-view="killers" class="block px-3 py-2 rounded-xl hover:bg-white/5 <?= $view==='killers'?'bg-white/5':''?>" href="?view=killers">CC Killer</a>
  <div class="pt-6">
    <a href="/logout.php" class="w-full inline-flex items-center justify-center rounded-xl bg-rose-500/15 text-rose-300 px-3 py-2 hover:bg-rose-500/20">Logout</a>
  </div>
</nav>
