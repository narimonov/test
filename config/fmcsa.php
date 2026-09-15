<?php

/*
|--------------------------------------------------------------------------
| FMCSA integratsiyasi
|--------------------------------------------------------------------------
|
| Kompaniya ro'yxatdan o'tayotganda MC yoki DOT raqami FMCSA bazasidan
| tekshiriladi. Tasdiqlash kodi FMCSA'da ro'yxatdan o'tgan telefon/emailga
| yuboriladi — foydalanuvchi kiritgan kontaktga emas. Shu sababli faqat
| kompaniyaning haqiqiy egasi ro'yxatdan o'ta oladi.
|
| driver=fake => tashqi so'rov yo'q, lokal ishlab chiqish uchun.
|
*/

return [

    'driver' => env('FMCSA_DRIVER', 'fake'),

    'qcmobile' => [
        'base_url'         => env('FMCSA_BASE_URL', 'https://mobile.fmcsa.dot.gov/qc/services'),
        'web_key'          => env('FMCSA_WEB_KEY'),
        'timeout'          => env('FMCSA_TIMEOUT', 15),

        // Telefon/email QCMobile'da yo'q — Company Census dataset'idan olinadi.
        'census_base_url'  => env('FMCSA_CENSUS_BASE_URL', 'https://data.transportation.gov'),
        'census_dataset'   => env('FMCSA_CENSUS_DATASET'),
        'census_app_token' => env('FMCSA_CENSUS_APP_TOKEN'),
    ],

    /*
    | Kompaniya FMCSA'da ACTIVE bo'lmasa ro'yxatdan o'ta olmaydi.
    | Test muhitida buni o'chirish uchun false qiling.
    */
    'require_active_status' => env('FMCSA_REQUIRE_ACTIVE', true),

    /*
    | Kompaniya ma'lumotini qayta tekshirish oralig'i (kun).
    | Authority to'xtatilgan bo'lsa akkaunt ham to'xtatiladi.
    */
    'recheck_after_days' => env('FMCSA_RECHECK_DAYS', 30),
];
