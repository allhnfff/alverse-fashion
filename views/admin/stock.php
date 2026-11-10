<?php
/** @var array $products @var array $stocks @var string $q @var string $category @var int $fetchedAt */
use App\Domain\MasterCatalog;
?>
<div class="page-head">
    <h1>Stok global</h1>
    <p class="muted">Satu angka per produk, ukuran, dan warna, dipakai oleh <strong>semua</strong> website cabang. Dibaca <?= e(date('H:i:s', $fetchedAt)) ?> WIB.</p>
</div>

<div class="alert alert-info">Halaman ini hanya untuk melihat. Koreksi dan restock stok global adalah wewenang <strong>admin_pusat</strong> lewat panel pusat (Kelompok 6).
    Jika ada selisih, kirim permintaan koreksi ke petugas pusat beserta varian, angka, dan alasannya.</div>

<form class="filter-bar wrap" method="get" action="/admin/stok">
    <div class="field compact">
        <label for="s-q" class="label">Cari</label>
        <input id="s-q" type="search" name="q" value="<?= e($q) ?>" placeholder="P037 / Topi">
    </div>
    <div class="field compact">
        <label for="s-kat" class="label">Kategori</label>
        <select id="s-kat" name="kategori">
            <option value="">Semua</option>
            <?php foreach (MasterCatalog::CATEGORIES as $k => $l): ?>
                <option value="<?= e($k) ?>" <?= $category === $k ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button class="btn btn-primary" type="submit">Terapkan</button>
        <a class="btn btn-ghost" href="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '/admin/stok')) ?>">Muat ulang</a>
    </div>
</form>

<?php foreach ($products as $p): ?>
    <?php $s = $stocks[$p['product_id']] ?? []; $total = array_sum($s); ?>
    <section class="card stock-card">
        <div class="stock-card-head">
            <strong><?= e($p['product_id']) ?> · <?= e($p['name']) ?></strong>
            <span class="small muted"><?= e($p['category_label']) ?> · <?= rupiah($p['price']) ?> · total semua varian <?= (int) $total ?></span>
        </div>
        <div class="table-wrap">
            <table class="table stock-table">
                <thead>
                <tr><th>Ukuran \ Warna</th>
                    <?php foreach ($p['colors'] as $c): ?><th class="num"><?= e($c['label']) ?></th><?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($p['sizes'] as $sz): ?>
                    <tr>
                        <th scope="row"><?= e($sz['label']) ?></th>
                        <?php foreach ($p['colors'] as $c): ?>
                            <?php $code = $sz['code'] . '_' . $c['code']; $v = $s[$code] ?? null; ?>
                            <td class="num <?= $v === 0 ? 'text-danger' : ($v === null ? 'muted' : '') ?>" title="<?= e($code) ?>"><?= $v === null ? '-' : (int) $v ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endforeach; ?>
<?php if (!$products): ?><p class="muted">Tidak ada produk.</p><?php endif; ?>
