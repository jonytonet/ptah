<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers\Admin;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\PaginationState;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Ptah\Http\Headless\HeadlessAiChatWidget;
use Ptah\Http\Headless\HeadlessAiModelConfigList;
use Ptah\Livewire\AI\AiModelConfigList;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiAttachmentService;
use Ptah\Support\AI\ChatMarkdown;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The AI agent module for the React and Vue stacks (1.59.0): the provider
 * configuration screen (AiModelConfigList, /ptah-ai/models) and the floating
 * chat (AiChatWidget, mounted by the dashboard layout).
 *
 * Each action runs the Livewire component as a plain object
 * (Ptah\Http\Headless\*): the `ai.config` lock (boot() + every action), the
 * validation rules, the encrypted key, the rate limit, the token budget, the
 * provider fallback, the conversation ownership, the attachment checks and
 * the confirmation cards are the components' and services' own code. This
 * controller only carries the request in and a whitelisted answer out.
 *
 * Stateless: the chat widget keeps the open conversation between two clicks;
 * here the client sends its id (`conversation_id`) and the history is read
 * from where the engine persists it (ptah_ai_conversations, owner-checked by
 * AiChatService::loadConversation()).
 */
class AiApiController extends Controller
{
    /** What the configuration list may be sorted by: the Blade view sorts by name only. */
    private const SORTS = ['name'];

    // ── Provider configurations ─────────────────────────────────────────────

    public function models(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            // `orderBy()` takes the property as a column name.
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => 'nullable|integer|min:1',
        ]);

        $page = HeadlessAiModelConfigList::headless();
        $page->search = $this->text($request, 'search');
        $page->sort = $this->text($request, 'sort') ?: 'name';
        $page->direction = $this->text($request, 'direction') ?: 'asc';

        $rows = $this->paginate(fn () => $page->rows(), max(1, $request->integer('page', 1)));

        return response()->json([
            'data' => array_map(fn (AiModelConfig $config) => $this->modelRow($config), $rows->items()),
            'meta' => [
                'page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'state' => ['search' => $page->search, 'sort' => $page->sort, 'direction' => $page->direction],
            'providers' => $this->providers(),
        ]);
    }

    public function model(int $model): JsonResponse
    {
        $page = HeadlessAiModelConfigList::headless();
        // edit() never pre-fills the key: the form answers it empty, as the screen does.
        $page->run('edit', ['id' => $model]);

        return response()->json(['id' => $model, 'form' => $this->modelForm($page)]);
    }

    public function storeModel(Request $request): JsonResponse
    {
        $page = HeadlessAiModelConfigList::headless();
        $page->run('create');

        return $this->saveModel($page, $request);
    }

    public function updateModel(Request $request, int $model): JsonResponse
    {
        $page = HeadlessAiModelConfigList::headless();
        // The record being edited, as the screen keeps it between two clicks.
        $page->run('edit', ['id' => $model]);

        return $this->saveModel($page, $request);
    }

    public function destroyModel(int $model): JsonResponse
    {
        $page = HeadlessAiModelConfigList::headless();
        $page->run('confirmDelete', ['id' => $model]);
        $page->run('delete');

        return $this->modelOutcome($page, $page->errorMsg === '');
    }

    public function defaultModel(int $model): JsonResponse
    {
        $page = HeadlessAiModelConfigList::headless();
        $page->run('setDefault', ['id' => $model]);

        return $this->modelOutcome($page, $page->errorMsg === '');
    }

    // ── Chat ────────────────────────────────────────────────────────────────

    /**
     * What the widget shows when it opens: the latest conversation, the
     * provider picker, the `/commands`, the attachment rules. `config_id`
     * names the provider picked, which narrows the attachment formats (the
     * Livewire picker re-renders the widget for the same reason).
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate(['config_id' => 'nullable|integer|min:1']);

        $widget = HeadlessAiChatWidget::headless();
        if (! $widget->available) {
            return response()->json(['available' => false]);
        }
        if ($request->filled('config_id')) {
            // Client-writable on Livewire too; resolveForTurn() falls back to
            // the default for an id that is not an active config.
            $widget->selectedConfigId = $request->integer('config_id');
        }

        return response()->json($this->chatState($widget));
    }

    /** The history panel: the user's last `limit` conversations (5 more per "load more", up to 100). */
    public function conversations(Request $request): JsonResponse
    {
        $request->validate(['limit' => 'nullable|integer|min:1|max:1000']);

        $widget = $this->availableWidget();
        $limit = $request->integer('limit', 5);

        if ($limit <= 5) {
            // Opening the panel: toggleHistory() starts the list at 5.
            $widget->run('toggleHistory');
        } else {
            // "Load more" from the step below, so the component's own ceiling applies.
            $widget->historyLimit = $limit - 5;
            $widget->run('loadMoreHistory');
        }

        return response()->json([
            'conversations' => $this->conversationList($widget),
            'limit' => $widget->historyLimit,
        ]);
    }

    /** Opens one conversation from the history panel. Another user's conversation is not found. */
    public function conversation(int $conversation): JsonResponse
    {
        $widget = $this->availableWidget();
        $this->openConversation($widget, $conversation);

        return response()->json([
            'conversation_id' => $widget->conversationId,
            'messages' => $this->messageList($widget->messages),
        ]);
    }

    /** "New conversation": the next message starts a new record (a guest's one is emptied). */
    public function newConversation(): JsonResponse
    {
        $widget = $this->availableWidget();
        $widget->run('newConversation');

        return response()->json([
            'conversation_id' => $widget->conversationId,
            'messages' => [],
        ]);
    }

    /**
     * Sends a message and answers the assistant's reply.
     *
     * With `Accept: text/event-stream` (or `stream=1`) the answer is a server-
     * sent event stream: `delta` events with the answer so far as HTML (the
     * `wire:stream` frames of the Livewire widget) and one `done` event with
     * the same body the JSON answer has. A request refused before the turn
     * starts (validation, an attachment, a conversation that is not the
     * user's) is answered as plain JSON in both modes.
     */
    public function send(Request $request): JsonResponse|StreamedResponse
    {
        $request->validate([
            'message' => 'nullable|string',
            'conversation_id' => 'nullable|integer|min:1',
            'config_id' => 'nullable|integer|min:1',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file',
        ]);

        $widget = $this->availableWidget();
        $this->openConversation($widget, $request->filled('conversation_id') ? $request->integer('conversation_id') : null);
        if ($request->filled('config_id')) {
            $widget->selectedConfigId = $request->integer('config_id');
        }

        // One file at a time through the widget's own upload hook: the count,
        // the extension (narrowed by the provider) and the size are its checks.
        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $widget->incoming = $file;
            $widget->run('updatedIncoming');

            if ($widget->errorMsg !== '') {
                return $this->refused($widget->errorMsg);
            }
        }

        $message = $this->text($request, 'message');
        $widget->run('send', ['message' => $message]);

        // send() returns without a word for an empty message, and with
        // errorMsg for one over the size limit. It accepted the turn when it
        // switched `loading` on — then, on Livewire, processAiMessage runs in
        // the next request; here, right away.
        if (! $widget->loading) {
            return $this->refused($widget->errorMsg !== ''
                ? $widget->errorMsg
                : trans('validation.required', ['attribute' => 'message']));
        }

        $turn = fn () => $widget->run('processAiMessage', [
            'message' => trim($message),
            'conversationId' => $widget->conversationId,
        ]);

        if (! $this->wantsStream($request)) {
            $turn();

            return $this->turnAnswer($widget);
        }

        return response()->stream(function () use ($widget, $turn): void {
            $widget->streamTo(fn (string $html) => $this->event('delta', ['html' => $html]));
            $turn();
            $widget->streamTo(null);

            $answer = $this->turnAnswer($widget);
            $this->event('done', (array) $answer->getData(true) + ['status' => $answer->getStatusCode()]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** "Confirm" / "Cancel" on a preview card: the write runs once, without the model. */
    public function confirmation(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer|min:1',
            'token' => 'required|string|max:128',
            'confirm' => 'required|boolean',
        ]);

        $widget = $this->availableWidget();
        $this->openConversation($widget, $request->integer('conversation_id'));
        $widget->run($request->boolean('confirm') ? 'confirmAction' : 'cancelAction', ['token' => $this->text($request, 'token')]);

        if ($widget->errorMsg !== '') {
            return $this->refused($widget->errorMsg);
        }

        return response()->json([
            'ok' => true,
            'error' => null,
            'conversation_id' => $widget->conversationId,
            'messages' => $this->messageList($widget->messages),
        ]);
    }

    // ── Helpers: configurations ─────────────────────────────────────────────

    private function saveModel(HeadlessAiModelConfigList $page, Request $request): JsonResponse
    {
        // The two typed properties cannot hold a wrong type: refused here with
        // the field named, as the component's own rules would.
        $request->validate([
            'max_tokens' => 'sometimes|integer',
            'temperature' => 'sometimes|nullable|numeric',
        ]);

        foreach (['name', 'provider', 'model', 'api_key', 'api_endpoint', 'temperature', 'system_prompt', 'notes'] as $field) {
            if ($request->has($field)) {
                $page->{$field} = $this->text($request, $field);
            }
        }
        if ($request->has('max_tokens')) {
            $page->max_tokens = $request->integer('max_tokens');
        }
        foreach (['is_active', 'is_default'] as $field) {
            if ($request->has($field)) {
                $page->{$field} = $request->boolean($field);
            }
        }

        // validate() inside save() throws a 422 with the field named; the key
        // is encrypted by the model's cast, through AiProviderConfigService.
        $page->run('save');

        return $this->modelOutcome($page, ! $page->showModal && $page->errorMsg === '');
    }

    private function modelOutcome(HeadlessAiModelConfigList $page, bool $ok): JsonResponse
    {
        return response()->json([
            'ok' => $ok,
            'error' => $ok ? null : ($page->errorMsg !== '' ? $page->errorMsg : null),
            'messages' => $ok && $page->successMsg !== '' ? [['text' => $page->successMsg, 'level' => 'success']] : [],
        ], $ok ? 200 : 422);
    }

    /**
     * A row of the list. Whitelisted: never `api_key` (not even masked — the
     * Livewire table shows nothing of it).
     *
     * @return array<string, mixed>
     */
    private function modelRow(AiModelConfig $config): array
    {
        return [
            'id' => (int) $config->id,
            'name' => (string) $config->name,
            'provider' => (string) $config->provider,
            'provider_label' => AiModelConfigList::PROVIDERS[$config->provider] ?? (string) $config->provider,
            'model' => (string) $config->model,
            'notes' => $config->notes,
            'is_active' => (bool) $config->is_active,
            'is_default' => (bool) $config->is_default,
        ];
    }

    /** @return array<string, mixed> */
    private function modelForm(HeadlessAiModelConfigList $page): array
    {
        return [
            'name' => $page->name,
            // Always '' after edit(): a blank key on save keeps the stored one.
            'api_key' => $page->api_key,
            'provider' => $page->provider,
            'model' => $page->model,
            'api_endpoint' => $page->api_endpoint,
            'max_tokens' => $page->max_tokens,
            'temperature' => $page->temperature,
            'system_prompt' => $page->system_prompt,
            'notes' => $page->notes,
            'is_active' => $page->is_active,
            'is_default' => $page->is_default,
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function providers(): array
    {
        $out = [];
        foreach (AiModelConfigList::PROVIDERS as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }

    /**
     * One page of the screen's `rows()` without Livewire's page state.
     *
     * @param  Closure(): LengthAwarePaginator  $rows
     */
    private function paginate(Closure $rows, int $page): LengthAwarePaginator
    {
        // By reference: the retry below moves the page.
        Paginator::currentPageResolver(function () use (&$page) {
            return $page;
        });

        try {
            $list = $rows();

            // A page past the last one (its last row was deleted) shows the last page.
            if ($list->isEmpty() && $page > 1 && $list->lastPage() < $page) {
                $page = $list->lastPage();
                $list = $rows();
            }
        } finally {
            PaginationState::resolveUsing(app());
        }

        return $list;
    }

    // ── Helpers: chat ───────────────────────────────────────────────────────

    /**
     * The widget, mounted. One that would not render (no active provider, a
     * guest where guests are not allowed) refuses every action.
     */
    private function availableWidget(): HeadlessAiChatWidget
    {
        $widget = HeadlessAiChatWidget::headless();
        abort_unless($widget->available, 404, trans('ptah::ui.ai_widget_no_provider'));

        return $widget;
    }

    /**
     * Puts the widget on the conversation the client has open — what the
     * Livewire widget keeps in `$conversationId` between two clicks. Null is
     * "a new conversation", as after the "new conversation" button.
     */
    private function openConversation(HeadlessAiChatWidget $widget, ?int $id): void
    {
        if ($id === null) {
            $widget->conversationId = null;
            $widget->messages = [];

            return;
        }

        $widget->run('loadConversation', ['id' => $id]);

        // loadConversation() leaves the widget as it was when the conversation
        // is not this user's (or a guest asks): refused, never the latest one
        // in its place.
        abort_unless($widget->conversationId === $id, 404);
    }

    /** @return array<string, mixed> */
    private function chatState(HeadlessAiChatWidget $widget): array
    {
        // The attachment rules the view is rendered with: render() computes
        // them for the selected provider, and the view is their only consumer.
        $view = $widget->render();
        $data = $view instanceof View ? $view->getData() : [];

        $providers = [];
        foreach ($widget->providerOptions as $option) {
            $providers[] = [
                'id' => (int) $option['id'],
                'name' => (string) $option['name'],
                'provider' => (string) $option['provider'],
                'model' => (string) $option['model'],
                'is_default' => (bool) $option['is_default'],
            ];
        }

        $commands = [];
        foreach ($widget->commands as $command) {
            $commands[] = ['name' => (string) $command['name'], 'description' => (string) $command['description']];
        }

        $attachments = app(AiAttachmentService::class);

        return [
            'available' => true,
            'conversation_id' => $widget->conversationId,
            'messages' => $this->messageList($widget->messages),
            'providers' => $providers,
            'selected_config_id' => $widget->selectedConfigId,
            'commands' => $commands,
            // The history panel exists for signed-in users only (@auth in the view).
            'history' => auth()->check(),
            'conversations' => $this->conversationList($widget),
            'history_limit' => $widget->historyLimit,
            'attachments' => [
                'enabled' => (bool) ($data['attachmentsEnabled'] ?? false),
                'extensions' => array_values((array) ($data['attachmentExtensions'] ?? [])),
                'blocked' => array_values((array) ($data['attachmentBlocked'] ?? [])),
                'accept' => (string) ($data['attachmentAccept'] ?? ''),
                'max_files' => $attachments->maxFiles(),
                'max_size_kb' => $attachments->maxSizeKb(),
            ],
            'stream' => (bool) config('ptah.ai_agent.stream', true),
            'max_message_chars' => max(1, (int) config('ptah.ai_agent.max_message_chars', 8000)),
        ];
    }

    /**
     * The messages as the widget draws them: the user's text as typed (the
     * client escapes it), the assistant's as the HTML ChatMarkdown renders —
     * the same renderer, escaping and link filter as the Livewire bubble.
     *
     * @param  array<int, mixed>  $messages
     * @return list<array<string, mixed>>
     */
    private function messageList(array $messages): array
    {
        $out = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }
            $role = (string) ($message['role'] ?? '');
            $content = (string) ($message['content'] ?? '');

            if ($role === 'user') {
                $out[] = [
                    'role' => 'user',
                    'content' => $content,
                    'attachments' => array_values(array_map('strval', (array) ($message['attachments'] ?? []))),
                ];

                continue;
            }

            $cards = [];
            foreach ((array) ($message['confirmations'] ?? []) as $card) {
                if (is_array($card)) {
                    $cards[] = $this->card($card);
                }
            }

            $out[] = [
                'role' => 'assistant',
                'content' => $content,
                'html' => ChatMarkdown::render($content),
                'confirmations' => $cards,
            ];
        }

        return $out;
    }

    /**
     * A preview card, as partials/confirmation-card.blade.php reads it. The
     * payload to be written never was in it (it stays on the server under
     * the token); a token that is not the alphanumeric one the service
     * issues is dropped, as the view draws no button for it.
     *
     * @param  array<mixed>  $card
     * @return array<string, mixed>
     */
    private function card(array $card): array
    {
        $token = (string) ($card['token'] ?? '');
        $status = (string) ($card['status'] ?? 'pending');

        $columns = [];
        foreach ((array) ($card['columns'] ?? []) as $key => $label) {
            $columns[] = ['key' => (string) $key, 'label' => (string) $label];
        }

        $rows = [];
        foreach ((array) ($card['rows'] ?? []) as $row) {
            $cells = [];
            foreach ($columns as $column) {
                $value = is_array($row) ? ($row[$column['key']] ?? '') : '';
                $cells[] = is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $rows[] = $cells;
        }

        return [
            'token' => preg_match('/^[A-Za-z0-9]{20,64}$/', $token) === 1 ? $token : '',
            'summary' => (string) ($card['summary'] ?? ''),
            'columns' => $columns,
            'rows' => $rows,
            'total_rows' => (int) ($card['total_rows'] ?? count($rows)),
            'status' => in_array($status, ['pending', 'confirmed', 'cancelled', 'expired', 'failed'], true) ? $status : 'expired',
        ];
    }

    /** @return list<array{id: int, title: string, date: string}> */
    private function conversationList(HeadlessAiChatWidget $widget): array
    {
        $out = [];
        foreach ($widget->conversations as $conversation) {
            $out[] = [
                'id' => (int) $conversation['id'],
                'title' => (string) $conversation['title'],
                'date' => (string) $conversation['date'],
            ];
        }

        return $out;
    }

    /**
     * The end of a turn. The widget reports a provider failure, the rate
     * limit and the daily budget in errorMsg (shown under the messages); the
     * user's message stays in the list, as on Livewire.
     */
    private function turnAnswer(HeadlessAiChatWidget $widget): JsonResponse
    {
        $ok = $widget->errorMsg === '';

        return response()->json([
            'ok' => $ok,
            'accepted' => true,
            'error' => $ok ? null : $widget->errorMsg,
            'conversation_id' => $widget->conversationId,
            'messages' => $this->messageList($widget->messages),
            'conversations' => $this->conversationList($widget),
        ], $ok ? 200 : 422);
    }

    /** A turn the widget did not start: nothing was sent to a provider nor stored. */
    private function refused(string $error): JsonResponse
    {
        return response()->json(['ok' => false, 'accepted' => false, 'error' => $error], 422);
    }

    private function wantsStream(Request $request): bool
    {
        return $request->boolean('stream') || str_contains((string) $request->header('Accept', ''), 'text/event-stream');
    }

    /** @param  array<string, mixed>  $data */
    private function event(string $name, array $data): void
    {
        echo 'event: '.$name."\n";
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /** A scalar input as a string; anything else (an array, an object) as empty. */
    private function text(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
