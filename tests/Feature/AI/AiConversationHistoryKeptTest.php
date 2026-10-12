<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Ptah\Models\AiConversation;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiChatService;
use Ptah\Services\AI\AiProviderConfigService;
use Ptah\Services\AI\AiToolRegistry;
use Ptah\Tests\TestCase;

/**
 * 1.44.2 (PetPlace): the history trimmed for the model's context was what got
 * SAVED, so a long conversation lost its beginning for good.
 */
class AiConversationHistoryKeptTest extends TestCase
{
    private function turns(int $n): array
    {
        AiModelConfig::create(['name' => 'D', 'provider' => 'openai', 'model' => 'm', 'api_key' => 'k',
            'max_tokens' => 256, 'temperature' => 0.5, 'is_active' => true, 'is_default' => true]);
        $fake = Prism::fake(array_map(fn ($i) => TextResponseFake::make()->withText('resposta '.$i), range(1, $n)));

        $service = new AiChatService(new AiProviderConfigService, new AiToolRegistry);
        $conversationId = null;
        for ($i = 1; $i <= $n; $i++) {
            $conversationId = $service->send('pergunta '.$i, 'sess-h', 1, $conversationId)['conversationId'];
        }

        return [$fake, AiConversation::find($conversationId)];
    }

    #[Test]
    public function the_trim_limits_the_context_not_the_stored_conversation(): void
    {
        config(['ptah.ai_agent.max_history' => 4]);

        [$fake, $conversation] = $this->turns(5);

        $this->assertCount(10, $conversation->messages, 'A conversa salva perdeu o comeco.');
        $this->assertSame('pergunta 1', $conversation->messages[0]['content']);
        $this->assertSame('resposta 5', $conversation->messages[9]['content']);

        $fake->assertRequest(function (array $requests) {
            // 5o turno: as 4 ultimas mensagens + a pergunta nova.
            $this->assertCount(5, $requests[4]->messages());
        });
    }

    #[Test]
    public function the_stored_conversation_has_its_own_ceiling(): void
    {
        config(['ptah.ai_agent.max_history' => 4, 'ptah.ai_agent.max_stored_messages' => 6]);

        [, $conversation] = $this->turns(5);

        $this->assertCount(6, $conversation->messages);
        $this->assertSame('pergunta 3', $conversation->messages[0]['content']);
    }
}
