# Kontrak data website Alverse Bogor

Dokumen ini menjelaskan bentuk input/output antarmuka `App\Data\CentralDataService`.
Kedua adapter (`RedisLocalAdapter` dan `CentralHttpAdapter`) **wajib** mengikuti bentuk yang sama.
Status: **usulan dari sisi website Bogor**, mengikuti Panduan v2. Kelompok 6 dapat menyesuaikan nama field, dan perubahannya cukup dilakukan di adapter.

## 1. Key Redis (adapter lokal)

| Key | Tipe | Isi | Sumber |
|---|---|---|---|
| `brand:catalog:product:{product_id}` | Hash | master produk | panduan v2 |
| `brand:catalog:products` | Set | daftar product_id | panduan v2 |
| `inventory:stock:{product_id}` | Hash | **stok global**, field = kode varian | panduan v2 |
| `cart:{session_id}:{branch_id}` | Hash | `"P037:adjustable_hitam" → qty` | panduan v2 |
| `order:{order_id}` | Hash | header pesanan | panduan v2 |
| `order:{order_id}:items` | List | JSON item + snapshot harga | panduan v2 |
| `branch:{branch_id}:orders` | ZSET | indeks asal pesanan (score = waktu dibuat) | panduan v2 |
| `order:{order_id}:history` | List | riwayat status | tambahan lokal |
| `customer:{session_hash}:orders` | ZSET | pesanan milik sesi pelanggan | tambahan lokal |
| `branch:{branch_id}:completed` | ZSET | pesanan selesai (score = waktu selesai) untuk rekap | tambahan lokal |
| `checkout:request:{request_id}` | String (TTL 30 hari) | `{order_id, payload_hash}` untuk idempotensi | tambahan lokal |
| `inventory:movements` | Stream | log pergerakan stok (sale / cancel_restock) | tambahan lokal |
| `alverse:meta:dataset` | String | penanda dataset `demo-local` / `test` | tambahan lokal |

**Tidak ada** key `branch:{id}:stock:*`.

## 2. Kode varian

`{kode_ukuran}_{kode_warna}`, huruf kecil, spasi pada nama warna diganti `-`.
Contoh: `m_hitam`, `30_khaki`, `40_putih`, `adjustable_hitam`, `one_dusty-pink`, `105_cokelat`.
Ukuran tunggal (hijab, tas, bucket hat, dan sejenisnya) memakai `one`, sedangkan topi memakai `adjustable` sesuai contoh panduan.

## 3. Produk

```json
{
  "product_id": "P037",
  "name": "Topi Baseball",
  "category": "tas-aksesori",
  "category_label": "Tas & Aksesori",
  "price": 79000,
  "size_label": "Adjustable",
  "color_label": "Hitam, Navy, Cream",
  "sizes":  [{"code": "adjustable", "label": "Adjustable"}],
  "colors": [{"code": "hitam", "label": "Hitam"}, {"code": "navy", "label": "Navy"}, {"code": "cream", "label": "Cream"}],
  "variants": [{"code": "adjustable_hitam", "size": "adjustable", "size_label": "Adjustable", "color": "hitam", "color_label": "Hitam"}],
  "description": "…",
  "image_url": "/assets/img/produk/tas-aksesori.svg",
  "image_is_demo": true,
  "active": true
}
```

## 4. Stok

`getStock("P037")`
```json
{"product_id": "P037", "variants": {"adjustable_hitam": 10, "adjustable_navy": 4, "adjustable_cream": 4}, "fetched_at": 1794027296}
```
Varian yang tidak ada di `variants` dianggap **tidak tersedia** (berbeda dengan stok 0 = habis).

Endpoint website untuk browser: `GET /api/stok/P037`
```json
{"ok": true, "product_id": "P037", "variants": {"adjustable_hitam": 9}, "fetched_at": 1794027300, "fetched_at_label": "11:55:00", "scope": "global"}
```
Jika terjadi gangguan, endpoint mengembalikan HTTP 503 `{"ok": false, "message": "Tidak dapat memperbarui stok"}`.

## 5. Checkout

Request (dikirim website, **tanpa harga**):
```json
{
  "request_id": "9f2c…(32 hex)",
  "branch_id": "bogor",
  "session_hash": "(sha256 64 hex, identitas pemilik pesanan)",
  "customer": {"name": "Budi", "phone": "081234567890", "address": "", "notes": ""},
  "shipping_method": "pickup",
  "pickup_location": "Lokasi pickup simulasi Alverse Bogor Kota (alamat demo)",
  "items": [{"product_id": "P037", "variant": "adjustable_hitam", "qty": 1}]
}
```
Respons sukses (`status` = `created` pada panggilan pertama, `duplicate` jika request_id sama diulang):
```json
{
  "status": "created",
  "order": {
    "order_id": "BGR-261107-FRCBKX", "branch_id": "bogor", "branch_name": "Bogor Kota",
    "request_id": "9f2c…", "status": "dikonfirmasi",
    "customer_name": "Budi", "customer_phone": "081234567890",
    "shipping_method": "pickup", "shipping_address": "", "pickup_location": "…", "pickup_code": "482913",
    "items_subtotal": 79000, "shipping_fee": 0, "total": 79000, "total_units": 1,
    "payment_method": "simulasi", "payment_status": "dibayar_simulasi",
    "courier": "", "tracking_number": "", "restocked": false,
    "created_at": 1794027300, "updated_at": 1794027300, "completed_at": null, "cancelled_at": null,
    "items": [{"product_id": "P037", "name": "Topi Baseball", "category": "tas-aksesori", "variant": "adjustable_hitam",
               "size_label": "Adjustable", "color_label": "Hitam", "qty": 1, "unit_price": 79000, "line_total": 79000}],
    "history": [{"status": "dikonfirmasi", "at": 1794027300, "by": "sistem", "note": "Pembayaran simulasi berhasil; stok global dipotong."}]
  }
}
```
`pickup_code` hanya dikirim untuk akses pelanggan. Admin tidak menerimanya karena kode itu harus dicocokkan dari pelanggan saat penyerahan.

Penolakan (`DomainRejected`), contoh stok kurang:
```json
{"ok": false, "error": {"code": "INSUFFICIENT_STOCK", "message": "…",
  "details": {"problems": [{"code": "INSUFFICIENT_STOCK", "product_id": "P037", "variant": "adjustable_cream", "available": 0, "requested": 1}]}}}
```

## 6. Kode error

| Kode | Arti |
|---|---|
| `INSUFFICIENT_STOCK` | stok global kurang |
| `VARIANT_NOT_FOUND` | varian tidak ada |
| `PRODUCT_UNAVAILABLE` | produk tidak aktif / tidak ada |
| `INVALID_QTY` | jumlah bukan bulat positif |
| `EMPTY_ORDER` | tidak ada item |
| `IDEMPOTENCY_CONFLICT` | request_id sama dengan isi berbeda |
| `ORDER_NOT_FOUND` | pesanan tidak ada **atau** bukan milik cabang/sesi ini |
| `STATUS_CONFLICT` | status sudah berubah (data di layar basi) |
| `INVALID_TRANSITION` | perubahan status tidak sah |
| `TRACKING_REQUIRED` | kurir dan resi wajib sebelum `dikirim` |
| `PICKUP_CODE_MISMATCH` | kode pickup salah |
| `NOT_CANCELLABLE` | hanya `dikonfirmasi` yang bisa dibatalkan |
| `REASON_REQUIRED` | alasan batal kosong |
| `FORBIDDEN` | akses tidak sah |

Gangguan koneksi **bukan** kode error bisnis. Adapter melempar `DataServiceUnavailable` (opsi `outcomeUnknown=true` untuk operasi tulis yang hasilnya belum diketahui).

## 7. Status pesanan

```
reguler : dikonfirmasi → dikemas → dikirim (kurir+resi) → selesai
pickup  : dikonfirmasi → siap_diambil → selesai (kode pickup cocok)
batal   : dikonfirmasi → dibatalkan (alasan wajib; stok kembali sekali)
```
`updateOrderStatus(order_id, {from, to, courier?, tracking_number?, pickup_code?}, actor)` mengembalikan `status: "updated" | "unchanged"`. Hasil `unchanged` berarti klik ganda aman.

## 8. Rekap

`getSalesSummary("bogor", from, to)` menghitung hanya pesanan `selesai`, berdasarkan `completed_at` dalam rentang `[from, to)` (UNIX, website memakai batas hari WIB):
```json
{"branch_id": "bogor", "from": 1793984400, "to": 1794070800, "orders_completed": 2, "units_sold": 3,
 "items_subtotal": 647000, "shipping_total": 10000, "by_method": {"reguler": 1, "pickup": 1},
 "products": [{"product_id": "P029", "name": "Canvas Sneakers", "units": 2, "subtotal": 558000}]}
```
