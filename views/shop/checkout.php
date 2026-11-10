<?php
/** @var array $cart @var array $old @var array $quotes @var array $branch @var array $errors */
$method = in_array($old['shipping_method'] ?? '', ['reguler', 'pickup'], true) ? $old['shipping_method'] : 'reguler';
$step = 1;
$errors = $errors ?? [];
// Atribut aksesibilitas dan pesan untuk kolom yang salah (pesan tampil di bawah kolom).
$inv = static fn (string $f): string => isset($errors[$f]) ? ' aria-invalid="true" aria-describedby="' . $f . '-err"' : '';
$err = static fn (string $f): string => isset($errors[$f]) ? '<p class="field-error" id="' . $f . '-err">' . e($errors[$f]) . '</p>' : '';
?>
<nav class="breadcrumb" aria-label="Breadcrumb"><a href="/">Beranda</a><span aria-hidden="true">/</span><a href="/keranjang">Keranjang</a><span aria-hidden="true">/</span><span aria-current="page">Checkout</span></nav>
<div class="page-head">
    <h1>Checkout</h1>
    <?php include __DIR__ . '/../partials/checkout-steps.php'; ?>
</div>

<div class="cart-layout">
    <form action="/checkout/tinjau" method="post" class="stack" id="checkout-form">
        <?= csrf_field() ?>
        <section class="card">
            <h2 class="card-title">Data penerima</h2>
            <div class="field-row">
                <div class="field">
                    <label for="name">Nama penerima</label>
                    <input id="name" name="name" type="text" required minlength="3" maxlength="80" autocomplete="name" value="<?= e($old['name'] ?? '') ?>"<?= $inv('name') ?>>
                    <?= $err('name') ?>
                </div>
                <div class="field">
                    <label for="phone">Nomor kontak</label>
                    <input id="phone" name="phone" type="tel" required maxlength="19" autocomplete="tel" inputmode="tel" placeholder="08xxxxxxxxxx" value="<?= e($old['phone'] ?? '') ?>"<?= $inv('phone') ?>>
                    <?= $err('phone') ?>
                </div>
            </div>
        </section>

        <section class="card">
            <h2 class="card-title">Produk dipesan</h2>
            <ul class="mini-list">
                <?php foreach ($cart['lines'] as $l): ?>
                    <li><span><?= e($l['name']) ?> <span class="muted small">Variasi: <?= e(size_text($l['size_label'])) ?>, <?= e($l['color_label']) ?> · <?= rupiah($l['unit_price']) ?> × <?= (int) $l['qty'] ?></span></span><span><?= rupiah($l['line_total']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card">
            <fieldset>
                <legend class="card-title">Cara menerima pesanan</legend>
                <div class="method-grid">
                    <label class="method-option">
                        <input type="radio" name="shipping_method" value="reguler" <?= $method === 'reguler' ? 'checked' : '' ?>>
                        <span><strong>Pengiriman reguler · <?= rupiah($quotes['reguler']['shipping_fee']) ?></strong><span class="muted small">per pesanan, dikirim ke alamatmu</span></span>
                    </label>
                    <label class="method-option">
                        <input type="radio" name="shipping_method" value="pickup" <?= $method === 'pickup' ? 'checked' : '' ?>>
                        <span><strong>Pickup di <?= e($branch['name']) ?> · <?= rupiah($quotes['pickup']['shipping_fee']) ?></strong><span class="muted small">ambil sendiri dengan kode pickup 6 angka</span></span>
                    </label>
                </div>
                <?= $err('shipping_method') ?>
            </fieldset>

            <div class="field" id="address-field">
                <label for="address">Alamat pengiriman</label>
                <textarea id="address" name="address" rows="3" maxlength="300" autocomplete="street-address"<?= $inv('address') ?>><?= e($old['address'] ?? '') ?></textarea>
                <?= $err('address') ?>
                <p class="small muted">Wajib untuk pengiriman reguler. Tulis nama jalan, nomor rumah, RT/RW, dan kelurahan.</p>
            </div>

            <div class="pickup-info" id="pickup-info">
                <p><strong>Lokasi pengambilan:</strong> <?= e($branch['pickup_location']) ?></p>
                <p class="small muted">Jam pickup <?= e($branch['pickup_hours']) ?>. Datang setelah status pesanan menjadi Siap Diambil.</p>
            </div>

            <div class="field">
                <label for="notes">Catatan untuk penjual (opsional)</label>
                <input id="notes" name="notes" type="text" maxlength="200" value="<?= e($old['notes'] ?? '') ?>"<?= $inv('notes') ?>>
                <?= $err('notes') ?>
            </div>
        </section>
    </form>

    <aside class="summary-card">
        <h2 class="card-title">Rincian pembayaran</h2>
        <dl class="summary-list">
            <div><dt>Subtotal produk</dt><dd><?= rupiah($cart['subtotal']) ?></dd></div>
            <div><dt>Ongkir</dt>
                <dd><span data-method-show="reguler"><?= rupiah($quotes['reguler']['shipping_fee']) ?></span><span data-method-show="pickup"><?= rupiah($quotes['pickup']['shipping_fee']) ?></span></dd></div>
            <div class="summary-total"><dt>Total pembayaran</dt>
                <dd><span data-method-show="reguler"><?= rupiah($quotes['reguler']['total']) ?></span><span data-method-show="pickup"><?= rupiah($quotes['pickup']['total']) ?></span></dd></div>
        </dl>
        <button class="btn btn-primary btn-block btn-lg" type="submit" form="checkout-form">Lanjut ke konfirmasi pembayaran</button>
        <p class="small muted">Pembayaran simulasi, tidak ada uang yang ditarik.</p>
    </aside>
</div>
