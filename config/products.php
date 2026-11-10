<?php
/**
 * MASTER PRODUK ALVERSE (42 model, P001–P042)
 *
 * Sumber: "Daftar Harga Alverse Semua Cabang" (halaman 2–4, master harga).
 * Panduan v2: seluruh 42 model dipakai di semua website, harga dari daftar harga lama.
 *
 * Yang BERASAL dari daftar harga: kode, nama, kategori, ukuran, warna, harga.
 * Yang BELUM ADA di daftar harga (deskripsi, foto) diberi label DEMO
 * dan nantinya diganti data resmi dari pusat (Kelompok 6 / admin_pusat).
 *
 * size_spec  -> dipetakan menjadi kode ukuran oleh App\Domain\MasterCatalog::sizes()
 * size_label -> teks ukuran persis seperti di daftar harga
 */

return [
    // ---------------- Atasan ----------------
    ['id' => 'P001', 'name' => 'Kaus Basic',        'category' => 'atasan', 'size_spec' => 'S-XXL', 'size_label' => 'S–XXL', 'colors' => ['Hitam', 'Putih', 'Navy'],  'price' => 129000],
    ['id' => 'P002', 'name' => 'Oversized Tee',     'category' => 'atasan', 'size_spec' => 'S-XXL', 'size_label' => 'S–XXL', 'colors' => ['Hitam', 'Putih', 'Sage'],  'price' => 149000],
    ['id' => 'P003', 'name' => 'Polo Shirt',        'category' => 'atasan', 'size_spec' => 'S-XXL', 'size_label' => 'S–XXL', 'colors' => ['Hitam', 'Navy', 'Cream'],  'price' => 169000],
    ['id' => 'P004', 'name' => 'Kemeja Linen',      'category' => 'atasan', 'size_spec' => 'S-XL',  'size_label' => 'S–XL',  'colors' => ['Putih', 'Cream', 'Sage'],  'price' => 189000],
    ['id' => 'P005', 'name' => 'Kemeja Flanel',     'category' => 'atasan', 'size_spec' => 'S-XXL', 'size_label' => 'S–XXL', 'colors' => ['Merah', 'Navy', 'Abu'],    'price' => 199000],
    ['id' => 'P006', 'name' => 'Hoodie Pullover',   'category' => 'atasan', 'size_spec' => 'S-XXL', 'size_label' => 'S–XXL', 'colors' => ['Hitam', 'Abu', 'Navy'],    'price' => 229000],
    ['id' => 'P007', 'name' => 'Sweatshirt',        'category' => 'atasan', 'size_spec' => 'S-XXL', 'size_label' => 'S–XXL', 'colors' => ['Cream', 'Sage', 'Abu'],    'price' => 209000],

    // ---------------- Outerwear ----------------
    ['id' => 'P008', 'name' => 'Cardigan Basic',    'category' => 'outerwear', 'size_spec' => 'S-XL', 'size_label' => 'S–XL', 'colors' => ['Cream', 'Hitam', 'Mocca'], 'price' => 189000],
    ['id' => 'P009', 'name' => 'Jaket Denim',       'category' => 'outerwear', 'size_spec' => 'M-XL', 'size_label' => 'M–XL', 'colors' => ['Biru', 'Hitam'],          'price' => 279000],
    ['id' => 'P010', 'name' => 'Zip Hoodie',        'category' => 'outerwear', 'size_spec' => 'M-XL', 'size_label' => 'M–XL', 'colors' => ['Hitam', 'Abu', 'Navy'],   'price' => 249000],
    ['id' => 'P011', 'name' => 'Jaket Varsity',     'category' => 'outerwear', 'size_spec' => 'M-XL', 'size_label' => 'M–XL', 'colors' => ['Hitam', 'Navy', 'Marun'], 'price' => 329000],
    ['id' => 'P012', 'name' => 'Trench Coat',       'category' => 'outerwear', 'size_spec' => 'M-XL', 'size_label' => 'M–XL', 'colors' => ['Khaki', 'Cream', 'Hitam'], 'price' => 399000],
    ['id' => 'P013', 'name' => 'Jaket Bomber',      'category' => 'outerwear', 'size_spec' => 'M-XL', 'size_label' => 'M–XL', 'colors' => ['Army', 'Hitam', 'Abu'],   'price' => 299000],
    ['id' => 'P014', 'name' => 'Outer Casual',      'category' => 'outerwear', 'size_spec' => 'M-XL', 'size_label' => 'M–XL', 'colors' => ['Sage', 'Cream', 'Hitam'], 'price' => 249000],

    // ---------------- Bawahan ----------------
    ['id' => 'P015', 'name' => 'Slimfit Jeans',     'category' => 'bawahan', 'size_spec' => '28-36', 'size_label' => '28–36 (genap)', 'colors' => ['Biru', 'Hitam'],            'price' => 249000],
    ['id' => 'P016', 'name' => 'Chino Pants',       'category' => 'bawahan', 'size_spec' => '28-36', 'size_label' => '28–36 (genap)', 'colors' => ['Khaki', 'Navy', 'Hitam'],   'price' => 229000],
    ['id' => 'P017', 'name' => 'Celana Kulot',      'category' => 'bawahan', 'size_spec' => 'S-XL',  'size_label' => 'S–XL',          'colors' => ['Hitam', 'Cream', 'Mocca'],  'price' => 179000],
    ['id' => 'P018', 'name' => 'Rok Plisket',       'category' => 'bawahan', 'size_spec' => 'S-XL',  'size_label' => 'S–XL',          'colors' => ['Hitam', 'Mocca', 'Sage'],   'price' => 169000],
    ['id' => 'P019', 'name' => 'Cargo Pants',       'category' => 'bawahan', 'size_spec' => '28-36', 'size_label' => '28–36 (genap)', 'colors' => ['Army', 'Khaki', 'Hitam'],   'price' => 259000],
    ['id' => 'P020', 'name' => 'Straight Jeans',    'category' => 'bawahan', 'size_spec' => '28-36', 'size_label' => '28–36 (genap)', 'colors' => ['Biru Muda', 'Biru Tua'],    'price' => 259000],
    ['id' => 'P021', 'name' => 'Celana Jogger',     'category' => 'bawahan', 'size_spec' => 'S-XXL', 'size_label' => 'S–XXL',         'colors' => ['Hitam', 'Abu', 'Navy'],     'price' => 189000],

    // ---------------- Busana muslim ----------------
    ['id' => 'P022', 'name' => 'Hijab Voal Polos',  'category' => 'busana-muslim', 'size_spec' => 'ONE', 'size_label' => '115 × 115 cm', 'colors' => ['Hitam', 'Cream', 'Dusty Pink'], 'price' => 59000],
    ['id' => 'P023', 'name' => 'Pashmina Polos',    'category' => 'busana-muslim', 'size_spec' => 'ONE', 'size_label' => '175 × 70 cm',  'colors' => ['Mocca', 'Sage', 'Hitam'],       'price' => 69000],
    ['id' => 'P024', 'name' => 'Kemeja Koko Basic', 'category' => 'busana-muslim', 'size_spec' => 'S-XL', 'size_label' => 'S–XL',        'colors' => ['Putih', 'Navy', 'Hitam'],       'price' => 189000],
    ['id' => 'P025', 'name' => 'Gamis Modern',      'category' => 'busana-muslim', 'size_spec' => 'S-XL', 'size_label' => 'S–XL',        'colors' => ['Navy', 'Sage', 'Mocca'],        'price' => 299000],
    ['id' => 'P026', 'name' => 'Tunik Linen',       'category' => 'busana-muslim', 'size_spec' => 'S-XL', 'size_label' => 'S–XL',        'colors' => ['Cream', 'Sage', 'Dusty Pink'],  'price' => 219000],
    ['id' => 'P027', 'name' => 'Hijab Voal Motif',  'category' => 'busana-muslim', 'size_spec' => 'ONE', 'size_label' => '115 × 115 cm', 'colors' => ['Cream', 'Navy', 'Mocca'],       'price' => 79000],
    ['id' => 'P028', 'name' => 'Pashmina Plisket',  'category' => 'busana-muslim', 'size_spec' => 'ONE', 'size_label' => '175 × 70 cm',  'colors' => ['Mocca', 'Hitam', 'Cream'],      'price' => 89000],

    // ---------------- Sepatu dan sandal ----------------
    ['id' => 'P029', 'name' => 'Canvas Sneakers',   'category' => 'sepatu-sandal', 'size_spec' => 'EUR38-44', 'size_label' => 'EUR 38–44', 'colors' => ['Putih', 'Hitam'],   'price' => 279000],
    ['id' => 'P030', 'name' => 'Slip On Canvas',    'category' => 'sepatu-sandal', 'size_spec' => 'EUR38-44', 'size_label' => 'EUR 38–44', 'colors' => ['Hitam', 'Navy'],    'price' => 249000],
    ['id' => 'P031', 'name' => 'Sandal Casual',     'category' => 'sepatu-sandal', 'size_spec' => 'EUR38-44', 'size_label' => 'EUR 38–44', 'colors' => ['Hitam', 'Cream'],   'price' => 129000],
    ['id' => 'P032', 'name' => 'Sport Sandals',     'category' => 'sepatu-sandal', 'size_spec' => 'EUR38-44', 'size_label' => 'EUR 38–44', 'colors' => ['Hitam', 'Army'],    'price' => 199000],
    ['id' => 'P033', 'name' => 'Leather Loafers',   'category' => 'sepatu-sandal', 'size_spec' => 'EUR38-44', 'size_label' => 'EUR 38–44', 'colors' => ['Hitam', 'Cokelat'], 'price' => 399000],
    ['id' => 'P034', 'name' => 'Sneakers Chunky',   'category' => 'sepatu-sandal', 'size_spec' => 'EUR38-44', 'size_label' => 'EUR 38–44', 'colors' => ['Putih', 'Cream'],   'price' => 349000],
    ['id' => 'P035', 'name' => 'Sandal Slides',     'category' => 'sepatu-sandal', 'size_spec' => 'EUR38-44', 'size_label' => 'EUR 38–44', 'colors' => ['Hitam', 'Navy'],    'price' => 149000],

    // ---------------- Tas dan aksesori ----------------
    ['id' => 'P036', 'name' => 'Tote Bag Canvas',   'category' => 'tas-aksesori', 'size_spec' => 'ONE',     'size_label' => '35 × 40 cm',             'colors' => ['Natural', 'Hitam'],          'price' => 89000],
    ['id' => 'P037', 'name' => 'Topi Baseball',     'category' => 'tas-aksesori', 'size_spec' => 'ADJ',     'size_label' => 'Adjustable',             'colors' => ['Hitam', 'Navy', 'Cream'],    'price' => 79000],
    ['id' => 'P038', 'name' => 'Sabuk Sintetis',    'category' => 'tas-aksesori', 'size_spec' => '105/115', 'size_label' => '105 / 115 cm',           'colors' => ['Hitam', 'Cokelat'],          'price' => 69000],
    ['id' => 'P039', 'name' => 'Laptop Backpack',   'category' => 'tas-aksesori', 'size_spec' => 'ONE',     'size_label' => 'Untuk laptop ≤15,6 inci', 'colors' => ['Hitam', 'Navy'],            'price' => 249000],
    ['id' => 'P040', 'name' => 'Sling Bag',         'category' => 'tas-aksesori', 'size_spec' => 'ONE',     'size_label' => '24 × 18 × 8 cm',         'colors' => ['Hitam', 'Cream', 'Mocca'],   'price' => 169000],
    ['id' => 'P041', 'name' => 'Mini Shoulder Bag', 'category' => 'tas-aksesori', 'size_spec' => 'ONE',     'size_label' => '22 × 14 × 7 cm',         'colors' => ['Hitam', 'Cream', 'Cokelat'], 'price' => 189000],
    ['id' => 'P042', 'name' => 'Bucket Hat',        'category' => 'tas-aksesori', 'size_spec' => 'ONE',     'size_label' => 'Lingkar 56–58 cm',       'colors' => ['Cream', 'Hitam', 'Army'],    'price' => 89000],
];
