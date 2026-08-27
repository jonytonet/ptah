<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Guards FIX 5 of the Onda 3 accessibility audit: the ~24 `.ptah-module-*`
 * declaration sites migrated from forge-dashboard-layout.blade.php's frozen
 * <style> block onto resources/css/ptah-components.css, and the 7 module
 * screens (role/company/menu/audit/department/user-permission/page list)
 * that carried `bg-white`/`border-slate-200` hardcoded in light mode.
 *
 * Same two failure modes as ThemeSurfaceLightDarkParityTest, applied to the
 * module-screen family instead of the 4 large chrome surfaces:
 *
 *  1. A module view reintroducing the literal `bg-white`/`border-slate-200`
 *     on its `.ptah-module-toolbar`/`.ptah-module-table` wrapper.
 *  2. ptah-components.css missing a light OR dark rule, on the exact bare
 *     selector (light) or `.ptah-dark <selector>` (dark), that declares at
 *     least one var(--ptah-*) value.
 */
class ModuleScreenThemeParityTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function moduleViewProvider(): array
    {
        return [
            'role-list' => ['livewire/permission/role-list.blade.php'],
            'company-list' => ['livewire/company/company-list.blade.php'],
            'menu-list' => ['livewire/menu/menu-list.blade.php'],
            'audit-list' => ['livewire/permission/audit-list.blade.php'],
            'department-list' => ['livewire/permission/department-list.blade.php'],
            'user-permission-list' => ['livewire/permission/user-permission-list.blade.php'],
            'page-list' => ['livewire/permission/page-list.blade.php'],
            'permission-guide' => ['livewire/permission/permission-guide.blade.php'],
        ];
    }

    #[Test]
    #[DataProvider('moduleViewProvider')]
    public function module_view_wrappers_carry_no_hardcoded_light_only_utility(string $relativePath): void
    {
        $blade = self::read(self::viewPath($relativePath));

        if (preg_match('/class="ptah-module-toolbar([^"]*)"/', $blade, $m)) {
            $this->assertStringNotContainsString('bg-white', $m[1], $relativePath.': .ptah-module-toolbar ainda carrega bg-white fixo.');
            $this->assertStringNotContainsString('border-slate-200', $m[1], $relativePath.': .ptah-module-toolbar ainda carrega border-slate-200 fixo.');
        }

        $this->assertSame(
            1,
            preg_match('/class="ptah-module-table([^"]*)"/', $blade, $tableMatch),
            $relativePath.': elemento .ptah-module-table nao encontrado.'
        );
        $this->assertStringNotContainsString('bg-white', $tableMatch[1], $relativePath.': .ptah-module-table ainda carrega bg-white fixo.');
        $this->assertStringNotContainsString('border-slate-200', $tableMatch[1], $relativePath.': .ptah-module-table ainda carrega border-slate-200 fixo.');
    }

    /** @return array<string, array{0: string}> */
    public static function tokenDrivenSelectorProvider(): array
    {
        return [
            '.ptah-module-toolbar' => ['.ptah-module-toolbar'],
            '.ptah-module-toolbar input[type="search"]:not(.ptah-input-wrapper input):not(.ptah-c-mod_field), .ptah-module-toolbar select:not(.ptah-c-mod_field)' => ['.ptah-module-toolbar input[type="search"]:not(.ptah-input-wrapper input):not(.ptah-c-mod_field), .ptah-module-toolbar select:not(.ptah-c-mod_field)'],
            '.ptah-module-toolbar input[type="search"]::placeholder' => ['.ptah-module-toolbar input[type="search"]::placeholder'],
            '.ptah-module-table' => ['.ptah-module-table'],
            '.ptah-module-table thead tr' => ['.ptah-module-table thead tr'],
            '.ptah-module-table thead th' => ['.ptah-module-table thead th'],
            '.ptah-module-table tbody' => ['.ptah-module-table tbody'],
            '.ptah-module-table tbody tr' => ['.ptah-module-table tbody tr'],
            '.ptah-module-table tbody td' => ['.ptah-module-table tbody td'],
            '.ptah-module-table tbody tr:hover' => ['.ptah-module-table tbody tr:hover'],
            '.ptah-module-table .text-slate-800' => ['.ptah-module-table .text-slate-800'],
            '.ptah-module-table .text-slate-500' => ['.ptah-module-table .text-slate-500'],
            '.ptah-module-table .text-slate-400' => ['.ptah-module-table .text-slate-400'],
            '.ptah-module-table .bg-slate-100' => ['.ptah-module-table .bg-slate-100'],
            '.ptah-module-table .bg-slate-50' => ['.ptah-module-table .bg-slate-50'],
            '.ptah-module-table .text-slate-700' => ['.ptah-module-table .text-slate-700'],
            '.ptah-module-table .text-slate-300' => ['.ptah-module-table .text-slate-300'],
            '.ptah-page-title' => ['.ptah-page-title'],

            // Onda A UX-ACL, FIX 1 — semantic chips/badges (status, MASTER/DEFAULT,
            // audit action, menu Link) inside the 7 module screens' own tables.
            '.ptah-module-table .text-slate-600' => ['.ptah-module-table .text-slate-600'],
            '.ptah-module-table .bg-blue-50' => ['.ptah-module-table .bg-blue-50'],
            '.ptah-module-table .bg-blue-100' => ['.ptah-module-table .bg-blue-100'],
            '.ptah-module-table .text-blue-600' => ['.ptah-module-table .text-blue-600'],
            '.ptah-module-table .text-blue-700' => ['.ptah-module-table .text-blue-700'],
            '.ptah-module-table .bg-amber-100' => ['.ptah-module-table .bg-amber-100'],
            '.ptah-module-table .text-amber-600' => ['.ptah-module-table .text-amber-600'],
            '.ptah-module-table .text-amber-700' => ['.ptah-module-table .text-amber-700'],
            '.ptah-module-table .bg-green-100' => ['.ptah-module-table .bg-green-100'],
            '.ptah-module-table .text-green-700' => ['.ptah-module-table .text-green-700'],
            '.ptah-module-table .bg-red-100' => ['.ptah-module-table .bg-red-100'],
            '.ptah-module-table .text-red-600' => ['.ptah-module-table .text-red-600'],
            '.ptah-module-table .text-red-700' => ['.ptah-module-table .text-red-700'],

            // Toolbar filter labels (menu-list "Filtrar por tipo", user-permission-list
            // "Perfil") and the two modal families (create/edit + manage-roles) that
            // carried more slate/amber shades than the original modal-panel set covered.
            '.ptah-module-toolbar .text-slate-500' => ['.ptah-module-toolbar .text-slate-500'],
            '.ptah-c-mod_modal .text-slate-800' => ['.ptah-c-mod_modal .text-slate-800'],
            '.ptah-c-mod_modal .text-slate-400' => ['.ptah-c-mod_modal .text-slate-400'],
            '.ptah-c-mod_modal .text-slate-500' => ['.ptah-c-mod_modal .text-slate-500'],
            '.ptah-c-mod_modal .bg-slate-50' => ['.ptah-c-mod_modal .bg-slate-50'],
            '.ptah-c-mod_modal .border-slate-200' => ['.ptah-c-mod_modal .border-slate-200'],
            '.ptah-c-mod_modal .text-amber-500' => ['.ptah-c-mod_modal .text-amber-500'],
            '.ptah-c-mod_modal .text-amber-600' => ['.ptah-c-mod_modal .text-amber-600'],

            // page-list's hand-rolled "Páginas" list (a <div>-based item list, not a
            // <table>) and its heading/subtitle pair.
            '.ptah-c-mod_pagelist' => ['.ptah-c-mod_pagelist'],
            '.ptah-c-mod_pagelist .text-slate-800' => ['.ptah-c-mod_pagelist .text-slate-800'],
            '.ptah-c-mod_pagelist .text-slate-700' => ['.ptah-c-mod_pagelist .text-slate-700'],
            '.ptah-c-mod_pagelist .text-slate-500' => ['.ptah-c-mod_pagelist .text-slate-500'],
            '.ptah-c-mod_pagelist .bg-slate-100' => ['.ptah-c-mod_pagelist .bg-slate-100'],
            '.ptah-c-mod_pagelist .border-slate-200' => ['.ptah-c-mod_pagelist .border-slate-200'],
            '.ptah-c-mod_hdg' => ['.ptah-c-mod_hdg'],
            '.ptah-c-mod_subttl' => ['.ptah-c-mod_subttl'],

            // Bespoke component classes replacing fractional-opacity/hover utilities
            // that a plain class-repaint selector cannot reach (FIX 1), plus the
            // role-list bind-modal accordion/object-row classes (FIX 3).
            // NOTE: .ptah-c-mod_master_row / .ptah-c-mod_denied_row are intentionally
            // NOT listed here — their dark rule mixes the semantic color straight
            // against `transparent` (a lighter, row-tint-only mix than the chip
            // tokens above), so it carries no var(--ptah-*) for this mechanism to
            // key on. Covered instead by ModuleScreenSemanticChipContrastTest.
            '.ptah-c-mod_btn_soft' => ['.ptah-c-mod_btn_soft'],
            '.ptah-c-mod_item_sel' => ['.ptah-c-mod_item_sel'],
            '.ptah-c-acc_hd' => ['.ptah-c-acc_hd'],
            '.ptah-c-mod_obj_ttl' => ['.ptah-c-mod_obj_ttl'],
            '.ptah-c-mod_obj_type' => ['.ptah-c-mod_obj_type'],

            // Onda B (batch 6) — audit-list's toolbar fields (bg-slate-50/60
            // focus:bg-white, opaque-by-contract replacement for the /60
            // translucency the .focus\:bg-white:focus specificity bug exploited)
            // and the ACL row-hover tint 3 module screens carried as a plain
            // opacity utility (invisible to a class-name repaint hook).
            '.ptah-c-mod_field' => ['.ptah-c-mod_field'],
            '.ptah-c-mod_field:focus' => ['.ptah-c-mod_field:focus'],
            '.ptah-c-mod_row:hover' => ['.ptah-c-mod_row:hover'],

            // permission-guide "Code Examples" tab (truth+theme wave) — replaces
            // the per-token syntax-highlight span soup with a plain code block.
            '.ptah-c-code' => ['.ptah-c-code'],
            '.ptah-c-code_cap' => ['.ptah-c-code_cap'],

            // permission-guide "Step by Step" tab — step-number badge.
            '.ptah-c-step_num' => ['.ptah-c-step_num'],

            // permission-guide "Overview" tab — architecture diagram + flow nodes.
            '.ptah-c-guide_node' => ['.ptah-c-guide_node'],
            '.ptah-c-guide_node_q' => ['.ptah-c-guide_node_q'],
            '.ptah-c-guide_node_ok' => ['.ptah-c-guide_node_ok'],
            '.ptah-c-guide_node_no' => ['.ptah-c-guide_node_no'],
            '.ptah-c-guide_conn' => ['.ptah-c-guide_conn'],
        ];
    }

    #[Test]
    #[DataProvider('tokenDrivenSelectorProvider')]
    public function selector_has_a_token_driven_rule_in_both_scopes(string $selector): void
    {
        $css = self::read(dirname(__DIR__, 3).'/resources/css/ptah-components.css');
        $rules = self::rulesFor($css, $selector);

        $this->assertNotSame(
            '',
            $rules['light'],
            sprintf(
                'ptah-components.css: falta uma regra CLARA "%s { ... }" que declare var(--ptah-*). '.
                'Sem ela, o tom claro escolhido em /profile nao alcanca essa tela de modulo.',
                $selector
            )
        );
        $this->assertNotSame(
            '',
            $rules['dark'],
            sprintf(
                'ptah-components.css: falta uma regra ESCURA ".ptah-dark %s { ... }" que declare var(--ptah-*).',
                $selector
            )
        );
    }

    /**
     * @return array{light: string, dark: string}
     */
    private static function rulesFor(string $css, string $selector): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;

        $light = '';
        $dark = '';

        if (! preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $matches, PREG_SET_ORDER)) {
            throw new RuntimeException('ModuleScreenThemeParityTest: nenhuma regra encontrada em ptah-components.css.');
        }

        // Multi-selector groups (e.g. "input[type=\"search\"], select") are matched
        // as the FULL, normalized comma-list against $selector when $selector itself
        // contains a comma — same rule, split differently depending on which side
        // (this file's provider, vs the stylesheet's own selector list) introduced
        // the comma. Falls back to per-selector matching (prefixed for dark) otherwise.
        $wantsFullList = str_contains($selector, ',');

        foreach ($matches as $rule) {
            $body = trim($rule[2]);

            if ($body === '' || ! str_contains($body, 'var(--ptah-')) {
                continue;
            }

            $fullSelectorList = trim(preg_replace('/\s+/', ' ', $rule[1]) ?? $rule[1]);

            if ($wantsFullList) {
                if ($fullSelectorList === $selector) {
                    $light .= $body;
                } elseif ($fullSelectorList === implode(', ', array_map(
                    static fn (string $s): string => '.ptah-dark '.trim($s),
                    explode(',', $selector)
                ))) {
                    $dark .= $body;
                }

                continue;
            }

            foreach (explode(',', $rule[1]) as $rawSelector) {
                $normalized = trim(preg_replace('/\s+/', ' ', $rawSelector) ?? $rawSelector);

                if ($normalized === $selector) {
                    $light .= $body;
                } elseif ($normalized === '.ptah-dark '.$selector) {
                    $dark .= $body;
                }
            }
        }

        return ['light' => $light, 'dark' => $dark];
    }

    private static function viewPath(string $relative): string
    {
        return dirname(__DIR__, 3).'/resources/views/'.$relative;
    }

    private static function read(string $path): string
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('ModuleScreenThemeParityTest: falha ao ler '.$path);
        }

        return $content;
    }
}
