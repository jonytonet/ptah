import { PtahApi } from '../../core/api';
import type { AiCard, AiChatMessage, AiConversationItem, AiModelForm, AiModelRow, AiProviderOption } from '../../core/admin/ai';

export interface FakeCall {
    method: string;
    path: string;
    query: URLSearchParams;
    body: Record<string, unknown> | null;
    form: FormData | null;
    accept: string;
}

const PER_PAGE = 20;
const TOKEN = 'Tok3nTok3nTok3nTok3nTok3n';

/**
 * A stand-in for the AI area of the admin API: the same routes and shapes as
 * AiApiController, over records in memory. `forbidden` answers 403 to the
 * configuration screen (no `ai.config`); `unavailable` is a chat without an
 * active provider. A message answers `Resposta para: <text>` — streamed in two
 * `delta` events when the request accepts `text/event-stream`. `/preview`
 * answers a preview card; `erro` a provider failure; `longo…` (over 20 chars)
 * a refusal before the turn.
 */
export function fakeAi(options: { forbidden?: boolean; unavailable?: boolean; models?: number; providers?: AiProviderOption[]; history?: number } = {}) {
    const calls: FakeCall[] = [];
    let seq = 100;

    const models: (AiModelRow & { form: AiModelForm })[] = [
        { id: 1, name: 'Principal', provider: 'openai', provider_label: 'OpenAI', model: 'gpt-4o-mini', notes: 'Conta da empresa', is_active: true, is_default: true, form: { name: 'Principal', provider: 'openai', model: 'gpt-4o-mini', api_key: '', api_endpoint: '', max_tokens: 1024, temperature: '0.70', system_prompt: '', notes: 'Conta da empresa', is_active: true, is_default: true } },
        { id: 2, name: 'Local', provider: 'ollama', provider_label: 'Ollama (Local)', model: 'llama3', notes: null, is_active: false, is_default: false, form: { name: 'Local', provider: 'ollama', model: 'llama3', api_key: '', api_endpoint: 'http://localhost:11434', max_tokens: 512, temperature: '0.20', system_prompt: '', notes: '', is_active: false, is_default: false } },
    ];
    for (let i = 0; i < (options.models ?? 0); i++) {
        const name = `Zeta ${String(i).padStart(2, '0')}`;
        models.push({ id: ++seq, name, provider: 'groq', provider_label: 'Groq', model: 'mixtral', notes: null, is_active: true, is_default: false, form: { name, provider: 'groq', model: 'mixtral', api_key: '', api_endpoint: '', max_tokens: 1024, temperature: '0.70', system_prompt: '', notes: '', is_active: true, is_default: false } });
    }
    const keys: Record<number, string> = { 1: 'sk-guardada' };

    const providers: AiProviderOption[] = options.providers ?? [
        { id: 1, name: 'Principal', provider: 'openai', model: 'gpt-4o-mini', is_default: true },
        { id: 3, name: 'Local', provider: 'ollama', model: 'llama3', is_default: false },
    ];
    const conversations: { id: number; title: string; messages: AiChatMessage[] }[] = [];
    for (let i = 1; i <= (options.history ?? 2); i++) {
        conversations.unshift({ id: i, title: `Conversa ${i}`, messages: [{ role: 'user', content: `pergunta ${i}`, attachments: [] }, { role: 'assistant', content: `resposta ${i}`, html: `<p>resposta ${i}</p>`, confirmations: [] }] });
    }
    let cards: Record<string, AiCard['status']> = {};

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    const invalid = (errors: Record<string, string>) => json(422, { message: 'The given data was invalid.', errors: Object.fromEntries(Object.entries(errors).map(([field, text]) => [field, [text]])) });
    const done = (text: string) => json(200, { ok: true, error: null, messages: [{ text, level: 'success' }] });

    const rowOf = (model: (typeof models)[number]): AiModelRow => ({ id: model.id, name: model.name, provider: model.provider, provider_label: model.provider_label, model: model.model, notes: model.notes, is_active: model.is_active, is_default: model.is_default });

    function list(query: URLSearchParams) {
        const search = (query.get('search') ?? '').toLowerCase();
        const direction = query.get('direction') === 'desc' ? 'desc' : 'asc';
        const all = models
            .filter((row) => search === '' || `${row.name} ${row.provider} ${row.model}`.toLowerCase().includes(search))
            .sort((a, b) => a.name.localeCompare(b.name) * (direction === 'asc' ? 1 : -1));
        const last = Math.max(1, Math.ceil(all.length / PER_PAGE));
        const current = Math.min(last, Math.max(1, Number(query.get('page') ?? 1)));
        const slice = all.slice((current - 1) * PER_PAGE, current * PER_PAGE).map(rowOf);

        return json(200, {
            data: slice,
            meta: { page: current, last_page: last, per_page: PER_PAGE, total: all.length, from: slice.length ? (current - 1) * PER_PAGE + 1 : null, to: slice.length ? (current - 1) * PER_PAGE + slice.length : null },
            state: { search, sort: 'name', direction },
            providers: [
                { value: 'openai', label: 'OpenAI' },
                { value: 'anthropic', label: 'Anthropic (Claude)' },
                { value: 'ollama', label: 'Ollama (Local)' },
                { value: 'openai_compatible', label: 'OpenAI-compatible (custom endpoint)' },
            ],
        });
    }

    function save(id: number | null, body: Record<string, unknown>) {
        const form = body as unknown as AiModelForm;
        const errors: Record<string, string> = {};
        if (!form.name) {
            errors.name = 'O campo nome é obrigatório.';
        }
        if (id === null && !form.api_key && form.provider !== 'ollama') {
            errors.api_key = 'O campo chave de API é obrigatório.';
        }
        if (form.provider === 'openai_compatible' && !form.api_endpoint) {
            errors.api_endpoint = 'O campo endpoint é obrigatório.';
        }
        if (Object.keys(errors).length) {
            return invalid(errors);
        }
        if (form.is_default) {
            models.forEach((model) => (model.is_default = false));
        }
        const label = { openai: 'OpenAI', anthropic: 'Anthropic (Claude)', ollama: 'Ollama (Local)' }[form.provider] ?? form.provider;
        const record = { id: id ?? ++seq, name: form.name, provider: form.provider, provider_label: label, model: form.model.trim(), notes: form.notes || null, is_active: form.is_active, is_default: form.is_default, form: { ...form, api_key: '' } };
        if (form.api_key) {
            keys[record.id] = form.api_key;
        }
        if (id === null) {
            models.push(record);

            return done('Configuração criada.');
        }
        models.splice(models.findIndex((model) => model.id === id), 1, record);

        return done('Configuração atualizada.');
    }

    function sse(events: { name: string; data: unknown }[]) {
        const encoder = new TextEncoder();
        const body = new ReadableStream<Uint8Array>({
            start(controller) {
                for (const event of events) {
                    controller.enqueue(encoder.encode(`event: ${event.name}\ndata: ${JSON.stringify(event.data)}\n\n`));
                }
                controller.close();
            },
        });

        return new Response(body, { status: 200, headers: { 'Content-Type': 'text/event-stream' } });
    }

    function turn(input: { message: string; conversation_id: number | null; files: string[] }, streaming: boolean) {
        if (input.message.length > 20) {
            return json(422, { ok: false, accepted: false, error: 'A mensagem passa de 20 caracteres.' });
        }
        if (input.message === '' && input.files.length === 0) {
            return json(422, { ok: false, accepted: false, error: 'O campo message é obrigatório.' });
        }
        let conversation = conversations.find((item) => item.id === input.conversation_id);
        if (input.conversation_id !== null && !conversation) {
            return json(404, { message: 'Not Found' });
        }
        if (!conversation) {
            conversation = { id: ++seq, title: input.message || input.files[0], messages: [] };
            conversations.unshift(conversation);
        }
        conversation.messages.push({ role: 'user', content: input.message, attachments: input.files });

        let reply: AiChatMessage | null;
        if (input.message === 'erro') {
            reply = null;
        } else if (input.message === '/preview') {
            cards[TOKEN] = 'pending';
            reply = { role: 'assistant', content: 'Revise e confirme.', html: '<p>Revise e confirme.</p>', confirmations: [{ token: TOKEN, summary: '2 produtos vão mudar.', columns: [{ key: 'id', label: 'ID' }, { key: 'qty', label: 'Qtd' }], rows: [['5', '10'], ['6', '0']], total_rows: 2, status: 'pending' }] };
        } else {
            const text = `Resposta para: ${input.message || input.files.join(', ')}`;
            reply = { role: 'assistant', content: text, html: `<p><strong>${text}</strong></p>`, confirmations: [] };
        }
        if (reply) {
            conversation.messages.push(reply);
        }
        const answer = {
            ok: reply !== null,
            accepted: true,
            error: reply === null ? 'O provedor falhou.' : null,
            conversation_id: conversation.id,
            messages: conversation.messages,
            conversations: history(5),
        };

        if (!streaming) {
            return json(reply === null ? 422 : 200, answer);
        }

        return sse([
            ...(reply ? [{ name: 'delta', data: { html: '<p>Respos</p>' } }, { name: 'delta', data: { html: reply.html } }] : []),
            { name: 'done', data: { ...answer, status: reply === null ? 422 : 200 } },
        ]);
    }

    const history = (limit: number): AiConversationItem[] => conversations.slice(0, limit).map((item) => ({ id: item.id, title: item.title, date: 'há 1 hora' }));

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(String(input), 'http://localhost');
        const path = url.pathname.replace('/ptah/api', '');
        const method = (init.method ?? 'GET').toUpperCase();
        const form = init.body instanceof FormData ? init.body : null;
        const body = typeof init.body === 'string' ? (JSON.parse(init.body) as Record<string, unknown>) : null;
        const accept = String((init.headers as Record<string, string> | undefined)?.Accept ?? '');
        calls.push({ method, path, query: url.searchParams, body, form, accept });

        const model = path.match(/^\/admin\/ai\/models(?:\/(\d+))?(\/default)?$/);
        if (model) {
            if (options.forbidden) {
                return json(403, { message: 'Você não tem permissão para acessar esta página.' });
            }
            const id = model[1] === undefined ? null : Number(model[1]);
            const found = models.find((row) => row.id === id);
            if (model[2]) {
                models.forEach((row) => (row.is_default = row.id === id));

                return done('Modelo padrão atualizado.');
            }
            switch (method) {
                case 'GET':
                    return id === null ? list(url.searchParams) : found ? json(200, { id, form: found.form }) : json(404, { message: 'Not found.' });
                case 'POST':
                    return save(null, body ?? {});
                case 'PUT':
                    return save(id, body ?? {});
                case 'DELETE':
                    models.splice(models.findIndex((row) => row.id === id), 1);

                    return done('Configuração excluída.');
            }
        }

        if (path.startsWith('/admin/ai/chat') && options.unavailable) {
            return path === '/admin/ai/chat' ? json(200, { available: false }) : json(404, { message: 'Not Found' });
        }

        if (path === '/admin/ai/chat') {
            const configId = Number(url.searchParams.get('config_id') ?? 1);
            const ollama = providers.find((option) => option.id === configId)?.provider === 'ollama';
            const latest = conversations[0];

            return json(200, {
                available: true,
                conversation_id: latest?.id ?? null,
                messages: latest?.messages ?? [],
                providers,
                selected_config_id: configId,
                commands: [
                    { name: 'preview', description: 'Mostra uma prévia' },
                    { name: 'pedidos', description: 'Lista pedidos' },
                ],
                history: true,
                conversations: history(5),
                history_limit: 5,
                attachments: { enabled: true, extensions: ollama ? ['png', 'txt'] : ['png', 'txt', 'pdf'], blocked: ollama ? ['pdf'] : [], accept: '', max_files: 2, max_size_kb: 8192 },
                stream: true,
                max_message_chars: 20,
            });
        }
        if (path === '/admin/ai/chat/conversations') {
            if (method === 'POST') {
                return json(200, { conversation_id: null, messages: [] });
            }
            const limit = Math.min(100, Number(url.searchParams.get('limit') ?? 5));

            return json(200, { conversations: history(limit), limit });
        }
        const one = path.match(/^\/admin\/ai\/chat\/conversations\/(\d+)$/);
        if (one) {
            const found = conversations.find((item) => item.id === Number(one[1]));

            return found ? json(200, { conversation_id: found.id, messages: found.messages }) : json(404, { message: 'Not Found' });
        }
        if (path === '/admin/ai/chat/messages') {
            const files = form ? (form.getAll('attachments[]') as File[]).map((file) => file.name) : [];
            const raw = form ? { message: form.get('message'), conversation_id: form.get('conversation_id'), stream: form.get('stream') === '1' } : (body ?? {});
            const conversation = raw.conversation_id === null || raw.conversation_id === undefined || raw.conversation_id === '' ? null : Number(raw.conversation_id);

            return turn({ message: String(raw.message ?? ''), conversation_id: conversation, files }, accept.includes('text/event-stream'));
        }
        if (path === '/admin/ai/chat/confirmations') {
            const token = String(body?.token ?? '');
            const conversation = conversations.find((item) => item.id === Number(body?.conversation_id));
            if (!conversation) {
                return json(404, { message: 'Not Found' });
            }
            if (cards[token] !== 'pending') {
                return json(422, { ok: false, error: 'Esta prévia expirou ou já foi usada.' });
            }
            cards = { ...cards, [token]: body?.confirm ? 'confirmed' : 'cancelled' };
            for (const message of conversation.messages) {
                for (const card of message.confirmations ?? []) {
                    if (card.token === token) {
                        card.status = cards[token];
                    }
                }
            }
            conversation.messages.push({ role: 'assistant', content: body?.confirm ? 'Corrigi 2 produtos.' : 'Cancelado — nada foi gravado.', html: body?.confirm ? '<p>Corrigi 2 produtos.</p>' : '<p>Cancelado — nada foi gravado.</p>', confirmations: [] });

            return json(200, { ok: true, error: null, conversation_id: conversation.id, messages: conversation.messages });
        }

        return json(404, { message: `No fake for ${method} ${path}` });
    };

    return { api: new PtahApi({ fetch: handler as typeof fetch }), calls, models, keys, conversations };
}
