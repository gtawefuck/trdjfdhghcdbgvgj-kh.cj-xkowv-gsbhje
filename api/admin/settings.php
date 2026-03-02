<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Bootstrap.php';
require_once __DIR__ . '/../../app/Db.php';

header('Content-Type: application/json');

if (empty($_SESSION['uid'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }
$pdo = \App\Db::pdo();
$me  = (int)$_SESSION['uid'];
$st  = $pdo->prepare("SELECT status FROM users WHERE id=:id LIMIT 1");
$st->execute([':id'=>$me]);
if (($st->fetchColumn() ?? '') !== 'admin') { http_response_code(403); echo json_encode(['ok'=>false]); exit; }

/**
 * Simple key-value settings table:
 * CREATE TABLE settings ( `key` varchar(64) PRIMARY KEY, `val` text, `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP );
 */
function setKV(PDO $pdo, string $k, string $v){ $s=$pdo->prepare("INSERT INTO settings(`key`,`val`) VALUES(:k,:v) ON DUPLICATE KEY UPDATE val=VALUES(val)"); $s->execute([':k'=>$k,':v'=>$v]); }
function getKV(PDO $pdo, string $k, $def=''){ $s=$pdo->prepare("SELECT val FROM settings WHERE `key`=:k"); $s->execute([':k'=>$k]); $v=$s->fetchColumn(); return $v!==false?$v:$def; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  setKV($pdo,'brand',          (string)($_POST['brand'] ?? 'CyborX'));
  setKV($pdo,'announce_chat',  (string)($_POST['announce_chat'] ?? ''));
  setKV($pdo,'require_allow',  (string)($_POST['require_allow'] ?? 'false'));
  setKV($pdo,'xcoin_rate',     (string)($_POST['xcoin_rate'] ?? '1'));
  echo json_encode(['ok'=>true]); exit;
}

echo json_encode(['ok'=>true,'data'=>[
  'brand'         => getKV($pdo,'brand','CyborX'),
  'announce_chat' => getKV($pdo,'announce_chat',''),
  'require_allow' => getKV($pdo,'require_allow','false'),
  'xcoin_rate'    => getKV($pdo,'xcoin_rate','1'),
]]);
