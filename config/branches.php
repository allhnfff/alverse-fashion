<?php
/**
 * Identitas tetap tujuh website cabang Alverse (Panduan bersama v2, hal. 2).
 * URL website teman diisi lewat .env (BRANCH_URL_xxx) setelah mereka memberikannya.
 *
 * 'uji' BUKAN cabang resmi: hanya untuk instance kedua saat pengujian lokal
 * (membuktikan dua website membaca stok yang sama). Hanya boleh dipakai saat APP_ENV=local.
 */

return [
    'dramaga'   => ['name' => 'Dramaga',         'developer' => 'Ridho',   'prefix' => 'DRM'],
    'bogor'     => ['name' => 'Bogor Kota',      'developer' => 'Hanif',   'prefix' => 'BGR'],
    'bandung'   => ['name' => 'Bandung',         'developer' => 'Fahmi',   'prefix' => 'BDG'],
    'depok'     => ['name' => 'Depok',           'developer' => 'Fuad',    'prefix' => 'DPK'],
    'jaksel'    => ['name' => 'Jakarta Selatan', 'developer' => 'Tia',     'prefix' => 'JKS'],
    'bekasi'    => ['name' => 'Bekasi',          'developer' => 'Alyatur', 'prefix' => 'BKS'],
    'tangerang' => ['name' => 'Tangerang',       'developer' => 'Alifia',  'prefix' => 'TNG'],
    'uji'       => ['name' => 'UJI LOKAL',       'developer' => '-',       'prefix' => 'UJI', 'test_only' => true],
];
