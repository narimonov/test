<?php

/*
|--------------------------------------------------------------------------
| Driver documents (CDL, medical card)
|--------------------------------------------------------------------------
|
| A driver photographs the document and marks the sensitive areas. Those
| areas are destroyed, a watermark is applied, and the result is stored as a
| PDF. Carriers only ever see that PDF.
|
*/

return [

    // Neither the original nor the PDF ever sits in the public folder.
    'disk' => env('DOCUMENTS_DISK', 'local'),

    /*
    | Watermark text. Change it through .env in production.
    */
    'watermark_text' => env('DOCUMENTS_WATERMARK', 'recruiting'),

    'watermark' => [
        'opacity'  => 0.16,   // 0..1
        'angle'    => 30,     // daraja
        'font_size_ratio' => 0.045,  // rasm kengligiga nisbatan
    ],

    /*
    | How areas are removed:
    |   pixelate — collapsed and stretched back, irreversible
    |   blackout — a solid black rectangle
    |
    | Plain blur is not offered: blurred text can be recovered.
    */
    'redaction_mode' => env('DOCUMENTS_REDACTION', 'pixelate'),

    'max_width'  => 2000,     // px; uploads are scaled down to this
    'jpeg_quality' => 82,
    'max_upload_kb' => 12288, // 12 MB

    /*
    | The watermark font ships with the repo, so it does not depend on the
    | server having one installed.
    */
    'font_path' => resource_path('fonts/DejaVuSans-Bold.ttf'),

    'types' => [
        'cdl'           => 'CDL',
        'medical_card'  => 'Medical Card',
    ],
];
