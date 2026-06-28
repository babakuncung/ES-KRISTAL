<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/lib/wa_parser.php';
require_once __DIR__ . '/lib/wa_sender.php';

// ── Selalu balas 200 ke WAHA ────────────────────────────────
header('Content-Type: application/json');

function selesai(string $pesan = 'ok'): void {
    echo json_encode(['status' => $pesan]);
    exit;
}

// ── 1. Hanya terima POST ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    selesai('method_not_allowed');
}

// ── 2. Verifikasi API key ───────────────────────────────────
$key_masuk = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (WA_WEBHOOK_SECRET === '' || $key_masuk !== WA_WEBHOOK_SECRET) {
    http_response_code(401);
    selesai('unauthorized');
}

// ── 3. Decode body JSON ─────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    selesai('bad_json');
}

// ── 4. Hanya proses event "message" ────────────────────────
$event = $data['event'] ?? '';
if ($event !== 'message') {
    selesai('ignored');
}

$payload  = $data['payload'] ?? [];
$from_raw = $payload['from'] ?? '';
$body     = trim($payload['body'] ?? '');

if ($from_raw === '' || $body === '') {
    selesai('empty');
}

// ── 5. Normalkan nomor WA (628xxx@c.us → 628xxx) ───────────
$nomor_wa = preg_replace('/@.*$/', '', $from_raw);
$chat_id  = $from_raw; // tetap pakai format @c.us untuk kirim balasan

// ── 6. Cek pekerja aktif ───────────────────────────────────
$stmt = db()->prepare(
    "SELECT id, nama FROM pekerja WHERE nomor_wa = ? AND aktif = 1 LIMIT 1"
);
$stmt->execute([$nomor_wa]);
$pekerja = $stmt->fetch();

if (!$pekerja) {
    kirim_wa($chat_id, "Maaf Kak, nomor ini belum terdaftar atau sudah nonaktif. Hubungi admin.");
    log_activity($nomor_wa, 'wa_tolak_nomor', 'Nomor tidak terdaftar');
    selesai('not_registered');
}

$nama = $pekerja['nama'];

// ── 7. Rate limit: maks 15 pesan/menit per nomor ───────────
$stmt_rl = db()->prepare(
    "SELECT COUNT(*) FROM activity_log
     WHERE aktor = ? AND aksi = 'wa_perintah'
       AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
);
$stmt_rl->execute([$nomor_wa]);
if ((int)$stmt_rl->fetchColumn() >= 15) {
    kirim_wa($chat_id, "Terlalu banyak permintaan Kak $nama. Coba lagi sebentar.");
    selesai('rate_limited');
}

// ── 8. Parse perintah ───────────────────────────────────────
$parsed = parse_perintah($body);
$cmd    = $parsed['cmd'];

log_activity($nomor_wa, 'wa_perintah', "$cmd — $body");

// ── 9. Eksekusi ─────────────────────────────────────────────
switch ($cmd) {

    case 'MASUK': {
        $jumlah      = $parsed['jumlah'];
        $nilai       = $jumlah * harga_satuan();

        $stmt = db()->prepare(
            "INSERT INTO transaksi_stok (tipe, jumlah, nilai_rupiah, pekerja_id, sumber)
             VALUES ('masuk', ?, ?, ?, 'wa')"
        );
        $stmt->execute([$jumlah, $nilai, $pekerja['id']]);

        $stok = stok_sekarang();
        $balas = "OK Kak $nama. Masuk $jumlah balok. Stok sekarang: " . number_format($stok) . " balok.";
        kirim_wa($chat_id, $balas);
        break;
    }

    case 'KELUAR': {
        $jumlah = $parsed['jumlah'];
        $stok   = stok_sekarang();

        if ($jumlah > $stok) {
            kirim_wa($chat_id,
                "Maaf Kak $nama, stok tidak cukup. Stok sekarang hanya " . number_format($stok) . " balok."
            );
            break;
        }

        $nilai = $jumlah * harga_satuan();
        $stmt  = db()->prepare(
            "INSERT INTO transaksi_stok (tipe, jumlah, nilai_rupiah, pekerja_id, sumber)
             VALUES ('keluar', ?, ?, ?, 'wa')"
        );
        $stmt->execute([$jumlah, $nilai, $pekerja['id']]);

        $stok_baru = stok_sekarang();
        $balas = "OK Kak $nama. Keluar $jumlah balok (" . format_rupiah($nilai) . "). "
               . "Stok sekarang: " . number_format($stok_baru) . " balok.";
        kirim_wa($chat_id, $balas);
        break;
    }

    case 'STOK': {
        $stok  = stok_sekarang();
        $balas = "Stok saat ini: " . number_format($stok) . " balok.";
        kirim_wa($chat_id, $balas);
        break;
    }

    case 'LAPORAN': {
        $r     = ringkasan_hari_ini();
        $stok  = stok_sekarang();
        $balas = "Laporan hari ini:\n"
               . "Masuk : " . number_format((int)$r['masuk'])  . " balok\n"
               . "Keluar: " . number_format((int)$r['keluar']) . " balok\n"
               . "Stok  : " . number_format($stok)             . " balok\n"
               . "Omzet : " . format_rupiah((int)$r['omzet']);
        kirim_wa($chat_id, $balas);
        break;
    }

    case 'BANTUAN': {
        $balas = "Perintah yang tersedia:\n"
               . "MASUK [jumlah]  - Catat stok masuk\n"
               . "KELUAR [jumlah] - Catat stok keluar\n"
               . "STOK            - Cek stok sekarang\n"
               . "LAPORAN         - Rekap hari ini\n"
               . "BANTUAN         - Tampilkan pesan ini";
        kirim_wa($chat_id, $balas);
        break;
    }

    case 'FORMAT_ERROR': {
        $p     = $parsed['perintah_asli'];
        $balas = "Format salah Kak. Contoh: $p 50";
        kirim_wa($chat_id, $balas);
        break;
    }

    default: {
        kirim_wa($chat_id, "Perintah tidak dikenal Kak $nama. Ketik BANTUAN untuk melihat daftar perintah.");
        break;
    }
}

selesai();
