<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Dev;
use App\Http\Controllers\Site;
use App\Http\Controllers\Site\Planner;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

/*
| Rute berbentuk Route::view atau controller agar tetap bisa di-cache (php artisan route:cache).
*/

Route::get('/', Site\HomeController::class)->name('home');
Route::view('/about', 'pages.about')->name('about');

Route::get('/places', [Site\PlaceController::class, 'index'])->middleware('throttle:search')->name('places.index');
Route::get('/places/{slug}', [Site\PlaceController::class, 'show'])->name('places.show');
Route::get('/places/{slug}/save', [Site\BookmarkController::class, 'prompt'])->name('places.save');

Route::get('/regions', [Site\RegionController::class, 'index'])->name('regions.index');
Route::get('/regions/{slug}', [Site\RegionController::class, 'show'])->name('regions.show');

Route::get('/hotels', [Site\HotelController::class, 'index'])->middleware('throttle:search')->name('hotels.index');
Route::get('/hotels/{slug}', [Site\HotelController::class, 'show'])->name('hotels.show');

Route::get('/collections/{slug}', [Site\CollectionController::class, 'show'])->name('collections.show');

/*
| Planner (FILE 10). Guest dan user memakai editor dan endpoint yang sama. Setiap endpoint
| membuka rencana lewat PlanAccess: rencana milik user lewat scope pemilik dan policy,
| draf tamu lewat token cookie. {plan} sengaja bukan route model binding.
*/
Route::prefix('planner')->name('planner.')->group(function () {
    Route::get('/', [Planner\PlannerController::class, 'index'])->name('index');
    Route::get('/recommend', [Planner\PlannerController::class, 'recommend'])->name('recommend');
    Route::post('/recommend', [Planner\PlannerController::class, 'generate'])->middleware('throttle:planner-create')->name('generate');
    Route::get('/manual', [Planner\PlannerController::class, 'manual'])->name('manual');
    Route::post('/manual', [Planner\PlannerController::class, 'createManual'])->middleware('throttle:planner-create')->name('manual.store');

    Route::get('/drafts/{plan}', [Planner\PlanController::class, 'show'])->whereNumber('plan')->name('drafts.show');

    Route::middleware('throttle:planner-edit')->prefix('plans/{plan}')->whereNumber('plan')->name('plans.')->group(function () {
        Route::patch('/', [Planner\PlanController::class, 'update'])->name('update');
        Route::delete('/', [Planner\PlanController::class, 'destroy'])->name('destroy');
        Route::post('/save', [Planner\PlanController::class, 'save'])->name('save');
        Route::post('/hotel', [Planner\PlanController::class, 'useHotel'])->name('hotel');

        Route::post('/days', [Planner\DayController::class, 'store'])->name('days.store');
        Route::patch('/days/{day}', [Planner\DayController::class, 'update'])->whereNumber('day')->name('days.update');
        Route::delete('/days/{day}', [Planner\DayController::class, 'destroy'])->whereNumber('day')->name('days.destroy');
        Route::post('/days/{day}/regenerate', [Planner\DayController::class, 'regenerate'])->whereNumber('day')->name('days.regenerate');

        Route::post('/items', [Planner\ItemController::class, 'store'])->name('items.store');
        Route::patch('/items/{item}', [Planner\ItemController::class, 'update'])->whereNumber('item')->name('items.update');
        Route::post('/items/{item}/move', [Planner\ItemController::class, 'move'])->whereNumber('item')->name('items.move');
        Route::post('/items/{item}/replace', [Planner\ItemController::class, 'replace'])->whereNumber('item')->name('items.replace');
        Route::delete('/items/{item}', [Planner\ItemController::class, 'destroy'])->whereNumber('item')->name('items.destroy');
    });
});

Route::middleware('auth')->group(function () {
    Route::put('/places/{slug}/bookmark', [Site\BookmarkController::class, 'store'])->name('places.bookmark.store');
    Route::delete('/places/{slug}/bookmark', [Site\BookmarkController::class, 'destroy'])->name('places.bookmark.destroy');

    Route::prefix('account')->group(function () {
        Route::get('/', Site\Account\AccountController::class)->name('account.index');
        Route::get('/saved-places', [Site\Account\SavedPlaceController::class, 'index'])->name('account.saved-places');
        Route::get('/plans', [Planner\PlanController::class, 'index'])->name('account.plans.index');
        Route::get('/plans/{plan}', [Planner\PlanController::class, 'show'])->whereNumber('plan')->name('account.plans.show');
    });
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

    Route::post('{type}/bulk-status', Admin\BulkStatusController::class)
        ->where('type', 'places|hotels')
        ->name('bulk-status');

    Route::post('{type}/{id}/status', Admin\ContentStatusController::class)
        ->where(['type' => 'places|hotels|collections', 'id' => '[0-9]+'])
        ->name('status');

    Route::get('media', [Admin\MediaController::class, 'index'])->name('media.index');
    Route::post('media', [Admin\MediaController::class, 'store'])->name('media.store');
    Route::put('media/{media}', [Admin\MediaController::class, 'update'])->whereNumber('media')->name('media.update');
    Route::delete('media/{media}', [Admin\MediaController::class, 'destroy'])->whereNumber('media')->name('media.destroy');

    Route::get('imports', [Admin\ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/create', [Admin\ImportController::class, 'create'])->name('imports.create');
    Route::post('imports', [Admin\ImportController::class, 'store'])->name('imports.store');
    Route::get('imports/{batch:id}', [Admin\ImportController::class, 'show'])->name('imports.show');
    Route::post('imports/{batch:id}/commit', [Admin\ImportController::class, 'commit'])->name('imports.commit');
    Route::post('imports/{batch:id}/rollback', [Admin\ImportController::class, 'rollback'])->name('imports.rollback');

    Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
});

if (app()->isLocal()) {
    Route::view('/styleguide', 'pages.styleguide')->name('styleguide');
    Route::get('/dev/maps', Dev\MapsPlaygroundController::class)->name('dev.maps');
}
