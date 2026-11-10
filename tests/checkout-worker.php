<?php

declare(strict_types=1);

/**
 * Proses anak untuk uji checkout BERSAMAAN (dipanggil oleh tests/run-tests.php, bukan manual).
 * Argumen: <waktu_mulai_microtime> <branch_id> <request_id> <product_id> <variant> <qty>
 * Setiap proses membuka koneksi Redis sendiri, menunggu waktu mulai yang sama, lalu checkout.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\App;
use App\Data\DomainRejected;
use App\Data\RedisLocalAdapter;

[, $startAt, $branch, $requestId, $productId, $variant, $qty] = $argv;

$cfg = App::config('redis');
$adapter = new RedisLocalAdapter(
    RedisLocalAdapter::createClient($cfg, (int) $cfg['test_database']),
    (array) App::config('shipping'),
    (array) App::config('branches')
);
$adapter->getProduct($productId); // memanaskan koneksi & cache sebelum garis start

while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    $res = $adapter->checkout([
        'request_id' => $requestId, 'branch_id' => $branch, 'session_hash' => hash('sha256', $requestId),
        'customer' => ['name' => 'Uji Bersamaan', 'phone' => '081200000000', 'address' => '', 'notes' => ''],
        'shipping_method' => 'pickup', 'pickup_location' => 'Lokasi uji',
        'items' => [['product_id' => $productId, 'variant' => $variant, 'qty' => (int) $qty]],
    ]);
    echo json_encode(['ok' => true, 'branch' => $branch, 'order_id' => $res['order']['order_id']]);
} catch (DomainRejected $e) {
    echo json_encode(['ok' => false, 'branch' => $branch, 'code' => $e->errorCode]);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'branch' => $branch, 'code' => 'ERROR', 'message' => $e->getMessage()]);
}
