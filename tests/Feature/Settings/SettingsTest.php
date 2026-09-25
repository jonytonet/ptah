<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Settings;

use Illuminate\Filesystem\Filesystem;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Settings\SettingsPage;
use Ptah\Models\Setting;
use Ptah\Services\SettingsService;
use Ptah\Tests\Support\ActsAsPtahUser;
use Ptah\Tests\TestCase;

/**
 * System settings — typed values, company → global → default, only declared
 * keys, and a screen with the structure screens' access rule.
 */
class SettingsTest extends TestCase
{
    use ActsAsPtahUser;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().'/ptah-settings-'.uniqid();
        $this->app->useDatabasePath($this->tmp.'/database');
        $this->app->useConfigPath($this->tmp.'/config');
        $this->artisan('ptah:settings:install')->assertExitCode(0);
        foreach (glob($this->tmp.'/database/migrations/*.php') as $file) {
            (require $file)->up();
        }
        Setting::flushTableCache();

        config(['ptah-settings.per_company' => true, 'ptah-settings.definitions' => [
            'invoice_due_days' => ['label' => 'Vencimento', 'type' => 'integer', 'group' => 'Financeiro', 'default' => 30, 'rules' => 'min:0|max:365'],
            'approval' => ['label' => 'Exige aprovação', 'type' => 'boolean', 'group' => 'Vendas', 'default' => false],
            'carrier' => ['label' => 'Transportadora', 'type' => 'select', 'options' => ['correios' => 'Correios', 'jadlog' => 'Jadlog'], 'default' => 'correios'],
        ]]);

        session(['ptah_company_id' => 7]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->tmp);
        Setting::flushTableCache();

        parent::tearDown();
    }

    private function settings(): SettingsService
    {
        return app(SettingsService::class);
    }

    #[Test]
    public function the_install_command_writes_the_migration_and_the_config_once(): void
    {
        $this->assertFileExists($this->tmp.'/config/ptah-settings.php');
        $this->artisan('ptah:settings:install')->expectsOutputToContain('Already installed')->assertExitCode(0);
        $this->assertCount(1, glob($this->tmp.'/database/migrations/*_create_ptah_settings_table.php'));
    }

    #[Test]
    public function values_resolve_company_then_global_then_default_with_their_type(): void
    {
        $this->assertSame(30, ptah_setting('invoice_due_days'), 'Sem valor gravado: o padrao declarado.');

        $this->settings()->set('invoice_due_days', '45', 0);
        $this->assertSame(45, ptah_setting('invoice_due_days'), 'Global vale para a empresa sem valor proprio — e volta inteiro.');

        $this->settings()->set('invoice_due_days', '10', 7);
        $this->assertSame(10, ptah_setting('invoice_due_days'));
        $this->assertSame(45, ptah_setting('invoice_due_days', null, 8), 'Outra empresa continua no global.');

        $this->settings()->set('approval', '1', 7);
        $this->assertTrue(ptah_setting('approval'));

        $this->settings()->reset('invoice_due_days', 7);
        $this->assertSame(45, ptah_setting('invoice_due_days'));
    }

    #[Test]
    public function changing_the_global_value_reaches_companies_already_cached(): void
    {
        $this->assertSame(30, ptah_setting('invoice_due_days', null, 8)); // popula o cache da empresa 8

        $this->settings()->set('invoice_due_days', 60, 0);

        $this->assertSame(60, ptah_setting('invoice_due_days', null, 8));
    }

    #[Test]
    public function an_undeclared_key_reads_as_the_default_and_cannot_be_written(): void
    {
        $this->assertSame('x', ptah_setting('typo_key', 'x'));

        $this->expectException(\InvalidArgumentException::class);
        $this->settings()->set('typo_key', 'y');
    }

    #[Test]
    public function the_screen_saves_validated_values_for_the_active_company(): void
    {
        $this->actAsMaster();
        config(['ptah.modules.permissions' => true]);

        Livewire::test(SettingsPage::class)
            ->assertSee('Financeiro')
            ->set('values.invoice_due_days', 400)
            ->call('save')
            ->assertSet('errorsBag.invoice_due_days', fn ($m) => is_string($m))
            ->set('values.invoice_due_days', 15)
            ->set('values.carrier', 'jadlog')
            ->call('save')
            ->assertSet('errorsBag', []);

        $this->assertSame(15, ptah_setting('invoice_due_days'));
        $this->assertSame('jadlog', ptah_setting('carrier'));
        $this->assertSame(30, ptah_setting('invoice_due_days', null, 8), 'Salvo so para a empresa ativa.');
    }

    #[Test]
    public function a_select_value_outside_its_options_is_refused(): void
    {
        $this->actAsMaster();
        config(['ptah.modules.permissions' => true]);

        Livewire::test(SettingsPage::class)
            ->set('values.carrier', 'hacked')
            ->call('save')
            ->assertSet('errorsBag.carrier', fn ($m) => is_string($m));

        $this->assertSame('correios', ptah_setting('carrier'));
    }

    #[Test]
    public function a_non_master_cannot_open_the_screen(): void
    {
        config(['ptah.modules.permissions' => true]);
        $this->actAsMaster(false);

        Livewire::test(SettingsPage::class)->assertStatus(403);
    }
}
