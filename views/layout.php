<?php
/** @var string $content @var array|null $miniCart @var array|null $megaMenu */
$branchName = (string) config('branch.name');
$branchId = (string) config('branch.id');
$isTestInstance = !empty(config('branches')[$branchId]['test_only']);
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isActive = static fn (string ...$prefixes): bool => (bool) array_filter($prefixes, static fn ($p) => $p === '/' ? $path === '/' : str_starts_with($path, $p));
$current = static fn (string ...$prefixes): string => $isActive(...$prefixes) ? ' aria-current="page"' : '';
$cartCount = $cartCount ?? null;
$cartText = $cartCount === null ? '–' : (string) (int) $cartCount;
$cartLabel = $cartCount === null ? 'Keranjang' : 'Keranjang, ' . (int) $cartCount . ' barang';
$miniCart = $miniCart ?? null;
$megaMenu = $megaMenu ?? null;
$cartJustAdded = !empty($cartJustAdded) && $miniCart && $miniCart['lines'];
$searchTips = ['Oversized Tee', 'Hoodie', 'Gamis Modern', 'Canvas Sneakers', 'Sling Bag', 'Celana Kulot'];
$otherBranches = [];
foreach ((array) config('branches') as $id => $b) {
    if (empty($b['test_only']) && $id !== $branchId) {
        $otherBranches[] = ['name' => $b['name'], 'url' => (string) (config('branch_urls')[$id] ?? '')];
    }
}
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Alverse') . ' · Alverse ' . $branchName) ?></title>
    <meta name="description" content="Alverse <?= e($branchName) ?>: fashion pakaian, busana muslim, sepatu, tas, dan aksesori.">
    <meta name="theme-color" content="#1F3A2E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <link rel="icon" href="<?= asset('assets/img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body class="shop<?= isset($bodyClass) && preg_match('/^[a-z-]+$/', (string) $bodyClass) ? ' ' . $bodyClass : '' ?>">
<a class="skip-link" href="#main">Lewati ke konten</a>

<?php if ($isTestInstance): ?>
    <div class="test-banner">Instance UJI LOKAL untuk membuktikan stok bersama, bukan website cabang resmi.</div>
<?php endif; ?>

<div class="topbar">
    <div class="container tb-in">
        <span>Alverse cabang <?= e($branchName) ?></span>
        <nav aria-label="Tautan cepat">
            <a href="/cabang"<?= $current('/cabang') ?>>Info Cabang dan Pickup</a>
            <a href="/pesanan"<?= $current('/pesanan') ?>>Lacak Pesanan</a>
        </nav>
    </div>
</div>

<header class="hdr">
    <div class="container hb">
        <div class="brandwrap">
            <a class="brand" href="/"<?= $current('/') ?>>
                <img src="<?= asset('assets/img/logo.svg') ?>" alt="" width="38" height="38">
                <b>Alverse</b><span><?= e($branchName) ?></span>
            </a>
            <?php if ($megaMenu): ?>
                <button type="button" class="katbtn" id="mega-btn" aria-expanded="false" aria-controls="mega" hidden><?= icon('grid', 18) ?>Kategori</button>
            <?php endif; ?>
        </div>

        <div class="srch">
            <form class="sx" action="/katalog" method="get" role="search">
                <label class="sr-only" for="q-header">Cari produk</label>
                <input id="q-header" type="search" name="q" placeholder="Cari kaus, gamis, sneakers, atau kode P001" value="<?= e($searchQ ?? '') ?>">
                <button type="submit" class="sbtn" aria-label="Cari"><?= icon('search', 20) ?></button>
            </form>
            <nav class="kw" aria-label="Coba cari">
                <span>Coba cari:</span>
                <?php foreach ($searchTips as $tip): ?>
                    <a href="<?= e(url('/katalog', ['q' => $tip])) ?>"><?= e($tip) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="cartw">
            <a class="cartbtn" id="cart-btn" href="/keranjang" aria-label="<?= e($cartLabel) ?>"<?= $current('/keranjang') ?><?= $miniCart !== null ? ' aria-controls="mini-cart" aria-expanded="' . ($cartJustAdded ? 'true' : 'false') . '"' : '' ?>><?= icon('cart', 28) ?><span class="bd" aria-hidden="true"><?= e($cartText) ?></span></a>
            <?php if ($miniCart !== null): ?>
                <div class="mc" id="mini-cart" role="dialog" aria-labelledby="mc-h"<?= $cartJustAdded ? '' : ' hidden' ?>>
                    <h2 id="mc-h"><?= $cartJustAdded ? 'Baru ditambahkan ke keranjang' : 'Isi keranjang' ?></h2>
                    <?php if (!$miniCart['lines']): ?>
                        <p class="mc-empty">Belum ada barang di keranjang.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($miniCart['lines'] as $l): ?>
                                <li>
                                    <img src="<?= e(product_image($l, variant_color($l['variant']))) ?>" alt="" width="44" height="44">
                                    <span><a class="n" href="/produk/<?= e($l['product_id']) ?>"><?= e($l['name']) ?></a><small><?= e(size_text($l['size_label'])) ?>, <?= e($l['color_label']) ?> × <?= (int) $l['qty'] ?></small></span>
                                    <span class="p"><?= rupiah($l['line_total']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <div class="mcf"><span><?= (int) $miniCart['units'] ?> barang di keranjang</span><a href="/keranjang">Lihat keranjang</a></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($megaMenu): ?>
            <div class="mega" id="mega" role="dialog" aria-label="Kategori Alverse" hidden>
                <div class="mtabs" role="tablist" aria-label="Kategori" aria-orientation="vertical">
                    <?php $first = true; foreach ($megaMenu as $key => $m): ?>
                        <button type="button" class="mtab" role="tab" id="mtab-<?= e($key) ?>" aria-controls="mpanel-<?= e($key) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>"<?= $first ? '' : ' tabindex="-1"' ?>>
                            <span class="mtab-ic"><img src="<?= e(category_image($key)) ?>" alt="" width="28" height="28"></span><?= e($m['label']) ?>
                        </button>
                    <?php $first = false; endforeach; ?>
                </div>
                <?php $first = true; foreach ($megaMenu as $key => $m): ?>
                    <div class="mbody" role="tabpanel" id="mpanel-<?= e($key) ?>" aria-labelledby="mtab-<?= e($key) ?>"<?= $first ? '' : ' hidden' ?>>
                        <div>
                            <div class="mhead"><h2><?= e($m['label']) ?></h2><a href="/katalog?kategori=<?= e($key) ?>">Belanja semua</a></div>
                            <ul class="mlist">
                                <?php foreach ($m['items'] as $p): ?>
                                    <li><a href="/produk/<?= e($p['product_id']) ?>"><span><?= e($p['name']) ?></span><span class="pr2"><?= rupiah($p['price']) ?></span></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="mside">
                            <h3>Ringkas kategori ini</h3>
                            <dl>
                                <div><dt>Jumlah model</dt><dd><?= count($m['items']) ?> model</dd></div>
                                <div><dt>Harga</dt><dd><?= rupiah($m['min']) ?> sampai <?= rupiah($m['max']) ?></dd></div>
                                <div><dt>Ukuran</dt><dd><?= e($m['sizes']) ?></dd></div>
                                <div><dt>Pengiriman</dt><dd>Reguler <?= rupiah((int) config('shipping.reguler')) ?></dd></div>
                                <div><dt>Pickup</dt><dd><?= e($branchName) ?> <?= rupiah((int) config('shipping.pickup')) ?></dd></div>
                            </dl>
                        </div>
                        <a class="mpromo" href="/produk/<?= e($m['promo']['product_id']) ?>">
                            <img src="<?= e(product_image($m['promo'])) ?>" alt="" width="200" height="200" loading="lazy">
                            <span><?= e($m['promo']['name']) ?> · <?= rupiah($m['promo']['price']) ?></span>
                        </a>
                    </div>
                <?php $first = false; endforeach; ?>
                <button type="button" class="mclose" id="mega-close" aria-label="Tutup menu kategori"><?= icon('close', 20) ?></button>
            </div>
        <?php endif; ?>
    </div>
</header>
<?php if ($megaMenu): ?><div class="dim" id="mega-dim" hidden></div><?php endif; ?>

<main id="main" class="container page">
    <?php foreach (($flash ?? []) as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="ftr">
    <div class="container fg">
        <div>
            <p class="fb"><img src="<?= asset('assets/img/logo-ubin.svg') ?>" alt="" width="36" height="36"><b>Alverse</b></p>
            <p>Toko online Alverse cabang <?= e($branchName) ?>.</p>
        </div>
        <div>
            <h2>Layanan pembeli</h2>
            <ul>
                <li><a href="/cabang">Cara pickup di <?= e($branchName) ?></a></li>
                <li><a href="/pesanan">Lacak status pesanan</a></li>
                <li><a href="/keranjang">Keranjang belanja</a></li>
                <li><a href="/admin/login">Masuk admin cabang</a></li>
            </ul>
        </div>
        <div>
            <h2>Pengiriman dan pembayaran</h2>
            <ul>
                <li>Reguler <?= rupiah((int) config('shipping.reguler')) ?> per pesanan</li>
                <li>Pickup di <?= e($branchName) ?> <?= rupiah((int) config('shipping.pickup')) ?></li>
                <li>Pembayaran simulasi</li>
            </ul>
        </div>
        <div>
            <h2>Tentang website ini</h2>
            <ul>
                <li>Dibuat oleh <?= e((string) config('branch.developer')) ?> (Kelompok 5)</li>
                <li>Layanan data pusat: Kelompok 6</li>
            </ul>
        </div>
    </div>
    <div class="container fbot">
        <span>Website cabang lain:
            <?php foreach ($otherBranches as $i => $b): ?><?= $i ? ' · ' : '' ?><?php if ($b['url'] !== ''): ?><a href="<?= e($b['url']) ?>" rel="noopener"><?= e($b['name']) ?></a><?php else: ?><?= e($b['name']) ?><?php endif; ?><?php endforeach; ?>
        </span>
        <span>© <?= date('Y') ?> Alverse <?= e($branchName) ?></span>
    </div>
</footer>

<nav class="tabbar" aria-label="Menu utama">
    <a href="/"<?= $current('/') ?>><?= icon('home') ?><span>Beranda</span></a>
    <a href="/katalog"<?= $current('/katalog', '/produk') ?>><?= icon('grid') ?><span>Katalog</span></a>
    <a href="/keranjang"<?= $current('/keranjang', '/checkout') ?>><span class="tb-ic"><?= icon('cart') ?><span class="bd" aria-hidden="true"><?= e($cartText) ?></span></span><span>Keranjang</span></a>
    <a href="/pesanan"<?= $current('/pesanan') ?>><?= icon('receipt') ?><span>Pesanan</span></a>
    <a href="/cabang"<?= $current('/cabang') ?>><?= icon('pin') ?><span>Cabang</span></a>
</nav>
<script src="<?= asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
