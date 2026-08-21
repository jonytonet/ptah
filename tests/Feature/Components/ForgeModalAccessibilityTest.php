<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Guards the keyboard/focus fixes applied to <x-forge-modal> — see FIX 3 of
 * the Onda 3 accessibility audit:
 *   - Esc closes the modal (there was no keyboard escape at all before).
 *   - The dialog panel traps focus via Alpine's bundled `x-trap` plugin
 *     (confirmed present in Livewire 4's shipped Alpine build — see
 *     `Alpine.directive('trap', ...)` in vendor/livewire/livewire/dist/livewire.js
 *     — so no new CDN dependency was needed) with `.noscroll`, which also
 *     restores focus to the trigger on close (focus-trap's `returnFocus`
 *     defaults to true unless `.noreturn` is present, which this markup
 *     does not use).
 *   - The title id used to be `Str::random(6)` — regenerated on every
 *     render, and Livewire's DOM-diff keys the morph on `el.id` when there
 *     is no wire:id/wire:key, so the <h3> (and its aria-labelledby target)
 *     was destroyed and recreated on every re-render. Same defect/fix as
 *     forge-input.blade.php's $inputId (ForgeInputMorphKeyTest).
 */
class ForgeModalAccessibilityTest extends TestCase
{
    #[Test]
    public function root_carries_a_window_escape_handler(): void
    {
        $html = Blade::render('<x-forge-modal title="X">body</x-forge-modal>');

        $this->assertStringContainsString('@keydown.escape.window', $html);
    }

    #[Test]
    public function panel_traps_focus_with_noscroll(): void
    {
        $html = Blade::render('<x-forge-modal title="X">body</x-forge-modal>');

        $this->assertStringContainsString('x-trap.noscroll="open"', $html);
    }

    #[Test]
    public function title_id_is_identical_across_two_renders(): void
    {
        $template = '<x-forge-modal title="Confirmar exclusão">body</x-forge-modal>';

        $first = $this->extractLabelledBy((string) Blade::render($template));
        $second = $this->extractLabelledBy((string) Blade::render($template));

        $this->assertNotNull($first);
        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^forge-modal-title-[0-9a-f]{12}$/', $first);
    }

    #[Test]
    public function title_id_matches_the_heading_id(): void
    {
        $html = (string) Blade::render('<x-forge-modal title="Confirmar exclusão">body</x-forge-modal>');

        $labelledBy = $this->extractLabelledBy($html);

        $this->assertNotNull($labelledBy);
        $this->assertStringContainsString('id="'.$labelledBy.'"', $html);
    }

    #[Test]
    public function no_title_emits_no_aria_labelledby(): void
    {
        $html = (string) Blade::render('<x-forge-modal>body</x-forge-modal>');

        $this->assertStringNotContainsString('aria-labelledby', $html);
    }

    private function extractLabelledBy(string $html): ?string
    {
        return preg_match('/aria-labelledby="([^"]+)"/', $html, $m) === 1 ? $m[1] : null;
    }
}
