<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Onda A UX-ACL, FIX 1 — guards the specific hardcoded-light-only utilities the
 * systematic grep audit found across the 7 module screens (INCLUDING their
 * modals), listed explicitly so a future edit reverting one of these fixes
 * fails loudly instead of silently reintroducing the exact defect from the
 * dark-mode screenshots (role-list "Gerenciar Permissões", page-list
 * "Páginas e Objetos").
 *
 * Two kinds of assertion, same idiom as ModuleScreenThemeParityTest's own
 * markup guard:
 *  1. The OLD literal utility (fractional-opacity classes and hover-variant
 *     classes a plain `.foo { }` CSS repaint cannot reach) must be GONE.
 *  2. The NEW reuse class/token that replaced it must be PRESENT.
 */
class ModuleScreenLightOnlyUtilityRemovedTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function removedLiteralProvider(): array
    {
        return [
            'role-list MASTER row bg-amber-50/60' => ['livewire/permission/role-list.blade.php', 'bg-amber-50/60'],
            'role-list manage-perms hover:bg-blue-100' => ['livewire/permission/role-list.blade.php', 'hover:bg-blue-100'],
            'role-list bind-modal sticky bg-slate-100 divider' => ['livewire/permission/role-list.blade.php', 'sticky top-0 bg-slate-100'],
            'role-list bind-modal text-slate-300 obj_type caption' => ['livewire/permission/role-list.blade.php', 'text-slate-300'],
            'page-list selected-item bg-blue-50/border-blue-500' => ['livewire/permission/page-list.blade.php', 'bg-blue-50 border-l-4 border-blue-500'],
            'audit-list denied-row bg-red-50/30' => ['livewire/permission/audit-list.blade.php', 'bg-red-50/30'],
            'user-permission-list manage-roles hover:bg-blue-100' => ['livewire/permission/user-permission-list.blade.php', 'hover:bg-blue-100'],
            'company-list sort-arrow text-blue-500' => ['livewire/company/company-list.blade.php', 'class="text-blue-500"'],
            'menu-list sort-arrow text-blue-500' => ['livewire/menu/menu-list.blade.php', 'class="text-blue-500"'],
            'department-list sort-arrow text-blue-500' => ['livewire/permission/department-list.blade.php', 'class="text-blue-500"'],
        ];
    }

    #[Test]
    #[DataProvider('removedLiteralProvider')]
    public function old_light_only_literal_is_gone(string $relativePath, string $needle): void
    {
        $blade = self::read(self::viewPath($relativePath));

        $this->assertStringNotContainsString(
            $needle,
            $blade,
            sprintf('%s: "%s" ainda presente — deveria ter sido substituido por uma classe tokenizada (Onda A UX-ACL, FIX 1).', $relativePath, $needle)
        );
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function reuseClassPresentProvider(): array
    {
        return [
            'role-list master row class' => ['livewire/permission/role-list.blade.php', 'ptah-c-mod_master_row'],
            'role-list manage-perms button class' => ['livewire/permission/role-list.blade.php', 'ptah-c-mod_btn_soft'],
            'role-list pagination class' => ['livewire/permission/role-list.blade.php', 'ptah-c-pag'],
            'role-list bind-modal accordion header class' => ['livewire/permission/role-list.blade.php', 'ptah-c-acc_hd'],
            'role-list bind-modal object row class' => ['livewire/permission/role-list.blade.php', 'ptah-c-mod_obj_row'],
            'page-list wrapper class' => ['livewire/permission/page-list.blade.php', 'ptah-c-mod_pagelist'],
            'page-list item class' => ['livewire/permission/page-list.blade.php', 'ptah-c-mod_item'],
            'page-list heading class' => ['livewire/permission/page-list.blade.php', 'ptah-c-mod_hdg'],
            'page-list subtitle class' => ['livewire/permission/page-list.blade.php', 'ptah-c-mod_subttl'],
            'page-list pagination class' => ['livewire/permission/page-list.blade.php', 'ptah-c-pag'],
            'company-list sort-arrow reuse' => ['livewire/company/company-list.blade.php', 'ptah-c-sort_active'],
            'company-list pagination class' => ['livewire/company/company-list.blade.php', 'ptah-c-pag'],
            'menu-list sort-arrow reuse' => ['livewire/menu/menu-list.blade.php', 'ptah-c-sort_active'],
            'audit-list denied row class' => ['livewire/permission/audit-list.blade.php', 'ptah-c-mod_denied_row'],
            'audit-list subtitle class' => ['livewire/permission/audit-list.blade.php', 'ptah-c-mod_subttl'],
            'audit-list pagination class' => ['livewire/permission/audit-list.blade.php', 'ptah-c-pag'],
            'department-list sort-arrow reuse' => ['livewire/permission/department-list.blade.php', 'ptah-c-sort_active'],
            'department-list subtitle class' => ['livewire/permission/department-list.blade.php', 'ptah-c-mod_subttl'],
            'department-list pagination class' => ['livewire/permission/department-list.blade.php', 'ptah-c-pag'],
            'user-permission-list manage button class' => ['livewire/permission/user-permission-list.blade.php', 'ptah-c-mod_btn_soft'],
            'user-permission-list pagination class' => ['livewire/permission/user-permission-list.blade.php', 'ptah-c-pag'],
        ];
    }

    #[Test]
    #[DataProvider('reuseClassPresentProvider')]
    public function reuse_class_is_present(string $relativePath, string $needle): void
    {
        $blade = self::read(self::viewPath($relativePath));

        $this->assertStringContainsString(
            $needle,
            $blade,
            sprintf('%s: classe tokenizada "%s" esperada (Onda A UX-ACL, FIX 1) nao encontrada.', $relativePath, $needle)
        );
    }

    private static function viewPath(string $relative): string
    {
        return dirname(__DIR__, 3).'/resources/views/'.$relative;
    }

    private static function read(string $path): string
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('ModuleScreenLightOnlyUtilityRemovedTest: falha ao ler '.$path);
        }

        return $content;
    }
}
