<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Penautan otomatis dengan Lokal POS
    |--------------------------------------------------------------------------
    |
    | Diisi operator sekali saat deploy; pemilik cafe tidak pernah melihatnya.
    | secret: SAMA dengan LOKAL_LINK_SECRET di lokal-pos. Kosong = penautan & satu login mati,
    | aplikasi tetap jalan sendiri (login email/password).
    | pos_url: alamat Lokal POS untuk tombol "Masuk lewat Lokal POS" dan "Kembali ke POS".
    | verify_ssl: false hanya berlaku saat APP_ENV=local (sertifikat Herd).
    |
    */

    'link' => [
        'secret' => env('LOKAL_LINK_SECRET'),
        'pos_url' => env('LOKAL_POS_URL'),
        'verify_ssl' => (bool) env('LOKAL_LINK_VERIFY_SSL', true),
    ],

];
