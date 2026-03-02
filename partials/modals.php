<div id="topModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/60"></div>
  <div class="absolute inset-x-4 md:inset-x-20 top-10 bottom-10 rounded-2xl bg-slate-900 border border-white/10 flex flex-col">
    <div class="flex items-center justify-between p-4 border-b border-white/10">
      <div class="font-semibold">Top Users</div>
      <button data-close="topModal" class="rounded-md bg-white/10 hover:bg-white/20 p-2" aria-label="Close">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="p-4 flex-1 overflow-hidden">
      <div id="topModalList" class="no-scrollbar h-full space-y-2 pr-1"></div>
    </div>
  </div>
</div>

<div id="onlineModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/60"></div>
  <div class="absolute inset-x-4 md:inset-x-20 top-10 bottom-10 rounded-2xl bg-slate-900 border border-white/10 flex flex-col">
    <div class="flex items-center justify-between p-4 border-b border-white/10">
      <div class="font-semibold">Online Users • <span id="modalOnlineNow">—</span></div>
      <button data-close="onlineModal" class="rounded-md bg-white/10 hover:bg-white/20 p-2" aria-label="Close">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="p-4 flex-1 overflow-hidden">
      <div id="onlineModalList" class="no-scrollbar h-full space-y-2 pr-1"></div>
    </div>
  </div>
</div>
