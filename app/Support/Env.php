<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pembaca file .env sederhana (tanpa dependency tambahan).
 * Format yang didukung:
 *   KEY=nilai
 *   KEY="nilai dengan spasi"
 *   KEY='nilai yang mengandung $ (misalnya hash password)'
 *   # komentar
 * Tidak ada interpolasi variabel, jadi hash bcrypt ($2y$...) aman.
 */
final class Env
{
    /** @return array<string,string> */
    public static function parseFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue;
            }
            $len = strlen($value);
            if ($len >= 2 && (($value[0] === '"' && $value[$len - 1] === '"') || ($value[0] === "'" && $value[$len - 1] === "'"))) {
                $value = substr($value, 1, -1);
            } else {
                // buang komentar di akhir baris: KEY=nilai # komentar
                $hash = strpos($value, ' #');
                if ($hash !== false) {
                    $value = rtrim(substr($value, 0, $hash));
                }
            }
            $values[$key] = $value;
        }
        return $values;
    }

    /**
     * Menentukan file .env yang dipakai. Default ".env".
     * Instance uji kedua memakai variabel lingkungan APP_ENV_FILE=.env.uji
     */
    public static function resolveFile(string $root): string
    {
        $name = getenv('APP_ENV_FILE') ?: ($_SERVER['APP_ENV_FILE'] ?? '.env');
        if (!preg_match('/^\.env(\.[A-Za-z0-9_-]+)?$/', (string) $name)) {
            throw new \RuntimeException('APP_ENV_FILE tidak valid. Contoh yang benar: .env atau .env.uji');
        }
        return $root . DIRECTORY_SEPARATOR . $name;
    }
}
