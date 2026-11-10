<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Validasi input dari browser. Semua dicek di SERVER; browser tidak dipercaya.
 * Setiap fungsi mengembalikan nilai yang sudah bersih atau melempar ValidationError.
 */
final class Validator
{
    public static function productId(mixed $value): string
    {
        $v = is_string($value) ? strtoupper(trim($value)) : '';
        if (!preg_match('/^P\d{3}$/', $v)) {
            throw new ValidationError('Kode produk tidak valid.');
        }
        return $v;
    }

    public static function variant(mixed $value): string
    {
        $v = is_string($value) ? strtolower(trim($value)) : '';
        if (!preg_match('/^[a-z0-9-]{1,20}_[a-z0-9-]{1,30}$/', $v)) {
            throw new ValidationError('Varian tidak valid.');
        }
        return $v;
    }

    /** Jumlah wajib bilangan bulat positif. "0", "-1", "1.5", "1e2", "abc" ditolak. */
    public static function quantity(mixed $value, int $max = 99): int
    {
        $v = is_string($value) || is_int($value) ? trim((string) $value) : '';
        if (!preg_match('/^[0-9]{1,3}$/', $v)) {
            throw new ValidationError('Jumlah harus bilangan bulat positif.');
        }
        $n = (int) $v;
        if ($n < 1) {
            throw new ValidationError('Jumlah minimal 1.');
        }
        if ($n > $max) {
            throw new ValidationError("Jumlah maksimal {$max} per item.");
        }
        return $n;
    }

    public static function text(mixed $value, string $field, int $min, int $max): string
    {
        $v = is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value) ?? '') : '';
        $len = function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
        if ($len < $min) {
            throw new ValidationError("{$field} minimal {$min} karakter.");
        }
        if ($len > $max) {
            throw new ValidationError("{$field} maksimal {$max} karakter.");
        }
        return $v;
    }

    public static function optionalText(mixed $value, string $field, int $max): string
    {
        $v = is_string($value) ? trim($value) : '';
        return $v === '' ? '' : self::text($v, $field, 0, $max);
    }

    public static function phone(mixed $value): string
    {
        $v = is_string($value) ? trim($value) : '';
        if (!preg_match('/^\+?[0-9][0-9 \-]{7,18}$/', $v)) {
            throw new ValidationError('Nomor kontak tidak valid (8–19 digit, boleh diawali +).');
        }
        return $v;
    }

    public static function method(mixed $value): string
    {
        $v = is_string($value) ? trim($value) : '';
        if (!in_array($v, OrderRules::METHODS, true)) {
            throw new ValidationError('Pilih metode pengiriman reguler atau pickup.');
        }
        return $v;
    }

    public static function status(mixed $value): string
    {
        $v = is_string($value) ? trim($value) : '';
        if (!array_key_exists($v, OrderRules::STATUS_LABELS)) {
            throw new ValidationError('Status tidak dikenal.');
        }
        return $v;
    }

    public static function orderId(mixed $value): string
    {
        $v = is_string($value) ? strtoupper(trim($value)) : '';
        if (!preg_match('/^[A-Z]{3}-\d{6}-[A-Z0-9]{6}$/', $v)) {
            throw new ValidationError('Kode pesanan tidak valid.');
        }
        return $v;
    }

    public static function requestId(mixed $value): string
    {
        $v = is_string($value) ? trim($value) : '';
        if (!preg_match('/^[a-f0-9]{32}$/', $v)) {
            throw new ValidationError('request_id tidak valid.');
        }
        return $v;
    }

    public static function date(mixed $value): ?string
    {
        $v = is_string($value) ? trim($value) : '';
        if ($v === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v, new \DateTimeZone('Asia/Jakarta'));
        if (!$d || $d->format('Y-m-d') !== $v) {
            throw new ValidationError('Format tanggal harus YYYY-MM-DD.');
        }
        return $v;
    }
}
