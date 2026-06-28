<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = require_login();

$stok      = stok_sekarang();
$hari_ini  = ringkasan_hari_ini();
$tren7     = tren_stok(7);
$tren30    = tren_stok(30);

$labels7  = array_column($tren7,  'tanggal');
$values7  = array_column($tren7,  'net');
$labels30 = array_column($tren30, 'tanggal');
$values30 = array_column($tren30, 'net');

$page_title  = 'Dashboard';
$active_page = 'dashboard';
$with_chart  = true;
include __DIR__ . '/includes/header.php';
?>

<div class="cards">
  <div class="card blue">
    <div class="label">Stok Sekarang</div>
    <div class="value"><?= number_format($stok) ?> <small style="font-size:.9rem;font-weight:400">balok</small></div>
  </div>
  <div class="card green">
    <div class="label">Masuk Hari Ini</div>
    <div class="value"><?= number_format((int)$hari_ini['masuk']) ?></div>
  </div>
  <div class="card red">
    <div class="label">Keluar Hari Ini</div>
    <div class="value"><?= number_format((int)$hari_ini['keluar']) ?></div>
  </div>
  <div class="card" style="grid-column:1/-1">
    <div class="label">Omzet Hari Ini</div>
    <div class="value" style="color:#7c3aed"><?= format_rupiah((int)$hari_ini['omzet']) ?></div>
  </div>
</div>

<div class="chart-wrap">
  <h3>Transaksi Net — 7 Hari Terakhir</h3>
  <canvas id="chart7" height="120"></canvas>
</div>

<div class="chart-wrap">
  <h3>Transaksi Net — 30 Hari Terakhir</h3>
  <canvas id="chart30"></canvas>
</div>

<script>
const chartData7  = {labels:<?= json_encode($labels7) ?>,  values:<?= json_encode($values7) ?>};
const chartData30 = {labels:<?= json_encode($labels30) ?>, values:<?= json_encode($values30) ?>};
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
