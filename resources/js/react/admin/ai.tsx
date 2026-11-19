/**
 * The AI agent module for React — the provider configuration screen and the
 * floating chat — over the admin API. The rules (who may manage providers,
 * the validation, the encrypted key, the rate limit, which conversation is
 * whose, which files a provider takes, the preview cards) are the server's:
 * the Livewire components run behind it.
 */
import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import type { CSSProperties, FormEvent, KeyboardEvent as ReactKeyboardEvent, ReactNode } from 'react';
import { PtahApi } from '../../core';
import {
    acceptFiles,
    AI_GUIDE,
    AI_PROVIDERS,
    AI_STORAGE,
    aiRangeText,
    aiWords,
    blankAiModel,
    cardStatusText,
    chatConversations,
    chatState,
    commandMatches,
    deleteAiModel,
    fill,
    keyOptional,
    listAiModels,
    newConversation,
    nextAiSort,
    openConversation,
    readStore,
    resolveCard,
    saveAiModel,
    sendMessage,
    setDefaultAiModel,
    shortNotes,
    showAiModel,
    streamMessage,
    writeStore,
} from '../../core/admin/ai';
import type { AiCard, AiChatMessage, AiChatState, AiConversationItem, AiListQuery, AiModelForm, AiModelListing, AiOption, AiResult, AiTurn, AiWords } from '../../core/admin/ai';
import { ForgeAlert, ForgeButton, ForgeCheckbox, ForgeEmpty, ForgeInput, ForgeModal, ForgePageHeader, ForgePagination, ForgeSelect, ForgeTextarea, ForgeToastHost } from '../forge';

export interface AiScreenProps {
    api?: PtahApi;
    locale?: string;
}

const failure = (error: unknown) => (error instanceof Error ? error.message : 'Error');

const EditIcon = () => (
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
    </svg>
);

const TrashIcon = () => (
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
    </svg>
);

function Pill({ color, children }: { color: string; children: ReactNode }) {
    return (
        <span className="pf pf-badge" data-color={color}>
            <span className="pf-badge-mark" style={{ position: 'static' }}>
                {children}
            </span>
        </span>
    );
}

// ── Provider configurations ─────────────────────────────────────────────────

const guideCard: CSSProperties = { border: '1px solid var(--pc-line)', borderRadius: 8, padding: 12, background: 'var(--pc-bg)' };

export function PtahAiModels({ api, locale }: AiScreenProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = aiWords(locale);
    const [query, setQuery] = useState<AiListQuery>({ search: '', sort: 'name', direction: 'asc', page: 1 });
    const [search, setSearch] = useState('');
    const [listing, setListing] = useState<AiModelListing | null>(null);
    const [providers, setProviders] = useState<AiOption[]>(AI_PROVIDERS);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);
    // The screen's banners: successMsg and errorMsg of the Livewire component.
    const [banner, setBanner] = useState<{ ok: boolean; text: string } | null>(null);
    const [guide, setGuide] = useState(false);
    const [modal, setModal] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [form, setForm] = useState<AiModelForm>(blankAiModel);
    const [answer, setAnswer] = useState<AiResult | null>(null);
    const [saving, setSaving] = useState(false);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);
    const formId = useId();
    const guideId = useId();

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const next = await listAiModels(client, query);
            setListing(next);
            setProviders(next.providers?.length ? next.providers : AI_PROVIDERS);
            setLoadError(null);
        } catch (error) {
            setLoadError(failure(error));
        } finally {
            setLoading(false);
        }
    }, [client, query]);

    useEffect(() => {
        void load();
    }, [load]);

    // The Livewire screen searches 300 ms after the last key, from page 1.
    useEffect(() => {
        if (search === query.search) {
            return;
        }
        const timer = setTimeout(() => setQuery((current) => ({ ...current, search, page: 1 })), 300);

        return () => clearTimeout(timer);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    const report = (result: AiResult) => {
        if (result.ok) {
            setBanner(result.messages[0] ? { ok: true, text: result.messages[0].text } : null);
        } else if (result.error) {
            setBanner({ ok: false, text: result.error });
        }
    };

    const openCreate = () => {
        setEditingId(null);
        setForm(blankAiModel());
        setAnswer(null);
        setModal(true);
    };

    const openEdit = async (id: number) => {
        try {
            const record = await showAiModel(client, id);
            setEditingId(id);
            setForm(record.form);
            setAnswer(null);
            setModal(true);
        } catch (error) {
            setBanner({ ok: false, text: failure(error) });
        }
    };

    const save = async (event?: FormEvent) => {
        event?.preventDefault();
        setSaving(true);
        try {
            const result = await saveAiModel(client, editingId, form);
            setAnswer(result);
            if (result.ok) {
                setModal(false);
                report(result);
                await load();
            }
        } catch (error) {
            setAnswer({ ok: false, error: failure(error), errors: {}, messages: [] });
        } finally {
            setSaving(false);
        }
    };

    const remove = async () => {
        if (deleteId === null) {
            return;
        }
        setDeleting(true);
        try {
            report(await deleteAiModel(client, deleteId));
            await load();
        } catch (error) {
            setBanner({ ok: false, text: failure(error) });
        } finally {
            setDeleting(false);
            setDeleteId(null);
        }
    };

    const makeDefault = async (id: number) => {
        try {
            report(await setDefaultAiModel(client, id));
            await load();
        } catch (error) {
            setBanner({ ok: false, text: failure(error) });
        }
    };

    const set = (changes: Partial<AiModelForm>) => setForm((current) => ({ ...current, ...changes }));
    const rows = listing?.data ?? [];
    const meta = listing?.meta;
    const errors = answer?.errors ?? {};
    const editing = editingId !== null;
    const sorted = query.sort === 'name';
    const keyHint = editing ? t.apiKeyLeaveBlank : keyOptional(form.provider) ? t.apiKeyOptional : null;

    return (
        <div className="pf pc-root">
            <ForgePageHeader title={t.title} subtitle={t.subtitle} />

            <div style={{ border: '1px solid var(--pc-line)', borderRadius: 8, padding: 16, marginBottom: 16, background: 'var(--pc-primary-soft, var(--pc-bg-soft))' }}>
                <button type="button" className="pc-th-btn" aria-expanded={guide} aria-controls={guideId} onClick={() => setGuide(!guide)} style={{ width: '100%', justifyContent: 'space-between' }}>
                    <span>{t.guideTitle}</span>
                    <span aria-hidden="true">{guide ? '▲' : '▼'}</span>
                </button>
                {guide && (
                    <div id={guideId} style={{ marginTop: 12 }}>
                        <p>{t.guideIntro}</p>
                        <div style={{ display: 'grid', gap: 12, gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))' }}>
                            {AI_GUIDE.map((card) => (
                                <div key={card.name} style={guideCard}>
                                    <p style={{ fontWeight: 600, margin: 0 }}>{card.name}</p>
                                    <p style={{ fontSize: 12, margin: '4px 0 0' }}>{t[card.hint]}</p>
                                    <p style={{ fontSize: 12, margin: '4px 0 0', fontFamily: 'monospace' }}>{card.name.startsWith('Ollama') ? `${card.url} — ${t.apiKeyOptional}` : card.url}</p>
                                </div>
                            ))}
                        </div>
                        <p style={{ fontSize: 12 }}>{t.guideNote}</p>
                    </div>
                )}
            </div>

            {banner && (
                <ForgeAlert key={banner.text} type={banner.ok ? 'success' : 'danger'}>
                    {banner.text}
                </ForgeAlert>
            )}

            <div className="pc-toolbar">
                <ForgeButton size="sm" onClick={openCreate}>
                    + {t.newLabel}
                </ForgeButton>
                <div className="pc-search">
                    <ForgeInput type="search" aria-label={t.search} placeholder={t.searchPlaceholder} value={search} onChange={setSearch} />
                </div>
            </div>

            {loadError && <ForgeAlert type="danger">{loadError}</ForgeAlert>}

            <div className="pc-table-wrap" aria-busy={loading}>
                <table className="pc-table">
                    <thead>
                        <tr>
                            <th aria-sort={sorted ? (query.direction === 'asc' ? 'ascending' : 'descending') : undefined}>
                                <button type="button" className="pc-th-btn" onClick={() => setQuery(nextAiSort(query, 'name'))}>
                                    {t.name}
                                    <span className={`pc-sort${sorted ? ' pc-sort--on' : ''}`} aria-hidden="true">
                                        {sorted && query.direction === 'desc' ? '▼' : '▲'}
                                    </span>
                                </button>
                            </th>
                            <th>{t.provider}</th>
                            <th>{t.model}</th>
                            <th className="pc-align-center">{t.status}</th>
                            <th className="pc-align-center">{t.defaultCol}</th>
                            <th className="pc-align-right">{t.actions}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {listing === null && loading && (
                            <tr>
                                <td className="pc-empty" colSpan={6} role="status">
                                    {t.loading}
                                </td>
                            </tr>
                        )}
                        {listing !== null && rows.length === 0 && (
                            <tr>
                                <td colSpan={6}>
                                    <ForgeEmpty title={t.empty} />
                                </td>
                            </tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id}>
                                <td>
                                    <strong>{row.name}</strong>
                                    {row.notes && <p style={{ margin: 0, fontSize: 12, color: 'var(--pc-text-muted)' }}>{shortNotes(row.notes)}</p>}
                                </td>
                                <td>
                                    <Pill color="light">{row.provider_label}</Pill>
                                </td>
                                <td style={{ fontFamily: 'monospace', fontSize: 12 }}>{row.model}</td>
                                <td className="pc-align-center">
                                    <Pill color={row.is_active ? 'success' : 'light'}>{row.is_active ? t.active : t.inactive}</Pill>
                                </td>
                                <td className="pc-align-center">
                                    {row.is_default ? (
                                        <Pill color="primary">★ {t.isDefault}</Pill>
                                    ) : (
                                        <button type="button" className="pc-link" onClick={() => void makeDefault(row.id)}>
                                            {t.setDefault}
                                        </button>
                                    )}
                                </td>
                                <td className="pc-align-right" style={{ whiteSpace: 'nowrap' }}>
                                    <button type="button" className="pc-icon-btn" title={t.edit} aria-label={t.edit} onClick={() => void openEdit(row.id)}>
                                        <EditIcon />
                                    </button>
                                    <button type="button" className="pc-icon-btn pc-icon-btn--danger" title={t.delete} aria-label={t.delete} onClick={() => setDeleteId(row.id)}>
                                        <TrashIcon />
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {meta && meta.last_page > 1 && (
                <div className="pc-footer">
                    <span>{aiRangeText(t, meta)}</span>
                    <span className="pc-spacer" />
                    <ForgePagination page={meta.page} lastPage={meta.last_page} onChange={(page) => setQuery({ ...query, page })} />
                </div>
            )}

            <ForgeModal
                open={modal}
                onClose={() => setModal(false)}
                title={editing ? t.modalEdit : t.modalNew}
                subtitle={editing ? t.modalEditSubtitle : t.modalNewSubtitle}
                size="2xl"
                footer={
                    <>
                        <ForgeButton color="light" onClick={() => setModal(false)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton type="submit" form={formId} loading={saving}>
                            {saving ? t.saving : editing ? t.saveChanges : t.create}
                        </ForgeButton>
                    </>
                }
            >
                <form id={formId} className="pc-form" onSubmit={(event) => void save(event)} noValidate>
                    {answer && !answer.ok && answer.error && (
                        <div className="pc-form-field--wide">
                            <ForgeAlert type="danger">{answer.error}</ForgeAlert>
                        </div>
                    )}
                    <ForgeInput label={t.name} required value={form.name} error={errors.name} onChange={(name) => set({ name })} />
                    <ForgeSelect label={t.provider} required options={providers} selected={form.provider} error={errors.provider} onChange={(value) => set({ provider: String(value ?? '') })} />
                    <ForgeInput label={t.model} required placeholder="e.g. gpt-4o-mini" value={form.model} error={errors.model} onChange={(model) => set({ model })} />
                    <ForgeInput
                        label={keyHint ? `${t.apiKey} (${keyHint})` : t.apiKey}
                        required={keyHint === null}
                        type="password"
                        autoComplete="new-password"
                        placeholder={editing ? '••••••••' : keyOptional(form.provider) ? t.apiKeyOptional : ''}
                        value={form.api_key}
                        error={errors.api_key}
                        onChange={(api_key) => set({ api_key })}
                    />
                    <ForgeInput label={t.endpoint} type="url" placeholder="https://..." message={t.endpointHint} value={form.api_endpoint} error={errors.api_endpoint} onChange={(api_endpoint) => set({ api_endpoint })} />
                    <ForgeInput label={t.maxTokens} type="number" min={1} max={128000} value={String(form.max_tokens)} error={errors.max_tokens} onChange={(value) => set({ max_tokens: Number(value) })} />
                    <ForgeInput label={t.temperature} type="number" min={0} max={2} step={0.1} value={form.temperature} error={errors.temperature} onChange={(temperature) => set({ temperature })} />
                    <div className="pc-form-field--wide">
                        <ForgeTextarea label={t.systemPrompt} placeholder={t.systemPromptPh} rows={3} helper={t.systemPromptHint} state={errors.system_prompt ? 'danger' : null} value={form.system_prompt} onChange={(system_prompt) => set({ system_prompt })} />
                        {errors.system_prompt && (
                            <p className="pf-field-error" role="alert">
                                {errors.system_prompt}
                            </p>
                        )}
                    </div>
                    <ForgeCheckbox label={t.active} checked={form.is_active} onChange={(is_active) => set({ is_active })} />
                    <ForgeCheckbox label={t.isDefault} checked={form.is_default} onChange={(is_default) => set({ is_default })} />
                    <div className="pc-form-field--wide">
                        <ForgeTextarea label={t.notes} rows={2} state={errors.notes ? 'danger' : null} value={form.notes} onChange={(notes) => set({ notes })} />
                        {errors.notes && (
                            <p className="pf-field-error" role="alert">
                                {errors.notes}
                            </p>
                        )}
                    </div>
                </form>
            </ForgeModal>

            <ForgeModal
                open={deleteId !== null}
                onClose={() => setDeleteId(null)}
                title={t.deleteTitle}
                subtitle={t.deleteMessage}
                size="sm"
                footer={
                    <>
                        <ForgeButton color="light" onClick={() => setDeleteId(null)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton color="danger" loading={deleting} onClick={() => void remove()}>
                            {t.delete}
                        </ForgeButton>
                    </>
                }
            />

            <ForgeToastHost />
        </div>
    );
}

// ── Chat ────────────────────────────────────────────────────────────────────

export interface AiChatProps extends AiScreenProps {
    /** Read the answer as it is written (server-sent events). False: one JSON answer per message. */
    stream?: boolean;
}

/** The look of the widget, on the kit's tokens (no chat classes in ptah-ui.css yet). */
export const chatStyles: Record<string, CSSProperties> = {
    root: { position: 'fixed', right: 24, bottom: 24, zIndex: 50, display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 12 },
    panel: { position: 'relative', width: 384, maxWidth: 'calc(100vw - 32px)', maxHeight: 'min(560px, calc(100vh - 100px))', display: 'flex', flexDirection: 'column', overflow: 'hidden', borderRadius: 16, border: '1px solid var(--pc-line)', background: 'var(--pc-bg)', color: 'var(--pc-text)', boxShadow: 'var(--pc-shadow)' },
    panelExpanded: { position: 'fixed', inset: 0, width: '100%', maxWidth: 'none', maxHeight: 'none', borderRadius: 0 },
    head: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 16px', background: 'var(--pc-primary)', color: '#fff', flexShrink: 0 },
    headBtn: { background: 'transparent', border: 0, color: 'inherit', cursor: 'pointer', padding: 4, borderRadius: 4, fontSize: 16, lineHeight: 1 },
    bar: { display: 'flex', alignItems: 'center', gap: 8, padding: '8px 12px', borderBottom: '1px solid var(--pc-line)', flexShrink: 0 },
    list: { flex: 1, overflowY: 'auto', padding: '12px 16px', display: 'flex', flexDirection: 'column', gap: 12 },
    user: { alignSelf: 'flex-end', maxWidth: '85%', borderRadius: '16px 4px 16px 16px', background: 'var(--pc-primary)', color: '#fff', padding: '8px 12px', fontSize: 14, whiteSpace: 'pre-wrap', overflowWrap: 'anywhere' },
    bot: { alignSelf: 'flex-start', maxWidth: '85%', borderRadius: '4px 16px 16px 16px', background: 'var(--pc-bg-soft)', padding: '8px 12px', fontSize: 14, overflowWrap: 'anywhere' },
    chip: { display: 'inline-flex', alignItems: 'center', gap: 4, borderRadius: 6, border: '1px solid var(--pc-line)', padding: '2px 6px', fontSize: 12, maxWidth: '100%' },
    error: { borderRadius: 8, border: '1px solid var(--pc-danger)', color: 'var(--pc-danger)', padding: '8px 12px', fontSize: 12 },
    foot: { borderTop: '1px solid var(--pc-line)', padding: 12, flexShrink: 0 },
    input: { flex: 1, resize: 'none', minHeight: 38, maxHeight: 128, borderRadius: 12, border: '1px solid var(--pc-line-field, var(--pc-line))', padding: '8px 12px', fontSize: 14, background: 'var(--pc-bg)', color: 'var(--pc-text)' },
    send: { width: 36, height: 36, borderRadius: 12, border: 0, background: 'var(--pc-primary)', color: '#fff', cursor: 'pointer', flexShrink: 0 },
    hint: { margin: '6px 0 0', textAlign: 'center', fontSize: 10, color: 'var(--pc-text-muted)' },
    launcher: { width: 56, height: 56, borderRadius: '50%', border: 0, background: 'var(--pc-primary)', color: '#fff', cursor: 'pointer', boxShadow: 'var(--pc-shadow)', fontSize: 22 },
    dismiss: { position: 'absolute', top: -4, right: -4, width: 20, height: 20, borderRadius: '50%', border: '1px solid var(--pc-line)', background: 'var(--pc-bg)', color: 'var(--pc-text-muted)', cursor: 'pointer', fontSize: 11, lineHeight: 1, padding: 0 },
    handle: { position: 'fixed', right: 0, top: '50%', transform: 'translateY(-50%)', width: 10, height: 64, border: 0, borderRadius: '6px 0 0 6px', background: 'var(--pc-primary)', cursor: 'pointer', zIndex: 50 },
    drop: { position: 'absolute', inset: 8, zIndex: 10, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '2px dashed var(--pc-primary)', borderRadius: 12, background: 'var(--pc-bg)', pointerEvents: 'none', fontSize: 14 },
    card: { marginTop: 8, border: '1px solid var(--pc-line)', borderRadius: 8, padding: 10, fontSize: 12, background: 'var(--pc-bg)' },
};

/** A preview card under an assistant answer: Confirm / Cancel while pending, its status after. */
function PreviewCard({ card, t, busy, onResolve }: { card: AiCard; t: AiWords; busy: boolean; onResolve: (token: string, confirm: boolean) => void }) {
    return (
        <div style={chatStyles.card} data-ptah-confirmation={card.token}>
            <p style={{ margin: 0, fontWeight: 600 }}>{card.summary}</p>
            {card.rows.length > 0 && card.columns.length > 0 && (
                <div style={{ marginTop: 8, maxHeight: 224, overflow: 'auto' }}>
                    <table className="pc-table" style={{ fontSize: 11 }}>
                        <thead>
                            <tr>
                                {card.columns.map((column) => (
                                    <th key={column.key}>{column.label}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {card.rows.map((row, index) => (
                                <tr key={index}>
                                    {row.map((cell, at) => (
                                        <td key={at}>{cell}</td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            {card.total_rows > card.rows.length && <p style={{ margin: '4px 0 0' }}>{fill(t.moreRows, { shown: card.rows.length, total: card.total_rows })}</p>}
            {card.status === 'pending' && card.token !== '' ? (
                <div style={{ marginTop: 8, display: 'flex', gap: 8 }}>
                    <ForgeButton size="sm" disabled={busy} onClick={() => onResolve(card.token, true)}>
                        {t.confirm}
                    </ForgeButton>
                    <ForgeButton size="sm" color="light" disabled={busy} onClick={() => onResolve(card.token, false)}>
                        {t.cardCancel}
                    </ForgeButton>
                </div>
            ) : (
                <p role="status" style={{ margin: '8px 0 0', fontWeight: 600 }}>
                    {cardStatusText(t, card.status === 'pending' ? 'expired' : card.status)}
                </p>
            )}
        </div>
    );
}

/**
 * The floating assistant: a round button in the bottom-right corner that
 * opens the chat panel (full screen on demand). Renders nothing while the
 * server says the chat is unavailable (no active provider).
 */
export function PtahAiChat({ api, locale, stream = true }: AiChatProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = aiWords(locale);
    const [state, setState] = useState<AiChatState | null>(null);
    const [open, setOpen] = useState(() => readStore('session', AI_STORAGE.open) === '1');
    const [expanded, setExpanded] = useState(() => readStore('local', AI_STORAGE.expanded) === '1');
    const [launcherHidden, setLauncherHidden] = useState(() => readStore('local', AI_STORAGE.launcherHidden) === '1');
    // The draft is the browser's alone, as on Livewire (where a server-owned draft got erased mid-typing).
    const [draft, setDraft] = useState(() => readStore('session', AI_STORAGE.draft) ?? '');
    const [messages, setMessages] = useState<AiChatMessage[]>([]);
    const [conversationId, setConversationId] = useState<number | null>(null);
    const [configId, setConfigId] = useState<number | null>(null);
    const [conversations, setConversations] = useState<AiConversationItem[]>([]);
    const [historyLimit, setHistoryLimit] = useState(5);
    const [showHistory, setShowHistory] = useState(false);
    const [loading, setLoading] = useState(false);
    const [streamHtml, setStreamHtml] = useState('');
    const [error, setError] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [dragging, setDragging] = useState(false);
    const [busyCard, setBusyCard] = useState(false);
    const abort = useRef<AbortController | null>(null);
    const listRef = useRef<HTMLDivElement | null>(null);
    const inputRef = useRef<HTMLTextAreaElement | null>(null);
    const fileRef = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        let alive = true;
        chatState(client)
            .then((next) => {
                if (!alive) {
                    return;
                }
                setState(next);
                if (next.available) {
                    setMessages(next.messages);
                    setConversationId(next.conversation_id);
                    setConfigId(next.selected_config_id);
                    setConversations(next.conversations);
                    setHistoryLimit(next.history_limit);
                }
            })
            .catch(() => alive && setState(null));

        return () => {
            alive = false;
            abort.current?.abort();
        };
    }, [client]);

    useEffect(() => writeStore('session', AI_STORAGE.open, open ? '1' : '0'), [open]);
    useEffect(() => writeStore('local', AI_STORAGE.expanded, expanded ? '1' : '0'), [expanded]);
    useEffect(() => writeStore('session', AI_STORAGE.draft, draft === '' ? null : draft), [draft]);
    useEffect(() => writeStore('local', AI_STORAGE.launcherHidden, launcherHidden ? '1' : '0'), [launcherHidden]);

    // The class that reserves room for the button at the end of a listing — only while the button is there.
    const available = state?.available === true;
    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }
        document.body.classList.toggle('ptah-has-ai-launcher', available && !launcherHidden);

        return () => document.body.classList.remove('ptah-has-ai-launcher');
    }, [available, launcherHidden]);

    useEffect(() => {
        if (!open) {
            return;
        }
        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false);
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [open]);

    useEffect(() => {
        const list = listRef.current;
        if (list) {
            list.scrollTop = list.scrollHeight;
        }
    }, [messages, streamHtml, open, loading]);

    useEffect(() => {
        if (open) {
            inputRef.current?.focus();
        }
    }, [open]);

    if (!state || !state.available) {
        return null;
    }

    const rules = state.attachments;
    const attachable = rules.enabled && rules.extensions.length > 0;
    const matches = commandMatches(state.commands, draft);

    const take = (list: FileList | File[] | null | undefined) => {
        const picked = acceptFiles(Array.from(list ?? []), rules, files.length);
        if (picked.length > 0) {
            setFiles((current) => [...current, ...picked]);
            setError('');
        }
    };

    const pickProvider = async (id: number) => {
        setConfigId(id);
        try {
            // The formats a provider takes change with it, as the Livewire picker re-renders the widget.
            const next = await chatState(client, id);
            setState((current) => (current ? { ...current, attachments: next.attachments } : current));
        } catch {
            // The server checks the files again on send.
        }
    };

    const submit = async () => {
        const text = draft.trim();
        if ((text === '' && files.length === 0) || loading) {
            return;
        }
        const sending = files;
        const optimistic: AiChatMessage = { role: 'user', content: text, attachments: sending.map((file) => file.name) };

        setDraft('');
        setMessages((current) => [...current, optimistic]);
        setError('');
        setLoading(true);
        setShowHistory(false);
        setStreamHtml('');
        const controller = new AbortController();
        abort.current = controller;

        try {
            const input = { message: text, conversation_id: conversationId, config_id: configId, files: sending };
            const result: AiTurn = stream && typeof ReadableStream !== 'undefined' ? await streamMessage(client, input, { onDelta: setStreamHtml, signal: controller.signal }) : await sendMessage(client, input);

            if (!result.accepted) {
                // Refused before any provider call: nothing was stored, the text comes back.
                setMessages((current) => current.filter((message) => message !== optimistic));
                setDraft(text);
                setError(result.error || t.chatError);

                return;
            }
            setFiles([]);
            setConversationId(result.conversation_id);
            setMessages(result.messages);
            if (state.history) {
                setConversations(result.conversations);
            }
            if (!result.ok) {
                setError(result.error || t.chatError);
            }
        } catch (caught) {
            if (!controller.signal.aborted) {
                setError(failure(caught));
            }
        } finally {
            if (abort.current === controller) {
                abort.current = null;
            }
            setLoading(false);
            setStreamHtml('');
        }
    };

    const toggleHistory = async () => {
        const next = !showHistory;
        setShowHistory(next);
        setHistoryLimit(5);
        if (next) {
            try {
                const list = await chatConversations(client, 5);
                setConversations(list.conversations);
                setHistoryLimit(list.limit);
            } catch (caught) {
                setError(failure(caught));
            }
        }
    };

    const loadMore = async () => {
        try {
            const list = await chatConversations(client, historyLimit + 5);
            setConversations(list.conversations);
            setHistoryLimit(list.limit);
        } catch (caught) {
            setError(failure(caught));
        }
    };

    const pickConversation = async (id: number) => {
        try {
            const found = await openConversation(client, id);
            setConversationId(found.conversation_id);
            setMessages(found.messages);
            setError('');
            setShowHistory(false);
        } catch {
            // As on Livewire: a conversation that cannot be opened leaves the panel as it was.
        }
    };

    const startNew = async () => {
        abort.current?.abort();
        try {
            const fresh = await newConversation(client);
            setConversationId(fresh.conversation_id);
        } catch (caught) {
            setError(failure(caught));

            return;
        }
        setMessages([]);
        setFiles([]);
        setError('');
        setShowHistory(false);
        setDraft('');
    };

    const resolve = async (token: string, confirm: boolean) => {
        if (conversationId === null || loading) {
            return;
        }
        setBusyCard(true);
        try {
            const outcome = await resolveCard(client, conversationId, token, confirm);
            if (outcome.ok) {
                setMessages(outcome.messages);
                setError('');
            } else {
                setError(outcome.error || t.chatError);
            }
        } catch (caught) {
            setError(failure(caught));
        } finally {
            setBusyCard(false);
        }
    };

    const onKeyDown = (event: ReactKeyboardEvent<HTMLTextAreaElement>) => {
        // Shift+Enter is a new line where the cursor is; Enter alone sends.
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            void submit();
        }
    };

    const panelStyle = expanded ? { ...chatStyles.panel, ...chatStyles.panelExpanded } : chatStyles.panel;
    const options = state.providers;

    return (
        <div className="pf" style={chatStyles.root}>
            {open && (
                <div
                    role="dialog"
                    aria-modal="false"
                    aria-label={t.chatTitle}
                    className="pl-ai-panel ptah-c-chat_panel"
                    style={panelStyle}
                    onPaste={(event) => {
                        // A pasted screenshot arrives as a file; text pastes go on as usual.
                        if (attachable && event.clipboardData.files.length > 0) {
                            event.preventDefault();
                            take(event.clipboardData.files);
                        }
                    }}
                    onDragOver={(event) => {
                        if (attachable) {
                            event.preventDefault();
                            setDragging(true);
                        }
                    }}
                    onDragLeave={(event) => event.target === event.currentTarget && setDragging(false)}
                    onDrop={(event) => {
                        if (attachable) {
                            event.preventDefault();
                            setDragging(false);
                            take(event.dataTransfer.files);
                        }
                    }}
                >
                    {dragging && <div style={chatStyles.drop}>{t.attachDrop}</div>}

                    <div style={chatStyles.head} onDoubleClick={() => setExpanded(!expanded)}>
                        <strong style={{ fontSize: 14 }}>{t.chatTitle}</strong>
                        <div style={{ display: 'flex', gap: 4 }}>
                            {state.history && (
                                <button type="button" style={chatStyles.headBtn} title={t.chatHistory} aria-label={t.chatHistory} aria-pressed={showHistory} onClick={() => void toggleHistory()}>
                                    ⟲
                                </button>
                            )}
                            <button type="button" style={chatStyles.headBtn} title={t.chatNew} aria-label={t.chatNew} onClick={() => void startNew()}>
                                ✎
                            </button>
                            <button type="button" style={chatStyles.headBtn} title={expanded ? t.chatCollapse : t.chatExpand} aria-label={expanded ? t.chatCollapse : t.chatExpand} onClick={() => setExpanded(!expanded)}>
                                {expanded ? '⤡' : '⤢'}
                            </button>
                            <button type="button" style={chatStyles.headBtn} aria-label={t.chatClose} title={t.chatClose} onClick={() => setOpen(false)}>
                                ✕
                            </button>
                        </div>
                    </div>

                    {!showHistory && options.length > 1 && (
                        <div style={chatStyles.bar}>
                            <select className="pc-input" aria-label={t.chatProvider} value={configId ?? ''} onChange={(event) => void pickProvider(Number(event.target.value))} style={{ flex: 1, minWidth: 0, fontSize: 12 }}>
                                {options.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name} — {option.model}
                                        {option.is_default ? ` (${t.chatProviderDefault})` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {showHistory ? (
                        <div style={{ ...chatStyles.list, gap: 2 }}>
                            <p style={{ margin: 0, fontSize: 12, textTransform: 'uppercase', color: 'var(--pc-text-muted)' }}>{t.chatHistory}</p>
                            {conversations.length === 0 ? (
                                <ForgeEmpty title={t.chatNoHistory} />
                            ) : (
                                <>
                                    {conversations.map((item) => (
                                        <button
                                            key={item.id}
                                            type="button"
                                            className="pc-menu-item"
                                            aria-current={item.id === conversationId ? 'true' : undefined}
                                            onClick={() => void pickConversation(item.id)}
                                            style={{ textAlign: 'left', width: '100%', padding: '8px 12px', border: 0, borderRadius: 8, background: item.id === conversationId ? 'var(--pc-primary-soft, var(--pc-bg-soft))' : 'transparent', cursor: 'pointer', color: 'inherit' }}
                                        >
                                            <span style={{ display: 'block', fontWeight: 500, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{item.title}</span>
                                            <span style={{ display: 'block', fontSize: 12, color: 'var(--pc-text-muted)' }}>{item.date}</span>
                                        </button>
                                    ))}
                                    {conversations.length >= historyLimit && (
                                        <button type="button" className="pc-link" onClick={() => void loadMore()}>
                                            {t.chatLoadMore}
                                        </button>
                                    )}
                                </>
                            )}
                        </div>
                    ) : (
                        <>
                            <div ref={listRef} style={chatStyles.list}>
                                {messages.length === 0 && !loading && <ForgeEmpty title={t.chatEmpty} />}
                                {messages.map((message, index) =>
                                    message.role === 'user' ? (
                                        <div key={index} style={chatStyles.user}>
                                            {(message.attachments ?? []).length > 0 && (
                                                <span style={{ display: 'flex', flexWrap: 'wrap', gap: 4, marginBottom: 4 }}>
                                                    {(message.attachments ?? []).map((name) => (
                                                        <span key={name} style={{ ...chatStyles.chip, borderColor: 'rgba(255,255,255,.4)' }}>
                                                            📎 {name}
                                                        </span>
                                                    ))}
                                                </span>
                                            )}
                                            {message.content}
                                        </div>
                                    ) : (
                                        <div key={index} style={chatStyles.bot}>
                                            {/* Rendered and sanitised on the server (ChatMarkdown: HTML escaped, unsafe links refused). */}
                                            <div className="ptah-chat-md" dangerouslySetInnerHTML={{ __html: message.html ?? '' }} />
                                            {(message.confirmations ?? []).map((card, at) => (
                                                <PreviewCard key={card.token || at} card={card} t={t} busy={busyCard} onResolve={(token, confirm) => void resolve(token, confirm)} />
                                            ))}
                                        </div>
                                    ),
                                )}
                                {loading && (
                                    <div style={chatStyles.bot} role="status" aria-label={t.chatThinking}>
                                        {streamHtml !== '' ? <div className="ptah-chat-md" dangerouslySetInnerHTML={{ __html: streamHtml }} /> : <span aria-hidden="true">• • •</span>}
                                    </div>
                                )}
                                {error !== '' && (
                                    <div role="alert" style={chatStyles.error}>
                                        {error}
                                    </div>
                                )}
                            </div>

                            <div style={chatStyles.foot}>
                                {attachable && files.length > 0 && (
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBottom: 8 }}>
                                        {files.map((file, index) => (
                                            <span key={`${file.name}-${index}`} style={chatStyles.chip}>
                                                {file.type.startsWith('image/') ? '🖼' : '📄'} {file.name}
                                                <button
                                                    type="button"
                                                    className="pf-x"
                                                    title={fill(t.attachRemove, { name: file.name })}
                                                    aria-label={fill(t.attachRemove, { name: file.name })}
                                                    onClick={() => setFiles((current) => current.filter((_, at) => at !== index))}
                                                >
                                                    ✕
                                                </button>
                                            </span>
                                        ))}
                                    </div>
                                )}

                                {matches.length > 0 && (
                                    <div role="listbox" aria-label={t.commands} style={{ marginBottom: 8, border: '1px solid var(--pc-line)', borderRadius: 8, overflow: 'hidden', fontSize: 12 }}>
                                        {matches.map((command) => (
                                            <button
                                                key={command.name}
                                                type="button"
                                                role="option"
                                                aria-selected="false"
                                                onClick={() => {
                                                    setDraft(`/${command.name}`);
                                                    inputRef.current?.focus();
                                                }}
                                                style={{ display: 'flex', gap: 8, width: '100%', padding: '6px 12px', border: 0, background: 'transparent', textAlign: 'left', cursor: 'pointer', color: 'inherit' }}
                                            >
                                                <strong style={{ fontFamily: 'monospace' }}>/{command.name}</strong>
                                                <span style={{ color: 'var(--pc-text-muted)' }}>{command.description}</span>
                                            </button>
                                        ))}
                                    </div>
                                )}

                                <div style={{ display: 'flex', alignItems: 'flex-end', gap: 8 }}>
                                    {attachable && (
                                        <>
                                            <input
                                                ref={fileRef}
                                                type="file"
                                                multiple
                                                hidden
                                                accept={rules.accept}
                                                aria-hidden="true"
                                                tabIndex={-1}
                                                data-testid="ptah-ai-file"
                                                onChange={(event) => {
                                                    take(event.target.files);
                                                    event.target.value = '';
                                                }}
                                            />
                                            <button type="button" style={{ ...chatStyles.send, background: 'var(--pc-bg-soft)', color: 'var(--pc-text)' }} title={t.attach} aria-label={t.attach} disabled={files.length >= rules.max_files} onClick={() => fileRef.current?.click()}>
                                                📎
                                            </button>
                                        </>
                                    )}
                                    {/* Not disabled while the answer comes: typing the next question meanwhile is what people do. */}
                                    <textarea ref={inputRef} rows={1} value={draft} placeholder={t.chatPlaceholder} aria-label={t.chatPlaceholder} style={chatStyles.input} onChange={(event) => setDraft(event.target.value)} onKeyDown={onKeyDown} />
                                    <button type="button" style={chatStyles.send} title={t.chatSend} aria-label={t.chatSend} disabled={(draft.trim() === '' && files.length === 0) || loading} onClick={() => void submit()}>
                                        ➤
                                    </button>
                                </div>
                                <p style={chatStyles.hint}>
                                    {t.chatKeyboard}
                                    {attachable ? ` · ${t.attachHint}` : ''}
                                </p>
                                {rules.enabled && rules.blocked.length > 0 && <p style={chatStyles.hint}>{fill(t.attachBlocked, { formats: rules.blocked.join(', ') })}</p>}
                            </div>
                        </>
                    )}
                </div>
            )}

            {!launcherHidden && !(expanded && open) && (
                // The round button of ai-chat-widget.blade.php (w-14, bg-primary, bx-bot / bx-x), and the
                // "hide" corner that shows on hover or keyboard focus.
                <div className="pl-ai-corner">
                    <button type="button" className={`pl-ai-launcher${open ? ' pl-ai-launcher--open' : ''}`} title={open ? t.chatClose : t.chatOpen} aria-label={open ? t.chatClose : t.chatOpen} aria-expanded={open} onClick={() => setOpen(!open)}>
                        <i className={open ? 'bx bx-x' : 'bx bx-bot'} aria-hidden="true" />
                    </button>
                    {!open && (
                        <button type="button" className="pl-ai-dismiss ptah-c-chat_dismiss" title={t.chatHide} aria-label={t.chatHide} onClick={() => setLauncherHidden(true)}>
                            <i className="bx bx-x" aria-hidden="true" />
                        </button>
                    )}
                </div>
            )}

            {launcherHidden && (
                <button
                    type="button"
                    className="pl-ai-handle ptah-c-chat_handle"
                    title={t.chatShow}
                    aria-label={t.chatShow}
                    onClick={() => {
                        setLauncherHidden(false);
                        setOpen(true);
                    }}
                />
            )}
        </div>
    );
}
