<?php
/** @var array $orders @var array $filter @var int $total @var int $page @var int $pages */
use App\Domain\OrderRules;

$pageUrl = static fn (int $n): string => url('/admin/pesanan', ['q' => $filter['q'], 'status' => $filter['status'], 'metode' => $filter['method'], 'hal' => $n > 1 ? $n : null]);
?>
<div class="page-head">
    <h1>Pesanan <?= e((string) config('branch.name')) ?></h1>
    <p class="muted"><?= (int) $total ?> pesanan sesuai filter<?= $pages > 1 ? ', halaman ' . $page . ' dari ' . $pages : '' ?>. Hanya pesanan dengan branch_id <code><?= e((string) config('branch.id')) ?></code>.</p>
</div>

<form class="filter-bar wrap" method="get" action="/admin/pesanan">
    <div class="field compact">
        <label for="f-q" class="label">Kode / nama</label>
        <input id="f-q" type="search" name="q" value="<?= e($filter['q']) ?>" placeholder="BGR-...">
    </div>
    <div class="field compact">
        <label for="f-status" class="label">Status</label>
        <select id="f-status" name="status">
            <option value="">Semua</option>
            <?php foreach (OrderRules::STATUS_LABELS as $k => $l): ?>
                <option value="<?= e($k) ?>" <?= $filter['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field compact">
        <label for="f-metode" class="label">Metode</label>
        <select id="f-metode" name="metode">
            <option value="">Semua</option>
            <option value="reguler" <?= $filter['method'] === 'reguler' ? 'selected' : '' ?>>Reguler</option>
            <option value="pickup" <?= $filter['method'] === 'pickup' ? 'selected' : '' ?>>Pickup</option>
        </select>
    </div>
    <div class="filter-actions">
        <button class="btn btn-primary" type="submit">Terapkan</button>
        <a class="btn btn-ghost" href="/admin/pesanan">Reset</a>
    </div>
</form>

<?php if (!$orders): ?>
    <p class="muted">Tidak ada pesanan.</p>
<?php else: ?>
    <?php include __DIR__ . '/partials/order-table.php'; ?>
    <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Halaman daftar pesanan">
            <?php if ($page > 1): ?><a href="<?= e($pageUrl($page - 1)) ?>" rel="prev" aria-label="Halaman sebelumnya">‹</a><?php else: ?><span class="off" aria-hidden="true">‹</span><?php endif; ?>
            <?php for ($n = 1; $n <= $pages; $n++): ?>
                <?php if ($n === $page): ?><span class="cur" aria-current="page"><?= $n ?></span><?php else: ?><a href="<?= e($pageUrl($n)) ?>"><?= $n ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a href="<?= e($pageUrl($page + 1)) ?>" rel="next" aria-label="Halaman berikutnya">›</a><?php else: ?><span class="off" aria-hidden="true">›</span><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
