<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\CrudConfig;
use Ptah\Models\CrudConfig as CrudConfigModel;
use Ptah\Tests\TestCase;

/**
 * Round-trip regression for the RBAC key migration (ACL hardening, phase 1):
 * opening the config editor on a screen still stored under the legacy
 * `permissions.identifier` key, then saving, must migrate it to
 * `permissionIdentifier` — the key the runtime actually reads
 * (HasCrudForm::authorizeCrudAction / BaseCrud::getEffectivePermissions /
 * ExportController) — without losing the configured value. Before the fix,
 * `identifier` was silently never read: the screen ran ungated.
 */
class CrudConfigLegacyPermissionMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Exercises the editor's load/save round-trip, not its authorization gate
        // (covered separately by CrudConfigAuthorizationTest) — module off + the
        // opt-in editor flag on lets save() through deterministically.
        config()->set('ptah.modules.permissions', false);
        config()->set('ptah.crud.config_editor', true);
    }

    #[Test]
    public function opening_and_saving_a_legacy_config_migrates_the_rbac_key(): void
    {
        CrudConfigModel::create([
            'model' => 'Widget',
            'route' => '',
            'config' => [
                'cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text']],
                'permissions' => [
                    'identifier' => 'pageFoo',
                    'create' => 'widgets.create',
                ],
            ],
        ]);

        // mount() unconditionally calls loadFromDb() — the fallback added in
        // phase 1 (permissionIdentifier ?? identifier) populates the editor's
        // bound property straight from the legacy key.
        $editor = Livewire::test(CrudConfig::class, ['model' => 'Widget'])
            ->assertSet('permissionIdentifier', 'pageFoo');

        // save() rebuilds the whole `permissions` sub-array from the editor's
        // properties — this is the real persistence path, not a hand-rolled one.
        // It persists under $configRoute (the mounted request path — a random
        // Livewire test-endpoint path here, NOT the '' the row was seeded under),
        // so the fallback lookup in CrudConfigService::find() is what makes the
        // legacy global row visible to the editor in the first place.
        $editor->call('save');
        $route = $editor->get('configRoute');

        $row = CrudConfigModel::where('model', 'Widget')->where('route', $route)->first();

        $this->assertNotNull($row);
        $this->assertSame('pageFoo', $row->config['permissions']['permissionIdentifier']);
        $this->assertArrayNotHasKey('identifier', $row->config['permissions']);
        // The rest of the permissions config survives the round-trip too.
        $this->assertSame('widgets.create', $row->config['permissions']['create']);
    }
}
