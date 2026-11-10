<?php

declare(strict_types=1);

namespace App\Data;

use App\Domain\OrderRules;
use Predis\Client;
use Predis\ClientInterface;
use Predis\PredisException;
use Predis\Response\ServerException;

/**
 * ADAPTER REDIS LOKAL (untuk latihan / pengembangan).
 *
 * Mengikuti key kontrak v2:
 *   brand:catalog:product:{product_id}  Hash   master produk
 *   brand:catalog:products              Set    indeks master
 *   inventory:stock:{product_id}        Hash   STOK GLOBAL, field = kode varian (adjustable_hitam)
 *   cart:{session_id}:{branch_id}       Hash   field "P037:adjustable_hitam" -> qty
 *   order:{order_id}                    Hash   header pesanan
 *   order:{order_id}:items              List   JSON item (snapshot harga)
 *   branch:{branch_id}:orders           ZSET   indeks asal pesanan (score = waktu dibuat)
 * Tambahan lokal (perlu disepakati dengan Kelompok 6):
 *   order:{order_id}:history            List   riwayat status
 *   customer:{session_hash}:orders      ZSET   pesanan milik sesi pelanggan
 *   branch:{branch_id}:completed        ZSET   pesanan selesai (score = waktu selesai) untuk rekap
 *   checkout:request:{request_id}       String hasil request_id (idempotensi)
 *   inventory:movements                 Stream log pergerakan stok
 *   alverse:meta:dataset                String penanda dataset ("demo-local" / "test")
 *
 * TIDAK ADA stok per branch_id.
 */
final class RedisLocalAdapter implements CentralDataService
{
    private const REQUEST_TTL = 60 * 60 * 24 * 30;
    private const CART_TTL = 60 * 60 * 24 * 30;
    private const ID_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** @var array<string,array<string,mixed>>|null */
    private ?array $productCache = null;

    /**
     * @param array<string,int> $shippingRates ['reguler' => 10000, 'pickup' => 0]
     * @param array<string,array<string,mixed>> $branches config/branches.php
     */
    public function __construct(
        private readonly ClientInterface $redis,
        private readonly array $shippingRates,
        private readonly array $branches,
        private readonly string $label = 'Redis lokal'
    ) {
    }

    /** Membuat koneksi Predis dari konfigurasi .env. Koneksi baru dibuka saat perintah pertama. */
    public static function createClient(array $cfg, ?int $database = null): Client
    {
        $params = [
            'scheme'             => 'tcp',
            'host'               => (string) $cfg['host'],
            'port'               => (int) $cfg['port'],
            'database'           => $database ?? (int) $cfg['database'],
            'timeout'            => (float) $cfg['timeout'],
            'read_write_timeout' => 5,
        ];
        if (($cfg['username'] ?? '') !== '') {
            $params['username'] = (string) $cfg['username'];
        }
        if (($cfg['password'] ?? '') !== '') {
            $params['password'] = (string) $cfg['password'];
        }
        return new Client($params);
    }

    public function client(): ClientInterface
    {
        return $this->redis;
    }

    // ------------------------------------------------------------------
    // Penanganan error koneksi: tidak pernah menghasilkan "sukses palsu"
    // ------------------------------------------------------------------

    /**
     * @template T
     * @param callable(ClientInterface):T $fn
     * @return T
     */
    private function run(callable $fn, bool $isWrite = false): mixed
    {
        try {
            return $fn($this->redis);
        } catch (ServerException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'NOAUTH') || str_contains($msg, 'WRONGPASS') || str_contains($msg, 'invalid password')) {
                throw new DataServiceUnavailable('Autentikasi Redis gagal. Periksa REDIS_USERNAME/REDIS_PASSWORD di .env.', false, $e);
            }
            if (str_contains($msg, 'LOADING')) {
                throw new DataServiceUnavailable('Redis sedang memuat data dari disk. Coba lagi beberapa detik lagi.', false, $e);
            }
            throw new DataServiceUnavailable('Redis menolak perintah: ' . $msg, false, $e);
        } catch (PredisException $e) {
            // ConnectionException, timeout, koneksi putus, dsb.
            throw new DataServiceUnavailable(
                'Redis tidak dapat dihubungi (' . $e->getMessage() . '). Pastikan Redis server berjalan.',
                $isWrite,
                $e
            );
        }
    }

    // ------------------------------------------------------------------
    // Info & kesehatan
    // ------------------------------------------------------------------

    public function describe(): array
    {
        return ['driver' => 'redis', 'label' => $this->label, 'dataset' => 'demo-local'];
    }

    public function health(): array
    {
        try {
            $res = $this->run(static function (ClientInterface $r): array {
                $pong = (string) $r->ping();
                $dataset = $r->get('alverse:meta:dataset');
                return [$pong, $dataset];
            });
            return [
                'ok'      => true,
                'driver'  => 'redis',
                'detail'  => 'PING -> ' . $res[0],
                'dataset' => $res[1] ?? '(belum di-seed)',
            ];
        } catch (DataServiceUnavailable $e) {
            return ['ok' => false, 'driver' => 'redis', 'detail' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Katalog
    // ------------------------------------------------------------------

    public function listProducts(): array
    {
        return array_values($this->loadProducts());
    }

    public function getProduct(string $productId): ?array
    {
        $all = $this->loadProducts();
        return $all[$productId] ?? null;
    }

    /** @return array<string,array<string,mixed>> */
    private function loadProducts(): array
    {
        if ($this->productCache !== null) {
            return $this->productCache;
        }
        $rows = $this->run(static function (ClientInterface $r): array {
            $ids = $r->smembers('brand:catalog:products');
            sort($ids);
            if (!$ids) {
                return [];
            }
            $hashes = $r->pipeline(static function ($pipe) use ($ids): void {
                foreach ($ids as $id) {
                    $pipe->hgetall('brand:catalog:product:' . $id);
                }
            });
            return array_combine($ids, $hashes);
        });
        $products = [];
        foreach ($rows as $id => $hash) {
            $p = self::decodeProduct((array) $hash);
            if ($p !== null) {
                $products[$id] = $p;
            }
        }
        return $this->productCache = $products;
    }

    /** @param array<string,string> $h */
    public static function decodeProduct(array $h): ?array
    {
        if (!$h || empty($h['product_id'])) {
            return null;
        }
        $sizes = json_decode($h['sizes'] ?? '[]', true) ?: [];
        $colors = json_decode($h['colors'] ?? '[]', true) ?: [];
        $variants = [];
        foreach ($sizes as $s) {
            foreach ($colors as $c) {
                $variants[] = [
                    'code'        => $s['code'] . '_' . $c['code'],
                    'size'        => $s['code'],
                    'size_label'  => $s['label'],
                    'color'       => $c['code'],
                    'color_label' => $c['label'],
                ];
            }
        }
        return [
            'product_id'     => $h['product_id'],
            'name'           => $h['name'] ?? '',
            'category'       => $h['category'] ?? '',
            'category_label' => $h['category_label'] ?? '',
            'price'          => (int) ($h['price'] ?? 0),
            'size_label'     => $h['size_label'] ?? '',
            'color_label'    => $h['color_label'] ?? '',
            'sizes'          => $sizes,
            'colors'         => $colors,
            'variants'       => $variants,
            'description'    => $h['description'] ?? '',
            'image_url'      => $h['image_url'] ?? '',
            'image_is_demo'  => ($h['image_is_demo'] ?? '0') === '1',
            'active'         => ($h['active'] ?? '0') === '1',
        ];
    }

    // ------------------------------------------------------------------
    // Stok global
    // ------------------------------------------------------------------

    public function getStock(string $productId): array
    {
        $raw = $this->run(static fn (ClientInterface $r) => $r->hgetall('inventory:stock:' . $productId));
        return [
            'product_id' => $productId,
            'variants'   => array_map('intval', (array) $raw),
            'fetched_at' => time(),
        ];
    }

    public function getStockMany(array $productIds): array
    {
        $productIds = array_values(array_unique($productIds));
        if (!$productIds) {
            return [];
        }
        $rows = $this->run(static fn (ClientInterface $r) => $r->pipeline(static function ($pipe) use ($productIds): void {
            foreach ($productIds as $id) {
                $pipe->hgetall('inventory:stock:' . $id);
            }
        }));
        $out = [];
        foreach ($productIds as $i => $id) {
            $out[$id] = array_map('intval', (array) ($rows[$i] ?? []));
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Keranjang (tidak memesan / mengurangi stok)
    // ------------------------------------------------------------------

    private function cartKey(string $sessionId, string $branchId): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $sessionId)) {
            throw new \InvalidArgumentException('session_id keranjang tidak valid.');
        }
        if (!isset($this->branches[$branchId])) {
            throw new \InvalidArgumentException('branch_id tidak dikenal.');
        }
        return "cart:{$sessionId}:{$branchId}";
    }

    public function getCart(string $sessionId, string $branchId): array
    {
        $key = $this->cartKey($sessionId, $branchId);
        $raw = $this->run(static fn (ClientInterface $r) => $r->hgetall($key));
        $items = [];
        foreach ((array) $raw as $field => $qty) {
            $parts = explode(':', (string) $field, 2);
            if (count($parts) !== 2 || (int) $qty < 1) {
                continue;
            }
            $items[] = ['product_id' => $parts[0], 'variant' => $parts[1], 'qty' => (int) $qty];
        }
        return $items;
    }

    public function setCartItem(string $sessionId, string $branchId, string $productId, string $variant, int $qty): void
    {
        $key = $this->cartKey($sessionId, $branchId);
        if ($qty < 1 || $qty > 99) {
            throw new DomainRejected('INVALID_QTY');
        }
        $product = $this->getProduct($productId);
        if ($product === null || !$product['active']) {
            throw new DomainRejected('PRODUCT_UNAVAILABLE');
        }
        $exists = $this->run(static fn (ClientInterface $r) => (bool) $r->hexists('inventory:stock:' . $productId, $variant));
        if (!$exists) {
            throw new DomainRejected('VARIANT_NOT_FOUND');
        }
        $this->run(static function (ClientInterface $r) use ($key, $productId, $variant, $qty): void {
            $r->hset($key, $productId . ':' . $variant, (string) $qty);
            $r->expire($key, self::CART_TTL);
        }, true);
    }

    public function removeCartItem(string $sessionId, string $branchId, string $productId, string $variant): void
    {
        $key = $this->cartKey($sessionId, $branchId);
        $this->run(static fn (ClientInterface $r) => $r->hdel($key, [$productId . ':' . $variant]), true);
    }

    // ------------------------------------------------------------------
    // Checkout atomik + idempoten
    // ------------------------------------------------------------------

    public function checkout(array $request): array
    {
        $req = $this->validateCheckoutRequest($request);
        $payloadHash = self::payloadHash($req);
        $shippingFee = (int) ($this->shippingRates[$req['shipping_method']] ?? 0);
        $now = time();

        // Snapshot label ukuran/warna dari master (nama & harga diambil Lua langsung dari master saat itu).
        $itemsForLua = [];
        foreach ($req['items'] as $it) {
            $product = $this->getProduct($it['product_id']);
            $labels = ['size_label' => '', 'color_label' => ''];
            foreach ($product['variants'] ?? [] as $v) {
                if ($v['code'] === $it['variant']) {
                    $labels = ['size_label' => $v['size_label'], 'color_label' => $v['color_label']];
                }
            }
            $itemsForLua[] = [
                'product_id'  => $it['product_id'],
                'variant'     => $it['variant'],
                'qty'         => $it['qty'],
            ] + $labels;
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $orderId = $this->generateOrderId($req['branch_id'], $now);
            $isPickup = $req['shipping_method'] === 'pickup';
            $header = [
                'order_id'        => $orderId,
                'branch_id'       => $req['branch_id'],
                'branch_name'     => (string) ($this->branches[$req['branch_id']]['name'] ?? $req['branch_id']),
                'request_id'      => $req['request_id'],
                'session_hash'    => $req['session_hash'],
                'customer_name'   => $req['customer']['name'],
                'customer_phone'  => $req['customer']['phone'],
                'shipping_method' => $req['shipping_method'],
                'shipping_address'=> $isPickup ? '' : $req['customer']['address'],
                'pickup_location' => $isPickup ? $req['pickup_location'] : '',
                'pickup_code'     => $isPickup ? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT) : '',
                'notes'           => $req['customer']['notes'],
                'payment_method'  => 'simulasi',
                'payment_status'  => 'dibayar_simulasi',
                'courier'         => '',
                'tracking_number' => '',
                'data_source'     => 'redis-local',
            ];

            $keys = [
                'checkout:request:' . $req['request_id'],
                'order:' . $orderId,
                'order:' . $orderId . ':items',
                'order:' . $orderId . ':history',
                'branch:' . $req['branch_id'] . ':orders',
                'customer:' . $req['session_hash'] . ':orders',
                'inventory:movements',
            ];
            foreach ($itemsForLua as $it) {
                $keys[] = 'inventory:stock:' . $it['product_id'];
                $keys[] = 'brand:catalog:product:' . $it['product_id'];
            }
            $args = [
                $payloadHash,
                json_encode($header, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                json_encode($itemsForLua, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                (string) $shippingFee,
                (string) $now,
                (string) self::REQUEST_TTL,
            ];

            $raw = $this->run(
                static fn (ClientInterface $r) => $r->eval(LuaScripts::CHECKOUT, count($keys), ...$keys, ...$args),
                true // jika koneksi putus di sini, hasil checkout BELUM DIKETAHUI
            );
            $res = json_decode((string) $raw, true);
            if (!is_array($res)) {
                throw new DataServiceUnavailable('Jawaban checkout dari Redis tidak terbaca.', true);
            }
            if (!empty($res['ok'])) {
                $order = $this->loadOrderRaw((string) $res['order_id'], true);
                if ($order === null) {
                    throw new DataServiceUnavailable('Pesanan tercatat tetapi gagal dibaca ulang. Periksa hasil request_id.', true);
                }
                return ['status' => $res['status'], 'order' => $this->publicOrder($order, 'customer')];
            }
            if (($res['code'] ?? '') === 'ORDER_ID_COLLISION') {
                continue; // ID bentrok (sangat jarang) -> buat ID baru, request_id tetap sama
            }
            throw new DomainRejected((string) ($res['code'] ?? 'UNKNOWN'), '', ['problems' => $res['problems'] ?? []]);
        }
        throw new DataServiceUnavailable('Gagal membuat ID pesanan unik. Coba lagi.');
    }

    /** Validasi struktur request checkout (lapisan pertahanan kedua setelah controller). */
    private function validateCheckoutRequest(array $r): array
    {
        $fail = static fn (string $code = 'BAD_REQUEST') => throw new DomainRejected($code, $code === 'BAD_REQUEST' ? 'Data checkout tidak lengkap atau tidak valid.' : '');

        if (!preg_match('/^[a-f0-9]{32}$/', (string) ($r['request_id'] ?? ''))) {
            $fail();
        }
        if (!isset($this->branches[$r['branch_id'] ?? ''])) {
            $fail();
        }
        if (!preg_match('/^[a-f0-9]{64}$/', (string) ($r['session_hash'] ?? ''))) {
            $fail();
        }
        if (!in_array($r['shipping_method'] ?? '', OrderRules::METHODS, true)) {
            $fail();
        }
        $c = $r['customer'] ?? [];
        $customer = [
            'name'    => trim((string) ($c['name'] ?? '')),
            'phone'   => trim((string) ($c['phone'] ?? '')),
            'address' => trim((string) ($c['address'] ?? '')),
            'notes'   => trim((string) ($c['notes'] ?? '')),
        ];
        if ($customer['name'] === '' || $customer['phone'] === '') {
            $fail();
        }
        if ($r['shipping_method'] === 'reguler' && $customer['address'] === '') {
            $fail();
        }
        $items = $r['items'] ?? [];
        if (!is_array($items) || !$items) {
            $fail('EMPTY_ORDER');
        }
        $merged = [];
        foreach ($items as $it) {
            $pid = (string) ($it['product_id'] ?? '');
            $variant = (string) ($it['variant'] ?? '');
            $qty = $it['qty'] ?? null;
            if (!preg_match('/^P\d{3}$/', $pid) || !preg_match('/^[a-z0-9-]+_[a-z0-9-]+$/', $variant)) {
                $fail('VARIANT_NOT_FOUND');
            }
            if (!is_int($qty) || $qty < 1 || $qty > 99) {
                $fail('INVALID_QTY');
            }
            $k = $pid . ':' . $variant;
            $merged[$k] = ($merged[$k] ?? 0) + $qty;
        }
        ksort($merged);
        $clean = [];
        foreach ($merged as $k => $qty) {
            [$pid, $variant] = explode(':', $k, 2);
            $clean[] = ['product_id' => $pid, 'variant' => $variant, 'qty' => $qty];
        }
        return [
            'request_id'      => $r['request_id'],
            'branch_id'       => $r['branch_id'],
            'session_hash'    => $r['session_hash'],
            'customer'        => $customer,
            'shipping_method' => $r['shipping_method'],
            'pickup_location' => $r['shipping_method'] === 'pickup' ? trim((string) ($r['pickup_location'] ?? '')) : '',
            'items'           => $clean,
        ];
    }

    /** Sidik isi checkout. request_id sama + isi berbeda => ditolak (IDEMPOTENCY_CONFLICT). */
    public static function payloadHash(array $req): string
    {
        return hash('sha256', json_encode([
            $req['branch_id'], $req['session_hash'], $req['shipping_method'],
            $req['customer'], $req['pickup_location'], $req['items'],
        ], JSON_UNESCAPED_UNICODE));
    }

    private function generateOrderId(string $branchId, int $now): string
    {
        $prefix = (string) ($this->branches[$branchId]['prefix'] ?? 'ALV');
        $rand = '';
        $max = strlen(self::ID_ALPHABET) - 1;
        for ($i = 0; $i < 6; $i++) {
            $rand .= self::ID_ALPHABET[random_int(0, $max)];
        }
        return $prefix . '-' . date('ymd', $now) . '-' . $rand;
    }

    public function getCheckoutResult(string $requestId, array $access): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $requestId)) {
            return null;
        }
        $rec = $this->run(static fn (ClientInterface $r) => $r->get('checkout:request:' . $requestId));
        if ($rec === null) {
            return null;
        }
        $data = json_decode((string) $rec, true);
        $order = $this->getOrder((string) ($data['order_id'] ?? ''), $access);
        return $order === null ? null : ['status' => 'duplicate', 'order' => $order];
    }

    // ------------------------------------------------------------------
    // Pesanan
    // ------------------------------------------------------------------

    private function loadOrderRaw(string $orderId, bool $withItems): ?array
    {
        if (!preg_match('/^[A-Z]{3}-\d{6}-[A-Z0-9]{6}$/', $orderId)) {
            return null;
        }
        $res = $this->run(static fn (ClientInterface $r) => $r->pipeline(static function ($pipe) use ($orderId, $withItems): void {
            $pipe->hgetall('order:' . $orderId);
            if ($withItems) {
                $pipe->lrange('order:' . $orderId . ':items', 0, -1);
                $pipe->lrange('order:' . $orderId . ':history', 0, -1);
            }
        }));
        $h = (array) ($res[0] ?? []);
        if (!$h) {
            return null;
        }
        $order = self::castOrder($h);
        if ($withItems) {
            $order['items'] = array_map(static fn ($j) => json_decode((string) $j, true), (array) ($res[1] ?? []));
            $order['history'] = array_map(static fn ($j) => json_decode((string) $j, true), (array) ($res[2] ?? []));
        }
        return $order;
    }

    /** @param array<string,string> $h */
    private static function castOrder(array $h): array
    {
        foreach (['items_subtotal', 'shipping_fee', 'total', 'total_units', 'created_at', 'updated_at',
                     'completed_at', 'cancelled_at', 'shipped_at', 'ready_at'] as $f) {
            $h[$f] = isset($h[$f]) && $h[$f] !== '' ? (int) $h[$f] : null;
        }
        $h['restocked'] = ($h['restocked'] ?? '0') === '1';
        return $h;
    }

    /** Buang data internal sesuai peran. Admin tidak melihat kode pickup (harus dicocokkan dari pelanggan). */
    private function publicOrder(array $order, string $viewer): array
    {
        unset($order['session_hash']);
        if ($viewer !== 'customer') {
            $order['has_pickup_code'] = ($order['pickup_code'] ?? '') !== '';
            unset($order['pickup_code']);
        }
        return $order;
    }

    private static function allowed(array $order, array $access): bool
    {
        $branch = (string) ($access['branch_id'] ?? '');
        if ($branch === '' || ($order['branch_id'] ?? '') !== $branch) {
            return false;
        }
        return match ($access['type'] ?? '') {
            'customer'     => isset($access['session_hash'], $order['session_hash'])
                && hash_equals((string) $order['session_hash'], (string) $access['session_hash']),
            'branch_admin' => true,
            default        => false,
        };
    }

    public function getOrder(string $orderId, array $access): ?array
    {
        $order = $this->loadOrderRaw($orderId, true);
        if ($order === null || !self::allowed($order, $access)) {
            return null; // tidak membedakan "tidak ada" dan "tidak berhak"
        }
        return $this->publicOrder($order, (string) $access['type']);
    }

    public function listOrders(array $access, array $filter = []): array
    {
        $type = (string) ($access['type'] ?? '');
        if ($type === 'customer') {
            if (!preg_match('/^[a-f0-9]{64}$/', (string) ($access['session_hash'] ?? ''))) {
                return [];
            }
            $index = 'customer:' . $access['session_hash'] . ':orders';
        } elseif ($type === 'branch_admin' && isset($this->branches[$access['branch_id'] ?? ''])) {
            $index = 'branch:' . $access['branch_id'] . ':orders';
        } else {
            throw new DomainRejected('FORBIDDEN');
        }
        $limit = max(1, min(1000, (int) ($filter['limit'] ?? 500)));
        $rows = $this->run(static function (ClientInterface $r) use ($index, $limit): array {
            $ids = $r->zrevrange($index, 0, $limit - 1);
            if (!$ids) {
                return [];
            }
            return $r->pipeline(static function ($pipe) use ($ids): void {
                foreach ($ids as $id) {
                    $pipe->hgetall('order:' . $id);
                }
            });
        });
        $status = (string) ($filter['status'] ?? '');
        $method = (string) ($filter['method'] ?? '');
        $q = lower(trim((string) ($filter['q'] ?? '')));
        $out = [];
        foreach ($rows as $h) {
            if (!$h) {
                continue;
            }
            $order = self::castOrder((array) $h);
            if (!self::allowed($order, $access)) {
                continue;
            }
            if ($status !== '' && $order['status'] !== $status) {
                continue;
            }
            if ($method !== '' && $order['shipping_method'] !== $method) {
                continue;
            }
            if ($q !== '' && !str_contains(lower($order['order_id'] . ' ' . ($order['customer_name'] ?? '')), $q)) {
                continue;
            }
            $out[] = $this->publicOrder($order, $type);
        }
        return $out;
    }

    public function updateOrderStatus(string $orderId, array $change, array $actor): array
    {
        $branch = (string) ($actor['branch_id'] ?? '');
        if (!isset($this->branches[$branch])) {
            throw new DomainRejected('FORBIDDEN');
        }
        $keys = ['order:' . $orderId, 'order:' . $orderId . ':history', 'branch:' . $branch . ':completed'];
        $args = [
            $branch,
            (string) ($change['from'] ?? ''),
            (string) ($change['to'] ?? ''),
            (string) ($actor['username'] ?? 'admin'),
            (string) time(),
            trim((string) ($change['courier'] ?? '')),
            trim((string) ($change['tracking_number'] ?? '')),
            trim((string) ($change['pickup_code'] ?? '')),
            json_encode(OrderRules::TRANSITIONS, JSON_THROW_ON_ERROR),
        ];
        $raw = $this->run(static fn (ClientInterface $r) => $r->eval(LuaScripts::UPDATE_STATUS, count($keys), ...$keys, ...$args), true);
        $res = json_decode((string) $raw, true) ?: [];
        if (empty($res['ok'])) {
            throw new DomainRejected((string) ($res['code'] ?? 'UNKNOWN'), '', ['current' => $res['current'] ?? null]);
        }
        $order = $this->getOrder($orderId, ['type' => 'branch_admin', 'branch_id' => $branch]);
        return ['status' => $res['status'], 'order' => $order];
    }

    public function cancelOrder(string $orderId, string $reason, array $actor): array
    {
        $branch = (string) ($actor['branch_id'] ?? '');
        if (!isset($this->branches[$branch])) {
            throw new DomainRejected('FORBIDDEN');
        }
        if (trim($reason) === '') {
            throw new DomainRejected('REASON_REQUIRED');
        }
        // Baca item lebih dulu hanya untuk mendeklarasikan key stok; skrip Lua membaca ulang secara atomik.
        $rawItems = $this->run(static fn (ClientInterface $r) => $r->lrange('order:' . $orderId . ':items', 0, -1));
        $stockKeys = [];
        foreach ((array) $rawItems as $j) {
            $it = json_decode((string) $j, true);
            if (isset($it['product_id'])) {
                $stockKeys['inventory:stock:' . $it['product_id']] = true;
            }
        }
        $keys = array_merge(
            ['order:' . $orderId, 'order:' . $orderId . ':items', 'order:' . $orderId . ':history', 'inventory:movements'],
            array_keys($stockKeys)
        );
        $args = [$branch, trim($reason), (string) ($actor['username'] ?? 'admin'), (string) time()];
        $raw = $this->run(static fn (ClientInterface $r) => $r->eval(LuaScripts::CANCEL, count($keys), ...$keys, ...$args), true);
        $res = json_decode((string) $raw, true) ?: [];
        if (empty($res['ok'])) {
            throw new DomainRejected((string) ($res['code'] ?? 'UNKNOWN'), '', ['current' => $res['current'] ?? null]);
        }
        $order = $this->getOrder($orderId, ['type' => 'branch_admin', 'branch_id' => $branch]);
        return ['status' => $res['status'], 'order' => $order];
    }

    // ------------------------------------------------------------------
    // Rekap
    // ------------------------------------------------------------------

    public function getSalesSummary(string $branchId, int $fromTs, int $toTs): array
    {
        if (!isset($this->branches[$branchId])) {
            throw new DomainRejected('FORBIDDEN');
        }
        $rows = $this->run(static function (ClientInterface $r) use ($branchId, $fromTs, $toTs): array {
            $ids = $r->zrangebyscore('branch:' . $branchId . ':completed', $fromTs, '(' . $toTs);
            if (!$ids) {
                return [];
            }
            return $r->pipeline(static function ($pipe) use ($ids): void {
                foreach ($ids as $id) {
                    $pipe->hgetall('order:' . $id);
                    $pipe->lrange('order:' . $id . ':items', 0, -1);
                }
            });
        });
        $summary = [
            'branch_id' => $branchId, 'from' => $fromTs, 'to' => $toTs,
            'orders_completed' => 0, 'units_sold' => 0, 'items_subtotal' => 0, 'shipping_total' => 0,
            'by_method' => ['reguler' => 0, 'pickup' => 0],
            'products' => [],
        ];
        $products = [];
        for ($i = 0; $i + 1 < count($rows); $i += 2) {
            $h = (array) $rows[$i];
            if (!$h || ($h['status'] ?? '') !== 'selesai' || ($h['branch_id'] ?? '') !== $branchId) {
                continue;
            }
            $summary['orders_completed']++;
            $summary['items_subtotal'] += (int) $h['items_subtotal'];
            $summary['shipping_total'] += (int) $h['shipping_fee'];
            $summary['by_method'][$h['shipping_method']] = ($summary['by_method'][$h['shipping_method']] ?? 0) + 1;
            foreach ((array) $rows[$i + 1] as $j) {
                $it = json_decode((string) $j, true);
                $summary['units_sold'] += (int) $it['qty'];
                $key = $it['product_id'];
                $products[$key] ??= ['product_id' => $key, 'name' => $it['name'], 'units' => 0, 'subtotal' => 0];
                $products[$key]['units'] += (int) $it['qty'];
                $products[$key]['subtotal'] += (int) $it['line_total'];
            }
        }
        usort($products, static fn ($a, $b) => $b['subtotal'] <=> $a['subtotal']);
        $summary['products'] = array_values($products);
        return $summary;
    }
}
