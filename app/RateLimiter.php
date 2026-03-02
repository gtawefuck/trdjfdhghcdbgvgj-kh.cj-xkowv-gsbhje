<?php
declare(strict_types=1);

namespace App;

final class RateLimiter
{
    public function __construct(
        private string $path,
        private int $window,
        private int $max
    ) {
        if (!is_dir($this->path)) { @mkdir($this->path, 0700, true); }
    }

    private function key(string $ip, string $user): string
    {
        $slug = preg_replace('~[^a-z0-9\-_.]~i', '_', strtolower($ip.'__'.$user));
        return rtrim($this->path, '/') . '/' . $slug . '.json';
    }

    public function hit(string $ip, string $user): array
    {
        $file = $this->key($ip, $user);
        $now  = time();
        $data = ['start' => $now, 'count' => 0];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            if ($raw) { $data = json_decode($raw, true) ?: $data; }
        }
        if (($now - (int)$data['start']) > $this->window) {
            $data = ['start' => $now, 'count' => 0];
        }
        $data['count']++;
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        $remaining = max(0, $this->max - (int)$data['count']);
        return ['blocked' => $data['count'] > $this->max, 'remaining' => $remaining, 'reset_in' => max(0, $this->window - ($now - (int)$data['start']))];
    }
}