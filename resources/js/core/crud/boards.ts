/**
 * The alternative views of a CRUD screen — kanban, calendar, cards — and the
 * view/density switch, over the boards API (`/crud/{screen}/kanban`,
 * `/crud/{screen}/calendar`, `/crud/{screen}/{id}/move`).
 *
 * The board and the calendar are the SAME listing as the table: every
 * request carries the listing state the screen shows (`crud.getState().list`)
 * and the server applies it before drawing. Moving a card is the engine's
 * own `moveCard()` — the rules (permission, locked columns, transitions)
 * are the server's; these pieces only ask and show.
 */
import { toQuery } from '../api';
import type { PtahApi } from '../api';
import type { Features, ListState, Message, Option, RowId } from '../types';

// ── Types ───────────────────────────────────────────────────────────────────

/** A card of the board or an event of the calendar: what the Blade view prints. */
export interface BoardCard {
    id: RowId;
    title: string;
    subtitle: string;
}

export interface KanbanColumn {
    label: string;
    value: string;
    /** Records in this column under the current filters (the cards may be fewer). */
    total: number;
    cards: BoardCard[];
    /** A `locked` column: no card comes in, none goes out. */
    locked: boolean;
    /** The "other values" column: records whose value is not one of the options. */
    other: boolean;
}

export interface KanbanBoard {
    columns: KanbanColumn[];
    /** The options of the kanban field, in order (label → value). */
    options: Option[];
    /** from → the columns a card may move to. Empty when the user may not move. */
    targets: Record<string, string[]>;
    can_move: boolean;
    /** The config's explanation shown under a locked column ('' when none). */
    locked_message: string;
}

export interface CalendarDay {
    date: string;
    day: number;
    in_month: boolean;
    today: boolean;
    items: BoardCard[];
}

export interface CalendarGrid {
    /** 'Y-m' of the month shown. */
    month: string;
    /** Weeks, Sunday first. */
    weeks: CalendarDay[][];
    /** More records than the calendar shows: refine the filters. */
    overflow: boolean;
}

export interface MoveResult {
    ok: boolean;
    messages: Message[];
}

// ── Client ──────────────────────────────────────────────────────────────────

const path = (screen: string, rest: string) => `/crud/${encodeURIComponent(screen)}${rest}`;

/** The listing state as the query of a board request — the page is the table's, not the board's. */
function listQuery(list: Partial<ListState>, extra: Record<string, unknown> = {}): string {
    const { page: _page, per_page: _perPage, ...rest } = list; // eslint-disable-line @typescript-eslint/no-unused-vars
    const query = toQuery({ ...rest, ...extra });

    return query ? `?${query}` : '';
}

export const getKanban = (api: PtahApi, screen: string, list: Partial<ListState>) => api.call<KanbanBoard>('GET', path(screen, `/kanban${listQuery(list)}`));

/** `month` 'Y-m' (empty: the current one); `shift` months from it, as the arrows do. */
export const getCalendar = (api: PtahApi, screen: string, list: Partial<ListState>, month = '', shift = 0) =>
    api.call<CalendarGrid>('GET', path(screen, `/calendar${listQuery(list, { month: month || undefined, shift: shift || undefined })}`));

/** Moves a card. A refusal (422) answers `{ok: false, messages}` with the engine's reason. */
export const moveCard = (api: PtahApi, screen: string, id: RowId, value: string) => api.call<MoveResult>('POST', path(screen, `/${id}/move`), { value });

// ── View modes and density ──────────────────────────────────────────────────

export type ViewMode = 'auto' | 'table' | 'cards' | 'kanban' | 'calendar';
export type Density = 'global' | 'compact' | 'comfortable' | 'spacious';

export const DENSITIES: Density[] = ['global', 'compact', 'comfortable', 'spacious'];

/** The density menu's marks, as the Livewire toolbar draws them. */
export const DENSITY_ICONS: Record<Density, string> = { global: '◈', compact: '≡', comfortable: '☰', spacious: '⊟' };

/** The modes this screen offers: kanban and calendar only when configured (setViewMode() refuses them otherwise). */
export function viewModes(features: Pick<Features, 'kanban' | 'calendar'>): ViewMode[] {
    return ['auto', 'table', 'cards', ...(features.kanban ? (['kanban'] as const) : []), ...(features.calendar ? (['calendar'] as const) : [])];
}

/**
 * Below this width 'auto' shows cards — the `md` breakpoint of the Livewire
 * screen (`hidden md:block` / `md:hidden`).
 */
export const NARROW_QUERY = '(max-width: 767.98px)';

/**
 * What to draw for a saved mode. 'auto' follows the viewport and is decided
 * here, at render time, never written back: the preference is per user, the
 * layout question is per device — saving 'cards' because a phone opened the
 * screen would hand the desktop a card grid the next morning.
 * A board mode the screen no longer offers falls back to the table, as the
 * Livewire view does. With `responsiveCards` off (`ptah.crud.responsive_cards`)
 * 'auto' stays a table on a narrow screen too, as base-crud.blade.php does.
 */
export function resolveView(
    mode: string | null | undefined,
    features: Pick<Features, 'kanban' | 'calendar'> & Partial<Pick<Features, 'responsiveCards'>>,
    narrow: boolean,
): Exclude<ViewMode, 'auto'> {
    switch (mode) {
        case 'table':
        case 'cards':
            return mode;
        case 'kanban':
            return features.kanban ? 'kanban' : 'table';
        case 'calendar':
            return features.calendar ? 'calendar' : 'table';
        default:
            // ptah.crud.responsive_cards off: 'auto' is a table everywhere.
            return narrow && features.responsiveCards !== false ? 'cards' : 'table';
    }
}

/** Whether the viewport is narrow now (false where there is no window, e.g. on the server). */
export function isNarrow(): boolean {
    return typeof window !== 'undefined' && typeof window.matchMedia === 'function' && window.matchMedia(NARROW_QUERY).matches;
}

/** Calls `listener` when the viewport crosses the breakpoint; returns the unsubscribe. */
export function watchNarrow(listener: (narrow: boolean) => void): () => void {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return () => undefined;
    }
    const media = window.matchMedia(NARROW_QUERY);
    const onChange = () => listener(media.matches);
    media.addEventListener?.('change', onChange);

    return () => media.removeEventListener?.('change', onChange);
}

// ── Row link (`configLinkLinha`) ────────────────────────────────────────────

/** Where a row link goes — replaceable (tests, a client-side router). */
export const rowNavigator = {
    open(url: string): void {
        window.open(url, '_blank');
    },
    go(url: string): void {
        window.location.assign(url);
    },
};

/** What `followRowLink` reads of a DOM event (mouse or keyboard). */
export interface RowLinkEvent {
    type: string;
    button?: number;
    key?: string;
    ctrlKey: boolean;
    metaKey: boolean;
    altKey: boolean;
    target: EventTarget | null;
    currentTarget: EventTarget | null;
}

/** What already answers a click inside a row: the row link leaves it alone. */
const INTERACTIVE = 'a, button, input, select, textarea, label, summary, [role="button"], [contenteditable="true"]';

/**
 * A click (or Enter) on a row or card with a link — `ptahRowNav` of the
 * Livewire screen: middle-click or ctrl/cmd-click opens a new tab, a plain
 * click navigates; selecting the row's text to copy it does not navigate,
 * nor does a click on the row's own buttons, checkboxes or links, nor the
 * alt+click that filters by a cell. True when it navigated.
 */
export function followRowLink(event: RowLinkEvent, url: string | null | undefined): boolean {
    if (!url || event.altKey) {
        return false;
    }
    const row = event.currentTarget as Element | null;
    const target = event.target as Element | null;
    const inside = target && typeof target.closest === 'function' ? target.closest(INTERACTIVE) : null;
    if (inside && inside !== row && (!row || row.contains(inside))) {
        return false;
    }
    if (event.type === 'keydown') {
        // Only the row itself (focused) answers Enter — not a field inside it.
        if (event.key !== 'Enter' || target !== row) {
            return false;
        }
        rowNavigator.go(url);

        return true;
    }
    const selection = typeof window !== 'undefined' && typeof window.getSelection === 'function' ? window.getSelection() : null;
    if (selection && !selection.isCollapsed && selection.toString().trim() !== '' && row && selection.anchorNode && row.contains(selection.anchorNode)) {
        return false;
    }
    if (event.button === 1 || event.ctrlKey || event.metaKey) {
        rowNavigator.open(url);

        return true;
    }
    // Another button (right) on auxclick: nothing.
    if (event.type === 'auxclick') {
        return false;
    }
    rowNavigator.go(url);

    return true;
}

/** The `v` shortcut of the Livewire screen: auto → table → cards → auto. */
export function nextViewMode(mode: string | null | undefined): ViewMode {
    const next: Record<string, ViewMode> = { auto: 'table', table: 'cards', cards: 'auto' };

    return next[mode ?? 'auto'] ?? 'auto';
}

// ── Board logic ─────────────────────────────────────────────────────────────

/** The listing state that decides which records the board shows (not the page). */
export function listKey(list: ListState): string {
    const { page: _page, per_page: _perPage, ...rest } = list; // eslint-disable-line @typescript-eslint/no-unused-vars

    return JSON.stringify(rest);
}

/** The columns a card in `from` may move to (the server checks again). */
export function targetsOf(board: KanbanBoard, from: string): string[] {
    return board.can_move ? (board.targets[from] ?? []) : [];
}

export function canDrop(board: KanbanBoard, from: string | null, to: string): boolean {
    return from !== null && targetsOf(board, from).includes(to);
}

/**
 * The "Move to" choices of a card, as the Blade select lists them: its own
 * column first-in-order and selected, then only the allowed destinations.
 * A card of the "other values" column has no current option (a placeholder).
 */
export function moveChoices(board: KanbanBoard, column: KanbanColumn): Option[] {
    const targets = targetsOf(board, column.value);

    return board.options.filter((option) => option.value === column.value || targets.includes(option.value));
}

/** The board with a card moved — drawn at once, put back if the server refuses. */
export function withCardMoved(board: KanbanBoard, id: RowId, from: string, to: string): KanbanBoard {
    const card = board.columns.find((column) => column.value === from)?.cards.find((item) => String(item.id) === String(id));
    if (!card) {
        return board;
    }

    return {
        ...board,
        columns: board.columns.map((column) => {
            if (column.value === from) {
                return { ...column, total: Math.max(0, column.total - 1), cards: column.cards.filter((item) => item !== card) };
            }
            if (column.value === to) {
                return { ...column, total: column.total + 1, cards: [card, ...column.cards] };
            }

            return column;
        }),
    };
}

// ── Calendar logic ──────────────────────────────────────────────────────────

/** How many events a day shows before "+N". */
export const CALENDAR_DAY_LIMIT = 4;

const intlLocale = (locale?: string | null) => (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').replace('_', '-') || 'en';

/** "setembro de 2026" / "September 2026" for 'Y-m'. */
export function monthTitle(month: string, locale?: string | null): string {
    const [year, mon] = month.split('-').map(Number);
    if (!year || !mon) {
        return month;
    }
    const date = new Date(Date.UTC(year, mon - 1, 1));

    try {
        return new Intl.DateTimeFormat(intlLocale(locale), { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(date);
    } catch {
        return month;
    }
}

/** The tooltip of an event: "title — subtitle". */
export const eventTitle = (item: BoardCard) => (item.subtitle !== '' ? `${item.title} — ${item.subtitle}` : item.title);

// ── Cards ───────────────────────────────────────────────────────────────────

/** `background:#fee;color:red` → a style object (React wants camelCase). */
export function styleObject(style: string | null): Record<string, string> | undefined {
    if (!style) {
        return undefined;
    }
    const out: Record<string, string> = {};
    for (const rule of style.split(';')) {
        const [name, ...rest] = rule.split(':');
        if (name && rest.length) {
            out[name.trim().replace(/-([a-z])/g, (_, letter: string) => letter.toUpperCase())] = rest.join(':').trim();
        }
    }

    return out;
}

/** Visually hidden but read by screen readers (ptah-ui.css has no `sr-only`). */
export const SR_ONLY = { position: 'absolute', width: '1px', height: '1px', padding: '0', margin: '-1px', overflow: 'hidden', clip: 'rect(0, 0, 0, 0)', whiteSpace: 'nowrap', border: '0' } as const;

// ── Words ───────────────────────────────────────────────────────────────────

export interface BoardsWords {
    viewMode: string;
    viewAuto: string;
    viewTable: string;
    viewCards: string;
    viewKanban: string;
    viewCalendar: string;
    density: string;
    densityGlobal: string;
    densityCompact: string;
    densityComfortable: string;
    densitySpacious: string;
    loading: string;
    loadError: string;
    retry: string;
    moveTo: string;
    more: string;
    emptyColumn: string;
    otherHint: string;
    locked: string;
    moveNotAllowed: string;
    calendarPrev: string;
    calendarNext: string;
    calendarToday: string;
    calendarOverflow: string;
    weekdays: string[];
    emptyTitle: string;
    emptySubtitle: string;
    sortGroup: string;
    sortBy: string;
    sortAsc: string;
    sortDesc: string;
    selectRow: string;
    edit: string;
    duplicate: string;
    restore: string;
    delete: string;
    confirm: string;
    cancel: string;
    confirmDelete: string;
}

const en: BoardsWords = {
    viewMode: 'View mode',
    viewAuto: 'Automatic (table on desktop, cards on mobile)',
    viewTable: 'Table view',
    viewCards: 'Card view',
    viewKanban: 'Board',
    viewCalendar: 'Calendar',
    density: 'Density',
    densityGlobal: 'Profile default',
    densityCompact: 'Compact',
    densityComfortable: 'Comfortable',
    densitySpacious: 'Spacious',
    loading: 'Loading…',
    loadError: 'Could not load. Try again.',
    retry: 'Try again',
    moveTo: 'Move to',
    more: ':count more — refine the filters to see them',
    emptyColumn: 'Nothing here',
    otherHint: 'Their value is not one of the options — move them to a column.',
    locked: 'Locked column',
    moveNotAllowed: 'This card cannot move to that column.',
    calendarPrev: 'Previous month',
    calendarNext: 'Next month',
    calendarToday: 'Today',
    calendarOverflow: 'Too many records this month to show them all — refine the filters.',
    weekdays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    emptyTitle: 'No records found',
    emptySubtitle: 'Adjust filters or add a new item',
    sortGroup: 'Card ordering',
    sortBy: 'Sort by',
    sortAsc: 'Ascending order — tap to reverse',
    sortDesc: 'Descending order — tap to reverse',
    selectRow: 'Select row :id',
    edit: 'Edit',
    duplicate: 'Duplicate record',
    restore: 'Restore',
    delete: 'Delete',
    confirm: 'Confirm',
    cancel: 'Cancel',
    confirmDelete: 'Delete this record?',
};

const ptBR: BoardsWords = {
    viewMode: 'Modo de visualização',
    viewAuto: 'Automático (tabela no desktop, cards no celular)',
    viewTable: 'Visão em tabela',
    viewCards: 'Visão em cards',
    viewKanban: 'Quadro',
    viewCalendar: 'Calendário',
    density: 'Densidade',
    densityGlobal: 'Padrão do perfil',
    densityCompact: 'Compacto',
    densityComfortable: 'Confortável',
    densitySpacious: 'Espaçoso',
    loading: 'Carregando…',
    loadError: 'Não foi possível carregar. Tente de novo.',
    retry: 'Tentar de novo',
    moveTo: 'Mover para',
    more: 'mais :count — refine os filtros para vê-los',
    emptyColumn: 'Nada aqui',
    otherHint: 'O valor destes não é uma das opções — mova para uma coluna.',
    locked: 'Coluna bloqueada',
    moveNotAllowed: 'Este card não pode ir para essa coluna.',
    calendarPrev: 'Mês anterior',
    calendarNext: 'Próximo mês',
    calendarToday: 'Hoje',
    calendarOverflow: 'Registros demais neste mês para mostrar todos — refine os filtros.',
    weekdays: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
    emptyTitle: 'Nenhum registro encontrado',
    emptySubtitle: 'Ajuste os filtros ou adicione um novo item',
    sortGroup: 'Ordenação dos cards',
    sortBy: 'Ordenar por',
    sortAsc: 'Ordem crescente — toque para inverter',
    sortDesc: 'Ordem decrescente — toque para inverter',
    selectRow: 'Selecionar linha :id',
    edit: 'Editar',
    duplicate: 'Duplicar registro',
    restore: 'Restaurar',
    delete: 'Excluir',
    confirm: 'Confirmar',
    cancel: 'Cancelar',
    confirmDelete: 'Excluir este registro?',
};

const dictionaries: Record<string, BoardsWords> = { en, pt: ptBR };

/** The words of the board pieces for a locale (`pt_BR`, `pt-BR`, `en`…); English when unknown. */
export function boardsWords(locale?: string | null): BoardsWords {
    const lang = (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').toLowerCase().slice(0, 2);

    return dictionaries[lang] ?? en;
}

export const densityLabel = (t: BoardsWords, density: string): string =>
    ({ global: t.densityGlobal, compact: t.densityCompact, comfortable: t.densityComfortable, spacious: t.densitySpacious })[density] ?? density;

export const viewLabel = (t: BoardsWords, mode: ViewMode): string =>
    ({ auto: t.viewAuto, table: t.viewTable, cards: t.viewCards, kanban: t.viewKanban, calendar: t.viewCalendar })[mode];

/** The mark of each mode in the switch (the Livewire toolbar uses SVG icons). */
export const VIEW_ICONS: Record<ViewMode, string> = { auto: '◐', table: '☰', cards: '▦', kanban: '▥', calendar: '▤' };
