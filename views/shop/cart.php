<?php /** @var array $cart */
$branchName = (string) config('branch.name');
?>
<nav class="breadcrumb" aria-label="Breadcrumb"><a href="/">Beranda</a><span aria-hidden="true">/</span><span aria-current="page">Keranjang</span></nav>
<div class="page-head">
    <h1>Keranjang</h1>
</div>

<?php if (!$cart['lines']): ?>
    <div class="empty-state">
        <h2>Keranjangmu masih kosong</h2>
        <p class="muted">Pilih produk, ukuran, dan warna di katalog, lalu masukkan ke sini.</p>
        <a class="btn btn-primary" href="/katalog">Buka katalog</a>
    </div>
<?php else: ?>
<div class="cart-head" aria-hidden="true"><span>Produk</span><span>Harga satuan</span><span>Jumlah</span><span>Total</span><span>Aksi</span></div>
<section class="cart-group" aria-label="Barang dari Alverse <?= e($branchName) ?>">
    <div class="cart-group-h"><img src="<?= asset('assets/img/logo-ubin.svg') ?>" alt="" width="24" height="24">Alverse <?= e($branchName) ?></div>
    <ul class="cart-list">
        <?php foreach ($cart['lines'] as $l): ?>
            <?php $qid = 'qty-' . $l['product_id'] . $l['variant']; ?>
            <li class="cart-item<?= $l['issue'] ? ' has-issue' : '' ?>">
                <div class="prod">
                    <img class="cart-thumb" src="<?= e(product_image($l, variant_color($l['variant']))) ?>" alt="" width="80" height="80">
                    <div>
                        <a class="cart-name" href="/produk/<?= e($l['product_id']) ?>"><?= e($l['name']) ?></a>
                        <p class="small muted">Variasi: <?= e(size_text($l['size_label'])) ?>, <?= e($l['color_label']) ?> · Stok bersama: <?= $l['available'] === null ? '-' : (int) $l['available'] ?></p>
                        <?php if ($l['issue']): ?><p class="issue" role="alert"><?= e($l['issue']) ?></p><?php endif; ?>
                    </div>
                </div>
                <span class="unit"><span class="sr-only">Harga satuan </span><?= rupiah($l['unit_price']) ?></span>
                <form action="/keranjang/ubah" method="post" class="qty-form js-qty-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= e($l['product_id']) ?>">
                    <input type="hidden" name="variant" value="<?= e($l['variant']) ?>">
                    <label class="sr-only" for="<?= e($qid) ?>">Jumlah <?= e($l['name']) ?></label>
                    <div class="stepper">
                        <button type="button" class="step-btn" data-step="-1" aria-controls="<?= e($qid) ?>" aria-label="Kurangi jumlah <?= e($l['name']) ?>">−</button>
                        <input id="<?= e($qid) ?>" class="qty-input" type="number" name="qty" value="<?= (int) $l['qty'] ?>" min="1" max="99" step="1" inputmode="numeric">
                        <button type="button" class="step-btn" data-step="1" aria-controls="<?= e($qid) ?>" aria-label="Tambah jumlah <?= e($l['name']) ?>">+</button>
                    </div>
                    <button class="btn btn-ghost btn-sm qty-save" type="submit">Simpan</button>
                </form>
                <span class="tot"><span class="sr-only">Total </span><?= rupiah($l['line_total']) ?></span>
                <form action="/keranjang/hapus" method="post" class="del-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= e($l['product_id']) ?>">
                    <input type="hidden" name="variant" value="<?= e($l['variant']) ?>">
                    <button class="btn-link" type="submit">Hapus<span class="sr-only"> <?= e($l['name']) ?></span></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<div class="sumbar">
    <p class="sum-total">Total (<?= (int) $cart['units'] ?> barang):<strong><?= rupiah($cart['subtotal']) ?></strong></p>
    <?php if ($cart['has_problem']): ?>
        <button class="btn btn-primary btn-lg" type="button" disabled>Checkout</button>
        <p class="warn">Perbaiki item yang ditandai sebelum checkout.</p>
    <?php else: ?>
        <a class="btn btn-primary btn-lg" href="/checkout">Checkout</a>
    <?php endif; ?>
</div>
<?php endif; ?>
