<?php
/** @var array $orders */
use App\Domain\OrderRules;
?>
<div class="table-wrap">
    <table class="table">
        <thead>
        <tr><th>Kode</th><th>Waktu</th><th>Penerima</th><th>Metode</th><th class="num">Barang</th><th class="num">Total</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><a href="/admin/pesanan/<?= e($o['order_id']) ?>"><strong><?= e($o['order_id']) ?></strong></a></td>
                <td class="nowrap"><?= e(wib($o['created_at'], 'd M, H:i')) ?></td>
                <td><?= e($o['customer_name']) ?></td>
                <td><?= $o['shipping_method'] === 'pickup' ? 'Pickup' : 'Reguler' ?></td>
                <td class="num"><?= (int) $o['total_units'] ?></td>
                <td class="num nowrap"><?= rupiah($o['total']) ?></td>
                <td><?php $status = $o['status']; include __DIR__ . '/../../partials/status-badge.php'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
