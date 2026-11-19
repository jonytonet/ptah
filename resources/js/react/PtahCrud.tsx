import { Fragment, useEffect, useMemo, useRef, useState, useSyncExternalStore } from 'react';
import type { ReactNode } from 'react';
import { CrudController, debounce, pagesAround, words } from '../core';
import type { ControllerOptions, CrudState, Row, RowAction, Words } from '../core';
import { followRowLink, nextViewMode } from '../core/crud/boards';
import { fullOrder, moveColumn, saveColumnOrder } from '../core/crud/extras';
import { clearAllFilters, filterByCell, filterSession, isCellFilterable } from '../core/crud/filters';
import { focusFirstField, formSections, formSubtitle, formTitle, lockBodyScroll, needsDiscardConfirm, submitLabel } from '../core/crud/form';
import { PtahFormField, PtahFormSection } from './crud/form';
import { PtahCrudConfigButton } from './admin/crud-config';
import { PtahCrudCalendar, PtahCrudCards, PtahCrudKanban, PtahCrudViewSwitch, useResolvedView } from './crud/boards';
import { PtahCrudColumnResizer, PtahCrudColumnsMenu, PtahCrudDetailRow, PtahCrudDetailToggle, PtahCrudFormChanges, PtahCrudFormDraft, PtahCrudSaveAndNew } from './crud/extras';
import { PtahCrudFilterBadges, PtahCrudFilterPanel, PtahCrudSearchHistory, PtahCrudUrlFilters } from './crud/filters';
import { PtahCrudImport, PtahCrudImportButton } from './crud/import';
import { PtahCrudAttachments, PtahCrudHistory, PtahCrudRecordButtons } from './crud/records';

export interface PtahCrudProps extends ControllerOptions {
    /** The key the screen was registered with: `CrudScreen::register('products', …)`. */
    screen: string;
    /** `pt_BR`, `en`… Defaults to the page's `<html lang>`. */
    locale?: string;
    /** Replaces the screen's title. */
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

/**
 * A complete CRUD screen over the ptah headless API: listing, search,
 * filters, sort, pages, form, delete/restore, bulk and row actions, export.
 */
export function PtahCrud({ screen, locale, title, toolbar, controller, ...options }: PtahCrudProps) {
    // One controller per screen key. The options are read once, when it is made.
    const crud = useMemo(() => controller ?? new CrudController(screen, options), [controller, screen]); // eslint-disable-line react-hooks/exhaustive-deps
    const state = usePtahCrud(crud);
    const t = useMemo(() => words(locale), [locale]);
    const view = useResolvedView(crud);

    useEffect(() => {
        void crud.init();

        return () => {
            if (!controller) {
                crud.destroy();
            }
        };
    }, [crud, controller]);

    if (state.status !== 'ready' || !state.schema) {
        const text = { loading: t.loading, forbidden: t.forbidden, 'not-found': t.notFound, error: t.error, ready: t.loading }[state.status];

        return (
            <div className="pc-root">
                <div className="pc-state" role={state.status === 'loading' ? 'status' : 'alert'}>
                    {text}
                </div>
            </div>
        );
    }

    return (
        <div className="pc-root" data-density={state.preferences?.view_density ?? 'global'}>
            {/* A detail grid (a child screen) does not read the page's address. */}
            <PtahCrudUrlFilters crud={crud} locale={locale} readLocation={!crud.screen.includes('..')} />
            <Toolbar crud={crud} state={state} t={t} title={title} extra={toolbar} locale={locale} />
            <PtahCrudFilterBadges crud={crud} locale={locale} />
            {state.selection.length > 0 && <BulkBar crud={crud} state={state} t={t} />}
            {view === 'kanban' ? (
                <PtahCrudKanban crud={crud} locale={locale} />
            ) : view === 'calendar' ? (
                <PtahCrudCalendar crud={crud} locale={locale} />
            ) : view === 'cards' ? (
                <PtahCrudCards crud={crud} locale={locale} renderActions={(row) => <RowButtons crud={crud} state={state} t={t} row={row} locale={locale} />} />
            ) : (
                <Table crud={crud} state={state} t={t} locale={locale} options={options} />
            )}
            {/* A board shows every record of the filter: no pages. */}
            {view !== 'kanban' && view !== 'calendar' && <Footer crud={crud} state={state} t={t} />}
            {(state.form || crud.formOpening) && <FormModal crud={crud} state={state} t={t} locale={locale} />}
            {/* After the form in the DOM: they open over it. */}
            <PtahCrudHistory crud={crud} locale={locale} />
            <PtahCrudAttachments crud={crud} locale={locale} />
            <PtahCrudImport crud={crud} locale={locale} />
            {state.deleting !== null && (
                <Confirm text={t.confirmDelete} confirm={t.delete} cancel={t.cancel} danger onConfirm={() => void crud.confirmDelete()} onCancel={() => crud.cancelDelete()} />
            )}
            <Toasts crud={crud} state={state} t={t} />
        </div>
    );
}

interface PartProps {
    crud: CrudController;
    state: CrudState;
    t: Words;
    locale?: string;
}

/** Typing in a field, or a dialog is open: a single-key shortcut must not fire. */
function typing(event: KeyboardEvent): boolean {
    const target = event.target as HTMLElement | null;

    return !!target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
}

// ── Toolbar ─────────────────────────────────────────────────────────────────

function Toolbar({ crud, state, t, title, extra, locale }: PartProps & { title?: ReactNode; extra?: ReactNode }) {
    const schema = state.schema!;
    const [search, setSearch] = useState(state.list.search);
    const [showFilters, setShowFilters] = useState(false);
    const apply = useMemo(() => debounce((value: string) => void crud.setSearch(value), 350), [crud]);

    // The search box follows the state when it changes from outside (restore, clear).
    useEffect(() => setSearch(state.list.search), [state.list.search]);
    useEffect(() => () => apply.cancel(), [apply]);

    // `v` walks the views (auto → table → cards), as on the Livewire screen.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const now = crud.getState();
            if (event.key !== 'v' || crud.screen.includes('..') || event.ctrlKey || event.metaKey || event.altKey || typing(event) || now.form || now.panel || now.deleting !== null) {
                return;
            }
            void crud.savePreferences({ view_mode: nextViewMode(now.preferences?.view_mode) });
        };
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [crud]);

    return (
        <>
            <div className="pc-toolbar">
                <h2 className="pc-title">{title ?? schema.title}</h2>
                <input
                    type="search"
                    className="pc-input pc-search"
                    placeholder={t.search}
                    aria-label={t.search}
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        apply(event.target.value);
                    }}
                />
                <PtahCrudSearchHistory crud={crud} locale={locale} />
                {/* Always: quick dates and the advanced search need no filterable column. */}
                <button type="button" className={`pc-btn${showFilters || crud.hasFilters ? ' pc-btn--active' : ''}`} aria-expanded={showFilters} onClick={() => setShowFilters(!showFilters)}>
                    {t.filters}
                </button>
                {crud.hasFilters && (
                    <button type="button" className="pc-btn" onClick={() => void clearAllFilters(crud, filterSession(crud))}>
                        {t.clear}
                    </button>
                )}
                <span className="pc-spacer" />
                {schema.trash && (
                    <button type="button" className={`pc-btn${state.list.trashed ? ' pc-btn--active' : ''}`} aria-pressed={state.list.trashed} onClick={() => void crud.toggleTrashed()}>
                        {state.list.trashed ? t.backFromTrash : t.trash}
                    </button>
                )}
                <PtahCrudViewSwitch crud={crud} locale={locale} />
                <PtahCrudColumnsMenu crud={crud} locale={locale} />
                <PtahCrudConfigButton crud={crud} locale={locale} />
                {schema.export.enabled && (
                    <Menu label={t.export}>
                        {[...schema.export.formats, 'print'].map((format) => (
                            <li key={format}>
                                <button type="button" className="pc-menu-item" onClick={() => void crud.export(format)}>
                                    {(t as Record<string, string>)[format] ?? format}
                                </button>
                            </li>
                        ))}
                    </Menu>
                )}
                <PtahCrudImportButton crud={crud} locale={locale} />
                {extra}
                {schema.permissions.canCreate && !state.list.trashed && (
                    <button type="button" className="pc-btn pc-btn--primary" onClick={() => void crud.openCreate()}>
                        + {t.new}
                    </button>
                )}
            </div>
            {showFilters && <PtahCrudFilterPanel crud={crud} locale={locale} onClose={() => setShowFilters(false)} />}
        </>
    );
}

function Menu({ label, children }: { label: string; children: ReactNode }) {
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

    return (
        <div className="pc-menu" ref={ref}>
            <button type="button" className="pc-btn" aria-haspopup="true" aria-expanded={open} onClick={() => setOpen(!open)}>
                {label} ▾
            </button>
            {open && <ul className="pc-menu-list">{children}</ul>}
        </div>
    );
}

// ── Bulk bar ────────────────────────────────────────────────────────────────

function BulkBar({ crud, state, t }: PartProps) {
    const schema = state.schema!;
    const [pending, setPending] = useState<{ action: string; text: string } | null>(null);
    const run = (action: string, confirm: string | boolean | null) => {
        if (confirm) {
            setPending({ action, text: typeof confirm === 'string' ? confirm : t.confirmBulk });
        } else {
            void crud.bulk(action);
        }
    };

    return (
        <div className="pc-bulk" role="region" aria-label={t.selected}>
            <strong>
                {state.selection.length} {t.selected}
            </strong>
            {schema.bulkActions.map((action) => (
                <button key={action.action} type="button" className="pc-btn" onClick={() => run(action.action, action.confirm)}>
                    {action.icon && <i className={action.icon} aria-hidden="true" />}
                    {action.label}
                </button>
            ))}
            {state.list.trashed
                ? schema.permissions.canRestore && (
                      <button type="button" className="pc-btn" onClick={() => void crud.bulk('restore')}>
                          {t.restoreSelected}
                      </button>
                  )
                : schema.permissions.canDelete && (
                      <button type="button" className="pc-btn pc-btn--danger" onClick={() => run('delete', t.confirmBulk)}>
                          {t.deleteSelected}
                      </button>
                  )}
            <button type="button" className="pc-btn" onClick={() => crud.clearSelection()}>
                {t.clear}
            </button>
            {pending && (
                <Confirm
                    text={pending.text}
                    confirm={t.confirm}
                    cancel={t.cancel}
                    onCancel={() => setPending(null)}
                    onConfirm={() => {
                        void crud.bulk(pending.action);
                        setPending(null);
                    }}
                />
            )}
        </div>
    );
}

// ── Table ───────────────────────────────────────────────────────────────────

function alignClass(align: string | null): string | undefined {
    return align === 'right' ? 'pc-align-right' : align === 'center' ? 'pc-align-center' : undefined;
}

function Table({ crud, state, t, locale, options }: PartProps & { options: ControllerOptions }) {
    const schema = state.schema!;
    const { permissions, features } = schema;
    const hasRowButtons =
        permissions.canUpdate || permissions.canDelete || permissions.canCreate || features.history || features.attachments || state.rows.some((row) => row.actions.length > 0);
    const allSelected = state.rows.length > 0 && state.rows.every((row) => state.selection.includes(String(row.id)));
    const detail = features.masterDetail;
    const span = schema.columns.length + (schema.selectable ? 1 : 0) + (hasRowButtons ? 1 : 0) + (detail ? 1 : 0);
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
    const totals = new Map(state.totals.map((total) => [total.field, total]));

    return (
        <div className="pc-table-wrap" aria-busy={state.busy}>
            <table className="pc-table">
                <thead>
                    <tr>
                        {detail && <th className="pc-col-check" />}
                        {schema.selectable && (
                            <th className="pc-col-check">
                                <input type="checkbox" aria-label={t.all} checked={allSelected} onChange={() => crud.toggleAll()} />
                            </th>
                        )}
                        {schema.columns.map((column) => {
                            const sorted = column.sortBy !== null && state.list.sort === column.sortBy;
                            const ascending = state.list.direction.toUpperCase() === 'ASC';

                            return (
                                <th
                                    key={column.field}
                                    className={alignClass(column.align)}
                                    aria-sort={sorted ? (ascending ? 'ascending' : 'descending') : undefined}
                                    style={{ position: 'relative', ...widthOf(column.field) }}
                                    draggable
                                    onDragStart={() => setDragged(column.field)}
                                    onDragOver={(event) => dragged && event.preventDefault()}
                                    onDrop={() => drop(column.field)}
                                    onDragEnd={() => setDragged(null)}
                                >
                                    {column.sortBy !== null ? (
                                        <button type="button" className="pc-th-btn" onClick={() => void crud.sortBy(column.sortBy!)}>
                                            {column.label}
                                            <span className={`pc-sort${sorted ? ' pc-sort--on' : ''}`} aria-hidden="true">
                                                {sorted && !ascending ? '▼' : '▲'}
                                            </span>
                                        </button>
                                    ) : (
                                        column.label
                                    )}
                                    <PtahCrudColumnResizer crud={crud} field={column.field} label={column.label} locale={locale} />
                                </th>
                            );
                        })}
                        {hasRowButtons && <th className="pc-col-actions">{t.actions}</th>}
                    </tr>
                </thead>
                <tbody>
                    {state.rows.length === 0 && (
                        <tr>
                            <td className="pc-empty" colSpan={span}>
                                {t.empty}
                            </td>
                        </tr>
                    )}
                    {state.rows.map((row) => (
                        <Fragment key={row.id}>
                        <tr
                            style={row.link ? { cursor: 'pointer', ...styleOf(row.style) } : styleOf(row.style)}
                            // configLinkLinha: the row opens its record (Enter when focused).
                            tabIndex={row.link ? 0 : undefined}
                            data-link={row.link ?? undefined}
                            onClick={row.link ? (event) => followRowLink(event, row.link) : undefined}
                            onAuxClick={row.link ? (event) => followRowLink(event, row.link) : undefined}
                            onKeyDown={row.link ? (event) => followRowLink(event, row.link) : undefined}
                        >
                            {detail && (
                                <td className="pc-col-check">
                                    <PtahCrudDetailToggle crud={crud} row={row} locale={locale} />
                                </td>
                            )}
                            {schema.selectable && (
                                <td className="pc-col-check">
                                    <input type="checkbox" aria-label={String(row.id)} checked={state.selection.includes(String(row.id))} onChange={() => crud.toggleRow(row.id)} />
                                </td>
                            )}
                            {schema.columns.map((column) => (
                                // The HTML comes rendered and escaped by the server — the same
                                // string the Livewire screen prints for this cell.
                                <td
                                    key={column.field}
                                    className={alignClass(column.align)}
                                    style={widthOf(column.field)}
                                    // Alt+click filters the listing by this cell's value.
                                    title={isCellFilterable(crud, column.field) ? t.filterByCell : undefined}
                                    onClick={(event) => event.altKey && isCellFilterable(crud, column.field) && void filterByCell(crud, row.id, column.field)}
                                    dangerouslySetInnerHTML={{ __html: row.cells[column.field]?.html ?? '' }}
                                />
                            ))}
                            {hasRowButtons && (
                                <td className="pc-col-actions">
                                    <RowButtons crud={crud} state={state} t={t} row={row} locale={locale} />
                                </td>
                            )}
                        </tr>
                        {detail && (
                            <PtahCrudDetailRow
                                crud={crud}
                                row={row}
                                colSpan={span}
                                locale={locale}
                                // The child screen, bound by the server to this row: its own
                                // controller, nothing remembered (the Livewire nested grid
                                // starts clean on every expansion too).
                                renderDetail={(child) => <PtahCrud {...options} screen={child.screen} title={child.title || undefined} locale={locale} api={crud.api} remember={false} />}
                            />
                        )}
                        </Fragment>
                    ))}
                </tbody>
                {state.totals.length > 0 && (
                    <tfoot>
                        <tr>
                            {detail && <td />}
                            {schema.selectable && <td />}
                            {schema.columns.map((column, index) => (
                                <td key={column.field} className={alignClass(column.align)}>
                                    {totals.has(column.field) ? String(totals.get(column.field)!.value ?? '') : index === 0 ? t.total : ''}
                                </td>
                            ))}
                            {hasRowButtons && <td />}
                        </tr>
                    </tfoot>
                )}
            </table>
        </div>
    );
}

/** `background:#fee;color:red` → a React style object. */
function styleOf(style: string | null): Record<string, string> | undefined {
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

function RowButtons({ crud, state, t, row, locale }: PartProps & { row: Row }) {
    const { permissions } = state.schema!;
    const [pending, setPending] = useState<RowAction | null>(null);

    return (
        <>
            <PtahCrudRecordButtons crud={crud} row={row} locale={locale} />
            {row.actions.map((action, index) => {
                const content = action.icon ? <i className={action.icon} aria-hidden="true" /> : action.label || '→';

                if (action.type === 'link') {
                    return (
                        <a
                            key={index}
                            className="pc-icon-btn"
                            href={action.value}
                            title={action.label}
                            aria-label={action.label}
                            onClick={(event) => {
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
                    <button key={index} type="button" className="pc-icon-btn" title={action.label} aria-label={action.label} onClick={() => (action.confirm ? setPending(action) : crud.runAction(row, action))}>
                        {content}
                    </button>
                );
            })}
            {row.trashed
                ? permissions.canRestore && (
                      <button type="button" className="pc-icon-btn" onClick={() => void crud.restore(row.id)}>
                          {t.restore}
                      </button>
                  )
                : (
                      <>
                          {permissions.canUpdate && (
                              <button type="button" className="pc-icon-btn" onClick={() => void crud.openEdit(row.id)}>
                                  {t.edit}
                              </button>
                          )}
                          {permissions.canCreate && (
                              <button type="button" className="pc-icon-btn" onClick={() => void crud.duplicate(row.id)}>
                                  {t.duplicate}
                              </button>
                          )}
                          {permissions.canDelete && (
                              <button type="button" className="pc-icon-btn pc-icon-btn--danger" onClick={() => crud.askDelete(row.id)}>
                                  {t.delete}
                              </button>
                          )}
                      </>
                  )}
            {pending && (
                <Confirm
                    text={pending.confirm ?? ''}
                    confirm={t.confirm}
                    cancel={t.cancel}
                    onCancel={() => setPending(null)}
                    onConfirm={() => {
                        crud.runAction(row, pending);
                        setPending(null);
                    }}
                />
            )}
        </>
    );
}

// ── Footer ──────────────────────────────────────────────────────────────────

function Footer({ crud, state, t }: PartProps) {
    const meta = state.meta;
    if (!meta) {
        return null;
    }

    const first = meta.total === 0 ? 0 : (meta.page - 1) * meta.per_page + 1;
    const last = Math.min(meta.total, meta.page * meta.per_page);
    const sizes = [...new Set([10, 25, 50, 100, meta.per_page])].sort((a, b) => a - b);

    return (
        <div className="pc-footer">
            <span>
                {first}–{last} {t.of} {meta.total}
            </span>
            <select className="pc-input" aria-label={t.perPage} value={meta.per_page} onChange={(event) => void crud.setPerPage(Number(event.target.value))}>
                {sizes.map((size) => (
                    <option key={size} value={size}>
                        {size} {t.perPage}
                    </option>
                ))}
            </select>
            <nav className="pc-pager" aria-label="pagination">
                <button type="button" className="pc-btn" disabled={meta.page <= 1} onClick={() => void crud.setPage(meta.page - 1)}>
                    {t.previous}
                </button>
                {pagesAround(meta.page, meta.last_page).map((page) => (
                    <button key={page} type="button" className={`pc-btn${page === meta.page ? ' pc-btn--active' : ''}`} aria-current={page === meta.page ? 'page' : undefined} onClick={() => void crud.setPage(page)}>
                        {page}
                    </button>
                ))}
                <button type="button" className="pc-btn" disabled={meta.page >= meta.last_page} onClick={() => void crud.setPage(meta.page + 1)}>
                    {t.next}
                </button>
            </nav>
        </div>
    );
}

// ── Form ────────────────────────────────────────────────────────────────────

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

/**
 * The create/edit modal, as the Livewire one: title by gender and subtitle,
 * sections, focus on the first field, the page locked behind it, "discard
 * changes?" before closing a changed form (Cancel, ✕, Escape, a click
 * outside), and a loading state while the form is on its way — mounted with
 * `state.form || crud.formOpening`.
 */
export function FormModal({ crud, state, t, locale }: PartProps) {
    const schema = state.schema!;
    const form = state.form;
    const titleId = `pc-form-title-${crud.screen}`;
    const [dirty, setDirty] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const body = useRef<HTMLDivElement>(null);
    const instance = form?.instance ?? 0;

    // A new opening (also "save & new", a draft reverted) starts clean and focused.
    useEffect(() => {
        setDirty(false);
        setConfirming(false);
        if (form) {
            focusFirstField(body.current);
        }
    }, [instance, form === null]); // eslint-disable-line react-hooks/exhaustive-deps
    useEffect(() => lockBodyScroll(), []);

    const tryClose = () => {
        if (needsDiscardConfirm(crud, dirty)) {
            setConfirming(true);
        } else {
            crud.closeForm();
        }
    };
    // The confirmation takes the Escape first; a panel over the form (history, attachments) too.
    useEscape(() => {
        if (confirming) {
            setConfirming(false);
        } else if (!crud.getState().panel) {
            tryClose();
        }
    });

    const title = form ? formTitle(form, schema, t) : `${crud.formOpening === 'edit' ? t.editPrefix : t.newPrefix} ${schema.titleSingular}`;

    return (
        <>
            <div className="pc-overlay" onMouseDown={(event) => event.target === event.currentTarget && tryClose()}>
                <div className="pc-modal" role="dialog" aria-modal="true" aria-labelledby={titleId} aria-busy={form ? undefined : true}>
                    <form
                        noValidate
                        onSubmit={(event) => {
                            event.preventDefault();
                            void crud.save();
                        }}
                    >
                        <div className="pc-modal-head">
                            <div className="pc-modal-heading">
                                <h3 className="pc-modal-title" id={titleId}>
                                    {title}
                                </h3>
                                {form && <p className="pc-modal-subtitle">{formSubtitle(form, t)}</p>}
                            </div>
                            <button type="button" className="pc-icon-btn" aria-label={t.close} onClick={tryClose}>
                                ✕
                            </button>
                        </div>
                        <div className="pc-modal-body" ref={body}>
                            {!form ? (
                                <div className="pc-form-loading" role="status">
                                    <span className="pc-form-spinner" aria-hidden="true" />
                                    {t.formLoading}
                                </div>
                            ) : (
                                <>
                                    {form.errors._general && (
                                        <p className="pc-alert" role="alert">
                                            {form.errors._general}
                                        </p>
                                    )}
                                    <PtahCrudFormDraft crud={crud} locale={locale} />
                                    <PtahCrudFormChanges crud={crud} />
                                    {/* Typing anywhere in the fields makes the form dirty, as the Blade's @input/@change. */}
                                    <div className="pc-form" onInput={() => setDirty(true)} onChange={() => setDirty(true)}>
                                        {formSections(schema.form).map(({ field, heading }) => (
                                            <Fragment key={field.field}>
                                                {heading !== null && <PtahFormSection title={heading} />}
                                                <PtahFormField field={field} crud={crud} form={form} t={t} onTouch={() => setDirty(true)} />
                                            </Fragment>
                                        ))}
                                    </div>
                                </>
                            )}
                        </div>
                        <div className="pc-modal-foot">
                            {/* History and attachments of the record being edited, as in the Livewire modal. */}
                            {form && form.mode === 'edit' && form.id !== null && <PtahCrudRecordButtons crud={crud} row={{ id: form.id }} locale={locale} />}
                            <span className="pc-spacer" />
                            <button type="button" className="pc-btn" disabled={form?.saving} onClick={tryClose}>
                                {t.cancel}
                            </button>
                            {form && <PtahCrudSaveAndNew crud={crud} locale={locale} />}
                            {form && (
                                <button type="submit" className="pc-btn pc-btn--primary" disabled={form.saving} aria-busy={form.saving || undefined}>
                                    {form.saving ? t.saving : submitLabel(form, t)}
                                </button>
                            )}
                        </div>
                    </form>
                </div>
            </div>
            {confirming && (
                <div className="pc-overlay pc-overlay--top" onMouseDown={(event) => event.target === event.currentTarget && setConfirming(false)}>
                    <div className="pc-modal pc-modal--sm" role="alertdialog" aria-modal="true" aria-labelledby={`${titleId}-discard`} aria-describedby={`${titleId}-discard-text`}>
                        <div className="pc-modal-body">
                            <p className="pc-modal-title" id={`${titleId}-discard`}>
                                {t.unsavedTitle}
                            </p>
                            <p className="pc-modal-subtitle" id={`${titleId}-discard-text`}>
                                {t.unsavedConfirm}
                            </p>
                        </div>
                        <div className="pc-modal-foot">
                            <button type="button" className="pc-btn" autoFocus onClick={() => setConfirming(false)}>
                                {t.unsavedKeep}
                            </button>
                            <button type="button" className="pc-btn pc-btn--danger" onClick={() => crud.closeForm()}>
                                {t.unsavedDiscard}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

// ── Confirm and toasts ──────────────────────────────────────────────────────

interface ConfirmProps {
    text: string;
    confirm: string;
    cancel: string;
    danger?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

function Confirm({ text, confirm, cancel, danger, onConfirm, onCancel }: ConfirmProps) {
    useEscape(onCancel);

    return (
        <div className="pc-overlay" onMouseDown={(event) => event.target === event.currentTarget && onCancel()}>
            <div className="pc-modal pc-modal--sm" role="alertdialog" aria-modal="true" aria-label={text}>
                <div className="pc-modal-body">{text}</div>
                <div className="pc-modal-foot">
                    <button type="button" className="pc-btn" onClick={onCancel}>
                        {cancel}
                    </button>
                    <button type="button" className={`pc-btn ${danger ? 'pc-btn--danger' : 'pc-btn--primary'}`} autoFocus onClick={onConfirm}>
                        {confirm}
                    </button>
                </div>
            </div>
        </div>
    );
}

function Toasts({ crud, state, t }: PartProps) {
    return (
        <div className="pc-toasts" aria-live="polite">
            {state.toasts.map((toast) => (
                <div key={toast.id} className={`pc-toast pc-toast--${toast.level}`} role="status">
                    <span className="pc-toast-text">{toast.text}</span>
                    <button type="button" className="pc-icon-btn" aria-label={t.close} onClick={() => crud.dismiss(toast.id)}>
                        ✕
                    </button>
                </div>
            ))}
        </div>
    );
}
