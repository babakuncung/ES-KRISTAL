<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = require_login();

$pesan = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipe   = $_POST['tipe']    ?? '';
    $jumlah = (int)($_POST['jumlah'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');

    $allowed = ['masuk', 'keluar', 'koreksi'];
    if (!in_array($tipe, $allowed, true)) {
        $error = 'Tipe tidak valid.';
    } elseif ($jumlah <= 0) {
        $error = 'Jumlah harus lebih dari 0.';
    } else {
        $harga       = harga_satuan();
        $nilai       = $jumlah * $harga;

        $stmt = db()->prepare(
            "INSERT INTO transaksi_stok (tipe, jumlah, nilai_rupiah, sumber, catatan)
             VALUES (?, ?, ?, 'web', ?)"
        );
        $stmt->execute([$tipe, $jumlah, $nilai, $catatan ?: null]);

        log_activity(
            $admin['username'],
            'input_stok',
            "$tipe $jumlah balok" . ($catatan ? " — $catatan" : '')
        );

        $pesan = "Berhasil input $tipe $jumlah balok (" . format_rupiah($nilai) . ").";
        // Reset POST
        $_POST = [];
    }
}

$stok_sekarang = stok_sekarang();
$harga         = harga_satuan();

$page_title  = 'Input Stok';
$active_page = 'input';
include __DIR__ . '/includes/header.php';
?>

<div class="card" style="margin-bottom:1rem">
  <div class="label">Stok Sekarang</div>
  <div class="value blue"><?= number_format($stok_sekarang) ?> balok</div>
</div>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-ok"><?= htmlspecialchars($pesan) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="alert alert-err"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
  <form method="post">
    <div class="form-group">
      <label for="tipe">Tipe Transaksi</label>
      <select id="tipe" name="tipe" required>
        <option value="">— Pilih —</option>
        <option value="masuk"   <?= ($_POST['tipe'] ?? '') === 'masuk'   ? 'selected' : '' ?>>Masuk (produksi)</option>
        <option value="keluar"  <?= ($_POST['tipe'] ?? '') === 'keluar'  ? 'selected' : '' ?>>Keluar (penjualan)</option>
        <option value="koreksi" <?= ($_POST['tipe'] ?? '') === 'koreksi' ? 'selected' : '' ?>>Koreksi (penyesuaian)</option>
      </select>
    </div>
    <div class="form-group">
      <label for="jumlah">Jumlah (balok)</label>
      <input type="number" id="jumlah" name="jumlah" min="1" max="99999"
             value="<?= (int)($_POST['jumlah'] ?? '') ?>" required>
      <div class="form-hint" id="preview-nilai"></div>
    </div>
    <div class="form-group">
      <label for="catatan">Catatan <span style="font-weight:400;color:#94a3b8">(opsional)</span></label>
      <input type="text" id="catatan" name="catatan" maxlength="255"
             value="<?= htmlspecialchars($_POST['catatan'] ?? '') ?>">
    </div>
    <button type="submit" class="btn btn-primary">Simpan</button>
  </form>
</div>

<script>
const harga = <?= $harga ?>;
document.getElementById('jumlah').addEventListener('input', function() {
  const val = parseInt(this.value) || 0;
  document.getElementById('preview-nilai').textContent =
    val > 0 ? 'Nilai: Rp ' + (val * harga).toLocaleString('id-ID') : '';
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
