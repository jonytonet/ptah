<?php

declare(strict_types=1);

namespace Ptah\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
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
    public string $current_password      = '';
    public string $password              = '';
    public string $password_confirmation = '';

    // ── Aba: 2FA ───────────────────────────────────────────────────────
    public string $totpType      = '';   // totp | email
    public string $totpSecret    = '';
    public string $qrCodeSvg     = '';
    public array  $recoveryCodes = [];
    public string $totp_code     = '';
    public bool   $showSetup2fa  = false;

    // ── Aba: Sessões ───────────────────────────────────────────────────
    public array $sessions = [];

    // ── Aba: Foto ──────────────────────────────────────────────────────
    public $photo = null;

    // ── Feedback ───────────────────────────────────────────────────────
    public string $successMsg = '';
    public string $errorMsg   = '';

    public function mount(): void
    {
        $user           = Auth::user();
        $this->name     = $user->name  ?? '';
        $this->email    = $user->email ?? '';
        $this->totpType = $user->two_factor_type ?? '';
    }

    // ── Perfil ─────────────────────────────────────────────────────────────

    public function saveProfile(): void
    {
        $this->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        Auth::user()->forceFill([
            'name'  => $this->name,
            'email' => $this->email,
        ])->save();

        $this->flash('Perfil atualizado com sucesso!');
    }

    // ── Senha ──────────────────────────────────────────────────────────────

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (! Hash::check($this->current_password, $user->password)) {
            $this->errorMsg = 'Senha atual incorreta.';
            return;
        }

        $user->forceFill(['password' => Hash::make($this->password)])->save();
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->flash('Senha alterada com sucesso!');
    }

    // ── 2FA ────────────────────────────────────────────────────────────────

    public function initTotp(TwoFactorService $twoFactor): void
    {
        $data = $twoFactor->enableTotp(Auth::user());

        $this->totpSecret    = $data['secret'];
        $this->qrCodeSvg     = $data['qr_image_uri'];
        $this->recoveryCodes = $data['recovery_codes'];
        $this->totpType      = 'totp';
        $this->showSetup2fa  = true;
    }

    public function confirmTotp(TwoFactorService $twoFactor): void
    {
        $this->validate(['totp_code' => 'required|string|size:6']);

        if ($twoFactor->confirmTotp(Auth::user(), $this->totp_code, $this->recoveryCodes)) {
            $this->showSetup2fa  = false;
            $this->recoveryCodes = [];
            $this->flash('Autenticação TOTP ativada!');
        } else {
            $this->errorMsg = 'Código inválido. Tente novamente.';
        }

        $this->reset('totp_code');
    }

    public function enableEmailTwoFactor(TwoFactorService $twoFactor): void
    {
        $twoFactor->sendEmailCode(Auth::user());
        $this->totpType = 'email';
        $this->flash('Código enviado! Verifique seu e-mail para confirmar.');
    }

    public function loadRecoveryCodes(TwoFactorService $twoFactor): void
    {
        $this->recoveryCodes = $twoFactor->getRecoveryCodes(Auth::user());
    }

    public function regenerateRecoveryCodes(TwoFactorService $twoFactor): void
    {
        $this->recoveryCodes = $twoFactor->regenerateRecoveryCodes(Auth::user());
        $this->flash('Códigos regenerados. Guarde-os em local seguro!');
    }

    public function disableTwoFactor(TwoFactorService $twoFactor): void
    {
        $twoFactor->disable(Auth::user());
        $this->totpType     = '';
        $this->showSetup2fa = false;
        $this->flash('Autenticação em duas etapas desativada.');
    }

    // ── Sessões ────────────────────────────────────────────────────────────

    public function loadSessions(SessionService $sessionService): void
    {
        $this->sessions = $sessionService->getActiveSessions(Auth::user())->toArray();
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

    // ── Foto ───────────────────────────────────────────────────────────────

    public function savePhoto(): void
    {
        $this->validate(['photo' => 'required|image|max:2048']);

        $old  = Auth::user()->profile_photo_path;
        $path = $this->photo->store('profile-photos', 'public');

        Auth::user()->forceFill(['profile_photo_path' => $path])->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $this->reset('photo');
        $this->flash('Foto atualizada!');
    }

    public function removePhoto(): void
    {
        $user = Auth::user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->forceFill(['profile_photo_path' => null])->save();
        }

        $this->flash('Foto removida.');
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
