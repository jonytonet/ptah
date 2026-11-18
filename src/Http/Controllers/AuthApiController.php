<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Ptah\Http\Headless\HeadlessForgotPassword;
use Ptah\Http\Headless\HeadlessLogin;
use Ptah\Http\Headless\HeadlessProfile;
use Ptah\Http\Headless\HeadlessResetPassword;
use Ptah\Http\Headless\HeadlessTwoFactorChallenge;
use Ptah\Models\UserPreference;
use Ptah\Services\Auth\TwoFactorService;
use Ptah\Support\AppearancePresets;
use Ptah\Support\ProfilePhoto;
use Ptah\Support\PtahGuard;

/**
 * ptah's sign-in and profile, for the React and Vue stacks (1.59.0).
 *
 * Each action runs the Livewire page of the auth module as a plain object
 * (Ptah\Http\Headless\*): the throttles, the 2FA flow, the password checks
 * and the session handling are the same code. This controller only carries
 * the request in and the answer out.
 */
class AuthApiController extends Controller
{
    private const PENDING_TOTP = 'ptah.2fa.pending_setup';

    // ── Guests ──────────────────────────────────────────────────────────────

    /** What the sign-in pages need to draw themselves. */
    public function config(): JsonResponse
    {
        return response()->json([
            'app' => config('app.name'),
            'locale' => app()->getLocale(),
            'remember_me' => (bool) config('ptah.auth.remember_me', true),
            'home' => (string) config('ptah.auth.home', '/dashboard'),
            'urls' => [
                'login' => Route::has('ptah.auth.login') ? route('ptah.auth.login') : null,
                'forgot' => Route::has('ptah.auth.forgot-password') ? route('ptah.auth.forgot-password') : null,
                'two_factor' => Route::has('ptah.auth.two-factor') ? route('ptah.auth.two-factor') : null,
            ],
            'signed_in' => PtahGuard::guard()->check(),
            'two_factor_pending' => Session::has('ptah.2fa.user_id'),
            'status' => session('status'),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $page = HeadlessLogin::headless();
        $page->email = (string) $request->input('email', '');
        $page->password = (string) $request->input('password', '');
        $page->remember = $request->boolean('remember');
        $page->run('login');

        $twoFactor = Session::has('ptah.2fa.user_id');

        return $this->answer($page->errorMessage === '', [
            'two_factor' => $twoFactor,
            'redirect' => $page->redirectTarget(),
            'error' => $page->errorMessage ?: null,
        ]);
    }

    public function twoFactor(Request $request): JsonResponse
    {
        abort_unless(Session::has('ptah.2fa.user_id'), 409, 'No sign-in is waiting for a code.');

        $page = HeadlessTwoFactorChallenge::headless();
        $page->code = (string) $request->input('code', '');
        $page->usingRecovery = $request->boolean('recovery');
        $page->run('verify');

        return $this->answer($page->errorMsg === '', ['redirect' => $page->redirectTarget(), 'error' => $page->errorMsg ?: null]);
    }

    public function twoFactorEmail(): JsonResponse
    {
        abort_unless(Session::has('ptah.2fa.user_id'), 409, 'No sign-in is waiting for a code.');

        $page = HeadlessTwoFactorChallenge::headless();
        $page->run('sendEmailCode');

        return $this->answer($page->errorMsg === '', ['message' => session('code_sent'), 'error' => $page->errorMsg ?: null]);
    }

    public function forgot(Request $request): JsonResponse
    {
        $page = HeadlessForgotPassword::headless();
        $page->email = (string) $request->input('email', '');
        $page->run('sendLink');

        return $this->answer($page->errorMsg === '', ['message' => $page->status ?: null, 'error' => $page->errorMsg ?: null]);
    }

    public function reset(Request $request): JsonResponse
    {
        $page = HeadlessResetPassword::headless(token: (string) $request->input('token', ''));
        $page->email = (string) $request->input('email', '');
        $page->password = (string) $request->input('password', '');
        $page->password_confirmation = (string) $request->input('password_confirmation', '');
        $page->run('resetPassword');

        return $this->answer($page->errorMsg === '', [
            'redirect' => $page->redirectTarget(),
            'message' => session('status'),
            'error' => $page->errorMsg ?: null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        PtahGuard::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true, 'redirect' => Route::has('ptah.auth.login') ? route('ptah.auth.login') : '/']);
    }

    // ── The signed-in user's profile ────────────────────────────────────────

    public function profile(TwoFactorService $twoFactor): JsonResponse
    {
        return response()->json($this->profilePayload($twoFactor));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $page = HeadlessProfile::headless();
        $page->name = (string) $request->input('name', '');
        $page->email = (string) $request->input('email', '');
        $page->email_password = (string) $request->input('current_password', '');

        return $this->profileAction($page, 'saveProfile');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $page = HeadlessProfile::headless();
        $page->current_password = (string) $request->input('current_password', '');
        $page->password = (string) $request->input('password', '');
        $page->password_confirmation = (string) $request->input('password_confirmation', '');

        return $this->profileAction($page, 'savePassword');
    }

    public function updatePhoto(Request $request): JsonResponse
    {
        $page = HeadlessProfile::headless();
        $page->photo = $request->file('photo');

        return $this->profileAction($page, 'savePhoto');
    }

    public function removePhoto(): JsonResponse
    {
        return $this->profileAction(HeadlessProfile::headless(), 'removePhoto');
    }

    /** One or more of light, dark, accent, text, density, fontsize; `reset: true` restores them. */
    public function updateAppearance(Request $request): JsonResponse
    {
        $page = HeadlessProfile::headless();

        if ($request->boolean('reset')) {
            return $this->profileAction($page, 'resetAppearance');
        }

        foreach (['light' => 'setLight', 'dark' => 'setDark', 'accent' => 'setAccent', 'text' => 'setText', 'density' => 'setDensity', 'fontsize' => 'setFontsize'] as $axis => $setter) {
            if (is_string($request->input($axis))) {
                // Each setter checks the value against its own whitelist.
                $page->run($setter, ['value' => $request->input($axis)]);
            }
        }

        return $this->profileAnswer($page);
    }

    // 2FA — every change asks for the current password, as on the Livewire page.

    public function startTotp(Request $request): JsonResponse
    {
        $page = $this->profileWithPassword($request);
        $page->run('initTotp');

        if ($page->pendingTotpSecret !== '') {
            // The Livewire page keeps the pending secret in its (locked) state
            // between two requests; here it waits in the session.
            Session::put(self::PENDING_TOTP, ['secret' => $page->pendingTotpSecret, 'codes' => $page->recoveryCodes]);
        }

        return $this->profileAnswer($page, [
            'secret' => $page->totpSecret ?: null,
            'qr' => $page->qrCodeSvg ?: null,
            'recovery_codes' => $page->recoveryCodes,
        ]);
    }

    public function confirmTotp(Request $request): JsonResponse
    {
        $pending = (array) Session::get(self::PENDING_TOTP, []);
        abort_if(($pending['secret'] ?? '') === '', 409, 'No authenticator setup is in progress.');

        $page = HeadlessProfile::headless();
        $page->pendingTotpSecret = (string) $pending['secret'];
        $page->recoveryCodes = (array) ($pending['codes'] ?? []);
        $page->totp_code = (string) $request->input('code', '');
        $page->run('confirmTotp');

        if ($page->errorMsg === '') {
            Session::forget(self::PENDING_TOTP);
        }

        return $this->profileAnswer($page);
    }

    public function enableEmailTwoFactor(Request $request): JsonResponse
    {
        return $this->profileAction($this->profileWithPassword($request), 'enableEmailTwoFactor');
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        Session::forget(self::PENDING_TOTP);

        return $this->profileAction($this->profileWithPassword($request), 'disableTwoFactor');
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $page = $this->profileWithPassword($request);
        $page->run($request->boolean('regenerate') ? 'regenerateRecoveryCodes' : 'loadRecoveryCodes');

        return $this->profileAnswer($page, ['recovery_codes' => $page->recoveryCodes]);
    }

    // Sessions

    public function sessions(): JsonResponse
    {
        $page = HeadlessProfile::headless();
        $page->run('loadSessions');

        return response()->json(['data' => array_values($page->sessions)]);
    }

    public function revokeSession(string $session): JsonResponse
    {
        $page = HeadlessProfile::headless();
        $page->run('revokeSession', ['sessionId' => $session]);

        return $this->profileAnswer($page, ['sessions' => array_values($page->sessions)]);
    }

    public function revokeOtherSessions(): JsonResponse
    {
        $page = HeadlessProfile::headless();
        $page->run('revokeOtherSessions');

        return $this->profileAnswer($page, ['sessions' => array_values($page->sessions)]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function profileWithPassword(Request $request): HeadlessProfile
    {
        $page = HeadlessProfile::headless();
        $page->twofa_password = (string) $request->input('current_password', '');

        return $page;
    }

    private function profileAction(HeadlessProfile $page, string $action): JsonResponse
    {
        $page->run($action);

        return $this->profileAnswer($page);
    }

    /**
     * The outcome of a profile action: refused when the page set an error
     * message or an error on a field (a wrong current password).
     *
     * @param  array<string, mixed>  $extra
     */
    private function profileAnswer(HeadlessProfile $page, array $extra = []): JsonResponse
    {
        $errors = array_map(fn (array $messages) => (string) ($messages[0] ?? ''), $page->getErrorBag()->toArray());
        // The field the Livewire page calls email_password / twofa_password.
        foreach (['email_password', 'twofa_password'] as $alias) {
            if (isset($errors[$alias])) {
                $errors['current_password'] = $errors[$alias];
                unset($errors[$alias]);
            }
        }

        $ok = $page->errorMsg === '' && $errors === [];

        return $this->answer($ok, array_merge([
            'error' => $page->errorMsg ?: null,
            'errors' => (object) $errors,
            'messages' => $page->toasts(),
            'profile' => $this->profilePayload(app(TwoFactorService::class)),
        ], $extra));
    }

    /** @return array<string, mixed> */
    private function profilePayload(TwoFactorService $twoFactor): array
    {
        $user = PtahGuard::user();
        $theme = AppearancePresets::sanitize(UserPreference::get(PtahGuard::id(), 'theme'));

        return [
            'name' => (string) ($user->name ?? ''),
            'email' => (string) ($user->email ?? ''),
            'photo_url' => ProfilePhoto::url($user),
            'two_factor' => [
                'available' => (bool) config('ptah.auth.two_factor', true),
                'enabled' => $twoFactor->isEnabled($user),
                'type' => (string) ($user->two_factor_type ?? ''),
            ],
            'sessions_available' => (bool) config('ptah.auth.session_protection', true),
            'appearance' => [
                'light' => $theme['light'],
                'dark' => $theme['dark'],
                'accent' => $theme['accent'],
                'text' => $theme['text'],
                'density' => $theme['density'],
                'fontsize' => $theme['fontsize'],
                'mode' => $theme['mode'] ?? null,
                'choices' => [
                    'light' => AppearancePresets::LIGHT,
                    'dark' => AppearancePresets::DARK,
                    'accent' => AppearancePresets::accentChoices(),
                    'text' => AppearancePresets::TEXT,
                    'density' => AppearancePresets::DENSITY,
                    'fontsize' => AppearancePresets::FONTSIZE,
                ],
            ],
        ];
    }

    /** @param  array<string, mixed>  $body */
    private function answer(bool $ok, array $body): JsonResponse
    {
        return response()->json(['ok' => $ok] + $body, $ok ? 200 : 422);
    }
}
