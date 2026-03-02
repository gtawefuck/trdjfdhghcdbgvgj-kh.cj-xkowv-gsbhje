<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Bootstrap.php';
require_once __DIR__ . '/../app/Db.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = \App\Db::pdo();
$scope = isset($_GET['scope']) ? $_GET['scope'] : 'online';
$limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 100;

try{
  if ($scope === 'top') {
    $rows = $pdo->query("SELECT username, first_name, last_name, profile_picture, status,
                                (lives+charges) AS hits,
                                (online_status='online' AND last_activity >= (NOW() - INTERVAL 5 MINUTE)) AS is_online
                         FROM users ORDER BY hits DESC LIMIT ".$limit)->fetchAll(PDO::FETCH_ASSOC);
    $users = array_map(function($r){
      return [
        'username'  => $r['username'],
        'full_name' => trim(($r['first_name']??'').' '.($r['last_name']??'')),
        'profile'   => $r['profile_picture'],
        'status'    => $r['status'],
        'hits'      => (int)$r['hits'],
        'is_online' => (bool)$r['is_online'],
      ];
    }, $rows);
    echo json_encode(['ok'=>true,'users'=>$users]); exit;
  }

  // default: online
  $rows = $pdo->query("SELECT username, first_name, last_name, profile_picture, status
                       FROM users
                       WHERE online_status='online' AND last_activity >= (NOW() - INTERVAL 5 MINUTE)
                       ORDER BY last_activity DESC LIMIT ".$limit)->fetchAll(PDO::FETCH_ASSOC);
  $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE online_status='online' AND last_activity >= (NOW() - INTERVAL 5 MINUTE)")->fetchColumn();
  $users = array_map(function($r){
    return [
      'username'  => $r['username'],
      'full_name' => trim(($r['first_name']??'').' '.($r['last_name']??'')),
      'profile'   => $r['profile_picture'],
      'status'    => $r['status'],
      'is_online' => true,
    ];
  }, $rows);

  echo json_encode(['ok'=>true,'count'=>$count,'users'=>$users]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'SERVER']);
}
