<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = require_login();

// ── Filter ──────────────────────────────────────────────────
$dari   = $_GET['dari']       ?? date('Y-m-01');
$sampai = $_GET['sampai']     ?? date('Y-m-d');
$tipe   = $_GET['tipe']       ?? '';
$sumber = $_GET['sumber']     ?? '';
$pid    = (int)($_GET['pekerja_id'] ?? 0);

// Validasi tanggal
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari))   $dari   = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) $sampai = date('Y-m-d');

// ── Paginasi ─────────────────────────────────────────────────
$per_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

// ── Query ────────────────────────────────────────────────────
$where  = "WHERE DATE(t.created_at) BETWEEN ? AND ?";
$params = [$dari, $sampai];

$allowed_tipe   = ['masuk', 'keluar', 'koreksi'];
$allowed_sumber = ['wa', 'web'];

if (in_array($tipe, $allowed_tipe, true))     { $where .= " AND t.tipe = ?";      $params[] = $tipe; }
if (in_array($sumber, $allowed_sumber, true)) { $where .= " AND t.sumber = ?";    $params[] = $sumber; }
if ($pid > 0)                                 { $where .= " AND t.pekerja_id = ?"; $params[] = $pid; }

$count_stmt = db()->prepare("SELECT COUNT(*) FROM transaksi_stok t $where");
$count_stmt->execute($params);
$total = (int) $count_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total / $per_page));

$stmt = db()->prepare(
    "SELECT t.*, p.nama AS nama_pekerja
     FROM transaksi_stok t
     LEFT JOIN pekerja p ON p.id = t.pekerja_id
     $where
     ORDER BY t.created_at DESC
     LIMIT $per_page OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Daftar pekerja untuk filter
$pekerja_list = db()->query("SELECT id, nama FROM pekerja ORDER BY nama")->fetchAll();

$page_title  = 'Riwayat Transaksi';
$active_page = 'riwayat';
include __DIR__ . '/includes/header.php';
?>

<form method="get" class="filter-bar no-print">
  <div class="form-group">
    <label>Dari</label>
    <input type="date" name="dari" value="<?= htmlspecialchars($dari) ?>">
  </div>
  <div class="form-group">
    <label>Sampai</label>
    <input type="date" name="sampai" value="<?= htmlspecialchars($sampai) ?>">
  </div>
  <div class="form-group">
    <label>Tipe</label>
    <select name="tipe">
      <option value="">Semua</option>
      <option value="masuk"   <?= $tipe === 'masuk'   ? 'selected' : '' ?>>Masuk</option>
      <option value="keluar"  <?= $tipe === 'keluar'  ? 'selected' : '' ?>>Keluar</option>
      <option value="koreksi" <?= $tipe === 'koreksi' ? 'selected' : '' ?>>Koreksi</option>
    </select>
  </div>
  <div class="form-group">
    <label>Sumber</label>
    <select name="sumber">
      <option value="">Semua</option>
      <option value="wa"  <?= $sumber === 'wa'  ? 'selected' : '' ?>>WhatsApp</option>
      <option value="web" <?= $sumber === 'web' ? 'selected' : '' ?>>Web</option>
    </select>
  </div>
  <div class="form-group">
    <label>Pekerja</label>
    <select name="pekerja_id">
      <option value="">Semua</option>
      <?php foreach ($pekerja_list as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $pid === (int)$p['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($p['nama']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
</form>

<div class="table-wrap">
<table>
  <thead>
    <tr>
      <th>Waktu</th>
      <th>Tipe</th>
      <th>Jumlah</th>
      <th>Nilai</th>
      <th>Pekerja</th>
      <th>Sumber</th>
      <th>Catatan</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="7" style="text-align:center;color:#94a3b8">Tidak ada data</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
          <td>
            <?php
              $badge = match($r['tipe']) {
                'masuk'   => 'badge-green',
                'keluar'  => 'badge-red',
                'koreksi' => 'badge-blue',
                default   => 'badge-gray',
              };
            ?>
            <span class="badge <?= $badge ?>"><?= htmlspecialchars($r['tipe']) ?></span>
          </td>
          <td><?= number_format((int)$r['jumlah']) ?></td>
          <td><?= format_rupiah((int)$r['nilai_rupiah']) ?></td>
          <td><?= htmlspecialchars($r['nama_pekerja'] ?? '—') ?></td>
          <td><span class="badge badge-gray"><?= htmlspecialchars($r['sumber']) ?></span></td>
          <td><?= htmlspecialchars($r['catatan'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
</div>

<?php if ($total_pages > 1): ?>
<div class="pagination no-print">
  <?php if ($page > 1): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo;</a>
  <?php endif; ?>
  <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
    <?php if ($i === $page): ?>
      <span class="current"><?= $i ?></span>
    <?php else: ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $total_pages): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">&raquo;</a>
  <?php endif; ?>
</div>
<p style="font-size:.8rem;color:#94a3b8;margin-top:.5rem">
  Total <?= number_format($total) ?> transaksi
</p>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
