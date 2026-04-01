<?php

declare(strict_types=1);

namespace Ptah\Contracts;

/**
 * Contract for AI tools (function-calling) that can be registered in the Ptah AI Agent module.
 *
 * Implement this interface in your application and register the class in config/ptah.php:
 *
 *   'ai_agent' => [
 *       'tools' => [
 *           App\Services\AI\Tools\MyCustomTool::class,
 *       ],
 *   ],
 *
 * The `parameters()` method must return a JSON-Schema "object" descriptor:
 *
 *   [
 *       'type'       => 'object',
 *       'properties' => [
 *           'status' => ['type' => 'string', 'description' => 'Filter by status'],
 *       ],
 *       'required'  => ['status'],
 *   ]
 *
 * The `execute()` method receives the named arguments as an associative array
 * matching the keys declared in `properties`.
 */
interface AiToolInterface
{
    /** Unique tool name (snake_case). Used by the LLM to call the tool. */
    public function name(): string;

    /** Short description of what the tool does (shown to the LLM). */
    public function description(): string;

    /**
     * JSON-Schema-compatible parameter descriptor.
     *
     * @return array{type: string, properties: array<string, array<string, mixed>>, required?: list<string>}
     */
    public function parameters(): array;

    /**
     * Execute the tool with the provided arguments.
     *
     * @param  array<string, mixed> $arguments  Named arguments from the LLM tool call
     * @return array<mixed>                     Data to return to the LLM (will be JSON-encoded)
     */
    public function execute(array $arguments): array;
}
