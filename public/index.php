<?php

declare(strict_types=1);

/**
 * PINTU MASUK SEMUA HALAMAN (front controller).
 * Jalankan: php -S localhost:8001 -t public public/index.php
 */

// Built-in server PHP: biarkan file statis (CSS/JS/gambar) dilayani langsung.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

use App\App;
use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\OrderController;
use App\Controllers\ShopController;
use App\Data\DataServiceUnavailable;
use App\Http\HttpException;
use App\Http\Router;
use App\Http\SecurityHeaders;
use App\Http\Session;
use App\Http\View;

$isApi = str_starts_with((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/api/');

try {
    require dirname(__DIR__) . '/app/bootstrap.php';
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<h1>Konfigurasi bermasalah</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Header keamanan dasar (lihat App\Http\SecurityHeaders; mode preview lokal hanya jika APP_ENV=local)
foreach (SecurityHeaders::build((array) App::config('app'), (array) App::config('preview')) as $h) {
    header($h);
}

Session::start();

$shop = new ShopController();
$cart = new CartController();
$checkout = new CheckoutController();
$orders = new OrderController();
$api = new ApiController();
$admin = new AdminController();

$r = new Router();
// ---- Pelanggan ----
$r->get('/', [$shop, 'home']);
$r->get('/katalog', [$shop, 'catalog']);
$r->get('/produk/{id}', [$shop, 'product']);
$r->get('/cabang', [$shop, 'branch']);
$r->get('/keranjang', [$cart, 'show']);
$r->post('/keranjang/tambah', [$cart, 'add']);
$r->post('/keranjang/ubah', [$cart, 'update']);
$r->post('/keranjang/hapus', [$cart, 'remove']);
$r->get('/checkout', [$checkout, 'show']);
$r->post('/checkout/tinjau', [$checkout, 'review']);
$r->get('/checkout/tinjau', [$checkout, 'reviewPage']);
$r->post('/checkout/konfirmasi', [$checkout, 'confirm']);
$r->post('/checkout/periksa', [$checkout, 'verify']);
$r->post('/checkout/batal-bayar', [$checkout, 'cancelPayment']);
$r->get('/pesanan', [$orders, 'index']);
$r->get('/pesanan/{id}', [$orders, 'show']);
$r->post('/pesanan/{id}/batal', [$orders, 'cancel']);
// ---- API untuk JavaScript (lewat server, bukan langsung ke Redis) ----
$r->get('/api/stok/{id}', [$api, 'stock']);
$r->get('/api/status', [$api, 'status']);
// ---- Admin cabang ----
$r->get('/admin/login', [$admin, 'loginForm']);
$r->post('/admin/login', [$admin, 'login']);
$r->post('/admin/logout', [$admin, 'logout']);
$r->get('/admin', [$admin, 'dashboard']);
$r->get('/admin/pesanan', [$admin, 'orders']);
$r->get('/admin/pesanan/{id}', [$admin, 'orderShow']);
$r->post('/admin/pesanan/{id}/status', [$admin, 'updateStatus']);
$r->post('/admin/pesanan/{id}/batal', [$admin, 'cancel']);
$r->get('/admin/stok', [$admin, 'stock']);
$r->get('/admin/rekap', [$admin, 'sales']);

$renderError = static function (int $status, string $title, string $message, bool $isApi): void {
    if ($isApi) {
        View::json(['ok' => false, 'message' => $message], $status);
        return;
    }
    try {
        View::render('shop/error', ['title' => $title, 'status' => $status, 'message' => $message, 'flash' => [],
            'source' => App::data()->describe(), 'cartCount' => null, 'searchQ' => ''], 'layout', $status);
    } catch (\Throwable) {
        http_response_code($status);
        echo '<h1>' . htmlspecialchars($title) . '</h1><p>' . htmlspecialchars($message) . '</p>';
    }
};

try {
    $r->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
} catch (HttpException $e) {
    $renderError($e->status, 'Terjadi kendala', $e->getMessage(), $isApi);
} catch (DataServiceUnavailable $e) {
    error_log('[alverse] layanan data: ' . $e->getMessage());
    $msg = 'Halaman sedang tidak bisa dimuat. Coba lagi sebentar lagi.';
    if (App::config('app.debug')) {
        $msg .= ' Detail: ' . $e->getMessage();
    }
    $renderError(503, 'Sedang ada gangguan', $msg, $isApi);
} catch (\Throwable $e) {
    error_log('[alverse] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $msg = App::config('app.debug')
        ? $e::class . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        : 'Terjadi kesalahan pada server.';
    $renderError(500, 'Kesalahan server', $msg, $isApi);
}
