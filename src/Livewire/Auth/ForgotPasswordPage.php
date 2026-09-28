<?php

declare(strict_types=1);

namespace Ptah\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('ptah::layouts.forge-auth')]
class ForgotPasswordPage extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    public string $status = '';

    public string $errorMsg = '';

    public function sendLink(): void
    {
        $this->validate();
        $this->status = '';
        $this->errorMsg = '';

        // Throttle reset-link requests to prevent email-bombing / enumeration.
        $throttleKey = 'ptah-forgot|'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->errorMsg = trans('ptah::ui.auth_too_many_attempts', ['seconds' => $seconds]);

            return;
        }

        // Um teto por IP tambem: o limite por e-mail+IP deixava testar
        // e-mails diferentes sem fim.
        $ipKey = 'ptah-forgot-ip|'.request()->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            $this->errorMsg = trans('ptah::ui.auth_too_many_attempts', ['seconds' => RateLimiter::availableIn($ipKey)]);

            return;
        }

        RateLimiter::hit($throttleKey, 300);
        RateLimiter::hit($ipKey, 300);

        Password::sendResetLink(['email' => $this->email]);

        // A mesma resposta exista ou nao o e-mail: "We can't find a user with
        // that email address" contava a quem perguntasse quem tem conta aqui
        // (auditoria de 28/09/2026, 1.41.8). O throttle do broker tambem so
        // vale para quem existe, entao tambem nao pode aparecer.
        $this->status = trans('ptah::ui.auth_link_sent');
        $this->reset('email');
    }

    public function render()
    {
        return view('ptah::livewire.auth.forgot-password');
    }
}
