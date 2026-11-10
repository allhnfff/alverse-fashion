<?php

declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Data\DataServiceUnavailable;
use App\Domain\ValidationError;
use App\Domain\Validator;
use App\Http\View;

/**
 * Endpoint JSON yang dipanggil JavaScript halaman produk (polling 5 detik).
 * Browser TIDAK pernah terhubung langsung ke Redis; ia hanya memanggil server PHP ini.
 */
final class ApiController extends Controller
{
    public function stock(string $id): void
    {
        try {
            $id = Validator::productId($id);
        } catch (ValidationError) {
            View::json(['ok' => false, 'message' => 'Kode produk tidak valid.'], 404);
            return;
        }
        try {
            $s = $this->data()->getStock($id);
        } catch (DataServiceUnavailable) {
            // Jangan kirim angka 0: pelanggan harus melihat "Tidak dapat memperbarui stok".
            View::json(['ok' => false, 'message' => 'Tidak dapat memperbarui stok'], 503);
            return;
        }
        View::json([
            'ok'         => true,
            'product_id' => $s['product_id'],
            'variants'   => (object) $s['variants'],
            'fetched_at' => $s['fetched_at'],
            'fetched_at_label' => date('H:i:s', $s['fetched_at']),
            'scope'      => 'global',
        ]);
    }

    public function status(): void
    {
        $health = $this->data()->health();
        View::json([
            'ok'        => $health['ok'],
            'branch_id' => App::config('branch.id'),
            'source'    => $this->data()->describe(),
            'health'    => $health,
            'time'      => date('c'),
        ], $health['ok'] ? 200 : 503);
    }
}
