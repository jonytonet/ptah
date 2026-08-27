<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the dark-mode toolbar fix on
 * resources/views/livewire/permission/audit-list.blade.php.
 *
 * The frozen <style> block in forge-dashboard-layout.blade.php already themes
 * .ptah-module-toolbar plus its input[type="search"]/select children (by descendant
 * selector, unlayered CSS beats any Tailwind utility regardless of specificity), so
 * those controls need no dark: classes of their own. The two gaps it does NOT cover —
 * input[type="date"] and the "Limpar" button — do, or they render with a light,
 * out-of-place box on top of the dark page. Plain string checks (no app boot, no
 * Blade compilation) are enough to pin that those two spots keep their dark: classes.
 */
class AuditListDarkModeTest extends TestCase
{
    private static function view(): string
    {
        static $view = null;

        return $view ??= file_get_contents(
            dirname(__DIR__, 3).'/resources/views/livewire/permission/audit-list.blade.php'
        );
    }

    #[Test]
    public function the_date_range_inputs_have_dark_variants_for_every_light_declaration_they_carry(): void
    {
        $view = self::view();

        if (! preg_match_all('/<input wire:model\.live="date(?:From|To)" type="date"\s+class="([^"]+)"/', $view, $matches)) {
            $this->fail('AuditListDarkModeTest: could not locate the dateFrom/dateTo <input type="date"> elements.');
        }

        $this->assertCount(2, $matches[1], 'Expected exactly the dateFrom and dateTo date inputs.');

        foreach ($matches[1] as $classList) {
            $this->assertStringContainsString('dark:border-slate-600', $classList);
            $this->assertStringContainsString('dark:text-slate-200', $classList);
            $this->assertStringContainsString('dark:focus:border-blue-500', $classList);
            // Palette-token migration (batch 6): the resting/focus background moved
            // from dark:bg-slate-700/60 + dark:focus:bg-slate-700 to .ptah-c-mod_field
            // (--ptah-field-muted / --ptah-field, both scopes) — same fix as the
            // focus:bg-white specificity bug on the search input/selects above.
            $this->assertStringContainsString('ptah-c-mod_field', $classList);
            $this->assertStringNotContainsString('dark:bg-slate-700/60', $classList);
            $this->assertStringNotContainsString('dark:focus:bg-slate-700', $classList);
        }
    }

    #[Test]
    public function the_clear_filters_button_is_no_longer_light_only(): void
    {
        $view = self::view();

        if (! preg_match('/wire:click="clearFilters"\s+class="([^"]+)"/', $view, $m)) {
            $this->fail('AuditListDarkModeTest: could not locate the "Limpar" (clearFilters) button.');
        }

        $classList = $m[1];

        $this->assertStringContainsString('bg-slate-100', $classList, 'Sanity check: light bg should still be present for light mode.');
        $this->assertStringContainsString('dark:bg-slate-700', $classList, 'The "Limpar" button must no longer render a bright white/light box in dark mode.');
        $this->assertStringContainsString('dark:text-slate-300', $classList);
        $this->assertStringContainsString('dark:hover:bg-slate-600', $classList);
    }
}
