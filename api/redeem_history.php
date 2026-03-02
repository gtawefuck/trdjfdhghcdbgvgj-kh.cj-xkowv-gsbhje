<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Bootstrap.php';
require_once __DIR__ . '/../app/Db.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['uid'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'UNAUTH']); exit;
}

$pdo = \App\Db::pdo();
$uid = (int)$_SESSION['uid'];

$st = $pdo->prepare("SELECT id, username FROM users WHERE id=:id LIMIT 1");
$st->execute([':id'=>$uid]);
$me = $st->fetch(PDO::FETCH_ASSOC);
if (!$me) { echo json_encode(['ok'=>false]); exit; }

$uname = (string)($me['username'] ?: ('tg_'.$me['id']));
$limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;

$sql = "SELECT id, code, status, credits, expiry_date, isRedeemed, redeemed_by, created_at
        FROM redeemcodes
        WHERE isRedeemed=1 AND (redeemed_by=:uname OR redeemed_by=:uid)
        ORDER BY id DESC
        LIMIT :lim";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':uname', $uname, PDO::PARAM_STR);
$stmt->bindValue(':uid', (string)$uid, PDO::PARAM_STR);
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($rows as &$r) { $r['isRedeemed'] = ((int)$r['isRedeemed'] === 1); }

echo json_encode(['ok'=>true, 'items'=>$rows]);
