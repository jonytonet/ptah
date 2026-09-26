<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Permission;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Services\Permission\PermissionService;
use Ptah\Support\PermissionKeyScanner;
use Ptah\Tests\TestCase;

/**
 * Achado #2 do PetPlace: 47 `@ptahCan` com chaves nunca registradas negavam
 * em silencio para todo nao-master — e quem testa costuma ser master.
 */
class UnknownPermissionKeyTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ptah.modules.permissions' => true]);

        $page = PtahPage::create(['slug' => 'sales', 'name' => 'Sales', 'is_active' => true]);
        PageObject::create(['page_id' => $page->id, 'section' => 'main', 'obj_key' => 'sales.orders', 'obj_label' => 'Orders', 'obj_type' => 'page', 'obj_order' => 1, 'is_active' => true]);

        $this->tmp = sys_get_temp_dir().'/ptah-keys-'.uniqid();
        $fs = new Filesystem;
        $fs->ensureDirectoryExists($this->tmp.'/resources/views');
        $fs->ensureDirectoryExists($this->tmp.'/app/Http');
        $fs->ensureDirectoryExists($this->tmp.'/routes');
        $fs->put($this->tmp.'/resources/views/invoice.blade.php', "<div>\n@ptahCan('sales.orders', 'read') ok @endptahCan\n@ptahCan('purchasing.invoices', 'update') <button>Dar entrada</button> @endptahCan\n{{-- @ptahCan('only.in.a.comment', 'read') --}}\n</div>\n");
        $fs->put($this->tmp.'/app/Http/Thing.php', "<?php\n// ptah_can('commented.out', 'read')\nif (ptah_can(\"health.records\", 'create')) {}\n");
        $fs->put($this->tmp.'/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
        $fs->put($this->tmp.'/routes/web.php', "<?php\nRoute::get('/x', fn () => 1)->middleware('ptah.can:financial.payables,update');\n");
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->tmp);

        parent::tearDown();
    }

    #[Test]
    public function the_scanner_finds_literal_keys_and_skips_comments(): void
    {
        $keys = array_column(PermissionKeyScanner::scan([$this->tmp.'/app', $this->tmp.'/resources/views', $this->tmp.'/routes'], $this->tmp), 'key');
        sort($keys);

        $this->assertSame(['financial.payables', 'health.records', 'purchasing.invoices', 'sales.orders'], $keys);
    }

    #[Test]
    public function a_known_key_is_known_and_an_unknown_one_is_not(): void
    {
        $service = app(PermissionService::class);

        $this->assertTrue($service->isKnownKey('sales.orders'));
        $this->assertTrue($service->isKnownKey('sales::sales.orders'), 'A forma qualificada tambem existe.');
        $this->assertFalse($service->isKnownKey('purchasing.invoices'));
    }

    #[Test]
    public function in_debug_a_denied_unknown_key_is_logged_once(): void
    {
        config(['app.debug' => true]);
        Log::spy();
        $service = app(PermissionService::class);

        $service->check(55, 'purchasing.invoices', 'update');
        $service->check(55, 'purchasing.invoices', 'update');
        $service->check(55, 'sales.orders', 'read'); // conhecida: negada por falta de grant, sem aviso

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($m) => str_contains($m, '"purchasing.invoices" is not a registered page object'));
    }

    #[Test]
    public function outside_debug_nothing_is_logged(): void
    {
        config(['app.debug' => false]);
        Log::spy();

        app(PermissionService::class)->check(55, 'purchasing.invoices', 'update');

        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function ptah_check_lists_the_unknown_keys_with_where_they_are(): void
    {
        $this->app->useAppPath($this->tmp.'/app');
        $this->app->setBasePath($this->tmp);
        CrudConfig::create(['model' => 'Nope', 'route' => '', 'config' => ['cols' => [], 'permissions' => ['permissionIdentifier' => 'pos.sale']]]);

        $this->artisan('ptah:check')
            ->expectsOutputToContain('not registered page objects')
            ->expectsOutputToContain('purchasing.invoices  resources/views/invoice.blade.php:3')
            ->expectsOutputToContain('pos.sale  crud_config Nope')
            ->assertExitCode(1); // a tela "Nope" nao resolve um model
    }
}
