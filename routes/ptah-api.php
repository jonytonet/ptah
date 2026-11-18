<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\AuthApiController;
use Ptah\Http\Controllers\CrudApiController;
use Ptah\Http\Controllers\ShellApiController;

/*
|--------------------------------------------------------------------------
| Headless CRUD API (1.59.0)
|--------------------------------------------------------------------------
| Loaded only with ptah.crud_api.enabled. The BaseCrud over JSON, for a React,
| Vue or any other client. {screen} is a key registered with
| Ptah\Crud\CrudScreen::register() — the client never names a model.
*/
Route::middleware((array) config('ptah.crud_api.middleware', ['web', 'auth']))
    ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/crud')
    ->name('ptah.api.crud.')
    ->where(['screen' => '[A-Za-z0-9][A-Za-z0-9._-]*', 'id' => '[0-9]+', 'field' => '[A-Za-z0-9_.]+'])
    ->group(function () {
        Route::get('{screen}/schema', [CrudApiController::class, 'schema'])->name('schema');
        Route::get('{screen}/preferences', [CrudApiController::class, 'preferences'])->name('preferences');
        Route::put('{screen}/preferences', [CrudApiController::class, 'updatePreferences'])->name('preferences.update');
        Route::post('{screen}/saved-filters', [CrudApiController::class, 'saveFilter'])->name('filters.save');
        Route::delete('{screen}/saved-filters', [CrudApiController::class, 'forgetFilter'])->name('filters.forget');
        Route::get('{screen}/create', [CrudApiController::class, 'create'])->name('create');
        Route::match(['get', 'post'], '{screen}/lookup/{field}', [CrudApiController::class, 'lookup'])->name('lookup');
        Route::post('{screen}/bulk', [CrudApiController::class, 'bulk'])->name('bulk');
        Route::post('{screen}/export', [CrudApiController::class, 'export'])->name('export');
        Route::get('{screen}', [CrudApiController::class, 'index'])->name('index');
        Route::post('{screen}', [CrudApiController::class, 'store'])->name('store');
        Route::get('{screen}/{id}', [CrudApiController::class, 'show'])->name('show');
        Route::post('{screen}/{id}/duplicate', [CrudApiController::class, 'duplicate'])->name('duplicate');
        // POST too: a multipart body (file upload) is only parsed on POST.
        Route::match(['put', 'post'], '{screen}/{id}', [CrudApiController::class, 'update'])->name('update');
        Route::delete('{screen}/{id}', [CrudApiController::class, 'destroy'])->name('destroy');
        Route::post('{screen}/{id}/restore', [CrudApiController::class, 'restore'])->name('restore');
    });

// The layout around the screens: the menu the Livewire sidebar draws, and
// who is signed in.
Route::middleware((array) config('ptah.crud_api.middleware', ['web', 'auth']))
    ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/'))
    ->name('ptah.api.')
    ->group(function () {
        Route::get('menu', [ShellApiController::class, 'menu'])->name('menu');
        Route::get('me', [ShellApiController::class, 'me'])->name('me');
    });

// ptah's own sign-in and profile (auth module) for the React and Vue stacks.
// Guests reach the first group; the throttles are in the actions themselves.
if (config('ptah.modules.auth')) {
    Route::middleware((array) config('ptah.auth.middleware', ['web']))
        ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/auth')
        ->name('ptah.api.auth.')
        ->group(function () {
            Route::get('config', [AuthApiController::class, 'config'])->name('config');
            Route::post('login', [AuthApiController::class, 'login'])->name('login');
            Route::post('two-factor', [AuthApiController::class, 'twoFactor'])->name('two-factor');
            Route::post('two-factor/email', [AuthApiController::class, 'twoFactorEmail'])->name('two-factor.email');
            Route::post('forgot-password', [AuthApiController::class, 'forgot'])->name('forgot');
            Route::post('reset-password', [AuthApiController::class, 'reset'])->name('reset');
            Route::post('logout', [AuthApiController::class, 'logout'])->name('logout');

            Route::middleware('auth:'.config('ptah.auth.guard', 'web'))->group(function () {
                Route::get('profile', [AuthApiController::class, 'profile'])->name('profile');
                Route::put('profile', [AuthApiController::class, 'updateProfile'])->name('profile.update');
                Route::put('password', [AuthApiController::class, 'updatePassword'])->name('password');
                Route::post('photo', [AuthApiController::class, 'updatePhoto'])->name('photo');
                Route::delete('photo', [AuthApiController::class, 'removePhoto'])->name('photo.remove');
                Route::put('appearance', [AuthApiController::class, 'updateAppearance'])->name('appearance');
                Route::post('two-factor/totp', [AuthApiController::class, 'startTotp'])->name('totp.start');
                Route::post('two-factor/totp/confirm', [AuthApiController::class, 'confirmTotp'])->name('totp.confirm');
                Route::post('two-factor/email-method', [AuthApiController::class, 'enableEmailTwoFactor'])->name('two-factor.email-method');
                Route::post('two-factor/disable', [AuthApiController::class, 'disableTwoFactor'])->name('two-factor.disable');
                Route::post('two-factor/recovery-codes', [AuthApiController::class, 'recoveryCodes'])->name('recovery-codes');
                Route::get('sessions', [AuthApiController::class, 'sessions'])->name('sessions');
                Route::delete('sessions/{session}', [AuthApiController::class, 'revokeSession'])->name('sessions.revoke');
                Route::delete('sessions', [AuthApiController::class, 'revokeOtherSessions'])->name('sessions.revoke-others');
            });
        });
}
