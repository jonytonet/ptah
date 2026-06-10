<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\AiConversation;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiProviderConfigService;
use Ptah\Tests\TestCase;

/**
 * Covers the AI provider configuration service and the model contracts that
 * protect API keys (encryption at rest + hidden from serialisation).
 */
class AiProviderConfigServiceTest extends TestCase
{
    private function service(): AiProviderConfigService
    {
        return new AiProviderConfigService;
    }

    private function makeConfig(array $overrides = []): AiModelConfig
    {
        return AiModelConfig::create(array_merge([
            'name' => 'Default',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-secret-key',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'is_active' => true,
            'is_default' => true,
        ], $overrides));
    }

    #[Test]
    public function api_key_is_encrypted_at_rest_and_hidden_from_array(): void
    {
        $config = $this->makeConfig();

        $raw = DB::table('ptah_ai_model_configs')->where('id', $config->id)->value('api_key');

        $this->assertNotSame('sk-secret-key', $raw, 'API key must not be stored in plaintext');
        $this->assertSame('sk-secret-key', $config->fresh()->api_key, 'API key must decrypt transparently');
        $this->assertArrayNotHasKey('api_key', $config->toArray(), 'API key must be hidden from serialisation');
    }

    #[Test]
    public function create_with_default_clears_the_flag_from_others(): void
    {
        $first = $this->makeConfig(['name' => 'First']);
        $this->assertTrue($first->fresh()->is_default);

        $this->service()->create([
            'name' => 'Second',
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-6',
            'api_key' => 'sk-other',
            'max_tokens' => 1024,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertFalse($first->fresh()->is_default);
    }

    #[Test]
    public function find_default_returns_the_active_default_provider(): void
    {
        $this->makeConfig(['name' => 'Inactive', 'is_active' => false, 'is_default' => false]);
        $default = $this->makeConfig(['name' => 'TheDefault']);

        $this->assertSame($default->id, $this->service()->findDefault()?->id);
    }

    #[Test]
    public function has_active_provider_reflects_state_after_cache_clear(): void
    {
        $service = $this->service();
        $this->assertFalse($service->hasActiveProvider());

        $config = $this->makeConfig();
        $service->clearCache();

        $this->assertTrue($service->hasActiveProvider());

        $service->delete($config);
        $this->assertFalse($service->hasActiveProvider());
    }

    #[Test]
    public function set_default_moves_the_flag(): void
    {
        $a = $this->makeConfig(['name' => 'A']);
        $b = $this->makeConfig(['name' => 'B', 'is_default' => false]);

        $this->service()->setDefault($b->id);

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
    }

    #[Test]
    public function conversation_scopes_filter_by_user_and_session(): void
    {
        AiConversation::create(['user_id' => 1, 'session_id' => 's1', 'messages' => [], 'tokens_used' => 0]);
        AiConversation::create(['user_id' => 2, 'session_id' => 's2', 'messages' => [], 'tokens_used' => 0]);

        $this->assertCount(1, AiConversation::byUser(1)->get());
        $this->assertCount(1, AiConversation::bySession('s2')->get());
    }
}
