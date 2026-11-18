/**
 * Record history and record attachments for React — the History and
 * Attachments buttons of the BaseCrud edit modal and the two dialogs they
 * open (_modal-history / _modal-attachments.blade.php). The dialogs follow
 * the screen's panel: `crud.openPanel('history', id)`,
 * `crud.openPanel('attachments', id)`, closed with `crud.closePanel()`.
 */
import { useCallback, useEffect, useId, useMemo, useState, useSyncExternalStore } from 'react';
import type { CrudController, RowId } from '../../core';
import {
    countsOf,
    deleteAttachment,
    failureText,
    getHistory,
    listAttachments,
    PANEL_ATTACHMENTS,
    PANEL_HISTORY,
    plural,
    recordsWords,
    SR_ONLY,
    uploadAttachments,
} from '../../core/crud/records';
import type { AttachmentItem, HistoryEntry, RecordsWords } from '../../core/crud/records';
import { ForgeAlert, ForgeButton, ForgeEmpty, ForgeModal } from '../forge';

export interface RecordsPanelProps {
    crud: CrudController;
    locale?: string;
}

export interface RecordButtonsProps extends RecordsPanelProps {
    /** The row (or `{ id }` of the record open in the form). A trashed row has neither button. */
    row: { id: RowId; trashed?: boolean };
}

function useCrudState(crud: CrudController) {
    return useSyncExternalStore(crud.subscribe, crud.getState, crud.getState);
}

/** The record of the panel `name`, when it is the open one. */
function usePanel(crud: CrudController, name: string): RowId | null {
    const state = useCrudState(crud);

    return state.panel?.name === name && state.panel.id !== null ? state.panel.id : null;
}

const Loading = ({ t }: { t: RecordsWords }) => (
    <div className="pc-state" role="status" aria-live="polite">
        <span className="pf-spinner" aria-hidden="true" /> {t.loading}
    </div>
);

const MUTED = { color: 'var(--pc-text-muted)' } as const;

// ── Row buttons ─────────────────────────────────────────────────────────────

/**
 * History and Attachments (with the record's attachment count) for one row
 * — or for the record open in the form. The counts of a page come in one
 * request for all its rows.
 */
export function PtahCrudRecordButtons({ crud, row, locale }: RecordButtonsProps) {
    const state = useCrudState(crud);
    const t = useMemo(() => recordsWords(locale), [locale]);
    const features = state.schema?.features;
    const store = countsOf(crud);
    const count = useSyncExternalStore(
        store.subscribe,
        () => (features?.attachments && !row.trashed ? store.get(row.id) : null),
        () => null,
    );

    if (!features || row.trashed || (!features.history && !features.attachments)) {
        return null;
    }

    return (
        <>
            {features.attachments && (
                <button type="button" className="pc-icon-btn" onClick={() => crud.openPanel(PANEL_ATTACHMENTS, row.id)}>
                    {t.attachments}
                    {count !== null && count >= 0 ? ` (${count})` : ''}
                </button>
            )}
            {features.history && (
                <button type="button" className="pc-icon-btn" onClick={() => crud.openPanel(PANEL_HISTORY, row.id)}>
                    {t.history}
                </button>
            )}
        </>
    );
}

// ── History ─────────────────────────────────────────────────────────────────

/** Who changed what, and when — only the fields this screen shows. */
export function PtahCrudHistory({ crud, locale }: RecordsPanelProps) {
    const state = useCrudState(crud);
    const id = usePanel(crud, PANEL_HISTORY);
    const t = useMemo(() => recordsWords(locale), [locale]);
    const [items, setItems] = useState<HistoryEntry[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const enabled = state.schema?.features.history ?? false;

    useEffect(() => {
        if (id === null || !enabled) {
            return;
        }
        let alive = true;
        setItems(null);
        setError(null);
        getHistory(crud, id)
            .then((data) => alive && setItems(data))
            .catch((reason) => alive && setError(failureText(reason, t)));

        return () => {
            alive = false;
        };
    }, [crud, id, enabled, t]);

    const close = useCallback(() => crud.closePanel(), [crud]);

    if (id === null || !enabled) {
        return null;
    }

    return (
        <ForgeModal
            open
            onClose={close}
            title={t.historyTitle}
            size="lg"
            footer={
                <ForgeButton color="dark" flat onClick={close}>
                    {t.close}
                </ForgeButton>
            }
        >
            {error ? (
                <ForgeAlert color="danger">{error}</ForgeAlert>
            ) : items === null ? (
                <Loading t={t} />
            ) : items.length === 0 ? (
                <ForgeEmpty title={t.historyEmpty} />
            ) : (
                <ol style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: 16 }}>
                    {items.map((item, index) => (
                        <li key={index} style={{ border: '1px solid var(--pc-line)', borderRadius: 'var(--pc-radius)', background: 'var(--pc-bg)', padding: 12 }}>
                            <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'baseline', justifyContent: 'space-between', gap: 8 }}>
                                <p style={{ margin: 0, fontSize: 14, fontWeight: 600, color: 'var(--pc-text-strong)' }}>
                                    {t.events[item.event] ?? item.event} <span style={{ ...MUTED, fontWeight: 400 }}>· {item.who}</span>
                                </p>
                                <time style={{ ...MUTED, fontSize: 12, fontVariantNumeric: 'tabular-nums' }}>{item.when}</time>
                            </div>
                            {item.changes.length > 0 && (
                                <dl style={{ margin: '8px 0 0', display: 'grid', gridTemplateColumns: 'minmax(8rem, auto) 1fr', columnGap: 12, rowGap: 4, fontSize: 14 }}>
                                    {item.changes.map((change, i) => (
                                        <Change key={i} change={change} updated={item.event === 'updated'} t={t} />
                                    ))}
                                </dl>
                            )}
                            {item.hidden > 0 && <p style={{ ...MUTED, margin: '4px 0 0', fontSize: 12 }}>{plural(t.hiddenFields, item.hidden)}</p>}
                        </li>
                    ))}
                </ol>
            )}
        </ForgeModal>
    );
}

function Change({ change, updated, t }: { change: HistoryEntry['changes'][number]; updated: boolean; t: RecordsWords }) {
    return (
        <>
            <dt className="pc-label" style={{ margin: 0 }}>
                {change.label}
            </dt>
            <dd style={{ margin: 0, color: 'var(--pc-text)', overflowWrap: 'anywhere' }}>
                {updated && (
                    <>
                        <span style={{ ...MUTED, textDecoration: 'line-through' }}>{change.old}</span> <span aria-hidden="true" style={MUTED}>→</span>
                        <span style={SR_ONLY}>{t.became}</span>{' '}
                    </>
                )}
                <span>{change.new}</span>
            </dd>
        </>
    );
}

// ── Attachments ─────────────────────────────────────────────────────────────

/** The record's files: list and download; upload and remove with the update permission. */
export function PtahCrudAttachments({ crud, locale }: RecordsPanelProps) {
    const state = useCrudState(crud);
    const id = usePanel(crud, PANEL_ATTACHMENTS);
    const t = useMemo(() => recordsWords(locale), [locale]);
    const inputId = useId();
    const features = state.schema?.features;
    const enabled = features?.attachments ?? false;
    const canWrite = enabled && (features?.attachmentsWrite ?? false);

    const [items, setItems] = useState<AttachmentItem[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [files, setFiles] = useState<File[]>([]);
    const [errors, setErrors] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState<AttachmentItem | null>(null);

    useEffect(() => {
        if (id === null || !enabled) {
            return;
        }
        let alive = true;
        setItems(null);
        setError(null);
        setFiles([]);
        setErrors([]);
        setRemoving(null);
        listAttachments(crud, id)
            .then((list) => {
                if (alive) {
                    setItems(list.data);
                    countsOf(crud).set(id, list.count);
                }
            })
            .catch((reason) => alive && setError(failureText(reason, t)));

        return () => {
            alive = false;
        };
    }, [crud, id, enabled, t]);

    const close = useCallback(() => crud.closePanel(), [crud]);

    if (id === null || !enabled) {
        return null;
    }

    const upload = async () => {
        if (files.length === 0 || busy) {
            return;
        }
        setBusy(true);
        setErrors([]);
        try {
            const result = await uploadAttachments(crud, id, files);
            if (!result.ok) {
                setErrors(result.errors);

                return;
            }
            setItems(result.data);
            setFiles([]);
            countsOf(crud).set(id, result.count);
            result.messages.forEach((message) => crud.toast(message.text, message.level));
        } catch (reason) {
            setErrors([failureText(reason, t)]);
        } finally {
            setBusy(false);
        }
    };

    const remove = async (item: AttachmentItem) => {
        setRemoving(null);
        setBusy(true);
        setErrors([]);
        try {
            const result = await deleteAttachment(crud, id, item.id);
            if (result.ok) {
                setItems(result.data);
                countsOf(crud).set(id, result.count);
            } else {
                setErrors(result.errors);
            }
        } catch (reason) {
            setErrors([failureText(reason, t)]);
            // Gone already (another tab, another user): show what is there now.
            listAttachments(crud, id)
                .then((list) => setItems(list.data))
                .catch(() => undefined);
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <ForgeModal
                open={removing === null}
                onClose={close}
                title={t.attachmentsTitle}
                size="lg"
                footer={
                    <ForgeButton color="dark" flat onClick={close}>
                        {t.close}
                    </ForgeButton>
                }
            >
                {error ? (
                    <ForgeAlert color="danger">{error}</ForgeAlert>
                ) : (
                    <div style={{ display: 'grid', gap: 16 }}>
                        {canWrite && (
                            <div style={{ display: 'grid', gap: 8, justifyItems: 'start' }}>
                                <label htmlFor={inputId} className="pc-btn" style={{ cursor: 'pointer', position: 'relative' }}>
                                    📎 {t.pick}
                                    <input
                                        id={inputId}
                                        type="file"
                                        multiple
                                        style={SR_ONLY}
                                        disabled={busy}
                                        onChange={(event) => {
                                            setFiles(Array.from(event.target.files ?? []));
                                            setErrors([]);
                                            event.target.value = '';
                                        }}
                                    />
                                </label>
                                {files.length > 0 && (
                                    <>
                                        <ul style={{ ...MUTED, margin: 0, paddingLeft: 18, fontSize: 12 }}>
                                            {files.map((file, index) => (
                                                <li key={index}>{file.name}</li>
                                            ))}
                                        </ul>
                                        <ForgeButton color="primary" size="sm" loading={busy} onClick={() => void upload()}>
                                            {busy ? t.uploading : plural(t.send, files.length)}
                                        </ForgeButton>
                                    </>
                                )}
                                {errors.map((message, index) => (
                                    <p key={index} role="alert" style={{ margin: 0, fontSize: 12, color: 'var(--pc-danger)' }}>
                                        {message}
                                    </p>
                                ))}
                            </div>
                        )}
                        {!canWrite &&
                            errors.map((message, index) => (
                                <p key={index} role="alert" style={{ margin: 0, fontSize: 12, color: 'var(--pc-danger)' }}>
                                    {message}
                                </p>
                            ))}

                        {items === null ? (
                            <Loading t={t} />
                        ) : items.length === 0 ? (
                            <ForgeEmpty title={t.attachmentsEmpty} />
                        ) : (
                            <ul style={{ listStyle: 'none', margin: 0, padding: 0, border: '1px solid var(--pc-line)', borderRadius: 'var(--pc-radius)' }}>
                                {items.map((item, index) => (
                                    <li
                                        key={item.id}
                                        style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, padding: '8px 12px', borderTop: index === 0 ? undefined : '1px solid var(--pc-line)' }}
                                    >
                                        <div style={{ minWidth: 0 }}>
                                            <a
                                                href={item.url}
                                                download={item.name}
                                                aria-label={t.download.replace(':name', item.name)}
                                                className="pc-link"
                                                style={{ display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', fontSize: 14, fontWeight: 500, color: 'var(--pc-primary)' }}
                                            >
                                                {item.name}
                                            </a>
                                            <p style={{ ...MUTED, margin: 0, fontSize: 12 }}>
                                                {item.size} · {item.when}
                                            </p>
                                        </div>
                                        {canWrite && (
                                            <ForgeButton color="danger" flat size="sm" disabled={busy} aria-label={`${t.delete} ${item.name}`} onClick={() => setRemoving(item)}>
                                                {t.delete}
                                            </ForgeButton>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </ForgeModal>
            <ForgeModal
                open={removing !== null}
                onClose={() => setRemoving(null)}
                title={t.deleteTitle}
                size="sm"
                footer={
                    <>
                        <ForgeButton color="light" onClick={() => setRemoving(null)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton color="danger" onClick={() => removing && void remove(removing)}>
                            {t.delete}
                        </ForgeButton>
                    </>
                }
            >
                <p>{removing ? t.deleteConfirm.replace(':name', removing.name) : ''}</p>
            </ForgeModal>
        </>
    );
}
