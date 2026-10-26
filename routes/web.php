<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

/*
| Rute berbentuk Route::view agar tetap bisa di-cache (php artisan route:cache).
| Halaman bertanda "pending" adalah penampung. Controller aslinya masuk di fase
| yang tertulis pada nilai 'phase' (lihat FILE 18).
*/

Route::view('/', 'pages.home')->name('home');
Route::view('/about', 'pages.about')->name('about');

Route::view('/places', 'pages.pending', ['title' => 'Jelajah', 'phase' => 2])->name('places.index');
Route::view('/regions', 'pages.pending', ['title' => 'Wilayah', 'phase' => 2])->name('regions.index');
Route::view('/hotels', 'pages.pending', ['title' => 'Hotel', 'phase' => 2])->name('hotels.index');
Route::view('/planner', 'pages.pending', ['title' => 'Rencanakan perjalanan', 'phase' => 4])->name('planner.index');

Route::middleware('auth')->prefix('account')->group(function () {
    Route::view('/', 'account.index')->name('account.index');
});

Route::middleware(['auth', EnsureUserIsAdmin::class])->prefix('admin')->group(function () {
    Route::view('/', 'admin.index')->name('admin.index');
});

if (app()->isLocal()) {
    Route::view('/styleguide', 'pages.styleguide')->name('styleguide');
}
