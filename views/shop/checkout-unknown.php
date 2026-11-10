<?php /** @var string $requestId @var string $detail */ ?>
<div class="card notice-card">
    <h1>Hasil checkout belum diketahui</h1>
    <p>Koneksi ke layanan data terputus saat pesananmu diproses, jadi pesanan <strong>mungkin sudah tercatat, mungkin juga belum</strong>.</p>
    <p>Tekan tombol di bawah untuk mengecek hasilnya. Mengecek atau mencoba lagi tidak akan memotong stok dua kali.</p>
    <form action="/checkout/periksa" method="post">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit">Cek hasil pesanan</button>
    </form>
    <p class="muted small">Detail teknis: <?= e($detail) ?> (kode permintaan <code><?= e(substr($requestId, 0, 8)) ?>…</code>)</p>
</div>
