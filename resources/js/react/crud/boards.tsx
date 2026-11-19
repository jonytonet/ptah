/**
 * The alternative views of a CRUD screen for React — kanban, calendar and
 * cards — and the view/density switch. Each piece reads the screen through
 * its CrudController; the board and the calendar ask the boards API with
 * the listing state the screen shows, so they show the same records.
 */
import { useCallback, useEffect, useMemo, useRef, useState, useSyncExternalStore } from 'react';
import type { CSSProperties, DragEvent, ReactNode } from 'react';
import { ApiError } from '../../core';
import type { CrudController, CrudState, Row, RowAction } from '../../core';
import {
    boardsWords,
    CALENDAR_DAY_LIMIT,
    canDrop,
    DENSITIES,
    DENSITY_ICONS,
    densityLabel,
    eventTitle,
    followRowLink,
    getCalendar,
    getKanban,
    isNarrow,
    listKey,
    monthTitle,
    moveCard,
    moveChoices,
    resolveView,
    SR_ONLY,
    styleObject,
    targetsOf,
    VIEW_ICONS,
    viewLabel,
    viewModes,
    watchNarrow,
    withCardMoved,
} from '../../core/crud/boards';
import type { BoardsWords, CalendarGrid, KanbanBoard, KanbanColumn, ViewMode } from '../../core/crud/boards';
import { ForgeAlert, ForgeButton, ForgeEmpty, ForgeModal, ForgeSpinner } from '../forge';

export interface BoardProps {
    crud: CrudController;
    /** `pt_BR`, `en`… Defaults to the page's `<html lang>`. */
    locale?: string;
}

export interface CardsProps extends BoardProps {
    /**
     * The buttons of a card's footer — pass the table's own row actions so
     * both views offer the same. Without it the cards draw the configured
     * row actions and edit / duplicate / delete / restore.
     */
    renderActions?: (row: Row) => ReactNode;
}

function useCrudState(crud: CrudController): CrudState {
    return useSyncExternalStore(crud.subscribe, crud.getState, crud.getState);
}

const failure = (error: unknown, t: BoardsWords) => (error instanceof ApiError && (error.status === 403 || error.status === 404) && error.message ? error.message : t.loadError);

/**
 * Which view to draw now: the saved mode, with 'auto' decided by the
 * viewport at render time (cards below `md`, the table above; always the
 * table when the server turned `responsiveCards` off) — never saved.
 */
export function useResolvedView(crud: CrudController): Exclude<ViewMode, 'auto'> {
    const state = useCrudState(crud);
    const narrow = useSyncExternalStore(
        (listener) => watchNarrow(() => listener()),
        isNarrow,
        () => false,
    );

    return resolveView(state.preferences?.view_mode, crud.features, narrow);
}

// ── View switch ─────────────────────────────────────────────────────────────

/** Table / cards / board / calendar, and the density — saved in the user's preferences. */
export function PtahCrudViewSwitch({ crud, locale }: BoardProps) {
    const state = useCrudState(crud);
    const t = useMemo(() => boardsWords(locale), [locale]);
    const [saving, setSaving] = useState(false);
    const [open, setOpen] = useState(false);
    const menu = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }
        const close = (event: MouseEvent | KeyboardEvent) => {
            if (event instanceof KeyboardEvent ? event.key === 'Escape' : !menu.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, [open]);

    if (!state.schema) {
        return null;
    }

    const mode = state.preferences?.view_mode ?? 'auto';
    const density = state.preferences?.view_density ?? 'global';
    const save = async (changes: { view_mode?: string; view_density?: string }) => {
        setSaving(true);
        try {
            await crud.savePreferences(changes);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
            <div role="group" aria-label={t.viewMode} style={{ display: 'inline-flex', gap: 2 }}>
                {viewModes(crud.features).map((item) => (
                    <button
                        key={item}
                        type="button"
                        className={`pc-btn${mode === item ? ' pc-btn--active' : ''}`}
                        aria-pressed={mode === item}
                        aria-label={viewLabel(t, item)}
                        title={viewLabel(t, item)}
                        disabled={saving}
                        onClick={() => mode !== item && void save({ view_mode: item })}
                    >
                        <span aria-hidden="true">{VIEW_ICONS[item]}</span>
                    </button>
                ))}
            </div>
            <div className="pc-menu" ref={menu}>
                <button type="button" className="pc-btn" aria-haspopup="true" aria-expanded={open} aria-label={t.density} title={t.density} disabled={saving} onClick={() => setOpen(!open)}>
                    <span aria-hidden="true">{DENSITY_ICONS[density as keyof typeof DENSITY_ICONS] ?? '☰'}</span>
                    <span>{t.density}</span> ▾
                </button>
                {open && (
                    <ul className="pc-menu-list">
                        {DENSITIES.map((item) => (
                            <li key={item}>
                                <button
                                    type="button"
                                    className="pc-menu-item"
                                    aria-current={density === item ? 'true' : undefined}
                                    style={density === item ? { fontWeight: 600, color: 'var(--pc-primary)' } : undefined}
                                    onClick={() => {
                                        setOpen(false);
                                        if (density !== item) {
                                            void save({ view_density: item });
                                        }
                                    }}
                                >
                                    <span aria-hidden="true">{DENSITY_ICONS[item]}</span> {densityLabel(t, item)}
                                    {density === item && (
                                        <span aria-hidden="true" style={{ marginLeft: 'auto' }}>
                                            ✓
                                        </span>
                                    )}
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}

// ── Kanban ──────────────────────────────────────────────────────────────────

const muted: CSSProperties = { color: 'var(--pc-text-muted)', fontSize: '0.75rem' };

/** The board: one column per option, drag and drop and a "Move to" select per card. */
export function PtahCrudKanban({ crud, locale }: BoardProps) {
    const state = useCrudState(crud);
    const t = useMemo(() => boardsWords(locale), [locale]);
    const on = state.schema?.features.kanban ?? false;
    const key = listKey(state.list);
    const [board, setBoard] = useState<KanbanBoard | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [drag, setDrag] = useState<string | null>(null);
    const [over, setOver] = useState<string | null>(null);
    const seq = useRef(0);

    const load = useCallback(async () => {
        const mine = ++seq.current;
        setLoading(true);
        try {
            const next = await getKanban(crud.api, crud.screen, crud.getState().list);
            if (mine === seq.current) {
                setBoard(next);
                setError(null);
            }
        } catch (reason) {
            if (mine === seq.current) {
                setError(failure(reason, t));
            }
        } finally {
            if (mine === seq.current) {
                setLoading(false);
            }
        }
    }, [crud, t]);

    // Again when the filters change or the listing reloads (a save, a delete).
    useEffect(() => {
        if (on) {
            void load();
        }
    }, [on, key, state.rows, load]);

    if (!on) {
        return null;
    }

    const move = async (id: string, from: string, to: string) => {
        if (!board || !canDrop(board, from, to)) {
            return;
        }
        const before = board;
        // Drawn at once; a refusal puts the card back where it was.
        ++seq.current;
        setBoard(withCardMoved(board, id, from, to));
        try {
            const result = await moveCard(crud.api, crud.screen, id, to);
            result.messages.forEach((message) => crud.toast(message.text, message.level));
            if (!result.ok) {
                setBoard(before);
                if (result.messages.length === 0) {
                    crud.toast(t.moveNotAllowed, 'warn');
                }

                return;
            }
            // The listing reloads, and with it the board (counts, order).
            await crud.reload();
        } catch (reason) {
            setBoard(before);
            crud.toast(reason instanceof Error && reason.message ? reason.message : t.moveNotAllowed, 'danger');
        }
    };

    if (!board) {
        return error ? (
            <div className="pc-state" role="alert">
                {error}{' '}
                <ForgeButton color="dark" flat size="sm" onClick={() => void load()}>
                    {t.retry}
                </ForgeButton>
            </div>
        ) : (
            <div className="pc-state">
                <ForgeSpinner label={t.loading} />
            </div>
        );
    }

    return (
        <div aria-busy={loading} style={{ display: 'flex', gap: 12, overflowX: 'auto', paddingBottom: 8, opacity: loading ? 0.7 : 1 }}>
            {error && (
                <div style={{ flex: '0 0 100%' }}>
                    <ForgeAlert color="danger">{error}</ForgeAlert>
                </div>
            )}
            {board.columns.map((column) => (
                <Column
                    key={column.value}
                    board={board}
                    column={column}
                    t={t}
                    drag={drag}
                    over={over === column.value}
                    onOver={(value) => setOver(value)}
                    onDrag={(from) => {
                        setDrag(from);
                        if (from === null) {
                            setOver(null);
                        }
                    }}
                    onMove={(id, from, to) => void move(id, from, to)}
                    onOpen={(id) => void crud.openEdit(id)}
                />
            ))}
        </div>
    );
}

interface ColumnProps {
    board: KanbanBoard;
    column: KanbanColumn;
    t: BoardsWords;
    drag: string | null;
    over: boolean;
    onOver: (value: string | null) => void;
    onDrag: (from: string | null) => void;
    onMove: (id: string, from: string, to: string) => void;
    onOpen: (id: string | number) => void;
}

function Column({ board, column, t, drag, over, onOver, onDrag, onMove, onOpen }: ColumnProps) {
    const targets = targetsOf(board, column.value);
    const choices = moveChoices(board, column);
    const droppable = board.can_move && !column.other && !column.locked;
    const accepts = canDrop(board, drag, column.value);

    return (
        <section
            aria-label={column.label}
            data-ptah-kanban-locked={column.locked || undefined}
            style={{
                display: 'flex',
                flexDirection: 'column',
                flex: '0 0 18rem',
                border: '1px solid var(--pc-line)',
                borderRadius: 'var(--pc-radius)',
                background: 'var(--pc-bg-soft)',
                outline: over ? '2px solid var(--pc-primary)' : undefined,
                outlineOffset: -2,
            }}
            onDragOver={
                droppable
                    ? (event: DragEvent) => {
                          if (accepts) {
                              event.preventDefault();
                              onOver(column.value);
                          }
                      }
                    : undefined
            }
            onDragLeave={droppable ? () => onOver(null) : undefined}
            onDrop={
                droppable
                    ? (event: DragEvent) => {
                          event.preventDefault();
                          onOver(null);
                          if (accepts && drag !== null) {
                              onMove(event.dataTransfer.getData('text/plain'), drag, column.value);
                          }
                      }
                    : undefined
            }
        >
            <header style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '8px 12px', borderBottom: '1px solid var(--pc-line)', background: 'var(--pc-bg)' }}>
                <h3 style={{ margin: 0, fontSize: '0.875rem', fontWeight: 600, color: 'var(--pc-text-strong)' }}>
                    {column.locked && (
                        <span title={t.locked} aria-label={t.locked} role="img" style={{ marginRight: 4 }}>
                            🔒
                        </span>
                    )}
                    {column.label}
                </h3>
                <span style={{ ...muted, fontVariantNumeric: 'tabular-nums' }}>{column.total}</span>
            </header>

            <ul style={{ display: 'flex', flexDirection: 'column', gap: 8, margin: 0, padding: 8, listStyle: 'none', minHeight: 64 }}>
                {column.cards.length === 0 && <li style={{ ...muted, padding: '12px 4px', textAlign: 'center' }}>{t.emptyColumn}</li>}
                {column.cards.map((card) => {
                    const id = String(card.id);
                    const movable = board.can_move && targets.length > 0;
                    const selectId = `pc-kanban-move-${id}`;

                    return (
                        <li
                            key={id}
                            draggable={movable || undefined}
                            onDragStart={
                                movable
                                    ? (event: DragEvent) => {
                                          onDrag(column.value);
                                          event.dataTransfer.setData('text/plain', id);
                                      }
                                    : undefined
                            }
                            onDragEnd={movable ? () => onDrag(null) : undefined}
                            style={{ padding: 8, border: '1px solid var(--pc-line)', borderRadius: 'var(--pc-radius)', background: 'var(--pc-bg)', fontSize: '0.875rem', opacity: column.locked ? 0.75 : 1 }}
                        >
                            <button type="button" className="pc-th-btn" style={{ display: 'block', width: '100%', fontWeight: 500, color: 'var(--pc-text)' }} onClick={() => onOpen(card.id)}>
                                {card.title}
                            </button>
                            {card.subtitle !== '' && <p style={{ ...muted, margin: '2px 0 0', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{card.subtitle}</p>}
                            {movable && (
                                <>
                                    <label htmlFor={selectId} style={SR_ONLY}>
                                        {t.moveTo}
                                    </label>
                                    <select
                                        id={selectId}
                                        className="pc-input"
                                        style={{ marginTop: 8, padding: '4px 6px', fontSize: '0.75rem' }}
                                        value={column.other ? '' : column.value}
                                        onChange={(event) => onMove(id, column.value, event.target.value)}
                                    >
                                        {column.other && (
                                            <option value="" disabled>
                                                {t.moveTo}…
                                            </option>
                                        )}
                                        {choices.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {t.moveTo}: {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </>
                            )}
                        </li>
                    );
                })}
            </ul>

            {column.locked && board.locked_message !== '' && <p style={{ ...muted, margin: 0, padding: '0 12px 8px' }}>{board.locked_message}</p>}
            {column.other && <p style={{ ...muted, margin: 0, padding: '0 12px 8px' }}>{t.otherHint}</p>}
            {column.total > column.cards.length && <p style={{ ...muted, margin: 0, padding: '0 12px 8px' }}>{t.more.replace(':count', String(column.total - column.cards.length))}</p>}
        </section>
    );
}

// ── Calendar ────────────────────────────────────────────────────────────────

/** The month grid: each record on its days (start to end), opened by a click. */
export function PtahCrudCalendar({ crud, locale }: BoardProps) {
    const state = useCrudState(crud);
    const t = useMemo(() => boardsWords(locale), [locale]);
    const on = state.schema?.features.calendar ?? false;
    const key = listKey(state.list);
    const [grid, setGrid] = useState<CalendarGrid | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    // The month shown — '' is the current one. It lives here, not on the server.
    const month = useRef('');
    const seq = useRef(0);

    const load = useCallback(
        async (shift = 0) => {
            const mine = ++seq.current;
            setLoading(true);
            try {
                const next = await getCalendar(crud.api, crud.screen, crud.getState().list, month.current, shift);
                if (mine === seq.current) {
                    month.current = month.current === '' && shift === 0 ? '' : next.month;
                    setGrid(next);
                    setError(null);
                }
            } catch (reason) {
                if (mine === seq.current) {
                    setError(failure(reason, t));
                }
            } finally {
                if (mine === seq.current) {
                    setLoading(false);
                }
            }
        },
        [crud, t],
    );

    useEffect(() => {
        if (on) {
            void load();
        }
    }, [on, key, state.rows, load]);

    if (!on) {
        return null;
    }

    if (!grid) {
        return error ? (
            <div className="pc-state" role="alert">
                {error}{' '}
                <ForgeButton color="dark" flat size="sm" onClick={() => void load()}>
                    {t.retry}
                </ForgeButton>
            </div>
        ) : (
            <div className="pc-state">
                <ForgeSpinner label={t.loading} />
            </div>
        );
    }

    return (
        <div aria-busy={loading} style={{ border: '1px solid var(--pc-line)', borderRadius: 'var(--pc-radius)', background: 'var(--pc-bg)', opacity: loading ? 0.7 : 1 }}>
            <header style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8, padding: '8px 12px', borderBottom: '1px solid var(--pc-line)', background: 'var(--pc-bg-soft)' }}>
                <ForgeButton color="dark" flat size="sm" aria-label={t.calendarPrev} title={t.calendarPrev} onClick={() => void load(-1)}>
                    ‹
                </ForgeButton>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <h3 style={{ margin: 0, fontSize: '0.875rem', fontWeight: 600, textTransform: 'capitalize', color: 'var(--pc-text-strong)' }}>{monthTitle(grid.month, locale)}</h3>
                    <ForgeButton
                        color="dark"
                        flat
                        size="sm"
                        onClick={() => {
                            month.current = '';
                            void load();
                        }}
                    >
                        {t.calendarToday}
                    </ForgeButton>
                </div>
                <ForgeButton color="dark" flat size="sm" aria-label={t.calendarNext} title={t.calendarNext} onClick={() => void load(1)}>
                    ›
                </ForgeButton>
            </header>

            {error && <ForgeAlert color="danger">{error}</ForgeAlert>}
            {grid.overflow && (
                <div style={{ padding: '8px 12px 0' }}>
                    <ForgeAlert color="warn">{t.calendarOverflow}</ForgeAlert>
                </div>
            )}

            <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', minWidth: 640, tableLayout: 'fixed', borderCollapse: 'collapse', fontSize: '0.875rem' }}>
                    <thead>
                        <tr>
                            {t.weekdays.map((weekday) => (
                                <th key={weekday} scope="col" style={{ ...muted, padding: '6px 8px', fontWeight: 600, textAlign: 'left' }}>
                                    {weekday}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {grid.weeks.map((week) => (
                            <tr key={week[0]?.date}>
                                {week.map((day) => (
                                    <td key={day.date} data-date={day.date} style={{ verticalAlign: 'top', height: 96, padding: 4, borderTop: '1px solid var(--pc-line)', background: day.in_month ? undefined : 'var(--pc-bg-soft)' }}>
                                        <div
                                            aria-current={day.today ? 'date' : undefined}
                                            style={{
                                                marginBottom: 4,
                                                fontSize: '0.75rem',
                                                fontVariantNumeric: 'tabular-nums',
                                                color: day.today ? 'var(--pc-primary)' : day.in_month ? 'var(--pc-text)' : 'var(--pc-text-muted)',
                                                fontWeight: day.today ? 700 : undefined,
                                            }}
                                        >
                                            {day.day}
                                        </div>
                                        <ul style={{ display: 'flex', flexDirection: 'column', gap: 2, margin: 0, padding: 0, listStyle: 'none' }}>
                                            {day.items.slice(0, CALENDAR_DAY_LIMIT).map((item) => (
                                                <li key={String(item.id)}>
                                                    <button
                                                        type="button"
                                                        className="pc-th-btn"
                                                        title={eventTitle(item)}
                                                        style={{ display: 'block', width: '100%', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', padding: '0 4px', borderRadius: 4, fontSize: '0.75rem', background: 'var(--pc-primary-soft)', color: 'var(--pc-text-strong)' }}
                                                        onClick={() => void crud.openEdit(item.id)}
                                                    >
                                                        {item.title}
                                                    </button>
                                                </li>
                                            ))}
                                            {day.items.length > CALENDAR_DAY_LIMIT && <li style={muted}>+{day.items.length - CALENDAR_DAY_LIMIT}</li>}
                                        </ul>
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// ── Cards ───────────────────────────────────────────────────────────────────

/** The rows of the current page as cards — the same cells and actions as the table. */
export function PtahCrudCards({ crud, locale, renderActions }: CardsProps) {
    const state = useCrudState(crud);
    const t = useMemo(() => boardsWords(locale), [locale]);
    const schema = state.schema;

    if (!schema) {
        return null;
    }

    // The title is the first column that is not the id; the rest are the body.
    const columns = schema.columns.filter((column) => column.field !== 'id');
    const [titleColumn, ...bodyColumns] = columns;
    const sortable = schema.columns.filter((column) => column.sortBy !== null);
    const ascending = state.list.direction.toUpperCase() === 'ASC';
    const sortId = `pc-cards-sort-${crud.screen}`;

    return (
        <div>
            {sortable.length > 0 && (
                <div role="group" aria-label={t.sortGroup} style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
                    <label htmlFor={sortId} style={SR_ONLY}>
                        {t.sortBy}
                    </label>
                    <select id={sortId} className="pc-input" style={{ flex: 1, minWidth: 0 }} title={t.sortBy} value={state.list.sort} onChange={(event) => void crud.patchList({ sort: event.target.value })}>
                        {sortable.map((column) => (
                            <option key={column.field} value={column.sortBy!}>
                                {column.label}
                            </option>
                        ))}
                    </select>
                    <button
                        type="button"
                        className="pc-btn"
                        aria-pressed={ascending}
                        aria-label={ascending ? t.sortAsc : t.sortDesc}
                        title={ascending ? t.sortAsc : t.sortDesc}
                        disabled={state.busy}
                        onClick={() => void crud.patchList({ direction: ascending ? 'DESC' : 'ASC' })}
                    >
                        <span aria-hidden="true">{ascending ? '▲' : '▼'}</span>
                    </button>
                </div>
            )}

            {state.rows.length === 0 ? (
                <div className="pc-state">
                    <ForgeEmpty title={t.emptyTitle} description={t.emptySubtitle} />
                </div>
            ) : (
                <div aria-busy={state.busy} style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: 12, opacity: state.busy ? 0.6 : 1 }}>
                    {state.rows.map((row) => {
                        const selected = state.selection.includes(String(row.id));

                        return (
                            <article
                                key={row.id}
                                aria-label={`#${row.id}`}
                                // configLinkLinha: the card opens its record (Enter when focused).
                                tabIndex={row.link ? 0 : undefined}
                                onClick={row.link ? (event) => followRowLink(event, row.link) : undefined}
                                onAuxClick={row.link ? (event) => followRowLink(event, row.link) : undefined}
                                onKeyDown={row.link ? (event) => followRowLink(event, row.link) : undefined}
                                data-link={row.link ?? undefined}
                                style={{
                                    cursor: row.link ? 'pointer' : undefined,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    padding: 16,
                                    border: '1px solid var(--pc-line)',
                                    borderRadius: 'var(--pc-radius)',
                                    background: selected ? 'var(--pc-primary-soft)' : 'var(--pc-bg)',
                                    outline: selected ? '2px solid var(--pc-primary)' : undefined,
                                    ...styleObject(row.style),
                                }}
                            >
                                <div style={{ display: 'flex', alignItems: 'flex-start', gap: 10, marginBottom: 12 }}>
                                    {schema.selectable && <input type="checkbox" aria-label={t.selectRow.replace(':id', String(row.id))} checked={selected} onChange={() => crud.toggleRow(row.id)} />}
                                    <div style={{ minWidth: 0, flex: 1 }}>
                                        {titleColumn && (
                                            // Rendered and escaped by the server — the table's own cell.
                                            <p style={{ margin: 0, fontWeight: 600, fontSize: '0.875rem', color: 'var(--pc-text-strong)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }} dangerouslySetInnerHTML={{ __html: row.cells[titleColumn.field]?.html ?? '' }} />
                                        )}
                                        <p style={{ ...muted, margin: 0, fontSize: '0.6875rem' }}>#{row.id}</p>
                                    </div>
                                </div>
                                <dl style={{ flex: 1, margin: 0 }}>
                                    {bodyColumns.map((column) => (
                                        <div key={column.field} style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', gap: 12, fontSize: '0.75rem', marginBottom: 6 }}>
                                            <dt style={{ ...muted, flexShrink: 0, fontSize: '0.625rem', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.03em' }}>{column.label}</dt>
                                            <dd style={{ margin: 0, textAlign: 'right', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', color: 'var(--pc-text)' }} dangerouslySetInnerHTML={{ __html: row.cells[column.field]?.html ?? '' }} />
                                        </div>
                                    ))}
                                </dl>
                                <CardFooter crud={crud} state={state} t={t} row={row} renderActions={renderActions} />
                            </article>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function CardFooter({ crud, state, t, row, renderActions }: { crud: CrudController; state: CrudState; t: BoardsWords; row: Row; renderActions?: (row: Row) => ReactNode }) {
    const { permissions } = state.schema!;
    const [pending, setPending] = useState<RowAction | null>(null);
    const footer: CSSProperties = { display: 'flex', alignItems: 'center', justifyContent: 'flex-end', flexWrap: 'wrap', gap: 4, paddingTop: 12, marginTop: 12, borderTop: '1px solid var(--pc-line)' };

    if (renderActions) {
        return <div style={footer}>{renderActions(row)}</div>;
    }

    // The rules of _cards.blade.php: edit and duplicate outside the trash;
    // in the trash a deleted row offers restore, any other row delete.
    const inTrash = state.list.trashed;
    const restorable = inTrash && row.trashed;
    if (!(permissions.canUpdate || permissions.canDelete || (permissions.canCreate && !inTrash) || row.actions.length > 0)) {
        return null;
    }

    const run = (action: RowAction) => {
        if (action.type === 'link') {
            window.location.assign(action.value);
        } else {
            crud.runAction(row, action);
        }
    };

    return (
        <div style={footer}>
            {row.actions.map((action, index) => {
                const content = action.icon ? <i className={action.icon} aria-hidden="true" /> : action.label || '→';

                return action.type === 'link' ? (
                    <a
                        key={index}
                        className="pc-icon-btn"
                        href={action.value}
                        title={action.label}
                        aria-label={action.label}
                        onClick={(event) => {
                            if (action.confirm) {
                                event.preventDefault();
                                setPending(action);
                            }
                        }}
                    >
                        {content}
                    </a>
                ) : (
                    <button key={index} type="button" className="pc-icon-btn" title={action.label} aria-label={action.label} onClick={() => (action.confirm ? setPending(action) : crud.runAction(row, action))}>
                        {content}
                    </button>
                );
            })}
            {permissions.canUpdate && !inTrash && (
                <button type="button" className="pc-icon-btn" title={t.edit} onClick={() => void crud.openEdit(row.id)}>
                    {t.edit}
                </button>
            )}
            {permissions.canCreate && !inTrash && (
                <button type="button" className="pc-icon-btn" title={t.duplicate} onClick={() => void crud.duplicate(row.id)}>
                    {t.duplicate}
                </button>
            )}
            {restorable
                ? permissions.canRestore && (
                      <button type="button" className="pc-icon-btn" title={t.restore} onClick={() => void crud.restore(row.id)}>
                          {t.restore}
                      </button>
                  )
                : permissions.canDelete && (
                      <button type="button" className="pc-icon-btn pc-icon-btn--danger" title={t.delete} onClick={() => crud.askDelete(row.id)}>
                          {t.delete}
                      </button>
                  )}
            <ForgeModal
                open={pending !== null}
                onClose={() => setPending(null)}
                title={pending?.label ?? ''}
                size="sm"
                footer={
                    <>
                        <ForgeButton color="dark" flat onClick={() => setPending(null)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton
                            onClick={() => {
                                const action = pending;
                                setPending(null);
                                if (action) {
                                    run(action);
                                }
                            }}
                        >
                            {t.confirm}
                        </ForgeButton>
                    </>
                }
            >
                {pending?.confirm ?? ''}
            </ForgeModal>
        </div>
    );
}
