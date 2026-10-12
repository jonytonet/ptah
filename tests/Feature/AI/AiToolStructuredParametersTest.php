<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Tool;
use Ptah\Contracts\AiToolInterface;
use Ptah\Services\AI\AiToolRegistry;
use Ptah\Tests\TestCase;

final class BatchFixTool implements AiToolInterface
{
    /** @var array<string, mixed> */
    public static array $received = [];

    public function name(): string
    {
        return 'batchFix';
    }

    public function description(): string
    {
        return 'Fixes many products.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'lista' => [
                    'type' => 'array',
                    'description' => 'One per product',
                    'items' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']],
                ],
                'filtro' => ['type' => 'object', 'description' => 'Filter', 'properties' => ['status' => ['type' => 'string']]],
                'modo' => ['type' => 'string', 'description' => 'Mode', 'enum' => ['preview', 'gravar']],
                'nota' => ['type' => 'string', 'description' => 'Free text'],
            ],
            'required' => ['lista'],
        ];
    }

    public function execute(array $arguments): array
    {
        self::$received = $arguments;

        return ['ok' => true];
    }
}

/**
 * 1.44.2 (PetPlace): a tool parameter of type array/object was declared to
 * the provider as STRING — the model sent the list as text, `is_array()` was
 * false, and a batch tool saved nothing.
 */
class AiToolStructuredParametersTest extends TestCase
{
    private function tool(): Tool
    {
        $registry = new AiToolRegistry;
        $registry->registerClass(BatchFixTool::class);

        return $registry->getPrismTools()[0];
    }

    #[Test]
    public function array_object_and_enum_keep_their_schema(): void
    {
        $params = $this->tool()->parametersAsArray();

        $this->assertSame('array', $params['lista']['type']);
        $this->assertSame('integer', $params['lista']['items']['properties']['id']['type']);
        $this->assertSame('object', $params['filtro']['type']);
        $this->assertSame(['preview', 'gravar'], $params['modo']['enum']);
        $this->assertSame('string', $params['nota']['type']);
        $this->assertSame(['lista'], $this->tool()->requiredParameters());
    }

    #[Test]
    public function the_list_reaches_execute_as_an_array(): void
    {
        $this->tool()->handle(lista: [['id' => 1], ['id' => 2]], modo: 'gravar');

        $this->assertSame([['id' => 1], ['id' => 2]], BatchFixTool::$received['lista']);
    }

    #[Test]
    public function a_provider_that_still_sends_text_gets_it_decoded(): void
    {
        $this->tool()->handle(lista: '[{"id":5},{"id":52}]', filtro: '{"status":"open"}', nota: '[nao e lista]');

        $this->assertSame([['id' => 5], ['id' => 52]], BatchFixTool::$received['lista']);
        $this->assertSame(['status' => 'open'], BatchFixTool::$received['filtro']);
        $this->assertSame('[nao e lista]', BatchFixTool::$received['nota'], 'Parametro string nao e decodificado.');
    }
}
