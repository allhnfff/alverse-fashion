# Pindah dari Redis lokal ke layanan pusat Kelompok 6

**Status saat ini: BELUM TERHUBUNG.** Endpoint dan kredensial dari Kelompok 6 belum ada.
`CentralHttpAdapter` sudah dibuat, tetapi setiap operasinya akan menolak dengan pesan
"Layanan pusat belum dikonfigurasi…" sampai konfigurasi diisi. Tidak ada endpoint yang dikarang.

## 1. Arsitektur singkat

```
Browser ──HTTP──> Website Bogor (PHP) ──> CentralDataService (interface)
                                              ├── RedisLocalAdapter   (DATA_DRIVER=redis)   → Redis di laptop
                                              └── CentralHttpAdapter  (DATA_DRIVER=central) → Layanan pusat Kelompok 6
```
Controller, view, dan aturan bisnis di `app/Domain` tidak berubah saat adapter diganti.

## 2. Hal yang perlu ditanyakan ke Kelompok 6

Salin daftar ini ke grup koordinasi:

1. **Bentuk akses:** API HTTP/JSON, atau akses langsung ke Redis pusat? (lihat bagian 4)
2. **URL dasar** layanan (contoh bentuk `https://…/api`) dan apakah bisa diakses dari laptop kita (jaringan kampus / VPN / tunnel).
3. **Autentikasi:** apakah memakai API key per website? Header apa (`Authorization: Bearer …` atau lainnya)? Apakah server pusat memastikan key Bogor hanya bisa mengakses `branch_id=bogor`?
4. **Daftar endpoint** untuk tiap operasi: `listProducts, getProduct, getStock, getStockMany, getCart, setCartItem, removeCartItem, checkout, getCheckoutResult, getOrder, listOrders, updateOrderStatus, cancelOrder, getSalesSummary, health`, lengkap dengan method HTTP dan path.
5. **Format respons:** apakah memakai amplop `{"ok": true, "data": …}` / `{"ok": false, "error": {"code", "message", "details"}}`? Jika berbeda, minta contoh respons sukses dan gagal.
6. **Daftar kode varian final** (contoh `adjustable_hitam`, `one_dusty-pink`, `m_hitam`) dan label ukuran/warna.
7. **Kode error** (apakah sama dengan `docs/KONTRAK-DATA.md` bagian 6).
8. **Idempotensi checkout:** apakah pusat menerima `request_id` dari website? Berapa lama hasilnya disimpan? Bagaimana cara mencari hasil `request_id` lama?
9. **Identitas pemilik pesanan:** apakah pusat menerima `session_hash` untuk membatasi pesanan pelanggan, atau ada mekanisme lain?
10. **Kode pickup:** dibuat oleh pusat atau website? Apakah disembunyikan dari admin?
11. **Format ID pesanan** (website memakai `BGR-YYMMDD-XXXXXX`).
12. **Server uji + data seed:** kapan tersedia, dan apakah ada dataset uji terpisah dari data demo bersama?
13. **Batas polling:** apakah polling stok setiap 5 detik dari 7 website diizinkan? Apakah ada endpoint stok banyak produk sekaligus?
14. **Panel admin_pusat** untuk restock/koreksi: URL dan siapa operatornya (usulan: Ridho).

## 3. Langkah setelah informasi diterima (jalur API HTTP)

1. Isi `.env`:
   ```
   DATA_DRIVER=central
   CENTRAL_BASE_URL=<URL dari Kelompok 6>
   CENTRAL_API_KEY=<isi di .env saja, jangan di-commit / jangan ditempel di chat>
   CENTRAL_TIMEOUT=5
   ```
2. Isi `config/central_endpoints.php`, misalnya `'getStock' => ['GET', '/…/{product_id}']` sesuai dokumen mereka.
3. Jika format respons berbeda dari amplop usulan, ubah **hanya** method `decode()` di `app/Data/CentralHttpAdapter.php`. Jika nama field berbeda, petakan di method terkait adapter tersebut.
4. Cek koneksi: buka `http://localhost:8001/api/status`. Bagian `health.ok` harus `true`.
5. Uji ulang seluruh alur (lihat `docs/PENGUJIAN.md` bagian D). **Data demo lokal bukan bukti sinkronisasi.** Bukti integrasi adalah pembelian dari website Bogor yang terlihat di website teman, dan sebaliknya.
6. Jangan menjalankan `scripts/seed-demo.php` atau `reset-demo.php` terhadap pusat (kedua script sudah menolak jika `DATA_DRIVER=central`).

## 4. Jika Kelompok 6 memberi akses Redis langsung

Ini hanya boleh dilakukan jika Kelompok 6 **menyetujui** key pada `docs/KONTRAK-DATA.md` dan skrip checkout Lua kita, atau memberikan skrip/fungsi Redis mereka sendiri. Langkahnya:
- Tetap gunakan `DATA_DRIVER=redis`, lalu ganti `REDIS_HOST`, `REDIS_PORT`, `REDIS_USERNAME`, `REDIS_PASSWORD`, dan `REDIS_DB` sesuai yang diberikan.
- Koneksi harus melalui jaringan aman (TLS/VPN). Redis tidak boleh terbuka bebas di internet.
- Jika mereka memakai logika checkout sendiri (misalnya Redis Function), ganti pemanggilan `eval(LuaScripts::CHECKOUT…)` di `RedisLocalAdapter::checkout()` dengan fungsi mereka.

## 5. Yang sudah dan belum

| Item | Status |
|---|---|
| Antarmuka `CentralDataService` | Sudah |
| Adapter Redis lokal + tes | Sudah, teruji di lingkungan pengembang |
| Adapter HTTP pusat (kerangka, pemetaan endpoint, penanganan error) | Sudah dibuat, **belum diuji ke server nyata** |
| Endpoint, kredensial, format respons pusat | **Menunggu Kelompok 6** |
| Uji 7 website memperebutkan stok yang sama | **Menunggu** layanan pusat + website teman |
