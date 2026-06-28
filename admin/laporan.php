<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = require_login();

$mode    = $_GET['mode']  ?? 'harian';   // harian | bulanan
$bulan   = $_GET['bulan'] ?? date('Y-m');
$tahun   = $_GET['tahun'] ?? date('Y');

// Validasi
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) $bulan = date('Y-m');
if (!preg_match('/^\d{4}$/', $tahun))        $tahun = date('Y');

// ── Data harian ───────────────────────────────────────────────
if ($mode === 'harian') {
    [$y, $m]   = explode('-', $bulan);
    $stmt = db()->prepare(
        "SELECT
            DATE(created_at) AS tanggal,
            COALESCE(SUM(CASE WHEN tipe='masuk'  THEN jumlah ELSE 0 END), 0) AS masuk,
            COALESCE(SUM(CASE WHEN tipe='keluar' THEN jumlah ELSE 0 END), 0) AS keluar,
            COALESCE(SUM(CASE WHEN tipe='koreksi' THEN jumlah ELSE 0 END), 0) AS koreksi,
            COALESCE(SUM(CASE WHEN tipe='keluar' THEN nilai_rupiah ELSE 0 END), 0) AS omzet
         FROM transaksi_stok
         WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?
         GROUP BY DATE(created_at)
         ORDER BY tanggal ASC"
    );
    $stmt->execute([$y, $m]);
    $rows = $stmt->fetchAll();

    $judul    = "Laporan Harian — " . date('F Y', mktime(0, 0, 0, (int)$m, 1, (int)$y));
    $filename = "laporan-harian-$bulan";
} else {
    // ── Data bulanan ─────────────────────────────────────────
    $stmt = db()->prepare(
        "SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS bulan,
            COALESCE(SUM(CASE WHEN tipe='masuk'  THEN jumlah ELSE 0 END), 0) AS masuk,
            COALESCE(SUM(CASE WHEN tipe='keluar' THEN jumlah ELSE 0 END), 0) AS keluar,
            COALESCE(SUM(CASE WHEN tipe='koreksi' THEN jumlah ELSE 0 END), 0) AS koreksi,
            COALESCE(SUM(CASE WHEN tipe='keluar' THEN nilai_rupiah ELSE 0 END), 0) AS omzet
         FROM transaksi_stok
         WHERE YEAR(created_at) = ?
         GROUP BY DATE_FORMAT(created_at, '%Y-%m')
         ORDER BY bulan ASC"
    );
    $stmt->execute([$tahun]);
    $rows = $stmt->fetchAll();

    $judul    = "Laporan Bulanan — $tahun";
    $filename = "laporan-bulanan-$tahun";
}

// Total baris
$total_masuk  = array_sum(array_column($rows, 'masuk'));
$total_keluar = array_sum(array_column($rows, 'keluar'));
$total_omzet  = array_sum(array_column($rows, 'omzet'));
$col_period   = $mode === 'harian' ? 'tanggal' : 'bulan';

// ── Export CSV ────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM utf-8 agar Excel terbaca
    fputcsv($out, ['Periode', 'Masuk (balok)', 'Keluar (balok)', 'Koreksi', 'Omzet (Rp)']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r[$col_period],
            $r['masuk'],
            $r['keluar'],
            $r['koreksi'],
            $r['omzet'],
        ]);
    }
    fputcsv($out, ['TOTAL', $total_masuk, $total_keluar, '', $total_omzet]);
    fclose($out);
    exit;
}

// ── Halaman laporan ───────────────────────────────────────────
$nama_usaha  = get_setting('nama_usaha');
$page_title  = 'Laporan';
$active_page = 'laporan';
include __DIR__ . '/includes/header.php';
?>

<div class="print-only" style="margin-bottom:1rem">
  <strong><?= htmlspecialchars($nama_usaha) ?></strong><br>
  <?= htmlspecialchars($judul) ?><br>
  <small>Dicetak: <?= date('d/m/Y H:i') ?></small>
</div>

<div class="filter-bar no-print">
  <div class="form-group">
    <label>Mode</label>
    <select onchange="ubahMode(this.value)">
      <option value="harian"  <?= $mode === 'harian'  ? 'selected' : '' ?>>Harian</option>
      <option value="bulanan" <?= $mode === 'bulanan' ? 'selected' : '' ?>>Bulanan</option>
    </select>
  </div>

  <?php if ($mode === 'harian'): ?>
  <div class="form-group">
    <label>Bulan</label>
    <input type="month" id="inp-bulan" value="<?= htmlspecialchars($bulan) ?>"
           onchange="location.href='?mode=harian&bulan='+this.value">
  </div>
  <?php else: ?>
  <div class="form-group">
    <label>Tahun</label>
    <input type="number" id="inp-tahun" min="2020" max="2099"
           value="<?= htmlspecialchars($tahun) ?>"
           onchange="location.href='?mode=bulanan&tahun='+this.value">
  </div>
  <?php endif; ?>

  <a href="?mode=<?= $mode ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&export=csv"
     class="btn btn-outline btn-sm">Export CSV</a>
  <button onclick="window.print()" class="btn btn-outline btn-sm">Print / PDF</button>
</div>

<h3 style="font-size:.95rem;margin-bottom:.75rem"><?= htmlspecialchars($judul) ?></h3>

<div class="table-wrap">
<table>
  <thead>
    <tr>
      <th><?= $mode === 'harian' ? 'Tanggal' : 'Bulan' ?></th>
      <th>Masuk</th>
      <th>Keluar</th>
      <th>Koreksi</th>
      <th>Omzet</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="5" style="text-align:center;color:#94a3b8">Tidak ada data</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r[$col_period]) ?></td>
          <td><?= number_format((int)$r['masuk']) ?></td>
          <td><?= number_format((int)$r['keluar']) ?></td>
          <td><?= number_format((int)$r['koreksi']) ?></td>
          <td><?= format_rupiah((int)$r['omzet']) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
  <tfoot>
    <tr style="font-weight:700;background:#f8fafc">
      <td>TOTAL</td>
      <td><?= number_format($total_masuk) ?></td>
      <td><?= number_format($total_keluar) ?></td>
      <td>—</td>
      <td><?= format_rupiah($total_omzet) ?></td>
    </tr>
  </tfoot>
</table>
</div>

<script>
function ubahMode(mode) {
  const bulan = document.getElementById('inp-bulan')?.value || '<?= $bulan ?>';
  const tahun = document.getElementById('inp-tahun')?.value || '<?= $tahun ?>';
  location.href = '?mode=' + mode + '&bulan=' + bulan + '&tahun=' + tahun;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
