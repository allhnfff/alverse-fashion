<?php
/** @var array $products @var string $q @var string $category @var string $sort @var array $sorts @var array $categories @var array $categoryCounts @var int $matchingTotal @var bool $empty @var array $filters @var array $sizeOptions @var array $colorOptions */
$f = $filters;
$filterQuery = ['harga_min' => $f['min'], 'harga_max' => $f['max'], 'ukuran' => $f['sizes'], 'warna' => $f['colors']];
$keep = static fn (array $over): string => url('/katalog', $over + ['q' => $q, 'kategori' => $category, 'urut' => $sort] + $filterQuery);
$active = ($f['min'] !== null || $f['max'] !== null ? 1 : 0) + count($f['sizes']) + count($f['colors']);
$without = static fn (string $key, ?string $code = null): string => $keep($code === null ? [$key => null] : [$key => array_values(array_diff($f[$key === 'ukuran' ? 'sizes' : 'colors'], [$code]))]);
?>
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="/">Beranda</a><span aria-hidden="true">/</span>
    <?php if ($category !== ''): ?>
        <a href="<?= e($keep(['kategori' => '', 'ukuran' => [], 'warna' => []])) ?>">Katalog</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($title) ?></span>
    <?php else: ?>
        <span aria-current="page">Katalog</span>
    <?php endif; ?>
</nav>

<div class="catalog">
    <aside class="cat-side" aria-labelledby="side-h">
        <h2 id="side-h">Kategori</h2>
        <ul>
            <li><a href="<?= e($keep(['kategori' => '', 'ukuran' => [], 'warna' => []])) ?>"<?= $category === '' ? ' aria-current="page"' : '' ?>><span>Semua</span><span class="n"><?= (int) $matchingTotal ?></span></a></li>
            <?php foreach ($categories as $key => $label): ?>
                <li><a href="<?= e($keep(['kategori' => $key, 'ukuran' => [], 'warna' => []])) ?>"<?= $category === $key ? ' aria-current="page"' : '' ?>><span><?= e($label) ?></span><span class="n"><?= (int) ($categoryCounts[$key] ?? 0) ?></span></a></li>
            <?php endforeach; ?>
        </ul>

        <details class="cat-filter" id="cat-filter"<?= $active ? ' open' : '' ?>>
            <summary>Filter<?= $active ? ' (' . $active . ')' : '' ?></summary>
            <form action="/katalog" method="get" class="filter-form">
                <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
                <?php if ($category !== ''): ?><input type="hidden" name="kategori" value="<?= e($category) ?>"><?php endif; ?>
                <?php if ($sort !== ''): ?><input type="hidden" name="urut" value="<?= e($sort) ?>"><?php endif; ?>
                <fieldset class="f-group">
                    <legend>Rentang harga (Rp)</legend>
                    <div class="f-price">
                        <label class="sr-only" for="harga-min">Harga minimum (Rp)</label>
                        <input id="harga-min" name="harga_min" type="number" inputmode="numeric" min="0" step="1000" placeholder="Min" value="<?= $f['min'] !== null ? (int) $f['min'] : '' ?>">
                        <span aria-hidden="true">–</span>
                        <label class="sr-only" for="harga-max">Harga maksimum (Rp)</label>
                        <input id="harga-max" name="harga_max" type="number" inputmode="numeric" min="0" step="1000" placeholder="Maks" value="<?= $f['max'] !== null ? (int) $f['max'] : '' ?>">
                    </div>
                </fieldset>
                <?php if ($sizeOptions && $category !== ''): ?>
                    <fieldset class="f-group">
                        <legend>Ukuran</legend>
                        <div class="f-opts">
                            <?php foreach ($sizeOptions as $code => $label): ?>
                                <label class="f-chip"><input type="checkbox" class="js-autosubmit" name="ukuran[]" value="<?= e((string) $code) ?>"<?= in_array((string) $code, $f['sizes'], true) ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>
                <?php if ($colorOptions): ?>
                    <fieldset class="f-group">
                        <legend>Warna</legend>
                        <div class="f-opts">
                            <?php foreach ($colorOptions as $code => $label): ?>
                                <label class="f-chip"><input type="checkbox" class="js-autosubmit" name="warna[]" value="<?= e((string) $code) ?>"<?= in_array((string) $code, $f['colors'], true) ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>
                <button class="btn btn-primary btn-block" type="submit">Terapkan filter</button>
            </form>
        </details>
    </aside>

    <div class="cat-main">
        <form class="toolbar" action="/katalog" method="get" id="catalog-form">
            <h1><?= e($title) ?></h1>
            <?php if ($category !== ''): ?><input type="hidden" name="kategori" value="<?= e($category) ?>"><?php endif; ?>
            <?php if ($f['min'] !== null): ?><input type="hidden" name="harga_min" value="<?= (int) $f['min'] ?>"><?php endif; ?>
            <?php if ($f['max'] !== null): ?><input type="hidden" name="harga_max" value="<?= (int) $f['max'] ?>"><?php endif; ?>
            <?php foreach ($f['sizes'] as $code): ?><input type="hidden" name="ukuran[]" value="<?= e($code) ?>"><?php endforeach; ?>
            <?php foreach ($f['colors'] as $code): ?><input type="hidden" name="warna[]" value="<?= e($code) ?>"><?php endforeach; ?>
            <div class="tb-search">
                <label class="sr-only" for="q-catalog">Cari di katalog</label>
                <input id="q-catalog" type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama, kode, atau warna">
            </div>
            <div class="tb-sort">
                <label for="urut">Urutkan</label>
                <select id="urut" name="urut" class="js-autosubmit">
                    <?php foreach ($sorts as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Terapkan</button>
        </form>

        <?php if ($active): ?>
            <div class="f-active" aria-label="Filter aktif">
                <?php if ($f['min'] !== null || $f['max'] !== null): ?>
                    <?php $priceText = $f['min'] !== null && $f['max'] !== null ? rupiah($f['min']) . ' sampai ' . rupiah($f['max']) : ($f['min'] !== null ? 'Mulai ' . rupiah($f['min']) : 'Sampai ' . rupiah($f['max'])); ?>
                    <a href="<?= e($keep(['harga_min' => null, 'harga_max' => null])) ?>" aria-label="Hapus filter harga <?= e($priceText) ?>"><?= e($priceText) ?> <?= icon('close', 14) ?></a>
                <?php endif; ?>
                <?php foreach ($f['sizes'] as $code): ?>
                    <a href="<?= e($without('ukuran', $code)) ?>" aria-label="Hapus filter ukuran <?= e($sizeOptions[$code] ?? $code) ?>">Ukuran <?= e($sizeOptions[$code] ?? $code) ?> <?= icon('close', 14) ?></a>
                <?php endforeach; ?>
                <?php foreach ($f['colors'] as $code): ?>
                    <a href="<?= e($without('warna', $code)) ?>" aria-label="Hapus filter warna <?= e($colorOptions[$code] ?? $code) ?>"><?= e($colorOptions[$code] ?? $code) ?> <?= icon('close', 14) ?></a>
                <?php endforeach; ?>
                <a class="clear" href="<?= e($keep(['harga_min' => null, 'harga_max' => null, 'ukuran' => [], 'warna' => []])) ?>">Hapus semua filter</a>
            </div>
        <?php endif; ?>

        <p class="count" role="status"><?= count($products) ?> produk<?= $q !== '' ? ' untuk “' . e($q) . '”' : '' ?><?= $category !== '' ? ' di ' . e($title) : '' ?><?= $active ? ' sesuai filter' : '' ?></p>

        <?php if ($empty): ?>
            <div class="empty-state">
                <h2>Katalog belum tersedia</h2>
                <p class="muted">Produk belum tersedia. Coba lagi nanti.</p>
            </div>
        <?php elseif (!$products): ?>
            <div class="empty-state">
                <h2>Produk tidak ditemukan</h2>
                <p class="muted">Tidak ada produk yang cocok<?= $q !== '' ? ' dengan “' . e($q) . '”' : '' ?><?= $category !== '' ? ' di ' . e($title) : '' ?>.</p>
                <a class="btn btn-ghost" href="/katalog">Hapus pencarian dan filter</a>
            </div>
        <?php else: ?>
            <?php $visible = \App\Controllers\ShopController::CATALOG_VISIBLE; $rest = max(0, count($products) - $visible); ?>
            <div class="product-grid catalog-grid" id="catalog-grid">
                <?php foreach ($products as $i => $p): ?>
                    <?php $cardExtra = $i >= $visible; include __DIR__ . '/../partials/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
            <?php if ($rest > 0): ?>
                <div class="more"><button type="button" class="btn btn-ghost" id="show-more" aria-controls="catalog-grid" hidden>Tampilkan <?= $rest ?> produk lainnya</button></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
