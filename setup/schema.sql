-- =============================================================
-- ESKRISTAL — Skema Database
-- DB: eskristal | Charset: utf8mb4_unicode_ci
-- Jalankan: mysql -u root -p < setup/schema.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS eskristal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE eskristal;

-- -------------------------------------------------------------
-- 1. pekerja
--    Direferensikan oleh transaksi_stok, dibuat lebih dulu.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pekerja (
  id         INT          NOT NULL AUTO_INCREMENT,
  nama       VARCHAR(100) NOT NULL,
  nomor_wa   VARCHAR(20)  NOT NULL,   -- format: 628xxxxxxxxx
  aktif      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_nomor_wa (nomor_wa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 2. admin_users
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
  id            INT          NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,   -- bcrypt / argon2
  nama          VARCHAR(100) NOT NULL,
  aktif         TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 3. transaksi_stok  ← ledger utama
--    Stok dihitung: SUM(masuk) − SUM(keluar) + SUM(koreksi)
--    JANGAN simpan stok sebagai angka tunggal di tempat lain.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transaksi_stok (
  id           BIGINT       NOT NULL AUTO_INCREMENT,
  tipe         ENUM('masuk','keluar','koreksi') NOT NULL,
  jumlah       INT          NOT NULL,
  nilai_rupiah INT          NOT NULL,   -- jumlah × harga_satuan pada saat transaksi
  pekerja_id   INT          NULL,
  sumber       ENUM('wa','web') NOT NULL,
  catatan      VARCHAR(255) NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tipe       (tipe),
  KEY idx_created_at (created_at),
  KEY idx_pekerja_id (pekerja_id),
  CONSTRAINT fk_transaksi_pekerja
    FOREIGN KEY (pekerja_id) REFERENCES pekerja (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 4. stok_harian  — ringkasan per hari
--    Diisi/diperbarui oleh cron atau sync Python.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stok_harian (
  id           INT      NOT NULL AUTO_INCREMENT,
  tanggal      DATE     NOT NULL,
  saldo_awal   INT      NOT NULL DEFAULT 0,
  total_masuk  INT      NOT NULL DEFAULT 0,
  total_keluar INT      NOT NULL DEFAULT 0,
  saldo_akhir  INT      NOT NULL DEFAULT 0,
  omzet        INT      NOT NULL DEFAULT 0,   -- total_keluar × harga saat itu
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 5. system_settings  — key-value store
--    Harga & nama produk WAJIB diambil dari sini, bukan hardcode.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_settings (
  key_name VARCHAR(50)  NOT NULL,
  value    VARCHAR(255) NOT NULL,
  PRIMARY KEY (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (key_name, value) VALUES
  ('nama_usaha',   'BITNET INDONESIA'),
  ('nama_produk',  'Es Kristal'),
  ('harga_satuan', '20000'),
  ('tagline',      'Dingin & Berkualitas')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- -------------------------------------------------------------
-- 6. admin_sessions
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_sessions (
  id         BIGINT       NOT NULL AUTO_INCREMENT,
  admin_id   INT          NOT NULL,
  token      VARCHAR(64)  NOT NULL,
  ip         VARCHAR(45)  NULL,
  user_agent TEXT         NULL,
  expires_at DATETIME     NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token (token),
  KEY idx_admin_id (admin_id),
  KEY idx_expires_at (expires_at),
  CONSTRAINT fk_session_admin
    FOREIGN KEY (admin_id) REFERENCES admin_users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 7. sync_log  — riwayat sinkronisasi ke Google Sheets
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sync_log (
  id           INT      NOT NULL AUTO_INCREMENT,
  waktu        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status       ENUM('sukses','gagal') NOT NULL,
  jumlah_baris INT      NOT NULL DEFAULT 0,
  keterangan   TEXT     NULL,
  PRIMARY KEY (id),
  KEY idx_waktu (waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 8. activity_log  — audit trail semua aksi penting
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
  id         BIGINT       NOT NULL AUTO_INCREMENT,
  aktor      VARCHAR(100) NOT NULL,   -- username admin atau nomor WA pekerja
  aksi       VARCHAR(100) NOT NULL,
  detail     TEXT         NULL,
  ip         VARCHAR(45)  NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_aktor      (aktor),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
