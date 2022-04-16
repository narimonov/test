<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('home');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/users', [App\Http\Controllers\HomeController::class, 'users'])->name('users');
Route::get('/user/{id}', [App\Http\Controllers\HomeController::class, 'user'])->name('user');
Route::get('/client/{id}', [App\Http\Controllers\HomeController::class, 'client'])->name('client');
Route::get('/delete/{id}', [App\Http\Controllers\HomeController::class, 'delete'])->name('delete');
