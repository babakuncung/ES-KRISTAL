<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

// Jika sudah login, langsung ke dashboard
if (!empty($_COOKIE[COOKIE_NAME])) {
    $stmt = db()->prepare(
        "SELECT 1 FROM admin_sessions WHERE token = ? AND expires_at > NOW() LIMIT 1"
    );
    $stmt->execute([$_COOKIE[COOKIE_NAME]]);
    if ($stmt->fetchColumn()) {
        header('Location: /admin/index.php');
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } elseif (!login_user($username, $password)) {
        $error = 'Username atau password salah.';
        log_activity($username, 'login_gagal', 'Percobaan login gagal');
    } else {
        header('Location: /admin/index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — ESKRISTAL</title>
<link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <h1>ES KRISTAL</h1>
    <p class="sub">Panel Admin — <?= htmlspecialchars(get_setting('nama_usaha')) ?></p>

    <?php if ($error !== ''): ?>
      <div class="alert alert-err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               autocomplete="username" required>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">Masuk</button>
    </form>
  </div>
</div>
</body>
</html>
