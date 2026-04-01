<?php

declare(strict_types=1);

namespace Ptah\Services\AI\Tools;

use Ptah\Contracts\AiToolInterface;

/**
 * Built-in tool: returns key system information about the host application.
 *
 * This is provided as a working demonstration of the AiToolInterface contract
 * and as a useful default capability — the assistant can answer questions
 * like "what system is this?" or "what Laravel version is running?" out of the box.
 */
class GetSystemInfoTool implements AiToolInterface
{
    public function name(): string
    {
        return 'getSystemInfo';
    }

    public function description(): string
    {
        return 'Returns information about the system: application name, Laravel version, PHP version, and current environment.';
    }

    public function parameters(): array
    {
        // No parameters needed — returns system metadata unconditionally
        return [
            'type'       => 'object',
            'properties' => new \stdClass(), // empty object in JSON Schema
            'required'   => [],
        ];
    }

    public function execute(array $arguments): array
    {
        return [
            'app_name'       => config('app.name', 'Unknown'),
            'laravel_version'=> app()->version(),
            'php_version'    => PHP_VERSION,
            'environment'    => app()->environment(),
            'timezone'       => config('app.timezone', 'UTC'),
            'locale'         => app()->getLocale(),
        ];
    }
}
