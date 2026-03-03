<?php

declare(strict_types=1);

namespace Ptah\Services\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TwoFactorService
{
    private const EMAIL_PREFIX = 'ptah_2fa_email_';
    private const EMAIL_TTL    = 600; // 10 minutes

    // ── TOTP ───────────────────────────────────────────────────────────────

    /**
     * Generates a new TOTP secret, QR code SVG data URI and recovery codes.
     * Requires pragmarx/google2fa-laravel.
     */
    public function enableTotp(Authenticatable $user): array
    {
        if (! class_exists(\PragmaRX\Google2FALaravel\Google2FA::class)) {
            throw new \RuntimeException('Install pragmarx/google2fa-laravel to use TOTP.');
        }

        /** @var \PragmaRX\Google2FA\Google2FA $google2fa */
        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);

        $secret        = $google2fa->generateSecretKey();
        $recoveryCodes = $this->generateRecoveryCodes();
        $appName       = config('app.name', 'Ptah');
        $email         = $user->email ?? (string) $user->getKey();

        $qrUrl = $google2fa->getQRCodeUrl($appName, $email, $secret);

        // Saved temporarily — confirmed only after verification
        $user->forceFill(['two_factor_secret' => encrypt($secret)])->save();

        $qrImageUri = $this->qrCodeUri($qrUrl);

        return [
            'secret'         => $secret,
            'qr_image_uri'   => $qrImageUri,
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /**
     * Confirms TOTP activation after the user validates the code.
     */
    public function confirmTotp(Authenticatable $user, string $code, array $recoveryCodes): bool
    {
        if (! $this->verifyTotp($user, $code)) {
            return false;
        }

        $user->forceFill([
            'two_factor_type'           => 'totp',
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        return true;
    }

    /**
     * Verifies a TOTP code.
     */
    public function verifyTotp(Authenticatable $user, string $code): bool
    {
        if (! class_exists(\PragmaRX\Google2FA\Google2FA::class)) {
            return false;
        }

        $secret = decrypt($user->two_factor_secret ?? '');

        /** @var \PragmaRX\Google2FA\Google2FA $google2fa */
        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);

        return (bool) $google2fa->verifyKey($secret, $code);
    }

    // ── Email OTP ──────────────────────────────────────────────────────────

    /**
     * Generates and sends a 6-digit code via e-mail.
     */
    public function sendEmailCode(Authenticatable $user): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put(self::EMAIL_PREFIX . $user->getKey(), $code, self::EMAIL_TTL);

        Mail::to($user->email)->send(new \Ptah\Mail\TwoFactorCodeMail($code));

        // Activates the email type if no 2FA was previously configured
        if (is_null($user->two_factor_type)) {
            $user->forceFill([
                'two_factor_type'         => 'email',
                'two_factor_confirmed_at' => now(),
            ])->save();
        }
    }

    /**
     * Verifies the e-mail code.
     */
    public function verifyEmailCode(Authenticatable $user, string $code): bool
    {
        $stored = Cache::get(self::EMAIL_PREFIX . $user->getKey());

        if ($stored && hash_equals($stored, $code)) {
            Cache::forget(self::EMAIL_PREFIX . $user->getKey());
            return true;
        }

        return false;
    }

    // ── Recovery ───────────────────────────────────────────────────────────

    /**
     * Verifies and consumes a recovery code (single use).
     */
    public function verifyRecoveryCode(Authenticatable $user, string $code): bool
    {
        $codes = json_decode(decrypt($user->two_factor_recovery_codes ?? encrypt('[]')), true) ?? [];

        $index = array_search(hash('sha256', $code), array_map(fn($c) => hash('sha256', $c), $codes));

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode(array_values($codes))),
        ])->save();

        return true;
    }

    /**
     * Returns the user's decrypted recovery codes.
     */
    public function getRecoveryCodes(Authenticatable $user): array
    {
        if (! $user->two_factor_recovery_codes) {
            return [];
        }

        return json_decode(decrypt($user->two_factor_recovery_codes), true) ?? [];
    }

    /**
     * Generates new recovery codes, saves them encrypted and returns the plain array.
     */
    public function regenerateRecoveryCodes(Authenticatable $user): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($codes)),
        ])->save();

        return $codes;
    }

    /**
     * Disables 2FA entirely.
     */
    public function disable(Authenticatable $user): void
    {
        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
            'two_factor_type'           => null,
        ])->save();
    }

    /**
     * Does the user have 2FA active and confirmed?
     */
    public function isEnabled(Authenticatable $user): bool
    {
        return ! is_null($user->two_factor_confirmed_at);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function generateRecoveryCodes(int $count = 8): array
    {
        return Collection::times($count, fn() => Str::random(10) . '-' . Str::random(10))->all();
    }

    private function qrCodeUri(string $url): string
    {
        if (class_exists(\BaconQrCode\Renderer\ImageRenderer::class)) {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            );
            $writer = new \BaconQrCode\Writer($renderer);
            return 'data:image/svg+xml;base64,' . base64_encode($writer->writeString($url));
        }

        // Fallback: Google Charts API
        return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . urlencode($url);
    }
}
