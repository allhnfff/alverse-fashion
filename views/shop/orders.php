<?php /** @var array $orders */ ?>
<nav class="breadcrumb" aria-label="Breadcrumb"><a href="/">Beranda</a><span aria-hidden="true">/</span><span aria-current="page">Pesanan</span></nav>
<div class="page-head">
    <h1>Pesanan saya</h1>
    <p class="muted">Pesanan yang dibuat dari browser ini.</p>
</div>

<?php if (!$orders): ?>
    <div class="empty-state">
        <h2>Belum ada pesanan</h2>
        <p class="muted">Pesanan muncul di sini setelah pembayaran berhasil.</p>
        <a class="btn btn-primary" href="/katalog">Buka katalog</a>
    </div>
<?php else: ?>
    <ul class="order-list">
        <?php foreach ($orders as $o): ?>
            <li>
                <a class="order-row card" href="/pesanan/<?= e($o['order_id']) ?>">
                    <div>
                        <strong class="oid"><?= e($o['order_id']) ?></strong>
                        <p class="small muted"><?= e(wib($o['created_at'])) ?> · <?= e(\App\Domain\OrderRules::methodLabel($o['shipping_method'])) ?> · <?= (int) $o['total_units'] ?> barang</p>
                    </div>
                    <div class="order-row-end">
                        <?php $status = $o['status']; include __DIR__ . '/../partials/status-badge.php'; ?>
                        <strong class="price"><?= rupiah($o['total']) ?></strong>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
