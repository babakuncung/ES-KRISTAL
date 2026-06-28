<?php
/**
 * Konfigurasi aplikasi ESKRISTAL.
 * Baca variabel dari config/.env — JANGAN hardcode kredensial di sini.
 */

declare(strict_types=1);

// ── Muat .env ────────────────────────────────────────────────
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

// ── Helper ───────────────────────────────────────────────────
function env(string $key, string $default = ''): string {
    return $_ENV[$key] ?? $default;
}

// ── Koneksi Database ─────────────────────────────────────────
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'eskristal'));
define('DB_USER', env('DB_USER', ''));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── WAHA (WhatsApp Gateway) ───────────────────────────────────
define('WAHA_URL',     env('WAHA_URL',     'http://localhost:3000'));
define('WAHA_SESSION', env('WAHA_SESSION', 'default'));
define('WAHA_API_KEY', env('WAHA_API_KEY', ''));

// ── Keamanan ──────────────────────────────────────────────────
define('WA_WEBHOOK_SECRET', env('WA_WEBHOOK_SECRET', ''));
define('SESSION_LIFETIME',  (int) env('SESSION_LIFETIME', '3600')); // detik

// ── Zona Waktu ────────────────────────────────────────────────
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Makassar'));
