<?php

/*
|--------------------------------------------------------------------------
| Driver hujjatlari (CDL, medical card)
|--------------------------------------------------------------------------
|
| Driver hujjatni rasmga olib yuklaydi, maxfiy joylarni belgilaydi. Tizim
| o'sha joylarni qaytarib bo'lmaydigan qilib berkitadi, watermark qo'yadi
| va PDF ko'rinishida saqlaydi. Carrier faqat shu PDF'ni ko'radi.
|
*/

return [

    // Asl rasm va PDF hech qachon public papkada turmaydi.
    'disk' => env('DOCUMENTS_DISK', 'local'),

    /*
    | Watermark matni. Production'ga chiqqanda .env orqali almashtiriladi.
    */
    'watermark_text' => env('DOCUMENTS_WATERMARK', 'recruiting'),

    'watermark' => [
        'opacity'  => 0.16,   // 0..1
        'angle'    => 30,     // daraja
        'font_size_ratio' => 0.045,  // rasm kengligiga nisbatan
    ],

    /*
    | Berkitish usuli:
    |   pixelate — kuchli piksellashtirish (orqaga qaytarib bo'lmaydi)
    |   blackout — to'liq qora to'rtburchak
    |
    | Oddiy "blur" qaytarib tiklanishi mumkin, shuning uchun ishlatilmaydi.
    */
    'redaction_mode' => env('DOCUMENTS_REDACTION', 'pixelate'),

    'max_width'  => 2000,     // px, yuklangan rasm shungacha kichraytiriladi
    'jpeg_quality' => 82,
    'max_upload_kb' => 12288, // 12 MB

    /*
    | Watermark uchun shrift. Repoda bor, shuning uchun serverga bog'liq emas.
    */
    'font_path' => resource_path('fonts/DejaVuSans-Bold.ttf'),

    'types' => [
        'cdl'           => 'CDL',
        'medical_card'  => 'Medical Card',
    ],
];
