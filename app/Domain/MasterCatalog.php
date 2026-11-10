<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Mengubah master produk dari config/products.php menjadi bentuk data kontrak:
 * kode ukuran, kode warna, dan kode varian "{ukuran}_{warna}" (contoh: adjustable_hitam).
 *
 * Catatan: daftar kode ukuran/warna adalah USULAN LOKAL mengikuti contoh panduan
 * (adjustable_hitam, ukuran tunggal = ONE). Pusat membekukan daftar kode final sebelum integrasi.
 */
final class MasterCatalog
{
    public const CATEGORIES = [
        'atasan'        => 'Atasan',
        'outerwear'     => 'Outerwear',
        'bawahan'       => 'Bawahan',
        'busana-muslim' => 'Busana Muslim',
        'sepatu-sandal' => 'Sepatu & Sandal',
        'tas-aksesori'  => 'Tas & Aksesori',
    ];

    public static function categoryLabel(string $key): string
    {
        return self::CATEGORIES[$key] ?? ucfirst($key);
    }

    /** @return list<array{code:string,label:string}> */
    public static function sizes(string $spec, string $singleLabel): array
    {
        $letters = static fn (array $list): array => array_map(
            static fn (string $s): array => ['code' => strtolower($s), 'label' => $s],
            $list
        );
        return match ($spec) {
            'S-XXL'    => $letters(['S', 'M', 'L', 'XL', 'XXL']),
            'S-XL'     => $letters(['S', 'M', 'L', 'XL']),
            'M-XL'     => $letters(['M', 'L', 'XL']),
            '28-36'    => array_map(static fn (int $n): array => ['code' => (string) $n, 'label' => (string) $n], [28, 30, 32, 34, 36]),
            'EUR38-44' => array_map(static fn (int $n): array => ['code' => (string) $n, 'label' => 'EUR ' . $n], range(38, 44)),
            'ADJ'      => [['code' => 'adjustable', 'label' => 'Adjustable']],
            '105/115'  => [['code' => '105', 'label' => '105 cm'], ['code' => '115', 'label' => '115 cm']],
            'ONE'      => [['code' => 'one', 'label' => $singleLabel]],
            default    => throw new \InvalidArgumentException("size_spec tidak dikenal: {$spec}"),
        };
    }

    public static function colorCode(string $label): string
    {
        $code = strtolower(trim($label));
        $code = preg_replace('/[^a-z0-9]+/', '-', $code) ?? $code;
        return trim($code, '-');
    }

    /**
     * @param array<string,mixed> $row satu baris dari config/products.php
     * @return array<string,mixed> produk dalam bentuk kontrak
     */
    public static function normalize(array $row): array
    {
        $sizes = self::sizes($row['size_spec'], $row['size_label']);
        $colors = array_map(
            static fn (string $c): array => ['code' => self::colorCode($c), 'label' => $c],
            $row['colors']
        );
        $categoryLabel = self::categoryLabel($row['category']);
        return [
            'product_id'     => $row['id'],
            'name'           => $row['name'],
            'category'       => $row['category'],
            'category_label' => $categoryLabel,
            'price'          => (int) $row['price'],
            'size_label'     => $row['size_label'],
            'color_label'    => implode(', ', $row['colors']),
            'sizes'          => $sizes,
            'colors'         => $colors,
            // Deskripsi & foto belum ada di daftar harga -> diberi label DEMO.
            'description'    => sprintf(
                '[Deskripsi demo] %s dari kategori %s. Ukuran: %s. Warna: %s. Deskripsi resmi menunggu data pusat.',
                $row['name'],
                $categoryLabel,
                $row['size_label'],
                implode(', ', $row['colors'])
            ),
            'image_url'      => '/assets/img/produk/' . $row['category'] . '.svg',
            'image_is_demo'  => true,
            'active'         => true,
        ];
    }

    /** Daftar kode varian lengkap (ukuran x warna). */
    public static function variantCodes(array $product): array
    {
        $codes = [];
        foreach ($product['sizes'] as $s) {
            foreach ($product['colors'] as $c) {
                $codes[] = $s['code'] . '_' . $c['code'];
            }
        }
        return $codes;
    }

    /** @return list<array<string,mixed>> */
    public static function fromConfig(string $file): array
    {
        $rows = require $file;
        return array_map([self::class, 'normalize'], $rows);
    }
}
