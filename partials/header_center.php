<header class="sticky top-0 z-10 bg-slate-950/80 backdrop-blur border-b border-white/10">
  <div class="px-4 lg:px-6 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-500 to-emerald-400 flex items-center justify-center shadow">
        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zM7.5 12l2.5 2.5L16.5 8l1.5 1.5-8 8L6 13.5 7.5 12z"/></svg>
      </div>
      <div class="font-semibold">CyborX</div>
    </div>

    <button id="btnNotif" class="relative rounded-xl px-3 py-2 bg-white/5 hover:bg-white/10">
      <svg class="w-5 h-5 text-slate-200" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2zm6-6v-5a6 6 0 1 0-12 0v5l-2 2v1h16v-1l-2-2z"/></svg>
      <span id="notifDot" class="hidden absolute -top-1 -right-1 w-2 h-2 bg-rose-400 rounded-full"></span>
    </button>
  </div>

  <?php if ($showBanner): ?>
  <div class="px-4 lg:px-6 pb-3">
    <div class="rounded-2xl border border-amber-400/30 bg-gradient-to-r from-amber-500/10 to-rose-500/10 text-amber-200 px-4 py-3 flex items-start gap-3">
      <svg class="w-5 h-5 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
      <div class="text-sm leading-relaxed"><?= $bannerHtml ?></div>
    </div>
  </div>
  <?php endif; ?>
</header>
