<?php
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

$status     = strtolower((string)($row['status'] ?? 'free')); // free | premium | admin | banned
$credits    = (int)($row['credits'] ?? 0);
$fname      = trim((string)($row['first_name'] ?? ''));
$lname      = trim((string)($row['last_name'] ?? ''));
$displayName= $fname || $lname ? trim($fname . ' ' . $lname) : ($row['username'] ?: $uname);
$profilePic = $row['profile_picture'] ?: '/assets/images/default_profile.png';

if ($status === 'admin')      $maxChecks = 1000000000;
elseif ($status === 'premium')$maxChecks = 2000;
elseif ($status === 'free')   $maxChecks = 100;
else                          $maxChecks = 10;

/* Fetch user's saved proxies for the dropdown */
$stmt = $pdo->prepare("SELECT id, host, port, username, password, ptype FROM user_proxies WHERE user_id = :uid ORDER BY created_at DESC");
$stmt->execute([':uid' => $uid]);
$userProxies = $stmt->fetchAll(PDO::FETCH_ASSOC);
$proxySet    = !empty($userProxies);

// Threads (UI only)
$maxThreadsByPlan = ($status === 'premium' || $status === 'admin') ? 5 : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>All-in-One Checker • CyborX</title>
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
    body { font-family: 'Inter', sans-serif; color: var(--text); background: radial-gradient(1200px 600px at 10% -10%, rgba(16,185,129,.12), transparent 60%),
                                        radial-gradient(1000px 500px at 110% 10%, rgba(59,130,246,.12), transparent 60%),
                                        linear-gradient(180deg, #0a0f1f 0%, #0b1220 100%); }
    /* Fancy container grid lines */
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
    .btn-primary:hover{ transform: translateY(-1px); box-shadow: 0 8px 24px rgba(16,185,129,.25); }
    .btn-muted { background:#111827; border:1px solid var(--border); color:#e5e7eb; }
    .btn-muted:hover{ background:#1f2937; }
    
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
      /* NEW: word wrap */
      white-space: pre-wrap;
      word-break: break-word;
      overflow-wrap: anywhere;
    }

    .line-ok { border-left:3px solid #f59e0b; padding-left:.55rem; }
    .line-live { border-left:3px solid #34d399; padding-left:.55rem; }
    .line-dead { border-left:3px solid #ef4444; padding-left:.55rem; }
    
    /* === Blur Mode (scoped to #mainRoot): Card List + সব টেক্সট ইনপুট/টেক্সটএরিয়া + ফলাফলের কার্ড নম্বর === */
    #mainRoot.blur-mode #cardList,
    #mainRoot.blur-mode input[type="text"],
    #mainRoot.blur-mode textarea,
    #mainRoot.blur-mode .cc-mask {
      filter: blur(7px);
    }
        
    /* Tools Drawer */
    .tools-backdrop { position: fixed; inset: 0; background: rgba(2,6,23,.55); backdrop-filter: blur(2px); display:none; }
    .tools-backdrop.active { display:block; }
    .tools-drawer { position: fixed; right: -440px; top: 0; height: 100vh; width: 440px; max-width: 100vw;
    background: linear-gradient(180deg,#0b1220,#0c1628); border-left: 1px solid var(--border);
    transition: right .25s ease; z-index: 60; overflow-y: auto; }
    .tools-drawer.open { right: 0; }
    
    @media (max-width: 1024px) {
    .profile-pic { width: 44px; height: 44px; }
    }
  
    /* === Dark select + dropdown option FIX (Proxy select সাদা দেখানোর সমাধান) === */
    .select-container select,
    #proxySelect{
      background:#0b1730 !important;
      color:#e5e7eb !important;
      border:1px solid rgba(255,255,255,.12) !important;
      color-scheme: dark;
    }
    #proxySelect option{ background:#0b1730; color:#e5e7eb; }
    
    
    /* === Progress bar === */
    #progressWrap{ margin-top:12px; height:14px; border-radius:10px;
      background:#0e1730; border:1px solid rgba(255,255,255,.10); position:relative; overflow:hidden;}
    #progressBar{ position:absolute; inset:0; width:0%;
      background:linear-gradient(90deg,#22c55e,#60a5fa); }
    #progressText{ margin-top:8px; font-size:.85rem; color:#94a3b8; }
    
    /* === API status pill (Active/Inactive) === */
    .api-pill{ display:inline-flex; align-items:center; gap:.4rem; font-size:.78rem;
      padding:.25rem .5rem; border-radius:.5rem; border:1px solid rgba(255,255,255,.12); }
    .api-pill.ok{ color:#22c55e; background:rgba(34,197,94,.12); }
    .api-pill.down{ color:#f87171; background:rgba(248,113,113,.12); }
    
    /* ছোট ল্যাপটপে প্যাডিং/গ্যাপ টাইট করুন */
    @media (max-width:1366px){
      .glass.p-8{ padding:1.25rem !important; }
      .grid.gap-8{ gap:1.25rem !important; }
    }

    /* ==== Fancy dropdown (modal list look) ==== */
    .fsel-list{display:grid;gap:.5rem}
    .fsel-item{
      display:flex; align-items:center; justify-content:space-between;
      padding:.85rem 1rem; border-radius:12px;
      background:#0d152b; border:1px solid rgba(255,255,255,.10);
      transition:transform .12s ease, border-color .12s ease, background .12s ease;
    }
    .fsel-item:hover{ transform: translateY(-1px); border-color: rgba(34,197,94,.35); background:#0f1936; }
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
    
    /* ==== Config modal ==== */
    #cfgModal .sheet{ width:100%; max-width:720px; }
    .stepper{ display:flex; gap:.5rem; margin-bottom:1rem }
    .step{ flex:1; text-align:center; font-size:.85rem; padding:.55rem .4rem; border-radius:.6rem;
      border:1px solid rgba(255,255,255,.10); background:#0e1730; color:#cbd5e1;
    }
    .step.on{ background:linear-gradient(90deg,#22c55e33,#60a5fa33); border-color:rgba(16,185,129,.35); color:#e5e7eb; font-weight:600; }
    
    /* Hide inline selects (we’ll configure from modal) */
    #inlineConfigRow{ display:none !important; }
    
    /* Progress bar (already referenced) */
    #progressWrap{ margin-top:12px; height:14px; border-radius:10px;
      background:#0e1730; border:1px solid rgba(255,255,255,.10); position:relative; overflow:hidden;}
    #progressBar{ position:absolute; inset:0; width:0%; background:linear-gradient(90deg,#22c55e,#60a5fa); }
    
    /* Dark select fix (proxy select turning white) */
    #cfgProxySelect, #proxySelect{ background:#0b1730 !important; color:#e5e7eb !important; border:1px solid rgba(255,255,255,.12) !important; color-scheme: dark; }
    #cfgProxySelect option, #proxySelect option{ background:#0b1730; color:#e5e7eb; }

    /* Scrollable Gateway/API Lists and Sticky Buttons for Configuration Modal */
    #cfgModal .sheet {
      max-height: 90vh; /* Limit modal height to 90% of viewport height */
      display: flex;
      flex-direction: column;
    }
    
    #cfgModal .fsel-list {
      max-height: 50vh; /* Limit list height to allow scrolling */
      overflow-y: auto; /* Enable vertical scrolling */
      -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
      scrollbar-width: thin; /* Firefox: slim scrollbar */
      scrollbar-color: rgba(255,255,255,.2) transparent; /* Firefox: scrollbar style */
    }
    
    /* Webkit scrollbar styling */
    #cfgModal .fsel-list::-webkit-scrollbar {
      width: 6px;
    }
    #cfgModal .fsel-list::-webkit-scrollbar-track {
      background: transparent;
    }
    #cfgModal .fsel-list::-webkit-scrollbar-thumb {
      background: rgba(255,255,255,.2);
      border-radius: 3px;
    }
    
    /* Sticky buttons container */
    #cfgModal .sheet > div:last-child {
      position: sticky;
      bottom: 0;
      background: linear-gradient(180deg, rgba(15,23,42,.95), rgba(11,18,32,.95));
      padding: 1rem 1.25rem;
      border-top: 1px solid rgba(255,255,255,.1);
      margin-top: auto; /* Push buttons to bottom of flex container */
    }
    
    /* Ensure modal content is responsive on small screens */
    @media (max-width: 640px) {
      #cfgModal .sheet {
        width: 100%;
        max-width: 100%;
        max-height: 100vh;
        border-radius: 0;
      }
      #cfgModal .fsel-list {
        max-height: 40vh; /* Slightly shorter on mobile for better usability */
      }
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
    <!-- LEFT: Controls -->
    <section class="lg:col-span-2 glass card-shadow p-8">
      <h2 class="text-2xl section-title mb-6">CyborX Checkers & Multi APIs</h2>

    <!-- Hidden inline selects: we’ll configure them from the modal -->
    <div id="inlineConfigRow" class="grid sm:grid-cols-3 gap-6">
      <div class="sm:col-span-1 select-container">
        <label class="block text-sm font-medium text-slate-200 mb-2">Select Gateway</label>
        <select id="gateway" class="w-full px-4 py-3 text-sm">
          <option value="">Select Gateway</option>
          <option value="amazon">Amazon</option>
          <option value="stripe">Stripe</option>
          <option value="braintree">Braintree</option>
          <option value="paypal">PayPal</option>
          <option value="payflow">PayFlow</option>
          <option value="shopify">Shopify</option>
          <option value="fastspring">FastSpring</option>
          <option value="nmi">NMI</option>
          <option value="vbvlookup">3DS Lookup</option>
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
              <option value="<?= htmlspecialchars(json_encode(['host' => $proxy['host'], 'port' => $proxy['port'], 'username' => $proxy['username'] ?? '', 'password' => $proxy['password'] ?? '']), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars("{$proxy['host']}:{$proxy['port']} ({$proxy['ptype']})", ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
    
    <!-- Configure + Tools buttons (above Card List) -->
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

      <!-- Import -->
      <div class="mt-4">
        <label class="inline-flex items-center justify-center w-full rounded-lg border border-white/10 bg-slate-900/60 hover:bg-slate-800 px-4 py-3 text-sm cursor-pointer transition">
          <input id="fileInput" type="file" accept=".txt" hidden>
          <span class="inline-flex items-center gap-2"><i class="fa-solid fa-arrow-up-from-bracket"></i> Import Cards (.txt)</span>
        </label>
      </div>

      <!-- SK Key -->
      <div id="skInputContainer" class="hidden mt-4">
        <label class="block text-sm font-medium text-slate-200 mb-2">Drop Your SK Key (Optional)</label>
        <input type="text" id="skInput" class="w-full input-like" placeholder="Enter SK Key (e.g., sk_live_...)">
      </div>

        <!-- Shopify URLs -->
        <div id="urlInputContainer" class="hidden mt-4">
          <label class="block text-sm font-medium text-slate-200 mb-2">Shopify URLs or Package (Required)</label>
          <textarea id="urlInput" class="w-full input-like min-h-28 hidden" placeholder="Enter Shopify URLs (e.g., https://example.com or domain.com, one per line)"></textarea>
          <select id="urlPackageSelect" class="w-full px-3 py-2 text-sm select-container hidden text-gray-300 bg-gray-800 text-gray-200">
            <option value="free" class="bg-gray-800">Free</option>
            <?php if ($status !== 'free'): ?>
              <option value="premium" class="bg-gray-800">Premium</option>
            <?php endif; ?>
          </select>
        </div>
      
      <!-- Amazon Cookie -->
      <div id="cookieInputContainer" class="hidden mt-4">
        <label class="block text-sm font-medium text-slate-200 mb-2">Amazon Cookie (Required)</label>
        <textarea id="cookieInput" class="w-full input-like min-h-28" placeholder="Enter Amazon cookie (one per line)"></textarea>
      </div>
      
    <!-- Site Link -->
    <div id="siteLinkInputContainer" class="hidden mt-4">
      <label class="block text-sm font-medium text-slate-200 mb-2">Full Site Link (Required)</label>
      <input type="text" id="siteLinkInput" class="w-full input-like" placeholder="Enter Full Site Link (e.g., https://example.com/my-account/add-payment-method)">
    </div>
    
    <!-- PayPal Key Based -->
    <div id="paypalKeyInputContainer" class="hidden mt-4">
      <label class="block text-sm font-medium text-slate-200 mb-2">PayPal Client ID (Optional)</label>
      <input type="text" id="paypalClientId" class="w-full input-like" placeholder="e.g. AexxxxxxXxxxxX_XxxxxxX">
      <label class="block text-sm font-medium text-slate-200 mt-4 mb-2">PayPal Client Secret (Optional)</label>
      <input type="text" id="paypalClientSecret" class="w-full input-like" placeholder="e.g. EPxxxxxxXxxxxX_XxxxxxX">
      <p class="text-xs text-slate-400 mt-2">Leave empty to use default keys</p>
    </div>

    <!-- Stripe Package -->
    <div id="stripePackageContainer" class="hidden mt-4">
      <label class="block text-sm font-medium text-slate-200 mb-2">Site Package (Inbuilt)</label>
      <select id="stripePackageSelect" class="w-full px-3 py-2 text-sm select-container text-gray-300 bg-gray-800 text-gray-200">
        <option value="free">Free Sites</option>
        <?php if ($status !== 'free'): ?>
          <option value="premium">Premium Sites</option>
        <?php endif; ?>
      </select>
    </div>

      <!-- Actions + Progress -->
      <div class="mt-6 flex flex-wrap items-center gap-4">
        <button id="btnStart" class="rounded-lg btn-primary px-6 py-3 text-sm">
          <i class="fas fa-play mr-1"></i> Start Checking
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

    <!-- RIGHT: Stats -->
    <aside class="glass card-shadow p-8">
      <h2 class="text-2xl section-title mb-6">Statistics</h2>
      <div class="border border-white/10 bg-gradient-to-b from-slate-900/60 to-slate-800/40 rounded-xl p-3 mb-4 flex items-center gap-3">
        <div class="avatar-ring"><img src="<?= htmlspecialchars($profilePic, ENT_QUOTES, 'UTF-8') ?>" alt="Profile" class="profile-pic"></div>
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
        <li class="flex items-center justify-between"><span class="inline-flex items-center gap-2"><i class="fas fa-list-ul text-sky-400"></i> Total Cards</span><span id="statTotal" class="font-semibold">0</span></li>
        <li class="flex items-center justify-between"><span class="inline-flex items-center gap-2"><i class="fas fa-check-circle text-amber-300"></i> Approved</span><span id="statApproved" class="font-semibold text-amber-300">0</span></li>
        <li class="flex items-center justify-between"><span class="inline-flex items-center gap-2"><i class="fas fa-heartbeat text-emerald-400"></i> Live</span><span id="statLive" class="font-semibold text-emerald-400">0</span></li>
        <li class="flex items-center justify-between"><span class="inline-flex items-center gap-2"><i class="fas fa-ban text-rose-400"></i> Dead</span><span id="statDead" class="font-semibold text-rose-400">0</span></li>
        <li class="flex items-center justify-between"><span class="inline-flex items-center gap-2"><i class="fas fa-clock text-sky-400"></i> Remaining</span><span id="statYet" class="font-semibold">0</span></li>
        <li class="flex items-center justify-between"><span class="inline-flex items-center gap-2"><i class="fas fa-coins text-yellow-300"></i> Credits</span><span id="statCred" class="font-semibold text-yellow-300"><?= (int)$credits ?></span></li>
        <li class="flex items-center justify-between"><span class="inline-flex items-center gap-2"><i class="fas fa-stopwatch text-fuchsia-300"></i> Limit</span><span id="statLimit" class="font-semibold text-fuchsia-300"><?= number_format($maxChecks) ?></span></li>
        <li class="flex items-center justify-between"><span class="inline-flex items-center gap-2"><i class="fas fa-server text-cyan-300"></i> Current API</span><span id="statGateway" class="font-semibold text-cyan-300">Select API</span></li>
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

  <!-- Panel switch -->
  <div class="mt-10 flex items-center justify-center gap-3">
    <button id="tabApproved" class="rounded-xl px-4 py-2 text-sm font-medium btn-muted">Approved</button>
    <button id="tabLive" class="rounded-xl px-4 py-2 text-sm font-medium btn-muted">Live</button>
    <button id="tabDead" class="rounded-xl px-4 py-2 text-sm font-medium btn-muted">Dead</button>
  </div>

  <!-- Results -->
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


<!-- Tools Modal -->
<div id="toolsModal" class="fixed inset-0 z-[120] hidden">
  <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
  <div class="relative min-h-full flex items-end sm:items-center justify-center p-4">
    <div id="toolsSheet" class="w-full max-w-lg translate-y-4 opacity-0 transition-all duration-200">
      <div class="rounded-2xl border border-white/10 bg-gradient-to-b from-slate-900 to-slate-800 shadow-2xl">
        <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
          <h3 class="text-lg font-semibold"><i class="fa-solid fa-toolbox mr-2"></i>Tools</h3>
          <button id="toolsClose" class="px-3 py-1.5 rounded-md btn-muted text-sm">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <!-- Tools content (CC Generator) -->
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


<!-- Credits Info Modal (unchanged) -->
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

<!-- ===== Configuration Modal ===== -->
<div id="cfgModal" class="fixed inset-0 z-[120] hidden">
  <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
  <div class="relative min-h-full flex items-end sm:items-center justify-center p-4">
    <div class="sheet translate-y-4 opacity-0 transition-all duration-200">
      <div class="rounded-2xl border border-white/10 bg-gradient-to-b from-slate-900 to-slate-800 shadow-2xl p-5">
        <div class="modal-content">
          <div class="stepper">
            <div id="s1" class="step on">1. Gateway</div>
            <div id="s2" class="step">2. API</div>
            <div id="s3" class="step">3. Proxy & Advanced Settings</div>
          </div>
          <!-- Step 1 -->
          <div id="step1">
            <div class="text-sm text-slate-300 mb-2">Select a gateway</div>
            <div id="gwList" class="fsel-list"></div>
          </div>
          <!-- Step 2 -->
          <div id="step2" class="hidden">
            <div class="text-sm text-slate-300 mb-2">Select an API</div>
            <div id="apiList" class="fsel-list"></div>
          </div>
          <!-- Step 3 -->
          <div id="step3" class="hidden">
            <div class="fsel-list"> <!-- Match scrolling behavior of Steps 1 and 2 -->
              <div class="grid sm:grid-cols-2 gap-6 bg-gradient-to-br from-slate-900 to-slate-800 p-6 rounded-xl shadow-lg border border-gray-800">
                <!-- Proxy Settings -->
                <div class="rounded-lg bg-gray-900 p-5 border border-gray-700">
                  <div class="text-sm font-semibold text-gray-200 mb-4">Proxy Configuration</div>
                  <div class="flex items-center justify-between mb-4">
                    <span class="text-sm text-gray-300">Enable Proxy</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input id="cfgProxySwitch" type="checkbox" class="sr-only peer" checked>
                      <div class="w-12 h-6 bg-gray-700 rounded-full peer-focus:outline-none transition-colors duration-300 ease-in-out peer-checked:bg-emerald-600">
                        <span class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow-md transform transition-transform duration-300 ease-in-out peer-checked:translate-x-6 z-10"></span>
                      </div>
                    </label>
                  </div>
                  <div id="cfgProxySelectWrap" class="mt-4">
                    <label class="block text-xs text-gray-400 mb-2">Proxy Selection</label>
                    <select id="cfgProxySelect" class="w-full px-4 py-2 text-sm bg-gray-800 text-gray-200 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all duration-200">
                      <option value="random" class="bg-gray-800">Random</option>
                      <?php foreach ($userProxies as $proxy): ?>
                        <option value="<?= htmlspecialchars(json_encode(['host' => $proxy['host'], 'port' => $proxy['port'], 'username' => $proxy['username'] ?? '', 'password' => $proxy['password'] ?? '']), ENT_QUOTES, 'UTF-8') ?>" class="bg-gray-800">
                          <?= htmlspecialchars("{$proxy['host']}:{$proxy['port']} ({$proxy['ptype']})", ENT_QUOTES, 'UTF-8') ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <!-- Tools & Settings -->
                <div class="rounded-lg bg-gray-900 p-5 border border-gray-700">
                  <div class="text-sm font-semibold text-gray-200 mb-4">Advanced Settings</div>
                  <div class="flex items-center justify-between mb-4">
                    <span class="text-sm text-gray-300">Blur Mode</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input id="cfgBlurSwitch" type="checkbox" class="sr-only peer">
                      <div class="w-12 h-6 bg-gray-700 rounded-full peer-focus:outline-none transition-colors duration-300 ease-in-out peer-checked:bg-emerald-600">
                        <span class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow-md transform transition-transform duration-300 ease-in-out peer-checked:translate-x-6 z-10"></span>
                      </div>
                    </label>
                  </div>
                  <div class="flex items-center justify-between mb-4">
                    <span class="text-sm text-gray-300">Hit Sound</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input id="cfgHitSoundSwitch" type="checkbox" class="sr-only peer" checked>
                      <div class="w-12 h-6 bg-gray-700 rounded-full peer-focus:outline-none transition-colors duration-300 ease-in-out peer-checked:bg-emerald-600">
                        <span class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow-md transform transition-transform duration-300 ease-in-out peer-checked:translate-x-6 z-10"></span>
                      </div>
                    </label>
                  </div>
                  <div class="flex items-center justify-between mb-4">
                    <span class="text-sm text-gray-300">Auto API Config</span>
                    <select id="cfgAutoApiConfig" class="w-40 px-4 py-2 text-sm bg-gray-800 text-gray-200 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all duration-200">
                      <option value="inbuilt" class="bg-gray-800">Inbuilt</option>
                      <option value="custom" class="bg-gray-800">Custom</option>
                    </select>
                  </div>
                  <div class="flex items-center justify-between mb-4">
                    <span class="text-sm text-gray-300">Hit Sender</span>
                    <select id="cfgHitSender" class="w-40 px-4 py-2 text-sm bg-gray-800 text-gray-200 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all duration-200">
                      <option value="both" class="bg-gray-800">Both</option>
                      <option value="charge" class="bg-gray-800">Approve</option>
                      <option value="live" class="bg-gray-800">Live</option>
                    </select>
                  </div>
                  <button id="cfgResetAllBtn" class="w-full rounded-lg px-4 py-2 bg-red-900/30 hover:bg-red-900/50 border border-red-700 text-red-200 text-sm font-medium transition-colors duration-200">
                    <i class="fa-solid fa-rotate-left mr-2"></i> Reset All Settings
                  </button>
                </div>
              </div>
            </div>
          </div>
          <!-- Sticky buttons -->
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
</div>

<!-- sounds -->
<audio id="approvedSound" src="/assets/sounds/charge.mp3"></audio>
<audio id="liveSound" src="/assets/sounds/live.mp3"></audio>

<script>
(() => {
  // ---------- helpers ----------
  const $ = sel => document.querySelector(sel);
  const byId = id => document.getElementById(id);
  const toast = (msg, type='info') => {
    Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2600, icon: type, title: msg,
      background: 'rgba(2,6,23,.95)', color: '#fff', iconColor: '#34d399' });
  };
  const esc = (s) => String(s ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  // Fancy hit toast (with response)
  const hitToast = (resp, type='success') => {
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
  // Progress
  const $pWrap = document.getElementById('progressWrap');
  const $pBar = document.getElementById('progressBar');
  const $pText = document.getElementById('progressText');
  let P_TOTAL=0, P_DONE=0;
  function initProgress(total){
    P_TOTAL=total; P_DONE=0;
    $pWrap?.classList.remove('hidden'); $pText?.classList.remove('hidden');
    $pBar.style.width='0%'; $pText.textContent=`0% • 0/${P_TOTAL}`;
  }
  function tickProgress(){
    P_DONE=Math.min(P_DONE+1,P_TOTAL);
    const pct=P_TOTAL?Math.round((P_DONE/P_TOTAL)*100):0;
    $pBar.style.width=pct+'%'; $pText.textContent=`${pct}% • ${P_DONE}/${P_TOTAL}`;
    if(P_DONE>=P_TOTAL){ $pWrap.classList.add('hidden'); $pText.classList.add('hidden'); }
  }
  // ===== LocalStorage keys =====
  const CFG_KEY = 'cx_cfg_v2';
  const BLUR_KEY = 'cx_blur_mode_v1';
  const SK_LS_KEY = 'cx_input_sk_v1';
  const URLS_LS_KEY = 'cx_input_urls_v1';
  const COOKIE_LS_KEY = 'cx_input_cookie_v1';
  const SITE_LINK_LS_KEY = 'cx_input_site_link_v1';
  const HIT_SOUND_KEY = 'cx_hit_sound_v1';
  const AUTO_API_CONFIG_KEY = 'cx_auto_api_config_v1';
  const HIT_SENDER_KEY = 'cx_hit_sender_v1';
  // ===== Blur helpers (idempotent) =====
  function setBlur(on, persist = true){
    const root = document.getElementById('mainRoot');
    root && root.classList.toggle('blur-mode', !!on);
    if (typeof $cfgBlurSwitch !== 'undefined' && $cfgBlurSwitch) $cfgBlurSwitch.checked = !!on;
    const pageSwitch = document.getElementById('blurSwitch');
    if (pageSwitch) pageSwitch.checked = !!on;
    if (persist) { try { localStorage.setItem(BLUR_KEY, on ? '1' : '0'); } catch{} }
  }
  try {
    const saved = localStorage.getItem(BLUR_KEY);
    if (saved === '1') setBlur(true, false);
    else if (saved === '0') setBlur(false, false);
  } catch {}
  // ===== Hit Sound helpers =====
  function setHitSound(on, persist = true){
    if (typeof $cfgHitSoundSwitch !== 'undefined' && $cfgHitSoundSwitch) $cfgHitSoundSwitch.checked = !!on;
    if (persist) { try { localStorage.setItem(HIT_SOUND_KEY, on ? '1' : '0'); } catch{} }
  }
  try {
    const saved = localStorage.getItem(HIT_SOUND_KEY);
    if (saved === '1') setHitSound(true, false);
    else if (saved === '0') setHitSound(false, false);
    else setHitSound(true, false); // Default enabled
  } catch {}
  // ===== Config save/load/apply =====
  function saveCurrentCfg(){
    const obj = {
      gateway: $gateway.value || '',
      api: $api.value || '',
      proxyOn: !!$proxySw.checked,
      proxyVal: $proxySelect.value || 'random',
      hitSound: !!$cfgHitSoundSwitch?.checked,
      autoApiConfig: $cfgAutoApiConfig?.value || 'inbuilt',
      hitSender: $cfgHitSender?.value || 'both'
    };
    try { localStorage.setItem(CFG_KEY, JSON.stringify(obj)); } catch {}
  }
  function loadCfg(){
    try {
      const raw = localStorage.getItem(CFG_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch { return null; }
  }
  function applyCfg(cfg, notify=false){
    if(!cfg || !cfg.gateway) return false;
    $gateway.value = cfg.gateway;
    updateApiOptions();
    if (cfg.api) {
      $api.value = cfg.api;
    }
    const selectedApi = apiMap[$gateway.value]?.apis.find(a => a.value === $api.value);
    setApiPill(selectedApi);
    toggleAuxInputs($gateway.value, $api.value);
    $statGateway.textContent = selectedApi?.label || 'Select API';
    $proxySw.checked = !!cfg.proxyOn;
    $proxySelectContainer.classList.toggle('hidden', !$proxySw.checked);
    if (cfg.proxyVal) $proxySelect.value = cfg.proxyVal;
    if (typeof cfg.hitSound !== 'undefined') setHitSound(cfg.hitSound, false);
    if (cfg.autoApiConfig) $cfgAutoApiConfig.value = cfg.autoApiConfig;
    if (cfg.hitSender) $cfgHitSender.value = cfg.hitSender;
    toggleAuxUrlInput();
    CFG_OK = !!($gateway.value && $api.value);
    $start.disabled = !CFG_OK;
    if (notify) toast('Configuration restored', 'success');
    return true;
  }
  // ---------- dom ----------
  const $main = byId('mainRoot');
  const $gateway = byId('gateway');
  const $api = byId('api');
  const $proxySw = byId('proxySwitch');
  const $proxySelectContainer = byId('proxySelectContainer');
  const $proxySelect = byId('proxySelect');
  const $file = byId('fileInput');
  const $txt = byId('cardList');
  const $start = byId('btnStart');
  const $stop = byId('btnStop');
  const $clear = byId('btnClear');
  const $boxApproved = byId('approvedBox');
  const $boxLive = byId('liveBox');
  const $boxDead = byId('deadBox');
  const $statTotal = byId('statTotal');
  const $statApproved = byId('statApproved');
  const $statLive = byId('statLive');
  const $statDead = byId('statDead');
  const $statYet = byId('statYet');
  const $statCred = byId('statCred');
  const $statGateway = byId('statGateway');
  const $tabApproved = byId('tabApproved');
  const $tabLive = byId('tabLive');
  const $tabDead = byId('tabDead');
  const $panelApproved = byId('panelApproved');
  const $panelLive = byId('panelLive');
  const $panelDead = byId('panelDead');
  const $skInputContainer = byId('skInputContainer');
  const $skInput = byId('skInput');
  const $urlInputContainer = byId('urlInputContainer');
  const $urlInput = byId('urlInput');
  const $urlPackageSelect = byId('urlPackageSelect');
  const $cookieInput = byId('cookieInput');
  const $cookieInputContainer = byId('cookieInputContainer');
  const $siteLinkInputContainer = byId('siteLinkInputContainer');
  const $siteLinkInput = byId('siteLinkInput');
  const $stripePackageContainer = byId('stripePackageContainer');
  const $stripePackageSelect = byId('stripePackageSelect');
  const $paypalKeyInputContainer = byId('paypalKeyInputContainer');
  const $paypalClientId = byId('paypalClientId');
  const $paypalClientSecret = byId('paypalClientSecret');
  const $apiLockHint = byId('apiLockHint');
  const $blurSwitch = byId('blurSwitch');
  const $genbin = byId('genbin');
  const $genqty = byId('genqty');
  const $gencvv = byId('gencvv');
  const $month = byId('month');
  const $year = byId('year');
  const $btnGen = byId('btnGen');
  const $cfgModal = byId('cfgModal');
  const $cfgSheet = $cfgModal?.querySelector('.sheet');
  const $cfgNext = byId('cfgNext');
  const $cfgBack = byId('cfgBack');
  const $cfgSave = byId('cfgSave');
  const $cfgCancel = byId('cfgCancel');
  const $cfgProxySwitch = byId('cfgProxySwitch');
  const $cfgProxySelect = byId('cfgProxySelect');
  const $cfgProxyWrap = byId('cfgProxySelectWrap');
  const $cfgBlurSwitch = byId('cfgBlurSwitch');
  const $cfgHitSoundSwitch = byId('cfgHitSoundSwitch');
  const $cfgAutoApiConfig = byId('cfgAutoApiConfig');
  const $cfgHitSender = byId('cfgHitSender');
  const $cfgResetAllBtn = byId('cfgResetAllBtn');
  const $openCfgBtn = byId('openCfgBtn');
  let CFG_OK = false;
  const cfg = { gateway: '', api: '', useProxy: false, proxyVal: 'random', hitSound: true, autoApiConfig: 'inbuilt', hitSender: 'both' };
  $start.disabled = true;
  // ---------- speed tracker (in seconds) + persist to localStorage ----------
  const SPEED_STATE_KEY = 'cx_speed_state_v1';
  const speed = {
    n: 0,
    sumMs: 0,
    reset(){
      this.n = 0;
      this.sumMs = 0;
      updateAvg();
      saveSpeed();
    }
  };
  const speedByApi = {};
  function saveSpeed(){
    try{
      localStorage.setItem(
        SPEED_STATE_KEY,
        JSON.stringify({ n: speed.n, sumMs: speed.sumMs })
      );
    }catch{}
  }
  function loadSpeed(){
    try{
      const raw = localStorage.getItem(SPEED_STATE_KEY);
      if(!raw) return;
      const s = JSON.parse(raw);
      if (s && typeof s.n === 'number' && typeof s.sumMs === 'number'){
        speed.n = s.n;
        speed.sumMs = s.sumMs;
        updateAvg();
      }
    }catch{}
  }
  function addSample(apiLabel, ms){
    speed.n++;
    speed.sumMs += ms;
    updateAvg();
    saveSpeed();
    (speedByApi[apiLabel] ??= {n:0,sumMs:0});
    speedByApi[apiLabel].n++;
    speedByApi[apiLabel].sumMs += ms;
    renderApiSpeeds();
  }
  function updateAvg(){
    const el = byId('statSpeed');
    el.textContent = speed.n ? ((speed.sumMs / speed.n / 1000).toFixed(2) + ' s') : '—';
  }
  function renderApiSpeeds(){
    const ul = byId('apiSpeedList'); if(!ul) return;
    ul.innerHTML = Object.entries(speedByApi)
      .map(([name,v]) => `<li class="flex justify-between"><span>${name}</span><span>${(v.sumMs/v.n/1000).toFixed(2)} s</span></li>`)
      .join('');
  }
  loadSpeed();
  // ---- persist inputs ----
  try {
    const savedSk = localStorage.getItem(SK_LS_KEY);
    const savedUrls = localStorage.getItem(URLS_LS_KEY);
    const savedCookie = localStorage.getItem(COOKIE_LS_KEY);
    const savedSiteLink = localStorage.getItem(SITE_LINK_LS_KEY);
    const savedPaypalClientId = localStorage.getItem('cx_paypal_clientid');
    const savedPaypalClientSecret = localStorage.getItem('cx_paypal_clientsecret');
    if (savedPaypalClientId && $paypalClientId) $paypalClientId.value = savedPaypalClientId;
    if (savedPaypalClientSecret && $paypalClientSecret) $paypalClientSecret.value = savedPaypalClientSecret;
    if (savedSk && $skInput) $skInput.value = savedSk;
    if (savedUrls && $urlInput) $urlInput.value = savedUrls;
    if (savedCookie && $cookieInput) $cookieInput.value = savedCookie;
    if (savedSiteLink && $siteLinkInput) $siteLinkInput.value = savedSiteLink;
  } catch {}
  $skInput?.addEventListener('input', () => {
    try { localStorage.setItem(SK_LS_KEY, ($skInput.value || '').trim()); } catch {}
  });
  $urlInput?.addEventListener('input', () => {
    try {
      localStorage.setItem(URLS_LS_KEY, ($urlInput.value || '').replace(/\r/g,'').trim());
    } catch {}
  });
  $cookieInput?.addEventListener('input', () => {
    try {
      localStorage.setItem(COOKIE_LS_KEY, ($cookieInput.value || '').replace(/\r/g,'').trim());
    } catch {}
  });
  $paypalClientId?.addEventListener('input', () => {
    try { localStorage.setItem('cx_paypal_clientid', $paypalClientId.value.trim()); } catch {}
  });
  $paypalClientSecret?.addEventListener('input', () => {
    try { localStorage.setItem('cx_paypal_clientsecret', $paypalClientSecret.value); } catch {}
  });
  $siteLinkInput?.addEventListener('input', () => {
    try {
      localStorage.setItem(SITE_LINK_LS_KEY, ($siteLinkInput.value || '').trim());
    } catch {}
  });
  // open/close modal + steps
  let STEP = 1;
  function stepUI(){
    ['step1','step2','step3'].forEach((id,i)=>{
      byId(id).classList.toggle('hidden', STEP !== (i+1));
      byId('s'+(i+1)).classList.toggle('on', STEP === (i+1));
    });
    $cfgBack.classList.toggle('hidden', STEP===1);
    $cfgNext.classList.toggle('hidden', STEP===3);
    $cfgSave.classList.toggle('hidden', STEP!==3);
  }
    function openCfg(){
      STEP = 1; stepUI();
      buildGatewayList(); buildApiList();
      if ($cfgProxySwitch) $cfgProxySwitch.checked = $proxySw.checked;
      $cfgProxyWrap.classList.toggle('hidden', !$cfgProxySwitch.checked);
      if ($cfgProxySelect) $cfgProxySelect.value = ($proxySelect.value || 'random');
      try {
        if ($cfgBlurSwitch) $cfgBlurSwitch.checked = localStorage.getItem(BLUR_KEY) === '1';
        if ($cfgHitSoundSwitch) $cfgHitSoundSwitch.checked = localStorage.getItem(HIT_SOUND_KEY) === '1';
        if ($cfgAutoApiConfig) $cfgAutoApiConfig.value = localStorage.getItem(AUTO_API_CONFIG_KEY) || 'inbuilt';
        if ($cfgHitSender) $cfgHitSender.value = localStorage.getItem(HIT_SENDER_KEY) || 'both';
      } catch (e) {
        console.error('Error setting config values:', e);
        if ($cfgBlurSwitch) $cfgBlurSwitch.checked = false;
        if ($cfgHitSoundSwitch) $cfgHitSoundSwitch.checked = true;
        if ($cfgAutoApiConfig) $cfgAutoApiConfig.value = 'inbuilt';
        if ($cfgHitSender) $cfgHitSender.value = 'both';
      }
      toggleAuxUrlInput();
      $cfgModal.classList.remove('hidden');
      requestAnimationFrame(() => {
        $cfgSheet.classList.remove('translate-y-4', 'opacity-0');
        $cfgSheet.classList.add('translate-y-0', 'opacity-100');
      });
    }
  function closeCfg(){
    $cfgSheet.classList.add('translate-y-4','opacity-0');
    setTimeout(() => $cfgModal.classList.add('hidden'), 180);
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
    $skInput && ($skInput.value = '');
    $urlInput && ($urlInput.value = '');
    $cookieInput && ($cookieInput.value = '');
    $siteLinkInput && ($siteLinkInput.value = '');
    if ($proxySw) $proxySw.checked = false;
    $proxySelectContainer?.classList.add('hidden');
    if ($proxySelect) $proxySelect.value = 'random';
    if ($gateway) $gateway.value = '';
    if ($api) { $api.innerHTML = ''; }
    $apiLockHint?.classList.add('hidden');
    CFG_OK = false;
    if ($start) $start.disabled = true;
    if ($statGateway) $statGateway.textContent = 'Select API';
    if (typeof setBlur === 'function') setBlur(false, false);
    if (typeof setHitSound === 'function') setHitSound(true, false);
    if ($cfgAutoApiConfig) $cfgAutoApiConfig.value = 'inbuilt';
    if ($cfgHitSender) $cfgHitSender.value = 'both';
    toggleAuxUrlInput();
    toast('All saved data cleared', 'success');
  }
  $cfgResetAllBtn?.addEventListener('click', resetAllSaved);
  // Toggle Shopify URL input based on Auto API Config
  function toggleAuxUrlInput() {
    const wooStripeApis = ['autowoostripe1', 'autowoostripe2', 'autowoostripe3', 'autowoostripe4', 'autowoostripe5'];
    // Shopify logic
    if ($gateway.value === 'shopify' && $api.value === 'autoshopify' && $cfgAutoApiConfig?.value === 'inbuilt') {
      $urlInput?.classList.add('hidden');
      $urlPackageSelect?.classList.remove('hidden');
    } else {
      $urlInput?.classList.remove('hidden');
      $urlPackageSelect?.classList.add('hidden');
    }
    // Stripe logic
    if ($gateway.value === 'stripe' && wooStripeApis.includes($api.value)) {
      if ($cfgAutoApiConfig?.value === 'inbuilt') {
        $siteLinkInputContainer?.classList.add('hidden');
        $stripePackageContainer?.classList.remove('hidden');
      } else {
        $siteLinkInputContainer?.classList.remove('hidden');
        $stripePackageContainer?.classList.add('hidden');
      }
    }
  }
  $cfgAutoApiConfig?.addEventListener('change', () => {
    try { localStorage.setItem(AUTO_API_CONFIG_KEY, $cfgAutoApiConfig.value); } catch {}
    toggleAuxUrlInput();
  });
  $cfgHitSoundSwitch?.addEventListener('change', () => {
    setHitSound($cfgHitSoundSwitch.checked);
  });
  $cfgHitSender?.addEventListener('change', () => {
    try { localStorage.setItem(HIT_SENDER_KEY, $cfgHitSender.value); } catch {}
  });
  // Fancy list builders
  const GW_ICONS = {amazon:'🛒', stripe:'💳', braintree:'🏦', paypal:'🅿️', payflow:'🅿️', shopify:'🏬', fastspring:'⚡', nmi:'🖧', vbvlookup:'👀'};
  function buildGatewayList(){
    const box = byId('gwList'); box.innerHTML = '';
    Object.keys(apiMap).forEach(key=>{
      const label = apiMap[key].label;
      const li = document.createElement('div');
      li.className = 'fsel-item' + (cfg.gateway===key? ' active':'');
      li.innerHTML = `
        <div class="fsel-left">
          <div class="fsel-ico">${GW_ICONS[key] || '🗂️'}</div>
          <div>
            <div class="fsel-title">${label}</div>
            <div class="fsel-sub">${apiMap[key].apis.length} API available</div>
          </div>
        </div>
        <div class="fsel-right"><span class="badge-s">Select</span></div>`;
      li.addEventListener('click', ()=>{ cfg.gateway=key; buildGatewayList(); STEP=2; buildApiList(); stepUI(); });
      box.appendChild(li);
    });
  }
  function buildApiList(){
    const wrap = byId('apiList'); wrap.innerHTML = '';
    if(!cfg.gateway){ wrap.innerHTML = '<div class="text-sm text-slate-400">Choose a gateway first.</div>'; return; }
    apiMap[cfg.gateway].apis.forEach(a=>{
      const allowed = canUse(a.minRole || 'free');
      const li = document.createElement('div');
      li.className = 'fsel-item' + (cfg.api===a.value ? ' active':'');
      li.style.opacity = (allowed && a.alive) ? '1' : '.55';
      li.innerHTML = `
        <div class="fsel-left">
          <div class="fsel-ico">🔌</div>
          <div>
            <div class="fsel-title">${a.label}</div>
            <div class="fsel-sub">${a.credits} credit • ${allowed ? 'Unlocked' : 'Locked'} • <span>${a.value}</span></div>
          </div>
        </div>
        <div class="fsel-right">
          <span class="badge-s ${a.alive?'badge-ok':'badge-down'}">${a.alive?'Active':'Inactive'}</span>
        </div>`;
      if(allowed && a.alive){
        li.addEventListener('click', ()=>{ cfg.api=a.value; buildApiList(); toggleAuxUrlInput(); });
      }
      wrap.appendChild(li);
    });
  }
  // Step nav
  $cfgNext.addEventListener('click', ()=>{
    if(STEP===1 && !cfg.gateway) return toast('Select a gateway first','warning');
    if(STEP===2 && !cfg.api) return toast('Select an API','warning');
    STEP = Math.min(3, STEP+1); stepUI();
  });
  $cfgBack.addEventListener('click', ()=>{ STEP = Math.max(1, STEP-1); stepUI(); });
  $cfgCancel.addEventListener('click', closeCfg);
  // Proxy toggles inside modal
  $cfgProxySwitch.addEventListener('change', ()=>{
    $cfgProxyWrap.classList.toggle('hidden', !$cfgProxySwitch.checked);
  });
  // Apply config -> mirror to hidden inline controls
  $cfgSave.addEventListener('click', ()=>{
    if (!cfg.gateway || !cfg.api) {
      toast('Select gateway & API', 'warning');
      return;
    }
    console.log('Saving config:', cfg);
    $gateway.value = cfg.gateway;
    updateApiOptions();
    $api.value = cfg.api;
    const selectedApi = apiMap[cfg.gateway]?.apis.find(a=>a.value===cfg.api);
    if (!selectedApi) {
      toast('Selected API not found', 'error');
      return;
    }
    setApiPill(selectedApi);
    toggleAuxInputs(cfg.gateway, cfg.api);
    $statGateway.textContent = selectedApi?.label || 'Select API';
    $proxySw.checked = $cfgProxySwitch.checked;
    $proxySelectContainer.classList.toggle('hidden', !$proxySw.checked);
    $proxySelect.value = $cfgProxySelect.value || 'random';
    setBlur($cfgBlurSwitch.checked);
    setHitSound($cfgHitSoundSwitch.checked);
    $cfgAutoApiConfig.value = $cfgAutoApiConfig.value || 'inbuilt';
    $cfgHitSender.value = $cfgHitSender.value || 'both';
    toggleAuxUrlInput();
    CFG_OK = true;
    $start.disabled = false;
    saveCurrentCfg();
    closeCfg();
    toast('Configuration saved', 'success');
  });
  $openCfgBtn?.addEventListener('click', openCfg);
  // ---------- role / permissions ----------
  const USER_ROLE = '<?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>';
  const roleLevel = r => ({free:1,premium:2,admin:3})[r] || 1;
  const canUse = (minRole='free') => roleLevel(USER_ROLE) >= roleLevel(minRole);
  // ---------- config ----------
  const apiMap = {
    amazon: {
      label: 'Amazon',
      apis: [
        { value: 'amazonauth', label: 'Amazon Auth Cookie Based', endpoint: '/api/amazon/amazonauth.php', credits: 3, minRole: 'free', alive: true }
      ]
    },
    stripe: {
      label: 'Stripe',
      apis: [
        { value: 'autowoostripe1', label: 'Auto Woo+Stripe 1', endpoint: '/api/stripe/autowoostripe1.php', credits: 3, minRole: 'free', alive: true },
        { value: 'autowoostripe2', label: 'Auto Woo+Stripe 2', endpoint: '/api/stripe/autowoostripe2.php', credits: 3, minRole: 'free', alive: true },
        { value: 'autowoostripe3', label: 'Auto Woo+Stripe 3', endpoint: '/api/stripe/autowoostripe3.php', credits: 3, minRole: 'free', alive: true },
        // { value: 'autowoostripe4', label: 'Auto Woo+Stripe 4', endpoint: '/api/stripe/autowoostripe4.php', credits: 3, minRole: 'free', alive: false },
        // { value: 'autowoostripe5', label: 'Auto Woo+Stripe 5', endpoint: '/api/stripe/autowoostripe5.php', credits: 3, minRole: 'free', alive: false },
        { value: 'nonsk', label: 'NonSK Charge', endpoint: '/api/stripe/nonsk.php', credits: 5, minRole: 'free', alive: false },
        { value: 'nonsk2', label: 'NonSK Charge 2', endpoint: '/api/stripe/nonsk2.php', credits: 5, minRole: 'free', alive: true },
        { value: 'nonsk3', label: 'NonSK Charge 3', endpoint: '/api/stripe/nonsk3.php', credits: 5, minRole: 'premium', alive: true },
        { value: 'nonsk4', label: 'NonSK Charge 4', endpoint: '/api/stripe/nonsk4.php', credits: 5, minRole: 'premium', alive: true },
        { value: 'skbased', label: 'SK Based CVV Charge', endpoint: '/api/stripe/skbased.php', credits: 5, minRole: 'free', alive: true },
        { value: 'skbasedccn', label: 'SK Based CCN Charge', endpoint: '/api/stripe/skbasedccn.php', credits: 5, minRole: 'premium', alive: true }
      ]
    },
    braintree: {
      label: 'Braintree',
      apis: [
        { value: 'b3auth', label: 'Braintree Auth', endpoint: '/api/braintree/b3auth.php', credits: 3, minRole: 'free', alive: false },
        { value: 'b3auth2', label: 'Braintree Auth 2', endpoint: '/api/braintree/b3auth2.php', credits: 3, minRole: 'premium', alive: true },
        { value: 'b3chg', label: 'Braintree 0.3$ Charge', endpoint: '/api/braintree/b3chargex.php', credits: 5, minRole: 'premium', alive: false }
      ]
    },
    paypal: {
      label: 'PayPal',
      apis: [
        { value: 'paypalkeyavs', label: 'PayPal Key Based AVS', endpoint: '/api/paypal/paypalkeyavs.php', credits: 5, minRole: 'free', alive: true },
        { value: 'paypalcharge', label: 'PayPal 1$ CVV', endpoint: '/api/paypal/paypalcharge.php', credits: 5, minRole: 'free', alive: true },
        { value: 'paypalcharge3', label: 'PayPal 1$ CVV 2', endpoint: '/api/paypal/paypalcharge3.php', credits: 5, minRole: 'premium', alive: true },
        { value: 'paypalcharge4', label: 'PayPal 1$ CVV 3', endpoint: '/api/paypal/paypalcharge4.php', credits: 5, minRole: 'premium', alive: true },
        { value: 'paypalcharge2', label: 'PayPal 1$ CCN', endpoint: '/api/paypal/paypalcharge2.php', credits: 5, minRole: 'premium', alive: true },
        { value: 'paypalcharge5', label: 'PayPal 1$ CCN 2', endpoint: '/api/paypal/paypalcharge5.php', credits: 5, minRole: 'premium', alive: true },
        { value: 'paypalpro', label: 'PayPal Pro AVS', endpoint: '/api/paypal/paypalpro.php', credits: 5, minRole: 'premium', alive: false }
      ]
    },
    payflow: {
      label: 'PayFlow',
      apis: [
        { value: 'payflowauth', label: 'PayFlow Auth', endpoint: '/api/payflow/payflowauth.php', credits: 5, minRole: 'premium', alive: false },
        { value: 'payflowcharge', label: 'PayFlow 10$ CVV', endpoint: '/api/payflow/payflowcharge.php', credits: 5, minRole: 'premium', alive: false }
      ]
    },
    shopify: {
      label: 'Shopify',
      apis: [
        { value: 'autoshopify', label: 'AutoShopify GraphQL', endpoint: '/api/shopify/autoshopify.php', credits: 5, minRole: 'free', alive: true }
      ]
    },
    fastspring: {
      label: 'FastSpring',
      apis: [
        { value: 'fastauth', label: 'FastSpring Auth', endpoint: '/api/fastspring/fastauth.php', credits: 3, minRole: 'premium', alive: true },
        { value: 'fastcharge', label: 'FastSpring 10$', endpoint: '/api/fastspring/fastcharge.php', credits: 5, minRole: 'free', alive: true }
      ]
    },
    nmi: {
      label: 'NMI',
      apis: [
        { value: 'nmicharge', label: 'NMI 1$ CVV', endpoint: '/api/nmi/nmicharge.php', credits: 5, minRole: 'premium', alive: false }
      ]
    },
    vbvlookup: {
      label: '3DS Lookup',
      apis: [
        { value: '3dslookup', label: '3DS Lookup', endpoint: '/api/vbv/vbvpro.php', credits: 5, minRole: 'free', alive: true }
      ]
    }
  };
  const proxyRequired = <?= $proxySet ? 'false' : 'true' ?>;
  const maxChecks = <?= (int)$maxChecks ?>;
  // ---------- CC Generator Functions ----------
  function generateCard(bin) {
    if (!bin) return '';
    var bin2 = "";
    var bin2_l = [];
    var card = "";
    var card1_l = [];
    var card2_l = [];
    var sum = 0;
    var mod = 0;
    var check_sum = 0;
    for (var i in bin) {
      var char = bin[i].toLowerCase();
      if (char == "x") {
        var rand_num = Math.floor(Math.random() * 10);
        char = rand_num;
      }
      bin2 += char;
    }
    for (var i in bin2) {
      bin2_l.push(parseInt(bin2[i]));
    }
    for (var i in bin2_l) {
      card1_l.push(bin2_l[i]);
      card2_l.push(bin2_l[i]);
    }
    for (var i = 0; i < 15 - bin.length; i++) {
      var rand_num = Math.floor(Math.random() * 10);
      card1_l.push(rand_num);
      card2_l.push(rand_num);
    }
    for (var i = 0; i < card2_l.length; i += 2) {
      card2_l[i] *= 2;
    }
    for (var i in card2_l) {
      if (card2_l[i] > 9) {
        card2_l[i] -= 9;
      }
    }
    for (var i in card2_l) {
      sum += card2_l[i];
    }
    mod = sum % 10;
    if (mod !== 0) {
      check_sum = 10 - mod;
    }
    card1_l.push(check_sum);
    for (var i in card1_l) {
      card += card1_l[i];
    }
    return card;
  }
  function generateMonth(val) {
    if (val && val !== 'Random') return val;
    var monthList = ["01", "02", "03", "04", "05", "06", "07", "08", "09", "10", "11", "12"];
    var rand_num = Math.floor(Math.random() * 12);
    return monthList[rand_num];
  }
  function generateYear(val) {
    if (val && val !== 'Random') return val;
    var yearList = ["2025", "2026", "2027", "2028", "2029", "2030", "2031", "2032"];
    var rand_num = Math.floor(Math.random() * 8);
    return yearList[rand_num];
  }
  function generateBIN(val) {
    if (val && val !== 'Random') return val.replace(/\D+/g, '').slice(0, 3);
    var generatedBIN = "";
    for (var i = 0; i < 3; i++) {
      var rand_num = Math.floor(Math.random() * 10);
      generatedBIN += rand_num;
    }
    return generatedBIN;
  }
  function generate() {
    var bin = $genbin.value.trim();
    var ccv = $gencvv.value.trim();
    var month = $month.value;
    var year = $year.value;
    var count = parseInt($genqty.value.trim(), 10) || 10;
    count = Math.min(Math.max(count, 1), 1000);
    if (!bin) {
      toast('Please enter a valid BIN', 'warning');
      return;
    }
    var cards = [];
    for (var i = 0; i < count; i++) {
      var generatedCard = generateCard(bin);
      var generatedMonth = generateMonth(month);
      var generatedYear = generateYear(year);
      var generatedBIN = generateBIN(ccv);
      cards.push(`${generatedCard}|${generatedMonth}|${generatedYear}|${generatedBIN}`);
    }
    return cards.join('\n');
  }
  // Tools modal
  const $openToolsBtn = byId('openToolsBtn');
  const $toolsModal = byId('toolsModal');
  const $toolsSheet = byId('toolsSheet');
  const $toolsClose = byId('toolsClose');
  function openToolsModal(){
    $toolsModal.classList.remove('hidden');
    requestAnimationFrame(()=>{
      $toolsSheet.classList.remove('translate-y-4','opacity-0');
      $toolsSheet.classList.add('translate-y-0','opacity-100');
    });
  }
  function closeToolsModal(){
    $toolsSheet.classList.add('translate-y-4','opacity-0');
    setTimeout(() => $toolsModal.classList.add('hidden'), 180);
  }
  $openToolsBtn?.addEventListener('click', openToolsModal);
  $toolsClose?.addEventListener('click', closeToolsModal);
  $toolsModal?.addEventListener('click', (e)=>{ if(e.target===$toolsModal) closeToolsModal(); });
  // ---------- Blur Mode ----------
  byId('blurSwitch')?.addEventListener('change', e => {
    setBlur(!!e.target.checked);
  });
  $cfgBlurSwitch?.addEventListener('change', e => {
    setBlur(!!e.target.checked);
  });
  // ---------- Aux inputs show/hide ----------
  function toggleAuxInputs(gateway, apiVal) {
    const wooStripeApis = ['autowoostripe1', 'autowoostripe2', 'autowoostripe3', 'autowoostripe4', 'autowoostripe5'];
    // Hide all inputs
    $siteLinkInputContainer?.classList.add('hidden');
    $skInputContainer?.classList.add('hidden');
    $urlInputContainer?.classList.add('hidden');
    $cookieInputContainer?.classList.add('hidden');
    $stripePackageContainer?.classList.add('hidden');
    $paypalKeyInputContainer?.classList.add('hidden');
   
// Stripe Woo APIs
    if (gateway === 'stripe' && wooStripeApis.includes(apiVal)) {
      if ($cfgAutoApiConfig?.value === 'inbuilt') {
        $stripePackageContainer?.classList.remove('hidden');
      } else {
        $siteLinkInputContainer?.classList.remove('hidden');
      }
    }
    // Stripe SK Based
    else if (gateway === 'stripe' && apiVal === 'skbased') {
      $skInputContainer?.classList.remove('hidden');
    }
    // Shopify Auto
    else if (gateway === 'shopify' && apiVal === 'autoshopify') {
      $urlInputContainer?.classList.remove('hidden');
      toggleAuxUrlInput(); // handles package vs custom URL
    }
    // Amazon Auth
    else if (gateway === 'amazon' && apiVal === 'amazonauth') {
      $cookieInputContainer?.classList.remove('hidden');
    }
    // PayPal Key Based (NEW)
    else if (gateway === 'paypal' && apiVal === 'paypalkeyavs') {
      $paypalKeyInputContainer?.classList.remove('hidden');
    }
  }
  
  function updateApiOptions() {
    const gateway = $gateway.value;
    $api.innerHTML = '<option value="">Select API</option>';
    $apiLockHint.classList.add('hidden');
    if (apiMap[gateway]) {
      let firstAllowed = '';
      let anyLocked = false;
      apiMap[gateway].apis.forEach(api => {
        const allowed = canUse(api.minRole || 'free');
        const option = document.createElement('option');
        option.value = api.value;
        const statusDot = api.alive ? '🟢' : '🔴';
        option.textContent = allowed ? `${statusDot} ${api.label}` : `${statusDot} ${api.label} 🔒`;
        option.disabled = !allowed || !api.alive;
        if (!allowed) { anyLocked = true; option.setAttribute('data-locked','1'); }
        $api.appendChild(option);
        if (allowed && api.alive && !firstAllowed) firstAllowed = api.value;
      });
      $api.value = firstAllowed || '';
      if (anyLocked && USER_ROLE==='free') $apiLockHint.classList.remove('hidden');
    }
    const selectedApiVal = $api.value;
    const selectedApi = apiMap[gateway]?.apis.find(a => a.value === selectedApiVal);
    $statGateway.textContent = selectedApi?.label || 'Select API';
    setApiPill(selectedApi);
    toggleAuxInputs(gateway, selectedApiVal);
  }
  $gateway.addEventListener('change', () => {
    $api.classList.add('opacity-50','pointer-events-none');
    setTimeout(() => { updateApiOptions(); $api.classList.remove('opacity-50','pointer-events-none'); }, 200);
  });
  $api.addEventListener('change', () => {
    const gateway = $gateway.value;
    const apiVal = $api.value;
    const selectedApi = apiMap[gateway]?.apis.find(a => a.value === apiVal);
    $statGateway.textContent = selectedApi?.label || 'Select API';
    setApiPill(selectedApi);
    toggleAuxInputs(gateway, apiVal);
  });
  function setApiPill(apiObj){
    const pill = byId('apiStatusPill');
    if(!pill){return;}
    if(!apiObj){ pill.style.display='none'; return; }
    pill.style.display = 'inline-flex';
    pill.textContent = apiObj.alive ? 'Active' : 'Inactive';
    pill.classList.toggle('ok', !!apiObj.alive);
    pill.classList.toggle('down', !apiObj.alive);
  }
  // ---------- Tabs ----------
  function switchTab(which) {
    [$tabApproved, $tabLive, $tabDead].forEach(tab => tab.classList.remove('ring-1', 'ring-emerald-400', 'bg-emerald-500/10'));
    [$panelApproved, $panelLive, $panelDead].forEach(panel => panel.classList.add('hidden'));
    if (which === 'approved') {
      $tabApproved.classList.add('ring-1', 'ring-emerald-400', 'bg-emerald-500/10'); $panelApproved.classList.remove('hidden');
    } else if (which === 'live') {
      $tabLive.classList.add('ring-1', 'ring-emerald-400', 'bg-emerald-500/10'); $panelLive.classList.remove('hidden');
    } else {
      $tabDead.classList.add('ring-1', 'ring-emerald-400', 'bg-emerald-500/10'); $panelDead.classList.remove('hidden');
    }
  }
  $tabApproved.addEventListener('click', () => switchTab('approved'));
  $tabLive.addEventListener('click', () => switchTab('live'));
  $tabDead.addEventListener('click', () => switchTab('dead'));
  // ---------- File import ----------
  $file.addEventListener('change', function() {
    const f = this.files && this.files[0];
    if (!f) return;
    const fr = new FileReader();
    fr.onload = () => { $txt.value = String(fr.result).replace(/\r/g, '').trim(); toast('Cards imported successfully', 'success'); this.value = ''; };
    fr.readAsText(f);
  });
  // ---------- Clear ----------
  $clear.addEventListener('click', () => {
    $txt.value = '';
    $boxApproved.innerHTML = '';
    $boxLive.innerHTML = '';
    $boxDead.innerHTML = '';
    $statTotal.textContent = '0';
    $statApproved.textContent = '0';
    $statLive.textContent = '0';
    $statDead.textContent = '0';
    $statYet.textContent = '0';
    $skInput.value = '';
    $urlInput.value = '';
    $cookieInput.value = '';
    $siteLinkInput.value = '';
    $urlPackageSelect.value = 'free';
    toast('Cleared all data', 'info');
  });
  // ---------- Validators ----------
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
  const gatewayLabel = (gateway, api) => (apiMap[gateway]?.apis.find(a => a.value === api)?.label || 'Unknown');
  // Proxy UI
  $proxySw.addEventListener('change', () => {
    if ($proxySw.checked) $proxySelectContainer.classList.remove('hidden');
    else $proxySelectContainer.classList.add('hidden');
  });
  // ---------- Concurrency ----------
  let running = false, aborter;
  async function worker(queue, ctx) {
    while (running && !aborter?.signal.aborted) {
      const next = queue.shift();
      if (!next) break;
      try {
        await ctx.runOne(next);
      } catch (e) {
        if (e.name === 'AbortError') {
          console.log('Worker aborted');
          break;
        }
        console.error('Worker error:', e);
      }
    }
  }
  function stopRun() {
    running = false;
    if (aborter) {
      aborter.abort();
      aborter = null;
    }
    $start.disabled = false;
    $start.innerHTML = '<i class="fas fa-play mr-1"></i> Start Checking';
    $stop.disabled = true;
    toast('Checking stopped immediately', 'warning');
  }
  async function startRun() {
    if (running) { toast('Already running', 'warning'); return; }
    if (!CFG_OK) { toast('Please configure first', 'warning'); openCfg(); return; }
    const gateway = $gateway.value;
    const api = $api.value;
    if (!gateway || !api) { toast('Please select both a gateway and an API', 'warning'); return; }
    const selectedApi = apiMap[gateway]?.apis.find(a => a.value === api);
    if (!selectedApi) { toast('Selected API not found', 'error'); return; }
    if (!canUse(selectedApi.minRole || 'free')) { toast('This API is locked for your plan. Upgrade to use 🔒', 'error'); return; }
    if (proxyRequired && !$proxySw.checked) { toast('Proxy Required! Enable proxy.', 'warning'); return; }
    const have = parseInt($statCred.textContent || '0', 10) || 0;
    if (have < selectedApi.credits) { toast(`Insufficient Credits (${have}/${selectedApi.credits})`, 'error'); return; }
    let lines = ($txt.value || '').replace(/\r/g,'').split(/\n+/).map(s=>s.trim()).filter(Boolean);
    lines = lines.filter(validateLine);
    if (!lines.length) { toast('No valid cards', 'warning'); return; }
    if (lines.length > maxChecks) { toast(`Max ${maxChecks} checks for your plan`, 'warning'); return; }
    let urls = [];
    let urlPackage = '';
    if (gateway === 'shopify' && api === 'autoshopify') {
      if ($cfgAutoApiConfig.value === 'inbuilt') {
        urlPackage = $urlPackageSelect.value || 'free';
      } else {
        urls = ($urlInput.value || '').replace(/\r/g,'').split(/\n+/).map(s=>s.trim()).filter(Boolean).filter(validateUrl);
        if (urls.length === 0) { toast('At least one valid URL is required for AutoShopify GraphQL', 'warning'); return; }
        if (urls.length > 500) { toast('Maximum 500 URLs allowed', 'warning'); return; }
      }
    }
    let cookies = [];
    if (gateway === 'amazon' && api === 'amazonauth') {
      cookies = ($cookieInput.value || '').replace(/\r/g,'').split(/\n+/).map(s=>s.trim()).filter(Boolean);
      if (cookies.length === 0) { toast('At least one valid cookie is required for Amazon Auth', 'warning'); return; }
    }
    const wooStripeApis = ['autowoostripe1', 'autowoostripe2', 'autowoostripe3', 'autowoostripe4', 'autowoostripe5'];
    let siteLink = '';
    let sitePackage = '';
    if (gateway === 'stripe' && wooStripeApis.includes(api)) {
      if ($cfgAutoApiConfig.value === 'inbuilt') {
        sitePackage = $stripePackageSelect.value || 'free';
      } else {
        siteLink = ($siteLinkInput.value || '').trim();
        if (!validateUrl(siteLink)) { toast('A valid Full Site Link is required for Auto Woo+Stripe APIs', 'warning'); return; }
      }
    }
    initProgress(lines.length);
    speed.reset();
    running = true;
    aborter = new AbortController();
    $start.disabled = true; $stop.disabled = false;
    $start.innerHTML = 'Checking... <i class="fas fa-spinner fa-spin"></i>';
    $boxApproved.innerHTML = '';
    $boxLive.innerHTML = '';
    $boxDead.innerHTML = '';
    $statTotal.textContent = String(lines.length);
    $statYet.textContent = String(lines.length);
    $statApproved.textContent = '0';
    $statLive.textContent = '0';
    $statDead.textContent = '0';
    $statGateway.textContent = gatewayLabel(gateway, api);
    let credits = parseInt($statCred.textContent || '0', 10) || 0;
    const useProxy = $proxySw.checked ? '1' : '0';
    const hitSound = $cfgHitSoundSwitch.checked;
    const hitSender = $cfgHitSender.value;
    let proxyHost='', proxyPort='', proxyUsername='', proxyPassword='';
    if (useProxy === '1') {
      const selectedValue = $proxySelect.value;
      const ALL_PROXIES = <?= json_encode(array_map(function($p){ return ['host'=>$p['host'],'port'=>$p['port'],'username'=>$p['username']??'','password'=>$p['password']??'']; }, $userProxies)) ?>;
      if (selectedValue === 'random' && ALL_PROXIES.length > 0) {
        const rnd = ALL_PROXIES[Math.floor(Math.random()*ALL_PROXIES.length)];
        proxyHost=rnd.host; proxyPort=rnd.port; proxyUsername=rnd.username; proxyPassword=rnd.password;
      } else if (selectedValue !== 'random') {
        try{ const sel = JSON.parse(selectedValue); proxyHost=sel.host; proxyPort=sel.port; proxyUsername=sel.username; proxyPassword=sel.password; }catch{}
      }
    }
    const skKey = ($skInput.value || '').trim();
    async function runSingle(line){
      let shopifyUrl = 'N/A';
      let cookie = 'N/A';
      try{
        const qs = new URLSearchParams({ cc: line, useProxy, hitSender });
        if (skKey && gateway==='stripe' && api==='skbased') qs.append('sk', skKey);
        if (gateway==='shopify' && api==='autoshopify'){
          if ($cfgAutoApiConfig.value === 'inbuilt') {
            qs.append('urlPackage', urlPackage);
          } else {
            shopifyUrl = urls.length>1 ? urls[Math.floor(Math.random()*urls.length)] : urls[0];
            qs.append('url', shopifyUrl);
          }
        }
        if (gateway==='amazon' && api==='amazonauth'){
          cookie = cookies.length>1 ? cookies[Math.floor(Math.random()*cookies.length)] : cookies[0];
          qs.append('cck', btoa(cookie));
        }
        if (gateway === 'stripe' && wooStripeApis.includes(api)) {
          if ($cfgAutoApiConfig.value === 'inbuilt') {
            qs.append('sitePackage', sitePackage);
          } else {
            qs.append('siteLink', siteLink);
          }
        }
        // ─────── NEW: PayPal Key Based ───────
        if (gateway === 'paypal' && api === 'paypalkeyavs') {
          const cid = ($paypalClientId?.value || '').trim();
          const sec = ($paypalClientSecret?.value || '').trim();
          if (cid) qs.append('client_id', cid);
          if (sec) qs.append('client_secret', sec);
        }
        if (proxyHost && proxyPort){
          qs.append('host', proxyHost); qs.append('port', proxyPort);
          qs.append('user', proxyUsername); qs.append('pass', proxyPassword);
        }
        
        const url = `${selectedApi.endpoint}?${qs.toString()}`;
        const t0 = performance.now();
        const res = await fetch(url, { method:'GET', cache:'no-store', signal:aborter.signal, keepalive:true });
        const t1 = performance.now();
        if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        const data = await res.json();
        const respText = (data && (data.Response ?? data.response)) ?? 'Unknown';
        const gate = (data && (data.Gateway ?? data.gateway)) ?? gatewayLabel(gateway, api);
        addSample(gate, (t1 - t0));
        const cat = classify(data);
        const brand = data?.brand ?? 'UNKNOWN';
        const ctype = data?.card_type ?? 'UNKNOWN';
        const level = data?.level ?? 'STANDARD';
        const bank = data?.issuer ?? 'Unknown';
        const country = data?.country_info ?? 'Unknown';
        const block =
`<div class="${cat==='APPROVED'?'line-ok':(cat==='LIVE'?'line-live':'line-dead')}">
#${esc(gate).replace(/\s+/g,'')}
━━━━━━━━━━━
[ﾒ] Card ➜ <span class="cc-mask">${esc(line)}</span>
[ﾒ] Status ➜ ${cat==='APPROVED'?'Approved ✅':(cat==='LIVE'?'Live ✅':'Dead ❌')}
[ﾒ] Response ➜ ${esc(respText)}
[ﾒ] Gateway ➜ ${esc(gate)}
━━━━━━━━━━━
[ﾒ] Info ➜ ${esc(brand)} - ${esc(ctype)} - ${esc(level)}
[ﾒ] Bank ➜ ${esc(bank)}
[ﾒ] Country ➜ ${esc(country)}
━━━━━━━━━━━
[ﾒ] Time ➜ ${((t1 - t0)/1000).toFixed(2)}s
</div>
`;
        if (cat === 'APPROVED') {
          $boxApproved.innerHTML += block + '\n';
          $statApproved.textContent = String(+$statApproved.textContent + 1);
          if (hitSound) byId('approvedSound')?.play?.();
          hitToast(respText, 'success');
        } else if (cat === 'LIVE') {
          $boxLive.innerHTML += block + '\n';
          $statLive.textContent = String(+$statLive.textContent + 1);
          if (hitSound) byId('liveSound')?.play?.();
          hitToast(respText, 'info');
        } else if (cat === 'DEAD') {
          $boxDead.innerHTML += block + '\n';
          $statDead.textContent = String(+$statDead.textContent + 1);
        }
        if (typeof data?.credits === 'number') { credits = data.credits; }
        else {
          if (cat === 'APPROVED') credits = Math.max(0, credits - selectedApi.credits);
          else if (cat === 'LIVE') credits = Math.max(0, credits - Math.min(selectedApi.credits, 1));
        }
        $statCred.textContent = String(credits);
      }catch(e){
        if (e.name === 'AbortError') throw e;
        const errorMessage = e.message || 'Request failed';
        const blockErr =
`<div class="line-dead">
#${esc(gatewayLabel(gateway, api))}
━━━━━━━━━━━
[ﾒ] Card ➜ <span class="cc-mask">${esc(line)}</span>
[ﾒ] Status ➜ Dead ❌
[ﾒ] Response ➜ ${esc(errorMessage)}
[ﾒ] Gateway ➜ ${esc(gatewayLabel(gateway, api))}
━━━━━━━━━━━
[ﾒ] Info ➜ UNKNOWN - UNKNOWN - STANDARD
[ﾒ] Bank ➜ Unknown
[ﾒ] Country ➜ Unknown
━━━━━━━━━━━
</div>
`;
        $boxDead.innerHTML += blockErr + '\n';
        $statDead.textContent = String(+$statDead.textContent + 1);
      }finally{
        const arr = ($txt.value || '').split(/\n+/).map(s=>s.trim()).filter(Boolean);
        const idx = arr.indexOf(line);
        if (idx > -1) { arr.splice(idx,1); $txt.value = arr.join('\n'); }
        $statYet.textContent = String(Math.max(0, +$statYet.textContent - 1));
        tickProgress();
      }
    }
    const queue = [...lines];
    await worker(queue, { runOne: runSingle });
    if (running) {
      running = false;
      $start.disabled = false;
      $start.innerHTML = '<i class="fas fa-play mr-1"></i> Start Checking';
      $stop.disabled = true;
      toast('Checking completed', 'success');
    }
  }
  $start.addEventListener('click', startRun);
  $stop.addEventListener('click', () => {
    if (!running) return;
    stopRun();
  });
  // Copy results
  byId('copyApproved').addEventListener('click', () => {
    const t = $boxApproved.innerText.trim();
    if (!t) return;
    navigator.clipboard.writeText(t).then(() => toast('Approved results copied', 'success'));
  });
  byId('copyLive').addEventListener('click', () => {
    const t = $boxLive.innerText.trim();
    if (!t) return;
    navigator.clipboard.writeText(t).then(() => toast('Live results copied', 'success'));
  });
  byId('clearDead').addEventListener('click', () => {
    $boxDead.innerHTML = '';
    toast('Dead results cleared', 'info');
  });
  // Credits modal
  const modal = byId('creditsModal');
  const card = byId('creditsCard');
  const ack = byId('ackBtn');
  const ACK_KEY = 'cx_ack_credits_v1';
  function openModal() {
    if (!modal || !card) return;
    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
      card.classList.remove('translate-y-4', 'opacity-0');
      card.classList.add('translate-y-0', 'opacity-100');
    });
  }
  function closeModal() {
    if (!modal || !card) return;
    card.classList.add('translate-y-4', 'opacity-0');
    setTimeout(() => modal.classList.add('hidden'), 180);
  }
  if (!localStorage.getItem(ACK_KEY)) { openModal(); }
  ack?.addEventListener('click', () => {
    localStorage.setItem(ACK_KEY, String(Date.now()));
    closeModal();
  });
  // CC Generator Integration
  $btnGen.addEventListener('click', () => {
    const cards = generate();
    if (cards) {
      const curr = ($txt.value || '').trim();
      $txt.value = (curr ? (curr + '\n') : '') + cards;
      toast('Generated cards added to Card List', 'success');
    }
  });
  // Prevent reload during checking
  window.onbeforeunload = () => running ? 'Checking is in progress. Are you sure you want to leave?' : null;
  // restore cfg on load (if stored)
  const initialCfg = loadCfg();
  if (initialCfg) {
    applyCfg(initialCfg, false);
    toggleAuxUrlInput(); // Apply initial toggle based on saved config
  }
  if ($proxySw.checked) $proxySelectContainer.classList.remove('hidden');
})();
</script>
</body>
</html>
