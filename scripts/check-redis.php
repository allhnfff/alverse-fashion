<?php

declare(strict_types=1);

/**
 * Memeriksa koneksi website -> Redis memakai pengaturan di .env.
 *     php scripts/check-redis.php
 * Hasil yang diharapkan: "PING -> PONG" dan info persistensi.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\App;
use App\Data\RedisLocalAdapter;

$cfg = App::config('redis');
echo "File konfigurasi : " . App::config('env_file') . (App::config('env_file_found') ? '' : '  (TIDAK DITEMUKAN — salin .env.example menjadi .env)') . "\n";
echo "DATA_DRIVER      : " . App::config('data.driver') . "\n";
echo "Redis tujuan     : {$cfg['host']}:{$cfg['port']} database {$cfg['database']}"
    . ($cfg['password'] !== '' ? ' (memakai password)' : ' (tanpa password)') . "\n";

try {
    $r = RedisLocalAdapter::createClient($cfg);
    echo "PING -> " . $r->ping() . "\n";
    $info = $r->info('server');
    $server = $info['Server'] ?? $info;
    echo "Versi Redis      : " . ($server['redis_version'] ?? '?') . "\n";
    $aof = $r->config('GET', 'appendonly');
    $save = $r->config('GET', 'save');
    $dir = $r->config('GET', 'dir');
    echo "appendonly (AOF) : " . ($aof['appendonly'] ?? '?') . "\n";
    echo "save (RDB)       : " . (($save['save'] ?? '') === '' ? '(mati)' : $save['save']) . "\n";
    echo "Folder data      : " . ($dir['dir'] ?? '?') . "\n";
    echo "Dataset          : " . ($r->get('alverse:meta:dataset') ?? '(belum di-seed — jalankan php scripts/seed-demo.php)') . "\n";
    echo "Jumlah produk    : " . $r->scard('brand:catalog:products') . "\n";
    exit(0);
} catch (\Predis\Response\ServerException $e) {
    $m = $e->getMessage();
    if (str_contains($m, 'NOAUTH') || str_contains($m, 'WRONGPASS')) {
        echo "GAGAL: autentikasi ditolak. Periksa REDIS_PASSWORD/REDIS_USERNAME di .env.\n";
    } elseif (str_contains($m, 'unknown command') || str_contains($m, 'CONFIG')) {
        echo "Terhubung, tetapi perintah CONFIG dinonaktifkan di server ini (normal untuk server pusat).\n";
        exit(0);
    } else {
        echo "GAGAL: Redis menolak perintah: {$m}\n";
    }
    exit(1);
} catch (\Predis\PredisException $e) {
    echo "GAGAL terhubung: " . $e->getMessage() . "\n";
    echo "Periksa: (1) Redis server sudah berjalan? (2) REDIS_HOST/REDIS_PORT di .env benar? (3) port tidak dipakai program lain?\n";
    exit(1);
}
