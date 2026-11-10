<?php /** @var array $branch @var array $branches @var string $prefix */
$reguler = rupiah((int) config('shipping.reguler'));
$pickup = rupiah((int) config('shipping.pickup'));
?>
<nav class="breadcrumb" aria-label="Breadcrumb"><a href="/">Beranda</a><span aria-hidden="true">/</span><span aria-current="page">Info Cabang</span></nav>

<div class="branch-top">
    <section class="branch-hero">
        <img class="wm" src="<?= asset('assets/img/logo.svg') ?>" alt="" width="260" height="260">
        <h1>Alverse <?= e($branch['name']) ?></h1>
        <p>Nomor pesanan dari cabang ini diawali <?= e($prefix) ?>.</p>
        <a class="cta" href="/pesanan">Lihat status pesananmu</a>
    </section>

    <section class="card">
        <h2 class="card-title">Informasi cabang</h2>
        <dl class="detail-list">
            <div><dt>Alamat</dt><dd><?= e($branch['address']) ?></dd></div>
            <div><dt>Kontak</dt><dd><?= e($branch['phone']) ?></dd></div>
            <div><dt>Jam pickup</dt><dd><?= e($branch['pickup_hours']) ?></dd></div>
            <div><dt>Lokasi pickup</dt><dd><?= e($branch['pickup_location']) ?></dd></div>
            <div><dt>Ongkir</dt><dd>Reguler <?= $reguler ?>, pickup <?= $pickup ?></dd></div>
        </dl>
    </section>
</div>

<div class="two-col">
    <section class="card">
        <h2 class="card-title">Cara mengambil pesanan di <?= e($branch['name']) ?></h2>
        <ol class="howto">
            <li>Pilih <strong>Pickup</strong> saat checkout. Ongkirnya <?= $pickup ?>, dan kode pickup 6 angka langsung muncul di halaman pesananmu.</li>
            <li>Tunggu sampai status pesanan berubah dari <?php $status = 'dikonfirmasi'; include __DIR__ . '/../partials/status-badge.php'; ?> menjadi <?php $status = 'siap_diambil'; include __DIR__ . '/../partials/status-badge.php'; ?>.</li>
            <li>Datang ke lokasi pickup dan tunjukkan kodenya. Setelah petugas mencocokkan kode, status menjadi <?php $status = 'selesai'; include __DIR__ . '/../partials/status-badge.php'; ?>.</li>
        </ol>
        <a class="btn btn-primary" href="/katalog">Mulai belanja</a>
    </section>

    <section class="card" id="stok-bersama">
        <h2 class="card-title">Cara kerja stok bersama</h2>
        <ul class="plain-list">
            <li>Ketujuh website Alverse memakai <strong>satu stok</strong> untuk tiap produk, ukuran, dan warna. Tidak ada stok khusus per cabang.</li>
            <li>Pembelian di website mana pun memotong stok itu satu kali, lalu semua website menampilkan angka yang sama.</li>
            <li>Stok di halaman produk diperbarui tiap 5 detik, jadi bisa terlambat beberapa detik. Checkout selalu memeriksa angka terbaru.</li>
            <li>Stok bersama tidak berarti barang sudah ada di <?= e($branch['name']) ?>. Karena itu pickup baru bisa setelah status Siap Diambil.</li>
        </ul>
    </section>
</div>

<section class="panel" id="cabang-lain" aria-labelledby="cab-h">
    <div class="panel-h"><h2 id="cab-h">Tujuh website cabang Alverse</h2></div>
    <p class="panel-sub muted small">Pindah website berarti memakai keranjang di website itu. Keranjangmu di sini tidak ikut pindah.</p>
    <ul class="branch-list">
        <?php foreach ($branches as $b): ?>
            <li class="<?= $b['current'] ? 'is-current' : '' ?>">
                <span class="code-chip"><?= e($b['prefix']) ?></span>
                <span class="b-name"><strong><?= e($b['name']) ?></strong><span class="small muted"><?= e($b['developer']) ?></span></span>
                <?php if ($b['current']): ?>
                    <span class="small b-here">kamu di sini</span>
                <?php elseif ($b['url'] !== ''): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e($b['url']) ?>" rel="noopener">Buka website</a>
                <?php else: ?>
                    <span class="small muted">tautan belum ada</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
