<?php

declare(strict_types=1);

namespace Ptah\Livewire\AI;

use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Ptah\Exceptions\AiProviderException;
use Ptah\Exceptions\AiRateLimitException;
use Ptah\Services\AI\AiAttachmentService;
use Ptah\Services\AI\AiChatService;
use Ptah\Services\AI\AiProviderConfigService;
use Ptah\Support\AI\ChatMarkdown;

/**
 * Floating AI chat widget — injected globally into the Forge Dashboard layout.
 *
 * ⚠  This component has NO #[Layout] attribute — it is embedded as a child
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
    use WithFileUploads;

    protected AiChatService $chatService;

    protected AiProviderConfigService $configService;

    public function boot(AiChatService $chatService, AiProviderConfigService $configService): void
    {
        $this->chatService = $chatService;
        $this->configService = $configService;
    }

    // ── State ──────────────────────────────────────────────────────────

    public bool $available = false;

    public bool $isOpen = false;

    public bool $loading = false;

    public bool $showHistory = false;

    public string $errorMsg = '';

    public ?int $conversationId = null;

    /** @var array<array{role: string, content: string}> */
    public array $messages = [];

    /** @var array<array{id: int, title: string, date: string}> */
    public array $conversations = [];

    public int $historyLimit = 5;

    /**
     * Arquivos que vao com a proxima mensagem.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $attachments = [];

    /**
     * Rascunho de upload: UM arquivo por vez.
     *
     * Colar e arrastar chegam de um em um, e `uploadMultiple` SUBSTITUI o array
     * inteiro — o segundo arquivo apagaria o primeiro, ou o cliente teria de
     * reenviar todos a cada adicao. Um slot de entrada, com o servidor
     * acumulando em `$attachments`, deixa a lista do servidor ser a autoridade,
     * e é ela que o `removeAttachment` mexe e a que os chips desenham.
     */
    public mixed $incoming = null;

    /**
     * The provider the next turn should use.
     *
     * Deliberately NOT #[Locked]: choosing a provider is the point, so this is
     * client-writable. The safety is on the read side —
     * AiProviderConfigService::resolveForTurn() only accepts an id that names an
     * ACTIVE config and otherwise falls back to the default, so a forged or
     * stale value degrades instead of reaching a provider an administrator
     * switched off.
     */
    public ?int $selectedConfigId = null;

    /**
     * Active providers for the picker. The picker only renders when there is
     * more than one — a single provider needs no choice, and the control would
     * be noise in a widget this small.
     *
     * @var array<int, array{id: int, name: string, provider: string, model: string, is_default: bool}>
     */
    public array $providerOptions = [];

    private const HISTORY_MAX = 100;

    private const HISTORY_STEP = 5;

    // ── Lifecycle ──────────────────────────────────────────────────────

    public function mount(): void
    {
        $userId = auth()->id();

        // Guests only get the widget when explicitly allowed.
        if (! $userId && ! config('ptah.ai_agent.allow_guests', false)) {
            $this->available = false;

            return;
        }

        $this->available = $this->configService->hasActiveProvider();

        if (! $this->available) {
            return;
        }

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

        // The picker starts on the default, which is what a user who never
        // touches it keeps getting.
        $this->providerOptions = $this->configService->listActive();
        $this->selectedConfigId = $this->configService->findDefault()?->id
            ?? ($this->providerOptions[0]['id'] ?? null);

        if ($userId) {
            $this->refreshConversations();
        }
    }

    // ── Anexos ─────────────────────────────────────────────────────────

    /**
     * Aceita (ou recusa) o arquivo que acabou de subir.
     *
     * A validacao e toda aqui, no servidor, porque a checagem do cliente e
     * conveniencia: o `accept` do input e o filtro do drop informam a pessoa
     * antes do upload, e nao impedem nada — `$wire.upload` e uma chamada
     * publica. Extensao, tamanho e quantidade sao decididos deste lado.
     */
    public function updatedIncoming(): void
    {
        $file = $this->incoming;
        $this->incoming = null;

        if ($file === null) {
            return;
        }

        if (! $this->attachmentService()->enabled()) {
            return;
        }

        // Pelo servico, nao por config() direto: os defaults do pacote vivem no
        // codigo porque `mergeConfigFrom` e RASO — um host que publicou
        // config/ptah.php nao recebe chave NOVA aninhada, e ler daqui com
        // fallback proprio faria o widget e o servico discordarem.
        $maxFiles = $this->attachmentService()->maxFiles();
        $maxKb = $this->attachmentService()->maxSizeKb();

        // A lista vem ESTREITADA pelo provedor selecionado: num provedor que
        // nao aceita documento, PDF nao entra. Mesma fonte que alimenta o
        // `accept` do input e o filtro do cliente — tres lugares que nao podem
        // discordar sobre o que e permitido.
        $allowed = $this->attachmentService()->allowedExtensions($this->currentProvider());

        $name = method_exists($file, 'getClientOriginalName')
            ? (string) $file->getClientOriginalName()
            : 'arquivo';

        if (count($this->attachments) >= $maxFiles) {
            $this->errorMsg = trans('ptah::ui.ai_attach_too_many', ['max' => $maxFiles]);
            $this->discard($file);

            return;
        }

        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if ($allowed !== [] && ! in_array($ext, $allowed, true)) {
            // Recusado porque o PROVEDOR nao aceita, e nao porque a extensao
            // esta fora da configuracao: sao motivos diferentes e a pessoa pode
            // agir sobre o primeiro trocando o provedor no seletor.
            $blocked = $this->attachmentService()->blockedExtensions($this->currentProvider());

            $this->errorMsg = in_array($ext, $blocked, true)
                ? trans('ptah::ui.ai_attach_provider_no_docs', [
                    'name' => $name,
                    'provider' => $this->currentProvider(),
                ])
                : trans('ptah::ui.ai_attach_bad_type', [
                    'name' => $name,
                    'allowed' => implode(', ', $allowed),
                ]);

            $this->discard($file);

            return;
        }

        // Em kilobytes, como o limite. getSize() devolve bytes.
        if ((int) ceil(((int) $file->getSize()) / 1024) > $maxKb) {
            $this->errorMsg = trans('ptah::ui.ai_attach_too_big', ['name' => $name, 'max' => $maxKb]);
            $this->discard($file);

            return;
        }

        $this->errorMsg = '';
        $this->attachments[] = $file;
    }

    public function removeAttachment(int $index): void
    {
        if (! array_key_exists($index, $this->attachments)) {
            return;
        }

        $this->discard($this->attachments[$index]);
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
        $this->errorMsg = '';
    }

    /**
     * Apaga o temporario agora em vez de esperar a limpeza do Livewire.
     *
     * Um print de tela recusado por tamanho ficaria horas no disco temporario
     * sem que nada mais fosse apontar para ele.
     */
    private function discard(mixed $file): void
    {
        try {
            if (is_object($file) && method_exists($file, 'delete')) {
                $file->delete();
            }
        } catch (\Throwable) {
            // Limpeza; o disco temporario do Livewire tem a dele.
        }
    }

    /**
     * Os anexos no formato que o AiAttachmentService espera.
     *
     * @return array<int, array{path: string, name: string, mime: string}>
     */
    private function attachmentPayload(): array
    {
        $out = [];

        foreach ($this->attachments as $file) {
            try {
                $path = (string) $file->getRealPath();

                if ($path === '' || ! is_file($path)) {
                    continue;
                }

                $out[] = [
                    'path' => $path,
                    'name' => (string) $file->getClientOriginalName(),
                    'mime' => (string) $file->getMimeType(),
                ];
            } catch (\Throwable $e) {
                Log::warning('[Ptah AI] anexo ilegivel na hora do envio', [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $out;
    }

    /**
     * O provedor que o proximo turno vai usar.
     *
     * Passa pelo resolveForTurn de proposito: `selectedConfigId` vem do cliente,
     * e um id que nao nomeia config ATIVA cai no padrao. Sem isso, a lista de
     * extensoes seria calculada para um provedor que o turno nao vai usar.
     */
    private function currentProvider(): string
    {
        return (string) ($this->configService->resolveForTurn($this->selectedConfigId)->provider ?? '');
    }

    private function attachmentService(): AiAttachmentService
    {
        return app(AiAttachmentService::class);
    }

    // ── Actions ────────────────────────────────────────────────────────

    /**
     * Sends a message. The text arrives as an argument, and that is the fix for
     * a bug, not a stylistic choice.
     *
     * The draft used to be a public property bound with `wire:model.live`. So
     * the textarea's value was server state, and the server's copy went to ''
     * the moment send() ran. `processAiMessage` is a SEPARATE request — often a
     * slow one, since it waits on the model — and whatever the user typed while
     * it was in flight lived only in the browser. When that response landed,
     * Livewire morphed the textarea back to the snapshot's empty string and the
     * typed text vanished. "As vezes estou digitando e o texto se apaga
     * sozinho, talvez seja por conta de uma resposta sendo recebida" — exactly
     * that, and the diagnosis was right.
     *
     * A draft nobody but the browser owns cannot be overwritten by a stale
     * snapshot. It also stops costing one request per keystroke, each one
     * re-rendering the whole message list.
     */
    public function send(string $message = ''): void
    {
        $message = trim($message);

        // Com anexo, um texto vazio ainda e um envio valido: "analisa isso" e o
        // proprio arquivo. Sem anexo, nao ha o que enviar.
        if (($message === '' && $this->attachments === []) || ! $this->available || $this->loading) {
            return;
        }

        $names = array_values(array_map(
            fn ($f) => (string) $f->getClientOriginalName(),
            $this->attachments
        ));

        // Os NOMES vao para a lista agora; os ARQUIVOS ficam em $attachments
        // ate o processAiMessage, que roda numa requisicao separada. Limpar aqui
        // esvaziaria o anexo antes de ele ser enviado.
        $userMessage = ['role' => 'user', 'content' => $message];

        if ($names !== []) {
            $userMessage['attachments'] = $names;
        }

        $this->messages[] = $userMessage;
        $this->errorMsg = '';
        $this->loading = true;
        $this->showHistory = false;

        $this->dispatch('ai-process-message', message: $message, conversationId: $this->conversationId);
        $this->dispatch('ai-message-sent');
    }

    #[On('ai-process-message')]
    public function processAiMessage(string $message, ?int $conversationId = null): void
    {
        // Re-check availability — this is a public Livewire listener and can be
        // dispatched directly, bypassing the guards in send().
        if (! $this->configService->hasActiveProvider()) {
            $this->loading = false;
            $this->errorMsg = trans('ptah::ui.ai_widget_no_provider');
            $this->dispatch('ai-message-sent');

            return;
        }

        try {
            // `stream` is a preference, not a capability: Prism's base provider
            // throws unsupportedProviderAction from stream(), so a provider that
            // ships no Stream handler (today: z.ai) would break the chat outright
            // under the package's own default. Ask before assuming.
            $canStream = config('ptah.ai_agent.stream', true)
                && $this->chatService->supportsStreaming($this->selectedConfigId);

            $attachments = $this->attachmentPayload();

            if ($canStream) {
                // Stream the answer token-by-token into the wire:stream region.
                $result = $this->chatService->stream(
                    $message,
                    session()->getId(),
                    auth()->id(),
                    $conversationId,
                    onDelta: function (string $delta, string $accumulated): void {
                        // Mesmo renderizador da bolha final, para o texto nao
                        // mudar de aparencia quando o streaming termina. O
                        // CommonMark aceita documento inacabado — cerca de
                        // codigo sem fechar sai como bloco de codigo aberto, nao
                        // como erro.
                        $this->stream(
                            to: 'ai-stream',
                            content: ChatMarkdown::render($accumulated),
                            replace: true,
                        );
                    },
                    configId: $this->selectedConfigId,
                    attachments: $attachments,
                );
            } else {
                $result = $this->chatService->send(
                    $message,
                    session()->getId(),
                    auth()->id(),
                    $conversationId,
                    $this->selectedConfigId,
                    $attachments,
                );
            }

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
                'message' => $e->getMessage(),
            ]);
            $this->errorMsg = config('app.debug')
                ? '['.class_basename($e).'] '.$e->getMessage()
                : trans('ptah::ui.ai_widget_error');
        } finally {
            $this->loading = false;

            // Descartados haja o que houver, inclusive em erro de provedor: o
            // temporario ja foi lido, e manter os chips na tela depois do envio
            // faria a proxima mensagem levar o arquivo de novo sem a pessoa
            // pedir.
            foreach ($this->attachments as $file) {
                $this->discard($file);
            }

            $this->attachments = [];
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
        $this->errorMsg = '';
        $this->showHistory = false;

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

        foreach ($this->attachments as $file) {
            $this->discard($file);
        }

        $this->attachments = [];
        $this->messages = [];
        $this->errorMsg = '';
        $this->showHistory = false;

        // O rascunho vive no navegador (ver send()), entao limpar e um aviso,
        // nao uma atribuicao.
        $this->dispatch('ai-draft-clear');
    }

    public function toggleHistory(): void
    {
        $this->showHistory = ! $this->showHistory;
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
        $this->isOpen = ! $this->isOpen;
        $this->errorMsg = '';
        $this->showHistory = false;
        $this->historyLimit = 5;
    }

    // ── Render ─────────────────────────────────────────────────────────

    public function render()
    {
        $enabled = $this->attachmentService()->enabled();
        $extensions = [];
        $blocked = [];

        if ($enabled && $this->available) {
            $provider = $this->currentProvider();
            $extensions = $this->attachmentService()->allowedExtensions($provider);
            $blocked = $this->attachmentService()->blockedExtensions($provider);
        }

        return view('ptah::livewire.ai.ai-chat-widget', [
            'attachmentsEnabled' => $enabled,
            'attachmentExtensions' => $extensions,
            // O que a configuracao permite mas ESTE provedor nao recebe. Vai
            // para a tela: a recusa fica legivel antes de acontecer, e a pessoa
            // pode trocar o provedor no seletor logo acima.
            'attachmentBlocked' => $blocked,
            // `accept` do input: dica para o seletor de arquivos do sistema, e
            // nada mais. Quem decide e updatedIncoming().
            'attachmentAccept' => $extensions === []
                ? ''
                : implode(',', array_map(fn (string $e): string => '.'.$e, $extensions)),
        ]);
    }

    // ── Private ────────────────────────────────────────────────────────

    private function refreshConversations(): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        $this->conversations = $this->chatService
            ->getUserConversations($userId, $this->historyLimit)
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title ?: trans('ptah::ui.ai_widget_untitled'),
                'date' => $c->updated_at?->diffForHumans() ?? '',
            ])
            ->all();
    }
}
