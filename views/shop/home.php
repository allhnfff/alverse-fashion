<?php
/** @var array $products @var array $quick @var array|null $muslimCheapest @var array $categories @var array $categoryCounts @var int $totalProducts @var array|null $promoSlide @var array $sideBanners */
use App\Controllers\ShopController;

$branchName = (string) config('branch.name');
$reguler = rupiah((int) config('shipping.reguler'));
$pickup = rupiah((int) config('shipping.pickup'));
$visible = ShopController::HOME_VISIBLE;
$rest = max(0, count($products) - $visible);
$slides = [
    ['cls' => 's-d', 'href' => '/cabang', 'kicker' => 'Pickup di ' . $branchName, 'title' => 'Ongkir ' . $pickup,
        'text' => 'Pilih pickup saat checkout, ambil setelah status pesanan Siap Diambil.', 'cta' => 'Lihat lokasi dan jam pickup', 'bg' => 'pickup'],
];
if ($promoSlide) {
    $names = array_column($promoSlide['items'], 'name');
    $lastName = array_pop($names);
    $nameText = $names ? implode(', ', $names) . (count($names) > 1 ? ',' : '') . ' dan ' . $lastName : (string) $lastName;
    $slides[] = ['cls' => 's-t', 'href' => '/katalog?kategori=' . $promoSlide['key'], 'kicker' => $promoSlide['label'], 'title' => 'Mulai ' . rupiah($promoSlide['min']),
        'text' => $promoSlide['count'] . ' model, termasuk ' . $nameText . '.', 'cta' => 'Lihat ' . lower($promoSlide['label']), 'bg' => $promoSlide['key'],
        // Foto ke-n memakai warna ke-n produknya supaya dua foto tidak terlihat kembar.
        'pics' => array_map(static fn (array $p, int $n): string => product_image($p, (string) ($p['colors'][$n % max(1, count($p['colors']))]['code'] ?? '')),
            array_slice($promoSlide['items'], 0, 2), [0, 1])];
}
if ($muslimCheapest) {
    $colorNames = array_column($muslimCheapest['colors'], 'label');
    $lastColor = array_pop($colorNames);
    $colorText = $colorNames ? implode(', ', $colorNames) . (count($colorNames) > 1 ? ',' : '') . ' dan ' . $lastColor : (string) $lastColor;
    $slides[] = ['cls' => 's-s', 'href' => '/katalog?kategori=busana-muslim', 'kicker' => 'Busana Muslim', 'title' => 'Mulai ' . rupiah($muslimCheapest['price']),
        'text' => $muslimCheapest['name'] . ' tersedia dalam ' . $colorText . '.',
        'cta' => 'Lihat busana muslim', 'bg' => 'busana-muslim'];
}
// Foto latar banner (gambar AI dari desain). Kalau filenya belum ada, banner tetap tampil dengan warna polos.
foreach ($slides as &$s) {
    $s['bgUrl'] = is_file(\App\App::root() . '/public/assets/img/banner/' . $s['bg'] . '.jpg') ? asset('assets/img/banner/' . $s['bg'] . '.jpg') : null;
}
unset($s);
?>
<h1 class="sr-only">Belanja di Alverse <?= e($branchName) ?></h1>

<?php if ($totalProducts === 0): ?>
    <div class="alert alert-info">Produk belum tersedia. Coba lagi nanti.</div>
<?php endif; ?>

<div class="hero">
    <section class="car" id="carousel" aria-roledescription="carousel" aria-label="Info utama">
        <?php foreach ($slides as $i => $s): ?>
            <a class="slide <?= e($s['cls']) ?><?= $s['bgUrl'] ? ' has-bg' : (!empty($s['pics']) ? ' has-pics' : '') ?><?= $i === 0 ? ' is-on' : '' ?>" href="<?= e($s['href']) ?>" role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> dari <?= count($slides) ?>">
                <?php if ($s['bgUrl']): ?>
                    <img class="bgi" src="<?= e($s['bgUrl']) ?>" alt="" width="1600" height="900"<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>>
                <?php elseif (!empty($s['pics'])): ?>
                    <span class="pics" aria-hidden="true">
                        <?php foreach ($s['pics'] as $src): ?><img src="<?= e($src) ?>" alt="" width="800" height="800"<?= $i === 0 ? '' : ' loading="lazy"' ?>><?php endforeach; ?>
                    </span>
                <?php elseif ($s['cls'] === 's-d'): ?>
                    <img class="wm" src="<?= asset('assets/img/logo.svg') ?>" alt="" width="300" height="300">
                <?php endif; ?>
                <small><?= e($s['kicker']) ?></small>
                <strong><?= e($s['title']) ?></strong>
                <p><?= e($s['text']) ?></p>
                <span class="cta"><?= e($s['cta']) ?></span>
            </a>
        <?php endforeach; ?>
        <button type="button" class="arr l" data-slide="-1" aria-label="Banner sebelumnya" hidden><?= icon('prev', 20) ?></button>
        <button type="button" class="arr r" data-slide="1" aria-label="Banner berikutnya" hidden><?= icon('next', 20) ?></button>
        <div class="dots" role="group" aria-label="Pilih banner" hidden>
            <?php foreach ($slides as $i => $s): ?>
                <button type="button" class="dot<?= $i === 0 ? ' on' : '' ?>" data-dot="<?= $i ?>" aria-label="Banner <?= $i + 1 ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>><i></i></button>
            <?php endforeach; ?>
        </div>
    </section>
    <div class="side">
        <?php foreach ($sideBanners as $k => $sb): ?>
            <a class="sb2 has-img <?= $k === 0 ? 's-t' : 's-s' ?>" href="/katalog?kategori=<?= e($sb['key']) ?>">
                <span class="sb-tx"><small><?= e($sb['label']) ?></small><strong>Mulai <?= rupiah($sb['min']) ?></strong><p><?= (int) $sb['count'] ?> model</p></span>
                <img src="<?= e(product_image($sb['items'][0])) ?>" alt="" width="800" height="800" loading="lazy">
            </a>
        <?php endforeach; ?>
        <?php if (!$sideBanners): ?>
            <a class="sb2 s-s" href="/cabang"><small>Pengiriman</small><strong>Reguler <?= $reguler ?></strong><p>Per pesanan, berapa pun jumlah barangnya.</p></a>
        <?php endif; ?>
    </div>
</div>

<ul class="svc5" aria-label="Layanan">
    <li><span class="ic"><?= icon('truck') ?></span><span>Reguler <?= $reguler ?> per pesanan</span></li>
    <li><span class="ic"><?= icon('code') ?></span><span>Kode pickup 6 angka</span></li>
    <li><span class="ic"><?= icon('receipt') ?></span><span>Status pesanan bisa dilacak</span></li>
</ul>

<section class="panel" aria-labelledby="kat-h">
    <div class="panel-h"><h2 id="kat-h">Kategori</h2></div>
    <ul class="cats">
        <?php foreach ($categories as $key => $label): ?>
            <li><a class="cat" href="/katalog?kategori=<?= e($key) ?>">
                <span class="cat-ic"><img src="<?= e(category_image($key)) ?>" alt="" width="84" height="84"></span>
                <span><?= e($label) ?></span>
            </a></li>
        <?php endforeach; ?>
    </ul>
</section>

<?php if ($products): ?>
<section aria-labelledby="rek-h">
    <h2 class="rek" id="rek-h">Produk Alverse <?= e($branchName) ?></h2>
    <div class="product-grid grid-wide" id="home-grid">
        <?php foreach ($products as $i => $p): ?>
            <?php $cardQuick = true; $cardExtra = $i >= $visible; include __DIR__ . '/../partials/product-card.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php if ($rest > 0): ?>
        <div class="more"><button type="button" class="btn btn-ghost" id="show-more" aria-controls="home-grid" hidden>Lihat <?= $rest ?> produk lainnya</button></div>
    <?php endif; ?>
</section>

<dialog class="qv" id="quick-view" aria-labelledby="qv-h">
    <div class="g"><img id="qv-img" src="" alt="" width="380" height="380"><p id="qv-note">Gambar dibuat dengan AI, bukan foto produk asli.</p></div>
    <form class="i" action="/keranjang/tambah" method="post" id="qv-form">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" id="qv-pid" value="">
        <input type="hidden" name="kembali" value="beranda">
        <button type="button" class="qclose" id="qv-close" aria-label="Tutup"><?= icon('close', 22) ?></button>
        <h2 id="qv-h"></h2>
        <p class="code" id="qv-code"></p>
        <p class="pbox" id="qv-price"></p>
        <fieldset>
            <legend class="lbl2" id="qv-color-lbl">Warna</legend>
            <div class="option-list" id="qv-colors"></div>
        </fieldset>
        <fieldset>
            <legend class="lbl2" id="qv-size-lbl">Ukuran</legend>
            <div class="option-list" id="qv-sizes"></div>
        </fieldset>
        <div class="qrow">
            <label class="lbl2" for="qv-qty">Jumlah</label>
            <div class="stepper">
                <button type="button" class="step-btn" data-step="-1" aria-controls="qv-qty" aria-label="Kurangi jumlah">−</button>
                <input id="qv-qty" class="qty-input" type="number" name="qty" value="1" min="1" max="99" step="1" inputmode="numeric">
                <button type="button" class="step-btn" data-step="1" aria-controls="qv-qty" aria-label="Tambah jumlah">+</button>
            </div>
            <span class="qv-stock" id="qv-stock" aria-live="polite"></span>
        </div>
        <div class="qacts">
            <button type="submit" class="btn btn-primary btn-lg" id="qv-add">Masukkan keranjang</button>
            <a class="btn btn-ghost btn-lg" id="qv-detail" href="/katalog">Lihat detail lengkap</a>
        </div>
    </form>
</dialog>
<script type="application/json" id="quick-data"><?= json_encode($quick, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
