/**
 * Four smaller BaseCrud features for the React and Vue stacks — the form
 * draft, the form's server-side behaviours (onChange formulas, cascading
 * dropdowns, "save & add another"), master/detail and column management —
 * over `routes/api/crud-extras.php`. The rules are the engine's; what lives
 * here is the client half both frameworks share: the requests, the words,
 * the draft kept in localStorage, the expanded rows and their cache, the
 * column order maths and the column resize.
 */
import { ApiError } from '../api';
import type { PtahApi } from '../api';
import type { CrudController, FormState } from '../controller';
import type { Preferences, RowId } from '../types';

// ── Types ───────────────────────────────────────────────────────────────────

/** What the server says about the draft of one opening of the form. */
export interface DraftContext {
    /** `ptah:draft:{user}:{screen}:{new|edit:id}` — shared with the Livewire screen. */
    key: string;
    prefix: string;
    /** The user part of the key: another user's drafts in this browser are purged. */
    user: string;
    /** The fields a draft may carry (never passwords, files, `$hidden`, excluded…). */
    fields: string[];
    /** The values the form opened with. */
    original: Record<string, unknown>;
    /** The fingerprint of the edited record ('' for a new one). */
    version: string;
    ttlDays: number;
    restored: boolean;
    discarded: boolean;
    data: Record<string, unknown>;
    labels: Record<string, string>;
}

/** What is kept in localStorage — the Livewire modal's own shape. */
export interface StoredDraft {
    v: Record<string, unknown>;
    version: string;
    at: number;
    ttl: number;
}

export interface FieldPatch {
    /** The form fields whose value the server changed (null = cleared). */
    data: Record<string, unknown>;
    labels: Record<string, string>;
}

/**
 * One detail of a row: a whole CRUD screen of the child model, served by the
 * same API under a key the server derives (`{screen}..{id}.{index}`) and
 * binds, on every request, to that row — mount `<PtahCrud screen={screen}>`.
 */
export interface DetailScreen {
    index: number;
    title: string;
    /** The child screen's key: never built by the client. */
    screen: string;
    /** False when the user may not read that detail (or it has no config). */
    available: boolean;
}

export interface ColumnItem {
    field: string;
    label: string;
    visible: boolean;
}

// ── Client ──────────────────────────────────────────────────────────────────

const base = (screen: string) => `/crud/${encodeURIComponent(screen)}`;

const map = <T>(value: unknown): Record<string, T> => (value && typeof value === 'object' && !Array.isArray(value) ? (value as Record<string, T>) : {});

/** The draft context of an opening (`id` null = the create form); with `draft`, also restores it. */
export async function draftContext(api: PtahApi, screen: string, id: RowId | null, draft?: { values: Record<string, unknown>; version: string }): Promise<DraftContext> {
    const raw = await api.call<Partial<DraftContext>>('POST', `${base(screen)}/form/draft`, { ...(id === null ? {} : { id }), ...(draft ? { draft } : {}) });

    return {
        key: String(raw.key ?? ''),
        prefix: String(raw.prefix ?? ''),
        user: String(raw.user ?? ''),
        fields: Array.isArray(raw.fields) ? raw.fields.map(String) : [],
        original: map(raw.original),
        version: String(raw.version ?? ''),
        ttlDays: Number(raw.ttlDays ?? 7),
        restored: Boolean(raw.restored),
        discarded: Boolean(raw.discarded),
        data: map(raw.data),
        labels: map<string>(raw.labels),
    };
}

/** What the server does when a field changes (formula, cascade): the patch to apply. */
export async function changeField(api: PtahApi, screen: string, id: RowId | null, data: Record<string, unknown>, field: string): Promise<FieldPatch> {
    const raw = await api.call<Partial<FieldPatch> & { errors?: unknown }>('POST', `${base(screen)}/form/change`, { ...(id === null ? {} : { id }), data, field });

    return { data: map(raw?.data), labels: map<string>(raw?.labels) };
}

/** The form fields whose change the server must hear about. */
export async function formTriggers(api: PtahApi, screen: string): Promise<string[]> {
    const raw = await api.call<{ fields?: string[] }>('GET', `${base(screen)}/form/triggers`);

    return Array.isArray(raw?.fields) ? raw.fields.map(String) : [];
}

/** The detail screens of a row. */
export async function rowDetails(api: PtahApi, screen: string, id: RowId): Promise<DetailScreen[]> {
    const raw = await api.call<{ data?: Partial<DetailScreen>[] }>('GET', `${base(screen)}/${encodeURIComponent(String(id))}/details`);

    return Array.isArray(raw?.data)
        ? raw.data
              .filter((item) => typeof item?.screen === 'string' && item.screen !== '')
              .map((item) => ({ index: Number(item.index ?? 0), title: String(item.title ?? ''), screen: String(item.screen), available: Boolean(item.available) }))
        : [];
}

/** Every column the user may show or hide, with its label, in the current order. */
export async function listColumns(api: PtahApi, screen: string): Promise<ColumnItem[]> {
    const raw = await api.call<{ data?: ColumnItem[] }>('GET', `${base(screen)}/columns`);

    return Array.isArray(raw?.data) ? raw.data : [];
}

/** Back to the default layout; with `field`, forgets only that column's width. */
export function resetColumns(api: PtahApi, screen: string, field?: string): Promise<{ preferences: Preferences; data: ColumnItem[] }> {
    return api.call('POST', `${base(screen)}/columns/reset`, field === undefined ? {} : { field });
}

// ── Words ───────────────────────────────────────────────────────────────────

const en = {
    loading: 'Loading…',
    error: 'Something went wrong. Try again.',
    retry: 'Try again',
    cancel: 'Cancel',
    // Form draft
    draftFound: 'An unsaved draft of this form was found.',
    draftRestore: 'Restore draft',
    draftDiscard: 'Discard draft',
    draftRestored: 'Your unsaved draft was restored.',
    draftDiscarded: 'This record changed since your draft, so the draft was discarded.',
    draftKept: 'Your changes are kept as a draft in this browser until you save.',
    draftClear: 'Clear all',
    draftRevert: 'Revert to original',
    // Form
    saveAndNew: 'Save & add another',
    // Master / detail
    showDetails: 'Show details',
    hideDetails: 'Hide details',
    detailsError: 'Could not load the details.',
    detailsEmpty: 'No records found',
    detailUnavailable: 'You cannot see these records.',
    // Columns
    columns: 'Columns',
    hiddenCount: ':count hidden',
    showAll: 'Show all',
    hideAll: 'Hide all',
    resetColumns: 'Reset layout',
    resetConfirm: 'Show every column again and forget the order and the widths you chose?',
    resetConfirmButton: 'Reset',
    moveUp: 'Move :label up',
    moveDown: 'Move :label down',
    dragHint: 'Drag to reorder, or use the arrows',
    columnsError: 'Could not load the columns.',
    noColumns: 'No columns to choose.',
    resizeColumn: 'Resize column :label',
};

export type ExtrasWords = typeof en;

const ptBR: ExtrasWords = {
    loading: 'Carregando…',
    error: 'Algo deu errado. Tente de novo.',
    retry: 'Tentar novamente',
    cancel: 'Cancelar',
    draftFound: 'Encontramos um rascunho não salvo deste formulário.',
    draftRestore: 'Restaurar rascunho',
    draftDiscard: 'Descartar rascunho',
    draftRestored: 'Seu rascunho não salvo foi restaurado.',
    draftDiscarded: 'Este registro mudou desde o seu rascunho, e o rascunho foi descartado.',
    draftKept: 'Suas alterações ficam guardadas como rascunho neste navegador até você salvar.',
    draftClear: 'Limpar tudo',
    draftRevert: 'Voltar ao original',
    saveAndNew: 'Salvar e criar outro',
    showDetails: 'Ver detalhes',
    hideDetails: 'Ocultar detalhes',
    detailsError: 'Não foi possível carregar os detalhes.',
    detailsEmpty: 'Nenhum registro encontrado',
    detailUnavailable: 'Você não pode ver estes registros.',
    columns: 'Colunas',
    hiddenCount: ':count ocultas',
    showAll: 'Mostrar todas',
    hideAll: 'Ocultar todas',
    resetColumns: 'Restaurar padrão',
    resetConfirm: 'Mostrar todas as colunas de novo e esquecer a ordem e as larguras que você escolheu?',
    resetConfirmButton: 'Restaurar',
    moveUp: 'Mover :label para cima',
    moveDown: 'Mover :label para baixo',
    dragHint: 'Arraste para reordenar, ou use as setas',
    columnsError: 'Não foi possível carregar as colunas.',
    noColumns: 'Nenhuma coluna para escolher.',
    resizeColumn: 'Redimensionar coluna :label',
};

const dictionaries: Record<string, ExtrasWords> = { en, pt: ptBR };

/** The words of these pieces for a locale (`pt_BR`, `pt-BR`, `en`…); English when unknown. */
export function extrasWords(locale?: string | null): ExtrasWords {
    const lang = (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').toLowerCase().slice(0, 2);

    return dictionaries[lang] ?? en;
}

/** `Move :label up` → `Move Name up`. */
export const fill = (template: string, values: Record<string, string | number>) => template.replace(/:(\w+)/g, (whole, name: string) => (name in values ? String(values[name]) : whole));

const message = (error: unknown, fallback: string) => (error instanceof Error && error.message ? error.message : fallback);

// ── A tiny store (both frameworks subscribe to it) ──────────────────────────

class Store<T> {
    private readonly listeners = new Set<() => void>();

    constructor(protected value: T) {}

    get = (): T => this.value;

    subscribe = (listener: () => void): (() => void) => {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    };

    protected put(patch: Partial<T>): void {
        this.value = { ...this.value, ...patch };
        this.listeners.forEach((listener) => listener());
    }
}

// ── Form draft ──────────────────────────────────────────────────────────────

const DRAFT_PREFIX = 'ptah:draft:';

function storage(): Storage | null {
    try {
        return typeof localStorage !== 'undefined' ? localStorage : null;
    } catch {
        return null;
    }
}

const norm = (value: unknown) => (value === null || value === undefined ? '' : typeof value === 'object' ? JSON.stringify(value) : String(value));

/** Field by field, never the whole object: only what may go into a draft. */
function pick(data: Record<string, unknown>, fields: string[]): Record<string, unknown> {
    const out: Record<string, unknown> = {};
    fields.forEach((field) => {
        const value = data[field];
        out[field] = value === undefined ? null : value !== null && typeof value === 'object' ? JSON.parse(JSON.stringify(value)) : value;
    });

    return out;
}

const differs = (values: Record<string, unknown>, original: Record<string, unknown>, fields: string[]) => fields.some((field) => norm(values[field]) !== norm(original[field]));

const fresh = (item: StoredDraft | null): item is StoredDraft => Boolean(item && item.v && item.at && !(item.ttl && Date.now() - item.at > item.ttl * 86400000));

/** Drops another user's drafts and the expired ones — what the Livewire modal does on load. */
export function purgeDrafts(user: string | null, store: Storage | null = storage()): void {
    if (!store) {
        return;
    }
    try {
        const now = Date.now();
        for (const key of Object.keys(store)) {
            if (!key.startsWith(DRAFT_PREFIX)) {
                continue;
            }
            if (user && !key.startsWith(`${DRAFT_PREFIX}${user}:`)) {
                store.removeItem(key);
                continue;
            }
            const item = JSON.parse(store.getItem(key) ?? 'null') as StoredDraft | null;
            if (!item || !item.at || (item.ttl && now - item.at > item.ttl * 86400000)) {
                store.removeItem(key);
            }
        }
    } catch {
        // Storage full, blocked or a foreign value: drafts are a convenience.
    }
}

function readDraft(key: string, store: Storage | null): StoredDraft | null {
    try {
        return store ? (JSON.parse(store.getItem(key) ?? 'null') as StoredDraft | null) : null;
    } catch {
        return null;
    }
}

function forget(key: string, store: Storage | null): void {
    try {
        store?.removeItem(key);
    } catch {
        // see purgeDrafts
    }
}

export interface DraftView {
    /** off: no draft for this opening (feature off, a copy, no form); ready: kept while typing. */
    phase: 'off' | 'loading' | 'ready' | 'error';
    mode: 'new' | 'edit' | null;
    /** The form differs from what it opened with — and that is kept as a draft. */
    dirty: boolean;
    /** A stored draft was found after the user had already typed: restore it or not. */
    offer: boolean;
    busy: boolean;
}

const OFF: DraftView = { phase: 'off', mode: null, dirty: false, offer: false, busy: false };

const formKey = (form: FormState) => `${form.mode}:${form.id ?? ''}`;

/**
 * The browser half of the form draft, framework-free. Started inside the
 * form, it follows the controller: a form opens → asks the server for the
 * draft context, purges, restores (or offers to); typing → writes the draft
 * (debounced); a successful save → forgets it; closing → keeps it.
 */
export class FormDraftSession extends Store<DraftView> {
    private lastForm: FormState | null = null;
    private context: DraftContext | null = null;
    private initial: Record<string, unknown> = {};
    private seq = 0;
    private timer: ReturnType<typeof setTimeout> | null = null;
    private stopListening: (() => void) | null = null;

    constructor(
        private readonly crud: CrudController,
        private readonly words: ExtrasWords,
        private readonly store: Storage | null = storage(),
        private readonly delay = 400,
    ) {
        super(OFF);
    }

    start(): void {
        if (this.stopListening) {
            return;
        }
        this.stopListening = this.crud.subscribe(() => this.follow());
        this.follow();
    }

    /** Unmounted: a successful save forgets, anything else is kept. */
    stop(): void {
        this.stopListening?.();
        this.stopListening = null;
        this.follow();
        if (this.lastForm) {
            this.flush();
        }
        this.end();
        // A later start() (React's StrictMode remounts) opens the form afresh.
        this.lastForm = null;
    }

    private follow(): void {
        if (!this.crud.features.formDraft) {
            this.lastForm = this.crud.getState().form;

            return;
        }

        const form = this.crud.getState().form;
        const last = this.lastForm;
        this.lastForm = form;

        if (!form) {
            if (last && last.saving) {
                // Saved: this draft is over.
                if (this.context) {
                    forget(this.context.key, this.store);
                }
            } else if (last) {
                this.lastForm = last;
                this.flush();
                this.lastForm = null;
            }
            if (last) {
                this.end();
            }

            return;
        }

        if (!last || formKey(form) !== formKey(last)) {
            void this.open(form);

            return;
        }

        if (form.data !== last.data) {
            this.schedule();
        }
    }

    private end(): void {
        this.seq++;
        this.clearTimer();
        this.context = null;
        this.put(OFF);
    }

    private clearTimer(): void {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
    }

    private async open(form: FormState): Promise<void> {
        const mine = ++this.seq;
        const mode = form.mode === 'edit' ? 'edit' : 'new';
        this.clearTimer();
        this.context = null;
        this.initial = { ...form.data };
        this.put({ phase: 'loading', mode, dirty: false, offer: false, busy: false });

        let context: DraftContext;
        try {
            context = await draftContext(this.crud.api, this.crud.screen, mode === 'edit' ? form.id : null);
        } catch (error) {
            if (mine === this.seq) {
                // 403/404: no draft for this opening; anything else: say nothing either,
                // the form works without it.
                this.put({ phase: error instanceof ApiError && error.status >= 500 ? 'error' : 'off' });
            }

            return;
        }
        if (mine !== this.seq) {
            return;
        }

        purgeDrafts(context.user, this.store);

        // A "new" form that did not open with the defaults is a copy
        // (duplicate): no draft for it, as in the Livewire modal.
        if (form.copy || (mode === 'new' && differs(pick(this.initial, context.fields), context.original, context.fields))) {
            this.put({ phase: 'off' });

            return;
        }

        this.context = context;
        this.put({ phase: 'ready' });

        const item = readDraft(context.key, this.store);
        if (!fresh(item)) {
            this.schedule();

            return;
        }
        if ((item.version || '') !== context.version) {
            // The record changed since: dropped rather than written over someone else's change.
            forget(context.key, this.store);
            this.crud.toast(this.words.draftDiscarded, 'warn');

            return;
        }
        if (!differs(item.v, context.original, context.fields)) {
            return;
        }

        const now = this.crud.getState().form;
        const untouched = now !== null && !differs(pick(now.data, context.fields), pick(this.initial, context.fields), context.fields);
        if (untouched) {
            await this.apply(item);
        } else {
            this.put({ offer: true });
        }
    }

    /** Accepts the offered draft. */
    async restore(): Promise<void> {
        const item = this.context ? readDraft(this.context.key, this.store) : null;
        this.put({ offer: false });
        if (fresh(item)) {
            await this.apply(item);
        }
    }

    /** Declines the offered draft: it is gone, what is typed stays. */
    discard(): void {
        if (this.context) {
            forget(this.context.key, this.store);
        }
        this.put({ offer: false });
        this.schedule();
    }

    /** Through the engine: only draftable fields, with the searchdropdown labels. */
    private async apply(item: StoredDraft): Promise<void> {
        const form = this.crud.getState().form;
        const context = this.context;
        if (!form || !context) {
            return;
        }
        const mine = this.seq;
        this.put({ busy: true });

        try {
            const answer = await draftContext(this.crud.api, this.crud.screen, form.mode === 'edit' ? form.id : null, { values: item.v, version: item.version || '' });
            if (mine !== this.seq) {
                return;
            }
            if (answer.discarded) {
                forget(context.key, this.store);
                this.crud.toast(this.words.draftDiscarded, 'warn');
            } else if (answer.restored) {
                Object.entries(answer.data).forEach(([field, value]) => this.crud.setField(field, value, answer.labels[field]));
                this.put({ dirty: true });
                this.crud.toast(this.words.draftRestored, 'info');
            }
        } catch (error) {
            if (mine === this.seq) {
                this.crud.toast(message(error, this.words.error), 'danger');
            }
        } finally {
            if (mine === this.seq) {
                this.put({ busy: false });
            }
        }
    }

    /** "Clear all" (new): the form as it opens, the draft gone. */
    async clear(): Promise<void> {
        if (this.context) {
            forget(this.context.key, this.store);
        }
        this.put({ dirty: false });
        await this.crud.openCreate();
        this.reopen();
    }

    /** "Revert to original" (edit): the stored record again, the draft gone. */
    async revert(): Promise<void> {
        const form = this.crud.getState().form;
        if (this.context) {
            forget(this.context.key, this.store);
        }
        this.put({ dirty: false });
        if (form?.id !== null && form?.id !== undefined) {
            await this.crud.openEdit(form.id);
            this.reopen();
        }
    }

    /** The form was replaced without closing (clear/revert): a new opening. */
    private reopen(): void {
        const form = this.crud.getState().form;
        this.lastForm = form;
        if (form) {
            void this.open(form);
        }
    }

    private schedule(): void {
        if (!this.context || this.value.offer) {
            return;
        }
        this.clearTimer();
        this.timer = setTimeout(() => {
            this.timer = null;
            this.write();
        }, this.delay);
    }

    /** Writes now what a pending timer would have written. */
    private flush(): void {
        if (this.timer) {
            this.clearTimer();
            this.write();
        }
    }

    private write(): void {
        const context = this.context;
        const form = this.lastForm ?? this.crud.getState().form;
        // While a stored draft is offered, it is not overwritten.
        if (!context || !form || this.value.offer) {
            return;
        }
        const values = pick(form.data, context.fields);
        const dirty = differs(values, context.original, context.fields);
        try {
            if (dirty) {
                const item: StoredDraft = { v: values, version: context.version, at: Date.now(), ttl: context.ttlDays };
                this.store?.setItem(context.key, JSON.stringify(item));
            } else {
                this.store?.removeItem(context.key);
            }
        } catch {
            // see purgeDrafts
        }
        if (dirty !== this.value.dirty) {
            this.put({ dirty });
        }
    }
}

// ── Form behaviours ─────────────────────────────────────────────────────────

/**
 * Tells the server a field changed and applies what it answers — the
 * field's `colsOnChange` formula, the dependent dropdowns it clears. A field
 * the user changed again meanwhile keeps what was typed.
 */
export function applyFieldChange(crud: CrudController, field: string): Promise<void> {
    return fieldChange(crud, field, (apply) => apply());
}

/** `around` wraps the moment the patch is written into the form. */
async function fieldChange(crud: CrudController, field: string, around: (apply: () => void) => void): Promise<void> {
    const form = crud.getState().form;
    if (!form) {
        return;
    }

    try {
        const patch = await changeField(crud.api, crud.screen, form.mode === 'edit' ? form.id : null, form.data, field);
        const now = crud.getState().form;
        if (!now || formKey(now) !== formKey(form)) {
            return;
        }
        const fields = new Set([...Object.keys(patch.data), ...Object.keys(patch.labels)]);
        around(() =>
            fields.forEach((name) => {
                // Changed again by the user while the server answered: it stays as typed.
                if (norm(now.data[name]) !== norm(form.data[name])) {
                    return;
                }
                const value = name in patch.data ? patch.data[name] : now.data[name];
                crud.setField(name, value, name in patch.labels ? patch.labels[name] : undefined);
            }),
        );
    } catch (error) {
        crud.toast(message(error, 'Error'), 'danger');
    }
}

const triggersCache = new WeakMap<CrudController, Promise<string[]>>();

/**
 * Follows the open form and calls `applyFieldChange` when a trigger field
 * (a formula, a cascade parent) changes — debounced, as the Livewire
 * `wire:model.live.debounce`. Changes it applied itself do not re-trigger,
 * as server-side changes do not run the Livewire hook. Returns the stop.
 */
export function watchFormChanges(crud: CrudController, delay = 400): () => void {
    let triggers: string[] = [];
    let last: FormState | null = crud.getState().form;
    let applying = false;
    const timers = new Map<string, ReturnType<typeof setTimeout>>();

    let request = triggersCache.get(crud);
    if (!request) {
        request = formTriggers(crud.api, crud.screen).catch(() => []);
        triggersCache.set(crud, request);
    }
    void request.then((fields) => {
        triggers = fields;
    });

    const run = (field: string) => {
        timers.delete(field);
        void fieldChange(crud, field, (apply) => {
            applying = true;
            try {
                apply();
            } finally {
                applying = false;
            }
        });
    };

    const stop = crud.subscribe(() => {
        const form = crud.getState().form;
        const before = last;
        last = form;
        if (applying || !form || !before || formKey(form) !== formKey(before) || form.data === before.data) {
            return;
        }
        triggers.forEach((field) => {
            if (norm(form.data[field]) !== norm(before.data[field])) {
                clearTimeout(timers.get(field));
                timers.set(field, setTimeout(() => run(field), delay));
            }
        });
    });

    return () => {
        stop();
        timers.forEach(clearTimeout);
        timers.clear();
    };
}

/**
 * Saves and, when it was a new record and it saved, opens a blank form again
 * — the Blade footer's "Save & add another" (`saveAndNew`). On an edit it is
 * a plain save. True when it saved.
 */
export async function saveAndNew(crud: CrudController): Promise<boolean> {
    const form = crud.getState().form;
    if (!form) {
        return false;
    }
    const creating = form.mode === 'create';
    const saved = await crud.save();
    if (saved && creating) {
        await crud.openCreate();
    }

    return saved;
}

// ── Master / detail ─────────────────────────────────────────────────────────

export interface DetailEntry {
    status: 'loading' | 'ready' | 'error';
    details: DetailScreen[];
    error: string | null;
}

export interface DetailsView {
    expanded: string[];
    entries: Record<string, DetailEntry>;
}

/**
 * The expanded rows of a screen and the detail screens of each, fetched on
 * the first expansion and kept per row (collapsing does not forget them).
 */
export class DetailsStore extends Store<DetailsView> {
    constructor(private readonly crud: CrudController) {
        super({ expanded: [], entries: {} });
    }

    isExpanded(id: RowId): boolean {
        return this.value.expanded.includes(String(id));
    }

    toggle(id: RowId): void {
        const key = String(id);
        if (this.isExpanded(id)) {
            this.put({ expanded: this.value.expanded.filter((item) => item !== key) });

            return;
        }
        this.put({ expanded: [...this.value.expanded, key] });
        if (!this.value.entries[key] || this.value.entries[key].status === 'error') {
            void this.load(id);
        }
    }

    /** (Re)loads the detail screens of a row. */
    async load(id: RowId): Promise<void> {
        const key = String(id);
        this.entry(key, { status: 'loading', details: this.value.entries[key]?.details ?? [], error: null });
        try {
            this.entry(key, { status: 'ready', details: await rowDetails(this.crud.api, this.crud.screen, id), error: null });
        } catch (error) {
            this.entry(key, { status: 'error', details: [], error: error instanceof ApiError && error.status < 500 ? error.message : null });
        }
    }

    private entry(key: string, entry: DetailEntry): void {
        this.put({ entries: { ...this.value.entries, [key]: entry } });
    }
}

const detailStores = new WeakMap<CrudController, DetailsStore>();

/** The one details store of a screen — the toggle and the row share it. */
export function detailsOf(crud: CrudController): DetailsStore {
    let store = detailStores.get(crud);
    if (!store) {
        store = new DetailsStore(crud);
        detailStores.set(crud, store);
    }

    return store;
}

/** `background:#fee;color:red` → a style map (camelCase keys, as React wants them). */
export function styleMap(style: string | null, camel = true): Record<string, string> | undefined {
    if (!style) {
        return undefined;
    }
    const out: Record<string, string> = {};
    for (const rule of style.split(';')) {
        const [name, ...rest] = rule.split(':');
        if (name && rest.length) {
            const key = name.trim();
            out[camel ? key.replace(/-([a-z])/g, (_, letter: string) => letter.toUpperCase()) : key] = rest.join(':').trim();
        }
    }

    return out;
}

// ── Columns ─────────────────────────────────────────────────────────────────

/**
 * Every field of the visibility map in display order: the saved order
 * first, then the rest as the config lists them — the rule of
 * `getVisibleColumns()`.
 */
export function fullOrder(preferences: Preferences): string[] {
    const known = Object.keys(preferences.columns ?? {});
    const saved = (preferences.column_order ?? []).filter((field) => known.includes(field));

    return [...saved, ...known.filter((field) => !saved.includes(field))];
}

/** The menu's columns (labels from the server) in the order the user sees them. */
export function menuColumns(preferences: Preferences, items: ColumnItem[]): ColumnItem[] {
    const labels = new Map(items.map((item) => [item.field, item.label]));

    return fullOrder(preferences)
        .filter((field) => labels.has(field))
        .map((field) => ({ field, label: labels.get(field)!, visible: preferences.columns[field] !== false }));
}

/**
 * Moves one column of the menu to another menu position and answers the
 * whole new order — the fields the menu does not show (`id`) keep their place.
 */
export function moveColumn(order: string[], shown: string[], field: string, to: number): string[] {
    const from = shown.indexOf(field);
    if (from < 0 || to < 0 || to >= shown.length || from === to) {
        return order;
    }
    const moved = [...shown];
    moved.splice(from, 1);
    moved.splice(to, 0, field);

    const slots = new Set(shown);
    let next = 0;

    return order.map((item) => (slots.has(item) ? moved[next++] : item));
}

/** Saves a new order of the columns (the table and the menu follow the schema). */
export function saveColumnOrder(crud: CrudController, order: string[]): Promise<void> {
    return crud.savePreferences({ column_order: order });
}

/** Shows or hides every column the menu lists, in one write. */
export function setAllColumns(crud: CrudController, fields: string[], visible: boolean): Promise<void> {
    return crud.savePreferences({ columns: Object.fromEntries(fields.map((field) => [field, visible])) });
}

export const MIN_COLUMN_WIDTH = 60;

export interface ColumnResizeOptions {
    /** The resize handle (the element the pointer went down on). */
    handle: HTMLElement;
    /** The header cell to resize (default: the handle's closest `th`). */
    th?: HTMLElement | null;
    /** Called while dragging, with the new width. */
    onResize?: (width: number) => void;
    /** Called once, with the final width. */
    onCommit: (width: number) => void;
    min?: number;
}

/**
 * Pointer drag on a header edge → a width, framework-free: the header grows
 * while the pointer moves (as `ptahResizeStart`), the width is committed on
 * release. Returns a cancel.
 */
export function startColumnResize(event: PointerEvent | MouseEvent, options: ColumnResizeOptions): () => void {
    const handle = options.handle;
    const th = options.th ?? handle.closest('th');
    if (!th) {
        return () => undefined;
    }
    event.preventDefault();
    event.stopPropagation();

    const min = options.min ?? MIN_COLUMN_WIDTH;
    const startX = event.clientX;
    const startWidth = th.getBoundingClientRect().width || th.offsetWidth;
    let width = Math.max(min, Math.round(startWidth));
    const owner = th.ownerDocument;
    const body = owner.body;
    if ('pointerId' in event && typeof handle.setPointerCapture === 'function') {
        try {
            handle.setPointerCapture(event.pointerId);
        } catch {
            // jsdom and old browsers: the document listeners still work.
        }
    }

    const move = (moveEvent: PointerEvent | MouseEvent) => {
        width = Math.max(min, Math.round(startWidth + (moveEvent.clientX - startX)));
        th.style.width = `${width}px`;
        th.style.minWidth = `${width}px`;
        options.onResize?.(width);
    };
    // Pointer events where there are, mouse events otherwise — never both.
    const kind = typeof PointerEvent !== 'undefined' && event instanceof PointerEvent ? 'pointer' : 'mouse';
    const cleanup = () => {
        owner.removeEventListener(`${kind}move`, move as EventListener);
        owner.removeEventListener(`${kind}up`, up);
        body.style.cursor = '';
        body.style.userSelect = '';
    };
    const up = () => {
        cleanup();
        options.onCommit(width);
    };

    body.style.cursor = 'col-resize';
    body.style.userSelect = 'none';
    owner.addEventListener(`${kind}move`, move as EventListener);
    owner.addEventListener(`${kind}up`, up);

    return cleanup;
}

/**
 * Everything a resize handle needs, for one column of one screen: the
 * pointer drag, the keyboard alternative (←/→ by 10px, saved at once) and the
 * double-click that forgets the saved width. Each takes the handle element
 * (React's synthetic `currentTarget` is not the native event's).
 */
export function columnResizer(crud: CrudController, field: string) {
    const commit = (width: number) => crud.savePreferences({ column_widths: { [field]: Math.max(MIN_COLUMN_WIDTH, Math.round(width)) } });
    const saved = () => crud.getState().preferences?.column_widths?.[field] ?? null;
    const size = (th: HTMLElement | null, width: number | null) => {
        if (th) {
            th.style.width = width === null ? '' : `${width}px`;
            th.style.minWidth = `${width ?? MIN_COLUMN_WIDTH}px`;
        }
    };

    return {
        width: saved,
        pointerDown(event: PointerEvent | MouseEvent, handle: HTMLElement): void {
            if (event.button !== 0) {
                return;
            }
            startColumnResize(event, { handle, onCommit: (width) => void commit(width) });
        },
        keyDown(event: KeyboardEvent, handle: HTMLElement): void {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
                return;
            }
            event.preventDefault();
            const th = handle.closest('th');
            const current = saved() ?? Math.round(th?.getBoundingClientRect().width || th?.offsetWidth || 120);
            const width = Math.max(MIN_COLUMN_WIDTH, current + (event.key === 'ArrowRight' ? 10 : -10));
            size(th, width);
            void commit(width);
        },
        async reset(handle?: HTMLElement | null): Promise<void> {
            size(handle?.closest('th') ?? null, null);
            try {
                await resetColumns(crud.api, crud.screen, field);
                // Re-reads the preferences and the schema.
                await crud.savePreferences({});
            } catch (error) {
                crud.toast(message(error, 'Error'), 'danger');
            }
        },
    };
}
