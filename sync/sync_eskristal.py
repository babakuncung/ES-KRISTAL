#!/usr/bin/env python3
"""
sync_eskristal.py — Sinkronisasi DB eskristal -> Google Sheets (satu arah).
Dijalankan via cron tiap 10 menit.
"""

import os
import sys
from datetime import datetime

import mysql.connector
import gspread
from google.oauth2.service_account import Credentials


# ── Tentukan root proyek (dua level di atas sync/) ───────────────────────────
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))


# ── 1. Load config/.env ───────────────────────────────────────────────────────
def load_env(path: str) -> dict:
    env = {}
    try:
        with open(path, encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith('#') or '=' not in line:
                    continue
                key, val = line.split('=', 1)
                env[key.strip()] = val.strip()
    except FileNotFoundError:
        pass
    return env


_env = load_env(os.path.join(ROOT, 'config', '.env'))


def cfg(key: str, default: str = '') -> str:
    return _env.get(key) or os.environ.get(key, default)


DB_HOST   = cfg('DB_HOST', '127.0.0.1')
DB_PORT   = int(cfg('DB_PORT', '3306'))
DB_NAME   = cfg('DB_NAME', 'eskristal')
DB_USER   = cfg('DB_USER')
DB_PASS   = cfg('DB_PASS')

GSHEET_ID = cfg('GOOGLE_SPREADSHEET_ID')
SA_PATH   = cfg('GOOGLE_SERVICE_ACCOUNT_PATH',
                os.path.join(ROOT, 'sync', 'service-account.json'))
if not os.path.isabs(SA_PATH):
    SA_PATH = os.path.join(ROOT, SA_PATH)


# ── 2. Koneksi MySQL ──────────────────────────────────────────────────────────
def buat_koneksi():
    return mysql.connector.connect(
        host=DB_HOST, port=DB_PORT,
        database=DB_NAME, user=DB_USER, password=DB_PASS,
        connection_timeout=10,
    )


# ── 3. Catat ke sync_log ─────────────────────────────────────────────────────
def catat_sync(conn, status: str, jumlah_baris: int, keterangan: str = ''):
    try:
        cur = conn.cursor()
        cur.execute(
            "INSERT INTO sync_log (status, jumlah_baris, keterangan) VALUES (%s, %s, %s)",
            (status, jumlah_baris, keterangan[:65535] if keterangan else None),
        )
        conn.commit()
        cur.close()
    except Exception:
        pass  # jangan biarkan log gagal membunuh proses


# ── 4. Auth Google Sheets ─────────────────────────────────────────────────────
SCOPES = ['https://www.googleapis.com/auth/spreadsheets']


def buka_spreadsheet():
    creds = Credentials.from_service_account_file(SA_PATH, scopes=SCOPES)
    gc    = gspread.authorize(creds)
    return gc.open_by_key(GSHEET_ID)


# ── 5. Ambil atau buat worksheet ──────────────────────────────────────────────
def get_or_create_ws(sh, nama: str):
    try:
        return sh.worksheet(nama)
    except gspread.WorksheetNotFound:
        return sh.add_worksheet(title=nama, rows=5000, cols=10)


# ── 6. Sync sheet HARIAN ──────────────────────────────────────────────────────
def sync_harian(conn, sh) -> int:
    cur = conn.cursor()
    cur.execute("""
        SELECT
            DATE(t.created_at)      AS tanggal,
            TIME(t.created_at)      AS waktu,
            t.tipe,
            t.jumlah,
            t.nilai_rupiah,
            COALESCE(p.nama, '(admin)') AS pekerja,
            t.sumber
        FROM transaksi_stok t
        LEFT JOIN pekerja p ON p.id = t.pekerja_id
        ORDER BY t.created_at ASC
    """)
    rows = cur.fetchall()
    cur.close()

    header = ['Tanggal', 'Waktu', 'Tipe', 'Jumlah', 'Nilai (Rp)', 'Pekerja', 'Sumber']
    data   = [
        [
            str(r[0]),          # tanggal
            str(r[1]),          # waktu (timedelta → str)
            r[2],               # tipe
            r[3],               # jumlah (int)
            r[4],               # nilai_rupiah (int)
            r[5],               # pekerja
            r[6],               # sumber
        ]
        for r in rows
    ]

    ws = get_or_create_ws(sh, 'HARIAN')
    ws.clear()
    ws.append_row(header, value_input_option='USER_ENTERED')
    if data:
        ws.append_rows(data, value_input_option='USER_ENTERED')

    return len(data)


# ── 7. Sync sheet REKAP BULANAN ───────────────────────────────────────────────
def sync_rekap_bulanan(conn, sh) -> int:
    cur = conn.cursor()
    cur.execute("""
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS bulan,
            COALESCE(SUM(CASE WHEN tipe='masuk'   THEN jumlah ELSE 0 END), 0) AS masuk,
            COALESCE(SUM(CASE WHEN tipe='keluar'  THEN jumlah ELSE 0 END), 0) AS keluar,
            COALESCE(SUM(CASE WHEN tipe='koreksi' THEN jumlah ELSE 0 END), 0) AS koreksi,
            COALESCE(SUM(CASE WHEN tipe='keluar'  THEN nilai_rupiah ELSE 0 END), 0) AS omzet
        FROM transaksi_stok
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY bulan ASC
    """)
    rows = cur.fetchall()
    cur.close()

    header = ['Bulan', 'Masuk', 'Keluar', 'Koreksi', 'Omzet (Rp)']
    data   = [[r[0], r[1], r[2], r[3], r[4]] for r in rows]

    ws = get_or_create_ws(sh, 'REKAP BULANAN')
    ws.clear()
    ws.append_row(header, value_input_option='USER_ENTERED')
    if data:
        ws.append_rows(data, value_input_option='USER_ENTERED')

    return len(data)


# ── 8. Sync sheet STOK ────────────────────────────────────────────────────────
def sync_stok(conn, sh) -> int:
    cur = conn.cursor()

    # Stok sekarang
    cur.execute("""
        SELECT
            COALESCE(SUM(CASE WHEN tipe='masuk'   THEN jumlah ELSE 0 END), 0)
          - COALESCE(SUM(CASE WHEN tipe='keluar'  THEN jumlah ELSE 0 END), 0)
          + COALESCE(SUM(CASE WHEN tipe='koreksi' THEN jumlah ELSE 0 END), 0)
        FROM transaksi_stok
    """)
    stok_sekarang = int(cur.fetchone()[0])

    # Ringkasan hari ini
    cur.execute("""
        SELECT
            COALESCE(SUM(CASE WHEN tipe='masuk'  THEN jumlah ELSE 0 END), 0),
            COALESCE(SUM(CASE WHEN tipe='keluar' THEN jumlah ELSE 0 END), 0),
            COALESCE(SUM(CASE WHEN tipe='keluar' THEN nilai_rupiah ELSE 0 END), 0)
        FROM transaksi_stok
        WHERE DATE(created_at) = CURDATE()
    """)
    r = cur.fetchone()
    masuk_hari  = int(r[0])
    keluar_hari = int(r[1])
    omzet_hari  = int(r[2])
    cur.close()

    sekarang = datetime.now().strftime('%Y-%m-%d %H:%M')

    data = [
        ['Keterangan',       'Nilai'],
        ['Update Terakhir',  sekarang],
        ['Stok Sekarang',    f'{stok_sekarang:,} balok'.replace(',', '.')],
        ['Masuk Hari Ini',   f'{masuk_hari:,} balok'.replace(',', '.')],
        ['Keluar Hari Ini',  f'{keluar_hari:,} balok'.replace(',', '.')],
        ['Omzet Hari Ini',   f'Rp {omzet_hari:,}'.replace(',', '.')],
    ]

    ws = get_or_create_ws(sh, 'STOK')
    ws.clear()
    ws.append_rows(data, value_input_option='USER_ENTERED')

    return len(data)


# ── Main ──────────────────────────────────────────────────────────────────────
def main():
    if not GSHEET_ID:
        print('[ERROR] GOOGLE_SPREADSHEET_ID tidak diset di config/.env', file=sys.stderr)
        sys.exit(1)

    conn       = None
    total_rows = 0
    errors     = []

    try:
        conn = buat_koneksi()
        sh   = buka_spreadsheet()

        for nama_sheet, fn_sync in [
            ('HARIAN',        sync_harian),
            ('REKAP BULANAN', sync_rekap_bulanan),
            ('STOK',          sync_stok),
        ]:
            try:
                n = fn_sync(conn, sh)
                total_rows += n
                print(f'[OK] {nama_sheet}: {n} baris')
            except Exception as e:
                msg = f'{nama_sheet}: {e}'
                errors.append(msg)
                print(f'[GAGAL] {msg}', file=sys.stderr)

        if errors:
            catat_sync(conn, 'gagal', total_rows, '; '.join(errors))
        else:
            catat_sync(conn, 'sukses', total_rows, f'Sync OK — {total_rows} baris total')

    except Exception as e:
        print(f'[ERROR] {e}', file=sys.stderr)
        if conn:
            catat_sync(conn, 'gagal', 0, str(e))
        sys.exit(1)
    finally:
        if conn and conn.is_connected():
            conn.close()


if __name__ == '__main__':
    main()
