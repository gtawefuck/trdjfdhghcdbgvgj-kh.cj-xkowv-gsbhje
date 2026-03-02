<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Bootstrap.php';
require_once __DIR__ . '/../../app/Db.php';
use App\Db;

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['uid'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Auth required']); exit; }

$pdo = Db::pdo();
/* admin only */
$me = $pdo->prepare("SELECT status FROM users WHERE id=? LIMIT 1");
$me->execute([(int)$_SESSION['uid']]);
if (!($row=$me->fetch(PDO::FETCH_ASSOC)) || strtolower((string)$row['status'])!=='admin') {
  http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Admin only']); exit;
}

/* inputs */
$q        = trim((string)($_GET['q'] ?? ''));
$limit    = max(1, min(50, (int)($_GET['limit'] ?? 50)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $limit;
$onlyRed  = isset($_GET['only_redeemed']) && $_GET['only_redeemed']=='1';

/*
  redeemcodes schema (expected):
  id, code, status(FREE|PREMIUM), credits, expiry_date(date|null),
  isRedeemed(0/1), redeemed_by(user_id|null), created_at(timestamp)
*/
$where = [];
$params = [];

/* search: code / username / telegram_id / numeric user id */
if ($q !== '') {
  $where[] = "(r.code LIKE :q OR u.username LIKE :q OR u.telegram_id LIKE :q OR r.redeemed_by = :uid)";
  $params[':q'] = '%'.$q.'%';
  $params[':uid'] = ctype_digit($q) ? (int)$q : -1;
}
if ($onlyRed) $where[] = "r.isRedeemed = 1";

$W = $where ? ("WHERE ".implode(" AND ", $where)) : "";

/* total count */
$sqlCnt = "SELECT COUNT(*) FROM redeemcodes r LEFT JOIN users u ON u.id=r.redeemed_by {$W}";
$st = $pdo->prepare($sqlCnt);
foreach ($params as $k=>$v) $st->bindValue($k,$v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
$st->execute();
$total = (int)$st->fetchColumn();

/* page data */
$sql = "SELECT r.id,r.code,r.status,r.credits,r.expiry_date,r.isRedeemed,r.redeemed_by,r.created_at,
               u.username, u.telegram_id
        FROM redeemcodes r
        LEFT JOIN users u ON u.id=r.redeemed_by
        {$W}
        ORDER BY r.id DESC
        LIMIT :lim OFFSET :off";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) $st->bindValue($k,$v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
$st->bindValue(':lim',$limit,PDO::PARAM_INT);
$st->bindValue(':off',$offset,PDO::PARAM_INT);
$st->execute();
$items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo json_encode([
  'ok'    => true,
  'items' => $items,
  'total' => $total,
  'page'  => $page,
  'pages' => (int)ceil($total / $limit),
  'limit' => $limit
]);
