<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

const COOKIE_NAME = 'eskristal_token';

function _bersihkan_sesi_kedaluwarsa(): void {
    db()->exec("DELETE FROM admin_sessions WHERE expires_at < NOW()");
}

function require_login(): array {
    _bersihkan_sesi_kedaluwarsa();

    $token = $_COOKIE[COOKIE_NAME] ?? '';
    if ($token === '') {
        header('Location: /admin/login.php');
        exit;
    }

    $stmt = db()->prepare(
        "SELECT s.id AS session_id, u.id, u.username, u.nama, u.aktif
         FROM admin_sessions s
         JOIN admin_users u ON u.id = s.admin_id
         WHERE s.token = ? AND s.expires_at > NOW()"
    );
    $stmt->execute([$token]);
    $admin = $stmt->fetch();

    if (!$admin || !$admin['aktif']) {
        setcookie(COOKIE_NAME, '', time() - 3600, '/', '', false, true);
        header('Location: /admin/login.php');
        exit;
    }

    // Perpanjang sesi
    $stmt = db()->prepare(
        "UPDATE admin_sessions SET expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?"
    );
    $stmt->execute([SESSION_LIFETIME, $admin['session_id']]);

    return $admin;
}

function login_user(string $username, string $password): bool {
    $stmt = db()->prepare(
        "SELECT id, password_hash, nama, aktif FROM admin_users WHERE username = ?"
    );
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch();

    if (!$user || !$user['aktif']) return false;

    // Rate limit: blokir setelah 5 gagal dalam 5 menit
    $stmt_rl = db()->prepare(
        "SELECT COUNT(*) FROM activity_log
         WHERE aktor = ? AND aksi = 'login_gagal'
           AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
    );
    $stmt_rl->execute([trim($username)]);
    if ((int)$stmt_rl->fetchColumn() >= 5) {
        log_activity(trim($username), 'login_blokir', 'Terlalu banyak percobaan gagal');
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) return false;

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = db()->prepare(
        "INSERT INTO admin_sessions (admin_id, token, ip, user_agent, expires_at)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$user['id'], $token, $ip, $ua, $expires]);

    setcookie(COOKIE_NAME, $token, [
        'expires'  => time() + SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    log_activity($username, 'login', 'Login berhasil dari ' . $ip);
    return true;
}

function logout_user(): void {
    $token = $_COOKIE[COOKIE_NAME] ?? '';
    if ($token !== '') {
        $stmt = db()->prepare("DELETE FROM admin_sessions WHERE token = ?");
        $stmt->execute([$token]);
    }
    setcookie(COOKIE_NAME, '', time() - 3600, '/', '', false, true);
}
