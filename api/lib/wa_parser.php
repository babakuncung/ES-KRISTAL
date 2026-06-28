<?php

declare(strict_types=1);

/**
 * Parse teks pesan WA menjadi perintah terstruktur.
 *
 * Return array dengan key:
 *   'cmd'           => string  (MASUK|KELUAR|STOK|LAPORAN|BANTUAN|FORMAT_ERROR|UNKNOWN)
 *   'jumlah'        => int     (hanya untuk MASUK/KELUAR)
 *   'perintah_asli' => string  (hanya untuk FORMAT_ERROR)
 */
function parse_perintah(string $teks): array {
    $teks   = trim($teks);
    $upper  = mb_strtoupper($teks, 'UTF-8');
    $tokens = preg_split('/\s+/', $upper, 2);
    $cmd    = $tokens[0] ?? '';
    $sisa   = trim($tokens[1] ?? '');

    $whitelist_tanpa_arg = ['STOK', 'LAPORAN', 'BANTUAN'];
    $whitelist_dengan_arg = ['MASUK', 'KELUAR'];

    if (in_array($cmd, $whitelist_tanpa_arg, true)) {
        return ['cmd' => $cmd];
    }

    if (in_array($cmd, $whitelist_dengan_arg, true)) {
        if ($sisa === '' || !ctype_digit($sisa) || (int)$sisa <= 0) {
            return ['cmd' => 'FORMAT_ERROR', 'perintah_asli' => $cmd];
        }
        return ['cmd' => $cmd, 'jumlah' => (int)$sisa];
    }

    return ['cmd' => 'UNKNOWN'];
}
