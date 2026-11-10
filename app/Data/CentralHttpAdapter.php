<?php

declare(strict_types=1);

namespace App\Data;

/**
 * ADAPTER LAYANAN PUSAT KELOMPOK 6 (HTTP/JSON).
 *
 * STATUS: kerangka siap pakai, BELUM TERHUBUNG. Endpoint belum diberikan Kelompok 6,
 * sehingga config/central_endpoints.php masih null. Selama itu setiap operasi melempar
 * DataServiceUnavailable dengan pesan "belum dikonfigurasi". Tidak ada keberhasilan palsu.
 *
 * Asumsi yang PERLU DIKONFIRMASI ke Kelompok 6 (lihat docs/INTEGRASI-PUSAT.md):
 *   - Autentikasi: header "Authorization: Bearer <CENTRAL_API_KEY>" + "X-Branch-Id".
 *   - Amplop JSON usulan:
 *       sukses : {"ok": true,  "data": <bentuk sama dengan kontrak>}
 *       gagal  : {"ok": false, "error": {"code": "INSUFFICIENT_STOCK", "message": "...", "details": {...}}}
 *   - Jika format mereka berbeda, cukup ubah method decode() di bawah. Controller tidak perlu diubah.
 */
final class CentralHttpAdapter implements CentralDataService
{
    /**
     * @param array<string,array{0:string,1:string}|null> $endpoints
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly float $timeout,
        private readonly array $endpoints,
        private readonly string $branchId
    ) {
    }

    public function describe(): array
    {
        $host = parse_url($this->baseUrl, PHP_URL_HOST) ?: '(CENTRAL_BASE_URL kosong)';
        return ['driver' => 'central', 'label' => 'Layanan pusat Kelompok 6 (' . $host . ')', 'dataset' => 'central'];
    }

    public function health(): array
    {
        try {
            $data = $this->call('health');
            return ['ok' => true, 'driver' => 'central', 'detail' => 'Layanan pusat menjawab', 'dataset' => (string) ($data['dataset'] ?? 'central')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'driver' => 'central', 'detail' => $e->getMessage()];
        }
    }

    public function listProducts(): array
    {
        return (array) $this->call('listProducts');
    }

    public function getProduct(string $productId): ?array
    {
        return $this->call('getProduct', ['product_id' => $productId], null, true);
    }

    public function getStock(string $productId): array
    {
        $d = (array) $this->call('getStock', ['product_id' => $productId]);
        return [
            'product_id' => $productId,
            'variants'   => array_map('intval', (array) ($d['variants'] ?? [])),
            'fetched_at' => (int) ($d['fetched_at'] ?? time()),
        ];
    }

    public function getStockMany(array $productIds): array
    {
        $d = (array) $this->call('getStockMany', [], ['product_ids' => array_values($productIds)]);
        $out = [];
        foreach ($d as $pid => $variants) {
            $out[(string) $pid] = array_map('intval', (array) $variants);
        }
        return $out;
    }

    public function getCart(string $sessionId, string $branchId): array
    {
        return (array) $this->call('getCart', ['session_id' => $sessionId, 'branch_id' => $branchId]);
    }

    public function setCartItem(string $sessionId, string $branchId, string $productId, string $variant, int $qty): void
    {
        $this->call('setCartItem', ['session_id' => $sessionId, 'branch_id' => $branchId],
            ['product_id' => $productId, 'variant' => $variant, 'qty' => $qty]);
    }

    public function removeCartItem(string $sessionId, string $branchId, string $productId, string $variant): void
    {
        $this->call('removeCartItem', ['session_id' => $sessionId, 'branch_id' => $branchId],
            ['product_id' => $productId, 'variant' => $variant]);
    }

    public function checkout(array $request): array
    {
        // Ongkir & harga TIDAK dikirim: pusat yang menghitung.
        return (array) $this->call('checkout', ['request_id' => $request['request_id']], $request, false, true);
    }

    public function getCheckoutResult(string $requestId, array $access): ?array
    {
        return $this->call('getCheckoutResult', ['request_id' => $requestId], ['access' => $access], true);
    }

    public function getOrder(string $orderId, array $access): ?array
    {
        return $this->call('getOrder', ['order_id' => $orderId], ['access' => $access], true);
    }

    public function listOrders(array $access, array $filter = []): array
    {
        return (array) $this->call('listOrders', ['branch_id' => (string) ($access['branch_id'] ?? '')],
            ['access' => $access, 'filter' => $filter]);
    }

    public function updateOrderStatus(string $orderId, array $change, array $actor): array
    {
        return (array) $this->call('updateOrderStatus', ['order_id' => $orderId], ['change' => $change, 'actor' => $actor], false, true);
    }

    public function cancelOrder(string $orderId, string $reason, array $actor): array
    {
        return (array) $this->call('cancelOrder', ['order_id' => $orderId], ['reason' => $reason, 'actor' => $actor], false, true);
    }

    public function getSalesSummary(string $branchId, int $fromTs, int $toTs): array
    {
        return (array) $this->call('getSalesSummary', ['branch_id' => $branchId], ['from' => $fromTs, 'to' => $toTs]);
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string,string> $params pengganti {placeholder} di path
     * @param array<string,mixed>|null $body body JSON (untuk POST/PUT/PATCH) atau query (untuk GET)
     */
    private function call(string $operation, array $params = [], ?array $body = null, bool $nullOn404 = false, bool $isWrite = false): mixed
    {
        $endpoint = $this->endpoints[$operation] ?? null;
        if ($this->baseUrl === '' || !is_array($endpoint) || count($endpoint) !== 2) {
            throw new DataServiceUnavailable(
                "Layanan pusat belum dikonfigurasi untuk operasi '{$operation}'. "
                . 'Isi CENTRAL_BASE_URL di .env dan endpoint di config/central_endpoints.php setelah kontrak dari Kelompok 6 tersedia.'
            );
        }
        [$method, $path] = $endpoint;
        $method = strtoupper($method);
        $params += ['branch_id' => $this->branchId];
        $path = preg_replace_callback('/\{([a-z_]+)\}/', static function (array $m) use ($params): string {
            if (!isset($params[$m[1]])) {
                throw new \InvalidArgumentException("Placeholder {{$m[1]}} tidak tersedia.");
            }
            return rawurlencode((string) $params[$m[1]]);
        }, $path);

        $url = $this->baseUrl . $path;
        $headers = [
            'Accept: application/json',
            'X-Branch-Id: ' . $this->branchId,
        ];
        if ($this->apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        $content = null;
        if ($body !== null) {
            if ($method === 'GET') {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($body);
            } else {
                $content = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $headers[] = 'Content-Type: application/json';
            }
        }

        $ctx = stream_context_create(['http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $headers),
            'content'       => $content ?? '',
            'timeout'       => $this->timeout,
            'ignore_errors' => true, // agar body error 4xx/5xx tetap terbaca
        ]]);

        $http_response_header = [];
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new DataServiceUnavailable('Layanan pusat tidak dapat dihubungi (' . $operation . ').', $isWrite);
        }
        $status = 0;
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            }
        }
        return $this->decode($operation, $status, $raw, $nullOn404, $isWrite);
    }

    /** SATU-SATUNYA tempat yang perlu diubah jika format respons Kelompok 6 berbeda. */
    private function decode(string $operation, int $status, string $raw, bool $nullOn404, bool $isWrite): mixed
    {
        if ($status === 404 && $nullOn404) {
            return null;
        }
        if ($status >= 500 || $status === 0) {
            throw new DataServiceUnavailable("Layanan pusat bermasalah (HTTP {$status}) pada operasi {$operation}.", $isWrite);
        }
        $json = json_decode($raw, true);
        if (!is_array($json) || !array_key_exists('ok', $json)) {
            throw new DataServiceUnavailable("Format respons pusat tidak dikenali pada operasi {$operation}.", $isWrite);
        }
        if ($json['ok'] !== true) {
            $err = (array) ($json['error'] ?? []);
            throw new DomainRejected(
                (string) ($err['code'] ?? 'CENTRAL_ERROR'),
                (string) ($err['message'] ?? ''),
                (array) ($err['details'] ?? [])
            );
        }
        return $json['data'] ?? null;
    }
}
