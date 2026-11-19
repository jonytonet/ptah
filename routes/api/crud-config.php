<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\Admin\CrudConfigApiController;

/*
|--------------------------------------------------------------------------
| The BaseCrud's visual config editor over the headless API (1.59.0)
|--------------------------------------------------------------------------
| The gear of a BaseCrud screen (`ptah-crud-config`) for the React and Vue
| stacks. Same group and constraints as routes/ptah-api.php: the editor is
| addressed by the registered screen key, resolved through
| CrudRuntimeResolver (the screen's gate and read permission), and then the
| editor's own gate — ptah_can_manage_config(), checked by the component's
| openModal() and save() — decides. The Livewire editor has no route of its
| own: it lives inside the BaseCrud page, behind the same API middleware.
*/
$ptahCrudConfigMiddleware = (array) config('ptah.crud_api.middleware', ['web', 'auth']);

// A privileged editor (joins, hooks, link templates feed SQL and render
// sinks). With the permissions module off its gate is a config flag that
// cannot tell a guest from a user, so a signed-in user is required even when
// the host's API middleware has no `auth*` of its own.
$ptahCrudConfigHasAuth = false;
foreach ($ptahCrudConfigMiddleware as $ptahCrudConfigEntry) {
    $ptahCrudConfigHasAuth = $ptahCrudConfigHasAuth || (is_string($ptahCrudConfigEntry) && str_starts_with($ptahCrudConfigEntry, 'auth'));
}
if (! $ptahCrudConfigHasAuth) {
    $ptahCrudConfigMiddleware[] = 'auth';
}

Route::middleware($ptahCrudConfigMiddleware)
    ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/crud')
    ->name('ptah.api.crud-config.')
    ->where([
        'screen' => '[A-Za-z0-9][A-Za-z0-9._-]*',
        // Only the component's own actions (CrudConfigApiController::ACTIONS).
        'action' => implode('|', array_keys(CrudConfigApiController::ACTIONS)),
    ])
    ->group(function () {
        Route::get('{screen}/config/can', [CrudConfigApiController::class, 'can'])->name('can');
        Route::get('{screen}/config', [CrudConfigApiController::class, 'show'])->name('show');
        Route::put('{screen}/config', [CrudConfigApiController::class, 'update'])->name('update');
        Route::post('{screen}/config/preview', [CrudConfigApiController::class, 'preview'])->name('preview');
        Route::post('{screen}/config/audience-count', [CrudConfigApiController::class, 'audienceCount'])->name('audience-count');
        Route::post('{screen}/config/actions/{action}', [CrudConfigApiController::class, 'action'])->name('action');
    });
