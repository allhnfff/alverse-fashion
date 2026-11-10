<?php
/** @var array $order @var bool $isNew @var array|false|null $freshStock @var array $cancelReasons */
use App\Domain\OrderRules;

$isPickup = $order['shipping_method'] === 'pickup';
?>
<nav class="breadcrumb" aria-label="Breadcrumb"><a href="/">Beranda</a><span aria-hidden="true">/</span><a href="/pesanan">Pesanan</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($order['order_id']) ?></span></nav>

<?php if ($isNew): ?>
    <div class="alert alert-success" role="status">
        Pembayaran simulasi berhasil. Pesanan <strong><?= e($order['order_id']) ?></strong> sudah diterima cabang.
    </div>
<?php endif; ?>

<div class="page-head order-head">
    <div>
        <h1>Pesanan <?= e($order['order_id']) ?></h1>
        <p class="muted">Dibuat <?= e(wib($order['created_at'])) ?> di website <?= e($order['branch_name'] ?? $order['branch_id']) ?></p>
    </div>
    <?php $status = $order['status']; include __DIR__ . '/../partials/status-badge.php'; ?>
</div>

<section class="card tl-card" aria-label="Status pesanan">
    <?php $forCustomer = true; include __DIR__ . '/../partials/order-timeline.php'; ?>
</section>

<div class="cart-layout">
    <div class="stack">
        <section class="card">
            <h2 class="card-title">Penerima dan cara menerima</h2>
            <dl class="detail-list">
                <div><dt>Penerima</dt><dd><?= e($order['customer_name']) ?> · <?= e($order['customer_phone']) ?></dd></div>
                <div><dt>Metode</dt><dd><?= e(OrderRules::methodLabel($order['shipping_method'])) ?></dd></div>
                <?php if ($isPickup): ?>
                    <div><dt>Lokasi pickup</dt><dd><?= e($order['pickup_location']) ?></dd></div>
                <?php else: ?>
                    <div><dt>Alamat</dt><dd><?= e($order['shipping_address']) ?></dd></div>
                <?php endif; ?>
                <?php if (($order['courier'] ?? '') !== ''): ?>
                    <div><dt>Kurir</dt><dd><?= e($order['courier']) ?></dd></div>
                    <div><dt>Resi</dt><dd><?= e($order['tracking_number']) ?></dd></div>
                <?php endif; ?>
                <div><dt>Pembayaran</dt><dd>Berhasil</dd></div>
            </dl>

            <?php if ($isPickup && $order['status'] !== 'dibatalkan'): ?>
                <div class="pickup-code">
                    <span class="label">Kode pickup</span>
                    <strong class="code"><?= e($order['pickup_code'] ?? '') ?></strong>
                    <span class="small">Tunjukkan kode ini ke petugas saat mengambil barang, setelah status Siap Diambil.</span>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2 class="card-title">Riwayat</h2>
            <ul class="history">
                <?php foreach (array_reverse($order['history'] ?? []) as $h): ?>
                    <?php $hl = OrderRules::label((string) ($h['status'] ?? '')); $hn = customer_history_note($hl, (string) ($h['note'] ?? '')); ?>
                    <li><span class="h-at"><?= e(wib($h['at'] ?? 0, 'd M Y, H:i:s')) ?></span><span><?= e($hl) ?><?= $hn !== '' ? ': ' . e($hn) : '' ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <aside class="summary-card">
        <div class="cart-group-h flat"><img src="<?= asset('assets/img/logo-ubin.svg') ?>" alt="" width="24" height="24">Alverse <?= e($order['branch_name'] ?? (string) config('branch.name')) ?></div>
        <ul class="mini-list">
            <?php foreach ($order['items'] as $it): ?>
                <li>
                    <span><?= e($it['name']) ?> <span class="muted small">Variasi: <?= e(size_text($it['size_label'])) ?>, <?= e($it['color_label']) ?> · <span class="nowrap"><?= (int) $it['qty'] ?> × <?= rupiah($it['unit_price']) ?></span></span>
                        <?php if (is_array($freshStock)): ?>
                            <span class="small muted block">Stok bersama terbaru: <?= (int) ($freshStock[$it['product_id']][$it['variant']] ?? 0) ?></span>
                        <?php endif; ?>
                    </span>
                    <span><?= rupiah($it['line_total']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($freshStock === false): ?><p class="small text-danger">Tidak dapat memperbarui stok terbaru saat ini.</p><?php endif; ?>
        <dl class="summary-list">
            <div><dt>Subtotal produk</dt><dd><?= rupiah($order['items_subtotal']) ?></dd></div>
            <div><dt>Ongkir</dt><dd><?= rupiah($order['shipping_fee']) ?></dd></div>
            <div class="summary-total"><dt>Total pembayaran</dt><dd><?= rupiah($order['total']) ?></dd></div>
        </dl>
        <?php if (OrderRules::canCancel($order['status'])): ?>
            <details class="cancel-box" id="batal">
                <summary class="btn btn-danger btn-block">Batalkan pesanan</summary>
                <form method="post" action="/pesanan/<?= e($order['order_id']) ?>/batal" class="cancel-form js-once">
                    <?= csrf_field() ?>
                    <p class="small">Pesanan yang dibatalkan tidak bisa diaktifkan lagi.</p>
                    <label class="lbl2" for="cancel-reason">Alasan pembatalan</label>
                    <select id="cancel-reason" name="reason" required>
                        <option value="">Pilih alasan</option>
                        <?php foreach ($cancelReasons as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-danger btn-block">Ya, batalkan pesanan</button>
                </form>
            </details>
        <?php endif; ?>
        <a class="btn btn-ghost btn-block" href="/cabang">Info cabang dan pickup</a>
        <a class="btn btn-ghost btn-block" href="/pesanan">Semua pesanan</a>
        <a class="btn btn-primary btn-block" href="/katalog">Belanja lagi</a>
    </aside>
</div>
