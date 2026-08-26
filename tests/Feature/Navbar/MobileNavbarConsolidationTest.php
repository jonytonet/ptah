<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Navbar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Company\CompanySwitcher;
use Ptah\Models\Company;
use Ptah\Tests\TestCase;

/**
 * On a phone the navbar's company switcher (active company name + one tab per
 * company) fought the bell, the avatar and the gear for the same ~60px, and the
 * companies came out overlapping and unreadable — reported from a production
 * ERP. The fix consolidates: below `md` the inline group is hidden and the
 * switcher reappears as a vertical section INSIDE the admin dropdown, so the
 * right-hand side is one menu instead of two controls colliding.
 *
 * Verified in a real browser at 390/767/768/1440 CSS px (inline hidden below
 * the breakpoint, visible at and above it, zero overflow at every width). These
 * tests pin the structure that produces it, since the suite cannot measure
 * layout.
 */
class MobileNavbarConsolidationTest extends TestCase
{
    private function seedCompanies(int $count = 2): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Company::create([
                'name' => "Empresa {$i}",
                'label' => "EMP{$i}",
                'is_active' => true,
            ]);
        }
    }

    private function loginUser(): void
    {
        /** @var class-string<Model> $userClass */
        $userClass = config('auth.providers.users.model');

        $user = $userClass::forceCreate([
            'name' => 'Nav User',
            'email' => 'nav@example.test',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);
    }

    // ── The switcher's stacked variant ──────────────────────────────────────

    #[Test]
    public function the_switcher_defaults_to_the_inline_layout(): void
    {
        $this->seedCompanies();

        Livewire::test(CompanySwitcher::class)
            ->assertSet('layout', 'inline')
            ->assertSeeHtml('ptah-switcher-group');
    }

    #[Test]
    public function the_stacked_layout_renders_menu_items_instead_of_the_inline_group(): void
    {
        $this->seedCompanies();

        Livewire::test(CompanySwitcher::class, ['layout' => 'stacked'])
            ->assertSet('layout', 'stacked')
            // Inherits the hosting panel's theme colours instead of repainting a
            // menu item with hardcoded Tailwind.
            ->assertSeeHtml('ptah-admin-dropdown-link')
            ->assertDontSeeHtml('ptah-switcher-group');
    }

    #[Test]
    public function an_unknown_layout_falls_back_to_inline(): void
    {
        $this->seedCompanies();

        Livewire::test(CompanySwitcher::class, ['layout' => 'carousel'])
            ->assertSet('layout', 'inline');
    }

    #[Test]
    public function the_stacked_layout_marks_the_active_company_with_more_than_colour(): void
    {
        $this->seedCompanies();

        // WCAG 1.4.1: colour cannot be the only carrier of information, and the
        // dark navbar panel does not give two text colours enough separation to
        // rely on anyway. The active row carries a check icon and aria-current.
        Livewire::test(CompanySwitcher::class, ['layout' => 'stacked'])
            ->assertSeeHtml('aria-current="true"')
            ->assertSeeHtml('M5 13l4 4L19 7');
    }

    #[Test]
    public function a_single_company_installation_renders_no_section_at_all(): void
    {
        $this->seedCompanies(1);

        // The section must disappear on its own rather than leaving an empty
        // "SELECIONAR EMPRESA" heading inside the menu.
        Livewire::test(CompanySwitcher::class, ['layout' => 'stacked'])
            ->assertDontSeeHtml('ptah-admin-dropdown-link');
    }

    // ── The navbar wiring ───────────────────────────────────────────────────

    #[Test]
    public function the_inline_switcher_is_hidden_below_md_when_the_admin_menu_can_host_it(): void
    {
        config(['ptah.modules.company' => true]);
        $this->loginUser();
        $this->seedCompanies();

        $html = Blade::render('<x-forge-navbar />');

        $this->assertStringContainsString('hidden md:flex', $html);
        $this->assertStringContainsString('ptah-company-switcher-mobile', $html);
    }

    #[Test]
    public function the_inline_switcher_stays_visible_on_mobile_when_there_is_no_admin_menu(): void
    {
        // Every module that generates the admin dropdown is off, so there is no
        // menu to host the switcher. Cramped beats absent: hiding it here would
        // leave a phone user with no way to change company at all.
        config([
            'ptah.modules.company' => false,
            'ptah.modules.permissions' => false,
            'ptah.modules.menu' => false,
            'ptah.modules.ai_agent' => false,
        ]);
        $this->loginUser();
        $this->seedCompanies();

        $html = Blade::render('<x-forge-navbar />');

        $this->assertStringNotContainsString('hidden md:flex', $html);
        $this->assertStringNotContainsString('ptah-company-switcher-mobile', $html);
    }

    #[Test]
    public function the_mobile_company_section_is_gated_to_narrow_viewports(): void
    {
        config(['ptah.modules.company' => true]);
        $this->loginUser();
        $this->seedCompanies();

        $html = Blade::render('<x-forge-navbar />');

        // The stacked instance must sit inside an md:hidden wrapper, or wide
        // screens would show the companies twice.
        $this->assertMatchesRegularExpression(
            '/md:hidden[^>]*>\s*(?:<!--.*?-->\s*)*[^<]*<[^>]*ptah-company-switcher-mobile/s',
            $html,
            'A instancia stacked precisa estar dentro de um wrapper md:hidden.'
        );
    }

    #[Test]
    public function the_actions_group_is_pinned_to_the_third_grid_column(): void
    {
        config(['ptah.modules.company' => true]);
        $this->loginUser();
        $this->seedCompanies();

        $html = Blade::render('<x-forge-navbar />');

        // Load-bearing, and the reason is invisible from the markup: the navbar
        // is a three-column grid whose MIDDLE child (the switcher) is now
        // `hidden` on mobile. A display:none item occupies no grid column, so
        // auto-placement pulls the actions into column 2 — which is `auto`, so
        // they render in the MIDDLE of the bar instead of against the right
        // edge. That was the reported symptom. Pinning the column makes the
        // position independent of how many siblings are visible.
        $this->assertStringContainsString(
            'col-start-3',
            $html,
            'O grupo de acoes precisa de col-start-3: sem ele, esconder o switcher no celular joga as acoes para o meio da barra.'
        );

        // And the grid must still declare three columns, or col-start-3 has
        // nothing to anchor to.
        $this->assertStringContainsString('grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]', $html);
    }
}
