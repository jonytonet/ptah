/**
 * Record history and record attachments for Vue — the History and
 * Attachments buttons of the BaseCrud edit modal and the two dialogs they
 * open (_modal-history / _modal-attachments.blade.php). The dialogs follow
 * the screen's panel: `crud.openPanel('history', id)`,
 * `crud.openPanel('attachments', id)`, closed with `crud.closePanel()`.
 * Mirrors react/crud/records.tsx.
 */
import { computed, defineComponent, h, onBeforeUnmount, ref, shallowRef, watch } from 'vue';
import type { PropType, VNode } from 'vue';
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
import type { AttachmentItem, HistoryChange, HistoryEntry, RecordsWords } from '../../core/crud/records';
import { modalButton } from '../../core/crud/form';
import PtahConfirm from '../PtahConfirm.vue';
import { icon } from '../icons';
import { PtahCrudModal, PtahModalAlert, PtahModalEmpty } from './modal';
import { usePtahCrud } from '../usePtahCrud';

const panelProps = {
    crud: { type: Object as PropType<CrudController>, required: true as const },
    locale: { type: String, default: undefined },
};

let ids = 0;

const loading = (t: RecordsWords) => h('div', { class: 'pc-fm-loading', role: 'status', 'aria-live': 'polite' }, [h('span', { class: 'pc-fm-spin', 'aria-hidden': 'true' }), ` ${t.loading}`]);

const alertLines = (messages: string[]) => messages.map((message) => h('p', { role: 'alert', class: 'pc-fm-err' }, message));

const modal = (onClose: () => void, closeLabel: string, title: string, size: 'sm' | 'lg', body: () => unknown, footer: () => unknown) =>
    h(PtahCrudModal, { title, size, closeLabel, onClose }, { default: body, footer });

/** forge-button dark flat. */
const flatButton = (label: string, onClick: () => void) => h('button', { type: 'button', class: modalButton('flat'), onClick }, label);

/** The record of the panel `name`, when it is the open one. */
function usePanel(crud: CrudController, name: string) {
    const state = usePtahCrud(crud);

    return {
        state,
        id: computed<RowId | null>(() => (state.value.panel?.name === name && state.value.panel.id !== null ? state.value.panel.id : null)),
    };
}

// ── Row buttons ─────────────────────────────────────────────────────────────

/**
 * History and Attachments (with the record's attachment count) for one row
 * — or for the record open in the form. The counts of a page come in one
 * request for all its rows.
 */
export const PtahCrudRecordButtons = defineComponent({
    name: 'PtahCrudRecordButtons',
    props: {
        ...panelProps,
        /** The row (or `{ id }` of the record open in the form). A trashed row has neither button. */
        row: { type: Object as PropType<{ id: RowId; trashed?: boolean }>, required: true as const },
    },
    setup(props) {
        const state = usePtahCrud(props.crud);
        const t = computed(() => recordsWords(props.locale));
        const store = countsOf(props.crud);
        const version = ref(0);
        const stop = store.subscribe(() => version.value++);
        onBeforeUnmount(stop);

        return () => {
            const features = state.value.schema?.features;
            if (!features || props.row.trashed || (!features.history && !features.attachments)) {
                return null;
            }
            void version.value; // follow the counts store
            const count = features.attachments ? store.get(props.row.id) : null;

            return [
                features.attachments
                    ? h('button', { type: 'button', class: modalButton('flat'), onClick: () => props.crud.openPanel(PANEL_ATTACHMENTS, props.row.id) }, `${t.value.attachments}${count !== null && count >= 0 ? ` (${count})` : ''}`)
                    : null,
                features.history ? h('button', { type: 'button', class: modalButton('flat'), onClick: () => props.crud.openPanel(PANEL_HISTORY, props.row.id) }, t.value.history) : null,
            ];
        };
    },
});

// ── History ─────────────────────────────────────────────────────────────────

function change(item: HistoryChange, updated: boolean, t: RecordsWords): VNode[] {
    return [
        h('dt', item.label),
        h('dd', [
            ...(updated
                ? [h('span', { class: 'pc-hist-old', style: { textDecoration: 'line-through' } }, item.old), ' ', h('span', { 'aria-hidden': 'true', class: 'pc-fm-muted' }, '→'), h('span', { style: SR_ONLY }, t.became), ' ']
                : []),
            h('span', item.new),
        ]),
    ];
}

/** Who changed what, and when — only the fields this screen shows. */
export const PtahCrudHistory = defineComponent({
    name: 'PtahCrudHistory',
    props: panelProps,
    setup(props) {
        const { state, id } = usePanel(props.crud, PANEL_HISTORY);
        const t = computed(() => recordsWords(props.locale));
        const enabled = computed(() => state.value.schema?.features.history ?? false);
        const items = shallowRef<HistoryEntry[] | null>(null);
        const error = ref<string | null>(null);
        let request = 0;

        watch(
            [id, enabled],
            ([current, on]) => {
                if (current === null || !on) {
                    return;
                }
                const mine = ++request;
                items.value = null;
                error.value = null;
                getHistory(props.crud, current)
                    .then((data) => mine === request && (items.value = data))
                    .catch((reason) => mine === request && (error.value = failureText(reason, t.value)));
            },
            { immediate: true },
        );

        const close = () => props.crud.closePanel();

        return () => {
            if (id.value === null || !enabled.value) {
                return null;
            }
            const words = t.value;

            return modal(
                close,
                words.close,
                words.historyTitle,
                'lg',
                () =>
                    error.value
                        ? h(PtahModalAlert, null, () => error.value)
                        : items.value === null
                          ? loading(words)
                          : items.value.length === 0
                            ? h(PtahModalEmpty, { title: words.historyEmpty })
                            : h(
                                  'ol',
                                  { class: 'pc-hist' },
                                  items.value.map((item, index) =>
                                      h('li', { key: index, class: 'pc-hist-item' }, [
                                          h('div', { class: 'pc-hist-top' }, [
                                              h('p', { class: 'pc-hist-event' }, [`${words.events[item.event] ?? item.event} `, h('span', { class: 'pc-hist-who' }, `· ${item.who}`)]),
                                              h('time', { class: 'pc-hist-when' }, item.when),
                                          ]),
                                          item.changes.length > 0 ? h('dl', { class: 'pc-hist-changes' }, item.changes.flatMap((c) => change(c, item.event === 'updated', words))) : null,
                                          item.hidden > 0 ? h('p', { class: 'pc-hist-hidden' }, plural(words.hiddenFields, item.hidden)) : null,
                                      ]),
                                  ),
                              ),
                () => flatButton(words.close, close),
            );
        };
    },
});

// ── Attachments ─────────────────────────────────────────────────────────────

/** The record's files: list and download; upload and remove with the update permission. */
export const PtahCrudAttachments = defineComponent({
    name: 'PtahCrudAttachments',
    props: panelProps,
    setup(props) {
        const { state, id } = usePanel(props.crud, PANEL_ATTACHMENTS);
        const t = computed(() => recordsWords(props.locale));
        const inputId = `ptah-attachments-input-${++ids}`;
        const enabled = computed(() => state.value.schema?.features.attachments ?? false);
        const canWrite = computed(() => enabled.value && (state.value.schema?.features.attachmentsWrite ?? false));

        const items = shallowRef<AttachmentItem[] | null>(null);
        const error = ref<string | null>(null);
        const files = shallowRef<File[]>([]);
        const errors = shallowRef<string[]>([]);
        const busy = ref(false);
        const removing = shallowRef<AttachmentItem | null>(null);
        let request = 0;

        watch(
            [id, enabled],
            ([current, on]) => {
                if (current === null || !on) {
                    return;
                }
                const mine = ++request;
                items.value = null;
                error.value = null;
                files.value = [];
                errors.value = [];
                removing.value = null;
                listAttachments(props.crud, current)
                    .then((list) => {
                        if (mine === request) {
                            items.value = list.data;
                            countsOf(props.crud).set(current, list.count);
                        }
                    })
                    .catch((reason) => mine === request && (error.value = failureText(reason, t.value)));
            },
            { immediate: true },
        );

        const close = () => props.crud.closePanel();

        const upload = async (record: RowId) => {
            if (files.value.length === 0 || busy.value) {
                return;
            }
            busy.value = true;
            errors.value = [];
            try {
                const result = await uploadAttachments(props.crud, record, files.value);
                if (!result.ok) {
                    errors.value = result.errors;

                    return;
                }
                items.value = result.data;
                files.value = [];
                countsOf(props.crud).set(record, result.count);
                result.messages.forEach((message) => props.crud.toast(message.text, message.level));
            } catch (reason) {
                errors.value = [failureText(reason, t.value)];
            } finally {
                busy.value = false;
            }
        };

        const remove = async (record: RowId, item: AttachmentItem) => {
            removing.value = null;
            busy.value = true;
            errors.value = [];
            try {
                const result = await deleteAttachment(props.crud, record, item.id);
                if (result.ok) {
                    items.value = result.data;
                    countsOf(props.crud).set(record, result.count);
                } else {
                    errors.value = result.errors;
                }
            } catch (reason) {
                errors.value = [failureText(reason, t.value)];
                // Gone already (another tab, another user): show what is there now.
                listAttachments(props.crud, record)
                    .then((list) => (items.value = list.data))
                    .catch(() => undefined);
            } finally {
                busy.value = false;
            }
        };

        const picker = (words: RecordsWords) =>
            h('div', { class: 'pc-att-up' }, [
                h('label', { for: inputId, class: 'pc-fm-pick' }, [
                    icon('paperclip'),
                    words.pick,
                    h('input', {
                        id: inputId,
                        type: 'file',
                        multiple: true,
                        style: SR_ONLY,
                        disabled: busy.value,
                        onChange: (event: Event) => {
                            const input = event.target as HTMLInputElement;
                            files.value = Array.from(input.files ?? []);
                            errors.value = [];
                            input.value = '';
                        },
                    }),
                ]),
                ...(files.value.length > 0
                    ? [
                          h('ul', { class: 'pc-att-files' }, files.value.map((file, index) => h('li', { key: index }, file.name))),
                          h(
                              'button',
                              { type: 'button', class: modalButton('primary', true), disabled: busy.value, 'aria-busy': busy.value || undefined, onClick: () => id.value !== null && void upload(id.value) },
                              [busy.value ? h('span', { class: 'pc-fm-spin', 'aria-hidden': 'true' }) : null, busy.value ? words.uploading : plural(words.send, files.value.length)],
                          ),
                      ]
                    : []),
                ...alertLines(errors.value),
            ]);

        const list = (words: RecordsWords, list: AttachmentItem[]) =>
            h(
                'ul',
                { class: 'pc-att-list' },
                list.map((item) =>
                    h('li', { key: item.id, class: 'pc-att-row' }, [
                        h('div', { class: 'pc-att-info' }, [
                            h(
                                'a',
                                {
                                    href: item.url,
                                    download: item.name,
                                    'aria-label': words.download.replace(':name', item.name),
                                    class: 'pc-att-name',
                                },
                                item.name,
                            ),
                            h('p', { class: 'pc-att-meta' }, `${item.size} · ${item.when}`),
                        ]),
                        canWrite.value
                            ? h('button', { type: 'button', class: modalButton('flat-danger', true), disabled: busy.value, 'aria-label': `${words.delete} ${item.name}`, onClick: () => (removing.value = item) }, words.delete)
                            : null,
                    ]),
                ),
            );

        return () => {
            const record = id.value;
            if (record === null || !enabled.value) {
                return null;
            }
            const words = t.value;
            const pending = removing.value;

            if (pending !== null) {
                return h(PtahConfirm, {
                    title: words.deleteTitle,
                    text: words.deleteConfirm.replace(':name', pending.name),
                    confirm: words.delete,
                    cancel: words.cancel,
                    closeLabel: words.close,
                    danger: true,
                    onCancel: () => (removing.value = null),
                    onConfirm: () => void remove(record, pending),
                });
            }

            return modal(
                close,
                words.close,
                words.attachmentsTitle,
                'lg',
                () =>
                    error.value
                        ? h(PtahModalAlert, null, () => error.value)
                        : h('div', { class: 'pc-att' }, [
                              canWrite.value ? picker(words) : alertLines(errors.value),
                              items.value === null ? loading(words) : items.value.length === 0 ? h(PtahModalEmpty, { title: words.attachmentsEmpty }) : list(words, items.value),
                          ]),
                () => flatButton(words.close, close),
            );
        };
    },
});
