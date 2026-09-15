const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Frontend — Vue 3 SPA. app.js butun ilovani yuklaydi, routing brauzerda.
 |
 */

mix.js('resources/js/app.js', 'public/js')
    .vue({ version: 3 })
    .sass('resources/sass/app.scss', 'public/css')
    .sourceMaps(false)
    .version();
