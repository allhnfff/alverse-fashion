<?php
/** @var array $order */
use App\Domain\OrderRules;

$steps = OrderRules::steps($order['shipping_method']);
$currentIdx = array_search($order['status'], $steps, true);
$reachedAt = [];
foreach (($order['history'] ?? []) as $h) {
    $s = (string) ($h['status'] ?? '');
    if ($s !== '' && !isset($reachedAt[$s])) {
        $reachedAt[$s] = $h['at'] ?? null;
    }
}
?>
<?php if ($order['status'] === 'dibatalkan'): ?>
    <div class="cancel-note" role="status">
        <?php $status = 'dibatalkan'; include __DIR__ . '/status-badge.php'; ?>
        <p>Pesanan dibatalkan<?= ($order['cancel_reason'] ?? '') !== '' ? '. Alasan: ' . e($order['cancel_reason']) : '' ?>.
            <?= !empty($order['restocked']) && empty($forCustomer) ? 'Stok bersama sudah dikembalikan.' : '' ?></p>
    </div>
<?php else: ?>
    <ol class="timeline" aria-label="Status pesanan">
        <?php foreach ($steps as $i => $s): ?>
            <?php $done = $currentIdx !== false && $i <= $currentIdx; ?>
            <li class="<?= $done ? 'done' : '' ?><?= $i === $currentIdx ? ' current' : '' ?>"<?= $i === $currentIdx ? ' aria-current="step"' : '' ?>>
                <span class="dot" aria-hidden="true"><?= $done ? icon('check', 14) : $i + 1 ?></span>
                <span class="tl-label"><?= e(OrderRules::label($s)) ?></span>
                <span class="tl-at"><?= $done && isset($reachedAt[$s]) ? e(wib($reachedAt[$s], 'd M, H.i')) : '' ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
