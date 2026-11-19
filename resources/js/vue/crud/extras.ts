/**
 * BaseCrud extras for Vue — the form draft notice, the server-side form
 * behaviours, "Save & add another", the master/detail toggle and row, the
 * columns menu and the column resize handle. Each piece takes the screen's
 * CrudController and renders nothing when its feature is off. Mirrors
 * react/crud/extras.tsx.
 */
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import type { PropType, ShallowRef, VNode } from 'vue';
import type { CrudController, Row } from '../../core';
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
import type { ColumnItem, DetailScreen } from '../../core/crud/extras';
import { ForgeAlert, ForgeButton, ForgeEmpty, ForgeModal, ForgeSpinner } from '../forge';
import { icon } from '../icons';
import { usePtahCrud } from '../usePtahCrud';

const pieceProps = {
    crud: { type: Object as PropType<CrudController>, required: true as const },
    /** `pt_BR`, `en`… Defaults to the page's `<html lang>`. */
    locale: { type: String, default: undefined },
};

/** A store of core/crud/extras as a ref that follows every change. */
function useStore<T>(store: { subscribe: (listener: () => void) => () => void; get: () => T }): ShallowRef<T> {
    const value = shallowRef(store.get());
    const stop = store.subscribe(() => {
        value.value = store.get();
    });
    onBeforeUnmount(stop);

    return value;
}

const ROW = { display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '8px' };
const MUTED = { color: 'var(--pc-text-muted)', fontSize: '13px' };
const TITLE = { margin: '0 0 8px', fontSize: '12px', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '.05em', color: 'var(--pc-text-muted)' };

// ── Form draft ──────────────────────────────────────────────────────────────

/**
 * Mounted inside the form (top of the modal body): keeps what is typed as a
 * draft in this browser, restores it when the form is opened again (or
 * offers to, when typing already started), and offers "Clear all" (new) /
 * "Revert to original" (edit) while the draft holds changes.
 */
export const PtahCrudFormDraft = defineComponent({
    name: 'PtahCrudFormDraft',
    props: pieceProps,
    setup(props) {
        const t = extrasWords(props.locale);
        const state = usePtahCrud(props.crud);
        const session = new FormDraftSession(props.crud, t);
        const view = useStore(session);

        onMounted(() => session.start());
        onBeforeUnmount(() => session.stop());

        return () => {
            const form = state.value.form;
            const v = view.value;
            if (!state.value.schema?.features.formDraft || !form || (!v.offer && !v.dirty && !v.busy)) {
                return null;
            }

            return h('div', { style: { display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '12px' } }, [
                v.offer
                    ? h(ForgeAlert, { color: 'warn', title: t.draftFound }, () =>
                          h('div', { style: { ...ROW, marginTop: '8px' } }, [
                              h(ForgeButton, { size: 'sm', onClick: () => void session.restore() }, () => t.draftRestore),
                              h(ForgeButton, { size: 'sm', color: 'dark', flat: true, onClick: () => session.discard() }, () => t.draftDiscard),
                          ]),
                      )
                    : null,
                v.dirty && !v.offer
                    ? h('div', { style: { ...ROW, justifyContent: 'space-between' } }, [
                          h('span', { style: MUTED }, t.draftKept),
                          h(
                              ForgeButton,
                              { size: 'sm', color: 'dark', flat: true, disabled: form.saving || v.busy, onClick: () => void (v.mode === 'edit' ? session.revert() : session.clear()) },
                              () => (v.mode === 'edit' ? t.draftRevert : t.draftClear),
                          ),
                      ])
                    : null,
                v.busy ? h(ForgeSpinner, { size: 'sm', label: t.loading }) : null,
            ]);
        };
    },
});

// ── Form behaviours ─────────────────────────────────────────────────────────

/**
 * Mounted inside the form, draws nothing: when a field with a `colsOnChange`
 * formula or with dependent dropdowns changes, the server recomputes and the
 * form takes its answer.
 */
export const PtahCrudFormChanges = defineComponent({
    name: 'PtahCrudFormChanges',
    props: { crud: pieceProps.crud },
    setup(props) {
        let stop: (() => void) | null = null;
        onMounted(() => {
            stop = watchFormChanges(props.crud);
        });
        onBeforeUnmount(() => stop?.());

        return () => null;
    },
});

/** The extra footer button of a new record's form: saves, then opens a blank form. */
export const PtahCrudSaveAndNew = defineComponent({
    name: 'PtahCrudSaveAndNew',
    props: pieceProps,
    setup(props) {
        const t = extrasWords(props.locale);
        const state = usePtahCrud(props.crud);

        return () => {
            const form = state.value.form;
            if (!form || form.mode !== 'create' || !state.value.schema?.permissions.canCreate) {
                return null;
            }

            return h(ForgeButton, { color: 'primary', flat: true, disabled: form.saving, onClick: () => void saveAndNew(props.crud) }, () => t.saveAndNew);
        };
    },
});

// ── Master / detail ─────────────────────────────────────────────────────────

/** The expand button of a row (first cell). */
export const PtahCrudDetailToggle = defineComponent({
    name: 'PtahCrudDetailToggle',
    props: { ...pieceProps, row: { type: Object as PropType<Row>, required: true as const } },
    setup(props) {
        const t = extrasWords(props.locale);
        const state = usePtahCrud(props.crud);
        const store = detailsOf(props.crud);
        const view = useStore(store);

        return () => {
            if (!state.value.schema?.features.masterDetail) {
                return null;
            }
            const open = view.value.expanded.includes(String(props.row.id));

            return h('button', { type: 'button', class: 'pc-expand', title: t.showDetails, 'aria-label': t.showDetails, 'aria-expanded': open ? 'true' : 'false', onClick: () => store.toggle(props.row.id) }, [
                icon('chevronRight'),
            ]);
        };
    },
});

/**
 * The expanded content of a row, mounted on the first expansion: under each
 * other, a full CRUD screen per detail of the row (search, filters, form,
 * export, row actions — the child's own permissions), bound by the server to
 * this row, as the nested BaseCrud of the Livewire table.
 *
 * Each detail is drawn by the `detail` slot (`{ detail }`) or `renderDetail`.
 * PtahCrud.vue fills the slot with `<PtahCrud :screen="detail.screen" :remember="false">`
 * — handed in, not imported, so this file never imports PtahCrud (which
 * imports it).
 */
export const PtahCrudDetailRow = defineComponent({
    name: 'PtahCrudDetailRow',
    props: {
        ...pieceProps,
        row: { type: Object as PropType<Row>, required: true as const },
        colSpan: { type: Number, required: true as const },
        renderDetail: { type: Function as PropType<(detail: DetailScreen) => VNode | VNode[] | null>, default: undefined },
    },
    setup(props, { slots }) {
        const t = extrasWords(props.locale);
        const state = usePtahCrud(props.crud);
        const store = detailsOf(props.crud);
        const view = useStore(store);
        const draw = (detail: DetailScreen) => (slots.detail ? slots.detail({ detail }) : props.renderDetail ? props.renderDetail(detail) : null);

        return () => {
            const key = String(props.row.id);
            if (!state.value.schema?.features.masterDetail || !view.value.expanded.includes(key)) {
                return null;
            }
            const entry = view.value.entries[key];

            return h('tr', { class: 'pc-detail-row' }, [
                h('td', { colspan: props.colSpan, class: 'pc-detail-td', 'aria-busy': !entry || entry.status === 'loading' ? 'true' : 'false' }, [
                    !entry || (entry.status === 'loading' && entry.details.length === 0) ? h(ForgeSpinner, { label: t.loading }) : null,
                    entry?.status === 'error'
                        ? h(ForgeAlert, { color: 'danger' }, () =>
                              h('div', { style: ROW }, [h('span', entry.error || t.detailsError), h(ForgeButton, { size: 'sm', color: 'danger', flat: true, onClick: () => void store.load(props.row.id) }, () => t.retry)]),
                          )
                        : null,
                    entry && entry.status === 'ready' && entry.details.length === 0 ? h(ForgeEmpty, { title: t.detailsEmpty }) : null,
                    h(
                        'div',
                        { style: { display: 'flex', flexDirection: 'column', gap: '16px' } },
                        (entry?.details ?? []).map((detail) =>
                            detail.available
                                ? h('section', { key: detail.screen, class: 'pc-detail', 'aria-label': detail.title || undefined }, draw(detail) ?? undefined)
                                : h('section', { key: detail.screen, 'aria-label': detail.title || undefined }, [
                                      detail.title ? h('p', { style: TITLE }, detail.title) : null,
                                      h('p', { style: MUTED }, t.detailUnavailable),
                                  ]),
                        ),
                    ),
                ]),
            ]);
        };
    },
});

// ── Columns ─────────────────────────────────────────────────────────────────

/**
 * The toolbar's columns menu: show/hide each column, show all, hide all,
 * reset the layout, and reorder — dragging a column, or with its ↑/↓
 * buttons from the keyboard.
 */
export const PtahCrudColumnsMenu = defineComponent({
    name: 'PtahCrudColumnsMenu',
    props: pieceProps,
    setup(props) {
        const t = extrasWords(props.locale);
        const crud = props.crud;
        const state = usePtahCrud(crud);
        const open = ref(false);
        const items = shallowRef<ColumnItem[] | null>(null);
        const error = ref<string | null>(null);
        const busy = ref(false);
        const confirming = ref(false);
        const dragging = ref<string | null>(null);
        const over = ref<string | null>(null);
        const root = ref<HTMLElement | null>(null);

        const load = () => {
            error.value = null;
            listColumns(crud.api, crud.screen).then(
                (found) => (items.value = found),
                () => (error.value = t.columnsError),
            );
        };
        watch(open, (value) => {
            if (value && items.value === null) {
                load();
            }
        });

        const close = (event: MouseEvent | KeyboardEvent) => {
            if (!open.value || confirming.value) {
                return;
            }
            if (event instanceof KeyboardEvent ? event.key === 'Escape' : !root.value?.contains(event.target as Node)) {
                open.value = false;
            }
        };
        onMounted(() => {
            document.addEventListener('mousedown', close);
            document.addEventListener('keydown', close);
        });
        onBeforeUnmount(() => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        });

        const list = computed(() => (items.value && state.value.preferences ? menuColumns(state.value.preferences, items.value) : []));
        const shown = computed(() => list.value.map((item) => item.field));

        const act = async (work: () => Promise<unknown>) => {
            busy.value = true;
            try {
                await work();
            } finally {
                busy.value = false;
            }
        };
        const move = (field: string, to: number) => {
            const preferences = state.value.preferences;
            if (preferences) {
                void act(() => saveColumnOrder(crud, moveColumn(fullOrder(preferences), shown.value, field, to)));
            }
        };
        const all = (visible: boolean) => {
            open.value = false;
            // Every column of the map, as hideAllColumns()/showAllColumns() (id too).
            void act(() => setAllColumns(crud, Object.keys(state.value.preferences?.columns ?? {}), visible));
        };
        const reset = () =>
            void act(async () => {
                confirming.value = false;
                try {
                    const answer = await resetColumns(crud.api, crud.screen);
                    items.value = answer.data;
                    await crud.savePreferences({});
                } catch {
                    crud.toast(t.error, 'danger');
                }
            });
        const drop = (event: DragEvent, field: string) => {
            event.preventDefault();
            if (dragging.value && dragging.value !== field) {
                move(dragging.value, shown.value.indexOf(field));
            }
            dragging.value = null;
            over.value = null;
        };

        return () => {
            const preferences = state.value.preferences;
            if (!state.value.schema || !preferences || Object.keys(preferences.columns ?? {}).length === 0) {
                return null;
            }
            const hidden = Object.values(preferences.columns).filter((visible) => !visible).length;
            const rows = list.value;

            return h('div', { class: 'pc-dd-wrap pc-tb-secondary', ref: root }, [
                h(
                    'button',
                    {
                        type: 'button',
                        class: `pc-tbtn${hidden > 0 ? ' pc-tbtn--warn-on' : ''}`,
                        title: t.columns,
                        'aria-label': hidden > 0 ? `${t.columns} (${fill(t.hiddenCount, { count: hidden })})` : t.columns,
                        'aria-haspopup': 'true',
                        'aria-expanded': open.value ? 'true' : 'false',
                        onClick: () => (open.value = !open.value),
                    },
                    [
                        icon('columns'),
                        h('span', { class: 'pc-tbtn-label' }, t.columns),
                        hidden > 0 ? h('span', { class: 'pc-count pc-count--warn' }, String(hidden)) : null,
                        icon('chevronDown', 12),
                    ],
                ),
                open.value
                    ? h('div', { class: 'pc-dd pc-cols-dd', role: 'group', 'aria-label': t.columns, 'aria-busy': busy.value ? 'true' : 'false' }, [
                          h('div', { class: 'pc-cols-quick' }, [
                              h('button', { type: 'button', class: 'pc-cols-qbtn', disabled: busy.value || rows.length === 0, onClick: () => all(true) }, t.showAll),
                              h('button', { type: 'button', class: 'pc-cols-qbtn', disabled: busy.value || rows.length === 0, onClick: () => all(false) }, t.hideAll),
                              h('button', { type: 'button', class: 'pc-cols-qbtn', disabled: busy.value, onClick: () => (confirming.value = true) }, t.resetColumns),
                          ]),
                          error.value
                              ? h('div', { style: { padding: '8px' } }, [h('p', { class: 'pc-error', role: 'alert' }, error.value), h('button', { type: 'button', class: 'pc-btn', onClick: load }, t.retry)])
                              : null,
                          !error.value && items.value === null ? h('div', { style: { padding: '8px' } }, [h(ForgeSpinner, { size: 'sm', label: t.loading })]) : null,
                          items.value !== null && rows.length === 0 ? h('p', { style: { ...MUTED, padding: '8px' } }, t.noColumns) : null,
                          rows.length > 0
                              ? h(
                                    'ul',
                                    { class: 'pc-cols-list', 'aria-label': t.dragHint },
                                    rows.map((item, index) =>
                                        h(
                                            'li',
                                            {
                                                key: item.field,
                                                draggable: !busy.value,
                                                onDragstart: (event: DragEvent) => {
                                                    if (event.dataTransfer) {
                                                        event.dataTransfer.effectAllowed = 'move';
                                                        event.dataTransfer.setData('text/plain', item.field);
                                                    }
                                                    dragging.value = item.field;
                                                },
                                                onDragover: (event: DragEvent) => {
                                                    event.preventDefault();
                                                    over.value = item.field;
                                                },
                                                onDrop: (event: DragEvent) => drop(event, item.field),
                                                onDragend: () => {
                                                    dragging.value = null;
                                                    over.value = null;
                                                },
                                                class: 'pc-cols-item',
                                                'data-dragging': dragging.value === item.field ? 'true' : undefined,
                                                'data-over': over.value === item.field && dragging.value !== item.field ? 'true' : undefined,
                                            },
                                            [
                                                h('span', { class: 'pc-cols-grip', 'aria-hidden': 'true', title: t.dragHint }, '⠿'),
                                                h('label', { class: 'pc-cols-label' }, [
                                                    h('input', {
                                                        type: 'checkbox',
                                                        checked: item.visible,
                                                        disabled: busy.value,
                                                        onChange: (event: Event) => void act(() => crud.setColumnVisible(item.field, (event.target as HTMLInputElement).checked)),
                                                    }),
                                                    item.label,
                                                ]),
                                                h(
                                                    'button',
                                                    {
                                                        type: 'button',
                                                        class: 'pc-cols-move',
                                                        'aria-label': fill(t.moveUp, { label: item.label }),
                                                        title: fill(t.moveUp, { label: item.label }),
                                                        disabled: busy.value || index === 0,
                                                        onClick: () => move(item.field, index - 1),
                                                    },
                                                    [icon('chevronUp', 12)],
                                                ),
                                                h(
                                                    'button',
                                                    {
                                                        type: 'button',
                                                        class: 'pc-cols-move',
                                                        'aria-label': fill(t.moveDown, { label: item.label }),
                                                        title: fill(t.moveDown, { label: item.label }),
                                                        disabled: busy.value || index === rows.length - 1,
                                                        onClick: () => move(item.field, index + 1),
                                                    },
                                                    [icon('chevronDown', 12)],
                                                ),
                                            ],
                                        ),
                                    ),
                                )
                              : null,
                          h(
                              ForgeModal,
                              { modelValue: confirming.value, 'onUpdate:modelValue': (value: boolean) => (confirming.value = value), title: t.resetColumns, size: 'sm' },
                              {
                                  default: () => t.resetConfirm,
                                  footer: () => [
                                      h(ForgeButton, { color: 'dark', flat: true, onClick: () => (confirming.value = false) }, () => t.cancel),
                                      h(ForgeButton, { color: 'danger', onClick: reset }, () => t.resetConfirmButton),
                                  ],
                              },
                          ),
                      ])
                    : null,
            ]);
        };
    },
});

/**
 * The resize handle at the right edge of a header cell (the `th` must be
 * `position: relative`): drag with the pointer, ←/→ from the keyboard,
 * double-click to forget the saved width.
 */
export const PtahCrudColumnResizer = defineComponent({
    name: 'PtahCrudColumnResizer',
    props: { ...pieceProps, field: { type: String, required: true as const }, label: { type: String, required: true as const } },
    setup(props) {
        const t = extrasWords(props.locale);
        const state = usePtahCrud(props.crud);
        const resizer = computed(() => columnResizer(props.crud, props.field));

        return () => {
            const width = state.value.preferences?.column_widths?.[props.field];

            return h('div', {
                role: 'separator',
                'aria-orientation': 'vertical',
                'aria-label': fill(t.resizeColumn, { label: props.label }),
                'aria-valuenow': width ?? undefined,
                'aria-valuemin': 60,
                tabindex: 0,
                style: { position: 'absolute', top: 0, right: '-4px', width: '8px', height: '100%', cursor: 'col-resize', zIndex: 1, touchAction: 'none' },
                onClick: (event: MouseEvent) => event.stopPropagation(),
                onPointerdown: (event: PointerEvent) => resizer.value.pointerDown(event, event.currentTarget as HTMLElement),
                onKeydown: (event: KeyboardEvent) => resizer.value.keyDown(event, event.currentTarget as HTMLElement),
                onDblclick: (event: MouseEvent) => void resizer.value.reset(event.currentTarget as HTMLElement),
            });
        };
    },
});
