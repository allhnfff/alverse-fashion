# Panduan pengujian Alverse Bogor

Ada tiga jenis pengujian:
- **A. Otomatis (lapisan data):** `php tests/run-tests.php`. Memakai database Redis 15, data demo tidak tersentuh.
- **B. Otomatis (HTTP end-to-end):** `php tests/http-e2e.php`. Menguji dua website yang sedang berjalan.
- **C. Manual:** langkah yang perlu mata manusia atau restart Redis.

Catat hasil dengan format panduan: **ID, prasyarat, langkah, hasil diharapkan, hasil aktual, lulus/gagal, bukti, tanggal.**
Template tabel ada di bagian E. Laporan otomatis tersimpan di `tests/output/*.md`.

> Perintah `redis-cli` di bawah diketik di terminal tempat redis-cli tersedia (cara membukanya tergantung instalasimu, lihat `docs/INSTALASI.md`).
> Perintah `php ...` diketik di terminal yang terbuka di folder proyek.

---

## A. Tes otomatis lapisan data

```bash
php tests/run-tests.php
```
Hasil yang diharapkan: `15 lulus, 0 gagal`.

| ID | Uji wajib yang dicakup |
|---|---|
| T01 | (1) Redis PONG |
| T02 | (2) katalog dari sumber data yang benar |
| T03 | (3) keranjang tambah/ubah/hapus, stok tidak berubah |
| T04 | (4) stok 10 dibeli 1 → 9, tanpa key stok per cabang |
| T05 | (5) dua instance membaca stok yang sama |
| T06 | (6) 2 dan 10 proses checkout **bersamaan** (proses PHP terpisah) memperebutkan stok 1 → hanya 1 berhasil |
| T07 | (7) request_id diulang → stok dipotong sekali; isi berbeda ditolak |
| T08, T08b | (8) batal 2x → stok kembali sekali; batal setelah dikemas ditolak |
| T09 | (9) admin Bogor tidak bisa baca/ubah/batal pesanan cabang lain |
| T10 | (10) manipulasi jumlah/varian/harga ditolak atau dihitung ulang |
| T11 | (11) Redis mati → `DataServiceUnavailable` |
| T12 | alur status reguler & pickup, rekap hanya pesanan selesai |
| T13 | seed ulang tidak menimpa stok |
| T14 | (12, prasyarat) persistensi AOF/RDB aktif |

## B. Tes HTTP end-to-end (dua website)

1. **Terminal 1:** `php -S localhost:8001 -t public public/index.php`
2. **Terminal 2:** jalankan instance uji (lihat README bagian 4).
3. **Terminal 3:**
   ```bash
   # macOS / Linux / WSL / Git Bash
   ALVERSE_ADMIN_PASSWORD='password-admin-yang-kamu-buat' php tests/http-e2e.php
   ```
   ```powershell
   # Windows PowerShell
   $env:ALVERSE_ADMIN_PASSWORD = 'password-admin-yang-kamu-buat'; php tests/http-e2e.php
   ```
   Jika password admin uji berbeda, isi juga `ALVERSE_UJI_ADMIN_PASSWORD`. Password hanya diketik di terminalmu sendiri, **jangan** dikirim ke chat.

Hasil yang diharapkan: `11 lulus`. Pembelian uji dibatalkan lagi, jadi stok kembali ke angka awal.

---

## C. Tes manual

### C1. Redis PONG (uji 1)
```
redis-cli ping
```
Hasil yang diharapkan: `PONG`. Lalu jalankan `php scripts/check-redis.php`, yang harus menampilkan `PING -> PONG` dan `appendonly (AOF) : yes`.

### C2. Skenario demo stok bersama (uji 4 dan 5)
1. Buka `http://localhost:8001/produk/P037` di tab A dan `http://localhost:8002/produk/P037` di tab B. Pilih **Hitam**. Catat angkanya (contoh 10).
2. Di tab A (Bogor): tambah 1 ke keranjang → Checkout → **Pickup** → Konfirmasi pembayaran simulasi.
3. Halaman sukses menampilkan "Stok bersama terbaru: 9".
4. Tunggu ±5 detik **tanpa reload** di tab B: angka berubah menjadi 9.
5. Cek di redis-cli: `HGET inventory:stock:P037 adjustable_hitam` → `"9"`.
6. Pastikan tidak ada stok per cabang: `KEYS branch:*:stock:*` → `(empty array)`.
7. Di tab B (uji), beli 2 → kedua tab menjadi 7 setelah pembaruan.

> Ini **membuktikan integrasi lokal** (dua instance, satu Redis). Ini belum membuktikan koneksi ke website teman. Bukti itu baru bisa didapat setelah layanan pusat Kelompok 6 tersedia (bagian D).

### C3. Klik ganda checkout (uji 7)
Di halaman konfirmasi, klik tombol konfirmasi berkali-kali dengan cepat (atau tekan *Back* lalu kirim ulang form). Hasil yang diharapkan: hanya satu pesanan di **Pesanan saya**, dan stok hanya berkurang 1.

### C4. Pembatalan berulang (uji 8)
Login admin → buka pesanan berstatus Dikonfirmasi → isi alasan → **Batalkan**. Stok naik 1. Kirim ulang form (tombol *Back* lalu kirim lagi): muncul pesan "sudah dibatalkan sebelumnya", dan stok **tidak** naik lagi.

### C5. Akses pesanan cabang lain (uji 9)
1. Buat pesanan di website uji (8002), catat kodenya (`UJI-…`).
2. Login admin Bogor (8001), buka `http://localhost:8001/admin/pesanan/UJI-…` → **404**.
3. Buka `http://localhost:8001/pesanan/BGR-…` dari browser lain/jendela penyamaran → **404** (bukan milik sesi itu).

### C6. Manipulasi dari browser (uji 10)
Buka DevTools (F12) → tab Elements, lalu di halaman produk ubah input jumlah menjadi `1.5` atau `0` (hapus atribut `min`). Kirim. Hasil yang diharapkan: pesan error, dan keranjang tidak berubah. Tambahkan input tersembunyi `price=1` di form checkout. Total tetap dihitung dari harga master.

### C7. Gangguan Redis (uji 11)
1. Buka halaman produk P037.
2. Hentikan Redis (cara tergantung instalasi, lihat `docs/INSTALASI.md`).
3. Dalam ±5 detik halaman menampilkan **"Tidak dapat memperbarui stok"** beserta angka terakhir yang diketahui. Halaman **tidak** menampilkan "Stok 0".
4. Muat ulang halaman, maka muncul halaman "Sedang ada gangguan" (HTTP 503). Coba checkout: tidak ada pesanan yang dibuat dan tidak ada pesan sukses.
5. Nyalakan Redis lagi, dan angka kembali muncul.

### C8. Data tetap setelah restart (uji 12)
1. Catat stok P037 hitam dan satu kode pesanan.
2. Matikan Redis, lalu nyalakan lagi (atau restart komputer lalu jalankan Redis lagi).
3. `HGET inventory:stock:P037 adjustable_hitam` dan `HGET order:<kode> status` harus sama seperti sebelum restart.
Syaratnya: Redis dijalankan dengan `appendonly yes` (file `redis/redis-dev.conf`) dan folder datanya tidak terhapus.

### C9. Tampilan ponsel (uji 13)
Chrome DevTools → *Toggle device toolbar* (Ctrl+Shift+M). Uji lebar **360, 768, 1280 px** di halaman beranda, katalog (2/3/5 kolom), detail (satu kolom di ponsel), keranjang, checkout, pesanan, dan admin. Halaman tidak boleh bisa digeser ke samping. Tombol harus mudah ditekan (tinggi minimal 44 px).

### C10. Alur status lengkap
- Reguler: Dikonfirmasi → Tandai Dikemas → isi kurir + resi → Tandai Dikirim → Tandai Selesai. Pelanggan melihat kurir/resi di halaman pesanannya.
- Pickup: Dikonfirmasi → Tandai Siap Diambil → masukkan kode pickup dari halaman pelanggan → Tandai Selesai. Kode yang salah ditolak.
- Rekap (`/admin/rekap`): hanya pesanan Selesai yang dihitung, dan ongkir ditampilkan terpisah.

---

## D. Setelah layanan pusat Kelompok 6 tersedia
1. Set `DATA_DRIVER=central` (lihat `docs/INTEGRASI-PUSAT.md`), lalu buka `/api/status`.
2. Ulangi C2 bersama teman: beli dari Bogor, lalu lihat angkanya di website teman (dan sebaliknya).
3. Uji wajib 2: dua website berbeda membeli stok terakhir (fixture stok 1 dari Kelompok 6) secara bersamaan. Hanya satu yang berhasil.

---

## E. Template catatan hasil uji

| ID | Prasyarat | Langkah | Hasil diharapkan | Hasil aktual | Lulus/Gagal | Bukti | Tanggal |
|---|---|---|---|---|---|---|---|
| C2 | Redis jalan, seed, 2 instance | Beli 1 P037 hitam di Bogor | Pusat 10→9, kedua website 9 | | | screenshot / log | |
| C7 | Halaman produk terbuka | Matikan Redis | "Tidak dapat memperbarui stok" | | | screenshot | |

---

## F. Catatan hasil uji lokal — 7 November 2026

**Lingkungan:** laptop Hanif, Windows 11, PHP 8.2.12 (XAMPP), Redis 7.0.15 di WSL Ubuntu dengan AOF aktif.
Website Bogor berjalan di `http://localhost:8501` (`.env`, `branch_id=bogor`), instance uji di `http://localhost:8502` (`.env.uji`, `branch_id=uji`).
Keduanya memakai Redis `127.0.0.1:6380` database 0, dataset `demo-local`.
Port dan Redis berbeda dari bawaan README, lihat `docs/INSTALASI.md`.

**Cakupan:** ini integrasi **lokal** (dua instance, satu Redis). Integrasi dengan website teman dan layanan pusat Kelompok 6 **belum diuji** (bagian D).

Keterangan kolom "Bukti":
- **Manual:** diamati di browser oleh Hanif.
- **Redis:** dicek dengan `redis-cli` secara read-only.
- **Kode:** fungsi adapter dipanggil langsung secara read-only, tanpa HTTP.

| ID | Prasyarat | Langkah | Hasil diharapkan | Hasil aktual | Lulus/Gagal | Bukti |
|---|---|---|---|---|---|---|
| L1 | Redis WSL jalan | `php scripts/check-redis.php` | PONG, AOF yes | PONG, Redis 7.0.15, `appendonly yes`, data di `/var/lib/redis` | Lulus | Terminal |
| L2 | L1 | `seed-demo.php --dry-run`, lalu seed | 42 produk, tidak menimpa data | Dry run: 42 produk / 420 varian baru / 0 dipertahankan. Seed: sama, dataset `demo-local` | Lulus | Terminal |
| C2a | Seed, 8501 jalan | Beli 1 P037 `adjustable_hitam` di Bogor (pickup) | Stok 10 → 9 | `BGR-261107-CAAD87` dibuat, stok 9 | Lulus | Manual + Redis (stream `inventory:movements`: `sale`, `after=9`) |
| C4a | C2a | Admin Bogor membatalkan `BGR-261107-CAAD87` | Status Dibatalkan, stok kembali 10 | Status `dibatalkan`, `restocked=1`, stok 10 | Lulus | Manual + Redis (`cancel_restock`, `after=10`) |
| C2b | 8501 & 8502 jalan | Buka P037 Hitam di kedua website | Keduanya 10 | Keduanya 10 | Lulus | Manual + API `/api/stok/P037` |
| C2c | C2b | Beli 1 P037 Hitam lewat website uji (reguler) | Pesanan `UJI-…`, stok global 9 | `UJI-261107-598QVC`, `branch_id=uji`, status `dikonfirmasi`, reguler, subtotal 79.000 + ongkir 10.000 = **89.000** | Lulus | Manual + Redis |
| C2d | C2c | Amati kedua tab | Keduanya menjadi 9 setelah pembaruan | Keduanya 9 | Lulus | Manual |
| C2e | C2c | Cek stok per cabang di Redis | Hanya `inventory:stock:P037`, tidak ada key stok per cabang | `HGET inventory:stock:P037 adjustable_hitam` = 9. `--scan branch:*:stock*` kosong | Lulus | Redis |
| C5a | C2c | Daftar pesanan admin uji vs admin Bogor | UJI hanya di admin uji | Admin uji: `UJI-261107-598QVC`. Admin Bogor: hanya `BGR-261107-CAAD87`. Redis: `branch:uji:orders` = {UJI-…}, `branch:bogor:orders` = {BGR-…} | Lulus | Manual + Redis |
| C5b | C2c | `getOrder('UJI-261107-598QVC')` dengan akses `branch_admin` bogor | `null` → controller 404 | `null`. Kontrol: akses uji → ditemukan; Bogor → `BGR-…` ditemukan; ID tak ada → `null` | Lulus | Kode |
| C5c | C2c, login admin Bogor | Buka `http://localhost:8501/admin/pesanan/UJI-261107-598QVC` di browser | HTTP 404 | Halaman "KODE 404, Pesanan tidak ditemukan di cabang Bogor Kota." Pesan ini hanya muncul setelah lolos cek login admin; tanpa login, URL yang sama diarahkan (303) ke `/admin/login` | Lulus | Manual (screenshot 13:40) |

### F2. Tes otomatis lapisan data — `php tests/run-tests.php` (7 November 2026, 13:45 WIB)

**Pemeriksaan keamanan sebelum tes:**
- Tidak ada variabel lingkungan yang menimpa `.env`. Konfigurasi runtime: `REDIS_DB=0`, `REDIS_TEST_DB=15`, `DATA_DRIVER=redis`, Redis `127.0.0.1:6380`.
- Semua klien tes, termasuk worker T06, dibuat dengan `test_database`. `CLIENT INFO` klien tes menunjukkan `db=15`. Satu-satunya `flushdb()` ada di klien database 15. T11 sengaja memakai port 1 (tanpa server), sehingga tidak ada yang bisa ditulis.
- Database 15 sebelum tes: 0 key, tanpa penanda dataset (kosong).
- Snapshot database 0 sebelum tes: 101 key, P037 `adjustable_hitam 9 / navy 4 / cream 4`, 3 catatan `inventory:movements`, dataset `demo-local`, sidik isi `83b1f15857a52403`.

**Hasil: 15 lulus, 0 gagal, 0 dilewati** (laporan lengkap: `tests/output/hasil-tes-data.md`, tidak masuk Git).

| ID | Hasil | Catatan aktual |
|---|---|---|
| T01 | Lulus | PING → PONG |
| T02 | Lulus | 42 produk, P037 Rp79.000, driver redis |
| T03 | Lulus | Stok tetap 5 selama operasi keranjang |
| T04 | Lulus | Stok 10 → 9, tidak ada `branch:*:stock:*` |
| T05 | Lulus | Dua instance: total persediaan 10, bukan 20 |
| T06 | Lulus | 2 proses: 1 sukses, 1 ditolak. 10 proses: 1 sukses, 9 ditolak. Stok tidak negatif |
| T07 | Lulus | 3× kirim → 1 pesanan, stok 5 → 3. Isi berbeda dengan token sama ditolak |
| T08 | Lulus | Stok 7 → 8 → tetap 8 |
| T08b | Lulus | `NOT_CANCELLABLE` setelah dikemas, alasan wajib |
| T09 | Lulus | Admin Bogor: baca/ubah status/batal pesanan uji → `ORDER_NOT_FOUND` |
| T10 | Lulus | qty 0/-1/1.5/1e2 ditolak, harga palsu diabaikan, total Rp168.000 |
| T11 | Lulus | `DataServiceUnavailable` saat Redis tidak dapat dihubungi |
| T12 | Lulus | Rekap Bogor 2 pesanan, 3 unit, Rp647.000 + ongkir Rp10.000 |
| T13 | Lulus | Seed ulang tidak menimpa stok, dataset berbeda ditolak |
| T14 | Lulus | `appendonly=yes`, `save="3600 1 300 100 60 10000"` |

**Pemeriksaan sesudah tes:**
- Database 0 **identik** dengan snapshot sebelum tes (101 key, sidik isi `83b1f15857a52403`, stok P037 tetap 9/4/4).
- Database 15 berisi 141 key bertanda `test`. Data ini milik tes dan akan dikosongkan lagi pada tes berikutnya.

Catatan: T04–T12 berjalan di database 15 lewat adapter, bukan lewat HTTP. Isolasi admin cabang lewat HTTP sudah dibuktikan terpisah di C5c (baca detail). Ubah status dan pembatalan lintas cabang lewat HTTP belum diuji, tetapi lapisan datanya lulus di T09.

### F3. Tes HTTP, alur status, gangguan Redis, restart, dan tampilan, 8 November 2026, 20:12–20:17 WIB

**Lingkungan terisolasi:** dua website sementara, yaitu Bogor `localhost:8511` dan uji `localhost:8512`. Konfigurasinya sementara (`.env.e2e`, `.env.e2e-uji`) dengan Redis `6380` **database 14** dan password admin acak sementara. Database 14 kosong sebelum di-seed dan dikosongkan lagi sesudah tes. File konfigurasi dan password sementara sudah dihapus. Kode yang diuji sama dengan website 8501; yang berbeda hanya database dan port.
Database 0 (data demo) tidak tersentuh: sidik isi `83b1f15857a52403` sama sebelum dan sesudah seluruh rangkaian.

**`php tests/http-e2e.php --bogor=http://localhost:8511 --uji=http://localhost:8512`: 11 lulus, 0 gagal, 0 dilewati**

| ID | Hasil | Catatan aktual |
|---|---|---|
| H01 | Lulus | Kedua website memakai Redis 127.0.0.1:6380 db 14 |
| H02 | Lulus | 42 kartu produk, P037 Rp79.000 |
| H03 | Lulus | Keranjang tambah/ubah/hapus, stok navy tetap 4 |
| H04 | Lulus | `BGR-261108-ZM8WPS`: stok 10 → 9 di kedua website |
| H05 (C3) | Lulus | Konfirmasi dikirim ulang → tetap satu pesanan, stok tidak dipotong lagi |
| H06 (C6) | Lulus | qty 1.5/0/-2/abc, varian palsu, dan POST tanpa CSRF ditolak |
| H06b (C6) | Lulus | Field harga/ongkir palsu diabaikan, `UJI-261108-2SEYFR` total Rp168.000 |
| H07 | Lulus | Sesi lain mendapat 404 untuk pesanan orang lain |
| H08 | Lulus | Admin Bogor: detail & pembatalan pesanan uji → 404, tidak tampil di daftar |
| H09 | Lulus | Batal dua kali → stok 7 → 8, batal kedua tidak menambah |
| H10 | Lulus | Admin uji membatalkan pesanannya, stok kembali ke 10 |

**Uji tambahan lewat HTTP (skrip di luar proyek, memakai kelas `Browser` dari `tests/lib/TestKit.php`): 4 lulus, 0 gagal**

| ID | Hasil | Catatan aktual |
|---|---|---|
| C10a | Lulus | Reguler `BGR-261108-4U5J5W`: loncat ke Selesai ditolak ("Perubahan status tersebut tidak diizinkan"). Klik ganda Dikemas → info "tidak ada perubahan ganda". Dikirim tanpa kurir ditolak. Dengan kurir+resi → pelanggan melihat "Kurir Demo"/"DEMOC10". Selesai, lalu pembatalan setelah selesai ditolak |
| C10b | Lulus | Pickup `BGR-261108-59YUXW`: pelanggan melihat kode 6 digit, admin tidak. Siap Diambil. Kode salah ("Kode pickup tidak cocok") dan format salah ditolak. Kode benar → Selesai |
| C10c | Lulus | Rekap Bogor: 2 pesanan selesai / 2 unit / Rp158.000 / ongkir Rp10.000 terpisah. Pesanan batal tidak dihitung. Rekap uji: 0 |
| X01 | Lulus | Admin Bogor POST ubah status pesanan `UJI-261108-E7JVFH` → 404. Status tetap Dikonfirmasi, stok tetap 7 |

**C7 — gangguan Redis**
- *Server:* website sementara `localhost:8513` sengaja diarahkan ke Redis yang tidak ada (port 1). Hasilnya:
  - `/`, `/katalog`, `/produk/P037`, `/keranjang` → **503** "Sedang ada gangguan" (sebelum F12: "Layanan data tidak tersedia").
  - `/api/stok/P037` → 503 `{"ok":false,"message":"Tidak dapat memperbarui stok"}`.
  - POST `/checkout/konfirmasi` → 503, tanpa pesan sukses.
  - `/cabang` dan `/admin/login` tetap 200 karena tidak butuh data.
  - **Lulus.**
- *Browser (Chrome headless lewat DevTools Protocol):* halaman produk P037 dibiarkan terbuka di 8511. Saat request `/api/stok` dialihkan ke 8513, dalam ±5 detik tanpa reload tampil "Tidak dapat memperbarui stok" dan "Angka terakhir yang diketahui: 5 (pukul 20:15:24 WIB)…". Halaman tidak menampilkan "Stok 0". Setelah API normal lagi, angka kembali tanpa reload. **Lulus.**
- Catatan: Redis asli tidak dihentikan untuk C7, karena systemd menyalakannya lagi dalam 0,1 detik (`Restart=always`). Jika ingin melihatnya langsung, jalankan `sudo systemctl stop redis-server` di Ubuntu, amati halaman, lalu `sudo systemctl start redis-server`.

**C2 — pembaruan otomatis tanpa reload:** halaman produk terbuka di Chrome headless (tab `visible`). Stok `adjustable_hitam` di db 14 diubah 7 → 5 dan tampilan berubah menjadi 5 dalam ≤7 detik tanpa reload. **Lulus.**

**C8 — data tetap setelah Redis restart:**
- `redis-cli -p 6380 shutdown` membuat Redis menyimpan data lalu berhenti. systemd menyalakannya lagi dalam ±0,5 detik: PID 1171 → 8170, `NRestarts=1`, AOF aktif.
- Isi database 0 (sidik `83b1f15857a52403`) dan database 14 (sidik `03659d5ae7cc0710`) **identik** sebelum dan sesudah restart. Website 8501 langsung normal lagi.
- **Lulus.** Restart laptop belum diuji, tetapi data tersimpan di `/var/lib/redis` dengan AOF, jadi mekanismenya sama.

**C9 — tampilan (Chrome headless):**
- Diuji di lebar 360×800 (mode HP), 768×1024, dan 1280×900. Halamannya: beranda, katalog, produk, cabang, pesanan, keranjang (berisi 1 item), checkout, serta admin dashboard/pesanan/stok/rekap.
- **Tidak ada halaman yang bisa digeser ke samping** di ketiga lebar.
- Kolom katalog 360/768/1280 = **2/3/4**. Halaman produk satu kolom di HP.
- **Lulus**, dengan catatan untuk desain ulang:
  - Di lebar 360, menu atas terpotong ("Info Cabang") tanpa tanda bahwa menu bisa digeser.
  - Logo 34 px, tautan breadcrumb dan nama produk di keranjang 20–22 px (tautan teks).
  - Radio metode checkout 18 px, tetapi seluruh kotak labelnya (±50 px) bisa diklik.
  - Tombol utama ≥ 44 px.
  - Halaman login admin di 360 tercatat saat sudah login (diarahkan ke dashboard), jadi form login belum diukur terpisah.

**Belum diuji (per 8 November 2026):**
- Restart laptop (C8 versi penuh) dan penghentian Redis asli lewat `systemctl` (C7 versi penuh). Opsional, karena mekanismenya sudah teruji.
- Klik ganda tombol konfirmasi di browser sungguhan (C3 versi manual). Kirim ulang form sudah lulus di H05.
- Integrasi dengan website teman dan layanan pusat Kelompok 6 (bagian D). Ini menunggu Kelompok 6.

### F4. Uji ulang setelah desain ulang tampilan, 9 November 2026, 19:49–19:54 WIB

**Yang berubah:**
- Tampilan semua halaman pelanggan dan admin, mengikuti rancangan "Kebun Raya": hijau tua, krem, aksen oker, judul Fraunces, isi Inter.
- Fitur baru **Urutkan** di katalog (`?urut=`): kode produk, harga termurah, harga termahal, nama A–Z. Fitur ini hanya mengurutkan tampilan; nilai yang tidak dikenal kembali ke urutan kode.
- Data tampilan tambahan di `ShopController`: harga "mulai" per kategori, produk sejenis di halaman produk, dan daftar tujuh cabang dengan kodenya.
- Tidak ada perubahan pada Redis, skrip Lua, alur checkout/pembatalan, CSRF, atau aturan keamanan. Gambar produk tetap ilustrasi per kategori.

**Lingkungan:** sama seperti F3. Server sementara 8511/8512/8513 memakai Redis `6380` **database 14**, di-seed dari kosong, dengan password admin acak sementara. Setelah tes, server dihentikan, `.env.e2e*` dan password dihapus, dan database 14 dikosongkan (0 kunci). Database 0 tidak tersentuh: sidik `83b1f15857a52403` sama sebelum dan sesudah tes.

| Uji | Hasil |
|---|---|
| `php tests/run-tests.php` (database 15) | 15 lulus, 0 gagal, 0 dilewati |
| `php tests/http-e2e.php` (8511/8512, db 14) | 11 lulus, 0 gagal, 0 dilewati: H02 tetap 42 kartu produk, H03 keranjang tambah/ubah/hapus lewat form baru, H04–H10 lulus |
| C10a/b/c + X01 (alur status admin, rekap, ubah status lintas cabang) | 4 lulus, 0 gagal |
| Urutkan katalog | Harga termurah dimulai Rp59.000 (Hijab Voal Polos); termahal dimulai Trench Coat; nama dimulai Bucket Hat. Semua urutan tetap 42 produk. `urut` palsu → urutan kode, 42 produk |
| C2 (Chrome headless) | Lulus: stok berubah 6 → 4 tanpa reload |
| C7 (browser) | Lulus: "Tidak dapat memperbarui stok" + angka terakhir, lalu pulih sendiri |
| Checkout lewat form di browser | Lulus: isi data → tinjau → konfirmasi → halaman pesanan `BGR-…` |
| C9 tampilan 360/768/1280 | Lulus: tidak ada halaman yang bisa digeser ke samping, termasuk detail pesanan pelanggan dan admin. Kolom katalog 2/3/4; baris rekomendasi 6 kolom di 1280 |

**Catatan C9 setelah desain ulang:**
- Menu atas yang terpotong di 360 px sudah tidak ada. Di bawah 900 px, menu pindah ke bar bawah: Beranda, Katalog, Keranjang, Pesanan, Cabang, masing-masing setinggi 64 px.
- Tombol − / + jumlah, pilihan ukuran/warna, tombol utama, dan menu bawah semuanya ≥ 44 px.
- Yang masih di bawah 44 px hanya tautan teks: breadcrumb (20 px), logo (36 px), tombol cari (40 px), dan nama produk di keranjang.
- Tangkapan layar diperiksa langsung. Dua masalah ditemukan dan diperbaiki sebelum uji akhir: kotak jumlah di halaman produk melebar, dan label garis status pesanan tidak sejajar.

### F5. Audit teks dan tampilan (antislop), 9 November 2026, 20:20–20:30 WIB

**Yang diperbaiki:**
- Tanda pisah panjang di judul tab browser, riwayat status admin, label sumber data pusat, sel stok kosong di admin, dan nilai bawaan alamat/kontak demo (`config/app.php`, `.env.example`).
- Penjelasan stok bersama dan ongkir yang diulang di banyak tempat dipangkas. Istilah teknis untuk pembeli ("server", "harga master", "request_id") diganti bahasa biasa.
- Teks yang tidak tepat dikoreksi:
  - "Pilihan untuk kamu" diganti "Pilihan cabang Bogor Kota", karena daftarnya tetap dan tidak dipersonalisasi.
  - "Foto resmi menyusul dari pusat" diganti "Gambar ilustrasi kategori, belum foto produk asli".
  - Banner Busana Muslim kini menyebut kemeja koko juga.
- Pola template dihapus:
  - Label kecil di atas judul halaman Cabang.
  - Langkah pickup berbulatan nomor, diganti daftar biasa yang memakai label status asli.
  - Label "Pickup Rp0" yang sama di semua 42 kartu produk.
  - Banner "Stok bersama" ganda, diganti tautan "Cek pesanan".
  - Footer yang mengulang info ongkir.
- Ikon kategori di beranda dipotong supaya teks kecil di gambar ilustrasi tidak terpotong setengah.

**Uji ulang (db 14, server sementara 8511/8512/8513, dibersihkan sesudahnya; sidik db 0 tetap `83b1f15857a52403`):**
- `run-tests.php`: 15 lulus, 0 gagal.
- `http-e2e.php`: 11 lulus, 0 gagal.
- C10/X01: 4 lulus, 0 gagal.
- Browser (C2, C7, checkout, C9): lulus.
- Uji klik kontrol baru: 9 lulus.
  - Cari di header → `/katalog?q=linen` (2 produk).
  - Urutkan otomatis dengan kata kunci tetap.
  - Kategori sidebar dengan urutan tetap.
  - − / + di produk, lalu tambah ke keranjang (lencana 2).
  - − / + di keranjang tersimpan otomatis (Rp447.000).
  - Hapus item.
  - Lima menu bawah di 360 px (64 px, semua terbuka).
  - Tab keyboard dimulai dari "Lewati ke konten", fokus terlihat.

**Belum:** alamat dan kontak demo di `.env` utama masih memakai tanda pisah panjang. File itu milik pemilik proyek dan belum diubah.

### F6. Beranda gaya marketplace dari rancangan canvas, 9 November 2026, 22:50–23:00 WIB

**Yang dibuat:**
- **Header baru di semua halaman pelanggan:**
  - Bar atas berisi Info Cabang dan Lacak Pesanan.
  - Tombol **Kategori** membuka menu produk per kategori, lengkap dengan ringkasan jumlah model, rentang harga, ukuran, dan ongkir.
  - Saran **Coba cari**.
  - **Keranjang mini** yang terbuka dari ikon keranjang.
  - Di bawah 900 px, bar atas, Kategori, dan Coba cari disembunyikan. Ikon keranjang tetap membuka halaman keranjang, dan menu bawah tetap dipakai.
- **Beranda:**
  - Tiga banner yang digeser manual dengan panah dan titik (tidak berputar sendiri).
  - Dua banner samping dan lima info layanan.
  - Kategori dan 42 produk diselang-seling per kategori. 18 produk tampil langsung, sisanya lewat tombol **Lihat 24 produk lainnya**.
  - Jendela **Lihat cepat** (`<dialog>`) untuk memilih warna, ukuran, dan jumlah dengan stok asli dari `/api/stok/{id}`.
- **Footer terang 4 kolom** di semua halaman pelanggan.
- **Server:**
  - `Controller::render` menyiapkan data keranjang mini dan menu Kategori. Data dibaca saja, dan bernilai null jika Redis gangguan.
  - `CartController::add` menerima `kembali=beranda`. Hanya nilai tetap itu yang diterima, lalu diarahkan ke `/?keranjang=baru`. Validasi, CSRF, dan stok tidak berubah.
- **Penyesuaian dari canvas, alasannya dicatat:**
  - Judul grid "Rekomendasi untuk kamu" diganti "Produk Alverse Bogor Kota" karena daftarnya tidak dipersonalisasi.
  - Label "Bisa pickup" di setiap kartu tidak dipasang karena sama di semua produk.
  - Stok di Lihat cepat memakai angka asli, bukan angka contoh.
  - Gambar tetap ilustrasi kategori sesuai keputusan pemilik.

**Uji (db 14, server sementara 8511/8512/8513, dibersihkan; sidik db 0 tetap `83b1f15857a52403`):**
- `run-tests.php`: 15 lulus.
- `http-e2e.php`: 11 lulus.
- C10/X01: 4 lulus.
- Browser C2, C7, checkout, C9: lulus. Tidak ada halaman yang bisa digeser ke samping di 360/768/1280.
- Uji klik halaman lain: 9 lulus.
- Uji klik beranda baru: 13 lulus.
  - Menu Kategori: buka, hover, Escape mengembalikan fokus, tautan produk.
  - Coba cari "Hoodie" menghasilkan 2 produk.
  - Banner: maju, kembali ke awal, titik, dan tetap diam 6 detik.
  - Lihat lainnya: 18 → 42 kartu.
  - Keranjang mini kosong, lalu ditutup dengan Escape.
  - Lihat cepat P037: stok hitam/navy sama dengan API. Tambah 2 navy → kembali ke beranda dengan panel "Baru ditambahkan" dan lencana 2. Stok tidak dipotong. Panel tidak terbuka lagi saat halaman dimuat ulang.
  - Ponsel 360.
  - Urutan Tab.
  - Konsol tanpa error JavaScript.

### F7. Gambar produk dari canvas desain (gambar AI), 10 November 2026

**Yang dipasang:**
- **Gambar produk:** 113 file di `public/assets/img/produk/foto/{ID}-{kode warna}.jpg`, satu per warna untuk semua 42 produk. Ukurannya 800 × 800 dan totalnya sekitar 7,6 MB.
  - Nama warnanya dicocokkan otomatis dengan `config/products.php`: 113 cocok, tidak ada yang kurang atau berlebih.
- **Banner:** 3 file di `public/assets/img/banner/` (pickup, stok-bersama, busana-muslim), ukuran 1600 × 900.
- **Pemilik menyetujui** pemasangan semua gambar, termasuk motif naga emas.
- **Data Redis tidak diubah:** `image_url` di Redis tetap SVG kategori. Gambar dipilih di lapisan tampilan oleh helper:
  - `product_image()`: jika file warna tidak ada, dipakai warna pertama, lalu `image_url` dari data.
  - `category_image()`, `variant_color()`, dan `image_note()`.
- **Gambar ikut warna di:**
  - halaman produk (`data-img` pada pilihan warna)
  - Lihat cepat
  - keranjang dan keranjang mini (dari kode varian)
- **Bingkai gambar** diubah dari 4:5 ke 1:1, mengikuti canvas.
- **Banner foto** diberi lapisan hijau gelap di sisi teks, dan lapisan yang lebih rata di bawah 640 px.
- **Label jujur:**
  - alt gambar "(gambar AI)"
  - keterangan "Gambar dibuat dengan AI, bukan foto produk asli."
  - footer "Gambar produk dibuat dengan AI, bukan foto asli."

**Uji (db 14, server sementara 8511/8512/8513, dibersihkan; sidik db 0 tetap `83b1f15857a52403`):**
- `run-tests.php`: 15 lulus.
- `http-e2e.php`: 11 lulus.
- C10/X01: 4 lulus.
- Uji klik: 9 lulus. Uji browser C2/C7/checkout/C9: lulus. Uji beranda: 13 lulus.
- Uji foto: 10 lulus.
  - 116 file terkirim sebagai `image/jpeg`.
  - 49 gambar beranda termuat, tanpa SVG lama.
  - Foto 3 banner termuat, dengan teks di atas lapisan gelap.
  - Halaman produk P002: Hitam → Sage. Lihat cepat P025: Navy → Sage. Keranjang menampilkan P002 putih.
  - Ikon menu Kategori memakai foto.
  - Tidak ada geser samping di 360 dan 768.
  - Konsol tanpa error.
- Gambar lazy di 360 termuat setelah digulir (18 dari 18).

### F8. Penyesuaian beranda dari canvas versi terbaru, 10 November 2026

**Yang berubah, mengikuti canvas "Beranda gaya marketplace":**
- **Banner samping kedua:** dulu "Pengiriman / Reguler Rp10.000", sekarang "Sepatu & Sandal / 7 model / Mulai Rp129.000, dari sneakers sampai loafers". Jumlah model dan harga termurah dihitung dari data, dan banner ini mengarah ke `/katalog?kategori=sepatu-sandal`.
- **Strip layanan:** dari 5 menjadi 4 item. "Pickup Rp0" dan "Stok bersama 7 cabang" dihapus karena sudah ada di banner. Yang tersisa: Reguler, Kode pickup 6 angka, Status pesanan bisa dilacak, dan pembatalan.
- **Satu penyesuaian dari canvas, alasannya dicatat:** canvas menulis "Bisa batal selama status Dikonfirmasi". Di website ini, yang membatalkan adalah admin cabang (`OrderRules::canCancel`, rute `/admin/pesanan/{id}/batal`), bukan pembeli. Karena itu teksnya ditulis "Bisa dibatalkan cabang selama Dikonfirmasi".

**Uji (db 14, server sementara, dibersihkan; db 0 tidak disentuh):**
- run-tests: 15 lulus.
- http-e2e: 11 lulus.
- C10: 4 lulus.
- Uji klik: 9 lulus.
- Uji browser: 6 lulus.
- Uji beranda: 13 lulus.
- Uji foto: 10 lulus.

### F9. Fitur dari wireframe (`Wireframe-Alverse-Bogor-Kota.pdf`), 10 November 2026

**Fitur yang ditambahkan, per halaman wireframe:**
- **Halaman produk:**
  - Ukuran yang stoknya 0 diberi tanda "habis" dan tidak bisa dipilih. Jika ukuran yang sedang dipilih habis, pilihan pindah ke ukuran yang masih ada. Warna yang semua ukurannya habis juga ditandai. Semua ini mengikuti angka stok yang diperbarui tiap 5 detik.
  - Tombol "Lihat keranjang" sebaris dengan "Masukkan keranjang".
  - Di ponsel (di bawah 900 px):
    - Bar beli menempel di bawah layar, dan menu bawah disembunyikan di halaman ini.
    - Baris ringkasan "Variasi" dan "Stok bersama" membuka lembar variasi dari bawah. Lembar ini berupa dialog dengan fokus terkunci, dan bisa ditutup dengan Escape, latar gelap, atau tombol Tutup.
    - Tanpa JavaScript, form biasa tetap tampil.
- **Katalog:**
  - 5 kolom di layar 1100 px ke atas. Kolomnya 2/3/5 di 360/768/1280 px; langkah uji C9 di bagian C sudah disesuaikan.
  - 20 produk tampil dulu, sisanya lewat tombol "Tampilkan N produk lainnya". Semua 42 kartu tetap ada di HTML, sehingga tanpa JavaScript semua produk terlihat.
  - Di ponsel, kategori samping menjadi deretan chip yang bisa digeser ke samping.
- **Checkout:** pesan error tampil di bawah kolom yang salah (nama, nomor, alamat, catatan, cara terima), memakai `aria-invalid` dan `aria-describedby`. Ringkasan jumlah kolom yang salah tetap tampil di atas.
- **Detail pesanan pembeli:** pembeli bisa membatalkan pesanannya sendiri selama status masih Dikonfirmasi (`POST /pesanan/{id}/batal`).
  - Alasan harus dipilih dari daftar tetap. Nilai lain ditolak server.
  - Hanya sesi pemilik pesanan yang bisa membatalkan. Sesi lain mendapat 404.
  - Proses ini memakai skrip pembatalan yang sama dengan admin (`cancelOrder`), jadi stok bersama dikembalikan tepat satu kali walaupun form dikirim ulang.
  - Riwayat mencatat "Oleh pembeli: {alasan}".
- **Login admin:** tombol Lihat/Sembunyikan password (`aria-pressed`). Tombol ini hanya muncul jika JavaScript aktif.
- **Daftar pesanan admin:** 20 pesanan per halaman, dengan teks "N pesanan sesuai filter, halaman X dari Y". Nomor halaman tetap membawa filter cari, status, dan metode. `?hal=` di luar batas diarahkan ke halaman terakhir.

**Perbaikan ikutan:**
- Riwayat status dulu bisa tertulis "Dibatalkan: Dibatalkan: …". Helper `history_note()` sekarang membuang awalan yang dobel, di halaman pembeli dan admin.
- Kartu produk tambahan ditandai atribut `data-extra`, bukan kelas baru. Dengan begitu `class="product-card"` tetap muncul 42 kali, sesuai yang dicek tes H02.
- Item pembatalan dihapus dari strip layanan beranda atas permintaan pemilik. Pembeli sudah biasa bisa membatalkan pesanan di marketplace, dan tombolnya sudah ada di halaman pesanan. Strip sekarang 3 item (Reguler, Kode pickup, Status bisa dilacak) dalam 3 kolom di semua lebar layar. Sudah dicek di 360/768/1280 px: 1 baris, tanpa geser samping.

**Uji (db 14, server sementara 8511/8512/8513, dibersihkan; sidik db 0 tetap `83b1f15857a52403`):**
- run-tests: 15 lulus.
- http-e2e: 11 lulus.
- C10/X01: 4 lulus, setelah db 14 diisi ulang.
  - Pada putaran gabungan kedua, C10c sempat gagal karena tes ini menghitung tepat 2 pesanan selesai, sedangkan db 14 sudah berisi 4 dari putaran sebelumnya. Setelah db 14 dikosongkan dan diisi ulang, hasilnya "Bogor: 2 / 2 / Rp158.000 / Rp10.000".
- Uji klik: 9 lulus. Uji browser: 6 lulus. Uji beranda: 13 lulus. Uji foto: 10 lulus. Uji gulir: lulus.
- Uji wireframe: 12 lulus.
  - Ukuran habis terkunci dan pindah otomatis.
  - Tombol Lihat keranjang.
  - Bar beli dan lembar variasi di ponsel 390 px, termasuk Escape dan kembalinya fokus.
  - Tambah ke keranjang dari lembar, tanpa memotong stok.
  - Chip kategori dan katalog 5 kolom.
  - Error per kolom di checkout.
  - Pembeli membatalkan pesanan: stok 4 → 2 → 4, dan pengiriman ulang tetap 4.
  - Lihat password dan nomor halaman admin.
  - Konsol tanpa error, dan tidak ada geser samping di 360 px.

### F10. Merapikan teks untuk pembeli (antislop), 10 November 2026

**Permintaan pemilik:** spesifikasi dibuat seperti listing penjual pada umumnya, dan penjelasan yang tidak perlu bagi pembeli dihapus. Data Redis tidak diubah. Semua perubahan ada di tampilan dan pesan.

**Halaman produk:**
- Spesifikasi berisi Kategori, Merek, Ukuran, Warna, Kode produk, dan Dikirim dari.
- Ukuran diambil dari data master, misalnya "S, M, L, XL, XXL", "EUR 38–44", atau "22 × 14 × 7 cm". Tidak ada ukuran badan (cm) yang dikarang.
- Bagian Deskripsi disembunyikan selama isinya masih "[Deskripsi demo]…", karena teksnya hanya mengulang spesifikasi. Bagian ini muncul lagi setelah pusat mengirim deskripsi asli.
- Pengiriman dipendekkan jadi "Reguler Rp10.000 · Pickup di cabang Rp0".
- Kotak pickup menjadi kartu toko "Alverse Bogor Kota" dengan tombol "Info cabang".
- Kalimat tentang cara kerja stok di bawah angka stok dan tombol keranjang dihapus. Angka stok dan waktu pembaruannya tetap tampil.

**Keranjang, checkout, konfirmasi, pesanan:**
- Dihapus: catatan keranjang per cabang, catatan ongkir di keranjang, "Stok bersama baru dipotong…", "stok sudah dipotong satu kali", dan alasan panjang di petunjuk pickup.
- Label "simulasi" cukup satu kali per halaman.
- Harga item ditulis "2 × Rp79.000", tidak lagi "× 2 @ Rp79.000".
- Kotak batal hanya berisi "Pesanan yang dibatalkan tidak bisa diaktifkan lagi."
- Pesan "request_id yang sama…" diganti kalimat biasa.
- Riwayat pesanan pembeli memakai `customer_history_note()`. Contohnya, "Pembayaran simulasi berhasil; stok global dipotong." tampil sebagai "Pembayaran berhasil.", dan "barang demo" tidak muncul lagi. Catatan asli di data dan halaman admin tidak berubah.
- Kalimat "Stok bersama sudah dikembalikan" pada pesanan batal hanya tampil di admin.

**Header dan footer:**
- Bar atas jadi "Alverse cabang Bogor Kota".
- Footer tidak lagi menampilkan "Sumber data: Redis…". Sumber data tetap tampil di halaman admin dan `/api/status`.
- Pesan katalog kosong tidak lagi menyuruh menjalankan `php scripts/seed-demo.php`.

**Tetap dipertahankan:**
- Angka stok di produk, keranjang, dan setelah pembelian, karena menjadi bukti stok bersama.
- Penjelasan stok bersama di halaman Info Cabang.
- Label gambar AI.
- Label jam demo dan alamat demo.
- Pesan batal admin, karena dicek http-e2e.

**Uji (db 14, server sementara 8511/8512/8513, dibersihkan; sidik db 0 tetap `83b1f15857a52403`):**
- run-tests: 15 lulus. http-e2e: 11 lulus. C10/X01: 4 lulus.
- Uji klik: 9 lulus. Uji browser: 6 lulus. Uji beranda: 13 lulus. Uji foto: 10 lulus. Uji wireframe: 12 lulus. Uji gulir: lulus.
- Strip layanan 3 item: 1 baris di 360/768/1280 px.

**Tambahan: panduan ukuran (atas persetujuan pemilik)**
- `config/size-guide.php` berisi ukuran standar yang umum di toko pakaian Indonesia, karena daftar harga Alverse belum memuat ukuran cm. Ukuran resmi dari pusat cukup diganti di file ini.
- Tabel tampil di bawah Spesifikasi untuk 31 produk:
  - kaus, oversized, kemeja/koko, hoodie/sweater, jaket, dan mantel
  - celana nomor 28–36, jogger, kulot, dan rok
  - gamis dan tunik
  - sepatu EUR 38–44 (panjang telapak kaki)
- Hijab, pashmina, tas, topi, dan sabuk tidak diberi tabel, karena ukurannya sudah berupa cm atau adjustable.
- Baris "Ukuran" di spesifikasi punya tautan "Panduan ukuran". Judul tabel tidak tertutup header saat tautan diklik.
- Catatan di bawah tabel: "Dalam cm, diukur dalam posisi mendatar. Toleransi 1–2 cm." Untuk sepatu, catatannya cara mengukur telapak kaki.
- Ikut diperbaiki: di layar 360 px, tombol "Lihat keranjang" pada bar beli dulu turun jadi 2 baris. Sekarang kolom pertama mengikuti lebar tombol, sehingga kedua tombol tetap 1 baris.
- Dicek di localhost:8501, hanya dengan membuka halaman tanpa mengubah data:
  - Tabel P002, P015, P029, dan P025 benar. P022 tanpa tabel.
  - Di 360 px, tabel tidak perlu digeser dan halaman tetap 360 px.
  - Konsol tanpa error.
- Ukuran sepatu ditulis tanpa "EUR" di semua halaman pembeli, seperti di marketplace: pilihan "38 … 44", kartu "38–44", spesifikasi, panduan ukuran, lihat cepat, keranjang, checkout, dan pesanan. Ini memakai helper `size_text()` di tampilan. Label asli "EUR 40" di data master, Redis, dan halaman admin tidak berubah. Sudah dicek di localhost:8501: halaman P029 dan beranda tidak lagi memuat teks "EUR".

**Tambahan: galeri gambar per warna di halaman produk (seperti marketplace)**
- Gambar produk kini berupa galeri, satu slide per warna, yang bisa digeser ke samping (CSS scroll-snap, tetap jalan tanpa JavaScript).
  - Di ponsel ada penanda "1/3" di pojok gambar.
  - Di layar lebar ada deretan thumbnail. Thumbnail berupa tautan `#foto-N`, jadi tetap berfungsi tanpa JavaScript.
- **Memilih warna** (di form atau di lembar variasi) menggeser galeri ke gambar warna itu.
- **Menggeser galeri** tidak mengubah warna yang dipilih, sama seperti di marketplace.
- Tautan `?warna=sage` membuka galeri langsung di gambar warna tersebut.
- Gambar selain warna terpilih dimuat lazy. Gambar kecil di lembar variasi mengikuti warna yang dipilih.
- Kode lama yang mengganti `src` satu gambar (`#pd-img`) diganti `initGallery()` di `app.js`. Uji foto disesuaikan untuk membaca slide yang sedang terlihat.
- **Uji galeri** di localhost:8501 (hanya membuka halaman):
  - Laptop: 3 slide dan thumbnail tampil. Klik thumbnail ke-3 menampilkan sage, sedangkan warna tetap hitam. Pilih Putih menampilkan slide 2 dan thumbnail 2 aktif.
  - Ponsel 390 px: geser dari "1/3" ke "3/3", warna tetap hitam, thumbnail disembunyikan, lebar 390 px.
  - Pilih Putih di lembar variasi: galeri menjadi "2/3" dan gambar lembar juga putih.
  - `?warna=sage` membuka "3/3".
  - Konsol tanpa error.
- Tes lengkap setelah galeri dan penghapusan "EUR" (db 14, dibersihkan; sidik db 0 tetap `83b1f15857a52403`):
  - run-tests 15, http-e2e 11, C10 4, uji klik 9, browser 6, beranda 13, foto 10, wireframe 12, gulir 1. Semuanya lulus.
  - Keranjang dan checkout sepatu menampilkan "Variasi: 40, Putih", tanpa "EUR".

### F11. Filter katalog, README, dan cadangan, 10 November 2026

**Filter katalog** (`/katalog`), hanya membaca data master:
- **Rentang harga** (`harga_min`, `harga_max`): angka dibersihkan dari titik, dan bila minimum lebih besar dari maksimum, keduanya ditukar.
- **Ukuran** (`ukuran[]`) dan **warna** (`warna[]`):
  - Kotak centang langsung menerapkan filter.
  - Kode hanya boleh huruf kecil, angka, dan '-', maksimal 20. Kode yang tidak dikenal diabaikan.
  - Filter ukuran baru muncul setelah kategori dipilih, karena S dan 38 tidak sebanding.
- **Aturan gabungan:** di dalam satu kelompok berlaku "atau", antar-kelompok berlaku "dan".
- **Hitungan kategori** mengikuti kata kunci dan harga.
- **Pindah kategori:** filter harga tetap, ukuran dan warna direset.
- **Chip filter aktif** di atas daftar produk. Tiap chip bisa dihapus sendiri, atau semuanya lewat "Hapus semua filter".
- Mengurutkan produk tidak menghilangkan filter.
- Panel filter terbuka di layar lebar. Di ponsel berupa tombol "Filter", yang terbuka sendiri bila ada filter aktif.

**Uji filter** (localhost:8501, hanya membuka halaman):
- Atasan: 7 → XXL 6 → XXL + Sage 2 (P002, P007).
- Harga 150–200 rb + XXL + Sage → 0. Hapus XXL → 1 (Kemeja Linen Rp189.000).
- Urutkan harga turun: filter tetap ada. Hapus semua → 7.
- Lewat HTTP:
  - harga 100–150 rb → 4, dan rentang terbalik memberi hasil yang sama
  - sepatu ukuran 44 → 7
  - `ukuran[]=<script>` diabaikan
- Ponsel 360 px: panel tertutup, chip kategori 1 baris, tanpa geser samping.
- Konsol tanpa error.

**README:** ditambah tabel fitur per halaman (pembeli dan admin), catatan port 8501/8502 di laptop pengembang, serta catatan gambar AI, deskripsi demo, panduan ukuran, dan label ukuran sepatu. Tanda pisah panjang di judul diganti titik dua. Langkah uji C9 di bagian C kini menulis 2/3/5 kolom.

**Tes lengkap** (db 14, dibersihkan; sidik db 0 tetap `83b1f15857a52403`):
- run-tests 15, http-e2e 11, C10 4.
- Browser 51 pemeriksaan: klik 9, browser 6, beranda 13, foto 10, wireframe 12, gulir 1.
- Semuanya lulus.

**Teks toko (lanjutan F11):**
- **Info Cabang:** kalimat pembuka kini hanya "Nomor pesanan dari cabang ini diawali BGR." Kode website dan nama pembuat tidak perlu diketahui pembeli, dan nama pembuat sudah ada di footer. Kalimat "Alamat, kontak, dan jam di atas masih data demo…" dihapus karena setiap baris sudah bertanda demo.
- **Katalog:** urutan bawaan diberi nama "Urutan toko", bukan "Sesuai kode produk". Nama seperti "Terpopuler" atau "Terbaru" tidak dipakai karena urutannya memang bukan itu.
- **Detail produk:** di bawah judul hanya tertulis kategori. Kode produk tetap ada di Spesifikasi.
- Dicek di localhost:8501: /cabang, /produk/P025, dan /katalog tampil (200) dengan teks baru.

### F12. Teks dan banner yang terasa buatan AI, 12 November 2026

**Perubahan tampilan** (tanpa mengubah data, Redis, atau alur transaksi):
- **Banner geser ke-2:** slide "Stok bersama" diganti promo kategori Atasan: "Mulai Rp129.000", "7 model, termasuk Kaus Basic, Oversized Tee, dan Polo Shirt.", dengan dua foto produk. Jumlah, harga, nama, dan foto dihitung dari data.
- **Banner samping:** kotak "42 produk, 6 kategori" diganti banner Outerwear. Kedua banner samping (Outerwear, Sepatu & Sandal) kini berisi harga termurah, jumlah model, dan foto produk termurahnya.
- **Info Cabang:** bagian "Cara kerja stok bersama" dihapus. Penjelasan stok bersama cukup ada di README dan presentasi.
- **Daftar cabang:** "tautan belum ada" diganti tombol "Buka website" ke tautan sementara `https://alverse-<cabang>.example/`. Domain `.example` khusus contoh dan tidak pernah aktif. Tautan asli cukup diisi di `BRANCH_URL_*` pada `.env`.
- **Halaman 503:** judul "Sedang ada gangguan", isi "Halaman sedang tidak bisa dimuat. Coba lagi sebentar lagi." Detail teknis hanya muncul bila `APP_DEBUG=true`.
- **Detail produk:** keterangan "Gambar dibuat dengan AI" di bawah galeri dihapus karena sudah ada di footer. Alt gambar tetap memuat "(gambar AI)".

**Tes lengkap** (db 14, dibersihkan; sidik db 0 tetap `83b1f15857a52403`):
- run-tests 15, http-e2e 11, C10 4.
- Browser 51 pemeriksaan: klik 9, browser 6, beranda 13, foto 10, wireframe 12, gulir 1.
- Tes foto disesuaikan: banner berfoto latar harus termuat dengan lapisan gelap, banner promo harus memuat 2 foto produk.
- Semuanya lulus. Tampilan dicek di 1440, 1024, dan 390 px, tanpa geser ke samping.
