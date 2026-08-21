<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Guards FIX 1 of the Onda C ux-acl-tree audit for forge-sidebar.blade.php:
 * the collapsed (icon-only) state was leaking label width via
 * `opacity:0;max-width:0` instead of really leaving the flow, icons were not
 * centered once the label disappeared, the group's sub-item rail rendered
 * narrow and misaligned while collapsed, and `title` duplicated the visible
 * label even while the sidebar was expanded.
 *
 * Decision this test pins: clicking a collapsed group's icon expands the
 * sidebar (persisting `ptah_sidebar_collapsed`) AND opens the group, instead
 * of a flyout — the simplest predictable behaviour, and the only one that
 * also covers touch devices where the hover preview never fires.
 */
class ForgeSidebarCollapseTest extends TestCase
{
    private function render(array $items): string
    {
        return (string) $this->blade(
            '<x-forge-sidebar appName="Test" :items="$items" />',
            ['items' => $items]
        );
    }

    #[Test]
    public function labels_are_removed_from_the_flow_with_x_show_not_just_opacity(): void
    {
        $html = $this->render([
            ['type' => 'menuLink', 'label' => 'Users', 'url' => '/users'],
        ]);

        $this->assertStringContainsString('x-show="!iconOnly()"', $html);
        $this->assertStringNotContainsString('max-width:0', $html);
        $this->assertStringNotContainsString('opacity:0;width:0;overflow:hidden;', $html);
    }

    #[Test]
    public function the_icon_only_helper_is_defined_on_the_aside_scope(): void
    {
        $html = $this->render([
            ['type' => 'menuLink', 'label' => 'Users', 'url' => '/users'],
        ]);

        $this->assertMatchesRegularExpression(
            '/iconOnly\(\)\s*\{\s*return !this\.hovered/',
            $html
        );
    }

    #[Test]
    public function top_level_menu_link_title_is_bound_and_only_set_when_collapsed(): void
    {
        $html = $this->render([
            ['type' => 'menuLink', 'label' => 'Users', 'url' => '/users'],
        ]);

        $this->assertStringContainsString(":title=\"iconOnly() ? 'Users' : null\"", $html);
        // Never a static title duplicating the always-visible label.
        $this->assertDoesNotMatchRegularExpression('/<a[^>]* title="Users"/', $html);
    }

    #[Test]
    public function group_button_title_is_bound_and_only_set_when_collapsed(): void
    {
        $html = $this->render([
            [
                'type' => 'menuGroup',
                'label' => 'Settings',
                'children' => [
                    ['label' => 'General', 'url' => '/settings/general'],
                ],
            ],
        ]);

        $this->assertStringContainsString(":title=\"iconOnly() ? 'Settings' : null\"", $html);
        $this->assertDoesNotMatchRegularExpression('/<button[^>]* title="Settings"/', $html);
    }

    #[Test]
    public function child_link_no_longer_carries_a_static_title(): void
    {
        $html = $this->render([
            [
                'type' => 'menuGroup',
                'label' => 'Settings',
                'children' => [
                    ['label' => 'General', 'url' => '/settings/general'],
                ],
            ],
        ]);

        $this->assertDoesNotMatchRegularExpression('/href="\/settings\/general"[^>]*title=/', $html);
    }

    #[Test]
    public function collapsed_group_click_expands_the_sidebar_and_opens_the_group(): void
    {
        $html = $this->render([
            [
                'type' => 'menuGroup',
                'label' => 'Settings',
                'children' => [
                    ['label' => 'General', 'url' => '/settings/general'],
                ],
            ],
        ]);

        $this->assertMatchesRegularExpression(
            "/@click=\"if \(iconOnly\(\)\) \{ sidebarCollapsed = false; localStorage\.setItem\('ptah_sidebar_collapsed', 'false'\); open = true; \} else \{ open = !open; \}\"/",
            $html
        );
    }

    #[Test]
    public function the_group_sub_list_only_renders_while_the_sidebar_is_not_icon_only(): void
    {
        $html = $this->render([
            [
                'type' => 'menuGroup',
                'label' => 'Settings',
                'children' => [
                    ['label' => 'General', 'url' => '/settings/general'],
                ],
            ],
        ]);

        $this->assertStringContainsString('x-show="open && !iconOnly()"', $html);
    }

    #[Test]
    public function icon_rows_center_the_lone_icon_when_collapsed(): void
    {
        $html = $this->render([
            ['type' => 'menuLink', 'label' => 'Users', 'url' => '/users'],
        ]);

        $this->assertStringContainsString(":class=\"iconOnly() ? 'justify-center' : ''\"", $html);
    }
}
