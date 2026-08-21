<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Permission;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Company\CompanyList;
use Ptah\Livewire\Menu\MenuList;
use Ptah\Livewire\Permission\DepartmentList;
use Ptah\Livewire\Permission\PageList;
use Ptah\Livewire\Permission\RoleList;
use Ptah\Livewire\Permission\UserPermissionList;
use Ptah\Models\Company;
use Ptah\Models\Role;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;

/**
 * FIX 2 of the Onda C ux-acl-tree audit: the module screens used a page-top
 * inline alert ($successMsg / $errorMsg) that a user who had scrolled down
 * could easily miss ("salvar com a pagina rolada parece que nada
 * aconteceu"). All six screens now dispatch the global `ptah-toast` event
 * instead — the host lives once in forge-dashboard-layout.blade.php, so
 * feedback is visible regardless of scroll position.
 *
 * Per-field validation ($errors->first(...) rendered on the form inputs
 * inside each modal) is untouched by this fix; only the page-level
 * success/error banner moved to the toast, and the now-orphaned
 * $successMsg/$errorMsg properties + their inline <x-forge-alert> markup
 * were removed.
 */
class ModuleScreensToastFeedbackTest extends TestCase
{
    private function mockMaster(bool $isMaster): void
    {
        $stub = new class($isMaster) extends PermissionService
        {
            public function __construct(private bool $master) {}

            public function isMaster(mixed $user = null): bool
            {
                return $this->master;
            }
        };

        $this->app->instance(PermissionService::class, $stub);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockMaster(true);
    }

    #[Test]
    public function role_list_dispatches_a_success_toast_on_create_and_drops_the_inline_alert(): void
    {
        $html = Livewire::test(RoleList::class)
            ->call('create')
            ->set('name', 'Editor')
            ->call('save')
            ->assertDispatched('ptah-toast', title: 'Role created.', color: 'success')
            ->html();

        $this->assertStringNotContainsString('successMsg', $html);
        $this->assertStringNotContainsString('errorMsg', $html);
    }

    #[Test]
    public function role_list_dispatches_a_success_toast_on_delete(): void
    {
        $role = Role::create(['name' => 'Deletable', 'is_active' => true]);

        Livewire::test(RoleList::class)
            ->call('confirmDelete', $role->id)
            ->call('delete')
            ->assertDispatched('ptah-toast', title: 'Role deleted.', color: 'success');
    }

    #[Test]
    public function role_list_dispatches_a_danger_toast_when_deleting_the_master_role(): void
    {
        $role = Role::create(['name' => 'Master', 'is_master' => true, 'is_active' => true]);

        Livewire::test(RoleList::class)
            ->call('confirmDelete', $role->id)
            ->call('delete')
            ->assertDispatched('ptah-toast', color: 'danger');
    }

    #[Test]
    public function department_list_dispatches_a_success_toast_on_create(): void
    {
        $html = Livewire::test(DepartmentList::class)
            ->call('create')
            ->set('name', 'Financeiro')
            ->call('save')
            ->assertDispatched('ptah-toast', title: 'Department created.', color: 'success')
            ->html();

        $this->assertStringNotContainsString('successMsg', $html);
        $this->assertStringNotContainsString('errorMsg', $html);
    }

    #[Test]
    public function page_list_dispatches_a_success_toast_on_create(): void
    {
        $html = Livewire::test(PageList::class)
            ->call('createPage')
            ->set('page_slug', 'reports')
            ->set('page_name', 'Reports')
            ->call('savePage')
            ->assertDispatched('ptah-toast', title: 'Page created.', color: 'success')
            ->html();

        $this->assertStringNotContainsString('successMsg', $html);
        $this->assertStringNotContainsString('errorMsg', $html);
    }

    #[Test]
    public function user_permission_list_dispatches_a_danger_toast_when_no_role_is_selected(): void
    {
        $html = Livewire::test(UserPermissionList::class)
            ->call('openUserModal', 1, 'Someone')
            ->call('addRole')
            ->assertDispatched('ptah-toast', title: 'Selecione um role.', color: 'danger')
            ->html();

        $this->assertStringNotContainsString('successMsg', $html);
        $this->assertStringNotContainsString('errorMsg', $html);
    }

    #[Test]
    public function menu_list_dispatches_a_success_toast_on_create(): void
    {
        $html = Livewire::test(MenuList::class)
            ->call('create')
            ->set('text', 'Relatorios')
            ->call('save')
            ->assertDispatched('ptah-toast', color: 'success')
            ->html();

        $this->assertStringNotContainsString('successMsg', $html);
        $this->assertStringNotContainsString('errorMsg', $html);
    }

    #[Test]
    public function company_list_dispatches_a_success_toast_on_create(): void
    {
        $html = Livewire::test(CompanyList::class)
            ->call('create')
            ->set('name', 'Nova Empresa')
            ->call('save')
            ->assertDispatched('ptah-toast', title: 'Company created successfully!', color: 'success')
            ->html();

        $this->assertStringNotContainsString('successMsg', $html);
        $this->assertStringNotContainsString('errorMsg', $html);
    }

    #[Test]
    public function company_list_dispatches_a_danger_toast_when_deleting_the_default_company(): void
    {
        $company = Company::create(['name' => 'Padrao', 'is_default' => true, 'is_active' => true]);

        Livewire::test(CompanyList::class)
            ->call('confirmDelete', $company->id)
            ->call('delete')
            ->assertDispatched('ptah-toast', title: 'The default company cannot be deleted.', color: 'danger');
    }
}
