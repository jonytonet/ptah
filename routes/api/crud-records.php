<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\Crud\RecordsApiController;

/*
|--------------------------------------------------------------------------
| Record history and attachments of the headless CRUD (1.59.0)
|--------------------------------------------------------------------------
| The History and Attachments buttons of the BaseCrud edit modal, for the
| React and Vue stacks. Same group, prefix and constraints as
| routes/ptah-api.php; loaded only with ptah.crud_api.enabled.
*/
Route::middleware((array) config('ptah.crud_api.middleware', ['web', 'auth']))
    ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/crud')
    ->name('ptah.api.crud.records.')
    ->where(['screen' => '[A-Za-z0-9][A-Za-z0-9._-]*', 'id' => '[0-9]+', 'attachment' => '[0-9]+'])
    ->group(function () {
        Route::get('{screen}/attachments/counts', [RecordsApiController::class, 'counts'])->name('attachments.counts');
        Route::get('{screen}/{id}/history', [RecordsApiController::class, 'history'])->name('history');
        Route::get('{screen}/{id}/attachments', [RecordsApiController::class, 'attachments'])->name('attachments.index');
        // POST: a multipart body is only parsed on POST.
        Route::post('{screen}/{id}/attachments', [RecordsApiController::class, 'upload'])->name('attachments.upload');
        Route::get('{screen}/{id}/attachments/{attachment}/download', [RecordsApiController::class, 'download'])->name('attachments.download');
        Route::delete('{screen}/{id}/attachments/{attachment}', [RecordsApiController::class, 'destroy'])->name('attachments.destroy');
    });
