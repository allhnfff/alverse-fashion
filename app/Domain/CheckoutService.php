<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Aturan checkout di sisi website:
 *  - Validasi form penerima & metode.
 *  - Menghitung ringkasan biaya dari harga master SERVER (pratinjau).
 *  - Menyusun request checkout untuk layanan data (tanpa harga; harga final dihitung layanan data).
 */
final class CheckoutService
{
    /** @param array<string,int> $rates */
    public function __construct(private readonly array $rates)
    {
    }

    /**
     * @param array<string,mixed> $post isi $_POST (tidak dipercaya)
     * @return array{customer: array{name:string,phone:string,address:string,notes:string}, method:string}
     */
    public function validateForm(array $post): array
    {
        $method = Validator::method($post['shipping_method'] ?? '');
        $customer = [
            'name'    => Validator::text($post['name'] ?? '', 'Nama penerima', 3, 80),
            'phone'   => Validator::phone($post['phone'] ?? ''),
            'address' => $method === 'reguler'
                ? Validator::text($post['address'] ?? '', 'Alamat pengiriman', 10, 300)
                : '',
            'notes'   => Validator::optionalText($post['notes'] ?? '', 'Catatan', 200),
        ];
        return ['customer' => $customer, 'method' => $method];
    }

    /**
     * Semua kesalahan form sekaligus, per kolom, untuk ditampilkan di bawah kolom yang salah (wireframe hal. 5).
     * Aturannya sama persis dengan validateForm(); fungsi ini hanya mengumpulkan pesannya.
     * @return array<string,string> nama kolom => pesan
     */
    public function fieldErrors(array $post): array
    {
        $errors = [];
        $check = static function (string $field, callable $fn) use (&$errors): mixed {
            try {
                return $fn();
            } catch (ValidationError $e) {
                $errors[$field] = $e->getMessage();
                return null;
            }
        };
        $method = $check('shipping_method', static fn () => Validator::method($post['shipping_method'] ?? ''));
        $check('name', static fn () => Validator::text($post['name'] ?? '', 'Nama penerima', 3, 80));
        $check('phone', static fn () => Validator::phone($post['phone'] ?? ''));
        if ($method === 'reguler') {
            $check('address', static fn () => Validator::text($post['address'] ?? '', 'Alamat pengiriman', 10, 300));
        }
        $check('notes', static fn () => Validator::optionalText($post['notes'] ?? '', 'Catatan', 200));
        return $errors;
    }

    public function shippingFee(string $method): int
    {
        return (int) ($this->rates[$method] ?? 0);
    }

    /** Ringkasan biaya dari keranjang detail (harga sudah dari master server). */
    public function quote(array $cart, string $method): array
    {
        $shipping = $this->shippingFee($method);
        return [
            'items_subtotal' => $cart['subtotal'],
            'shipping_fee'   => $shipping,
            'total'          => $cart['subtotal'] + $shipping,
            'units'          => $cart['units'],
        ];
    }

    /**
     * @param list<array{product_id:string,variant:string,qty:int}> $items
     */
    public function buildRequest(
        string $requestId,
        string $branchId,
        string $ownerHash,
        array $form,
        string $pickupLocation,
        array $items
    ): array {
        return [
            'request_id'      => $requestId,
            'branch_id'       => $branchId,
            'session_hash'    => $ownerHash,
            'customer'        => $form['customer'],
            'shipping_method' => $form['method'],
            'pickup_location' => $form['method'] === 'pickup' ? $pickupLocation : '',
            'items'           => array_map(static fn (array $l): array => [
                'product_id' => $l['product_id'],
                'variant'    => $l['variant'],
                'qty'        => (int) $l['qty'],
            ], $items),
        ];
    }

    /** Sidik isi keranjang untuk mendeteksi keranjang berubah antara tinjau dan konfirmasi. */
    public static function cartFingerprint(array $rawCart): string
    {
        $rows = array_map(static fn ($l) => $l['product_id'] . ':' . $l['variant'] . '=' . $l['qty'], $rawCart);
        sort($rows);
        return hash('sha256', implode('|', $rows));
    }
}
