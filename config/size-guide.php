<?php
/**
 * PANDUAN UKURAN (tampilan saja, tidak disimpan di Redis).
 *
 * Angka di sini memakai ukuran standar yang umum dipakai toko pakaian di Indonesia,
 * karena daftar harga Alverse belum memuat ukuran dalam cm. Pemilik proyek menyetujui ini.
 * Kalau pusat mengirim ukuran resmi, ganti angka di file ini saja.
 *
 * - groups:   kolom + baris per kode ukuran (kode sama dengan MasterCatalog::sizes()).
 * - products: kode produk -> nama grup. Produk yang tidak ada di sini tidak punya tabel
 *             (hijab, tas, topi, sabuk: ukurannya sudah berupa cm atau adjustable).
 * - Satuan cm. Pakaian diukur dalam posisi kain mendatar.
 */

return [
    'groups' => [
        'kaus' => [
            'cols' => ['Lebar dada', 'Panjang badan'],
            'rows' => ['s' => [48, 68], 'm' => [50, 70], 'l' => [52, 72], 'xl' => [54, 74], 'xxl' => [56, 76]],
        ],
        'oversized' => [
            'cols' => ['Lebar dada', 'Panjang badan'],
            'rows' => ['s' => [55, 70], 'm' => [57, 72], 'l' => [59, 74], 'xl' => [61, 76], 'xxl' => [63, 78]],
        ],
        'kemeja' => [
            'cols' => ['Lebar dada', 'Panjang badan', 'Panjang lengan'],
            'rows' => ['s' => [50, 70, 58], 'm' => [52, 72, 59], 'l' => [54, 74, 60], 'xl' => [56, 76, 61], 'xxl' => [58, 78, 62]],
        ],
        'hoodie' => [
            'cols' => ['Lebar dada', 'Panjang badan', 'Panjang lengan'],
            'rows' => ['s' => [52, 66, 58], 'm' => [54, 68, 59], 'l' => [56, 70, 60], 'xl' => [58, 72, 61], 'xxl' => [60, 74, 62]],
        ],
        'jaket' => [
            'cols' => ['Lebar dada', 'Panjang badan', 'Panjang lengan'],
            'rows' => ['m' => [56, 66, 60], 'l' => [58, 68, 61], 'xl' => [60, 70, 62]],
        ],
        'mantel' => [
            'cols' => ['Lebar dada', 'Panjang badan', 'Panjang lengan'],
            'rows' => ['m' => [56, 98, 60], 'l' => [58, 100, 61], 'xl' => [60, 102, 62]],
        ],
        'celana-angka' => [
            'cols' => ['Lingkar pinggang', 'Panjang celana'],
            'rows' => ['28' => [74, 98], '30' => [78, 99], '32' => [82, 100], '34' => [86, 101], '36' => [90, 102]],
        ],
        'jogger' => [
            'cols' => ['Lingkar pinggang (karet)', 'Panjang celana'],
            'rows' => ['s' => [66, 94], 'm' => [70, 96], 'l' => [74, 98], 'xl' => [78, 100], 'xxl' => [82, 102]],
        ],
        'kulot' => [
            'cols' => ['Lingkar pinggang (karet)', 'Panjang celana'],
            'rows' => ['s' => [64, 92], 'm' => [68, 93], 'l' => [72, 94], 'xl' => [76, 95]],
        ],
        'rok' => [
            'cols' => ['Lingkar pinggang (karet)', 'Panjang rok'],
            'rows' => ['s' => [64, 85], 'm' => [68, 86], 'l' => [72, 87], 'xl' => [76, 88]],
        ],
        'gamis' => [
            'cols' => ['Lebar dada', 'Panjang badan'],
            'rows' => ['s' => [48, 135], 'm' => [50, 137], 'l' => [53, 139], 'xl' => [56, 141]],
        ],
        'tunik' => [
            'cols' => ['Lebar dada', 'Panjang badan'],
            'rows' => ['s' => [48, 95], 'm' => [50, 97], 'l' => [53, 99], 'xl' => [56, 101]],
        ],
        'sepatu' => [
            'cols' => ['Panjang telapak kaki'],
            'note' => 'Dalam cm, diukur dari tumit sampai ujung jari terpanjang. Toleransi 0,5 cm.',
            'rows' => ['38' => [24], '39' => [24.5], '40' => [25.5], '41' => [26], '42' => [26.5], '43' => [27.5], '44' => [28]],
        ],
    ],
    'products' => [
        'P001' => 'kaus', 'P002' => 'oversized', 'P003' => 'kaus', 'P004' => 'kemeja', 'P005' => 'kemeja',
        'P006' => 'hoodie', 'P007' => 'hoodie',
        'P008' => 'hoodie', 'P009' => 'jaket', 'P010' => 'jaket', 'P011' => 'jaket', 'P012' => 'mantel',
        'P013' => 'jaket', 'P014' => 'jaket',
        'P015' => 'celana-angka', 'P016' => 'celana-angka', 'P017' => 'kulot', 'P018' => 'rok',
        'P019' => 'celana-angka', 'P020' => 'celana-angka', 'P021' => 'jogger',
        'P024' => 'kemeja', 'P025' => 'gamis', 'P026' => 'tunik',
        'P029' => 'sepatu', 'P030' => 'sepatu', 'P031' => 'sepatu', 'P032' => 'sepatu', 'P033' => 'sepatu',
        'P034' => 'sepatu', 'P035' => 'sepatu',
    ],
];
