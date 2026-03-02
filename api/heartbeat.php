<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Bootstrap.php';
require_once __DIR__ . '/../app/Db.php';
header('Content-Type: application/json; charset=utf-8');

// সেশন নাই/মেয়াদ শেষ ⇒ 440 (Login Timeout)
if (empty($_SESSION['uid'])) {
    http_response_code(440);
    echo json_encode(['ok' => false, 'reason' => 'session_timeout']);
    exit;
}

// সেশন আছে ⇒ লাস্ট অ্যাক্টিভিটি টাচ করি
try {
    $pdo = \App\Db::pdo();
    $stmt = $pdo->prepare(
        "UPDATE users SET last_activity = NOW(), online_status='online' WHERE id = :id"
    );
    $stmt->execute([':id' => (int) $_SESSION['uid']]);
} catch (Throwable $e) {
    // DB ডাউন হলে হার্টবিট 500 না করে শুধু ok=false দিই (সেশন যেন না ড্রপ হয়)
    echo json_encode(['ok' => false, 'reason' => 'db_update_failed']);
    exit;
}

echo json_encode(['ok' => true]);
