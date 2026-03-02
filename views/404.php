<?php
// views/404.php
// http_response_code(404);
$path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$pathSafe = htmlspecialchars($path, ENT_QUOTES);
?>
<!-- 404 Card -->
<div class="rounded-2xl border border-white/10 bg-gradient-to-br from-slate-800/60 to-slate-900/60 p-6 md:p-8 relative overflow-hidden shadow-2xl">

  <!-- Watermark 404 -->
  <div class="pointer-events-none select-none absolute right-6 top-4 md:top-6 text-7xl md:text-8xl font-black tracking-tighter text-white/5">
    404
  </div>

  <!-- Header row -->
  <div class="flex items-start gap-4">

    <!-- Copy current URL -->
    <button id="cxCopyUrl"
            class="shrink-0 rounded-xl bg-white/10 border border-white/10 p-3 hover:bg-white/15 transition"
            title="Copy URL" aria-label="Copy URL">
      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="9" y="9" width="13" height="13" rx="2"></rect>
        <rect x="2" y="2" width="13" height="13" rx="2"></rect>
      </svg>
    </button>

    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2 mb-2">
        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold bg-rose-500/15 text-rose-300 border border-rose-400/20">
          ERROR
        </span>
        <span class="text-xs text-slate-400">Code: <span class="font-mono text-slate-300">404</span></span>
      </div>

      <h1 class="text-2xl md:text-[26px] font-semibold">Page not found</h1>
      <p class="text-sm text-slate-400 mt-1">
        The URL you requested doesn’t exist or may have been moved.
      </p>

      <!-- Path preview -->
      <div class="mt-3 text-xs font-mono text-slate-400 bg-white/5 inline-flex items-center gap-2 rounded-lg px-2.5 py-1 border border-white/10">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17V7a2 2 0 012-2h7l5 5v7a2 2 0 01-2 2H6a2 2 0 01-2-2z"/><path d="M14 3v4a1 1 0 001 1h4"/></svg>
        <span class="truncate max-w-[60ch]"><?= $pathSafe ?></span>
      </div>

      <!-- Actions -->
      <div class="mt-4 flex flex-wrap gap-2">
        <button type="button" onclick="history.length>1?history.back():location.href='/app/dashboard'"
                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 bg-white/10 hover:bg-white/15 border border-white/10 text-slate-200 text-sm">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M15 18l-6-6 6-6"/></svg>
          Back
        </button>

        <a href="/app/dashboard" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 bg-emerald-600/20 hover:bg-emerald-600/25 border border-emerald-500/25 text-emerald-200 text-sm">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3l9 8h-3v9H6v-9H3l9-8z"/></svg>
          Dashboard
        </a>

        <a href="/app/tools" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 bg-violet-600/20 hover:bg-violet-600/25 border border-violet-500/25 text-violet-200 text-sm">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14.7 6.3l3 3L9 18H6v-3l8.7-8.7zM17 2l5 5-2 2-5-5 2-2z"/></svg>
          Tools
        </a>

        <a href="/app/settings" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 bg-white/10 hover:bg-white/15 border border-white/10 text-slate-200 text-sm">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94a8 8 0 000-1.88l2.03-1.58-1.92-3.32-2.39.96a8.06 8.06 0 00-1.63-.94L14.5 2h-5l-.7 2.18c-.57.23-1.12.53-1.63.94l-2.39-.96L2.86 7.5l2.03 1.58a8.6 8.6 0 000 1.84L2.86 12.5l1.92 3.32 2.39-.96c.5.4 1.05.71 1.63.94L9.5 22h5l.7-2.18c.57-.23 1.12-.53 1.63-.94l2.39.96 1.92-3.32-2.03-1.58z"/></svg>
          Settings
        </a>
      </div>

      <!-- Tips -->
      <div class="mt-4 grid gap-2 text-sm text-slate-400">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-emerald-300" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2l-3.5-3.5L4 14.2l5 5 11-11-1.5-1.5z"/></svg>
          Check if the URL is correct—there might be a typo.
        </div>
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-emerald-300" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2l-3.5-3.5L4 14.2l5 5 11-11-1.5-1.5z"/></svg>
          Try navigating from the menu instead.
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // copy current URL
  (function(){
    const btn = document.getElementById('cxCopyUrl');
    if(!btn) return;
    btn.addEventListener('click', async ()=>{
      try{
        await navigator.clipboard.writeText(location.href);
        btn.classList.add('ring-2','ring-emerald-400/40');
        btn.innerHTML = '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2l-3.5-3.5L4 14.2l5 5 11-11-1.5-1.5z"/></svg>';
        setTimeout(()=>{ btn.classList.remove('ring-2','ring-emerald-400/40'); btn.innerHTML =
          '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><rect x="2" y="2" width="13" height="13" rx="2"></rect></svg>';
        }, 1200);
      }catch(e){}
    });
  })();
</script>
