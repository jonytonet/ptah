<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Testing\TextStepFake;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;
use Ptah\Models\AiConversation;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiChatService;
use Ptah\Services\AI\AiProviderConfigService;
use Ptah\Services\AI\AiToolRegistry;
use Ptah\Tests\TestCase;

/**
 * 1.43.1 (PetPlace, xAI/Grok): with history, a turn that needed a tool came
 * back from the stream EMPTY — no tool run, no text, no exception — and the
 * widget kept an empty bubble. The same turn via send() worked.
 */
class AiStreamEmptyTurnTest extends TestCase
{
    private function service(): AiChatService
    {
        return new AiChatService(new AiProviderConfigService, new AiToolRegistry);
    }

    private function provider(string $provider = 'openai'): void
    {
        AiModelConfig::create(['name' => 'Default', 'provider' => $provider, 'model' => 'm', 'api_key' => 'k',
            'max_tokens' => 256, 'temperature' => 0.5, 'is_active' => true, 'is_default' => true]);
    }

    #[Test]
    public function an_empty_stream_with_no_tool_run_is_retried_without_streaming(): void
    {
        Log::spy();
        Prism::fake([
            TextResponseFake::make()->withText(''),
            TextResponseFake::make()->withText('Produto cadastrado.'),
        ]);
        $this->provider();

        $deltas = [];
        $result = $this->service()->stream('cadastra', 'sess-e1', 1, null, function (string $d) use (&$deltas) {
            $deltas[] = $d;
        });

        $this->assertSame('Produto cadastrado.', $result['text']);
        $this->assertSame(['Produto cadastrado.'], $deltas, 'O widget recebe o texto da nova tentativa.');
        $this->assertSame('Produto cadastrado.', AiConversation::find($result['conversationId'])->messages[1]['content']);
        Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'retrying the turn'))->once();
    }

    #[Test]
    public function after_a_tool_has_run_it_is_never_retried_and_the_user_gets_a_notice(): void
    {
        Log::spy();
        $step = TextStepFake::make()->withText('')
            ->withToolCalls([new ToolCall('c1', 'createProduct', ['name' => 'Ração'])])
            ->withToolResults([new ToolResult('c1', 'createProduct', ['name' => 'Ração'], 'ok')]);
        $fake = Prism::fake([
            TextResponseFake::make()->withText('')->withSteps(collect([$step])),
            TextResponseFake::make()->withText('nao pode chegar aqui'),
        ]);
        $this->provider();

        $result = $this->service()->stream('cadastra', 'sess-e2', 1, null, fn () => null);

        $this->assertSame(__('ptah::ui.ai_empty_turn'), $result['text'], 'Nunca a bolha vazia.');
        $fake->assertCallCount(1);
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $ctx) => str_contains($m, 'without text') && $ctx['provider'] === 'openai' && $ctx['mode'] === 'stream')->once();
    }

    #[Test]
    public function an_empty_send_also_shows_the_notice(): void
    {
        Prism::fake([TextResponseFake::make()->withText('')]);
        $this->provider();

        $this->assertSame(__('ptah::ui.ai_empty_turn'), $this->service()->send('oi', 'sess-e3', 1)['text']);
    }

    #[Test]
    public function the_tool_rounds_per_turn_are_configurable(): void
    {
        config(['ptah.ai_agent.max_steps' => 12]);
        $fake = Prism::fake([TextResponseFake::make()->withText('ok')]);
        $this->provider();

        $this->service()->send('oi', 'sess-e4', 1);

        $fake->assertRequest(fn (array $requests) => $this->assertSame(12, $requests[0]->maxSteps()));
    }

    #[Test]
    public function on_xai_the_reasoning_is_not_read_from_the_stream(): void
    {
        $fake = Prism::fake([TextResponseFake::make()->withText('ok')]);
        $this->provider('xai');

        $this->service()->stream('oi', 'sess-e5', 1, null, fn () => null);

        $fake->assertRequest(fn (array $requests) => $this->assertFalse($requests[0]->providerOptions('thinking.enabled')));
    }
}
