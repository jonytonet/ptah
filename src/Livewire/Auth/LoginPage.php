<?php

declare(strict_types=1);

namespace Ptah\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Ptah\Services\Auth\TwoFactorService;
use Ptah\Services\Company\CompanyService;

#[Layout('ptah::layouts.forge-auth')]
class LoginPage extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    #[Rule('required|string')]
    public string $password = '';

    public bool $remember = false;

    public string $errorMessage = '';

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirect(config('ptah.auth.home', '/dashboard'), navigate: true);
        }
    }

    public function login(TwoFactorService $twoFactor): void
    {
        $this->validate();
        $this->errorMessage = '';

        $throttleKey = Str::lower($this->email) . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->errorMessage = trans('ptah::ui.auth_too_many_attempts', ['seconds' => $seconds]);
            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);
            $this->errorMessage = trans('ptah::ui.auth_invalid_credentials');
            $this->reset('password');
            return;
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::user();

        // 2FA active → redirect to the challenge
        if ($twoFactor->isEnabled($user)) {
            Session::put('ptah.2fa.user_id', $user->getKey());
            Auth::logout();
            $this->redirect(route('ptah.auth.two-factor'));
            return;
        }

        Session::regenerate();

        // Set the active company in the session (is_default → first active → first of all)
        app(CompanyService::class)->initSession();

        $this->redirect(config('ptah.auth.home', '/dashboard'), navigate: true);
    }

    public function render()
    {
        return view('ptah::livewire.auth.login');
    }
}
