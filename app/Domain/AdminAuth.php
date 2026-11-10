<?php

declare(strict_types=1);

namespace App\Domain;

use App\App;
use App\Http\View;

/**
 * Login admin cabang.
 * - Username & HASH password disimpan di .env (password asli tidak pernah disimpan).
 * - branch_id admin diambil dari konfigurasi server, BUKAN dari browser.
 * - Peran selalu admin_cabang: boleh melihat stok global, tidak boleh mengubahnya.
 */
final class AdminAuth
{
    private const MAX_FAILS = 5;
    private const LOCK_SECONDS = 60;

    public static function isConfigured(): bool
    {
        return (string) App::config('admin.password_hash') !== '';
    }

    /** @return string|null pesan error, atau null jika berhasil */
    public static function attempt(string $username, string $password): ?string
    {
        if (!self::isConfigured()) {
            return 'Password admin belum diatur. Jalankan: php scripts/set-admin-password.php';
        }
        $lockedUntil = (int) ($_SESSION['_admin_lock_until'] ?? 0);
        if ($lockedUntil > time()) {
            return 'Terlalu banyak percobaan gagal. Tunggu ' . ($lockedUntil - time()) . ' detik.';
        }
        $okUser = hash_equals((string) App::config('admin.username'), $username);
        $okPass = password_verify($password, (string) App::config('admin.password_hash'));
        if (!$okUser || !$okPass) {
            usleep(300000); // perlambat tebakan password
            $fails = (int) ($_SESSION['_admin_fails'] ?? 0) + 1;
            $_SESSION['_admin_fails'] = $fails;
            if ($fails >= self::MAX_FAILS) {
                $_SESSION['_admin_lock_until'] = time() + self::LOCK_SECONDS;
                $_SESSION['_admin_fails'] = 0;
            }
            return 'Username atau password salah.';
        }
        session_regenerate_id(true);
        unset($_SESSION['_admin_fails'], $_SESSION['_admin_lock_until']);
        $_SESSION['admin'] = [
            'username'  => $username,
            'branch_id' => (string) App::config('branch.id'),
            'role'      => (string) App::config('admin.role'),
            'login_at'  => time(),
            'last_seen' => time(),
        ];
        return null;
    }

    public static function user(): ?array
    {
        $a = $_SESSION['admin'] ?? null;
        if (!is_array($a)) {
            return null;
        }
        // Sesi admin dari website/cabang lain tidak berlaku di sini.
        if (($a['branch_id'] ?? '') !== App::config('branch.id')) {
            unset($_SESSION['admin']);
            return null;
        }
        if (time() - (int) ($a['last_seen'] ?? 0) > (int) App::config('session.admin_idle_limit')) {
            unset($_SESSION['admin']);
            return null;
        }
        $_SESSION['admin']['last_seen'] = time();
        return $a;
    }

    /** Wajib login; jika belum, arahkan ke halaman login. */
    public static function require(): array
    {
        $user = self::user();
        if ($user === null) {
            View::redirect('/admin/login');
        }
        return $user;
    }

    public static function logout(): void
    {
        unset($_SESSION['admin']);
        session_regenerate_id(true);
    }

    /** Konteks akses untuk layanan data. */
    public static function access(array $user): array
    {
        return ['type' => 'branch_admin', 'branch_id' => $user['branch_id']];
    }

    public static function actor(array $user): array
    {
        return ['branch_id' => $user['branch_id'], 'username' => $user['username'], 'role' => $user['role']];
    }
}
