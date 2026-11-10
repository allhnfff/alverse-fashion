<?php

declare(strict_types=1);

namespace App\Domain;

use App\Data\CentralDataService;

/**
 * Aturan bisnis keranjang (lapisan antara controller dan layanan data).
 * - Harga SELALU diambil dari master di server, bukan dari browser.
 * - Menambah keranjang TIDAK mengurangi atau memesan stok.
 */
final class CartService
{
    public function __construct(
        private readonly CentralDataService $data,
        private readonly string $branchId,
        private readonly int $maxQty = 99
    ) {
    }

    /** Cari varian di produk master. */
    public static function findVariant(array $product, string $variantCode): ?array
    {
        foreach ($product['variants'] as $v) {
            if ($v['code'] === $variantCode) {
                return $v;
            }
        }
        return null;
    }

    /**
     * Tambah ke keranjang: jumlah baru = jumlah lama + qty.
     * Ditolak jika produk/varian tidak sah atau melebihi stok global SAAT INI
     * (ini bukan reservasi; checkout tetap memeriksa ulang).
     */
    public function add(string $sessionId, string $productId, string $variant, int $qty): void
    {
        $product = $this->data->getProduct($productId);
        if ($product === null || !$product['active']) {
            throw new ValidationError('Produk tidak tersedia.');
        }
        if (self::findVariant($product, $variant) === null) {
            throw new ValidationError('Pilih ukuran dan warna yang tersedia.');
        }
        $stock = $this->data->getStock($productId)['variants'];
        if (!array_key_exists($variant, $stock)) {
            throw new ValidationError('Kombinasi ukuran dan warna ini tidak tersedia.');
        }
        $current = 0;
        foreach ($this->data->getCart($sessionId, $this->branchId) as $line) {
            if ($line['product_id'] === $productId && $line['variant'] === $variant) {
                $current = $line['qty'];
            }
        }
        $newQty = $current + $qty;
        if ($newQty > $this->maxQty) {
            throw new ValidationError("Jumlah maksimal {$this->maxQty} per item.");
        }
        if ($newQty > $stock[$variant]) {
            throw new ValidationError(sprintf(
                'Stok bersama tidak cukup. Tersedia %d, di keranjang %d.',
                $stock[$variant],
                $current
            ));
        }
        $this->data->setCartItem($sessionId, $this->branchId, $productId, $variant, $newQty);
    }

    public function update(string $sessionId, string $productId, string $variant, int $qty): void
    {
        $product = $this->data->getProduct($productId);
        if ($product === null || self::findVariant($product, $variant) === null) {
            throw new ValidationError('Item keranjang tidak valid.');
        }
        $this->data->setCartItem($sessionId, $this->branchId, $productId, $variant, $qty);
    }

    public function remove(string $sessionId, string $productId, string $variant): void
    {
        $this->data->removeCartItem($sessionId, $this->branchId, $productId, $variant);
    }

    /**
     * Keranjang lengkap dengan nama, label varian, harga master, dan stok global terbaru.
     * @return array{lines: list<array<string,mixed>>, subtotal:int, units:int, has_problem:bool}
     */
    public function detailed(string $sessionId): array
    {
        $raw = $this->data->getCart($sessionId, $this->branchId);
        $stocks = $raw ? $this->data->getStockMany(array_column($raw, 'product_id')) : [];
        $lines = [];
        $subtotal = 0;
        $units = 0;
        $problem = false;
        foreach ($raw as $line) {
            $product = $this->data->getProduct($line['product_id']);
            $variant = $product ? self::findVariant($product, $line['variant']) : null;
            $available = $stocks[$line['product_id']][$line['variant']] ?? null;
            $issue = null;
            if ($product === null || !$product['active'] || $variant === null || $available === null) {
                $issue = 'Item ini tidak lagi tersedia. Silakan hapus.';
            } elseif ($available < $line['qty']) {
                $issue = $available === 0
                    ? 'Stok bersama habis.'
                    : "Stok bersama tinggal {$available}. Kurangi jumlahnya.";
            }
            $price = $product['price'] ?? 0;
            $lineTotal = $issue === 'Item ini tidak lagi tersedia. Silakan hapus.' ? 0 : $price * $line['qty'];
            $lines[] = [
                'product_id'  => $line['product_id'],
                'variant'     => $line['variant'],
                'qty'         => $line['qty'],
                'name'        => $product['name'] ?? $line['product_id'],
                'image_url'   => $product['image_url'] ?? '',
                'size_label'  => $variant['size_label'] ?? '-',
                'color_label' => $variant['color_label'] ?? '-',
                'unit_price'  => $price,
                'line_total'  => $lineTotal,
                'available'   => $available,
                'issue'       => $issue,
            ];
            if ($issue !== null) {
                $problem = true;
            }
            $subtotal += $lineTotal;
            $units += $line['qty'];
        }
        return ['lines' => $lines, 'subtotal' => $subtotal, 'units' => $units, 'has_problem' => $problem];
    }

    public function count(string $sessionId): int
    {
        return array_sum(array_column($this->data->getCart($sessionId, $this->branchId), 'qty'));
    }
}
