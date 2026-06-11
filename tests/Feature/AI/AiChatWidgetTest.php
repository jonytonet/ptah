<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Ptah\Livewire\AI\AiChatWidget;
use Ptah\Models\AiModelConfig;
use Ptah\Tests\TestCase;

/**
 * Covers the floating chat widget wiring to the streaming service (PHP-level;
 * the browser-side incremental flush is verified manually).
 */
class AiChatWidgetTest extends TestCase
{
    private function makeProvider(): void
    {
        AiModelConfig::create([
            'name' => 'Default',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-test',
            'max_tokens' => 256,
            'temperature' => 0.5,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    #[Test]
    public function it_hides_the_widget_for_guests_when_not_allowed(): void
    {
        config(['ptah.ai_agent.allow_guests' => false]);
        $this->makeProvider();

        Livewire::test(AiChatWidget::class)->assertSet('available', false);
    }

    #[Test]
    public function it_streams_and_appends_the_assistant_message(): void
    {
        config(['ptah.ai_agent.allow_guests' => true, 'ptah.ai_agent.stream' => true]);
        Prism::fake([
            TextResponseFake::make()->withText('Streamed answer')->withUsage(new Usage(2, 4)),
        ]);
        $this->makeProvider();

        $component = Livewire::test(AiChatWidget::class)
            ->assertSet('available', true)
            ->call('processAiMessage', 'Hello');

        $messages = $component->get('messages');
        $last = end($messages);

        $this->assertSame('assistant', $last['role']);
        $this->assertSame('Streamed answer', $last['content']);
        $component->assertSet('loading', false);
    }
}
