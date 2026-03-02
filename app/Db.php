<?php
declare(strict_types=1);

namespace App;

final class Db
{
    public static function pdo(): \PDO
    {
        // .env থেকে নাও
        $dsn  = $_ENV['DB_DSN']  ?? 'mysql:host=127.0.0.1;dbname=app;charset=utf8mb4';
        $user = $_ENV['DB_USER'] ?? 'app_user';
        $pass = $_ENV['DB_PASS'] ?? 'change_me';

        // PDO / pdo_mysql না থাকলে 500 (সাইলেন্ট)
        if (!class_exists(\PDO::class) || (str_starts_with($dsn, 'mysql:') && !in_array('mysql', \PDO::getAvailableDrivers(), true))) {
            // error_log('PDO or pdo_mysql not available');
            http_response_code(500);
            exit;
        }

        try {
            $pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            if (str_starts_with($dsn, 'mysql:')) {
                $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
                $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            return $pdo;
        } catch (\PDOException $e) {
            // error_log('DB connect error: ' . $e->getMessage());
            http_response_code(500);
            exit;
        }
    }
}
