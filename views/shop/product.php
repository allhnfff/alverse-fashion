<?php
/** @var array $product @var array $related @var array|null $stock @var string|null $stockError @var string $selSize @var string $selColor @var int $pollMs */
$selCode = $selSize . '_' . $selColor;
$selStock = $stock['variants'][$selCode] ?? null;
$initial = [
    'productId' => $product['product_id'],
    'pollMs'    => $pollMs,
    'variants'  => $stock ? (object) $stock['variants'] : null,
    'fetchedAt' => $stock ? date('H:i:s', $stock['fetched_at']) : null,
    'error'     => $stockError,
];
$branchName = (string) config('branch.name');
?>
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="/">Beranda</a><span aria-hidden="true">/</span>
    <a href="/katalog?kategori=<?= e($product['category']) ?>"><?= e($product['category_label']) ?></a><span aria-hidden="true">/</span>
    <span aria-current="page"><?= e($product['name']) ?></span>
</nav>

<div class="pd" id="product-page">
    <?php
    $mainImg = product_image($product, $selColor);
    $isAi = image_note($mainImg) === 'gambar AI';
    // Satu slide per gambar warna (gambar yang sama untuk dua warna cukup satu slide).
    $slides = [];
    foreach ($product['colors'] as $c) {
        $src = product_image($product, $c['code']);
        $slides[$src] ??= ['src' => $src, 'colors' => [], 'labels' => []];
        $slides[$src]['colors'][] = $c['code'];
        $slides[$src]['labels'][] = $c['label'];
    }
    $slides = array_values($slides);
    $multi = count($slides) > 1;
    ?>
    <figure class="pd-gallery" id="pd-gallery">
        <div class="pd-stage">
        <div class="product-media large pd-track" id="pd-track"<?= $multi ? ' tabindex="0" role="region" aria-label="Gambar produk, geser untuk warna lain"' : '' ?>>
            <?php foreach ($slides as $i => $sl): ?>
                <div class="pd-slide" id="foto-<?= $i + 1 ?>" data-colors="<?= e(implode(' ', $sl['colors'])) ?>">
                    <img src="<?= e($sl['src']) ?>" alt="<?= e($product['name']) ?> warna <?= e(implode(', ', $sl['labels'])) ?> (<?= image_note($sl['src']) ?>)" width="800" height="800"<?= in_array($selColor, $sl['colors'], true) ? '' : ' loading="lazy"' ?>>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($multi): ?><span class="pd-count" id="pd-count" aria-hidden="true">1/<?= count($slides) ?></span><?php endif; ?>
        </div>
        <?php if ($multi): ?>
            <div class="pd-thumbs" role="group" aria-label="Gambar per warna">
                <?php foreach ($slides as $i => $sl): ?>
                    <a class="pd-thumb" href="#foto-<?= $i + 1 ?>" data-index="<?= $i ?>" aria-label="Lihat gambar warna <?= e(implode(', ', $sl['labels'])) ?>"><img src="<?= e($sl['src']) ?>" alt="" width="64" height="64" loading="lazy"></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <figcaption><?= $isAi ? 'Gambar dibuat dengan AI, bukan foto produk asli.' : 'Gambar ilustrasi kategori, belum foto produk asli.' ?></figcaption>
    </figure>

    <div class="pd-info">
        <h1><?= e($product['name']) ?></h1>
        <p class="pd-meta"><?= e($product['category_label']) ?></p>
        <p class="pd-price"><?= rupiah($product['price']) ?></p>

        <dl class="pd-rows">
            <div><dt>Pengiriman</dt><dd>Reguler <?= rupiah((int) config('shipping.reguler')) ?> · Pickup di cabang <?= rupiah((int) config('shipping.pickup')) ?></dd></div>
        </dl>

        <?php
        $sizeLabels = array_map('size_text', array_column($product['sizes'], 'label', 'code'));
        $colorLabels = array_column($product['colors'], 'label', 'code');
        ?>
        <div class="pd-pick" id="pd-pick" hidden>
            <button type="button" class="pick-row" data-open-sheet aria-controls="vsheet"><span>Variasi</span><strong id="pick-variant"><?= e(($sizeLabels[$selSize] ?? '-') . ', ' . ($colorLabels[$selColor] ?? '-')) ?></strong><?= icon('next', 18) ?></button>
            <button type="button" class="pick-row" data-open-sheet aria-controls="vsheet"><span>Stok bersama</span><strong id="pick-stock"><?= $selStock === null ? '-' : (int) $selStock ?></strong><?= icon('next', 18) ?></button>
        </div>

        <form action="/keranjang/tambah" method="post" class="buy-form" id="buy-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= e($product['product_id']) ?>">

            <div class="vsheet" id="vsheet" aria-labelledby="vsheet-title">
            <div class="vsheet-h">
                <img id="vsheet-img" src="<?= e($mainImg) ?>" alt="" width="64" height="64">
                <div><strong><?= rupiah($product['price']) ?></strong><span id="vsheet-title">Pilih variasi <?= e($product['name']) ?></span></div>
                <button type="button" class="qclose" id="vsheet-close" aria-label="Tutup pilihan variasi"><?= icon('close', 22) ?></button>
            </div>

            <fieldset class="option-group">
                <legend>Ukuran <span class="muted">(<?= e(size_text($product['size_label'])) ?>)</span></legend>
                <div class="option-list">
                    <?php foreach ($product['sizes'] as $s): ?>
                        <label class="option">
                            <input type="radio" name="size" value="<?= e($s['code']) ?>" <?= $s['code'] === $selSize ? 'checked' : '' ?> required>
                            <span><?= e(size_text($s['label'])) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="option-group">
                <legend>Warna</legend>
                <div class="option-list">
                    <?php foreach ($product['colors'] as $c): ?>
                        <label class="option">
                            <input type="radio" name="color" value="<?= e($c['code']) ?>" data-img="<?= e(product_image($product, $c['code'])) ?>" <?= $c['code'] === $selColor ? 'checked' : '' ?> required>
                            <span><?= e($c['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="qty-row">
                <label class="legend" for="qty">Jumlah</label>
                <div class="stepper">
                    <button type="button" class="step-btn" data-step="-1" aria-controls="qty" aria-label="Kurangi jumlah">−</button>
                    <input id="qty" class="qty-input" type="number" name="qty" value="1" min="1" max="99" step="1" inputmode="numeric" required>
                    <button type="button" class="step-btn" data-step="1" aria-controls="qty" aria-label="Tambah jumlah">+</button>
                </div>
            </div>

            <div class="stock-box" id="stock-box" aria-live="polite">
                <div class="stock-row">
                    <span class="label">Stok bersama Alverse</span>
                    <strong class="stock-value" id="stock-value">
                        <?php if ($stockError): ?>
                            Tidak dapat memperbarui stok
                        <?php elseif ($selStock === null): ?>
                            Kombinasi tidak tersedia
                        <?php elseif ($selStock === 0): ?>
                            Habis
                        <?php else: ?>
                            <?= (int) $selStock ?>
                        <?php endif; ?>
                    </strong>
                </div>
                <p class="small muted" id="stock-meta">
                    <?= $stock ? 'Diperbarui ' . e(date('H:i:s', $stock['fetched_at'])) . ' WIB' : '' ?>
                </p>
            </div>

            <div class="buy-actions">
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="add-btn" <?= ($selStock === null || $selStock === 0) && !$stockError ? 'disabled' : '' ?>>
                    <?= icon('cart', 20) ?> Masukkan keranjang
                </button>
                <a class="btn btn-ghost btn-lg" href="/keranjang">Lihat keranjang</a>
            </div>
            </div>
        </form>
    </div>
</div>

<div class="pd-bar" id="pd-bar" hidden>
    <a class="btn btn-ghost" href="/keranjang">Lihat keranjang</a>
    <button type="button" class="btn btn-primary" data-open-sheet aria-controls="vsheet">Masukkan keranjang</button>
</div>
<div class="dim vsheet-dim" id="vsheet-dim" hidden></div>

<div class="shop-strip">
    <img src="<?= asset('assets/img/logo-ubin.svg') ?>" alt="" width="40" height="40">
    <div><strong>Alverse <?= e($branchName) ?></strong><span>Pickup <?= e((string) config('branch.pickup_hours')) ?></span></div>
    <a class="btn btn-ghost" href="/cabang">Info cabang</a>
</div>

<?php
// Deskripsi dari data master masih teks pengganti ("[Deskripsi demo] ...") yang hanya mengulang spesifikasi.
// Bagian deskripsi baru tampil setelah pusat mengirim deskripsi asli.
$desc = trim((string) ($product['description'] ?? ''));
$hasDesc = $desc !== '' && !str_starts_with($desc, '[Deskripsi demo]');
$guide = size_guide($product);
?>
<section class="panel spec" aria-labelledby="spec-h">
    <h2 id="spec-h" class="panel-title">Spesifikasi produk</h2>
    <dl class="spec-list">
        <div><dt>Kategori</dt><dd><a href="/katalog?kategori=<?= e($product['category']) ?>"><?= e($product['category_label']) ?></a></dd></div>
        <div><dt>Merek</dt><dd>Alverse</dd></div>
        <div><dt>Ukuran</dt><dd><?= e(implode(', ', array_map('size_text', array_column($product['sizes'], 'label')))) ?><?php if ($guide): ?> · <a href="#panduan-ukuran">Panduan ukuran</a><?php endif; ?></dd></div>
        <div><dt>Warna</dt><dd><?= e(implode(', ', array_column($product['colors'], 'label'))) ?></dd></div>
        <div><dt>Kode produk</dt><dd><?= e($product['product_id']) ?></dd></div>
        <div><dt>Dikirim dari</dt><dd>Alverse <?= e($branchName) ?></dd></div>
    </dl>
    <?php if ($guide): ?>
        <h2 class="panel-title" id="panduan-ukuran">Panduan ukuran</h2>
        <div class="size-guide">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th scope="col">Ukuran</th><?php foreach ($guide['cols'] as $col): ?><th scope="col" class="num"><?= e($col) ?></th><?php endforeach; ?></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guide['rows'] as $row): ?>
                            <tr><th scope="row"><?= e($row['label']) ?></th><?php foreach ($row['values'] as $v): ?><td class="num"><?= e(cm($v)) ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="small muted"><?= e($guide['note']) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($hasDesc): ?>
        <h2 class="panel-title">Deskripsi produk</h2>
        <p class="desc"><?= e($desc) ?></p>
    <?php endif; ?>
</section>

<?php if ($related): ?>
<section aria-labelledby="rel-h">
    <h2 class="rek" id="rel-h"><?= e($product['category_label']) ?> lainnya</h2>
    <div class="product-grid grid-wide">
        <?php foreach ($related as $p): ?>
            <?php include __DIR__ . '/../partials/product-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<script type="application/json" id="stock-data"><?= json_encode($initial, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
