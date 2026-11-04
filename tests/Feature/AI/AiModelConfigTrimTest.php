<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\AI\AiModelConfigList;
use Ptah\Models\AiModelConfig;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;

/**
 * 1.56.1 (laudo B2B): a pasted " gpt-6-astra" was saved with its leading space
 * and failed only on the first chat message, as a 404. The fields that travel
 * to the provider are trimmed before they are validated and saved.
 */
class AiModelConfigTrimTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(PermissionService::class, new class extends PermissionService
        {
            public function __construct() {}

            public function isMaster(mixed $user = null): bool
            {
                return true;
            }
        });
    }

    #[Test]
    public function model_key_and_endpoint_are_saved_without_edge_spaces(): void
    {
        Livewire::test(AiModelConfigList::class)
            ->call('create')
            ->set('name', 'Principal')
            ->set('provider', 'openai_compatible')
            ->set('model', ' gpt-4o-mini ')
            ->set('api_key', "  sk-test\t")
            ->set('api_endpoint', ' https://llm.example.com/v1 ')
            ->call('save')
            ->assertHasNoErrors();

        $config = AiModelConfig::query()->where('name', 'Principal')->firstOrFail();

        $this->assertSame('gpt-4o-mini', $config->model);
        $this->assertSame('sk-test', $config->api_key);
        $this->assertSame('https://llm.example.com/v1', $config->api_endpoint);
    }
}
