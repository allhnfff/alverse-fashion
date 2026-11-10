<?php

declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Data\DataServiceUnavailable;
use App\Domain\MasterCatalog;
use App\Domain\Validator;
use App\Domain\ValidationError;
use App\Http\HttpException;

/** Halaman pelanggan: beranda, katalog, detail produk, informasi cabang. */
final class ShopController extends Controller
{
    /** Pilihan urutan katalog. Hanya mengubah urutan tampilan, bukan data. */
    public const SORTS = [
        ''            => 'Urutan toko',
        'harga-naik'  => 'Harga termurah',
        'harga-turun' => 'Harga termahal',
        'nama'        => 'Nama A–Z',
    ];

    /** Jumlah kartu produk yang langsung tampil di beranda; sisanya lewat tombol "Lihat … lainnya". */
    public const HOME_VISIBLE = 18;

    /** Kartu katalog yang langsung tampil (4 baris × 5 kolom di desktop); sisanya lewat "Tampilkan lainnya". */
    public const CATALOG_VISIBLE = 20;

    public function home(): void
    {
        $products = array_values(array_filter($this->data()->listProducts(), static fn ($p) => $p['active']));
        usort($products, static fn ($a, $b) => strcmp((string) $a['product_id'], (string) $b['product_id']));
        $counts = array_count_values(array_column($products, 'category'));

        // Grid beranda: produk diselang-seling per kategori supaya keenam kategori terlihat sejak baris pertama.
        $groups = [];
        foreach (array_keys(MasterCatalog::CATEGORIES) as $key) {
            $groups[] = array_values(array_filter($products, static fn ($p) => $p['category'] === $key));
        }
        $mixed = [];
        for ($i = 0, $n = $groups ? max(array_map('count', $groups)) : 0; $i < $n; $i++) {
            foreach ($groups as $g) {
                if (isset($g[$i])) {
                    $mixed[] = $g[$i];
                }
            }
        }

        // Produk termurah di Busana Muslim untuk banner ketiga (diambil dari data, bukan ditulis tangan).
        $muslim = array_values(array_filter($products, static fn ($p) => $p['category'] === 'busana-muslim'));
        usort($muslim, static fn ($a, $b) => $a['price'] <=> $b['price']);

        // Banner samping kedua: jumlah model dan harga termurah Sepatu & Sandal, dihitung dari data.
        $shoePrices = array_column(array_filter($products, static fn ($p) => $p['category'] === 'sepatu-sandal'), 'price');

        // Data untuk jendela "Lihat cepat": hanya data master yang sudah tampil di halaman.
        $quick = array_map(static fn (array $p): array => [
            'id'        => $p['product_id'],
            'name'      => $p['name'],
            'cat'       => $p['category_label'],
            'price'     => (int) $p['price'],
            'sizeLabel' => size_text($p['size_label']),
            'img'       => product_image($p),
            'sizes'     => array_map(static fn ($s) => ['code' => $s['code'], 'label' => size_text($s['label'])], $p['sizes']),
            'colors'    => array_map(static fn ($c) => ['code' => $c['code'], 'label' => $c['label'], 'img' => product_image($p, $c['code'])], $p['colors']),
        ], $products);

        $this->render('shop/home', [
            'title'          => 'Beranda',
            'products'       => $mixed,
            'quick'          => $quick,
            'muslimCheapest' => $muslim[0] ?? null,
            'shoeSide'       => $shoePrices ? ['count' => count($shoePrices), 'min' => (int) min($shoePrices)] : null,
            'categoryCounts' => $counts,
            'totalProducts'  => count($products),
            'cartJustAdded'  => $this->query('keranjang') === 'baru',
        ]);
    }

    public function catalog(): void
    {
        $q = $this->query('q');
        $category = $this->query('kategori');
        if (!array_key_exists($category, MasterCatalog::CATEGORIES)) {
            $category = '';
        }
        $sort = $this->query('urut');
        if (!array_key_exists($sort, self::SORTS)) {
            $sort = '';
        }
        $products = array_filter($this->data()->listProducts(), static fn ($p) => $p['active']);
        $needle = lower($q);
        $matchesQuery = static function (array $p) use ($needle): bool {
            if ($needle === '') {
                return true;
            }
            $hay = lower($p['product_id'] . ' ' . $p['name'] . ' ' . $p['category_label'] . ' ' . $p['color_label']);
            return str_contains($hay, $needle);
        };
        // Filter harga, ukuran, warna (hanya membaca data master; tidak ada yang disimpan).
        $price = static function (string $v): ?int {
            $digits = preg_replace('/\D+/', '', $v) ?? '';
            return $digits === '' ? null : min((int) substr($digits, 0, 9), 99_999_999);
        };
        $minPrice = $price($this->query('harga_min'));
        $maxPrice = $price($this->query('harga_max'));
        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
        }
        $pickSizes = $this->queryList('ukuran');
        $pickColors = $this->queryList('warna');
        $inPrice = static fn (array $p): bool => ($minPrice === null || $p['price'] >= $minPrice) && ($maxPrice === null || $p['price'] <= $maxPrice);

        // Hitungan kategori mengikuti kata kunci + harga. Ukuran/warna berlaku di dalam kategori yang dibuka.
        $matching = array_filter($products, static fn (array $p): bool => $matchesQuery($p) && $inPrice($p));
        $base = array_values(array_filter($matching, static fn (array $p): bool => $category === '' || $p['category'] === $category));

        $sizeOptions = [];
        $colorOptions = [];
        foreach ($base as $p) {
            foreach ($p['sizes'] as $s) {
                if ($s['code'] !== 'one') {
                    $sizeOptions[$s['code']] ??= size_text((string) $s['label']);
                }
            }
            foreach ($p['colors'] as $c) {
                $colorOptions[$c['code']] ??= (string) $c['label'];
            }
        }
        asort($colorOptions, SORT_NATURAL | SORT_FLAG_CASE);
        // Ukuran baru bisa difilter setelah kategori dipilih (S dan 38 tidak sebanding).
        $pickSizes = $category === '' ? [] : array_values(array_intersect($pickSizes, array_keys($sizeOptions)));
        $pickColors = array_values(array_intersect($pickColors, array_keys($colorOptions)));

        $result = array_values(array_filter($base, static function (array $p) use ($pickSizes, $pickColors): bool {
            if ($pickSizes && !array_intersect($pickSizes, array_column($p['sizes'], 'code'))) {
                return false;
            }
            return !$pickColors || (bool) array_intersect($pickColors, array_column($p['colors'], 'code'));
        }));
        self::sortProducts($result, $sort);

        $this->render('shop/catalog', [
            'title'          => $category !== '' ? MasterCatalog::categoryLabel($category) : 'Katalog',
            'products'       => $result,
            'q'              => $q,
            'category'       => $category,
            'sort'           => $sort,
            'sorts'          => self::SORTS,
            'categoryCounts' => array_count_values(array_column($matching, 'category')),
            'matchingTotal'  => count($matching),
            'empty'          => $products === [],
            'filters'        => ['min' => $minPrice, 'max' => $maxPrice, 'sizes' => $pickSizes, 'colors' => $pickColors],
            'sizeOptions'    => $sizeOptions,
            'colorOptions'   => $colorOptions,
        ]);
    }

    /** @param list<array<string,mixed>> $products */
    private static function sortProducts(array &$products, string $sort): void
    {
        $byId = static fn (array $a, array $b): int => strcmp((string) $a['product_id'], (string) $b['product_id']);
        match ($sort) {
            'harga-naik'  => usort($products, static fn ($a, $b) => [$a['price'], $a['product_id']] <=> [$b['price'], $b['product_id']]),
            'harga-turun' => usort($products, static fn ($a, $b) => [$b['price'], $a['product_id']] <=> [$a['price'], $b['product_id']]),
            'nama'        => usort($products, static fn ($a, $b) => strcmp(lower((string) $a['name']), lower((string) $b['name'])) ?: $byId($a, $b)),
            default       => usort($products, $byId),
        };
    }

    public function product(string $id): void
    {
        try {
            $id = Validator::productId($id);
        } catch (ValidationError) {
            throw new HttpException(404, 'Produk tidak ditemukan.');
        }
        $product = $this->data()->getProduct($id);
        if ($product === null || !$product['active']) {
            throw new HttpException(404, 'Produk tidak ditemukan.');
        }

        // Stok global terbaru dibaca saat halaman dibuka. Jika gagal, halaman tetap tampil
        // dengan pesan "Tidak dapat memperbarui stok" (bukan "Stok 0").
        $stock = null;
        $stockError = null;
        try {
            $stock = $this->data()->getStock($id);
        } catch (DataServiceUnavailable $e) {
            $stockError = 'Tidak dapat memperbarui stok';
        }

        // Varian terpilih: dari URL (?ukuran=&warna=) atau varian pertama yang masih ada stok.
        $size = strtolower($this->query('ukuran'));
        $color = strtolower($this->query('warna'));
        $codes = array_column($product['variants'], 'code');
        if (!in_array($size . '_' . $color, $codes, true)) {
            $size = $product['sizes'][0]['code'] ?? '';
            $color = $product['colors'][0]['code'] ?? '';
            foreach ($product['variants'] as $v) {
                if (($stock['variants'][$v['code']] ?? 0) > 0) {
                    $size = $v['size'];
                    $color = $v['color'];
                    break;
                }
            }
        }

        // Produk lain di kategori yang sama (hanya untuk tampilan "lainnya").
        $related = [];
        foreach ($this->data()->listProducts() as $p) {
            if ($p['active'] && $p['category'] === $product['category'] && $p['product_id'] !== $product['product_id']) {
                $related[] = $p;
            }
        }

        $this->render('shop/product', [
            'title'      => $product['name'],
            'product'    => $product,
            'related'    => array_slice($related, 0, 6),
            'stock'      => $stock,
            'stockError' => $stockError,
            'selSize'    => $size,
            'selColor'   => $color,
            'pollMs'     => (int) App::config('stock_poll_ms'),
            'bodyClass'  => 'page-product', // di ponsel menu bawah diganti bar tombol beli
        ]);
    }

    public function branch(): void
    {
        $branches = App::config('branches');
        $urls = App::config('branch_urls');
        $all = [];
        foreach ($branches as $id => $b) {
            if (!empty($b['test_only'])) {
                continue;
            }
            $all[] = [
                'id'        => $id,
                'name'      => $b['name'],
                'developer' => $b['developer'],
                'prefix'    => $b['prefix'],
                'url'       => (string) ($urls[$id] ?? ''),
                'current'   => $id === $this->branchId(),
            ];
        }
        $this->render('shop/branch', [
            'title'    => 'Informasi Cabang',
            'branch'   => App::config('branch'),
            'branches' => $all,
            'prefix'   => (string) ($branches[$this->branchId()]['prefix'] ?? ''),
        ]);
    }
}
