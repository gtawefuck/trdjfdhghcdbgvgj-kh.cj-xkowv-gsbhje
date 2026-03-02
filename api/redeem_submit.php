<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Bootstrap.php';
require_once __DIR__ . '/../app/Db.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['uid'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'UNAUTH']); exit; }

$pdo = \App\Db::pdo();
$uid = (int)$_SESSION['uid'];

/* ---------- Limits & Telegram config ---------- */
$DAILY_LIMIT = 1; // ✅ দিনে সর্বোচ্চ কয়টা রিডিম করা যাবে

$BOT_TOKEN     = $_ENV['TELEGRAM_BOT_TOKEN']        ?? '';
$ANNOUNCE_CHAT = $_ENV['TELEGRAM_ANNOUNCE_CHAT_ID'] ?? '';

/* ---------- tiny log + telegram helpers ---------- */
$LOG_FILE = '/www/wwwroot/cyborx.net/storage/logs/redeem.log';
@is_dir(dirname($LOG_FILE)) || @mkdir(dirname($LOG_FILE), 0775, true);
function logerr(string $m){ global $LOG_FILE; @file_put_contents($LOG_FILE,'['.date('c')."] $m\n",FILE_APPEND); }

function tg_send(string $token,string $chat,string $txt,string $parse='HTML'):bool{
  if($token===''||$chat==='') return false;
  if(!function_exists('curl_init')){ logerr('curl missing for telegram'); return false; }
  $ch=curl_init("https://api.telegram.org/bot{$token}/sendMessage");
  curl_setopt_array($ch,[
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>http_build_query([
      'chat_id'=>$chat,'text'=>$txt,'parse_mode'=>$parse,'disable_web_page_preview'=>'true'
    ]),
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>10
  ]);
  curl_exec($ch);
  $ok=(curl_errno($ch)===0)&&(curl_getinfo($ch,CURLINFO_HTTP_CODE)===200);
  if(!$ok) logerr('tg_send fail: '.curl_error($ch).' http='.curl_getinfo($ch,CURLINFO_HTTP_CODE));
  curl_close($ch);
  return $ok;
}

/* simple maskers */
function utf8_first_char(string $s): string { if (function_exists('mb_substr')) return mb_substr($s, 0, 1, 'UTF-8'); if (preg_match('/^./u', $s, $m)) return $m[0]; return substr($s, 0, 1); }
function utf8_upper(string $s): string { if (function_exists('mb_strtoupper')) return mb_strtoupper($s, 'UTF-8'); return strtoupper($s); }
function mask_public_name(string $who): string { $name = trim(ltrim($who,'@')); if($name==='') return 'U***'; $f = utf8_first_char($name); return utf8_upper($f).'***'; }
function mask_redeem_code(string $code): string {
  // keep prefix 'CYBORX-' & suffix '-XXXX', mask middle
  $c = trim($code);
  if ($c==='') return 'C********';
  if (preg_match('~^(CYBORX-)(.+)(-CREDITS|-FREE|-PREMIUM)$~i', $c, $m)) {
    $body = $m[2];
    $keepL = 4; $keepR = 3;
    $masked = substr($body,0,$keepL) . str_repeat('X', max(0, strlen($body)-$keepL-$keepR)) . substr($body,-$keepR);
    return $m[1].$masked.(preg_match('~credits$~i',$m[3])?'-CREDITS':$m[3]);
  }
  // fallback: expose first 4 last 3
  $keepL = 4; $keepR = 3;
  return substr($c,0,$keepL).str_repeat('X', max(0, strlen($c)-$keepL-$keepR)).substr($c,-$keepR);
}

/* ---------- input ---------- */
$codeRaw = (string)($_POST['code'] ?? '');
$norm = strtoupper(preg_replace('~[^A-Z0-9]~', '', $codeRaw)); // case-insensitive, hyphen-insensitive
if ($norm === '') { echo json_encode(['ok'=>false,'error'=>'EMPTY']); exit; }

try {
  $pdo->beginTransaction();

  // 1) Lock user row (avoid race)
  $meQ = $pdo->prepare("SELECT id, username, telegram_id, status, credits, expiry_date FROM users WHERE id=:id LIMIT 1 FOR UPDATE");
  $meQ->execute([':id'=>$uid]);
  $me = $meQ->fetch(PDO::FETCH_ASSOC);
  if (!$me) { $pdo->rollBack(); echo json_encode(['ok'=>false,'error'=>'UNAUTH']); exit; }
  $uname = (string)($me['username'] ?: ('tg_'.$me['id']));
  $tgId  = (string)($me['telegram_id'] ?? '');

  // 2) Daily limit window
  $start = (new DateTime('today'))->format('Y-m-d 00:00:00');
  $end   = (new DateTime('tomorrow'))->format('Y-m-d 00:00:00');

  // 3) Count today's redeems by this user
  $cntQ = $pdo->prepare("
    SELECT COUNT(*) FROM redeemcodes
    WHERE isRedeemed=1
      AND (redeemed_by=:uname OR redeemed_by=:uid)
      AND COALESCE(last_redeemed_at, created_at) >= :s
      AND COALESCE(last_redeemed_at, created_at) <  :e
  ");
  $cntQ->execute([':uname'=>$uname, ':uid'=>(string)$uid, ':s'=>$start, ':e'=>$end]);
  $todayCount = (int)$cntQ->fetchColumn();
  if ($todayCount >= $DAILY_LIMIT) {
    $pdo->rollBack();
    echo json_encode(['ok'=>false,'error'=>'DAILY_LIMIT','limit'=>$DAILY_LIMIT]); // ❌ আজ limit ছাড়িয়েছে
    exit;
  }

  // 4) Lock redeem row
  $stmt = $pdo->prepare("SELECT id, code, status, credits, expiry_date, isRedeemed, redeemed_by
                         FROM redeemcodes
                         WHERE UPPER(REPLACE(code,'-','')) = :c
                         LIMIT 1
                         FOR UPDATE");
  $stmt->execute([':c'=>$norm]);
  $rc = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$rc) { $pdo->rollBack(); echo json_encode(['ok'=>false,'error'=>'NOT_FOUND']); exit; }

  // 5) Expired?
  $expired = !empty($rc['expiry_date']) && (strtotime((string)$rc['expiry_date']) < time());
  if ($expired) { $pdo->rollBack(); echo json_encode(['ok'=>false,'error'=>'EXPIRED']); exit; }

  // 6) Already used?
  if ((int)$rc['isRedeemed'] === 1) {
    if ($rc['redeemed_by'] === $uname || $rc['redeemed_by'] === (string)$uid) {
      $pdo->rollBack(); echo json_encode(['ok'=>false,'error'=>'ALREADY_YOU']); exit;
    }
    $pdo->rollBack(); echo json_encode(['ok'=>false,'error'=>'ALREADY_USED']); exit;
  }

  // 7) Compute new user state
  $addCredits = (int)$rc['credits'];
  $curStatus  = (string)$me['status'];
  $curExpiry  = (string)($me['expiry_date'] ?? '');
  $newStatus  = $curStatus;
  $newExpiry  = $curExpiry;

  if (strtolower((string)$rc['status']) === 'premium') {
    $newStatus = 'premium';
    $codeExp = (string)$rc['expiry_date'];
    if ($codeExp !== '' && (empty($curExpiry) || strtotime($curExpiry) < strtotime($codeExp))) {
      $newExpiry = $codeExp;
    }
  }

  // 8) Apply to user
  $upd = $pdo->prepare("UPDATE users
                        SET credits = credits + :c,
                            status  = :s,
                            expiry_date = :e
                        WHERE id = :id");
  $upd->execute([':c'=>$addCredits, ':s'=>$newStatus, ':e'=>$newExpiry, ':id'=>$uid]);

  // 9) Mark code redeemed
  $mark = $pdo->prepare("UPDATE redeemcodes
                         SET isRedeemed = 1, redeemed_by = :rb, last_redeemed_at = NOW()
                         WHERE id = :id AND isRedeemed = 0");
  $mark->execute([':rb'=>$uname, ':id'=>$rc['id']]);

  $pdo->commit();

  /* ---------- Telegram notifications (best-effort) ---------- */
  $when = (new DateTime('now'))->format('d-m-Y H:i');
  $statusLabel = strtoupper((string)$rc['status'])==='PREMIUM' ? 'PREMIUM' : 'FREE';
  $codeDM = $rc['code'];
  $codePub = mask_redeem_code($rc['code']);
  $exp = $rc['expiry_date'] ?: '—';

  // DM to user (if telegram_id exists)
  if ($BOT_TOKEN !== '' && $tgId !== '') {
    $dm = "Redeem Successful ✅
━ ━ ━ ━ ━ ━ ━ ━ ━ ━
User ➜ {$uname}
Telegram ID ➜ {$tgId}
Code ➜ {$codeDM}
Status ➜ {$statusLabel}
Credits Added ➜ {$addCredits}
New Status ➜ {$newStatus}
New Expiry ➜ ".($newExpiry ?: '—')."
Redeemed At ➜ {$when}
━ ━ ━ ━ ━ ━ ━ ━ ━ ━
Thanks for using CyborX.";
    tg_send($BOT_TOKEN, $tgId, $dm, 'HTML');
  }

  // Announcement (masked)
  if ($BOT_TOKEN !== '' && $ANNOUNCE_CHAT !== '') {
    $pubUser = mask_public_name($uname);
    $ann = "🎟️ <b>Code Redeemed</b>
<b>User</b> ➜ {$pubUser}
<b>Code</b> ➜ <code>{$codePub}</code>
<b>Status</b> ➜ <b>{$statusLabel}</b>
<b>+Credits</b> ➜ <b>{$addCredits}</b>";
    tg_send($BOT_TOKEN, $ANNOUNCE_CHAT, $ann, 'HTML');
  }

  echo json_encode(['ok'=>true,'data'=>[
    'code'=>$rc['code'],
    'credits_added'=>$addCredits,
    'new_status'=>$newStatus,
    'new_expiry'=>$newExpiry
  ]]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  logerr('REDEEM ERROR: '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'SERVER']);
}
