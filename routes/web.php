<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| The app is a SPA, so every page route lands on the same blade and routing
| happens in the browser. The API lives in routes/api.php.
|
*/

Route::view('/{any?}', 'spa')->where('any', '^(?!api).*$')->name('spa');
