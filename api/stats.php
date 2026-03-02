<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Bootstrap.php';
require_once __DIR__ . '/../app/Db.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = \App\Db::pdo();
  $row = $pdo->query("
    SELECT
      (SELECT COUNT(*) FROM users) AS total_users,
      (SELECT COALESCE(SUM(lives),0) FROM users)   AS live_cards,
      (SELECT COALESCE(SUM(charges),0) FROM users) AS charge_cards
  ")->fetch(PDO::FETCH_ASSOC);

  $online_now = (int)$pdo->query("
    SELECT COUNT(*) FROM users
    WHERE online_status='online' AND last_activity >= (NOW() - INTERVAL 5 MINUTE)
  ")->fetchColumn();

  $live  = (int)$row['live_cards'];
  $chg   = (int)$row['charge_cards'];
  $hits  = $live + $chg;

  echo json_encode([
    'ok' => true,
    'data' => [
      'total_users' => (int)$row['total_users'],
      'live_cards'  => $live,
      'charge_cards'=> $chg,
      'total_hits'  => $hits,
      'online_now'  => $online_now,
    ],
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'SERVER','msg'=>$e->getMessage()]);
}
