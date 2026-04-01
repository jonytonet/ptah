<?php

declare(strict_types=1);

namespace Ptah\Services\AI\Tools;

use Carbon\Carbon;
use Ptah\Contracts\AiToolInterface;

/**
 * Built-in tool: returns the current date and time.
 *
 * Useful for grounding the assistant in the present — LLMs have a knowledge
 * cut-off date and may not know the current date without a tool like this.
 */
class GetCurrentDateTimeTool implements AiToolInterface
{
    public function name(): string
    {
        return 'getCurrentDateTime';
    }

    public function description(): string
    {
        return 'Returns the current date and time in the application timezone. Use this whenever the user asks about the current date, time, day of the week, etc.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => new \stdClass(),
            'required'   => [],
        ];
    }

    public function execute(array $arguments): array
    {
        $now = Carbon::now();

        return [
            'datetime'    => $now->toDateTimeString(),
            'date'        => $now->toDateString(),
            'time'        => $now->toTimeString(),
            'day_of_week' => $now->isoFormat('dddd'),
            'timezone'    => $now->timezoneName,
            'unix'        => $now->timestamp,
        ];
    }
}
