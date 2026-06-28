<?php

declare(strict_types=1);

function kirim_wa(string $chat_id, string $teks): void {
    $url  = rtrim(WAHA_URL, '/') . '/api/sendText';
    $body = json_encode([
        'chatId'  => $chat_id,
        'text'    => $teks,
        'session' => WAHA_SESSION,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Api-Key: ' . WAHA_API_KEY,
        ],
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code < 200 || $code >= 300) {
        // Gagal silently — log saja agar tidak mengganggu HTTP 200 ke WAHA
        log_activity('system', 'wa_send_gagal', "chat_id=$chat_id code=$code err=$err");
    }
}
