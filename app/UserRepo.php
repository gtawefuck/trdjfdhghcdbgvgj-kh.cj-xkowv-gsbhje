<?php
declare(strict_types=1);

namespace App;

use PDO;

final class UserRepo
{
    public function __construct(private PDO $pdo) {}

    public function findByLogin(string $login): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, email, password_hash, otp_secret, is_active FROM users WHERE username = :u OR email = :u LIMIT 1');
        $stmt->execute([':u' => $login]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}