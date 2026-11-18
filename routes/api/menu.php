<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\Admin\MenuApiController;

/*
|--------------------------------------------------------------------------
| Menu editor and system settings — admin API for the React and Vue stacks
|--------------------------------------------------------------------------
| The Livewire screens (/ptah-menu, /ptah-settings) are behind ['web', 'auth'];
| who may use them is decided by the components' own boot() gate
| (ptah_can_manage_structure), which runs on every request here too.
|
| Under `admin/…`: `{prefix}/menu` is already the layout's menu tree.
*/

$ptahMenuApiMiddleware = (array) config('ptah.crud_api.middleware', ['web', 'auth']);

// The Livewire routes always require a signed-in user. When the host's API
// middleware has no `auth*` of its own, it is added: with the permissions
// module off, the boot() gate alone (PTAH_STRUCTURE_EDITOR) would not tell a
// guest from a user.
$ptahMenuApiHasAuth = false;
foreach ($ptahMenuApiMiddleware as $ptahMenuApiEntry) {
    $ptahMenuApiHasAuth = $ptahMenuApiHasAuth || (is_string($ptahMenuApiEntry) && str_starts_with($ptahMenuApiEntry, 'auth'));
}
if (! $ptahMenuApiHasAuth) {
    $ptahMenuApiMiddleware[] = 'auth';
}

$ptahMenuApiPrefix = trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/admin';

// The menu editor exists only with the menu module, as /ptah-menu.
if (config('ptah.modules.menu')) {
    Route::middleware($ptahMenuApiMiddleware)
        ->prefix($ptahMenuApiPrefix.'/menu')
        ->name('ptah.api.menu.')
        ->where(['id' => '[0-9]+'])
        ->group(function () {
            Route::get('/', [MenuApiController::class, 'index'])->name('index');
            Route::post('/', [MenuApiController::class, 'store'])->name('store');
            Route::get('{id}', [MenuApiController::class, 'show'])->name('show');
            Route::put('{id}', [MenuApiController::class, 'update'])->name('update');
            Route::post('{id}/toggle', [MenuApiController::class, 'toggle'])->name('toggle');
            Route::delete('{id}', [MenuApiController::class, 'destroy'])->name('destroy');
        });
}

// The settings screen has no module flag: /ptah-settings is always loaded and
// explains how to install when the table is missing — so does this API.
Route::middleware($ptahMenuApiMiddleware)
    ->prefix($ptahMenuApiPrefix.'/settings')
    ->name('ptah.api.settings.')
    ->where(['key' => '[A-Za-z0-9_.-]+'])
    ->group(function () {
        Route::get('/', [MenuApiController::class, 'settings'])->name('index');
        Route::put('/', [MenuApiController::class, 'saveSettings'])->name('update');
        Route::post('{key}/reset', [MenuApiController::class, 'resetSetting'])->name('reset');
    });
