# Instalasi Alverse Bogor — laptop Hanif (Windows 11 + XAMPP + WSL Ubuntu)

Panduan ini disusun dari hasil pemeriksaan laptop pada **7 November 2026**. Jika laptopmu berbeda, sesuaikan bagian 1.

## 1. Hasil pemeriksaan awal

| Komponen | Hasil | Keterangan |
|---|---|---|
| Windows | 11 Home Single Language 25H2 | — |
| PHP | **8.2.12** dari XAMPP (`C:\xampp\php\php.exe`), sudah ada di PATH | Memenuhi syarat ≥ 8.1. Ekstensi `json`, `mbstring`, `openssl`, `curl` aktif |
| Composer | **2.11.1** (`C:\ProgramData\ComposerSetup\bin\composer.bat`) | Sudah terpasang |
| WSL | Distro **Ubuntu 24.04.3 LTS**, WSL versi 2, systemd aktif | Tempat Redis dipasang |
| Redis lama di Windows | Service Windows `Redis` = **Redis 3.0.504** (port Windows lama, 2016), memakai port **6379** | **Tidak bisa dipakai**, lihat bagian 2 |
| Memurai | Terpasang, service berhenti | Tidak dipakai |
| Port 8001/8002 | **Dicadangkan Windows** (rentang Hyper-V/WSL 7462–8361) | Website memakai **8501/8502**, lihat bagian 7 |

## 2. Kenapa Redis memakai port 6380, bukan 6379

- Redis 3.0 di Windows terlalu tua. Skrip checkout (`app/Data/LuaScripts.php`) memakai perintah `XADD` (Redis Streams) yang baru ada sejak Redis 5.0. Selain itu AOF-nya mati dan ia mendengarkan di `0.0.0.0` (semua jaringan) tanpa password.
- Redis lama itu sudah menempati port 6379 di Windows. Jika Redis baru di WSL juga memakai 6379, `127.0.0.1:6379` dari PHP di Windows tetap mengarah ke Redis lama.
- Solusinya: pasang **Redis 7 di Ubuntu WSL** pada port **6380**, lalu tulis `REDIS_PORT=6380` di `.env`. Dengan cara ini Redis lama tidak perlu diubah.

> **Opsional, disarankan:** jika Redis lama tidak dipakai tugas lain, matikan agar tidak terbuka ke jaringan.
> Buka **PowerShell sebagai Administrator**, lalu jalankan:
> `Stop-Service Redis; Set-Service Redis -StartupType Manual`
> Proyek ini tetap memakai port 6380 meskipun Redis lama dimatikan.

## 3. Pembagian terminal

| Terminal | Cara membuka | Dipakai untuk |
|---|---|---|
| **Ubuntu (WSL)** | Start → ketik `Ubuntu`, atau di VS Code: panah ∨ di samping `+` pada panel Terminal → *Ubuntu (WSL)* | Memasang & mengelola Redis, `redis-cli` |
| **PowerShell (VS Code)** | VS Code → *Terminal → New Terminal*. Pastikan folder aktif adalah `alverse-bogor` | Semua perintah `composer` dan `php` |

PHP dijalankan dari Windows (XAMPP), sedangkan Redis berjalan di Ubuntu WSL. WSL2 meneruskan `127.0.0.1:6380` di Windows ke Redis di Ubuntu.

## 4. Pasang Redis di Ubuntu (sekali saja)

Jalankan di **terminal Ubuntu**. `sudo` akan menanyakan password **Linux**-mu (ketikannya tidak tampil, itu normal).

```bash
sudo apt update
sudo apt install -y redis-server

# Pakai port 6380 dan aktifkan AOF (data tidak hilang saat restart)
sudo sed -i 's/^port 6379$/port 6380/; s/^appendonly no$/appendonly yes/' /etc/redis/redis.conf
sudo systemctl enable redis-server
sudo systemctl restart redis-server

# Periksa
redis-server --version
redis-cli -p 6380 ping
redis-cli -p 6380 config get appendonly
redis-cli -p 6380 config get bind
```

Hasil yang diharapkan:
- versi `7.x`
- `PONG`
- `appendonly` → `yes`
- `bind` → `127.0.0.1 -::1` (hanya laptop ini yang bisa mengakses Redis)

Data Redis tersimpan di `/var/lib/redis` di dalam Ubuntu, bukan di folder OneDrive, sehingga tidak ikut tersinkron atau terkunci oleh OneDrive.

### Mengelola Redis sehari-hari (terminal Ubuntu)

```bash
sudo systemctl status redis-server     # cek status (q untuk keluar)
sudo systemctl stop redis-server       # hentikan (dipakai untuk uji C7 di PENGUJIAN.md)
sudo systemctl start redis-server      # nyalakan lagi
redis-cli -p 6380                      # masuk redis-cli (ketik: ping, lalu exit)
```

Selama mengerjakan proyek, **biarkan satu jendela Ubuntu tetap terbuka**. WSL bisa mematikan dirinya sendiri jika tidak ada jendela Ubuntu yang aktif, sehingga Redis ikut mati. Setelah laptop dinyalakan ulang, cukup buka jendela Ubuntu: Redis menyala otomatis karena sudah `enable`.

> Di `docs/PENGUJIAN.md`, setiap perintah `redis-cli ...` dijalankan sebagai `redis-cli -p 6380 ...` di terminal Ubuntu.

## 5. Siapkan proyek (PowerShell di folder `alverse-bogor`)

```powershell
composer install                       # membuat folder vendor/
Copy-Item .env.example .env            # lewati jika .env sudah ada
```

Buka `.env`, lalu pastikan isinya:
```
REDIS_HOST=127.0.0.1
REDIS_PORT=6380
REDIS_DB=0
REDIS_TEST_DB=15
```

Cek koneksi:
```powershell
php scripts/check-redis.php
```
Hasil yang diharapkan: `PING -> PONG`, `Versi Redis : 7.x`, `appendonly (AOF) : yes`.

Jika gagal terhubung padahal `redis-cli -p 6380 ping` di Ubuntu menjawab `PONG`, berarti penerusan localhost WSL tidak aktif. Buat atau ubah file `C:\Users\USER\.wslconfig` menjadi:
```
[wsl2]
localhostForwarding=true
```
Setelah itu jalankan `wsl --shutdown` di PowerShell, buka Ubuntu lagi, lalu ulangi pengecekan.

## 6. Isi data demo dan atur password admin

```powershell
php scripts/seed-demo.php --dry-run    # lihat dulu apa yang akan ditulis
php scripts/seed-demo.php              # isi 42 produk + stok demo ke database 0
php scripts/set-admin-password.php     # ketik password admin sendiri (minimal 8 karakter)
```

- Seed aman diulang karena stok yang sudah ada tidak ditimpa.
- Di Windows, password yang kamu ketik **terlihat di layar**. Pastikan tidak ada orang lain yang melihat layar. Yang disimpan di `.env` hanya hash bcrypt-nya.
- **Jangan** mengirim password admin ke chat atau ke siapa pun.

## 7. Jalankan website

Di laptop ini port 8001 dan 8002 masuk rentang yang dicadangkan Windows untuk Hyper-V/WSL. Akibatnya `php -S localhost:8001` gagal dengan pesan `Failed to listen on localhost:8001 (reason: An attempt was made to access a socket in a way forbidden by its access permissions)`. Karena itu website memakai **8501**. Di `.env`, isi `APP_URL=http://localhost:8501`.

```powershell
php -S localhost:8501 -t public public/index.php
```

- Toko: <http://localhost:8501>
- Admin: <http://localhost:8501/admin/login>, username `admin_bogor`

Rentang port yang dicadangkan bisa berubah setelah restart. Jika 8501 suatu saat juga ditolak, periksa daftarnya dengan perintah di bawah, lalu pilih port di luar rentang yang tercantum (dan sesuaikan `APP_URL`):
```powershell
netsh int ipv4 show excludedportrange protocol=tcp
```
- Hentikan server dengan `Ctrl + C`. Redis tetap berjalan di Ubuntu.

Apache XAMPP **tidak** perlu dinyalakan karena proyek ini memakai server bawaan PHP.

## 8. Instance kedua untuk uji stok bersama (opsional)

Di PowerShell kedua:
```powershell
Copy-Item .env.uji.example .env.uji
```
Di `.env.uji`, ubah `REDIS_PORT=6379` menjadi `REDIS_PORT=6380` dan `APP_URL` menjadi `http://localhost:8502`, lalu jalankan:
```powershell
$env:APP_ENV_FILE=".env.uji"; php scripts/set-admin-password.php
$env:APP_ENV_FILE=".env.uji"; php -S localhost:8502 -t public public/index.php
```
Karena portnya berbeda dari bawaan, tes HTTP dijalankan dengan:
`php tests/http-e2e.php --bogor=http://localhost:8501 --uji=http://localhost:8502`
Variabel `$env:APP_ENV_FILE` hanya berlaku di jendela PowerShell itu. Jangan menjalankan perintah untuk website Bogor di jendela yang sama. Jika terlanjur, hapus variabelnya dengan `Remove-Item Env:APP_ENV_FILE`.

## 8b. Preview di dalam iframe (Mobile Preview)

Secara bawaan website menolak ditampilkan di dalam iframe (`X-Frame-Options: DENY` dan `frame-ancestors 'none'`). Ini perlindungan dari *clickjacking*. Akibatnya alat seperti Mobile Preview menampilkan pesan "Your site blocks the preview".

Untuk preview lokal, tambahkan baris berikut di `.env`:
```
LOCAL_PREVIEW_FRAME=true
# opsional; kosong = 'self' http://localhost:* http://127.0.0.1:* vscode-webview: chrome-extension:
LOCAL_PREVIEW_FRAME_ANCESTORS=
```
- Opsi ini hanya berlaku jika `APP_ENV=local`. Di environment lain, opsi diabaikan dan website tetap tidak bisa dibingkai.
- Yang berubah hanya `frame-ancestors` dan hilangnya `X-Frame-Options`. Direktif CSP lain tetap sama.
- Nilai `*`, `http:`, `https:`, atau nilai yang mengandung `;` ditolak. Website kembali ke mode tidak bisa dibingkai dan pesan penolakannya muncul di terminal server.
- Server PHP **tidak perlu** dimulai ulang karena `.env` dibaca ulang di setiap request.
- Matikan lagi (`false` atau hapus barisnya) setelah selesai preview.

Keterbatasan: jika alat preview berjalan dari asal lain (misalnya `vscode-webview:` atau `chrome-extension:`), browser menganggap iframe-nya lintas situs. Cookie sesi (`SameSite=Lax`) lalu tidak terkirim, sehingga keranjang, login admin, dan form bisa gagal di dalam preview. Preview cocok untuk memeriksa tampilan. Transaksi tetap diuji di tab browser biasa.

## 9. Keamanan data saat pengujian

| Perintah | Menghapus data? | Database |
|---|---|---|
| `php scripts/check-redis.php` | Tidak | 0 (hanya baca) |
| `php scripts/seed-demo.php` | Tidak (stok lama dipertahankan) | 0 |
| `php tests/run-tests.php` | **Ya, mengosongkan database 15** | 15 saja. Tes menolak jalan jika database 15 berisi data lain yang tidak bertanda `test` |
| `php tests/http-e2e.php` | Tidak dikosongkan, tetapi membuat lalu membatalkan pesanan uji | 0 (lewat website) |
| `php scripts/reset-demo.php --confirm=RESET-DEMO` | **Ya, semua data proyek** | 0 |

Sebelum menjalankan `run-tests.php`, pastikan database 15 kosong atau memang khusus tes (jalankan di terminal Ubuntu):
```bash
redis-cli -p 6380 -n 15 dbsize                  # 0 = kosong
redis-cli -p 6380 -n 15 get alverse:meta:dataset  # "test" = milik tes
```

## 10. Masalah umum

| Gejala | Penyebab & solusi |
|---|---|
| `Folder vendor/ belum ada` | Jalankan `composer install` di folder proyek |
| `GAGAL terhubung ... 127.0.0.1:6380` | Redis di Ubuntu belum berjalan atau WSL mati. Buka Ubuntu, lalu jalankan `sudo systemctl start redis-server` |
| Versi Redis di `check-redis.php` = `3.0.504` | `.env` masih memakai `REDIS_PORT=6379`. Ganti menjadi `6380` |
| `ERR unknown command 'XADD'` | Sama seperti di atas, website tersambung ke Redis lama |
| `Failed to listen ... forbidden by its access permissions` | Port masuk rentang yang dicadangkan Windows. Pilih port lain (lihat bagian 7) |
| `Address already in use` saat `php -S` | Port sudah dipakai server lain. Tutup terminal server sebelumnya |
| Login admin ditolak terus | Ulangi `php scripts/set-admin-password.php`. Setelah 5 kali gagal, tunggu 60 detik |
