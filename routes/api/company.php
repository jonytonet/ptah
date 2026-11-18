<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\Admin\CompanyApiController;

/*
|--------------------------------------------------------------------------
| Company area of the headless API (1.59.0)
|--------------------------------------------------------------------------
| Companies and the company switcher (company module) and departments
| (permissions module), for the React and Vue stacks. Same conditions and
| middleware as routes/ptah-company.php and routes/ptah-permissions.php; the
| components re-check access in boot() on every request, as on Livewire.
*/
$ptahApiPrefix = trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/admin';
$ptahApiMiddleware = (array) config('ptah.crud_api.middleware', ['web', 'auth']);

if (config('ptah.modules.company')) {
    Route::middleware($ptahApiMiddleware)
        ->prefix($ptahApiPrefix)
        ->name('ptah.api.company.')
        ->where(['company' => '[0-9]+'])
        ->group(function () {
            // CompanyList: ptah_can_manage_structure(), checked by the component.
            Route::get('companies', [CompanyApiController::class, 'companies'])->name('companies.index');
            Route::post('companies', [CompanyApiController::class, 'storeCompany'])->name('companies.store');
            Route::get('companies/{company}', [CompanyApiController::class, 'company'])->name('companies.show');
            Route::put('companies/{company}', [CompanyApiController::class, 'updateCompany'])->name('companies.update');
            Route::delete('companies/{company}', [CompanyApiController::class, 'destroyCompany'])->name('companies.destroy');

            // CompanySwitcher: any signed-in user, over the companies they belong to.
            Route::get('company-switcher', [CompanyApiController::class, 'switcher'])->name('switcher');
            Route::post('company-switcher', [CompanyApiController::class, 'switchCompany'])->name('switcher.switch');
        });
}

if (config('ptah.modules.permissions')) {
    Route::middleware(array_merge($ptahApiMiddleware, ['ptah.master']))
        ->prefix($ptahApiPrefix)
        ->name('ptah.api.company.')
        ->where(['department' => '[0-9]+'])
        ->group(function () {
            Route::get('departments', [CompanyApiController::class, 'departments'])->name('departments.index');
            Route::post('departments', [CompanyApiController::class, 'storeDepartment'])->name('departments.store');
            Route::get('departments/{department}', [CompanyApiController::class, 'department'])->name('departments.show');
            Route::put('departments/{department}', [CompanyApiController::class, 'updateDepartment'])->name('departments.update');
            Route::delete('departments/{department}', [CompanyApiController::class, 'destroyDepartment'])->name('departments.destroy');
        });
}

unset($ptahApiPrefix, $ptahApiMiddleware);
