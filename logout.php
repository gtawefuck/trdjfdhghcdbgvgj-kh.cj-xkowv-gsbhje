<?php
declare(strict_types=1);
require_once __DIR__ . '/app/Bootstrap.php';
require_once __DIR__ . '/app/Db.php';

$secure =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

if (!empty($_SESSION['uid'])) {
    try {
        $pdo = \App\Db::pdo();
        $stmt = $pdo->prepare("UPDATE users SET online_status='offline', last_activity=NOW() WHERE id=:id");
        $stmt->execute([':id' => (int)$_SESSION['uid']]);
    } catch (\Throwable $e) {}
}

$sid = session_name();
session_unset();
session_destroy();
setcookie($sid, '', time()-3600, '/', '', $secure, true);

$to = (isset($_GET['timeout']) ? '/?expired=1' : '/');
header('Location: ' . $to);
exit;
