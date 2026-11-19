/**
 * The alternative views of a CRUD screen for React — kanban, calendar and
 * cards — and the view/density switch. Each piece reads the screen through
 * its CrudController; the board and the calendar ask the boards API with
 * the listing state the screen shows, so they show the same records.
 */
import { useCallback, useEffect, useMemo, useRef, useState, useSyncExternalStore } from 'react';
import type { DragEvent, ReactNode } from 'react';
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
    styleObject,
    targetsOf,
    viewLabel,
    viewModes,
    watchNarrow,
    withCardMoved,
} from '../../core/crud/boards';
import type { BoardsWords, CalendarGrid, KanbanBoard, KanbanColumn, ViewMode } from '../../core/crud/boards';
import { ForgeAlert, ForgeButton, ForgeModal, ForgeSpinner } from '../forge';
import { Icon } from '../icons';

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
        <>
            <div className="pc-seg" role="group" aria-label={t.viewMode}>
                {viewModes(crud.features).map((item) => (
                    <button
                        key={item}
                        type="button"
                        className="pc-seg-btn"
                        aria-pressed={mode === item}
                        aria-label={viewLabel(t, item)}
                        title={viewLabel(t, item)}
                        disabled={saving}
                        onClick={() => mode !== item && void save({ view_mode: item })}
                    >
                        <Icon name={VIEW_SVG[item]} />
                    </button>
                ))}
            </div>
            <div className="pc-dd-wrap pc-tb-secondary" ref={menu}>
                <button type="button" className="pc-tbtn" aria-haspopup="true" aria-expanded={open} aria-label={t.density} title={t.density} disabled={saving} onClick={() => setOpen(!open)}>
                    <span className="pc-density-glyph" aria-hidden="true">
                        {DENSITY_ICONS[density as keyof typeof DENSITY_ICONS] ?? '☰'}
                    </span>
                    <span className="pc-tbtn-label">{t.density}</span>
                    <Icon name="chevronDown" size={12} />
                </button>
                {open && (
                    <div className="pc-dd" style={{ minWidth: '11.25rem' }}>
                        {DENSITIES.map((item) => (
                            <button
                                key={item}
                                type="button"
                                className={`pc-dd-item pc-dd-item--between${density === item ? ' pc-dd-item--active' : ''}`}
                                aria-current={density === item ? 'true' : undefined}
                                onClick={() => {
                                    setOpen(false);
                                    if (density !== item) {
                                        void save({ view_density: item });
                                    }
                                }}
                            >
                                <span>
                                    {DENSITY_ICONS[item]} {densityLabel(t, item)}
                                </span>
                                {density === item && <Icon name="check" />}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

/** The view buttons' icons: the very SVGs of _toolbar.blade.php. */
const VIEW_SVG: Record<ViewMode, string> = { auto: 'viewAuto', table: 'viewTable', cards: 'viewCards', kanban: 'viewKanban', calendar: 'calendar' };

// ── Kanban ──────────────────────────────────────────────────────────────────

/** The board: one column per option, drag and drop and a "Move to" select per card (_kanban.blade.php). */
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
        return <StateBox error={error} t={t} onRetry={() => void load()} />;
    }

    return (
        <div className="pc-kanban" aria-busy={loading}>
            {error && (
                <div className="pc-kanban-error">
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

/** Loading, or a failure with "try again" — before a board or a month first arrives. */
function StateBox({ error, t, onRetry }: { error: string | null; t: BoardsWords; onRetry: () => void }) {
    return error ? (
        <div className="pc-state" role="alert">
            {error}{' '}
            <ForgeButton color="dark" flat size="sm" onClick={onRetry}>
                {t.retry}
            </ForgeButton>
        </div>
    ) : (
        <div className="pc-state">
            <ForgeSpinner label={t.loading} />
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
            className={`pc-kanban-col${column.locked ? ' pc-kanban-col--locked' : ''}${over ? ' pc-kanban-col--over' : ''}`}
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
            <header className="pc-kanban-head">
                <h3 className="pc-kanban-ttl">
                    {column.locked && <i className="bx bx-lock-alt pc-kanban-lock" role="img" title={t.locked} aria-label={t.locked} />}
                    {column.label}
                </h3>
                <span className="pc-kanban-count">{column.total}</span>
            </header>

            <ul className="pc-kanban-list">
                {column.cards.length === 0 && <li className="pc-kanban-empty">{t.emptyColumn}</li>}
                {column.cards.map((card) => {
                    const id = String(card.id);
                    const movable = board.can_move && targets.length > 0;
                    const selectId = `pc-kanban-move-${id}`;

                    return (
                        <li
                            key={id}
                            className="pc-kanban-card"
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
                        >
                            <button type="button" className="pc-kanban-open" onClick={() => onOpen(card.id)}>
                                {card.title}
                            </button>
                            {card.subtitle !== '' && <p className="pc-kanban-sub">{card.subtitle}</p>}
                            {movable && (
                                <>
                                    <label htmlFor={selectId} className="pc-sr-only">
                                        {t.moveTo}
                                    </label>
                                    <select id={selectId} className="pc-kanban-move" value={column.other ? '' : column.value} onChange={(event) => onMove(id, column.value, event.target.value)}>
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

            {column.locked && board.locked_message !== '' && <p className="pc-kanban-note">{board.locked_message}</p>}
            {column.other && <p className="pc-kanban-note">{t.otherHint}</p>}
            {column.total > column.cards.length && <p className="pc-kanban-note">{t.more.replace(':count', String(column.total - column.cards.length))}</p>}
        </section>
    );
}

// ── Calendar ────────────────────────────────────────────────────────────────

/** The month grid: each record on its days (start to end), opened by a click (_calendar.blade.php). */
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
        return <StateBox error={error} t={t} onRetry={() => void load()} />;
    }

    return (
        <div className="pc-cal" aria-busy={loading}>
            <header className="pc-cal-head">
                <button type="button" className="pc-cal-btn" aria-label={t.calendarPrev} title={t.calendarPrev} onClick={() => void load(-1)}>
                    ‹
                </button>
                <div className="pc-cal-center">
                    <h3 className="pc-cal-ttl">{monthTitle(grid.month, locale)}</h3>
                    <button
                        type="button"
                        className="pc-cal-btn"
                        onClick={() => {
                            month.current = '';
                            void load();
                        }}
                    >
                        {t.calendarToday}
                    </button>
                </div>
                <button type="button" className="pc-cal-btn" aria-label={t.calendarNext} title={t.calendarNext} onClick={() => void load(1)}>
                    ›
                </button>
            </header>

            {error && <ForgeAlert color="danger">{error}</ForgeAlert>}
            {grid.overflow && (
                <div className="pc-cal-alert">
                    <ForgeAlert color="warn">{t.calendarOverflow}</ForgeAlert>
                </div>
            )}

            <div className="pc-cal-scroll">
                <table className="pc-cal-table">
                    <thead>
                        <tr>
                            {t.weekdays.map((weekday) => (
                                <th key={weekday} scope="col">
                                    {weekday}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {grid.weeks.map((week) => (
                            <tr key={week[0]?.date}>
                                {week.map((day) => (
                                    <td key={day.date} data-date={day.date} className={day.in_month ? undefined : 'pc-cal-out'}>
                                        <div className="pc-cal-day" aria-current={day.today ? 'date' : undefined}>
                                            {day.day}
                                        </div>
                                        <ul className="pc-cal-items">
                                            {day.items.slice(0, CALENDAR_DAY_LIMIT).map((item) => (
                                                <li key={String(item.id)}>
                                                    <button type="button" className="pc-cal-event" title={eventTitle(item)} onClick={() => void crud.openEdit(item.id)}>
                                                        {item.title}
                                                    </button>
                                                </li>
                                            ))}
                                            {day.items.length > CALENDAR_DAY_LIMIT && <li className="pc-cal-more">+{day.items.length - CALENDAR_DAY_LIMIT}</li>}
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

/** The rows of the current page as cards — the same cells and actions as the table (_cards.blade.php). */
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

    return (
        <div>
            <SortBar crud={crud} state={state} t={t} />

            <div className="pc-cards" aria-busy={state.busy}>
                {state.rows.length === 0 ? (
                    <div className="pc-cards-empty">
                        <div className="pc-empty-state">
                            <div className="pc-empty-box">
                                <Icon name="inbox" size={32} strokeWidth={1.5} />
                            </div>
                            <div>
                                <p className="pc-empty-ttl">{t.emptyTitle}</p>
                                <p className="pc-empty-sub">{t.emptySubtitle}</p>
                            </div>
                        </div>
                    </div>
                ) : (
                    state.rows.map((row) => {
                        const selected = state.selection.includes(String(row.id));

                        return (
                            <article
                                key={row.id}
                                aria-label={`#${row.id}`}
                                className={`pc-card${row.link ? ' pc-card--link' : ''}${selected ? ' pc-card--selected' : ''}`}
                                // configLinkLinha: the card opens its record (Enter when focused).
                                tabIndex={row.link ? 0 : undefined}
                                onClick={row.link ? (event) => followRowLink(event, row.link) : undefined}
                                onAuxClick={row.link ? (event) => followRowLink(event, row.link) : undefined}
                                onKeyDown={row.link ? (event) => followRowLink(event, row.link) : undefined}
                                data-link={row.link ?? undefined}
                                // The row style of the config (a coloured stripe, a tint) — as the table row.
                                style={styleObject(row.style)}
                            >
                                <div className="pc-card-head">
                                    {schema.selectable && (
                                        <input
                                            type="checkbox"
                                            aria-label={t.selectRow.replace(':id', String(row.id))}
                                            checked={selected}
                                            onClick={(event) => event.stopPropagation()}
                                            onChange={() => crud.toggleRow(row.id)}
                                        />
                                    )}
                                    <div className="pc-card-heading">
                                        {titleColumn && (
                                            // Rendered and escaped by the server — the table's own cell.
                                            <p className="pc-card-ttl" dangerouslySetInnerHTML={{ __html: row.cells[titleColumn.field]?.html ?? '' }} />
                                        )}
                                        <p className="pc-card-id">#{row.id}</p>
                                    </div>
                                </div>
                                <dl className="pc-card-body">
                                    {bodyColumns.map((column) => (
                                        <div key={column.field} className="pc-card-row">
                                            <dt>{column.label}</dt>
                                            <dd dangerouslySetInnerHTML={{ __html: row.cells[column.field]?.html ?? '' }} />
                                        </div>
                                    ))}
                                </dl>
                                <CardFooter crud={crud} state={state} t={t} row={row} renderActions={renderActions} />
                            </article>
                        );
                    })
                )}
            </div>
        </div>
    );
}

/** The ordering of the cards: a column select and a direction button (_sort-bar.blade.php). */
function SortBar({ crud, state, t }: { crud: CrudController; state: CrudState; t: BoardsWords }) {
    const sortable = state.schema!.columns.filter((column) => column.sortBy !== null);
    if (sortable.length === 0) {
        return null;
    }
    const ascending = state.list.direction.toUpperCase() === 'ASC';
    const sortId = `pc-cards-sort-${crud.screen}`;

    return (
        <div className="pc-sortbar" role="group" aria-label={t.sortGroup}>
            <label htmlFor={sortId} className="pc-sr-only">
                {t.sortBy}
            </label>
            <Icon name="sortBar" className="pc-sortbar-icon" />
            <select id={sortId} className="pc-sortbar-select" title={t.sortBy} value={state.list.sort} onChange={(event) => void crud.patchList({ sort: event.target.value })}>
                {sortable.map((column) => (
                    <option key={column.field} value={column.sortBy!}>
                        {column.label}
                    </option>
                ))}
            </select>
            <button
                type="button"
                className="pc-sortbar-dir"
                aria-pressed={ascending}
                aria-label={ascending ? t.sortAsc : t.sortDesc}
                title={ascending ? t.sortAsc : t.sortDesc}
                disabled={state.busy}
                onClick={() => void crud.patchList({ direction: ascending ? 'DESC' : 'ASC' })}
            >
                <Icon name={ascending ? 'sortAsc' : 'sortDesc'} />
            </button>
        </div>
    );
}

function CardFooter({ crud, state, t, row, renderActions }: { crud: CrudController; state: CrudState; t: BoardsWords; row: Row; renderActions?: (row: Row) => ReactNode }) {
    const { permissions } = state.schema!;
    const [pending, setPending] = useState<RowAction | null>(null);

    if (renderActions) {
        const actions = renderActions(row);

        // A footer only when the parent gives it something to hold.
        return actions === null || actions === undefined || actions === false ? null : <div className="pc-card-foot">{actions}</div>;
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
    const stop = (event: MouseEvent) => event.stopPropagation();

    return (
        <div className="pc-card-foot">
            {row.actions.map((action, index) => {
                const content = action.icon ? <i className={action.icon} aria-hidden="true" /> : action.label || '→';

                return action.type === 'link' ? (
                    <a
                        key={index}
                        className="pc-act pc-act--custom"
                        data-color={action.color || undefined}
                        href={action.value}
                        title={action.label}
                        aria-label={action.label}
                        onClick={(event) => {
                            event.stopPropagation();
                            if (action.confirm) {
                                event.preventDefault();
                                setPending(action);
                            }
                        }}
                    >
                        {content}
                    </a>
                ) : (
                    <button
                        key={index}
                        type="button"
                        className="pc-act pc-act--custom"
                        data-color={action.color || undefined}
                        title={action.label}
                        aria-label={action.label}
                        onClick={(event) => {
                            event.stopPropagation();
                            if (action.confirm) {
                                setPending(action);
                            } else {
                                crud.runAction(row, action);
                            }
                        }}
                    >
                        {content}
                    </button>
                );
            })}
            {permissions.canUpdate && !inTrash && (
                <button
                    type="button"
                    className="pc-act pc-act--edit"
                    title={t.edit}
                    aria-label={t.edit}
                    onClick={(event) => {
                        stop(event.nativeEvent);
                        void crud.openEdit(row.id);
                    }}
                >
                    <Icon name="edit" />
                </button>
            )}
            {permissions.canCreate && !inTrash && (
                <button
                    type="button"
                    className="pc-act pc-act--dup"
                    title={t.duplicate}
                    aria-label={t.duplicate}
                    onClick={(event) => {
                        stop(event.nativeEvent);
                        void crud.duplicate(row.id);
                    }}
                >
                    <Icon name="duplicate" />
                </button>
            )}
            {restorable
                ? permissions.canRestore && (
                      <button
                          type="button"
                          className="pc-act pc-act--restore"
                          title={t.restore}
                          aria-label={t.restore}
                          onClick={(event) => {
                              stop(event.nativeEvent);
                              void crud.restore(row.id);
                          }}
                      >
                          <Icon name="refresh" />
                      </button>
                  )
                : permissions.canDelete && (
                      <button
                          type="button"
                          className="pc-act pc-act--del"
                          title={t.delete}
                          aria-label={t.delete}
                          onClick={(event) => {
                              stop(event.nativeEvent);
                              crud.askDelete(row.id);
                          }}
                      >
                          <Icon name="trash" />
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
