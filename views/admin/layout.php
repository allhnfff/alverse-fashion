<?php
/** @var string $content @var array|null $admin */
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$is = static fn (string $p): string => ($p === '/admin' ? $path === '/admin' : str_starts_with($path, $p)) ? ' aria-current="page"' : '';
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e(($title ?? 'Admin') . ' · Admin Alverse ' . config('branch.name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <link rel="icon" href="<?= asset('assets/img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body class="admin">
<header class="hdr admin-header">
    <div class="container admin-header-grid">
        <a class="brand" href="/admin">
            <img src="<?= asset('assets/img/logo.svg') ?>" alt="" width="36" height="36">
            <b>Alverse</b><span>Admin <?= e((string) config('branch.name')) ?></span>
        </a>
        <?php if (!empty($admin)): ?>
            <nav class="main-nav admin-nav" aria-label="Navigasi admin">
                <a href="/admin"<?= $is('/admin') ?>>Dashboard</a>
                <a href="/admin/pesanan"<?= $is('/admin/pesanan') ?>>Pesanan</a>
                <a href="/admin/stok"<?= $is('/admin/stok') ?>>Stok global</a>
                <a href="/admin/rekap"<?= $is('/admin/rekap') ?>>Rekap</a>
                <a href="/" target="_blank" rel="noopener">Lihat toko</a>
            </nav>
            <form action="/admin/logout" method="post" class="admin-user">
                <?= csrf_field() ?>
                <span class="small muted"><?= e($admin['username']) ?> · <?= e($admin['role']) ?></span>
                <button class="btn btn-on-dark btn-sm" type="submit">Keluar</button>
            </form>
        <?php endif; ?>
    </div>
</header>
<main id="main" class="container page">
    <?php foreach (($flash ?? []) as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<footer class="ftr admin-ftr">
    <div class="container fbot">
        Admin cabang hanya mengelola pesanan asal <?= e((string) config('branch.name')) ?> (branch_id <code><?= e((string) config('branch.id')) ?></code>).
        Sumber data: <?= e($source['label'] ?? '-') ?>.
    </div>
</footer>
<script src="<?= asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
