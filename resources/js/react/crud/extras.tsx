/**
 * BaseCrud extras for React — the form draft notice, the server-side form
 * behaviours, "Save & add another", the master/detail toggle and row, the
 * columns menu and the column resize handle. Each piece takes the screen's
 * CrudController and renders nothing when its feature is off.
 */
import { useEffect, useMemo, useRef, useState, useSyncExternalStore } from 'react';
import type { CSSProperties, DragEvent, ReactNode } from 'react';
import type { CrudController, CrudState, Row } from '../../core';
import {
    columnResizer,
    detailsOf,
    extrasWords,
    fill,
    FormDraftSession,
    fullOrder,
    listColumns,
    menuColumns,
    moveColumn,
    resetColumns,
    saveAndNew,
    saveColumnOrder,
    setAllColumns,
    watchFormChanges,
} from '../../core/crud/extras';
import type { ColumnItem, DetailScreen, ExtrasWords } from '../../core/crud/extras';
import { ForgeAlert, ForgeButton, ForgeEmpty, ForgeModal, ForgeSpinner } from '../forge';
import { Icon } from '../icons';

interface PieceProps {
    crud: CrudController;
    /** `pt_BR`, `en`… Defaults to the page's `<html lang>`. */
    locale?: string;
}

function useCrud(crud: CrudController): CrudState {
    return useSyncExternalStore(crud.subscribe, crud.getState, crud.getState);
}

function useStore<T>(store: { subscribe: (listener: () => void) => () => void; get: () => T }): T {
    return useSyncExternalStore(store.subscribe, store.get, store.get);
}

const useWords = (locale?: string): ExtrasWords => useMemo(() => extrasWords(locale), [locale]);

const ROW: CSSProperties = { display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 8 };
const MUTED: CSSProperties = { color: 'var(--pc-text-muted)', fontSize: 13 };

// ── Form draft ──────────────────────────────────────────────────────────────

/**
 * Mounted inside the form (top of the modal body): keeps what is typed as a
 * draft in this browser, restores it when the form is opened again (or
 * offers to, when typing already started), and offers "Clear all" (new) /
 * "Revert to original" (edit) while the draft holds changes.
 */
export function PtahCrudFormDraft({ crud, locale }: PieceProps) {
    const t = useWords(locale);
    const state = useCrud(crud);
    const session = useMemo(() => new FormDraftSession(crud, t), [crud, t]);
    const view = useStore(session);

    useEffect(() => {
        session.start();

        return () => session.stop();
    }, [session]);

    if (!state.schema?.features.formDraft || !state.form || (!view.offer && !view.dirty && !view.busy)) {
        return null;
    }

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginBottom: 12 }}>
            {view.offer && (
                <ForgeAlert color="warn" title={t.draftFound}>
                    <div style={{ ...ROW, marginTop: 8 }}>
                        <ForgeButton size="sm" onClick={() => void session.restore()}>
                            {t.draftRestore}
                        </ForgeButton>
                        <ForgeButton size="sm" color="dark" flat onClick={() => session.discard()}>
                            {t.draftDiscard}
                        </ForgeButton>
                    </div>
                </ForgeAlert>
            )}
            {view.dirty && !view.offer && (
                <div style={{ ...ROW, justifyContent: 'space-between' }}>
                    <span style={MUTED}>{t.draftKept}</span>
                    <ForgeButton size="sm" color="dark" flat disabled={state.form.saving || view.busy} onClick={() => void (view.mode === 'edit' ? session.revert() : session.clear())}>
                        {view.mode === 'edit' ? t.draftRevert : t.draftClear}
                    </ForgeButton>
                </div>
            )}
            {view.busy && <ForgeSpinner size="sm" label={t.loading} />}
        </div>
    );
}

// ── Form behaviours ─────────────────────────────────────────────────────────

/**
 * Mounted inside the form, draws nothing: when a field with a `colsOnChange`
 * formula or with dependent dropdowns changes, the server recomputes and the
 * form takes its answer.
 */
export function PtahCrudFormChanges({ crud }: { crud: CrudController }) {
    useEffect(() => watchFormChanges(crud), [crud]);

    return null;
}

/** The extra footer button of a new record's form: saves, then opens a blank form. */
export function PtahCrudSaveAndNew({ crud, locale }: PieceProps) {
    const t = useWords(locale);
    const state = useCrud(crud);
    const form = state.form;

    if (!form || form.mode !== 'create' || !state.schema?.permissions.canCreate) {
        return null;
    }

    return (
        <ForgeButton color="primary" flat disabled={form.saving} onClick={() => void saveAndNew(crud)}>
            {t.saveAndNew}
        </ForgeButton>
    );
}

// ── Master / detail ─────────────────────────────────────────────────────────

/** The expand button of a row (first cell). */
export function PtahCrudDetailToggle({ crud, row, locale }: PieceProps & { row: Row }) {
    const t = useWords(locale);
    const state = useCrud(crud);
    const store = detailsOf(crud);
    const view = useStore(store);

    if (!state.schema?.features.masterDetail) {
        return null;
    }
    const open = view.expanded.includes(String(row.id));

    return (
        <button type="button" className="pc-expand" title={t.showDetails} aria-label={t.showDetails} aria-expanded={open} onClick={() => store.toggle(row.id)}>
            <Icon name="chevronRight" />
        </button>
    );
}

export interface DetailRowProps extends PieceProps {
    row: Row;
    colSpan: number;
    /**
     * Draws one detail: a whole CRUD screen of the child model. PtahCrud
     * passes `<PtahCrud screen={detail.screen} title={detail.title} remember={false} />`
     * — handed in, not imported, so this file never imports PtahCrud (which
     * imports it).
     */
    renderDetail: (detail: DetailScreen) => ReactNode;
}

/**
 * The expanded content of a row, mounted on the first expansion: under each
 * other, a full CRUD screen per detail of the row (search, filters, form,
 * export, row actions — the child's own permissions), bound by the server to
 * this row, as the nested BaseCrud of the Livewire table.
 */
export function PtahCrudDetailRow({ crud, row, colSpan, locale, renderDetail }: DetailRowProps) {
    const t = useWords(locale);
    const state = useCrud(crud);
    const store = detailsOf(crud);
    const view = useStore(store);
    const key = String(row.id);

    if (!state.schema?.features.masterDetail || !view.expanded.includes(key)) {
        return null;
    }
    const entry = view.entries[key];

    return (
        <tr className="pc-detail-row">
            <td colSpan={colSpan} className="pc-detail-td" aria-busy={!entry || entry.status === 'loading'}>
                {(!entry || (entry.status === 'loading' && entry.details.length === 0)) && <ForgeSpinner label={t.loading} />}
                {entry?.status === 'error' && (
                    <ForgeAlert color="danger">
                        <div style={ROW}>
                            <span>{entry.error || t.detailsError}</span>
                            <ForgeButton size="sm" color="danger" flat onClick={() => void store.load(row.id)}>
                                {t.retry}
                            </ForgeButton>
                        </div>
                    </ForgeAlert>
                )}
                {entry?.status === 'ready' && entry.details.length === 0 && <ForgeEmpty title={t.detailsEmpty} />}
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    {entry?.details.map((detail) =>
                        detail.available ? (
                            <section key={detail.screen} className="pc-detail" aria-label={detail.title || undefined}>
                                {renderDetail(detail)}
                            </section>
                        ) : (
                            <section key={detail.screen} aria-label={detail.title || undefined}>
                                {detail.title && <p style={TITLE}>{detail.title}</p>}
                                <p style={MUTED}>{t.detailUnavailable}</p>
                            </section>
                        ),
                    )}
                </div>
            </td>
        </tr>
    );
}

const TITLE: CSSProperties = { margin: '0 0 8px', fontSize: 12, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '.05em', color: 'var(--pc-text-muted)' };

// ── Columns ─────────────────────────────────────────────────────────────────

/**
 * The toolbar's columns menu: show/hide each column, show all, hide all,
 * reset the layout, and reorder — dragging a column, or with its ↑/↓
 * buttons from the keyboard.
 */
export function PtahCrudColumnsMenu({ crud, locale }: PieceProps) {
    const t = useWords(locale);
    const state = useCrud(crud);
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<ColumnItem[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [dragging, setDragging] = useState<string | null>(null);
    const [over, setOver] = useState<string | null>(null);
    const ref = useRef<HTMLDivElement>(null);
    const preferences = state.preferences;

    const load = () => {
        setError(null);
        listColumns(crud.api, crud.screen).then(
            (found) => setItems(found),
            () => setError(t.columnsError),
        );
    };

    useEffect(() => {
        if (open && items === null) {
            load();
        }
    }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (!open) {
            return;
        }
        const close = (event: MouseEvent | KeyboardEvent) => {
            if (confirming) {
                return;
            }
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
    }, [open, confirming]);

    if (!state.schema || !preferences || Object.keys(preferences.columns ?? {}).length === 0) {
        return null;
    }

    const list = items ? menuColumns(preferences, items) : [];
    const shown = list.map((item) => item.field);
    const hidden = Object.values(preferences.columns).filter((visible) => !visible).length;

    const act = async (work: () => Promise<unknown>) => {
        setBusy(true);
        try {
            await work();
        } finally {
            setBusy(false);
        }
    };
    const move = (field: string, to: number) => void act(() => saveColumnOrder(crud, moveColumn(fullOrder(preferences), shown, field, to)));
    const all = (visible: boolean) => {
        setOpen(false);
        // Every column of the map, as hideAllColumns()/showAllColumns() (id too).
        void act(() => setAllColumns(crud, Object.keys(preferences.columns), visible));
    };
    const reset = () =>
        void act(async () => {
            setConfirming(false);
            try {
                const answer = await resetColumns(crud.api, crud.screen);
                setItems(answer.data);
                await crud.savePreferences({});
            } catch {
                crud.toast(t.error, 'danger');
            }
        });

    const onDrop = (event: DragEvent, field: string) => {
        event.preventDefault();
        if (dragging && dragging !== field) {
            move(dragging, shown.indexOf(field));
        }
        setDragging(null);
        setOver(null);
    };

    return (
        <div className="pc-dd-wrap pc-tb-secondary" ref={ref}>
            <button
                type="button"
                className={`pc-tbtn${hidden > 0 ? ' pc-tbtn--warn-on' : ''}`}
                title={t.columns}
                aria-label={hidden > 0 ? `${t.columns} (${fill(t.hiddenCount, { count: hidden })})` : t.columns}
                aria-haspopup="true"
                aria-expanded={open}
                onClick={() => setOpen(!open)}
            >
                <Icon name="columns" />
                <span className="pc-tbtn-label">{t.columns}</span>
                {hidden > 0 && <span className="pc-count pc-count--warn">{hidden}</span>}
                <Icon name="chevronDown" size={12} />
            </button>
            {open && (
                <div className="pc-dd pc-cols-dd" role="group" aria-label={t.columns} aria-busy={busy}>
                    <div className="pc-cols-quick">
                        <button type="button" className="pc-cols-qbtn" disabled={busy || list.length === 0} onClick={() => all(true)}>
                            {t.showAll}
                        </button>
                        <button type="button" className="pc-cols-qbtn" disabled={busy || list.length === 0} onClick={() => all(false)}>
                            {t.hideAll}
                        </button>
                        <button type="button" className="pc-cols-qbtn" disabled={busy} onClick={() => setConfirming(true)}>
                            {t.resetColumns}
                        </button>
                    </div>
                    {error && (
                        <div style={{ padding: 8 }}>
                            <p className="pc-error" role="alert">
                                {error}
                            </p>
                            <button type="button" className="pc-btn" onClick={load}>
                                {t.retry}
                            </button>
                        </div>
                    )}
                    {!error && items === null && (
                        <div style={{ padding: 8 }}>
                            <ForgeSpinner size="sm" label={t.loading} />
                        </div>
                    )}
                    {items !== null && list.length === 0 && <p style={{ ...MUTED, padding: 8 }}>{t.noColumns}</p>}
                    {list.length > 0 && (
                        <ul className="pc-cols-list" aria-label={t.dragHint}>
                            {list.map((item, index) => (
                                <li
                                    key={item.field}
                                    draggable={!busy}
                                    onDragStart={(event) => {
                                        event.dataTransfer.effectAllowed = 'move';
                                        event.dataTransfer.setData('text/plain', item.field);
                                        setDragging(item.field);
                                    }}
                                    onDragOver={(event) => {
                                        event.preventDefault();
                                        setOver(item.field);
                                    }}
                                    onDrop={(event) => onDrop(event, item.field)}
                                    onDragEnd={() => {
                                        setDragging(null);
                                        setOver(null);
                                    }}
                                    className="pc-cols-item"
                                    data-dragging={dragging === item.field || undefined}
                                    data-over={(over === item.field && dragging !== item.field) || undefined}
                                >
                                    <span className="pc-cols-grip" aria-hidden="true" title={t.dragHint}>
                                        ⠿
                                    </span>
                                    <label className="pc-cols-label">
                                        <input type="checkbox" checked={item.visible} disabled={busy} onChange={(event) => void act(() => crud.setColumnVisible(item.field, event.target.checked))} />
                                        {item.label}
                                    </label>
                                    <button type="button" className="pc-cols-move" aria-label={fill(t.moveUp, { label: item.label })} title={fill(t.moveUp, { label: item.label })} disabled={busy || index === 0} onClick={() => move(item.field, index - 1)}>
                                        <Icon name="chevronUp" size={12} />
                                    </button>
                                    <button type="button" className="pc-cols-move" aria-label={fill(t.moveDown, { label: item.label })} title={fill(t.moveDown, { label: item.label })} disabled={busy || index === list.length - 1} onClick={() => move(item.field, index + 1)}>
                                        <Icon name="chevronDown" size={12} />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <ForgeModal
                        open={confirming}
                        onClose={() => setConfirming(false)}
                        title={t.resetColumns}
                        size="sm"
                        footer={
                            <>
                                <ForgeButton color="dark" flat onClick={() => setConfirming(false)}>
                                    {t.cancel}
                                </ForgeButton>
                                <ForgeButton color="danger" onClick={reset}>
                                    {t.resetConfirmButton}
                                </ForgeButton>
                            </>
                        }
                    >
                        {t.resetConfirm}
                    </ForgeModal>
                </div>
            )}
        </div>
    );
}

/**
 * The resize handle at the right edge of a header cell (the `th` must be
 * `position: relative`): drag with the pointer, ←/→ from the keyboard,
 * double-click to forget the saved width.
 */
export function PtahCrudColumnResizer({ crud, field, label, locale }: PieceProps & { field: string; label: string }) {
    const t = useWords(locale);
    const state = useCrud(crud);
    const resizer = useMemo(() => columnResizer(crud, field), [crud, field]);
    const width = state.preferences?.column_widths?.[field];

    return (
        <div
            role="separator"
            aria-orientation="vertical"
            aria-label={fill(t.resizeColumn, { label })}
            aria-valuenow={width ?? undefined}
            aria-valuemin={60}
            tabIndex={0}
            style={{ position: 'absolute', top: 0, right: -4, width: 8, height: '100%', cursor: 'col-resize', zIndex: 1, touchAction: 'none' }}
            onClick={(event) => event.stopPropagation()}
            onPointerDown={(event) => resizer.pointerDown(event.nativeEvent, event.currentTarget)}
            onKeyDown={(event) => resizer.keyDown(event.nativeEvent, event.currentTarget)}
            onDoubleClick={(event) => void resizer.reset(event.currentTarget)}
        />
    );
}
