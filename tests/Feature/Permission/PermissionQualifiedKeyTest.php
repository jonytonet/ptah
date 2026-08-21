<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Permission;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;

/**
 * Covers the QUALIFIED key ("{page.slug}::{obj_key}" / "{page.slug}::{section}::{obj_key}")
 * disambiguation path added to disambiguate a colliding `obj_key` across
 * pages/sections (see `ConfigDoctorCommand`'s "obj_key collision" check).
 *
 * The bare map / bare lookup must stay byte-identical (retro-compat) — the
 * qualified map is ONLY consulted when the bare lookup misses AND the
 * requested key contains the qualifier.
 */
class PermissionQualifiedKeyTest extends TestCase
{
    private PermissionService $service;

    private int $userId = 300;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PermissionService;
    }

    private function makeRole(bool $master = false): Role
    {
        return Role::create(['name' => 'R'.uniqid(), 'is_master' => $master, 'is_active' => true]);
    }

    private function assign(int $userId, Role $role, ?int $companyId = null): void
    {
        UserRole::create([
            'user_id' => $userId, 'role_id' => $role->id,
            'company_id' => $companyId, 'is_active' => true,
        ]);
    }

    private function grant(Role $role, PageObject $obj, array $flags): RolePermission
    {
        return RolePermission::create(array_merge([
            'role_id' => $role->id, 'page_object_id' => $obj->id,
            'can_create' => false, 'can_read' => false, 'can_update' => false, 'can_delete' => false,
        ], $flags));
    }

    private function makePage(string $slug): PtahPage
    {
        return PtahPage::create(['slug' => $slug, 'name' => $slug, 'is_active' => true]);
    }

    private function makeObject(PtahPage $page, string $key, string $section = 'main', bool $active = true): PageObject
    {
        return PageObject::create([
            'page_id' => $page->id, 'section' => $section,
            'obj_key' => $key, 'obj_label' => $key,
            'obj_type' => 'button', 'obj_order' => 1, 'is_active' => $active,
        ]);
    }

    // ── Retro-compat: bare map still OR's across pages ──────────────────────

    #[Test]
    public function bare_key_still_ors_grants_across_colliding_pages(): void
    {
        $pageA = $this->makePage('page-a');
        $pageB = $this->makePage('page-b');
        $objA = $this->makeObject($pageA, 'shared.key');
        $objB = $this->makeObject($pageB, 'shared.key');

        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, $objA, ['can_read' => true]);

        // Bare lookup: retrocompat — grant on page-a's object is visible
        // under the bare key regardless of which page granted it.
        $this->assertTrue($this->service->check($this->userId, 'shared.key', 'read'));

        // No grant at all on page-b's object yet — still false via bare key
        // because it's the SAME bare key already resolved true above; verify
        // the qualified form distinguishes them instead.
        $this->assertTrue($this->service->check($this->userId, 'page-a::shared.key', 'read'));
        $this->assertFalse($this->service->check($this->userId, 'page-b::shared.key', 'read'));

        unset($objB);
    }

    #[Test]
    public function qualified_key_disambiguates_a_grant_only_on_one_page(): void
    {
        $pageA = $this->makePage('page-a');
        $pageB = $this->makePage('page-b');
        $objA = $this->makeObject($pageA, 'shared.key');
        $this->makeObject($pageB, 'shared.key');

        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, $objA, ['can_read' => true]);

        $this->assertTrue($this->service->check($this->userId, 'page-a::shared.key', 'read'));
        $this->assertFalse($this->service->check($this->userId, 'page-b::shared.key', 'read'));
    }

    #[Test]
    public function three_part_key_disambiguates_by_section_within_the_same_page(): void
    {
        $page = $this->makePage('page-a');
        $objToolbar = $this->makeObject($page, 'shared.key', section: 'toolbar');
        $objForm = $this->makeObject($page, 'shared.key', section: 'form');

        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, $objToolbar, ['can_read' => true]);

        $this->assertTrue($this->service->check($this->userId, 'page-a::toolbar::shared.key', 'read'));
        $this->assertFalse($this->service->check($this->userId, 'page-a::form::shared.key', 'read'));

        unset($objForm);
    }

    #[Test]
    public function inactive_object_or_page_denies_the_qualified_key_too(): void
    {
        $page = $this->makePage('page-a');
        $obj = $this->makeObject($page, 'shared.key', active: false);

        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, $obj, ['can_read' => true]);

        $this->assertFalse($this->service->check($this->userId, 'page-a::shared.key', 'read'));

        $obj->update(['is_active' => true]);
        $page->update(['is_active' => false]);

        $this->assertFalse($this->service->check($this->userId, 'page-a::shared.key', 'read'));
    }

    #[Test]
    public function master_passes_the_qualified_key_too(): void
    {
        $page = $this->makePage('page-a');
        $this->makeObject($page, 'shared.key');

        $this->assign($this->userId, $this->makeRole(master: true));

        $this->assertTrue($this->service->check($this->userId, 'page-a::shared.key', 'read'));
        $this->assertTrue($this->service->check($this->userId, 'page-a::main::shared.key', 'delete'));
    }

    #[Test]
    public function nonexistent_page_in_the_qualified_key_denies(): void
    {
        $page = $this->makePage('page-a');
        $obj = $this->makeObject($page, 'shared.key');

        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, $obj, ['can_read' => true]);

        $this->assertFalse($this->service->check($this->userId, 'page-does-not-exist::shared.key', 'read'));
    }

    #[Test]
    public function an_obj_key_containing_the_qualifier_literally_resolves_via_the_bare_map_first(): void
    {
        // A literal obj_key like "shared::button" — unusual but not forbidden.
        // Because the bare map has this exact key, check() must resolve it
        // there and never fall through to the qualified interpretation.
        $page = $this->makePage('page-a');
        $obj = $this->makeObject($page, 'shared::button');

        $role = $this->makeRole();
        $this->assign($this->userId, $role);
        $this->grant($role, $obj, ['can_read' => true]);

        $this->assertTrue($this->service->check($this->userId, 'shared::button', 'read'));
    }

    #[Test]
    public function get_companies_for_resource_accepts_a_qualified_key(): void
    {
        $pageA = $this->makePage('page-a');
        $pageB = $this->makePage('page-b');
        $objA = $this->makeObject($pageA, 'shared.key');
        $this->makeObject($pageB, 'shared.key');

        $role = $this->makeRole();
        $this->assign($this->userId, $role, companyId: 5);
        $this->grant($role, $objA, ['can_read' => true]);

        $this->assertSame([5], $this->service->getCompaniesForResource($this->userId, 'page-a::shared.key', 'read'));
        $this->assertSame([], $this->service->getCompaniesForResource($this->userId, 'page-b::shared.key', 'read'));
    }

    #[Test]
    public function get_companies_for_resource_treats_a_literal_obj_key_containing_the_qualifier_as_bare(): void
    {
        // Mesma ordem literal-primeiro do check(): um obj_key REAL que contem
        // '::' nunca e decomposto — sem isso o metodo filtrava por um
        // page.slug fantasma e devolvia [] para um recurso legitimo.
        $page = $this->makePage('reports');
        $obj = $this->makeObject($page, 'legacy::weird.key');

        $role = $this->makeRole();
        $this->assign($this->userId, $role, companyId: 7);
        $this->grant($role, $obj, ['can_read' => true]);

        $companies = $this->service->getCompaniesForResource($this->userId, 'legacy::weird.key', 'read');

        $this->assertContains(7, $companies);
    }
}
