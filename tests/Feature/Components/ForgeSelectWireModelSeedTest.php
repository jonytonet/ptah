<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Livewire\Component;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Guards the wire:model seeding of <x-forge-select> when the consumer does
 * NOT pass an explicit `:selected`.
 *
 * `$initialSelected` (forge-select.blade.php) feeds the Alpine `x-data`,
 * which is reevaluated on EVERY render because the wrapper `id` uses
 * `uniqid()` and Livewire's morph keys on `el.id`. The hidden-input bridge
 * (Alpine → Livewire) is unidirectional, so without seeding from the
 * Livewire component's own property, `selected` resets to null on the very
 * next re-render and the trigger falls back to the placeholder — even
 * though the server-side value is correct. This affected 10 of 13 call
 * sites in the package (edit modals included), not just the BaseCrud
 * filters (which already worked via `:selected`).
 *
 * HONEST LIMIT: this only proves the server-side seed (the value baked into
 * the initial `x-data`). It does not exercise a live wire:model.live round
 * trip in a browser.
 */
class ForgeSelectWireModelSeedTest extends TestCase
{
    #[Test]
    public function wire_model_value_seeds_selected(): void
    {
        $result = Livewire::test(new class extends Component
        {
            public string $status = 'active';

            public function render()
            {
                return <<<'BLADE'
                <div>
                    <x-forge-select wire:model.live="status" :options="[['value' => 'active', 'label' => 'Ativo']]" />
                </div>
                BLADE;
            }
        });

        $this->assertStringContainsString('selected: &quot;active&quot;', $result->html());
    }

    #[Test]
    public function dotted_path_is_resolved(): void
    {
        $result = Livewire::test(new class extends Component
        {
            public array $filters = ['kind' => 'a'];

            public function render()
            {
                return <<<'BLADE'
                <div>
                    <x-forge-select wire:model.live="filters.kind" :options="[['value' => 'a', 'label' => 'A']]" />
                </div>
                BLADE;
            }
        });

        $this->assertStringContainsString('selected: &quot;a&quot;', $result->html());
    }

    #[Test]
    public function missing_dotted_key_stays_null(): void
    {
        $result = Livewire::test(new class extends Component
        {
            public array $filters = [];

            public function render()
            {
                return <<<'BLADE'
                <div>
                    <x-forge-select wire:model.live="filters.kind" :options="[]" />
                </div>
                BLADE;
            }
        });

        $this->assertStringContainsString('selected: null', $result->html());
    }

    #[Test]
    public function explicit_selected_prop_wins_over_wire_model(): void
    {
        $result = Livewire::test(new class extends Component
        {
            public ?int $parent_id = 7;

            public function render()
            {
                return <<<'BLADE'
                <div>
                    <x-forge-select wire:model="parent_id" :selected="''" :options="[]" />
                </div>
                BLADE;
            }
        });

        $this->assertStringContainsString('selected: &quot;&quot;', $result->html());
    }

    #[Test]
    public function deferred_wire_model_is_also_seeded(): void
    {
        $result = Livewire::test(new class extends Component
        {
            public string $obj_type = 'button';

            public function render()
            {
                return <<<'BLADE'
                <div>
                    <x-forge-select wire:model="obj_type" :options="[['value' => 'button', 'label' => 'Botão']]" />
                </div>
                BLADE;
            }
        });

        $this->assertStringContainsString('selected: &quot;button&quot;', $result->html());
    }

    #[Test]
    public function wire_modelable_alone_does_not_seed(): void
    {
        $result = Livewire::test(new class extends Component
        {
            public string $x = 'boom';

            public function render()
            {
                return <<<'BLADE'
                <div>
                    <x-forge-select wire:modelable="x" :options="[]" />
                </div>
                BLADE;
            }
        });

        $html = $result->html();

        // Assertion scoped to the x-data payload, not the whole HTML: Livewire
        // always serializes public properties (including $x = 'boom') into
        // the root wire:snapshot attribute, so a bare "does not contain
        // 'boom'" check would false-positive-fail regardless of this fix.
        $this->assertStringContainsString('selected: null', $html);
        $this->assertStringNotContainsString('selected: &quot;boom&quot;', $html);
    }

    #[Test]
    public function outside_livewire_with_wire_model_does_not_throw(): void
    {
        $html = (string) $this->blade('<x-forge-select wire:model="x" :options="[]" />');

        $this->assertStringContainsString('selected: null', $html);
    }

    #[Test]
    public function multiple_with_wire_model_still_seeds_empty_array(): void
    {
        $result = Livewire::test(new class extends Component
        {
            public array $tags = ['a', 'b'];

            public function render()
            {
                return <<<'BLADE'
                <div>
                    <x-forge-select multiple wire:model="tags" :options="[]" />
                </div>
                BLADE;
            }
        });

        $this->assertStringContainsString('selected: []', $result->html());
    }
}
