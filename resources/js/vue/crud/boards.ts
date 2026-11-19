/**
 * The alternative views of a CRUD screen for Vue — kanban, calendar and
 * cards — and the view/density switch, mirroring react/crud/boards.tsx.
 * Each piece reads the screen through its CrudController; the board and the
 * calendar ask the boards API with the listing state the screen shows.
 */
import { Comment, computed, defineComponent, Fragment, h, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import type { PropType, Ref, VNode, VNodeChild } from 'vue';
import { ApiError, navigateTo } from '../../core';
import type { CrudController, CrudState, Row, RowAction } from '../../core';
import {
    boardsWords,
    CALENDAR_DAY_LIMIT,
    canDrop,
    DENSITIES,
    DENSITY_ICONS,
    densityLabel,
    eventTitle,
    followRowLink,
    getCalendar,
    getKanban,
    isNarrow,
    listKey,
    monthTitle,
    moveCard,
    moveChoices,
    resolveView,
    targetsOf,
    viewLabel,
    viewModes,
    watchNarrow,
    withCardMoved,
} from '../../core/crud/boards';
import type { BoardsWords, CalendarGrid, KanbanBoard, KanbanColumn, ViewMode } from '../../core/crud/boards';
import { ForgeAlert, ForgeButton, ForgeModal, ForgeSpinner } from '../forge';
import { icon } from '../icons';
import { usePtahCrud } from '../usePtahCrud';

const crudProp = { type: Object as PropType<CrudController>, required: true } as const;
const localeProp = { type: String, default: undefined };

const failure = (error: unknown, t: BoardsWords) => (error instanceof ApiError && (error.status === 403 || error.status === 404) && error.message ? error.message : t.loadError);

/**
 * Which view to draw now: the saved mode, with 'auto' decided by the
 * viewport at render time (cards below `md`, the table above; always the
 * table when the server turned `responsiveCards` off) — never saved.
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

            return [
                h(
                    'div',
                    { class: 'pc-seg', role: 'group', 'aria-label': words.viewMode },
                    viewModes(props.crud.features).map((item) =>
                        h(
                            'button',
                            {
                                key: item,
                                type: 'button',
                                class: 'pc-seg-btn',
                                'aria-pressed': mode === item ? 'true' : 'false',
                                'aria-label': viewLabel(words, item),
                                title: viewLabel(words, item),
                                disabled: saving.value,
                                onClick: () => mode !== item && void save({ view_mode: item }),
                            },
                            [icon(VIEW_SVG[item])],
                        ),
                    ),
                ),
                h('div', { class: 'pc-dd-wrap pc-tb-secondary', ref: menu }, [
                    h(
                        'button',
                        {
                            type: 'button',
                            class: 'pc-tbtn',
                            'aria-haspopup': 'true',
                            'aria-expanded': open.value ? 'true' : 'false',
                            'aria-label': words.density,
                            title: words.density,
                            disabled: saving.value,
                            onClick: () => {
                                open.value = !open.value;
                            },
                        },
                        [
                            h('span', { class: 'pc-density-glyph', 'aria-hidden': 'true' }, DENSITY_ICONS[density as keyof typeof DENSITY_ICONS] ?? '☰'),
                            h('span', { class: 'pc-tbtn-label' }, words.density),
                            icon('chevronDown', 12),
                        ],
                    ),
                    open.value
                        ? h(
                              'div',
                              { class: 'pc-dd', style: { minWidth: '11.25rem' } },
                              DENSITIES.map((item) =>
                                  h(
                                      'button',
                                      {
                                          key: item,
                                          type: 'button',
                                          class: `pc-dd-item pc-dd-item--between${density === item ? ' pc-dd-item--active' : ''}`,
                                          'aria-current': density === item ? 'true' : undefined,
                                          onClick: () => {
                                              open.value = false;
                                              if (density !== item) {
                                                  void save({ view_density: item });
                                              }
                                          },
                                      },
                                      [h('span', `${DENSITY_ICONS[item]} ${densityLabel(words, item)}`), density === item ? icon('check') : null],
                                  ),
                              ),
                          )
                        : null,
                ]),
            ];
        };
    },
});

/** The view buttons' icons: the very SVGs of _toolbar.blade.php. */
const VIEW_SVG: Record<ViewMode, string> = { auto: 'viewAuto', table: 'viewTable', cards: 'viewCards', kanban: 'viewKanban', calendar: 'calendar' };

// ── Shared loading state ────────────────────────────────────────────────────

function stateBox(error: string | null, t: BoardsWords, retry: () => void): VNode {
    return error
        ? h('div', { class: 'pc-state', role: 'alert' }, [error, ' ', h(ForgeButton, { color: 'dark', flat: true, size: 'sm', onClick: retry }, () => t.retry)])
        : h('div', { class: 'pc-state' }, [h(ForgeSpinner, { label: t.loading })]);
}

// ── Kanban ──────────────────────────────────────────────────────────────────

/** The board: one column per option, drag and drop and a "Move to" select per card (_kanban.blade.php). */
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
            const note = (text: string) => h('p', { class: 'pc-kanban-note' }, text);

            return h(
                'section',
                {
                    key: col.value,
                    'aria-label': col.label,
                    'data-ptah-kanban-locked': col.locked ? 'true' : undefined,
                    class: ['pc-kanban-col', { 'pc-kanban-col--locked': col.locked, 'pc-kanban-col--over': over.value === col.value }],
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
                    h('header', { class: 'pc-kanban-head' }, [
                        h('h3', { class: 'pc-kanban-ttl' }, [
                            col.locked ? h('i', { class: 'bx bx-lock-alt pc-kanban-lock', role: 'img', title: words.locked, 'aria-label': words.locked }) : null,
                            col.label,
                        ]),
                        h('span', { class: 'pc-kanban-count' }, String(col.total)),
                    ]),
                    h('ul', { class: 'pc-kanban-list' }, [
                        col.cards.length === 0 ? h('li', { class: 'pc-kanban-empty' }, words.emptyColumn) : null,
                        ...col.cards.map((card) => {
                            const id = String(card.id);
                            const movable = current.can_move && targets.length > 0;
                            const selectId = `pc-kanban-move-${id}`;

                            return h(
                                'li',
                                {
                                    key: id,
                                    class: 'pc-kanban-card',
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
                                },
                                [
                                    h('button', { type: 'button', class: 'pc-kanban-open', onClick: () => void props.crud.openEdit(card.id) }, card.title),
                                    card.subtitle !== '' ? h('p', { class: 'pc-kanban-sub' }, card.subtitle) : null,
                                    ...(movable
                                        ? [
                                              h('label', { for: selectId, class: 'pc-sr-only' }, words.moveTo),
                                              h(
                                                  'select',
                                                  {
                                                      id: selectId,
                                                      class: 'pc-kanban-move',
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

            return h('div', { class: 'pc-kanban', 'aria-busy': loading.value ? 'true' : 'false' }, [
                error.value ? h('div', { class: 'pc-kanban-error' }, [h(ForgeAlert, { color: 'danger' }, () => error.value)]) : null,
                ...current.columns.map((col) => column(current, col, words)),
            ]);
        };
    },
});

// ── Calendar ────────────────────────────────────────────────────────────────

/** The month grid: each record on its days (start to end), opened by a click (_calendar.blade.php). */
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

        const nav = (label: string, text: string, onClick: () => void) => h('button', { type: 'button', class: 'pc-cal-btn', 'aria-label': label, title: label, onClick }, text);

        return () => {
            if (!on.value) {
                return null;
            }
            const current = grid.value;
            const words = t.value;
            if (!current) {
                return stateBox(error.value, words, () => void load());
            }

            return h('div', { class: 'pc-cal', 'aria-busy': loading.value ? 'true' : 'false' }, [
                h('header', { class: 'pc-cal-head' }, [
                    nav(words.calendarPrev, '‹', () => void load(-1)),
                    h('div', { class: 'pc-cal-center' }, [
                        h('h3', { class: 'pc-cal-ttl' }, monthTitle(current.month, props.locale)),
                        h(
                            'button',
                            {
                                type: 'button',
                                class: 'pc-cal-btn',
                                onClick: () => {
                                    month = '';
                                    void load();
                                },
                            },
                            words.calendarToday,
                        ),
                    ]),
                    nav(words.calendarNext, '›', () => void load(1)),
                ]),
                error.value ? h(ForgeAlert, { color: 'danger' }, () => error.value) : null,
                current.overflow ? h('div', { class: 'pc-cal-alert' }, [h(ForgeAlert, { color: 'warn' }, () => words.calendarOverflow)]) : null,
                h('div', { class: 'pc-cal-scroll' }, [
                    h('table', { class: 'pc-cal-table' }, [
                        h('thead', [h('tr', words.weekdays.map((weekday) => h('th', { key: weekday, scope: 'col' }, weekday)))]),
                        h(
                            'tbody',
                            current.weeks.map((week) =>
                                h(
                                    'tr',
                                    { key: week[0]?.date },
                                    week.map((day) =>
                                        h('td', { key: day.date, 'data-date': day.date, class: day.in_month ? undefined : 'pc-cal-out' }, [
                                            h('div', { class: 'pc-cal-day', 'aria-current': day.today ? 'date' : undefined }, String(day.day)),
                                            h('ul', { class: 'pc-cal-items' }, [
                                                ...day.items.slice(0, CALENDAR_DAY_LIMIT).map((item) =>
                                                    h('li', { key: String(item.id) }, [
                                                        h('button', { type: 'button', class: 'pc-cal-event', title: eventTitle(item), onClick: () => void props.crud.openEdit(item.id) }, item.title),
                                                    ]),
                                                ),
                                                day.items.length > CALENDAR_DAY_LIMIT ? h('li', { class: 'pc-cal-more' }, `+${day.items.length - CALENDAR_DAY_LIMIT}`) : null,
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

/** True when a slot rendered nothing but comments / empty fragments. */
function isEmptySlot(nodes: VNodeChild): boolean {
    if (nodes === null || nodes === undefined || nodes === false || nodes === '') {
        return true;
    }
    if (Array.isArray(nodes)) {
        return nodes.every((node) => isEmptySlot(node as VNodeChild));
    }
    if (typeof nodes === 'object' && 'type' in nodes) {
        if (nodes.type === Comment) {
            return true;
        }
        if (nodes.type === Fragment) {
            return isEmptySlot(nodes.children as VNodeChild);
        }
    }

    return false;
}

/**
 * The rows of the current page as cards — the same cells and actions as the
 * table (_cards.blade.php). The footer's buttons come from the `actions` slot
 * (`{ row }`) or the `renderActions` prop — pass the table's own row actions;
 * without either the cards draw the configured row actions and edit /
 * duplicate / delete / restore.
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
                navigateTo(action.value);
            } else {
                props.crud.runAction(row, action);
            }
        };

        // The card's own link must not fire under a footer button (@click.stop).
        const act = (cls: string, label: string, name: string, onClick: () => void) =>
            h(
                'button',
                {
                    type: 'button',
                    class: `pc-act ${cls}`,
                    title: label,
                    'aria-label': label,
                    onClick: (event: MouseEvent) => {
                        event.stopPropagation();
                        onClick();
                    },
                },
                [icon(name)],
            );

        const footer = (current: CrudState, row: Row, words: BoardsWords): VNode | null => {
            const custom = slots.actions ? slots.actions({ row }) : props.renderActions ? props.renderActions(row) : undefined;
            if (slots.actions || props.renderActions) {
                // A footer only when the parent gives it something to hold.
                return isEmptySlot(custom as VNodeChild) ? null : h('div', { class: 'pc-card-foot' }, [custom as VNode]);
            }

            const { permissions } = current.schema!;
            // The rules of _cards.blade.php: edit and duplicate outside the
            // trash; in the trash a deleted row offers restore, any other row delete.
            const inTrash = current.list.trashed;
            const restorable = inTrash && row.trashed;
            if (!(permissions.canUpdate || permissions.canDelete || (permissions.canCreate && !inTrash) || row.actions.length > 0)) {
                return null;
            }

            return h('div', { class: 'pc-card-foot' }, [
                ...row.actions.map((action, index) => {
                    const content = action.icon ? h('i', { class: action.icon, 'aria-hidden': 'true' }) : action.label || '→';

                    return action.type === 'link'
                        ? h(
                              'a',
                              {
                                  key: index,
                                  class: 'pc-act pc-act--custom',
                                  'data-color': action.color || undefined,
                                  href: action.value,
                                  title: action.label,
                                  'aria-label': action.label,
                                  onClick: (event: MouseEvent) => {
                                      event.stopPropagation();
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
                              {
                                  key: index,
                                  type: 'button',
                                  class: 'pc-act pc-act--custom',
                                  'data-color': action.color || undefined,
                                  title: action.label,
                                  'aria-label': action.label,
                                  onClick: (event: MouseEvent) => {
                                      event.stopPropagation();
                                      if (action.confirm) {
                                          pending.value = { row, action };
                                      } else {
                                          props.crud.runAction(row, action);
                                      }
                                  },
                              },
                              [content],
                          );
                }),
                permissions.canUpdate && !inTrash ? act('pc-act--edit', words.edit, 'edit', () => void props.crud.openEdit(row.id)) : null,
                permissions.canCreate && !inTrash ? act('pc-act--dup', words.duplicate, 'duplicate', () => void props.crud.duplicate(row.id)) : null,
                restorable
                    ? permissions.canRestore
                        ? act('pc-act--restore', words.restore, 'refresh', () => void props.crud.restore(row.id))
                        : null
                    : permissions.canDelete
                      ? act('pc-act--del', words.delete, 'trash', () => props.crud.askDelete(row.id))
                      : null,
            ]);
        };

        /** The ordering of the cards: a column select and a direction button (_sort-bar.blade.php). */
        const sortBar = (current: CrudState, words: BoardsWords): VNode | null => {
            const sortable = current.schema!.columns.filter((column) => column.sortBy !== null);
            if (sortable.length === 0) {
                return null;
            }
            const ascending = current.list.direction.toUpperCase() === 'ASC';
            const sortId = `pc-cards-sort-${props.crud.screen}`;

            return h('div', { class: 'pc-sortbar', role: 'group', 'aria-label': words.sortGroup }, [
                h('label', { for: sortId, class: 'pc-sr-only' }, words.sortBy),
                icon('sortBar', 16, undefined, 'pc-sortbar-icon'),
                h(
                    'select',
                    { id: sortId, class: 'pc-sortbar-select', title: words.sortBy, value: current.list.sort, onChange: (event: Event) => void props.crud.patchList({ sort: (event.target as HTMLSelectElement).value }) },
                    sortable.map((column) => h('option', { key: column.field, value: column.sortBy! }, column.label)),
                ),
                h(
                    'button',
                    {
                        type: 'button',
                        class: 'pc-sortbar-dir',
                        'aria-pressed': ascending ? 'true' : 'false',
                        'aria-label': ascending ? words.sortAsc : words.sortDesc,
                        title: ascending ? words.sortAsc : words.sortDesc,
                        disabled: current.busy,
                        onClick: () => void props.crud.patchList({ direction: ascending ? 'DESC' : 'ASC' }),
                    },
                    [icon(ascending ? 'sortAsc' : 'sortDesc')],
                ),
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
            const confirming = pending.value;

            return h('div', [
                sortBar(current, words),
                h(
                    'div',
                    { class: 'pc-cards', 'aria-busy': current.busy ? 'true' : 'false' },
                    current.rows.length === 0
                        ? [
                              h('div', { class: 'pc-cards-empty' }, [
                                  h('div', { class: 'pc-empty-state' }, [
                                      h('div', { class: 'pc-empty-box' }, [icon('inbox', 32, 1.5)]),
                                      h('div', [h('p', { class: 'pc-empty-ttl' }, words.emptyTitle), h('p', { class: 'pc-empty-sub' }, words.emptySubtitle)]),
                                  ]),
                              ]),
                          ]
                        : current.rows.map((row) => {
                              const selected = current.selection.includes(String(row.id));
                              const link = row.link ?? null;
                              const follow = link ? (event: MouseEvent | KeyboardEvent) => followRowLink(event, link) : undefined;

                              // configLinkLinha: the card opens its record (Enter when focused).
                              // The row style of the config (a coloured stripe, a tint) — as the table row.
                              return h(
                                  'article',
                                  {
                                      key: row.id,
                                      'aria-label': `#${row.id}`,
                                      class: ['pc-card', { 'pc-card--link': link, 'pc-card--selected': selected }],
                                      tabindex: link ? 0 : undefined,
                                      'data-link': link ?? undefined,
                                      onClick: follow,
                                      onAuxclick: follow,
                                      onKeydown: follow,
                                      style: row.style ?? undefined,
                                  },
                                  [
                                      h('div', { class: 'pc-card-head' }, [
                                          schema.selectable
                                              ? h('input', {
                                                    type: 'checkbox',
                                                    'aria-label': words.selectRow.replace(':id', String(row.id)),
                                                    checked: selected,
                                                    onClick: (event: MouseEvent) => event.stopPropagation(),
                                                    onChange: () => props.crud.toggleRow(row.id),
                                                })
                                              : null,
                                          h('div', { class: 'pc-card-heading' }, [
                                              // Rendered and escaped by the server — the table's own cell.
                                              titleColumn ? h('p', { class: 'pc-card-ttl', innerHTML: row.cells[titleColumn.field]?.html ?? '' }) : null,
                                              h('p', { class: 'pc-card-id' }, `#${row.id}`),
                                          ]),
                                      ]),
                                      h(
                                          'dl',
                                          { class: 'pc-card-body' },
                                          bodyColumns.map((column) =>
                                              h('div', { key: column.field, class: 'pc-card-row' }, [h('dt', column.label), h('dd', { innerHTML: row.cells[column.field]?.html ?? '' })]),
                                          ),
                                      ),
                                      footer(current, row, words),
                                  ],
                              );
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
