/**
 * The alternative views of a CRUD screen for Vue — kanban, calendar and
 * cards — and the view/density switch, mirroring react/crud/boards.tsx.
 * Each piece reads the screen through its CrudController; the board and the
 * calendar ask the boards API with the listing state the screen shows.
 */
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import type { PropType, Ref, VNode, VNodeChild } from 'vue';
import { ApiError } from '../../core';
import type { CrudController, CrudState, Row, RowAction } from '../../core';
import {
    boardsWords,
    CALENDAR_DAY_LIMIT,
    canDrop,
    DENSITIES,
    DENSITY_ICONS,
    densityLabel,
    eventTitle,
    getCalendar,
    getKanban,
    isNarrow,
    listKey,
    monthTitle,
    moveCard,
    moveChoices,
    resolveView,
    SR_ONLY,
    targetsOf,
    VIEW_ICONS,
    viewLabel,
    viewModes,
    watchNarrow,
    withCardMoved,
} from '../../core/crud/boards';
import type { BoardsWords, CalendarGrid, KanbanBoard, KanbanColumn, ViewMode } from '../../core/crud/boards';
import { ForgeAlert, ForgeButton, ForgeEmpty, ForgeModal, ForgeSpinner } from '../forge';
import { usePtahCrud } from '../usePtahCrud';

const crudProp = { type: Object as PropType<CrudController>, required: true } as const;
const localeProp = { type: String, default: undefined };

const failure = (error: unknown, t: BoardsWords) => (error instanceof ApiError && (error.status === 403 || error.status === 404) && error.message ? error.message : t.loadError);

const muted = { color: 'var(--pc-text-muted)', fontSize: '0.75rem' };

/**
 * Which view to draw now: the saved mode, with 'auto' decided by the
 * viewport at render time (cards below `md`, the table above) — never saved.
 */
export function useResolvedView(crud: CrudController): Ref<Exclude<ViewMode, 'auto'>> {
    const state = usePtahCrud(crud);
    const narrow = ref(isNarrow());
    const stop = watchNarrow((value) => {
        narrow.value = value;
    });
    onBeforeUnmount(stop);

    return computed(() => resolveView(state.value.preferences?.view_mode, crud.features, narrow.value));
}

// ── View switch ─────────────────────────────────────────────────────────────

/** Table / cards / board / calendar, and the density — saved in the user's preferences. */
export const PtahCrudViewSwitch = defineComponent({
    name: 'PtahCrudViewSwitch',
    props: { crud: crudProp, locale: localeProp },
    setup(props) {
        const state = usePtahCrud(props.crud);
        const t = computed(() => boardsWords(props.locale));
        const saving = ref(false);
        const open = ref(false);
        const menu = ref<HTMLElement | null>(null);

        const close = (event: MouseEvent | KeyboardEvent) => {
            if (!open.value) {
                return;
            }
            if (event instanceof KeyboardEvent ? event.key === 'Escape' : !menu.value?.contains(event.target as Node)) {
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

        const save = async (changes: { view_mode?: string; view_density?: string }) => {
            saving.value = true;
            try {
                await props.crud.savePreferences(changes);
            } finally {
                saving.value = false;
            }
        };

        return () => {
            if (!state.value.schema) {
                return null;
            }
            const words = t.value;
            const mode = state.value.preferences?.view_mode ?? 'auto';
            const density = state.value.preferences?.view_density ?? 'global';

            return h('div', { style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } }, [
                h(
                    'div',
                    { role: 'group', 'aria-label': words.viewMode, style: { display: 'inline-flex', gap: '2px' } },
                    viewModes(props.crud.features).map((item) =>
                        h(
                            'button',
                            {
                                key: item,
                                type: 'button',
                                class: `pc-btn${mode === item ? ' pc-btn--active' : ''}`,
                                'aria-pressed': mode === item ? 'true' : 'false',
                                'aria-label': viewLabel(words, item),
                                title: viewLabel(words, item),
                                disabled: saving.value,
                                onClick: () => mode !== item && void save({ view_mode: item }),
                            },
                            [h('span', { 'aria-hidden': 'true' }, VIEW_ICONS[item])],
                        ),
                    ),
                ),
                h('div', { class: 'pc-menu', ref: menu }, [
                    h(
                        'button',
                        {
                            type: 'button',
                            class: 'pc-btn',
                            'aria-haspopup': 'true',
                            'aria-expanded': open.value ? 'true' : 'false',
                            'aria-label': words.density,
                            title: words.density,
                            disabled: saving.value,
                            onClick: () => {
                                open.value = !open.value;
                            },
                        },
                        [h('span', { 'aria-hidden': 'true' }, DENSITY_ICONS[density as keyof typeof DENSITY_ICONS] ?? '☰'), h('span', words.density), ' ▾'],
                    ),
                    open.value
                        ? h(
                              'ul',
                              { class: 'pc-menu-list' },
                              DENSITIES.map((item) =>
                                  h('li', { key: item }, [
                                      h(
                                          'button',
                                          {
                                              type: 'button',
                                              class: 'pc-menu-item',
                                              'aria-current': density === item ? 'true' : undefined,
                                              style: density === item ? { fontWeight: 600, color: 'var(--pc-primary)' } : undefined,
                                              onClick: () => {
                                                  open.value = false;
                                                  if (density !== item) {
                                                      void save({ view_density: item });
                                                  }
                                              },
                                          },
                                          [
                                              h('span', { 'aria-hidden': 'true' }, DENSITY_ICONS[item]),
                                              ` ${densityLabel(words, item)}`,
                                              density === item ? h('span', { 'aria-hidden': 'true', style: { marginLeft: 'auto' } }, '✓') : null,
                                          ],
                                      ),
                                  ]),
                              ),
                          )
                        : null,
                ]),
            ]);
        };
    },
});

// ── Shared loading state ────────────────────────────────────────────────────

function stateBox(error: string | null, t: BoardsWords, retry: () => void): VNode {
    return error
        ? h('div', { class: 'pc-state', role: 'alert' }, [error, ' ', h(ForgeButton, { color: 'dark', flat: true, size: 'sm', onClick: retry }, () => t.retry)])
        : h('div', { class: 'pc-state' }, [h(ForgeSpinner, { label: t.loading })]);
}

// ── Kanban ──────────────────────────────────────────────────────────────────

/** The board: one column per option, drag and drop and a "Move to" select per card. */
export const PtahCrudKanban = defineComponent({
    name: 'PtahCrudKanban',
    props: { crud: crudProp, locale: localeProp },
    setup(props) {
        const state = usePtahCrud(props.crud);
        const t = computed(() => boardsWords(props.locale));
        const on = computed(() => state.value.schema?.features.kanban ?? false);
        const board = shallowRef<KanbanBoard | null>(null);
        const error = ref<string | null>(null);
        const loading = ref(false);
        const drag = ref<string | null>(null);
        const over = ref<string | null>(null);
        let seq = 0;

        const load = async () => {
            const mine = ++seq;
            loading.value = true;
            try {
                const next = await getKanban(props.crud.api, props.crud.screen, props.crud.getState().list);
                if (mine === seq) {
                    board.value = next;
                    error.value = null;
                }
            } catch (reason) {
                if (mine === seq) {
                    error.value = failure(reason, t.value);
                }
            } finally {
                if (mine === seq) {
                    loading.value = false;
                }
            }
        };

        // Again when the filters change or the listing reloads (a save, a delete).
        watch(
            () => [on.value, listKey(state.value.list), state.value.rows] as const,
            () => {
                if (on.value) {
                    void load();
                }
            },
            { immediate: true },
        );

        const move = async (id: string, from: string, to: string) => {
            const before = board.value;
            if (!before || !canDrop(before, from, to)) {
                return;
            }
            // Drawn at once; a refusal puts the card back where it was.
            ++seq;
            board.value = withCardMoved(before, id, from, to);
            try {
                const result = await moveCard(props.crud.api, props.crud.screen, id, to);
                result.messages.forEach((message) => props.crud.toast(message.text, message.level));
                if (!result.ok) {
                    board.value = before;
                    if (result.messages.length === 0) {
                        props.crud.toast(t.value.moveNotAllowed, 'warn');
                    }

                    return;
                }
                // The listing reloads, and with it the board (counts, order).
                await props.crud.reload();
            } catch (reason) {
                board.value = before;
                props.crud.toast(reason instanceof Error && reason.message ? reason.message : t.value.moveNotAllowed, 'danger');
            }
        };

        const column = (current: KanbanBoard, col: KanbanColumn, words: BoardsWords): VNode => {
            const targets = targetsOf(current, col.value);
            const choices = moveChoices(current, col);
            const droppable = current.can_move && !col.other && !col.locked;
            const accepts = () => canDrop(current, drag.value, col.value);
            const note = (text: string) => h('p', { style: { ...muted, margin: 0, padding: '0 12px 8px' } }, text);

            return h(
                'section',
                {
                    key: col.value,
                    'aria-label': col.label,
                    'data-ptah-kanban-locked': col.locked ? 'true' : undefined,
                    style: {
                        display: 'flex',
                        flexDirection: 'column',
                        flex: '0 0 18rem',
                        border: '1px solid var(--pc-line)',
                        borderRadius: 'var(--pc-radius)',
                        background: 'var(--pc-bg-soft)',
                        outline: over.value === col.value ? '2px solid var(--pc-primary)' : undefined,
                        outlineOffset: '-2px',
                    },
                    onDragover: droppable
                        ? (event: DragEvent) => {
                              if (accepts()) {
                                  event.preventDefault();
                                  over.value = col.value;
                              }
                          }
                        : undefined,
                    onDragleave: droppable
                        ? () => {
                              over.value = null;
                          }
                        : undefined,
                    onDrop: droppable
                        ? (event: DragEvent) => {
                              event.preventDefault();
                              over.value = null;
                              const from = drag.value;
                              if (accepts() && from !== null) {
                                  void move(event.dataTransfer?.getData('text/plain') ?? '', from, col.value);
                              }
                          }
                        : undefined,
                },
                [
                    h('header', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '8px 12px', borderBottom: '1px solid var(--pc-line)', background: 'var(--pc-bg)' } }, [
                        h('h3', { style: { margin: 0, fontSize: '0.875rem', fontWeight: 600, color: 'var(--pc-text-strong)' } }, [
                            col.locked ? h('span', { title: words.locked, 'aria-label': words.locked, role: 'img', style: { marginRight: '4px' } }, '🔒') : null,
                            col.label,
                        ]),
                        h('span', { style: { ...muted, fontVariantNumeric: 'tabular-nums' } }, String(col.total)),
                    ]),
                    h('ul', { style: { display: 'flex', flexDirection: 'column', gap: '8px', margin: 0, padding: '8px', listStyle: 'none', minHeight: '64px' } }, [
                        col.cards.length === 0 ? h('li', { style: { ...muted, padding: '12px 4px', textAlign: 'center' } }, words.emptyColumn) : null,
                        ...col.cards.map((card) => {
                            const id = String(card.id);
                            const movable = current.can_move && targets.length > 0;
                            const selectId = `pc-kanban-move-${id}`;

                            return h(
                                'li',
                                {
                                    key: id,
                                    draggable: movable ? 'true' : undefined,
                                    onDragstart: movable
                                        ? (event: DragEvent) => {
                                              drag.value = col.value;
                                              event.dataTransfer?.setData('text/plain', id);
                                          }
                                        : undefined,
                                    onDragend: movable
                                        ? () => {
                                              drag.value = null;
                                              over.value = null;
                                          }
                                        : undefined,
                                    style: { padding: '8px', border: '1px solid var(--pc-line)', borderRadius: 'var(--pc-radius)', background: 'var(--pc-bg)', fontSize: '0.875rem', opacity: col.locked ? 0.75 : 1 },
                                },
                                [
                                    h('button', { type: 'button', class: 'pc-th-btn', style: { display: 'block', width: '100%', fontWeight: 500, color: 'var(--pc-text)' }, onClick: () => void props.crud.openEdit(card.id) }, card.title),
                                    card.subtitle !== '' ? h('p', { style: { ...muted, margin: '2px 0 0', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } }, card.subtitle) : null,
                                    ...(movable
                                        ? [
                                              h('label', { for: selectId, style: SR_ONLY }, words.moveTo),
                                              h(
                                                  'select',
                                                  {
                                                      id: selectId,
                                                      class: 'pc-input',
                                                      style: { marginTop: '8px', padding: '4px 6px', fontSize: '0.75rem' },
                                                      value: col.other ? '' : col.value,
                                                      onChange: (event: Event) => {
                                                          const target = event.target as HTMLSelectElement;
                                                          const to = target.value;
                                                          // Back to the current column until the board says otherwise.
                                                          target.value = col.other ? '' : col.value;
                                                          void move(id, col.value, to);
                                                      },
                                                  },
                                                  [
                                                      col.other ? h('option', { value: '', disabled: true }, `${words.moveTo}…`) : null,
                                                      ...choices.map((option) => h('option', { key: option.value, value: option.value }, `${words.moveTo}: ${option.label}`)),
                                                  ],
                                              ),
                                          ]
                                        : []),
                                ],
                            );
                        }),
                    ]),
                    col.locked && current.locked_message !== '' ? note(current.locked_message) : null,
                    col.other ? note(words.otherHint) : null,
                    col.total > col.cards.length ? note(words.more.replace(':count', String(col.total - col.cards.length))) : null,
                ],
            );
        };

        return () => {
            if (!on.value) {
                return null;
            }
            const current = board.value;
            const words = t.value;
            if (!current) {
                return stateBox(error.value, words, () => void load());
            }

            return h('div', { 'aria-busy': loading.value ? 'true' : 'false', style: { display: 'flex', gap: '12px', overflowX: 'auto', paddingBottom: '8px', opacity: loading.value ? 0.7 : 1 } }, [
                error.value ? h('div', { style: { flex: '0 0 100%' } }, [h(ForgeAlert, { color: 'danger' }, () => error.value)]) : null,
                ...current.columns.map((col) => column(current, col, words)),
            ]);
        };
    },
});

// ── Calendar ────────────────────────────────────────────────────────────────

/** The month grid: each record on its days (start to end), opened by a click. */
export const PtahCrudCalendar = defineComponent({
    name: 'PtahCrudCalendar',
    props: { crud: crudProp, locale: localeProp },
    setup(props) {
        const state = usePtahCrud(props.crud);
        const t = computed(() => boardsWords(props.locale));
        const on = computed(() => state.value.schema?.features.calendar ?? false);
        const grid = shallowRef<CalendarGrid | null>(null);
        const error = ref<string | null>(null);
        const loading = ref(false);
        // The month shown — '' is the current one. It lives here, not on the server.
        let month = '';
        let seq = 0;

        const load = async (shift = 0) => {
            const mine = ++seq;
            loading.value = true;
            try {
                const next = await getCalendar(props.crud.api, props.crud.screen, props.crud.getState().list, month, shift);
                if (mine === seq) {
                    month = month === '' && shift === 0 ? '' : next.month;
                    grid.value = next;
                    error.value = null;
                }
            } catch (reason) {
                if (mine === seq) {
                    error.value = failure(reason, t.value);
                }
            } finally {
                if (mine === seq) {
                    loading.value = false;
                }
            }
        };

        watch(
            () => [on.value, listKey(state.value.list), state.value.rows] as const,
            () => {
                if (on.value) {
                    void load();
                }
            },
            { immediate: true },
        );

        const nav = (label: string, text: string, onClick: () => void) => h(ForgeButton, { color: 'dark', flat: true, size: 'sm', 'aria-label': label, title: label, onClick }, () => text);

        return () => {
            if (!on.value) {
                return null;
            }
            const current = grid.value;
            const words = t.value;
            if (!current) {
                return stateBox(error.value, words, () => void load());
            }

            return h('div', { 'aria-busy': loading.value ? 'true' : 'false', style: { border: '1px solid var(--pc-line)', borderRadius: 'var(--pc-radius)', background: 'var(--pc-bg)', opacity: loading.value ? 0.7 : 1 } }, [
                h('header', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '8px', padding: '8px 12px', borderBottom: '1px solid var(--pc-line)', background: 'var(--pc-bg-soft)' } }, [
                    nav(words.calendarPrev, '‹', () => void load(-1)),
                    h('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, [
                        h('h3', { style: { margin: 0, fontSize: '0.875rem', fontWeight: 600, textTransform: 'capitalize', color: 'var(--pc-text-strong)' } }, monthTitle(current.month, props.locale)),
                        h(
                            ForgeButton,
                            {
                                color: 'dark',
                                flat: true,
                                size: 'sm',
                                onClick: () => {
                                    month = '';
                                    void load();
                                },
                            },
                            () => words.calendarToday,
                        ),
                    ]),
                    nav(words.calendarNext, '›', () => void load(1)),
                ]),
                error.value ? h(ForgeAlert, { color: 'danger' }, () => error.value) : null,
                current.overflow ? h('div', { style: { padding: '8px 12px 0' } }, [h(ForgeAlert, { color: 'warn' }, () => words.calendarOverflow)]) : null,
                h('div', { style: { overflowX: 'auto' } }, [
                    h('table', { style: { width: '100%', minWidth: '640px', tableLayout: 'fixed', borderCollapse: 'collapse', fontSize: '0.875rem' } }, [
                        h('thead', [h('tr', words.weekdays.map((weekday) => h('th', { key: weekday, scope: 'col', style: { ...muted, padding: '6px 8px', fontWeight: 600, textAlign: 'left' } }, weekday)))]),
                        h(
                            'tbody',
                            current.weeks.map((week) =>
                                h(
                                    'tr',
                                    { key: week[0]?.date },
                                    week.map((day) =>
                                        h('td', { key: day.date, 'data-date': day.date, style: { verticalAlign: 'top', height: '96px', padding: '4px', borderTop: '1px solid var(--pc-line)', background: day.in_month ? undefined : 'var(--pc-bg-soft)' } }, [
                                            h(
                                                'div',
                                                {
                                                    'aria-current': day.today ? 'date' : undefined,
                                                    style: {
                                                        marginBottom: '4px',
                                                        fontSize: '0.75rem',
                                                        fontVariantNumeric: 'tabular-nums',
                                                        color: day.today ? 'var(--pc-primary)' : day.in_month ? 'var(--pc-text)' : 'var(--pc-text-muted)',
                                                        fontWeight: day.today ? 700 : undefined,
                                                    },
                                                },
                                                String(day.day),
                                            ),
                                            h('ul', { style: { display: 'flex', flexDirection: 'column', gap: '2px', margin: 0, padding: 0, listStyle: 'none' } }, [
                                                ...day.items.slice(0, CALENDAR_DAY_LIMIT).map((item) =>
                                                    h('li', { key: String(item.id) }, [
                                                        h(
                                                            'button',
                                                            {
                                                                type: 'button',
                                                                class: 'pc-th-btn',
                                                                title: eventTitle(item),
                                                                style: { display: 'block', width: '100%', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', padding: '0 4px', borderRadius: '4px', fontSize: '0.75rem', background: 'var(--pc-primary-soft)', color: 'var(--pc-text-strong)' },
                                                                onClick: () => void props.crud.openEdit(item.id),
                                                            },
                                                            item.title,
                                                        ),
                                                    ]),
                                                ),
                                                day.items.length > CALENDAR_DAY_LIMIT ? h('li', { style: muted }, `+${day.items.length - CALENDAR_DAY_LIMIT}`) : null,
                                            ]),
                                        ]),
                                    ),
                                ),
                            ),
                        ),
                    ]),
                ]),
            ]);
        };
    },
});

// ── Cards ───────────────────────────────────────────────────────────────────

/**
 * The rows of the current page as cards — the same cells and actions as the
 * table. The footer's buttons come from the `actions` slot (`{ row }`) or the
 * `renderActions` prop — pass the table's own row actions; without either
 * the cards draw the configured row actions and edit / duplicate / delete /
 * restore.
 */
export const PtahCrudCards = defineComponent({
    name: 'PtahCrudCards',
    props: {
        crud: crudProp,
        locale: localeProp,
        renderActions: { type: Function as PropType<(row: Row) => VNodeChild>, default: undefined },
    },
    setup(props, { slots }) {
        const state = usePtahCrud(props.crud);
        const t = computed(() => boardsWords(props.locale));
        const pending = shallowRef<{ row: Row; action: RowAction } | null>(null);

        const run = (row: Row, action: RowAction) => {
            if (action.type === 'link') {
                window.location.assign(action.value);
            } else {
                props.crud.runAction(row, action);
            }
        };

        const footer = (current: CrudState, row: Row, words: BoardsWords): VNode | null => {
            const style = { display: 'flex', alignItems: 'center', justifyContent: 'flex-end', flexWrap: 'wrap', gap: '4px', paddingTop: '12px', marginTop: '12px', borderTop: '1px solid var(--pc-line)' };
            const custom = slots.actions ? slots.actions({ row }) : props.renderActions ? props.renderActions(row) : undefined;
            if (custom !== undefined) {
                return h('div', { style }, [custom as VNode]);
            }

            const { permissions } = current.schema!;
            // The rules of _cards.blade.php: edit and duplicate outside the
            // trash; in the trash a deleted row offers restore, any other row delete.
            const inTrash = current.list.trashed;
            const restorable = inTrash && row.trashed;
            if (!(permissions.canUpdate || permissions.canDelete || (permissions.canCreate && !inTrash) || row.actions.length > 0)) {
                return null;
            }

            return h('div', { style }, [
                ...row.actions.map((action, index) => {
                    const content = action.icon ? h('i', { class: action.icon, 'aria-hidden': 'true' }) : action.label || '→';

                    return action.type === 'link'
                        ? h(
                              'a',
                              {
                                  key: index,
                                  class: 'pc-icon-btn',
                                  href: action.value,
                                  title: action.label,
                                  'aria-label': action.label,
                                  onClick: (event: MouseEvent) => {
                                      if (action.confirm) {
                                          event.preventDefault();
                                          pending.value = { row, action };
                                      }
                                  },
                              },
                              [content],
                          )
                        : h(
                              'button',
                              { key: index, type: 'button', class: 'pc-icon-btn', title: action.label, 'aria-label': action.label, onClick: () => (action.confirm ? (pending.value = { row, action }) : props.crud.runAction(row, action)) },
                              [content],
                          );
                }),
                permissions.canUpdate && !inTrash ? h('button', { type: 'button', class: 'pc-icon-btn', title: words.edit, onClick: () => void props.crud.openEdit(row.id) }, words.edit) : null,
                permissions.canCreate && !inTrash ? h('button', { type: 'button', class: 'pc-icon-btn', title: words.duplicate, onClick: () => void props.crud.duplicate(row.id) }, words.duplicate) : null,
                restorable
                    ? permissions.canRestore
                        ? h('button', { type: 'button', class: 'pc-icon-btn', title: words.restore, onClick: () => void props.crud.restore(row.id) }, words.restore)
                        : null
                    : permissions.canDelete
                      ? h('button', { type: 'button', class: 'pc-icon-btn pc-icon-btn--danger', title: words.delete, onClick: () => props.crud.askDelete(row.id) }, words.delete)
                      : null,
            ]);
        };

        return () => {
            const current = state.value;
            const schema = current.schema;
            if (!schema) {
                return null;
            }
            const words = t.value;
            // The title is the first column that is not the id; the rest are the body.
            const [titleColumn, ...bodyColumns] = schema.columns.filter((column) => column.field !== 'id');
            const sortable = schema.columns.filter((column) => column.sortBy !== null);
            const ascending = current.list.direction.toUpperCase() === 'ASC';
            const sortId = `pc-cards-sort-${props.crud.screen}`;
            const confirming = pending.value;

            return h('div', [
                sortable.length > 0
                    ? h('div', { role: 'group', 'aria-label': words.sortGroup, style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '12px' } }, [
                          h('label', { for: sortId, style: SR_ONLY }, words.sortBy),
                          h(
                              'select',
                              { id: sortId, class: 'pc-input', style: { flex: 1, minWidth: 0 }, title: words.sortBy, value: current.list.sort, onChange: (event: Event) => void props.crud.patchList({ sort: (event.target as HTMLSelectElement).value }) },
                              sortable.map((column) => h('option', { key: column.field, value: column.sortBy! }, column.label)),
                          ),
                          h(
                              'button',
                              {
                                  type: 'button',
                                  class: 'pc-btn',
                                  'aria-pressed': ascending ? 'true' : 'false',
                                  'aria-label': ascending ? words.sortAsc : words.sortDesc,
                                  title: ascending ? words.sortAsc : words.sortDesc,
                                  disabled: current.busy,
                                  onClick: () => void props.crud.patchList({ direction: ascending ? 'DESC' : 'ASC' }),
                              },
                              [h('span', { 'aria-hidden': 'true' }, ascending ? '▲' : '▼')],
                          ),
                      ])
                    : null,
                current.rows.length === 0
                    ? h('div', { class: 'pc-state' }, [h(ForgeEmpty, { title: words.emptyTitle, description: words.emptySubtitle })])
                    : h(
                          'div',
                          { 'aria-busy': current.busy ? 'true' : 'false', style: { display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '12px', opacity: current.busy ? 0.6 : 1 } },
                          current.rows.map((row) => {
                              const selected = current.selection.includes(String(row.id));
                              const base = {
                                  display: 'flex',
                                  flexDirection: 'column',
                                  padding: '16px',
                                  border: '1px solid var(--pc-line)',
                                  borderRadius: 'var(--pc-radius)',
                                  background: selected ? 'var(--pc-primary-soft)' : 'var(--pc-bg)',
                                  outline: selected ? '2px solid var(--pc-primary)' : undefined,
                              };

                              return h('article', { key: row.id, 'aria-label': `#${row.id}`, style: [base, row.style ?? ''] }, [
                                  h('div', { style: { display: 'flex', alignItems: 'flex-start', gap: '10px', marginBottom: '12px' } }, [
                                      schema.selectable ? h('input', { type: 'checkbox', 'aria-label': words.selectRow.replace(':id', String(row.id)), checked: selected, onChange: () => props.crud.toggleRow(row.id) }) : null,
                                      h('div', { style: { minWidth: 0, flex: 1 } }, [
                                          // Rendered and escaped by the server — the table's own cell.
                                          titleColumn
                                              ? h('p', { style: { margin: 0, fontWeight: 600, fontSize: '0.875rem', color: 'var(--pc-text-strong)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }, innerHTML: row.cells[titleColumn.field]?.html ?? '' })
                                              : null,
                                          h('p', { style: { ...muted, margin: 0, fontSize: '0.6875rem' } }, `#${row.id}`),
                                      ]),
                                  ]),
                                  h(
                                      'dl',
                                      { style: { flex: 1, margin: 0 } },
                                      bodyColumns.map((column) =>
                                          h('div', { key: column.field, style: { display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', gap: '12px', fontSize: '0.75rem', marginBottom: '6px' } }, [
                                              h('dt', { style: { ...muted, flexShrink: 0, fontSize: '0.625rem', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.03em' } }, column.label),
                                              h('dd', { style: { margin: 0, textAlign: 'right', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', color: 'var(--pc-text)' }, innerHTML: row.cells[column.field]?.html ?? '' }),
                                          ]),
                                      ),
                                  ),
                                  footer(current, row, words),
                              ]);
                          }),
                      ),
                h(
                    ForgeModal,
                    {
                        modelValue: confirming !== null,
                        'onUpdate:modelValue': (value: boolean) => {
                            if (!value) {
                                pending.value = null;
                            }
                        },
                        title: confirming?.action.label ?? '',
                        size: 'sm',
                    },
                    {
                        default: () => confirming?.action.confirm ?? '',
                        footer: () => [
                            h(
                                ForgeButton,
                                {
                                    color: 'dark',
                                    flat: true,
                                    onClick: () => {
                                        pending.value = null;
                                    },
                                },
                                () => words.cancel,
                            ),
                            h(
                                ForgeButton,
                                {
                                    onClick: () => {
                                        const chosen = pending.value;
                                        pending.value = null;
                                        if (chosen) {
                                            run(chosen.row, chosen.action);
                                        }
                                    },
                                },
                                () => words.confirm,
                            ),
                        ],
                    },
                ),
            ]);
        };
    },
});
