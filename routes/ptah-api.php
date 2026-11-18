<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\CrudApiController;
use Ptah\Http\Controllers\ShellApiController;

/*
|--------------------------------------------------------------------------
| Headless CRUD API (1.59.0)
|--------------------------------------------------------------------------
| Loaded only with ptah.crud_api.enabled. The BaseCrud over JSON, for a React,
| Vue or any other client. {screen} is a key registered with
| Ptah\Crud\CrudScreen::register() — the client never names a model.
*/
Route::middleware((array) config('ptah.crud_api.middleware', ['web', 'auth']))
    ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/crud')
    ->name('ptah.api.crud.')
    ->where(['screen' => '[A-Za-z0-9][A-Za-z0-9._-]*', 'id' => '[0-9]+', 'field' => '[A-Za-z0-9_.]+'])
    ->group(function () {
        Route::get('{screen}/schema', [CrudApiController::class, 'schema'])->name('schema');
        Route::get('{screen}/preferences', [CrudApiController::class, 'preferences'])->name('preferences');
        Route::put('{screen}/preferences', [CrudApiController::class, 'updatePreferences'])->name('preferences.update');
        Route::post('{screen}/saved-filters', [CrudApiController::class, 'saveFilter'])->name('filters.save');
        Route::delete('{screen}/saved-filters', [CrudApiController::class, 'forgetFilter'])->name('filters.forget');
        Route::get('{screen}/create', [CrudApiController::class, 'create'])->name('create');
        Route::match(['get', 'post'], '{screen}/lookup/{field}', [CrudApiController::class, 'lookup'])->name('lookup');
        Route::post('{screen}/bulk', [CrudApiController::class, 'bulk'])->name('bulk');
        Route::post('{screen}/export', [CrudApiController::class, 'export'])->name('export');
        Route::get('{screen}', [CrudApiController::class, 'index'])->name('index');
        Route::post('{screen}', [CrudApiController::class, 'store'])->name('store');
        Route::get('{screen}/{id}', [CrudApiController::class, 'show'])->name('show');
        Route::post('{screen}/{id}/duplicate', [CrudApiController::class, 'duplicate'])->name('duplicate');
        // POST too: a multipart body (file upload) is only parsed on POST.
        Route::match(['put', 'post'], '{screen}/{id}', [CrudApiController::class, 'update'])->name('update');
        Route::delete('{screen}/{id}', [CrudApiController::class, 'destroy'])->name('destroy');
        Route::post('{screen}/{id}/restore', [CrudApiController::class, 'restore'])->name('restore');
    });

// The layout around the screens: the menu the Livewire sidebar draws, and
// who is signed in.
Route::middleware((array) config('ptah.crud_api.middleware', ['web', 'auth']))
    ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/'))
    ->name('ptah.api.')
    ->group(function () {
        Route::get('menu', [ShellApiController::class, 'menu'])->name('menu');
        Route::get('me', [ShellApiController::class, 'me'])->name('me');
    });
