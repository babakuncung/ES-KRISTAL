<?php

declare(strict_types=1);

function stok_sekarang(): int {
    $stmt = db()->query(
        "SELECT
            COALESCE(SUM(CASE WHEN tipe='masuk'   THEN jumlah ELSE 0 END), 0)
          - COALESCE(SUM(CASE WHEN tipe='keluar'  THEN jumlah ELSE 0 END), 0)
          + COALESCE(SUM(CASE WHEN tipe='koreksi' THEN jumlah ELSE 0 END), 0)
         AS stok
         FROM transaksi_stok"
    );
    return (int) $stmt->fetchColumn();
}

function get_setting(string $key): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $stmt = db()->prepare("SELECT value FROM system_settings WHERE key_name = ?");
        $stmt->execute([$key]);
        $cache[$key] = (string) $stmt->fetchColumn();
    }
    return $cache[$key];
}

function harga_satuan(): int {
    return (int) get_setting('harga_satuan');
}

function format_rupiah(int $angka): string {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function log_activity(string $aktor, string $aksi, string $detail = ''): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = db()->prepare(
        "INSERT INTO activity_log (aktor, aksi, detail, ip) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$aktor, $aksi, $detail, $ip]);
}

function ringkasan_hari_ini(): array {
    $stmt = db()->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN tipe='masuk'  THEN jumlah ELSE 0 END), 0) AS masuk,
            COALESCE(SUM(CASE WHEN tipe='keluar' THEN jumlah ELSE 0 END), 0) AS keluar
         FROM transaksi_stok
         WHERE DATE(created_at) = CURDATE()"
    );
    $stmt->execute();
    $row = $stmt->fetch();
    $row['omzet'] = (int)$row['keluar'] * harga_satuan();
    return $row;
}

function tren_stok(int $hari = 7): array {
    $stmt = db()->prepare(
        "SELECT
            DATE(created_at) AS tanggal,
            COALESCE(SUM(CASE WHEN tipe='masuk'   THEN jumlah ELSE 0 END), 0)
          - COALESCE(SUM(CASE WHEN tipe='keluar'  THEN jumlah ELSE 0 END), 0)
          + COALESCE(SUM(CASE WHEN tipe='koreksi' THEN jumlah ELSE 0 END), 0) AS net
         FROM transaksi_stok
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(created_at)
         ORDER BY tanggal ASC"
    );
    $stmt->execute([$hari]);
    return $stmt->fetchAll();
}
