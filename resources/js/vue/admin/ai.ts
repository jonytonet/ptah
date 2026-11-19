/**
 * The AI agent module for Vue — the provider configuration screen and the
 * floating chat — over the admin API. The rules (who may manage providers,
 * the validation, the encrypted key, the rate limit, which conversation is
 * whose, which files a provider takes, the preview cards) are the server's:
 * the Livewire components run behind it. Mirrors react/admin/ai.tsx.
 */
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { PropType, VNodeChild } from 'vue';
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

const screenProps = {
    api: { type: Object as PropType<PtahApi>, default: undefined },
    locale: { type: String, default: undefined },
};

const failure = (error: unknown) => (error instanceof Error ? error.message : 'Error');

let forms = 0;

const icon = (d: string) =>
    h('svg', { width: 16, height: 16, fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor', 'aria-hidden': 'true' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d })]);

const EDIT_ICON = 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z';
const TRASH_ICON = 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16';

const pill = (shade: string, text: VNodeChild) => h('span', { class: 'pf pf-badge', 'data-color': shade }, [h('span', { class: 'pf-badge-mark', style: { position: 'static' } }, [text])]);

// ── Provider configurations ─────────────────────────────────────────────────

const guideCard = { border: '1px solid var(--pc-line)', borderRadius: '8px', padding: '12px', background: 'var(--pc-bg)' };

export const PtahAiModels = defineComponent({
    name: 'PtahAiModels',
    props: screenProps,
    setup(props) {
        const client = props.api ?? new PtahApi();
        const t = computed(() => aiWords(props.locale));
        const query = ref<AiListQuery>({ search: '', sort: 'name', direction: 'asc', page: 1 });
        const search = ref('');
        const listing = ref<AiModelListing | null>(null);
        const providers = ref<AiOption[]>(AI_PROVIDERS);
        const loading = ref(true);
        const loadError = ref<string | null>(null);
        // The screen's banners: successMsg and errorMsg of the Livewire component.
        const banner = ref<{ ok: boolean; text: string } | null>(null);
        const guide = ref(false);
        const modal = ref(false);
        const editingId = ref<number | null>(null);
        const form = ref<AiModelForm>(blankAiModel());
        const answer = ref<AiResult | null>(null);
        const saving = ref(false);
        const deleteId = ref<number | null>(null);
        const deleting = ref(false);
        const formId = `pa-ai-form-${++forms}`;
        const guideId = `pa-ai-guide-${forms}`;

        const load = async () => {
            loading.value = true;
            try {
                const next = await listAiModels(client, query.value);
                listing.value = next;
                providers.value = next.providers?.length ? next.providers : AI_PROVIDERS;
                loadError.value = null;
            } catch (error) {
                loadError.value = failure(error);
            } finally {
                loading.value = false;
            }
        };

        onMounted(() => void load());
        watch(query, () => void load());

        // The Livewire screen searches 300 ms after the last key, from page 1.
        let timer: ReturnType<typeof setTimeout> | undefined;
        watch(search, (value) => {
            clearTimeout(timer);
            if (value !== query.value.search) {
                timer = setTimeout(() => (query.value = { ...query.value, search: value, page: 1 }), 300);
            }
        });
        onBeforeUnmount(() => clearTimeout(timer));

        const report = (result: AiResult) => {
            if (result.ok) {
                banner.value = result.messages[0] ? { ok: true, text: result.messages[0].text } : null;
            } else if (result.error) {
                banner.value = { ok: false, text: result.error };
            }
        };

        const openCreate = () => {
            editingId.value = null;
            form.value = blankAiModel();
            answer.value = null;
            modal.value = true;
        };

        const openEdit = async (id: number) => {
            try {
                const record = await showAiModel(client, id);
                editingId.value = id;
                form.value = record.form;
                answer.value = null;
                modal.value = true;
            } catch (error) {
                banner.value = { ok: false, text: failure(error) };
            }
        };

        const save = async () => {
            saving.value = true;
            try {
                const result = await saveAiModel(client, editingId.value, form.value);
                answer.value = result;
                if (result.ok) {
                    modal.value = false;
                    report(result);
                    await load();
                }
            } catch (error) {
                answer.value = { ok: false, error: failure(error), errors: {}, messages: [] };
            } finally {
                saving.value = false;
            }
        };

        const remove = async () => {
            if (deleteId.value === null) {
                return;
            }
            deleting.value = true;
            try {
                report(await deleteAiModel(client, deleteId.value));
                await load();
            } catch (error) {
                banner.value = { ok: false, text: failure(error) };
            } finally {
                deleting.value = false;
                deleteId.value = null;
            }
        };

        const makeDefault = async (id: number) => {
            try {
                report(await setDefaultAiModel(client, id));
                await load();
            } catch (error) {
                banner.value = { ok: false, text: failure(error) };
            }
        };

        const set = (changes: Partial<AiModelForm>) => (form.value = { ...form.value, ...changes });

        return () => {
            const w = t.value;
            const rows = listing.value?.data ?? [];
            const meta = listing.value?.meta;
            const errors = answer.value?.errors ?? {};
            const editing = editingId.value !== null;
            const sorted = query.value.sort === 'name';
            const f = form.value;
            const keyHint = editing ? w.apiKeyLeaveBlank : keyOptional(f.provider) ? w.apiKeyOptional : null;

            const body: VNodeChild[] = [];
            if (listing.value === null && loading.value) {
                body.push(h('tr', [h('td', { class: 'pc-empty', colspan: 6, role: 'status' }, w.loading)]));
            }
            if (listing.value !== null && rows.length === 0) {
                body.push(h('tr', [h('td', { colspan: 6 }, [h(ForgeEmpty, { title: w.empty })])]));
            }
            for (const row of rows) {
                body.push(
                    h('tr', { key: row.id }, [
                        h('td', [h('strong', row.name), row.notes ? h('p', { style: { margin: 0, fontSize: '12px', color: 'var(--pc-text-muted)' } }, shortNotes(row.notes)) : null]),
                        h('td', [pill('light', row.provider_label)]),
                        h('td', { style: { fontFamily: 'monospace', fontSize: '12px' } }, row.model),
                        h('td', { class: 'pc-align-center' }, [pill(row.is_active ? 'success' : 'light', row.is_active ? w.active : w.inactive)]),
                        h('td', { class: 'pc-align-center' }, [row.is_default ? pill('primary', `★ ${w.isDefault}`) : h('button', { type: 'button', class: 'pc-link', onClick: () => void makeDefault(row.id) }, w.setDefault)]),
                        h('td', { class: 'pc-align-right', style: { whiteSpace: 'nowrap' } }, [
                            h('button', { type: 'button', class: 'pc-icon-btn', title: w.edit, 'aria-label': w.edit, onClick: () => void openEdit(row.id) }, [icon(EDIT_ICON)]),
                            h('button', { type: 'button', class: 'pc-icon-btn pc-icon-btn--danger', title: w.delete, 'aria-label': w.delete, onClick: () => (deleteId.value = row.id) }, [icon(TRASH_ICON)]),
                        ]),
                    ]),
                );
            }

            return h('div', { class: 'pf pc-root' }, [
                h(ForgePageHeader, { title: w.title, subtitle: w.subtitle }),

                h('div', { style: { border: '1px solid var(--pc-line)', borderRadius: '8px', padding: '16px', marginBottom: '16px', background: 'var(--pc-primary-soft, var(--pc-bg-soft))' } }, [
                    h('button', { type: 'button', class: 'pc-th-btn', 'aria-expanded': guide.value ? 'true' : 'false', 'aria-controls': guideId, style: { width: '100%', justifyContent: 'space-between' }, onClick: () => (guide.value = !guide.value) }, [
                        h('span', w.guideTitle),
                        h('span', { 'aria-hidden': 'true' }, guide.value ? '▲' : '▼'),
                    ]),
                    guide.value
                        ? h('div', { id: guideId, style: { marginTop: '12px' } }, [
                              h('p', w.guideIntro),
                              h(
                                  'div',
                                  { style: { display: 'grid', gap: '12px', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))' } },
                                  AI_GUIDE.map((card) =>
                                      h('div', { key: card.name, style: guideCard }, [
                                          h('p', { style: { fontWeight: 600, margin: 0 } }, card.name),
                                          h('p', { style: { fontSize: '12px', margin: '4px 0 0' } }, w[card.hint]),
                                          h('p', { style: { fontSize: '12px', margin: '4px 0 0', fontFamily: 'monospace' } }, card.name.startsWith('Ollama') ? `${card.url} — ${w.apiKeyOptional}` : card.url),
                                      ]),
                                  ),
                              ),
                              h('p', { style: { fontSize: '12px' } }, w.guideNote),
                          ])
                        : null,
                ]),

                banner.value ? h(ForgeAlert, { key: banner.value.text, type: banner.value.ok ? 'success' : 'danger' }, () => banner.value?.text) : null,

                h('div', { class: 'pc-toolbar' }, [
                    h(ForgeButton, { size: 'sm', onClick: openCreate }, () => `+ ${w.newLabel}`),
                    h('div', { class: 'pc-search' }, [
                        h(ForgeInput, { type: 'search', 'aria-label': w.search, placeholder: w.searchPlaceholder, modelValue: search.value, 'onUpdate:modelValue': (value: string) => (search.value = value) }),
                    ]),
                ]),

                loadError.value ? h(ForgeAlert, { type: 'danger' }, () => loadError.value) : null,

                h('div', { class: 'pc-table-wrap', 'aria-busy': loading.value }, [
                    h('table', { class: 'pc-table' }, [
                        h('thead', [
                            h('tr', [
                                h('th', { 'aria-sort': sorted ? (query.value.direction === 'asc' ? 'ascending' : 'descending') : undefined }, [
                                    h('button', { type: 'button', class: 'pc-th-btn', onClick: () => (query.value = nextAiSort(query.value, 'name')) }, [
                                        w.name,
                                        h('span', { class: `pc-sort${sorted ? ' pc-sort--on' : ''}`, 'aria-hidden': 'true' }, sorted && query.value.direction === 'desc' ? '▼' : '▲'),
                                    ]),
                                ]),
                                h('th', w.provider),
                                h('th', w.model),
                                h('th', { class: 'pc-align-center' }, w.status),
                                h('th', { class: 'pc-align-center' }, w.defaultCol),
                                h('th', { class: 'pc-align-right' }, w.actions),
                            ]),
                        ]),
                        h('tbody', body),
                    ]),
                ]),

                meta && meta.last_page > 1
                    ? h('div', { class: 'pc-footer' }, [
                          h('span', aiRangeText(w, meta)),
                          h('span', { class: 'pc-spacer' }),
                          h(ForgePagination, { page: meta.page, lastPage: meta.last_page, onChange: (page: number) => (query.value = { ...query.value, page }) }),
                      ])
                    : null,

                h(
                    ForgeModal,
                    { modelValue: modal.value, 'onUpdate:modelValue': (open: boolean) => (modal.value = open), title: editing ? w.modalEdit : w.modalNew, subtitle: editing ? w.modalEditSubtitle : w.modalNewSubtitle, size: '2xl' },
                    {
                        default: () =>
                            h(
                                'form',
                                {
                                    id: formId,
                                    class: 'pc-form',
                                    novalidate: true,
                                    onSubmit: (event: Event) => {
                                        event.preventDefault();
                                        void save();
                                    },
                                },
                                [
                                    answer.value && !answer.value.ok && answer.value.error ? h('div', { class: 'pc-form-field--wide' }, [h(ForgeAlert, { type: 'danger' }, () => answer.value?.error)]) : null,
                                    h(ForgeInput, { label: w.name, required: true, modelValue: f.name, error: errors.name, 'onUpdate:modelValue': (name: string) => set({ name }) }),
                                    h(ForgeSelect, { label: w.provider, required: true, options: providers.value, modelValue: f.provider, error: errors.provider, 'onUpdate:modelValue': (value: string | number | null) => set({ provider: String(value ?? '') }) }),
                                    h(ForgeInput, { label: w.model, required: true, placeholder: 'e.g. gpt-4o-mini', modelValue: f.model, error: errors.model, 'onUpdate:modelValue': (model: string) => set({ model }) }),
                                    h(ForgeInput, {
                                        label: keyHint ? `${w.apiKey} (${keyHint})` : w.apiKey,
                                        required: keyHint === null,
                                        type: 'password',
                                        autocomplete: 'new-password',
                                        placeholder: editing ? '••••••••' : keyOptional(f.provider) ? w.apiKeyOptional : '',
                                        modelValue: f.api_key,
                                        error: errors.api_key,
                                        'onUpdate:modelValue': (api_key: string) => set({ api_key }),
                                    }),
                                    h(ForgeInput, { label: w.endpoint, type: 'url', placeholder: 'https://...', message: w.endpointHint, modelValue: f.api_endpoint, error: errors.api_endpoint, 'onUpdate:modelValue': (api_endpoint: string) => set({ api_endpoint }) }),
                                    h(ForgeInput, { label: w.maxTokens, type: 'number', min: 1, max: 128000, modelValue: String(f.max_tokens), error: errors.max_tokens, 'onUpdate:modelValue': (value: string) => set({ max_tokens: Number(value) }) }),
                                    h(ForgeInput, { label: w.temperature, type: 'number', min: 0, max: 2, step: 0.1, modelValue: f.temperature, error: errors.temperature, 'onUpdate:modelValue': (temperature: string) => set({ temperature }) }),
                                    h('div', { class: 'pc-form-field--wide' }, [
                                        h(ForgeTextarea, { label: w.systemPrompt, placeholder: w.systemPromptPh, rows: 3, helper: w.systemPromptHint, state: errors.system_prompt ? 'danger' : undefined, modelValue: f.system_prompt, 'onUpdate:modelValue': (system_prompt: string) => set({ system_prompt }) }),
                                        errors.system_prompt ? h('p', { class: 'pf-field-error', role: 'alert' }, errors.system_prompt) : null,
                                    ]),
                                    h(ForgeCheckbox, { label: w.active, modelValue: f.is_active, 'onUpdate:modelValue': (is_active: boolean) => set({ is_active }) }),
                                    h(ForgeCheckbox, { label: w.isDefault, modelValue: f.is_default, 'onUpdate:modelValue': (is_default: boolean) => set({ is_default }) }),
                                    h('div', { class: 'pc-form-field--wide' }, [
                                        h(ForgeTextarea, { label: w.notes, rows: 2, state: errors.notes ? 'danger' : undefined, modelValue: f.notes, 'onUpdate:modelValue': (notes: string) => set({ notes }) }),
                                        errors.notes ? h('p', { class: 'pf-field-error', role: 'alert' }, errors.notes) : null,
                                    ]),
                                ],
                            ),
                        footer: () => [
                            h(ForgeButton, { color: 'light', onClick: () => (modal.value = false) }, () => w.cancel),
                            h(ForgeButton, { type: 'submit', form: formId, loading: saving.value }, () => (saving.value ? w.saving : editing ? w.saveChanges : w.create)),
                        ],
                    },
                ),

                h(
                    ForgeModal,
                    { modelValue: deleteId.value !== null, 'onUpdate:modelValue': (open: boolean) => !open && (deleteId.value = null), title: w.deleteTitle, subtitle: w.deleteMessage, size: 'sm' },
                    {
                        footer: () => [
                            h(ForgeButton, { color: 'light', onClick: () => (deleteId.value = null) }, () => w.cancel),
                            h(ForgeButton, { color: 'danger', loading: deleting.value, onClick: () => void remove() }, () => w.delete),
                        ],
                    },
                ),

                h(ForgeToastHost),
            ]);
        };
    },
});

// ── Chat ────────────────────────────────────────────────────────────────────

type Style = Record<string, string | number>;

/** The look of the widget, on the kit's tokens (no chat classes in ptah-ui.css yet). */
export const chatStyles: Record<string, Style> = {
    root: { position: 'fixed', right: '24px', bottom: '24px', zIndex: 50, display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '12px' },
    panel: { position: 'relative', width: '384px', maxWidth: 'calc(100vw - 32px)', maxHeight: 'min(560px, calc(100vh - 100px))', display: 'flex', flexDirection: 'column', overflow: 'hidden', borderRadius: '16px', border: '1px solid var(--pc-line)', background: 'var(--pc-bg)', color: 'var(--pc-text)', boxShadow: 'var(--pc-shadow)' },
    panelExpanded: { position: 'fixed', inset: '0', width: '100%', maxWidth: 'none', maxHeight: 'none', borderRadius: '0' },
    head: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 16px', background: 'var(--pc-primary)', color: '#fff', flexShrink: 0 },
    headBtn: { background: 'transparent', border: '0', color: 'inherit', cursor: 'pointer', padding: '4px', borderRadius: '4px', fontSize: '16px', lineHeight: 1 },
    bar: { display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 12px', borderBottom: '1px solid var(--pc-line)', flexShrink: 0 },
    list: { flex: 1, overflowY: 'auto', padding: '12px 16px', display: 'flex', flexDirection: 'column', gap: '12px' },
    user: { alignSelf: 'flex-end', maxWidth: '85%', borderRadius: '16px 4px 16px 16px', background: 'var(--pc-primary)', color: '#fff', padding: '8px 12px', fontSize: '14px', whiteSpace: 'pre-wrap', overflowWrap: 'anywhere' },
    bot: { alignSelf: 'flex-start', maxWidth: '85%', borderRadius: '4px 16px 16px 16px', background: 'var(--pc-bg-soft)', padding: '8px 12px', fontSize: '14px', overflowWrap: 'anywhere' },
    chip: { display: 'inline-flex', alignItems: 'center', gap: '4px', borderRadius: '6px', border: '1px solid var(--pc-line)', padding: '2px 6px', fontSize: '12px', maxWidth: '100%' },
    error: { borderRadius: '8px', border: '1px solid var(--pc-danger)', color: 'var(--pc-danger)', padding: '8px 12px', fontSize: '12px' },
    foot: { borderTop: '1px solid var(--pc-line)', padding: '12px', flexShrink: 0 },
    input: { flex: 1, resize: 'none', minHeight: '38px', maxHeight: '128px', borderRadius: '12px', border: '1px solid var(--pc-line-field, var(--pc-line))', padding: '8px 12px', fontSize: '14px', background: 'var(--pc-bg)', color: 'var(--pc-text)' },
    send: { width: '36px', height: '36px', borderRadius: '12px', border: '0', background: 'var(--pc-primary)', color: '#fff', cursor: 'pointer', flexShrink: 0 },
    hint: { margin: '6px 0 0', textAlign: 'center', fontSize: '10px', color: 'var(--pc-text-muted)' },
    launcher: { width: '56px', height: '56px', borderRadius: '50%', border: '0', background: 'var(--pc-primary)', color: '#fff', cursor: 'pointer', boxShadow: 'var(--pc-shadow)', fontSize: '22px' },
    dismiss: { position: 'absolute', top: '-4px', right: '-4px', width: '20px', height: '20px', borderRadius: '50%', border: '1px solid var(--pc-line)', background: 'var(--pc-bg)', color: 'var(--pc-text-muted)', cursor: 'pointer', fontSize: '11px', lineHeight: 1, padding: '0' },
    handle: { position: 'fixed', right: '0', top: '50%', transform: 'translateY(-50%)', width: '10px', height: '64px', border: '0', borderRadius: '6px 0 0 6px', background: 'var(--pc-primary)', cursor: 'pointer', zIndex: 50 },
    drop: { position: 'absolute', inset: '8px', zIndex: 10, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '2px dashed var(--pc-primary)', borderRadius: '12px', background: 'var(--pc-bg)', pointerEvents: 'none', fontSize: '14px' },
    card: { marginTop: '8px', border: '1px solid var(--pc-line)', borderRadius: '8px', padding: '10px', fontSize: '12px', background: 'var(--pc-bg)' },
};

/** A preview card under an assistant answer: Confirm / Cancel while pending, its status after. */
function previewCard(card: AiCard, t: AiWords, busy: boolean, onResolve: (token: string, confirm: boolean) => void, key: string | number) {
    return h('div', { key, style: chatStyles.card, 'data-ptah-confirmation': card.token }, [
        h('p', { style: { margin: 0, fontWeight: 600 } }, card.summary),
        card.rows.length > 0 && card.columns.length > 0
            ? h('div', { style: { marginTop: '8px', maxHeight: '224px', overflow: 'auto' } }, [
                  h('table', { class: 'pc-table', style: { fontSize: '11px' } }, [
                      h('thead', [h('tr', card.columns.map((column) => h('th', { key: column.key }, column.label)))]),
                      h('tbody', card.rows.map((row, index) => h('tr', { key: index }, row.map((cell, at) => h('td', { key: at }, cell))))),
                  ]),
              ])
            : null,
        card.total_rows > card.rows.length ? h('p', { style: { margin: '4px 0 0' } }, fill(t.moreRows, { shown: card.rows.length, total: card.total_rows })) : null,
        card.status === 'pending' && card.token !== ''
            ? h('div', { style: { marginTop: '8px', display: 'flex', gap: '8px' } }, [
                  h(ForgeButton, { size: 'sm', disabled: busy, onClick: () => onResolve(card.token, true) }, () => t.confirm),
                  h(ForgeButton, { size: 'sm', color: 'light', disabled: busy, onClick: () => onResolve(card.token, false) }, () => t.cardCancel),
              ])
            : h('p', { role: 'status', style: { margin: '8px 0 0', fontWeight: 600 } }, cardStatusText(t, card.status === 'pending' ? 'expired' : card.status)),
    ]);
}

/**
 * The floating assistant: a round button in the bottom-right corner that
 * opens the chat panel (full screen on demand). Renders nothing while the
 * server says the chat is unavailable (no active provider).
 */
export const PtahAiChat = defineComponent({
    name: 'PtahAiChat',
    props: {
        ...screenProps,
        /** Read the answer as it is written (server-sent events). False: one JSON answer per message. */
        stream: { type: Boolean, default: true },
    },
    setup(props) {
        const client = props.api ?? new PtahApi();
        const t = computed(() => aiWords(props.locale));
        const state = ref<AiChatState | null>(null);
        const open = ref(readStore('session', AI_STORAGE.open) === '1');
        const expanded = ref(readStore('local', AI_STORAGE.expanded) === '1');
        const launcherHidden = ref(readStore('local', AI_STORAGE.launcherHidden) === '1');
        // The draft is the browser's alone, as on Livewire (where a server-owned draft got erased mid-typing).
        const draft = ref(readStore('session', AI_STORAGE.draft) ?? '');
        const messages = ref<AiChatMessage[]>([]);
        const conversationId = ref<number | null>(null);
        const configId = ref<number | null>(null);
        const conversations = ref<AiConversationItem[]>([]);
        const historyLimit = ref(5);
        const showHistory = ref(false);
        const loading = ref(false);
        const streamHtml = ref('');
        const error = ref('');
        const files = ref<File[]>([]);
        const dragging = ref(false);
        const busyCard = ref(false);
        const listRef = ref<HTMLElement | null>(null);
        const inputRef = ref<HTMLTextAreaElement | null>(null);
        const fileRef = ref<HTMLInputElement | null>(null);
        let abort: AbortController | null = null;
        let alive = true;

        onMounted(async () => {
            try {
                const next = await chatState(client);
                if (!alive) {
                    return;
                }
                state.value = next;
                if (next.available) {
                    messages.value = next.messages;
                    conversationId.value = next.conversation_id;
                    configId.value = next.selected_config_id;
                    conversations.value = next.conversations;
                    historyLimit.value = next.history_limit;
                }
            } catch {
                state.value = null;
            }
        });

        watch(open, (value) => writeStore('session', AI_STORAGE.open, value ? '1' : '0'), { immediate: true });
        watch(expanded, (value) => writeStore('local', AI_STORAGE.expanded, value ? '1' : '0'));
        watch(draft, (value) => writeStore('session', AI_STORAGE.draft, value === '' ? null : value));
        watch(launcherHidden, (value) => writeStore('local', AI_STORAGE.launcherHidden, value ? '1' : '0'));

        // The class that reserves room for the button at the end of a listing — only while the button is there.
        watch(
            () => state.value?.available === true && !launcherHidden.value,
            (shown) => typeof document !== 'undefined' && document.body.classList.toggle('ptah-has-ai-launcher', shown),
            { immediate: true },
        );

        const onKey = (event: KeyboardEvent) => {
            if (open.value && event.key === 'Escape') {
                open.value = false;
            }
        };
        onMounted(() => document.addEventListener('keydown', onKey));
        onBeforeUnmount(() => {
            alive = false;
            abort?.abort();
            document.removeEventListener('keydown', onKey);
            document.body.classList.remove('ptah-has-ai-launcher');
        });

        watch([messages, streamHtml, open, loading], () =>
            nextTick(() => {
                if (listRef.value) {
                    listRef.value.scrollTop = listRef.value.scrollHeight;
                }
            }),
        );
        watch(open, (value) => value && nextTick(() => inputRef.value?.focus()));

        const take = (list: FileList | File[] | null | undefined) => {
            if (!state.value) {
                return;
            }
            const picked = acceptFiles(Array.from(list ?? []), state.value.attachments, files.value.length);
            if (picked.length > 0) {
                files.value = [...files.value, ...picked];
                error.value = '';
            }
        };

        const pickProvider = async (id: number) => {
            configId.value = id;
            try {
                // The formats a provider takes change with it, as the Livewire picker re-renders the widget.
                const next = await chatState(client, id);
                if (state.value) {
                    state.value = { ...state.value, attachments: next.attachments };
                }
            } catch {
                // The server checks the files again on send.
            }
        };

        const submit = async () => {
            const text = draft.value.trim();
            if ((text === '' && files.value.length === 0) || loading.value || !state.value) {
                return;
            }
            const sending = files.value;
            const optimistic: AiChatMessage = { role: 'user', content: text, attachments: sending.map((file) => file.name) };

            draft.value = '';
            // By position: the list holds reactive proxies, never the object pushed.
            const at = messages.value.length;
            messages.value = [...messages.value, optimistic];
            error.value = '';
            loading.value = true;
            showHistory.value = false;
            streamHtml.value = '';
            const controller = new AbortController();
            abort = controller;

            try {
                const input = { message: text, conversation_id: conversationId.value, config_id: configId.value, files: sending };
                const result: AiTurn =
                    props.stream && typeof ReadableStream !== 'undefined'
                        ? await streamMessage(client, input, { onDelta: (html) => (streamHtml.value = html), signal: controller.signal })
                        : await sendMessage(client, input);

                if (!result.accepted) {
                    // Refused before any provider call: nothing was stored, the text comes back.
                    messages.value = messages.value.filter((_, index) => index !== at);
                    draft.value = text;
                    error.value = result.error || t.value.chatError;

                    return;
                }
                files.value = [];
                conversationId.value = result.conversation_id;
                messages.value = result.messages;
                if (state.value.history) {
                    conversations.value = result.conversations;
                }
                if (!result.ok) {
                    error.value = result.error || t.value.chatError;
                }
            } catch (caught) {
                if (!controller.signal.aborted) {
                    error.value = failure(caught);
                }
            } finally {
                if (abort === controller) {
                    abort = null;
                }
                loading.value = false;
                streamHtml.value = '';
            }
        };

        const toggleHistory = async () => {
            showHistory.value = !showHistory.value;
            historyLimit.value = 5;
            if (showHistory.value) {
                try {
                    const list = await chatConversations(client, 5);
                    conversations.value = list.conversations;
                    historyLimit.value = list.limit;
                } catch (caught) {
                    error.value = failure(caught);
                }
            }
        };

        const loadMore = async () => {
            try {
                const list = await chatConversations(client, historyLimit.value + 5);
                conversations.value = list.conversations;
                historyLimit.value = list.limit;
            } catch (caught) {
                error.value = failure(caught);
            }
        };

        const pickConversation = async (id: number) => {
            try {
                const found = await openConversation(client, id);
                conversationId.value = found.conversation_id;
                messages.value = found.messages;
                error.value = '';
                showHistory.value = false;
            } catch {
                // As on Livewire: a conversation that cannot be opened leaves the panel as it was.
            }
        };

        const startNew = async () => {
            abort?.abort();
            try {
                const fresh = await newConversation(client);
                conversationId.value = fresh.conversation_id;
            } catch (caught) {
                error.value = failure(caught);

                return;
            }
            messages.value = [];
            files.value = [];
            error.value = '';
            showHistory.value = false;
            draft.value = '';
        };

        const resolve = async (token: string, confirm: boolean) => {
            if (conversationId.value === null || loading.value) {
                return;
            }
            busyCard.value = true;
            try {
                const outcome = await resolveCard(client, conversationId.value, token, confirm);
                if (outcome.ok) {
                    messages.value = outcome.messages;
                    error.value = '';
                } else {
                    error.value = outcome.error || t.value.chatError;
                }
            } catch (caught) {
                error.value = failure(caught);
            } finally {
                busyCard.value = false;
            }
        };

        const onKeyDown = (event: KeyboardEvent) => {
            // Shift+Enter is a new line where the cursor is; Enter alone sends.
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                void submit();
            }
        };

        const renderMessages = (w: AiWords) => [
            messages.value.length === 0 && !loading.value ? h(ForgeEmpty, { title: w.chatEmpty }) : null,
            ...messages.value.map((message, index) =>
                message.role === 'user'
                    ? h('div', { key: index, style: chatStyles.user }, [
                          (message.attachments ?? []).length > 0
                              ? h(
                                    'span',
                                    { style: { display: 'flex', flexWrap: 'wrap', gap: '4px', marginBottom: '4px' } },
                                    (message.attachments ?? []).map((name) => h('span', { key: name, style: { ...chatStyles.chip, borderColor: 'rgba(255,255,255,.4)' } }, `📎 ${name}`)),
                                )
                              : null,
                          message.content,
                      ])
                    : h('div', { key: index, style: chatStyles.bot }, [
                          // Rendered and sanitised on the server (ChatMarkdown: HTML escaped, unsafe links refused).
                          h('div', { class: 'ptah-chat-md', innerHTML: message.html ?? '' }),
                          ...(message.confirmations ?? []).map((card, at) => previewCard(card, w, busyCard.value, (token, confirm) => void resolve(token, confirm), card.token || at)),
                      ]),
            ),
            loading.value
                ? h('div', { style: chatStyles.bot, role: 'status', 'aria-label': w.chatThinking }, [
                      streamHtml.value !== '' ? h('div', { class: 'ptah-chat-md', innerHTML: streamHtml.value }) : h('span', { 'aria-hidden': 'true' }, '• • •'),
                  ])
                : null,
            error.value !== '' ? h('div', { role: 'alert', style: chatStyles.error }, error.value) : null,
        ];

        const renderFoot = (w: AiWords, current: AiChatState) => {
            const rules = current.attachments;
            const attachable = rules.enabled && rules.extensions.length > 0;
            const matches = commandMatches(current.commands, draft.value);

            return h('div', { style: chatStyles.foot }, [
                attachable && files.value.length > 0
                    ? h(
                          'div',
                          { style: { display: 'flex', flexWrap: 'wrap', gap: '6px', marginBottom: '8px' } },
                          files.value.map((file, index) =>
                              h('span', { key: `${file.name}-${index}`, style: chatStyles.chip }, [
                                  `${file.type.startsWith('image/') ? '🖼' : '📄'} ${file.name}`,
                                  h(
                                      'button',
                                      {
                                          type: 'button',
                                          class: 'pf-x',
                                          title: fill(w.attachRemove, { name: file.name }),
                                          'aria-label': fill(w.attachRemove, { name: file.name }),
                                          onClick: () => (files.value = files.value.filter((_, at) => at !== index)),
                                      },
                                      '✕',
                                  ),
                              ]),
                          ),
                      )
                    : null,

                matches.length > 0
                    ? h(
                          'div',
                          { role: 'listbox', 'aria-label': w.commands, style: { marginBottom: '8px', border: '1px solid var(--pc-line)', borderRadius: '8px', overflow: 'hidden', fontSize: '12px' } },
                          matches.map((command) =>
                              h(
                                  'button',
                                  {
                                      key: command.name,
                                      type: 'button',
                                      role: 'option',
                                      'aria-selected': 'false',
                                      style: { display: 'flex', gap: '8px', width: '100%', padding: '6px 12px', border: '0', background: 'transparent', textAlign: 'left', cursor: 'pointer', color: 'inherit' },
                                      onClick: () => {
                                          draft.value = `/${command.name}`;
                                          inputRef.value?.focus();
                                      },
                                  },
                                  [h('strong', { style: { fontFamily: 'monospace' } }, `/${command.name}`), h('span', { style: { color: 'var(--pc-text-muted)' } }, command.description)],
                              ),
                          ),
                      )
                    : null,

                h('div', { style: { display: 'flex', alignItems: 'flex-end', gap: '8px' } }, [
                    ...(attachable
                        ? [
                              h('input', {
                                  ref: fileRef,
                                  type: 'file',
                                  multiple: true,
                                  hidden: true,
                                  accept: rules.accept,
                                  'aria-hidden': 'true',
                                  tabindex: -1,
                                  'data-testid': 'ptah-ai-file',
                                  onChange: (event: Event) => {
                                      const target = event.target as HTMLInputElement;
                                      take(target.files);
                                      target.value = '';
                                  },
                              }),
                              h('button', { type: 'button', style: { ...chatStyles.send, background: 'var(--pc-bg-soft)', color: 'var(--pc-text)' }, title: w.attach, 'aria-label': w.attach, disabled: files.value.length >= rules.max_files, onClick: () => fileRef.value?.click() }, '📎'),
                          ]
                        : []),
                    // Not disabled while the answer comes: typing the next question meanwhile is what people do.
                    h('textarea', {
                        ref: inputRef,
                        rows: 1,
                        value: draft.value,
                        placeholder: w.chatPlaceholder,
                        'aria-label': w.chatPlaceholder,
                        style: chatStyles.input,
                        onInput: (event: Event) => (draft.value = (event.target as HTMLTextAreaElement).value),
                        onKeydown: onKeyDown,
                    }),
                    h('button', { type: 'button', style: chatStyles.send, title: w.chatSend, 'aria-label': w.chatSend, disabled: (draft.value.trim() === '' && files.value.length === 0) || loading.value, onClick: () => void submit() }, '➤'),
                ]),
                h('p', { style: chatStyles.hint }, `${w.chatKeyboard}${attachable ? ` · ${w.attachHint}` : ''}`),
                rules.enabled && rules.blocked.length > 0 ? h('p', { style: chatStyles.hint }, fill(w.attachBlocked, { formats: rules.blocked.join(', ') })) : null,
            ]);
        };

        const renderHistory = (w: AiWords) =>
            h('div', { style: { ...chatStyles.list, gap: '2px' } }, [
                h('p', { style: { margin: 0, fontSize: '12px', textTransform: 'uppercase', color: 'var(--pc-text-muted)' } }, w.chatHistory),
                ...(conversations.value.length === 0
                    ? [h(ForgeEmpty, { title: w.chatNoHistory })]
                    : [
                          ...conversations.value.map((item) =>
                              h(
                                  'button',
                                  {
                                      key: item.id,
                                      type: 'button',
                                      class: 'pc-menu-item',
                                      'aria-current': item.id === conversationId.value ? 'true' : undefined,
                                      style: { textAlign: 'left', width: '100%', padding: '8px 12px', border: '0', borderRadius: '8px', background: item.id === conversationId.value ? 'var(--pc-primary-soft, var(--pc-bg-soft))' : 'transparent', cursor: 'pointer', color: 'inherit' },
                                      onClick: () => void pickConversation(item.id),
                                  },
                                  [
                                      h('span', { style: { display: 'block', fontWeight: 500, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } }, item.title),
                                      h('span', { style: { display: 'block', fontSize: '12px', color: 'var(--pc-text-muted)' } }, item.date),
                                  ],
                              ),
                          ),
                          conversations.value.length >= historyLimit.value ? h('button', { type: 'button', class: 'pc-link', onClick: () => void loadMore() }, w.chatLoadMore) : null,
                      ]),
            ]);

        return () => {
            const current = state.value;
            if (!current || !current.available) {
                return null;
            }
            const w = t.value;
            const attachable = current.attachments.enabled && current.attachments.extensions.length > 0;
            const options = current.providers;

            const panel = open.value
                ? h(
                      'div',
                      {
                          role: 'dialog',
                          'aria-modal': 'false',
                          'aria-label': w.chatTitle,
                          style: expanded.value ? { ...chatStyles.panel, ...chatStyles.panelExpanded } : chatStyles.panel,
                          onPaste: (event: ClipboardEvent) => {
                              // A pasted screenshot arrives as a file; text pastes go on as usual.
                              if (attachable && event.clipboardData && event.clipboardData.files.length > 0) {
                                  event.preventDefault();
                                  take(event.clipboardData.files);
                              }
                          },
                          onDragover: (event: DragEvent) => {
                              if (attachable) {
                                  event.preventDefault();
                                  dragging.value = true;
                              }
                          },
                          onDragleave: (event: DragEvent) => event.target === event.currentTarget && (dragging.value = false),
                          onDrop: (event: DragEvent) => {
                              if (attachable) {
                                  event.preventDefault();
                                  dragging.value = false;
                                  take(event.dataTransfer?.files);
                              }
                          },
                      },
                      [
                          dragging.value ? h('div', { style: chatStyles.drop }, w.attachDrop) : null,

                          h('div', { style: chatStyles.head, onDblclick: () => (expanded.value = !expanded.value) }, [
                              h('strong', { style: { fontSize: '14px' } }, w.chatTitle),
                              h('div', { style: { display: 'flex', gap: '4px' } }, [
                                  current.history ? h('button', { type: 'button', style: chatStyles.headBtn, title: w.chatHistory, 'aria-label': w.chatHistory, 'aria-pressed': showHistory.value ? 'true' : 'false', onClick: () => void toggleHistory() }, '⟲') : null,
                                  h('button', { type: 'button', style: chatStyles.headBtn, title: w.chatNew, 'aria-label': w.chatNew, onClick: () => void startNew() }, '✎'),
                                  h('button', { type: 'button', style: chatStyles.headBtn, title: expanded.value ? w.chatCollapse : w.chatExpand, 'aria-label': expanded.value ? w.chatCollapse : w.chatExpand, onClick: () => (expanded.value = !expanded.value) }, expanded.value ? '⤡' : '⤢'),
                                  h('button', { type: 'button', style: chatStyles.headBtn, title: w.chatClose, 'aria-label': w.chatClose, onClick: () => (open.value = false) }, '✕'),
                              ]),
                          ]),

                          !showHistory.value && options.length > 1
                              ? h('div', { style: chatStyles.bar }, [
                                    h(
                                        'select',
                                        { class: 'pc-input', 'aria-label': w.chatProvider, value: configId.value ?? '', style: { flex: 1, minWidth: 0, fontSize: '12px' }, onChange: (event: Event) => void pickProvider(Number((event.target as HTMLSelectElement).value)) },
                                        options.map((option) => h('option', { key: option.id, value: option.id }, `${option.name} — ${option.model}${option.is_default ? ` (${w.chatProviderDefault})` : ''}`)),
                                    ),
                                ])
                              : null,

                          ...(showHistory.value ? [renderHistory(w)] : [h('div', { ref: listRef, style: chatStyles.list }, renderMessages(w)), renderFoot(w, current)]),
                      ],
                  )
                : null;

            return h('div', { class: 'pf', style: chatStyles.root }, [
                panel,
                !launcherHidden.value && !(expanded.value && open.value)
                    ? h('div', { style: { position: 'relative' } }, [
                          h('button', { type: 'button', style: chatStyles.launcher, title: open.value ? w.chatClose : w.chatOpen, 'aria-label': open.value ? w.chatClose : w.chatOpen, 'aria-expanded': open.value ? 'true' : 'false', onClick: () => (open.value = !open.value) }, open.value ? '✕' : '✦'),
                          !open.value ? h('button', { type: 'button', style: chatStyles.dismiss, title: w.chatHide, 'aria-label': w.chatHide, onClick: () => (launcherHidden.value = true) }, '✕') : null,
                      ])
                    : null,
                launcherHidden.value
                    ? h('button', {
                          type: 'button',
                          style: chatStyles.handle,
                          title: w.chatShow,
                          'aria-label': w.chatShow,
                          onClick: () => {
                              launcherHidden.value = false;
                              open.value = true;
                          },
                      })
                    : null,
            ]);
        };
    },
});
