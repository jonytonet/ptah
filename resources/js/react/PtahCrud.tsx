import { Fragment, useEffect, useMemo, useRef, useState, useSyncExternalStore } from 'react';
import type { ReactNode } from 'react';
import { CrudController, debounce, words } from '../core';
import type { ControllerOptions, CrudState, Row, RowAction, Words } from '../core';
import { followRowLink, nextViewMode } from '../core/crud/boards';
import { fullOrder, moveColumn, saveColumnOrder } from '../core/crud/extras';
import { cellActionWords, installCellActions } from '../core/crud/cell-actions';
import { clearAllFilters, filterSession, filtersWords } from '../core/crud/filters';
import {
    actionColumns,
    activeFilterCount,
    aggregateLabel,
    fillWords,
    isFiltered,
    pageElements,
    perPageChoices,
    remeasureToolbar,
    rowStyle,
    screenWords,
    shortcutOf,
    showsRowButtons,
    toolbarClearable,
    watchToolbarLabels,
} from '../core/crud/screen';
import type { ScreenWords } from '../core/crud/screen';
import { PtahCrudConfigButton } from './admin/crud-config';
import { PtahCrudCalendar, PtahCrudCards, PtahCrudKanban, PtahCrudViewSwitch, useResolvedView } from './crud/boards';
import { PtahCrudColumnResizer, PtahCrudColumnsMenu, PtahCrudDetailRow, PtahCrudDetailToggle } from './crud/extras';
import { PtahCrudFilterBadges, PtahCrudFilterPanel, PtahCrudUrlFilters } from './crud/filters';
import { PtahConfirmDialog, PtahCrudFormModal } from './crud/form';
import { PtahCrudModal } from './crud/modal';
import { modalButton } from '../core/crud/form';
import { PtahCrudImport, PtahCrudImportButton } from './crud/import';
import { PtahCrudAttachments, PtahCrudHistory } from './crud/records';
import { Icon } from './icons';

/** The create/edit modal (kept under its old name; it lives in ./crud/form). */
export { PtahCrudFormModal as FormModal } from './crud/form';

export interface PtahCrudProps extends ControllerOptions {
    /** The key the screen was registered with: `CrudScreen::register('products', …)`. */
    screen: string;
    /** `pt_BR`, `en`… Defaults to the page's `<html lang>`. */
    locale?: string;
    /**
     * A title row at the start of the toolbar. The Livewire screen has none
     * (the page header above it names the screen): give one only when the
     * screen is mounted alone.
     */
    title?: ReactNode;
    /** Extra buttons at the end of the toolbar. */
    toolbar?: ReactNode;
    /** A controller of your own (to drive the screen from outside). */
    controller?: CrudController;
}

/** The state of a controller, re-rendering on every change. */
export function usePtahCrud(controller: CrudController): CrudState {
    return useSyncExternalStore(controller.subscribe, controller.getState, controller.getState);
}

/** The shape of a CRUD screen while its first data is on the way: a toolbar and placeholder rows. */
export function PtahCrudSkeleton({ label, rows = 8 }: { label: string; rows?: number }) {
    return (
        <div className="pc-root pc-skeleton" role="status" aria-busy="true" aria-label={label}>
            <span className="pc-visually-hidden">{label}</span>
            <div className="pc-skeleton-toolbar" aria-hidden="true">
                <span className="pc-skeleton-bar pc-skeleton-bar--search" />
                <span className="pc-skeleton-spacer" />
                <span className="pc-skeleton-bar pc-skeleton-bar--button" />
                <span className="pc-skeleton-bar pc-skeleton-bar--button" />
            </div>
            <div className="pc-skeleton-table" aria-hidden="true">
                {Array.from({ length: rows + 1 }, (_, row) => (
                    <div key={row} className={row === 0 ? 'pc-skeleton-row pc-skeleton-row--head' : 'pc-skeleton-row'}>
                        {[18, 34, 22, 14].map((width, cell) => (
                            <span key={cell} className="pc-skeleton-bar" style={{ width: `${width}%` }} />
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}

/**
 * A complete CRUD screen over the ptah headless API, drawn as the Livewire
 * BaseCrud (base-crud.blade.php and its partials): toolbar, filter panel,
 * table / cards / board / calendar, pagination, form, bulk bar, shortcuts.
 */
export function PtahCrud({ screen, locale, title, toolbar, controller, ...options }: PtahCrudProps) {
    // One controller per screen key. The options are read once, when it is made.
    const crud = useMemo(() => controller ?? new CrudController(screen, options), [controller, screen]); // eslint-disable-line react-hooks/exhaustive-deps
    const state = usePtahCrud(crud);
    const t = useMemo(() => words(locale), [locale]);
    const w = useMemo(() => screenWords(locale), [locale]);
    const view = useResolvedView(crud);
    const [showFilters, setShowFilters] = useState(false);
    const [showKeys, setShowKeys] = useState(false);
    const searchRef = useRef<HTMLInputElement>(null);
    const nested = crud.screen.includes('..');

    useEffect(() => {
        void crud.init();

        return () => {
            if (!controller) {
                crud.destroy();
            }
        };
    }, [crud, controller]);

    // "Copy" and "Filter by this value" over the cell the mouse rests on.
    useEffect(() => installCellActions(crud, { words: cellActionWords(locale), enabled: () => crud.features.copyCell }), [crud, locale]);

    // The shortcuts of the Livewire screen (`_hotkeys`): ? f v r / n. A detail grid has none.
    useEffect(() => {
        if (nested) {
            return;
        }
        const onKey = (event: KeyboardEvent) => {
            const now = crud.getState();
            const action = shortcutOf(event, Boolean(now.schema?.permissions.canCreate) && !now.list.trashed);
            if (!action || now.form || now.panel || now.deleting !== null) {
                return;
            }
            event.preventDefault();
            if (action === 'help') {
                setShowKeys(true);
            } else if (action === 'filters') {
                setShowFilters((open) => !open);
            } else if (action === 'view') {
                void crud.savePreferences({ view_mode: nextViewMode(now.preferences?.view_mode) });
            } else if (action === 'refresh') {
                void crud.reload();
            } else if (action === 'search') {
                searchRef.current?.focus();
            } else {
                void crud.openCreate();
            }
        };
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [crud, nested]);

    // The first load: the shape of the screen (toolbar and rows), never a bare "Loading…".
    if (state.status === 'loading') {
        return <PtahCrudSkeleton label={t.loading} />;
    }

    if (state.status !== 'ready' || !state.schema) {
        const text = { loading: t.loading, forbidden: t.forbidden, 'not-found': t.notFound, error: t.error, ready: t.loading }[state.status];

        return (
            <div className="pc-root">
                <div className="pc-state" role="alert">
                    {text}
                </div>
            </div>
        );
    }

    const board = view === 'kanban' || view === 'calendar';

    return (
        <div className="pc-root pc-crud" data-ptah-crud={crud.screen} data-density={state.preferences?.view_density ?? 'global'}>
            {/* A detail grid (a child screen) does not read the page's address. */}
            <PtahCrudUrlFilters crud={crud} locale={locale} readLocation={!nested} />
            <Toolbar
                crud={crud}
                state={state}
                w={w}
                title={title}
                extra={toolbar}
                locale={locale}
                searchRef={searchRef}
                showFilters={showFilters}
                onToggleFilters={() => setShowFilters(!showFilters)}
            />
            {/* PTAH_CRUD_FILTER_PANEL=modal (1.62.0): the same panel in a dialog. */}
            {showFilters &&
                (crud.features.filterModal ? (
                    <PtahCrudModal
                        title={filtersWords(locale).title}
                        size="4xl"
                        closeLabel={filtersWords(locale).close}
                        onClose={() => setShowFilters(false)}
                        footer={
                            <button type="button" className={modalButton('primary')} onClick={() => setShowFilters(false)}>
                                {filtersWords(locale).showResults}
                            </button>
                        }
                    >
                        <PtahCrudFilterPanel crud={crud} locale={locale} />
                    </PtahCrudModal>
                ) : (
                    <PtahCrudFilterPanel crud={crud} locale={locale} />
                ))}
            <div className="pc-loadbar" aria-hidden="true">
                {state.busy && <span />}
            </div>
            {view === 'kanban' ? (
                <PtahCrudKanban crud={crud} locale={locale} />
            ) : view === 'calendar' ? (
                <PtahCrudCalendar crud={crud} locale={locale} />
            ) : view === 'cards' ? (
                <PtahCrudCards crud={crud} locale={locale} renderActions={(row) => <RowButtons crud={crud} state={state} t={t} w={w} row={row} card />} />
            ) : (
                <Table crud={crud} state={state} t={t} w={w} locale={locale} options={options} />
            )}
            {/* A board shows every record of the filter: no pages. */}
            {!board && <Pagination crud={crud} state={state} t={t} w={w} />}
            {state.selection.length > 0 && <BulkBar crud={crud} state={state} t={t} w={w} />}
            {(state.form || crud.formOpening === 'create') && <PtahCrudFormModal crud={crud} state={state} t={t} locale={locale} />}
            {/* Editing: a loading over the screen at the click; the modal opens with the record in it. */}
            {!state.form && crud.formOpening === 'edit' && (
                <div className="pc-busy" role="status" aria-label={t.loading} data-ptah-opening="edit">
                    <span className="pc-busy-spin" aria-hidden="true" />
                </div>
            )}
            {/* After the form in the DOM: they open over it. */}
            <PtahCrudHistory crud={crud} locale={locale} />
            <PtahCrudAttachments crud={crud} locale={locale} />
            <PtahCrudImport crud={crud} locale={locale} />
            {state.deleting !== null && (
                <PtahConfirmDialog
                    title={t.deleteTitle}
                    text={crud.features.undoDelete ? t.deleteMessageUndoable : t.deleteMessage}
                    confirm={t.delete}
                    cancel={t.cancel}
                    closeLabel={t.close}
                    danger
                    onConfirm={() => void crud.confirmDelete()}
                    onCancel={() => crud.cancelDelete()}
                />
            )}
            {showKeys && <Shortcuts w={w} canCreate={state.schema.permissions.canCreate} onClose={() => setShowKeys(false)} />}
            <Toasts crud={crud} state={state} w={w} locale={locale} />
        </div>
    );
}

interface PartProps {
    crud: CrudController;
    state: CrudState;
    t: Words;
    w: ScreenWords;
    locale?: string;
}

// ── Toolbar (_toolbar.blade.php) ────────────────────────────────────────────

interface ToolbarProps extends Omit<PartProps, 't'> {
    title?: ReactNode;
    extra?: ReactNode;
    searchRef: React.RefObject<HTMLInputElement | null>;
    showFilters: boolean;
    onToggleFilters: () => void;
}

function Toolbar({ crud, state, w, title, extra, locale, searchRef, showFilters, onToggleFilters }: ToolbarProps) {
    const schema = state.schema!;
    const [search, setSearch] = useState(state.list.search);
    const [more, setMore] = useState(false);
    const row = useRef<HTMLDivElement>(null);
    const apply = useMemo(() => debounce((value: string) => void crud.setSearch(value), 400), [crud]);
    const filterCount = activeFilterCount(state.list);
    const trashed = state.list.trashed;

    // The search box follows the state when it changes from outside (restore, clear).
    useEffect(() => setSearch(state.list.search), [state.list.search]);
    useEffect(() => () => apply.cancel(), [apply]);
    useEffect(() => (row.current ? watchToolbarLabels(row.current) : undefined), []);
    // Opening `⋯` re-renders the row's class: measure again.
    useEffect(() => remeasureToolbar(row.current), [more]);

    const clearSearch = () => {
        apply.cancel();
        setSearch('');
        void crud.setSearch('');
    };

    return (
        <div className="pc-tb">
            <div ref={row} className={`pc-tb-row${more ? ' pc-tb-row--more' : ''}`}>
                {title !== undefined && title !== null && <h2 className="pc-tb-title">{title}</h2>}
                {schema.permissions.canCreate && !trashed && (
                    <button type="button" className="pc-tbtn pc-tbtn--primary pc-tb-new" onClick={() => void crud.openCreate()}>
                        <Icon name="plus" />
                        {w.newButton}
                    </button>
                )}
                <div className="pc-tb-search">
                    <label className="pc-search-box">
                        <span className="pc-sr-only">{w.searchPlaceholder}</span>
                        <Icon name="search" />
                        <input
                            ref={searchRef}
                            type="search"
                            className="pc-search-input"
                            placeholder={w.searchPlaceholder}
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                apply(event.target.value);
                            }}
                        />
                    </label>
                    {search !== '' && (
                        <button type="button" className="pc-search-x" title={w.clearFilters} aria-label={w.clearFilters} onClick={clearSearch}>
                            <Icon name="x" size={14} strokeWidth={2.5} />
                        </button>
                    )}
                </div>
                <div className="pc-tb-actions">
                    {/* Always: quick dates and the advanced search need no filterable column. */}
                    <button type="button" className={`pc-tbtn${showFilters ? ' pc-tbtn--on' : ''}`} title={w.filters} aria-label={w.filters} aria-expanded={showFilters} onClick={onToggleFilters}>
                        <Icon name="funnel" />
                        <span className="pc-tbtn-label">{w.filters}</span>
                        {filterCount > 0 && <span className="pc-count">{filterCount}</span>}
                    </button>
                    {schema.trash && (
                        <button
                            type="button"
                            className={`pc-tbtn pc-tb-secondary${trashed ? ' pc-tbtn--trash-on' : ''}`}
                            title={trashed ? w.viewActive : w.viewTrash}
                            aria-label={trashed ? w.viewActive : w.viewTrash}
                            aria-pressed={trashed}
                            onClick={() => void crud.toggleTrashed()}
                        >
                            <Icon name={trashed ? 'arrowLeft' : 'trash'} />
                            <span className="pc-tbtn-label">{trashed ? w.back : w.trash}</span>
                        </button>
                    )}
                    <PtahCrudImportButton crud={crud} locale={locale} />
                    {schema.export.enabled && <ExportMenu crud={crud} w={w} formats={schema.export.formats} />}
                    <PtahCrudColumnsMenu crud={crud} locale={locale} />
                    <button
                        type="button"
                        className={`pc-tbtn pc-tb-more${more ? ' pc-tbtn--on' : ''}`}
                        aria-expanded={more}
                        title={more ? w.moreHide : w.more}
                        aria-label={more ? w.moreHide : w.more}
                        onClick={() => setMore(!more)}
                    >
                        <Icon name="dots" />
                    </button>
                    <PtahCrudViewSwitch crud={crud} locale={locale} />
                    <PtahCrudConfigButton crud={crud} locale={locale} />
                    <button type="button" className="pc-tbtn pc-tb-secondary" title={w.refresh} aria-label={w.refresh} onClick={() => void crud.reload()}>
                        <Icon name="refresh" className={state.busy ? 'pc-spin' : undefined} />
                    </button>
                    {toolbarClearable(state.list) && (
                        <button type="button" className="pc-tbtn pc-tbtn--clear" title={w.clearFilters} aria-label={w.clearFilters} onClick={() => void clearAllFilters(crud, filterSession(crud))}>
                            <Icon name="x" />
                        </button>
                    )}
                    {state.meta && (
                        <select className="pc-perpage pc-tb-secondary" aria-label={w.perPageLabel} value={state.meta.per_page} onChange={(event) => void crud.setPerPage(Number(event.target.value))}>
                            {perPageChoices(state.meta.per_page).map((size) => (
                                <option key={size} value={size}>
                                    {size} {w.perPageSuffix}
                                </option>
                            ))}
                        </select>
                    )}
                    {extra}
                </div>
            </div>
            <PtahCrudFilterBadges crud={crud} locale={locale} />
        </div>
    );
}

/** A dropdown of the toolbar: closes on a click outside and on Escape. */
function useDropdown() {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }
        const close = (event: MouseEvent | KeyboardEvent) => {
            if (event instanceof KeyboardEvent ? event.key === 'Escape' : !ref.current?.contains(event.target as Node)) {
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

    return { open, setOpen, ref };
}

/** Exportar ▾ — one entry per format, then Print. */
function ExportMenu({ crud, w, formats }: { crud: CrudController; w: ScreenWords; formats: string[] }) {
    const { open, setOpen, ref } = useDropdown();
    const run = (format: string) => {
        setOpen(false);
        void crud.export(format);
    };

    return (
        <div className="pc-dd-wrap pc-tb-secondary" ref={ref}>
            <button type="button" className="pc-tbtn" title={w.export} aria-label={w.export} aria-haspopup="true" aria-expanded={open} onClick={() => setOpen(!open)}>
                <Icon name="fileDown" />
                <span className="pc-tbtn-label">{w.export}</span>
                <Icon name="chevronDown" size={12} />
            </button>
            {open && (
                <div className="pc-dd" style={{ minWidth: '10rem' }}>
                    {formats.map((format) => (
                        <button key={format} type="button" className="pc-dd-item" onClick={() => run(format)}>
                            <Icon name={format === 'excel' ? 'fileChart' : format === 'pdf' ? 'file' : 'download'} className={format === 'excel' ? 'pc-dd-ico-excel' : format === 'pdf' ? 'pc-dd-ico-pdf' : 'pc-dd-ico-muted'} />
                            {format === 'excel' ? 'Excel' : format === 'pdf' ? 'PDF' : format.toUpperCase()}
                        </button>
                    ))}
                    <div className="pc-dd-sep" />
                    <button type="button" className="pc-dd-item" onClick={() => run('print')}>
                        <Icon name="printer" className="pc-dd-ico-muted" />
                        {w.print}
                    </button>
                </div>
            )}
        </div>
    );
}

// ── Table (_table.blade.php) ────────────────────────────────────────────────

function Table({ crud, state, t, w, locale, options }: PartProps & { options: ControllerOptions }) {
    const schema = state.schema!;
    const { permissions, features } = schema;
    const trashed = state.list.trashed;
    const rowButtons = showsRowButtons(permissions, trashed);
    const actions = actionColumns(state.rows);
    const allSelected = state.rows.length > 0 && state.rows.every((row) => state.selection.includes(String(row.id)));
    const detail = features.masterDetail;
    const span = schema.columns.length + (schema.selectable ? 1 : 0) + (rowButtons ? 1 : 0) + (detail ? 1 : 0) + actions.length;
    const widths = state.preferences?.column_widths ?? {};
    const widthOf = (field: string) => (widths[field] ? { width: `${widths[field]}px`, minWidth: 60 } : undefined);
    const [dragged, setDragged] = useState<string | null>(null);
    // A header dropped on another one takes its place (the saved order of the user).
    const drop = (target: string) => {
        if (dragged && dragged !== target && state.preferences) {
            const shown = schema.columns.map((column) => column.field);
            void saveColumnOrder(crud, moveColumn(fullOrder(state.preferences), shown, dragged, shown.indexOf(target)));
        }
        setDragged(null);
    };
    const breaks = new Map(state.breaks.map((group) => [group.start, group]));
    const ends = new Map(state.breaks.map((group) => [group.start + group.count - 1, group]));
    const filtered = isFiltered(state.list);
    const lead = (detail ? 1 : 0) + (schema.selectable ? 1 : 0);

    const subtotalRow = (group: (typeof state.breaks)[number]) => (
        <tr className="pc-subtotal" key={`subtotal-${group.start}`}>
            {lead > 0 && <td colSpan={lead} />}
            {schema.columns.map((column, index) => (
                <td key={column.field} className={alignClass(column.align)}>
                    {group.subtotals[column.field] ?? (index === 0 ? <span className="pc-break-key">{fillWords(w.subtotal, { n: group.count })}</span> : null)}
                </td>
            ))}
            {actions.map((label) => (
                <td key={label} />
            ))}
            {rowButtons && <td />}
        </tr>
    );

    return (
        <div className="pc-grid-wrap" aria-busy={state.busy}>
            <table className="pc-grid">
                <thead>
                    <tr>
                        {detail && <th className="pc-th-narrow" />}
                        {schema.selectable && (
                            <th className="pc-th-narrow">
                                <input type="checkbox" aria-label={w.selectAll} checked={allSelected} onChange={() => crud.toggleAll()} />
                            </th>
                        )}
                        {schema.columns.map((column) => {
                            const sorted = column.sortBy !== null && state.list.sort === column.sortBy;
                            const ascending = state.list.direction.toUpperCase() === 'ASC';

                            return (
                                <th
                                    key={column.field}
                                    className={`${alignClass(column.align) ?? ''}${dragged === column.field ? ' pc-th-dragging' : ''}`}
                                    aria-sort={column.sortBy !== null ? (sorted ? (ascending ? 'ascending' : 'descending') : 'none') : undefined}
                                    style={{ position: 'sticky', ...widthOf(column.field) }}
                                    draggable
                                    onDragStart={() => setDragged(column.field)}
                                    onDragOver={(event) => dragged && event.preventDefault()}
                                    onDrop={() => drop(column.field)}
                                    onDragEnd={() => setDragged(null)}
                                >
                                    <div className="pc-th-in">
                                        <span className="pc-th-grip" title={w.dragColumn} aria-hidden="true">
                                            <Grip />
                                        </span>
                                        {column.sortBy !== null ? (
                                            <button type="button" className="pc-th-sort" aria-label={fillWords(w.sortBy, { column: column.label })} onClick={() => void crud.sortBy(column.sortBy!)}>
                                                {column.label}
                                                {sorted ? (
                                                    <Icon name={ascending ? 'chevronUp' : 'chevronDown'} size={12} strokeWidth={2.5} className="pc-sort-on" />
                                                ) : (
                                                    <Icon name="sortIdle" size={12} className="pc-sort-idle" />
                                                )}
                                            </button>
                                        ) : (
                                            <span className="pc-th-label">{column.label}</span>
                                        )}
                                    </div>
                                    <PtahCrudColumnResizer crud={crud} field={column.field} label={column.label} locale={locale} />
                                </th>
                            );
                        })}
                        {actions.map((label) => (
                            <th key={label} className="pc-th-action">
                                {label || w.action}
                            </th>
                        ))}
                        {rowButtons && <th className="pc-sticky">{w.actions}</th>}
                    </tr>
                </thead>
                <tbody>
                    {state.rows.length === 0 && (
                        <tr>
                            <td className="pc-empty-cell" colSpan={span}>
                                <EmptyState w={w} filtered={filtered} onClear={() => void clearAllFilters(crud, filterSession(crud))} />
                            </td>
                        </tr>
                    )}
                    {state.rows.map((row, index) => {
                        const selected = state.selection.includes(String(row.id));
                        const group = breaks.get(index);
                        const end = ends.get(index);

                        return (
                            <Fragment key={row.id}>
                                {group && (
                                    <tr className="pc-break">
                                        <td colSpan={span}>
                                            <span className="pc-break-key">{group.label}:</span> <span dangerouslySetInnerHTML={{ __html: group.title }} />
                                        </td>
                                    </tr>
                                )}
                                <tr
                                    className={`pc-tr${selected ? ' pc-tr--selected' : ''}${row.link ? ' pc-tr--link' : ''}`}
                                    style={rowStyle(row.style)}
                                    // configLinkLinha: the row opens its record (Enter when focused).
                                    tabIndex={row.link ? 0 : undefined}
                                    data-link={row.link ?? undefined}
                                    onClick={row.link ? (event) => followRowLink(event, row.link) : undefined}
                                    onAuxClick={row.link ? (event) => followRowLink(event, row.link) : undefined}
                                    onKeyDown={row.link ? (event) => followRowLink(event, row.link) : undefined}
                                >
                                    {detail && (
                                        <td className="pc-td-narrow" onClick={(event) => event.stopPropagation()}>
                                            <PtahCrudDetailToggle crud={crud} row={row} locale={locale} />
                                        </td>
                                    )}
                                    {schema.selectable && (
                                        <td className="pc-td-narrow" onClick={(event) => event.stopPropagation()}>
                                            <input type="checkbox" aria-label={fillWords(w.selectRow, { id: row.id })} checked={selected} onChange={() => crud.toggleRow(row.id)} />
                                        </td>
                                    )}
                                    {schema.columns.map((column) => (
                                        // The HTML comes rendered and escaped by the server — the same
                                        // string the Livewire screen prints for this cell.
                                        <td
                                            key={column.field}
                                            className={alignClass(column.align)}
                                            style={widthOf(column.field)}
                                            // What the copy / filter buttons of the cell read (core/crud/cell-actions).
                                            data-ptah-cell={column.field}
                                            data-ptah-row={row.id}
                                            dangerouslySetInnerHTML={{ __html: row.cells[column.field]?.html ?? '' }}
                                        />
                                    ))}
                                    {actions.map((label) => {
                                        const action = row.actions.find((item) => item.label === label);

                                        return (
                                            <td key={label} className="pc-align-center" onClick={(event) => event.stopPropagation()}>
                                                {action && <ActionButton crud={crud} row={row} action={action} w={w} />}
                                            </td>
                                        );
                                    })}
                                    {rowButtons && (
                                        <td className="pc-sticky">
                                            <RowButtons crud={crud} state={state} t={t} w={w} row={row} />
                                        </td>
                                    )}
                                </tr>
                                {end && subtotalRow(end)}
                                {detail && (
                                    <PtahCrudDetailRow
                                        crud={crud}
                                        row={row}
                                        colSpan={span}
                                        locale={locale}
                                        // The child screen, bound by the server to this row: its own
                                        // controller, nothing remembered (the Livewire nested grid
                                        // starts clean on every expansion too). Its title is the small
                                        // heading above it, as in _table.blade.php.
                                        renderDetail={(child) => (
                                            <>
                                                {child.title && <p className="pc-detail-title">{child.title}</p>}
                                                <PtahCrud {...options} screen={child.screen} locale={locale} api={crud.api} remember={false} />
                                            </>
                                        )}
                                    />
                                )}
                            </Fragment>
                        );
                    })}
                </tbody>
                {state.totals.length > 0 && (
                    <tfoot>
                        <tr>
                            {lead > 0 && <td colSpan={lead} />}
                            {schema.columns.map((column) => {
                                const here = state.totals.filter((total) => total.field === column.field && total.value !== null);

                                return (
                                    <td key={column.field} className={alignClass(column.align)}>
                                        {here.map((total) => (
                                            <div key={total.aggregate}>
                                                {here.length > 1 && <span className="pc-total-agg">{aggregateLabel(w, total.aggregate)}</span>}
                                                {total.text ?? String(total.value ?? '')}
                                            </div>
                                        ))}
                                    </td>
                                );
                            })}
                            {actions.map((label) => (
                                <td key={label} />
                            ))}
                            {rowButtons && <td />}
                        </tr>
                    </tfoot>
                )}
            </table>
        </div>
    );
}

function alignClass(align: string | null): string | undefined {
    return align === 'right' ? 'pc-align-right' : align === 'center' ? 'pc-align-center' : undefined;
}

/** The column header's drag grip (six dots). */
function Grip() {
    return (
        <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <circle cx="7" cy="5" r="1.5" />
            <circle cx="13" cy="5" r="1.5" />
            <circle cx="7" cy="10" r="1.5" />
            <circle cx="13" cy="10" r="1.5" />
            <circle cx="7" cy="15" r="1.5" />
            <circle cx="13" cy="15" r="1.5" />
        </svg>
    );
}

/** forge-empty as _table/_cards draw it: a filtered listing offers to clear the filters. */
function EmptyState({ w, filtered, onClear }: { w: ScreenWords; filtered: boolean; onClear: () => void }) {
    return (
        <div className="pc-empty-state">
            <div className="pc-empty-box">
                <Icon name={filtered ? 'funnel' : 'inbox'} size={32} strokeWidth={1.5} />
            </div>
            <div>
                <p className="pc-empty-ttl">{filtered ? w.emptyFilteredTitle : w.emptyTitle}</p>
                <p className="pc-empty-sub">{filtered ? w.emptyFilteredSubtitle : w.emptySubtitle}</p>
            </div>
            {filtered && (
                <button type="button" className="pc-tbtn pc-empty-cta" onClick={onClear}>
                    <Icon name="x" size={14} />
                    {w.clearFilters}
                </button>
            )}
        </div>
    );
}

/** One configured action (_row-action.blade.php): its icon, or its label; a confirmation when configured. */
function ActionButton({ crud, row, action, w }: { crud: CrudController; row: Row; action: RowAction; w: ScreenWords }) {
    const [pending, setPending] = useState(false);
    const content = action.icon ? <i className={action.icon} aria-hidden="true" /> : action.label || (action.type === 'link' ? '→' : '▶');
    const color = action.color && action.color !== 'primary' ? action.color : undefined;

    if (action.type === 'link') {
        return (
            <a
                className="pc-act pc-act--custom"
                data-color={color}
                href={action.value}
                title={action.label}
                aria-label={action.label || undefined}
                onClick={(event) => {
                    event.stopPropagation();
                    if (action.confirm && !window.confirm(action.confirm)) {
                        event.preventDefault();
                    }
                }}
            >
                {content}
            </a>
        );
    }

    return (
        <>
            <button
                type="button"
                className="pc-act pc-act--custom"
                data-color={color}
                title={action.label}
                aria-label={action.label || undefined}
                onClick={(event) => {
                    event.stopPropagation();
                    if (action.confirm) {
                        setPending(true);
                    } else {
                        crud.runAction(row, action);
                    }
                }}
            >
                {content}
            </button>
            {pending && (
                <PtahConfirmDialog
                    text={action.confirm ?? ''}
                    confirm={w.confirm}
                    cancel={w.cancel}
                    onCancel={() => setPending(false)}
                    onConfirm={() => {
                        setPending(false);
                        crud.runAction(row, action);
                    }}
                />
            )}
        </>
    );
}

/**
 * Edit, duplicate, delete / restore as icon buttons — the sticky column of
 * the table (faded until the row is hovered) or the footer of a card, which
 * also carries the configured actions first.
 */
function RowButtons({ crud, state, w, row, card }: PartProps & { row: Row; card?: boolean }) {
    const { permissions } = state.schema!;
    const trashed = state.list.trashed;
    const stop = (event: React.MouseEvent) => event.stopPropagation();

    return (
        <div className={card ? 'pc-card-btns' : 'pc-row-btns'}>
            {card && row.actions.map((action, index) => <ActionButton key={index} crud={crud} row={row} action={action} w={w} />)}
            {permissions.canUpdate && !trashed && (
                <button type="button" className="pc-act pc-act--edit" title={w.edit} aria-label={w.edit} onPointerEnter={() => crud.intendEdit(row.id)} onPointerLeave={() => crud.leaveEdit()} onFocus={() => crud.intendEdit(row.id)} onBlur={() => crud.leaveEdit()} onClick={(event) => (stop(event), void crud.openEdit(row.id))}>
                    <Icon name="edit" />
                </button>
            )}
            {permissions.canCreate && !trashed && (
                <button type="button" className="pc-act pc-act--dup" title={w.duplicate} aria-label={w.duplicate} onClick={(event) => (stop(event), void crud.duplicate(row.id))}>
                    <Icon name="duplicate" />
                </button>
            )}
            {trashed && row.trashed
                ? permissions.canRestore && (
                      <button type="button" className="pc-act pc-act--restore" title={w.restore} aria-label={w.restore} onClick={(event) => (stop(event), void crud.restore(row.id))}>
                          <Icon name="refresh" />
                      </button>
                  )
                : permissions.canDelete && (
                      <button type="button" className="pc-act pc-act--del" title={w.delete} aria-label={w.delete} onClick={(event) => (stop(event), crud.askDelete(row.id))}>
                          <Icon name="trash" />
                      </button>
                  )}
        </div>
    );
}

// ── Pagination (_pagination.blade.php + forge-pagination) ───────────────────

function Pagination({ crud, state, w }: PartProps) {
    const meta = state.meta;
    const [jump, setJump] = useState(meta?.page ?? 1);
    useEffect(() => setJump(meta?.page ?? 1), [meta?.page]);

    if (!meta) {
        return null;
    }

    const first = meta.total === 0 ? 0 : (meta.page - 1) * meta.per_page + 1;
    const last = Math.min(meta.total, meta.page * meta.per_page);
    const go = (page: number) => void crud.setPage(Math.min(Math.max(1, page), meta.last_page));
    const onFirst = meta.page <= 1;
    const onLast = meta.page >= meta.last_page;

    return (
        <div className="pc-pag">
            <span>{fillWords(w.pagination, { first, last, total: meta.total })}</span>
            <div className="pc-pag-end">
                {meta.last_page > 1 && (
                    <nav className="pc-pag-nav" aria-label={w.paginationNav}>
                        <div className="pc-pag-mobile">
                            <button type="button" className="pc-pag-mbtn" disabled={onFirst} onClick={() => go(meta.page - 1)}>
                                {w.previous}
                            </button>
                            <span>
                                {meta.page} / {meta.last_page}
                            </span>
                            <button type="button" className="pc-pag-mbtn" disabled={onLast} onClick={() => go(meta.page + 1)}>
                                {w.next}
                            </button>
                        </div>
                        <div className="pc-pag-pages">
                            <button type="button" className="pc-pag-icon" disabled={onFirst} aria-label={w.previousPage} title={w.previousPage} onClick={() => go(meta.page - 1)}>
                                <Icon name="chevronLeft" />
                            </button>
                            {pageElements(meta.page, meta.last_page).map((page, index) =>
                                page === '…' ? (
                                    <span key={`gap-${index}`} className="pc-pag-gap">
                                        …
                                    </span>
                                ) : (
                                    <button
                                        key={page}
                                        type="button"
                                        className="pc-pag-num"
                                        aria-current={page === meta.page ? 'page' : undefined}
                                        aria-label={page === meta.page ? w.currentPage : undefined}
                                        onClick={() => go(page)}
                                    >
                                        {page}
                                    </button>
                                ),
                            )}
                            <button type="button" className="pc-pag-icon" disabled={onLast} aria-label={w.nextPage} title={w.nextPage} onClick={() => go(meta.page + 1)}>
                                <Icon name="chevronRight" />
                            </button>
                        </div>
                        <p className="pc-pag-sum">{fillWords(w.pageOf, { current: meta.page, last: meta.last_page })}</p>
                    </nav>
                )}
                {meta.last_page > 2 && (
                    <label className="pc-pag-jump">
                        <span>{w.goTo}</span>
                        <input
                            type="number"
                            min={1}
                            max={meta.last_page}
                            value={jump}
                            onChange={(event) => setJump(Number(event.target.value))}
                            onKeyDown={(event) => event.key === 'Enter' && go(jump)}
                            onBlur={() => jump !== meta.page && go(jump)}
                        />
                    </label>
                )}
            </div>
        </div>
    );
}

// ── Bulk bar (the floating bar of base-crud.blade.php) ──────────────────────

function BulkBar({ crud, state, w }: PartProps) {
    const schema = state.schema!;
    const n = state.selection.length;
    const [asking, setAsking] = useState<'delete' | 'force' | null>(null);
    const [pending, setPending] = useState<{ action: string; text: string } | null>(null);
    const run = (action: string, confirm: string | boolean | null) => {
        if (confirm) {
            setPending({ action, text: typeof confirm === 'string' ? confirm : fillWords(w.bulkActionConfirm, { n }) });
        } else {
            void crud.bulk(action);
        }
    };

    return (
        <>
            <div className="pc-bulkbar" role="region" aria-label={fillWords(w.nSelected, { n })}>
                <Icon name="clipboard" />
                <span className="pc-bulkbar-count">{fillWords(w.nSelected, { n })}</span>
                {state.list.trashed ? (
                    <>
                        {schema.permissions.canDelete && (
                            <button type="button" className="pc-bulkbar-btn pc-bulkbar-btn--danger" onClick={() => setAsking('force')}>
                                <Icon name="trash" size={14} />
                                {w.bulkForceDelete}
                            </button>
                        )}
                        {schema.permissions.canRestore && (
                            <button type="button" className="pc-bulkbar-btn" onClick={() => void crud.bulk('restore')}>
                                <Icon name="refresh" size={14} />
                                {w.bulkRestore}
                            </button>
                        )}
                    </>
                ) : (
                    <>
                        {schema.bulkActions.map((action) => (
                            <button key={action.action} type="button" className="pc-bulkbar-btn" onClick={() => run(action.action, action.confirm)}>
                                {action.icon && <i className={action.icon} aria-hidden="true" />}
                                {action.label}
                            </button>
                        ))}
                        {schema.permissions.canDelete && (
                            <button type="button" className="pc-bulkbar-btn pc-bulkbar-btn--danger" onClick={() => setAsking('delete')}>
                                <Icon name="trash" size={14} />
                                {w.bulkDelete}
                            </button>
                        )}
                    </>
                )}
                <button type="button" className="pc-bulkbar-btn" onClick={() => crud.clearSelection()}>
                    {w.bulkCancel}
                </button>
            </div>
            {/* The floating bar never covers the pagination. */}
            <div className="pc-bulkbar-spacer" aria-hidden="true" />
            {asking && (
                <BulkConfirm
                    w={w}
                    text={fillWords(asking === 'force' ? w.bulkForceConfirm : crud.features.undoDelete ? w.bulkDeleteConfirmUndoable : w.bulkDeleteConfirm, { n })}
                    note={asking === 'force' ? w.bulkIrreversible : null}
                    onCancel={() => setAsking(null)}
                    onConfirm={() => {
                        setAsking(null);
                        void crud.bulk(asking === 'force' ? 'force-delete' : 'delete');
                    }}
                />
            )}
            {pending && (
                <PtahConfirmDialog
                    text={pending.text}
                    confirm={w.confirm}
                    cancel={w.cancel}
                    onCancel={() => setPending(null)}
                    onConfirm={() => {
                        void crud.bulk(pending.action);
                        setPending(null);
                    }}
                />
            )}
        </>
    );
}

function useEscape(onEscape: () => void) {
    useEffect(() => {
        const listen = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onEscape();
            }
        };
        document.addEventListener('keydown', listen);

        return () => document.removeEventListener('keydown', listen);
    }, [onEscape]);
}

/** The bulk delete confirmation (replaces the native confirm()). */
function BulkConfirm({ w, text, note, onConfirm, onCancel }: { w: ScreenWords; text: string; note: string | null; onConfirm: () => void; onCancel: () => void }) {
    useEscape(onCancel);

    return (
        <div className="pc-dialog-back" onMouseDown={(event) => event.target === event.currentTarget && onCancel()}>
            <div className="pc-dialog" role="alertdialog" aria-modal="true" aria-label={text}>
                <div className="pc-dialog-row">
                    <div className="pc-dialog-icon">
                        <Icon name="warning" size={20} />
                    </div>
                    <div>
                        <p className="pc-dialog-ttl">{text}</p>
                        {note && <p className="pc-dialog-sub pc-dialog-sub--danger">{note}</p>}
                    </div>
                </div>
                <div className="pc-dialog-foot">
                    <button type="button" className="pc-dbtn" onClick={onCancel}>
                        {w.cancel}
                    </button>
                    <button type="button" className="pc-dbtn pc-dbtn--danger" autoFocus onClick={onConfirm}>
                        {w.deleteButton}
                    </button>
                </div>
            </div>
        </div>
    );
}

/** The `?` list of the keyboard shortcuts. */
function Shortcuts({ w, canCreate, onClose }: { w: ScreenWords; canCreate: boolean; onClose: () => void }) {
    useEscape(onClose);
    const keys: [string, string][] = [
        [w.shortcutsSearch, '/'],
        ...(canCreate ? ([[w.shortcutsNew, 'n']] as [string, string][]) : []),
        [w.shortcutsFilters, 'f'],
        [w.shortcutsView, 'v'],
        [w.shortcutsRefresh, 'r'],
        [w.shortcutsSidebar, 'Ctrl+B'],
        [w.shortcutsHelp, '?'],
    ];

    return (
        <div className="pc-dialog-back" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <div className="pc-dialog pc-dialog--sm" role="dialog" aria-modal="true" aria-labelledby="pc-shortcuts-title">
                <div className="pc-dialog-head">
                    <h3 id="pc-shortcuts-title">{w.shortcutsTitle}</h3>
                    <button type="button" className="pc-x" aria-label={w.close} title={w.close} autoFocus onClick={onClose}>
                        <Icon name="x" size={20} />
                    </button>
                </div>
                <dl className="pc-keys">
                    {keys.map(([label, key]) => (
                        <div key={key}>
                            <dt>{label}</dt>
                            <dd>
                                <kbd className="pc-kbd">{key}</kbd>
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </div>
    );
}

// ── Toasts (forge-toast-host.blade.php) ─────────────────────────────────────

const TOAST_ICONS: Record<string, string> = { success: 'checkCircle', warn: 'alertTriangle', danger: 'xCircle' };

function Toasts({ crud, state, w, locale }: Omit<PartProps, 't'>) {
    return (
        <div className="pc-toasts pc-tst-host" aria-live="polite">
            {state.toasts.map((toast) => (
                <div
                    key={toast.id}
                    className="pc-tst"
                    data-color={toast.level}
                    role="status"
                    // Hovering or focusing a toast pauses its clock (1.61.0).
                    onMouseEnter={() => crud.pauseToast(toast.id)}
                    onMouseLeave={() => crud.resumeToast(toast.id)}
                    onFocus={() => crud.pauseToast(toast.id)}
                    onBlur={() => crud.resumeToast(toast.id)}
                >
                    {TOAST_ICONS[toast.level] && <Icon name={TOAST_ICONS[toast.level]} />}
                    <span className="pc-toast-text">{toast.text}</span>
                    {toast.undo !== undefined && toast.undo !== null && (
                        <button type="button" className="pc-tst-undo" onClick={() => void crud.undo(toast.id)}>
                            {words(locale).undo}
                        </button>
                    )}
                    <button type="button" className="pc-tst-x" aria-label={w.close} title={w.close} onClick={() => crud.dismiss(toast.id)}>
                        <Icon name="x" size={14} strokeWidth={2.5} />
                    </button>
                </div>
            ))}
        </div>
    );
}
