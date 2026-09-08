<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\AI\AiChatWidget;
use Ptah\Models\AiModelConfig;
use Ptah\Tests\TestCase;
use RuntimeException;

/**
 * The chat input: the draft belongs to the browser, and the box grows.
 *
 * Reported as "as vezes estou digitando e o texto se apaga sozinho, talvez seja
 * por conta de uma resposta sendo recebida". The diagnosis was right, and the
 * mechanism is worth stating because the fix looks like a refactor otherwise.
 *
 * The textarea carried `wire:model.live="userInput"`, so its value was SERVER
 * state. `send()` set that property to '' and dispatched `ai-process-message`,
 * which Livewire runs as a separate request — a slow one, because it waits on
 * the model. Anything typed during that wait existed only in the browser. When
 * the slow response landed, Livewire morphed the component from a snapshot whose
 * `userInput` was '', and the typed text was gone.
 *
 * No amount of care inside `send()` fixes that: the bug is that a value the user
 * is actively editing was being round-tripped through a server that had already
 * decided it was empty. So the draft stopped being server state. `send()` takes
 * the text as an argument, and the property is gone.
 *
 * These tests therefore assert an absence — no `wire:model` on the textarea, no
 * `userInput` on the component — which is exactly the kind of assertion that
 * rots into passing vacuously. Each one is anchored: the textarea must exist and
 * must be found before anything is claimed about it.
 */
class AiChatInputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sem provedor ativo o widget nao renderiza nada, e sem convidado
        // permitido nao renderiza para uma sessao anonima. Os dois sao pre-
        // requisitos do alvo destes testes, nao detalhe de arranjo.
        config(['ptah.ai_agent.allow_guests' => true]);

        AiModelConfig::create([
            'name' => 'Padrao',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-test',
            'is_active' => true,
            'is_default' => true,
            'max_tokens' => 1024,
            'temperature' => 0.7,
        ]);
    }

    private function html(): string
    {
        $html = Livewire::test(AiChatWidget::class)->set('isOpen', true)->html();

        if (! str_contains($html, '<textarea')) {
            throw new RuntimeException('O textarea do chat nao renderizou — as asserticoes abaixo nao teriam alvo.');
        }

        return $html;
    }

    /**
     * The textarea tag, so assertions cannot accidentally match the panel around it.
     */
    private function textarea(): string
    {
        $html = $this->html();

        if (! preg_match('/<textarea\b[^>]*>/s', $html, $m)) {
            throw new RuntimeException('Nao consegui isolar a tag do textarea.');
        }

        return $m[0];
    }

    #[Test]
    public function the_draft_is_not_bound_to_the_server(): void
    {
        $textarea = $this->textarea();

        $this->assertStringNotContainsString(
            'wire:model',
            $textarea,
            'O rascunho voltou a ser estado do servidor: uma resposta lenta chegando apaga o que o usuario esta digitando.'
        );
        $this->assertStringContainsString('x-model="draft"', $textarea);
    }

    #[Test]
    public function the_morph_cannot_touch_the_textarea(): void
    {
        // Belt to the braces above: even with no wire:model, a re-render that
        // replaces the node would reset the value the browser holds.
        $this->assertStringContainsString('wire:ignore', $this->textarea());
    }

    #[Test]
    public function the_component_no_longer_holds_a_draft_property(): void
    {
        $this->assertFalse(
            property_exists(AiChatWidget::class, 'userInput'),
            'Enquanto a propriedade existir, alguem religa um wire:model nela.'
        );
    }

    #[Test]
    public function send_takes_the_message_as_an_argument(): void
    {
        Livewire::test(AiChatWidget::class)
            ->call('send', 'quantos pedidos abertos?')
            ->assertSet('loading', true)
            ->assertDispatched('ai-process-message', message: 'quantos pedidos abertos?');
    }

    #[Test]
    public function the_message_reaches_the_list_immediately(): void
    {
        // The user's own line must not wait for the model: it is the feedback
        // that the send happened at all.
        $component = Livewire::test(AiChatWidget::class)->call('send', 'ola');

        $this->assertSame(
            [['role' => 'user', 'content' => 'ola']],
            $component->get('messages')
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function emptyDraftProvider(): array
    {
        return [
            'vazio' => [''],
            'espacos' => ['   '],
            'quebras' => ["\n\n"],
        ];
    }

    #[Test]
    #[DataProvider('emptyDraftProvider')]
    public function an_empty_draft_sends_nothing(string $draft): void
    {
        Livewire::test(AiChatWidget::class)
            ->call('send', $draft)
            ->assertSet('loading', false)
            ->assertNotDispatched('ai-process-message');
    }

    #[Test]
    public function a_second_send_is_refused_while_one_is_in_flight(): void
    {
        $component = Livewire::test(AiChatWidget::class)
            ->call('send', 'primeira')
            ->call('send', 'segunda');

        $this->assertCount(
            1,
            $component->get('messages'),
            'A segunda mensagem entrou na lista sem ser enviada — ficaria orfa na tela.'
        );
    }

    #[Test]
    public function a_new_conversation_tells_the_browser_to_clear_the_draft(): void
    {
        // It cannot assign the draft any more, so it has to say so.
        Livewire::test(AiChatWidget::class)
            ->call('newConversation')
            ->assertDispatched('ai-draft-clear');
    }

    #[Test]
    public function the_textarea_is_never_disabled_while_the_model_answers(): void
    {
        // Typing the next question while the answer streams is what people do —
        // and it is how the original bug was found. Only the send waits.
        $textarea = $this->textarea();

        $this->assertStringNotContainsString('wire:loading.attr="disabled"', $textarea);
        $this->assertStringNotContainsString('disabled', $textarea);
    }

    #[Test]
    public function the_send_button_waits_for_the_answer_and_for_a_non_empty_draft(): void
    {
        $html = $this->html();

        $this->assertMatchesRegularExpression(
            '/x-bind:disabled="draft\.trim\(\) === \'\' \|\| \$wire\.loading"/',
            $html,
            'O envio precisa esperar a resposta e recusar rascunho vazio — do lado do cliente, que e quem tem o rascunho.'
        );
    }

    #[Test]
    public function the_box_grows_with_the_text_and_scrolls_at_the_ceiling(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('@input="grow()"', $html);

        // The ceiling used to be `max-h-32 overflow-hidden`: text past 128px was
        // invisible AND unreachable. The overflow now flips with the height.
        $this->assertStringContainsString("ta.style.overflowY = ta.scrollHeight > max ? 'auto' : 'hidden'", $html);
        $this->assertStringNotContainsString('max-h-32 overflow-hidden', $html);
    }

    #[Test]
    public function the_box_shrinks_back_after_sending(): void
    {
        // Otherwise a long question leaves a tall empty box behind it.
        $html = $this->html();

        $this->assertMatchesRegularExpression('/submit\(\)\s*\{[^}]*resetGrow\(\)/s', $html);
    }

    #[Test]
    public function shift_enter_is_left_to_the_browser(): void
    {
        // The old handler prevented every Enter and then appended '\n' to the
        // END of the value, ignoring the caret. Returning early lets the
        // browser insert the newline where the user actually is.
        $html = $this->html();

        $this->assertMatchesRegularExpression(
            '/onEnter\(e\)\s*\{.*?if \(e\.shiftKey\) return;/s',
            $html
        );
        $this->assertStringNotContainsString('@keydown.enter.prevent', $html);
    }
}
