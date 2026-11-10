<?php
/** @var array $cart @var array $draft @var array $quote @var array $branch @var bool $pending */
$form = $draft['form'];
$step = 2;
?>
<nav class="breadcrumb" aria-label="Breadcrumb"><a href="/">Beranda</a><span aria-hidden="true">/</span><a href="/keranjang">Keranjang</a><span aria-hidden="true">/</span><span aria-current="page">Checkout</span></nav>
<div class="page-head">
    <h1>Konfirmasi pembayaran</h1>
    <?php include __DIR__ . '/../partials/checkout-steps.php'; ?>
</div>

<div class="cart-layout">
    <div class="stack">
        <section class="card">
            <div class="card-head"><h2 class="card-title">Penerima</h2><a class="link small" href="/checkout">Ubah data</a></div>
            <dl class="detail-list">
                <div><dt>Nama</dt><dd><?= e($form['customer']['name']) ?></dd></div>
                <div><dt>Kontak</dt><dd><?= e($form['customer']['phone']) ?></dd></div>
                <div><dt>Metode</dt><dd><?= e(\App\Domain\OrderRules::methodLabel($form['method'])) ?></dd></div>
                <?php if ($form['method'] === 'reguler'): ?>
                    <div><dt>Alamat</dt><dd><?= e($form['customer']['address']) ?></dd></div>
                <?php else: ?>
                    <div><dt>Lokasi pickup</dt><dd><?= e($branch['pickup_location']) ?> <span class="muted small">(<?= e($branch['pickup_hours']) ?>)</span></dd></div>
                <?php endif; ?>
                <?php if ($form['customer']['notes'] !== ''): ?>
                    <div><dt>Catatan</dt><dd><?= e($form['customer']['notes']) ?></dd></div>
                <?php endif; ?>
            </dl>
        </section>
        <section class="card">
            <h2 class="card-title">Produk dipesan</h2>
            <ul class="mini-list">
                <?php foreach ($cart['lines'] as $l): ?>
                    <li><span><?= e($l['name']) ?> <span class="muted small">Variasi: <?= e(size_text($l['size_label'])) ?>, <?= e($l['color_label']) ?> · <?= rupiah($l['unit_price']) ?> × <?= (int) $l['qty'] ?></span></span><span><?= rupiah($l['line_total']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <aside class="summary-card">
        <h2 class="card-title">Pembayaran</h2>
        <dl class="summary-list">
            <div><dt>Subtotal produk</dt><dd><?= rupiah($quote['items_subtotal']) ?></dd></div>
            <div><dt>Ongkir</dt><dd><?= rupiah($quote['shipping_fee']) ?></dd></div>
            <div class="summary-total"><dt>Total pembayaran</dt><dd><?= rupiah($quote['total']) ?></dd></div>
        </dl>
        <?php if ($pending): ?>
            <p class="small text-danger">Konfirmasi sebelumnya belum jelas berhasil atau tidak. Menekan tombol di bawah tidak akan membuat pesanan ganda.</p>
        <?php endif; ?>
        <form action="/checkout/konfirmasi" method="post" class="js-once">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= e($draft['request_id']) ?>">
            <button class="btn btn-primary btn-block btn-lg" type="submit">Konfirmasi pembayaran</button>
        </form>
        <form action="/checkout/batal-bayar" method="post">
            <?= csrf_field() ?>
            <button class="btn btn-ghost btn-block" type="submit">Batalkan pembayaran</button>
        </form>
        <p class="small muted">Pembayaran simulasi, tidak ada uang yang ditarik.</p>
    </aside>
</div>
