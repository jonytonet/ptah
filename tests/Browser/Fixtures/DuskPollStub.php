<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser\Fixtures;

use Livewire\Component;

/**
 * A poll that ticks while a slow action of the same component is in flight —
 * the overlap Livewire answers by cancelling the poll action. With
 * `wire:poll="$refresh"` (or a bare `wire:poll`) Livewire 4.3.1 leaves that
 * cancellation as an unhandled promise rejection; with a named method it does
 * not. The package's bell and exports panel poll a named method for this
 * reason (see NotificationBell::refreshBadge()).
 */
class DuskPollStub extends Component
{
    /** '$refresh' to see the Livewire behaviour, a method name for the fix. */
    public string $pollExpression = 'tick';

    public int $done = 0;

    public function tick(): void
    {
        // Intentionally empty: any action re-renders.
    }

    public function slow(): void
    {
        usleep(1_500_000);
        $this->done++;
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div wire:poll.250ms="{{ $pollExpression }}">
                <button type="button" id="dusk-poll-slow" wire:click="slow">slow</button>
                <span id="dusk-poll-done">{{ $done }}</span>
            </div>
        BLADE;
    }
}
