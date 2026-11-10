<?php

declare(strict_types=1);

/**
 * MENGHAPUS seluruh data proyek Alverse di Redis LOKAL (produk, stok, keranjang, pesanan).
 * Gunakan hanya jika ingin mengulang demo dari awal.
 *
 *     php scripts/reset-demo.php --confirm=RESET-DEMO
 *
 * Pengaman:
 *   - Ditolak jika DATA_DRIVER=central.
 *   - Ditolak jika REDIS_HOST bukan 127.0.0.1 / localhost.
 *   - Ditolak jika database bertanda dataset selain 'demo-local'.
 *   - Hanya menghapus key berpola milik proyek ini (lihat RedisDemoSeeder::KEY_PATTERNS).
 * Setelah reset, jalankan lagi: php scripts/seed-demo.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\App;
use App\Data\RedisDemoSeeder;
use App\Data\RedisLocalAdapter;

if (!in_array('--confirm=RESET-DEMO', $argv, true)) {
    fwrite(STDERR, "Perintah ini menghapus pesanan & stok demo. Jalankan dengan: php scripts/reset-demo.php --confirm=RESET-DEMO\n");
    exit(1);
}
if (App::config('data.driver') !== 'redis') {
    fwrite(STDERR, "DITOLAK: DATA_DRIVER bukan 'redis'.\n");
    exit(1);
}
$cfg = App::config('redis');
if (!in_array($cfg['host'], ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "DITOLAK: REDIS_HOST '{$cfg['host']}' bukan Redis lokal.\n");
    exit(1);
}

try {
    $client = RedisLocalAdapter::createClient($cfg);
    $dataset = $client->get(RedisDemoSeeder::DATASET_KEY);
    if ($dataset !== null && $dataset !== 'demo-local') {
        fwrite(STDERR, "DITOLAK: database ini bertanda dataset '{$dataset}'.\n");
        exit(1);
    }
    $n = (new RedisDemoSeeder($client))->deleteProjectKeys();
} catch (\Predis\PredisException $e) {
    fwrite(STDERR, "GAGAL terhubung ke Redis: " . $e->getMessage() . "\n");
    exit(1);
}
echo "Terhapus {$n} key milik proyek di database {$cfg['database']}. Jalankan: php scripts/seed-demo.php\n";
