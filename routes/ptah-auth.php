<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Livewire\Auth\ForgotPasswordPage;
use Ptah\Livewire\Auth\LoginPage;
use Ptah\Livewire\Auth\ProfilePage;
use Ptah\Livewire\Auth\ResetPasswordPage;
use Ptah\Livewire\Auth\TwoFactorChallengePage;
use Ptah\Models\UserPreference;
use Ptah\Support\AppearancePresets;
use Ptah\Support\PtahGuard;

/*
|--------------------------------------------------------------------------
| Ptah Auth Routes
|--------------------------------------------------------------------------
| Carregadas apenas quando config('ptah.modules.auth') === true.
| Todas usam o prefixo e o guard configurados em config/ptah.php.
*/

$prefix = config('ptah.auth.route_prefix', '');
$guard = config('ptah.auth.guard', 'web');
$middleware = config('ptah.auth.middleware', ['web']);

Route::middleware($middleware)->group(function () use ($prefix, $guard) {

    // ── Rotas públicas ───────────────────────────────────────────────────
    Route::prefix($prefix)->group(function () {

        Route::get('/login', LoginPage::class)
            ->name('ptah.auth.login');

        Route::get('/forgot-password', ForgotPasswordPage::class)
            ->name('ptah.auth.forgot-password');

        // Not `password.reset` since 1.53.0: Fortify/Jetstream register that
        // name too. See Ptah\Support\PtahPasswordReset.
        Route::get('/reset-password/{token}', ResetPasswordPage::class)
            ->name('ptah.auth.password.reset');

        Route::get('/two-factor-challenge', TwoFactorChallengePage::class)
            ->name('ptah.auth.two-factor');

        Route::post('/logout', function () {
            PtahGuard::guard()->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('ptah.auth.login');
        })->name('ptah.auth.logout');

    });

    // ── Rotas protegidas ─────────────────────────────────────────────────
    // auth:<ptah guard> (1.53.0): plain 'auth' checked the default guard, so a
    // host whose admins use another guard was bounced off its own profile.
    Route::prefix($prefix)->middleware('auth:'.$guard)->group(function () {

        Route::get('/dashboard', fn () => view('ptah::livewire.auth.dashboard'))
            ->name('ptah.dashboard');

        Route::get('/profile', ProfilePage::class)
            ->name('ptah.profile');

        // Persiste o toggle claro/escuro do navbar (forge-dashboard-layout.blade.php,
        // método toggleDark()) além do localStorage — fire-and-forget via fetch(),
        // não bloqueia a UI. As outras 3 preferências de aparência (tom claro, tom
        // escuro, accent, cor da fonte) são persistidas via Livewire pela própria
        // ProfilePage (aba "Aparência"); esta rota cobre só o eixo "mode", que é o
        // único acionável fora dessa página.
        Route::post('/appearance/theme-mode', function () {
            $data = request()->validate([
                'mode' => 'required|in:'.implode(',', AppearancePresets::MODE),
            ]);

            $theme = AppearancePresets::sanitize(UserPreference::get(PtahGuard::id(), 'theme'));
            $theme['mode'] = $data['mode'];

            UserPreference::set(PtahGuard::id(), 'theme', $theme, 'appearance');
            AppearancePresets::queueCookie($theme);

            return response()->noContent();
        })->name('ptah.appearance.theme-mode');

    });

});
