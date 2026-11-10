<?php
/**
 * PEMETAAN ENDPOINT LAYANAN PUSAT KELOMPOK 6
 *
 * SENGAJA DIBIARKAN null. Endpoint belum diberikan Kelompok 6, jadi tidak boleh dikarang.
 * Selama masih null, CentralHttpAdapter menolak dengan pesan jelas
 * "endpoint ... belum dikonfigurasi" (tidak pernah pura-pura berhasil).
 *
 * Setelah Kelompok 6 memberi kontrak, isi dengan format:
 *     'namaOperasi' => ['METHOD', '/path/dengan/{placeholder}'],
 * Placeholder yang tersedia: {product_id} {order_id} {request_id} {session_id} {branch_id}
 *
 * Contoh BENTUK saja (bukan endpoint asli):
 *     'getStock' => ['GET', '/v2/stock/{product_id}'],
 *
 * Lihat docs/INTEGRASI-PUSAT.md untuk daftar pertanyaan yang harus diajukan ke Kelompok 6.
 */

return [
    'health'            => null,
    'listProducts'      => null,
    'getProduct'        => null,
    'getStock'          => null,
    'getStockMany'      => null,
    'getCart'           => null,
    'setCartItem'       => null,
    'removeCartItem'    => null,
    'checkout'          => null,
    'getCheckoutResult' => null,
    'getOrder'          => null,
    'listOrders'        => null,
    'updateOrderStatus' => null,
    'cancelOrder'       => null,
    'getSalesSummary'   => null,
];
