/**
 * The AI agent module of the admin API — the provider configuration screen
 * and the floating chat: the types, one client function per endpoint (the
 * chat's answer also as a server-sent event stream) and the words of the
 * screens. Nothing framework-specific: the React and Vue screens are drawn
 * over this.
 */
import { ApiError, toQuery } from '../api';
import type { PtahApi } from '../api';

// ── Types: provider configurations ──────────────────────────────────────────

export type SortDirection = 'asc' | 'desc';

/** What the list is asked for. The screen sorts by `name` only, as on Livewire. */
export interface AiListQuery {
    search: string;
    sort: string;
    direction: SortDirection;
    page: number;
}

export interface AiListMeta {
    page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface AiOption {
    value: string;
    label: string;
}

/** A row of the list. The API key is never in it — not even masked. */
export interface AiModelRow {
    id: number;
    name: string;
    provider: string;
    provider_label: string;
    model: string;
    notes: string | null;
    is_active: boolean;
    is_default: boolean;
}

export interface AiModelListing {
    data: AiModelRow[];
    meta: AiListMeta;
    state: { search: string; sort: string; direction: SortDirection };
    providers: AiOption[];
}

export interface AiModelForm {
    name: string;
    provider: string;
    model: string;
    /** Always empty from the server; left empty on an edit, the stored key is kept. */
    api_key: string;
    api_endpoint: string;
    max_tokens: number;
    /** A string, as the screen keeps it ('0.70'). */
    temperature: string;
    system_prompt: string;
    notes: string;
    is_active: boolean;
    is_default: boolean;
}

export interface AiMessageNote {
    text: string;
    level: string;
}

/**
 * The answer of a save, a delete or "set as default". A validation error
 * (Laravel's `{message, errors: {field: [..]}}`) and a refusal of the screen
 * (`{ok: false, error}`) come back in this one shape.
 */
export interface AiResult {
    ok: boolean;
    error: string | null;
    errors: Record<string, string>;
    messages: AiMessageNote[];
}

/** The providers of the form (the server answers the same list with each listing). */
export const AI_PROVIDERS: AiOption[] = [
    { value: 'openai', label: 'OpenAI' },
    { value: 'anthropic', label: 'Anthropic (Claude)' },
    { value: 'gemini', label: 'Google Gemini' },
    { value: 'xai', label: 'xAI (Grok)' },
    { value: 'deepseek', label: 'DeepSeek' },
    { value: 'groq', label: 'Groq' },
    { value: 'mistral', label: 'Mistral' },
    { value: 'openrouter', label: 'OpenRouter' },
    { value: 'perplexity', label: 'Perplexity' },
    { value: 'z', label: 'z.ai (GLM)' },
    { value: 'ollama', label: 'Ollama (Local)' },
    { value: 'openai_compatible', label: 'OpenAI-compatible (custom endpoint)' },
];

/** The provider that needs no key (the view's `$provider === 'ollama'`). */
export const keyOptional = (provider: string) => provider === 'ollama';

/** The how-to cards of the screen: provider, words key, where the key is made. */
export const AI_GUIDE: { name: string; hint: keyof AiWords; url: string }[] = [
    { name: 'OpenAI', hint: 'guideOpenai', url: 'platform.openai.com/api-keys' },
    { name: 'Anthropic (Claude)', hint: 'guideAnthropic', url: 'console.anthropic.com/settings/keys' },
    { name: 'Google Gemini', hint: 'guideGemini', url: 'aistudio.google.com/app/apikey' },
    { name: 'Ollama (Local)', hint: 'guideOllama', url: 'ollama.com' },
    { name: 'Groq', hint: 'guideGroq', url: 'console.groq.com/keys' },
    { name: 'Mistral', hint: 'guideMistral', url: 'console.mistral.ai/api-keys' },
];

export const blankAiModel = (): AiModelForm => ({
    name: '',
    provider: 'openai',
    model: 'gpt-4o-mini',
    api_key: '',
    api_endpoint: '',
    max_tokens: 1024,
    temperature: '0.70',
    system_prompt: '',
    notes: '',
    is_active: true,
    is_default: false,
});

export const blankAiQuery = (): AiListQuery => ({ search: '', sort: 'name', direction: 'asc', page: 1 });

/** The next sort after a click on the column header, as the Livewire screen toggles it. */
export function nextAiSort(query: AiListQuery, column: string): AiListQuery {
    return query.sort === column ? { ...query, direction: query.direction === 'asc' ? 'desc' : 'asc', page: 1 } : { ...query, sort: column, direction: 'asc', page: 1 };
}

/** The notes under the name, cut as the view's `Str::limit($notes, 60)`. */
export const shortNotes = (notes: string) => (notes.length > 60 ? `${notes.slice(0, 60)}...` : notes);

// ── Types: chat ─────────────────────────────────────────────────────────────

/** A preview card of a confirmable tool. The payload to be written stays on the server. */
export interface AiCard {
    /** Empty when the token is not the one the server issues: no buttons then. */
    token: string;
    summary: string;
    columns: { key: string; label: string }[];
    /** Cells in the order of `columns`, as text. */
    rows: string[][];
    total_rows: number;
    status: 'pending' | 'confirmed' | 'cancelled' | 'expired' | 'failed';
}

export interface AiChatMessage {
    role: 'user' | 'assistant';
    /** As typed (user) or as the model wrote it (assistant). */
    content: string;
    /** The assistant's answer as HTML, rendered and sanitised on the server (ChatMarkdown). */
    html?: string;
    /** The names of the files sent with a user message. */
    attachments?: string[];
    confirmations?: AiCard[];
}

export interface AiProviderOption {
    id: number;
    name: string;
    provider: string;
    model: string;
    is_default: boolean;
}

export interface AiConversationItem {
    id: number;
    title: string;
    /** "2 hours ago", in the server's locale. */
    date: string;
}

export interface AiAttachmentRules {
    enabled: boolean;
    /** Narrowed by the selected provider. Empty: no attachment UI. */
    extensions: string[];
    /** What the configuration allows but this provider does not take. */
    blocked: string[];
    accept: string;
    max_files: number;
    max_size_kb: number;
}

export interface AiChatState {
    available: boolean;
    conversation_id: number | null;
    messages: AiChatMessage[];
    providers: AiProviderOption[];
    selected_config_id: number | null;
    commands: { name: string; description: string }[];
    /** The history panel exists for signed-in users. */
    history: boolean;
    conversations: AiConversationItem[];
    history_limit: number;
    attachments: AiAttachmentRules;
    /** The server's streaming preference (`ptah.ai_agent.stream`). */
    stream: boolean;
    max_message_chars: number;
}

export interface AiTurnInput {
    message: string;
    /** The conversation open in the widget; null starts a new one. */
    conversation_id: number | null;
    config_id: number | null;
    files?: File[];
}

/**
 * The end of a turn. `accepted: false` — the widget refused it before any
 * provider call (too long, empty, an attachment); `accepted: true` with an
 * error — the provider failed or a limit was hit, and the user's message stays.
 */
export interface AiTurn {
    ok: boolean;
    accepted: boolean;
    error: string | null;
    conversation_id: number | null;
    messages: AiChatMessage[];
    conversations: AiConversationItem[];
}

// ── Client: configurations ──────────────────────────────────────────────────

const BASE = '/admin/ai';

function result(raw: Record<string, unknown> | null): AiResult {
    if (!raw) {
        return { ok: false, error: null, errors: {}, messages: [] };
    }
    if (raw.ok === undefined) {
        const errors = fieldErrors(raw);

        return { ok: false, errors, error: Object.keys(errors).length === 0 ? String(raw.message ?? '') : null, messages: [] };
    }

    return {
        ok: Boolean(raw.ok),
        error: (raw.error as string | null | undefined) ?? null,
        errors: {},
        messages: (raw.messages as AiMessageNote[] | undefined) ?? [],
    };
}

function fieldErrors(raw: Record<string, unknown>): Record<string, string> {
    const errors: Record<string, string> = {};
    for (const [field, messages] of Object.entries((raw.errors ?? {}) as Record<string, string[] | string>)) {
        errors[field] = Array.isArray(messages) ? String(messages[0] ?? '') : String(messages);
    }

    return errors;
}

export function listAiModels(api: PtahApi, query: Partial<AiListQuery> = {}): Promise<AiModelListing> {
    const params = toQuery({ ...query, search: query.search || undefined });

    return api.call('GET', `${BASE}/models${params ? `?${params}` : ''}`);
}

export function showAiModel(api: PtahApi, id: number): Promise<{ id: number; form: AiModelForm }> {
    return api.call('GET', `${BASE}/models/${id}`);
}

/** Create (`id` null) or update. */
export async function saveAiModel(api: PtahApi, id: number | null, form: AiModelForm): Promise<AiResult> {
    return result(await api.call<Record<string, unknown>>(id === null ? 'POST' : 'PUT', id === null ? `${BASE}/models` : `${BASE}/models/${id}`, form));
}

export async function deleteAiModel(api: PtahApi, id: number): Promise<AiResult> {
    return result(await api.call<Record<string, unknown>>('DELETE', `${BASE}/models/${id}`));
}

export async function setDefaultAiModel(api: PtahApi, id: number): Promise<AiResult> {
    return result(await api.call<Record<string, unknown>>('POST', `${BASE}/models/${id}/default`));
}

// ── Client: chat ────────────────────────────────────────────────────────────

const CHAT = `${BASE}/chat`;

/** What the widget shows when it opens. `configId` is the provider picked, which narrows the attachment formats. */
export async function chatState(api: PtahApi, configId: number | null = null): Promise<AiChatState> {
    return api.call('GET', `${CHAT}${configId !== null ? `?config_id=${configId}` : ''}`);
}

/** The history panel: the last `limit` conversations (the server caps it at 100). */
export function chatConversations(api: PtahApi, limit = 5): Promise<{ conversations: AiConversationItem[]; limit: number }> {
    return api.call('GET', `${CHAT}/conversations?limit=${limit}`);
}

/** Opens a conversation of the history. Another user's one throws ApiError 404. */
export function openConversation(api: PtahApi, id: number): Promise<{ conversation_id: number; messages: AiChatMessage[] }> {
    return api.call('GET', `${CHAT}/conversations/${id}`);
}

export function newConversation(api: PtahApi): Promise<{ conversation_id: number | null; messages: AiChatMessage[] }> {
    return api.call('POST', `${CHAT}/conversations`);
}

function turn(raw: Record<string, unknown> | null): AiTurn {
    const errors = raw && raw.ok === undefined ? fieldErrors(raw) : {};
    const firstError = Object.values(errors)[0];

    return {
        ok: Boolean(raw?.ok),
        // A validation answer of Laravel (no `ok`) never reached the widget.
        accepted: raw?.ok === undefined ? false : Boolean(raw.accepted),
        error: raw?.ok === undefined ? (firstError ?? String(raw?.message ?? '')) : ((raw.error as string | null | undefined) ?? null),
        conversation_id: (raw?.conversation_id as number | null | undefined) ?? null,
        messages: (raw?.messages as AiChatMessage[] | undefined) ?? [],
        conversations: (raw?.conversations as AiConversationItem[] | undefined) ?? [],
    };
}

function turnBody(input: AiTurnInput, stream: boolean): FormData | Record<string, unknown> {
    if (!input.files || input.files.length === 0) {
        return { message: input.message, conversation_id: input.conversation_id, config_id: input.config_id, stream };
    }

    const form = new FormData();
    form.append('message', input.message);
    if (input.conversation_id !== null) {
        form.append('conversation_id', String(input.conversation_id));
    }
    if (input.config_id !== null) {
        form.append('config_id', String(input.config_id));
    }
    form.append('stream', stream ? '1' : '0');
    for (const file of input.files) {
        form.append('attachments[]', file);
    }

    return form;
}

/** Sends a message and waits for the whole answer (the non-streaming path). */
export async function sendMessage(api: PtahApi, input: AiTurnInput): Promise<AiTurn> {
    return turn(await api.call<Record<string, unknown>>('POST', `${CHAT}/messages`, turnBody(input, false)));
}

export interface StreamOptions {
    /** Each frame: the answer so far, as HTML (sanitised on the server). */
    onDelta?: (html: string) => void;
    /** Aborts the request; the promise then rejects with an AbortError. */
    signal?: AbortSignal;
}

function cookie(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }
    const found = document.cookie.split('; ').find((row) => row.startsWith(`${name}=`));

    return found ? decodeURIComponent(found.slice(name.length + 1)) : null;
}

/** The CSRF header PtahApi sends (its helper is not exported). */
function csrfHeaders(): Record<string, string> {
    const xsrf = cookie('XSRF-TOKEN');
    if (xsrf) {
        return { 'X-XSRF-TOKEN': xsrf };
    }
    const token = typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') : null;

    return token ? { 'X-CSRF-TOKEN': token } : {};
}

function parseEvent(block: string): { name: string; data: string } {
    let name = 'message';
    const data: string[] = [];
    for (const line of block.split('\n')) {
        if (line.startsWith('event:')) {
            name = line.slice(6).trim();
        } else if (line.startsWith('data:')) {
            data.push(line.slice(5).replace(/^ /, ''));
        }
    }

    return { name, data: data.join('\n') };
}

/**
 * Sends a message and reads the answer as it is written: `delta` events
 * (the answer so far, as HTML) and a final `done` with the same body as
 * sendMessage(). A turn refused before it starts comes back as plain JSON
 * and is answered the same way. Uses `fetch` + `ReadableStream`, through the
 * transport, base URL and headers the PtahApi was built with.
 */
export async function streamMessage(api: PtahApi, input: AiTurnInput, options: StreamOptions = {}): Promise<AiTurn> {
    // PtahApi exposes no streaming request yet: its settings are reached by
    // element access (TypeScript's escape hatch for private members).
    const baseUrl = api['baseUrl'];
    const doFetch = api['doFetch'];
    const body = turnBody(input, true);
    const headers: Record<string, string> = { Accept: 'text/event-stream', 'X-Requested-With': 'XMLHttpRequest', ...csrfHeaders(), ...api['headers'] };
    if (!(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await doFetch(`${baseUrl}${CHAT}/messages`, {
        method: 'POST',
        headers,
        body: body instanceof FormData ? body : JSON.stringify(body),
        credentials: 'same-origin',
        signal: options.signal,
    });

    if (!(response.headers.get('Content-Type') ?? '').includes('text/event-stream') || !response.body) {
        const text = await response.text();
        let json: Record<string, unknown> | null = null;
        try {
            json = text ? (JSON.parse(text) as Record<string, unknown>) : null;
        } catch {
            json = null;
        }
        if (!response.ok && response.status !== 422) {
            throw new ApiError(response.status, String(json?.message ?? '') || `HTTP ${response.status}`, json);
        }

        return turn(json);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let done: AiTurn | null = null;

    for (;;) {
        const chunk = await reader.read();
        if (chunk.done) {
            break;
        }
        buffer += decoder.decode(chunk.value, { stream: true }).replace(/\r\n/g, '\n');

        let at = buffer.indexOf('\n\n');
        while (at >= 0) {
            const event = parseEvent(buffer.slice(0, at));
            buffer = buffer.slice(at + 2);
            at = buffer.indexOf('\n\n');

            const data = event.data ? (JSON.parse(event.data) as Record<string, unknown>) : {};
            if (event.name === 'delta') {
                options.onDelta?.(String(data.html ?? ''));
            } else if (event.name === 'done') {
                done = turn(data);
            }
        }
    }

    if (done === null) {
        throw new ApiError(response.status, 'The answer stream ended before it was complete.');
    }

    return done;
}

/** "Confirm" / "Cancel" on a preview card. */
export async function resolveCard(api: PtahApi, conversationId: number, token: string, confirm: boolean): Promise<{ ok: boolean; error: string | null; messages: AiChatMessage[] }> {
    const raw = await api.call<Record<string, unknown>>('POST', `${CHAT}/confirmations`, { conversation_id: conversationId, token, confirm });

    return { ok: Boolean(raw?.ok), error: (raw?.error as string | null | undefined) ?? null, messages: (raw?.messages as AiChatMessage[] | undefined) ?? [] };
}

/** The `/commands` that start with what was typed — only while the draft is `/word` (no space). */
export function commandMatches(commands: { name: string; description: string }[], draft: string): { name: string; description: string }[] {
    if (!draft.startsWith('/') || /\s/.test(draft)) {
        return [];
    }
    const typed = draft.slice(1).toLowerCase();

    return commands.filter((command) => command.name.startsWith(typed)).slice(0, 8);
}

/** The files the widget would upload: the accepted extensions, up to the room left (the Alpine `take()`). */
export function acceptFiles(files: File[], rules: AiAttachmentRules, current: number): File[] {
    let room = rules.max_files - current;
    const out: File[] = [];
    for (const file of files) {
        if (room <= 0) {
            break;
        }
        const ext = (file.name.split('.').pop() ?? '').toLowerCase();
        if (rules.extensions.length > 0 && !rules.extensions.includes(ext)) {
            continue;
        }
        room--;
        out.push(file);
    }

    return out;
}

/** Keys of the widget's device preferences, the same the Livewire widget keeps. */
export const AI_STORAGE = {
    expanded: 'ptah:ai:expanded',
    launcherHidden: 'ptah:ai:launcher-hidden',
    open: 'ptah:ai:open',
    draft: 'ptah:ai:draft',
    scroll: 'ptah:ai:scroll',
    conversation: 'ptah:ai:conversation',
};

export function readStore(storage: 'local' | 'session', key: string): string | null {
    try {
        return (storage === 'local' ? window.localStorage : window.sessionStorage).getItem(key);
    } catch {
        return null;
    }
}

/** How close to the end of the list still counts as "at the end" (px). */
export const CHAT_END_SLACK = 24;

/**
 * Notes where the reader is in the chat's list of messages (this tab only),
 * as `<conversation>:<scrollTop>`: a position when they scrolled up to read,
 * nothing when they are at the end. A hidden list (closed chat) is not a
 * position. The Livewire widget keeps the same keys with the same rule.
 */
export function noteChatScroll(list: HTMLElement, conversationId: number | null): void {
    if (!list.isConnected || list.clientHeight === 0) {
        return;
    }
    const atEnd = list.scrollHeight - list.scrollTop - list.clientHeight < CHAT_END_SLACK;
    writeStore('session', AI_STORAGE.scroll, atEnd || conversationId === null ? null : `${conversationId}:${Math.round(list.scrollTop)}`);
}

/**
 * Puts the list where the reader left it — reopening the chat, a reload, an
 * answer arriving while they read an older message — or at the end when they
 * were at the end, or when the position was taken in another conversation.
 * Sending a message goes to the end (`chatToEnd`).
 */
export function placeChatScroll(list: HTMLElement, conversationId: number | null): void {
    const kept = /^(\d+):(\d+)$/.exec(readStore('session', AI_STORAGE.scroll) ?? '');
    list.scrollTop = kept && conversationId !== null && Number(kept[1]) === conversationId ? Math.min(Number(kept[2]), list.scrollHeight) : list.scrollHeight;
}

/**
 * The conversation on screen, kept for this tab: a reload reopens the one the
 * person was reading (picked from the history), not the latest.
 */
export function keepChatConversation(id: number | null): void {
    writeStore('session', AI_STORAGE.conversation, id === null ? null : String(id));
}

export function keptChatConversation(): number | null {
    const id = Number(readStore('session', AI_STORAGE.conversation) ?? '');

    return Number.isInteger(id) && id > 0 ? id : null;
}

/** Forgets the reading position: what comes next is read from the end. */
export function chatToEnd(): void {
    writeStore('session', AI_STORAGE.scroll, null);
}

export function writeStore(storage: 'local' | 'session', key: string, value: string | null): void {
    try {
        const store = storage === 'local' ? window.localStorage : window.sessionStorage;
        if (value === null) {
            store.removeItem(key);
        } else {
            store.setItem(key, value);
        }
    } catch {
        // A private window or blocked storage: the preference is just not kept.
    }
}

// ── Words ───────────────────────────────────────────────────────────────────

const en = {
    // Configuration screen
    title: 'AI Model Configuration',
    subtitle: 'Manage AI providers and models used by the assistant.',
    guideTitle: 'How to configure an AI provider',
    guideIntro: 'Choose a provider below and follow the instructions to obtain your API key:',
    guideOpenai: 'OpenAI: Go to platform.openai.com → API Keys → Create new secret key.',
    guideAnthropic: 'Anthropic: Go to console.anthropic.com → API Keys → Create Key.',
    guideGemini: 'Google Gemini: Go to aistudio.google.com → Get API Key.',
    guideOllama: 'Ollama (local): Install ollama.ai, run "ollama pull <model>" and set the endpoint to http://localhost:11434.',
    guideGroq: 'Groq: Go to console.groq.com → API Keys → Create API Key.',
    guideMistral: 'Mistral AI: Go to console.mistral.ai → API Keys → Create new key.',
    guideNote: 'API keys are encrypted at rest. Never share them with third parties.',
    newLabel: 'New',
    search: 'Search',
    searchPlaceholder: 'Search...',
    loading: 'Loading…',
    loadError: 'Could not load the list.',
    name: 'Name',
    provider: 'Provider',
    model: 'Model',
    status: 'Status',
    defaultCol: 'Default',
    actions: 'Actions',
    active: 'Active',
    inactive: 'Inactive',
    isDefault: 'Default',
    setDefault: 'Set as default',
    edit: 'Edit',
    delete: 'Delete',
    empty: 'No records found',
    range: '{first}–{last} of {total}',
    modalNew: 'New AI Model Configuration',
    modalEdit: 'Edit AI Model Configuration',
    modalNewSubtitle: 'Fill in the fields below',
    modalEditSubtitle: 'Change fields and save',
    apiKey: 'API Key',
    apiKeyLeaveBlank: 'leave blank to keep current key',
    apiKeyOptional: 'optional for Ollama',
    endpoint: 'Custom Endpoint (optional)',
    endpointHint: 'Leave blank to use the provider default. Required for Ollama and self-hosted models.',
    maxTokens: 'Max tokens',
    temperature: 'Temperature (0-2)',
    systemPrompt: 'System prompt',
    systemPromptPh: 'You are a helpful assistant…',
    systemPromptHint: 'Optional instruction sent before every conversation.',
    notes: 'Notes',
    cancel: 'Cancel',
    create: 'Create',
    saveChanges: 'Save Changes',
    saving: 'Saving…',
    deleteTitle: 'Confirm deletion',
    deleteMessage: 'This action cannot be undone.',
    // Chat
    chatTitle: 'AI Assistant',
    chatOpen: 'Open AI assistant',
    chatClose: 'Close assistant',
    chatHistory: 'Conversation history',
    chatNew: 'New conversation',
    chatExpand: 'Expand to full screen',
    chatCollapse: 'Back to the corner',
    chatHide: 'Hide the assistant button',
    chatShow: 'Show the assistant',
    chatProvider: 'AI provider',
    chatProviderDefault: 'default',
    chatNoHistory: 'No previous conversations',
    chatLoadMore: 'Load more',
    chatEmpty: 'Ask me anything — I am here to help!',
    chatPlaceholder: 'Type a message…',
    chatSend: 'Send',
    chatThinking: 'The assistant is answering…',
    chatKeyboard: 'Press Enter to send · Shift+Enter for new line',
    chatError: 'An error occurred. Please try again.',
    attach: 'Attach a file',
    attachHint: 'Attach, paste (Ctrl+V) or drop a file',
    attachDrop: 'Drop the file to attach it',
    attachRemove: 'Remove {name}',
    attachBlocked: 'This provider does not take {formats}.',
    commands: 'Commands',
    confirm: 'Confirm',
    cardCancel: 'Cancel',
    moreRows: 'Showing {shown} of {total}',
    statusConfirmed: 'Confirmed',
    statusCancelled: 'Cancelled',
    statusExpired: 'Expired',
    statusFailed: 'Failed',
};

export type AiWords = typeof en;

const ptBR: AiWords = {
    title: 'Configuração de Modelos de IA',
    subtitle: 'Gerencie os provedores e modelos de IA usados pelo assistente.',
    guideTitle: 'Como configurar um provedor de IA',
    guideIntro: 'Escolha um provedor abaixo e siga as instruções para obter sua chave de API:',
    guideOpenai: 'OpenAI: Acesse platform.openai.com → API Keys → Create new secret key.',
    guideAnthropic: 'Anthropic: Acesse console.anthropic.com → API Keys → Create Key.',
    guideGemini: 'Google Gemini: Acesse aistudio.google.com → Get API Key.',
    guideOllama: 'Ollama (local): Instale em ollama.ai, execute "ollama pull <modelo>" e defina o endpoint como http://localhost:11434.',
    guideGroq: 'Groq: Acesse console.groq.com → API Keys → Create API Key.',
    guideMistral: 'Mistral AI: Acesse console.mistral.ai → API Keys → Create new key.',
    guideNote: 'As chaves de API são criptografadas em repouso. Nunca as compartilhe com terceiros.',
    newLabel: 'Novo',
    search: 'Buscar',
    searchPlaceholder: 'Buscar...',
    loading: 'Carregando…',
    loadError: 'Não foi possível carregar a lista.',
    name: 'Nome',
    provider: 'Provedor',
    model: 'Modelo',
    status: 'Status',
    defaultCol: 'Padrão',
    actions: 'Ações',
    active: 'Ativo',
    inactive: 'Inativo',
    isDefault: 'Padrão',
    setDefault: 'Definir como padrão',
    edit: 'Editar',
    delete: 'Excluir',
    empty: 'Nenhum registro encontrado',
    range: '{first}–{last} de {total}',
    modalNew: 'Novo Configuração de Modelos de IA',
    modalEdit: 'Editar Configuração de Modelos de IA',
    modalNewSubtitle: 'Preencha os campos abaixo',
    modalEditSubtitle: 'Altere os campos e salve',
    apiKey: 'Chave de API',
    apiKeyLeaveBlank: 'deixe em branco para manter a chave atual',
    apiKeyOptional: 'opcional para Ollama',
    endpoint: 'Endpoint personalizado (opcional)',
    endpointHint: 'Deixe em branco para usar o padrão do provedor. Necessário para Ollama e modelos auto-hospedados.',
    maxTokens: 'Tokens máximos',
    temperature: 'Temperatura (0-2)',
    systemPrompt: 'Prompt de sistema',
    systemPromptPh: 'Você é um assistente prestativo…',
    systemPromptHint: 'Instrução opcional enviada antes de cada conversa.',
    notes: 'Observações',
    cancel: 'Cancelar',
    create: 'Criar',
    saveChanges: 'Salvar Alterações',
    saving: 'Salvando…',
    deleteTitle: 'Confirmar exclusão',
    deleteMessage: 'Esta ação não pode ser desfeita.',
    chatTitle: 'Assistente IA',
    chatOpen: 'Abrir assistente de IA',
    chatClose: 'Fechar assistente',
    chatHistory: 'Histórico de conversas',
    chatNew: 'Nova conversa',
    chatExpand: 'Expandir para tela cheia',
    chatCollapse: 'Voltar ao canto',
    chatHide: 'Ocultar o botão do assistente',
    chatShow: 'Mostrar o assistente',
    chatProvider: 'Provedor de IA',
    chatProviderDefault: 'padrão',
    chatNoHistory: 'Nenhuma conversa anterior',
    chatLoadMore: 'Ver mais',
    chatEmpty: 'Pergunte qualquer coisa — estou aqui para ajudar!',
    chatPlaceholder: 'Digite uma mensagem…',
    chatSend: 'Enviar',
    chatThinking: 'O assistente está respondendo…',
    chatKeyboard: 'Enter para enviar · Shift+Enter para nova linha',
    chatError: 'Ocorreu um erro. Tente novamente.',
    attach: 'Anexar arquivo',
    attachHint: 'Anexe, cole (Ctrl+V) ou arraste um arquivo',
    attachDrop: 'Solte o arquivo para anexar',
    attachRemove: 'Remover {name}',
    attachBlocked: 'Este provedor não aceita {formats}.',
    commands: 'Comandos',
    confirm: 'Confirmar',
    cardCancel: 'Cancelar',
    moreRows: 'Mostrando {shown} de {total}',
    statusConfirmed: 'Confirmado',
    statusCancelled: 'Cancelado',
    statusExpired: 'Expirado',
    statusFailed: 'Falhou',
};

const dictionaries: Record<string, AiWords> = { en, pt: ptBR };

/** The words of the AI screens for a locale (`pt_BR`, `pt-BR`, `en`…); English when unknown. */
export function aiWords(locale?: string | null): AiWords {
    const lang = (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').toLowerCase().slice(0, 2);

    return dictionaries[lang] ?? en;
}

/** A word with its `{placeholders}` filled in. */
export function fill(text: string, values: Record<string, string | number>): string {
    return text.replace(/\{(\w+)\}/g, (match, key: string) => (key in values ? String(values[key]) : match));
}

/** `{first}–{last} of {total}` filled in. */
export function aiRangeText(t: AiWords, meta: AiListMeta): string {
    return fill(t.range, { first: meta.from ?? 0, last: meta.to ?? 0, total: meta.total });
}

/** The status line of a resolved card. */
export function cardStatusText(t: AiWords, status: AiCard['status']): string {
    return { confirmed: t.statusConfirmed, cancelled: t.statusCancelled, expired: t.statusExpired, failed: t.statusFailed, pending: '' }[status] ?? t.statusExpired;
}
