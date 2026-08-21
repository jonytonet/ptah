<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Guards the accessibility wiring of <x-forge-input>'s error state — see FIX 2
 * of the Onda 3 accessibility audit.
 *
 * Before this fix, an errored field carried NO `aria-invalid`/`aria-describedby`
 * at all (screen readers had no signal a field had failed validation), AND its
 * visual border was silently overridden: `.ptah-input-wrapper input` in
 * ptah-components.css (unlayered) declares `border-color` unconditionally,
 * which beat forge-input's `border-red-400` utility regardless of source
 * order — an errored field rendered pixel-identical to a valid one. The
 * message text (`text-red-500`, 3.76:1 against white) also failed the 4.5:1
 * AA floor.
 */
class ForgeInputAriaInvalidTest extends TestCase
{
    #[Test]
    public function no_error_emits_no_aria_invalid_or_describedby(): void
    {
        $html = (string) $this->blade('<x-forge-input label="Nome" wire:model="name" />');

        $inputTag = $this->extractInputTag($html);

        $this->assertNotNull($inputTag);
        $this->assertStringNotContainsString('aria-invalid', $inputTag);
        $this->assertStringNotContainsString('aria-describedby', $inputTag);
    }

    #[Test]
    public function error_prop_emits_aria_invalid_and_a_matching_describedby(): void
    {
        $html = (string) $this->blade('<x-forge-input label="Nome" wire:model="name" error="Campo obrigatório" />');

        $inputTag = $this->extractInputTag($html);

        $this->assertNotNull($inputTag);
        $this->assertStringContainsString('aria-invalid="true"', $inputTag);

        $this->assertSame(1, preg_match('/aria-describedby="([^"]+)"/', $inputTag, $m));
        $describedBy = $m[1];

        $this->assertStringContainsString('id="'.$describedBy.'"', $html);
        $this->assertStringContainsString('Campo obrigatório', $html);
    }

    #[Test]
    public function error_message_uses_the_tokenized_class_not_text_red_500(): void
    {
        $html = (string) $this->blade('<x-forge-input label="Nome" wire:model="name" error="Campo obrigatório" />');

        $this->assertStringContainsString('ptah-c-field_err', $html);
        $this->assertDoesNotMatchRegularExpression('/text-xs\s+text-red-500/', $html);
    }

    #[Test]
    public function state_danger_without_error_prop_stays_invisible_to_aria(): void
    {
        // state="danger" alone is styling, not a validation error — no message to
        // describe, so aria-invalid/aria-describedby must not fire (see forge-input's
        // $isInvalid, gated on $error, not $resolvedState).
        $html = (string) $this->blade('<x-forge-input label="Nome" wire:model="name" state="danger" />');

        $inputTag = $this->extractInputTag($html);

        $this->assertNotNull($inputTag);
        $this->assertStringNotContainsString('aria-invalid', $inputTag);
        $this->assertStringNotContainsString('aria-describedby', $inputTag);
    }

    private function extractInputTag(string $html): ?string
    {
        return preg_match('/<input\b[^>]*>/', $html, $matches) === 1 ? $matches[0] : null;
    }
}
