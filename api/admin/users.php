<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Bootstrap.php';
require_once __DIR__ . '/../../app/Db.php';

header('Content-Type: application/json');

if (empty($_SESSION['uid'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }

$pdo = \App\Db::pdo();
$st  = $pdo->prepare("SELECT status FROM users WHERE id=:id LIMIT 1");
$st->execute([':id'=>(int)$_SESSION['uid']]);
if (($st->fetchColumn() ?? '') !== 'admin') { http_response_code(403); echo json_encode(['ok'=>false]); exit; }

$q     = trim((string)($_GET['q'] ?? ''));
$limit = max(1, min(50, (int)($_GET['limit'] ?? 50))); // <= 50
$page  = max(1, (int)($_GET['page'] ?? 1));
$off   = ($page - 1) * $limit;

$where = '';
$bind  = [];

if ($q !== '') {
  // search by username, first/last, telegram_id, or exact numeric id
  $where = "WHERE (username LIKE :p1 OR first_name LIKE :p2 OR last_name LIKE :p3 OR telegram_id LIKE :p4 OR id = :id)";
  $bind[':p1'] = '%'.$q.'%';
  $bind[':p2'] = '%'.$q.'%';
  $bind[':p3'] = '%'.$q.'%';
  $bind[':p4'] = '%'.$q.'%';
  $bind[':id'] = ctype_digit($q) ? (int)$q : -1;
}

/* total count */
$sqlCnt = "SELECT COUNT(*) FROM users {$where}";
$cnt = $pdo->prepare($sqlCnt);
foreach ($bind as $k => $v) { $cnt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
$cnt->execute();
$total = (int)$cnt->fetchColumn();

/* data page */
$sql = "SELECT id, telegram_id, username, first_name, last_name, profile_picture,
               status, credits, xcoin, kcoin, lives, charges, hits, expiry_date, last_activity, plan_name
        FROM users
        {$where}
        ORDER BY id DESC
        LIMIT :lim OFFSET :off";

$st = $pdo->prepare($sql);
foreach ($bind as $k => $v) { $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
$st->bindValue(':lim', $limit, PDO::PARAM_INT);
$st->bindValue(':off', $off,   PDO::PARAM_INT);
$st->execute();

$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$users = array_map(function($r){
  $name = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
  return [
    'id'           => (int)$r['id'],
    'telegram_id'  => (string)$r['telegram_id'],
    'username'     => $r['username'],
    'name'         => $name ?: ($r['username'] ?: 'user'),
    'avatar'       => $r['profile_picture'],
    'status'       => $r['status'],
    'credits'      => (int)$r['credits'],
    'xcoin'        => (int)$r['xcoin'],                    // ✅ এখন সরাসরি xcoin
    'kcoin'        => (int)$r['kcoin'],                    // চাইলে UI-তে দেখাবেন
    'hits'         => isset($r['hits']) ? (int)$r['hits']  // virtual column থাকলে সেটাই
                                        : (int)($r['lives'] + $r['charges']),
    'expiry'       => $r['expiry_date'],
    'plan'         => $r['plan_name'] ?? null,
    'last_activity'=> $r['last_activity'],
  ];
}, $rows);

echo json_encode([
  'ok'    => true,
  'users' => $users,
  'total' => $total,
  'page'  => $page,
  'pages' => (int)ceil($total / $limit),
  'limit' => $limit
]);
