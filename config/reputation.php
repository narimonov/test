<?php

/*
|--------------------------------------------------------------------------
| Review va blacklist qoidalari
|--------------------------------------------------------------------------
|
| Driver kompaniyaga, kompaniya driverga baho qoldiradi. Belgilangan sondan
| ko'p qoniqarsiz baho to'plangan tomon blacklist'ga tushadi va apelyatsiya
| bera oladi.
|
*/

return [

    // Shu balldan past (yoki teng) baho "qoniqarsiz" hisoblanadi.
    'negative_rating_at_or_below' => env('REPUTATION_NEGATIVE_AT', 2),

    // Nechta qoniqarsiz bahodan keyin blacklist.
    'blacklist_after_negative' => env('REPUTATION_BLACKLIST_AFTER', 3),

    /*
    | Apelyatsiya qabul qilingandan keyin eski salbiy baholar hisobga
    | olinmaydi, aks holda foydalanuvchi darrov qayta blacklist'ga tushardi.
    */
    'reset_counter_on_appeal' => true,
];
