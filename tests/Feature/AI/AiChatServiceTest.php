<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Ptah\Exceptions\AiProviderException;
use Ptah\Exceptions\AiRateLimitException;
use Ptah\Models\AiConversation;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiChatService;
use Ptah\Services\AI\AiProviderConfigService;
use Ptah\Services\AI\AiToolRegistry;
use Ptah\Tests\TestCase;

/**
 * Covers the AI chat service against a faked Prism provider: response handling,
 * token accounting (promptTokens/completionTokens), conversation persistence,
 * temperature forwarding and the rate-limit / no-provider guards.
 */
class AiChatServiceTest extends TestCase
{
    private function service(): AiChatService
    {
        return new AiChatService(new AiProviderConfigService, new AiToolRegistry);
    }

    private function makeProvider(array $overrides = []): AiModelConfig
    {
        return AiModelConfig::create(array_merge([
            'name' => 'Default',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-test',
            'max_tokens' => 256,
            'temperature' => 0.5,
            'is_active' => true,
            'is_default' => true,
        ], $overrides));
    }

    #[Test]
    public function it_returns_the_assistant_text_and_persists_the_conversation(): void
    {
        Prism::fake([
            TextResponseFake::make()->withText('Hi there')->withUsage(new Usage(7, 13)),
        ]);
        $this->makeProvider();

        $result = $this->service()->send('Hello', 'sess-1', userId: 1);

        $this->assertSame('Hi there', $result['text']);

        $conv = AiConversation::find($result['conversationId']);
        $this->assertSame(1, $conv->user_id);
        $this->assertSame(20, $conv->tokens_used);               // 7 + 13 (promptTokens + completionTokens)
        $this->assertSame('openai', $conv->provider_used);
        $this->assertCount(2, $conv->messages);
        $this->assertSame('Hello', $conv->messages[0]['content']);
        $this->assertSame('Hi there', $conv->messages[1]['content']);
        $this->assertSame('Hello', $conv->title);                 // title from first message
    }

    #[Test]
    public function it_forwards_the_configured_temperature_to_the_provider(): void
    {
        $fake = Prism::fake([
            TextResponseFake::make()->withText('x')->withUsage(new Usage(1, 1)),
        ]);
        $this->makeProvider(['temperature' => 0.42]);

        $this->service()->send('hi', 'sess-temp', userId: 1);

        $fake->assertRequest(function (array $requests): void {
            $this->assertEqualsWithDelta(0.42, $requests[0]->temperature(), 0.0001);
        });
    }

    #[Test]
    public function it_throws_when_no_provider_is_configured(): void
    {
        Prism::fake([
            TextResponseFake::make()->withText('never')->withUsage(new Usage(1, 1)),
        ]);

        $this->expectException(AiProviderException::class);
        $this->service()->send('hi', 'sess-2', userId: 1);
    }

    #[Test]
    public function it_throws_when_the_rate_limit_is_exceeded(): void
    {
        config(['ptah.ai_agent.rate_limit' => 1]);
        // Pre-register one hit so the limiter's timer exists and the next attempt
        // is genuinely over the limit (key matches "ptah:ai:user:{id}").
        RateLimiter::hit('ptah:ai:user:1', 60);

        $this->expectException(AiRateLimitException::class);
        $this->service()->send('hi', 'sess-3', userId: 1);
    }

    #[Test]
    public function guests_are_rejected_unless_allowed(): void
    {
        config(['ptah.ai_agent.allow_guests' => false]);
        $this->makeProvider();

        $this->expectException(AiProviderException::class);
        $this->service()->send('hi', 'sess-guest', userId: null);
    }

    #[Test]
    public function stream_accumulates_deltas_and_persists_the_full_text(): void
    {
        Prism::fake([
            TextResponseFake::make()->withText('Hello streaming world')->withUsage(new Usage(3, 9)),
        ]);
        $this->makeProvider();

        $deltas = [];
        $result = $this->service()->stream(
            'Hi',
            'sess-stream',
            userId: 1,
            onDelta: function (string $delta, string $accumulated) use (&$deltas): void {
                $deltas[] = $delta;
            },
        );

        // Deltas were emitted incrementally and concatenate to the full text.
        $this->assertNotEmpty($deltas);
        $this->assertSame('Hello streaming world', implode('', $deltas));
        $this->assertSame('Hello streaming world', $result['text']);

        $conv = AiConversation::find($result['conversationId']);
        $this->assertSame('Hello streaming world', $conv->messages[1]['content']);
    }

    #[Test]
    public function extract_delta_handles_both_real_events_and_the_testing_fake(): void
    {
        $method = new \ReflectionMethod(AiChatService::class, 'extractDelta');
        $method->setAccessible(true);
        $service = $this->service();

        // Real event-based API
        $event = new TextDeltaEvent('id-1', time(), 'Hello', 'msg-1');
        $this->assertSame('Hello', $method->invoke($service, $event));

        // Testing fake (stdClass with ->text)
        $this->assertSame('Hi', $method->invoke($service, (object) ['text' => 'Hi']));

        // Non-text event → empty string
        $this->assertSame('', $method->invoke($service, (object) ['foo' => 'bar']));
    }
}
