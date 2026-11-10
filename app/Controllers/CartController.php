<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Data\DomainRejected;
use App\Domain\Validator;
use App\Domain\ValidationError;
use App\Http\Csrf;
use App\Http\Session;
use App\Http\View;

/** Keranjang: lihat, tambah, ubah jumlah, hapus. Tidak menyentuh stok. */
final class CartController extends Controller
{
    public function show(): void
    {
        $cart = $this->cart()->detailed(Session::cartSessionId());
        $this->render('shop/cart', ['title' => 'Keranjang', 'cart' => $cart]);
    }

    public function add(): void
    {
        Csrf::verify();
        $productId = '';
        try {
            $productId = Validator::productId($this->post('product_id'));
            $size = strtolower(trim($this->post('size')));
            $color = strtolower(trim($this->post('color')));
            if ($size === '' || $color === '') {
                throw new ValidationError('Pilih ukuran dan warna terlebih dahulu.');
            }
            $variant = Validator::variant($size . '_' . $color);
            $qty = Validator::quantity($this->post('qty'));
            $this->cart()->add(Session::cartSessionId(), $productId, $variant, $qty);
            Session::flash('success', "Ditambahkan ke keranjang ({$qty}).");
            // Dari jendela "Lihat cepat" di beranda: kembali ke beranda dengan keranjang mini terbuka.
            // Hanya nilai tetap 'beranda' yang diterima, jadi tidak bisa dipakai untuk redirect ke situs lain.
            if ($this->post('kembali') === 'beranda') {
                View::redirect('/?keranjang=baru');
            }
            View::redirect(url('/produk/' . $productId, ['ukuran' => $size, 'warna' => $color]));
        } catch (ValidationError | DomainRejected $e) {
            Session::flash('error', $e->getMessage());
            View::redirect($productId !== '' ? '/produk/' . $productId : '/katalog');
        }
    }

    public function update(): void
    {
        Csrf::verify();
        try {
            $productId = Validator::productId($this->post('product_id'));
            $variant = Validator::variant($this->post('variant'));
            $qty = Validator::quantity($this->post('qty'));
            $this->cart()->update(Session::cartSessionId(), $productId, $variant, $qty);
            Session::flash('success', 'Jumlah diperbarui.');
        } catch (ValidationError | DomainRejected $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/keranjang');
    }

    public function remove(): void
    {
        Csrf::verify();
        try {
            $productId = Validator::productId($this->post('product_id'));
            $variant = Validator::variant($this->post('variant'));
            $this->cart()->remove(Session::cartSessionId(), $productId, $variant);
            Session::flash('success', 'Item dihapus dari keranjang.');
        } catch (ValidationError $e) {
            Session::flash('error', $e->getMessage());
        }
        View::redirect('/keranjang');
    }
}
