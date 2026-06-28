# Blueprint Sistem Manajemen — ESKRISTAL

**Domain:** https://eskristal.ajengmedia.com
**Tema:** Manajemen stok & laporan barang harian, sinkronisasi Google Spreadsheet, dan WA Bot
**Pemilik:** BITNET INDONESIA
**Tanggal dokumen:** 28 Juni 2026

---

## 1. Ringkasan Proyek

Sistem manajemen stok untuk usaha **Es Kristal** dengan dua muka berbeda beban:

- **Pekerja** melapor stok masuk/keluar lewat **WhatsApp** dengan perintah teks polos — dirancang untuk HP berspesifikasi rendah ("HP kentang"): tanpa aplikasi tambahan, tanpa tombol interaktif, hemat kuota, jalan di WA versi lama.
- **Owner/Admin** memantau lewat **web dashboard mobile-friendly**: stok real-time, laporan harian, omzet otomatis, dan export.

Database MySQL menjadi **sumber kebenaran tunggal**. Semua data didorong otomatis ke **Google Spreadsheet** sebagai laporan yang mudah dibuka/dibagikan tanpa login.

---

## 2. Spesifikasi Bisnis

| Item | Nilai |
|---|---|
| Produk | Es Kristal |
| Ukuran/satuan | 20 kg per balok |
| Harga | Rp20.000 / balok |
| Jumlah jenis produk | 1 (tunggal) |

Karena produk tunggal dan harga tetap:
- Tidak perlu tabel produk yang rumit — **harga & nama disimpan di pengaturan** (mudah diubah kalau harga naik).
- Perintah WA cukup `MASUK <jumlah>` dan `KELUAR <jumlah>` tanpa menyebut nama produk.
- **Omzet dihitung otomatis**: jumlah keluar × Rp20.000. Laporan stok sekaligus jadi laporan uang.

---

## 3. Arsitektur Sistem

### Komponen

| Komponen | Teknologi | Peran |
|---|---|---|
| Web Dashboard + API | PHP 8.2 + Nginx | Halaman admin, webhook handler, logika bisnis |
| Database | MySQL (db: `eskristal`) | Sumber kebenaran semua data |
| WA Gateway | WAHA (Docker, self-hosted) | Terima & kirim pesan WhatsApp |
| Sinkronisasi | Python + gspread (cron) | Dorong data DB → Google Sheets |
| Laporan eksternal | Google Spreadsheet | Cermin laporan, backup |

### Alur data — laporan dari pekerja (WA)

1. Pekerja kirim `MASUK 50` → WhatsApp.
2. **WAHA** (container Docker di VPS) menerima pesan, lalu kirim **webhook** ke `https://eskristal.ajengmedia.com/api/wa-webhook.php`.
3. **PHP** memproses: validasi nomor pekerja → parsing perintah → simpan ke `transaksi_stok` → hitung stok terkini.
4. **PHP** memanggil REST API WAHA (`POST /api/sendText`) untuk membalas konfirmasi ke pekerja.
5. Selesai. Tidak ada kode Node custom — WAHA yang menangani sisi WhatsApp.

### Alur data — owner (web)

1. Owner buka `eskristal.ajengmedia.com/admin/` di HP → login.
2. PHP baca/tulis ke MySQL → tampilkan stok, laporan, grafik.

### Alur data — sinkronisasi

1. Cron menjalankan `sync_eskristal.py` tiap 10 menit.
2. Python tarik transaksi baru sejak sync terakhir → tulis ke Google Sheets → catat status ke `sync_log`.

> **Catatan runtime:** dengan WAHA, sistem hanya butuh **3 stack**: PHP (aplikasi & webhook), Docker (WAHA), dan Python (sync). Semua logika bisnis terpusat di PHP — sama seperti WiFi Portal — sehingga tidak ada logika kembar.

---

## 4. Infrastruktur

### VPS — PANEL BITNET

| Item | Nilai |
|---|---|
| IP | 103.93.163.58 |
| Hostname | panelbitnet.ajengmedia.com |
| Peran | Rumah untuk eskristal & tools `*.ajengmedia.com` lainnya |

### Stack yang perlu disiapkan (VPS baru)

- [ ] **Nginx** + **PHP 8.2-FPM**
- [ ] **MySQL** (buat db `eskristal` + user khusus)
- [ ] **SSL** via Certbot (Let's Encrypt)
- [ ] **Docker** + Docker Compose (untuk WAHA)
- [ ] **Python 3** + pip (`gspread`, `google-auth`) untuk sync
- [ ] **Cron** untuk sync & backup
- [ ] DNS: A record `eskristal.ajengmedia.com` → `103.93.163.58`

### Struktur folder aplikasi

Mengikuti pola WiFi Portal (path tanpa prefix `/eskristal/`):

```
/var/www/eskristal/
├── api/                 # endpoint publik & webhook
│   ├── wa-webhook.php    # penerima webhook dari WAHA
│   ├── branding.php      # info nama usaha/harga untuk publik
│   └── lib/              # fungsi bisnis (parsing, hitung stok)
├── admin/               # panel admin
│   ├── login.php
│   ├── index.php         # dashboard
│   ├── riwayat.php
│   ├── input.php
│   ├── pekerja.php
│   ├── pengaturan.php
│   ├── laporan.php
│   └── includes/
│       └── auth.php      # sesi admin
├── config/              # DIBLOKIR Nginx
│   ├── config.php        # koneksi DB, konstanta
│   └── .env              # kredensial (WAHA key, token, dll)
├── includes/            # DIBLOKIR Nginx (fungsi bersama)
├── public/              # aset, logo, css, js
└── sync/
    ├── sync_eskristal.py
    └── service-account.json   # DIBLOKIR Nginx
```

---

## 5. Skema Database (db: `eskristal`)

Prinsip: **ledger sebagai sumber kebenaran, stok dihitung otomatis** (tidak ada angka stok yang di-set manual).

### Tabel `transaksi_stok` — jantung sistem

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGINT PK AI | |
| tipe | ENUM('masuk','keluar','koreksi') | produksi dicatat sebagai `masuk` |
| jumlah | INT | jumlah balok (koreksi bisa negatif) |
| nilai_rupiah | INT | dihitung saat insert (jumlah × harga) |
| pekerja_id | INT NULL | NULL bila input dari web admin |
| sumber | ENUM('wa','web') | asal data |
| catatan | VARCHAR(255) NULL | opsional |
| created_at | DATETIME | waktu transaksi |

**Stok terkini** = `SUM(masuk) − SUM(keluar) + SUM(koreksi)`.

### Tabel `stok_harian` — rekap cepat per hari

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK AI | |
| tanggal | DATE UNIQUE | |
| saldo_awal | INT | stok awal hari |
| total_masuk | INT | |
| total_keluar | INT | |
| saldo_akhir | INT | |
| omzet | INT | total_keluar × harga |
| updated_at | DATETIME | |

Di-generate/di-update dari `transaksi_stok`.

### Tabel `pekerja`

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK AI | |
| nama | VARCHAR(100) | |
| nomor_wa | VARCHAR(20) UNIQUE | format `628xxxxxxxxx` |
| aktif | TINYINT(1) | hanya yang aktif boleh lapor |
| created_at | DATETIME | |

### Tabel `system_settings` (key-value)

Menyimpan nilai yang bisa diubah admin tanpa sentuh kode:

| key | contoh value |
|---|---|
| nama_usaha | Es Kristal BITNET |
| nama_produk | Es Kristal 20 kg |
| harga_satuan | 20000 |
| tagline | Layanan stok & laporan harian |

> Kredensial sensitif (WAHA API key, internal token, ID Google Sheet) disimpan di `config/.env`, **bukan** di tabel ini.

### Tabel pendukung

- **`admin_users`** — id, username, password_hash, nama, aktif, created_at
- **`admin_sessions`** — id, admin_id, token, ip, user_agent, expires_at, created_at
- **`sync_log`** — id, waktu, status('sukses','gagal'), jumlah_baris, keterangan
- **`activity_log`** — id, aktor, aksi, detail, ip, created_at

---

## 6. WA Bot — Spesifikasi

### Prinsip desain (HP kentang)

- **Perintah teks polos** — tanpa tombol/menu interaktif (butuh WA versi baru & sinyal bagus).
- **Balasan singkat** — hemat kuota, cepat dibaca.
- **Bahasa ramah** — gunakan sapaan "Kak".

### Daftar perintah

| Perintah | Fungsi | Contoh kirim | Contoh balasan |
|---|---|---|---|
| `MASUK <jumlah>` | Tambah stok | `MASUK 50` | OK Kak. Masuk 50 balok. Sisa stok: 1.200 |
| `KELUAR <jumlah>` | Kurangi stok | `KELUAR 30` | OK Kak. Keluar 30 balok (Rp600.000). Sisa stok: 1.170 |
| `STOK` | Cek stok | `STOK` | Stok saat ini: 1.170 balok |
| `LAPORAN` | Rekap hari ini | `LAPORAN` | Hari ini — Masuk: 50, Keluar: 30, Sisa: 1.170, Omzet: Rp600.000 |
| `BANTUAN` | Daftar perintah | `BANTUAN` | (kirim daftar perintah) |

### Aturan validasi

1. **Nomor pengirim** harus terdaftar & aktif di tabel `pekerja`. Jika tidak → balasan halus menolak.
2. **Jumlah** harus angka bulat positif. Jika tidak → minta ulang dengan contoh format.
3. **KELUAR** tidak boleh melebihi stok saat ini → tolak dengan info sisa stok.
4. Perintah tidak dikenal → arahkan ke `BANTUAN`.
5. Setiap aksi dicatat di `activity_log`.

### Endpoint webhook

`POST /api/wa-webhook.php`
- Verifikasi request berasal dari WAHA (cek API key/header).
- Parsing pesan → jalankan logika → simpan → balas via WAHA `POST /api/sendText`.

---

## 7. Web Dashboard — Spesifikasi (mobile-first)

Diakses di `eskristal.ajengmedia.com/admin/`. Pola autentikasi & logging mengikuti WiFi Portal.

| Halaman | Isi |
|---|---|
| `login.php` | Login admin (username + password) |
| `index.php` | Dashboard: stok terkini, kartu Masuk/Keluar/Omzet hari ini, grafik tren 7 & 30 hari |
| `riwayat.php` | Daftar transaksi + filter (tanggal/pekerja/tipe/sumber) |
| `input.php` | Input manual: masuk, keluar, koreksi (untuk pembetulan) |
| `pekerja.php` | Kelola pekerja + daftarkan nomor WA |
| `pengaturan.php` | Ubah harga, nama usaha; lihat status koneksi WAHA |
| `laporan.php` | Rekap harian/bulanan + export **CSV & PDF** |

Fitur penting:
- **Omzet otomatis** di semua laporan (jumlah keluar × harga).
- **Responsif** — dirancang untuk layar HP lebih dulu.
- Export CSV/PDF memanfaatkan komponen yang sudah dikuasai dari WiFi Portal.

---

## 8. Sinkronisasi Google Spreadsheet

**Arah: satu arah, DB → Sheets** (paling aman, tanpa risiko konflik). Database tetap sumber kebenaran; Sheets jadi cermin laporan.

### Implementasi

- Script `sync_eskristal.py` (pola seperti `kas_ledger_v2.py`).
- Autentikasi **service account** + `gspread`.
- Dijalankan **cron tiap 10 menit**.
- Setiap run dicatat ke `sync_log` (untuk debug bila gagal).

### Struktur Spreadsheet

| Sheet | Isi |
|---|---|
| HARIAN | Semua transaksi (tanggal, tipe, jumlah, nilai, pekerja, sumber) |
| REKAP BULANAN | Agregat per bulan (total masuk/keluar/omzet) |
| STOK | Saldo terkini & ringkasan |

> Sinkronisasi dua arah (edit dari Sheets ikut ke DB) **tidak** untuk versi awal — menambah kompleksitas & risiko. Bisa dipertimbangkan nanti bila benar-benar dibutuhkan.

---

## 9. Keamanan & Hardening

Penting mengingat pengalaman sebelumnya dengan backdoor/web shell di server lain.

- [ ] **Blokir akses** folder `config/`, `includes/`, `sync/` di Nginx.
- [ ] **`config/.env`** untuk semua kredensial (WAHA key, internal token, gsheet id). Jangan hardcode di kode.
- [ ] **Validasi ketat** semua input dari webhook WA (tipe data, panjang, whitelist perintah).
- [ ] **Verifikasi webhook** — pastikan request benar dari WAHA (API key/header).
- [ ] **Rate limiting** pada endpoint webhook & login admin.
- [ ] **Nomor WA khusus bot** — terpisah dari nomor pribadi/admin.
- [ ] **Session WAHA** disimpan di volume Docker persisten (scan QR cukup sekali).
- [ ] **WAHA auto-restart** (`restart: always` di Docker Compose).
- [ ] **Password admin** di-hash (bcrypt/argon2), bukan plaintext.
- [ ] **HTTPS wajib** (Certbot, auto-renew).
- [ ] **Backup** — `mysqldump` harian via cron.

---

## 10. Roadmap Pembangunan

### Fase 0 — Persiapan
- [ ] Arahkan DNS `eskristal.ajengmedia.com` → `103.93.163.58`
- [ ] Siapkan stack VPS: Nginx, PHP 8.2, MySQL, Docker, Python, Certbot
- [ ] Buat db `eskristal` + user MySQL khusus
- [ ] Deploy WAHA (Docker Compose) + scan QR nomor bot

### Fase 1 — Database & fondasi
- [ ] Buat semua tabel (Bagian 5)
- [ ] Siapkan struktur folder (Bagian 4)
- [ ] Isi `system_settings` (harga 20000, nama usaha/produk)
- [ ] `config.php` + `.env`

### Fase 2 — Web dashboard
- [ ] Autentikasi admin (login + sesi)
- [ ] Dashboard stok + kartu harian + grafik
- [ ] Riwayat transaksi + filter
- [ ] Input manual (masuk/keluar/koreksi)
- [ ] Kelola pekerja + nomor WA
- [ ] Pengaturan (harga, nama usaha)
- [ ] Laporan + export CSV/PDF

### Fase 3 — WA Bot
- [ ] Endpoint `api/wa-webhook.php`
- [ ] Parsing perintah (MASUK/KELUAR/STOK/LAPORAN/BANTUAN)
- [ ] Validasi nomor pekerja & jumlah
- [ ] Balasan via WAHA REST API
- [ ] Uji dengan beberapa nomor pekerja

### Fase 4 — Sinkronisasi Google Sheets
- [ ] Service account + `service-account.json`
- [ ] Script `sync_eskristal.py` (3 sheet)
- [ ] Cron tiap 10 menit + `sync_log`

### Fase 5 — Testing & hardening
- [ ] Uji end-to-end (WA → DB → dashboard → Sheets)
- [ ] Terapkan checklist keamanan (Bagian 9)
- [ ] Backup otomatis
- [ ] Serah ke operasional

---

## Lampiran A — Cheat Sheet Perintah WA (untuk pekerja)

```
MASUK 50    → catat 50 balok masuk
KELUAR 30   → catat 30 balok keluar
STOK        → lihat sisa stok sekarang
LAPORAN     → lihat ringkasan hari ini
BANTUAN     → lihat daftar perintah
```

*Cukup ketik perintah lalu kirim. Bot akan balas konfirmasi.*

## Lampiran B — Catatan Operasional

- **WAHA**: jalan via Docker Compose dengan `restart: always`; volume untuk session agar tidak perlu scan QR ulang.
- **Scan QR**: lewat dashboard WAHA, satu kali saat setup awal.
- **Jaga bot tetap hidup**: bot mati = laporan pekerja hilang. Pantau status di halaman pengaturan.
- **Backup DB**: `mysqldump` harian, simpan beberapa hari ke belakang.
- **Pemulihan**: bila VPS reboot, pastikan Docker (WAHA), PHP-FPM, Nginx, dan cron otomatis aktif kembali.