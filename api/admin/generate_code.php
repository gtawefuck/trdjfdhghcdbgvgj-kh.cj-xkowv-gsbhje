<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Bootstrap.php';
require_once __DIR__ . '/../../app/Db.php';

use App\Db;

header('Content-Type: application/json; charset=utf-8');

/* ping */
if (($_GET['ping'] ?? '') === '1') { echo json_encode(['ok'=>true,'msg'=>'alive']); exit; }

/* auth/admin */
if (empty($_SESSION['uid'])) { echo json_encode(['ok'=>false,'msg'=>'Auth required']); exit; }
$pdo = Db::pdo();
$me = $pdo->prepare("SELECT status FROM users WHERE id=? LIMIT 1");
$me->execute([ (int)$_SESSION['uid'] ]);
if (!($row = $me->fetch(PDO::FETCH_ASSOC)) || strtolower((string)$row['status']) !== 'admin') {
  echo json_encode(['ok'=>false,'msg'=>'Admin only']); exit;
}

/* input (JSON or form) */
$in = $_POST;
if (!$in && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
  $raw = file_get_contents('php://input'); $j = json_decode((string)$raw, true); if (is_array($j)) $in = $j;
}

/* --- sanitize inputs --- */
$credits = max(0, (int)($in['credits'] ?? 0));
$count   = min(50, max(1, (int)($in['count'] ?? 1)));

/* status: store as UPPERCASE to match enum in DB (FREE | PREMIUM) */
$allowedStatus = ['FREE','PREMIUM'];
$reqStatus = strtoupper(trim((string)($in['status'] ?? 'FREE')));
$status = in_array($reqStatus, $allowedStatus, true) ? $reqStatus : 'FREE';

/* expiry: either exact date (YYYY-MM-DD) or expiry_days */
$expiryDate = null;
$expStr = trim((string)($in['expiry_date'] ?? ''));
$expDays = (int)($in['expiry_days'] ?? 0);

try {
  if ($expStr !== '') {
    $dt = new DateTime($expStr);
    $expiryDate = $dt->format('Y-m-d');
  } elseif ($expDays > 0) {
    $dt = new DateTime('today');
    $dt->modify("+{$expDays} days")->setTime(23,59,59);
    $expiryDate = $dt->format('Y-m-d');
  }
} catch (Throwable $e) {
  // invalid date string
  echo json_encode(['ok'=>false,'msg'=>'Invalid expiry_date']); exit;
}

/* code generator */
function rand_code(): string {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $s = '';
  for ($i=0;$i<8;$i++) $s .= $alphabet[random_int(0, strlen($alphabet)-1)];
  return $s;
}

$codes = [];
try {
  $pdo->beginTransaction();
  // redeemcodes: code, status(FREE|PREMIUM), credits(int), expiry_date(date|null), isRedeemed(0/1), redeemed_by(user_id|null)
  $ins = $pdo->prepare("INSERT INTO redeemcodes (code,status,credits,expiry_date,isRedeemed,redeemed_by) VALUES (?,?,?,?,0,NULL)");

  for ($i=0; $i<$count; $i++) {
    $codeBody = rand_code().'-'.rand_code();
    // keep status suffix uppercase to match DB enum
    $code = "CYBORX-{$codeBody}-{$status}";
    $ins->execute([$code, $status, $credits, $expiryDate]);
    $codes[] = $code;
  }

  $pdo->commit();
  echo json_encode([
    'ok'          => true,
    'status'      => $status,
    'credits'     => $credits,
    'count'       => $count,
    'expiry_date' => $expiryDate,
    'codes'       => $codes
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok'=>false,'msg'=>'DB error: '.$e->getMessage()]);
}
