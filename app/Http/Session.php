<?php

declare(strict_types=1);

namespace App\Http;

use App\App;

/**
 * Sesi PHP. Nama cookie berbeda per website (SESSION_NAME) supaya beberapa
 * proyek di localhost (port berbeda) tidak saling menimpa sesi.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $lifetime = (int) App::config('session.lifetime');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        session_name((string) App::config('session.name'));
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /**
     * ID sesi keranjang (32 hex). Dipakai pada key cart:{session_id}:{branch_id}.
     * Dibuat terpisah dari session_id PHP agar tidak berubah saat login admin.
     */
    public static function cartSessionId(): string
    {
        if (empty($_SESSION['cart_sid']) || !preg_match('/^[a-f0-9]{32}$/', (string) $_SESSION['cart_sid'])) {
            $_SESSION['cart_sid'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['cart_sid'];
    }

    /** Hash pemilik pesanan. Yang disimpan di pesanan hanya hash, bukan ID sesi asli. */
    public static function ownerHash(): string
    {
        return hash('sha256', 'owner|' . self::cartSessionId());
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string,message:string}> */
    public static function takeFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public static function setOld(array $input): void
    {
        $_SESSION['_old'] = $input;
    }

    public static function takeOld(): array
    {
        $o = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
        return is_array($o) ? $o : [];
    }
}
