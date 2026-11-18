<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\Crud\BoardsApiController;

/*
|--------------------------------------------------------------------------
| Headless CRUD — kanban and calendar (1.59.0)
|--------------------------------------------------------------------------
| The alternative views of a BaseCrud screen (HasCrudBoards). Same group as
| routes/ptah-api.php. GET carries the listing state in the query, like the
| listing itself: both views show the SAME filtered rows as the table.
*/
Route::middleware((array) config('ptah.crud_api.middleware', ['web', 'auth']))
    ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/crud')
    ->name('ptah.api.crud.boards.')
    ->where(['screen' => '[A-Za-z0-9][A-Za-z0-9._-]*', 'id' => '[0-9]+'])
    ->group(function () {
        Route::get('{screen}/kanban', [BoardsApiController::class, 'kanban'])->name('kanban');
        Route::post('{screen}/{id}/move', [BoardsApiController::class, 'move'])->name('move');
        Route::get('{screen}/calendar', [BoardsApiController::class, 'calendar'])->name('calendar');
    });
