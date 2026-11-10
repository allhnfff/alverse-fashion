<?php

declare(strict_types=1);

/**
 * Mengatur password admin cabang TANPA menuliskannya di file mana pun.
 * Yang disimpan ke .env hanya HASH (password_hash / bcrypt).
 *
 *     php scripts/set-admin-password.php
 *     APP_ENV_FILE=.env.uji php scripts/set-admin-password.php   (untuk instance uji)
 *
 * Jangan kirim password aslimu ke siapa pun, termasuk ke chat.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\App;
use App\Support\Env;

$envFile = Env::resolveFile(App::root());
if (!is_file($envFile)) {
    fwrite(STDERR, "File " . basename($envFile) . " belum ada. Salin dulu dari contoh (lihat README).\n");
    exit(1);
}

function ask_hidden(string $prompt): string
{
    echo $prompt;
    $isWindows = DIRECTORY_SEPARATOR === '\\';
    if (!$isWindows && function_exists('shell_exec') && trim((string) shell_exec('command -v stty')) !== '') {
        shell_exec('stty -echo');
        $value = rtrim((string) fgets(STDIN), "\r\n");
        shell_exec('stty echo');
        echo PHP_EOL;
        return $value;
    }
    // Windows: input tidak bisa disembunyikan dengan mudah tanpa ekstensi tambahan.
    echo "(peringatan: ketikan terlihat di layar) ";
    return rtrim((string) fgets(STDIN), "\r\n");
}

echo "Admin untuk cabang: " . App::config('branch.name') . " (" . App::config('branch.id') . ")\n";
echo "Username admin    : " . App::config('admin.username') . "  (ubah ADMIN_USERNAME di " . basename($envFile) . " jika perlu)\n";
$p1 = ask_hidden('Password baru (minimal 8 karakter): ');
if (strlen($p1) < 8) {
    fwrite(STDERR, "Password terlalu pendek.\n");
    exit(1);
}
$p2 = ask_hidden('Ulangi password: ');
if (!hash_equals($p1, $p2)) {
    fwrite(STDERR, "Password tidak sama.\n");
    exit(1);
}

$hash = password_hash($p1, PASSWORD_DEFAULT);
$content = (string) file_get_contents($envFile);
$line = "ADMIN_PASSWORD_HASH='" . $hash . "'";
if (preg_match('/^ADMIN_PASSWORD_HASH=.*$/m', $content)) {
    $content = (string) preg_replace_callback('/^ADMIN_PASSWORD_HASH=.*$/m', static fn () => $line, $content);
} else {
    $content = rtrim($content) . PHP_EOL . $line . PHP_EOL;
}
file_put_contents($envFile, $content);
echo "Berhasil. Hash password tersimpan di " . basename($envFile) . " (password asli tidak disimpan).\n";
