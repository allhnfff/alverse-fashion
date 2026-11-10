<?php

declare(strict_types=1);

/**
 * TES END-TO-END LEWAT HTTP (meniru browser sungguhan) terhadap DUA website yang sedang berjalan:
 *   - Website Bogor     : http://localhost:8001  (.env)
 *   - Instance uji lokal: http://localhost:8002  (.env.uji)
 *
 * Cara pakai (server harus sudah dijalankan, lihat README):
 *     php tests/http-e2e.php
 *     php tests/http-e2e.php --bogor=http://localhost:8001 --uji=http://localhost:8002
 *
 * Tes admin butuh password admin yang KAMU atur sendiri, diberikan lewat variabel lingkungan
 * (tidak disimpan di file):  ALVERSE_ADMIN_PASSWORD  dan opsional ALVERSE_UJI_ADMIN_PASSWORD.
 *
 * Tes ini memakai data demo (REDIS_DB). Semua pembelian uji dibatalkan lagi oleh admin
 * sehingga stok kembali ke angka awal (jika tes admin dijalankan).
 */

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/lib/TestKit.php';

use App\App;
use App\Support\Env;

$opts = getopt('', ['bogor::', 'uji::']);
$bogorUrl = rtrim((string) ($opts['bogor'] ?? 'http://localhost:8001'), '/');
$ujiUrl = rtrim((string) ($opts['uji'] ?? 'http://localhost:8002'), '/');
$adminPass = getenv('ALVERSE_ADMIN_PASSWORD') ?: '';
$ujiAdminPass = getenv('ALVERSE_UJI_ADMIN_PASSWORD') ?: $adminPass;
$bogorAdminUser = (string) App::config('admin.username');
$ujiEnv = Env::parseFile(App::root() . '/.env.uji');
$ujiAdminUser = $ujiEnv['ADMIN_USERNAME'] ?? 'admin_uji';

echo "Tes HTTP end-to-end\n  Bogor: {$bogorUrl}\n  Uji  : {$ujiUrl}\n\n";

$apiStock = static function (string $base, string $variant = 'adjustable_hitam'): int {
    $b = (new Browser($base))->get('/api/stok/P037');
    $d = $b->json();
    if (empty($d['ok'])) {
        throw new AssertionFailed("API stok {$base} gagal: " . $b->body);
    }
    return (int) $d['variants'][$variant];
};

/** Jalankan alur belanja lengkap di satu browser. @return array{order_id:string, request_id:string} */
$buy = static function (Browser $br, int $qty, string $method = 'pickup', array $extraReview = []): array {
    $br->get('/produk/P037');
    $csrf = $br->csrf();
    $br->post('/keranjang/tambah', ['_csrf' => $csrf, 'product_id' => 'P037', 'size' => 'adjustable', 'color' => 'hitam', 'qty' => (string) $qty]);
    TestKit::same(303, $br->status, 'tambah keranjang redirect');
    $br->get('/checkout');
    TestKit::same(200, $br->status, 'halaman checkout');
    $br->post('/checkout/tinjau', [
        '_csrf' => $br->csrf(), 'name' => 'Pelanggan E2E', 'phone' => '081234567890',
        'shipping_method' => $method, 'address' => $method === 'reguler' ? 'Jl. Uji End To End No. 1 (alamat uji)' : '', 'notes' => '',
    ] + $extraReview);
    TestKit::same('/checkout/tinjau', $br->location(), 'redirect ke halaman konfirmasi');
    $br->get('/checkout/tinjau');
    if (!preg_match('/name="request_id" value="([a-f0-9]{32})"/', $br->body, $m)) {
        throw new AssertionFailed('request_id tidak ada di halaman konfirmasi');
    }
    $rid = $m[1];
    $br->post('/checkout/konfirmasi', ['_csrf' => $br->csrf(), 'request_id' => $rid]);
    if (!preg_match('#^/pesanan/([A-Z]{3}-\d{6}-[A-Z0-9]{6})\?baru=1$#', $br->location(), $mm)) {
        throw new AssertionFailed('Checkout tidak berakhir di halaman pesanan. Status ' . $br->status . ' lokasi ' . $br->location());
    }
    return ['order_id' => $mm[1], 'request_id' => $rid];
};

$adminLogin = static function (string $base, string $user, string $pass): Browser {
    $a = new Browser($base);
    $a->get('/admin/login');
    $a->post('/admin/login', ['_csrf' => $a->csrf(), 'username' => $user, 'password' => $pass]);
    if ($a->location() !== '/admin') {
        throw new AssertionFailed("Login admin {$user} gagal (periksa password di variabel lingkungan).");
    }
    $a->get('/admin');
    return $a;
};

$initial = null;
$bogorOrder = null;
$ujiOrder = null;

TestKit::run('H01', 'Kedua website hidup dan memakai sumber data yang sama', function () use ($bogorUrl, $ujiUrl) {
    $a = (new Browser($bogorUrl))->get('/api/status')->json();
    $b = (new Browser($ujiUrl))->get('/api/status')->json();
    TestKit::same(true, $a['ok'], 'status Bogor');
    TestKit::same(true, $b['ok'], 'status uji');
    TestKit::same('bogor', $a['branch_id'], 'branch Bogor');
    TestKit::same('uji', $b['branch_id'], 'branch uji');
    TestKit::same($a['source']['label'], $b['source']['label'], 'sumber data sama');
    return $a['source']['label'];
});

TestKit::run('H02', 'Katalog website menampilkan data master (P037 Rp79.000)', function () use ($bogorUrl) {
    $b = (new Browser($bogorUrl))->get('/katalog?q=topi');
    TestKit::same(200, $b->status, 'HTTP');
    TestKit::assert(str_contains($b->body, 'Topi Baseball') && str_contains($b->body, 'Rp79.000'), 'P037 tampil dengan harga master');
    $all = (new Browser($bogorUrl))->get('/katalog');
    TestKit::same(42, substr_count($all->body, 'class="product-card"'), 'jumlah kartu produk');
    return '42 kartu produk di katalog';
});

TestKit::run('H03', 'Keranjang lewat website: tambah, ubah, hapus; stok tidak berubah', function () use ($bogorUrl, $apiStock) {
    $before = $apiStock($bogorUrl, 'adjustable_navy');
    if ($before < 2) {
        throw new SkipTest('Stok P037 navy < 2 di data demo.');
    }
    $br = new Browser($bogorUrl);
    $br->get('/produk/P037');
    $br->post('/keranjang/tambah', ['_csrf' => $br->csrf(), 'product_id' => 'P037', 'size' => 'adjustable', 'color' => 'navy', 'qty' => '1']);
    $br->get('/keranjang');
    TestKit::assert(str_contains($br->body, 'Topi Baseball') && str_contains($br->body, 'value="1"'), 'item ada qty 1');
    $br->post('/keranjang/ubah', ['_csrf' => $br->csrf(), 'product_id' => 'P037', 'variant' => 'adjustable_navy', 'qty' => '2']);
    $br->get('/keranjang');
    TestKit::assert(str_contains($br->body, 'value="2"') && str_contains($br->body, 'Rp158.000'), 'qty 2, subtotal Rp158.000');
    TestKit::same($before, $apiStock($bogorUrl, 'adjustable_navy'), 'stok tetap');
    $br->post('/keranjang/hapus', ['_csrf' => $br->csrf(), 'product_id' => 'P037', 'variant' => 'adjustable_navy']);
    $br->get('/keranjang');
    TestKit::assert(str_contains($br->body, 'Keranjangmu masih kosong'), 'pesan keranjang kosong');
    return "stok navy tetap {$before}";
});

TestKit::run('H04', 'Checkout di Bogor memotong stok global 1; kedua website membaca angka baru', function () use ($bogorUrl, $ujiUrl, $apiStock, $buy, &$initial, &$bogorOrder) {
    $initial = $apiStock($bogorUrl);
    if ($initial < 3) {
        throw new SkipTest("Stok P037 hitam tinggal {$initial}; butuh minimal 3.");
    }
    TestKit::same($initial, $apiStock($ujiUrl), 'stok awal sama di kedua website');
    $br = new Browser($bogorUrl);
    $bogorOrder = $buy($br, 1) + ['browser' => $br];
    TestKit::same($initial - 1, $apiStock($bogorUrl), 'stok di website Bogor');
    TestKit::same($initial - 1, $apiStock($ujiUrl), 'stok di website uji');
    $br->get('/pesanan/' . $bogorOrder['order_id'] . '?baru=1');
    TestKit::assert(str_contains($br->body, 'Pembayaran simulasi berhasil') && str_contains($br->body, 'Rp79.000'), 'halaman sukses total Rp79.000');
    return "{$bogorOrder['order_id']}: {$initial} -> " . ($initial - 1) . ' di kedua website';
});

TestKit::run('H05', 'Konfirmasi dikirim ulang (klik ganda) -> pesanan sama, stok tidak dipotong lagi', function () use ($bogorUrl, $apiStock, &$bogorOrder, &$initial) {
    if (!$bogorOrder) {
        throw new SkipTest('H04 tidak berjalan.');
    }
    $br = $bogorOrder['browser'];
    $br->get('/produk/P037'); // ambil token CSRF sesi ini
    $br->post('/checkout/konfirmasi', ['_csrf' => $br->csrf(), 'request_id' => $bogorOrder['request_id']]);
    TestKit::same('/pesanan/' . $bogorOrder['order_id'] . '?baru=1', $br->location(), 'diarahkan ke pesanan yang sama');
    TestKit::same($initial - 1, $apiStock($bogorUrl), 'stok tetap');
    $br->get('/pesanan');
    TestKit::same(1, substr_count($br->body, 'class="order-row card"'), 'hanya satu pesanan di daftar');
    return 'tetap satu pesanan';
});

TestKit::run('H06', 'Manipulasi input dari browser ditolak / dihitung ulang', function () use ($bogorUrl, $apiStock) {
    $br = new Browser($bogorUrl);
    $br->get('/produk/P037');
    foreach (['1.5', '0', '-2', 'abc'] as $bad) {
        $br->post('/keranjang/tambah', ['_csrf' => $br->csrf(), 'product_id' => 'P037', 'size' => 'adjustable', 'color' => 'hitam', 'qty' => $bad]);
        $br->follow();
        TestKit::assert(str_contains($br->body, 'alert-error'), "qty {$bad} ditolak");
    }
    $br->post('/keranjang/tambah', ['_csrf' => $br->csrf(), 'product_id' => 'P037', 'size' => 'xl', 'color' => 'ungu', 'qty' => '1']);
    $br->follow();
    TestKit::assert(str_contains($br->body, 'alert-error'), 'varian palsu ditolak');
    $br->get('/keranjang');
    TestKit::assert(str_contains($br->body, 'Keranjangmu masih kosong'), 'tidak ada yang masuk keranjang');
    $br->post('/keranjang/tambah', ['product_id' => 'P037', 'size' => 'adjustable', 'color' => 'hitam', 'qty' => '1']);
    TestKit::same(419, $br->status, 'tanpa token CSRF ditolak');
    return 'qty 1.5/0/-2/abc, varian palsu, dan tanpa CSRF ditolak';
});

TestKit::run('H06b', 'Field harga/ongkir palsu di form checkout diabaikan server', function () use ($ujiUrl, $buy, $apiStock, &$ujiOrder) {
    $br = new Browser($ujiUrl);
    $before = $apiStock($ujiUrl);
    $ujiOrder = $buy($br, 2, 'reguler', ['price' => '1', 'unit_price' => '1', 'total' => '1', 'shipping_fee' => '0']) + ['browser' => $br];
    $br->get('/pesanan/' . $ujiOrder['order_id']);
    TestKit::assert(str_contains($br->body, 'Rp168.000'), 'total = 2 x Rp79.000 + Rp10.000');
    TestKit::same($before - 2, $apiStock($ujiUrl), 'stok berkurang 2');
    return "{$ujiOrder['order_id']} total Rp168.000 (dibeli lewat instance uji)";
});

TestKit::run('H07', 'Pesanan hanya bisa dilihat sesi pemiliknya', function () use ($bogorUrl, &$bogorOrder) {
    if (!$bogorOrder) {
        throw new SkipTest('H04 tidak berjalan.');
    }
    $other = new Browser($bogorUrl);
    $other->get('/pesanan/' . $bogorOrder['order_id']);
    TestKit::same(404, $other->status, 'sesi lain');
    return 'sesi lain mendapat 404';
});

TestKit::run('H08', 'Admin Bogor tidak bisa membaca/mengubah pesanan instance uji', function () use ($bogorUrl, $adminLogin, $bogorAdminUser, $adminPass, &$ujiOrder) {
    if ($adminPass === '') {
        throw new SkipTest('Set ALVERSE_ADMIN_PASSWORD untuk menjalankan tes admin.');
    }
    if (!$ujiOrder) {
        throw new SkipTest('H06b tidak berjalan.');
    }
    $a = $adminLogin($bogorUrl, $bogorAdminUser, $adminPass);
    $csrf = $a->csrf();
    $a->get('/admin/pesanan/' . $ujiOrder['order_id']);
    TestKit::same(404, $a->status, 'detail pesanan uji');
    $a->post('/admin/pesanan/' . $ujiOrder['order_id'] . '/batal', ['_csrf' => $csrf, 'reason' => 'Mencoba membatalkan milik cabang lain']);
    TestKit::same(404, $a->status, 'batal pesanan uji');
    $a->get('/admin/pesanan');
    TestKit::assert(!str_contains($a->body, $ujiOrder['order_id']), 'tidak tampil di daftar');
    return '404 untuk detail dan pembatalan';
});

TestKit::run('H09', 'Admin Bogor membatalkan pesanan Bogor dua kali -> stok kembali sekali', function () use ($bogorUrl, $adminLogin, $bogorAdminUser, $adminPass, $apiStock, &$bogorOrder, &$initial) {
    if ($adminPass === '' || !$bogorOrder) {
        throw new SkipTest('Butuh ALVERSE_ADMIN_PASSWORD dan H04.');
    }
    $a = $adminLogin($bogorUrl, $bogorAdminUser, $adminPass);
    $before = $apiStock($bogorUrl);
    $a->get('/admin/pesanan/' . $bogorOrder['order_id']);
    TestKit::same(200, $a->status, 'detail pesanan sendiri');
    $csrf = $a->csrf();
    $a->post('/admin/pesanan/' . $bogorOrder['order_id'] . '/batal', ['_csrf' => $csrf, 'reason' => 'Uji pembatalan end-to-end']);
    $a->follow();
    TestKit::assert(str_contains($a->body, 'Stok global dikembalikan satu kali'), 'pesan batal pertama');
    $a->post('/admin/pesanan/' . $bogorOrder['order_id'] . '/batal', ['_csrf' => $csrf, 'reason' => 'Uji pembatalan end-to-end']);
    $a->follow();
    TestKit::assert(str_contains($a->body, 'TIDAK dikembalikan lagi'), 'pesan batal kedua');
    TestKit::same($before + 1, $apiStock($bogorUrl), 'stok naik tepat 1');
    return "stok {$before} -> " . ($before + 1) . ' (batal kedua tidak menambah)';
});

TestKit::run('H10', 'Bersihkan: admin uji membatalkan pesanan uji, stok kembali ke awal', function () use ($ujiUrl, $adminLogin, $ujiAdminUser, $ujiAdminPass, $apiStock, &$ujiOrder, &$initial) {
    if ($ujiAdminPass === '' || !$ujiOrder) {
        throw new SkipTest('Butuh password admin uji dan H06b.');
    }
    $a = $adminLogin($ujiUrl, $ujiAdminUser, $ujiAdminPass);
    $a->get('/admin/pesanan/' . $ujiOrder['order_id']);
    $a->post('/admin/pesanan/' . $ujiOrder['order_id'] . '/batal', ['_csrf' => $a->csrf(), 'reason' => 'Pembersihan tes end-to-end']);
    if ($initial !== null) {
        TestKit::same($initial, $apiStock($ujiUrl), 'stok kembali ke angka awal');
    }
    return "stok kembali ke {$initial}";
});

$code = TestKit::summary('Hasil tes HTTP end-to-end');
TestKit::writeReport(__DIR__ . '/output/hasil-tes-http.md', 'Hasil tes HTTP end-to-end Alverse Bogor');
echo 'Laporan: tests/output/hasil-tes-http.md' . PHP_EOL;
exit($code);
