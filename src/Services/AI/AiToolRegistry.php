<?php

declare(strict_types=1);

namespace Ptah\Services\AI;

use Prism\Prism\Tool;
use Ptah\Contracts\AiToolInterface;

/**
 * Registry for AI tools (function-calling) used by the Ptah AI Agent.
 *
 * Tools registered via AiToolInterface are automatically converted to
 * Prism Tool objects that the LLM provider can invoke.
 *
 * Built-in tools (GetSystemInfoTool, GetCurrentDateTimeTool) are registered
 * automatically by the ServiceProvider. Consumer applications can add their
 * own tools via config/ptah.php:
 *
 *   'ai_agent' => [
 *       'tools' => [
 *           App\Services\AI\Tools\MyCustomTool::class,
 *       ],
 *   ],
 */
class AiToolRegistry
{
    /** @var AiToolInterface[] */
    private array $tools = [];

    public function register(AiToolInterface $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    /**
     * Execute a tool by name with the provided arguments.
     *
     * Called by AiChatService during the manual agentic loop.
     *
     * @param  array<string, mixed> $arguments
     * @return array<mixed>
     */
    public function execute(string $name, array $arguments): array
    {
        if (!isset($this->tools[$name])) {
            return ['error' => "Tool '{$name}' not found."];
        }

        try {
            return $this->tools[$name]->execute($arguments);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Convert all registered AiToolInterface tools to Prism Tool objects.
     *
     * The `using()` closure uses PHP 8's variadic named-arg spread:
     * when Prism calls `$fn(...$namedArgs)`, the `...$args` variadic
     * captures the full associative array including keys — so we can
     * forward it directly to `AiToolInterface::execute()`.
     *
     * @return Tool[]
     */
    public function getPrismTools(): array
    {
        return array_map(
            fn (AiToolInterface $tool) => $this->convertToPrismTool($tool),
            array_values($this->tools)
        );
    }

    /** Returns true when at least one tool is registered. */
    public function hasTools(): bool
    {
        return !empty($this->tools);
    }

    // ─────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────

    private function convertToPrismTool(AiToolInterface $tool): Tool
    {
        $schema    = $tool->parameters();
        $props     = $schema['properties'] ?? [];
        $required  = $schema['required'] ?? [];

        $prismTool = (new Tool())->as($tool->name())->for($tool->description());

        foreach ($props as $name => $def) {
            if (!is_array($def)) {
                continue;
            }

            $isRequired = in_array($name, $required, true);
            $desc       = $def['description'] ?? $name;
            $type       = $def['type'] ?? 'string';

            $prismTool = match ($type) {
                'number', 'integer' => $prismTool->withNumberParameter($name, $desc, required: $isRequired),
                'boolean'           => $prismTool->withBooleanParameter($name, $desc, required: $isRequired),
                default             => $prismTool->withStringParameter($name, $desc, required: $isRequired),
            };
        }

        // PHP 8 variadic spread preserves named argument keys:
        // $fn(...['status' => 'active']) → $args = ['status' => 'active']
        $t         = $tool;
        $prismTool = $prismTool->using(static function (mixed ...$args) use ($t): string {
            return json_encode($t->execute($args));
        });

        return $prismTool;
    }
}
