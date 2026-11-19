<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\Admin\AccessApiController;

/*
|--------------------------------------------------------------------------
| Users-ACL and permission audit, for the React and Vue stacks
|--------------------------------------------------------------------------
| The API of the /ptah-users-acl and /ptah-audit screens. Same module
| condition and the same `ptah.master` gate as routes/ptah-permissions.php;
| the components check MASTER again in boot().
*/
if (config('ptah.modules.permissions')) {
    Route::middleware([...(array) config('ptah.crud_api.middleware', ['web', 'auth']), 'ptah.master'])
        ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/admin/access')
        ->name('ptah.api.access.')
        ->where(['user' => '[0-9]+', 'userRole' => '[0-9]+'])
        ->group(function () {
            Route::get('users', [AccessApiController::class, 'users'])->name('users');
            Route::post('users', [AccessApiController::class, 'storeUser'])->name('users.store');
            Route::get('users/{user}', [AccessApiController::class, 'showUser'])->name('users.show');
            Route::put('users/{user}', [AccessApiController::class, 'updateUser'])->name('users.update');
            Route::post('users/{user}/password-link', [AccessApiController::class, 'sendPasswordLink'])->name('users.password-link');
            Route::get('users/{user}/roles', [AccessApiController::class, 'roles'])->name('roles');
            Route::post('users/{user}/roles', [AccessApiController::class, 'addRole'])->name('roles.store');
            Route::delete('users/{user}/roles/{userRole}', [AccessApiController::class, 'removeRole'])->name('roles.destroy');
            Route::get('audit', [AccessApiController::class, 'audit'])->name('audit');
        });
}
