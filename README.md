# Alverse: Website Cabang Bogor Kota

Website toko Alverse cabang **Bogor Kota** (`branch_id = bogor`) untuk Kelompok 5.
Pengembang: **Hanif** (anggota 2). Layanan data pusat: **Kelompok 6**.

> Acuan: Panduan Bersama v2 dan Panduan Hanif v2 (7 November 2026), serta `Wireframe-Alverse-Bogor-Kota.pdf`. Dokumen lama (proposal, daftar harga) hanya dipakai sebagai latar dan sumber harga.

### Fitur per halaman

**Pembeli**

| Halaman | Fitur |
|---|---|
| Beranda (`/`) | Banner geser (3 slide), banner samping, menu Kategori dengan ringkasan harga, kotak cari dengan saran, ikon 6 kategori, 18 produk pertama dengan tombol "Lihat produk lainnya", Lihat cepat (pilih warna/ukuran tanpa pindah halaman), keranjang mini |
| Katalog (`/katalog`) | Cari nama/kode/warna, urutkan (Urutan toko, Harga termurah, Harga termahal, Nama A–Z), kategori di samping (chip geser di ponsel), **filter rentang harga, ukuran, dan warna**, chip filter aktif yang bisa dihapus satu per satu, 20 produk pertama lalu "Tampilkan produk lainnya", 2/3/5 kolom di 360/768/1280 px |
| Detail produk (`/produk/{id}`) | **Galeri gambar per warna yang bisa digeser** (penanda 1/3 di ponsel, thumbnail di layar lebar), pilihan ukuran dan warna, ukuran habis terkunci otomatis, **stok global bersama diperbarui tiap 5 detik**, spesifikasi, **panduan ukuran (cm)**, tombol Lihat keranjang, bar beli dan lembar pilih variasi di ponsel, produk sekategori |
| Keranjang (`/keranjang`) | Ubah jumlah dengan − / +, hapus item, cek stok per item, total. Keranjang tidak memotong stok |
| Checkout (`/checkout`) | Data penerima, pilih **pengiriman reguler (Rp10.000)** atau **pickup di cabang (Rp0)**, pesan error di bawah kolom yang salah, halaman konfirmasi pembayaran simulasi |
| Pesanan (`/pesanan`, `/pesanan/{id}`) | Daftar pesanan dari browser ini, detail dengan garis status, riwayat, kode pickup 6 angka, **pembatalan oleh pembeli** selama status Dikonfirmasi (alasan dipilih, stok dikembalikan tepat satu kali) |
| Info cabang (`/cabang`) | Jam dan lokasi pickup, cara mengambil pesanan, cara kerja stok bersama, tautan ke 6 website cabang lain |

**Admin cabang** (`/admin`)

| Halaman | Fitur |
|---|---|
| Login | Username + password (hash bcrypt), tombol Lihat/Sembunyikan password, dikunci 60 detik setelah 5 kali gagal |
| Dashboard | Total pesanan, pesanan hari ini, pesanan yang perlu diproses, pesanan terbaru |
| Pesanan | Cari, filter status dan metode, **20 pesanan per halaman** dengan nomor halaman, hanya pesanan cabang Bogor |
| Detail pesanan | Ubah status sesuai alur (Dikonfirmasi → Dikemas → Dikirim (kurir + resi) → Selesai, atau Siap Diambil → Selesai dengan kode pickup), pembatalan dengan alasan |
| Stok | Stok global per ukuran × warna, cari dan filter kategori (baca saja; restock wewenang admin pusat) |
| Rekap | Penjualan per periode (tanggal dari–sampai) dan per produk, hanya pesanan Selesai, ongkir dipisah |

---

## 1. Teknologi

| Bagian | Pilihan | Alasan |
|---|---|---|
| Server web | PHP 8.1+ (tanpa framework) | Sesuai proposal, mudah dipelajari |
| Dependency | Composer + `predis/predis` ^3 | Klien Redis berbasis PHP murni, tanpa ekstensi tambahan |
| Tampilan | HTML, CSS, JavaScript biasa | Tanpa build tool |
| Data latihan | Redis lokal | Sesuai rancangan Kelompok 6 |
| Data akhir | Layanan pusat Kelompok 6 | Lewat `CentralHttpAdapter` (lihat `docs/INTEGRASI-PUSAT.md`) |

Tidak ada database lain (MySQL dan sejenisnya) karena memang tidak dibutuhkan.

### Empat hal yang sering tertukar

| Istilah | Penjelasan sederhana |
|---|---|
| **Redis server** | Program database yang menyimpan data, mendengarkan di port 6379. |
| **Redis client untuk aplikasi** | Pustaka di dalam kode PHP (di proyek ini: Predis). Website memakainya untuk berbicara dengan Redis server. |
| **redis-cli** | Program terminal untuk mengetik perintah Redis secara manual (`PING`, `HGETALL`, dst.). Hanya alat bantu. |
| **Layanan pusat Kelompok 6** | Server bersama milik Kelompok 6 yang dipakai ketujuh website. Website kita nantinya memanggil layanan ini, bukan Redis di laptop sendiri. |

Browser **tidak pernah** terhubung langsung ke Redis. JavaScript hanya memanggil `/api/stok/...` di server PHP.

---

## 2. Struktur folder

```
alverse-bogor/
├── public/                 ← satu-satunya folder yang boleh diakses browser
│   ├── index.php           ← pintu masuk + daftar route
│   ├── .htaccess           ← (untuk Apache/XAMPP)
│   └── assets/css, js, img
├── app/
│   ├── bootstrap.php       ← memuat .env dan konfigurasi
│   ├── App.php
│   ├── Controllers/        ← menerima request, memanggil aturan bisnis, merender view
│   ├── Domain/             ← ATURAN BISNIS: validasi, keranjang, checkout, status, login admin
│   ├── Data/               ← AKSES DATA: interface + adapter Redis lokal + adapter pusat + skrip Lua
│   ├── Http/               ← router, sesi, CSRF, view
│   └── Support/            ← pembaca .env, fungsi bantu
├── views/                  ← tampilan (PHP + HTML)
├── config/                 ← konfigurasi, master 42 produk, endpoint pusat
├── scripts/                ← seed, reset, cek Redis, atur password admin
├── tests/                  ← tes otomatis lapisan data + tes HTTP end-to-end
├── redis/redis-dev.conf    ← konfigurasi Redis lokal (AOF aktif, hanya 127.0.0.1)
├── docs/                   ← kontrak data, integrasi pusat, panduan pengujian
├── .env.example            ← contoh konfigurasi (tanpa rahasia)
└── .env.uji.example        ← contoh instance kedua khusus uji lokal
```

---

## 3. Instalasi kebutuhan

Kebutuhan: **PHP 8.1 atau lebih baru**, **Composer**, **Redis server**, dan editor (VS Code).

Cara memasang Redis berbeda untuk tiap sistem operasi. Sumber resmi:
- Ringkasan semua cara: <https://redis.io/docs/latest/operate/oss_and_stack/install/install-stack/>
- Windows (Docker Desktop): <https://redis.io/docs/latest/operate/oss_and_stack/install/install-stack/windows/>
- macOS (Homebrew): <https://redis.io/docs/latest/operate/oss_and_stack/install/install-stack/homebrew/>
- Ubuntu/Debian (APT): <https://redis.io/docs/latest/operate/oss_and_stack/install/install-stack/apt/>
- PHP: <https://www.php.net/downloads> · Composer: <https://getcomposer.org/download/>

Langkah instalasi untuk laptop pengembang (Windows 11 + XAMPP PHP + Redis 7 di WSL Ubuntu) ada di **`docs/INSTALASI.md`**.
Di laptop itu Redis memakai **port 6380** (`REDIS_PORT=6380` di `.env`) karena port 6379 sudah dipakai Redis 3.0 lama yang tidak kompatibel. Website memakai **port 8501** (bukan 8001), karena 8001/8002 dicadangkan Windows untuk Hyper-V/WSL.

---

## 4. Menjalankan proyek (setelah PHP, Composer, Redis terpasang)

Semua perintah dijalankan di **terminal yang terbuka di folder proyek** `alverse-bogor/`.

```bash
# 1) Pasang dependency (membuat folder vendor/)
composer install

# 2) Buat file konfigurasi dari contoh
cp .env.example .env            # Windows PowerShell: Copy-Item .env.example .env

# 3) Pastikan Redis berjalan dan terhubung
php scripts/check-redis.php     # harus menampilkan: PING -> PONG

# 4) Isi data demo SEKALI (42 produk + stok demo; aman diulang, stok tidak ditimpa)
php scripts/seed-demo.php

# 5) Atur password admin (yang disimpan hanya hash-nya; jangan bagikan password)
php scripts/set-admin-password.php

# 6) Jalankan website
php -S localhost:8001 -t public public/index.php
```

Buka <http://localhost:8001>. Admin: <http://localhost:8001/admin/login> (username default `admin_bogor`).
Hentikan server dengan `Ctrl + C`.
Di laptop pengembang, pakai port **8501** (dan **8502** untuk instance uji), karena 8001/8002 dicadangkan Windows (lihat bagian 3).

### Instance kedua untuk uji stok bersama (opsional)

```bash
cp .env.uji.example .env.uji
APP_ENV_FILE=.env.uji php scripts/set-admin-password.php
APP_ENV_FILE=.env.uji php -S localhost:8002 -t public public/index.php
```
Di Windows PowerShell, ganti awalan `APP_ENV_FILE=.env.uji` dengan `$env:APP_ENV_FILE=".env.uji";` sebelum perintah `php`.
Jalankan di **terminal kedua**. Instance `uji` memakai Redis yang sama tetapi `branch_id` berbeda. Ini **hanya membuktikan integrasi lokal**, belum membuktikan koneksi ke website teman.

---

## 5. Aturan stok dan transaksi

- Stok disimpan di **satu** key global per produk: `inventory:stock:{product_id}`, dengan field varian (contoh `inventory:stock:P037` → `adjustable_hitam = 10`). Tidak ada stok per cabang.
- Pesanan menyimpan `branch_id`. Indeks `branch:{branch_id}:orders` dipakai agar admin Bogor hanya melihat pesanan Bogor.
- Keranjang `cart:{session_id}:{branch_id}` **tidak** mengurangi atau memesan stok.
- Stok dipotong saat **konfirmasi pembayaran simulasi** melalui skrip Lua atomik (`app/Data/LuaScripts.php`):
  1. Jika `request_id` sudah pernah dipakai, kembalikan pesanan yang sama (idempoten). Jika isinya berbeda, tolak.
  2. **Tahap validasi:** cek produk aktif, varian ada, jumlah bulat positif, stok cukup. Belum ada yang ditulis.
  3. **Tahap tulis:** potong stok, simpan pesanan + item (snapshot harga), indeks cabang, dan catatan `request_id`.
  Redis menjalankan satu skrip tanpa disela perintah lain, jadi dua checkout bersamaan tidak bisa sama-sama mengambil stok terakhir. Redis **tidak punya rollback otomatis**, karena itu semua validasi dilakukan sebelum perintah tulis pertama.
- Pembatalan (hanya status `dikonfirmasi`, alasan wajib) mengembalikan stok dan menandai `restocked=1` dalam satu skrip. Pembatalan kedua tidak mengembalikan stok lagi.
- Harga, ongkir (reguler Rp10.000, pickup Rp0), dan total dihitung di server dari master. Field harga dari browser diabaikan.
- Tampilan stok di halaman produk diperbarui saat dibuka, setiap 5 detik selama tab aktif, saat tab aktif kembali, dan setelah transaksi. **Angka di layar bisa terlambat beberapa detik.** Checkout selalu memvalidasi stok terbaru di server.
- Jika Redis atau layanan pusat terputus, website menampilkan "Tidak dapat memperbarui stok" atau halaman 503, **bukan** "Stok 0" atau sukses palsu. Jika koneksi putus tepat saat checkout, website meminta pengguna **memeriksa hasil `request_id`** sebelum mencoba lagi.

---

## 6. Pengujian

```bash
php tests/run-tests.php        # tes lapisan data (memakai database Redis 15, data demo aman)
php tests/http-e2e.php         # tes lewat HTTP (server 8001 dan 8002 harus berjalan)
```
Untuk tes admin di `http-e2e.php`, berikan password lewat variabel lingkungan `ALVERSE_ADMIN_PASSWORD` (lihat `docs/PENGUJIAN.md`). Hasil tes tersimpan di `tests/output/` sebagai bukti uji.

---

## 7. Pindah ke layanan pusat Kelompok 6

Ringkasnya: isi `CENTRAL_BASE_URL` + `CENTRAL_API_KEY` di `.env`, isi endpoint di `config/central_endpoints.php`, lalu ubah `DATA_DRIVER=central`. Controller dan tampilan tidak perlu diubah. Detail dan daftar pertanyaan untuk Kelompok 6 ada di **`docs/INTEGRASI-PUSAT.md`**.

---

## 8. Keamanan

- `.env` berisi rahasia dan **tidak** masuk Git (`.gitignore`). Bagikan hanya `.env.example`.
- Redis lokal hanya mendengarkan `127.0.0.1` (`redis/redis-dev.conf`). Jangan buka port 6379 ke internet.
- Password admin disimpan sebagai hash bcrypt. Login dibatasi 5 percobaan gagal, lalu dikunci 60 detik.
- Semua form POST memakai token CSRF. Output di-escape untuk mencegah XSS. Cookie sesi bersifat HttpOnly + SameSite=Lax dan namanya unik per website.
- Admin cabang berperan `admin_cabang`: hanya bisa melihat stok global. Restock dan koreksi stok adalah wewenang `admin_pusat` di panel pusat.

## 9. Data demo

- Kode, nama, kategori, ukuran, warna, dan harga 42 produk diambil dari **Daftar Harga Alverse** (harga simulasi).
- **Deskripsi, foto, angka stok, alamat, kontak, dan jam cabang adalah DEMO** dan diberi label di tampilan. Penggantinya adalah data resmi dari pusat.
- **Gambar produk dan banner dibuat dengan AI** (`public/assets/img/produk/foto/`, `public/assets/img/banner/`), satu gambar per warna. Tampilan memberi label "gambar AI".
- Deskripsi demo ("[Deskripsi demo] …") tidak ditampilkan karena hanya mengulang spesifikasi. Bagian deskripsi muncul otomatis bila pusat mengirim deskripsi asli.
- **Panduan ukuran** (`config/size-guide.php`) memakai ukuran standar umum toko pakaian di Indonesia karena daftar harga belum memuat ukuran cm. Ganti angka di file itu bila pusat mengirim ukuran resmi.
- Ukuran sepatu di data tertulis "EUR 40". Di halaman pembeli ditulis "40" seperti di marketplace (`size_text()`); data aslinya tidak berubah.
- Kode varian (`s_hitam`, `one_dusty-pink`, `adjustable_hitam`, …) masih usulan lokal. Kelompok 6 yang membekukan daftar kode final.
