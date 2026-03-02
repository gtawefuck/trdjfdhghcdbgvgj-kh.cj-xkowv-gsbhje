<?php
/**
 * Bootstrap: env loader (no putenv) + hardened session
 * PHP 8.1+
 */
declare(strict_types=1);

// ---------- timezone ----------
@date_default_timezone_set('UTC');

// ---------- tiny .env loader (NO putenv) ----------
$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $raw) {
        $t = trim($raw);
        if ($t === '' || $t[0] === '#') continue;
        if (!str_contains($t, '=')) continue;

        [$k, $v] = explode('=', $t, 2);
        $k = trim($k);
        // strip wrapping quotes & whitespace
        $v = trim($v);
        if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
            $q = $v[0];
            if (str_ends_with($v, $q)) $v = substr($v, 1, -1);
        }
        // Prefer $_ENV/$_SERVER to share within app without OS env
        $_ENV[$k]    = $v;
        $_SERVER[$k] = $v;
        // DO NOT call putenv(): it may be disabled on this host
    }
}

// ---------- derive host / https ----------
$host = $_SERVER['HTTP_HOST'] ?? ($_ENV['APP_HOST'] ?? 'cyborx.net');
$root = preg_replace('/^www\./i', '', $host);
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
);

// ---------- session knobs (env overrides) ----------
$SESSION_NAME     = $_ENV['SESSION_NAME']            ?? 'CYBORXSESSID';
$COOKIE_DOMAIN    = $_ENV['SESSION_COOKIE_DOMAIN']   ?? ('.' . $root);
$COOKIE_LIFETIME  = (int)($_ENV['SESSION_COOKIE_LIFETIME'] ?? 7200);
$GC_MAXLIFETIME   = (int)($_ENV['SESSION_GC_MAXLIFETIME']  ?? 7200);
$SAMESITE         = $_ENV['SESSION_SAMESITE']        ?? 'Lax';   // OAuth redirect → Lax OK
$IDLE_MAX         = (int)($_ENV['SESSION_IDLE_MAX']  ?? 7200);

// ---------- dedicated session path ----------
$customSessPath = __DIR__ . '/../_sessions';
if (!is_dir($customSessPath)) {
    @mkdir($customSessPath, 0700, true);
}

// ---------- INI + cookie params ----------
ini_set('session.save_path', $customSessPath);
ini_set('session.gc_maxlifetime', (string)$GC_MAXLIFETIME);
ini_set('session.cookie_lifetime', (string)$COOKIE_LIFETIME);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.sid_length', '48');
ini_set('session.sid_bits_per_character', '6');

session_name($SESSION_NAME);
session_set_cookie_params([
    'lifetime' => $COOKIE_LIFETIME,
    'path'     => '/',
    'domain'   => $COOKIE_DOMAIN,   // .cyborx.net
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => $SAMESITE,        // Lax | Strict | None(HTTPS)
]);

// ---------- start session ----------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


