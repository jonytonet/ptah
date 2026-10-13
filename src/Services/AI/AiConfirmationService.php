<?php

declare(strict_types=1);

namespace Ptah\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Ptah\Contracts\AiConfirmableTool;
use Ptah\Contracts\AiToolContextAware;
use Ptah\Support\AI\AiPreview;
use Ptah\Support\AI\AiToolContext;
use Ptah\Support\ExportOwner;
use Throwable;

/**
 * The "preview → Confirm" half of confirmable tools (1.45.0).
 *
 * stage() keeps a preview's payload on the server under a random token, bound
 * to the user (id + guard), the company and — once the turn is saved — the
 * conversation. commit() takes the token ONCE (Cache::pull), re-checks all of
 * that plus the tool's permission, and runs the tool's commit(). Nothing that
 * reaches the browser or the model can write: they only ever see the token
 * and the rows meant for display.
 */
final class AiConfirmationService
{
    private const PREFIX = 'ptah:ai:confirm:';

    /** Staged during the current request, picked up by the turn that saves them. @var list<array<string, mixed>> */
    private static array $staged = [];

    /**
     * @param  class-string<AiConfirmableTool>  $class
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed> what the model gets instead of a write
     */
    public function stage(string $class, AiConfirmableTool $tool, array $arguments, AiToolContext $context): array
    {
        $preview = $tool->preview($arguments);

        if ($preview->isEmpty()) {
            return ['status' => 'nothing_to_confirm', 'summary' => $preview->summary];
        }

        $token = Str::random(40);
        $maxRows = max(1, (int) config('ptah.ai_agent.max_preview_rows', 50));
        $card = [
            'token' => $token,
            'tool' => $tool->name(),
            'summary' => $preview->summary,
            'columns' => $preview->columns !== [] ? $preview->columns : self::columnsOf($preview),
            'rows' => array_slice($preview->rows, 0, $maxRows),
            'total_rows' => count($preview->rows),
            'status' => 'pending',
        ];

        Cache::put(self::PREFIX.$token, [
            'class' => $class,
            'payload' => $preview->payload,
            'user' => $context->userId(),
            'guard' => ExportOwner::guard(),
            'company' => $context->companyId,
            'conversation' => null,
            'card' => $card,
        ], now()->addMinutes($this->ttlMinutes()));

        self::$staged[] = $card;

        return [
            'status' => 'awaiting_confirmation',
            'summary' => $preview->summary,
            'rows' => count($preview->rows),
            'instruction' => 'Nothing was written. The user sees this preview as a card with Confirm and Cancel buttons and must click Confirm. Tell them so in one or two sentences; do not call this tool again and do not ask them to type "yes".',
        ];
    }

    /**
     * The cards staged in this request — for the turn being saved. Emptied.
     *
     * @return list<array<string, mixed>>
     */
    public function takeStaged(): array
    {
        $staged = self::$staged;
        self::$staged = [];

        return $staged;
    }

    /** Binds staged tokens to the conversation they were shown in. */
    public function bindToConversation(array $cards, int $conversationId): void
    {
        foreach ($cards as $card) {
            $key = self::PREFIX.($card['token'] ?? '');
            $entry = Cache::get($key);
            if (is_array($entry)) {
                $entry['conversation'] = $conversationId;
                Cache::put($key, $entry, now()->addMinutes($this->ttlMinutes()));
            }
        }
    }

    /**
     * Runs the confirmed write. Exactly once per token.
     *
     * @return array{status: string, message: string, tool: string, result: array<string, mixed>}
     */
    public function commit(string $token, int $conversationId): array
    {
        $expired = ['status' => 'expired', 'message' => trans('ptah::ui.ai_confirm_expired'), 'tool' => '', 'result' => []];
        $entry = Cache::get(self::PREFIX.$token);

        if (! is_array($entry)) {
            return $expired;
        }

        $tool = (string) ($entry['card']['tool'] ?? '');
        $context = AiToolContext::current();

        // O dono e conferido ANTES de consumir: uma chamada de outra conversa
        // (ou de outra pessoa) nao pode gastar o token do dono.
        if (! $this->sameOwner($entry, $context, $conversationId)) {
            return ['status' => 'denied', 'message' => trans('ptah::ui.ai_confirm_denied'), 'tool' => $tool, 'result' => []];
        }

        // pull: so quem tira o token do cache grava — dois cliques (ou duas
        // abas) nao gravam duas vezes.
        if (! is_array(Cache::pull(self::PREFIX.$token))) {
            return $expired;
        }

        $class = (string) $entry['class'];
        if (! is_subclass_of($class, AiConfirmableTool::class) || ! AiToolRegistry::allows($class, $context)) {
            return ['status' => 'denied', 'message' => trans('ptah::ui.ai_tool_forbidden', ['tool' => $tool]), 'tool' => $tool, 'result' => []];
        }

        try {
            /** @var AiConfirmableTool $instance */
            $instance = app($class);
            if ($instance instanceof AiToolContextAware) {
                $instance->withContext($context);
            }
            $result = $instance->commit((array) $entry['payload']);
        } catch (Throwable $e) {
            Log::error('[Ptah AI] Confirmed action failed', ['tool' => $tool, 'exception' => $e::class, 'message' => $e->getMessage()]);

            return ['status' => 'failed', 'message' => trans('ptah::ui.ai_tool_failed', ['tool' => $tool]), 'tool' => $tool, 'result' => []];
        }

        $message = is_string($result['message'] ?? null) && $result['message'] !== ''
            ? $result['message']
            : trans('ptah::ui.ai_confirm_done');

        return ['status' => 'confirmed', 'message' => $message, 'tool' => $tool, 'result' => $result];
    }

    /** Drops a pending preview. Owner checks as commit(). */
    public function cancel(string $token, int $conversationId): bool
    {
        $entry = Cache::get(self::PREFIX.$token);

        if (! is_array($entry) || ! $this->sameOwner($entry, AiToolContext::current(), $conversationId)) {
            return false;
        }

        Cache::forget(self::PREFIX.$token);

        return true;
    }

    private function sameOwner(array $entry, AiToolContext $context, int $conversationId): bool
    {
        return ExportOwner::owns($entry['user'] ?? null, $entry['guard'] ?? null)
            && (string) ($entry['user'] ?? '') === (string) $context->userId()
            && (int) ($entry['company'] ?? 0) === $context->companyId
            && (int) ($entry['conversation'] ?? 0) === $conversationId;
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('ptah.ai_agent.confirmation_ttl', 30));
    }

    /** @return array<string, string> */
    private static function columnsOf(AiPreview $preview): array
    {
        $keys = array_keys($preview->rows[0] ?? []);

        return array_combine(array_map('strval', $keys), array_map('strval', $keys)) ?: [];
    }
}
