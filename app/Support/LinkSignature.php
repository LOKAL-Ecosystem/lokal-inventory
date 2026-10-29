<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Tanda tangan antara lokal-pos dan lokal-inventory (penautan otomatis & satu login, kontrak v2.3).
 * File ini SAMA di kedua repo; ubah di keduanya bersamaan.
 *
 * Permintaan server-ke-server:
 *   X-Lokal-Timestamp: unix detik
 *   X-Lokal-Signature: hex(HMAC-SHA256(timestamp + "." + body_mentah, LOKAL_LINK_SECRET))
 *   Ditolak bila selisih waktu > 300 detik atau tanda tangan salah.
 *
 * Token satu login (berlaku 60 detik, sekali pakai lewat nonce di penerima):
 *   base64url(json payload) + "." + hex(HMAC-SHA256(base64url(json payload), LOKAL_LINK_SECRET))
 *   payload: {pos_outlet_id, pos_user_id, name, email, role, is_active, exp, nonce}
 */
class LinkSignature
{
    public const HEADER_TIMESTAMP = 'X-Lokal-Timestamp';
    public const HEADER_SIGNATURE = 'X-Lokal-Signature';
    public const TOLERANCE_SECONDS = 300;
    public const SSO_TTL_SECONDS = 60;

    public static function secret(): ?string
    {
        $secret = config('lokal.link.secret');

        return filled($secret) ? (string) $secret : null;
    }

    /** Penautan mati dengan tenang bila secret kosong. */
    public static function enabled(): bool
    {
        return self::secret() !== null;
    }

    public static function sign(string $body, int $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    /**
     * Header untuk permintaan keluar dengan body mentah $body.
     *
     * @return array<string, string>
     */
    public static function headers(string $body, ?int $timestamp = null): array
    {
        $timestamp ??= time();

        return [
            self::HEADER_TIMESTAMP => (string) $timestamp,
            self::HEADER_SIGNATURE => self::sign($body, $timestamp, (string) self::secret()),
        ];
    }

    public static function verify(Request $request): bool
    {
        $secret = self::secret();
        $timestamp = $request->header(self::HEADER_TIMESTAMP);
        $signature = $request->header(self::HEADER_SIGNATURE);

        if ($secret === null || ! is_string($timestamp) || ! ctype_digit($timestamp) || ! is_string($signature)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        return hash_equals(self::sign($request->getContent(), (int) $timestamp, $secret), $signature);
    }

    /**
     * @param  array<string, mixed>  $payload  tanpa exp/nonce; keduanya ditambahkan di sini
     */
    public static function makeToken(array $payload, ?int $now = null): string
    {
        $payload['exp'] = ($now ?? time()) + self::SSO_TTL_SECONDS;
        $payload['nonce'] ??= bin2hex(random_bytes(16));
        $encoded = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $encoded . '.' . hash_hmac('sha256', $encoded, (string) self::secret());
    }

    /**
     * Payload token bila tanda tangan benar dan belum kedaluwarsa; null bila tidak.
     * Pengecekan nonce sekali pakai dilakukan pemanggil.
     *
     * @return array<string, mixed>|null
     */
    public static function readToken(string $token, ?int $now = null): ?array
    {
        $secret = self::secret();
        $parts = explode('.', $token);

        if ($secret === null || count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;

        if (! hash_equals(hash_hmac('sha256', $encoded, $secret), $signature)) {
            return null;
        }

        $payload = json_decode((string) self::base64UrlDecode($encoded), true);

        if (! is_array($payload) || ! isset($payload['exp'], $payload['nonce']) || (int) $payload['exp'] < ($now ?? time())) {
            return null;
        }

        return $payload;
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
