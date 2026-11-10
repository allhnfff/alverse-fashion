<?php

declare(strict_types=1);

/**
 * TES OTOMATIS LAPISAN DATA + ATURAN BISNIS
 *
 *     php tests/run-tests.php
 *
 * - Memakai database Redis KHUSUS TES (REDIS_TEST_DB, default 15), bukan data demo (REDIS_DB).
 * - Database tes dikosongkan di awal. Data demo dan transaksi di REDIS_DB tidak disentuh.
 * - Hasil juga disimpan ke tests/output/hasil-tes-data.md sebagai bukti uji.
 */

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/lib/TestKit.php';

use App\App;
use App\Data\DataServiceUnavailable;
use App\Data\DomainRejected;
use App\Data\RedisDemoSeeder;
use App\Data\RedisLocalAdapter;
use App\Domain\CartService;
use App\Domain\CheckoutService;
use App\Domain\ValidationError;
use App\Domain\Validator;

$cfg = App::config('redis');
$testDb = (int) $cfg['test_database'];
if ($testDb === (int) $cfg['database']) {
    fwrite(STDERR, "REDIS_TEST_DB tidak boleh sama dengan REDIS_DB.\n");
    exit(1);
}
if (App::config('data.driver') !== 'redis') {
    fwrite(STDERR, "Tes ini untuk adapter Redis lokal (DATA_DRIVER=redis).\n");
    exit(1);
}

echo "Tes lapisan data Alverse — Redis {$cfg['host']}:{$cfg['port']} database TES {$testDb}" . PHP_EOL . PHP_EOL;

$shipping = (array) App::config('shipping');
$branches = (array) App::config('branches');
$newAdapter = static fn (): RedisLocalAdapter => new RedisLocalAdapter(
    RedisLocalAdapter::createClient($cfg, $testDb), $shipping, $branches, 'Redis tes'
);

// ---------- Siapkan database tes ----------
try {
    $raw = RedisLocalAdapter::createClient($cfg, $testDb);
    $raw->ping();
} catch (\Predis\PredisException $e) {
    fwrite(STDERR, "Redis tidak dapat dihubungi: {$e->getMessage()}\nJalankan Redis server dulu.\n");
    exit(1);
}
$marker = $raw->get(RedisDemoSeeder::DATASET_KEY);
if ((int) $raw->dbsize() > 0 && $marker !== 'test') {
    fwrite(STDERR, "Database {$testDb} berisi data lain (penanda: " . var_export($marker, true) . "). Tes dibatalkan agar tidak menghapus data.\n");
    exit(1);
}
$raw->flushdb();
(new RedisDemoSeeder($raw))->seed(App::root() . '/config/products.php', 'test');

/** Atur stok fixture langsung (HANYA di database tes, meniru tindakan admin_pusat). */
$setStock = static fn (string $pid, string $variant, int $n) => $raw->hset('inventory:stock:' . $pid, $variant, (string) $n);
$stockOf = static fn (string $pid, string $variant): ?int => ($v = $raw->hget('inventory:stock:' . $pid, $variant)) === null ? null : (int) $v;
$rid = static fn (): string => bin2hex(random_bytes(16));
$owner = static fn (string $seed): string => hash('sha256', 'owner|' . $seed);
$request = static function (string $branch, string $sessionHash, array $items, string $method = 'pickup', ?string $requestId = null) use ($rid): array {
    return [
        'request_id' => $requestId ?? $rid(), 'branch_id' => $branch, 'session_hash' => $sessionHash,
        'customer' => ['name' => 'Pelanggan Uji', 'phone' => '081234567890',
            'address' => $method === 'reguler' ? 'Jl. Uji Coba No. 1, Bogor (alamat uji)' : '', 'notes' => ''],
        'shipping_method' => $method, 'pickup_location' => 'Lokasi pickup uji', 'items' => $items,
    ];
};
$adminBogor = ['branch_id' => 'bogor', 'username' => 'admin_bogor', 'role' => 'admin_cabang'];
$adminUji = ['branch_id' => 'uji', 'username' => 'admin_uji', 'role' => 'admin_cabang'];

$bogor = $newAdapter(); // instance website Bogor
$uji = $newAdapter();   // instance website uji (koneksi Redis terpisah)

// =====================================================================
TestKit::run('T01', 'Redis memberi respons PONG', function () use ($raw) {
    TestKit::same('PONG', (string) $raw->ping(), 'PING');
    return 'PING -> PONG';
});

TestKit::run('T02', 'Katalog dibaca dari sumber data (42 produk, harga master)', function () use ($bogor) {
    $all = $bogor->listProducts();
    TestKit::same(42, count($all), 'jumlah produk');
    $p = $bogor->getProduct('P037');
    TestKit::same('Topi Baseball', $p['name'], 'nama P037');
    TestKit::same(79000, $p['price'], 'harga P037');
    TestKit::assert(in_array('adjustable_hitam', array_column($p['variants'], 'code'), true), 'varian adjustable_hitam ada');
    TestKit::same(129000, $bogor->getProduct('P001')['price'], 'harga P001');
    TestKit::same(89000, $bogor->getProduct('P042')['price'], 'harga P042');
    TestKit::same('redis', $bogor->describe()['driver'], 'driver');
    return '42 produk, P037 Rp79.000, driver redis';
});

TestKit::run('T03', 'Keranjang: tambah, ubah, hapus (stok tidak berubah)', function () use ($bogor, $stockOf, $setStock) {
    $setStock('P001', 'm_hitam', 5);
    $sid = bin2hex(random_bytes(16));
    $cart = new CartService($bogor, 'bogor');
    $cart->add($sid, 'P001', 'm_hitam', 2);
    TestKit::same(2, $bogor->getCart($sid, 'bogor')[0]['qty'], 'qty setelah tambah');
    $cart->add($sid, 'P001', 'm_hitam', 1);
    TestKit::same(3, $bogor->getCart($sid, 'bogor')[0]['qty'], 'qty setelah tambah lagi');
    $cart->update($sid, 'P001', 'm_hitam', 1);
    TestKit::same(1, $bogor->getCart($sid, 'bogor')[0]['qty'], 'qty setelah ubah');
    TestKit::same(5, $stockOf('P001', 'm_hitam'), 'stok tidak berubah karena keranjang');
    TestKit::same([], $bogor->getCart($sid, 'uji'), 'keranjang website lain terpisah');
    $cart->remove($sid, 'P001', 'm_hitam');
    TestKit::same([], $bogor->getCart($sid, 'bogor'), 'keranjang kosong setelah hapus');
    TestKit::throws(ValidationError::class, fn () => $cart->add($sid, 'P001', 'm_hitam', 6)); // melebihi stok saat ini
    return 'stok tetap 5 selama operasi keranjang';
});

$orderBogor = null;
TestKit::run('T04', 'Stok global 10, beli 1 di Bogor -> 9 (satu catatan stok)', function () use ($bogor, $setStock, $stockOf, $request, $owner, $raw, &$orderBogor) {
    $setStock('P037', 'adjustable_hitam', 10);
    $res = $bogor->checkout($request('bogor', $owner('a'), [['product_id' => 'P037', 'variant' => 'adjustable_hitam', 'qty' => 1]]));
    TestKit::same('created', $res['status'], 'status checkout');
    TestKit::same(9, $stockOf('P037', 'adjustable_hitam'), 'stok pusat');
    TestKit::same(79000, $res['order']['total'], 'total pickup 1 topi');
    TestKit::same('bogor', $res['order']['branch_id'], 'branch_id pesanan');
    $branchStockKeys = $raw->keys('branch:*:stock:*');
    TestKit::same([], $branchStockKeys, 'tidak ada key stok per cabang');
    $orderBogor = $res['order'];
    return 'order ' . $res['order']['order_id'] . ', stok 10 -> 9, tidak ada branch:*:stock:*';
});

TestKit::run('T05', 'Dua instance memakai stok yang sama; Bandung-like (uji) beli 2 -> 7', function () use ($bogor, $uji, $request, $owner) {
    TestKit::same(9, $uji->getStock('P037')['variants']['adjustable_hitam'], 'instance uji membaca 9');
    $uji->checkout($request('uji', $owner('b'), [['product_id' => 'P037', 'variant' => 'adjustable_hitam', 'qty' => 2]]));
    TestKit::same(7, $bogor->getStock('P037')['variants']['adjustable_hitam'], 'instance Bogor membaca 7');
    TestKit::same(7, $uji->getStock('P037')['variants']['adjustable_hitam'], 'instance uji membaca 7');
    return 'total persediaan 10, bukan 20';
});

TestKit::run('T06', 'Checkout bersamaan memperebutkan stok terakhir: hanya satu berhasil', function () use ($setStock, $stockOf, $rid) {
    $results = [];
    foreach ([2, 10] as $workers) {
        $setStock('P037', 'adjustable_cream', 1);
        $startAt = microtime(true) + 1.5;
        $procs = [];
        $pipesList = [];
        for ($i = 0; $i < $workers; $i++) {
            $branch = $i % 2 === 0 ? 'bogor' : 'uji';
            $cmd = [PHP_BINARY, __DIR__ . '/checkout-worker.php', sprintf('%.6F', $startAt), $branch, $rid(), 'P037', 'adjustable_cream', '1'];
            $pipes = [];
            $procs[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $pipesList[] = $pipes;
        }
        $ok = 0;
        $rejected = 0;
        foreach ($procs as $i => $p) {
            $out = stream_get_contents($pipesList[$i][1]);
            $err = stream_get_contents($pipesList[$i][2]);
            proc_close($p);
            $d = json_decode((string) $out, true);
            if (!is_array($d)) {
                throw new AssertionFailed('Worker gagal: ' . $out . $err);
            }
            if ($d['ok']) {
                $ok++;
            } elseif ($d['code'] === 'INSUFFICIENT_STOCK') {
                $rejected++;
            } else {
                throw new AssertionFailed('Worker error tak terduga: ' . json_encode($d));
            }
        }
        TestKit::same(1, $ok, "jumlah sukses ({$workers} proses)");
        TestKit::same($workers - 1, $rejected, "jumlah ditolak ({$workers} proses)");
        TestKit::same(0, $stockOf('P037', 'adjustable_cream'), 'stok akhir');
        $results[] = "{$workers} proses: 1 sukses, " . ($workers - 1) . ' ditolak';
    }
    return implode('; ', $results) . '; stok tidak pernah negatif';
});

TestKit::run('T07', 'request_id sama diulang: pesanan sama, stok tidak dipotong lagi', function () use ($bogor, $setStock, $stockOf, $request, $owner, $rid) {
    $setStock('P002', 'l_sage', 5);
    $id = $rid();
    $req = $request('bogor', $owner('c'), [['product_id' => 'P002', 'variant' => 'l_sage', 'qty' => 2]], 'pickup', $id);
    $a = $bogor->checkout($req);
    $b = $bogor->checkout($req);
    $c = $bogor->checkout($req);
    TestKit::same('created', $a['status'], 'pertama');
    TestKit::same('duplicate', $b['status'], 'kedua');
    TestKit::same($a['order']['order_id'], $c['order']['order_id'], 'kode pesanan sama');
    TestKit::same(3, $stockOf('P002', 'l_sage'), 'stok dipotong sekali');
    $req2 = $req;
    $req2['items'][0]['qty'] = 1; // isi berbeda, token sama
    TestKit::throws(DomainRejected::class, fn () => $bogor->checkout($req2), fn ($e) => TestKit::same('IDEMPOTENCY_CONFLICT', $e->errorCode, 'kode'));
    TestKit::same(3, $stockOf('P002', 'l_sage'), 'stok tetap setelah konflik');
    return '3x kirim -> 1 pesanan, stok 5 -> 3; isi berbeda ditolak';
});

TestKit::run('T08', 'Pembatalan berulang mengembalikan stok sekali', function () use ($bogor, $stockOf, $adminBogor, &$orderBogor) {
    TestKit::same(7, $stockOf('P037', 'adjustable_hitam'), 'stok sebelum batal');
    $r1 = $bogor->cancelOrder($orderBogor['order_id'], 'Pelanggan membatalkan (uji)', $adminBogor);
    $r2 = $bogor->cancelOrder($orderBogor['order_id'], 'Pelanggan membatalkan (uji)', $adminBogor);
    TestKit::same('cancelled', $r1['status'], 'batal pertama');
    TestKit::same('already_cancelled', $r2['status'], 'batal kedua');
    TestKit::same(8, $stockOf('P037', 'adjustable_hitam'), 'stok setelah batal 2x');
    TestKit::same(true, $r2['order']['restocked'], 'penanda restocked');
    return 'stok 7 -> 8 -> tetap 8 (sesuai skenario panduan)';
});

TestKit::run('T08b', 'Pembatalan ditolak setelah Dikemas; alasan wajib', function () use ($bogor, $setStock, $request, $owner, $adminBogor) {
    $setStock('P003', 'm_navy', 3);
    $o = $bogor->checkout($request('bogor', $owner('d'), [['product_id' => 'P003', 'variant' => 'm_navy', 'qty' => 1]], 'reguler'))['order'];
    TestKit::throws(DomainRejected::class, fn () => $bogor->cancelOrder($o['order_id'], '  ', $adminBogor), fn ($e) => TestKit::same('REASON_REQUIRED', $e->errorCode, 'kode'));
    $bogor->updateOrderStatus($o['order_id'], ['from' => 'dikonfirmasi', 'to' => 'dikemas'], $adminBogor);
    TestKit::throws(DomainRejected::class, fn () => $bogor->cancelOrder($o['order_id'], 'Terlambat (uji)', $adminBogor), fn ($e) => TestKit::same('NOT_CANCELLABLE', $e->errorCode, 'kode'));
    return 'NOT_CANCELLABLE setelah dikemas';
});

TestKit::run('T09', 'Admin Bogor tidak bisa membaca/mengubah pesanan cabang lain', function () use ($bogor, $uji, $setStock, $request, $owner, $adminBogor, $adminUji, $stockOf) {
    $setStock('P004', 's_putih', 4);
    $ujiOrder = $uji->checkout($request('uji', $owner('e'), [['product_id' => 'P004', 'variant' => 's_putih', 'qty' => 1]]))['order'];
    $id = $ujiOrder['order_id'];
    TestKit::same(null, $bogor->getOrder($id, ['type' => 'branch_admin', 'branch_id' => 'bogor']), 'baca detail');
    $list = $bogor->listOrders(['type' => 'branch_admin', 'branch_id' => 'bogor']);
    TestKit::assert(!in_array($id, array_column($list, 'order_id'), true), 'tidak muncul di daftar Bogor');
    TestKit::assert(!array_filter($list, fn ($o) => $o['branch_id'] !== 'bogor'), 'daftar Bogor hanya berisi bogor');
    TestKit::throws(DomainRejected::class, fn () => $bogor->updateOrderStatus($id, ['from' => 'dikonfirmasi', 'to' => 'siap_diambil'], $adminBogor),
        fn ($e) => TestKit::same('ORDER_NOT_FOUND', $e->errorCode, 'ubah status'));
    TestKit::throws(DomainRejected::class, fn () => $bogor->cancelOrder($id, 'Coba batalkan milik cabang lain', $adminBogor),
        fn ($e) => TestKit::same('ORDER_NOT_FOUND', $e->errorCode, 'batal'));
    TestKit::same(3, $stockOf('P004', 's_putih'), 'stok tidak berubah oleh percobaan ilegal');
    TestKit::same(null, $uji->getOrder($id, ['type' => 'customer', 'branch_id' => 'uji', 'session_hash' => hash('sha256', 'orang-lain')]), 'pelanggan lain');
    TestKit::assert($uji->getOrder($id, ['type' => 'branch_admin', 'branch_id' => 'uji']) !== null, 'admin uji bisa membaca miliknya');
    TestKit::assert(!isset($bogor->getOrder($id, ['type' => 'branch_admin', 'branch_id' => 'uji'])['pickup_code']), 'kode pickup disembunyikan dari admin');
    return 'baca/ubah/batal ditolak (ORDER_NOT_FOUND)';
});

TestKit::run('T10', 'Manipulasi jumlah, varian, dan harga dari browser ditolak/dihitung ulang', function () use ($bogor, $setStock, $request, $owner, $stockOf) {
    foreach (['0', '-1', '1.5', '1e2', 'abc', '', '2,5', '100', '0x10'] as $bad) {
        TestKit::throws(ValidationError::class, fn () => Validator::quantity($bad), null);
    }
    TestKit::same(2, Validator::quantity(' 2 '), 'spasi di tepi dirapikan (aman)');
    TestKit::same(3, Validator::quantity('3'), 'qty sah');
    TestKit::throws(ValidationError::class, fn () => Validator::variant('adjustable_hitam<script>'));
    $sid = bin2hex(random_bytes(16));
    TestKit::throws(DomainRejected::class, fn () => $bogor->setCartItem($sid, 'bogor', 'P037', 'xl_ungu', 1), fn ($e) => TestKit::same('VARIANT_NOT_FOUND', $e->errorCode, 'varian palsu'));
    TestKit::throws(DomainRejected::class, fn () => $bogor->checkout($request('bogor', $owner('f'), [['product_id' => 'P037', 'variant' => 'xl_ungu', 'qty' => 1]])),
        fn ($e) => TestKit::same('VARIANT_NOT_FOUND', $e->errorCode, 'checkout varian palsu'));
    TestKit::throws(DomainRejected::class, fn () => $bogor->checkout($request('bogor', $owner('f'), [['product_id' => 'P037', 'variant' => 'adjustable_navy', 'qty' => 0]])),
        fn ($e) => TestKit::same('INVALID_QTY', $e->errorCode, 'qty 0'));
    // Harga palsu dari "browser" diabaikan: checkout menghitung dari master.
    $setStock('P037', 'adjustable_navy', 5);
    $req = $request('bogor', $owner('f'), [['product_id' => 'P037', 'variant' => 'adjustable_navy', 'qty' => 2, 'unit_price' => 1, 'price' => 1]], 'reguler');
    $req['total'] = 1;
    $req['shipping_fee'] = 0;
    $o = $bogor->checkout($req)['order'];
    TestKit::same(158000, $o['items_subtotal'], 'subtotal dari master 2 x 79.000');
    TestKit::same(10000, $o['shipping_fee'], 'ongkir reguler dari server');
    TestKit::same(168000, $o['total'], 'total');
    TestKit::same(79000, $o['items'][0]['unit_price'], 'harga snapshot');
    TestKit::same(3, $stockOf('P037', 'adjustable_navy'), 'stok navy');
    return 'qty 0/-1/1.5/1e2 ditolak; harga palsu diabaikan, total Rp168.000';
});

TestKit::run('T11', 'Gangguan Redis menghasilkan error jelas, bukan sukses/stok 0', function () use ($cfg, $shipping, $branches, $request, $owner) {
    $down = new RedisLocalAdapter(RedisLocalAdapter::createClient(['host' => '127.0.0.1', 'port' => 1, 'database' => 0, 'timeout' => 0.5] + $cfg, null), $shipping, $branches);
    TestKit::throws(DataServiceUnavailable::class, fn () => $down->getStock('P037'));
    $e = TestKit::throws(DataServiceUnavailable::class, fn () => $down->checkout($request('bogor', $owner('g'), [['product_id' => 'P037', 'variant' => 'adjustable_hitam', 'qty' => 1]])));
    TestKit::same(false, $down->health()['ok'], 'health');
    return 'DataServiceUnavailable: ' . substr($e->getMessage(), 0, 60) . '…';
});

TestKit::run('T12', 'Alur status reguler & pickup + rekap hanya pesanan selesai', function () use ($bogor, $setStock, $request, $owner, $adminBogor, $raw) {
    $setStock('P029', '40_putih', 5);
    $reg = $bogor->checkout($request('bogor', $owner('h'), [['product_id' => 'P029', 'variant' => '40_putih', 'qty' => 2]], 'reguler'))['order'];
    $id = $reg['order_id'];
    TestKit::throws(DomainRejected::class, fn () => $bogor->updateOrderStatus($id, ['from' => 'dikonfirmasi', 'to' => 'selesai'], $adminBogor),
        fn ($e) => TestKit::same('INVALID_TRANSITION', $e->errorCode, 'loncat status'));
    $bogor->updateOrderStatus($id, ['from' => 'dikonfirmasi', 'to' => 'dikemas'], $adminBogor);
    TestKit::same('unchanged', $bogor->updateOrderStatus($id, ['from' => 'dikonfirmasi', 'to' => 'dikemas'], $adminBogor)['status'], 'klik ganda status');
    TestKit::throws(DomainRejected::class, fn () => $bogor->updateOrderStatus($id, ['from' => 'dikemas', 'to' => 'dikirim'], $adminBogor),
        fn ($e) => TestKit::same('TRACKING_REQUIRED', $e->errorCode, 'resi wajib'));
    $bogor->updateOrderStatus($id, ['from' => 'dikemas', 'to' => 'dikirim', 'courier' => 'Kurir Demo', 'tracking_number' => 'DEMO1'], $adminBogor);
    $bogor->updateOrderStatus($id, ['from' => 'dikirim', 'to' => 'selesai'], $adminBogor);

    $setStock('P036', 'one_natural', 5);
    $pickOwner = $owner('i');
    $pick = $bogor->checkout($request('bogor', $pickOwner, [['product_id' => 'P036', 'variant' => 'one_natural', 'qty' => 1]]))['order'];
    $code = $pick['pickup_code'];
    TestKit::assert(preg_match('/^\d{6}$/', $code) === 1, 'kode pickup 6 digit untuk pelanggan');
    $bogor->updateOrderStatus($pick['order_id'], ['from' => 'dikonfirmasi', 'to' => 'siap_diambil'], $adminBogor);
    $wrong = $code === '000000' ? '111111' : '000000';
    TestKit::throws(DomainRejected::class, fn () => $bogor->updateOrderStatus($pick['order_id'], ['from' => 'siap_diambil', 'to' => 'selesai', 'pickup_code' => $wrong], $adminBogor),
        fn ($e) => TestKit::same('PICKUP_CODE_MISMATCH', $e->errorCode, 'kode salah'));
    $bogor->updateOrderStatus($pick['order_id'], ['from' => 'siap_diambil', 'to' => 'selesai', 'pickup_code' => $code], $adminBogor);

    $sum = $bogor->getSalesSummary('bogor', time() - 3600, time() + 3600);
    TestKit::same(2, $sum['orders_completed'], 'pesanan selesai (batal & belum selesai tidak dihitung)');
    TestKit::same(3, $sum['units_sold'], 'unit');
    TestKit::same(2 * 279000 + 89000, $sum['items_subtotal'], 'subtotal barang');
    TestKit::same(10000, $sum['shipping_total'], 'ongkir terpisah');
    $sumUji = $bogor->getSalesSummary('uji', time() - 3600, time() + 3600);
    TestKit::same(0, $sumUji['orders_completed'], 'rekap uji terpisah');
    return 'rekap Bogor: 2 pesanan, 3 unit, Rp647.000 + ongkir Rp10.000';
});

TestKit::run('T13', 'Seed ulang tidak menimpa stok yang sudah berubah', function () use ($raw, $stockOf) {
    $before = $stockOf('P037', 'adjustable_hitam');
    $res = (new RedisDemoSeeder($raw))->seed(App::root() . '/config/products.php', 'test');
    TestKit::same(0, $res['stock_created'], 'stok baru');
    TestKit::same($before, $stockOf('P037', 'adjustable_hitam'), 'stok P037 tetap');
    TestKit::throws(\RuntimeException::class, fn () => (new RedisDemoSeeder($raw))->seed(App::root() . '/config/products.php', 'demo-local'));
    return "stok tetap {$before}; dataset berbeda ditolak";
});

TestKit::run('T14', 'Persistensi Redis aktif (AOF/RDB) — prasyarat uji restart', function () use ($raw) {
    try {
        $aof = $raw->config('GET', 'appendonly')['appendonly'] ?? 'no';
        $save = $raw->config('GET', 'save')['save'] ?? '';
    } catch (\Throwable $e) {
        throw new SkipTest('CONFIG GET tidak diizinkan di server ini.');
    }
    if ($aof !== 'yes' && $save === '') {
        throw new AssertionFailed('AOF dan RDB mati: data hilang saat Redis restart. Jalankan Redis dengan redis/redis-dev.conf.');
    }
    return "appendonly={$aof}, save=\"{$save}\" (uji restart manual: lihat docs/PENGUJIAN.md)";
});

$code = TestKit::summary('Hasil tes lapisan data');
TestKit::writeReport(__DIR__ . '/output/hasil-tes-data.md', 'Hasil tes lapisan data Alverse Bogor');
echo 'Laporan: tests/output/hasil-tes-data.md' . PHP_EOL;
exit($code);
