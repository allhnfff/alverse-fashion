<?php

declare(strict_types=1);

namespace App\Data;

/**
 * SATU ANTARMUKA LAYANAN DATA untuk website cabang.
 *
 * Dipenuhi oleh dua adapter dengan bentuk input/output yang SAMA:
 *   - RedisLocalAdapter  : Redis lokal untuk latihan (DATA_DRIVER=redis)
 *   - CentralHttpAdapter : layanan pusat Kelompok 6 (DATA_DRIVER=central)
 *
 * Nama operasi mengikuti Panduan Hanif v2 hal. 5:
 *   listProducts / getProduct / getStock, getCart / setCartItem / removeCartItem,
 *   checkout / getCheckoutResult, getOrder / listOrders,
 *   updateOrderStatus / cancelOrder, getSalesSummary
 *
 * Bentuk data lengkap + contoh JSON: docs/KONTRAK-DATA.md
 *
 * Aturan umum:
 *   - Gangguan koneksi  -> DataServiceUnavailable (JANGAN dianggap stok 0 / sukses).
 *   - Penolakan aturan  -> DomainRejected (kode error, pesan, detail).
 *   - $access untuk pesanan:
 *       ['type' => 'customer', 'branch_id' => 'bogor', 'session_hash' => '<sha256>']
 *       ['type' => 'branch_admin', 'branch_id' => 'bogor']
 *   - $actor untuk tindakan admin:
 *       ['branch_id' => 'bogor', 'username' => 'admin_bogor', 'role' => 'admin_cabang']
 */
interface CentralDataService
{
    /** Info sumber data untuk ditampilkan (tanpa akses jaringan). @return array{driver:string,label:string,dataset:string} */
    public function describe(): array;

    /** Cek koneksi. @return array{ok:bool,driver:string,detail:string,dataset?:string} (tidak melempar exception) */
    public function health(): array;

    /** @return list<array<string,mixed>> semua produk master (aktif & nonaktif), urut product_id */
    public function listProducts(): array;

    /** @return array<string,mixed>|null */
    public function getProduct(string $productId): ?array;

    /**
     * Stok GLOBAL satu produk. Satu angka per varian, berlaku untuk semua website.
     * @return array{product_id:string, variants: array<string,int>, fetched_at:int}
     */
    public function getStock(string $productId): array;

    /** @param list<string> $productIds @return array<string, array<string,int>> product_id => [varian => stok] */
    public function getStockMany(array $productIds): array;

    /** Keranjang per sesi + website asal. @return list<array{product_id:string,variant:string,qty:int}> */
    public function getCart(string $sessionId, string $branchId): array;

    /** Set jumlah (bukan menambah). Tidak mengurangi/memesan stok. */
    public function setCartItem(string $sessionId, string $branchId, string $productId, string $variant, int $qty): void;

    public function removeCartItem(string $sessionId, string $branchId, string $productId, string $variant): void;

    /**
     * Checkout atomik + idempoten.
     * @param array{
     *   request_id:string, branch_id:string, session_hash:string,
     *   customer: array{name:string, phone:string, address:string, notes:string},
     *   shipping_method:string, pickup_location:string,
     *   items: list<array{product_id:string, variant:string, qty:int}>
     * } $request   (TIDAK berisi harga: harga & ongkir dihitung oleh layanan data)
     * @return array{status:'created'|'duplicate', order: array<string,mixed>}
     * @throws DomainRejected | DataServiceUnavailable
     */
    public function checkout(array $request): array;

    /** Cari hasil checkout lama berdasarkan request_id. @return array{status:string, order:array<string,mixed>}|null */
    public function getCheckoutResult(string $requestId, array $access): ?array;

    /** @return array<string,mixed>|null null jika tidak ada ATAU tidak berhak */
    public function getOrder(string $orderId, array $access): ?array;

    /**
     * @param array{status?:string, method?:string, q?:string, limit?:int} $filter
     * @return list<array<string,mixed>> (header pesanan, tanpa item)
     */
    public function listOrders(array $access, array $filter = []): array;

    /**
     * @param array{from:string, to:string, courier?:string, tracking_number?:string, pickup_code?:string} $change
     * @return array{status:'updated'|'unchanged', order:array<string,mixed>}
     */
    public function updateOrderStatus(string $orderId, array $change, array $actor): array;

    /** @return array{status:'cancelled'|'already_cancelled', order:array<string,mixed>} */
    public function cancelOrder(string $orderId, string $reason, array $actor): array;

    /**
     * Rekap pesanan SELESAI berdasarkan waktu selesai (completed_at) dalam rentang [from, to).
     * @return array{branch_id:string, from:int, to:int, orders_completed:int, units_sold:int,
     *               items_subtotal:int, shipping_total:int, products:list<array<string,mixed>>}
     */
    public function getSalesSummary(string $branchId, int $fromTs, int $toTs): array;
}
