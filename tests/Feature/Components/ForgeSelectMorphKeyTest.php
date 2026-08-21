<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Guards the morph-stable id of <x-forge-select>.
 *
 * Livewire's DOM-diff uses `el.id` as the morph key when the element has no
 * wire:id/wire:key. Before this fix, forge-select.blade.php generated a
 * random id (`uniqid()`) on EVERY render, so the morph would REMOVE and
 * recreate the wrapping <div x-data="..."> on each wire:model.live update —
 * destroying the Alpine component instance (losing `open`, `activeIndex`,
 * and any in-flight interaction) on every server round-trip.
 *
 * HONEST LIMIT: the actual Alpine state loss can only be proven in a real
 * browser (the morph runs against the browser DOM). This test only locks the
 * server-side precondition — that the id is identical across renders with
 * the same props, and distinct for a different field — which is the provable
 * root cause here. Mirrors ForgeInputMorphKeyTest.
 */
class ForgeSelectMorphKeyTest extends TestCase
{
    #[Test]
    public function id_is_identical_across_two_renders_of_the_same_field(): void
    {
        $template = '<x-forge-select label="Status" wire:model.live="filters.status" :options="[]" />';

        $firstId = $this->extractSelectId((string) $this->blade($template));
        $secondId = $this->extractSelectId((string) $this->blade($template));

        $this->assertNotNull($firstId);
        $this->assertSame($firstId, $secondId);
        $this->assertMatchesRegularExpression('/^forge-select-[0-9a-f]{12}$/', $firstId);
    }

    #[Test]
    public function different_fields_get_different_ids(): void
    {
        $statusHtml = (string) $this->blade(
            '<x-forge-select label="Status" wire:model.live="filters.status" :options="[]" />'
        );
        $kindHtml = (string) $this->blade(
            '<x-forge-select label="Tipo" wire:model.live="filters.kind" :options="[]" />'
        );

        $statusId = $this->extractSelectId($statusHtml);
        $kindId = $this->extractSelectId($kindHtml);

        $this->assertNotNull($statusId);
        $this->assertNotNull($kindId);
        $this->assertNotSame($statusId, $kindId);
    }

    #[Test]
    public function aria_controls_matches_the_list_id(): void
    {
        $html = (string) $this->blade(
            '<x-forge-select label="Status" wire:model.live="filters.status" :options="[]" />'
        );

        $selectId = $this->extractSelectId($html);

        $this->assertNotNull($selectId);
        $this->assertStringContainsString('aria-controls="'.$selectId.'-list"', $html);
        $this->assertStringContainsString('id="'.$selectId.'-list"', $html);
    }

    private function extractSelectId(string $html): ?string
    {
        return preg_match('/\bid="(forge-select-[0-9a-f]{12})"/', $html, $matches) === 1
            ? $matches[1]
            : null;
    }
}
