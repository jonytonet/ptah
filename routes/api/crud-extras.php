<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\Crud\ExtrasApiController;

/*
|--------------------------------------------------------------------------
| BaseCrud extras over the headless API (1.59.0)
|--------------------------------------------------------------------------
| Form draft, the form's server-side behaviours, master/detail and column
| management — the same group and constraints as routes/ptah-api.php. Each
| action resolves the screen through CrudRuntimeResolver (locks, permissions).
*/
Route::middleware((array) config('ptah.crud_api.middleware', ['web', 'auth']))
    ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/crud')
    ->name('ptah.api.crud.extras.')
    ->where(['screen' => '[A-Za-z0-9][A-Za-z0-9._-]*', 'id' => '[0-9]+'])
    ->group(function () {
        Route::post('{screen}/form/draft', [ExtrasApiController::class, 'draft'])->name('form.draft');
        Route::post('{screen}/form/change', [ExtrasApiController::class, 'change'])->name('form.change');
        Route::get('{screen}/form/triggers', [ExtrasApiController::class, 'triggers'])->name('form.triggers');
        Route::get('{screen}/columns', [ExtrasApiController::class, 'columns'])->name('columns');
        Route::post('{screen}/columns/reset', [ExtrasApiController::class, 'resetColumns'])->name('columns.reset');
        Route::get('{screen}/{id}/details', [ExtrasApiController::class, 'details'])->name('details');
    });
