<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = require_login();

$pesan = '';
$error = '';

// Nonaktifkan / aktifkan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $tid  = (int)$_POST['toggle_id'];
    $aktif = (int)$_POST['toggle_aktif'];
    $stmt = db()->prepare("UPDATE pekerja SET aktif = ? WHERE id = ?");
    $stmt->execute([$aktif, $tid]);
    log_activity($admin['username'], $aktif ? 'aktifkan_pekerja' : 'nonaktifkan_pekerja', "id=$tid");
    $pesan = 'Status pekerja diperbarui.';
}

// Tambah pekerja
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $nama     = trim($_POST['nama']     ?? '');
    $nomor_wa = trim($_POST['nomor_wa'] ?? '');

    if ($nama === '' || $nomor_wa === '') {
        $error = 'Nama dan nomor WA wajib diisi.';
    } elseif (!preg_match('/^628\d{8,12}$/', $nomor_wa)) {
        $error = 'Format nomor WA tidak valid. Gunakan format 628xxxxxxxxx.';
    } else {
        try {
            $stmt = db()->prepare("INSERT INTO pekerja (nama, nomor_wa) VALUES (?, ?)");
            $stmt->execute([$nama, $nomor_wa]);
            log_activity($admin['username'], 'tambah_pekerja', "$nama ($nomor_wa)");
            $pesan = "Pekerja $nama berhasil ditambahkan.";
            $_POST = [];
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'Nomor WA sudah terdaftar.';
            } else {
                throw $e;
            }
        }
    }
}

$pekerja_list = db()->query("SELECT * FROM pekerja ORDER BY aktif DESC, nama ASC")->fetchAll();

$page_title  = 'Kelola Pekerja';
$active_page = 'pekerja';
include __DIR__ . '/includes/header.php';
?>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-ok"><?= htmlspecialchars($pesan) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="alert alert-err"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:1rem">
  <h3 style="font-size:1rem;margin-bottom:.75rem">Tambah Pekerja</h3>
  <form method="post">
    <input type="hidden" name="tambah" value="1">
    <div class="form-group">
      <label>Nama</label>
      <input type="text" name="nama" maxlength="100"
             value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>Nomor WhatsApp</label>
      <input type="text" name="nomor_wa" maxlength="20" placeholder="628xxxxxxxxx"
             value="<?= htmlspecialchars($_POST['nomor_wa'] ?? '') ?>" required>
      <div class="form-hint">Format: 628 diikuti 8–12 digit angka</div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Tambah</button>
  </form>
</div>

<div class="table-wrap">
<table>
  <thead>
    <tr><th>Nama</th><th>Nomor WA</th><th>Status</th><th>Terdaftar</th><th class="no-print">Aksi</th></tr>
  </thead>
  <tbody>
    <?php if (empty($pekerja_list)): ?>
      <tr><td colspan="5" style="text-align:center;color:#94a3b8">Belum ada pekerja</td></tr>
    <?php else: ?>
      <?php foreach ($pekerja_list as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['nama']) ?></td>
          <td><?= htmlspecialchars($p['nomor_wa']) ?></td>
          <td>
            <span class="badge <?= $p['aktif'] ? 'badge-green' : 'badge-red' ?>">
              <?= $p['aktif'] ? 'Aktif' : 'Nonaktif' ?>
            </span>
          </td>
          <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
          <td class="no-print">
            <form method="post" style="display:inline"
                  onsubmit="return konfirmasi('<?= $p['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?> pekerja ini?')">
              <input type="hidden" name="toggle_id"    value="<?= $p['id'] ?>">
              <input type="hidden" name="toggle_aktif" value="<?= $p['aktif'] ? 0 : 1 ?>">
              <button type="submit" class="btn btn-sm <?= $p['aktif'] ? 'btn-danger' : 'btn-outline' ?>">
                <?= $p['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
