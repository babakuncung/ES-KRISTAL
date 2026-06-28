# CLAUDE.md — Proyek ESKRISTAL

Sistem manajemen stok Es Kristal untuk BITNET INDONESIA.
Spesifikasi lengkap ada di `docs/blueprint-eskristal.md` — **baca file itu sebelum mengerjakan fitur apa pun.**

## Ringkasan

- **Produk tunggal**: Es Kristal 20 kg seharga Rp20.000/balok. Harga & nama disimpan di tabel `system_settings` — JANGAN hardcode di kode.
- **Dua antarmuka**: WA bot (untuk pekerja, perintah teks polos) + web dashboard (untuk admin, mobile-first).
- **Stack**: PHP 8.2 + Nginx + MySQL (db `eskristal`); WAHA (Docker) untuk WhatsApp; Python + gspread untuk sinkronisasi Google Sheets.
- **Sumber kebenaran**: database. Stok DIHITUNG dari tabel `transaksi_stok` (ledger), tidak pernah di-set manual.

## Aturan teknis

- Ikuti struktur folder & skema database persis seperti di blueprint (Bagian 4 & 5).
- Path URL tanpa prefix `/eskristal/` (mis. `/api/`, `/admin/`). Cookie path `/`. Root Nginx di `/var/www/eskristal`.
- Semua logika bisnis ada di PHP. WAHA hanya jembatan WhatsApp: pesan masuk → webhook ke `/api/wa-webhook.php`; balasan dikirim via REST API WAHA (`POST /api/sendText`). Tidak ada kode Node custom.
- Perintah WA: `MASUK`, `KELUAR`, `STOK`, `LAPORAN`, `BANTUAN`. Balasan singkat, ramah (sapaan "Kak"), TANPA emoji di dalam kode JS (menyebabkan SyntaxError).
- Sinkronisasi Google Sheets satu arah saja (DB → Sheets) lewat cron.
- Gunakan PHP vanilla kecuali ada alasan kuat menambah dependency.

## KEAMANAN (wajib)

- JANGAN PERNAH commit kredensial. Semua rahasia di `config/.env` (tidak masuk git). Sediakan `.env.example` sebagai template.
- File yang tidak boleh masuk git: `config/.env`, `sync/service-account.json`, `vendor/`, `node_modules/`, log, isi `public/uploads/`.
- Nginx harus memblokir akses ke `config/`, `includes/`, `sync/`, `setup/`.
- Validasi ketat semua input dari webhook WA (whitelist perintah, cek tipe & panjang). Verifikasi request benar berasal dari WAHA (cek API key/header).
- Password admin di-hash (bcrypt/argon2), bukan plaintext.

## Cara kerja: bangun bertahap

Bangun per fase sesuai roadmap blueprint (Bagian 10):

1. **Fase 1** — skema database (`setup/schema.sql`) + struktur folder
2. **Fase 2** — web dashboard (login, dashboard stok, riwayat, input manual, kelola pekerja, pengaturan, laporan + export)
3. **Fase 3** — WA bot (webhook handler, parsing perintah, validasi, balasan)
4. **Fase 4** — sinkronisasi Google Sheets (`sync/sync_eskristal.py` + cron)
5. **Fase 5** — testing & hardening

**Kerjakan SATU fase per waktu.** Tampilkan rencana lebih dulu (Plan Mode). Setelah satu fase selesai dan teruji, BERHENTI agar bisa di-review dan di-commit sebelum lanjut ke fase berikutnya.

## Konvensi commit

Commit per fase/fitur dengan pesan jelas, mis. `"Fase 1: skema database & struktur folder"`.
