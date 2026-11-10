<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Data\DataServiceUnavailable;
use App\Data\DomainRejected;
use App\Domain\OrderRules;
use App\Domain\ValidationError;
use App\Domain\Validator;
use App\Http\Csrf;
use App\Http\HttpException;
use App\Http\Session;
use App\Http\View;

/** Pesanan pelanggan: hanya sesi pemilik yang boleh melihat. */
final class OrderController extends Controller
{
    private function access(): array
    {
        return ['type' => 'customer', 'branch_id' => $this->branchId(), 'session_hash' => Session::ownerHash()];
    }

    public function index(): void
    {
        $orders = $this->data()->listOrders($this->access(), ['limit' => 100]);
        $this->render('shop/orders', ['title' => 'Pesanan Saya', 'orders' => $orders]);
    }

    public function show(string $id): void
    {
        try {
            $id = Validator::orderId($id);
        } catch (ValidationError) {
            throw new HttpException(404, 'Pesanan tidak ditemukan.');
        }
        $order = $this->data()->getOrder($id, $this->access());
        if ($order === null) {
            // Sama untuk "tidak ada" dan "milik sesi lain" agar kode pesanan orang lain tidak bisa ditebak.
            throw new HttpException(404, 'Pesanan tidak ditemukan atau bukan milik sesi ini.');
        }
        $isNew = $this->query('baru') === '1';
        $freshStock = null;
        if ($isNew) {
            // Setelah transaksi: baca stok global terbaru untuk item yang dibeli.
            try {
                $freshStock = $this->data()->getStockMany(array_column($order['items'], 'product_id'));
            } catch (DataServiceUnavailable) {
                $freshStock = false;
            }
        }
        $this->render('shop/order', [
            'title'         => 'Pesanan ' . $order['order_id'],
            'order'         => $order,
            'isNew'         => $isNew,
            'freshStock'    => $freshStock,
            'cancelReasons' => self::CANCEL_REASONS,
        ]);
    }

    /** Alasan yang bisa dipilih pembeli. Hanya kunci dari daftar ini yang diterima server. */
    public const CANCEL_REASONS = [
        'salah-varian'  => 'Salah pilih ukuran atau warna',
        'ganti-metode'  => 'Ingin ganti cara menerima pesanan',
        'tidak-jadi'    => 'Tidak jadi membeli',
        'lainnya'       => 'Alasan lain',
    ];

    /**
     * Pembeli membatalkan pesanannya sendiri (wireframe hal. 7).
     * - Hanya pesanan milik sesi browser ini (akses customer + session_hash).
     * - Hanya saat Dikonfirmasi; dicek lagi secara atomik oleh skrip pembatalan yang sama dengan admin,
     *   sehingga stok bersama dikembalikan tepat satu kali walaupun tombol ditekan dua kali.
     */
    public function cancel(string $id): void
    {
        Csrf::verify();
        try {
            $id = Validator::orderId($id);
        } catch (ValidationError) {
            throw new HttpException(404, 'Pesanan tidak ditemukan.');
        }
        $order = $this->data()->getOrder($id, $this->access());
        if ($order === null) {
            throw new HttpException(404, 'Pesanan tidak ditemukan atau bukan milik sesi ini.');
        }
        $back = '/pesanan/' . $order['order_id'];
        $reasonKey = $this->post('reason');
        if (!array_key_exists($reasonKey, self::CANCEL_REASONS)) {
            Session::flash('error', 'Pilih alasan pembatalan terlebih dahulu.');
            View::redirect($back . '#batal');
        }
        if ($order['status'] === 'dibatalkan') {
            // Klik dua kali atau kirim ulang form: tidak ada yang berubah, stok tidak dikembalikan lagi.
            Session::flash('info', 'Pesanan ini sudah dibatalkan sebelumnya.');
            View::redirect($back);
        }
        if (!OrderRules::canCancel($order['status'])) {
            Session::flash('error', DomainRejected::defaultMessage('NOT_CANCELLABLE'));
            View::redirect($back);
        }
        try {
            $res = $this->data()->cancelOrder(
                $order['order_id'],
                'Oleh pembeli: ' . self::CANCEL_REASONS[$reasonKey],
                ['branch_id' => $this->branchId(), 'username' => 'pembeli', 'role' => 'pembeli']
            );
            Session::flash(
                $res['status'] === 'cancelled' ? 'success' : 'info',
                $res['status'] === 'cancelled'
                    ? 'Pesanan dibatalkan.'
                    : 'Pesanan ini sudah dibatalkan sebelumnya.'
            );
        } catch (DomainRejected $e) {
            Session::flash('error', $e->getMessage());
        } catch (DataServiceUnavailable) {
            Session::flash('error', 'Koneksi terputus, jadi hasil pembatalan belum pasti. Muat ulang halaman ini untuk melihat status terbaru.');
        }
        View::redirect($back);
    }
}
