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
import { modalButton } from '../../core/crud/form';
import { Icon } from '../icons';
import { PtahConfirmDialog, PtahCrudModal, PtahModalAlert, PtahModalEmpty } from './modal';

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
    <div className="pc-fm-loading" role="status" aria-live="polite">
        <span className="pc-fm-spin" aria-hidden="true" /> {t.loading}
    </div>
);

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
                <button type="button" className={modalButton('flat')} onClick={() => crud.openPanel(PANEL_ATTACHMENTS, row.id)}>
                    {t.attachments}
                    {count !== null && count >= 0 ? ` (${count})` : ''}
                </button>
            )}
            {features.history && (
                <button type="button" className={modalButton('flat')} onClick={() => crud.openPanel(PANEL_HISTORY, row.id)}>
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
        <PtahCrudModal
            onClose={close}
            closeLabel={t.close}
            title={t.historyTitle}
            size="lg"
            footer={
                <button type="button" className={modalButton('flat')} onClick={close}>
                    {t.close}
                </button>
            }
        >
            {error ? (
                <PtahModalAlert>{error}</PtahModalAlert>
            ) : items === null ? (
                <Loading t={t} />
            ) : items.length === 0 ? (
                <PtahModalEmpty title={t.historyEmpty} />
            ) : (
                <ol className="pc-hist">
                    {items.map((item, index) => (
                        <li key={index} className="pc-hist-item">
                            <div className="pc-hist-top">
                                <p className="pc-hist-event">
                                    {t.events[item.event] ?? item.event} <span className="pc-hist-who">· {item.who}</span>
                                </p>
                                <time className="pc-hist-when">{item.when}</time>
                            </div>
                            {item.changes.length > 0 && (
                                <dl className="pc-hist-changes">
                                    {item.changes.map((change, i) => (
                                        <Change key={i} change={change} updated={item.event === 'updated'} t={t} />
                                    ))}
                                </dl>
                            )}
                            {item.hidden > 0 && <p className="pc-hist-hidden">{plural(t.hiddenFields, item.hidden)}</p>}
                        </li>
                    ))}
                </ol>
            )}
        </PtahCrudModal>
    );
}

function Change({ change, updated, t }: { change: HistoryEntry['changes'][number]; updated: boolean; t: RecordsWords }) {
    return (
        <>
            <dt>{change.label}</dt>
            <dd>
                {updated && (
                    <>
                        <span className="pc-hist-old" style={{ textDecoration: 'line-through' }}>
                            {change.old}
                        </span>{' '}
                        <span aria-hidden="true" className="pc-fm-muted">
                            →
                        </span>
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
            {removing === null && (
                <PtahCrudModal
                    onClose={close}
                    closeLabel={t.close}
                    title={t.attachmentsTitle}
                    size="lg"
                    footer={
                        <button type="button" className={modalButton('flat')} onClick={close}>
                            {t.close}
                        </button>
                    }
                >
                    {error ? (
                        <PtahModalAlert>{error}</PtahModalAlert>
                    ) : (
                        <div className="pc-att">
                            {canWrite && (
                                <div className="pc-att-up">
                                    <label htmlFor={inputId} className="pc-fm-pick">
                                        <Icon name="paperclip" />
                                        {t.pick}
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
                                            <ul className="pc-att-files">
                                                {files.map((file, index) => (
                                                    <li key={index}>{file.name}</li>
                                                ))}
                                            </ul>
                                            <button type="button" className={modalButton('primary', true)} disabled={busy} aria-busy={busy || undefined} onClick={() => void upload()}>
                                                {busy && <span className="pc-fm-spin" aria-hidden="true" />}
                                                {busy ? t.uploading : plural(t.send, files.length)}
                                            </button>
                                        </>
                                    )}
                                    {errors.map((message, index) => (
                                        <p key={index} role="alert" className="pc-fm-err">
                                            {message}
                                        </p>
                                    ))}
                                </div>
                            )}
                            {!canWrite &&
                                errors.map((message, index) => (
                                    <p key={index} role="alert" className="pc-fm-err">
                                        {message}
                                    </p>
                                ))}

                            {items === null ? (
                                <Loading t={t} />
                            ) : items.length === 0 ? (
                                <PtahModalEmpty title={t.attachmentsEmpty} />
                            ) : (
                                <ul className="pc-att-list">
                                    {items.map((item) => (
                                        <li key={item.id} className="pc-att-row">
                                            <div className="pc-att-info">
                                                <a
                                                    href={item.url}
                                                    download={item.name}
                                                    aria-label={t.download.replace(':name', item.name)}
                                                    className="pc-att-name"
                                                >
                                                    {item.name}
                                                </a>
                                                <p className="pc-att-meta">
                                                    {item.size} · {item.when}
                                                </p>
                                            </div>
                                            {canWrite && (
                                                <button type="button" className={modalButton('flat-danger', true)} disabled={busy} aria-label={`${t.delete} ${item.name}`} onClick={() => setRemoving(item)}>
                                                    {t.delete}
                                                </button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                </PtahCrudModal>
            )}
            {removing !== null && (
                <PtahConfirmDialog
                    title={t.deleteTitle}
                    text={t.deleteConfirm.replace(':name', removing.name)}
                    confirm={t.delete}
                    cancel={t.cancel}
                    closeLabel={t.close}
                    danger
                    onCancel={() => setRemoving(null)}
                    onConfirm={() => void remove(removing)}
                />
            )}
        </>
    );
}
