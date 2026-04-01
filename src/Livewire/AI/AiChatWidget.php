<?php

declare(strict_types=1);

namespace Ptah\Livewire\AI;

use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Ptah\Exceptions\AiProviderException;
use Ptah\Exceptions\AiRateLimitException;
use Ptah\Services\AI\AiChatService;
use Ptah\Services\AI\AiProviderConfigService;

/**
 * Floating AI chat widget â€” injected globally into the Forge Dashboard layout.
 *
 * âš   This component has NO #[Layout] attribute â€” it is embedded as a child
 *    component via <livewire:ptah-ai-chat-widget /> inside forge-dashboard-layout.
 *
 * Behaviour:
 *  - Only renders the floating button when at least one active AI provider exists
 *  - Authenticated users: conversations persisted by user_id across sessions
 *  - Guests: single conversation per session_id
 *  - History panel shows last 20 conversations; user can switch between them
 *  - Enter sends a message; Shift+Enter inserts a line break
 */
class AiChatWidget extends Component
{
    protected AiChatService           $chatService;
    protected AiProviderConfigService $configService;

    public function boot(AiChatService $chatService, AiProviderConfigService $configService): void
    {
        $this->chatService   = $chatService;
        $this->configService = $configService;
    }

    // â”€â”€ State â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public bool   $available    = false;
    public bool   $isOpen       = false;
    public bool   $loading      = false;
    public bool   $showHistory  = false;
    public string $userInput    = '';
    public string $errorMsg     = '';
    public ?int   $conversationId = null;

    /** @var array<array{role: string, content: string}> */
    public array $messages = [];

    /** @var array<array{id: int, title: string, date: string}> */
    public array $conversations = [];

    public int $historyLimit = 5;

    private const HISTORY_MAX = 100;
    private const HISTORY_STEP = 5;

    // â”€â”€ Lifecycle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function mount(): void
    {
        $this->available = $this->configService->hasActiveProvider();

        if (! $this->available) {
            return;
        }

        $userId = auth()->id();

        if ($userId) {
            $conversation = $this->chatService->findLatestConversation($userId);
        } else {
            $conversation = $this->chatService->getOrCreateConversation(session()->getId());
        }

        if ($conversation) {
            $this->conversationId = $conversation->id;
            $this->messages = array_values(array_filter(
                $conversation->messages ?? [],
                fn ($m) => in_array($m['role'] ?? '', ['user', 'assistant'], true)
            ));
        }

        if ($userId) {
            $this->refreshConversations();
        }
    }

    // â”€â”€ Actions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function send(): void
    {
        $message = trim($this->userInput);

        if ($message === '' || ! $this->available || $this->loading) {
            return;
        }

        $this->messages[]  = ['role' => 'user', 'content' => $message];
        $this->userInput   = '';
        $this->errorMsg    = '';
        $this->loading     = true;
        $this->showHistory = false;

        $this->dispatch('ai-process-message', message: $message, conversationId: $this->conversationId);
        $this->dispatch('ai-message-sent');
    }

    #[On('ai-process-message')]
    public function processAiMessage(string $message, ?int $conversationId = null): void
    {
        try {
            $result = $this->chatService->send(
                $message,
                session()->getId(),
                auth()->id(),
                $conversationId,
            );

            $this->conversationId = $result['conversationId'];
            $this->messages[] = ['role' => 'assistant', 'content' => $result['text']];

            // Refresh history list after first message sets the title
            if (auth()->id()) {
                $this->refreshConversations();
            }
        } catch (AiRateLimitException $e) {
            $this->errorMsg = $e->getMessage();
        } catch (AiProviderException $e) {
            $this->errorMsg = $e->getMessage();
        } catch (\Throwable $e) {
            Log::error('[Ptah AI] Unexpected error in AiChatWidget::processAiMessage()', [
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);
            $this->errorMsg = config('app.debug')
                ? '[' . class_basename($e) . '] ' . $e->getMessage()
                : trans('ptah::ui.ai_widget_error');
        } finally {
            $this->loading = false;
        }

        $this->dispatch('ai-message-sent');
    }

    public function loadConversation(int $id): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        try {
            $conversation = $this->chatService->loadConversation($id, $userId);
        } catch (\Throwable) {
            return;
        }

        $this->conversationId = $conversation->id;
        $this->messages = array_values(array_filter(
            $conversation->messages ?? [],
            fn ($m) => in_array($m['role'] ?? '', ['user', 'assistant'], true)
        ));
        $this->errorMsg     = '';
        $this->showHistory  = false;

        $this->dispatch('ai-message-sent');
    }

    public function newConversation(): void
    {
        // For guest users: clear the DB conversation so the AI doesn't see old history
        if (! auth()->id()) {
            $conv = $this->chatService->newConversation(session()->getId());
            $this->conversationId = $conv->id;
        } else {
            $this->conversationId = null;
        }

        $this->messages    = [];
        $this->errorMsg    = '';
        $this->userInput   = '';
        $this->showHistory = false;
    }

    public function toggleHistory(): void
    {
        $this->showHistory  = ! $this->showHistory;
        $this->historyLimit = 5;
        if ($this->showHistory && auth()->id()) {
            $this->refreshConversations();
        }
    }

    public function loadMoreHistory(): void
    {
        $this->historyLimit = min($this->historyLimit + self::HISTORY_STEP, self::HISTORY_MAX);
        if (auth()->id()) {
            $this->refreshConversations();
        }
    }

    public function toggleOpen(): void
    {
        $this->isOpen       = ! $this->isOpen;
        $this->errorMsg     = '';
        $this->showHistory  = false;
        $this->historyLimit = 5;
    }

    // â”€â”€ Render â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function render()
    {
        return view('ptah::livewire.ai.ai-chat-widget');
    }

    // â”€â”€ Private â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function refreshConversations(): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        $this->conversations = $this->chatService
            ->getUserConversations($userId, $this->historyLimit)
            ->map(fn ($c) => [
                'id'    => $c->id,
                'title' => $c->title ?: trans('ptah::ui.ai_widget_untitled'),
                'date'  => $c->updated_at?->diffForHumans() ?? '',
            ])
            ->all();
    }
}
