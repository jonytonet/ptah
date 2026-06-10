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

        RateLimiter::hit($throttleKey, 300);

        $response = Password::sendResetLink(['email' => $this->email]);

        if ($response === Password::RESET_LINK_SENT) {
            $this->status = trans('ptah::ui.auth_link_sent');
            $this->reset('email');
        } else {
            $this->errorMsg = __($response);
        }
    }

    public function render()
    {
        return view('ptah::livewire.auth.forgot-password');
    }
}
