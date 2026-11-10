<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Aturan status pesanan (Panduan Hanif v2, hal. 4).
 *   Reguler : dikonfirmasi > dikemas > dikirim > selesai   (kurir + resi wajib sebelum dikirim)
 *   Pickup  : dikonfirmasi > siap_diambil > selesai        (cocokkan kode pickup saat penyerahan)
 *   Batal   : dikonfirmasi > dibatalkan                    (alasan wajib; stok kembali tepat sekali)
 */
final class OrderRules
{
    public const METHODS = ['reguler', 'pickup'];

    public const STATUS_LABELS = [
        'dikonfirmasi' => 'Dikonfirmasi',
        'dikemas'      => 'Dikemas',
        'dikirim'      => 'Dikirim',
        'siap_diambil' => 'Siap Diambil',
        'selesai'      => 'Selesai',
        'dibatalkan'   => 'Dibatalkan',
    ];

    /** Transisi maju yang sah per metode (pembatalan ditangani terpisah). */
    public const TRANSITIONS = [
        'reguler' => [
            'dikonfirmasi' => 'dikemas',
            'dikemas'      => 'dikirim',
            'dikirim'      => 'selesai',
        ],
        'pickup' => [
            'dikonfirmasi' => 'siap_diambil',
            'siap_diambil' => 'selesai',
        ],
    ];

    public const CANCELLABLE_STATUS = 'dikonfirmasi';

    public static function label(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    public static function methodLabel(string $method): string
    {
        return $method === 'pickup' ? 'Pickup (ambil di cabang)' : 'Pengiriman reguler';
    }

    public static function nextStatus(string $method, string $current): ?string
    {
        return self::TRANSITIONS[$method][$current] ?? null;
    }

    public static function canCancel(string $status): bool
    {
        return $status === self::CANCELLABLE_STATUS;
    }

    /** Urutan langkah untuk ditampilkan sebagai timeline. */
    public static function steps(string $method): array
    {
        return $method === 'pickup'
            ? ['dikonfirmasi', 'siap_diambil', 'selesai']
            : ['dikonfirmasi', 'dikemas', 'dikirim', 'selesai'];
    }

    /** Teks tombol tindakan admin untuk status berikutnya. */
    public static function actionLabel(string $next): string
    {
        return match ($next) {
            'dikemas'      => 'Tandai Dikemas',
            'dikirim'      => 'Tandai Dikirim',
            'siap_diambil' => 'Tandai Siap Diambil',
            'selesai'      => 'Tandai Selesai',
            default        => 'Perbarui',
        };
    }
}
