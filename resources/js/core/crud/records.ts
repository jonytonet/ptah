/**
 * Record history and record attachments of a CRUD screen — the History and
 * Attachments buttons of the BaseCrud edit modal — over the records API
 * (`/crud/{screen}/{id}/history`, `/crud/{screen}/{id}/attachments…`).
 *
 * What is shown, who may see it and which record is reachable are the
 * server's decisions (HasCrudHistory / HasCrudAttachments, through the
 * screen's scope); these functions only ask and keep what React and Vue
 * share.
 */
import { ApiError } from '../api';
import type { CrudController } from '../controller';
import type { Message, RowId } from '../types';

// ── Types ───────────────────────────────────────────────────────────────────

export interface HistoryChange {
    label: string;
    /** Display values, already formatted by the server (`—` when empty). */
    old: string;
    new: string;
}

export interface HistoryEntry {
    when: string;
    who: string;
    /** `created`, `updated`, `deleted`, `restored`. */
    event: string;
    changes: HistoryChange[];
    /** Changed fields this screen does not show — counted, never listed. */
    hidden: number;
}

export interface AttachmentItem {
    id: number;
    name: string;
    size: string;
    when: string;
    mime: string | null;
    /** The download (a stream on the same origin), opened as a link. */
    url: string;
}

export interface AttachmentList {
    data: AttachmentItem[];
    count: number;
}

/** The answer of an upload or a removal: a 422 carries `errors` (`files`, `files.0`…). */
export interface AttachmentResult {
    ok: boolean;
    data: AttachmentItem[];
    count: number;
    errors: string[];
    messages: Message[];
}

/** The names of the panels these pieces answer to: `crud.openPanel(PANEL_HISTORY, id)`. */
export const PANEL_HISTORY = 'history';
export const PANEL_ATTACHMENTS = 'attachments';

// ── Client ──────────────────────────────────────────────────────────────────

const base = (crud: CrudController, id: RowId) => `/crud/${encodeURIComponent(crud.screen)}/${encodeURIComponent(String(id))}`;

export async function getHistory(crud: CrudController, id: RowId): Promise<HistoryEntry[]> {
    return (await crud.api.call<{ data: HistoryEntry[] }>('GET', `${base(crud, id)}/history`)).data ?? [];
}

export async function listAttachments(crud: CrudController, id: RowId): Promise<AttachmentList> {
    const raw = await crud.api.call<Partial<AttachmentList>>('GET', `${base(crud, id)}/attachments`);

    return { data: raw.data ?? [], count: raw.count ?? (raw.data ?? []).length };
}

/** The messages of a 422 (`{message, errors: {files: [..], 'files.0': [..]}}`), one per line. */
function errorsOf(raw: Record<string, unknown>): string[] {
    const errors = (raw.errors ?? {}) as Record<string, string[] | string>;
    const out = Object.values(errors).flatMap((messages) => (Array.isArray(messages) ? messages : [messages])).map(String);

    return out.length > 0 ? out : [String(raw.message ?? '')].filter(Boolean);
}

function resultOf(raw: Record<string, unknown> | null): AttachmentResult {
    const body = raw ?? {};

    if (body.ok === undefined) {
        return { ok: false, data: [], count: 0, errors: errorsOf(body), messages: [] };
    }

    return {
        ok: Boolean(body.ok),
        data: (body.data as AttachmentItem[]) ?? [],
        count: Number(body.count ?? 0),
        errors: [],
        messages: (body.messages as Message[]) ?? [],
    };
}

/** Multipart POST of `files[]` — the server checks count, size and type. */
export async function uploadAttachments(crud: CrudController, id: RowId, files: File[]): Promise<AttachmentResult> {
    const form = new FormData();
    files.forEach((file) => form.append('files[]', file));

    return resultOf(await crud.api.call<Record<string, unknown> | null>('POST', `${base(crud, id)}/attachments`, form));
}

export async function deleteAttachment(crud: CrudController, id: RowId, attachmentId: number): Promise<AttachmentResult> {
    return resultOf(await crud.api.call<Record<string, unknown> | null>('DELETE', `${base(crud, id)}/attachments/${attachmentId}`));
}

export async function attachmentCounts(crud: CrudController, ids: RowId[]): Promise<Record<string, number>> {
    if (ids.length === 0) {
        return {};
    }
    const query = ids.map((id) => `ids[]=${encodeURIComponent(String(id))}`).join('&');

    return (await crud.api.call<{ counts: Record<string, number> }>('GET', `/crud/${encodeURIComponent(crud.screen)}/attachments/counts?${query}`)).counts ?? {};
}

/** A refusal as words: 403 the permission, 404 the record out of reach, else the generic one. */
export function failureText(error: unknown, t: RecordsWords): string {
    if (error instanceof ApiError && error.status === 403) {
        return t.forbidden;
    }
    if (error instanceof ApiError && error.status === 404) {
        return t.notFound;
    }

    return t.loadError;
}

// ── Attachment counts of the rows: one request per listing, not per row ────

type CountsListener = () => void;

/**
 * The attachment counts of the screen's rows. Every button asks for its own
 * row; the asks of one tick go out together as ONE request (the ids of the
 * page). A new listing (another page, a search, a reload) clears what is
 * known, so the counts follow the rows.
 */
export class AttachmentCounts {
    private known = new Map<string, number>();
    private queued = new Set<string>();
    private inFlight = new Set<string>();
    private scheduled = false;
    private readonly listeners = new Set<CountsListener>();
    private rows: unknown;

    constructor(private readonly crud: CrudController) {
        this.rows = crud.getState().rows;
        crud.subscribe(() => {
            const rows = this.crud.getState().rows;
            if (rows !== this.rows) {
                this.rows = rows;
                this.known = new Map();
                this.emit();
            }
        });
    }

    subscribe = (listener: CountsListener): (() => void) => {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    };

    private emit(): void {
        this.listeners.forEach((listener) => listener());
    }

    /** The count of a row, or null while it is being fetched (the fetch is started). */
    get(id: RowId): number | null {
        const key = String(id);
        const value = this.known.get(key);
        if (value !== undefined) {
            return value;
        }
        if (!this.inFlight.has(key)) {
            this.queued.add(key);
            this.schedule();
        }

        return null;
    }

    /** A count the client knows for sure (the answer of an upload or a removal). */
    set(id: RowId, count: number): void {
        this.known.set(String(id), count);
        this.emit();
    }

    private schedule(): void {
        if (this.scheduled) {
            return;
        }
        this.scheduled = true;
        queueMicrotask(() => void this.flush());
    }

    private async flush(): Promise<void> {
        this.scheduled = false;
        const ids = [...this.queued];
        this.queued.clear();
        if (ids.length === 0 || !this.crud.features.attachments) {
            return;
        }
        ids.forEach((id) => this.inFlight.add(id));

        try {
            const counts = await attachmentCounts(this.crud, ids);
            ids.forEach((id) => this.known.set(id, Number(counts[id] ?? 0)));
        } catch {
            // No count is not an error of the screen: the button shows none.
            ids.forEach((id) => this.known.set(id, -1));
        } finally {
            ids.forEach((id) => this.inFlight.delete(id));
            this.emit();
        }
    }
}

const registry = new WeakMap<CrudController, AttachmentCounts>();

/** The one counts store of a screen. */
export function countsOf(crud: CrudController): AttachmentCounts {
    let counts = registry.get(crud);
    if (!counts) {
        counts = new AttachmentCounts(crud);
        registry.set(crud, counts);
    }

    return counts;
}

// ── Words ───────────────────────────────────────────────────────────────────

/** `{1} one|[2,*] :count many` → the form for `count`, as Laravel's trans_choice. */
export function plural(text: string, count: number): string {
    return (count === 1 ? text.split('|')[0] : (text.split('|')[1] ?? text)).replace(':count', String(count));
}

const en = {
    history: 'History',
    attachments: 'Attachments',
    historyTitle: 'Change history',
    historyEmpty: 'No change recorded for this record yet.',
    events: { created: 'Created', updated: 'Changed', deleted: 'Deleted', restored: 'Restored' } as Record<string, string>,
    became: 'became',
    hiddenFields: 'and 1 field not shown on this screen|and :count fields not shown on this screen',
    attachmentsTitle: 'Attachments',
    pick: 'Choose files',
    send: 'Upload 1 file|Upload :count files',
    attachmentsEmpty: 'No file attached yet.',
    deleteConfirm: 'Remove the attachment :name?',
    deleteTitle: 'Confirm removal',
    delete: 'Delete',
    cancel: 'Cancel',
    close: 'Close',
    loading: 'Loading…',
    uploading: 'Uploading…',
    download: 'Download :name',
    loadError: 'Could not load this. Try again.',
    forbidden: 'You do not have permission to perform this action.',
    notFound: 'This record is not available.',
};

export type RecordsWords = typeof en;

const ptBR: RecordsWords = {
    history: 'Histórico',
    attachments: 'Anexos',
    historyTitle: 'Histórico de alterações',
    historyEmpty: 'Nenhuma alteração registrada para este registro ainda.',
    events: { created: 'Criado', updated: 'Alterado', deleted: 'Excluído', restored: 'Restaurado' },
    became: 'passou a',
    hiddenFields: 'e 1 campo que esta tela não mostra|e :count campos que esta tela não mostra',
    attachmentsTitle: 'Anexos',
    pick: 'Escolher arquivos',
    send: 'Enviar 1 arquivo|Enviar :count arquivos',
    attachmentsEmpty: 'Nenhum arquivo anexado ainda.',
    deleteConfirm: 'Remover o anexo :name?',
    deleteTitle: 'Confirmar remoção',
    delete: 'Excluir',
    cancel: 'Cancelar',
    close: 'Fechar',
    loading: 'Carregando…',
    uploading: 'Enviando…',
    download: 'Baixar :name',
    loadError: 'Não foi possível carregar. Tente novamente.',
    forbidden: 'Você não tem permissão para realizar esta ação.',
    notFound: 'Este registro não está disponível.',
};

const dictionaries: Record<string, RecordsWords> = { en, pt: ptBR };

/** The words of the history and attachments pieces for a locale (`pt_BR`, `pt-BR`, `en`…); English when unknown. */
export function recordsWords(locale?: string | null): RecordsWords {
    const lang = (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').toLowerCase().slice(0, 2);

    return dictionaries[lang] ?? en;
}

/** Visually hidden, still read by screen readers (the kit's CSS has no such class). */
export const SR_ONLY = {
    position: 'absolute',
    width: '1px',
    height: '1px',
    padding: '0',
    margin: '-1px',
    overflow: 'hidden',
    clip: 'rect(0, 0, 0, 0)',
    whiteSpace: 'nowrap',
    border: '0',
} as const;
