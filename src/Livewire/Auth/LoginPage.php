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

#[Layout('ptah::layouts.forge-auth')]
class LoginPage extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    #[Rule('required|string')]
    public string $password = '';

    public bool $remember = false;

    public string $errorMessage = '';

    public function login(TwoFactorService $twoFactor): void
    {
        $this->validate();
        $this->errorMessage = '';

        $throttleKey = Str::lower($this->email) . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->errorMessage = "Muitas tentativas. Tente novamente em {$seconds} segundos.";
            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);
            $this->errorMessage = 'E-mail ou senha incorretos.';
            $this->reset('password');
            return;
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::user();

        // 2FA ativo → redireciona para o challenge
        if ($twoFactor->isEnabled($user)) {
            Session::put('ptah.2fa.user_id', $user->getKey());
            Auth::logout();
            $this->redirect(route('ptah.auth.two-factor'));
            return;
        }

        Session::regenerate();
        $this->redirect(config('ptah.auth.home', '/dashboard'), navigate: true);
    }

    public function render()
    {
        return view('ptah::livewire.auth.login');
    }
}
