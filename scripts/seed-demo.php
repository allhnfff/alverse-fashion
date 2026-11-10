<?php

declare(strict_types=1);

/**
 * Mengisi DATASET DEMO ke Redis LOKAL (master 42 produk + stok demo).
 *
 * Jalankan manual SEKALI dari folder proyek:
 *     php scripts/seed-demo.php            (isi data)
 *     php scripts/seed-demo.php --dry-run  (lihat apa yang akan dilakukan, tanpa menulis)
 *
 * Aman diulang: stok yang sudah ada TIDAK ditimpa, pesanan & keranjang TIDAK disentuh.
 * Tidak pernah dijalankan otomatis oleh website.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\App;
use App\Data\RedisDemoSeeder;
use App\Data\RedisLocalAdapter;

$dryRun = in_array('--dry-run', $argv, true);

if (App::config('data.driver') !== 'redis') {
    fwrite(STDERR, "DITOLAK: DATA_DRIVER bukan 'redis'. Data pusat tidak boleh diisi dataset demo.\n");
    exit(1);
}
$redisCfg = App::config('redis');
if ((int) $redisCfg['database'] === (int) $redisCfg['test_database']) {
    fwrite(STDERR, "DITOLAK: REDIS_DB sama dengan REDIS_TEST_DB ({$redisCfg['test_database']}). Pisahkan database demo dan tes.\n");
    exit(1);
}

echo "File konfigurasi : " . App::config('env_file') . "\n";
echo "Redis tujuan     : {$redisCfg['host']}:{$redisCfg['port']} database {$redisCfg['database']}\n";
echo $dryRun ? "Mode             : DRY RUN (tidak menulis)\n" : "Mode             : tulis\n";

try {
    $client = RedisLocalAdapter::createClient($redisCfg);
    $client->ping();
    $seeder = new RedisDemoSeeder($client);
    $res = $seeder->seed(App::root() . '/config/products.php', 'demo-local', $dryRun);
} catch (\Predis\PredisException $e) {
    fwrite(STDERR, "GAGAL terhubung ke Redis: " . $e->getMessage() . "\nPastikan Redis server berjalan, lalu coba lagi.\n");
    exit(1);
} catch (\RuntimeException $e) {
    fwrite(STDERR, "DITOLAK: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Produk master    : {$res['products']} (ditulis/diperbarui dari config/products.php)\n";
echo "Stok varian baru : {$res['stock_created']}\n";
echo "Stok dipertahankan (sudah ada, tidak ditimpa): {$res['stock_kept']}\n";
echo $dryRun ? "Selesai (dry run).\n" : "Selesai. Dataset ditandai 'demo-local'.\n";
