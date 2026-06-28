<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$admin = require_login();

$pesan = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'nama_usaha'   => trim($_POST['nama_usaha']   ?? ''),
        'nama_produk'  => trim($_POST['nama_produk']  ?? ''),
        'harga_satuan' => trim($_POST['harga_satuan'] ?? ''),
        'tagline'      => trim($_POST['tagline']       ?? ''),
    ];

    if ($fields['nama_usaha'] === '' || $fields['nama_produk'] === '') {
        $error = 'Nama usaha dan nama produk wajib diisi.';
    } elseif (!ctype_digit($fields['harga_satuan']) || (int)$fields['harga_satuan'] <= 0) {
        $error = 'Harga satuan harus berupa angka positif.';
    } else {
        $stmt = db()->prepare(
            "INSERT INTO system_settings (key_name, value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)"
        );
        foreach ($fields as $key => $val) {
            $stmt->execute([$key, $val]);
        }
        log_activity($admin['username'], 'ubah_pengaturan', json_encode($fields));
        $pesan = 'Pengaturan berhasil disimpan.';
    }
}

// Ambil nilai terbaru
$settings = [];
$rows = db()->query("SELECT key_name, value FROM system_settings")->fetchAll();
foreach ($rows as $r) {
    $settings[$r['key_name']] = $r['value'];
}

// Cek status WAHA
$waha_status = 'tidak diketahui';
$waha_ok     = false;
if (defined('WAHA_URL') && WAHA_URL !== '') {
    $ctx = stream_context_create([
        'http' => [
            'timeout'        => 3,
            'ignore_errors'  => true,
            'method'         => 'GET',
            'header'         => 'X-Api-Key: ' . WAHA_API_KEY . "\r\n",
        ]
    ]);
    $resp = @file_get_contents(WAHA_URL . '/api/sessions', false, $ctx);
    if ($resp !== false) {
        $data = json_decode($resp, true);
        // WAHA v2: array session; periksa apakah ada sesi CONNECTED
        if (is_array($data)) {
            foreach ($data as $sess) {
                if (($sess['name'] ?? '') === WAHA_SESSION && ($sess['status'] ?? '') === 'WORKING') {
                    $waha_ok = true;
                    break;
                }
            }
            $waha_status = $waha_ok ? 'Terhubung' : 'Belum terhubung';
        }
    } else {
        $waha_status = 'Tidak dapat dijangkau';
    }
}

$page_title  = 'Pengaturan';
$active_page = 'pengaturan';
include __DIR__ . '/includes/header.php';
?>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-ok"><?= htmlspecialchars($pesan) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="alert alert-err"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:1rem">
  <h3 style="font-size:1rem;margin-bottom:.1rem">Status WAHA</h3>
  <p style="font-size:.85rem;color:#64748b;margin-bottom:.5rem">Gateway WhatsApp</p>
  <span class="badge <?= $waha_ok ? 'badge-green' : 'badge-red' ?>">
    <?= htmlspecialchars($waha_status) ?>
  </span>
  <span style="font-size:.8rem;color:#94a3b8;margin-left:.5rem"><?= htmlspecialchars(WAHA_URL) ?></span>
</div>

<div class="card">
  <h3 style="font-size:1rem;margin-bottom:.75rem">Pengaturan Usaha</h3>
  <form method="post">
    <div class="form-group">
      <label>Nama Usaha</label>
      <input type="text" name="nama_usaha" maxlength="255"
             value="<?= htmlspecialchars($settings['nama_usaha'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>Nama Produk</label>
      <input type="text" name="nama_produk" maxlength="255"
             value="<?= htmlspecialchars($settings['nama_produk'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>Harga Satuan (Rp)</label>
      <input type="number" name="harga_satuan" min="1" max="9999999"
             value="<?= htmlspecialchars($settings['harga_satuan'] ?? '') ?>" required>
      <div class="form-hint">Harga per balok dalam Rupiah, tanpa titik/koma.</div>
    </div>
    <div class="form-group">
      <label>Tagline <span style="font-weight:400;color:#94a3b8">(opsional)</span></label>
      <input type="text" name="tagline" maxlength="255"
             value="<?= htmlspecialchars($settings['tagline'] ?? '') ?>">
    </div>
    <button type="submit" class="btn btn-primary">Simpan</button>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
