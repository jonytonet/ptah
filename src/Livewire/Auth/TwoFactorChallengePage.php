<?php

declare(strict_types=1);

namespace Ptah\Livewire\Auth;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Ptah\Services\Auth\TwoFactorService;

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
        $userModel = config('auth.providers.users.model', \App\Models\User::class);
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
            $this->errorMsg = 'Código inválido ou expirado.';
            $this->reset('code');
            return;
        }

        Session::forget('ptah.2fa.user_id');
        Auth::loginUsingId($userId);
        event(new Login('web', $user, false));
        Session::regenerate();

        $this->redirect(config('ptah.auth.home', '/dashboard'), navigate: true);
    }

    public function sendEmailCode(TwoFactorService $twoFactor): void
    {
        $userId = Session::get('ptah.2fa.user_id');
        $userModel = config('auth.providers.users.model', \App\Models\User::class);
        $user = $userModel::findOrFail($userId);
        $twoFactor->sendEmailCode($user);
        session()->flash('code_sent', 'Código enviado para ' . $user->email);
    }

    public function render()
    {
        return view('ptah::livewire.auth.two-factor-challenge');
    }
}
