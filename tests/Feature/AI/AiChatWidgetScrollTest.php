<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Ai;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ao ABRIR o painel do chat a conversa precisa rolar para o fim (feedback do
 * usuario: abria mostrando o TOPO do historico). O scrollToBottom ja existia
 * para o envio (@ai-message-sent) mas ninguem o chamava na abertura — o
 * watcher de `open` fecha esse caminho.
 */
class AiChatWidgetScrollTest extends TestCase
{
    #[Test]
    public function opening_the_panel_scrolls_the_conversation_to_the_bottom(): void
    {
        $blade = file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/ai/ai-chat-widget.blade.php');

        $this->assertStringContainsString(
            "\$watch('open', value => { if (value) scrollToBottom() })",
            $blade
        );
        $this->assertStringContainsString('@ai-message-sent.window="scrollToBottom()"', $blade);
    }
}
