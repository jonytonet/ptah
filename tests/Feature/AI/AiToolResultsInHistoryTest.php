<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Testing\TextStepFake;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;
use Ptah\Contracts\AiToolInterface;
use Ptah\Models\AiConversation;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiChatService;
use Ptah\Services\AI\AiProviderConfigService;
use Ptah\Services\AI\AiToolRegistry;
use Ptah\Tests\TestCase;

final class SecretLookupTool implements AiToolInterface
{
    public function name(): string
    {
        return 'secretLookup';
    }

    public function description(): string
    {
        return 'Looks up something large.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(array $arguments): array
    {
        return [];
    }

    public function keepResultInHistory(): bool
    {
        return false;
    }
}

/**
 * 1.44.3 (PetPlace): what the tools returned vanished at the end of the turn,
 * so "yes" to a preview found no preview — the model asked for the product
 * ids it had just shown. The results now stay in the conversation and go
 * back to the model on the next turns.
 */
class AiToolResultsInHistoryTest extends TestCase
{
    private AiToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        AiModelConfig::create(['name' => 'D', 'provider' => 'openai', 'model' => 'm', 'api_key' => 'k',
            'max_tokens' => 256, 'temperature' => 0.5, 'is_active' => true, 'is_default' => true]);
        $this->registry = new AiToolRegistry;
        $this->registry->registerClass(SecretLookupTool::class);
    }

    private function service(): AiChatService
    {
        return new AiChatService(new AiProviderConfigService, $this->registry);
    }

    private function previewStep(string $tool = 'previewFiscal', string $result = '{"linhas":[{"product_id":5},{"product_id":52}]}'): TextStepFake
    {
        return TextStepFake::make()->withText('')
            ->withToolCalls([new ToolCall('c1', $tool, ['filial' => 1])])
            ->withToolResults([new ToolResult('c1', $tool, ['filial' => 1], $result)]);
    }

    #[Test]
    public function the_preview_is_still_there_on_the_next_turn(): void
    {
        $fake = Prism::fake([
            TextResponseFake::make()->withText('Previa: 2 produtos.')->withSteps(collect([$this->previewStep()])),
            TextResponseFake::make()->withText('Gravado.'),
        ]);

        $id = $this->service()->send('previa', 'sess-t', 1)['conversationId'];
        $stored = AiConversation::find($id)->messages[1];
        $this->assertSame('previewFiscal', $stored['tools'][0]['name']);
        $this->assertStringContainsString('"product_id":52', $stored['tools'][0]['result']);

        $this->service()->send('sim', 'sess-t', 1, $id);

        $fake->assertRequest(function (array $requests) {
            $messages = $requests[1]->messages();
            // user, assistant(tool call), tool result, assistant(texto), user "sim"
            $this->assertInstanceOf(AssistantMessage::class, $messages[1]);
            $this->assertSame('previewFiscal', $messages[1]->toolCalls[0]->name);
            $this->assertInstanceOf(ToolResultMessage::class, $messages[2]);
            $this->assertStringContainsString('"product_id":52', $messages[2]->toolResults[0]->result);
            $this->assertSame('Previa: 2 produtos.', $messages[3]->content);
        });
    }

    #[Test]
    public function a_large_result_is_clipped_and_zero_bytes_keeps_none(): void
    {
        config(['ptah.ai_agent.tool_result_history_bytes' => 20]);
        Prism::fake([TextResponseFake::make()->withText('ok')->withSteps(collect([$this->previewStep()]))]);
        $id = $this->service()->send('previa', 'sess-t2', 1)['conversationId'];
        $this->assertStringEndsWith('…[truncated]', AiConversation::find($id)->messages[1]['tools'][0]['result']);

        config(['ptah.ai_agent.tool_result_history_bytes' => 0]);
        Prism::fake([TextResponseFake::make()->withText('ok')->withSteps(collect([$this->previewStep()]))]);
        $id = $this->service()->send('previa', 'sess-t3', 1)['conversationId'];
        $this->assertArrayNotHasKey('tools', AiConversation::find($id)->messages[1]);
    }

    #[Test]
    public function a_tool_can_keep_its_result_out_of_the_history(): void
    {
        $this->registry->getPrismTools();
        Prism::fake([TextResponseFake::make()->withText('ok')->withSteps(collect([$this->previewStep('secretLookup')]))]);

        $id = $this->service()->send('busca', 'sess-t4', 1)['conversationId'];

        $this->assertArrayNotHasKey('tools', AiConversation::find($id)->messages[1]);
    }

    #[Test]
    public function complete_asks_the_configured_model_once_without_tools_or_history(): void
    {
        $fake = Prism::fake([TextResponseFake::make()->withText('NCM 2309.10.00')]);

        $this->assertSame('NCM 2309.10.00', $this->service()->complete('Voce sugere NCM.', 'Racao para gatos 3kg'));
        $fake->assertRequest(function (array $requests) {
            $this->assertSame([], $requests[0]->tools());
            $this->assertSame('Racao para gatos 3kg', $requests[0]->prompt());
        });
        $this->assertSame(0, AiConversation::count());
    }
}
