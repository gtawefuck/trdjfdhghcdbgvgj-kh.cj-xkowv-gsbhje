<?php
declare(strict_types=1);

/**
 * CyborX Router (SSR)
 * Nginx rewrite: /app/<path>  --->  /router.php?path=<path>
 * - Fixed header/left/right come from layout_master.php
 * - Center content comes from views/<view>.php
 * - Supports partial=1 for center-only render (optional PJAX)
 * - Avoids function redeclare by using function_exists guards
 *
 * XCoin migration note:
 *   - DB: former `kcoin` values are now stored in `xcoin`.
 *   - A NEW `kcoin` column also exists (separate balance).
 *   - $u_cash now reflects the XCoin balance (from users.xcoin).
 */

require_once __DIR__ . '/app/Bootstrap.php';
require_once __DIR__ . '/app/Db.php';

/* ----------- Auth guard ----------- */
if (empty($_SESSION['uid'])) {
  header('Location: /');
  exit;
}

$pdo = \App\Db::pdo();
$uid = (int)$_SESSION['uid'];

/* ----------- Helper functions (guarded) ----------- */
if (!function_exists('b')) {
  function b($v){ return number_format((int)$v); }
}
if (!function_exists('roleBadge')) {
  function roleBadge($status){
    $m = [
      'admin'   => ['bg'=>'bg-rose-500/15','text'=>'text-rose-300','label'=>'ADMIN'],
      'premium' => ['bg'=>'bg-amber-500/15','text'=>'text-amber-300','label'=>'PREMIUM'],
      'free'    => ['bg'=>'bg-slate-500/20','text'=>'text-slate-300','label'=>'FREE'],
      'banned'  => ['bg'=>'bg-rose-500/15','text'=>'text-rose-300','label'=>'BANNED'],
    ][$status] ?? ['bg'=>'bg-slate-500/20','text'=>'text-slate-300','label'=>'FREE'];
    return "<span class=\"inline-flex items-center rounded-md px-2 py-[2px] text-[10px] font-semibold {$m['bg']} {$m['text']}\">{$m['label']}</span>";
  }
}
if (!function_exists('daysLeft')) {
  function daysLeft(?string $date){
    if (!$date) return null;
    $d = (new DateTime($date))->setTime(23,59,59);
    $now = new DateTime('now');
    return (int)$now->diff($d)->format('%r%a');
  }
}

/* ----------- Current user + mark online ----------- */
$stmt = $pdo->prepare("SELECT id, username, first_name, last_name, profile_picture,
                              credits, lives, charges, status, xcoin, kcoin,
                              expiry_date, last_login
                       FROM users WHERE id = :uid LIMIT 1");
$stmt->execute([':uid'=>$uid]);
$user = $stmt->fetch();
if (!$user) { header('Location: /'); exit; }

$pdo->prepare("UPDATE users SET online_status='online', last_activity = NOW() WHERE id=:uid")
    ->execute([':uid'=>$uid]);

/* Per-user vars used by layout/sidebar/header */
$u_username  = $user['username'] ?: ('tg_'.$user['id']);
$u_name      = trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')) ?: $u_username;
$u_pic       = (string)($user['profile_picture'] ?? '');
$u_status    = strtolower((string)$user['status']);
$u_credits   = (int)($user['credits'] ?? 0);
$u_cash      = (int)($user['xcoin'] ?? 0); // XCoin balance shown in header
$u_xcoin     = $u_cash;                    // alias for clarity in views if needed
$u_kcoin     = (int)($user['kcoin'] ?? 0); // NEW KCoin balance (separate)
$u_lives     = (int)($user['lives'] ?? 0);
$u_charges   = (int)($user['charges'] ?? 0);
$u_hits      = $u_lives + $u_charges;
$u_lastlogin = (string)($user['last_login'] ?? '—');
$u_expiry    = $user['expiry_date'] ?: null;

$unameSafe = htmlspecialchars($u_username, ENT_QUOTES);
$nameSafe  = htmlspecialchars($u_name, ENT_QUOTES);

/* Expiry banner */
$expDays     = daysLeft($u_expiry);
$showBanner  = false; $bannerHtml = '';
$lowCredits  = ($u_credits < 10);
$nearExpiry  = ($expDays !== null && $expDays <= 5);
if ($lowCredits || $nearExpiry) {
  $showBanner = true;
  if ($lowCredits && $nearExpiry) {
    $bannerHtml = "Your <strong>{$u_credits}</strong> credits and plan are near the end (<strong>{$expDays} days</strong>). <a href=\"/app/buy\" class=\"underline\">Upgrade your plan now</a>.";
  } elseif ($lowCredits) {
    $bannerHtml = "Your credits (<strong>{$u_credits}</strong>) are almost finished. <a href=\"/app/buy\" class=\"underline\">Buy credits</a> or <a href=\"/app/buy\" class=\"underline\">upgrade your plan</a>.";
  } else {
    $left = ($expDays !== null) ? (int)$expDays : 0;
    $bannerHtml = "Your plan will expire in <strong>{$left} days</strong>. <a href=\"/app/buy\" class=\"underline\">Upgrade your plan now</a>.";
  }
}

/* ----------- Initial SSR lists for right column ----------- */
$onlineSSR = $pdo->query("SELECT username, first_name, last_name, profile_picture, status
                          FROM users
                          WHERE online_status='online' AND last_activity >= (NOW() - INTERVAL 5 MINUTE)
                          ORDER BY last_activity DESC LIMIT 20")->fetchAll();

$topSSR = $pdo->query("SELECT username, first_name, last_name, profile_picture, status,
                              (lives + charges) AS hits,
                              (online_status='online' AND last_activity >= (NOW() - INTERVAL 5 MINUTE)) AS is_online
                       FROM users
                       ORDER BY hits DESC LIMIT 20")->fetchAll();

/* ----------- Resolve view from /app/<path> or ?view= ----------- */
$path = isset($_GET['path']) ? (string)$_GET['path'] : '';
$path = trim($path, '/');
if ($path === '' && !empty($_GET['view'])) {
  $path = (string)$_GET['view'];
}
$view = $path === '' ? 'dashboard' : strtolower(explode('/', $path, 2)[0]);

$allowed = ['dashboard','deposit','buy','redeem','checkers','autohitters','killers','settings','admin','admin-panel'];
if (!in_array($view, $allowed, true)) {
  http_response_code(404);
  $view = '404';
}

if ($view === 'admin-panel') $view = 'admin';

/* If admin page but user not admin -> 403 to dashboard or 404 choice */
if ($view === 'admin' && $u_status !== 'admin') {
  http_response_code(403);
  // চাইলে ড্যাশবোর্ডে পাঠাতে পারো:
  $view = 'dashboard';
}

/* View file resolve */
$viewFile = __DIR__ . '/views/' . $view . '.php';
if (!is_file($viewFile)) {
  http_response_code(404);
  $view     = '404';
  $viewFile = __DIR__ . '/views/404.php';
}

/* ----------- Partial render (center-only) ----------- */
$partial = isset($_GET['partial']) && $_GET['partial'] === '1';
if ($partial) {
  // শুধু কেন্দ্রের কন্টেন্ট (layout ছাড়া)
  include $viewFile;
  exit;
}

/* ----------- Full render via master layout ----------- */
$title   = ucfirst($view) . ' • CyborX';
$pageKey = $view; // layout_master.php চাইলে active menu highlight এ ব্যবহার করবে

// layout_master.php এর ভেতরে center অংশে include $viewFile; করা থাকবে
include __DIR__ . '/layout_master.php';
