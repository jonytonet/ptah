<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Auth;

use BaconQrCode\Renderer\ImageRenderer;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Auth\ProfilePage;
use Ptah\Services\Auth\TwoFactorService;
use Ptah\Tests\TestCase;
use ReflectionMethod;

class TotpLibUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

/**
 * 1.50.1: the optional TOTP libraries are not installed in this suite, which
 * is exactly the case being tested.
 */
class TotpWithoutLibrariesTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('auth.providers.users.model', TotpLibUser::class);
    }

    /** Without bacon/bacon-qr-code the QR fallback sent the otpauth URL (with the secret) to Google Charts. */
    #[Test]
    public function no_qr_library_means_no_third_party_url(): void
    {
        $this->assertFalse(class_exists(ImageRenderer::class), 'Pre-condicao: bacon ausente.');

        $uri = (new ReflectionMethod(TwoFactorService::class, 'qrCodeUri'))
            ->invoke(app(TwoFactorService::class), 'otpauth://totp/App:a@x.com?secret=JBSWY3DPEHPK3PXP');

        $this->assertSame('', $uri);
    }

    /** Without pragmarx/google2fa-laravel starting TOTP setup was a 500. */
    #[Test]
    public function no_totp_library_is_an_error_message_not_a_500(): void
    {
        $this->actingAs(TotpLibUser::create(['name' => 'A', 'email' => 'a'.uniqid().'@x.com', 'password' => bcrypt('secret')]));

        Livewire::test(ProfilePage::class)
            ->set('twofa_password', 'secret')
            ->call('initTotp')
            ->assertSet('showSetup2fa', false)
            ->assertSet('errorMsg', 'Install pragmarx/google2fa-laravel to use TOTP.');
    }
}
