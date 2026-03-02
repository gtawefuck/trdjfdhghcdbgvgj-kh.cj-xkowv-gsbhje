<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
  'ok'=>true,
  'items'=>[] // later: pull from DB
]);
