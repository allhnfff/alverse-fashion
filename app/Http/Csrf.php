<?php

declare(strict_types=1);

namespace App\Http;

/** Token CSRF: setiap form POST wajib membawa token milik sesi ini. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(): void
    {
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $sent)) {
            throw new HttpException(419, 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        }
    }
}
