<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Bootstrap.php';
require_once __DIR__ . '/../../app/Db.php';

use App\Db;

header('Content-Type: application/json; charset=utf-8');

/* health check */
if (($_GET['ping'] ?? '') === '1') { echo json_encode(['ok'=>true,'msg'=>'alive']); exit; }

/* auth */
if (empty($_SESSION['uid'])) { echo json_encode(['ok'=>false,'msg'=>'Auth required']); exit; }
$pdo = Db::pdo();

/* admin only */
$st = $pdo->prepare("SELECT status FROM users WHERE id=? LIMIT 1");
$st->execute([(int)$_SESSION['uid']]);
$me = $st->fetch(PDO::FETCH_ASSOC);
if (!$me || strtolower((string)$me['status']) !== 'admin') {
  echo json_encode(['ok'=>false,'msg'=>'Admin only']); exit;
}

/* read input (JSON or form) */
$in = $_POST;
if (!$in && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $j = json_decode((string)$raw, true);
  if (is_array($j)) $in = $j;
}

/* identify target user: by telegram_id OR user_id */
$telegramId = trim((string)($in['telegram_id'] ?? ''));
$userId     = (int)($in['user_id'] ?? 0);

if ($telegramId !== '') {
  $q = $pdo->prepare("SELECT id FROM users WHERE telegram_id=? LIMIT 1");
  $q->execute([$telegramId]);
  $rid = $q->fetchColumn();
  if (!$rid) { echo json_encode(['ok'=>false,'msg'=>'User not found (telegram_id)']); exit; }
  $userId = (int)$rid;
}
if ($userId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Invalid user']); exit; }

/* current row (no 'cash' column anywhere) */
$curQ = $pdo->prepare("SELECT id,telegram_id,username,status,credits,xcoin,kcoin,plan_name,expiry_date FROM users WHERE id=? LIMIT 1");
$curQ->execute([$userId]);
$cur = $curQ->fetch(PDO::FETCH_ASSOC);
if (!$cur) { echo json_encode(['ok'=>false,'msg'=>'User not found']); exit; }

/* allowed values */
$allowedStatus = ['free','premium','admin','banned'];

/* fields to update */
$status       = isset($in['status']) ? strtolower(trim((string)$in['status'])) : null;
if ($status !== null && !in_array($status, $allowedStatus, true)) $status = null;

$deltaCredits = (int)($in['delta_credits'] ?? ($in['adjust_credits'] ?? 0));
$deltaXcoin   = (int)($in['delta_xcoin']   ?? 0);   // XCoin +/- (users.xcoin)
$deltaKcoin   = (int)($in['delta_kcoin']   ?? ($in['adjust_kcoin'] ?? 0)); // Killer credits +/- (users.kcoin)

/* NOTE: your requirement says "Set plan remove" — so no plan apply, no expiry change here */

/* build dynamic UPDATE */
$parts  = [];
$params = [];

if ($status !== null)        { $parts[] = "status=?";                       $params[] = $status; }
if ($deltaCredits !== 0)     { $parts[] = "credits=GREATEST(credits+?,0)";  $params[] = $deltaCredits; }
if ($deltaXcoin   !== 0)     { $parts[] = "xcoin=GREATEST(xcoin+?,0)";      $params[] = $deltaXcoin; }
if ($deltaKcoin   !== 0)     { $parts[] = "kcoin=GREATEST(kcoin+?,0)";      $params[] = $deltaKcoin; }

if (!$parts) { echo json_encode(['ok'=>true,'msg'=>'No change']); exit; }

$sql = "UPDATE users SET ".implode(', ',$parts).", last_activity=NOW() WHERE id=?";
$params[] = $userId;

try {
  $pdo->beginTransaction();
  $u = $pdo->prepare($sql);
  $u->execute($params);
  $pdo->commit();

  // return fresh data
  $re = $pdo->prepare("SELECT id,telegram_id,username,status,credits,xcoin,kcoin,plan_name,expiry_date FROM users WHERE id=? LIMIT 1");
  $re->execute([$userId]);
  $row = $re->fetch(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'msg'=>'Updated','user'=>$row]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok'=>false,'msg'=>'DB error: '.$e->getMessage()]);
}
