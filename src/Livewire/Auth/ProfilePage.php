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
use Ptah\Models\UserPreference;
use Ptah\Services\Auth\SessionService;
use Ptah\Services\Auth\TwoFactorService;
use Ptah\Support\AppearancePresets;

#[Layout('ptah::layouts.forge-dashboard')]
class ProfilePage extends Component
{
    use WithFileUploads;

    public string $activeTab = 'profile';

    // ── Tab: Profile ───────────────────────────────────────────────────
    public string $name = '';

    public string $email = '';

    // ── Tab: Password ──────────────────────────────────────────────────
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    // ── Tab: 2FA ────────────────────────────────────────────────────────
    public string $totpType = '';   // totp | email

    public string $totpSecret = '';

    public string $qrCodeSvg = '';

    public array $recoveryCodes = [];

    public string $totp_code = '';

    public bool $showSetup2fa = false;

    // ── Tab: Sessions ──────────────────────────────────────────────────
    public array $sessions = [];

    // ── Tab: Photo ─────────────────────────────────────────────────────
    public $photo = null;

    // ── Tab: Appearance ────────────────────────────────────────────────
    public string $themeLight = AppearancePresets::DEFAULT_LIGHT;

    public string $themeDark = AppearancePresets::DEFAULT_DARK;

    public string $themeAccent = AppearancePresets::DEFAULT_ACCENT;

    public string $themeText = AppearancePresets::DEFAULT_TEXT;

    // ── Feedback ───────────────────────────────────────────────────────
    public string $successMsg = '';

    public string $errorMsg = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name ?? '';
        $this->email = $user->email ?? '';
        $this->totpType = $user->two_factor_type ?? '';

        $theme = AppearancePresets::sanitize(UserPreference::get(Auth::id(), 'theme'));
        $this->themeLight = $theme['light'];
        $this->themeDark = $theme['dark'];
        $this->themeAccent = $theme['accent'];
        $this->themeText = $theme['text'];
    }

    // ── Profile ────────────────────────────────────────────────────────────

    public function saveProfile(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        Auth::user()->forceFill([
            'name' => $this->name,
            'email' => $this->email,
        ])->save();

        $this->flash(trans('ptah::ui.profile_updated'));
    }

    // ── Password ───────────────────────────────────────────────────────────

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (! Hash::check($this->current_password, $user->password)) {
            $this->errorMsg = trans('ptah::ui.profile_password_wrong');

            return;
        }

        $user->forceFill(['password' => Hash::make($this->password)])->save();
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->flash(trans('ptah::ui.profile_password_updated'));
    }

    // ── 2FA ────────────────────────────────────────────────────────────────

    public function initTotp(TwoFactorService $twoFactor): void
    {
        $data = $twoFactor->enableTotp(Auth::user());

        $this->totpSecret = $data['secret'];
        $this->qrCodeSvg = $data['qr_image_uri'];
        $this->recoveryCodes = $data['recovery_codes'];
        $this->totpType = 'totp';
        $this->showSetup2fa = true;
    }

    public function confirmTotp(TwoFactorService $twoFactor): void
    {
        $this->validate(['totp_code' => 'required|string|size:6']);

        if ($twoFactor->confirmTotp(Auth::user(), $this->totp_code, $this->recoveryCodes)) {
            $this->showSetup2fa = false;
            $this->recoveryCodes = [];
            $this->flash(trans('ptah::ui.profile_totp_enabled'));
        } else {
            $this->errorMsg = trans('ptah::ui.profile_totp_invalid');
        }

        $this->reset('totp_code');
    }

    public function enableEmailTwoFactor(TwoFactorService $twoFactor): void
    {
        $twoFactor->sendEmailCode(Auth::user());
        $this->totpType = 'email';
        $this->flash(trans('ptah::ui.profile_email_2fa_sent'));
    }

    public function loadRecoveryCodes(TwoFactorService $twoFactor): void
    {
        $this->recoveryCodes = $twoFactor->getRecoveryCodes(Auth::user());
    }

    public function regenerateRecoveryCodes(TwoFactorService $twoFactor): void
    {
        $this->recoveryCodes = $twoFactor->regenerateRecoveryCodes(Auth::user());
        $this->flash(trans('ptah::ui.profile_recovery_regen'));
    }

    public function disableTwoFactor(TwoFactorService $twoFactor): void
    {
        $twoFactor->disable(Auth::user());
        $this->totpType = '';
        $this->showSetup2fa = false;
        $this->flash(trans('ptah::ui.profile_2fa_disabled'));
    }

    // ── Sessions ───────────────────────────────────────────────────────────

    public function loadSessions(SessionService $sessionService): void
    {
        $this->sessions = $sessionService->getActiveSessions(Auth::user())->toArray();
    }

    public function revokeSession(string $sessionId, SessionService $sessionService): void
    {
        $sessionService->revokeSession($sessionId);
        $this->loadSessions($sessionService);
        $this->flash(trans('ptah::ui.profile_session_revoked'));
    }

    public function revokeOtherSessions(SessionService $sessionService): void
    {
        $count = $sessionService->revokeOtherSessions(
            Auth::user(),
            Request::session()->getId()
        );
        $this->loadSessions($sessionService);
        $this->flash(trans('ptah::ui.profile_sessions_revoked', ['count' => $count]));
    }

    // ── Photo ──────────────────────────────────────────────────────────────

    public function savePhoto(): void
    {
        $this->validate(['photo' => 'required|image|max:2048']);

        $old = Auth::user()->profile_photo_path;
        $path = $this->photo->store('profile-photos', 'public');

        Auth::user()->forceFill(['profile_photo_path' => $path])->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $this->reset('photo');
        $this->flash(trans('ptah::ui.profile_photo_updated'));
    }

    public function removePhoto(): void
    {
        $user = Auth::user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->forceFill(['profile_photo_path' => null])->save();
        }

        $this->flash(trans('ptah::ui.profile_photo_removed'));
    }

    // ── Appearance ─────────────────────────────────────────────────────────

    public function setLight(string $value): void
    {
        $this->setAppearanceAxis('light', $value);
    }

    public function setDark(string $value): void
    {
        $this->setAppearanceAxis('dark', $value);
    }

    public function setAccent(string $value): void
    {
        $this->setAppearanceAxis('accent', $value);
    }

    public function setText(string $value): void
    {
        $this->setAppearanceAxis('text', $value);
    }

    /**
     * "Voltar ao original": restores the 4 preset axes (light, dark, accent,
     * text) to their defaults. Deliberately leaves `mode` (claro/escuro)
     * untouched — that is the navbar toggle's own setting, persisted via the
     * `ptah.appearance.theme-mode` route, and a user clicking "restore
     * defaults" on the Aparência tab does not expect it to also flip their
     * light/dark choice.
     */
    public function resetAppearance(): void
    {
        $this->themeLight = AppearancePresets::DEFAULT_LIGHT;
        $this->themeDark = AppearancePresets::DEFAULT_DARK;
        $this->themeAccent = AppearancePresets::DEFAULT_ACCENT;
        $this->themeText = AppearancePresets::DEFAULT_TEXT;

        $theme = AppearancePresets::sanitize(UserPreference::get(Auth::id(), 'theme'));
        $theme['light'] = AppearancePresets::DEFAULT_LIGHT;
        $theme['dark'] = AppearancePresets::DEFAULT_DARK;
        $theme['accent'] = AppearancePresets::DEFAULT_ACCENT;
        $theme['text'] = AppearancePresets::DEFAULT_TEXT;

        UserPreference::set(Auth::id(), 'theme', $theme, 'appearance');
        AppearancePresets::queueCookie($theme);

        $this->flash(trans('ptah::ui.profile_appearance_updated'));
    }

    /**
     * Validates $value against the whitelist for $axis (light|dark|accent|text)
     * before writing anything — an un-whitelisted value has no matching CSS
     * block (see resources/css/ptah-components.css), so persisting it would
     * eventually render a `data-ptah-*` attribute that breaks every
     * var(--ptah-*) that depends on it. Silently ignored when invalid: the
     * only caller is this component's own view, built from the same
     * whitelist, so an invalid value here only ever comes from a tampered
     * Livewire request.
     */
    private function setAppearanceAxis(string $axis, string $value): void
    {
        $whitelist = AppearancePresets::whitelistFor($axis);

        if (! in_array($value, $whitelist, true)) {
            return;
        }

        $property = 'theme'.ucfirst($axis);
        $this->{$property} = $value;

        $theme = AppearancePresets::sanitize(UserPreference::get(Auth::id(), 'theme'));
        $theme[$axis] = $value;

        UserPreference::set(Auth::id(), 'theme', $theme, 'appearance');
        AppearancePresets::queueCookie($theme);

        $this->flash(trans('ptah::ui.profile_appearance_updated'));
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Success feedback goes out as a toast, not an inline alert.
     *
     * An inline alert pushed the form down on every save and stayed on screen until
     * the next render; on a settings screen where you click several options in a row
     * (the Aparência tab) it stacked up as visual noise. The toast host lives in the
     * layout and listens on the window, so this component does not need to know it
     * exists — see resources/views/components/forge-toast-host.blade.php.
     *
     * $successMsg is kept for backwards compatibility: a host that overrode the
     * profile view and renders it still works, it just no longer receives a value.
     */
    private function flash(string $msg): void
    {
        $this->errorMsg = '';

        $this->dispatch('ptah-toast', title: $msg, color: 'success');
    }

    public function render()
    {
        return view('ptah::livewire.auth.profile');
    }
}
