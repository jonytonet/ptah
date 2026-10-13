<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Ptah\Contracts\AiConfirmableTool;
use Ptah\Livewire\AI\AiChatWidget;
use Ptah\Models\AiConversation;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiChatService;
use Ptah\Services\AI\AiConfirmationService;
use Ptah\Services\AI\AiToolRegistry;
use Ptah\Support\AI\AiPreview;
use Ptah\Tests\Support\ActsAsPtahUser;
use Ptah\Tests\TestCase;

final class FiscalFixTool implements AiConfirmableTool
{
    /** @var list<array<string, mixed>> */
    public static array $commits = [];

    public function name(): string
    {
        return 'fiscalFix';
    }

    public function description(): string
    {
        return 'Fixes NCM of the branch products.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(array $arguments): array
    {
        throw new \LogicException('execute() must never run for a confirmable tool.');
    }

    public function preview(array $arguments): AiPreview
    {
        return new AiPreview(
            summary: '2 produtos vao receber NCM.',
            rows: [['product_id' => 5, 'ncm' => '2309.10.00'], ['product_id' => 52, 'ncm' => '2309.90.10']],
            payload: ['ids' => [5, 52], 'secret' => 'nao-vai-ao-navegador'],
            columns: ['product_id' => 'ID', 'ncm' => 'NCM'],
        );
    }

    public function commit(array $payload): array
    {
        self::$commits[] = $payload;

        return ['message' => 'Gravei 2 produtos.', 'gravados' => count($payload['ids'])];
    }
}

/**
 * 1.45.0: confirmable write tools (preview card → Confirm runs the write once,
 * without the model) and `/commands` (the tool runs directly).
 */
class AiConfirmableToolTest extends TestCase
{
    use ActsAsPtahUser;

    protected function setUp(): void
    {
        parent::setUp();

        FiscalFixTool::$commits = [];
        AiModelConfig::create(['name' => 'D', 'provider' => 'openai', 'model' => 'm', 'api_key' => 'k',
            'max_tokens' => 256, 'temperature' => 0.5, 'is_active' => true, 'is_default' => true]);
        config(['ptah.ai_agent.stream' => false, 'ptah.ai_agent.commands' => [
            'corrige-fiscal' => ['tool' => FiscalFixTool::class, 'arguments' => [], 'description' => 'Corrige NCM'],
        ]]);
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    /** Runs `/corrige-fiscal` in the widget and returns it with the card token. */
    private function preview(): array
    {
        Prism::fake([TextResponseFake::make()->withText('Revise a previa e confirme.')]);

        $widget = Livewire::test(AiChatWidget::class)->call('processAiMessage', '/corrige-fiscal');
        $messages = $widget->get('messages');
        $card = end($messages)['confirmations'][0] ?? null;
        $this->assertNotNull($card, 'O comando nao gerou o cartao de previa.');

        return [$widget, $card];
    }

    #[Test]
    public function when_the_model_calls_it_only_the_preview_runs(): void
    {
        $registry = new AiToolRegistry;
        $registry->registerClass(FiscalFixTool::class);

        $answer = json_decode((string) $registry->getPrismTools()[0]->handle(), true);

        $this->assertSame('awaiting_confirmation', $answer['status']);
        $this->assertSame([], FiscalFixTool::$commits, 'O modelo nao pode gravar.');
        $staged = app(AiConfirmationService::class)->takeStaged();
        $this->assertCount(1, $staged);
        $this->assertArrayNotHasKey('payload', $staged[0], 'O payload fica no servidor.');
    }

    #[Test]
    public function a_command_runs_its_tool_directly_and_shows_the_card(): void
    {
        [$widget, $card] = $this->preview();

        $this->assertSame('pending', $card['status']);
        $this->assertSame([['product_id' => 5, 'ncm' => '2309.10.00'], ['product_id' => 52, 'ncm' => '2309.90.10']], $card['rows']);
        $this->assertSame([], FiscalFixTool::$commits);
        $widget->assertSee(__('ptah::ui.ai_confirm_btn'))->assertDontSee('nao-vai-ao-navegador');
    }

    #[Test]
    public function confirm_writes_once_and_a_second_click_does_nothing(): void
    {
        [$widget, $card] = $this->preview();

        $widget->call('confirmAction', $card['token']);
        $this->assertCount(1, FiscalFixTool::$commits);
        $this->assertSame([5, 52], FiscalFixTool::$commits[0]['ids']);

        $messages = $widget->get('messages');
        $this->assertSame('Gravei 2 produtos.', end($messages)['content']);
        $this->assertSame('confirmed', $messages[count($messages) - 2]['confirmations'][0]['status']);

        $widget->call('confirmAction', $card['token']);
        $this->assertCount(1, FiscalFixTool::$commits, 'Dois cliques gravaram duas vezes.');

        // O resultado entra no historico para o modelo — mas nao no estado do widget.
        $stored = AiConversation::find($widget->get('conversationId'))->messages;
        $this->assertSame('fiscalFix', end($stored)['tools'][0]['name']);
        $this->assertArrayNotHasKey('tools', end($messages));
    }

    #[Test]
    public function cancel_writes_nothing_and_the_token_is_gone(): void
    {
        [$widget, $card] = $this->preview();

        $widget->call('cancelAction', $card['token']);
        $messages = $widget->get('messages');
        $this->assertSame(__('ptah::ui.ai_confirm_cancelled'), end($messages)['content']);

        $widget->call('confirmAction', $card['token']);
        $this->assertSame([], FiscalFixTool::$commits);
    }

    #[Test]
    public function another_user_cannot_spend_the_token(): void
    {
        [$widget, $card] = $this->preview();
        $conversationId = (int) $widget->get('conversationId');

        $this->actingAs(new GenericUser(['id' => 2]));
        $this->assertSame('denied', app(AiConfirmationService::class)->commit($card['token'], $conversationId)['status']);
        $this->assertSame([], FiscalFixTool::$commits);

        // O dono ainda confirma: a tentativa alheia nao consumiu o token.
        $this->actingAs(new GenericUser(['id' => 1]));
        $this->assertSame('confirmed', app(AiConfirmationService::class)->commit($card['token'], $conversationId)['status']);
    }

    #[Test]
    public function a_command_the_user_may_not_use_is_neither_listed_nor_run(): void
    {
        config(['ptah.modules.permissions' => true, 'ptah.ai_agent.commands.corrige-fiscal.permission' => ['fiscal', 'update']]);
        $this->actAsUserWhoCan(false);

        $this->assertSame([], app(AiChatService::class)->availableCommands());

        Prism::fake([TextResponseFake::make()->withText('x')]);
        $widget = Livewire::test(AiChatWidget::class)->call('processAiMessage', '/corrige-fiscal');
        $this->assertStringContainsString('/corrige-fiscal', $widget->get('errorMsg'));
        $this->assertSame([], FiscalFixTool::$commits);
    }

    #[Test]
    public function the_autocomplete_lists_the_allowed_commands(): void
    {
        Livewire::test(AiChatWidget::class)
            ->assertSet('commands', [['name' => 'corrige-fiscal', 'description' => 'Corrige NCM']]);
    }
}
