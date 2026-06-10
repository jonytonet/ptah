<?php

declare(strict_types=1);

namespace Ptah\Services\AI;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Ptah\Exceptions\AiProviderException;
use Ptah\Exceptions\AiRateLimitException;
use Ptah\Models\AiConversation;
use Ptah\Models\AiModelConfig;

/**
 * Core AI chat service: builds the message thread, calls the Prism provider,
 * runs the agentic tool-calling loop, and persists the conversation.
 *
 * Conversations are persisted per authenticated user (user_id).
 * Guest users fall back to session_id.
 */
class AiChatService
{
    private const MAX_TOOL_ITERATIONS = 5;

    public function __construct(
        private readonly AiProviderConfigService $configService,
        private readonly AiToolRegistry $toolRegistry,
    ) {}

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Public API
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Loads or creates the most-recent conversation for the given user/session.
     *
     * Authenticated users: latest conversation by user_id (survives session changes).
     * Guests: one conversation per session_id.
     */
    public function getOrCreateConversation(string $sessionId, ?int $userId = null): AiConversation
    {
        if ($userId) {
            $conversation = AiConversation::byUser($userId)->latest()->first();

            if ($conversation) {
                return $conversation;
            }

            return AiConversation::create([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'messages' => [],
                'tokens_used' => 0,
            ]);
        }

        return AiConversation::bySession($sessionId)->firstOrCreate(
            ['session_id' => $sessionId],
            ['messages' => [], 'tokens_used' => 0]
        );
    }

    /**
     * Finds the most recent conversation with messages for an authenticated user.
     * Returns null if the user has no conversation with messages yet.
     */
    public function findLatestConversation(int $userId): ?AiConversation
    {
        return AiConversation::byUser($userId)
            ->whereNotNull('title')
            ->latest()
            ->first();
    }

    /**
     * Lists conversations with messages for an authenticated user.
     * Returns lightweight data (no messages array) for the history panel.
     *
     * @return Collection<int, AiConversation>
     */
    public function getUserConversations(int $userId, int $limit = 20): Collection
    {
        return AiConversation::byUser($userId)
            ->whereNotNull('title')
            ->latest()
            ->limit($limit)
            ->get(['id', 'title', 'updated_at', 'tokens_used']);
    }

    /**
     * Loads a specific conversation that belongs to the given user.
     *
     * @throws ModelNotFoundException
     */
    public function loadConversation(int $conversationId, int $userId): AiConversation
    {
        return AiConversation::where('id', $conversationId)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    /**
     * Sends a user message to the AI provider and returns the assistant's response.
     *
     * @throws AiRateLimitException When the session exceeds the configured rate limit
     * @throws AiProviderException When no active provider is configured or the API call fails
     */
    public function send(
        string $message,
        string $sessionId,
        ?int $userId = null,
        ?int $conversationId = null,
    ): array {
        $ctx = $this->prepareTurn($message, $sessionId, $userId, $conversationId);

        try {
            $response = $this->buildRequest($ctx)->asText();
        } catch (\Throwable $e) {
            $this->logProviderFailure($ctx['config'], $e);
            throw new AiProviderException(
                trans('ptah::ui.ai_widget_error').' '.$e->getMessage(),
                previous: $e
            );
        } finally {
            // Always restore the shared config (Octane / long-lived workers).
            $this->restoreConfig($ctx['restoreConfig']);
        }

        return $this->persistTurn(
            $ctx,
            $message,
            $response->text ?? '',
            $response->usage->promptTokens ?? 0,
            $response->usage->completionTokens ?? 0,
        );
    }

    /**
     * Streaming variant of send(): yields the assistant's text incrementally via
     * the $onDelta callback, then persists the full conversation like send() does.
     *
     * @param  callable(string $delta, string $accumulated): void|null  $onDelta
     * @return array{text: string, conversationId: int}
     *
     * @throws AiRateLimitException|AiProviderException
     */
    public function stream(
        string $message,
        string $sessionId,
        ?int $userId = null,
        ?int $conversationId = null,
        ?callable $onDelta = null,
    ): array {
        $ctx = $this->prepareTurn($message, $sessionId, $userId, $conversationId);

        $full = '';
        $inputTokens = 0;
        $outputTokens = 0;

        try {
            foreach ($this->buildRequest($ctx)->asStream() as $event) {
                $delta = $this->extractDelta($event);
                if ($delta !== '') {
                    $full .= $delta;
                    if ($onDelta) {
                        $onDelta($delta, $full);
                    }
                }

                // Usage is delivered on terminal events (StepFinish/StreamEnd).
                $usage = is_object($event) && isset($event->usage) ? $event->usage : null;
                if ($usage) {
                    $inputTokens = $usage->promptTokens ?? $inputTokens;
                    $outputTokens = $usage->completionTokens ?? $outputTokens;
                }
            }
        } catch (\Throwable $e) {
            $this->logProviderFailure($ctx['config'], $e);
            throw new AiProviderException(
                trans('ptah::ui.ai_widget_error').' '.$e->getMessage(),
                previous: $e
            );
        } finally {
            $this->restoreConfig($ctx['restoreConfig']);
        }

        return $this->persistTurn($ctx, $message, $full, $inputTokens, $outputTokens);
    }

    /**
     * Extracts the text delta from a Prism stream item, tolerating both the real
     * event-based API (TextDeltaEvent->delta) and the testing fake (stdClass->text).
     * Non-text events (tool calls, thinking, etc.) yield an empty string.
     */
    private function extractDelta(mixed $event): string
    {
        if ($event instanceof TextDeltaEvent) {
            return $event->delta;
        }

        if (is_object($event) && isset($event->text)) {
            return (string) $event->text;
        }

        if (is_string($event)) {
            return $event;
        }

        return '';
    }

    /**
     * Runs all guards, resolves the conversation, applies provider credentials and
     * builds the Prism message list. Shared by send() and stream().
     *
     * @return array{config: AiModelConfig, conversation: AiConversation, history: array<int, array<string, mixed>>, prismMessages: array<int, mixed>, systemPrompt: string, tools: array<int, mixed>, restoreConfig: array<string, mixed>}
     *
     * @throws AiRateLimitException|AiProviderException
     */
    private function prepareTurn(string $message, string $sessionId, ?int $userId, ?int $conversationId): array
    {
        // Guests may only use the chat when explicitly allowed.
        if (! $userId && ! config('ptah.ai_agent.allow_guests', false)) {
            throw new AiProviderException(trans('ptah::ui.ai_widget_no_provider'));
        }

        // Key by user when authenticated so it can't be bypassed by dropping the
        // session cookie; fall back to session_id for guests.
        $rateKey = $userId ? "ptah:ai:user:{$userId}" : "ptah:ai:sess:{$sessionId}";
        $limit = (int) config('ptah.ai_agent.rate_limit', 30);

        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            throw new AiRateLimitException(trans('ptah::ui.ai_widget_rate_limit'));
        }
        RateLimiter::hit($rateKey, 60);

        // Optional per-user daily token budget.
        $this->assertWithinDailyTokenBudget($userId);

        $config = $this->configService->findDefault();
        if (! $config) {
            throw new AiProviderException(trans('ptah::ui.ai_widget_no_provider'));
        }

        // Resolve conversation
        if ($conversationId && $userId) {
            $conversation = $this->loadConversation($conversationId, $userId);
        } elseif ($userId) {
            // Authenticated without an explicit conversation ID → always create a new record
            $conversation = AiConversation::create([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'messages' => [],
                'tokens_used' => 0,
            ]);
        } else {
            $conversation = $this->getOrCreateConversation($sessionId);
        }

        // Set title from first message
        if (! $conversation->title) {
            $conversation->update(['title' => Str::limit($message, 60)]);
        }

        $maxHistory = (int) config('ptah.ai_agent.max_history', 20);
        $history = array_slice($conversation->messages ?? [], -$maxHistory);

        // Apply provider credentials (restored by the caller after the request)
        $restoreConfig = $this->applyConfig($config);

        // Extend execution time for local/slow AI providers
        set_time_limit(300);

        $prismMessages = $this->buildPrismMessages($history);
        $prismMessages[] = new UserMessage($message);

        $systemPrompt = $config->system_prompt
            ?: config('ptah.ai_agent.system_prompt', 'You are a helpful assistant.');

        return [
            'config' => $config,
            'conversation' => $conversation,
            'history' => $history,
            'prismMessages' => $prismMessages,
            'systemPrompt' => $systemPrompt,
            'tools' => $this->toolRegistry->getPrismTools(),
            'restoreConfig' => $restoreConfig,
        ];
    }

    /**
     * Builds the Prism text request from a prepared turn context.
     *
     * @param  array{config: AiModelConfig, prismMessages: array<int, mixed>, systemPrompt: string, tools: array<int, mixed>}  $ctx
     */
    private function buildRequest(array $ctx): PendingRequest
    {
        /** @var AiModelConfig $config */
        $config = $ctx['config'];

        $request = Prism::text()
            ->using($this->resolveProvider($config->provider), $config->model)
            ->withSystemPrompt($ctx['systemPrompt'])
            ->withMessages($ctx['prismMessages'])
            ->withMaxTokens($config->max_tokens)
            ->usingTemperature((float) $config->temperature)
            ->withMaxSteps(self::MAX_TOOL_ITERATIONS);

        if (! empty($ctx['tools'])) {
            $request = $request->withTools($ctx['tools']);
        }

        return $request;
    }

    /**
     * Persists the user + assistant turn and returns the result payload.
     *
     * @param  array{config: AiModelConfig, conversation: AiConversation, history: array<int, mixed>}  $ctx
     * @return array{text: string, conversationId: int}
     */
    private function persistTurn(array $ctx, string $message, string $finalText, int $inputTokens, int $outputTokens): array
    {
        /** @var AiConversation $conversation */
        $conversation = $ctx['conversation'];
        /** @var AiModelConfig $config */
        $config = $ctx['config'];

        $newMessages = array_merge($ctx['history'], [
            ['role' => 'user',      'content' => $message],
            ['role' => 'assistant', 'content' => $finalText],
        ]);

        $conversation->update([
            'messages' => $newMessages,
            'provider_used' => $config->provider,
            'model_used' => $config->model,
            'tokens_used' => $conversation->tokens_used + $inputTokens + $outputTokens,
        ]);

        return ['text' => $finalText, 'conversationId' => $conversation->id];
    }

    private function logProviderFailure(AiModelConfig $config, \Throwable $e): void
    {
        Log::error('[Ptah AI] Provider call failed', [
            'provider' => $config->provider,
            'model' => $config->model,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }

    /**
     * Creates a new empty conversation for the given user/session.
     * For authenticated users, creates a new record (preserves history).
     * For guests, clears the existing session conversation.
     */
    public function newConversation(string $sessionId, ?int $userId = null): AiConversation
    {
        if ($userId) {
            return AiConversation::create([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'messages' => [],
                'tokens_used' => 0,
            ]);
        }

        $conversation = $this->getOrCreateConversation($sessionId);
        $conversation->update(['messages' => [], 'tokens_used' => 0, 'title' => null]);

        return $conversation;
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Private helpers
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Applies the provider's API key and optional custom endpoint to the
     * application config so Prism can pick them up for this request.
     */
    private function applyConfig(AiModelConfig $config): array
    {
        $provider = strtolower($config->provider);
        $applied = [];

        $keyMap = [
            'openai' => 'prism.providers.openai.api_key',
            'anthropic' => 'prism.providers.anthropic.api_key',
            'gemini' => 'prism.providers.gemini.api_key',
            'groq' => 'prism.providers.groq.api_key',
            'mistral' => 'prism.providers.mistral.api_key',
        ];

        if (isset($keyMap[$provider])) {
            $applied[$keyMap[$provider]] = config($keyMap[$provider]);
            config([$keyMap[$provider] => $config->api_key]);
        }

        if ($config->api_endpoint) {
            $endpointMap = [
                'openai' => 'prism.providers.openai.base_url',
                'anthropic' => 'prism.providers.anthropic.base_url',
                'ollama' => 'prism.providers.ollama.url',
            ];

            if (isset($endpointMap[$provider])) {
                $applied[$endpointMap[$provider]] = config($endpointMap[$provider]);
                config([$endpointMap[$provider] => $config->api_endpoint]);
            }
        } elseif ($provider === 'ollama') {
            $applied['prism.providers.ollama.url'] = config('prism.providers.ollama.url');
            config(['prism.providers.ollama.url' => env('OLLAMA_URL', 'http://localhost:11434/api')]);
        }

        // Original values, so the caller can restore them after the request.
        // Without this, on long-lived workers (Octane) the API key would persist
        // in the shared config between requests.
        return $applied;
    }

    /**
     * Restores config keys to their pre-request values (Octane safety).
     *
     * @param  array<string, mixed>  $original
     */
    private function restoreConfig(array $original): void
    {
        foreach ($original as $key => $value) {
            config([$key => $value]);
        }
    }

    /**
     * Enforces the optional per-user daily token budget.
     * `ptah.ai_agent.daily_token_limit` = 0 (default) disables the cap.
     * Guests are not subject to the budget (they are already rate-limited).
     *
     * @throws AiRateLimitException
     */
    private function assertWithinDailyTokenBudget(?int $userId): void
    {
        $limit = (int) config('ptah.ai_agent.daily_token_limit', 0);

        if ($limit <= 0 || ! $userId) {
            return;
        }

        $usedToday = (int) AiConversation::byUser($userId)
            ->whereDate('updated_at', now()->toDateString())
            ->sum('tokens_used');

        if ($usedToday >= $limit) {
            throw new AiRateLimitException(trans('ptah::ui.ai_widget_rate_limit'));
        }
    }

    /** Maps our provider string to Prism's Provider enum. */
    private function resolveProvider(string $provider): Provider
    {
        return match (strtolower($provider)) {
            'anthropic' => Provider::Anthropic,
            'gemini' => Provider::Gemini,
            'ollama' => Provider::Ollama,
            'groq' => Provider::Groq,
            'mistral' => Provider::Mistral,
            default => Provider::OpenAI,
        };
    }

    /**
     * Converts our simple DB message format to Prism ValueObject messages.
     *
     * @param  array<array{role: string, content: string}>  $history
     * @return array<UserMessage|AssistantMessage>
     */
    private function buildPrismMessages(array $history): array
    {
        return array_map(
            fn (array $msg) => match ($msg['role']) {
                'assistant' => new AssistantMessage($msg['content'] ?? ''),
                default => new UserMessage($msg['content'] ?? ''),
            },
            $history
        );
    }
}
