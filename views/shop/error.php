<?php /** @var int $status @var string $message */ ?>
<div class="empty-state">
    <p class="eyebrow">Kode <?= (int) $status ?></p>
    <h1><?= e($title) ?></h1>
    <p class="muted"><?= e($message) ?></p>
    <div class="actions">
        <a class="btn btn-primary" href="/">Kembali ke beranda</a>
        <?php if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'): ?>
            <a class="btn btn-ghost" href="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '/')) ?>">Coba lagi</a>
        <?php endif; ?>
    </div>
</div>
