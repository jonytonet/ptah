<?php

declare(strict_types=1);

namespace Ptah\Support\AI;

/**
 * What a confirmable tool would write (1.45.0).
 *
 *  - summary: one line for the user and the model ("11 products will get NCM…")
 *  - rows / columns: the table on the card (the first `max_preview_rows`)
 *  - payload: what commit() receives. Kept on the server only — never sent to
 *    the browser or the model.
 */
final class AiPreview
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $columns  key => label; empty = the keys of the first row
     */
    public function __construct(
        public readonly string $summary,
        public readonly array $rows = [],
        public readonly array $payload = [],
        public readonly array $columns = [],
    ) {}

    /** Nothing to confirm: the card is not shown and the model is told so. */
    public function isEmpty(): bool
    {
        return $this->rows === [] && $this->payload === [];
    }
}
