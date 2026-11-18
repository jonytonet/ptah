import { useEffect, useMemo, useRef, useState, useSyncExternalStore } from 'react';
import type { ReactNode } from 'react';
import {
    controlOf,
    CrudController,
    debounce,
    fromInput,
    inputType,
    isWide,
    pagesAround,
    toInput,
    words,
} from '../core';
import type { ControllerOptions, CrudState, Field, FilterDef, LookupItem, Row, RowAction, Words } from '../core';

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
            <Toolbar crud={crud} state={state} t={t} title={title} extra={toolbar} />
            {state.selection.length > 0 && <BulkBar crud={crud} state={state} t={t} />}
            <Table crud={crud} state={state} t={t} />
            <Footer crud={crud} state={state} t={t} />
            {state.form && <FormModal crud={crud} state={state} t={t} />}
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
}

// ── Toolbar ─────────────────────────────────────────────────────────────────

function Toolbar({ crud, state, t, title, extra }: PartProps & { title?: ReactNode; extra?: ReactNode }) {
    const schema = state.schema!;
    const [search, setSearch] = useState(state.list.search);
    const [showFilters, setShowFilters] = useState(false);
    const apply = useMemo(() => debounce((value: string) => void crud.setSearch(value), 350), [crud]);

    // The search box follows the state when it changes from outside (restore, clear).
    useEffect(() => setSearch(state.list.search), [state.list.search]);
    useEffect(() => () => apply.cancel(), [apply]);

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
                {schema.filters.length > 0 && (
                    <button type="button" className={`pc-btn${showFilters || crud.hasFilters ? ' pc-btn--active' : ''}`} aria-expanded={showFilters} onClick={() => setShowFilters(!showFilters)}>
                        {t.filters}
                    </button>
                )}
                {crud.hasFilters && (
                    <button type="button" className="pc-btn" onClick={() => void crud.clearFilters()}>
                        {t.clear}
                    </button>
                )}
                <span className="pc-spacer" />
                {schema.trash && (
                    <button type="button" className={`pc-btn${state.list.trashed ? ' pc-btn--active' : ''}`} aria-pressed={state.list.trashed} onClick={() => void crud.toggleTrashed()}>
                        {state.list.trashed ? t.backFromTrash : t.trash}
                    </button>
                )}
                <Menu label={t.columns}>
                    {Object.entries(state.preferences?.columns ?? {}).map(([field, visible]) => (
                        <li key={field}>
                            <label className="pc-menu-item">
                                <input type="checkbox" checked={visible} onChange={(event) => void crud.setColumnVisible(field, event.target.checked)} />
                                {schema.columns.find((column) => column.field === field)?.label ?? schema.form.find((item) => item.field === field)?.label ?? field}
                            </label>
                        </li>
                    ))}
                </Menu>
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
                {extra}
                {schema.permissions.canCreate && !state.list.trashed && (
                    <button type="button" className="pc-btn pc-btn--primary" onClick={() => void crud.openCreate()}>
                        + {t.new}
                    </button>
                )}
            </div>
            {showFilters && schema.filters.length > 0 && <Filters crud={crud} state={state} t={t} />}
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

// ── Filters ─────────────────────────────────────────────────────────────────

function Filters({ crud, state, t }: PartProps) {
    return (
        <div className="pc-filters">
            {state.schema!.filters.map((filter) => (
                <FilterControl key={filter.field} filter={filter} crud={crud} state={state} t={t} />
            ))}
            <div className="pc-filters-actions">
                <button type="button" className="pc-btn" onClick={() => void crud.clearFilters()}>
                    {t.clear}
                </button>
            </div>
        </div>
    );
}

function FilterControl({ filter, crud, state, t }: PartProps & { filter: FilterDef }) {
    const id = `pc-f-${crud.screen}-${filter.field}`;
    const value = state.list.filters[filter.field];
    const [text, setText] = useState(value === undefined ? '' : String(value));
    const apply = useMemo(() => debounce((next: string) => void crud.setFilter(filter.field, next), 400), [crud, filter.field]);

    useEffect(() => setText(value === undefined ? '' : String(value)), [value]);
    useEffect(() => () => apply.cancel(), [apply]);

    if (filter.type === 'date' || filter.type === 'datetime') {
        return (
            <div>
                <span className="pc-label">{filter.label}</span>
                <div className="pc-range">
                    {(['start', 'end'] as const).map((edge) => (
                        <input
                            key={edge}
                            type="date"
                            className="pc-input"
                            aria-label={`${filter.label} — ${edge === 'start' ? t.from : t.to}`}
                            value={state.list.date_ranges[`${filter.field}_${edge}`] ?? ''}
                            onChange={(event) => void crud.setDateRange(filter.field, edge, event.target.value)}
                        />
                    ))}
                </div>
            </div>
        );
    }

    if (filter.type === 'searchdropdown') {
        return (
            <div>
                <label className="pc-label" htmlFor={id}>
                    {filter.label}
                </label>
                <Lookup id={id} value={value === undefined ? '' : String(value)} label="" t={t} search={(query) => crud.lookup(filter.field, query, true)} onPick={(item) => void crud.setFilter(filter.field, item?.value ?? '')} />
            </div>
        );
    }

    const options = filter.options ?? (filter.type === 'boolean' || filter.type === 'switch' ? [{ label: t.yes, value: '1' }, { label: t.no, value: '0' }] : null);

    return (
        <div>
            <label className="pc-label" htmlFor={id}>
                {filter.label}
            </label>
            {options ? (
                <select id={id} className="pc-input" value={value === undefined ? '' : String(value)} onChange={(event) => void crud.setFilter(filter.field, event.target.value)}>
                    <option value="">{t.all}</option>
                    {options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            ) : (
                <input
                    id={id}
                    type={filter.type === 'number' ? 'number' : 'text'}
                    className="pc-input"
                    value={text}
                    onChange={(event) => {
                        setText(event.target.value);
                        apply(event.target.value);
                    }}
                />
            )}
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

function Table({ crud, state, t }: PartProps) {
    const schema = state.schema!;
    const { permissions } = schema;
    const hasRowButtons = permissions.canUpdate || permissions.canDelete || permissions.canCreate || state.rows.some((row) => row.actions.length > 0);
    const allSelected = state.rows.length > 0 && state.rows.every((row) => state.selection.includes(String(row.id)));
    const span = schema.columns.length + (schema.selectable ? 1 : 0) + (hasRowButtons ? 1 : 0);
    const totals = new Map(state.totals.map((total) => [total.field, total]));

    return (
        <div className="pc-table-wrap" aria-busy={state.busy}>
            <table className="pc-table">
                <thead>
                    <tr>
                        {schema.selectable && (
                            <th className="pc-col-check">
                                <input type="checkbox" aria-label={t.all} checked={allSelected} onChange={() => crud.toggleAll()} />
                            </th>
                        )}
                        {schema.columns.map((column) => {
                            const sorted = column.sortBy !== null && state.list.sort === column.sortBy;
                            const ascending = state.list.direction.toUpperCase() === 'ASC';

                            return (
                                <th key={column.field} className={alignClass(column.align)} aria-sort={sorted ? (ascending ? 'ascending' : 'descending') : undefined}>
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
                        <tr key={row.id} style={styleOf(row.style)}>
                            {schema.selectable && (
                                <td className="pc-col-check">
                                    <input type="checkbox" aria-label={String(row.id)} checked={state.selection.includes(String(row.id))} onChange={() => crud.toggleRow(row.id)} />
                                </td>
                            )}
                            {schema.columns.map((column) => (
                                // The HTML comes rendered and escaped by the server — the same
                                // string the Livewire screen prints for this cell.
                                <td key={column.field} className={alignClass(column.align)} dangerouslySetInnerHTML={{ __html: row.cells[column.field]?.html ?? '' }} />
                            ))}
                            {hasRowButtons && (
                                <td className="pc-col-actions">
                                    <RowButtons crud={crud} state={state} t={t} row={row} />
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
                {state.totals.length > 0 && (
                    <tfoot>
                        <tr>
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

function RowButtons({ crud, state, t, row }: PartProps & { row: Row }) {
    const { permissions } = state.schema!;
    const [pending, setPending] = useState<RowAction | null>(null);

    return (
        <>
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

function FormModal({ crud, state, t }: PartProps) {
    const schema = state.schema!;
    const form = state.form!;
    const titleId = `pc-form-title-${crud.screen}`;
    useEscape(() => crud.closeForm());

    return (
        <div className="pc-overlay" onMouseDown={(event) => event.target === event.currentTarget && crud.closeForm()}>
            <div className="pc-modal" role="dialog" aria-modal="true" aria-labelledby={titleId}>
                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        void crud.save();
                    }}
                >
                    <div className="pc-modal-head">
                        <h3 className="pc-modal-title" id={titleId}>
                            {form.mode === 'create' ? t.newRecord : t.editRecord} — {schema.titleSingular}
                        </h3>
                        <button type="button" className="pc-icon-btn" aria-label={t.close} onClick={() => crud.closeForm()}>
                            ✕
                        </button>
                    </div>
                    <div className="pc-modal-body">
                        {form.errors._general && (
                            <p className="pc-alert" role="alert">
                                {form.errors._general}
                            </p>
                        )}
                        <div className="pc-form">
                            {schema.form.map((field) => (
                                <FormField key={field.field} field={field} crud={crud} state={state} t={t} />
                            ))}
                        </div>
                    </div>
                    <div className="pc-modal-foot">
                        <button type="button" className="pc-btn" onClick={() => crud.closeForm()}>
                            {t.cancel}
                        </button>
                        <button type="submit" className="pc-btn pc-btn--primary" disabled={form.saving}>
                            {form.saving ? t.saving : t.save}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function FormField({ field, crud, state, t }: PartProps & { field: Field }) {
    const form = state.form!;
    const id = `pc-${crud.screen}-${field.field}`;
    const error = form.errors[field.field];
    const value = form.data[field.field];
    const control = controlOf(field);
    const common = {
        id,
        className: `pc-input${error ? ' pc-input--error' : ''}`,
        disabled: field.readonly,
        'aria-invalid': error ? true : undefined,
        'aria-describedby': error ? `${id}-error` : undefined,
    };
    let input: ReactNode;

    if (control === 'switch') {
        input = (
            <label className="pc-switch">
                <input id={id} type="checkbox" checked={value === true || value === 1 || value === '1'} disabled={field.readonly} onChange={(event) => crud.setField(field.field, event.target.checked)} />
                <span className="pc-switch-track" />
                <span>{field.label}</span>
            </label>
        );
    } else if (control === 'textarea') {
        input = <textarea {...common} placeholder={field.placeholder ?? undefined} value={toInput(field, value)} onChange={(event) => crud.setField(field.field, event.target.value)} />;
    } else if (control === 'select' || control === 'yesno') {
        const options = control === 'yesno' ? [{ label: t.yes, value: '1' }, { label: t.no, value: '0' }] : (field.options ?? []);
        input = (
            <select {...common} value={toInput(field, value)} onChange={(event) => crud.setField(field.field, event.target.value)}>
                <option value="">{t.choose}</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        );
    } else if (control === 'file') {
        input = (
            <>
                <input {...common} type="file" accept={field.type === 'image' ? 'image/*' : undefined} onChange={(event) => crud.setFile(field.field, event.target.files?.[0] ?? null)} />
                {typeof value === 'string' && value !== '' && <p className="pc-help">{value}</p>}
            </>
        );
    } else if (control === 'lookup') {
        input = (
            <Lookup
                id={id}
                value={value === null || value === undefined ? '' : String(value)}
                label={form.labels[field.field] ?? ''}
                disabled={field.readonly}
                invalid={Boolean(error)}
                t={t}
                search={(query) => crud.lookup(field.field, query)}
                onPick={(item) => crud.setField(field.field, item?.value ?? '', item?.label ?? '')}
            />
        );
    } else {
        input = (
            <input
                {...common}
                type={inputType(field)}
                placeholder={field.placeholder ?? undefined}
                // A password opens empty and empty means "keep it".
                autoComplete={field.type === 'password' ? 'new-password' : undefined}
                value={toInput(field, value)}
                onChange={(event) => crud.setField(field.field, fromInput(field, event.target.value))}
            />
        );
    }

    return (
        <div className={`pc-form-field${isWide(field) ? ' pc-form-field--wide' : ''}`}>
            {control !== 'switch' && (
                <label className="pc-label" htmlFor={id}>
                    {field.label}
                    {field.required && (
                        <span className="pc-required" aria-hidden="true">
                            {' '}
                            *
                        </span>
                    )}
                </label>
            )}
            {input}
            {field.help && <p className="pc-help">{field.help}</p>}
            {error && (
                <p className="pc-error" id={`${id}-error`} role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}

// ── Lookup (searchdropdown) ─────────────────────────────────────────────────

interface LookupProps {
    id: string;
    value: string;
    label: string;
    disabled?: boolean;
    invalid?: boolean;
    t: Words;
    search: (query: string) => Promise<LookupItem[]>;
    onPick: (item: LookupItem | null) => void;
}

function Lookup({ id, value, label, disabled, invalid, t, search, onPick }: LookupProps) {
    const [text, setText] = useState(label || value);
    const [items, setItems] = useState<LookupItem[] | null>(null);
    const [active, setActive] = useState(-1);
    const seq = useRef(0);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => setText(label || value), [label, value]);

    const run = useMemo(
        () =>
            debounce((query: string) => {
                const mine = ++seq.current;
                void search(query).then((found) => {
                    if (mine === seq.current) {
                        setItems(found);
                        setActive(-1);
                    }
                });
            }, 250),
        [search],
    );
    useEffect(() => () => run.cancel(), [run]);

    useEffect(() => {
        const close = (event: MouseEvent) => {
            if (!ref.current?.contains(event.target as Node)) {
                setItems(null);
            }
        };
        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    const pick = (item: LookupItem) => {
        setText(String(item.label));
        setItems(null);
        onPick(item);
    };

    return (
        <div className="pc-combo" ref={ref}>
            <input
                id={id}
                type="text"
                role="combobox"
                aria-expanded={items !== null}
                aria-controls={`${id}-list`}
                aria-autocomplete="list"
                aria-invalid={invalid || undefined}
                autoComplete="off"
                className={`pc-input${invalid ? ' pc-input--error' : ''}`}
                placeholder={t.typeToSearch}
                disabled={disabled}
                value={text}
                onFocus={() => run(text === (label || value) ? '' : text)}
                onChange={(event) => {
                    setText(event.target.value);
                    if (event.target.value === '') {
                        onPick(null);
                    }
                    run(event.target.value);
                }}
                onKeyDown={(event) => {
                    if (!items) {
                        return;
                    }
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        setActive(Math.min(items.length - 1, active + 1));
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        setActive(Math.max(0, active - 1));
                    } else if (event.key === 'Enter' && active >= 0) {
                        event.preventDefault();
                        pick(items[active]);
                    } else if (event.key === 'Escape') {
                        event.stopPropagation();
                        setItems(null);
                    }
                }}
            />
            {items !== null && (
                <ul className="pc-combo-list" id={`${id}-list`} role="listbox">
                    {items.length === 0 && <li className="pc-combo-note">{t.noResults}</li>}
                    {items.map((item, index) => (
                        <li key={String(item.value)} role="option" aria-selected={index === active} className="pc-combo-item" onMouseDown={(event) => event.preventDefault()} onClick={() => pick(item)}>
                            {String(item.label)}
                        </li>
                    ))}
                </ul>
            )}
        </div>
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
