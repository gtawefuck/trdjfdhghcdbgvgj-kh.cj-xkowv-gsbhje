<?php
declare(strict_types=1);

namespace App;

final class Security
{
    public static function safeRedirect(string $url, string $fallback = '/dashboard.php'): void
    {
        if (preg_match('~^/[^\\r\\n]*$~', $url) !== 1) { $url = $fallback; }
        header('Location: ' . $url, true, 302);
        exit;
    }

    public static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) return trim(explode(',', (string)$_SERVER[$k])[0]);
        }
        return '0.0.0.0';
    }
}
