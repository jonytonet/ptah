<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Support\ModelKey;
use Ptah\Tests\TestCase;

class DoctorStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name'];
}

class DoctorStubTwo extends Model
{
    protected $table = 'items';

    protected $fillable = ['name'];
}

/**
 * Covers ptah:config:doctor — the author-time audit that catches the silent
 * failures the per-model tooling can't: orphan (non-canonical) keys, malformed
 * configs, empty screens and global-vs-route ambiguity.
 */
class ConfigDoctorCommandTest extends TestCase
{
    private function seedConfig(string $model, string $route, array $config): void
    {
        CrudConfig::create(['model' => $model, 'route' => $route, 'config' => $config]);
    }

    private function goodConfig(): array
    {
        return ['cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text']]];
    }

    #[Test]
    public function clean_configs_pass(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->goodConfig());

        $this->artisan('ptah:config:doctor')->assertExitCode(0);
    }

    #[Test]
    public function orphan_key_is_reported_then_fixed(): void
    {
        // Stored under the FQCN (backslashes) — the runtime reads the slash form.
        $this->seedConfig(DoctorStub::class, '', $this->goodConfig());
        $canonical = ModelKey::canonical(DoctorStub::class);

        // Without --fix: reported as an error.
        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('orphan key')
            ->assertExitCode(1);

        // With --fix: key rewritten to canonical, now clean.
        $this->artisan('ptah:config:doctor --fix')->assertExitCode(0);

        $this->assertDatabaseHas('crud_configs', ['model' => $canonical]);
        $this->assertDatabaseMissing('crud_configs', ['model' => DoctorStub::class]);
    }

    #[Test]
    public function malformed_config_is_an_error(): void
    {
        // Column without colsNomeFisico → ConfigSchemaValidator rejects it.
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', ['cols' => [['colsTipo' => 'text']]]);

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('malformed')
            ->assertExitCode(1);
    }

    #[Test]
    public function empty_columns_warns_but_does_not_fail(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', ['cols' => []]);

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('no columns')
            ->assertExitCode(0);
    }

    #[Test]
    public function global_plus_route_specific_is_flagged(): void
    {
        $canonical = ModelKey::canonical(DoctorStub::class);
        $this->seedConfig($canonical, '', $this->goodConfig());
        $this->seedConfig($canonical, 'invoices', $this->goodConfig());

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('route fallback')
            ->assertExitCode(0);
    }

    #[Test]
    public function legacy_rbac_key_is_reported_then_fixed(): void
    {
        $canonical = ModelKey::canonical(DoctorStub::class);
        $config = $this->goodConfig();
        $config['permissions'] = ['identifier' => 'pageDoctorStub'];
        $this->seedConfig($canonical, '', $config);

        // Without --fix: reported as an error, gate silently absent.
        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('legacy RBAC key')
            ->assertExitCode(1);

        // With --fix: value migrated to the canonical key, legacy key removed.
        $this->artisan('ptah:config:doctor --fix')->assertExitCode(0);

        $row = CrudConfig::where('model', $canonical)->first();
        $this->assertSame('pageDoctorStub', $row->config['permissions']['permissionIdentifier']);
        $this->assertArrayNotHasKey('identifier', $row->config['permissions']);

        // Re-run is clean.
        $this->artisan('ptah:config:doctor')->assertExitCode(0);
    }

    #[Test]
    public function legacy_styles_key_is_reported_then_migrated_idempotently(): void
    {
        $canonical = ModelKey::canonical(DoctorStub::class);
        $config = $this->goodConfig();
        $config['styles'] = [
            ['colsNomeFisico' => 'status', 'colsOperator' => 'eq', 'colsValue' => 'cancelled', 'colsCss' => 'background:red;'],
        ];
        $this->seedConfig($canonical, '', $config);

        // Without --fix: reported as an error, the rule never applies.
        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('legacy styles key')
            ->assertExitCode(1);

        // With --fix: the rule is normalised and folded into contitionStyles,
        // the legacy 'styles' key is dropped.
        $this->artisan('ptah:config:doctor --fix')->assertExitCode(0);

        $row = CrudConfig::where('model', $canonical)->first();
        $this->assertArrayNotHasKey('styles', $row->config);
        $this->assertSame(
            ['field' => 'status', 'condition' => '==', 'value' => 'cancelled', 'style' => 'background:red;'],
            $row->config['contitionStyles'][0]
        );

        // Re-run is clean and the stored config is byte-identical (idempotent).
        $before = $row->config;
        $this->artisan('ptah:config:doctor')->assertExitCode(0);
        $after = CrudConfig::where('model', $canonical)->first()->config;
        $this->assertSame($before, $after);
    }

    #[Test]
    public function unusable_row_style_warns_but_does_not_fail(): void
    {
        $canonical = ModelKey::canonical(DoctorStub::class);
        $config = $this->goodConfig();
        $config['contitionStyles'] = [
            ['field' => 'status', 'condition' => 'LIKE', 'value' => 'cancelled', 'style' => 'background:red;'],
        ];
        $this->seedConfig($canonical, '', $config);

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('unusable row style')
            ->assertExitCode(0);
    }

    #[Test]
    public function shared_permission_identifier_across_different_models_warns(): void
    {
        $configA = $this->goodConfig();
        $configA['permissions'] = ['permissionIdentifier' => 'pageShared'];
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $configA);

        $configB = $this->goodConfig();
        $configB['permissions'] = ['permissionIdentifier' => 'pageShared'];
        $this->seedConfig(ModelKey::canonical(DoctorStubTwo::class), '', $configB);

        // Warning only — does not fail the exit code, same as "no columns".
        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('permissionIdentifier collision')
            ->assertExitCode(0);
    }

    #[Test]
    public function distinct_permission_identifiers_do_not_warn(): void
    {
        $configA = $this->goodConfig();
        $configA['permissions'] = ['permissionIdentifier' => 'pageStubA'];
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $configA);

        $configB = $this->goodConfig();
        $configB['permissions'] = ['permissionIdentifier' => 'pageStubB'];
        $this->seedConfig(ModelKey::canonical(DoctorStubTwo::class), '', $configB);

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('permissionIdentifier collision')
            ->assertExitCode(0);
    }

    #[Test]
    public function shared_obj_key_across_different_pages_warns(): void
    {
        $pageA = PtahPage::create(['slug' => 'page-a', 'name' => 'Page A']);
        $pageB = PtahPage::create(['slug' => 'page-b', 'name' => 'Page B']);

        PageObject::create([
            'page_id' => $pageA->id,
            'section' => 'main',
            'obj_key' => 'shared.button',
            'obj_label' => 'Shared button',
            'obj_type' => 'button',
        ]);
        PageObject::create([
            'page_id' => $pageB->id,
            'section' => 'main',
            'obj_key' => 'shared.button',
            'obj_label' => 'Shared button',
            'obj_type' => 'button',
        ]);

        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->goodConfig());

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('obj_key collision')
            ->assertExitCode(0);
    }

    #[Test]
    public function obj_key_scoped_to_a_single_page_does_not_warn(): void
    {
        $page = PtahPage::create(['slug' => 'page-solo', 'name' => 'Page Solo']);

        PageObject::create([
            'page_id' => $page->id,
            'section' => 'main',
            'obj_key' => 'solo.button',
            'obj_label' => 'Solo button',
            'obj_type' => 'button',
        ]);

        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->goodConfig());

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('obj_key collision')
            ->assertExitCode(0);
    }
}
