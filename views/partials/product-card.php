<?php /** @var array $p @var bool|null $cardQuick @var bool|null $cardExtra */ $img = product_image($p); ?>
<article class="product-card"<?= !empty($cardExtra) ? ' data-extra' : '' ?>>
    <a href="/produk/<?= e($p['product_id']) ?>" class="product-card-link">
        <div class="product-media">
            <img src="<?= e($img) ?>" alt="<?= e($p['name']) ?> (<?= image_note($img) ?>)" loading="lazy" width="400" height="400">
        </div>
        <div class="product-body">
            <h3 class="product-name"><?= e($p['name']) ?></h3>
            <span class="price"><?= rupiah($p['price']) ?></span>
            <span class="meta"><?= count($p['colors']) ?> warna · <?= e(size_text($p['size_label'])) ?></span>
        </div>
    </a>
    <?php if (!empty($cardQuick)): ?>
        <button type="button" class="qvb" data-quick="<?= e($p['product_id']) ?>" hidden>Lihat cepat<span class="sr-only"> <?= e($p['name']) ?></span></button>
    <?php endif; ?>
</article>
