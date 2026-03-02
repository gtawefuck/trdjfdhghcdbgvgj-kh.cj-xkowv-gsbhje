<aside class="hidden xl:flex flex-col border-l border-white/10 bg-slate-900/60 sticky top-0 h-screen p-4 space-y-6 overflow-x-hidden">
  <!-- Online Users -->
  <div class="rounded-2xl bg-white/5 p-4 h-[45%] overflow-x-hidden">
    <div class="flex items-center justify-between mb-2">
      <div class="text-slate-300 text-sm">Online Users</div>
      <div class="text-xs rounded-lg px-2 py-0.5 bg-emerald-500/15 text-emerald-300">
        Online Now: <span id="rightOnlineNow"><?= count($onlineSSR) ?></span>
      </div>
    </div>
    <div id="rightOnlineList" class="no-scrollbar h-[calc(100%_-_1.75rem)] space-y-2 pr-1">
      <?php foreach ($onlineSSR as $r):
          $full = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')) ?: ($r['username'] ?? 'user'); ?>
        <div class="user-tile flex items-center gap-3">
          <div class="relative shrink-0">
            <img src="<?= $r['profile_picture'] ? htmlspecialchars($r['profile_picture'], ENT_QUOTES) : 'https://api.dicebear.com/7.x/shapes/svg?seed='.urlencode($r['username'] ?? 'user')?>"
                 onerror="this.onerror=null;this.src='https://api.dicebear.com/7.x/shapes/svg?seed=<?=urlencode($r['username'] ?? 'user')?>';"
                 class="w-10 h-10 rounded-lg object-cover" alt="">
            <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-slate-900"></span>
          </div>
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium truncate"><?= htmlspecialchars($full, ENT_QUOTES) ?></div>
            <div class="text-xs text-slate-400 truncate">@<?= htmlspecialchars($r['username'] ?? 'user', ENT_QUOTES) ?></div>
          </div>
          <?= roleBadge((string)$r['status']) ?>
        </div>
      <?php endforeach; if (!$onlineSSR): ?>
        <div class="text-sm text-slate-500">No one online.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Top Users -->
  <div class="rounded-2xl bg-white/5 p-4 h-[45%] overflow-x-hidden">
    <div class="text-slate-300 text-sm mb-2">Top Users</div>
    <div id="rightTopList" class="no-scrollbar h-[calc(100%_-_1.5rem)] space-y-2 pr-1">
      <?php foreach ($topSSR as $r):
          $full = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')) ?: ($r['username'] ?? 'user'); ?>
        <div class="user-tile flex items-center gap-3">
          <div class="relative shrink-0">
            <img src="<?= $r['profile_picture'] ? htmlspecialchars($r['profile_picture'], ENT_QUOTES) : 'https://api.dicebear.com/7.x/shapes/svg?seed='.urlencode($r['username'] ?? 'user')?>"
                 onerror="this.onerror=null;this.src='https://api.dicebear.com/7.x/shapes/svg?seed=<?=urlencode($r['username'] ?? 'user')?>';"
                 class="w-10 h-10 rounded-lg object-cover" alt="">
            <?php if (!empty($r['is_online'])): ?>
              <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-slate-900"></span>
            <?php endif; ?>
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <div class="text-sm font-medium truncate"><?= htmlspecialchars($full, ENT_QUOTES) ?></div>
              <?= roleBadge((string)$r['status']) ?>
            </div>
            <div class="text-xs text-slate-400 truncate">@<?= htmlspecialchars($r['username'] ?? 'user', ENT_QUOTES) ?></div>
          </div>
          <div class="text-xs rounded-md px-2 py-0.5 bg-violet-500/15 text-violet-300 font-medium"><?= b((int)($r['hits'] ?? 0)) ?> Hits</div>
        </div>
      <?php endforeach; if (!$topSSR): ?>
        <div class="text-sm text-slate-500">No data.</div>
      <?php endif; ?>
    </div>
  </div>
</aside>
