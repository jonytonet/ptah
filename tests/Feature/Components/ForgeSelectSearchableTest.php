<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Covers the `searchable` prop added to forge-select.blade.php: a client-
 * side filter input at the top of the dropdown, case/accent-insensitive
 * matching, arrow-key navigation restricted to the currently-matching
 * options, escape-clears-then-closes, and an i18n "no results" message.
 *
 * HONEST LIMIT: this is a server-render (Livewire::test / Blade string
 * render) suite — it proves the MARKUP, ARIA wiring and the Alpine
 * expressions/handlers are present and textually correct, not that a real
 * browser filters/navigates correctly at runtime (that is the job of the
 * Dusk suite — see tests/Browser). Kept deliberately textual/structural
 * rather than mocking a JS engine, same "HONEST LIMIT" idiom as
 * ForgeSelectMorphKeyTest / ForgeSelectWireModelSeedTest.
 */
class ForgeSelectSearchableTest extends TestCase
{
    private function render(string $extraAttrs = ''): string
    {
        return (string) $this->blade(
            '<x-forge-select '.$extraAttrs.' label="Status" :options="[[\'value\' => \'a\', \'label\' => \'Ativo\'], [\'value\' => \'b\', \'label\' => \'Inativo\']]" />'
        );
    }

    #[Test]
    public function filter_input_is_absent_without_the_prop(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('ptah-select-filter', $html);
        $this->assertStringNotContainsString('x-ref="filterInput"', $html);
    }

    #[Test]
    public function filter_input_renders_with_the_prop(): void
    {
        $html = $this->render('searchable');

        $this->assertStringContainsString('ptah-select-filter', $html);
        $this->assertStringContainsString('x-ref="filterInput"', $html);
        $this->assertStringContainsString('x-model="search"', $html);
    }

    #[Test]
    public function filter_input_carries_an_i18n_aria_label(): void
    {
        $html = $this->render('searchable');

        $this->assertStringContainsString(
            'aria-label="'.e(__('ptah::ui.forge_select_filter_aria')).'"',
            $html
        );
    }

    #[Test]
    public function filter_input_keeps_aria_activedescendant_wired_to_the_active_option(): void
    {
        $html = $this->render('searchable');

        $this->assertStringContainsString(':aria-activedescendant=', $html);
        // Each <li> carries the matching :id, built from the same expression
        // (uniqueId + '-opt-' + idx) the filter input's aria-activedescendant
        // resolves to.
        $this->assertStringContainsString(":id=\"'forge-select-", $html);
    }

    #[Test]
    public function no_results_message_is_present_and_i18n(): void
    {
        $html = $this->render('searchable');

        $this->assertStringContainsString('ptah-select-empty', $html);
        $this->assertStringContainsString('!hasVisibleOptions()', $html);
        $this->assertStringContainsString(e(__('ptah::ui.no_results')), $html);
    }

    #[Test]
    public function no_results_message_is_absent_without_the_prop(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('ptah-select-empty', $html);
    }

    #[Test]
    public function keyboard_handlers_are_wired_on_the_filter_input(): void
    {
        $html = $this->render('searchable');

        $this->assertStringContainsString('@keydown.enter.prevent="selectActive()"', $html);
        $this->assertStringContainsString('@keydown.arrow-down.prevent="move(1)"', $html);
        $this->assertStringContainsString('@keydown.arrow-up.prevent="move(-1)"', $html);
        $this->assertStringContainsString('@keydown.escape.prevent="onFilterEscape()"', $html);
        $this->assertStringContainsString('@input="onFilterInput()"', $html);
    }

    /**
     * The two-escapes-to-close contract: the first press clears the filter
     * (if any text is present) and only closes the dropdown when the filter
     * was already empty.
     */
    #[Test]
    public function escape_handler_clears_before_closing(): void
    {
        $html = $this->render('searchable');

        $this->assertStringContainsString(
            "onFilterEscape() {\n                if (this.search !== '') {\n                    this.search = '';",
            $html
        );
    }

    #[Test]
    public function accent_and_case_insensitive_normalization_is_present(): void
    {
        $html = $this->render('searchable');

        $this->assertStringContainsString('toLowerCase()', $html);
        $this->assertStringContainsString(".normalize('NFD')", $html);
        $this->assertStringContainsString('\\p{Diacritic}', $html);
    }

    /**
     * Navigation among only the matching options: move() walks `options`
     * skipping anything matchesFilter() rejects, instead of a fixed modulo
     * over the full array (the non-searchable behaviour).
     */
    #[Test]
    public function arrow_navigation_skips_non_matching_options(): void
    {
        $html = $this->render('searchable');

        $this->assertStringContainsString('this.matchesFilter(this.options[next])', $html);
    }

    /**
     * A "clear selection" option (empty value) must stay reachable no
     * matter what the user typed — the filter never hides it.
     */
    #[Test]
    public function empty_value_option_is_exempt_from_the_filter(): void
    {
        $html = $this->render('searchable');

        $this->assertStringContainsString("if (option.value === '' || option.value === null) return true;", $html);
    }

    #[Test]
    public function regular_consumers_are_unaffected_by_the_new_prop(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('matchesFilter', $html);
        $this->assertStringNotContainsString('normalizeText', $html);
        $this->assertStringNotContainsString('onFilterEscape', $html);
        $this->assertStringNotContainsString('searchable', $html);
    }
}
