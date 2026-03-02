<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Bootstrap.php';
require_once __DIR__ . '/../../app/Db.php';
require_once __DIR__ . '/../../app/Plan.php';

use App\Plan;
use App\Db;

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['uid'])) { echo json_encode(['ok'=>false,'msg'=>'Auth']); exit; }

// কেবল admin
$pdo = Db::pdo();
$me = $pdo->prepare("SELECT status FROM users WHERE id=:id LIMIT 1");
$me->execute([':id'=>(int)$_SESSION['uid']]);
$meRow = $me->fetch();
if (!$meRow || strtolower((string)$meRow['status'])!=='admin') {
    echo json_encode(['ok'=>false,'msg'=>'Admin only']); exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
$level  = (int)($_POST['level'] ?? 0);
if ($userId<=0 || $level<1 || $level>6) {
    echo json_encode(['ok'=>false,'msg'=>'Invalid input']); exit;
}

$res = Plan::apply($pdo, $userId, $level);
echo json_encode($res);
