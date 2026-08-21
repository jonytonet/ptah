<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\SearchDropdown;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\SearchDropdown\SearchDropdown;
use Ptah\Tests\TestCase;

/**
 * Guards the keyboard/aria wiring added to search-dropdown.blade.php — see FIX 4
 * of the Onda 3 accessibility audit. Before this fix the input had no keyboard
 * navigation at all (arrow keys / Enter did nothing, only mouse hover+click
 * worked) and the result items carried role="button" instead of role="option",
 * so the widget was invisible to assistive tech as the combobox+listbox it
 * visually is. Purely a markup change — no #[Locked] property, no wire:model,
 * no query behaviour touched (see SearchDropdown.php, unchanged).
 */
class SearchDropdownAccessibilityTest extends TestCase
{
    #[Test]
    public function input_carries_combobox_aria_wiring_to_the_results_listbox(): void
    {
        $html = Livewire::test(SearchDropdown::class, ['model' => 'Widget'])->html();

        $this->assertMatchesRegularExpression('/<input\b[^>]*role="combobox"/', $html);
        $this->assertMatchesRegularExpression('/<input\b[^>]*aria-haspopup="listbox"/', $html);
        $this->assertMatchesRegularExpression('/<input\b[^>]*:aria-expanded="show"/', $html);
        $this->assertStringContainsString('aria-controls="sd-result-', $html);
        $this->assertStringContainsString(':aria-activedescendant="activeIndex >= 0', $html);
    }

    #[Test]
    public function results_container_is_a_listbox_matching_the_input_aria_controls(): void
    {
        $html = Livewire::test(SearchDropdown::class, ['model' => 'Widget'])->html();

        $this->assertMatchesRegularExpression('/id="sd-result-[^"]*"[^>]*role="listbox"/', $html);
    }

    #[Test]
    public function result_items_are_options_not_buttons(): void
    {
        $html = Livewire::test(SearchDropdown::class, ['model' => 'Widget'])->html();

        $this->assertStringContainsString('role="option"', $html);
        $this->assertStringNotContainsString('role="button"', $html);
    }

    #[Test]
    public function arrow_keys_enter_and_escape_are_wired_on_the_input(): void
    {
        $html = Livewire::test(SearchDropdown::class, ['model' => 'Widget'])->html();

        $this->assertStringContainsString('keydown.arrow-down.prevent="moveActive(1)"', $html);
        $this->assertStringContainsString('keydown.arrow-up.prevent="moveActive(-1)"', $html);
        $this->assertStringContainsString('keydown.enter.prevent="selectActive()"', $html);
        $this->assertStringContainsString('keydown.escape="show = false; activeIndex = -1"', $html);
        // Escape/blur colapsam a listbox: aria-activedescendant nao pode continuar
        // apontando para uma opcao oculta, entao activeIndex reseta junto.
        $this->assertStringContainsString('blur.debounce.150ms="show = false; activeIndex = -1"', $html);
    }

    #[Test]
    public function mousedown_prevent_selection_is_preserved(): void
    {
        $html = Livewire::test(SearchDropdown::class, ['model' => 'Widget'])->html();

        $this->assertStringContainsString('mousedown.prevent="select(item)"', $html);
    }

    #[Test]
    public function results_panel_and_clear_button_use_tokenized_classes_not_raw_grays(): void
    {
        $html = Livewire::test(SearchDropdown::class, ['model' => 'Widget'])->html();

        $this->assertStringContainsString('ptah-c-dd"', $html);
        $this->assertStringContainsString('ptah-c-search_x', $html);
        $this->assertStringNotContainsString('bg-white border border-gray-200', $html);
    }
}
