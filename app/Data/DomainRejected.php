<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Layanan data menolak permintaan karena aturan bisnis (bukan karena gangguan koneksi).
 * Kode error mengikuti docs/KONTRAK-DATA.md, misalnya:
 *   INSUFFICIENT_STOCK, VARIANT_NOT_FOUND, PRODUCT_UNAVAILABLE, INVALID_QTY,
 *   IDEMPOTENCY_CONFLICT, ORDER_NOT_FOUND, INVALID_TRANSITION, STATUS_CONFLICT,
 *   NOT_CANCELLABLE, TRACKING_REQUIRED, PICKUP_CODE_MISMATCH, REASON_REQUIRED, EMPTY_ORDER
 */
final class DomainRejected extends \RuntimeException
{
    /** @param array<string,mixed> $details */
    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        public readonly array $details = []
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($errorCode, $details));
    }

    /** Pesan berbahasa Indonesia yang aman untuk pengguna. */
    public static function defaultMessage(string $code, array $details = []): string
    {
        return match ($code) {
            'INSUFFICIENT_STOCK'   => 'Stok bersama tidak mencukupi untuk sebagian item. Silakan sesuaikan jumlah di keranjang.',
            'VARIANT_NOT_FOUND'    => 'Kombinasi ukuran dan warna tidak tersedia.',
            'PRODUCT_UNAVAILABLE'  => 'Produk tidak tersedia.',
            'INVALID_QTY'          => 'Jumlah harus bilangan bulat positif.',
            'EMPTY_ORDER'          => 'Keranjang kosong.',
            'IDEMPOTENCY_CONFLICT' => 'Permintaan checkout ini sudah dipakai untuk isi pesanan yang berbeda. Silakan tinjau ulang checkout.',
            'ORDER_NOT_FOUND'      => 'Pesanan tidak ditemukan.',
            'INVALID_TRANSITION'   => 'Perubahan status tersebut tidak diizinkan.',
            'STATUS_CONFLICT'      => 'Status pesanan sudah berubah. Muat ulang halaman lalu coba lagi.',
            'NOT_CANCELLABLE'      => 'Pesanan hanya dapat dibatalkan saat berstatus Dikonfirmasi.',
            'TRACKING_REQUIRED'    => 'Isi kurir dan nomor resi simulasi sebelum menandai Dikirim.',
            'PICKUP_CODE_MISMATCH' => 'Kode pickup tidak cocok.',
            'REASON_REQUIRED'      => 'Alasan pembatalan wajib diisi.',
            'FORBIDDEN'            => 'Kamu tidak berhak mengakses data ini.',
            default                => 'Permintaan ditolak oleh layanan data (' . $code . ').',
        };
    }
}
