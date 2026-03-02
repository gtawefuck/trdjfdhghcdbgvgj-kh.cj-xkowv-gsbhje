<?php
declare(strict_types=1);
// কোনো স্পেস/আউটপুট নয়
require_once __DIR__ . '/app/Bootstrap.php';
require_once __DIR__ . '/app/Security.php';
require_once __DIR__ . '/app/Db.php';
require_once __DIR__ . '/app/Telegram.php';
use App\Security;
use App\Db;
use App\Telegram;
/* Debug helper */
function tgdbg(string $m): void {
    if (filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
        @file_put_contents(__DIR__ . '/tg-auth-debug.log', '[' . date('c') . "] " . $m . "\n", FILE_APPEND);
    }
}
/* ENV */
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
$requireAllow = filter_var($_ENV['TELEGRAM_REQUIRE_ALLOWLIST'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$allowIds = Telegram::parseAllowlist($_ENV['TELEGRAM_ALLOWED_IDS'] ?? '');
$announceChat = $_ENV['TELEGRAM_ANNOUNCE_CHAT_ID'] ?? '-1002552641928'; // MUST be member of this chat
/* Guards */
if ($botToken === '' || !isset($_GET['hash'])) { tgdbg('400 missing token/hash'); http_response_code(400); exit; }
if (!Telegram::verify($_GET, $botToken, 900)) { tgdbg('400 verify fail'); http_response_code(400); exit; }
/* Extract from Telegram Login Widget */
$tgId = (string)($_GET['id'] ?? '');
$tUser = (string)($_GET['username'] ?? '');
$first = (string)($_GET['first_name'] ?? '');
$last = (string)($_GET['last_name'] ?? '');
$photo = (string)($_GET['photo_url'] ?? '');
/* Allowlist (optional) */
if ($requireAllow && !in_array($tgId, $allowIds, true)) {
    tgdbg("403 not in allowlist id={$tgId}");
    Security::safeRedirect('/?error=unauthorized');
}
/* ---------- REQUIRED: must be member of announce group ---------- */
function isChatMember(string $botToken, string $chatId, string $userId): bool {
    $url = "https://api.telegram.org/bot{$botToken}/getChatMember?chat_id=" . urlencode($chatId) . "&user_id=" . urlencode($userId);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 12,
    ]);
    $res = curl_exec($ch);
    if ($res === false) { tgdbg('tg api error: '.curl_error($ch)); curl_close($ch); return false; }
    curl_close($ch);
    $data = json_decode($res, true);
    if (empty($data['ok'])) return false;
    $status = (string)($data['result']['status'] ?? 'left');
    // allowed statuses
    return in_array($status, ['creator','administrator','member','restricted'], true);
}
if ($announceChat !== '' && !isChatMember($botToken, $announceChat, $tgId)) {
    tgdbg("join required id={$tgId}");
    Security::safeRedirect('/?error=unauthorized&join=1');
}
/* ---------- NEW: Profile completeness check ---------- */
/*
 * name: কমপক্ষে first_name থাকতে হবে (last_name optional)
 * username: বাধ্যতামূলক
 * photo: বাধ্যতামূলক (Telegram photo_url)
 */
$missing = [];
if ($first === '') $missing[] = 'name';
if ($tUser === '') $missing[] = 'username';
if ($photo === '') $missing[] = 'photo';
if ($missing) {
    // উদাহরণ: /?error=profile_missing&need=name,username,photo
    $need = implode(',', $missing);
    tgdbg("profile missing for {$tgId}, need={$need}");
    Security::safeRedirect('/?error=profile_missing&need=' . urlencode($need));
    exit;
}
/* DB upsert (create OR basic save) */
$pdo = Db::pdo();
$res = Telegram::saveUser($pdo, $tgId, $tUser, $first, $last, $photo, 100); // Pass 100 credits for new users
if (!($res['ok'] ?? false)) { tgdbg('500 saveUser'); http_response_code(500); exit; }
$uid = (int)($res['id'] ?? 0);
$status = strtolower((string)($res['status'] ?? 'free'));
$created = (bool)($res['created'] ?? false);
/* ---------- NEW: Keep user fields in-sync on every login ---------- */
try {
    $q = $pdo->prepare("SELECT username, first_name, last_name, profile_picture FROM users WHERE id=:id LIMIT 1");
    $q->execute([':id' => $uid]);
    if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $needUpdate = false;
        $upd = [
            ':id' => $uid,
            ':u' => $row['username'],
            ':f' => $row['first_name'],
            ':l' => $row['last_name'],
            ':p' => $row['profile_picture'],
        ];
        if ((string)$row['username'] !== $tUser) { $upd[':u'] = $tUser; $needUpdate = true; }
        if ((string)$row['first_name'] !== $first) { $upd[':f'] = $first; $needUpdate = true; }
        if ((string)$row['last_name'] !== $last) { $upd[':l'] = $last; $needUpdate = true; }
        if ((string)$row['profile_picture'] !== $photo) { $upd[':p'] = $photo; $needUpdate = true; }
        if ($needUpdate) {
            $u = $pdo->prepare("UPDATE users
                                SET username=:u, first_name=:f, last_name=:l, profile_picture=:p, updated_at=NOW()
                                WHERE id=:id");
            $u->execute($upd);
            tgdbg("user synced id={$uid}");
        }
    }
} catch (Throwable $e) {
    tgdbg('sync error: ' . $e->getMessage());
}
/* ---------- Expiry check: if expired -> free, credits=10, expiry=NULL ---------- */
try {
    $st = $pdo->prepare("SELECT status, expiry_date FROM users WHERE id=:id LIMIT 1");
    $st->execute([':id' => $uid]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $exp = $row['expiry_date'] ?? null;
        if ($exp) {
            $expAt = new DateTime((string)$exp);
            $now = new DateTime('now');
            if ($expAt < $now) {
                $pdo->prepare("UPDATE users SET status='free', credits=10, expiry_date=NULL WHERE id=:id")
                    ->execute([':id'=>$uid]);
                $status = 'free';
                $fname = trim($first.' '.$last);
                $who = $fname !== '' ? $fname : ($tUser !== '' ? '@'.$tUser : 'friend');
                $human = $expAt->format('j M Y');
                $msg = "⛔ <b>Your CyborX Premium expired</b>\n".
                         "Hi <b>{$who}</b>, your plan expired on <b>{$human}</b>. ".
                         "Your account is now <code>FREE</code>, credits set to <b>10</b>.\n\n".
                         "➡️ You can upgrade anytime from <a href=\"https://cyborx.net/app/buy\">Buy Premium</a>.";
                App\Telegram::sendMessage($botToken, $tgId, $msg, 'HTML');
            }
        }
    }
} catch (Throwable $e) { tgdbg('expiry check error: '.$e->getMessage()); }
/* Session */
$_SESSION['uid'] = $uid;
$_SESSION['uname'] = $tUser !== '' ? $tUser : ('tg_' . $tgId);
$_SESSION['last_login'] = time();
session_regenerate_id(true);
/* --- Announce to Telegram group (non-blocking) --- */
if ($announceChat !== '') {
    $display = trim(($first.' '.$last)) ?: ($tUser !== '' ? '@'.$tUser : ('User '.$tgId));
    $displaySafe = htmlspecialchars($display, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $roleLabel = Telegram::roleLabel($status);
    if ($created) {
        $text = "🎉 <b>New member</b>: <b>{$displaySafe}</b> [{$roleLabel}]\n".
                "Welcome to <b>CyborX</b> — glad to have you here! 👋\n".
                "➡️ <a href=\"https://cyborx.net/\">Login to CyborX</a>";
    } else {
        $text = "🌟 <b>{$displaySafe}</b> [{$roleLabel}] just signed in to <b>CyborX</b>.\n".
                "Let’s make some hits today. ➡️ <a href=\"https://cyborx.net/\">Open Dashboard</a>\n";
    }
    Telegram::sendMessage($botToken, $announceChat, $text, 'HTML'); // ignore errors
}
/* Redirect */
$next = '/app/dashboard';
if (!empty($_GET['state']) && is_string($_GET['state']) && preg_match('~^/app(?:/[\w\-]+)?$~', $_GET['state'])) {
    $next = $_GET['state'];
}
App\Security::safeRedirect($next, '/app/dashboard');