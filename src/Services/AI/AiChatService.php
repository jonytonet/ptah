<?php

declare(strict_types=1);

namespace Ptah\Services\AI;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
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
        private readonly AiToolRegistry          $toolRegistry,
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
                'user_id'     => $userId,
                'session_id'  => $sessionId,
                'messages'    => [],
                'tokens_used' => 0,
            ]);
        }

        return AiConversation::bySession($sessionId)->firstOrCreate(
            ['session_id' => $sessionId],
            ['messages'   => [], 'tokens_used' => 0]
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
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
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
     * @throws AiRateLimitException  When the session exceeds the configured rate limit
     * @throws AiProviderException   When no active provider is configured or the API call fails
     */
    public function send(
        string   $message,
        string   $sessionId,
        ?int     $userId         = null,
        ?int     $conversationId = null,
    ): array {
        // â”€â”€ Rate limiting â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $rateKey = "ptah:ai:{$sessionId}";
        $limit   = (int) config('ptah.ai_agent.rate_limit', 30);

        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            throw new AiRateLimitException(trans('ptah::ui.ai_widget_rate_limit'));
        }
        RateLimiter::hit($rateKey, 60);

        // â”€â”€ Provider config â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $config = $this->configService->findDefault();
        if (!$config) {
            throw new AiProviderException(trans('ptah::ui.ai_widget_no_provider'));
        }

        // â”€â”€ Resolve conversation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        if ($conversationId && $userId) {
            $conversation = $this->loadConversation($conversationId, $userId);
        } elseif ($userId) {
            // Authenticated without an explicit conversation ID → always create a new record
            $conversation = AiConversation::create([
                'user_id'     => $userId,
                'session_id'  => $sessionId,
                'messages'    => [],
                'tokens_used' => 0,
            ]);
        } else {
            $conversation = $this->getOrCreateConversation($sessionId);
        }

        // â”€â”€ Set title from first message â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        if (!$conversation->title) {
            $conversation->update(['title' => Str::limit($message, 60)]);
        }

        $maxHistory = (int) config('ptah.ai_agent.max_history', 20);
        $history    = array_slice($conversation->messages ?? [], -$maxHistory);

        // â”€â”€ Apply provider credentials â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $this->applyConfig($config);

        // â”€â”€ Extend execution time for local/slow AI providers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        set_time_limit(300);

        // â”€â”€ Build message array â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $prismMessages   = $this->buildPrismMessages($history);
        $prismMessages[] = new UserMessage($message);

        $systemPrompt = $config->system_prompt
            ?: config('ptah.ai_agent.system_prompt', 'You are a helpful assistant.');

        $tools = $this->toolRegistry->getPrismTools();

        // â”€â”€ Call Prism â€” Prism manages the agentic loop internally â”€â”€â”€â”€â”€â”€â”€â”€
        try {
            $request = Prism::text()
                ->using($this->resolveProvider($config->provider), $config->model)
                ->withSystemPrompt($systemPrompt)
                ->withMessages($prismMessages)
                ->withMaxTokens($config->max_tokens)
                ->withMaxSteps(self::MAX_TOOL_ITERATIONS);

            if (!empty($tools)) {
                $request = $request->withTools($tools);
            }

            $response = $request->generate();
        } catch (\Throwable $e) {
            Log::error('[Ptah AI] Provider call failed', [
                'provider'  => $config->provider,
                'model'     => $config->model,
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);
            throw new AiProviderException(
                trans('ptah::ui.ai_widget_error') . ' ' . $e->getMessage(),
                previous: $e
            );
        }

        $finalText         = $response->text ?? '';
        $totalInputTokens  = $response->usage->inputTokens ?? 0;
        $totalOutputTokens = $response->usage->outputTokens ?? 0;

        // â”€â”€ Persist conversation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $newMessages = array_merge($history, [
            ['role' => 'user',      'content' => $message],
            ['role' => 'assistant', 'content' => $finalText],
        ]);

        $conversation->update([
            'messages'      => $newMessages,
            'provider_used' => $config->provider,
            'model_used'    => $config->model,
            'tokens_used'   => $conversation->tokens_used + $totalInputTokens + $totalOutputTokens,
        ]);

        return ['text' => $finalText, 'conversationId' => $conversation->id];
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
                'user_id'     => $userId,
                'session_id'  => $sessionId,
                'messages'    => [],
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
    private function applyConfig(AiModelConfig $config): void
    {
        $provider = strtolower($config->provider);

        $keyMap = [
            'openai'    => 'prism.providers.openai.api_key',
            'anthropic' => 'prism.providers.anthropic.api_key',
            'gemini'    => 'prism.providers.gemini.api_key',
            'groq'      => 'prism.providers.groq.api_key',
            'mistral'   => 'prism.providers.mistral.api_key',
        ];

        if (isset($keyMap[$provider])) {
            config([$keyMap[$provider] => $config->api_key]);
        }

        if ($config->api_endpoint) {
            $endpointMap = [
                'openai'    => 'prism.providers.openai.base_url',
                'anthropic' => 'prism.providers.anthropic.base_url',
                'ollama'    => 'prism.providers.ollama.url',
            ];

            if (isset($endpointMap[$provider])) {
                config([$endpointMap[$provider] => $config->api_endpoint]);
            }
        } elseif ($provider === 'ollama') {
            config(['prism.providers.ollama.url' => env('OLLAMA_URL', 'http://localhost:11434/api')]);
        }
    }

    /** Maps our provider string to Prism's Provider enum. */
    private function resolveProvider(string $provider): Provider
    {
        return match (strtolower($provider)) {
            'anthropic' => Provider::Anthropic,
            'gemini'    => Provider::Gemini,
            'ollama'    => Provider::Ollama,
            'groq'      => Provider::Groq,
            'mistral'   => Provider::Mistral,
            default     => Provider::OpenAI,
        };
    }

    /**
     * Converts our simple DB message format to Prism ValueObject messages.
     *
     * @param  array<array{role: string, content: string}> $history
     * @return array<UserMessage|AssistantMessage>
     */
    private function buildPrismMessages(array $history): array
    {
        return array_map(
            fn (array $msg) => match ($msg['role']) {
                'assistant' => new AssistantMessage($msg['content'] ?? ''),
                default     => new UserMessage($msg['content'] ?? ''),
            },
            $history
        );
    }
}

