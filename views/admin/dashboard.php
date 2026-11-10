<?php
/** @var array $counts @var int $total @var int $today @var array $needAction @var array $recent */
use App\Domain\OrderRules;
?>
<div class="page-head">
    <h1>Dashboard</h1>
    <p class="muted">Ringkasan pesanan asal website <?= e((string) config('branch.name')) ?>.</p>
</div>

<div class="stat-grid">
    <div class="stat card"><span class="label muted">Total pesanan</span><strong class="stat-num"><?= (int) $total ?></strong></div>
    <div class="stat card"><span class="label muted">Hari ini</span><strong class="stat-num"><?= (int) $today ?></strong></div>
    <?php foreach (OrderRules::STATUS_LABELS as $key => $label): ?>
        <a class="stat card" href="/admin/pesanan?status=<?= e($key) ?>">
            <span class="label muted"><?= e($label) ?></span><strong class="stat-num"><?= (int) ($counts[$key] ?? 0) ?></strong>
        </a>
    <?php endforeach; ?>
</div>

<section class="section">
    <div class="section-head"><h2>Perlu diproses</h2><a class="link" href="/admin/pesanan">Semua pesanan →</a></div>
    <?php if (!$needAction): ?>
        <p class="muted">Tidak ada pesanan yang menunggu tindakan.</p>
    <?php else: ?>
        <?php $orders = $needAction; include __DIR__ . '/partials/order-table.php'; ?>
    <?php endif; ?>
</section>

<section class="section">
    <h2>Pesanan terbaru</h2>
    <?php if (!$recent): ?>
        <p class="muted">Belum ada pesanan.</p>
    <?php else: ?>
        <?php $orders = $recent; include __DIR__ . '/partials/order-table.php'; ?>
    <?php endif; ?>
</section>
