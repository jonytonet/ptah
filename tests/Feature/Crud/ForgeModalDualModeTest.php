<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Support\Facades\Blade;
use Livewire\Component;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Guards the dual-mode support of <x-forge-modal>: it must keep working with
 * the historical "parent scope" pattern (parent declares x-data and passes
 * "open" down) while additively supporting a self-contained wire:model mode
 * that entangles its own x-data. A regression here would either break the
 * ~15 existing internal screens (parent mode) or silently no-op the new
 * wire:model mode.
 */
class ForgeModalDualModeTest extends TestCase
{
    #[Test]
    public function parent_scope_mode_keeps_reading_open_without_declaring_its_own_x_data(): void
    {
        $html = Blade::render('<x-forge-modal title="X">body</x-forge-modal>');

        $this->assertStringContainsString('x-show="open"', $html);
        $this->assertStringNotContainsString('x-data', $html);
    }

    #[Test]
    public function wire_model_mode_entangles_its_own_x_data_and_strips_wire_model_from_root(): void
    {
        $result = Livewire::test(new class extends Component
        {
            public bool $showX = false;

            public function render()
            {
                return <<<'BLADE'
                <div>
                    <x-forge-modal wire:model="showX" title="X">body</x-forge-modal>
                </div>
                BLADE;
            }
        });

        $html = $result->html();

        $this->assertStringContainsString('x-data="{ open:', $html);
        $this->assertStringContainsString("entangle('showX')", $html);
        $this->assertStringNotContainsString('wire:model="showX"', $html);
    }

    #[Test]
    public function wire_model_live_modifier_is_reflected_in_the_entangle_call(): void
    {
        $result = Livewire::test(new class extends Component
        {
            public bool $showX = false;

            public function render()
            {
                return <<<'BLADE'
                <div>
                    <x-forge-modal wire:model.live="showX" title="X">body</x-forge-modal>
                </div>
                BLADE;
            }
        });

        $html = $result->html();

        $this->assertStringContainsString("entangle('showX')", $html);
        $this->assertStringContainsString('.live', $html);
        $this->assertStringNotContainsString('wire:model.live="showX"', $html);
    }

    #[Test]
    public function wire_modelable_does_not_trigger_the_self_contained_mode(): void
    {
        $html = Blade::render('<x-forge-modal wire:modelable="x" title="X">body</x-forge-modal>');

        $this->assertStringNotContainsString('x-data', $html);
        $this->assertStringNotContainsString('entangle', $html);
    }
}
