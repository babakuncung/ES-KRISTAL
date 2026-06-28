<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'nama_usaha'   => get_setting('nama_usaha'),
    'nama_produk'  => get_setting('nama_produk'),
    'harga_satuan' => harga_satuan(),
    'tagline'      => get_setting('tagline'),
], JSON_UNESCAPED_UNICODE);
