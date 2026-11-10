<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Header keamanan dasar untuk semua halaman PHP.
 *
 * Normal (bawaan, dan SELALU di production):
 *   X-Frame-Options: DENY  +  CSP frame-ancestors 'none'  -> website tidak bisa dibingkai (anti-clickjacking).
 *
 * Mode preview lokal (LOCAL_PREVIEW_FRAME=true DAN APP_ENV=local):
 *   hanya aturan pembingkaian yang dilonggarkan agar alat seperti Mobile Preview bisa menampilkan
 *   website di dalam iframe. X-Frame-Options dihilangkan (tidak punya daftar izin) dan frame-ancestors
 *   diisi dari LOCAL_PREVIEW_FRAME_ANCESTORS. Direktif CSP lain tidak berubah.
 */
final class SecurityHeaders
{
    /** Direktif CSP selain frame-ancestors. Sama persis di mode normal maupun preview. */
    public const CSP_BASE = "default-src 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com; "
        . "font-src https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; form-action 'self'";

    /** Dipakai jika preview aktif tetapi LOCAL_PREVIEW_FRAME_ANCESTORS kosong. */
    public const DEFAULT_PREVIEW_ANCESTORS = "'self' http://localhost:* http://127.0.0.1:* vscode-webview: chrome-extension:";

    /**
     * @param array{env?:string} $app       konfigurasi 'app'
     * @param array{frame_enabled?:bool, frame_ancestors?:string} $preview konfigurasi 'preview'
     * @return list<string> baris header siap dikirim
     */
    public static function build(array $app, array $preview): array
    {
        $headers = [
            'X-Content-Type-Options: nosniff',
            'Referrer-Policy: same-origin',
        ];
        $ancestors = self::previewAncestors($app, $preview);
        if ($ancestors === null) {
            $headers[] = 'X-Frame-Options: DENY';
            $headers[] = 'Content-Security-Policy: ' . self::CSP_BASE . "; frame-ancestors 'none'";
        } else {
            $headers[] = 'Content-Security-Policy: ' . self::CSP_BASE . '; frame-ancestors ' . $ancestors;
        }
        return $headers;
    }

    /** null = perlindungan normal. String = daftar frame-ancestors untuk preview lokal. */
    public static function previewAncestors(array $app, array $preview): ?string
    {
        if (empty($preview['frame_enabled']) || ($app['env'] ?? '') !== 'local') {
            return null;
        }
        $raw = trim((string) ($preview['frame_ancestors'] ?? ''));
        $tokens = preg_split('/\s+/', $raw === '' ? self::DEFAULT_PREVIEW_ANCESTORS : $raw) ?: [];
        foreach ($tokens as $t) {
            if (!self::isAllowedSource($t)) {
                // Nilai tidak sah (bisa menyisipkan direktif lain atau mengizinkan semua situs): tetap blokir.
                error_log("LOCAL_PREVIEW_FRAME_ANCESTORS ditolak karena sumber tidak sah: {$t}");
                return null;
            }
        }
        return implode(' ', $tokens);
    }

    /**
     * Hanya menerima 'self', scheme-source (contoh vscode-webview:), atau host-source http(s)://host[:port|:*].
     * Menolak *, http:, https:, data:, 'none', dan apa pun yang mengandung ; , ' atau spasi aneh.
     */
    private static function isAllowedSource(string $t): bool
    {
        if ($t === "'self'") {
            return true;
        }
        if (in_array(strtolower($t), ['*', 'http:', 'https:', 'data:', 'blob:', 'ws:', 'wss:'], true)) {
            return false;
        }
        if (preg_match('/^[a-z][a-z0-9+.-]*:$/', $t)) {
            return true; // scheme-source, misalnya vscode-webview: atau chrome-extension:
        }
        return (bool) preg_match('#^https?://(\*\.)?[A-Za-z0-9.-]+(:(\d{1,5}|\*))?$#', $t);
    }
}
