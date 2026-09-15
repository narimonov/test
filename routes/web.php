<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Ilova SPA bo'lgani uchun barcha sahifa yo'llari bitta blade'ga tushadi,
| routing esa brauzerda vue-router tomonidan bajariladi.
| /api/* yo'llari routes/api.php da.
|
*/

Route::view('/{any?}', 'spa')->where('any', '^(?!api).*$')->name('spa');
