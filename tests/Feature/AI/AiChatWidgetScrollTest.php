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
 *
 * A asserticao casava a linha do watcher LITERALMENTE, e quebrou quando a
 * abertura passou a tambem focar o input. O comportamento vigiado continuava
 * intacto; era a forma exata que estava fixada. Agora casa o watcher e exige
 * scrollToBottom no corpo dele, que e a garantia de verdade.
 */
class AiChatWidgetScrollTest extends TestCase
{
    #[Test]
    public function opening_the_panel_scrolls_the_conversation_to_the_bottom(): void
    {
        $blade = file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/ai/ai-chat-widget.blade.php');

        $this->assertIsString($blade);

        $this->assertMatchesRegularExpression(
            '#\$watch\(.open.,[^\n]*scrollToBottom\(\)#',
            $blade,
            'Abrir o painel precisa rolar a conversa para o fim.'
        );
        $this->assertStringContainsString('@ai-message-sent.window="scrollToBottom()"', $blade);
    }
}
