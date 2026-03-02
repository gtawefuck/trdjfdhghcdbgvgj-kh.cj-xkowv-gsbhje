<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Bootstrap.php';
require_once __DIR__ . '/../app/Db.php';
use App\Db;
session_start();
if (empty($_SESSION['uid'])) { header('Location: /'); exit; }
$pdo = Db::pdo();
$uid = (int)$_SESSION['uid'];
/** user info */
$st = $pdo->prepare("SELECT username, first_name, last_name, profile_picture, status, kcoin
                     FROM users WHERE id = :id LIMIT 1");
$st->execute([':id' => $uid]);
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user) { header('Location: /'); exit; }
$status = strtolower((string)($user['status'] ?? 'free'));
$fname = trim((string)($user['first_name'] ?? ''));
$lname = trim((string)($user['last_name'] ?? ''));
$displayName = $fname || $lname ? trim($fname.' '.$lname) : ($user['username'] ?? ('u_'.$uid));
$profilePic = $user['profile_picture'] ?: '/assets/images/default_profile.png';
$kcoin = (int)($user['kcoin'] ?? 0);
/** user proxies (for config modal) */
$stmt = $pdo->prepare("SELECT id, host, port, username, password, ptype
                       FROM user_proxies WHERE user_id = :uid ORDER BY created_at DESC");
$stmt->execute([':uid' => $uid]);
$userProxies = $stmt->fetchAll(PDO::FETCH_ASSOC);
$proxySet = !empty($userProxies);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Card Killer • CyborX</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@tailwindcss/browser@4"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>
  :root{ --bg:#0b1220; --card:#0f172a; --border:rgba(255,255,255,.10); --muted:#94a3b8; --text:#e5e7eb; --accent1:#22c55e; --accent2:#60a5fa; }
  html,body{height:100%;margin:0;overflow-x:hidden}
  body{font-family:'Inter',sans-serif;color:var(--text);
    background:
      radial-gradient(1200px 600px at 10% -10%, rgba(16,185,129,.12), transparent 60%),
      radial-gradient(1000px 500px at 110% 10%, rgba(59,130,246,.12), transparent 60%),
      linear-gradient(180deg,#0a0f1f 0%,#0b1220 100%);
  }
  main::before{content:"";position:fixed;inset:0;pointer-events:none;
    background-image:
      linear-gradient(to right, rgba(255,255,255,.04) 1px, transparent 1px),
      linear-gradient(to bottom, rgba(255,255,255,.04) 1px, transparent 1px);
    background-size:40px 40px; mask-image: radial-gradient(800px 400px at 50% 0%, rgba(255,255,255,.4), transparent 70%);
  }
  .glass{position:relative;border-radius:18px;background:linear-gradient(180deg, rgba(17,24,39,.55), rgba(15,23,42,.55));border:1px solid var(--border);overflow:hidden}
  .card-shadow{box-shadow:0 10px 30px rgba(0,0,0,.35)}
  .section-title{font-family:"Space Grotesk",sans-serif;letter-spacing:.2px;background:linear-gradient(90deg,#e5e7eb 0%,#8abef8 60%,#34d399 100%);-webkit-background-clip:text;background-clip:text;color:transparent}
  .btn-primary{background:linear-gradient(90deg,var(--accent1) 0%,var(--accent2) 100%);color:#05131b;font-weight:700}
  .btn-primary:hover{filter:brightness(1.05)}
  .btn-muted{background:#111827;border:1px solid var(--border);color:#e5e7eb}
  .btn-muted:hover{background:#1f2937}
  .input-like{appearance:none;border:1px solid var(--border);background:#0b1730;color:var(--text);border-radius:12px;padding:.9rem 1rem;font-size:1rem;outline:none}
  .input-like:focus{border-color:rgba(16,185,129,.6);box-shadow:0 0 0 4px rgba(16,185,129,.12)}
  .pill{display:inline-flex;align-items:center;gap:.5rem;background:#0b1730;border:1px solid var(--border);border-radius:9999px;padding:.45rem .7rem;font-size:.85rem}
  .progress-wrap{height:14px;border-radius:10px;background:#0e1730;border:1px solid rgba(255,255,255,.10);overflow:hidden}
  .progress-bar{height:100%;width:0%;background:linear-gradient(90deg,#22c55e,#60a5fa)}
  .status-big{font-family:"Space Grotesk",sans-serif;font-size:1.6rem;line-height:1.25;margin:0}
  .status-ok{color:#34d399}
  .status-bad{color:#f87171}
  /* Config modal styles (sheet + list) */
  .sheet{width:100%;max-width:720px}
  .stepper{display:flex;gap:.5rem;margin-bottom:1rem}
  .step{flex:1;text-align:center;font-size:.85rem;padding:.55rem .4rem;border-radius:.6rem;border:1px solid rgba(255,255,255,.10);background:#0e1730;color:#cbd5e1}
  .step.on{background:linear-gradient(90deg,#22c55e33,#60a5fa33);border-color:rgba(16,185,129,.35);color:#e5e7eb;font-weight:600}
  .fsel-list{display:grid;gap:.5rem}
  .fsel-item{display:flex;align-items:center;justify-content:space-between;padding:.85rem 1rem;border-radius:12px;background:#0d152b;border:1px solid rgba(255,255,255,.10);transition:transform .12s ease,border-color .12s ease,background .12s ease}
  .fsel-item:hover{transform:translateY(-1px);border-color:rgba(34,197,94,.35);background:#0f1936}
  .fsel-left{display:flex;gap:.8rem;align-items:center}
  .fsel-ico{width:28px;height:28px;display:grid;place-items:center;border-radius:8px;background:rgba(148,163,184,.12);font-size:16px}
  .fsel-title{font-weight:600}
  .fsel-sub{font-size:.82rem;color:#9fb3c8;margin-top:.1rem}
  .badge-s{font-size:.70rem;padding:.12rem .4rem;border-radius:.4rem;border:1px solid rgba(255,255,255,.12)}
  .badge-ok{color:#22c55e;background:rgba(34,197,94,.12)}
  .badge-down{color:#f87171;background:rgba(248,113,113,.12)}
  .fsel-item.active{outline:2px solid rgba(34,197,94,.35)}
</style>
</head>
<body>
<main class="max-w-screen-lg mx-auto px-4 sm:px-6 lg:px-8 py-6">
  <!-- Header -->
  <div class="mb-6 grid sm:grid-cols-3 gap-4">
    <div class="sm:col-span-2 flex items-center gap-3">
      <div class="p-[2px] rounded-full" style="background:linear-gradient(135deg, rgba(16,185,129,.6), rgba(59,130,246,.6));">
        <img src="<?= htmlspecialchars($profilePic, ENT_QUOTES, 'UTF-8') ?>" class="w-12 h-12 rounded-full border border-white/20 object-cover" alt="">
      </div>
      <div class="min-w-0">
        <div class="font-semibold truncate"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="text-xs text-slate-400">@<?= htmlspecialchars($user['username'] ?? ('u_'.$uid), ENT_QUOTES, 'UTF-8') ?> • <?= ucfirst($status) ?></div>
      </div>
    </div>
    <!-- Kcoin card (Configure button removed from here) -->
    <div class="glass card-shadow px-4 py-3 flex items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 grid place-items-center rounded-xl" style="background:linear-gradient(135deg,#22c55e22,#60a5fa22);border:1px solid rgba(255,255,255,.08)"><i class="fa-solid fa-coins text-yellow-300"></i></div>
        <div>
          <div class="text-xs text-slate-400">Kcoin Balance</div>
          <div class="font-semibold text-lg"><span id="kcoin"><?= (int)$kcoin ?></span></div>
        </div>
      </div>
      <a href="/app/buy" class="btn-primary rounded-lg px-3 py-2 text-sm whitespace-nowrap"><i class="fa-solid fa-wallet mr-1"></i> Top up Kcoin</a>
    </div>
  </div>
  <!-- Killer -->
  <section class="glass card-shadow p-6">
    <!-- Title row: responsive (wraps on small screens) -->
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <div class="flex items-center gap-3 flex-wrap">
        <h2 class="text-2xl section-title">Card Killer</h2>
        <span id="pillApi" class="pill"><i class="fa-solid fa-plug-circle-bolt text-emerald-400"></i> API: <b id="apiName" class="ml-1">—</b></span>
      </div>
      <button id="openCfgBtn" class="btn-muted rounded-lg px-3 py-2 text-sm shrink-0">
        <i class="fa-solid fa-sliders mr-1"></i> Configure
      </button>
    </div>
    <!-- Input row -->
    <div class="mt-5 grid md:grid-cols-[1fr_auto] gap-3">
      <input id="cc" class="input-like font-mono w-full" placeholder="cc|mm|yyyy|cvv (e.g. 491611******1234|12|2027|123)">
      <div class="flex gap-2 justify-stretch sm:justify-end">
        <button id="eliminate" class="btn-primary rounded-lg px-5 py-3 text-sm w-full sm:w-auto"><i class="fa-solid fa-jet-fighter-up mr-1"></i> Eliminate</button>
        <button id="clear" class="btn-muted rounded-lg px-4 py-3 text-sm w-full sm:w-auto"><i class="fa-solid fa-eraser mr-1"></i> Clear</button>
      </div>
    </div>
    <!-- Progress -->
    <div id="pwrap" class="mt-5 hidden">
      <div class="progress-wrap"><div id="pbar" class="progress-bar"></div></div>
      <div class="mt-2 text-xs text-slate-300 flex items-center gap-2">
        <span id="ppct" class="font-semibold">0%</span>
        <span id="pmsg" class="text-slate-400">Initializing…</span>
      </div>
    </div>
    <!-- Panels -->
    <div class="mt-6 grid md:grid-cols-2 gap-4">
      <div class="glass p-4 border border-white/10">
        <div class="text-sm text-slate-300 mb-2 font-semibold">BIN Info</div>
        <ul class="text-sm text-slate-300 space-y-1">
          <li><span class="text-slate-400">Brand:</span> <span id="binBrand">—</span></li>
          <li><span class="text-slate-400">Issuer:</span> <span id="binIssuer">—</span></li>
          <li><span class="text-slate-400">Country:</span> <span id="binCountry">—</span></li>
        </ul>
      </div>
      <div class="glass p-4 border border-white/10 flex items-center">
        <p id="final" class="status-big text-slate-200">—</p>
      </div>
    </div>
  </section>
</main>
<!-- Credits Modal -->
<div id="creditsModal" class="fixed inset-0 z-[100] hidden">
  <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
  <div class="relative min-h-full flex items-end sm:items-center justify-center p-4">
    <div id="creditsCard" class="w-full max-w-md translate-y-4 sm:translate-y-0 opacity-0 transition-all duration-200">
      <div class="rounded-2xl border border-white/10 bg-gradient-to-b from-slate-900 to-slate-800 shadow-2xl">
        <div class="px-5 py-4 border-b border-white/10">
          <h3 class="text-lg font-semibold">Credits System</h3>
          <p class="text-xs text-slate-400 mt-0.5">Please review how credits are used across gates. No credits deduction for Dead Cards ⚠️</p>
        </div>
        <div class="px-5 py-4 space-y-4 text-sm text-slate-300">
          <div>
            <div class="text-slate-200 font-medium mb-1">Auth Gates</div>
            <ul class="space-y-1">
              <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Approved = <span class="font-semibold"> - 3 credits</span></li>
              <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-sky-400"></span> Live = <span class="font-semibold"> - 1 credits</span></li>
            </ul>
          </div>
          <div class="border-t border-white/10 pt-3">
            <div class="text-slate-200 font-medium mb-1">Charge Gates</div>
            <ul class="space-y-1">
              <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-fuchsia-400"></span> Charge = <span class="font-semibold"> - 5 credits</span></li>
              <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Live = <span class="font-semibold"> - 3 credits</span></li>
            </ul>
          </div>
        </div>
        <div class="px-5 pb-5">
          <button id="ackBtn"
            class="w-full rounded-xl px-4 py-2.5 font-semibold text-slate-900"
            style="background: linear-gradient(90deg,#34d399,#67e8f9)">
            I acknowledge
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Configuration Modal (API + Proxy) -->
<div id="cfgModal" class="fixed inset-0 z-[120] hidden">
  <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
  <div class="relative min-h-full flex items-end sm:items-center justify-center p-4">
    <div class="sheet translate-y-4 opacity-0 transition-all duration-200">
      <div class="rounded-2xl border border-white/10 bg-gradient-to-b from-slate-900 to-slate-800 shadow-2xl p-5">
        <div class="stepper">
          <div id="s1" class="step on">1. API</div>
          <div id="s2" class="step">2. Proxy</div>
        </div>
        <div id="step1">
          <div class="text-sm text-slate-300 mb-2">Select a test API</div>
          <div id="apiList" class="fsel-list"></div>
        </div>
        <div id="step2" class="hidden">
          <div class="rounded-xl border border-white/10 p-4">
            <div class="text-sm font-semibold mb-2">Proxy Settings</div>
            <div class="flex items-center gap-3 mb-2">
              <label class="inline-flex items-center cursor-pointer">
                <input id="cfgProxySwitch" type="checkbox" class="peer sr-only">
                <span class="w-11 h-6 bg-slate-700 rounded-full relative
                             after:content-[''] after:absolute after:top-0.5 after:left-0.5
                             after:w-5 after:h-5 after:bg-white after:rounded-full after:transition-all
                             duration-300 peer-checked:bg-emerald-500 peer-checked:after:left-[1.5rem]"></span>
              </label>
              <span class="text-sm text-slate-200">Use proxy</span>
            </div>
            <div id="cfgProxySelectWrap" class="hidden">
              <label class="block text-xs text-slate-300 mb-1">Select Proxy</label>
              <select id="cfgProxySelect" class="w-full px-3 py-2 text-sm" style="background:#0b1730;color:#e5e7eb;border:1px solid rgba(255,255,255,.12)">
                <option value="random">Random</option>
                <?php foreach ($userProxies as $proxy): ?>
                  <option value="<?= htmlspecialchars(json_encode(['host'=>$proxy['host'],'port'=>$proxy['port'],'username'=>$proxy['username']??'','password'=>$proxy['password']??'','ptype'=>$proxy['ptype']??'http']), ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars("{$proxy['host']}:{$proxy['port']} ({$proxy['ptype']})", ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if(!$proxySet): ?>
                <div class="mt-2 text-xs text-amber-300">No saved proxies found. Add one in Settings.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="mt-5 flex items-center justify-between">
          <button id="cfgCancel" class="px-4 py-2 rounded-lg btn-muted text-sm">Cancel</button>
          <div class="flex gap-2">
            <button id="cfgBack" class="px-4 py-2 rounded-lg btn-muted text-sm hidden">Back</button>
            <button id="cfgNext" class="px-4 py-2 rounded-lg btn-primary text-sm">Next</button>
            <button id="cfgSave" class="px-4 py-2 rounded-lg btn-primary text-sm hidden">Save & Apply</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
const $ = s => document.querySelector(s);
const toast = (t, icon='info') => Swal.fire({toast: true, position: 'top-end', timer: 2400, showConfirmButton: false, icon, title: t, background: 'rgba(2,6,23,.95)', color: '#fff', iconColor: '#34d399'});
/* API registry (endpoint hidden from UI) */
const API_OPTIONS = [
  { id: 'api1', label: 'Test API v1', endpoint: 'https://cyborx.net/api/killer/api.php', alive: true }, // Updated to absolute URL
  { id: 'api2', label: 'Test API v2', endpoint: 'https://cyborx.net/api/killer/api2.php', alive: false },
  { id: 'api3', label: 'Test API v3', endpoint: 'https://cyborx.net/api/killer/api3.php', alive: false },
];
const LS_CFG_KEY = 'cx_killer_cfg_v1';
const ACK_KEY = 'cx_ack_killer_credits_v1';
/* Progress stages */
const PROGRESS_STAGES = [
  { percent: 0, text: "Deploying Elimination Core..." },
  { percent: 20, text: "Analyzing Target (Attempt 1)..." },
  { percent: 40, text: "Breaking Encryption (Attempt 2)..." },
  { percent: 60, text: "Disrupting Security (Attempt 3)..." },
  { percent: 80, text: "Launching Termination (Attempt 4)..." },
  { percent: 100, text: "Finalizing Results (Attempt 5)..." }
];
const pwrap = $('#pwrap'), pbar = $('#pbar'), ppct = $('#ppct'), pmsg = $('#pmsg');
function setStage(i) { const s = PROGRESS_STAGES[Math.max(0, Math.min(i, PROGRESS_STAGES.length - 1))]; pbar.style.width = s.percent + '%'; ppct.textContent = s.percent + '%'; pmsg.textContent = s.text; }
function showProgress(on) { pwrap.classList.toggle('hidden', !on); if (on) setStage(0); }
/* State */
let SELECTED_API = API_OPTIONS.find(a => a.alive) || API_OPTIONS[0];
let CFG = { apiId: SELECTED_API?.id || 'api1', useProxy: false, proxyVal: 'random' };
function applyApiPill() {
  $('#apiName').textContent = (API_OPTIONS.find(a => a.id === CFG.apiId)?.label || '—');
  const pill = $('#pillApi');
  const alive = (API_OPTIONS.find(a => a.id === CFG.apiId)?.alive);
  pill.style.background = alive ? 'rgba(34,197,94,.12)' : 'rgba(248,113,113,.12)';
  pill.style.borderColor = 'rgba(255,255,255,.12)';
}
function loadCfg() { try { const raw = localStorage.getItem(LS_CFG_KEY); if (raw) CFG = Object.assign(CFG, JSON.parse(raw)); } catch {} SELECTED_API = API_OPTIONS.find(a => a.id === CFG.apiId) || SELECTED_API; applyApiPill(); }
function saveCfg() { try { localStorage.setItem(LS_CFG_KEY, JSON.stringify(CFG)); } catch {} }
loadCfg();
/* Credits modal (first time) */
const creditsModal = $('#creditsModal'), creditsCard = $('#creditsCard');
function openCredits() { creditsModal.classList.remove('hidden'); requestAnimationFrame(() => { creditsCard.classList.remove('translate-y-4', 'opacity-0'); creditsCard.classList.add('translate-y-0', 'opacity-100'); }); }
function closeCredits() { creditsCard.classList.add('translate-y-4', 'opacity-0'); setTimeout(() => creditsModal.classList.add('hidden'), 180); }
if (!localStorage.getItem(ACK_KEY)) openCredits();
$('#ackBtn')?.addEventListener('click', () => { localStorage.setItem(ACK_KEY, String(Date.now())); closeCredits(); });
/* Config modal */
const cfgModal = $('#cfgModal'), cfgSheet = cfgModal?.querySelector('.sheet');
const btnOpenCfg = $('#openCfgBtn'), btnCfgCancel = $('#cfgCancel'), btnCfgNext = $('#cfgNext'), btnCfgBack = $('#cfgBack'), btnCfgSave = $('#cfgSave');
const step1 = $('#step1'), step2 = $('#step2'), s1 = $('#s1'), s2 = $('#s2');
const cfgProxySwitch = $('#cfgProxySwitch'), cfgProxyWrap = $('#cfgProxySelectWrap'), cfgProxySelect = $('#cfgProxySelect');
let STEP = 1;
function stepUI() { step1.classList.toggle('hidden', STEP !== 1); step2.classList.toggle('hidden', STEP !== 2); s1.classList.toggle('on', STEP === 1); s2.classList.toggle('on', STEP === 2); btnCfgBack.classList.toggle('hidden', STEP === 1); btnCfgNext.classList.toggle('hidden', STEP === 2); btnCfgSave.classList.toggle('hidden', STEP !== 2); }
function buildApiList() {
  const box = $('#apiList'); box.innerHTML = '';
  API_OPTIONS.forEach(a => {
    const li = document.createElement('div');
    li.className = 'fsel-item' + (CFG.apiId === a.id ? ' active' : ''); li.style.opacity = a.alive ? '1' : '.55';
    li.innerHTML = `
      <div class="fsel-left">
        <div class="fsel-ico">🔌</div>
        <div><div class="fsel-title">${a.label}</div><div class="fsel-sub">${a.alive ? 'Active' : 'Inactive'}</div></div>
      </div>
      <div class="fsel-right"><span class="badge-s ${a.alive ? 'badge-ok' : 'badge-down'}">${a.alive ? 'Active' : 'Inactive'}</span></div>`;
    if (a.alive) { li.addEventListener('click', () => { CFG.apiId = a.id; buildApiList(); }); }
    box.appendChild(li);
  });
}
function openCfg() { STEP = 1; stepUI(); buildApiList(); cfgProxySwitch.checked = !!CFG.useProxy; cfgProxyWrap.classList.toggle('hidden', !cfgProxySwitch.checked); if (cfgProxySelect) cfgProxySelect.value = CFG.proxyVal || 'random'; cfgModal.classList.remove('hidden'); requestAnimationFrame(() => { cfgSheet.classList.remove('translate-y-4', 'opacity-0'); cfgSheet.classList.add('translate-y-0', 'opacity-100'); }); }
function closeCfg() { cfgSheet.classList.add('translate-y-4', 'opacity-0'); setTimeout(() => cfgModal.classList.add('hidden'), 180); }
btnOpenCfg?.addEventListener('click', openCfg);
btnCfgCancel?.addEventListener('click', closeCfg);
btnCfgNext?.addEventListener('click', () => { if (STEP === 1 && !CFG.apiId) return toast('Select an API', 'warning'); STEP = 2; stepUI(); });
btnCfgBack?.addEventListener('click', () => { STEP = 1; stepUI(); });
cfgProxySwitch?.addEventListener('change', () => { cfgProxyWrap.classList.toggle('hidden', !cfgProxySwitch.checked); });
btnCfgSave?.addEventListener('click', () => {
  CFG.useProxy = !!cfgProxySwitch.checked;
  CFG.proxyVal = cfgProxySelect?.value || 'random';
  SELECTED_API = API_OPTIONS.find(a => a.id === CFG.apiId) || SELECTED_API;
  applyApiPill(); saveCfg(); closeCfg(); toast('Configuration saved', 'success');
});
/* Five parallel fetches */
async function raceFive(ccLine, onBump) {
  const total = 5;
  const ALL_PROXIES = <?php echo json_encode(array_map(function($p) {
    return [
      'host' => $p['host'],
      'port' => $p['port'],
      'username' => $p['username'] ?? '',
      'password' => $p['password'] ?? '',
      'ptype' => $p['ptype'] ?? 'http'
    ];
  }, $userProxies)); ?>;

  function buildUrl(index) {
    const base = SELECTED_API?.endpoint || 'https://cyborx.net/api/killer/api.php'; // Fallback to absolute URL
    const u = new URL(base, window.location.origin);
    u.searchParams.set('cc', ccLine);
    u.searchParams.set('r', Math.random().toString(36).slice(2) + '-' + index); // Unique per request
    if (CFG.useProxy && CFG.proxyVal) {
      u.searchParams.set('useProxy', '1');
      try {
        let sel;
        if (CFG.proxyVal === 'random' && ALL_PROXIES.length > 0) {
          sel = ALL_PROXIES[Math.floor(Math.random() * ALL_PROXIES.length)];
        } else {
          sel = JSON.parse(CFG.proxyVal);
        }
        if (sel) {
          u.searchParams.set('host', sel.host || '');
          u.searchParams.set('port', sel.port || '');
          u.searchParams.set('user', sel.username || '');
          u.searchParams.set('pass', sel.password || '');
          if (sel.ptype) u.searchParams.set('pt', sel.ptype);
          console.log('Request ' + index + ' using proxy:', sel.host + ':' + sel.port);
        } else {
          console.log('Request ' + index + ' no valid proxy selected');
        }
      } catch (e) {
        console.error('Proxy parse error for request ' + index + ':', e);
      }
    }
    console.log('Request ' + index + ' URL:', u.toString());
    return u.toString();
  }

  const requests = Array.from({length: total}, (_, index) => {
    return fetch(buildUrl(index), { cache: 'no-store', keepalive: true }) // Use keepalive for faster responses
      .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status + ' for request ' + index);
        return r.json();
      })
      .then(data => ({
        index,
        success: true,
        data
      }))
      .catch(err => ({
        index,
        success: false,
        error: err.message
      }));
  });

  const results = await Promise.all(requests);
  let completed = 0;
  results.forEach(result => {
    completed++;
    if (typeof onBump === 'function') onBump(completed);
    console.log('Request ' + result.index + ' completed:', result.success ? 'Success' : 'Failure (' + (result.error || 'Unknown') + ')');
  });

  return results;
}
/* Actions */
$('#clear').addEventListener('click', () => {
  $('#cc').value = ''; $('#final').textContent = '—';
  $('#binBrand').textContent = '—'; $('#binIssuer').textContent = '—'; $('#binCountry').textContent = '—';
});
$('#eliminate').addEventListener('click', async () => {
  const raw = ($('#cc').value || '').trim();
  const re = /^(\d{13,19})[|/](0?[1-9]|1[0-2])[|/](\d{2,4})[|/](\d{3,4})$/;
  const m = raw.match(re);
  if (!m) { toast('Format: cc|mm|yyyy|cvv', 'error'); return; }
  if (!SELECTED_API || !SELECTED_API.alive) { toast('Please set an Active API', 'warning'); return; }
  showProgress(true); setStage(0);
  const t0 = performance.now();
  try {
    const results = await raceFive(raw, (completed) => {
      // Update progress for each completed request
      const stageIndex = Math.min(completed - 1, PROGRESS_STAGES.length - 1);
      setStage(stageIndex);
      if (completed === 5) {
        setStage(PROGRESS_STAGES.length - 1); // Final stage after all requests
      }
    });
    const secs = Math.max(1, Math.round((performance.now() - t0) / 1000));
    let kcoin = parseInt($('#kcoin').textContent) || 0;
    let validCount = 0;
    let binInfo = { brand: '—', issuer: '—', country: '—' };

    // Aggregate results and count valid responses
    results.forEach(result => {
      if (result.success) {
        if (typeof result.data.kcoin === 'number' && result.data.kcoin < kcoin) {
          kcoin = result.data.kcoin; // Update kcoin from the first deduction
        }
        if (String(result.data.status || '').toLowerCase() === 'valid') {
          validCount++;
          // Update BIN info from the first valid response
          if (!binInfo.brand || binInfo.brand === '—') {
            binInfo.brand = result.data.brand || 'UNKNOWN';
            binInfo.issuer = result.data.issuer || 'Unknown';
            binInfo.country = result.data.country_info || 'Unknown';
          }
        }
      }
    });

    // Update kcoin
    $('#kcoin').textContent = String(kcoin);

    // Determine final status based on majority (3 or more valid)
    const ok = validCount >= 3;
    $('#final').classList.toggle('status-ok', ok);
    $('#final').classList.toggle('status-bad', !ok);
    $('#final').textContent = ok
      ? `Card Eliminated ✅ - ${secs} seconds (Valid: ${validCount}/5)`
      : `Elimination Failed ❌ - ${secs} seconds (Valid: ${validCount}/5)`;

    // Update BIN info
    $('#binBrand').textContent = binInfo.brand;
    $('#binIssuer').textContent = binInfo.issuer;
    $('#binCountry').textContent = binInfo.country;
  } catch (e) {
    setStage(PROGRESS_STAGES.length - 1);
    $('#final').classList.remove('status-ok');
    $('#final').classList.add('status-bad');
    $('#final').textContent = 'Elimination Failed ❌';
    $('#binBrand').textContent = '—';
    $('#binIssuer').textContent = '—';
    $('#binCountry').textContent = '—';
    console.error('Error in raceFive:', e);
  } finally {
    setTimeout(() => showProgress(false), 450);
  }
});
</script>
</body>
</html>