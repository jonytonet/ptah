<?php

declare(strict_types=1);

namespace Ptah\Http\Headless;

use Closure;
use Ptah\Crud\RunsHeadless;
use Ptah\Livewire\AI\AiChatWidget;

/**
 * AiChatWidget, run without Livewire by the AI chat API (1.59.0). The logic
 * is the parent's.
 *
 * One thing is redirected: `$this->stream(...)`. On Livewire it writes a
 * `wire:stream` frame into the open Livewire response; outside a Livewire
 * request there is no such response, so the frames go to a sink the API
 * controller sets (a `text/event-stream` writer), or nowhere when the client
 * asked for a single JSON answer. What is streamed is untouched: the HTML the
 * component renders with ChatMarkdown, the same the Livewire bubble shows.
 */
class HeadlessAiChatWidget extends AiChatWidget
{
    use RunsHeadless;

    /** @var (Closure(string): void)|null */
    private ?Closure $headlessStreamSink = null;

    /** @param  (Closure(string): void)|null  $sink  Receives each streamed frame (the whole answer so far, as HTML). */
    public function streamTo(?Closure $sink): static
    {
        $this->headlessStreamSink = $sink;

        return $this;
    }

    /**
     * Same signature as Livewire's HandlesStreaming::stream().
     *
     * @param  mixed  $content
     * @param  bool  $replace
     * @param  string|null  $name
     * @param  string|null  $el
     * @param  string|null  $ref
     * @param  string|null  $to
     */
    public function stream($content = null, $replace = false, $name = null, $el = null, $ref = null, $to = null)
    {
        if ($this->headlessStreamSink !== null && is_string($content)) {
            ($this->headlessStreamSink)($content);
        }

        return null;
    }
}
