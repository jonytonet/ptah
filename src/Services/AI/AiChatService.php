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
use Prism\Prism\PrismManager;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Ptah\Exceptions\AiProviderException;
use Ptah\Exceptions\AiRateLimitException;
use Ptah\Models\AiConversation;
use Ptah\Models\AiModelConfig;
use Ptah\Support\AI\ProviderFailure;

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

    // ─────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────

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
        ?int $configId = null,
    ): array {
        $ctx = $this->prepareTurn($message, $sessionId, $userId, $conversationId, $configId);

        try {
            $response = $this->buildRequest($ctx)->asText();
        } catch (\Throwable $e) {
            $this->logProviderFailure($ctx['config'], $e);

            throw $this->providerException($e);
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
        ?int $configId = null,
    ): array {
        $ctx = $this->prepareTurn($message, $sessionId, $userId, $conversationId, $configId);

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

            throw $this->providerException($e);
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
    private function prepareTurn(string $message, string $sessionId, ?int $userId, ?int $conversationId, ?int $configId = null): array
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

        // An explicit choice from the widget's provider picker, falling back to
        // the default. The id is client-supplied, so the service validates that
        // it names an ACTIVE config — see AiProviderConfigService::resolveForTurn().
        $config = $this->configService->resolveForTurn($configId);
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

        // Extend execution time for local/slow AI providers — but only where
        // there is a limit to extend.
        //
        // Unguarded, this call was actively harmful in CLI: `php artisan`,
        // queue workers and PHPUnit all run with max_execution_time = 0
        // (unlimited), so set_time_limit(300) IMPOSED a 300s ceiling on the
        // whole process where none existed. That is what killed test runs
        // mid-suite with "Maximum execution time of 300 seconds exceeded"
        // pointing at unrelated files (a docblock parser, a query grammar) —
        // the timer started here and expired somewhere else entirely.
        //
        // It also must never LOWER an existing limit: a host that already
        // grants 600s to this endpoint should keep it.
        $currentLimit = (int) ini_get('max_execution_time');

        if (PHP_SAPI !== 'cli' && $currentLimit > 0 && $currentLimit < 300) {
            set_time_limit(300);
        }

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

    /**
     * The exception the caller (widget, controller) receives.
     *
     * The provider's raw body goes to the log unconditionally, but it is only
     * appended to the MESSAGE while `APP_DEBUG` is on: this message reaches the
     * chat widget, and a body can carry internal detail an end user has no
     * business seeing. A developer chasing a provider rejection gets it inline;
     * everyone else gets the translated sentence.
     */
    private function providerException(\Throwable $e): AiProviderException
    {
        $failure = ProviderFailure::from($e);

        // The actionable sentence, not the provider's raw string. `OpenAI Error
        // [422]: Unknown error` told a user nothing and an administrator nothing;
        // "the provider rejected the request" plus a log line naming the reason
        // and carrying the body tells both of them where to look.
        $message = $failure->message();

        // The technical detail is appended for a developer only: this message is
        // rendered in the chat widget, and a response body can carry internal
        // detail an end user has no business seeing. The log gets it either way.
        if (config('app.debug')) {
            $detail = trim($e->getMessage().' '.($failure->body ?? ''));

            if ($detail !== '') {
                $message .= ' — '.$detail;
            }
        }

        return new AiProviderException($message, previous: $e);
    }

    private function logProviderFailure(AiModelConfig $config, \Throwable $e): void
    {
        Log::error('[Ptah AI] Provider call failed', array_merge([
            'provider' => $config->provider,
            'model' => $config->model,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ], ProviderFailure::from($e)->logContext()));
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

    // ─────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────

    /**
     * Applies the provider's API key and optional custom endpoint to the
     * application config so Prism can pick them up for this request.
     */
    private function applyConfig(AiModelConfig $config): array
    {
        // Through the alias map, so `openai_compatible` writes into the Prism
        // provider that actually carries it — otherwise there is no
        // `prism.providers.openai_compatible` block and neither the key nor the
        // endpoint would land anywhere. See prismProviderSlug().
        $provider = $this->prismProviderSlug($config->provider);
        $applied = [];

        // Both maps this method used to carry are gone, and that is the fix.
        //
        // Every provider block in Prism's own config uses the SAME two keys —
        // `api_key` and `url` (see vendor/prism-php/prism/config/prism.php:
        // openai, anthropic, ollama, mistral, groq, xai, gemini, deepseek,
        // openrouter, perplexity, z … all `url`). The old endpoint map wrote
        // `prism.providers.openai.base_url` and `…anthropic.base_url`, keys
        // NOTHING reads, so the `api_endpoint` a user typed into /ptah-ai/models
        // was silently discarded for those two providers and Prism kept calling
        // api.openai.com. Only `ollama` worked, because it happened to be
        // spelled `.url`.
        //
        // Deriving the paths from the provider name instead of listing them
        // also means a provider added to Prism upstream works here with no
        // change — which is how the key map had drifted behind the roster.
        if (config()->has("prism.providers.{$provider}")) {
            // Only overwrite when this config actually carries a key: writing
            // null would blank out the host's env credential for providers that
            // legitimately have none saved (Ollama), and the env fallback is the
            // correct value in that case.
            if ($config->api_key !== null && $config->api_key !== '') {
                $keyPath = "prism.providers.{$provider}.api_key";
                $applied[$keyPath] = config($keyPath);
                config([$keyPath => $config->api_key]);
            }

            if ($config->api_endpoint) {
                $urlPath = "prism.providers.{$provider}.url";
                $applied[$urlPath] = config($urlPath);
                config([$urlPath => $config->api_endpoint]);
            }
        }

        // Ollama with no endpoint saved: fall back to the default, WITHOUT the
        // `/api` suffix this line used to add. Prism's Ollama handlers post to
        // the relative path `api/chat` on top of the base url, so
        // `http://localhost:11434/api` produced `…/api/api/chat` and every
        // default Ollama install failed until the host set OLLAMA_URL by hand.
        // This matches Prism's own default for the same key.
        if (! $config->api_endpoint && $provider === 'ollama') {
            $applied['prism.providers.ollama.url'] = config('prism.providers.ollama.url');
            config(['prism.providers.ollama.url' => env('OLLAMA_URL', 'http://localhost:11434')]);
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
        $slug = $this->prismProviderSlug($provider);

        // `Provider` is a string-backed enum whose values are exactly the
        // provider slugs stored in ai_model_configs, so tryFrom() resolves the
        // whole roster — including the ones the old match() had fallen behind
        // (xai, deepseek, openrouter, perplexity, z, voyageai) and any provider
        // Prism adds later.
        //
        // Falling through to OpenAI is not a harmless default: the OpenAI
        // handler posts to `responses` (the Responses API), which
        // OpenAI-compatible providers generally do not implement. x.ai answered
        // that with a 422 and no explanation, which is what made Grok look
        // unsupportable — while Prism has had a dedicated Provider::XAI, posting
        // to `chat/completions`, all along. OpenAI remains the fallback only for
        // a slug Prism does not know at all.
        return Provider::tryFrom($slug) ?? Provider::OpenAI;
    }

    /**
     * ptah provider slug => the Prism provider slug that carries it.
     *
     * Only one entry, and it exists because Prism has no generic
     * OpenAI-compatible provider. Together, Fireworks, Cerebras, SambaNova,
     * Azure OpenAI, vLLM, LM Studio, llama.cpp and LocalAI all speak plain
     * `chat/completions`, and Prism's `openai` provider posts to `responses`
     * (the Responses API), which none of them implement — so pointing `openai`
     * at a custom endpoint fails with the same unexplained 422 that made Grok
     * look unsupportable.
     *
     * `xai` is the vehicle because its handler builds the vanilla payload —
     * model, messages, max_tokens, temperature, top_p, tools, tool_choice — with
     * no provider-specific field or header, and posts to `chat/completions`. The
     * `api_endpoint` is required for this slug (enforced in AiModelConfigList),
     * since there is no sensible default for "somebody else's server".
     *
     * The cost, stated plainly: a config using this slug writes its key and url
     * into `prism.providers.xai.*` for the duration of the turn (restored
     * afterwards by restoreConfig(), like every other provider). A host holding
     * both an `openai_compatible` and an `xai` config is unaffected because only
     * one config is active per turn. The clean fix is upstream — a
     * `Provider::OpenAICompatible`, or a flag on the OpenAI provider to use
     * `chat/completions` — and this alias should be deleted when it lands.
     */
    private const PROVIDER_ALIASES = [
        'openai_compatible' => 'xai',
    ];

    private function prismProviderSlug(string $provider): string
    {
        $slug = strtolower($provider);

        return self::PROVIDER_ALIASES[$slug] ?? $slug;
    }

    /**
     * Whether the provider behind this config can stream.
     *
     * `ptah.ai_agent.stream` defaults to true, and Prism's base provider throws
     * `unsupportedProviderAction` from `stream()` — so a provider that ships no
     * Stream handler breaks the chat outright under the package's own default.
     * Today `z` is the only text-capable provider in that position, but the
     * check is on the class rather than a hardcoded list so a provider that
     * loses (or gains) streaming in a Prism release is handled without a change
     * here.
     *
     * Detection is a reflection on the resolved provider instance: if `stream()`
     * is still the base class's, it can only throw.
     */
    public function supportsStreaming(?int $configId = null): bool
    {
        $config = $this->configService->resolveForTurn($configId);

        if (! $config) {
            return false;
        }

        try {
            $instance = app(PrismManager::class)
                ->resolve($this->resolveProvider($config->provider));

            $declaring = (new \ReflectionMethod($instance, 'stream'))
                ->getDeclaringClass()
                ->getName();

            return $declaring !== \Prism\Prism\Providers\Provider::class;
        } catch (\Throwable) {
            // Cannot tell — assume no, so the caller takes the non-streaming
            // path that every text provider supports.
            return false;
        }
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
