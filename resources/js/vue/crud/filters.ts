/**
 * The filter panel of a CRUD screen for Vue — the pieces of
 * _filter-panel.blade.php, the filter chips (_toolbar.blade.php) and the
 * link-filters banner (_url-filters-banner.blade.php) of the Livewire
 * screen, and the recent searches. Line for line the React pieces
 * (react/crud/filters.tsx); the rules are in core/crud/filters.ts and the
 * filtering itself is the server's (the BaseCrud engine). The look is the
 * `pc-fp*`, `pc-chip*` and `pc-urlbanner*` rules of ptah-ui.css.
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
import { icon } from '../icons';
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

/** The ✕ of a chip: its own name for assistive tech, the Livewire tooltip ("Limpar filtros") on hover. */
const removeButton = (label: string, onClick: () => void, title?: string) => h('button', { type: 'button', 'aria-label': label, title: title ?? label, onClick }, [icon('x', 12, 2.5)]);

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
 * cascade, the custom filters, the advanced search, the saved filters and
 * the recent searches.
 */
export const PtahCrudFilterPanel = defineComponent({
    name: 'PtahCrudFilterPanel',
    props: {
        ...pieceProps,
        /** Shows a close button in the panel's header (the Livewire panel has none: the toolbar's Filtros toggles it). */
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
                return h('section', { class: 'pc-fp', 'aria-labelledby': titleId }, [
                    h('div', { class: 'pc-fp-body' }, [
                        h('h3', { class: 'pc-fp-ttl', id: titleId }, words.title),
                        h('p', { role: 'alert', class: 'pc-fp-error' }, `${words.loadError} ${filters.value.error ?? ''}`),
                        h('div', [h('button', { type: 'button', class: 'pc-tbtn', onClick: () => void session.ensure() }, words.retry)]),
                    ]),
                ]);
            }

            const meta = filters.value.meta;
            if (!meta) {
                return h('div', { class: 'pc-fp' }, [h('p', { role: 'status', class: 'pc-fp-note' }, words.loading)]);
            }

            const list = state.value.list as FilterListState;
            const advanced = meta.advanced.enabled && meta.advanced.fields.length > 0;

            if (meta.filters.length === 0 && !meta.quick_dates.enabled && !advanced) {
                return null;
            }

            const count = buildBadges(list, meta, filters.value.labels, words).length;

            return h('section', { class: 'pc-fp', 'aria-labelledby': titleId }, [
                h('div', { class: 'pc-fp-hd' }, [
                    h('div', { class: 'pc-fp-hd-side' }, [
                        icon('funnel', 16, undefined, 'pc-fp-icon'),
                        h('h3', { class: 'pc-fp-ttl', id: titleId }, words.title),
                        count > 0 ? h('span', { class: 'pc-fp-count' }, words.active(count)) : null,
                    ]),
                    h('div', { class: 'pc-fp-hd-side' }, [
                        h('button', { type: 'button', class: 'pc-fp-clear', onClick: () => void clearAllFilters(crud, session) }, [icon('x', 14), words.clearAll]),
                        props.onClose ? h('button', { type: 'button', class: 'pc-x', 'aria-label': words.close, title: words.close, onClick: () => props.onClose?.() }, [icon('x', 18)]) : null,
                    ]),
                ]),
                h('div', { class: 'pc-fp-body' }, [
                    meta.quick_dates.enabled ? quickDates(quickId, crud, meta, list, words) : null,
                    meta.filters.length > 0
                        ? h(
                              'div',
                              { class: 'pc-fp-grid' },
                              meta.filters.map((item) => h(FilterControl, { key: `${item.custom ? 'c' : 'f'}-${item.field}`, crud, session, item, list, labels: filters.value.labels, meta, t: words })),
                          )
                        : null,
                    advanced ? h(AdvancedSearch, { crud, meta, list, t: words }) : null,
                    h(SavedFilters, { crud, state: state.value, t: words }),
                    h(RecentSearches, { crud, t: words }),
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
        h('p', { id, class: 'pc-fp-section' }, t.dateShortcuts),
        h(
            'div',
            { class: 'pc-fp-chips' },
            meta.quick_dates.periods.map((period) =>
                h('button', { key: period, type: 'button', class: 'pc-fp-ql', 'aria-pressed': list.quick_date === period ? 'true' : 'false', onClick: () => void toggleQuickDate(crud, period) }, t.quick[period] ?? period),
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
            const cents = item.money_cents === true;
            const nullOp = isNullOperator(op);

            return h('div', [
                h('label', { class: 'pc-fp-label', for: id }, item.label),
                h('div', { class: 'pc-fp-row' }, [
                    item.operators.length > 0
                        ? h(
                              'select',
                              { class: 'pc-fp-in pc-fp-op', 'aria-label': `${item.label} — ${t.operator}`, value: op || item.operators[0], onChange: (event: Event) => void setFilterOperator(crud, item.field, valueOf(event)) },
                              item.operators.map((o) => option(o, operatorLabel(t, o, numeric))),
                          )
                        : null,
                    item.kind === 'date'
                        ? h('input', { id, type: 'date', class: 'pc-fp-in pc-fp-grow', value: text(list.filters[item.field]), onInput: (event: Event) => void setFilterValue(crud, item.field, valueOf(event)) })
                        : h('input', {
                              id,
                              type: numeric && !cents ? 'number' : 'text',
                              step: numeric && !cents ? 'any' : undefined,
                              inputmode: cents ? 'decimal' : undefined,
                              class: 'pc-fp-in pc-fp-grow',
                              placeholder: nullOp ? '—' : cents ? '0,00' : `${item.label}...`,
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
                h('label', { class: 'pc-fp-label', for: id }, item.label),
                h('select', { id, class: 'pc-fp-in pc-fp-select', value: text(list.filters[item.field]), onChange: (event: Event) => void setFilterValue(crud, item.field, valueOf(event)) }, [
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

    return h('fieldset', { class: 'pc-fp-fieldset pc-fp-wide' }, [
        h('legend', { class: 'pc-fp-label' }, item.label),
        h(
            'div',
            { class: 'pc-fp-pair' },
            edges.map(({ edge, title, ops }) => {
                const key = `${item.field}_${edge}`;

                return h('div', { key: edge }, [
                    h('p', { class: 'pc-fp-muted', 'aria-hidden': 'true' }, title),
                    h('div', { class: 'pc-fp-row' }, [
                        h(
                            'select',
                            { class: 'pc-fp-in pc-fp-op pc-fp-op--narrow', 'aria-label': `${item.label} — ${title} — ${t.operator}`, value: list.date_operators[key] || ops[0], onChange: (event: Event) => void setDateOperator(crud, key, valueOf(event)) },
                            ops.map((o) => option(o, operatorLabel(t, o, true))),
                        ),
                        h('input', { type: 'date', class: 'pc-fp-in pc-fp-grow', 'aria-label': `${item.label} — ${title}`, value: list.date_ranges[key] ?? '', onInput: (event: Event) => void setDateValue(crud, key, valueOf(event)) }),
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
                h('label', { class: 'pc-fp-label', for: id }, item.label),
                h(
                    'select',
                    { class: 'pc-fp-in pc-fp-spaced', 'aria-label': `${item.label} — ${t.operator}`, value: op || item.operators[0] || '=', onChange: (event: Event) => void setFilterOperator(crud, item.field, valueOf(event)) },
                    item.operators.map((o) => option(o, operatorLabel(t, o))),
                ),
                selected !== null
                    ? h('div', { class: 'pc-fp-spaced' }, [h('span', { class: 'pc-chip' }, [selected, removeButton(t.removeFilter(item.label), () => void clearDropdown(crud, session, item.field), t.clearFilters)])])
                    : null,
                h('div', { class: 'pc-fp-combo', ref: root }, [
                    h('input', {
                        id,
                        type: 'text',
                        role: 'combobox',
                        'aria-expanded': state.open ? 'true' : 'false',
                        'aria-controls': listId,
                        'aria-autocomplete': 'list',
                        'aria-activedescendant': state.open && state.active >= 0 ? `${id}-opt-${state.active}` : undefined,
                        autocomplete: 'off',
                        class: 'pc-fp-in pc-fp-combo-in',
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
                            class: 'pc-fp-chev',
                            'data-open': state.open ? 'true' : 'false',
                            'aria-label': t.showOptions,
                            onMousedown: (event: MouseEvent) => {
                                event.preventDefault();
                                lookup.toggle();
                            },
                        },
                        [icon('chevronDown')],
                    ),
                    state.open
                        ? h('ul', { class: `pc-fp-list${item.start_list === 'top' ? ' pc-fp-list--top' : ''}`, id: listId, role: 'listbox', 'aria-label': item.label, 'aria-busy': state.loading ? 'true' : 'false' }, [
                              state.items.length === 0 ? h('li', { class: 'pc-fp-note', role: state.loading ? 'status' : undefined }, state.loading ? t.loading : t.noResults) : null,
                              ...state.items.map((item2, index) =>
                                  h(
                                      'li',
                                      {
                                          key: `${String(item2.value)}-${index}`,
                                          id: `${id}-opt-${index}`,
                                          role: 'option',
                                          'aria-selected': index === state.active ? 'true' : 'false',
                                          class: 'pc-fp-opt',
                                          onMousedown: (event: MouseEvent) => event.preventDefault(),
                                          onClick: () => void lookup.choose(index),
                                      },
                                      [
                                          text(item2.label),
                                          'labelTwo' in item2 ? h('span', { class: 'pc-fp-sub' }, text(item2.labelTwo)) : null,
                                          'labelThree' in item2 ? h('span', { class: 'pc-fp-sub' }, text(item2.labelThree)) : null,
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

            return h('div', { class: 'pc-fp-adv' }, [
                h('label', { class: 'pc-switch pc-fp-adv-switch', for: switchId }, [
                    h('input', { id: switchId, type: 'checkbox', role: 'switch', checked: on.value, onChange: toggle }),
                    h('span', { class: 'pc-switch-track' }),
                    h('span', t.advanced),
                ]),
                on.value
                    ? h('div', [
                          rows.length === 0
                              ? h('p', { class: 'pc-fp-muted pc-fp-adv-empty' }, t.advancedEmpty)
                              : h(
                                    'ul',
                                    { class: 'pc-fp-adv-rows' },
                                    rows.map((row, index) => {
                                        const label = advancedText(t, meta, row, index);

                                        return h('li', { key: `${row.field}-${index}` }, [
                                            h('span', { class: 'pc-chip' }, [
                                                label,
                                                removeButton(t.removeRow(label), () => void setAdvancedRows(crud, rows.filter((_, other) => other !== index))),
                                            ]),
                                        ]);
                                    }),
                                ),
                          h(
                              'form',
                              {
                                  class: 'pc-fp-adv-form',
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
                                                class: 'pc-fp-in pc-fp-adv-ctl',
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
                                          class: 'pc-fp-in pc-fp-adv-ctl',
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
                                      { class: 'pc-fp-in pc-fp-adv-ctl', 'aria-label': t.operator, value: draft.value.operator, onChange: (event: Event) => (draft.value = { ...draft.value, operator: valueOf(event) }) },
                                      advancedOperators(meta, draft.value.field).map((o) => option(o, operatorLabel(t, o))),
                                  ),
                                  h('input', {
                                      type: kind === 'number' ? 'number' : kind === 'date' ? 'date' : 'text',
                                      step: kind === 'number' ? 'any' : undefined,
                                      class: 'pc-fp-in pc-fp-adv-ctl',
                                      'aria-label': t.advancedValue,
                                      value: draft.value.value,
                                      onInput: (event: Event) => (draft.value = { ...draft.value, value: valueOf(event) }),
                                  }),
                                  h('button', { type: 'submit', class: 'pc-tbtn', disabled: draft.value.value.trim() === '' }, t.add),
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

            return h('div', { class: 'pc-fp-saved' }, [
                h('span', { class: 'pc-fp-saved-lbl' }, t.saved),
                ...names.map((name) =>
                    h('span', { key: name, class: 'pc-fp-saved-item' }, [
                        h('button', { type: 'button', class: 'pc-fp-sv-load', onClick: () => void loadSavedFilter(crud, name) }, name),
                        h('button', { type: 'button', class: 'pc-fp-sv-del', 'aria-label': t.deleteSaved(name), title: t.deleteSaved(name), onClick: () => (deleting.value = name) }, [h('span', { 'aria-hidden': 'true' }, '×')]),
                    ]),
                ),
                h(
                    ForgeModal,
                    { modelValue: deleting.value !== null, 'onUpdate:modelValue': (open: boolean) => !open && (deleting.value = null), title: t.deleteSavedTitle, size: 'sm' },
                    {
                        default: () => [h('p', deleting.value !== null ? t.deleteSavedText(deleting.value) : '')],
                        footer: () => [
                            h('button', { type: 'button', class: 'pc-dbtn', onClick: () => (deleting.value = null) }, t.cancel),
                            h('button', { type: 'button', class: 'pc-dbtn pc-dbtn--danger', onClick: confirm }, t.delete),
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

            return h('div', { class: 'pc-fp-ft' }, [
                saving.value
                    ? h(
                          'form',
                          {
                              class: 'pc-fp-save-form',
                              onSubmit: (event: Event) => {
                                  event.preventDefault();
                                  submit();
                              },
                          },
                          [
                              h('input', {
                                  ref: input,
                                  type: 'text',
                                  class: 'pc-fp-save-in',
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
                              h('button', { type: 'submit', class: 'pc-fp-save-ok' }, t.save),
                              h('button', { type: 'button', class: 'pc-fp-save-cancel', onClick: cancel }, t.cancel),
                          ],
                      )
                    : h('button', { type: 'button', class: 'pc-fp-save', onClick: () => (saving.value = true) }, [icon('bookmark', 14), t.saveAction]),
            ]);
        };
    },
});

// ── Chips ───────────────────────────────────────────────────────────────────

/**
 * The active search and filters as chips, each with its remove button — the
 * row at the bottom of the Livewire toolbar card (`.pc-chips`): mount it
 * inside the toolbar card, after its control row.
 */
export const PtahCrudFilterBadges = defineComponent({
    name: 'PtahCrudFilterBadges',
    props: pieceProps,
    setup(props) {
        const crud = props.crud;
        const state = usePtahCrud(crud);
        const { session, filters } = useFilterSession(crud, state);
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

            // The list IS filtered (1.61.0): search and filters come back from the last visit.
            const chips: VNodeChild[] = [h('span', { class: 'pc-chips-lbl' }, `${words.applied}:`)];
            if (list.search !== '') {
                chips.push(h('span', { class: 'pc-chip' }, [icon('search', 12, undefined, 'pc-chip-ico'), limitText(list.search, 30), removeButton(words.clearSearch, () => void crud.setSearch(''), words.clearFilters)]));
            }
            for (const badge of badges) {
                chips.push(
                    h('span', { key: `${badge.key}|${badge.label}`, class: 'pc-chip' }, [
                        h('span', { class: 'pc-chip-key' }, `${badge.label}:`),
                        limitText(badge.value, 20),
                        removeButton(words.removeFilter(badge.label), () => void removeBadge(crud, badge.key), words.clearFilters),
                    ]),
                );
            }

            if ((list.search !== '' ? 1 : 0) + badges.length > 1) {
                chips.push(h('button', { type: 'button', class: 'pc-chips-clear', onClick: () => void clearAllFilters(crud, session) }, words.clearAll));
            }

            return h('div', { class: 'pc-chips', role: 'group', 'aria-label': words.title }, chips);
        };
    },
});

// ── Link filters ────────────────────────────────────────────────────────────

/** The banner of the link filters (`?f[...]`), with "clear link filters" — a forge-alert warn. */
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

            return h('div', { role: 'status', class: 'pc-urlbanner' }, [
                h('span', { class: 'pc-urlbanner-icon' }, [icon('alertTriangle', 20)]),
                h('div', { class: 'pc-urlbanner-body' }, [
                    h('span', { class: 'pc-urlbanner-ttl' }, words.urlActive),
                    h(
                        'span',
                        { class: 'pc-urlbanner-chips' },
                        items.value.map((item) => h('span', { key: item.field, class: 'pc-chip' }, [h('span', { class: 'pc-chip-key' }, `${item.label}:`), limitText(item.value, 30)])),
                    ),
                    h('button', { type: 'button', class: 'pc-urlbanner-btn', onClick: () => void clearLinkFilters(crud) }, words.urlClear),
                ]),
            ]);
        };
    },
});

// ── Recent searches ─────────────────────────────────────────────────────────

/** The "clear history" confirmation, shared by the panel row and the button. */
const clearHistoryModal = (open: boolean, t: FiltersWords, onCancel: () => void, onConfirm: () => void) =>
    h(
        ForgeModal,
        { modelValue: open, 'onUpdate:modelValue': (value: boolean) => !value && onCancel(), title: t.historyClearTitle, size: 'sm' },
        {
            default: () => [h('p', t.historyClearText)],
            footer: () => [h('button', { type: 'button', class: 'pc-dbtn', onClick: onCancel }, t.cancel), h('button', { type: 'button', class: 'pc-dbtn pc-dbtn--danger', onClick: onConfirm }, t.historyClear)],
        },
    );

/**
 * The recent searches inside the panel, laid out as the saved filters row.
 * The Livewire screen keeps the history (HasCrudPreferences) without drawing
 * it anywhere; the panel is where it sits next to its siblings.
 */
const RecentSearches = defineComponent({
    name: 'PtahCrudRecentSearches',
    props: {
        crud: { type: Object as PropType<CrudController>, required: true as const },
        t: { type: Object as PropType<FiltersWords>, required: true as const },
    },
    setup(props) {
        const items = shallowRef<string[]>([]);
        const confirming = ref(false);
        let live = true;

        onMounted(() => {
            fetchSearchHistory(props.crud)
                .then((found) => live && (items.value = found))
                .catch(() => live && (items.value = []));
        });
        onBeforeUnmount(() => {
            live = false;
        });

        const clear = async () => {
            confirming.value = false;
            try {
                await clearSearchHistory(props.crud);
                items.value = [];
            } catch (failure) {
                props.crud.toast(failure instanceof Error ? failure.message : props.t.historyError, 'danger');
            }
        };

        return () => {
            const { crud, t } = props;
            if (items.value.length === 0) {
                return null;
            }

            return h('div', { class: 'pc-fp-saved', role: 'group', 'aria-label': t.history }, [
                h('span', { class: 'pc-fp-saved-lbl' }, t.historyLabel),
                ...items.value.map((term) => h('button', { key: term, type: 'button', class: 'pc-fp-hist', title: t.search, onClick: () => void crud.setSearch(term) }, [icon('search', 12), term])),
                h('button', { type: 'button', class: 'pc-fp-save pc-fp-hist-clear', onClick: () => (confirming.value = true) }, t.historyClear),
                clearHistoryModal(
                    confirming.value,
                    t,
                    () => (confirming.value = false),
                    () => void clear(),
                ),
            ]);
        };
    },
});

/**
 * The recent searches of this screen as a toolbar dropdown: pick one to
 * search it again, or clear them. <PtahCrud> shows them in the filter panel
 * instead (the Livewire toolbar has no such button); this stays for hosts
 * that place it on a toolbar of their own.
 */
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

            return h('div', { class: 'pc-dd-wrap', ref: root }, [
                h(
                    'button',
                    {
                        type: 'button',
                        class: `pc-tbtn${open.value ? ' pc-tbtn--on' : ''}`,
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
                    [words.history, icon('chevronDown', 12)],
                ),
                open.value
                    ? h('div', { class: 'pc-dd', id: listId, role: 'region', 'aria-label': words.history }, [
                          found === null && !error.value ? h('p', { role: 'status', class: 'pc-fp-note' }, words.loading) : null,
                          error.value ? h('p', { role: 'alert', class: 'pc-fp-note' }, words.historyError) : null,
                          found !== null && found.length === 0 ? h('p', { class: 'pc-fp-note' }, words.historyEmpty) : null,
                          ...(found !== null && found.length > 0
                              ? [
                                    h(
                                        'ul',
                                        { class: 'pc-fp-plain' },
                                        found.map((term) =>
                                            h('li', { key: term }, [
                                                h(
                                                    'button',
                                                    {
                                                        type: 'button',
                                                        class: 'pc-dd-item',
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
                                    h('div', { class: 'pc-dd-sep' }),
                                    h('button', { type: 'button', class: 'pc-dd-item pc-fp-danger', onClick: () => (confirming.value = true) }, words.historyClear),
                                ]
                              : []),
                      ])
                    : null,
                clearHistoryModal(
                    confirming.value,
                    words,
                    () => (confirming.value = false),
                    () => void clear(),
                ),
            ]);
        };
    },
});
