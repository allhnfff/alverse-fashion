<?php

declare(strict_types=1);

use App\App;

/** Escape teks sebelum dicetak ke HTML (mencegah XSS). */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format rupiah: 79000 -> "Rp79.000" */
function rupiah(int|float|string|null $amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

/** Ambil nilai konfigurasi, contoh: config('branch.name') */
function config(string $key, mixed $default = null): mixed
{
    return App::config($key, $default);
}

/** URL relatif aman untuk tautan internal. */
function url(string $path = '/', array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
    return $path . ($query ? '?' . http_build_query($query) : '');
}

/** URL aset statis dengan versi berdasarkan waktu ubah file (agar cache browser diperbarui). */
function asset(string $path): string
{
    $file = App::root() . '/public/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return '/' . ltrim($path, '/') . '?v=' . $v;
}

/**
 * Gambar produk untuk satu warna: public/assets/img/produk/foto/{ID}-{kode warna}.jpg (gambar AI dari desain).
 * Data di Redis tidak diubah; kalau file warna itu tidak ada, dipakai warna pertama, lalu image_url dari data.
 */
function product_image(array $product, ?string $colorCode = null): string
{
    $id = (string) ($product['product_id'] ?? '');
    $codes = $colorCode !== null && $colorCode !== '' ? [$colorCode] : [];
    foreach ($product['colors'] ?? [] as $c) {
        $codes[] = (string) $c['code'];
    }
    if (preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
        foreach ($codes as $code) {
            if (!preg_match('/^[a-z0-9-]+$/', $code)) {
                continue;
            }
            $rel = 'assets/img/produk/foto/' . $id . '-' . $code . '.jpg';
            if (is_file(App::root() . '/public/' . $rel)) {
                return '/' . $rel;
            }
        }
    }
    return (string) ($product['image_url'] ?? '');
}

/** Gambar ikon kategori: satu foto produk wakil (pilihan sama dengan desain ponsel), kalau tidak ada pakai ilustrasi. */
function category_image(string $key): string
{
    $pick = [
        'atasan'        => 'P001-hitam',
        'outerwear'     => 'P009-biru',
        'bawahan'       => 'P015-biru',
        'busana-muslim' => 'P025-navy',
        'sepatu-sandal' => 'P029-putih',
        'tas-aksesori'  => 'P041-cokelat',
    ];
    if (isset($pick[$key]) && is_file(App::root() . '/public/assets/img/produk/foto/' . $pick[$key] . '.jpg')) {
        return '/assets/img/produk/foto/' . $pick[$key] . '.jpg';
    }
    return '/assets/img/produk/' . rawurlencode($key) . '.svg';
}

/**
 * Catatan riwayat tanpa awalan yang sama dengan label statusnya.
 * Skrip pembatalan menulis "Dibatalkan: <alasan>…", padahal tampilan sudah menulis "Dibatalkan: ",
 * sehingga tanpa ini riwayat terbaca "Dibatalkan: Dibatalkan: …".
 */
function history_note(string $statusLabel, string $note): string
{
    $prefix = $statusLabel . ': ';
    return str_starts_with($note, $prefix) ? substr($note, strlen($prefix)) : $note;
}

/** Label ukuran untuk pembeli: "EUR 40" ditulis "40" seperti di marketplace. Data aslinya tidak berubah. */
function size_text(string $label): string
{
    return (string) preg_replace('/^EUR\s+/', '', $label);
}

/**
 * Tabel panduan ukuran untuk satu produk dari config/size-guide.php.
 * Hanya baris untuk ukuran yang memang dijual produk itu. Null jika produk tidak punya tabel.
 *
 * @return array{cols: list<string>, rows: list<array{label: string, values: list<int|float>}>, note: string}|null
 */
function size_guide(array $product): ?array
{
    static $guide = null;
    $guide ??= require \App\App::root() . '/config/size-guide.php';
    $group = $guide['products'][(string) ($product['product_id'] ?? '')] ?? null;
    $def = $group !== null ? ($guide['groups'][$group] ?? null) : null;
    if ($def === null) {
        return null;
    }
    $rows = [];
    foreach ($product['sizes'] ?? [] as $s) {
        if (isset($def['rows'][$s['code']])) {
            $rows[] = ['label' => size_text((string) $s['label']), 'values' => $def['rows'][$s['code']]];
        }
    }
    return $rows ? ['cols' => $def['cols'], 'rows' => $rows, 'note' => $def['note'] ?? 'Dalam cm, diukur dalam posisi mendatar. Toleransi 1–2 cm.'] : null;
}

/** Angka cm gaya Indonesia: 24,5 bukan 24.5. */
function cm($n): string
{
    return str_replace('.', ',', (string) $n);
}

/**
 * Catatan riwayat untuk halaman pembeli. Catatan asli dari skrip Lua tetap utuh di data dan di halaman admin;
 * di sini hanya bagian teknis (stok global, "barang demo", catatan yang mengulang status) yang disembunyikan.
 */
function customer_history_note(string $statusLabel, string $note): string
{
    $note = trim(history_note($statusLabel, $note));
    $note = trim((string) preg_replace('/(;\s*stok global dipotong\.?|\s*Stok global dikembalikan\.?)$/i', '', $note));
    $map = [
        'Pembayaran simulasi berhasil'        => 'Pembayaran berhasil.',
        'Barang demo dinyatakan siap diambil' => 'Barang siap diambil di cabang.',
        'Diserahkan; kode pickup cocok'       => 'Sudah diambil dengan kode pickup.',
        'Pesanan selesai'                     => '',
    ];
    if (array_key_exists($note, $map)) {
        return $map[$note];
    }
    if (str_starts_with($note, 'Status menjadi ')) {
        return '';
    }
    $note = (string) preg_replace('/^Dikirim via /', 'Kurir ', $note);
    return $note !== '' && !str_ends_with($note, '.') ? $note . '.' : $note;
}

/** Kode warna dari kode varian "{ukuran}_{warna}". */
function variant_color(string $variant): string
{
    $pos = strpos($variant, '_');
    return $pos === false ? '' : substr($variant, $pos + 1);
}

/** Keterangan alt yang jujur: gambar AI atau ilustrasi kategori. */
function image_note(string $url): string
{
    return str_contains($url, '/produk/foto/') ? 'gambar AI' : 'gambar ilustrasi';
}

/** Input tersembunyi berisi token CSRF untuk setiap form POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(\App\Http\Csrf::token()) . '">';
}

/** Format waktu UNIX ke WIB. */
function wib(int|string|null $timestamp, string $format = 'd M Y, H:i'): string
{
    if ($timestamp === null || $timestamp === '' || (int) $timestamp <= 0) {
        return '-';
    }
    $dt = (new DateTimeImmutable('@' . (int) $timestamp))->setTimezone(new DateTimeZone('Asia/Jakarta'));
    return $dt->format($format) . ' WIB';
}

/** Ubah huruf kecil yang aman untuk teks UTF-8. */
function lower(string $text): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

/** Ikon garis sederhana (SVG inline, dekoratif). Nama yang tidak dikenal menghasilkan string kosong. */
function icon(string $name, int $size = 22): string
{
    static $paths = [
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'cart'    => '<path d="M5 8h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
        'pin'     => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'home'    => '<path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6h-4v6H5a1 1 0 0 1-1-1z"/>',
        'grid'    => '<rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/>',
        'truck'   => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="17.5" r="1.5"/><circle cx="17" cy="17.5" r="1.5"/>',
        'sync'    => '<path d="M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5"/>',
        'check'   => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'code'    => '<rect x="4" y="6" width="16" height="12" rx="2"/><path d="M8 10h2M12 10h4M8 14h8"/>',
        'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
        'prev'    => '<path d="m15 5-7 7 7 7"/>',
        'next'    => '<path d="m9 5 7 7-7 7"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}
