<?php
/**
 * Konfigurasi aplikasi. Semua nilai dibaca dari file .env (lihat .env.example).
 * Jangan menulis password di file ini; file ini ikut masuk Git.
 *
 * @var callable $env  fungsi env(KEY, default) yang disiapkan bootstrap.php
 */

$branchId = strtolower(trim((string) $env('BRANCH_ID', 'bogor')));

return [
    'app' => [
        'env'   => $env('APP_ENV', 'local'),
        'debug' => filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
        'url'   => rtrim((string) $env('APP_URL', 'http://localhost:8001'), '/'),
        'timezone' => 'Asia/Jakarta',
    ],

    'branch' => [
        'id'              => $branchId,
        'name'            => $env('BRANCH_NAME', 'Bogor Kota'),
        'developer'       => $env('DEVELOPER_NAME', 'Hanif'),
        'address'         => $env('BRANCH_ADDRESS', 'Alamat demo, belum ditetapkan'),
        'phone'           => $env('BRANCH_PHONE', 'Kontak demo, belum ditetapkan'),
        'pickup_hours'    => $env('PICKUP_HOURS', '10.00–20.00 WIB (jam demo)'),
        'pickup_location' => $env('PICKUP_LOCATION', 'Lokasi pickup simulasi (alamat demo)'),
    ],

    'session' => [
        // Nama cookie WAJIB berbeda per website: cookie localhost tidak dipisahkan per port.
        'name'             => $env('SESSION_NAME', 'ALVERSE_' . strtoupper($branchId) . '_SESS'),
        'lifetime'         => 60 * 60 * 24 * 30, // 30 hari (keranjang & daftar pesanan pelanggan)
        'admin_idle_limit' => 60 * 60 * 2,       // admin otomatis keluar setelah 2 jam tidak aktif
    ],

    'shipping' => [
        // Tarif simulasi dari panduan v2: reguler Rp10.000 per pesanan, pickup Rp0.
        'reguler' => (int) $env('SHIPPING_REGULER', '10000'),
        'pickup'  => (int) $env('SHIPPING_PICKUP', '0'),
    ],

    'cart' => [
        'max_qty_per_line' => 99,
    ],

    'data' => [
        'driver' => strtolower((string) $env('DATA_DRIVER', 'redis')), // redis | central
    ],

    'redis' => [
        'host'     => $env('REDIS_HOST', '127.0.0.1'),
        'port'     => (int) $env('REDIS_PORT', '6379'),
        'username' => $env('REDIS_USERNAME', ''),
        'password' => $env('REDIS_PASSWORD', ''),
        'database' => (int) $env('REDIS_DB', '0'),
        'timeout'  => (float) $env('REDIS_TIMEOUT', '2'),
        'test_database' => (int) $env('REDIS_TEST_DB', '15'),
    ],

    'central' => [
        'base_url' => rtrim((string) $env('CENTRAL_BASE_URL', ''), '/'),
        'api_key'  => $env('CENTRAL_API_KEY', ''),
        'timeout'  => (float) $env('CENTRAL_TIMEOUT', '5'),
    ],

    'admin' => [
        'username'      => $env('ADMIN_USERNAME', 'admin_' . $branchId),
        'password_hash' => $env('ADMIN_PASSWORD_HASH', ''),
        // Akun cabang SELALU admin_cabang. Peran admin_pusat (restock/koreksi) tidak tersedia di website cabang.
        'role'          => 'admin_cabang',
    ],

    // Tautan website cabang. Selama BRANCH_URL_* di .env masih kosong, dipakai tautan SEMENTARA
    // berdomain .example (domain khusus contoh, tidak pernah aktif). Isi .env saat tautan asli ada.
    'branch_urls' => [
        'dramaga'   => $env('BRANCH_URL_DRAMAGA', '') ?: 'https://alverse-dramaga.example/',
        'bogor'     => $env('BRANCH_URL_BOGOR', '') ?: 'https://alverse-bogor.example/',
        'bandung'   => $env('BRANCH_URL_BANDUNG', '') ?: 'https://alverse-bandung.example/',
        'depok'     => $env('BRANCH_URL_DEPOK', '') ?: 'https://alverse-depok.example/',
        'jaksel'    => $env('BRANCH_URL_JAKSEL', '') ?: 'https://alverse-jaksel.example/',
        'bekasi'    => $env('BRANCH_URL_BEKASI', '') ?: 'https://alverse-bekasi.example/',
        'tangerang' => $env('BRANCH_URL_TANGERANG', '') ?: 'https://alverse-tangerang.example/',
    ],

    // Mode preview lokal (misalnya Mobile Preview di dalam iframe). Bawaan MATI.
    // Hanya berlaku jika APP_ENV=local; di environment lain selalu diabaikan.
    'preview' => [
        'frame_enabled'   => filter_var($env('LOCAL_PREVIEW_FRAME', 'false'), FILTER_VALIDATE_BOOLEAN),
        'frame_ancestors' => (string) $env('LOCAL_PREVIEW_FRAME_ANCESTORS', ''),
    ],

    'stock_poll_ms' => 5000,
];
