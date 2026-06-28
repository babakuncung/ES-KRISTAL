# ESKRISTAL — Sistem Manajemen Stok Es Kristal

Sistem manajemen stok Es Kristal untuk **BITNET INDONESIA**. Pekerja melaporkan via WhatsApp, admin memantau via web dashboard, data otomatis tersinkron ke Google Sheets.

---

## Fitur

- **WA Bot** — pekerja kirim `MASUK 50`, `KELUAR 30`, `STOK`, `LAPORAN`, `BANTUAN` dari HP biasa
- **Web Dashboard** — login admin, input manual, riwayat transaksi, kelola pekerja, laporan CSV + print PDF
- **Google Sheets Sync** — mirror otomatis tiap 10 menit (satu arah: DB → Sheets)
- **Stok real-time** — dihitung dari ledger transaksi, tidak pernah di-set manual
- **Harga fleksibel** — disimpan di database, bisa diubah dari panel pengaturan

## Stack

| Komponen | Teknologi |
|---|---|
| Web server | Nginx |
| Backend | PHP 8.2 (vanilla) |
| Database | MySQL 8 (`db: eskristal`) |
| WA Gateway | WAHA (Docker) |
| Sync Sheets | Python 3 + gspread + cron |

---

## Prasyarat

Siapkan di VPS/server sebelum instalasi:

- Ubuntu 22.04 / Debian 12
- Nginx
- PHP 8.2 + php8.2-fpm + php8.2-mysql + php8.2-curl
- MySQL 8
- Docker + Docker Compose
- Python 3.10+ + pip
- Certbot (`python3-certbot-nginx`)
- Domain yang sudah diarahkan ke IP server (A record)

---

## Instalasi dari Awal

### 1. Clone repositori

```bash
sudo mkdir -p /var/www/eskristal
sudo chown $USER:$USER /var/www/eskristal
git clone <url-repo> /var/www/eskristal
cd /var/www/eskristal
```

### 2. Buat file konfigurasi `.env`

```bash
cp config/.env.example config/.env
nano config/.env
```

Isi semua nilai berikut:

```env
# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=eskristal
DB_USER=eskristal_user
DB_PASS=password_kuat_di_sini

# WAHA
WAHA_URL=http://localhost:3000
WAHA_SESSION=default
WAHA_API_KEY=api_key_waha_di_sini

# Keamanan webhook
WA_WEBHOOK_SECRET=secret_panjang_acak_di_sini

# Google Sheets
GOOGLE_SPREADSHEET_ID=id_spreadsheet_dari_url
GOOGLE_SERVICE_ACCOUNT_PATH=sync/service-account.json

# Aplikasi
APP_TIMEZONE=Asia/Makassar
SESSION_LIFETIME=3600
```

> **Penting:** `config/.env` tidak boleh masuk git. Sudah tercantum di `.gitignore`.

### 3. Buat database MySQL

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE eskristal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'eskristal_user'@'localhost' IDENTIFIED BY 'password_kuat_di_sini';
GRANT ALL PRIVILEGES ON eskristal.* TO 'eskristal_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Lalu jalankan skema:

```bash
mysql -u eskristal_user -p eskristal < setup/schema.sql
```

### 4. Buat akun admin pertama

Generate hash password:

```bash
php -r "echo password_hash('passwordkamu', PASSWORD_BCRYPT) . PHP_EOL;"
```

Salin output hash, lalu masukkan ke database:

```bash
mysql -u eskristal_user -p eskristal
```

```sql
INSERT INTO admin_users (username, password_hash, nama)
VALUES ('admin', '$2y$12$HASH_DARI_LANGKAH_DI_ATAS', 'Administrator');
EXIT;
```

### 5. Konfigurasi Nginx

```bash
sudo cp setup/nginx.conf /etc/nginx/sites-available/eskristal
sudo ln -s /etc/nginx/sites-available/eskristal /etc/nginx/sites-enabled/

# Edit nama domain jika berbeda
sudo nano /etc/nginx/sites-available/eskristal

# Test & reload
sudo nginx -t
sudo systemctl reload nginx
```

### 6. Pasang HTTPS (Certbot)

```bash
sudo certbot --nginx -d eskristal.ajengmedia.com
```

Certbot otomatis mengisi blok TLS di konfigurasi Nginx. Auto-renew sudah aktif via systemd timer bawaan Certbot.

### 7. Jalankan WAHA (WhatsApp Gateway)

```bash
# Mulai container
docker compose -f setup/docker-compose.waha.yml up -d

# Cek status
docker logs waha-eskristal -f
```

Buka `http://IP-SERVER:3000/dashboard` di browser → klik **Start Session** → scan QR Code dengan HP bot. Sesi tersimpan di volume Docker — tidak perlu scan ulang setelah restart.

Setelah sesi aktif, konfigurasi webhook di WAHA:
- **Webhook URL:** `https://eskristal.ajengmedia.com/api/wa-webhook.php`
- **Events:** centang `message`
- **API Key:** isi sesuai `WA_WEBHOOK_SECRET` di `.env`

### 8. Pasang sinkronisasi Google Sheets

**Persiapan di Google Cloud Console:**
1. Buat project baru → aktifkan **Google Sheets API**
2. Buat **Service Account** → download key sebagai JSON
3. Simpan file JSON ke `sync/service-account.json`
4. Buat Google Spreadsheet baru → catat ID dari URL
5. Share spreadsheet ke email service account dengan akses **Editor**
6. Isi `GOOGLE_SPREADSHEET_ID` di `config/.env`

**Install dependensi Python:**

```bash
pip install -r sync/requirements.txt
```

**Test sekali jalan:**

```bash
python3 sync/sync_eskristal.py
```

Pastikan 3 sheet (`HARIAN`, `REKAP BULANAN`, `STOK`) terbentuk di spreadsheet.

**Pasang cron (tiap 10 menit):**

```bash
sudo cp sync/eskristal.cron /etc/cron.d/eskristal
sudo chmod 644 /etc/cron.d/eskristal
```

### 9. Pasang backup otomatis

```bash
sudo cp setup/backup.cron /etc/cron.d/eskristal-backup
sudo chmod 644 /etc/cron.d/eskristal-backup

# Test sekali jalan
bash setup/backup.sh
```

Backup disimpan di `/var/backups/eskristal/`, otomatis hapus file lebih dari 7 hari.

### 10. Atur permission file

```bash
sudo chown -R www-data:www-data /var/www/eskristal
sudo chmod -R 755 /var/www/eskristal
sudo chmod 600 /var/www/eskristal/config/.env
sudo chmod 600 /var/www/eskristal/sync/service-account.json
sudo chmod +x /var/www/eskristal/setup/backup.sh
```

---

## Verifikasi Instalasi

```bash
# 1. Dashboard bisa diakses
curl -I https://eskristal.ajengmedia.com/admin/login.php
# → HTTP/2 200

# 2. Folder sensitif diblokir
curl https://eskristal.ajengmedia.com/config/config.php
# → 403 Forbidden

# 3. API branding
curl https://eskristal.ajengmedia.com/api/branding.php
# → {"nama_usaha":"BITNET INDONESIA","nama_produk":"Es Kristal","harga_satuan":20000,...}

# 4. Webhook (ganti SECRET)
curl -X POST https://eskristal.ajengmedia.com/api/wa-webhook.php \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: SECRET" \
  -d '{"event":"message","session":"default","payload":{"from":"628111@c.us","body":"STOK"}}'
# → {"status":"ok"}

# 5. Sync Sheets
python3 /var/www/eskristal/sync/sync_eskristal.py
mysql -u eskristal_user -p eskristal -e "SELECT * FROM sync_log ORDER BY id DESC LIMIT 1;"
# → status: sukses
```

---

## Struktur Folder

```
/var/www/eskristal/
├── admin/              # Panel admin (PHP)
│   └── includes/       # Auth & layout bersama
├── api/                # Endpoint publik & webhook
│   └── lib/            # Parser & sender WA
├── config/
│   ├── config.php      # Koneksi DB & konstanta
│   └── .env            # Kredensial (TIDAK masuk git)
├── docs/               # Blueprint & dokumentasi
├── includes/           # Fungsi PHP bersama (Nginx-blocked)
├── public/
│   ├── css/style.css   # Mobile-first CSS
│   └── js/main.js      # Chart.js + helper
├── setup/
│   ├── schema.sql              # Skema database
│   ├── nginx.conf              # Konfigurasi Nginx
│   ├── docker-compose.waha.yml # WAHA Docker
│   ├── backup.sh               # Script backup
│   └── backup.cron             # Cron backup
└── sync/
    ├── sync_eskristal.py       # Script sinkronisasi Sheets
    ├── requirements.txt        # Dependensi Python
    ├── eskristal.cron          # Cron sync
    └── service-account.json   # Google SA key (TIDAK masuk git)
```

---

## Perintah WA Bot

| Perintah | Contoh | Fungsi |
|---|---|---|
| `MASUK [jumlah]` | `MASUK 100` | Catat stok masuk (produksi) |
| `KELUAR [jumlah]` | `KELUAR 50` | Catat stok keluar (penjualan) |
| `STOK` | `STOK` | Cek stok saat ini |
| `LAPORAN` | `LAPORAN` | Rekap hari ini |
| `BANTUAN` | `BANTUAN` | Daftar perintah |

Nomor WA pekerja harus didaftarkan terlebih dahulu di panel **Admin → Pekerja** dengan format `628xxxxxxxxx`.

---

## Troubleshooting

**WA bot tidak membalas**
- Cek sesi WAHA aktif: `docker logs waha-eskristal -f`
- Cek webhook terdaftar di WAHA dashboard
- Cek `X-Api-Key` di WAHA config == `WA_WEBHOOK_SECRET` di `.env`
- Cek log Nginx: `sudo tail -f /var/log/nginx/eskristal-error.log`

**Sync Sheets gagal**
- Cek `SELECT * FROM sync_log ORDER BY id DESC LIMIT 3;` untuk pesan error
- Pastikan email service account punya akses Editor ke spreadsheet
- Jalankan manual: `python3 /var/www/eskristal/sync/sync_eskristal.py`

**Login admin tidak bisa**
- Pastikan `password_hash` di DB dibuat dengan `PASSWORD_BCRYPT`
- Cek `SELECT aktif FROM admin_users WHERE username='admin';` → harus `1`
- Setelah 5 gagal dalam 5 menit, akun diblokir 5 menit

**Backup gagal**
- Cek log: `tail -f /var/log/eskristal-backup.log`
- Pastikan `mysqldump` terinstall: `which mysqldump`
- Pastikan direktori `/var/backups/eskristal/` bisa ditulis oleh `root`

---

## Lisensi

Hak cipta © 2026 BITNET INDONESIA. Seluruh hak dilindungi.
