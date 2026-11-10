<?php
/** @var array $order @var string|null $next */
use App\Domain\OrderRules;
$canCancel = OrderRules::canCancel($order['status']);
?>
<nav class="breadcrumb"><a href="/admin/pesanan">Pesanan</a> <span aria-hidden="true">/</span> <span><?= e($order['order_id']) ?></span></nav>

<div class="page-head">
    <h1><?= e($order['order_id']) ?> <?php $status = $order['status']; include __DIR__ . '/../partials/status-badge.php'; ?></h1>
    <p class="muted">Dibuat <?= e(wib($order['created_at'])) ?> · <?= e(OrderRules::methodLabel($order['shipping_method'])) ?> · asal website <code><?= e($order['branch_id']) ?></code></p>
</div>

<div class="cart-layout">
    <section class="card">
        <?php include __DIR__ . '/../partials/order-timeline.php'; ?>

        <h2 class="h3">Item</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Produk</th><th>Varian</th><th class="num">Qty</th><th class="num">Harga (snapshot)</th><th class="num">Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($order['items'] as $it): ?>
                    <tr>
                        <td><?= e($it['product_id']) ?> · <?= e($it['name']) ?></td>
                        <td><?= e($it['size_label']) ?>, <?= e($it['color_label']) ?> <span class="muted small">(<?= e($it['variant']) ?>)</span></td>
                        <td class="num"><?= (int) $it['qty'] ?></td>
                        <td class="num nowrap"><?= rupiah($it['unit_price']) ?></td>
                        <td class="num nowrap"><?= rupiah($it['line_total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 class="h3">Penerima</h2>
        <dl class="detail-list">
            <div><dt>Nama</dt><dd><?= e($order['customer_name']) ?></dd></div>
            <div><dt>Kontak</dt><dd><?= e($order['customer_phone']) ?></dd></div>
            <?php if ($order['shipping_method'] === 'reguler'): ?>
                <div><dt>Alamat</dt><dd><?= e($order['shipping_address']) ?></dd></div>
            <?php else: ?>
                <div><dt>Lokasi pickup</dt><dd><?= e($order['pickup_location']) ?></dd></div>
            <?php endif; ?>
            <?php if (($order['notes'] ?? '') !== ''): ?><div><dt>Catatan</dt><dd><?= e($order['notes']) ?></dd></div><?php endif; ?>
            <?php if (($order['courier'] ?? '') !== ''): ?><div><dt>Kurir / resi</dt><dd><?= e($order['courier']) ?> · <?= e($order['tracking_number']) ?></dd></div><?php endif; ?>
            <?php if (!empty($order['completed_at'])): ?><div><dt>Selesai</dt><dd><?= e(wib($order['completed_at'])) ?></dd></div><?php endif; ?>
            <?php if ($order['status'] === 'dibatalkan'): ?>
                <div><dt>Dibatalkan</dt><dd><?= e(wib($order['cancelled_at'])) ?> oleh <?= e($order['cancelled_by'] ?? '-') ?>: <?= e($order['cancel_reason'] ?? '') ?></dd></div>
                <div><dt>Stok dikembalikan</dt><dd><?= $order['restocked'] ? 'Ya, satu kali' : 'Belum' ?></dd></div>
            <?php endif; ?>
        </dl>

        <h2 class="h3">Riwayat status</h2>
        <ul class="history">
            <?php foreach (array_reverse($order['history'] ?? []) as $h): ?>
                <li><span class="h-at"><?= e(wib($h['at'] ?? 0, 'd M Y, H:i:s')) ?></span><?php $hl = OrderRules::label((string) ($h['status'] ?? '')); ?><span><?= e($hl) ?> (<?= e((string) ($h['by'] ?? '')) ?>)<?= !empty($h['note']) ? ': ' . e(history_note($hl, (string) $h['note'])) : '' ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <aside class="summary-card">
        <h2 class="h3">Ringkasan</h2>
        <dl class="summary-list">
            <div><dt>Subtotal barang</dt><dd><?= rupiah($order['items_subtotal']) ?></dd></div>
            <div><dt>Ongkir</dt><dd><?= rupiah($order['shipping_fee']) ?></dd></div>
            <div class="summary-total"><dt>Total</dt><dd><?= rupiah($order['total']) ?></dd></div>
        </dl>

        <h2 class="h3">Tindakan</h2>
        <?php if ($next !== null): ?>
            <form action="/admin/pesanan/<?= e($order['order_id']) ?>/status" method="post" class="action-form">
                <?= csrf_field() ?>
                <input type="hidden" name="from" value="<?= e($order['status']) ?>">
                <input type="hidden" name="to" value="<?= e($next) ?>">
                <?php if ($next === 'dikirim'): ?>
                    <div class="field">
                        <label for="courier">Kurir (simulasi)</label>
                        <input id="courier" name="courier" type="text" required minlength="2" maxlength="40" placeholder="mis. Kurir Demo">
                    </div>
                    <div class="field">
                        <label for="tracking_number">Nomor resi (simulasi)</label>
                        <input id="tracking_number" name="tracking_number" type="text" required minlength="3" maxlength="40" placeholder="mis. DEMO123456">
                    </div>
                <?php elseif ($next === 'selesai' && $order['shipping_method'] === 'pickup'): ?>
                    <div class="field">
                        <label for="pickup_code">Kode pickup dari pelanggan</label>
                        <input id="pickup_code" name="pickup_code" type="text" required inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="6 digit">
                        <p class="small muted">Cocokkan dengan kode yang ditunjukkan pelanggan saat penyerahan.</p>
                    </div>
                <?php elseif ($next === 'siap_diambil'): ?>
                    <p class="small muted">Tandai setelah barang demo dinyatakan siap. Stok bersama tidak membuktikan barang sudah ada di cabang.</p>
                <?php endif; ?>
                <button class="btn btn-primary btn-block" type="submit"><?= e(OrderRules::actionLabel($next)) ?></button>
            </form>
        <?php else: ?>
            <p class="muted small">Tidak ada tindakan lanjutan untuk status ini.</p>
        <?php endif; ?>

        <?php if ($canCancel): ?>
            <form action="/admin/pesanan/<?= e($order['order_id']) ?>/batal" method="post" class="action-form cancel-form">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="reason">Alasan pembatalan (wajib)</label>
                    <textarea id="reason" name="reason" rows="2" required minlength="5" maxlength="200"></textarea>
                </div>
                <button class="btn btn-danger btn-block" type="submit">Batalkan pesanan</button>
                <p class="small muted">Hanya saat Dikonfirmasi. Stok global dikembalikan tepat satu kali.</p>
            </form>
        <?php endif; ?>
    </aside>
</div>
