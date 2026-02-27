<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Livewire\Auth\ForgotPasswordPage;
use Ptah\Livewire\Auth\LoginPage;
use Ptah\Livewire\Auth\ProfilePage;
use Ptah\Livewire\Auth\ResetPasswordPage;
use Ptah\Livewire\Auth\TwoFactorChallengePage;

/*
|--------------------------------------------------------------------------
| Ptah Auth Routes
|--------------------------------------------------------------------------
| Carregadas apenas quando config('ptah.modules.auth') === true.
| Todas usam o prefixo e o guard configurados em config/ptah.php.
*/

$prefix = config('ptah.auth.route_prefix', '');
$guard  = config('ptah.auth.guard', 'web');
$middleware = config('ptah.auth.middleware', ['web']);

Route::middleware($middleware)->group(function () use ($prefix) {

    // ── Rotas públicas ───────────────────────────────────────────────────
    Route::prefix($prefix)->group(function () {

        Route::get('/login', LoginPage::class)
             ->name('ptah.auth.login');

        Route::get('/forgot-password', ForgotPasswordPage::class)
             ->name('ptah.auth.forgot-password');

        Route::get('/reset-password/{token}', ResetPasswordPage::class)
             ->name('password.reset');

        Route::get('/two-factor-challenge', TwoFactorChallengePage::class)
             ->name('ptah.auth.two-factor');

        Route::post('/logout', function () {
            $guard = config('ptah.auth.guard', 'web');
            auth($guard)->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            return redirect()->route('ptah.auth.login');
        })->name('ptah.auth.logout');

    });

    // ── Rotas protegidas ─────────────────────────────────────────────────
    Route::prefix($prefix)->middleware('auth')->group(function () {

        Route::get('/dashboard', fn () => view('ptah::livewire.auth.dashboard'))
             ->name('ptah.dashboard');

        Route::get('/profile', ProfilePage::class)
             ->name('ptah.profile');

    });

});
