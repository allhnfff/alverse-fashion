<?php /** @var array $summary @var string $from @var string $to */ ?>
<div class="page-head">
    <h1>Rekap penjualan <?= e((string) config('branch.name')) ?></h1>
    <p class="muted">Hanya pesanan <strong>Selesai</strong>, dihitung dari waktu selesai (WIB). Pesanan batal dan yang belum selesai tidak dihitung. Harga memakai snapshot saat pembelian.</p>
</div>

<form class="filter-bar wrap" method="get" action="/admin/rekap">
    <div class="field compact">
        <label for="dari" class="label">Dari</label>
        <input id="dari" type="date" name="dari" value="<?= e($from) ?>">
    </div>
    <div class="field compact">
        <label for="sampai" class="label">Sampai</label>
        <input id="sampai" type="date" name="sampai" value="<?= e($to) ?>">
    </div>
    <div class="filter-actions"><button class="btn btn-primary" type="submit">Tampilkan</button></div>
</form>

<div class="stat-grid">
    <div class="stat card"><span class="label muted">Pesanan selesai</span><strong class="stat-num"><?= (int) $summary['orders_completed'] ?></strong></div>
    <div class="stat card"><span class="label muted">Unit terjual</span><strong class="stat-num"><?= (int) $summary['units_sold'] ?></strong></div>
    <div class="stat card"><span class="label muted">Subtotal barang</span><strong class="stat-num"><?= rupiah($summary['items_subtotal']) ?></strong></div>
    <div class="stat card"><span class="label muted">Ongkir (terpisah)</span><strong class="stat-num"><?= rupiah($summary['shipping_total']) ?></strong></div>
</div>

<section class="section">
    <h2>Per produk</h2>
    <?php if (!$summary['products']): ?>
        <p class="muted">Belum ada pesanan selesai pada periode ini.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Produk</th><th class="num">Unit</th><th class="num">Subtotal barang</th></tr></thead>
                <tbody>
                <?php foreach ($summary['products'] as $p): ?>
                    <tr><td><?= e($p['product_id']) ?> · <?= e($p['name']) ?></td><td class="num"><?= (int) $p['units'] ?></td><td class="num"><?= rupiah($p['subtotal']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <p class="small muted">Periode: <?= e($from) ?> 00:00 s.d. <?= e($to) ?> 23:59 WIB · reguler <?= (int) ($summary['by_method']['reguler'] ?? 0) ?> pesanan, pickup <?= (int) ($summary['by_method']['pickup'] ?? 0) ?> pesanan.</p>
</section>
