<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\CrudConfig;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;

/**
 * The CRUD config editor writes joins / lifecycle hooks / link templates / custom
 * methods — inputs that feed SQL and render sinks — so opening and (critically)
 * saving it must be authorized. The component is reachable by name, so the gate
 * lives on the server methods, not only on the toolbar trigger.
 *
 * ptah_can_manage_config():
 *  - permissions module ON  → master OR 'crud.config' manage grant;
 *  - module OFF             → ptah.crud.config_editor flag, DENY by default.
 */
class CrudConfigAuthorizationTest extends TestCase
{
    private function editor()
    {
        return Livewire::test(CrudConfig::class, ['model' => 'Widget']);
    }

    // ── Module OFF: governed by the opt-in flag ─────────────────────────────

    #[Test]
    public function denied_by_default_when_module_off_and_flag_unset(): void
    {
        config()->set('ptah.modules.permissions', false);
        config()->set('ptah.crud.config_editor', false);

        // A tampered payload marks the modal open and calls save() directly.
        $this->editor()
            ->set('displayName', 'Hacked')
            ->call('save');

        $this->assertDatabaseMissing('crud_configs', ['model' => 'Widget']);
    }

    #[Test]
    public function open_and_preview_are_no_ops_when_denied(): void
    {
        config()->set('ptah.modules.permissions', false);
        config()->set('ptah.crud.config_editor', false);

        $this->editor()
            ->call('openModal')
            ->assertSet('showModal', false)
            ->call('previewForm')
            ->assertSet('showPreview', false);
    }

    #[Test]
    public function opt_in_flag_allows_opening_and_saving(): void
    {
        config()->set('ptah.modules.permissions', false);
        config()->set('ptah.crud.config_editor', true);

        $this->editor()
            ->call('openModal')
            ->assertSet('showModal', true)
            ->set('displayName', 'Widgets')
            ->call('save');

        $this->assertDatabaseHas('crud_configs', ['model' => 'Widget']);
    }

    // ── Module ON: governed by master / ptah_can ────────────────────────────

    #[Test]
    public function module_on_denies_a_non_master_user(): void
    {
        config()->set('ptah.modules.permissions', true);
        // Flag is irrelevant when the module is on.
        config()->set('ptah.crud.config_editor', true);

        $this->mockPermission(master: false, can: false);

        $this->editor()
            ->set('displayName', 'Hacked')
            ->call('save');

        $this->assertDatabaseMissing('crud_configs', ['model' => 'Widget']);
    }

    #[Test]
    public function module_on_allows_a_master_user(): void
    {
        config()->set('ptah.modules.permissions', true);
        $this->mockPermission(master: true, can: false);

        $this->editor()
            ->call('openModal')
            ->assertSet('showModal', true)
            ->set('displayName', 'Widgets')
            ->call('save');

        $this->assertDatabaseHas('crud_configs', ['model' => 'Widget']);
    }

    /** Binds a PermissionService stub so ptah_is_master()/ptah_can() are deterministic. */
    private function mockPermission(bool $master, bool $can): void
    {
        $stub = new class($master, $can) extends PermissionService
        {
            public function __construct(private bool $master, private bool $can) {}

            public function isMaster(mixed $user = null): bool
            {
                return $this->master;
            }

            public function check(mixed $user, string $objectKey, string $action, ?int $companyId = null): bool
            {
                return $this->can;
            }
        };

        $this->app->instance(PermissionService::class, $stub);
    }
}
