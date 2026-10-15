<?php
declare(strict_types=1);

/** Verificação em duas etapas (TOTP, RFC 6238) — compatível com Google Authenticator. */

function base32_encode_str(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode_str(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32) ?? '');
    $bits = '';
    foreach (str_split($b32) as $c) {
        $pos = strpos($alphabet, $c);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_new_secret(): string
{
    return base32_encode_str(random_bytes(20));
}

function totp_code(string $secret, int $timeSlice): string
{
    $key = base32_decode_str($secret);
    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $value = ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF);
    return str_pad((string)($value % 1_000_000), 6, '0', STR_PAD_LEFT);
}

function totp_verify(string $secret, string $code): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return false;
    }
    $slice = intdiv(time(), 30);
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(totp_code($secret, $slice + $i), $code)) {
            return true;
        }
    }
    return false;
}

function totp_uri(string $secret, string $email): string
{
    $issuer = rawurlencode((string)config('site_nome'));
    return 'otpauth://totp/' . $issuer . ':' . rawurlencode($email)
        . '?secret=' . $secret . '&issuer=' . $issuer . '&digits=6&period=30';
}
