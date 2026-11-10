<?php /** @var bool $configured */ ?>
<div class="login-wrap">
    <form action="/admin/login" method="post" class="card form-card login-card">
        <?= csrf_field() ?>
        <h1 class="h2">Masuk admin cabang</h1>
        <p class="muted small">Website <?= e((string) config('branch.name')) ?> · peran admin_cabang</p>
        <?php if (!$configured): ?>
            <div class="alert alert-info">Password admin belum diatur. Di terminal folder proyek jalankan:<br><code>php scripts/set-admin-password.php</code></div>
        <?php endif; ?>
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" autocomplete="username" required>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <div class="pass-wrap">
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button type="button" class="pass-toggle" id="pass-toggle" aria-controls="password" aria-pressed="false" hidden>Lihat</button>
            </div>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Masuk</button>
        <a class="link small" href="/">← Kembali ke toko</a>
    </form>
</div>
