<?php

declare(strict_types=1);

namespace Ptah\Contracts;

use Ptah\Support\AI\AiPreview;

/**
 * A write tool that asks before it writes (1.45.0).
 *
 * When the model calls it, ptah runs only preview(): the result is shown in
 * the chat as a card with "Confirm" / "Cancel", and the payload stays on the
 * server under a one-time token. The model never writes — it gets "awaiting
 * confirmation" and the summary. A click on "Confirm" runs commit() with that
 * exact payload, once, re-checking permission and company; the model only
 * words the outcome afterwards.
 *
 * execute() is never called for a confirmable tool. Implement it as a call to
 * preview() if the contract asks for something.
 */
interface AiConfirmableTool extends AiToolInterface
{
    /**
     * Compute what would be written. Write nothing here.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function preview(array $arguments): AiPreview;

    /**
     * Write exactly what preview() returned in its payload. Called at most
     * once per preview.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> shown to the model; a `message` key is shown to the user
     */
    public function commit(array $payload): array;
}
