<?php

declare(strict_types=1);

namespace Ptah\Livewire\Auth;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Ptah\Models\UserPreference;
use Ptah\Services\Auth\TwoFactorService;
use Ptah\Support\AppearancePresets;

#[Layout('ptah::layouts.forge-auth')]
class TwoFactorChallengePage extends Component
{
    #[Rule('required|string')]
    public string $code = '';

    public bool $usingRecovery = false;

    public string $errorMsg = '';

    public function mount(): void
    {
        if (! Session::has('ptah.2fa.user_id')) {
            $this->redirect(route('ptah.auth.login'), navigate: true);
        }
    }

    public function verify(TwoFactorService $twoFactor): void
    {
        $this->validate();
        $this->errorMsg = '';

        $userId = Session::get('ptah.2fa.user_id');

        // Throttle code attempts to prevent brute-forcing the 6-digit code.
        $throttleKey = 'ptah-2fa|'.$userId.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->errorMsg = trans('ptah::ui.auth_too_many_attempts', ['seconds' => $seconds]);

            return;
        }

        $userModel = config('auth.providers.users.model', 'App\\Models\\User');
        $user = $userModel::findOrFail($userId);

        $valid = false;

        if ($this->usingRecovery) {
            $valid = $twoFactor->verifyRecoveryCode($user, trim($this->code));
        } elseif ($user->two_factor_type === 'email') {
            $valid = $twoFactor->verifyEmailCode($user, trim($this->code));
        } else {
            $valid = $twoFactor->verifyTotp($user, trim($this->code));
        }

        if (! $valid) {
            RateLimiter::hit($throttleKey);
            $this->errorMsg = trans('ptah::ui.two_fa_code_invalid');
            $this->reset('code');

            return;
        }

        RateLimiter::clear($throttleKey);
        Session::forget('ptah.2fa.user_id');
        Auth::loginUsingId($userId);
        event(new Login('web', $user, false));
        Session::regenerate();

        // Same seed/refresh as LoginPage::login() for the non-2FA path — this IS
        // the other half of "login", and without it a 2FA user's browser would
        // never pick up their saved theme on the login screen.
        AppearancePresets::queueCookie(AppearancePresets::sanitize(UserPreference::get($userId, 'theme')));

        $this->redirect(config('ptah.auth.home', '/dashboard'), navigate: true);
    }

    public function sendEmailCode(TwoFactorService $twoFactor): void
    {
        $userId = Session::get('ptah.2fa.user_id');
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');
        $user = $userModel::findOrFail($userId);
        $twoFactor->sendEmailCode($user);
        session()->flash('code_sent', trans('ptah::ui.two_fa_email_sent', ['email' => $user->email]));
    }

    public function render()
    {
        return view('ptah::livewire.auth.two-factor-challenge');
    }
}
