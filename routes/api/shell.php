<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\Admin\ShellExtrasApiController;

/*
| The layout's notification bell and exports panel, for the React and Vue
| stacks (1.59.0). Neither has a page route of its own in the Livewire stack:
| both are embedded in an authenticated layout, so they sit behind the API's
| own middleware. The bell answers `enabled: false` when
| `ptah.notifications.enabled` is off (the Livewire bell is not registered
| then) and refuses its actions with a 404.
*/

Route::middleware((array) config('ptah.crud_api.middleware', ['web', 'auth']))
    ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/'))
    ->name('ptah.api.shell.')
    ->group(function () {
        Route::get('notifications', [ShellExtrasApiController::class, 'notifications'])->name('notifications');
        Route::post('notifications/read-all', [ShellExtrasApiController::class, 'markAllNotificationsRead'])->name('notifications.read-all');
        Route::post('notifications/{id}/open', [ShellExtrasApiController::class, 'openNotification'])->whereNumber('id')->name('notifications.open');
        Route::post('notifications/{id}/dismiss', [ShellExtrasApiController::class, 'dismissNotification'])->whereNumber('id')->name('notifications.dismiss');

        Route::get('exports', [ShellExtrasApiController::class, 'exports'])->name('exports');
        Route::delete('exports/{id}', [ShellExtrasApiController::class, 'removeExport'])->whereNumber('id')->name('exports.remove');
    });
