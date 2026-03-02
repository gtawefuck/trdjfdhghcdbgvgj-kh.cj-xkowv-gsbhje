<?php
declare(strict_types=1);
require_once __DIR__ . '/app/Bootstrap.php';
require_once __DIR__ . '/app/Db.php';

if (empty($_SESSION['uid'])) { header('Location: /'); exit; }

$uid = (int)$_SESSION['uid'];
$pdo = \App\Db::pdo();

/* ---- Current user ---- */
// XCoin migration: former `kcoin` values now live in `xcoin`.
// A new `kcoin` column also exists (separate balance).
$stmt = $pdo->prepare("SELECT id, username, first_name, last_name, profile_picture,
                              credits, lives, charges, status, xcoin, kcoin,
                              expiry_date, last_login
                       FROM users WHERE id = :uid LIMIT 1");
$stmt->execute([':uid'=>$uid]);
$user = $stmt->fetch();
if (!$user) { header('Location: /'); exit; }

/* mark online */
$pdo->prepare("UPDATE users SET online_status='online', last_activity=NOW() WHERE id=:uid")
    ->execute([':uid'=>$uid]);

/* helpers (guarded) */
if (!function_exists('daysLeft')) {
  function daysLeft(?string $date){ if(!$date) return null; $d=(new DateTime($date))->setTime(23,59,59); $now=new DateTime('now'); return (int)$now->diff($d)->format('%r%a'); }
}
if (!function_exists('b')) { function b($v){ return number_format((int)$v); } }
if (!function_exists('roleBadge')) {
  function roleBadge($status){
    $m=['admin'=>['bg'=>'bg-rose-500/15','text'=>'text-rose-300','label'=>'ADMIN'],
        'premium'=>['bg'=>'bg-amber-500/15','text'=>'text-amber-300','label'=>'PREMIUM'],
        'free'=>['bg'=>'bg-slate-500/20','text'=>'text-slate-300','label'=>'FREE'],
        'banned'=>['bg'=>'bg-rose-500/15','text'=>'text-rose-300','label'=>'BANNED'],
    ][$status] ?? ['bg'=>'bg-slate-500/20','text'=>'text-slate-300','label'=>'FREE'];
    return "<span class=\"inline-flex items-center rounded-md px-2 py-[2px] text-[10px] font-semibold {$m['bg']} {$m['text']}\">{$m['label']}</span>";
  }
}

/* user vars */
$u_username = $user['username'] ?: ('tg_'.$user['id']);
$u_name     = trim(($user['first_name']??'').' '.($user['last_name']??'')) ?: $u_username;
$u_pic      = (string)($user['profile_picture'] ?? '');
$u_status   = strtolower((string)$user['status']);
$u_credits  = (int)($user['credits'] ?? 0);
$u_cash     = (int)($user['xcoin'] ?? 0); // XCoin balance for header/widgets
$u_xcoin    = $u_cash;                     // alias for clarity
$u_kcoin    = (int)($user['kcoin'] ?? 0);  // NEW separate KCoin balance
$u_lives    = (int)($user['lives'] ?? 0);
$u_charges  = (int)($user['charges'] ?? 0);
$u_hits     = $u_lives + $u_charges;
$u_lastlogin= (string)($user['last_login'] ?? '—');
$u_expiry   = $user['expiry_date'] ?: null;
$expDays    = daysLeft($u_expiry);

$unameSafe = htmlspecialchars($u_username, ENT_QUOTES);
$nameSafe  = htmlspecialchars($u_name, ENT_QUOTES);

/* Heads-up banner */
$showBanner=false; $bannerHtml='';
$lowCredits = ($u_credits < 10);
$nearExpiry = ($expDays !== null && $expDays <= 5);
if ($lowCredits || $nearExpiry) {
  $showBanner = true;
  if ($lowCredits && $nearExpiry) $bannerHtml = "Your <strong>{$u_credits}</strong> credits and plan are near the end (<strong>{$expDays} days</strong>). <a href=\"/app/buy\" class=\"underline\">Upgrade</a>.";
  elseif ($lowCredits) $bannerHtml = "Your credits (<strong>{$u_credits}</strong>) are almost finished. <a href=\"/app/buy\" class=\"underline\">Buy credits</a>.";
  else $bannerHtml = "Your plan will expire in <strong>".(int)$expDays." days</strong>. <a href=\"/app/buy\" class=\"underline\">Upgrade</a>.";
}

/* SSR lists for right column */
$onlineSSR = $pdo->query("SELECT username, first_name, last_name, profile_picture, status
                          FROM users
                          WHERE online_status='online' AND last_activity >= (NOW() - INTERVAL 5 MINUTE)
                          ORDER BY last_activity DESC LIMIT 20")->fetchAll();

$topSSR = $pdo->query("SELECT username, first_name, last_name, profile_picture, status,
                              (lives + charges) AS hits,
                              (online_status='online' AND last_activity >= (NOW() - INTERVAL 5 MINUTE)) AS is_online
                       FROM users
                       ORDER BY hits DESC LIMIT 20")->fetchAll();

/* -------- Router -------- */
$uri = $_SERVER['REQUEST_URI'] ?? '/app';
$path = parse_url($uri, PHP_URL_PATH) ?: '/app';
$seg  = explode('/', trim($path,'/')); // [app, dashboard]
$page = ($seg[0] ?? '') === 'app' ? ($seg[1] ?? 'dashboard') : 'dashboard';
$allowed = ['dashboard','deposit','buy','redeem','checkers','autohitters','killers','settings','admin'];
if (!in_array($page, $allowed, true)) { $page = 'dashboard'; }

$viewFile = __DIR__ . '/views/'.$page.'.php';

/* Partial request? (AJAX) -> only center HTML */
$isPartial = (isset($_SERVER['HTTP_X_PARTIAL']) && $_SERVER['HTTP_X_PARTIAL'] === '1')
             || (isset($_GET['partial']) && $_GET['partial'] === '1');

if ($isPartial) {
  if (is_file($viewFile)) { require $viewFile; }
  else { http_response_code(404); echo '<div class="p-6 text-slate-300">Not found</div>'; }
  exit;
}

/* Full page render with fixed layout */
require __DIR__ . '/layout_master.php';
