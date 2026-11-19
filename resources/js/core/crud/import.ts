/**
 * Spreadsheet import — the wizard of the BaseCrud (HasCrudImport):
 * file → columns → review → done, over `/crud/{screen}/import`.
 *
 * The server keeps the uploaded file and answers an opaque token; every
 * later step sends the token and the mapping back, and the engine re-reads
 * and re-checks the file. Nothing here decides what is valid: the review and
 * the import are the server's.
 */
import { ApiError } from '../api';
import type { PtahApi } from '../api';
import type { CrudController } from '../controller';
import type { Message } from '../types';

// ── Types ───────────────────────────────────────────────────────────────────

/** A form field a spreadsheet column may fill. */
export interface ImportField {
    field: string;
    label: string;
    required: boolean;
}

/** What step 1 shows: limits and accepted types. */
export interface ImportInfo {
    max_rows: number;
    max_kb: number;
    extensions: string[];
    fields: ImportField[];
}

export interface ImportRowError {
    line: number;
    field: string;
    message: string;
}

export interface ImportPreview {
    total: number;
    valid: number;
    errors: ImportRowError[];
    /** Errors beyond the ones listed. */
    more_errors: number;
    /** Labels of required fields no column fills. */
    unmapped_required: string[];
}

export interface ImportResult {
    created: number;
    updated: number;
    /** Nothing was imported: the line the database refused, and why. */
    error: string | null;
}

/** The file was accepted: the token of the stored upload and where the columns start. */
export interface ImportUpload {
    token: string;
    headers: string[];
    /** One field (or '' = ignore) per header, in header order. */
    mapping: string[];
    fields: ImportField[];
}

/**
 * An answer of a step:
 *  - `upload`: the file was accepted;
 *  - `refused`: the file is refused (type, size, empty, too many rows) — back to step 1;
 *  - `preview`: the review (also when the run found problems);
 *  - `done`: the import ran (`result.error` set when nothing was imported);
 *  - `expired`: the token is gone (expired, closed elsewhere) — choose the file again.
 */
export type ImportAnswer =
    | { kind: 'upload'; upload: ImportUpload }
    | { kind: 'refused'; message: string }
    | { kind: 'preview'; preview: ImportPreview }
    | { kind: 'done'; result: ImportResult; messages: Message[] }
    | { kind: 'expired' };

// ── Client ──────────────────────────────────────────────────────────────────

const base = (screen: string) => `/crud/${encodeURIComponent(screen)}/import`;

/** `['sku', '', 'status']` → `{0: 'sku', 2: 'status'}`: the shape the engine reads. */
export function mappingBody(mapping: string[]): Record<string, string> {
    const out: Record<string, string> = {};
    mapping.forEach((field, index) => {
        if (field !== '') {
            out[String(index)] = field;
        }
    });

    return out;
}

/** The server's automatic mapping (`{index: field}`) as one entry per header. */
export function mappingList(headers: string[], mapping: Record<string, string> | null | undefined): string[] {
    return headers.map((_, index) => (mapping && typeof mapping[String(index)] === 'string' ? mapping[String(index)] : ''));
}

/** The Livewire rule for showing "Import N rows": nothing to fix and something to import. */
export function canRun(preview: ImportPreview | null): boolean {
    return preview !== null && preview.errors.length === 0 && preview.unmapped_required.length === 0 && preview.valid > 0;
}

function refusal(raw: Record<string, unknown>): string {
    const errors = (raw.errors ?? {}) as Record<string, string | string[]>;
    const first = errors.file ?? errors.mapping ?? Object.values(errors)[0];

    return (Array.isArray(first) ? first[0] : first) ?? String(raw.message ?? '');
}

/** A 404 of a step means the token is no longer there; anything else is thrown. */
async function step(api: PtahApi, path: string, body: unknown): Promise<ImportAnswer | Record<string, unknown>> {
    try {
        return (await api.call<Record<string, unknown> | null>('POST', path, body)) ?? {};
    } catch (error) {
        if (error instanceof ApiError && error.status === 404) {
            return { kind: 'expired' };
        }
        throw error;
    }
}

export const getImportInfo = (api: PtahApi, screen: string) => api.call<ImportInfo>('GET', base(screen));

export async function uploadImport(api: PtahApi, screen: string, file: File): Promise<ImportAnswer> {
    const form = new FormData();
    form.append('file', file);
    const raw = (await api.call<Record<string, unknown> | null>('POST', base(screen), form)) ?? {};

    if (raw.ok !== true || typeof raw.token !== 'string') {
        return { kind: 'refused', message: refusal(raw) };
    }

    const headers = (raw.headers as string[]) ?? [];

    return {
        kind: 'upload',
        upload: { token: raw.token, headers, mapping: mappingList(headers, raw.mapping as Record<string, string>), fields: (raw.fields as ImportField[]) ?? [] },
    };
}

export async function previewImport(api: PtahApi, screen: string, token: string, mapping: string[]): Promise<ImportAnswer> {
    const raw = await step(api, `${base(screen)}/preview`, { token, mapping: mappingBody(mapping) });

    if ('kind' in raw) {
        return raw as ImportAnswer;
    }

    return raw.preview ? { kind: 'preview', preview: raw.preview as ImportPreview } : { kind: 'refused', message: refusal(raw) };
}

export async function runImport(api: PtahApi, screen: string, token: string, mapping: string[]): Promise<ImportAnswer> {
    const raw = await step(api, `${base(screen)}/run`, { token, mapping: mappingBody(mapping) });

    if ('kind' in raw) {
        return raw as ImportAnswer;
    }
    if (raw.result) {
        return { kind: 'done', result: raw.result as ImportResult, messages: (raw.messages as Message[]) ?? [] };
    }

    return raw.preview ? { kind: 'preview', preview: raw.preview as ImportPreview } : { kind: 'refused', message: refusal(raw) };
}

/** Closing the wizard: the server drops the stored file now. Never fails the close. */
export const discardImport = (api: PtahApi, screen: string, token: string) =>
    api.call<unknown>('DELETE', `${base(screen)}/${encodeURIComponent(token)}`).catch(() => undefined);

// ── The wizard ──────────────────────────────────────────────────────────────

export type ImportStep = 1 | 2 | 3 | 4;

export interface ImportState {
    step: ImportStep;
    /** Step 1's limits are loading, loaded, or failed to load. */
    status: 'loading' | 'ready' | 'error';
    info: ImportInfo | null;
    /** A step is in flight: the buttons wait. */
    busy: boolean;
    /** The refusal of the file, under the file input (step 1). */
    fileError: string | null;
    /** A request that failed (not a refusal): network, 403, 500. */
    error: string | null;
    upload: ImportUpload | null;
    mapping: string[];
    preview: ImportPreview | null;
    result: ImportResult | null;
}

/**
 * The steps of the Livewire modal, without a framework: React and Vue only
 * draw this state. `crud` is the screen's controller — the wizard closes its
 * panel, reloads its listing after an import and shows the server's toasts.
 */
export class ImportWizard {
    private state: ImportState = { step: 1, status: 'loading', info: null, busy: false, fileError: null, error: null, upload: null, mapping: [], preview: null, result: null };
    private readonly listeners = new Set<() => void>();

    constructor(
        private readonly crud: CrudController,
        private readonly words: ImportWords,
    ) {}

    getState = (): ImportState => this.state;

    subscribe = (listener: () => void): (() => void) => {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    };

    private set(patch: Partial<ImportState>): void {
        this.state = { ...this.state, ...patch };
        this.listeners.forEach((listener) => listener());
    }

    private failed(error: unknown): void {
        this.set({ busy: false, error: error instanceof Error && error.message ? error.message : this.words.failed });
    }

    /** Opening: the limits of step 1 (the row limit of the hint, the accepted types). */
    async start(): Promise<void> {
        try {
            this.set({ status: 'ready', info: await getImportInfo(this.crud.api, this.crud.screen) });
        } catch (error) {
            this.set({ status: 'error' });
            this.failed(error);
        }
    }

    /** Step 1 → 2. A refused file stays on step 1 with the reason. */
    async choose(file: File | null | undefined): Promise<void> {
        if (!file || this.state.busy) {
            return;
        }
        this.set({ busy: true, fileError: null, error: null });

        try {
            const answer = await uploadImport(this.crud.api, this.crud.screen, file);
            if (answer.kind === 'upload') {
                this.set({ busy: false, step: 2, upload: answer.upload, mapping: answer.upload.mapping, preview: null, result: null });
            } else {
                this.set({ busy: false, fileError: answer.kind === 'refused' ? answer.message : this.words.failed });
            }
        } catch (error) {
            this.failed(error);
        }
    }

    setMapping(index: number, field: string): void {
        const mapping = [...this.state.mapping];
        mapping[index] = field;
        this.set({ mapping });
    }

    /** Step 2 → 1 starts over (Livewire's openImport); step 3 → 2 keeps the mapping. */
    back(): void {
        if (this.state.step === 3) {
            this.set({ step: 2, error: null });

            return;
        }
        this.discard();
        this.set({ step: 1, upload: null, mapping: [], preview: null, result: null, fileError: null, error: null });
    }

    /** Step 2 → 3: the review. */
    async next(): Promise<void> {
        const { upload, mapping, busy } = this.state;
        if (!upload || busy) {
            return;
        }
        this.set({ busy: true, error: null });

        try {
            this.answer(await previewImport(this.crud.api, this.crud.screen, upload.token, mapping));
        } catch (error) {
            this.failed(error);
        }
    }

    /** Step 3 → 4: the import. */
    async run(): Promise<void> {
        const { upload, mapping, busy, preview } = this.state;
        if (!upload || busy || !canRun(preview)) {
            return;
        }
        this.set({ busy: true, error: null });

        try {
            this.answer(await runImport(this.crud.api, this.crud.screen, upload.token, mapping));
        } catch (error) {
            this.failed(error);
        }
    }

    private answer(answer: ImportAnswer): void {
        switch (answer.kind) {
            case 'preview':
                this.set({ busy: false, step: 3, preview: answer.preview });
                break;
            case 'done':
                // The server drops the file after a run: nothing left to discard.
                this.set({ busy: false, step: 4, result: answer.result, upload: null });
                answer.messages.forEach((message) => this.crud.toast(message.text, message.level));
                if (!answer.result.error) {
                    void this.crud.reload();
                }
                break;
            case 'refused':
                // The engine re-checks the file on every step (a limit lowered meanwhile).
                this.set({ busy: false, step: 1, upload: null, mapping: [], preview: null, fileError: answer.message });
                break;
            default:
                this.set({ busy: false, step: 1, upload: null, mapping: [], preview: null, fileError: this.words.expired });
        }
    }

    private discard(): void {
        if (this.state.upload) {
            void discardImport(this.crud.api, this.crud.screen, this.state.upload.token);
        }
    }

    /** Closes the wizard (any step): the stored file goes now. */
    close(): void {
        this.discard();
        this.set({ upload: null });
        this.listeners.clear();
        this.crud.closePanel();
    }
}

// ── Words ───────────────────────────────────────────────────────────────────

const en = {
    button: 'Import',
    title: 'Import spreadsheet',
    stepUpload: 'File',
    stepMap: 'Columns',
    stepPreview: 'Review',
    stepDone: 'Done',
    uploadHint: 'CSV or Excel (.xlsx). The first row must be the column headers. Up to :max rows.',
    chooseFile: 'Spreadsheet file',
    reading: 'Reading the file…',
    loading: 'Loading…',
    mapHint: 'Match each spreadsheet column to a field. Columns left as "Ignore" are not imported.',
    ignore: 'Ignore',
    unmappedRequired: 'Required fields without a column: :fields',
    previewSummary: ':valid of :total rows are ready to import.',
    errorsTitle: 'Rows with problems',
    moreErrors: '… and :count more.',
    fixAndRetry: 'Fix these rows in the spreadsheet and upload it again — nothing is imported while any row has a problem.',
    line: 'Line',
    field: 'Column',
    problem: 'Problem',
    run: 'Import :count rows',
    done: 'Import finished: :created created, :updated updated.',
    back: 'Back',
    next: 'Next',
    close: 'Close',
    expired: 'The uploaded file is no longer available. Choose it again.',
    failed: 'The request failed. Try again.',
};

export type ImportWords = typeof en;

const ptBR: ImportWords = {
    button: 'Importar',
    title: 'Importar planilha',
    stepUpload: 'Arquivo',
    stepMap: 'Colunas',
    stepPreview: 'Revisão',
    stepDone: 'Concluído',
    uploadHint: 'CSV ou Excel (.xlsx). A primeira linha deve ter os nomes das colunas. Até :max linhas.',
    chooseFile: 'Arquivo da planilha',
    reading: 'Lendo o arquivo…',
    loading: 'Carregando…',
    mapHint: 'Associe cada coluna da planilha a um campo. Colunas marcadas como "Ignorar" não são importadas.',
    ignore: 'Ignorar',
    unmappedRequired: 'Campos obrigatórios sem coluna: :fields',
    previewSummary: ':valid de :total linhas prontas para importar.',
    errorsTitle: 'Linhas com problema',
    moreErrors: '… e mais :count.',
    fixAndRetry: 'Corrija essas linhas na planilha e envie de novo — nada é importado enquanto houver linha com problema.',
    line: 'Linha',
    field: 'Coluna',
    problem: 'Problema',
    run: 'Importar :count linhas',
    done: 'Importação concluída: :created criados, :updated atualizados.',
    back: 'Voltar',
    next: 'Avançar',
    close: 'Fechar',
    expired: 'O arquivo enviado não está mais disponível. Escolha-o de novo.',
    failed: 'A requisição falhou. Tente de novo.',
};

const dictionaries: Record<string, ImportWords> = { en, pt: ptBR };

/** The words of the import wizard for a locale (`pt_BR`, `pt-BR`, `en`…); English when unknown. */
export function importWords(locale?: string | null): ImportWords {
    const lang = (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').toLowerCase().slice(0, 2);

    return dictionaries[lang] ?? en;
}

/** `':valid of :total'` with `{valid: 2, total: 3}`. Longer names first, so `:count` never eats `:counter`. */
export function fill(template: string, params: Record<string, string | number>): string {
    return Object.keys(params)
        .sort((a, b) => b.length - a.length)
        .reduce((text, key) => text.split(`:${key}`).join(String(params[key])), template);
}

/** The file input's `accept`: `.csv,.txt,.xlsx,.xls`. */
export const acceptOf = (extensions: string[]) => extensions.map((ext) => `.${ext}`).join(',');
