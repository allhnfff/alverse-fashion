<?php

declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Data\DomainRejected;
use App\Domain\AdminAuth;
use App\Domain\MasterCatalog;
use App\Domain\OrderRules;
use App\Domain\ValidationError;
use App\Domain\Validator;
use App\Http\Csrf;
use App\Http\HttpException;
use App\Http\Session;
use App\Http\View;

/**
 * Admin cabang Bogor.
 * - Hanya mengelola pesanan dengan branch_id = bogor (diambil dari konfigurasi server).
 * - Melihat stok global (read-only). Restock/koreksi = wewenang admin_pusat.
 */
final class AdminController extends Controller
{
    // ---------------- Login ----------------

    public function loginForm(): void
    {
        if (AdminAuth::user() !== null) {
            View::redirect('/admin');
        }
        $this->renderAdmin('admin/login', ['title' => 'Login Admin', 'configured' => AdminAuth::isConfigured()]);
    }

    public function login(): void
    {
        Csrf::verify();
        $error = AdminAuth::attempt(trim($this->post('username')), $this->post('password'));
        if ($error !== null) {
            Session::flash('error', $error);
            View::redirect('/admin/login');
        }
        Session::flash('success', 'Berhasil masuk sebagai admin cabang ' . App::config('branch.name') . '.');
        View::redirect('/admin');
    }

    public function logout(): void
    {
        Csrf::verify();
        AdminAuth::logout();
        Session::flash('success', 'Kamu sudah keluar.');
        View::redirect('/admin/login');
    }

    // ---------------- Dashboard ----------------

    public function dashboard(): void
    {
        $user = AdminAuth::require();
        $orders = $this->data()->listOrders(AdminAuth::access($user), ['limit' => 500]);
        $counts = array_fill_keys(array_keys(OrderRules::STATUS_LABELS), 0);
        $todayStart = (new \DateTimeImmutable('today', new \DateTimeZone('Asia/Jakarta')))->getTimestamp();
        $today = 0;
        foreach ($orders as $o) {
            $counts[$o['status']] = ($counts[$o['status']] ?? 0) + 1;
            if ((int) $o['created_at'] >= $todayStart) {
                $today++;
            }
        }
        $needAction = array_values(array_filter($orders, static fn ($o) => in_array($o['status'], ['dikonfirmasi', 'dikemas', 'siap_diambil'], true)));
        $this->renderAdmin('admin/dashboard', [
            'title'      => 'Dashboard',
            'counts'     => $counts,
            'total'      => count($orders),
            'today'      => $today,
            'needAction' => array_slice($needAction, 0, 8),
            'recent'     => array_slice($orders, 0, 5),
        ]);
    }

    // ---------------- Pesanan ----------------

    public function orders(): void
    {
        $user = AdminAuth::require();
        $filter = ['status' => '', 'method' => '', 'q' => $this->query('q')];
        $status = $this->query('status');
        if (array_key_exists($status, OrderRules::STATUS_LABELS)) {
            $filter['status'] = $status;
        }
        $method = $this->query('metode');
        if (in_array($method, OrderRules::METHODS, true)) {
            $filter['method'] = $method;
        }
        $all = $this->data()->listOrders(AdminAuth::access($user), $filter + ['limit' => 500]);
        // Halaman per 20 pesanan (wireframe hal. 13). Nomor halaman di luar jangkauan dikembalikan ke batas terdekat.
        $total = count($all);
        $pages = max(1, (int) ceil($total / self::ORDERS_PER_PAGE));
        $page = min($pages, max(1, (int) $this->query('hal')));
        $orders = array_slice($all, ($page - 1) * self::ORDERS_PER_PAGE, self::ORDERS_PER_PAGE);
        $this->renderAdmin('admin/orders', [
            'title'  => 'Pesanan',
            'orders' => $orders,
            'filter' => $filter,
            'total'  => $total,
            'page'   => $page,
            'pages'  => $pages,
        ]);
    }

    public const ORDERS_PER_PAGE = 20;

    private function loadOrder(array $user, string $id): array
    {
        try {
            $id = Validator::orderId($id);
        } catch (ValidationError) {
            throw new HttpException(404, 'Pesanan tidak ditemukan.');
        }
        $order = $this->data()->getOrder($id, AdminAuth::access($user));
        if ($order === null) {
            // Termasuk pesanan cabang lain: tidak ditampilkan.
            throw new HttpException(404, 'Pesanan tidak ditemukan di cabang ' . App::config('branch.name') . '.');
        }
        return $order;
    }

    public function orderShow(string $id): void
    {
        $user = AdminAuth::require();
        $order = $this->loadOrder($user, $id);
        $this->renderAdmin('admin/order', [
            'title' => 'Pesanan ' . $order['order_id'],
            'order' => $order,
            'next'  => OrderRules::nextStatus($order['shipping_method'], $order['status']),
        ]);
    }

    public function updateStatus(string $id): void
    {
        $user = AdminAuth::require();
        Csrf::verify();
        $order = $this->loadOrder($user, $id);
        $back = '/admin/pesanan/' . $order['order_id'];
        try {
            $from = Validator::status($this->post('from'));
            $to = Validator::status($this->post('to'));
            $change = ['from' => $from, 'to' => $to];
            if ($to === 'dikirim') {
                $change['courier'] = Validator::text($this->post('courier'), 'Kurir', 2, 40);
                $change['tracking_number'] = Validator::text($this->post('tracking_number'), 'Nomor resi', 3, 40);
            }
            if ($to === 'selesai' && $order['shipping_method'] === 'pickup') {
                $code = trim($this->post('pickup_code'));
                if (!preg_match('/^\d{6}$/', $code)) {
                    throw new ValidationError('Masukkan 6 digit kode pickup dari pelanggan.');
                }
                $change['pickup_code'] = $code;
            }
            $res = $this->data()->updateOrderStatus($order['order_id'], $change, AdminAuth::actor($user));
            Session::flash(
                $res['status'] === 'unchanged' ? 'info' : 'success',
                $res['status'] === 'unchanged'
                    ? 'Status sudah ' . OrderRules::label($to) . ' (tidak ada perubahan ganda).'
                    : 'Status diperbarui menjadi ' . OrderRules::label($to) . '.'
            );
        } catch (ValidationError | DomainRejected $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect($back);
    }

    public function cancel(string $id): void
    {
        $user = AdminAuth::require();
        Csrf::verify();
        $order = $this->loadOrder($user, $id);
        try {
            $reason = Validator::text($this->post('reason'), 'Alasan pembatalan', 5, 200);
            $res = $this->data()->cancelOrder($order['order_id'], $reason, AdminAuth::actor($user));
            Session::flash(
                $res['status'] === 'cancelled' ? 'success' : 'info',
                $res['status'] === 'cancelled'
                    ? 'Pesanan dibatalkan. Stok global dikembalikan satu kali.'
                    : 'Pesanan sudah dibatalkan sebelumnya. Stok TIDAK dikembalikan lagi.'
            );
        } catch (ValidationError | DomainRejected $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/admin/pesanan/' . $order['order_id']);
    }

    // ---------------- Stok global (read-only) ----------------

    public function stock(): void
    {
        AdminAuth::require();
        $q = lower($this->query('q'));
        $category = $this->query('kategori');
        if (!array_key_exists($category, MasterCatalog::CATEGORIES)) {
            $category = '';
        }
        $products = array_values(array_filter($this->data()->listProducts(), static function ($p) use ($q, $category) {
            if ($category !== '' && $p['category'] !== $category) {
                return false;
            }
            return $q === '' || str_contains(lower($p['product_id'] . ' ' . $p['name']), $q);
        }));
        $stocks = $products ? $this->data()->getStockMany(array_column($products, 'product_id')) : [];
        $this->renderAdmin('admin/stock', [
            'title'      => 'Stok Global',
            'products'   => $products,
            'stocks'     => $stocks,
            'q'          => $this->query('q'),
            'category'   => $category,
            'fetchedAt'  => time(),
        ]);
    }

    // ---------------- Rekap penjualan ----------------

    public function sales(): void
    {
        $user = AdminAuth::require();
        $tz = new \DateTimeZone('Asia/Jakarta');
        try {
            $from = Validator::date($this->query('dari')) ?? (new \DateTimeImmutable('first day of this month', $tz))->format('Y-m-d');
            $to = Validator::date($this->query('sampai')) ?? (new \DateTimeImmutable('today', $tz))->format('Y-m-d');
        } catch (ValidationError $e) {
            Session::flash('error', $e->getMessage());
            View::redirect('/admin/rekap');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $fromTs = (new \DateTimeImmutable($from . ' 00:00:00', $tz))->getTimestamp();
        $toTs = (new \DateTimeImmutable($to . ' 00:00:00', $tz))->modify('+1 day')->getTimestamp();
        $summary = $this->data()->getSalesSummary($user['branch_id'], $fromTs, $toTs);
        $this->renderAdmin('admin/sales', [
            'title'   => 'Rekap Penjualan',
            'summary' => $summary,
            'from'    => $from,
            'to'      => $to,
        ]);
    }
}
