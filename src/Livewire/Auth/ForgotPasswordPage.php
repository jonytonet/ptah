<?php

declare(strict_types=1);

namespace Ptah\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('ptah::layouts.forge-auth')]
class ForgotPasswordPage extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    public string $status  = '';
    public string $errorMsg = '';

    public function sendLink(): void
    {
        $this->validate();
        $this->status   = '';
        $this->errorMsg = '';

        $response = Password::sendResetLink(['email' => $this->email]);

        if ($response === Password::RESET_LINK_SENT) {
            $this->status = 'Link de recuperação enviado! Verifique seu e-mail.';
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
