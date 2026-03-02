<?php
declare(strict_types=1);
require_once __DIR__ . '/app/Bootstrap.php';

$botUsername   = $_ENV['TELEGRAM_BOT_USERNAME'] ?? ''; // without @
$requireAllow  = filter_var($_ENV['TELEGRAM_REQUIRE_ALLOWLIST'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$announceChat  = $_ENV['TELEGRAM_ANNOUNCE_CHAT_ID'] ?? '-1002552641928';
$joinLink      = 'https://t.me/+iJQ1t3G4a4Q0NjI8';

// Maintenance: when allowlist is ON and not using staff link
$maintenance = $requireAllow && !isset($_GET['admin']);

// -------- Error/notice preparation --------
$errCode    = isset($_GET['error']) ? strtolower((string)$_GET['error']) : '';
$needParam  = (string)($_GET['need'] ?? '');
$needJoin   = (isset($_GET['join']) && $_GET['join'] === '1'); // force join CTA
$bannerHtml = '';

// Safe HTML helper
$esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Banner render helper
function renderBanner(string $type, string $title, string $msg): string {
  $m = [
    'warn' => ['border'=>'border-amber-400/30','bg'=>'bg-amber-500/10','txt'=>'text-amber-100'],
    'err'  => ['border'=>'border-rose-400/30', 'bg'=>'bg-rose-500/10', 'txt'=>'text-rose-100'],
    'info' => ['border'=>'border-sky-400/30',  'bg'=>'bg-sky-500/10',  'txt'=>'text-sky-100'],
    'ok'   => ['border'=>'border-emerald-400/30','bg'=>'bg-emerald-500/10','txt'=>'text-emerald-100'],
  ][$type] ?? ['border'=>'border-slate-400/20','bg'=>'bg-slate-700/20','txt'=>'text-slate-100'];

  return '<div class="rounded-xl border '.$m['border'].' '.$m['bg'].' '.$m['txt'].' px-4 py-3 text-sm">'.
           '<div class="font-semibold">'.$title.'</div>'.
           '<div class="mt-1">'.$msg.'</div>'.
         '</div>';
}

// Config warning (bot username missing)
if ($botUsername === '') {
  $bannerHtml .= renderBanner(
    'err',
    'Configuration error',
    'Environment variable <code>TELEGRAM_BOT_USERNAME</code> is missing. Please set your bot username (without @) and try again.'
  );
}

// Profile incomplete case
if ($errCode === 'profile_missing') {
  // parse need list
  $keys = array_values(array_filter(array_map('trim', explode(',', $needParam))));
  $parts = [];
  foreach ($keys as $k) {
    if ($k === 'name')     $parts[] = 'Name';
    if ($k === 'username') $parts[] = 'Username';
    if ($k === 'photo')    $parts[] = 'Profile photo';
  }
  $needList = $parts ? implode(' / ', $parts) : 'required profile information';

  $tips = [];
  if (in_array('name', $keys, true)) {
    $tips[] = 'Set your <b>Name</b>: Telegram → <b>Settings → Edit Name</b>.';
  }
  if (in_array('username', $keys, true)) {
    $tips[] = 'Set your <b>Username</b>: Telegram → <b>Settings → Username</b>.';
  }
  if (in_array('photo', $keys, true)) {
    $tips[] = 'Add a <b>Profile photo</b>: Telegram → <b>Settings → Set Profile Photo</b>.';
  }

  $msg  = 'Your Telegram profile is incomplete. Missing: <b>'.$esc($needList).'</b>.';
  if ($tips) { $msg .= '<div class="mt-2 text-xs opacity-90">'.implode('<br>', $tips).'</div>'; }
  $msg .= '<div class="mt-3"><a href="/" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20">↻ Try again</a></div>';

  $bannerHtml .= renderBanner('warn', 'Profile incomplete', $msg);
}

// Unauthorized + ask to join
if ($errCode === 'unauthorized') {
  $needJoin = true; // usually means not a member of the channel/group
  $bannerHtml .= renderBanner('err', 'Unauthorized', 'You are not authorized to log in.');
}

// Any other error code
if ($errCode !== '' && !in_array($errCode, ['profile_missing','unauthorized'], true)) {
  $bannerHtml .= renderBanner('err', 'Something went wrong', 'Code: <code>'.$esc($errCode).'</code>. Please try again.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $maintenance ? 'Maintenance Mode • CyborX' : 'Sign in • CyborX' ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@tailwindcss/browser@4"></script>

<link rel="icon" href="/assets/branding/cyborx-mark.png">
<style>
  :root{ --glass: rgba(255,255,255,0.06); --stroke: rgba(255,255,255,.12); }
  html,body{height:100%;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial}
  body{
    background:
      radial-gradient(1100px 700px at 8% -10%, rgba(106,92,255,.20), transparent 60%),
      radial-gradient(900px 500px at 110% 110%, rgba(0,229,255,.16), transparent 60%),
      #0a1022;
    color:#e7eefc;
  }
  .glass{backdrop-filter: blur(14px); background: var(--glass); border:1px solid var(--stroke)}
  .card{box-shadow: 0 25px 70px rgba(0,0,0,.45), inset 0 0 0 1px rgba(255,255,255,.03)}
</style>
</head>
<body class="min-h-full">

  <main class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-xl space-y-6">

      <!-- Brand -->
      <div class="flex flex-col items-center text-center">
        <div class="w-16 h-16 rounded-2xl bg-slate-900/60 border border-white/10 grid place-items-center shadow-lg">
          <img src="/assets/branding/cyborx-mark.png" alt="CyborX" class="w-12 h-12 rounded-xl">
        </div>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight">CyborX: Secure Sign-in</h1>
      </div>

      <!-- Error / Notice banners -->
      <?php if ($bannerHtml): ?>
        <?= $bannerHtml ?>
      <?php endif; ?>

      <?php if ($maintenance && !isset($_GET['staff'])): ?>
        <!-- Maintenance UI -->
        <div class="glass card rounded-2xl p-6">
          <div class="text-center mb-4">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-500 to-rose-500 shadow-lg mb-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="currentColor">
                <path d="M1 21h22L12 2 1 21zm12-3h-2v2h2v-2zm0-8h-2v6h2V10z"/>
              </svg>
            </div>
            <h2 class="text-2xl font-semibold">Maintenance Mode is ON</h2>
            <p class="text-sm text-slate-400 mt-1">We’re upgrading a few systems. Please check back later.</p>
          </div>
          <div class="space-y-3 text-sm text-slate-300">
            <div class="flex items-center justify-between">
              <span>Status</span>
              <span class="inline-flex items-center rounded-full bg-amber-500/15 text-amber-300 px-2 py-0.5">Updating</span>
            </div>
            <div class="flex items-center justify-between">
              <span>Access</span>
              <span>Admins only</span>
            </div>
            <div class="pt-2 text-xs text-slate-500">Admin? Use the admin link below.</div>
          </div>
        </div>
        <div class="text-center">
          <a href="/?admin=1" class="inline-flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 px-3 py-2 text-xs text-slate-200">
            Admin Login In Only
          </a>
        </div>

      <?php else: ?>
        <!-- Sign-in Card -->
        <div class="glass card rounded-3xl p-6">
          <div class="flex flex-col items-center gap-4">
            <span class="text-sm text-slate-300">Sign in with Telegram</span>

            <!-- Telegram widget -->
            <div class="w-full flex justify-center">
              <script async src="https://telegram.org/js/telegram-widget.js?22"
                      data-telegram-login="<?= $esc($botUsername) ?>"
                      data-size="large"
                      data-auth-url="/telegram_auth.php"
                      data-request-access="write"></script>
            </div>

            <?php if ($needJoin): ?>
              <!-- Join requirement -->
              <div class="w-full text-center rounded-2xl border border-amber-400/30 bg-amber-400/10 p-4">
                <div class="text-amber-200 text-sm">
                  You must join our Telegram channel to continue.
                </div>
                <div class="mt-2">
                  <a class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-slate-900 font-bold
                             bg-gradient-to-r from-pink-400 to-amber-300"
                     href="<?= $esc($joinLink) ?>"
                     target="_blank" rel="noreferrer">
                    Join CyborX Announcements
                  </a>
                </div>
                <div class="text-[11px] text-amber-200/80 mt-2">
                  Channel ID: <code><?= $esc($announceChat) ?></code>
                </div>
              </div>
            <?php endif; ?>

            <p class="text-[11px] text-slate-500 text-center">
              Telegram OAuth is secure. We do not get access to your account.
            </p>
          </div>
        </div>

        <!-- Legal + Powered by -->
        <div class="text-center text-xs text-slate-400">
          By continuing, you agree to our
          <a class="text-sky-300 hover:underline" href="/legal/terms">Terms of Service</a> and
          <a class="text-sky-300 hover:underline" href="/legal/privacy">Privacy Policy</a>.
        </div>
        <div class="flex items-center justify-center gap-2 text-xs text-slate-400">
          <span>Powered by</span>
          <img src="/assets/branding/blink-badge.png" alt="Cyborx" class="h-5">
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
