<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Bootstrap.php';
require_once __DIR__ . '/../app/Db.php';

use App\Db;

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['uid'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'UNAUTH']);
  exit;
}

$uid = (int)$_SESSION['uid'];
$pdo = Db::pdo();

function out(bool $ok, array $extra = []) {
  echo json_encode(['ok'=>$ok] + $extra);
  exit;
}

// ইউজার ফেচ
$st = $pdo->prepare("SELECT id, status, credits FROM users WHERE id=:id LIMIT 1");
$st->execute([':id'=>$uid]);
$me = $st->fetch(PDO::FETCH_ASSOC);
if (!$me) out(false, ['error'=>'NO_USER']);

if (strtolower((string)$me['status']) === 'banned') {
  out(false, ['error'=>'BANNED','message'=>"You're banned from CyborX"]);
}

$amount = 50; // প্রতিদিন কত ক্রেডিট
$today  = (new DateTime('now'))->format('Y-m-d');
$reset  = (new DateTime('today'))->modify('+1 day');
$nextResetIso = $reset->format('c');

$fn = strtolower((string)($_GET['fn'] ?? ''));

// ---- STATE (GET): আজকে ক্লেইম হয়েছে কি না?
if ($fn === 'state') {
  $q = $pdo->prepare("SELECT 1 FROM user_credit_claims WHERE user_id=:u AND claim_date=:d LIMIT 1");
  $q->execute([':u'=>$uid, ':d'=>$today]);
  $claimed = (bool)$q->fetchColumn();

  out(true, [
    'claimed'    => $claimed,
    'amount'     => $amount,
    'next_reset' => $nextResetIso,
    'credits'    => (int)$me['credits'],
  ]);
}

// ---- CLAIM (POST): ক্লেইম করুন
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  out(false, ['error'=>'BAD_METHOD']);
}

try {
  $pdo->beginTransaction();

  // একই দিনে একবারই ইনসার্ট হবে (UNIQUE index দরকার; নিচে নোট দেখুন)
  $ins = $pdo->prepare("INSERT IGNORE INTO user_credit_claims (user_id, claim_date, amount, created_at)
                        VALUES (:u, :d, :a, NOW())");
  $ins->execute([':u'=>$uid, ':d'=>$today, ':a'=>$amount]);

  if ($ins->rowCount() === 0) {
    // আগেই ক্লেইম করা
    $pdo->rollBack();
    out(false, [
      'error'      => 'ALREADY',
      'message'    => 'Already claimed for today.',
      'next_reset' => $nextResetIso,
      'credits'    => (int)$me['credits'],
      'claimed'    => true,
    ]);
  }

  // ক্রেডিট বাড়ান (updated_at নেই, তাই সেট করা হয়নি)
  $upd = $pdo->prepare("UPDATE users SET credits = credits + :a WHERE id=:u LIMIT 1");
  $upd->execute([':a'=>$amount, ':u'=>$uid]);

  // নতুন ব্যালেন্স
  $nowBal = $pdo->prepare("SELECT credits FROM users WHERE id=:u LIMIT 1");
  $nowBal->execute([':u'=>$uid]);
  $credits = (int)$nowBal->fetchColumn();

  $pdo->commit();

  out(true, [
    'claimed'    => true,
    'amount'     => $amount,
    'credits'    => $credits,
    'next_reset' => $nextResetIso,
    'message'    => '+'.$amount.' credits added',
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  out(false, ['error'=>'SERVER','message'=>$e->getMessage()]);
}
