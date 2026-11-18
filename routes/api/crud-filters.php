<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\Crud\FiltersApiController;

/*
|--------------------------------------------------------------------------
| CRUD filter panel (1.59.0)
|--------------------------------------------------------------------------
| What the React and Vue filter panel needs around the listing: how to draw
| each filter, the link filters (?f[...]), "filter by this value" and the
| labels of the searchdropdown filters. The listing state itself (filters,
| operators, date ranges, quick date, advanced rows) goes with the listing
| request — see Ptah\Crud\Concerns\FiltersRuntime.
*/
Route::middleware((array) config('ptah.crud_api.middleware', ['web', 'auth']))
    ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/crud')
    ->name('ptah.api.crud.filters.')
    ->where(['screen' => '[A-Za-z0-9][A-Za-z0-9._-]*', 'id' => '[0-9]+'])
    ->group(function () {
        Route::get('{screen}/filters/meta', [FiltersApiController::class, 'meta'])->name('meta');
        Route::get('{screen}/filters/url', [FiltersApiController::class, 'url'])->name('url');
        Route::post('{screen}/filters/by-cell', [FiltersApiController::class, 'byCell'])->name('by-cell');
        Route::put('{screen}/filters/labels', [FiltersApiController::class, 'labels'])->name('labels');
    });
