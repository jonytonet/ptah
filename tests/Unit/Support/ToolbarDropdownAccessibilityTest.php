<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Guards FIX 6 of the Onda 3 accessibility audit: the toolbar's three Alpine
 * dropdowns (exportar, colunas, densidade) had no keyboard-escape handler and
 * no aria-expanded/aria-haspopup on their trigger — a sighted mouse user could
 * tell the menu was open from the chevron rotation alone, but nothing conveyed
 * that state to assistive tech, and Escape did nothing (only @click.outside
 * closed it). The "Filtros" button is a different pattern (a server-driven
 * `$showFilters` toggle, not an Alpine `open` popup) and gets its own
 * aria-expanded bound to that property instead.
 *
 * Pure file read + regex, no app boot needed — same idiom as
 * ToolbarCollapsibleLabelAccessibilityTest / ContrastGuardTest.
 */
class ToolbarDropdownAccessibilityTest extends TestCase
{
    private static function toolbarBlade(): string
    {
        static $blade = null;

        return $blade ??= self::read('resources/views/livewire/base-crud/partials/_toolbar.blade.php');
    }

    private static function read(string $relative): string
    {
        $content = file_get_contents(dirname(__DIR__, 3).'/'.$relative);

        if ($content === false) {
            throw new RuntimeException('ToolbarDropdownAccessibilityTest: falha ao ler '.$relative);
        }

        return $content;
    }

    /** @return array<string, array{0: string}> */
    public static function dropdownTitleKeyProvider(): array
    {
        return [
            'exportar' => ['ptah::ui.btn_export'],
            'colunas' => ['ptah::ui.btn_columns'],
            'densidade' => ['ptah::ui.btn_density'],
        ];
    }

    #[Test]
    #[DataProvider('dropdownTitleKeyProvider')]
    public function dropdown_trigger_and_its_wrapper_carry_the_expected_accessibility_attributes(string $titleKey): void
    {
        $blade = self::toolbarBlade();

        $titleNeedle = "__('{$titleKey}')";
        $titlePos = strpos($blade, $titleNeedle);
        $this->assertNotFalse($titlePos, "could not locate a title/aria-label using [{$titleKey}] in _toolbar.blade.php");

        // The x-data="{ open: false }" wrapper for this dropdown is the nearest
        // one BEFORE its title — the toolbar always declares wrapper -> trigger
        // button -> title, in that order, for each of the three dropdowns.
        $wrapperPos = strrpos(substr($blade, 0, $titlePos), 'x-data="{ open: false }"');
        $this->assertNotFalse($wrapperPos, "could not locate the x-data wrapper preceding [{$titleKey}]");

        // Window spanning wrapper -> trigger button (a few hundred bytes is ample
        // for the attribute list; the panel markup starts well past it).
        $window = substr($blade, $wrapperPos, ($titlePos - $wrapperPos) + 300);

        $this->assertStringContainsString(
            '@keydown.escape.window="open = false"',
            $window,
            "[{$titleKey}] dropdown wrapper is missing the Escape handler"
        );
        $this->assertStringContainsString('aria-haspopup="true"', $window, "[{$titleKey}] trigger is missing aria-haspopup");
        $this->assertStringContainsString(':aria-expanded="open"', $window, "[{$titleKey}] trigger is missing :aria-expanded");
    }

    #[Test]
    public function exactly_three_dropdowns_carry_the_escape_handler_and_aria_wiring(): void
    {
        $blade = self::toolbarBlade();

        $this->assertSame(3, substr_count($blade, '@keydown.escape.window="open = false"'));
        $this->assertSame(3, substr_count($blade, 'aria-haspopup="true"'));
        $this->assertSame(3, substr_count($blade, ':aria-expanded="open"'));
    }

    #[Test]
    public function filters_toggle_button_exposes_aria_expanded_bound_to_the_server_side_flag(): void
    {
        $blade = self::toolbarBlade();

        $this->assertStringContainsString(
            "aria-expanded=\"{{ \$showFilters ? 'true' : 'false' }}\"",
            $blade,
            'the "Filtros" toggle button must reflect $showFilters via aria-expanded'
        );
    }
}
