/**
 * The filter panel of a CRUD screen for Vue — the pieces of
 * _filter-panel.blade.php, the filter chips and the link-filters banner of
 * the Livewire screen, and the recent searches. Line for line the React
 * pieces (react/crud/filters.tsx); the rules are in core/crud/filters.ts and
 * the filtering itself is the server's (the BaseCrud engine).
 */
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import type { PropType, ShallowRef, VNodeChild } from 'vue';
import { debounce } from '../../core';
import type { CrudController, CrudState } from '../../core';
import {
    advancedKind,
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
import type { AdvancedRow, FilterListState, FilterLookupState, FilterMetaItem, FilterSession, FilterSessionState, FiltersMeta, FiltersWords, UrlFilter } from '../../core/crud/filters';
import { ForgeModal } from '../forge';
import { usePtahCrud } from '../usePtahCrud';

export { applyUrlFilters, filterByCell, isCellFilterable } from '../../core/crud/filters';

const pieceProps = {
    crud: { type: Object as PropType<CrudController>, required: true as const },
    /** `pt_BR`, `en`… Defaults to the page's `<html lang>`. */
    locale: { type: String, default: undefined },
};

const text = (value: unknown) => (value === undefined || value === null ? '' : String(value));
const valueOf = (event: Event) => (event.target as HTMLInputElement | HTMLSelectElement).value;

let ids = 0;
const uid = (prefix: string) => `${prefix}-${++ids}`;

/** The screen's filter session as a ref, loaded once the screen is. */
function useFilterSession(crud: CrudController, state: ShallowRef<CrudState>): { session: FilterSession; filters: ShallowRef<FilterSessionState> } {
    const session = filterSession(crud);
    const filters = shallowRef(session.getState());
    const stop = session.subscribe(() => {
        filters.value = session.getState();
    });
    onBeforeUnmount(stop);
    watch(
        () => state.value.status,
        (status) => {
            if (status === 'ready') {
                void session.ensure();
            }
        },
        { immediate: true },
    );

    return { session, filters };
}

/** Closes on a click outside the element. */
function useOutside(root: ShallowRef<HTMLElement | null>, onOutside: () => void) {
    const close = (event: MouseEvent) => {
        if (root.value && !root.value.contains(event.target as Node)) {
            onOutside();
        }
    };
    onMounted(() => document.addEventListener('mousedown', close));
    onBeforeUnmount(() => document.removeEventListener('mousedown', close));
}

const removeButton = (label: string, onClick: () => void) => h('button', { type: 'button', style: s.chipButton, 'aria-label': label, title: label, onClick }, [h('span', { 'aria-hidden': 'true' }, '×')]);

const option = (value: string, label: string) => h('option', { key: value, value }, label);

// ── The panel ───────────────────────────────────────────────────────────────

interface ControlProps {
    crud: CrudController;
    session: FilterSession;
    item: FilterMetaItem;
    list: FilterListState;
    labels: Record<string, string>;
    meta: FiltersMeta;
    t: FiltersWords;
}

const controlProps = {
    crud: { type: Object as PropType<CrudController>, required: true as const },
    session: { type: Object as PropType<FilterSession>, required: true as const },
    item: { type: Object as PropType<FilterMetaItem>, required: true as const },
    list: { type: Object as PropType<FilterListState>, required: true as const },
    labels: { type: Object as PropType<Record<string, string>>, required: true as const },
    meta: { type: Object as PropType<FiltersMeta>, required: true as const },
    t: { type: Object as PropType<FiltersWords>, required: true as const },
};

/**
 * The complete filter panel: every filterable column with its operator,
 * the date ranges, the quick dates, the searchdropdown filters with their
 * cascade, the custom filters, the advanced search and the saved filters.
 */
export const PtahCrudFilterPanel = defineComponent({
    name: 'PtahCrudFilterPanel',
    props: {
        ...pieceProps,
        /** Shows a close button in the panel's header. */
        onClose: { type: Function as PropType<() => void>, default: undefined },
    },
    setup(props) {
        const crud = props.crud;
        const state = usePtahCrud(crud);
        const { session, filters } = useFilterSession(crud, state);
        const t = computed(() => filtersWords(props.locale));
        const titleId = uid('pc-filters-title');
        const quickId = uid('pc-filters-quick');

        return () => {
            const words = t.value;
            if (state.value.status !== 'ready' || !state.value.schema) {
                return null;
            }

            if (filters.value.status === 'error') {
                return h('section', { style: s.panel, 'aria-labelledby': titleId }, [
                    h('div', { style: s.body }, [
                        h('h3', { style: s.title, id: titleId }, words.title),
                        h('p', { role: 'alert', style: s.error }, `${words.loadError} ${filters.value.error ?? ''}`),
                        h('div', [h('button', { type: 'button', class: 'pc-btn', onClick: () => void session.ensure() }, words.retry)]),
                    ]),
                ]);
            }

            const meta = filters.value.meta;
            if (!meta) {
                return h('div', { style: s.panel }, [h('p', { role: 'status', style: s.note }, words.loading)]);
            }

            const list = state.value.list as FilterListState;
            const advanced = meta.advanced.enabled && meta.advanced.fields.length > 0;

            if (meta.filters.length === 0 && !meta.quick_dates.enabled && !advanced) {
                return null;
            }

            const count = buildBadges(list, meta, filters.value.labels, words).length;

            return h('section', { style: s.panel, 'aria-labelledby': titleId }, [
                h('div', { style: s.head }, [
                    h('div', { style: s.headSide }, [h('h3', { style: s.title, id: titleId }, words.title), count > 0 ? h('span', { style: s.count }, words.active(count)) : null]),
                    h('div', { style: s.headSide }, [
                        h('button', { type: 'button', class: 'pc-btn', onClick: () => void clearAllFilters(crud, session) }, words.clearAll),
                        props.onClose ? h('button', { type: 'button', class: 'pc-icon-btn', 'aria-label': words.close, title: words.close, onClick: () => props.onClose?.() }, '✕') : null,
                    ]),
                ]),
                h('div', { style: s.body }, [
                    meta.quick_dates.enabled ? quickDates(quickId, crud, meta, list, words) : null,
                    meta.filters.length > 0
                        ? h(
                              'div',
                              { style: s.grid },
                              meta.filters.map((item) => h(FilterControl, { key: `${item.custom ? 'c' : 'f'}-${item.field}`, crud, session, item, list, labels: filters.value.labels, meta, t: words })),
                          )
                        : null,
                    advanced ? h(AdvancedSearch, { crud, meta, list, t: words }) : null,
                    h(SavedFilters, { crud, state: state.value, t: words }),
                ]),
                h(SaveFooter, { crud, t: words }),
            ]);
        };
    },
});

const FilterControl = defineComponent({
    name: 'PtahCrudFilterControl',
    props: controlProps,
    setup(props) {
        return () => {
            const { item } = props;
            if (item.kind === 'searchdropdown') {
                return h(DropdownFilter, { ...props });
            }
            if (item.kind === 'date' && !item.custom) {
                return dateRange(props);
            }
            if (item.kind === 'select') {
                return h(SelectFilter, { ...props });
            }

            return h(ValueFilter, { ...props });
        };
    },
});

function quickDates(id: string, crud: CrudController, meta: FiltersMeta, list: FilterListState, t: FiltersWords) {
    return h('div', { role: 'group', 'aria-labelledby': id }, [
        h('p', { id, style: s.section }, t.dateShortcuts),
        h(
            'div',
            { style: s.wrap },
            meta.quick_dates.periods.map((period) =>
                h('button', { key: period, type: 'button', class: `pc-btn${list.quick_date === period ? ' pc-btn--active' : ''}`, 'aria-pressed': list.quick_date === period ? 'true' : 'false', onClick: () => void toggleQuickDate(crud, period) }, t.quick[period] ?? period),
            ),
        ),
    ]);
}

/** Text and number filters with their operator; custom text, number and date filters (one value). */
const ValueFilter = defineComponent({
    name: 'PtahCrudValueFilter',
    props: controlProps,
    setup(props) {
        const id = uid('pc-filter');
        const typed = ref(text(props.list.filters[props.item.field]));
        const apply = debounce((next: string) => void setFilterValue(props.crud, props.item.field, next), 400);

        watch(
            () => props.list.filters[props.item.field],
            (value) => {
                typed.value = text(value);
            },
        );
        onBeforeUnmount(() => apply.cancel());

        return () => {
            const { item, list, t, crud } = props;
            const op = list.operators[item.field] ?? '';
            const numeric = item.kind === 'number';
            const nullOp = isNullOperator(op);

            return h('div', [
                h('label', { class: 'pc-label', for: id }, item.label),
                h('div', { style: s.row }, [
                    item.operators.length > 0
                        ? h(
                              'select',
                              { class: 'pc-input', style: s.op, 'aria-label': `${item.label} — ${t.operator}`, value: op || item.operators[0], onChange: (event: Event) => void setFilterOperator(crud, item.field, valueOf(event)) },
                              item.operators.map((o) => option(o, operatorLabel(t, o, numeric))),
                          )
                        : null,
                    item.kind === 'date'
                        ? h('input', { id, type: 'date', class: 'pc-input', style: s.grow, value: text(list.filters[item.field]), onInput: (event: Event) => void setFilterValue(crud, item.field, valueOf(event)) })
                        : h('input', {
                              id,
                              type: numeric ? 'number' : 'text',
                              step: numeric ? 'any' : undefined,
                              class: 'pc-input',
                              style: s.grow,
                              placeholder: nullOp ? '—' : `${item.label}...`,
                              disabled: nullOp,
                              value: typed.value,
                              onInput: (event: Event) => {
                                  typed.value = valueOf(event);
                                  apply(typed.value);
                              },
                          }),
                ]),
            ]);
        };
    },
});

const SelectFilter = defineComponent({
    name: 'PtahCrudSelectFilter',
    props: controlProps,
    setup(props) {
        const id = uid('pc-filter');

        return () => {
            const { item, list, meta, t, crud } = props;

            return h('div', [
                h('label', { class: 'pc-label', for: id }, item.label),
                h('select', { id, class: 'pc-input', value: text(list.filters[item.field]), onChange: (event: Event) => void setFilterValue(crud, item.field, valueOf(event)) }, [
                    option('', t.all),
                    ...(item.options ?? []).map((o) => option(o.value, o.label)),
                    ...(item.custom ? [] : [option(meta.sentinels.empty, `(${t.isNull})`), option(meta.sentinels.filled, `(${t.isNotNull})`)]),
                ]),
            ]);
        };
    },
});

function dateRange({ crud, item, list, t }: ControlProps) {
    const edges = [
        { edge: 'start', title: t.from, ops: item.date_operators?.start ?? ['>=', '>', '='] },
        { edge: 'end', title: t.to, ops: item.date_operators?.end ?? ['<=', '<', '='] },
    ];

    return h('fieldset', { style: { ...s.fieldset, ...s.wide } }, [
        h('legend', { class: 'pc-label' }, item.label),
        h(
            'div',
            { style: s.pair },
            edges.map(({ edge, title, ops }) => {
                const key = `${item.field}_${edge}`;

                return h('div', { key: edge }, [
                    h('p', { style: s.muted, 'aria-hidden': 'true' }, title),
                    h('div', { style: s.row }, [
                        h(
                            'select',
                            { class: 'pc-input', style: s.opNarrow, 'aria-label': `${item.label} — ${title} — ${t.operator}`, value: list.date_operators[key] || ops[0], onChange: (event: Event) => void setDateOperator(crud, key, valueOf(event)) },
                            ops.map((o) => option(o, operatorLabel(t, o, true))),
                        ),
                        h('input', { type: 'date', class: 'pc-input', style: s.grow, 'aria-label': `${item.label} — ${title}`, value: list.date_ranges[key] ?? '', onInput: (event: Event) => void setDateValue(crud, key, valueOf(event)) }),
                    ]),
                ]);
            }),
        ),
    ]);
}

/** A searchdropdown filter: operator, the chosen value as a chip, the search box with the options. */
const DropdownFilter = defineComponent({
    name: 'PtahCrudDropdownFilter',
    props: controlProps,
    setup(props) {
        const id = uid('pc-filter');
        const lookup = new FilterLookup(props.crud, props.session, props.item.field);
        const box = shallowRef<FilterLookupState>(lookup.getState());
        const root = shallowRef<HTMLElement | null>(null);
        const stop = lookup.subscribe(() => {
            box.value = lookup.getState();
        });
        onBeforeUnmount(() => {
            stop();
            lookup.destroy();
        });
        // A new parent value: the options on hand belonged to the old one.
        watch(
            () => (props.item.depends_on ? text(props.list.filters[props.item.depends_on]) : ''),
            () => lookup.reset(),
        );
        useOutside(root, () => {
            if (box.value.open) {
                lookup.close();
            }
        });

        const keys = (event: KeyboardEvent) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                lookup.move(1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                lookup.move(-1);
            } else if (event.key === 'Enter' && box.value.open && box.value.active >= 0) {
                event.preventDefault();
                void lookup.choose();
            } else if (event.key === 'Escape' && box.value.open) {
                event.stopPropagation();
                lookup.close();
            }
        };

        return () => {
            const { item, list, labels, t, crud, session } = props;
            const state = box.value;
            const value = list.filters[item.field];
            const selected = value === undefined || value === null || value === '' ? null : (labels[item.field] ?? String(value));
            const op = list.operators[item.field] ?? '';
            const listId = `${id}-list`;

            return h('div', [
                h('label', { class: 'pc-label', for: id }, item.label),
                h(
                    'select',
                    { class: 'pc-input', style: s.spaced, 'aria-label': `${item.label} — ${t.operator}`, value: op || item.operators[0] || '=', onChange: (event: Event) => void setFilterOperator(crud, item.field, valueOf(event)) },
                    item.operators.map((o) => option(o, operatorLabel(t, o))),
                ),
                selected !== null ? h('div', { style: s.spaced }, [h('span', { style: s.chip }, [selected, removeButton(t.removeFilter(item.label), () => void clearDropdown(crud, session, item.field))])]) : null,
                h('div', { class: 'pc-combo', ref: root }, [
                    h('div', { style: s.comboBox }, [
                        h('input', {
                            id,
                            type: 'text',
                            role: 'combobox',
                            'aria-expanded': state.open ? 'true' : 'false',
                            'aria-controls': listId,
                            'aria-autocomplete': 'list',
                            'aria-activedescendant': state.open && state.active >= 0 ? `${id}-opt-${state.active}` : undefined,
                            autocomplete: 'off',
                            class: 'pc-input',
                            style: s.comboInput,
                            placeholder: selected !== null ? t.change : (item.placeholder ?? t.searchLabel(item.label)),
                            value: state.text,
                            onFocus: () => lookup.focus(),
                            onInput: (event: Event) => lookup.type(valueOf(event)),
                            onKeydown: keys,
                        }),
                        h(
                            'button',
                            {
                                type: 'button',
                                tabindex: -1,
                                style: s.chevron,
                                'aria-label': t.showOptions,
                                onMousedown: (event: MouseEvent) => {
                                    event.preventDefault();
                                    lookup.toggle();
                                },
                            },
                            [h('span', { 'aria-hidden': 'true' }, state.open ? '▴' : '▾')],
                        ),
                    ]),
                    state.open
                        ? h('ul', { class: 'pc-combo-list', id: listId, role: 'listbox', 'aria-label': item.label, 'aria-busy': state.loading ? 'true' : 'false', style: item.start_list === 'top' ? s.listTop : undefined }, [
                              state.items.length === 0 ? h('li', { class: 'pc-combo-note', role: state.loading ? 'status' : undefined }, state.loading ? t.loading : t.noResults) : null,
                              ...state.items.map((item2, index) =>
                                  h(
                                      'li',
                                      {
                                          key: `${String(item2.value)}-${index}`,
                                          id: `${id}-opt-${index}`,
                                          role: 'option',
                                          'aria-selected': index === state.active ? 'true' : 'false',
                                          class: 'pc-combo-item',
                                          onMousedown: (event: MouseEvent) => event.preventDefault(),
                                          onClick: () => void lookup.choose(index),
                                      },
                                      [
                                          text(item2.label),
                                          'labelTwo' in item2 ? h('span', { style: s.sub }, text(item2.labelTwo)) : null,
                                          'labelThree' in item2 ? h('span', { style: s.sub }, text(item2.labelThree)) : null,
                                      ],
                                  ),
                              ),
                          ])
                        : null,
                ]),
            ]);
        };
    },
});

// ── Advanced search ─────────────────────────────────────────────────────────

const AdvancedSearch = defineComponent({
    name: 'PtahCrudAdvancedSearch',
    props: {
        crud: { type: Object as PropType<CrudController>, required: true as const },
        meta: { type: Object as PropType<FiltersMeta>, required: true as const },
        list: { type: Object as PropType<FilterListState>, required: true as const },
        t: { type: Object as PropType<FiltersWords>, required: true as const },
    },
    setup(props) {
        const switchId = uid('pc-advanced');
        const on = ref(advancedRows(props.list).length > 0);
        const draft = ref<AdvancedRow>(blankAdvancedRow(props.meta));

        watch(
            () => advancedRows(props.list).length,
            (length) => {
                if (length > 0) {
                    on.value = true;
                }
            },
        );

        const toggle = () => {
            // Turning it off forgets the rows (toggleAdvancedSearch()).
            if (on.value && advancedRows(props.list).length > 0) {
                void setAdvancedRows(props.crud, []);
            }
            on.value = !on.value;
        };

        const add = () => {
            const value = draft.value.value.trim();
            if (value === '' || draft.value.field === '') {
                return;
            }
            void setAdvancedRows(props.crud, [...advancedRows(props.list), { ...draft.value, value }]);
            draft.value = { ...draft.value, value: '' };
        };

        return () => {
            const { meta, list, t, crud } = props;
            const rows = advancedRows(list);
            const kind = advancedKind(meta, draft.value.field);

            return h('div', [
                h('label', { class: 'pc-switch', for: switchId }, [
                    h('input', { id: switchId, type: 'checkbox', role: 'switch', checked: on.value, onChange: toggle }),
                    h('span', { class: 'pc-switch-track' }),
                    h('span', t.advanced),
                ]),
                on.value
                    ? h('div', [
                          rows.length === 0
                              ? h('p', { style: { ...s.muted, marginTop: '8px' } }, t.advancedEmpty)
                              : h(
                                    'ul',
                                    { style: { ...s.plainList, marginTop: '8px' } },
                                    rows.map((row, index) => {
                                        const label = advancedText(t, meta, row, index);

                                        return h('li', { key: `${row.field}-${index}` }, [
                                            h('span', { style: s.chip }, [
                                                label,
                                                removeButton(t.removeRow(label), () => void setAdvancedRows(crud, rows.filter((_, other) => other !== index))),
                                            ]),
                                        ]);
                                    }),
                                ),
                          h(
                              'form',
                              {
                                  style: s.advancedRow,
                                  onSubmit: (event: Event) => {
                                      event.preventDefault();
                                      add();
                                  },
                              },
                              [
                                  rows.length > 0
                                      ? h(
                                            'select',
                                            {
                                                class: 'pc-input',
                                                style: s.advancedControl,
                                                'aria-label': t.advancedLogic,
                                                value: draft.value.logic,
                                                onChange: (event: Event) => (draft.value = { ...draft.value, logic: valueOf(event) === 'OR' ? 'OR' : 'AND' }),
                                            },
                                            [option('AND', t.and), option('OR', t.or)],
                                        )
                                      : null,
                                  h(
                                      'select',
                                      {
                                          class: 'pc-input',
                                          style: s.advancedControl,
                                          'aria-label': t.advancedField,
                                          value: draft.value.field,
                                          onChange: (event: Event) => {
                                              const field = valueOf(event);
                                              draft.value = { ...draft.value, field, operator: advancedOperators(meta, field)[0] ?? '=', value: '' };
                                          },
                                      },
                                      meta.advanced.fields.map((field) => option(field.field, field.label)),
                                  ),
                                  h(
                                      'select',
                                      { class: 'pc-input', style: s.advancedControl, 'aria-label': t.operator, value: draft.value.operator, onChange: (event: Event) => (draft.value = { ...draft.value, operator: valueOf(event) }) },
                                      advancedOperators(meta, draft.value.field).map((o) => option(o, operatorLabel(t, o))),
                                  ),
                                  h('input', {
                                      type: kind === 'number' ? 'number' : kind === 'date' ? 'date' : 'text',
                                      step: kind === 'number' ? 'any' : undefined,
                                      class: 'pc-input',
                                      style: s.advancedControl,
                                      'aria-label': t.advancedValue,
                                      value: draft.value.value,
                                      onInput: (event: Event) => (draft.value = { ...draft.value, value: valueOf(event) }),
                                  }),
                                  h('button', { type: 'submit', class: 'pc-btn', disabled: draft.value.value.trim() === '' }, t.add),
                              ],
                          ),
                      ])
                    : null,
            ]);
        };
    },
});

// ── Saved filters ───────────────────────────────────────────────────────────

const SavedFilters = defineComponent({
    name: 'PtahCrudSavedFilters',
    props: {
        crud: { type: Object as PropType<CrudController>, required: true as const },
        state: { type: Object as PropType<CrudState>, required: true as const },
        t: { type: Object as PropType<FiltersWords>, required: true as const },
    },
    setup(props) {
        const deleting = ref<string | null>(null);

        const confirm = () => {
            const name = deleting.value;
            deleting.value = null;
            if (name !== null) {
                void props.crud.forgetFilter(name);
            }
        };

        return () => {
            const { crud, state, t } = props;
            const names = Object.keys(state.preferences?.saved_filters ?? {});

            if (names.length === 0) {
                return null;
            }

            return h('div', { style: s.wrap }, [
                h('span', { class: 'pc-label', style: { margin: 0 } }, t.saved),
                ...names.map((name) =>
                    h('span', { key: name, style: { display: 'inline-flex' } }, [
                        h('button', { type: 'button', class: 'pc-btn', style: s.savedLoad, onClick: () => void loadSavedFilter(crud, name) }, name),
                        h('button', { type: 'button', class: 'pc-btn', style: s.savedDelete, 'aria-label': t.deleteSaved(name), title: t.deleteSaved(name), onClick: () => (deleting.value = name) }, [h('span', { 'aria-hidden': 'true' }, '×')]),
                    ]),
                ),
                h(
                    ForgeModal,
                    { modelValue: deleting.value !== null, 'onUpdate:modelValue': (open: boolean) => !open && (deleting.value = null), title: t.deleteSavedTitle, size: 'sm' },
                    {
                        default: () => [h('p', deleting.value !== null ? t.deleteSavedText(deleting.value) : '')],
                        footer: () => [
                            h('button', { type: 'button', class: 'pc-btn', onClick: () => (deleting.value = null) }, t.cancel),
                            h('button', { type: 'button', class: 'pc-btn pc-btn--danger', onClick: confirm }, t.delete),
                        ],
                    },
                ),
            ]);
        };
    },
});

const SaveFooter = defineComponent({
    name: 'PtahCrudSaveFilter',
    props: {
        crud: { type: Object as PropType<CrudController>, required: true as const },
        t: { type: Object as PropType<FiltersWords>, required: true as const },
    },
    setup(props) {
        const saving = ref(false);
        const name = ref('');
        const input = shallowRef<HTMLInputElement | null>(null);

        const cancel = () => {
            saving.value = false;
            name.value = '';
        };

        const submit = () => {
            const trimmed = name.value.trim();
            if (trimmed === '') {
                return;
            }
            cancel();
            void props.crud.saveFilter(trimmed);
        };

        watch(saving, (open) => {
            if (open) {
                void Promise.resolve().then(() => input.value?.focus());
            }
        });

        return () => {
            const { t } = props;

            return h('div', { style: s.foot }, [
                saving.value
                    ? h(
                          'form',
                          {
                              style: s.saveForm,
                              onSubmit: (event: Event) => {
                                  event.preventDefault();
                                  submit();
                              },
                          },
                          [
                              h('input', {
                                  ref: input,
                                  type: 'text',
                                  class: 'pc-input',
                                  style: s.grow,
                                  'aria-label': t.saveName,
                                  placeholder: t.savePlaceholder,
                                  maxlength: 80,
                                  value: name.value,
                                  onInput: (event: Event) => (name.value = valueOf(event)),
                                  onKeydown: (event: KeyboardEvent) => {
                                      if (event.key === 'Escape') {
                                          event.stopPropagation();
                                          cancel();
                                      }
                                  },
                              }),
                              h('button', { type: 'submit', class: 'pc-btn pc-btn--primary' }, t.save),
                              h('button', { type: 'button', class: 'pc-btn', onClick: cancel }, t.cancel),
                          ],
                      )
                    : h('button', { type: 'button', class: 'pc-btn', onClick: () => (saving.value = true) }, t.saveAction),
            ]);
        };
    },
});

// ── Chips ───────────────────────────────────────────────────────────────────

/** The active search and filters as chips, each with its remove button (the toolbar's chips). */
export const PtahCrudFilterBadges = defineComponent({
    name: 'PtahCrudFilterBadges',
    props: pieceProps,
    setup(props) {
        const crud = props.crud;
        const state = usePtahCrud(crud);
        const { filters } = useFilterSession(crud, state);
        const t = computed(() => filtersWords(props.locale));

        return () => {
            const words = t.value;
            if (state.value.status !== 'ready') {
                return null;
            }

            const list = state.value.list as FilterListState;
            const badges = buildBadges(list, filters.value.meta, filters.value.labels, words);

            if (list.search === '' && badges.length === 0) {
                return null;
            }

            const chips: VNodeChild[] = [];
            if (list.search !== '') {
                chips.push(h('span', { style: s.chip }, [h('span', { style: s.chipLabel }, `${words.search}:`), limitText(list.search, 30), removeButton(words.clearSearch, () => void crud.setSearch(''))]));
            }
            for (const badge of badges) {
                chips.push(
                    h('span', { key: `${badge.key}|${badge.label}`, style: s.chip }, [
                        h('span', { style: s.chipLabel }, `${badge.label}:`),
                        limitText(badge.value, 20),
                        removeButton(words.removeFilter(badge.label), () => void removeBadge(crud, badge.key)),
                    ]),
                );
            }

            return h('div', { style: s.badges, role: 'group', 'aria-label': words.title }, chips);
        };
    },
});

// ── Link filters ────────────────────────────────────────────────────────────

/** The banner of the link filters (`?f[...]`), with "clear link filters". */
export const PtahCrudUrlFilters = defineComponent({
    name: 'PtahCrudUrlFilters',
    props: {
        ...pieceProps,
        /** Take `?f[...]` of the page address into the listing when the screen loads (default true). */
        readLocation: { type: Boolean, default: true },
    },
    setup(props) {
        const crud = props.crud;
        const state = usePtahCrud(crud);
        const t = computed(() => filtersWords(props.locale));
        const items = shallowRef<UrlFilter[]>([]);
        const key = computed(() => JSON.stringify(linkFilters(state.value.list as FilterListState)));
        let seq = 0;

        watch(
            () => state.value.status,
            (status) => {
                if (status === 'ready' && props.readLocation) {
                    void applyUrlFiltersOnce(crud);
                }
            },
            { immediate: true },
        );

        watch(
            [key, () => state.value.status],
            ([current, status]) => {
                const mine = ++seq;
                if (status !== 'ready' || current === '{}') {
                    items.value = [];

                    return;
                }
                fetchUrlFilters(crud, JSON.parse(current) as Record<string, unknown>)
                    .then((found) => mine === seq && (items.value = found))
                    .catch(() => mine === seq && (items.value = []));
            },
            { immediate: true },
        );

        return () => {
            const words = t.value;
            if (state.value.status !== 'ready' || key.value === '{}' || items.value.length === 0) {
                return null;
            }

            return h('div', { role: 'status', style: s.banner }, [
                h('strong', words.urlActive),
                h(
                    'span',
                    { style: s.wrap },
                    items.value.map((item) => h('span', { key: item.field, style: s.chip }, [h('span', { style: s.chipLabel }, `${item.label}:`), limitText(item.value, 30)])),
                ),
                h('button', { type: 'button', class: 'pc-btn', style: s.pushRight, onClick: () => void clearLinkFilters(crud) }, words.urlClear),
            ]);
        };
    },
});

// ── Recent searches ─────────────────────────────────────────────────────────

/** A toolbar button with the recent searches of this screen: pick one to search it again, or clear them. */
export const PtahCrudSearchHistory = defineComponent({
    name: 'PtahCrudSearchHistory',
    props: pieceProps,
    setup(props) {
        const crud = props.crud;
        const state = usePtahCrud(crud);
        const t = computed(() => filtersWords(props.locale));
        const open = ref(false);
        const items = shallowRef<string[] | null>(null);
        const error = ref(false);
        const confirming = ref(false);
        const root = shallowRef<HTMLElement | null>(null);
        const listId = uid('pc-history');

        useOutside(root, () => {
            if (open.value && !confirming.value) {
                open.value = false;
            }
        });
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && open.value && !confirming.value) {
                open.value = false;
            }
        };
        onMounted(() => document.addEventListener('keydown', onKey));
        onBeforeUnmount(() => document.removeEventListener('keydown', onKey));

        const load = async () => {
            items.value = null;
            error.value = false;
            try {
                items.value = await fetchSearchHistory(crud);
            } catch {
                error.value = true;
            }
        };

        const clear = async () => {
            confirming.value = false;
            try {
                await clearSearchHistory(crud);
                items.value = [];
            } catch (failure) {
                crud.toast(failure instanceof Error ? failure.message : t.value.historyError, 'danger');
            }
        };

        return () => {
            const words = t.value;
            if (state.value.status !== 'ready') {
                return null;
            }
            const found = items.value;

            return h('div', { class: 'pc-menu', ref: root }, [
                h(
                    'button',
                    {
                        type: 'button',
                        class: 'pc-btn',
                        'aria-haspopup': 'true',
                        'aria-expanded': open.value ? 'true' : 'false',
                        'aria-controls': open.value ? listId : undefined,
                        onClick: () => {
                            open.value = !open.value;
                            if (open.value) {
                                void load();
                            }
                        },
                    },
                    words.history,
                ),
                open.value
                    ? h('div', { class: 'pc-menu-list', id: listId, role: 'region', 'aria-label': words.history }, [
                          found === null && !error.value ? h('p', { role: 'status', style: s.note }, words.loading) : null,
                          error.value ? h('p', { role: 'alert', style: s.note }, words.historyError) : null,
                          found !== null && found.length === 0 ? h('p', { style: s.note }, words.historyEmpty) : null,
                          ...(found !== null && found.length > 0
                              ? [
                                    h(
                                        'ul',
                                        { style: s.menuList },
                                        found.map((term) =>
                                            h('li', { key: term }, [
                                                h(
                                                    'button',
                                                    {
                                                        type: 'button',
                                                        class: 'pc-menu-item',
                                                        onClick: () => {
                                                            open.value = false;
                                                            void crud.setSearch(term);
                                                        },
                                                    },
                                                    term,
                                                ),
                                            ]),
                                        ),
                                    ),
                                    h('button', { type: 'button', class: 'pc-menu-item', style: s.danger, onClick: () => (confirming.value = true) }, words.historyClear),
                                ]
                              : []),
                      ])
                    : null,
                h(
                    ForgeModal,
                    { modelValue: confirming.value, 'onUpdate:modelValue': (value: boolean) => (confirming.value = value), title: words.historyClearTitle, size: 'sm' },
                    {
                        default: () => [h('p', words.historyClearText)],
                        footer: () => [
                            h('button', { type: 'button', class: 'pc-btn', onClick: () => (confirming.value = false) }, words.cancel),
                            h('button', { type: 'button', class: 'pc-btn pc-btn--danger', onClick: () => void clear() }, words.historyClear),
                        ],
                    },
                ),
            ]);
        };
    },
});
