<?php /** @var string $status */ ?>
<span class="status status-<?= e($status) ?>"><?= e(\App\Domain\OrderRules::label($status)) ?></span>