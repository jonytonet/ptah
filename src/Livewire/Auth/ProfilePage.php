<?php

declare(strict_types=1);

namespace Ptah\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Ptah\Services\Auth\SessionService;
use Ptah\Services\Auth\TwoFactorService;

#[Layout('ptah::layouts.forge-dashboard')]
class ProfilePage extends Component
{
    use WithFileUploads;

    public string $activeTab = 'profile';

    // ── Aba: Perfil ────────────────────────────────────────────────────
    public string $name  = '';
    public string $email = '';

    // ── Aba: Senha ─────────────────────────────────────────────────────
    #[Rule('required|string|min:8')]
    public string $current_password = '';

    #[Rule('required|string|min:8|confirmed')]
    public string $new_password = '';

    #[Rule('required|string')]
    public string $new_password_confirmation = '';

    // ── Aba: 2FA ───────────────────────────────────────────────────────
    public string $twoFactorType   = '';  // totp | email
    public string $totpSecret      = '';
    public string $totpQrUri       = '';
    public array  $recoveryCodes   = [];
    public string $twoFactorCode   = '';
    public bool   $showSetup2fa    = false;

    // ── Aba: Sessões ───────────────────────────────────────────────────
    public array $sessions = [];

    // ── Aba: Foto ──────────────────────────────────────────────────────
    public $photo = null;

    // ── Feedback ───────────────────────────────────────────────────────
    public string $successMsg = '';
    public string $errorMsg   = '';

    public function mount(): void
    {
        $user        = Auth::user();
        $this->name  = $user->name ?? '';
        $this->email = $user->email ?? '';
        $this->twoFactorType = $user->two_factor_type ?? '';
    }

    // ── Profile ────────────────────────────────────────────────────────────

    public function updateProfile(): void
    {
        $this->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255']);
        $user = Auth::user();

        $user->forceFill(['name' => $this->name, 'email' => $this->email])->save();
        $this->flash('Perfil atualizado com sucesso!');
    }

    // ── Password ───────────────────────────────────────────────────────────

    public function updatePassword(): void
    {
        $this->validate([
            'current_password'          => 'required',
            'new_password'              => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (! Hash::check($this->current_password, $user->password)) {
            $this->errorMsg = 'Senha atual incorreta.';
            return;
        }

        $user->forceFill(['password' => Hash::make($this->new_password)])->save();
        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        $this->flash('Senha alterada com sucesso!');
    }

    // ── 2FA ────────────────────────────────────────────────────────────────

    public function initTotp(TwoFactorService $twoFactor): void
    {
        $data = $twoFactor->enableTotp(Auth::user());
        $this->totpSecret    = $data['secret'];
        $this->totpQrUri     = $data['qr_image_uri'];
        $this->recoveryCodes = $data['recovery_codes'];
        $this->twoFactorType = 'totp';
        $this->showSetup2fa  = true;
    }

    public function confirmTotp(TwoFactorService $twoFactor): void
    {
        $this->validate(['twoFactorCode' => 'required|string|size:6']);

        if ($twoFactor->confirmTotp(Auth::user(), $this->twoFactorCode, $this->recoveryCodes)) {
            $this->showSetup2fa  = false;
            $this->recoveryCodes = [];
            $this->flash('Autenticação TOTP ativada!');
        } else {
            $this->errorMsg = 'Código inválido. Tente novamente.';
        }

        $this->reset('twoFactorCode');
    }

    public function enableEmailTwoFactor(TwoFactorService $twoFactor): void
    {
        $twoFactor->sendEmailCode(Auth::user());
        $this->twoFactorType = 'email';
        $this->flash('Código enviado! Verifique seu e-mail para confirmar.');
    }

    public function disableTwoFactor(TwoFactorService $twoFactor): void
    {
        $twoFactor->disable(Auth::user());
        $this->twoFactorType = '';
        $this->flash('Autenticação em duas etapas desativada.');
    }

    // ── Sessions ───────────────────────────────────────────────────────────

    public function loadSessions(SessionService $sessionService): void
    {
        $sessions = $sessionService->getActiveSessions(Auth::user());
        $this->sessions = $sessions->toArray();
    }

    public function revokeSession(string $sessionId, SessionService $sessionService): void
    {
        $sessionService->revokeSession($sessionId);
        $this->loadSessions($sessionService);
        $this->flash('Sessão encerrada.');
    }

    public function revokeOtherSessions(SessionService $sessionService): void
    {
        $count = $sessionService->revokeOtherSessions(
            Auth::user(),
            Request::session()->getId()
        );
        $this->loadSessions($sessionService);
        $this->flash("{$count} sessão(ões) encerrada(s).");
    }

    // ── Photo ──────────────────────────────────────────────────────────────

    public function updatePhoto(): void
    {
        $this->validate(['photo' => 'required|image|max:2048']);
        $path = $this->photo->store('profile-photos', 'public');
        Auth::user()->forceFill(['profile_photo_path' => $path])->save();
        $this->reset('photo');
        $this->flash('Foto atualizada!');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function flash(string $msg): void
    {
        $this->successMsg = $msg;
        $this->errorMsg   = '';
    }

    public function render()
    {
        return view('ptah::livewire.auth.profile');
    }
}
