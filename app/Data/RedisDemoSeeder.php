<?php

declare(strict_types=1);

namespace App\Data;

use App\Domain\MasterCatalog;
use Predis\ClientInterface;

/**
 * Mengisi DATASET DEMO ke Redis LOKAL. Hanya dijalankan manual lewat scripts/seed-demo.php.
 *
 * Aman dijalankan berulang:
 *   - Master produk ditulis ulang (upsert) dari config/products.php. Tidak menyentuh stok/pesanan.
 *   - Stok memakai HSETNX: varian yang SUDAH punya stok TIDAK ditimpa.
 *   - Keranjang, pesanan, dan riwayat transaksi TIDAK disentuh.
 */
final class RedisDemoSeeder
{
    public const DATASET_KEY = 'alverse:meta:dataset';

    public function __construct(private readonly ClientInterface $redis)
    {
    }

    /**
     * Angka stok demo. P037 adjustable_hitam = 10 sesuai skenario uji panduan.
     * Varian lain mendapat angka demo tetap 0–14 (beberapa 0 agar tampilan "Habis" bisa diuji).
     */
    public static function demoStock(string $productId, string $variant): int
    {
        if ($productId === 'P037' && $variant === 'adjustable_hitam') {
            return 10;
        }
        return crc32($productId . '|' . $variant) % 15;
    }

    /** @return array{products:int, stock_created:int, stock_kept:int} */
    public function seed(string $productsFile, string $datasetName = 'demo-local', bool $dryRun = false): array
    {
        $current = $this->redis->get(self::DATASET_KEY);
        if ($current !== null && $current !== $datasetName) {
            throw new \RuntimeException("Database ini bertanda dataset '{$current}', bukan '{$datasetName}'. Seed dibatalkan agar data lain tidak tercampur.");
        }

        $products = MasterCatalog::fromConfig($productsFile);
        $created = 0;
        $kept = 0;
        foreach ($products as $p) {
            $hash = [
                'product_id'     => $p['product_id'],
                'name'           => $p['name'],
                'category'       => $p['category'],
                'category_label' => $p['category_label'],
                'price'          => (string) $p['price'],
                'size_label'     => $p['size_label'],
                'color_label'    => $p['color_label'],
                'sizes'          => json_encode($p['sizes'], JSON_UNESCAPED_UNICODE),
                'colors'         => json_encode($p['colors'], JSON_UNESCAPED_UNICODE),
                'description'    => $p['description'],
                'image_url'      => $p['image_url'],
                'image_is_demo'  => '1',
                'active'         => '1',
                'data_label'     => $datasetName,
            ];
            foreach (MasterCatalog::variantCodes($p) as $variant) {
                $exists = (bool) $this->redis->hexists('inventory:stock:' . $p['product_id'], $variant);
                if ($exists) {
                    $kept++;
                    continue;
                }
                $created++;
                if (!$dryRun) {
                    $this->redis->hsetnx('inventory:stock:' . $p['product_id'], $variant, (string) self::demoStock($p['product_id'], $variant));
                }
            }
            if (!$dryRun) {
                $this->redis->hmset('brand:catalog:product:' . $p['product_id'], $hash);
                $this->redis->sadd('brand:catalog:products', [$p['product_id']]);
            }
        }
        if (!$dryRun) {
            $this->redis->set(self::DATASET_KEY, $datasetName);
            $this->redis->set('alverse:meta:seeded_at', (string) time());
        }
        return ['products' => count($products), 'stock_created' => $created, 'stock_kept' => $kept];
    }

    /** Pola key milik proyek ini (dipakai reset-demo & tes). Tidak menyentuh key lain di Redis. */
    public const KEY_PATTERNS = [
        'brand:catalog:*', 'inventory:*', 'cart:*', 'order:*', 'branch:*:orders', 'branch:*:completed',
        'customer:*', 'checkout:request:*', 'alverse:meta:*',
    ];

    public function deleteProjectKeys(): int
    {
        $deleted = 0;
        foreach (self::KEY_PATTERNS as $pattern) {
            $cursor = '0';
            do {
                [$cursor, $keys] = $this->redis->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 500]);
                if ($keys) {
                    $deleted += (int) $this->redis->del($keys);
                }
            } while ((string) $cursor !== '0');
        }
        return $deleted;
    }
}
