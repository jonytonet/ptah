<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Guards the morph-stable id of <x-forge-input>.
 *
 * Livewire's DOM-diff uses `el.id` as the morph key when the element has no
 * wire:id/wire:key. Before this fix, forge-input.blade.php generated a
 * random id (`uniqid()`) on EVERY render, so the morph would REMOVE and
 * recreate the <input> on each wire:model.live update — dropping focus while
 * typing.
 *
 * HONEST LIMIT: the actual focus loss can only be proven in a real browser
 * (the morph runs against the browser DOM). This test only locks the
 * server-side precondition — that the id is identical across renders with
 * the same props — which is the provable root cause here.
 */
class ForgeInputMorphKeyTest extends TestCase
{
    #[Test]
    public function unlabelled_input_emits_no_id(): void
    {
        $html = (string) $this->blade('<x-forge-input wire:model.live="search" type="search" />');

        $inputTag = $this->extractInputTag($html);

        $this->assertNotNull($inputTag);
        $this->assertStringNotContainsString(' id=', $inputTag);
    }

    #[Test]
    public function labelled_input_id_is_identical_across_two_renders(): void
    {
        $template = '<x-forge-input label="Preço" wire:model.live.debounce.600ms="formData.price" />';

        $firstId = $this->extractInputId((string) $this->blade($template));
        $secondId = $this->extractInputId((string) $this->blade($template));

        $this->assertNotNull($firstId);
        $this->assertSame($firstId, $secondId);
        $this->assertMatchesRegularExpression('/^forge-input-[0-9a-f]{12}$/', $firstId);
    }

    #[Test]
    public function explicit_id_is_preserved(): void
    {
        $html = (string) $this->blade('<x-forge-input id="my-field" label="Nome" wire:model.live="name" />');

        $this->assertSame('my-field', $this->extractInputId($html));
    }

    #[Test]
    public function label_for_matches_the_input_id(): void
    {
        $html = (string) $this->blade('<x-forge-input label="Preço" wire:model.live="formData.price" />');

        $inputId = $this->extractInputId($html);

        $this->assertNotNull($inputId);
        $this->assertStringContainsString('for="'.$inputId.'"', $html);
    }

    private function extractInputTag(string $html): ?string
    {
        return preg_match('/<input\b[^>]*>/', $html, $matches) === 1 ? $matches[0] : null;
    }

    private function extractInputId(string $html): ?string
    {
        $inputTag = $this->extractInputTag($html);

        if ($inputTag === null || preg_match('/\bid="([^"]*)"/', $inputTag, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
