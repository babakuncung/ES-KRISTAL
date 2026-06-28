<?php
// $page_title harus di-set sebelum include file ini
$page_title = $page_title ?? 'Admin';
$active_page = $active_page ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> — ESKRISTAL</title>
<link rel="stylesheet" href="/public/css/style.css">
<?php if (isset($with_chart) && $with_chart): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<?php endif; ?>
</head>
<body>

<div class="topbar">
  <span class="brand">ES KRISTAL</span>
  <button class="menu-btn" onclick="toggleSidebar()" aria-label="Menu">&#9776;</button>
</div>

<nav class="sidebar" id="sidebar">
  <div class="nav-label">Menu</div>
  <a href="/admin/index.php"      class="<?= $active_page === 'dashboard'   ? 'active' : '' ?>">Dashboard</a>
  <a href="/admin/input.php"      class="<?= $active_page === 'input'       ? 'active' : '' ?>">Input Stok</a>
  <a href="/admin/riwayat.php"    class="<?= $active_page === 'riwayat'     ? 'active' : '' ?>">Riwayat</a>
  <a href="/admin/laporan.php"    class="<?= $active_page === 'laporan'     ? 'active' : '' ?>">Laporan</a>
  <div class="nav-label">Kelola</div>
  <a href="/admin/pekerja.php"    class="<?= $active_page === 'pekerja'     ? 'active' : '' ?>">Pekerja</a>
  <a href="/admin/pengaturan.php" class="<?= $active_page === 'pengaturan'  ? 'active' : '' ?>">Pengaturan</a>
  <div class="nav-label">Akun</div>
  <a href="/admin/logout.php">Keluar</a>
</nav>
<div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>

<main class="main">
<h2 class="page-title"><?= htmlspecialchars($page_title) ?></h2>
