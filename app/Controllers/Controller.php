<?php

declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Data\CentralDataService;
use App\Data\DataServiceUnavailable;
use App\Domain\AdminAuth;
use App\Domain\CartService;
use App\Domain\MasterCatalog;
use App\Http\Session;
use App\Http\View;

/** Fungsi bantu bersama untuk semua controller. */
abstract class Controller
{
    protected function data(): CentralDataService
    {
        return App::data();
    }

    protected function branchId(): string
    {
        return (string) App::config('branch.id');
    }

    protected function cart(): CartService
    {
        return new CartService($this->data(), $this->branchId(), (int) App::config('cart.max_qty_per_line'));
    }

    /** Jumlah item keranjang untuk header; null jika layanan data sedang gangguan. */
    protected function cartCount(): ?int
    {
        try {
            return $this->cart()->count(Session::cartSessionId());
        } catch (DataServiceUnavailable) {
            return null;
        }
    }

    /** Render halaman pelanggan. */
    protected function render(string $view, array $vars = [], int $status = 200): void
    {
        $common = [
            'flash'      => Session::takeFlash(),
            'source'     => $this->data()->describe(),
            'cartCount'  => $this->cartCount(),
            'miniCart'   => $this->miniCart(),
            'megaMenu'   => $this->megaMenu(),
            'categories' => MasterCatalog::CATEGORIES,
            'searchQ'    => is_string($_GET['q'] ?? null) ? $_GET['q'] : '',
        ];
        View::render($view, $vars + $common, 'layout', $status);
    }

    /** Isi keranjang untuk panel keranjang mini di header; null jika layanan data gangguan. */
    protected function miniCart(): ?array
    {
        try {
            return $this->cart()->detailed(Session::cartSessionId());
        } catch (DataServiceUnavailable) {
            return null;
        }
    }

    /**
     * Data menu Kategori di header: produk per kategori beserta ringkasannya.
     * Hanya membaca data master; null jika layanan data gangguan.
     * @return array<string,array<string,mixed>>|null
     */
    protected function megaMenu(): ?array
    {
        try {
            $products = array_filter($this->data()->listProducts(), static fn ($p) => $p['active']);
        } catch (DataServiceUnavailable) {
            return null;
        }
        $menu = [];
        foreach (MasterCatalog::CATEGORIES as $key => $label) {
            $items = array_values(array_filter($products, static fn ($p) => $p['category'] === $key));
            if (!$items) {
                continue;
            }
            usort($items, static fn ($a, $b) => strcmp((string) $a['product_id'], (string) $b['product_id']));
            $prices = array_column($items, 'price');
            $promo = $items[0];
            foreach ($items as $p) {
                if (count($p['colors']) > count($promo['colors'])) {
                    $promo = $p;
                }
            }
            $menu[$key] = [
                'label' => $label,
                'items' => $items,
                'min'   => (int) min($prices),
                'max'   => (int) max($prices),
                'sizes' => implode(', ', array_slice(array_values(array_unique(array_map('size_text', array_column($items, 'size_label')))), 0, 3)),
                'promo' => $promo,
            ];
        }
        return $menu;
    }

    /** Render halaman admin. */
    protected function renderAdmin(string $view, array $vars = [], int $status = 200): void
    {
        $common = [
            'flash'  => Session::takeFlash(),
            'source' => $this->data()->describe(),
            'admin'  => AdminAuth::user(),
        ];
        View::render($view, $vars + $common, 'admin/layout', $status);
    }

    protected function post(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    protected function query(string $key): string
    {
        $v = $_GET[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    /** Daftar kode dari query (?k[]=a&k[]=b atau ?k=a,b). Hanya huruf kecil, angka, dan '-'; maksimal 20. */
    protected function queryList(string $key): array
    {
        $v = $_GET[$key] ?? [];
        $list = is_array($v) ? $v : explode(',', (string) $v);
        $out = [];
        foreach ($list as $item) {
            if (is_string($item) && preg_match('/^[a-z0-9-]{1,20}$/', $item = trim($item))) {
                $out[$item] = true;
            }
        }
        return array_slice(array_keys($out), 0, 20);
    }
}
