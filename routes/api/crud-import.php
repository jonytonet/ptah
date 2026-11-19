<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\Crud\ImportApiController;

/*
|--------------------------------------------------------------------------
| Headless CRUD — spreadsheet import (HasCrudImport)
|--------------------------------------------------------------------------
| The upload → columns → review → done wizard of the BaseCrud. The file is
| stored once and referenced by an opaque token bound to the user and the
| screen; each step re-establishes the engine from it.
*/
Route::middleware((array) config('ptah.crud_api.middleware', ['web', 'auth']))
    ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/crud')
    ->name('ptah.api.crud.import.')
    ->where(['screen' => '[A-Za-z0-9][A-Za-z0-9._-]*', 'id' => '[0-9]+', 'token' => '[A-Za-z0-9]{40}'])
    ->group(function () {
        Route::get('{screen}/import', [ImportApiController::class, 'info'])->name('info');
        Route::post('{screen}/import', [ImportApiController::class, 'upload'])->name('upload');
        Route::post('{screen}/import/preview', [ImportApiController::class, 'preview'])->name('preview');
        Route::post('{screen}/import/run', [ImportApiController::class, 'run'])->name('run');
        Route::delete('{screen}/import/{token}', [ImportApiController::class, 'discard'])->name('discard');
    });
