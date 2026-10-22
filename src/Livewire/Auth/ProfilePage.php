<?php

declare(strict_types=1);

namespace Ptah\Livewire\Auth;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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

    /** Current password, asked only when the e-mail changes (1.41.7). */
    public string $email_password = '';

    /** Current password for every 2FA change (1.41.8). */
    public string $twofa_password = '';

    /**
     * The TOTP secret being set up, held here until a code confirms it —
     * locked, so the browser cannot swap in a secret of its own choosing.
     */
    #[Locked]
    public string $pendingTotpSecret = '';

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

    public string $themeDensity = AppearancePresets::DEFAULT_DENSITY;

    public string $themeFontsize = AppearancePresets::DEFAULT_FONTSIZE;

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
        $this->themeDensity = $theme['density'];
        $this->themeFontsize = $theme['fontsize'];
    }

    // ── Profile ────────────────────────────────────────────────────────────

    public function saveProfile(): void
    {
        $user = Auth::user();
        $emailChanged = mb_strtolower(trim($this->email)) !== mb_strtolower((string) ($user->email ?? ''));

        $this->validate([
            'name' => 'required|string|max:255',
            // unique: sem ele, dois usuarios com o mesmo e-mail (ou um 500 no
            // indice unico), e o "esqueci a senha" iria para o errado.
            'email' => ['required', 'email', 'max:255', Rule::unique($user->getTable(), 'email')->ignore($user->getKey(), $user->getKeyName())],
        ]);

        // Trocar o e-mail pede a senha atual: com o e-mail, quem esta com uma
        // sessao roubada pedia "esqueci a senha" para si e tomava a conta —
        // e o codigo do 2FA por e-mail passava a ir para ele (auditoria de
        // 28/09/2026, 1.41.7).
        if ($emailChanged && ! Hash::check($this->email_password, (string) $user->getAuthPassword())) {
            $this->addError('email_password', trans('ptah::ui.profile_password_wrong'));

            return;
        }

        $user->forceFill([
            'name' => $this->name,
            'email' => $this->email,
        ])->save();
        $this->reset('email_password');

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

        // Trocar a senha derruba as outras sessoes e o "lembrar-me": antes a
        // sessao ja aberta de quem roubou a conta seguia valendo (1.41.8).
        $user->forceFill(['password' => Hash::make($this->password)]);
        $user->setRememberToken(Str::random(60));
        $user->save();
        app(SessionService::class)->revokeOtherSessions($user, session()->getId());
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->flash(trans('ptah::ui.profile_password_updated'));
    }

    // ── 2FA ────────────────────────────────────────────────────────────────

    /**
     * Every 2FA change asks for the current password: with a stolen session,
     * turning 2FA off, swapping the secret or reading the recovery codes made
     * the theft permanent (audit of 28/09/2026, 1.41.8).
     */
    private function passwordConfirmed(): bool
    {
        if (Hash::check($this->twofa_password, (string) Auth::user()->getAuthPassword())) {
            return true;
        }

        $this->addError('twofa_password', trans('ptah::ui.profile_password_wrong'));

        return false;
    }

    public function initTotp(TwoFactorService $twoFactor): void
    {
        if (! config('ptah.auth.two_factor', true)) {
            return; // 1.50.0: 2FA setup is off (ptah.auth.two_factor)
        }

        if (! $this->passwordConfirmed()) {
            return;
        }

        // Nada e gravado ate o codigo conferir: o enableTotp() gravava o
        // segredo novo antes, e o autenticador que funcionava parava.
        try {
            $data = $twoFactor->startTotp(Auth::user());
        } catch (\RuntimeException $e) {
            // pragmarx/google2fa-laravel not installed (1.50.1: was a 500).
            $this->errorMsg = $e->getMessage();
            $this->dispatch('ptah-toast', title: $e->getMessage(), color: 'danger');

            return;
        }
        $this->pendingTotpSecret = $data['secret'];

        $this->totpSecret = $data['secret'];
        $this->qrCodeSvg = $data['qr_image_uri'];
        $this->recoveryCodes = $data['recovery_codes'];
        $this->totpType = 'totp';
        $this->showSetup2fa = true;
    }

    public function confirmTotp(TwoFactorService $twoFactor): void
    {
        if (! config('ptah.auth.two_factor', true)) {
            return; // 1.50.0: 2FA setup is off (ptah.auth.two_factor)
        }

        $this->validate(['totp_code' => 'required|string|size:6']);

        if ($twoFactor->confirmPendingTotp(Auth::user(), $this->pendingTotpSecret, $this->totp_code, $this->recoveryCodes)) {
            $this->showSetup2fa = false;
            $this->recoveryCodes = [];
            $this->pendingTotpSecret = '';
            $this->reset('twofa_password');
            $this->flash(trans('ptah::ui.profile_totp_enabled'));
        } else {
            $this->errorMsg = trans('ptah::ui.profile_totp_invalid');
        }

        $this->reset('totp_code');
    }

    public function enableEmailTwoFactor(TwoFactorService $twoFactor): void
    {
        if (! config('ptah.auth.two_factor', true)) {
            return; // 1.50.0: 2FA setup is off (ptah.auth.two_factor)
        }

        if (! $this->passwordConfirmed()) {
            return;
        }

        // Throttle code sends to prevent email bombing (an attacker repeatedly
        // triggering this action to flood the victim's inbox). Same key shape
        // as TwoFactorChallengePage::sendEmailCode(), scoped by user+IP.
        $sendThrottleKey = 'ptah-2fa-send|'.Auth::id().'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($sendThrottleKey, 3)) {
            $seconds = RateLimiter::availableIn($sendThrottleKey);
            $this->errorMsg = trans('ptah::ui.two_fa_email_rate_limited', ['seconds' => $seconds]);

            return;
        }

        RateLimiter::hit($sendThrottleKey, 60);

        $twoFactor->sendEmailCode(Auth::user());
        $this->totpType = 'email';
        $this->flash(trans('ptah::ui.profile_email_2fa_sent'));
    }

    public function loadRecoveryCodes(TwoFactorService $twoFactor): void
    {
        if (! $this->passwordConfirmed()) {
            return;
        }

        $this->recoveryCodes = $twoFactor->getRecoveryCodes(Auth::user());
    }

    public function regenerateRecoveryCodes(TwoFactorService $twoFactor): void
    {
        if (! config('ptah.auth.two_factor', true)) {
            return; // 1.50.0: 2FA setup is off (ptah.auth.two_factor)
        }

        if (! $this->passwordConfirmed()) {
            return;
        }

        $this->recoveryCodes = $twoFactor->regenerateRecoveryCodes(Auth::user());
        $this->flash(trans('ptah::ui.profile_recovery_regen'));
    }

    public function disableTwoFactor(TwoFactorService $twoFactor): void
    {
        if (! $this->passwordConfirmed()) {
            return;
        }

        $twoFactor->disable(Auth::user());
        $this->reset('twofa_password');
        $this->totpType = '';
        $this->showSetup2fa = false;
        $this->flash(trans('ptah::ui.profile_2fa_disabled'));
    }

    // ── Sessions ───────────────────────────────────────────────────────────

    public function loadSessions(SessionService $sessionService): void
    {
        if (! config('ptah.auth.session_protection', true)) {
            return; // 1.50.0: session management is off (ptah.auth.session_protection)
        }

        $this->sessions = $sessionService->getActiveSessions(Auth::user())->toArray();
    }

    public function revokeSession(string $sessionId, SessionService $sessionService): void
    {
        if (! config('ptah.auth.session_protection', true)) {
            return; // 1.50.0: session management is off (ptah.auth.session_protection)
        }

        $sessionService->revokeSession($sessionId, Auth::user());
        // O aparelho revogado com "lembrar-me" logava de novo sozinho: o cookie
        // vale enquanto o remember_token nao muda (1.41.8).
        self::cycleRememberToken();
        $this->loadSessions($sessionService);
        $this->flash(trans('ptah::ui.profile_session_revoked'));
    }

    public function revokeOtherSessions(SessionService $sessionService): void
    {
        if (! config('ptah.auth.session_protection', true)) {
            return; // 1.50.0: session management is off (ptah.auth.session_protection)
        }

        $count = $sessionService->revokeOtherSessions(
            Auth::user(),
            Request::session()->getId()
        );
        self::cycleRememberToken();
        $this->loadSessions($sessionService);
        $this->flash(trans('ptah::ui.profile_sessions_revoked', ['count' => $count]));
    }

    private static function cycleRememberToken(): void
    {
        $user = Auth::user();

        if ($user !== null && $user->getRememberTokenName() !== '') {
            $user->setRememberToken(Str::random(60));
            $user->save();
        }
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

    public function setDensity(string $value): void
    {
        $this->setAppearanceAxis('density', $value);
    }

    public function setFontsize(string $value): void
    {
        $this->setAppearanceAxis('fontsize', $value);
    }

    /**
     * "Voltar ao original": restores the 6 preset axes (light, dark, accent,
     * text, density, fontsize) to their defaults. Deliberately leaves `mode`
     * (claro/escuro) untouched — that is the navbar toggle's own setting,
     * persisted via the `ptah.appearance.theme-mode` route, and a user
     * clicking "restore defaults" on the Aparência tab does not expect it to
     * also flip their light/dark choice.
     */
    public function resetAppearance(): void
    {
        $this->themeLight = AppearancePresets::DEFAULT_LIGHT;
        $this->themeDark = AppearancePresets::DEFAULT_DARK;
        $this->themeAccent = AppearancePresets::defaultAccent();
        $this->themeText = AppearancePresets::DEFAULT_TEXT;
        $this->themeDensity = AppearancePresets::DEFAULT_DENSITY;
        $this->themeFontsize = AppearancePresets::DEFAULT_FONTSIZE;

        $theme = AppearancePresets::sanitize(UserPreference::get(Auth::id(), 'theme'));
        $theme['light'] = AppearancePresets::DEFAULT_LIGHT;
        $theme['dark'] = AppearancePresets::DEFAULT_DARK;
        $theme['accent'] = AppearancePresets::defaultAccent();
        $theme['text'] = AppearancePresets::DEFAULT_TEXT;
        $theme['density'] = AppearancePresets::DEFAULT_DENSITY;
        $theme['fontsize'] = AppearancePresets::DEFAULT_FONTSIZE;

        if ($this->persistTheme($theme)) {
            $this->flash(trans('ptah::ui.profile_appearance_updated'));
        }
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

        if ($this->persistTheme($theme)) {
            $this->flash(trans('ptah::ui.profile_appearance_updated'));
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Saves the appearance: the cookie first (it alone repaints the next page),
     * then the preference row.
     *
     * 1.46.5 (Engepecas B2B): with `user_preferences.user_id` still pointing at
     * `users` while the identity is another table, the row insert hit the FK
     * and the whole screen was a 500. That host state is what
     * `ptah:upgrade-check` flags and `ptah:preferences:realign` fixes; here it
     * becomes a warning toast, and the theme still applies through the cookie.
     *
     * @param  array<string, string>  $theme
     * @return bool false when only the cookie was saved
     */
    protected function persistTheme(array $theme): bool
    {
        AppearancePresets::queueCookie($theme);

        try {
            UserPreference::set(Auth::id(), 'theme', $theme, 'appearance');
        } catch (QueryException $e) {
            if (! self::isForeignKeyViolation($e)) {
                throw $e;
            }
            report($e);
            $this->dispatch('ptah-toast', title: trans('ptah::ui.profile_appearance_not_saved'), color: 'warn');

            return false;
        }

        return true;
    }

    /** MySQL 1452/1216, PostgreSQL 23503, SQLite's message. */
    private static function isForeignKeyViolation(QueryException $e): bool
    {
        return $e->getCode() === '23503'
            || in_array($e->errorInfo[1] ?? null, [1216, 1452], true)
            || str_contains(strtolower($e->getMessage()), 'foreign key constraint');
    }

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
