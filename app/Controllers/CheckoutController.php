<?php

declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Data\DataServiceUnavailable;
use App\Data\DomainRejected;
use App\Domain\CheckoutService;
use App\Domain\ValidationError;
use App\Domain\Validator;
use App\Http\Csrf;
use App\Http\HttpException;
use App\Http\Session;
use App\Http\View;

/**
 * Alur checkout:
 *   GET  /checkout              form penerima + metode
 *   POST /checkout/tinjau       validasi form, buat request_id, simpan draf -> redirect
 *   GET  /checkout/tinjau       halaman "Konfirmasi pembayaran simulasi"
 *   POST /checkout/konfirmasi   panggil checkout layanan data (stok dipotong di sini)
 *   POST /checkout/periksa      cari hasil request_id jika hasil sebelumnya belum diketahui
 *   POST /checkout/batal-bayar  batalkan pembayaran simulasi (stok tidak berubah)
 */
final class CheckoutController extends Controller
{
    private function service(): CheckoutService
    {
        return new CheckoutService((array) App::config('shipping'));
    }

    private function access(): array
    {
        return ['type' => 'customer', 'branch_id' => $this->branchId(), 'session_hash' => Session::ownerHash()];
    }

    public function show(): void
    {
        // Jika ada checkout yang hasilnya belum diketahui, cari hasilnya dulu sebelum checkout baru.
        if (!empty($_SESSION['checkout_pending'])) {
            $this->resolvePending(false);
        }

        $cart = $this->cart()->detailed(Session::cartSessionId());
        if (!$cart['lines']) {
            Session::flash('info', 'Keranjang masih kosong.');
            View::redirect('/keranjang');
        }
        if ($cart['has_problem']) {
            Session::flash('error', 'Ada item yang stoknya tidak cukup atau tidak tersedia. Perbaiki keranjang terlebih dahulu.');
            View::redirect('/keranjang');
        }
        $old = Session::takeOld() ?: ($_SESSION['checkout_draft']['input'] ?? []);
        $errors = is_array($_SESSION['_field_errors'] ?? null) ? $_SESSION['_field_errors'] : [];
        unset($_SESSION['_field_errors']); // tampil sekali saja, seperti pesan flash
        $svc = $this->service();
        $this->render('shop/checkout', [
            'title'  => 'Checkout',
            'cart'   => $cart,
            'old'    => $old,
            'errors' => $errors,
            'quotes' => ['reguler' => $svc->quote($cart, 'reguler'), 'pickup' => $svc->quote($cart, 'pickup')],
            'branch' => App::config('branch'),
        ]);
    }

    public function review(): void
    {
        Csrf::verify();
        $input = [
            'name' => $this->post('name'), 'phone' => $this->post('phone'),
            'shipping_method' => $this->post('shipping_method'), 'address' => $this->post('address'),
            'notes' => $this->post('notes'),
        ];
        try {
            $form = $this->service()->validateForm($input);
        } catch (ValidationError $e) {
            Session::setOld($input);
            $errors = $this->service()->fieldErrors($input) ?: ['form' => $e->getMessage()];
            $_SESSION['_field_errors'] = $errors;
            Session::flash('error', count($errors) > 1
                ? 'Ada ' . count($errors) . ' kolom yang perlu diperbaiki. Lihat pesan di bawah kolomnya.'
                : 'Periksa kembali: ' . reset($errors));
            View::redirect('/checkout');
        }
        $raw = $this->data()->getCart(Session::cartSessionId(), $this->branchId());
        if (!$raw) {
            Session::flash('info', 'Keranjang masih kosong.');
            View::redirect('/keranjang');
        }
        $_SESSION['checkout_draft'] = [
            'request_id'  => bin2hex(random_bytes(16)), // kunci idempotensi
            'form'        => $form,
            'input'       => $input,
            'fingerprint' => CheckoutService::cartFingerprint($raw),
            'created_at'  => time(),
        ];
        View::redirect('/checkout/tinjau');
    }

    public function reviewPage(): void
    {
        $draft = $_SESSION['checkout_draft'] ?? null;
        if (!$draft) {
            View::redirect('/checkout');
        }
        $cart = $this->cart()->detailed(Session::cartSessionId());
        if (!$cart['lines']) {
            unset($_SESSION['checkout_draft']);
            View::redirect('/keranjang');
        }
        $raw = array_map(static fn ($l) => ['product_id' => $l['product_id'], 'variant' => $l['variant'], 'qty' => $l['qty']], $cart['lines']);
        if (CheckoutService::cartFingerprint($raw) !== $draft['fingerprint']) {
            unset($_SESSION['checkout_draft']);
            Session::flash('info', 'Keranjang berubah. Silakan isi ulang checkout.');
            View::redirect('/checkout');
        }
        $this->render('shop/review', [
            'title'   => 'Konfirmasi Pembayaran Simulasi',
            'cart'    => $cart,
            'draft'   => $draft,
            'quote'   => $this->service()->quote($cart, $draft['form']['method']),
            'branch'  => App::config('branch'),
            'pending' => !empty($_SESSION['checkout_pending']),
        ]);
    }

    public function confirm(): void
    {
        Csrf::verify();
        try {
            $requestId = Validator::requestId($this->post('request_id'));
        } catch (ValidationError) {
            throw new HttpException(400, 'Permintaan checkout tidak valid.');
        }
        $draft = $_SESSION['checkout_draft'] ?? null;

        // Klik ganda / kirim ulang setelah sukses: draf sudah dihapus -> tampilkan pesanan yang sama.
        if (!$draft || $draft['request_id'] !== $requestId) {
            $existing = $this->data()->getCheckoutResult($requestId, $this->access());
            if ($existing !== null) {
                View::redirect('/pesanan/' . $existing['order']['order_id'] . '?baru=1');
            }
            Session::flash('info', 'Sesi konfirmasi sudah tidak berlaku. Silakan ulangi checkout.');
            View::redirect('/checkout');
        }

        $raw = $this->data()->getCart(Session::cartSessionId(), $this->branchId());
        if (CheckoutService::cartFingerprint($raw) !== $draft['fingerprint']) {
            unset($_SESSION['checkout_draft']);
            Session::flash('info', 'Keranjang berubah sebelum dikonfirmasi. Silakan tinjau ulang.');
            View::redirect('/checkout');
        }

        $request = $this->service()->buildRequest(
            $requestId,
            $this->branchId(),
            Session::ownerHash(),
            $draft['form'],
            (string) App::config('branch.pickup_location'),
            $raw
        );

        try {
            $result = $this->data()->checkout($request);
        } catch (DomainRejected $e) {
            unset($_SESSION['checkout_draft']);
            Session::flash('error', $this->rejectionMessage($e));
            View::redirect($e->errorCode === 'IDEMPOTENCY_CONFLICT' ? '/checkout' : '/keranjang');
        } catch (DataServiceUnavailable $e) {
            // Hasil belum diketahui: JANGAN anggap gagal atau berhasil. Simpan request_id untuk diperiksa.
            $_SESSION['checkout_pending'] = ['request_id' => $requestId, 'items' => $raw, 'at' => time()];
            $this->render('shop/checkout-unknown', [
                'title'     => 'Hasil Checkout Belum Diketahui',
                'requestId' => $requestId,
                'detail'    => $e->getMessage(),
            ], 503);
            return;
        }

        $this->finish($result['order'], $raw);
    }

    /** Periksa hasil checkout yang sebelumnya belum diketahui. */
    public function verify(): void
    {
        Csrf::verify();
        if (empty($_SESSION['checkout_pending'])) {
            View::redirect('/checkout');
        }
        $this->resolvePending(true);
    }

    private function resolvePending(bool $explicit): void
    {
        $pending = $_SESSION['checkout_pending'];
        $found = $this->data()->getCheckoutResult((string) $pending['request_id'], $this->access());
        if ($found !== null) {
            $this->finish($found['order'], (array) $pending['items']);
        }
        unset($_SESSION['checkout_pending']);
        Session::flash('info', 'Pesanan dari percobaan sebelumnya belum tercatat. '
            . 'Kamu bisa menekan konfirmasi lagi tanpa membuat pesanan ganda.');
        if ($explicit) {
            View::redirect(!empty($_SESSION['checkout_draft']) ? '/checkout/tinjau' : '/checkout');
        }
    }

    public function cancelPayment(): void
    {
        Csrf::verify();
        unset($_SESSION['checkout_draft']);
        Session::flash('info', 'Pembayaran dibatalkan. Pesanan tidak dibuat.');
        View::redirect('/keranjang');
    }

    /** Sukses: bersihkan item yang dibeli dari keranjang, lalu tampilkan pesanan. */
    private function finish(array $order, array $purchased): never
    {
        unset($_SESSION['checkout_draft'], $_SESSION['checkout_pending']);
        try {
            foreach ($purchased as $line) {
                $this->data()->removeCartItem(Session::cartSessionId(), $this->branchId(), $line['product_id'], $line['variant']);
            }
        } catch (DataServiceUnavailable) {
            Session::flash('info', 'Pesanan berhasil, tetapi keranjang belum sempat dibersihkan. Hapus item secara manual bila masih terlihat.');
        }
        View::redirect('/pesanan/' . $order['order_id'] . '?baru=1');
    }

    private function rejectionMessage(DomainRejected $e): string
    {
        if ($e->errorCode !== 'INSUFFICIENT_STOCK') {
            return $e->getMessage();
        }
        $parts = [];
        foreach ((array) ($e->details['problems'] ?? []) as $p) {
            if (($p['code'] ?? '') !== 'INSUFFICIENT_STOCK') {
                continue;
            }
            $product = $this->data()->getProduct((string) $p['product_id']);
            $parts[] = sprintf('%s (%s): tersedia %d, diminta %d', $product['name'] ?? $p['product_id'], $p['variant'], $p['available'], $p['requested']);
        }
        return 'Checkout gagal: stok bersama tidak mencukupi. ' . implode('; ', $parts) . '. Keranjang tetap tersimpan, silakan sesuaikan.';
    }
}
