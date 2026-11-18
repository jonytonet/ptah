/**
 * The filter panel of a CRUD screen for React — the pieces of
 * _filter-panel.blade.php, the filter chips and the link-filters banner of
 * the Livewire screen, and the recent searches. Each piece reads the screen
 * through its CrudController; the rules are in core/crud/filters.ts and the
 * filtering itself is the server's (the BaseCrud engine).
 */
import { useEffect, useId, useMemo, useRef, useState, useSyncExternalStore } from 'react';
import type { KeyboardEvent as ReactKeyboardEvent, RefObject } from 'react';
import { debounce } from '../../core';
import type { CrudController, CrudState } from '../../core';
import {
    advancedOperators,
    advancedRows,
    advancedText,
    applyUrlFiltersOnce,
    blankAdvancedRow,
    buildBadges,
    clearAllFilters,
    clearDropdown,
    clearLinkFilters,
    clearSearchHistory,
    advancedKind,
    fetchSearchHistory,
    fetchUrlFilters,
    filterSession,
    filterStyles as s,
    filtersWords,
    FilterLookup,
    isNullOperator,
    limitText,
    linkFilters,
    loadSavedFilter,
    operatorLabel,
    removeBadge,
    setAdvancedRows,
    setDateOperator,
    setDateValue,
    setFilterOperator,
    setFilterValue,
    toggleQuickDate,
} from '../../core/crud/filters';
import type { AdvancedRow, FilterListState, FilterMetaItem, FilterSession, FilterSessionState, FiltersMeta, FiltersWords, UrlFilter } from '../../core/crud/filters';
import { ForgeModal } from '../forge';

export { applyUrlFilters, filterByCell, isCellFilterable } from '../../core/crud/filters';

export interface FilterPieceProps {
    crud: CrudController;
    /** `pt_BR`, `en`… Defaults to the page's `<html lang>`. */
    locale?: string;
}

export interface PtahCrudFilterPanelProps extends FilterPieceProps {
    /** Shows a close button in the panel's header. */
    onClose?: () => void;
}

const text = (value: unknown) => (value === undefined || value === null ? '' : String(value));

function useCrud(crud: CrudController): CrudState {
    return useSyncExternalStore(crud.subscribe, crud.getState, crud.getState);
}

/** The screen's filter session, loaded once the screen is. */
function useFilterSession(crud: CrudController, ready: boolean): [FilterSession, FilterSessionState] {
    const session = useMemo(() => filterSession(crud), [crud]);
    const state = useSyncExternalStore(session.subscribe, session.getState, session.getState);

    useEffect(() => {
        if (ready) {
            void session.ensure();
        }
    }, [session, ready]);

    return [session, state];
}

/** Closes on a click outside `ref`. */
function useOutside(ref: RefObject<HTMLElement | null>, onOutside: () => void, active: boolean) {
    useEffect(() => {
        if (!active) {
            return;
        }
        const close = (event: MouseEvent) => {
            if (!ref.current?.contains(event.target as Node)) {
                onOutside();
            }
        };
        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [ref, onOutside, active]);
}

const RemoveButton = ({ label, onClick }: { label: string; onClick: () => void }) => (
    <button type="button" style={s.chipButton} aria-label={label} title={label} onClick={onClick}>
        <span aria-hidden="true">×</span>
    </button>
);

// ── The panel ───────────────────────────────────────────────────────────────

/**
 * The complete filter panel: every filterable column with its operator,
 * the date ranges, the quick dates, the searchdropdown filters with their
 * cascade, the custom filters, the advanced search and the saved filters.
 */
export function PtahCrudFilterPanel({ crud, locale, onClose }: PtahCrudFilterPanelProps) {
    const state = useCrud(crud);
    const ready = state.status === 'ready' && !!state.schema;
    const [session, filters] = useFilterSession(crud, ready);
    const t = useMemo(() => filtersWords(locale), [locale]);
    const titleId = useId();

    if (!ready) {
        return null;
    }

    if (filters.status === 'error') {
        return (
            <section style={s.panel} aria-labelledby={titleId}>
                <div style={s.body}>
                    <h3 style={s.title} id={titleId}>
                        {t.title}
                    </h3>
                    <p role="alert" style={s.error}>
                        {t.loadError} {filters.error}
                    </p>
                    <div>
                        <button type="button" className="pc-btn" onClick={() => void session.ensure()}>
                            {t.retry}
                        </button>
                    </div>
                </div>
            </section>
        );
    }

    if (!filters.meta) {
        return (
            <div style={s.panel}>
                <p role="status" style={s.note}>
                    {t.loading}
                </p>
            </div>
        );
    }

    const meta = filters.meta;
    const list = state.list as FilterListState;
    const advanced = meta.advanced.enabled && meta.advanced.fields.length > 0;

    if (meta.filters.length === 0 && !meta.quick_dates.enabled && !advanced) {
        return null;
    }

    const count = buildBadges(list, meta, filters.labels, t).length;

    return (
        <section style={s.panel} aria-labelledby={titleId}>
            <div style={s.head}>
                <div style={s.headSide}>
                    <h3 style={s.title} id={titleId}>
                        {t.title}
                    </h3>
                    {count > 0 && <span style={s.count}>{t.active(count)}</span>}
                </div>
                <div style={s.headSide}>
                    <button type="button" className="pc-btn" onClick={() => void clearAllFilters(crud, session)}>
                        {t.clearAll}
                    </button>
                    {onClose && (
                        <button type="button" className="pc-icon-btn" aria-label={t.close} title={t.close} onClick={onClose}>
                            ✕
                        </button>
                    )}
                </div>
            </div>

            <div style={s.body}>
                {meta.quick_dates.enabled && <QuickDates crud={crud} meta={meta} list={list} t={t} />}

                {meta.filters.length > 0 && (
                    <div style={s.grid}>
                        {meta.filters.map((item) => (
                            <FilterControl key={`${item.custom ? 'c' : 'f'}-${item.field}`} crud={crud} session={session} item={item} list={list} labels={filters.labels} meta={meta} t={t} />
                        ))}
                    </div>
                )}

                {advanced && <AdvancedSearch crud={crud} meta={meta} list={list} t={t} />}

                <SavedFilters crud={crud} state={state} t={t} />
            </div>

            <SaveFooter crud={crud} t={t} />
        </section>
    );
}

interface ControlProps {
    crud: CrudController;
    session: FilterSession;
    item: FilterMetaItem;
    list: FilterListState;
    labels: Record<string, string>;
    meta: FiltersMeta;
    t: FiltersWords;
}

function FilterControl(props: ControlProps) {
    const { item } = props;

    if (item.kind === 'searchdropdown') {
        return <DropdownFilter {...props} />;
    }
    if (item.kind === 'date' && !item.custom) {
        return <DateRangeFilter {...props} />;
    }
    if (item.kind === 'select') {
        return <SelectFilter {...props} />;
    }

    return <ValueFilter {...props} />;
}

function QuickDates({ crud, meta, list, t }: { crud: CrudController; meta: FiltersMeta; list: FilterListState; t: FiltersWords }) {
    const id = useId();

    return (
        <div role="group" aria-labelledby={id}>
            <p id={id} style={s.section}>
                {t.dateShortcuts}
            </p>
            <div style={s.wrap}>
                {meta.quick_dates.periods.map((period) => (
                    <button key={period} type="button" className={`pc-btn${list.quick_date === period ? ' pc-btn--active' : ''}`} aria-pressed={list.quick_date === period} onClick={() => void toggleQuickDate(crud, period)}>
                        {t.quick[period] ?? period}
                    </button>
                ))}
            </div>
        </div>
    );
}

/** Text and number filters with their operator; custom text, number and date filters (one value). */
function ValueFilter({ crud, item, list, t }: ControlProps) {
    const id = useId();
    const value = list.filters[item.field];
    const op = list.operators[item.field] ?? '';
    const [typed, setTyped] = useState(text(value));
    const apply = useMemo(() => debounce((next: string) => void setFilterValue(crud, item.field, next), 400), [crud, item.field]);
    const numeric = item.kind === 'number';
    const nullOp = isNullOperator(op);

    useEffect(() => setTyped(text(value)), [value]);
    useEffect(() => () => apply.cancel(), [apply]);

    return (
        <div>
            <label className="pc-label" htmlFor={id}>
                {item.label}
            </label>
            <div style={s.row}>
                {item.operators.length > 0 && (
                    <select className="pc-input" style={s.op} aria-label={`${item.label} — ${t.operator}`} value={op || item.operators[0]} onChange={(event) => void setFilterOperator(crud, item.field, event.target.value)}>
                        {item.operators.map((option) => (
                            <option key={option} value={option}>
                                {operatorLabel(t, option, numeric)}
                            </option>
                        ))}
                    </select>
                )}
                {item.kind === 'date' ? (
                    <input id={id} type="date" className="pc-input" style={s.grow} value={text(value)} onChange={(event) => void setFilterValue(crud, item.field, event.target.value)} />
                ) : (
                    <input
                        id={id}
                        type={numeric ? 'number' : 'text'}
                        step={numeric ? 'any' : undefined}
                        className="pc-input"
                        style={s.grow}
                        placeholder={nullOp ? '—' : `${item.label}...`}
                        disabled={nullOp}
                        value={typed}
                        onChange={(event) => {
                            setTyped(event.target.value);
                            apply(event.target.value);
                        }}
                    />
                )}
            </div>
        </div>
    );
}

function SelectFilter({ crud, item, list, meta, t }: ControlProps) {
    const id = useId();
    const value = text(list.filters[item.field]);

    return (
        <div>
            <label className="pc-label" htmlFor={id}>
                {item.label}
            </label>
            <select id={id} className="pc-input" value={value} onChange={(event) => void setFilterValue(crud, item.field, event.target.value)}>
                <option value="">{t.all}</option>
                {(item.options ?? []).map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
                {!item.custom && (
                    <>
                        <option value={meta.sentinels.empty}>({t.isNull})</option>
                        <option value={meta.sentinels.filled}>({t.isNotNull})</option>
                    </>
                )}
            </select>
        </div>
    );
}

function DateRangeFilter({ crud, item, list, t }: ControlProps) {
    const edges = [
        { edge: 'start' as const, title: t.from, ops: item.date_operators?.start ?? ['>=', '>', '='] },
        { edge: 'end' as const, title: t.to, ops: item.date_operators?.end ?? ['<=', '<', '='] },
    ];

    return (
        <fieldset style={{ ...s.fieldset, ...s.wide }}>
            <legend className="pc-label">{item.label}</legend>
            <div style={s.pair}>
                {edges.map(({ edge, title, ops }) => {
                    const key = `${item.field}_${edge}`;

                    return (
                        <div key={edge}>
                            <p style={s.muted} aria-hidden="true">
                                {title}
                            </p>
                            <div style={s.row}>
                                <select className="pc-input" style={s.opNarrow} aria-label={`${item.label} — ${title} — ${t.operator}`} value={list.date_operators[key] || ops[0]} onChange={(event) => void setDateOperator(crud, key, event.target.value)}>
                                    {ops.map((option) => (
                                        <option key={option} value={option}>
                                            {operatorLabel(t, option, true)}
                                        </option>
                                    ))}
                                </select>
                                <input type="date" className="pc-input" style={s.grow} aria-label={`${item.label} — ${title}`} value={list.date_ranges[key] ?? ''} onChange={(event) => void setDateValue(crud, key, event.target.value)} />
                            </div>
                        </div>
                    );
                })}
            </div>
        </fieldset>
    );
}

/** A searchdropdown filter: operator, the chosen value as a chip, the search box with the options. */
function DropdownFilter({ crud, session, item, list, labels, t }: ControlProps) {
    const id = useId();
    const lookup = useMemo(() => new FilterLookup(crud, session, item.field), [crud, session, item.field]);
    const box = useSyncExternalStore(lookup.subscribe, lookup.getState, lookup.getState);
    const ref = useRef<HTMLDivElement>(null);
    const parent = item.depends_on ? text(list.filters[item.depends_on]) : '';
    const firstParent = useRef(parent);

    useEffect(() => () => lookup.destroy(), [lookup]);
    // A new parent value: the options on hand belonged to the old one.
    useEffect(() => {
        if (parent !== firstParent.current) {
            firstParent.current = parent;
            lookup.reset();
        }
    }, [parent, lookup]);
    useOutside(ref, () => lookup.close(), box.open);

    const value = list.filters[item.field];
    const selected = value === undefined || value === null || value === '' ? null : (labels[item.field] ?? String(value));
    const op = list.operators[item.field] ?? '';
    const listId = `${id}-list`;

    const keys = (event: ReactKeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            lookup.move(1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            lookup.move(-1);
        } else if (event.key === 'Enter' && box.open && box.active >= 0) {
            event.preventDefault();
            void lookup.choose();
        } else if (event.key === 'Escape' && box.open) {
            event.stopPropagation();
            lookup.close();
        }
    };

    return (
        <div>
            <label className="pc-label" htmlFor={id}>
                {item.label}
            </label>
            <select className="pc-input" style={s.spaced} aria-label={`${item.label} — ${t.operator}`} value={op || item.operators[0] || '='} onChange={(event) => void setFilterOperator(crud, item.field, event.target.value)}>
                {item.operators.map((option) => (
                    <option key={option} value={option}>
                        {operatorLabel(t, option)}
                    </option>
                ))}
            </select>
            {selected !== null && (
                <div style={s.spaced}>
                    <span style={s.chip}>
                        {selected}
                        <RemoveButton label={t.removeFilter(item.label)} onClick={() => void clearDropdown(crud, session, item.field)} />
                    </span>
                </div>
            )}
            <div className="pc-combo" ref={ref}>
                <div style={s.comboBox}>
                    <input
                        id={id}
                        type="text"
                        role="combobox"
                        aria-expanded={box.open}
                        aria-controls={listId}
                        aria-autocomplete="list"
                        aria-activedescendant={box.open && box.active >= 0 ? `${id}-opt-${box.active}` : undefined}
                        autoComplete="off"
                        className="pc-input"
                        style={s.comboInput}
                        placeholder={selected !== null ? t.change : (item.placeholder ?? t.searchLabel(item.label))}
                        value={box.text}
                        onFocus={() => lookup.focus()}
                        onChange={(event) => lookup.type(event.target.value)}
                        onKeyDown={keys}
                    />
                    <button
                        type="button"
                        tabIndex={-1}
                        style={s.chevron}
                        aria-label={t.showOptions}
                        onMouseDown={(event) => {
                            event.preventDefault();
                            lookup.toggle();
                        }}
                    >
                        <span aria-hidden="true">{box.open ? '▴' : '▾'}</span>
                    </button>
                </div>
                {box.open && (
                    <ul className="pc-combo-list" id={listId} role="listbox" aria-label={item.label} aria-busy={box.loading} style={item.start_list === 'top' ? s.listTop : undefined}>
                        {box.items.length === 0 && (
                            <li className="pc-combo-note" role={box.loading ? 'status' : undefined}>
                                {box.loading ? t.loading : t.noResults}
                            </li>
                        )}
                        {box.items.map((option, index) => (
                            <li
                                key={`${String(option.value)}-${index}`}
                                id={`${id}-opt-${index}`}
                                role="option"
                                aria-selected={index === box.active}
                                className="pc-combo-item"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => void lookup.choose(index)}
                            >
                                {text(option.label)}
                                {'labelTwo' in option && <span style={s.sub}>{text(option.labelTwo)}</span>}
                                {'labelThree' in option && <span style={s.sub}>{text(option.labelThree)}</span>}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}

// ── Advanced search ─────────────────────────────────────────────────────────

function AdvancedSearch({ crud, meta, list, t }: { crud: CrudController; meta: FiltersMeta; list: FilterListState; t: FiltersWords }) {
    const rows = advancedRows(list);
    const [on, setOn] = useState(rows.length > 0);
    const [draft, setDraft] = useState<AdvancedRow>(() => blankAdvancedRow(meta));
    const switchId = useId();

    useEffect(() => {
        if (rows.length > 0) {
            setOn(true);
        }
    }, [rows.length]);

    const toggle = () => {
        // Turning it off forgets the rows (toggleAdvancedSearch()).
        if (on && rows.length > 0) {
            void setAdvancedRows(crud, []);
        }
        setOn(!on);
    };

    const add = () => {
        const value = draft.value.trim();
        if (value === '' || draft.field === '') {
            return;
        }
        void setAdvancedRows(crud, [...rows, { ...draft, value }]);
        setDraft({ ...draft, value: '' });
    };

    const kind = advancedKind(meta, draft.field);

    return (
        <div>
            <label className="pc-switch" htmlFor={switchId}>
                <input id={switchId} type="checkbox" role="switch" checked={on} onChange={toggle} />
                <span className="pc-switch-track" />
                <span>{t.advanced}</span>
            </label>
            {on && (
                <div>
                    {rows.length === 0 ? (
                        <p style={{ ...s.muted, marginTop: '8px' }}>{t.advancedEmpty}</p>
                    ) : (
                        <ul style={{ ...s.plainList, marginTop: '8px' }}>
                            {rows.map((row, index) => {
                                const label = advancedText(t, meta, row, index);

                                return (
                                    <li key={`${row.field}-${index}`}>
                                        <span style={s.chip}>
                                            {label}
                                            <RemoveButton label={t.removeRow(label)} onClick={() => void setAdvancedRows(crud, rows.filter((_, other) => other !== index))} />
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    <form
                        style={s.advancedRow}
                        onSubmit={(event) => {
                            event.preventDefault();
                            add();
                        }}
                    >
                        {rows.length > 0 && (
                            <select className="pc-input" style={s.advancedControl} aria-label={t.advancedLogic} value={draft.logic} onChange={(event) => setDraft({ ...draft, logic: event.target.value === 'OR' ? 'OR' : 'AND' })}>
                                <option value="AND">{t.and}</option>
                                <option value="OR">{t.or}</option>
                            </select>
                        )}
                        <select
                            className="pc-input"
                            style={s.advancedControl}
                            aria-label={t.advancedField}
                            value={draft.field}
                            onChange={(event) => setDraft({ ...draft, field: event.target.value, operator: advancedOperators(meta, event.target.value)[0] ?? '=', value: '' })}
                        >
                            {meta.advanced.fields.map((field) => (
                                <option key={field.field} value={field.field}>
                                    {field.label}
                                </option>
                            ))}
                        </select>
                        <select className="pc-input" style={s.advancedControl} aria-label={t.operator} value={draft.operator} onChange={(event) => setDraft({ ...draft, operator: event.target.value })}>
                            {advancedOperators(meta, draft.field).map((option) => (
                                <option key={option} value={option}>
                                    {operatorLabel(t, option)}
                                </option>
                            ))}
                        </select>
                        <input
                            type={kind === 'number' ? 'number' : kind === 'date' ? 'date' : 'text'}
                            step={kind === 'number' ? 'any' : undefined}
                            className="pc-input"
                            style={s.advancedControl}
                            aria-label={t.advancedValue}
                            value={draft.value}
                            onChange={(event) => setDraft({ ...draft, value: event.target.value })}
                        />
                        <button type="submit" className="pc-btn" disabled={draft.value.trim() === ''}>
                            {t.add}
                        </button>
                    </form>
                </div>
            )}
        </div>
    );
}

// ── Saved filters ───────────────────────────────────────────────────────────

function SavedFilters({ crud, state, t }: { crud: CrudController; state: CrudState; t: FiltersWords }) {
    const [deleting, setDeleting] = useState<string | null>(null);
    const names = Object.keys(state.preferences?.saved_filters ?? {});

    if (names.length === 0) {
        return null;
    }

    const confirm = () => {
        const name = deleting;
        setDeleting(null);
        if (name !== null) {
            void crud.forgetFilter(name);
        }
    };

    return (
        <div style={s.wrap}>
            <span className="pc-label" style={{ margin: 0 }}>
                {t.saved}
            </span>
            {names.map((name) => (
                <span key={name} style={{ display: 'inline-flex' }}>
                    <button type="button" className="pc-btn" style={s.savedLoad} onClick={() => void loadSavedFilter(crud, name)}>
                        {name}
                    </button>
                    <button type="button" className="pc-btn" style={s.savedDelete} aria-label={t.deleteSaved(name)} title={t.deleteSaved(name)} onClick={() => setDeleting(name)}>
                        <span aria-hidden="true">×</span>
                    </button>
                </span>
            ))}
            <ForgeModal
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                title={t.deleteSavedTitle}
                size="sm"
                footer={
                    <>
                        <button type="button" className="pc-btn" onClick={() => setDeleting(null)}>
                            {t.cancel}
                        </button>
                        <button type="button" className="pc-btn pc-btn--danger" onClick={confirm}>
                            {t.delete}
                        </button>
                    </>
                }
            >
                <p>{deleting !== null ? t.deleteSavedText(deleting) : ''}</p>
            </ForgeModal>
        </div>
    );
}

function SaveFooter({ crud, t }: { crud: CrudController; t: FiltersWords }) {
    const [saving, setSaving] = useState(false);
    const [name, setName] = useState('');

    const cancel = () => {
        setSaving(false);
        setName('');
    };

    const submit = () => {
        const trimmed = name.trim();
        if (trimmed === '') {
            return;
        }
        cancel();
        void crud.saveFilter(trimmed);
    };

    return (
        <div style={s.foot}>
            {saving ? (
                <form
                    style={s.saveForm}
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <input
                        type="text"
                        className="pc-input"
                        style={s.grow}
                        aria-label={t.saveName}
                        placeholder={t.savePlaceholder}
                        maxLength={80}
                        autoFocus
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Escape') {
                                event.stopPropagation();
                                cancel();
                            }
                        }}
                    />
                    <button type="submit" className="pc-btn pc-btn--primary">
                        {t.save}
                    </button>
                    <button type="button" className="pc-btn" onClick={cancel}>
                        {t.cancel}
                    </button>
                </form>
            ) : (
                <button type="button" className="pc-btn" onClick={() => setSaving(true)}>
                    {t.saveAction}
                </button>
            )}
        </div>
    );
}

// ── Chips ───────────────────────────────────────────────────────────────────

/** The active search and filters as chips, each with its remove button (the toolbar's chips). */
export function PtahCrudFilterBadges({ crud, locale }: FilterPieceProps) {
    const state = useCrud(crud);
    const ready = state.status === 'ready';
    const [, filters] = useFilterSession(crud, ready);
    const t = useMemo(() => filtersWords(locale), [locale]);

    if (!ready) {
        return null;
    }

    const list = state.list as FilterListState;
    const badges = buildBadges(list, filters.meta, filters.labels, t);

    if (list.search === '' && badges.length === 0) {
        return null;
    }

    return (
        <div style={s.badges} role="group" aria-label={t.title}>
            {list.search !== '' && (
                <span style={s.chip}>
                    <span style={s.chipLabel}>{t.search}:</span>
                    {limitText(list.search, 30)}
                    <RemoveButton label={t.clearSearch} onClick={() => void crud.setSearch('')} />
                </span>
            )}
            {badges.map((badge) => (
                <span key={`${badge.key}|${badge.label}`} style={s.chip}>
                    <span style={s.chipLabel}>{badge.label}:</span>
                    {limitText(badge.value, 20)}
                    <RemoveButton label={t.removeFilter(badge.label)} onClick={() => void removeBadge(crud, badge.key)} />
                </span>
            ))}
        </div>
    );
}

// ── Link filters ────────────────────────────────────────────────────────────

export interface PtahCrudUrlFiltersProps extends FilterPieceProps {
    /** Take `?f[...]` of the page address into the listing when the screen loads (default true). */
    readLocation?: boolean;
}

/** The banner of the link filters (`?f[...]`), with "clear link filters". */
export function PtahCrudUrlFilters({ crud, locale, readLocation = true }: PtahCrudUrlFiltersProps) {
    const state = useCrud(crud);
    const ready = state.status === 'ready';
    const t = useMemo(() => filtersWords(locale), [locale]);
    const f = linkFilters(state.list as FilterListState);
    const key = JSON.stringify(f);
    const [items, setItems] = useState<UrlFilter[]>([]);

    useEffect(() => {
        if (ready && readLocation) {
            void applyUrlFiltersOnce(crud);
        }
    }, [crud, ready, readLocation]);

    useEffect(() => {
        if (!ready || key === '{}') {
            setItems([]);

            return;
        }
        let live = true;
        fetchUrlFilters(crud, JSON.parse(key) as Record<string, unknown>)
            .then((found) => live && setItems(found))
            .catch(() => live && setItems([]));

        return () => {
            live = false;
        };
    }, [crud, key, ready]);

    if (!ready || key === '{}' || items.length === 0) {
        return null;
    }

    return (
        <div role="status" style={s.banner}>
            <strong>{t.urlActive}</strong>
            <span style={s.wrap}>
                {items.map((item) => (
                    <span key={item.field} style={s.chip}>
                        <span style={s.chipLabel}>{item.label}:</span>
                        {limitText(item.value, 30)}
                    </span>
                ))}
            </span>
            <button type="button" className="pc-btn" style={s.pushRight} onClick={() => void clearLinkFilters(crud)}>
                {t.urlClear}
            </button>
        </div>
    );
}

// ── Recent searches ─────────────────────────────────────────────────────────

/** A toolbar button with the recent searches of this screen: pick one to search it again, or clear them. */
export function PtahCrudSearchHistory({ crud, locale }: FilterPieceProps) {
    const state = useCrud(crud);
    const t = useMemo(() => filtersWords(locale), [locale]);
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<string[] | null>(null);
    const [error, setError] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const listId = useId();

    useOutside(ref, () => setOpen(false), open && !confirming);

    useEffect(() => {
        if (!open || confirming) {
            return;
        }
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [open, confirming]);

    if (state.status !== 'ready') {
        return null;
    }

    const load = async () => {
        setItems(null);
        setError(false);
        try {
            setItems(await fetchSearchHistory(crud));
        } catch {
            setError(true);
        }
    };

    const clear = async () => {
        setConfirming(false);
        try {
            await clearSearchHistory(crud);
            setItems([]);
        } catch (failure) {
            crud.toast(failure instanceof Error ? failure.message : t.historyError, 'danger');
        }
    };

    return (
        <div className="pc-menu" ref={ref}>
            <button
                type="button"
                className="pc-btn"
                aria-haspopup="true"
                aria-expanded={open}
                aria-controls={open ? listId : undefined}
                onClick={() => {
                    const next = !open;
                    setOpen(next);
                    if (next) {
                        void load();
                    }
                }}
            >
                {t.history}
            </button>
            {open && (
                <div className="pc-menu-list" id={listId} role="region" aria-label={t.history}>
                    {items === null && !error && (
                        <p role="status" style={s.note}>
                            {t.loading}
                        </p>
                    )}
                    {error && (
                        <p role="alert" style={s.note}>
                            {t.historyError}
                        </p>
                    )}
                    {items !== null && items.length === 0 && <p style={s.note}>{t.historyEmpty}</p>}
                    {items !== null && items.length > 0 && (
                        <>
                            <ul style={s.menuList}>
                                {items.map((term) => (
                                    <li key={term}>
                                        <button
                                            type="button"
                                            className="pc-menu-item"
                                            onClick={() => {
                                                setOpen(false);
                                                void crud.setSearch(term);
                                            }}
                                        >
                                            {term}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                            <button type="button" className="pc-menu-item" style={s.danger} onClick={() => setConfirming(true)}>
                                {t.historyClear}
                            </button>
                        </>
                    )}
                </div>
            )}
            <ForgeModal
                open={confirming}
                onClose={() => setConfirming(false)}
                title={t.historyClearTitle}
                size="sm"
                footer={
                    <>
                        <button type="button" className="pc-btn" onClick={() => setConfirming(false)}>
                            {t.cancel}
                        </button>
                        <button type="button" className="pc-btn pc-btn--danger" onClick={() => void clear()}>
                            {t.historyClear}
                        </button>
                    </>
                }
            >
                <p>{t.historyClearText}</p>
            </ForgeModal>
        </div>
    );
}
