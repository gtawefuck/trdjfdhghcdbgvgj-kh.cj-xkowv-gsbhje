<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Bootstrap.php';
require_once __DIR__ . '/../app/Db.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['uid'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'UNAUTH']); exit;
}

$uid = (int)$_SESSION['uid'];
$pdo = \App\Db::pdo();
$stmt = $pdo->prepare("SELECT credits, kcoin, lives, charges FROM users WHERE id=:id LIMIT 1");
$stmt->execute([':id'=>$uid]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) { echo json_encode(['ok'=>false,'error'=>'NOT_FOUND']); exit; }

$credits = (int)$u['credits'];
$cash    = (int)$u['kcoin'];
$lives   = (int)$u['lives'];
$charges = (int)$u['charges'];
$hits    = $lives + $charges;

echo json_encode([
  'ok'=>true,
  'data'=>[
    'credits'=>$credits,
    'cash'=>$cash,
    'lives'=>$lives,
    'charges'=>$charges,
    'hits'=>$hits,
    'success' => $hits ? round(($lives/max(1,$hits))*100) : 0
  ]
]);
