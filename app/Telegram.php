<?php
namespace App;

use PDO;

final class Telegram
{
    /** Verify Telegram login (signature + freshness) */
    public static function verify(array $params, string $botToken, int $maxAge = 900): bool
    {
        try {
            if (!isset($params['hash'], $params['auth_date'])) return false;
            if (abs(time() - (int)$params['auth_date']) > $maxAge) return false;

            $checkHash = $params['hash'];
            unset($params['hash']);

            $data = [];
            foreach ($params as $k => $v) $data[] = $k . '=' . $v;
            sort($data);

            $secret = hash('sha256', $botToken, true);
            $calc   = hash_hmac('sha256', implode("\n", $data), $secret);
            return hash_equals($calc, $checkHash);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Parse comma-separated numeric Telegram IDs */
    public static function parseAllowlist(string $csv): array
    {
        try {
            return array_filter(array_map('trim', explode(',', $csv)), 'ctype_digit');
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Upsert user into your `users` table.
     * @return array{ok:bool, created?:bool, id?:int, status?:string}
     */
    public static function saveUser(PDO $pdo, string $id, string $username, string $firstName = '', string $lastName = '', string $photo = ''): array
    {
        try {
            $stmt = $pdo->prepare("SELECT id, status FROM users WHERE telegram_id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();

            if ($row) {
                $sql = "UPDATE users
                        SET username = :u, first_name = :f, last_name = :l,
                            profile_picture = :p, last_login = NOW(),
                            online_status = 'online', last_activity = NOW()
                        WHERE telegram_id = :id";
                $ok = $pdo->prepare($sql)->execute([
                    'u' => $username ?: null,
                    'f' => $firstName ?: null,
                    'l' => $lastName ?: null,
                    'p' => $photo ?: null,
                    'id'=> $id
                ]);
                return ['ok'=>$ok, 'created'=>false, 'id'=>(int)$row['id'], 'status'=>(string)$row['status']];
            } else {
                $sql = "INSERT INTO users
                        (telegram_id, username, first_name, last_name, profile_picture,
                         status, theme_preference, online_status, last_login, last_activity)
                        VALUES (:id, :u, :f, :l, :p, 'free', 'dark', 'online', NOW(), NOW())";
                $ok = $pdo->prepare($sql)->execute([
                    'id'=> $id,
                    'u' => $username ?: null,
                    'f' => $firstName ?: null,
                    'l' => $lastName ?: null,
                    'p' => $photo ?: null,
                ]);
                if (!$ok) return ['ok'=>false];
                $uid = (int)$pdo->lastInsertId();
                return ['ok'=>true, 'created'=>true, 'id'=>$uid, 'status'=>'free'];
            }
        } catch (\Throwable $e) {
            return ['ok'=>false];
        }
    }

    /** Role label for announcements */
    public static function roleLabel(string $status): string
    {
        $status = strtolower($status);
        return match ($status) {
            'admin'   => 'ADMIN 👑',
            'premium' => 'PREMIUM ⭐',
            'banned'  => 'BANNED',
            default   => 'FREE',
        };
    }

    /**
     * Send a message to a Telegram chat (group/channel)
     * Silently fails (no exception).
     */
    public static function sendMessage(string $botToken, string $chatId, string $text, string $parseMode = 'HTML'): void
    {
        if ($botToken === '' || $chatId === '' || $text === '') return;

        $url  = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $post = [
            'chat_id' => $chatId,
            'text'    => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true,
        ];

        // Prefer cURL
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 4,
            ]);
            @curl_exec($ch);
            @curl_close($ch);
            return;
        }

        // Fallback: stream context
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($post),
                'timeout' => 4,
            ],
        ]);
        @file_get_contents($url, false, $ctx);
    }
}
