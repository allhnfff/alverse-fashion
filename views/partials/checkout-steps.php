<?php /** @var int $step 1 atau 2 */ ?>
<ol class="steps" aria-label="Langkah checkout">
    <li class="<?= $step === 1 ? 'is-current' : 'is-done' ?>"<?= $step === 1 ? ' aria-current="step"' : '' ?>><span class="n"><?= $step > 1 ? icon('check', 16) : '1' ?></span>Data penerima dan metode</li>
    <li class="<?= $step === 2 ? 'is-current' : '' ?>"<?= $step === 2 ? ' aria-current="step"' : '' ?>><span class="n">2</span>Konfirmasi pembayaran simulasi</li>
</ol>
