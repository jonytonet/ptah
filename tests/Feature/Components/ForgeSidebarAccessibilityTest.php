<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Guards FIX 6 of the Onda 3 accessibility audit for forge-sidebar.blade.php:
 *   - the menuGroup accordion button exposes :aria-expanded/aria-haspopup
 *     (previously nothing signalled the accordion's open/closed state to
 *     assistive tech beyond the chevron's rotation).
 *   - the active menuLink and the active child link inside an open group
 *     both carry aria-current="page".
 *   - the sub-item rail's border (`border-gray-200`, light-only, no dark
 *     variant) now comes from a tokenized class.
 */
class ForgeSidebarAccessibilityTest extends TestCase
{
    private function render(array $items): string
    {
        return (string) $this->blade(
            '<x-forge-sidebar appName="Test" :items="$items" />',
            ['items' => $items]
        );
    }

    #[Test]
    public function menu_group_button_exposes_expanded_state_and_haspopup(): void
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

        $this->assertMatchesRegularExpression('/<button[^>]*:aria-expanded="open"/', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*aria-haspopup="true"/', $html);
    }

    #[Test]
    public function active_top_level_menu_link_carries_aria_current(): void
    {
        $this->get('/dashboard');

        $html = $this->render([
            ['type' => 'menuLink', 'label' => 'Dashboard', 'url' => '/dashboard', 'match' => 'dashboard'],
            ['type' => 'menuLink', 'label' => 'Users', 'url' => '/users', 'match' => 'users'],
        ]);

        $this->assertMatchesRegularExpression('/href="\/dashboard"[^>]*aria-current="page"/', $html);
        $this->assertDoesNotMatchRegularExpression('/href="\/users"[^>]*aria-current="page"/', $html);
    }

    #[Test]
    public function active_child_link_inside_a_group_carries_aria_current(): void
    {
        $this->get('/settings/general');

        $html = $this->render([
            [
                'type' => 'menuGroup',
                'label' => 'Settings',
                'children' => [
                    ['label' => 'General', 'url' => '/settings/general', 'match' => 'settings/general'],
                    ['label' => 'Billing', 'url' => '/settings/billing', 'match' => 'settings/billing'],
                ],
            ],
        ]);

        $this->assertMatchesRegularExpression('/href="\/settings\/general"[^>]*aria-current="page"/', $html);
        $this->assertDoesNotMatchRegularExpression('/href="\/settings\/billing"[^>]*aria-current="page"/', $html);
    }

    #[Test]
    public function sub_item_rail_uses_a_tokenized_border_class_not_the_hardcoded_gray(): void
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

        $this->assertStringContainsString('ptah-c-sidebar_subnav', $html);
        $this->assertStringNotContainsString('border-gray-200', $html);
    }
}
