<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Site;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

/*
| Rute berbentuk Route::view atau controller agar tetap bisa di-cache (php artisan route:cache).
| Halaman bertanda "pending" adalah penampung. Controller aslinya masuk di fase
| yang tertulis pada nilai 'phase' (lihat FILE 18).
*/

Route::view('/', 'pages.home')->name('home');
Route::view('/about', 'pages.about')->name('about');

Route::view('/places', 'pages.pending', ['title' => 'Jelajah', 'phase' => 2])->name('places.index');
Route::get('/places/{slug}', [Site\PlaceController::class, 'show'])->name('places.show');
Route::view('/regions', 'pages.pending', ['title' => 'Wilayah', 'phase' => 2])->name('regions.index');
Route::view('/hotels', 'pages.pending', ['title' => 'Hotel', 'phase' => 2])->name('hotels.index');
Route::view('/planner', 'pages.pending', ['title' => 'Rencanakan perjalanan', 'phase' => 4])->name('planner.index');

Route::middleware('auth')->prefix('account')->group(function () {
    Route::view('/', 'account.index')->name('account.index');
});

/*
| Area admin. Semua parameter record memakai id ({place:id}), bukan slug, supaya
| mengganti slug draf tidak memutus tautan admin. Selalu kirim $model->id ke route().
*/
Route::middleware(['auth', EnsureUserIsAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    $crud = function (string $uri, string $controller, string $param): void {
        Route::get($uri, [$controller, 'index'])->name("$uri.index");
        Route::get("$uri/create", [$controller, 'create'])->name("$uri.create");
        Route::post($uri, [$controller, 'store'])->name("$uri.store");
        Route::get("$uri/{{$param}:id}/edit", [$controller, 'edit'])->name("$uri.edit");
        Route::put("$uri/{{$param}:id}", [$controller, 'update'])->name("$uri.update");
        Route::delete("$uri/{{$param}:id}", [$controller, 'destroy'])->name("$uri.destroy");
    };

    Route::get('/', Admin\DashboardController::class)->name('index');

    $crud('places', Admin\PlaceController::class, 'place');
    Route::get('places/{place:id}/preview', [Admin\PlaceController::class, 'preview'])->name('places.preview');
    Route::get('places/{place:id}/preview/page', [Admin\PlaceController::class, 'previewPage'])->name('places.preview.page');

    $crud('hotels', Admin\HotelController::class, 'hotel');
    $crud('regions', Admin\RegionController::class, 'region');
    $crud('categories', Admin\CategoryController::class, 'category');
    $crud('tags', Admin\TagController::class, 'tag');
    $crud('collections', Admin\CollectionController::class, 'collection');

    Route::post('collections/{collection:id}/places', [Admin\CollectionPlaceController::class, 'store'])->name('collections.places.store');
    Route::patch('collections/{collection:id}/places/{placeId}', [Admin\CollectionPlaceController::class, 'update'])->whereNumber('placeId')->name('collections.places.update');
    Route::delete('collections/{collection:id}/places/{placeId}', [Admin\CollectionPlaceController::class, 'destroy'])->whereNumber('placeId')->name('collections.places.destroy');

    Route::post('{type}/{id}/status', Admin\ContentStatusController::class)
        ->where(['type' => 'places|hotels|collections', 'id' => '[0-9]+'])
        ->name('status');

    Route::get('media', [Admin\MediaController::class, 'index'])->name('media.index');
    Route::post('media', [Admin\MediaController::class, 'store'])->name('media.store');
    Route::put('media/{media}', [Admin\MediaController::class, 'update'])->whereNumber('media')->name('media.update');
    Route::delete('media/{media}', [Admin\MediaController::class, 'destroy'])->whereNumber('media')->name('media.destroy');

    Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
});

if (app()->isLocal()) {
    Route::view('/styleguide', 'pages.styleguide')->name('styleguide');
}
