<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Layanan data (Redis lokal / pusat) tidak dapat dihubungi atau menolak koneksi.
 * Website TIDAK boleh menampilkan keberhasilan palsu ketika exception ini muncul.
 *
 * $outcomeUnknown = true berarti permintaan tulis mungkin sudah sampai ke server
 * tetapi jawabannya tidak diterima (contoh: koneksi putus saat checkout).
 * Website wajib mencari hasil request_id yang sama sebelum mencoba lagi.
 */
class DataServiceUnavailable extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $outcomeUnknown = false,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
