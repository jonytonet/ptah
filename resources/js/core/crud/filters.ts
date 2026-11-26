/**
 * The filter panel of a CRUD screen — what _filter-panel.blade.php,
 * _url-filters-banner.blade.php, the filter chips and the search box of
 * _toolbar.blade.php and "filter by this value" of _table.blade.php do on the
 * Livewire screen: the types, the client of FiltersApiController, the rules
 * of each control (which keys of the listing state it changes, what it
 * clears with it) and the words. Nothing framework-specific: the React and
 * Vue pieces are drawn over this.
 *
 * The listing state itself lives in the CrudController: every change goes
 * through `crud.patchList()`, so the server's own validation (the engine's)
 * decides what filters.
 */
import { toQuery } from '../api';
import type { CrudController } from '../controller';
import type { ListState, LookupItem, Option } from '../types';

// ── Types ───────────────────────────────────────────────────────────────────

/** How a filter is drawn: the branches of _filter-panel.blade.php. */
export type FilterKind = 'text' | 'number' | 'date' | 'select' | 'searchdropdown';

export interface FilterMetaItem {
    field: string;
    label: string;
    /** The column type of the config (`colsTipo`, or `colsFilterType` of a custom filter). */
    type: string;
    kind: FilterKind;
    /** A custom filter (`customFilters`): one value, no operator. */
    custom: boolean;
    /** The operators offered, in the panel's order (empty: none). */
    operators: string[];
    /** The "from" and "to" operators of a date range. */
    date_operators: { start: string[]; end: string[] } | null;
    options: Option[] | null;
    /** The filter this searchdropdown's options depend on (cascade). */
    depends_on: string | null;
    /** Money kept in cents (1.62.0): typed in reais ("2.160,00"), so a text input. */
    money_cents?: boolean;
    placeholder: string | null;
    /** Where the options open: under the input or above it. */
    start_list: 'top' | 'bottom' | null;
}

export type AdvancedKind = 'text' | 'number' | 'date';

export interface FiltersMeta {
    filters: FilterMetaItem[];
    /** The "(empty)" / "(has value)" options of a select filter. */
    sentinels: { empty: string; filled: string };
    quick_dates: { enabled: boolean; column: string; periods: string[] };
    advanced: { enabled: boolean; fields: { field: string; label: string; kind: AdvancedKind }[]; operators: Record<AdvancedKind, string[]> };
    /** The columns whose cells offer "filter by this value". */
    cell_fields: string[];
    /** The labels of the searchdropdown values chosen (kept with the preferences). */
    labels: Record<string, string>;
}

/** One row of the advanced search. */
export interface AdvancedRow {
    field: string;
    operator: string;
    value: string;
    logic: 'AND' | 'OR';
}

/** The listing state with what the filter panel adds to it (sent with the listing; see FiltersRuntime). */
export interface FilterListState extends ListState {
    advanced?: AdvancedRow[];
    /** The link filters (`?f[...]`), until the panel takes over. */
    f?: Record<string, unknown>;
}

/** A link filter as the engine accepted it. */
export interface UrlFilter {
    field: string;
    label: string;
    op: string;
    value: string;
}

/** An active-filter chip. `key` is what removing it takes. */
export interface FilterBadge {
    key: string;
    label: string;
    value: string;
}

export interface CellFilterResult {
    ok: boolean;
    state: Pick<ListState, 'filters' | 'operators' | 'date_ranges' | 'date_operators'> | null;
}

// ── Client ──────────────────────────────────────────────────────────────────

const path = (crud: CrudController, rest: string) => `/crud/${encodeURIComponent(crud.screen)}/filters${rest}`;

/** How to draw the panel for this screen and user. */
export function fetchFiltersMeta(crud: CrudController): Promise<FiltersMeta> {
    return crud.api.call<FiltersMeta>('GET', path(crud, '/meta'));
}

/** The link filters the engine accepts, with their labels. */
export async function fetchUrlFilters(crud: CrudController, f: Record<string, unknown>): Promise<UrlFilter[]> {
    const query = toQuery({ f });

    return (await crud.api.call<{ data: UrlFilter[] }>('GET', path(crud, `/url${query ? `?${query}` : ''}`)))?.data ?? [];
}

/** Keeps (or forgets, with null) the labels of searchdropdown filters. */
export function storeLabels(crud: CrudController, labels: Record<string, string | null>, state: Partial<FilterListState> | null, replace = false): Promise<{ ok: boolean }> {
    return crud.api.call('PUT', path(crud, '/labels'), { labels, replace, ...(state ? { state } : {}) });
}

/** "Filter by this value": the filter the engine sets for a cell, or `ok: false` when it refuses the cell. */
export async function requestCellFilter(crud: CrudController, row: string | number, field: string, state: Partial<FilterListState>): Promise<CellFilterResult> {
    const result = await crud.api.call<CellFilterResult | null>('POST', path(crud, '/by-cell'), { row, field, state });

    return { ok: Boolean(result?.ok), state: result?.state ?? null };
}

// ── The panel's session: meta and labels, one per screen controller ─────────

export interface FilterSessionState {
    status: 'idle' | 'loading' | 'ready' | 'error';
    meta: FiltersMeta | null;
    /** Labels of the searchdropdown values chosen, by field. */
    labels: Record<string, string>;
    error: string | null;
}

/**
 * What every filter piece of one screen shares: the meta (fetched once) and
 * the searchdropdown labels. One per CrudController — see filterSession().
 */
export class FilterSession {
    private state: FilterSessionState = { status: 'idle', meta: null, labels: {}, error: null };
    private readonly listeners = new Set<() => void>();
    private loading: Promise<void> | null = null;

    constructor(private readonly crud: CrudController) {}

    getState = (): FilterSessionState => this.state;

    subscribe = (listener: () => void): (() => void) => {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    };

    private set(patch: Partial<FilterSessionState>): void {
        this.state = { ...this.state, ...patch };
        this.listeners.forEach((listener) => listener());
    }

    /** Loads the meta once (again after an error). */
    ensure(): Promise<void> {
        if (this.state.status === 'ready' || this.state.status === 'loading') {
            return this.loading ?? Promise.resolve();
        }
        this.set({ status: 'loading', error: null });
        this.loading = fetchFiltersMeta(this.crud)
            .then((meta) => {
                const labels = meta.labels && !Array.isArray(meta.labels) ? meta.labels : {};
                this.set({ status: 'ready', meta: { ...meta, labels }, labels: { ...labels, ...this.state.labels } });
            })
            .catch((error: unknown) => {
                this.set({ status: 'error', error: error instanceof Error && error.message ? error.message : 'Error' });
            })
            .finally(() => {
                this.loading = null;
            });

        return this.loading;
    }

    /**
     * Changes labels here at once and keeps them on the server with the
     * listing state they belong to. A failure there only costs the label on
     * the next opening: the filter itself is the listing's.
     */
    async setLabels(changes: Record<string, string | null>, state: Partial<FilterListState> | null, replace = false): Promise<void> {
        const labels = replace ? {} : { ...this.state.labels };
        for (const [field, label] of Object.entries(changes)) {
            if (label === null || label === '') {
                delete labels[field];
            } else {
                labels[field] = label;
            }
        }
        this.set({ labels });

        try {
            await storeLabels(this.crud, changes, state, replace);
        } catch {
            // The label is cosmetic; the listing request carries the filter.
        }
    }
}

const sessions = new WeakMap<CrudController, FilterSession>();

/** The filter session of a screen controller (the same one for every piece). */
export function filterSession(crud: CrudController): FilterSession {
    let session = sessions.get(crud);
    if (!session) {
        session = new FilterSession(crud);
        sessions.set(crud, session);
    }

    return session;
}

// ── Rules ───────────────────────────────────────────────────────────────────

const NULL_OPERATORS = ['IS NULL', 'IS NOT NULL', 'NULL', 'NOT NULL'];

/** IS NULL / IS NOT NULL: filter by the column itself, no value (FilterService::isNullOperator()). */
export function isNullOperator(op: unknown): boolean {
    return typeof op === 'string' && NULL_OPERATORS.includes(op.trim().toUpperCase());
}

/** The listing state with the panel's own keys. */
export function listOf(crud: CrudController): FilterListState {
    return crud.getState().list as FilterListState;
}

const hasKeys = (value: unknown) => !!value && typeof value === 'object' && Object.keys(value as object).length > 0;

/** The link filters in effect (none until set). */
export function linkFilters(list: FilterListState): Record<string, unknown> {
    return hasKeys(list.f) ? (list.f as Record<string, unknown>) : {};
}

/** The advanced rows in effect. */
export function advancedRows(list: FilterListState): AdvancedRow[] {
    return Array.isArray(list.advanced) ? list.advanced : [];
}

function patch(crud: CrudController, changes: Partial<FilterListState>): Promise<void> {
    return crud.patchList(changes);
}

/**
 * Touching the panel's values discards the link filters — updatedFilters(),
 * updatedDateRanges(), applyQuickDateFilter() and clearFilters() on Livewire.
 */
function dropLinks(list: FilterListState): Partial<FilterListState> {
    return hasKeys(list.f) ? { f: {} } : {};
}

const valued = (value: unknown) => value !== undefined && value !== null && value !== '';

/** A filter's value (`filters.<field>`); empty removes it. */
export function setFilterValue(crud: CrudController, field: string, value: unknown): Promise<void> {
    const list = listOf(crud);
    const filters = { ...list.filters };
    if (valued(value)) {
        filters[field] = value;
    } else {
        delete filters[field];
    }

    return patch(crud, { filters, ...dropLinks(list) });
}

/** A filter's operator (`filterOperators.<field>`). */
export function setFilterOperator(crud: CrudController, field: string, op: string): Promise<void> {
    const operators = { ...listOf(crud).operators };
    if (op) {
        operators[field] = op;
    } else {
        delete operators[field];
    }

    return patch(crud, { operators });
}

/** One edge of a date range: `key` is `<field>_start` or `<field>_end`. */
export function setDateValue(crud: CrudController, key: string, value: string): Promise<void> {
    const list = listOf(crud);
    const date_ranges = { ...list.date_ranges };
    if (value) {
        date_ranges[key] = value;
    } else {
        delete date_ranges[key];
    }

    return patch(crud, { date_ranges, ...dropLinks(list) });
}

/** The operator of one edge of a date range. */
export function setDateOperator(crud: CrudController, key: string, op: string): Promise<void> {
    return patch(crud, { date_operators: { ...listOf(crud).date_operators, [key]: op } });
}

/** A quick date button: the same period again turns it off (applyQuickDateFilter()). */
export function toggleQuickDate(crud: CrudController, period: string): Promise<void> {
    const list = listOf(crud);

    return patch(crud, { quick_date: list.quick_date === period ? '' : period, ...dropLinks(list) });
}

/** Every filter whose options depend on `field`, at any depth (resetSdFilterDependents()). */
export function dependentsOf(meta: FiltersMeta | null, field: string, seen = new Set<string>()): string[] {
    const out: string[] = [];
    for (const item of meta?.filters ?? []) {
        if (item.depends_on === field && !seen.has(item.field)) {
            seen.add(item.field);
            out.push(item.field, ...dependentsOf(meta, item.field, seen));
        }
    }

    return out;
}

/**
 * Picks an option of a searchdropdown filter (selectFilterDropdownOption()):
 * the value, "=" unless an operator was chosen, the label — and every
 * dependent filter is cleared, its options belonged to the old value.
 */
export async function pickDropdown(crud: CrudController, session: FilterSession, field: string, item: LookupItem): Promise<void> {
    const list = listOf(crud);
    const filters = { ...list.filters, [field]: item.value };
    const operators = { ...list.operators };
    if (!operators[field]) {
        operators[field] = '=';
    }
    const labels: Record<string, string | null> = { [field]: String(item.label ?? item.value) };
    for (const child of dependentsOf(session.getState().meta, field)) {
        delete filters[child];
        delete operators[child];
        labels[child] = null;
    }

    await session.setLabels(labels, { ...list, filters, operators });
    await patch(crud, { filters, operators });
}

/**
 * Clears a searchdropdown filter (clearFilterDropdownSelection()), and its
 * dependents. `keepOperator`: the search text was erased (filterSearchDropdown('')),
 * which leaves the operator.
 */
export async function clearDropdown(crud: CrudController, session: FilterSession, field: string, keepOperator = false): Promise<void> {
    const list = listOf(crud);
    const filters = { ...list.filters };
    const operators = { ...list.operators };
    const labels: Record<string, string | null> = { [field]: null };
    delete filters[field];
    if (!keepOperator) {
        delete operators[field];
    }
    for (const child of dependentsOf(session.getState().meta, field)) {
        delete filters[child];
        delete operators[child];
        labels[child] = null;
    }

    await session.setLabels(labels, { ...list, filters, operators });
    await patch(crud, { filters, operators });
}

/** "Clear all" of the panel (clearFilters()): the search too, the trash view stays. */
export async function clearAllFilters(crud: CrudController, session: FilterSession): Promise<void> {
    const list = listOf(crud);
    const cleared: Partial<FilterListState> = {
        search: '',
        filters: {},
        operators: {},
        date_ranges: {},
        date_operators: {},
        quick_date: '',
        ...(advancedRows(list).length ? { advanced: [] } : {}),
        ...dropLinks(list),
    };

    await session.setLabels({}, { ...list, ...cleared }, true);
    await patch(crud, cleared);
}

/** Applies a saved filter (loadNamedFilter()): its values replace the panel's. */
export function loadSavedFilter(crud: CrudController, name: string): Promise<void> {
    const saved = crud.getState().preferences?.saved_filters?.[name];
    if (!saved || typeof saved !== 'object') {
        return Promise.resolve();
    }

    return patch(crud, { filters: { ...saved } });
}

/** The advanced search rows (addAdvancedSearchField() / removeAdvancedSearchField() / toggleAdvancedSearch()). */
export function setAdvancedRows(crud: CrudController, rows: AdvancedRow[]): Promise<void> {
    return patch(crud, { advanced: rows.map((row) => ({ ...row })) });
}

/** The kind of an advanced search field. */
export function advancedKind(meta: FiltersMeta, field: string): AdvancedKind {
    return meta.advanced.fields.find((item) => item.field === field)?.kind ?? 'text';
}

/** The operators an advanced search row may use on a field. */
export function advancedOperators(meta: FiltersMeta, field: string): string[] {
    return meta.advanced.operators[advancedKind(meta, field)] ?? [];
}

/** A new advanced row on the first field. */
export function blankAdvancedRow(meta: FiltersMeta, logic: 'AND' | 'OR' = 'AND'): AdvancedRow {
    const field = meta.advanced.fields[0]?.field ?? '';

    return { field, operator: advancedOperators(meta, field)[0] ?? '=', value: '', logic };
}

/** How an advanced row reads: `and Name contains "al"`. */
export function advancedText(t: FiltersWords, meta: FiltersMeta, row: AdvancedRow, index: number): string {
    const label = meta.advanced.fields.find((item) => item.field === row.field)?.label ?? row.field;
    const logic = index > 0 ? `${row.logic === 'OR' ? t.or : t.and} ` : '';

    return `${logic}${label} ${operatorLabel(t, row.operator)} "${row.value}"`;
}

/** Leaves the link filters (clearUrlFilters()). */
export function clearLinkFilters(crud: CrudController): Promise<void> {
    return patch(crud, { f: {} });
}

/**
 * `f[...]` of a page address as the object the API reads back:
 * `f[status]=a` → `{status: 'a'}`, `f[name][op]=LIKE&f[name][val]=x` →
 * `{name: {op, val}}`, `f[id][]=1&f[id][]=2` → `{id: ['1', '2']}`.
 */
export function parseLinkFilters(search: string): Record<string, unknown> {
    const out: Record<string, unknown> = {};
    const params = new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);

    params.forEach((value, name) => {
        const match = /^f\[([^\]]+)\](?:\[([^\]]*)\])?$/.exec(name);
        if (!match) {
            return;
        }
        const [, field, sub] = match;
        if (sub === undefined) {
            out[field] = value;
        } else if (sub === '') {
            const list = Array.isArray(out[field]) ? (out[field] as unknown[]) : [];
            out[field] = [...list, value];
        } else {
            const spec = out[field] && typeof out[field] === 'object' && !Array.isArray(out[field]) ? (out[field] as Record<string, unknown>) : {};
            out[field] = { ...spec, [sub]: value };
        }
    });

    return out;
}

/**
 * Takes the link filters of the page address (`?f[status]=active`) into the
 * listing — what the Livewire screen does when it mounts. True when there
 * were some.
 */
export async function applyUrlFilters(crud: CrudController, search: string = typeof window !== 'undefined' ? window.location.search : ''): Promise<boolean> {
    const f = parseLinkFilters(search);
    if (!hasKeys(f)) {
        return false;
    }
    await patch(crud, { f });

    return true;
}

const readLocation = new WeakSet<CrudController>();

/** applyUrlFilters() once per screen controller — what the banner does when it first shows up. */
export function applyUrlFiltersOnce(crud: CrudController): Promise<boolean> {
    if (readLocation.has(crud)) {
        return Promise.resolve(false);
    }
    readLocation.add(crud);

    return applyUrlFilters(crud);
}

/**
 * "Filter by this value" for a cell: the server reads the value from the
 * record and answers the filter; the listing then reloads with it. False
 * when the engine refused the cell (not a filterable column, a row outside
 * the listing) — nothing changes, as on Livewire.
 */
export async function filterByCell(crud: CrudController, rowId: string | number, field: string): Promise<boolean> {
    const list = listOf(crud);
    try {
        const result = await requestCellFilter(crud, rowId, field, list);
        if (!result.ok || !result.state) {
            return false;
        }
        const map = <T>(value: unknown): Record<string, T> => (value && typeof value === 'object' && !Array.isArray(value) ? (value as Record<string, T>) : {});
        await patch(crud, {
            filters: map(result.state.filters),
            operators: map<string>(result.state.operators),
            date_ranges: map<string>(result.state.date_ranges),
            date_operators: map<string>(result.state.date_operators),
            ...dropLinks(list),
        });

        return true;
    } catch {
        return false;
    }
}

/** Whether a column's cells offer "filter by this value" (the meta must be loaded). */
export function isCellFilterable(crud: CrudController, field: string): boolean {
    return filterSession(crud).getState().meta?.cell_fields.includes(field) ?? false;
}

// ── Chips ───────────────────────────────────────────────────────────────────

const DATE_KEY = /_(start|end|from|to)$/;

/** Str::limit() of the toolbar. */
export function limitText(text: string, length: number): string {
    return text.length > length ? `${text.slice(0, length)}...` : text;
}

/**
 * The active-filter chips — buildTextFilter(): the values, the value-less
 * NULL operators, each edge of a date range, the quick date. A select shows
 * its option's label and a searchdropdown the label chosen, where Livewire
 * shows the raw value.
 */
export function buildBadges(list: FilterListState, meta: FiltersMeta | null, labels: Record<string, string>, t: FiltersWords): FilterBadge[] {
    const items = new Map((meta?.filters ?? []).map((item) => [item.field, item]));
    const labelOf = (field: string) => items.get(field)?.label ?? field;
    const empty = meta?.sentinels.empty ?? '__ptah_empty__';
    const filled = meta?.sentinels.filled ?? '__ptah_filled__';
    const badges: FilterBadge[] = [];

    for (const [field, value] of Object.entries(list.filters ?? {})) {
        if (!valued(value)) {
            continue;
        }
        const item = items.get(field);
        let shown = String(value);
        if (value === empty) {
            shown = t.isNull;
        } else if (value === filled) {
            shown = t.isNotNull;
        } else if (item?.kind === 'select') {
            shown = item.options?.find((option) => option.value === String(value))?.label ?? shown;
        } else if (item?.kind === 'searchdropdown' && labels[field]) {
            shown = labels[field];
        }
        badges.push({ key: field, label: labelOf(field), value: shown });
    }

    for (const [field, op] of Object.entries(list.operators ?? {})) {
        if (isNullOperator(op) && !valued(list.filters?.[field])) {
            const upper = op.trim().toUpperCase();
            badges.push({ key: field, label: labelOf(field), value: upper === 'IS NULL' || upper === 'NULL' ? t.isNull : t.isNotNull });
        }
    }

    for (const [key, value] of Object.entries(list.date_ranges ?? {})) {
        if (!valued(value)) {
            continue;
        }
        const field = key.replace(DATE_KEY, '');
        const suffix = /_(start|from)$/.test(key) ? t.rangeFrom : t.rangeTo;
        badges.push({ key, label: `${labelOf(field)} ${suffix}`, value: String(value) });
    }

    if (list.quick_date) {
        badges.push({ key: 'quickDate', label: t.period, value: t.quick[list.quick_date] ?? list.quick_date });
    }

    return badges;
}

/**
 * Removes one chip (removeTextFilterBadge()). A NULL-operator chip also
 * drops its operator — on Livewire it only unset the (empty) value, so that
 * chip could not be removed.
 */
export function removeBadge(crud: CrudController, key: string): Promise<void> {
    const list = listOf(crud);

    if (key === 'quickDate') {
        return patch(crud, { quick_date: '' });
    }
    if (DATE_KEY.test(key)) {
        const date_ranges = { ...list.date_ranges };
        delete date_ranges[key];

        return patch(crud, { date_ranges });
    }

    const filters = { ...list.filters };
    const operators = { ...list.operators };
    const hadValue = valued(filters[key]);
    delete filters[key];
    if (!hadValue && isNullOperator(operators[key])) {
        delete operators[key];
    }

    return patch(crud, { filters, operators });
}

// ── Search history ──────────────────────────────────────────────────────────

/** The recent searches of this user on this screen, most recent first. */
export async function fetchSearchHistory(crud: CrudController): Promise<string[]> {
    const preferences = await crud.api.preferences(crud.screen);

    return Array.isArray(preferences?.search_history) ? preferences.search_history.filter((term) => typeof term === 'string' && term !== '') : [];
}

/** Forgets the recent searches (clearSearchHistory()). */
export async function clearSearchHistory(crud: CrudController): Promise<void> {
    await crud.api.updatePreferences(crud.screen, { clear_search_history: true });
}

// ── Operator labels ─────────────────────────────────────────────────────────

const SYMBOLS: Record<string, string> = { '=': '=', '!=': '≠', '>': '>', '>=': '≥', '<': '<', '<=': '≤' };

/**
 * The text of an operator option. `symbols`: the number and date selects
 * of the panel show ≥, ≤… where the text ones show words.
 */
export function operatorLabel(t: FiltersWords, op: string, symbols = false): string {
    if (symbols && SYMBOLS[op]) {
        return SYMBOLS[op];
    }

    return t.ops[op] ?? op;
}

// ── Words ───────────────────────────────────────────────────────────────────

const en = {
    title: 'Filters',
    active: (count: number) => `${count} active`,
    clearAll: 'Clear all',
    /** The label before the chips (1.61.0): the list IS filtered. */
    applied: 'Filters applied',
    close: 'Close',
    loading: 'Loading…',
    loadError: 'Could not load the filters.',
    retry: 'Try again',
    dateShortcuts: 'Date shortcuts',
    from: 'From',
    to: 'To',
    all: '-- All --',
    noResults: 'No results found.',
    change: 'Change...',
    searchLabel: (label: string) => `Search ${label}...`,
    operator: 'Operator',
    showOptions: 'Show options',
    isNull: 'is empty',
    isNotNull: 'has value',
    ops: {
        LIKE: 'contains',
        'NOT LIKE': 'does not contain',
        '=': 'equals',
        '!=': 'not equal',
        '>': 'greater than',
        '>=': 'greater or equal',
        '<': 'less than',
        '<=': 'less or equal',
        LIKE_START: 'starts with',
        LIKE_END: 'ends with',
        'IS NULL': 'is empty',
        'IS NOT NULL': 'has value',
    } as Record<string, string>,
    saved: 'Saved:',
    saveAction: 'Save current filter with name',
    savePlaceholder: 'E.g.: Active clients SP',
    saveName: 'Filter name',
    save: 'Save',
    cancel: 'Cancel',
    deleteSaved: (name: string) => `Delete the saved filter "${name}"`,
    deleteSavedTitle: 'Delete saved filter',
    deleteSavedText: (name: string) => `Delete the saved filter "${name}"? This cannot be undone.`,
    delete: 'Delete',
    period: 'Period',
    rangeFrom: 'from',
    rangeTo: 'to',
    quick: {
        today: 'Today',
        yesterday: 'Yesterday',
        last7: '7 days',
        last30: '30 days',
        week: 'This week',
        month: 'This month',
        lastMonth: 'Last month',
        quarter: 'Quarter',
        year: 'This year',
    } as Record<string, string>,
    advanced: 'Advanced search',
    advancedField: 'Field',
    advancedValue: 'Value',
    advancedLogic: 'Combine',
    and: 'and',
    or: 'or',
    add: 'Add',
    remove: 'Remove',
    removeRow: (text: string) => `Remove the condition ${text}`,
    advancedEmpty: 'No condition yet: choose a field, an operator and a value.',
    removeFilter: (label: string) => `Remove the filter ${label}`,
    search: 'Search',
    clearSearch: 'Clear search',
    urlActive: 'Filters from link',
    urlClear: 'Clear link filters',
    history: 'Recent searches',
    historyEmpty: 'No recent searches.',
    historyClear: 'Clear history',
    historyClearTitle: 'Clear recent searches',
    historyClearText: 'Forget the recent searches of this screen?',
    historyError: 'Could not load the recent searches.',
    filterByCell: 'Filter by this value',
    clearFilters: 'Clear filters',
    historyLabel: 'Recent:',
};

export type FiltersWords = typeof en;

const ptBR: FiltersWords = {
    title: 'Filtros',
    active: (count: number) => `${count} ${count === 1 ? 'ativo' : 'ativos'}`,
    clearAll: 'Limpar tudo',
    applied: 'Filtros aplicados',
    close: 'Fechar',
    loading: 'Carregando…',
    loadError: 'Não foi possível carregar os filtros.',
    retry: 'Tentar de novo',
    dateShortcuts: 'Atalhos de data',
    from: 'De',
    to: 'Até',
    all: '-- Todos --',
    noResults: 'Nenhum resultado encontrado.',
    change: 'Alterar...',
    searchLabel: (label: string) => `Buscar ${label}...`,
    operator: 'Operador',
    showOptions: 'Mostrar opções',
    isNull: 'é vazio / sem valor',
    isNotNull: 'tem valor',
    ops: {
        LIKE: 'contém',
        'NOT LIKE': 'não contém',
        '=': 'igual a',
        '!=': 'diferente',
        '>': 'maior que',
        '>=': 'maior ou igual',
        '<': 'menor que',
        '<=': 'menor ou igual',
        LIKE_START: 'inicia com',
        LIKE_END: 'termina com',
        'IS NULL': 'é vazio / sem valor',
        'IS NOT NULL': 'tem valor',
    },
    saved: 'Salvos:',
    saveAction: 'Salvar filtro atual com nome',
    savePlaceholder: 'Ex: Clientes ativos SP',
    saveName: 'Nome do filtro',
    save: 'Salvar',
    cancel: 'Cancelar',
    deleteSaved: (name: string) => `Excluir o filtro salvo "${name}"`,
    deleteSavedTitle: 'Excluir filtro salvo',
    deleteSavedText: (name: string) => `Excluir o filtro salvo "${name}"? Esta ação não pode ser desfeita.`,
    delete: 'Excluir',
    period: 'Período',
    rangeFrom: 'de',
    rangeTo: 'até',
    quick: {
        today: 'Hoje',
        yesterday: 'Ontem',
        last7: '7 dias',
        last30: '30 dias',
        week: 'Esta semana',
        month: 'Este mês',
        lastMonth: 'Mês passado',
        quarter: 'Trimestre',
        year: 'Este ano',
    },
    advanced: 'Busca avançada',
    advancedField: 'Campo',
    advancedValue: 'Valor',
    advancedLogic: 'Combinar',
    and: 'e',
    or: 'ou',
    add: 'Adicionar',
    remove: 'Remover',
    removeRow: (text: string) => `Remover a condição ${text}`,
    advancedEmpty: 'Nenhuma condição ainda: escolha um campo, um operador e um valor.',
    removeFilter: (label: string) => `Remover o filtro ${label}`,
    search: 'Busca',
    clearSearch: 'Limpar busca',
    urlActive: 'Filtros do link',
    urlClear: 'Limpar filtros do link',
    history: 'Buscas recentes',
    historyEmpty: 'Nenhuma busca recente.',
    historyClear: 'Limpar histórico',
    historyClearTitle: 'Limpar buscas recentes',
    historyClearText: 'Esquecer as buscas recentes desta tela?',
    historyError: 'Não foi possível carregar as buscas recentes.',
    filterByCell: 'Filtrar por este valor',
    clearFilters: 'Limpar filtros',
    historyLabel: 'Recentes:',
};

const dictionaries: Record<string, FiltersWords> = { en, pt: ptBR };

/** The words of the filter pieces for a locale (`pt_BR`, `pt-BR`, `en`…); English when unknown. */
export function filtersWords(locale?: string | null): FiltersWords {
    const lang = (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').toLowerCase().slice(0, 2);

    return dictionaries[lang] ?? en;
}

// ── The searchdropdown of a filter ──────────────────────────────────────────

export interface FilterLookupState {
    /** What is typed in the box. */
    text: string;
    open: boolean;
    loading: boolean;
    items: LookupItem[];
    /** The option under the keyboard (-1: none). */
    active: number;
}

/**
 * The search box of a searchdropdown filter — the Alpine/Livewire pair of
 * the panel: options on focus, a search 300 ms after the last key, the
 * cascade context (the other filters), erasing the text clears the filter.
 * Framework-free: the React and Vue boxes draw this state.
 */
export class FilterLookup {
    private state: FilterLookupState = { text: '', open: false, loading: false, items: [], active: -1 };
    private readonly listeners = new Set<() => void>();
    private timer: ReturnType<typeof setTimeout> | undefined;
    private seq = 0;
    private loaded = false;

    constructor(
        private readonly crud: CrudController,
        private readonly session: FilterSession,
        readonly field: string,
    ) {}

    getState = (): FilterLookupState => this.state;

    subscribe = (listener: () => void): (() => void) => {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    };

    private set(patch: Partial<FilterLookupState>): void {
        this.state = { ...this.state, ...patch };
        this.listeners.forEach((listener) => listener());
    }

    private async search(query: string): Promise<void> {
        const mine = ++this.seq;
        this.set({ loading: true, open: true });
        const items = await this.crud.lookup(this.field, query, true);
        if (mine === this.seq) {
            this.loaded = true;
            this.set({ loading: false, items, open: true, active: -1 });
        }
    }

    /** Focus: the first options (openFilterDropdown()), unless they are already there. */
    focus(): void {
        if (this.loaded && this.state.items.length > 0 && this.state.text === '') {
            this.set({ open: true });

            return;
        }
        void this.search(this.state.text);
    }

    type(text: string): void {
        clearTimeout(this.timer);
        this.set({ text });

        if (text === '') {
            this.seq++;
            this.loaded = false;
            this.set({ open: false, items: [], loading: false, active: -1 });
            // filterSearchDropdown(''): erasing the text clears the filter.
            if (valued(listOf(this.crud).filters[this.field])) {
                void clearDropdown(this.crud, this.session, this.field, true);
            }

            return;
        }

        this.timer = setTimeout(() => void this.search(text), 300);
    }

    toggle(): void {
        if (this.state.open) {
            this.close();
        } else {
            this.focus();
        }
    }

    close(): void {
        this.set({ open: false, active: -1 });
    }

    move(delta: number): void {
        if (!this.state.open) {
            this.focus();

            return;
        }
        const last = this.state.items.length - 1;
        if (last < 0) {
            return;
        }
        this.set({ active: Math.max(0, Math.min(last, this.state.active + delta)) });
    }

    async choose(index = this.state.active): Promise<void> {
        const item = this.state.items[index];
        if (!item) {
            return;
        }
        clearTimeout(this.timer);
        this.seq++;
        this.loaded = false;
        this.set({ text: '', open: false, items: [], active: -1 });
        await pickDropdown(this.crud, this.session, this.field, item);
    }

    /** The options of the parent changed: the ones on hand are stale. */
    reset(): void {
        this.loaded = false;
        this.set({ items: [] });
    }

    destroy(): void {
        clearTimeout(this.timer);
        this.listeners.clear();
    }
}
