<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prism\Prism\Tool;
use Ptah\Contracts\AiToolInterface;
use Ptah\Services\AI\AiToolRegistry;

/**
 * Covers conversion of AiToolInterface tools into Prism Tool objects,
 * including the JSON-Schema → Prism parameter mapping and required flags.
 */
class AiToolRegistryTest extends TestCase
{
    private function sampleTool(): AiToolInterface
    {
        return new class implements AiToolInterface
        {
            public function name(): string
            {
                return 'searchProducts';
            }

            public function description(): string
            {
                return 'Search products by status and limit.';
            }

            public function parameters(): array
            {
                return [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string',  'description' => 'Status filter'],
                        'limit' => ['type' => 'integer', 'description' => 'Max results'],
                        'active' => ['type' => 'boolean', 'description' => 'Only active'],
                    ],
                    'required' => ['status'],
                ];
            }

            public function execute(array $arguments): array
            {
                return ['ok' => true, 'args' => $arguments];
            }
        };
    }

    #[Test]
    public function it_converts_registered_tools_into_prism_tools(): void
    {
        $registry = new AiToolRegistry;
        $registry->register($this->sampleTool());

        $tools = $registry->getPrismTools();

        $this->assertCount(1, $tools);
        $this->assertInstanceOf(Tool::class, $tools[0]);
        $this->assertSame('searchProducts', $tools[0]->name());
        $this->assertSame('Search products by status and limit.', $tools[0]->description());
    }

    #[Test]
    public function it_maps_parameters_and_required_flags(): void
    {
        $registry = new AiToolRegistry;
        $registry->register($this->sampleTool());

        $tool = $registry->getPrismTools()[0];

        $this->assertTrue($tool->hasParameters());
        $this->assertEqualsCanonicalizing(
            ['status', 'limit', 'active'],
            array_keys($tool->parameters()),
        );
        $this->assertSame(['status'], $tool->requiredParameters());
    }

    #[Test]
    public function registering_two_tools_with_the_same_name_keeps_one(): void
    {
        $registry = new AiToolRegistry;
        $registry->register($this->sampleTool());
        $registry->register($this->sampleTool());

        $this->assertCount(1, $registry->getPrismTools());
    }
}
