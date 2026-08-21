<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Permission;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Permission\RoleList;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;

/**
 * Onda A UX-ACL — FIX 2 (client-side filter) and FIX 3 (per-page accordion) on
 * role-list's "Gerenciar Permissões" bind modal. Presentation only: saveBind()
 * and the shape of $bindObjects are untouched (see role-list.blade.php's own
 * comment on the grouping @php block); these tests assert the rendered markup,
 * not any new Livewire behaviour.
 */
class RoleListBindModalAccordionTest extends TestCase
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

    private function createPageWithObject(string $slug, string $name, string $objKey): PtahPage
    {
        $page = PtahPage::create(['slug' => $slug, 'name' => $name, 'is_active' => true]);

        PageObject::create([
            'page_id' => $page->id,
            'section' => 'main',
            'obj_key' => $objKey,
            'obj_label' => ucfirst($objKey).' label',
            'obj_type' => 'page',
            'obj_order' => 1,
            'is_active' => true,
        ]);

        return $page;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockMaster(true);
    }

    #[Test]
    public function bind_modal_renders_a_client_side_filter_field_with_translated_placeholder_and_aria_label(): void
    {
        $this->createPageWithObject('users', 'Users', 'users.index');
        $role = Role::create(['name' => 'Reader', 'is_active' => true]);

        $html = Livewire::test(RoleList::class)
            ->call('openBind', $role->id)
            ->html();

        $this->assertStringContainsString('x-model.debounce.150ms="filterText"', $html);
        $this->assertStringContainsString(__('ptah::ui.role_bind_filter_ph'), $html);
        $this->assertStringContainsString(
            sprintf('aria-label="%s"', e(__('ptah::ui.role_bind_filter_aria'))),
            $html
        );
        // FIX 2 is explicitly client-side (Alpine), not a Livewire round-trip —
        // the filter must never be bound via wire:model.
        $this->assertStringNotContainsString('wire:model="filterText"', $html);
        $this->assertStringNotContainsString('wire:model.live="filterText"', $html);
    }

    #[Test]
    public function with_more_than_three_pages_every_accordion_group_starts_collapsed(): void
    {
        $this->createPageWithObject('page-a', 'Page A', 'page-a.view');
        $this->createPageWithObject('page-b', 'Page B', 'page-b.view');
        $this->createPageWithObject('page-c', 'Page C', 'page-c.view');
        $this->createPageWithObject('page-d', 'Page D', 'page-d.view');
        $role = Role::create(['name' => 'Reader', 'is_active' => true]);

        $html = Livewire::test(RoleList::class)
            ->call('openBind', $role->id)
            ->html();

        $this->assertGreaterThanOrEqual(4, substr_count($html, 'aria-controls="ptah-bind-group-'));
        $this->assertGreaterThanOrEqual(4, substr_count($html, 'manualOpen: false'));
        $this->assertStringNotContainsString('manualOpen: true', $html);
    }

    #[Test]
    public function with_three_or_fewer_pages_every_accordion_group_starts_expanded(): void
    {
        $this->createPageWithObject('page-a', 'Page A', 'page-a.view');
        $this->createPageWithObject('page-b', 'Page B', 'page-b.view');
        $role = Role::create(['name' => 'Reader', 'is_active' => true]);

        $html = Livewire::test(RoleList::class)
            ->call('openBind', $role->id)
            ->html();

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'manualOpen: true'));
        $this->assertStringNotContainsString('manualOpen: false', $html);
    }

    #[Test]
    public function group_header_is_a_real_button_with_aria_expanded_and_aria_controls(): void
    {
        $this->createPageWithObject('users', 'Users', 'users.index');
        $role = Role::create(['name' => 'Reader', 'is_active' => true]);

        $html = Livewire::test(RoleList::class)
            ->call('openBind', $role->id)
            ->html();

        $this->assertMatchesRegularExpression(
            '/<button[^>]+class="ptah-c-acc_hd[^"]*"[^>]+:aria-expanded="[^"]+"[^>]+aria-controls="ptah-bind-group-0"/',
            $html
        );
    }

    #[Test]
    public function checkboxes_remain_present_and_bound_to_the_untouched_livewire_property(): void
    {
        $this->createPageWithObject('users', 'Users', 'users.index');
        $role = Role::create(['name' => 'Reader', 'is_active' => true]);

        Livewire::test(RoleList::class)
            ->call('openBind', $role->id)
            ->assertSee(__('ptah::ui.role_bind_perm_read'))
            ->assertSee(__('ptah::ui.role_bind_perm_create'))
            ->assertSee(__('ptah::ui.role_bind_perm_edit'))
            ->assertSee(__('ptah::ui.role_bind_perm_delete'))
            ->assertSeeHtml('wire:model="bindObjects.0.can_read"');
    }
}
