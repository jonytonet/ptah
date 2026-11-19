import { ApiError, PtahApi } from './api';
import { NO_FEATURES } from './types';
import type {
    Features,
    Field,
    FormPayload,
    ListState,
    LookupItem,
    Message,
    Meta,
    Preferences,
    Row,
    RowAction,
    RowId,
    Schema,
    Total,
    GroupBreak,
} from './types';

export interface Toast extends Message {
    id: number;
}

export interface FormState {
    mode: 'create' | 'edit';
    id: RowId | null;
    data: Record<string, unknown>;
    /** Labels of the searchdropdown values already chosen. */
    labels: Record<string, string>;
    files: Record<string, File>;
    errors: Record<string, string>;
    saving: boolean;
    /** Opened by "duplicate": a new record that starts from another one. */
    copy?: boolean;
    /** Grows on every opening (Livewire's `formInstanceKey`): a new opening resets what the form tracks. */
    instance?: number;
    /** The modal title and subtitle the server built ("Nova conta"). */
    title?: string;
    subtitle?: string;
    /** URLs of the stored images, by field. */
    previews?: Record<string, string>;
}

export interface CrudState {
    status: 'loading' | 'ready' | 'forbidden' | 'not-found' | 'error';
    schema: Schema | null;
    rows: Row[];
    meta: Meta | null;
    totals: Total[];
    breaks: GroupBreak[];
    list: ListState;
    preferences: Preferences | null;
    /** A listing request is in flight (the first load is `status: loading`). */
    busy: boolean;
    selection: string[];
    form: FormState | null;
    /** The record awaiting "delete?" confirmation. */
    deleting: RowId | null;
    /** The feature panel open over the screen (history, attachments, import…), with what it was opened for. */
    panel: Panel | null;
    toasts: Toast[];
}

export interface Panel {
    name: string;
    /** The record the panel is about, when it is about one. */
    id: RowId | null;
    payload: Record<string, unknown>;
}

export interface ControllerOptions {
    api?: PtahApi;
    /** Reopen where the user left and keep remembering (default true). */
    remember?: boolean;
    /** A row action of type `action` (a Livewire action in the config): yours to run. */
    onAction?: (name: string, row: Row, action: RowAction) => void;
    /** How long a toast stays, in ms (0 keeps it). */
    toastMs?: number;
    /** Opens the export/print URL (default: a new tab). */
    open?: (url: string) => void;
}

const emptyList = (): ListState => ({
    page: 1,
    per_page: 25,
    sort: 'id',
    direction: 'DESC',
    search: '',
    filters: {},
    operators: {},
    date_ranges: {},
    date_operators: {},
    quick_date: '',
    trashed: false,
});

/**
 * Everything a CRUD screen does, without a framework: the React and the Vue
 * components only draw this state and call these methods.
 */
export class CrudController {
    readonly api: PtahApi;
    private state: CrudState;
    private readonly listeners = new Set<() => void>();
    private readonly options: ControllerOptions;
    private requests = 0;
    private toastSeq = 0;
    private readonly timers = new Set<ReturnType<typeof setTimeout>>();

    constructor(
        public readonly screen: string,
        options: ControllerOptions = {},
    ) {
        this.options = options;
        this.api = options.api ?? new PtahApi();
        this.state = {
            status: 'loading',
            schema: null,
            rows: [],
            meta: null,
            totals: [],
            breaks: [],
            list: emptyList(),
            preferences: null,
            busy: false,
            selection: [],
            form: null,
            deleting: null,
            panel: null,
            toasts: [],
        };
    }

    // ── Store ───────────────────────────────────────────────────────────────

    getState = (): CrudState => this.state;

    subscribe = (listener: () => void): (() => void) => {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    };

    private set(patch: Partial<CrudState>): void {
        this.state = { ...this.state, ...patch };
        this.listeners.forEach((listener) => listener());
    }

    /** Stops timers; call when the screen is unmounted. */
    destroy(): void {
        this.timers.forEach(clearTimeout);
        this.timers.clear();
        this.listeners.clear();
    }

    private get remember(): boolean {
        return this.options.remember !== false;
    }

    // ── Loading ─────────────────────────────────────────────────────────────

    /** The schema and the first page — reopening where the user left. */
    async init(): Promise<void> {
        try {
            const [schema, listing] = await Promise.all([
                this.api.schema(this.screen),
                this.api.list(this.screen, {}, { restore: this.remember }),
            ]);

            this.set({
                status: 'ready',
                schema,
                preferences: schema.preferences,
                rows: listing.data,
                meta: listing.meta,
                totals: listing.totals,
                breaks: listing.breaks ?? [],
                list: this.normalise(listing.state),
            });
            this.say(listing.messages);
        } catch (error) {
            this.fail(error);
        }
    }

    private normalise(state: ListState): ListState {
        const map = <T>(value: unknown): Record<string, T> => (value && !Array.isArray(value) && typeof value === 'object' ? (value as Record<string, T>) : {});

        return {
            ...state,
            filters: map(state.filters),
            operators: map<string>(state.operators),
            date_ranges: map<string>(state.date_ranges),
            date_operators: map<string>(state.date_operators),
        };
    }

    private fail(error: unknown): void {
        const status = error instanceof ApiError ? error.status : 0;

        if (this.state.status === 'loading') {
            this.set({ status: status === 403 ? 'forbidden' : status === 404 ? 'not-found' : 'error', busy: false });

            return;
        }

        this.set({ busy: false });
        this.toast(error instanceof Error && error.message ? error.message : 'Error', 'danger');
    }

    /** Fetches the listing for the current state. Only the last request in flight counts. */
    async reload(): Promise<void> {
        const request = ++this.requests;
        this.set({ busy: true });

        try {
            const listing = await this.api.list(this.screen, this.state.list, { remember: this.remember });

            if (request !== this.requests) {
                return;
            }

            const known = new Set(listing.data.map((row) => String(row.id)));
            this.set({
                busy: false,
                rows: listing.data,
                meta: listing.meta,
                totals: listing.totals,
                breaks: listing.breaks ?? [],
                list: { ...this.state.list, page: listing.meta.page },
                selection: this.state.selection.filter((id) => known.has(id)),
            });
            this.say(listing.messages);
        } catch (error) {
            if (request === this.requests) {
                this.fail(error);
            }
        }
    }

    private change(patch: Partial<ListState>, keepPage = false): Promise<void> {
        this.set({ list: { ...this.state.list, ...patch, ...(keepPage ? {} : { page: patch.page ?? 1 }) } });

        return this.reload();
    }

    // ── Listing ─────────────────────────────────────────────────────────────

    /**
     * Changes any part of the listing state and reloads — what the filter
     * pieces build on (operators, date operators, quick dates…). Goes back to
     * the first page unless the patch names one.
     */
    patchList(patch: Partial<ListState>): Promise<void> {
        return this.change(patch);
    }

    /** What the screen has on; all off until the schema arrives. */
    get features(): Features {
        return this.state.schema?.features ?? NO_FEATURES;
    }

    /** Opens a feature panel (one at a time): `openPanel('history', row.id)`. */
    openPanel(name: string, id: RowId | null = null, payload: Record<string, unknown> = {}): void {
        this.set({ panel: { name, id, payload } });
    }

    closePanel(): void {
        this.set({ panel: null });
    }

    setSearch(search: string): Promise<void> {
        return this.change({ search });
    }

    /** A header click: a new column sorts ascending, the same one flips. */
    sortBy(column: string): Promise<void> {
        const { sort, direction } = this.state.list;
        const next = sort === column && direction.toUpperCase() === 'ASC' ? 'DESC' : 'ASC';

        return this.change({ sort: column, direction: next });
    }

    setPage(page: number): Promise<void> {
        return this.change({ page: Math.max(1, page) }, true);
    }

    setPerPage(per_page: number): Promise<void> {
        return this.change({ per_page });
    }

    setFilter(field: string, value: unknown): Promise<void> {
        const filters = { ...this.state.list.filters };

        if (value === '' || value === null || value === undefined) {
            delete filters[field];
        } else {
            filters[field] = value;
        }

        return this.change({ filters });
    }

    setDateRange(field: string, edge: 'start' | 'end', value: string): Promise<void> {
        const date_ranges = { ...this.state.list.date_ranges };
        const key = `${field}_${edge}`;

        if (value === '') {
            delete date_ranges[key];
        } else {
            date_ranges[key] = value;
        }

        return this.change({ date_ranges });
    }

    applyFilters(filters: Record<string, unknown>): Promise<void> {
        return this.change({ filters: { ...filters } });
    }

    clearFilters(): Promise<void> {
        return this.change({ search: '', filters: {}, operators: {}, date_ranges: {}, date_operators: {}, quick_date: '' });
    }

    toggleTrashed(): Promise<void> {
        this.set({ selection: [] });

        return this.change({ trashed: !this.state.list.trashed });
    }

    get hasFilters(): boolean {
        const { search, filters, date_ranges, quick_date } = this.state.list;
        // The advanced rows and the link filters (?f) of the filter panel.
        const extra = this.state.list as ListState & { advanced?: unknown[]; f?: Record<string, unknown> };

        return (
            search !== '' ||
            Object.keys(filters).length > 0 ||
            Object.keys(date_ranges).length > 0 ||
            quick_date !== '' ||
            (extra.advanced?.length ?? 0) > 0 ||
            Object.keys(extra.f ?? {}).length > 0
        );
    }

    // ── Selection and bulk actions ──────────────────────────────────────────

    toggleRow(id: RowId): void {
        const key = String(id);
        const selection = this.state.selection.includes(key) ? this.state.selection.filter((item) => item !== key) : [...this.state.selection, key];

        this.set({ selection });
    }

    toggleAll(): void {
        const all = this.state.rows.map((row) => String(row.id));
        const every = all.length > 0 && all.every((id) => this.state.selection.includes(id));

        this.set({ selection: every ? [] : all });
    }

    clearSelection(): void {
        this.set({ selection: [] });
    }

    async bulk(action: string): Promise<void> {
        if (this.state.selection.length === 0) {
            return;
        }

        try {
            const result = await this.api.bulk(this.screen, action, this.state.selection, this.state.list);
            this.say(result.messages);
            // What the server did not process stays selected.
            this.set({ selection: result.remaining ?? [] });
            await this.reload();
        } catch (error) {
            this.fail(error);
        }
    }

    // ── Form ────────────────────────────────────────────────────────────────

    private opening: 'create' | 'edit' | null = null;
    private instances = 0;
    /** Closing while a form is on its way drops the answer. */
    private openSeq = 0;

    /**
     * A form is on its way (the request of "New"/"Edit" is in flight): the
     * screen can show the modal with its loading state at once, as the
     * Livewire modal does. `state.form` stays null until the data arrives, so
     * nothing that follows the form sees an empty one.
     */
    get formOpening(): 'create' | 'edit' | null {
        return this.opening;
    }

    private startOpening(mode: 'create' | 'edit'): void {
        // A form already open (save & new, draft clear/revert) stays until replaced.
        if (!this.state.form) {
            this.opening = mode;
            this.set({});
        }
    }

    private endOpening(): void {
        if (this.opening !== null) {
            this.opening = null;
            this.set({});
        }
    }

    private formFrom(payload: FormPayload, mode: 'create' | 'edit', id: RowId | null): FormState {
        this.opening = null;

        return {
            mode,
            id,
            data: payload.data && !Array.isArray(payload.data) ? payload.data : {},
            labels: this.labels(payload.labels),
            files: {},
            errors: {},
            saving: false,
            instance: ++this.instances,
            title: typeof payload.title === 'string' ? payload.title : undefined,
            subtitle: typeof payload.subtitle === 'string' ? payload.subtitle : undefined,
            previews: this.labels(payload.previews),
        };
    }

    async openCreate(): Promise<void> {
        this.startOpening('create');
        const mine = ++this.openSeq;
        try {
            const blank = await this.api.blank(this.screen);
            if (mine !== this.openSeq) {
                return;
            }
            this.set({ form: this.formFrom(blank, 'create', null) });
        } catch (error) {
            this.endOpening();
            this.fail(error);
        }
    }

    async openEdit(id: RowId): Promise<void> {
        this.startOpening('edit');
        const mine = ++this.openSeq;
        try {
            const record = await this.api.show(this.screen, id);
            if (mine !== this.openSeq) {
                return;
            }
            this.set({ form: this.formFrom(record, 'edit', id) });
        } catch (error) {
            this.endOpening();
            this.fail(error);
        }
    }

    async duplicate(id: RowId): Promise<void> {
        this.startOpening('create');
        const mine = ++this.openSeq;
        try {
            const copy = await this.api.duplicate(this.screen, id);
            if (mine !== this.openSeq) {
                return;
            }
            this.set({ form: { ...this.formFrom(copy, 'create', null), copy: true } });
        } catch (error) {
            this.endOpening();
            this.fail(error);
        }
    }

    /** Where a redirect answered by a save goes (an after-save hook). Replaceable, e.g. by a router. */
    navigate: (url: string) => void = (url) => window.location.assign(url);

    private labels(labels: unknown): Record<string, string> {
        return labels && !Array.isArray(labels) && typeof labels === 'object' ? (labels as Record<string, string>) : {};
    }

    setField(field: string, value: unknown, label?: string): void {
        const form = this.state.form;
        if (!form) {
            return;
        }

        const errors = { ...form.errors };
        delete errors[field];

        this.set({
            form: {
                ...form,
                data: { ...form.data, [field]: value },
                labels: label === undefined ? form.labels : { ...form.labels, [field]: label },
                errors,
            },
        });
    }

    setFile(field: string, file: File | null): void {
        const form = this.state.form;
        if (!form) {
            return;
        }

        const files = { ...form.files };
        if (file) {
            files[field] = file;
        } else {
            delete files[field];
        }

        this.set({ form: { ...form, files } });
    }

    closeForm(): void {
        this.openSeq++;
        this.opening = null;
        this.set({ form: null });
    }

    /** Saves the form. True when it was saved (the form closes); false leaves the errors on it. */
    async save(): Promise<boolean> {
        const form = this.state.form;
        if (!form || form.saving) {
            return false;
        }

        this.set({ form: { ...form, saving: true, errors: {} } });

        try {
            const result = await this.api.save(this.screen, form.id, form.data, form.files);
            this.say(result.messages);

            if (!result.ok) {
                const errors = result.errors && !Array.isArray(result.errors) ? result.errors : {};
                this.set({ form: { ...(this.state.form ?? form), saving: false, errors } });

                return false;
            }

            this.set({ form: null });

            // An after-save hook sent the user elsewhere: the page goes, no reload.
            if (typeof result.redirect === 'string' && result.redirect !== '') {
                this.navigate(result.redirect);

                return true;
            }

            await this.reload();

            return true;
        } catch (error) {
            this.set({ form: { ...(this.state.form ?? form), saving: false } });
            this.fail(error);

            return false;
        }
    }

    /** Options of a searchdropdown field, for the form or for the filter panel. */
    lookup(field: string, query: string, forFilter = false): Promise<LookupItem[]> {
        const context = forFilter ? this.state.list.filters : (this.state.form?.data ?? {});

        return this.api.lookup(this.screen, field, query, context, forFilter).catch(() => []);
    }

    field(name: string): Field | undefined {
        return this.state.schema?.form.find((item) => item.field === name);
    }

    // ── Delete / restore ────────────────────────────────────────────────────

    askDelete(id: RowId): void {
        this.set({ deleting: id });
    }

    cancelDelete(): void {
        this.set({ deleting: null });
    }

    async confirmDelete(): Promise<void> {
        const id = this.state.deleting;
        if (id === null) {
            return;
        }

        this.set({ deleting: null });

        try {
            const result = await this.api.destroy(this.screen, id);
            this.say(result.messages);
            await this.reload();
        } catch (error) {
            this.fail(error);
        }
    }

    async restore(id: RowId): Promise<void> {
        try {
            const result = await this.api.restore(this.screen, id);
            this.say(result.messages);
            await this.reload();
        } catch (error) {
            this.fail(error);
        }
    }

    // ── Row actions, export, preferences ────────────────────────────────────

    /** Runs a configured row action. A link navigates by itself (it is an `<a>`). */
    runAction(row: Row, action: RowAction): void {
        if (action.type === 'action') {
            this.options.onAction?.(action.value, row, action);
        }
    }

    async export(format: string): Promise<void> {
        try {
            const result = await this.api.export(this.screen, format, this.state.list, this.state.selection);
            this.say(result.messages);

            if (result.url) {
                (this.options.open ?? ((url: string) => window.open(url, '_blank')))(result.url);
            }
        } catch (error) {
            this.fail(error);
        }
    }

    /** Shows or hides a column, and keeps the choice. */
    async setColumnVisible(field: string, visible: boolean): Promise<void> {
        await this.savePreferences({ columns: { [field]: visible } });
    }

    async savePreferences(changes: Partial<Preferences>): Promise<void> {
        try {
            const preferences = await this.api.updatePreferences(this.screen, changes);
            // The visible columns are part of the schema.
            const schema = await this.api.schema(this.screen);
            this.set({ preferences, schema });
            await this.reload();
        } catch (error) {
            this.fail(error);
        }
    }

    async saveFilter(name: string): Promise<void> {
        try {
            this.set({ preferences: await this.api.saveFilter(this.screen, name, this.state.list.filters) });
        } catch (error) {
            this.fail(error);
        }
    }

    async forgetFilter(name: string): Promise<void> {
        try {
            this.set({ preferences: await this.api.forgetFilter(this.screen, name) });
        } catch (error) {
            this.fail(error);
        }
    }

    // ── Toasts ──────────────────────────────────────────────────────────────

    private say(messages: Message[] | undefined): void {
        (messages ?? []).forEach((message) => this.toast(message.text, message.level));
    }

    toast(text: string, level = 'info'): void {
        if (!text) {
            return;
        }

        const id = ++this.toastSeq;
        this.set({ toasts: [...this.state.toasts, { id, text, level }] });

        const ms = this.options.toastMs ?? 4000;
        if (ms > 0) {
            const timer = setTimeout(() => {
                this.timers.delete(timer);
                this.dismiss(id);
            }, ms);
            this.timers.add(timer);
        }
    }

    dismiss(id: number): void {
        this.set({ toasts: this.state.toasts.filter((toast) => toast.id !== id) });
    }
}
