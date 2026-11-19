<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\Admin\RolesApiController;

/*
|--------------------------------------------------------------------------
| Access control — roles, pages/objects, guide (1.59.0)
|--------------------------------------------------------------------------
| The API of RoleList, PageList and PermissionGuide for the React and Vue
| stacks. Same module condition and the same `ptah.master` gate as
| routes/ptah-permissions.php; the components re-check MASTER in boot().
*/
if (config('ptah.modules.permissions')) {
    Route::middleware(array_merge((array) config('ptah.crud_api.middleware', ['web', 'auth']), ['ptah.master']))
        ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/admin')
        ->name('ptah.api.roles.')
        ->where(['role' => '[0-9]+', 'ptahPage' => '[0-9]+', 'object' => '[0-9]+'])
        ->group(function () {
            Route::get('roles', [RolesApiController::class, 'roles'])->name('index');
            Route::post('roles', [RolesApiController::class, 'storeRole'])->name('store');
            Route::get('roles/{role}', [RolesApiController::class, 'role'])->name('show');
            Route::put('roles/{role}', [RolesApiController::class, 'updateRole'])->name('update');
            Route::delete('roles/{role}', [RolesApiController::class, 'destroyRole'])->name('destroy');
            Route::get('roles/{role}/permissions', [RolesApiController::class, 'permissions'])->name('permissions');
            Route::put('roles/{role}/permissions', [RolesApiController::class, 'savePermissions'])->name('permissions.save');

            Route::get('pages', [RolesApiController::class, 'pages'])->name('pages.index');
            Route::post('pages', [RolesApiController::class, 'storePage'])->name('pages.store');
            Route::put('pages/{ptahPage}', [RolesApiController::class, 'updatePage'])->name('pages.update');
            Route::delete('pages/{ptahPage}', [RolesApiController::class, 'destroyPage'])->name('pages.destroy');
            Route::get('pages/{ptahPage}/objects', [RolesApiController::class, 'objects'])->name('objects.index');
            Route::post('pages/{ptahPage}/objects', [RolesApiController::class, 'storeObject'])->name('objects.store');
            Route::put('pages/{ptahPage}/objects/{object}', [RolesApiController::class, 'updateObject'])->name('objects.update');
            Route::delete('pages/{ptahPage}/objects/{object}', [RolesApiController::class, 'destroyObject'])->name('objects.destroy');

            Route::get('permission-guide', [RolesApiController::class, 'guide'])->name('guide');
        });
}
