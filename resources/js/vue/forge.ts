/**
 * The forge kit for Vue — the counterparts of ptah's `<x-forge-*>` Blade
 * components, with the same prop names. Styles: ptah-ui.css (`.pf-*`).
 *
 * Written as render functions so the whole kit is one module that mirrors
 * the React one line by line; use them in templates like any component.
 */
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { PropType, VNode } from 'vue';
import { pagesAround } from '../core';
import { trapFocus } from '../core/focus';
import { forgeWords } from '../core/forge-words';
import { icon } from './icons';
import { useFloating } from './float';

export type ForgeColor = 'primary' | 'success' | 'danger' | 'warn' | 'dark' | 'light';

const cx = (...names: (string | false | null | undefined)[]) => names.filter(Boolean).join(' ');
const color = { type: String as PropType<ForgeColor | string>, default: 'primary' };
const size = { type: String, default: 'md' };
let ids = 0;
const uid = (prefix: string) => `${prefix}-${++ids}`;
const fold = (text: string) =>
    text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

// ── Button, spinner, skeleton, progress ─────────────────────────────────────

export const ForgeButton = defineComponent({
    name: 'ForgeButton',
    props: { color, size, flat: Boolean, relief: Boolean, rounded: Boolean, disabled: Boolean, loading: Boolean, href: { type: String, default: undefined }, type: { type: String as PropType<'button' | 'submit' | 'reset'>, default: 'button' } },
    setup(props, { slots }) {
        return () => {
            const classes = cx('pf pf-button', props.flat && 'pf-button--flat', props.relief && 'pf-button--relief', props.rounded && 'pf-button--rounded');
            const content = [props.loading ? h('span', { class: 'pf-spinner', 'aria-hidden': 'true' }) : null, slots.default?.()];

            return props.href !== undefined && !props.disabled
                ? h('a', { class: classes, 'data-color': props.color, 'data-size': props.size, href: props.href }, content)
                : h('button', { type: props.type, class: classes, 'data-color': props.color, 'data-size': props.size, disabled: props.disabled || props.loading, 'aria-busy': props.loading || undefined }, content);
        };
    },
});

export const ForgeSpinner = defineComponent({
    name: 'ForgeSpinner',
    props: { color, size, type: { type: String, default: 'circle' }, label: { type: String, default: 'Loading' } },
    setup(props) {
        return () =>
            props.type === 'dots'
                ? h('span', { class: 'pf pf-spinner pf-spinner--dots', 'data-color': props.color, role: 'status', 'aria-label': props.label }, [h('i'), h('i'), h('i')])
                : h('span', { class: 'pf pf-spinner', 'data-color': props.color, 'data-size': props.size, role: 'status', 'aria-label': props.label });
    },
});

export const ForgeSkeleton = defineComponent({
    name: 'ForgeSkeleton',
    props: { variant: { type: String, default: 'text' }, count: { type: Number, default: 1 } },
    setup(props) {
        return () =>
            h(
                'span',
                { class: 'pf', 'aria-hidden': 'true' },
                Array.from({ length: Math.max(1, props.count) }, (_, index) => h('span', { key: index, class: 'pf-skeleton', 'data-variant': props.variant })),
            );
    },
});

export const ForgeProgress = defineComponent({
    name: 'ForgeProgress',
    props: { value: { type: [Number, String], default: 0 }, color, size, label: Boolean, animated: Boolean },
    setup(props) {
        return () => {
            const percent = Math.max(0, Math.min(100, Number(props.value) || 0));

            return h('div', { class: 'pf pf-progress', 'data-color': props.color, 'data-size': props.size }, [
                h('div', { class: 'pf-progress-track', role: 'progressbar', 'aria-valuemin': 0, 'aria-valuemax': 100, 'aria-valuenow': percent }, [
                    h('div', { class: cx('pf-progress-bar', props.animated && 'pf-progress-bar--animated'), style: { width: `${percent}%` } }),
                ]),
                props.label ? h('span', `${percent}%`) : null,
            ]);
        };
    },
});

// ── Alert, badge, avatar ────────────────────────────────────────────────────

export const ForgeAlert = defineComponent({
    name: 'ForgeAlert',
    props: { color, type: { type: String, default: undefined }, title: { type: String, default: '' }, closable: Boolean, dismissible: Boolean },
    emits: ['close'],
    setup(props, { slots, emit }) {
        const open = ref(true);

        return () => {
            if (!open.value) {
                return null;
            }
            const shade = (props.type === 'info' ? 'primary' : props.type) ?? props.color;

            return h('div', { class: 'pf pf-alert', 'data-color': shade, role: shade === 'danger' ? 'alert' : 'status' }, [
                h('div', { class: 'pf-alert-body' }, [props.title ? h('strong', { class: 'pf-alert-title' }, props.title) : null, slots.default?.()]),
                props.closable || props.dismissible
                    ? h(
                          'button',
                          {
                              type: 'button',
                              class: 'pf-x',
                              'aria-label': forgeWords().close, title: forgeWords().close,
                              onClick: () => {
                                  open.value = false;
                                  emit('close');
                              },
                          },
                          icon('x'),
                      )
                    : null,
            ]);
        };
    },
});

export const ForgeBadge = defineComponent({
    name: 'ForgeBadge',
    props: { color: { ...color, default: 'danger' }, position: { type: String, default: 'top-right' }, value: { type: [String, Number], default: '' }, dot: Boolean },
    setup(props, { slots }) {
        return () =>
            h('span', { class: 'pf pf-badge', 'data-color': props.color, 'data-position': props.position }, [
                slots.default?.(),
                props.dot || props.value !== '' ? h('span', { class: cx('pf-badge-mark', props.dot && 'pf-badge-mark--dot') }, props.dot ? undefined : String(props.value)) : null,
            ]);
    },
});

export const ForgeAvatar = defineComponent({
    name: 'ForgeAvatar',
    props: { src: { type: String, default: null }, alt: { type: String, default: '' }, size, color, text: { type: String, default: '' }, badgeColor: { type: String, default: null }, badgePosition: { type: String, default: 'bottom-right' } },
    setup(props) {
        return () => {
            const initials = props.text
                .split(/\s+/)
                .filter(Boolean)
                .slice(0, 2)
                .map((word) => word[0]?.toUpperCase())
                .join('');

            return h('span', { class: 'pf pf-avatar', 'data-color': props.color, 'data-size': props.size, role: 'img', 'aria-label': props.alt || props.text }, [
                props.src ? h('img', { src: props.src, alt: '' }) : initials,
                props.badgeColor ? h('span', { class: 'pf-avatar-dot', 'data-position': props.badgePosition, style: { background: `var(--pc-${props.badgeColor}, ${props.badgeColor})` } }) : null,
            ]);
        };
    },
});

// ── Card, stat card, chart card, empty, page header, breadcrumb, list ──────

export const ForgeCard = defineComponent({
    name: 'ForgeCard',
    props: { type: { type: String, default: 'default' }, flat: Boolean, hoverable: Boolean },
    setup(props, { slots }) {
        return () =>
            h('div', { class: cx('pf pf-card', props.flat && 'pf-card--flat', props.hoverable && 'pf-card--hoverable'), 'data-type': props.type }, [
                slots.header ? h('div', { class: 'pf-card-head' }, slots.header()) : null,
                h('div', { class: 'pf-card-body' }, slots.default?.()),
                slots.footer ? h('div', { class: 'pf-card-foot' }, slots.footer()) : null,
            ]);
    },
});

export const ForgeStatCard = defineComponent({
    name: 'ForgeStatCard',
    props: { title: { type: String, default: 'Metric' }, value: { type: [String, Number], default: '0' }, icon: { type: String, default: null }, color, trend: { type: [Number, String], default: null }, trendLabel: { type: String, default: null } },
    setup(props) {
        return () => {
            const number = props.trend === null || props.trend === '' ? null : Number(props.trend);

            return h('div', { class: 'pf pf-stat', 'data-color': props.color }, [
                props.icon ? h('span', { class: 'pf-stat-icon', 'aria-hidden': 'true' }, [h('i', { class: props.icon })]) : null,
                h('div', [
                    h('div', { class: 'pf-stat-title' }, props.title),
                    h('div', { class: 'pf-stat-value' }, String(props.value)),
                    number !== null && !Number.isNaN(number)
                        ? h('div', { class: cx('pf-stat-trend', number >= 0 ? 'pf-stat-trend--up' : 'pf-stat-trend--down') }, `${number >= 0 ? '▲' : '▼'} ${Math.abs(number)}% ${props.trendLabel ?? ''}`)
                        : null,
                ]),
            ]);
        };
    },
});

export const ForgeChartCard = defineComponent({
    name: 'ForgeChartCard',
    props: { title: { type: String, default: null }, subtitle: { type: String, default: null }, color },
    setup(props, { slots }) {
        return () =>
            h('div', { class: 'pf pf-card', 'data-color': props.color }, [
                h('div', { class: 'pf-card-body' }, [
                    props.title ? h('div', { class: 'pf-chart-title' }, props.title) : null,
                    props.subtitle ? h('div', { class: 'pf-chart-subtitle' }, props.subtitle) : null,
                    h('div', { class: 'pf-chart-rule' }),
                    slots.default?.(),
                ]),
            ]);
    },
});

export const ForgeEmpty = defineComponent({
    name: 'ForgeEmpty',
    props: { title: { type: String, default: '' }, description: { type: String, default: '' } },
    setup(props, { slots }) {
        return () =>
            h('div', { class: 'pf pf-empty' }, [props.title ? h('strong', { class: 'pf-empty-title' }, props.title) : null, props.description ? h('div', props.description) : null, slots.default?.()]);
    },
});

export const ForgePageHeader = defineComponent({
    name: 'ForgePageHeader',
    props: { title: { type: String, default: '' }, subtitle: { type: String, default: null }, back: { type: String, default: null } },
    setup(props, { slots }) {
        return () =>
            h('header', { class: 'pf pf-page-header' }, [
                props.back ? h('a', { class: 'pf pf-button pf-button--flat', href: props.back, 'aria-label': forgeWords().back, title: forgeWords().back }, icon('chevronLeft')) : null,
                h('div', [h('h1', { class: 'pf-page-title' }, props.title), props.subtitle ? h('p', { class: 'pf-page-subtitle' }, props.subtitle) : null]),
                slots.default ? h('div', { class: 'pf-page-actions' }, slots.default()) : null,
            ]);
    },
});

export interface ForgeCrumb {
    label: string;
    url?: string;
}

export const ForgeBreadcrumb = defineComponent({
    name: 'ForgeBreadcrumb',
    props: { items: { type: Array as PropType<ForgeCrumb[]>, default: () => [] }, separator: { type: String, default: '/' } },
    setup(props) {
        return () =>
            h('nav', { class: 'pf', 'aria-label': 'Breadcrumb' }, [
                h(
                    'ol',
                    { class: 'pf-breadcrumb' },
                    props.items.map((item, index) => {
                        const last = index === props.items.length - 1;

                        return h('li', { key: index, 'aria-current': last ? 'page' : undefined }, [
                            item.url && !last ? h('a', { href: item.url }, item.label) : item.label,
                            last ? null : h('span', { 'aria-hidden': 'true' }, ` ${props.separator}`),
                        ]);
                    }),
                ),
            ]);
    },
});

export interface ForgeListItem {
    title: string;
    subtitle?: string;
    icon?: string;
}

export const ForgeList = defineComponent({
    name: 'ForgeList',
    props: { items: { type: Array as PropType<ForgeListItem[]>, default: () => [] } },
    setup(props, { slots }) {
        return () =>
            h(
                'ul',
                { class: 'pf pf-list' },
                props.items.map((item, index) =>
                    h('li', { key: index, class: 'pf-list-item' }, [
                        item.icon ? h('i', { class: item.icon, 'aria-hidden': 'true' }) : null,
                        h('span', { class: 'pf-list-text' }, [h('span', { class: 'pf-list-title' }, item.title), item.subtitle ? h('span', { class: 'pf-list-sub' }, item.subtitle) : null]),
                        slots.action?.({ item, index }),
                    ]),
                ),
            );
    },
});

// ── Fields (v-model) ────────────────────────────────────────────────────────

export const ForgeInput = defineComponent({
    name: 'ForgeInput',
    inheritAttrs: false,
    props: {
        modelValue: { type: [String, Number], default: undefined },
        label: { type: String, default: '' },
        placeholder: { type: String, default: '' },
        type: { type: String, default: 'text' },
        state: { type: String, default: 'normal' },
        iconAfter: { type: String, default: null },
        iconBefore: { type: String, default: null },
        disabled: Boolean,
        loading: Boolean,
        message: { type: String, default: '' },
        required: Boolean,
        error: { type: String, default: null },
        value: { type: [String, Number], default: null },
        name: { type: String, default: undefined },
        id: { type: String, default: undefined },
    },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
        const auto = uid('pf-input');
        // A password shows an eye that reveals it (forge-input's _show).
        const shown = ref(false);

        return () => {
            const id = props.id ?? auto;
            const isPassword = props.type === 'password';
            const after = props.iconAfter || props.loading || isPassword;
            const toggleText = shown.value ? forgeWords().hidePassword : forgeWords().showPassword;

            return h('div', { class: 'pf pf-field', 'data-state': props.error ? 'danger' : props.state }, [
                props.label ? h('label', { class: 'pf-field-label', for: id }, [props.label, props.required ? h('span', { 'aria-hidden': 'true' }, ' *') : null]) : null,
                h('div', { class: 'pf-field-box' }, [
                    props.iconBefore ? h('span', { class: 'pf-field-icon pf-field-icon--before', 'aria-hidden': 'true' }, [h('i', { class: props.iconBefore })]) : null,
                    h('input', {
                        ...attrs,
                        id,
                        name: props.name,
                        type: isPassword && shown.value ? 'text' : props.type,
                        placeholder: props.placeholder || undefined,
                        disabled: props.disabled,
                        required: props.required,
                        value: props.modelValue ?? props.value ?? '',
                        class: cx('pf-control', props.iconBefore && 'pf-control--before', after && 'pf-control--after'),
                        'aria-invalid': props.error ? true : undefined,
                        'aria-describedby': props.error ? `${id}-error` : props.message ? `${id}-message` : undefined,
                        onInput: (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).value),
                    }),
                    isPassword && !props.loading
                        ? h('button', { type: 'button', class: 'pf-field-toggle', 'aria-label': toggleText, title: toggleText, 'aria-pressed': shown.value ? 'true' : 'false', tabindex: -1, onClick: () => (shown.value = !shown.value) }, [icon(shown.value ? 'eyeOff' : 'eye')])
                        : after
                          ? h('span', { class: 'pf-field-icon pf-field-icon--after', 'aria-hidden': 'true' }, [props.loading ? h('span', { class: 'pf-spinner', 'data-size': 'sm' }) : h('i', { class: props.iconAfter })])
                          : null,
                ]),
                props.error
                    ? h('p', { class: 'pf-field-error', id: `${id}-error`, role: 'alert' }, props.error)
                    : props.message
                      ? h('p', { class: 'pf-field-message', id: `${id}-message` }, props.message)
                      : null,
            ]);
        };
    },
});

export const ForgeTextarea = defineComponent({
    name: 'ForgeTextarea',
    inheritAttrs: false,
    props: {
        modelValue: { type: String, default: undefined },
        label: { type: String, default: null },
        placeholder: { type: String, default: '' },
        rows: { type: Number, default: 4 },
        color,
        state: { type: String, default: null },
        disabled: Boolean,
        helper: { type: String, default: null },
        maxlength: { type: Number, default: null },
        counter: Boolean,
        id: { type: String, default: undefined },
    },
    emits: ['update:modelValue'],
    setup(props, { attrs, emit }) {
        const auto = uid('pf-textarea');
        // Counted from the text already there, not from zero.
        const typed = ref(String(props.modelValue ?? '').length);
        watch(
            () => props.modelValue,
            (value) => {
                typed.value = String(value ?? '').length;
            },
        );

        return () => {
            const id = props.id ?? auto;

            return h('div', { class: 'pf pf-field', 'data-color': props.color, 'data-state': props.state ?? 'normal' }, [
                props.label ? h('label', { class: 'pf-field-label', for: id }, props.label) : null,
                h('textarea', {
                    ...attrs,
                    id,
                    rows: props.rows,
                    placeholder: props.placeholder || undefined,
                    disabled: props.disabled,
                    maxlength: props.maxlength ?? undefined,
                    value: props.modelValue ?? '',
                    class: 'pf-control',
                    onInput: (event: Event) => {
                        const value = (event.target as HTMLTextAreaElement).value;
                        typed.value = value.length;
                        emit('update:modelValue', value);
                    },
                }),
                h('div', { class: 'pf-field-foot' }, [
                    props.helper ? h('p', { class: 'pf-field-message' }, props.helper) : h('span'),
                    props.counter && props.maxlength !== null ? h('p', { class: 'pf-field-message' }, `${typed.value}/${props.maxlength}`) : null,
                ]),
            ]);
        };
    },
});

const toggleProps = { modelValue: { type: Boolean, default: undefined }, label: { type: String, default: null }, color, checked: Boolean, disabled: Boolean, name: { type: String, default: undefined } };

export const ForgeCheckbox = defineComponent({
    name: 'ForgeCheckbox',
    props: toggleProps,
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () =>
            h('label', { class: cx('pf pf-check', props.disabled && 'pf-check--disabled'), 'data-color': props.color }, [
                h('input', { type: 'checkbox', name: props.name, checked: props.modelValue ?? props.checked, disabled: props.disabled, onChange: (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).checked) }),
                props.label ? h('span', props.label) : null,
            ]);
    },
});

export const ForgeRadio = defineComponent({
    name: 'ForgeRadio',
    props: { modelValue: { type: String, default: undefined }, label: { type: String, default: null }, color, value: { type: String, default: '' }, name: { type: String, default: '' }, disabled: Boolean },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () =>
            h('label', { class: cx('pf pf-check', props.disabled && 'pf-check--disabled'), 'data-color': props.color }, [
                h('input', { type: 'radio', name: props.name, value: props.value, checked: props.modelValue === props.value, disabled: props.disabled, onChange: () => emit('update:modelValue', props.value) }),
                props.label ? h('span', props.label) : null,
            ]);
    },
});

/** The state is the model — one source of truth, so it can never show off while on. */
export const ForgeSwitch = defineComponent({
    name: 'ForgeSwitch',
    props: { ...toggleProps, size },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () =>
            h('label', { class: cx('pf pf-switch', props.disabled && 'pf-switch--disabled'), 'data-color': props.color, 'data-size': props.size }, [
                h('input', { type: 'checkbox', role: 'switch', name: props.name, checked: props.modelValue ?? props.checked, disabled: props.disabled, onChange: (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).checked) }),
                h('span', { class: 'pf-switch-track' }),
                props.label ? h('span', props.label) : null,
            ]);
    },
});

// ── Select ──────────────────────────────────────────────────────────────────

export interface ForgeOption {
    value: string | number;
    label: string;
}

type Selected = string | number | (string | number)[] | null;

export const ForgeSelect = defineComponent({
    name: 'ForgeSelect',
    props: {
        modelValue: { type: [String, Number, Array] as PropType<Selected>, default: undefined },
        label: { type: String, default: '' },
        options: { type: Array as PropType<ForgeOption[]>, default: () => [] },
        placeholder: { type: String, default: () => forgeWords().select },
        multiple: Boolean,
        disabled: Boolean,
        required: Boolean,
        error: { type: String, default: null },
        selected: { type: [String, Number, Array] as PropType<Selected>, default: null },
        name: { type: String, default: undefined },
        searchable: Boolean,
    },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        const id = uid('pf-select');
        const open = ref(false);
        const filter = ref('');
        const active = ref(-1);
        const root = ref<HTMLElement | null>(null);
        // Fixed under the field (1.63.1): a dialog body that scrolls hid the list.
        const panel = ref<HTMLElement | null>(null);
        useFloating(root, panel, () => open.value);
        const chosen = computed(() => {
            const value = props.modelValue === undefined ? props.selected : props.modelValue;

            return (Array.isArray(value) ? value : value === null || value === '' ? [] : [value]).map(String);
        });
        // Diacritics-insensitive: "permissao" finds "Permissão".
        const shown = computed(() => (filter.value === '' ? props.options : props.options.filter((option) => String(option.value) === '' || fold(option.label).includes(fold(filter.value)))));
        const text = computed(() =>
            props.options
                .filter((option) => chosen.value.includes(String(option.value)))
                .map((option) => option.label)
                .join(', '),
        );

        const onDown = (event: MouseEvent) => {
            if (open.value && !root.value?.contains(event.target as Node)) {
                open.value = false;
            }
        };
        onMounted(() => document.addEventListener('mousedown', onDown));
        onBeforeUnmount(() => document.removeEventListener('mousedown', onDown));

        const toggle = () => {
            // Reopening starts from a clean filter.
            filter.value = '';
            active.value = -1;
            open.value = !open.value;
        };

        const pick = (option: ForgeOption) => {
            if (props.multiple) {
                const key = String(option.value);
                const next = chosen.value.includes(key) ? chosen.value.filter((item) => item !== key) : [...chosen.value, key];
                emit(
                    'update:modelValue',
                    props.options.filter((item) => next.includes(String(item.value))).map((item) => item.value),
                );
            } else {
                emit('update:modelValue', option.value);
                open.value = false;
            }
        };

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                active.value = Math.min(shown.value.length - 1, active.value + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                active.value = Math.max(0, active.value - 1);
            } else if (event.key === 'Enter' && active.value >= 0 && shown.value[active.value]) {
                event.preventDefault();
                pick(shown.value[active.value]);
            } else if (event.key === 'Escape') {
                event.stopPropagation();
                // First Escape clears the filter, the second closes.
                if (filter.value !== '') {
                    filter.value = '';
                } else {
                    open.value = false;
                }
            }
        };

        return () =>
            h('div', { class: 'pf pf-field', 'data-state': props.error ? 'danger' : 'normal' }, [
                props.label ? h('label', { class: 'pf-field-label', id: `${id}-label` }, [props.label, props.required ? h('span', { 'aria-hidden': 'true' }, ' *') : null]) : null,
                h('div', { class: 'pf-select', ref: root }, [
                    h(
                        'button',
                        {
                            type: 'button',
                            class: 'pf-control pf-select-trigger',
                            'aria-haspopup': 'listbox',
                            'aria-expanded': open.value,
                            'aria-labelledby': props.label ? `${id}-label` : undefined,
                            'aria-invalid': props.error ? true : undefined,
                            disabled: props.disabled,
                            onClick: toggle,
                            onKeydown: open.value && !props.searchable ? onKey : undefined,
                        },
                        [h('span', { class: text.value ? undefined : 'pf-select-placeholder' }, text.value || props.placeholder), h('span', { 'aria-hidden': 'true' }, '▾')],
                    ),
                    open.value
                        ? h('div', { class: 'pf-select-panel', ref: panel }, [
                              props.searchable
                                  ? h('input', {
                                        type: 'text',
                                        class: 'pf-control pf-select-filter',
                                        'aria-label': forgeWords().filter,
                                        value: filter.value,
                                        onVnodeMounted: (vnode: VNode) => (vnode.el as HTMLInputElement | null)?.focus(),
                                        onInput: (event: Event) => {
                                            filter.value = (event.target as HTMLInputElement).value;
                                            active.value = -1;
                                        },
                                        onKeydown: onKey,
                                    })
                                  : null,
                              h('ul', { class: 'pf-select-list', role: 'listbox', 'aria-multiselectable': props.multiple || undefined }, [
                                  shown.value.length === 0 ? h('li', { class: 'pf-select-empty' }, '—') : null,
                                  ...shown.value.map((option, index) =>
                                      h('li', { key: String(option.value), role: 'option', 'aria-selected': chosen.value.includes(String(option.value)), 'data-active': index === active.value, class: 'pf-select-option', onClick: () => pick(option) }, [
                                          props.multiple ? h('input', { type: 'checkbox', readonly: true, tabindex: -1, checked: chosen.value.includes(String(option.value)) }) : null,
                                          option.label,
                                      ]),
                                  ),
                              ]),
                          ])
                        : null,
                    ...(props.name ? chosen.value.map((value) => h('input', { key: value, type: 'hidden', name: props.multiple ? `${props.name}[]` : props.name, value })) : []),
                ]),
                props.error ? h('p', { class: 'pf-field-error', role: 'alert' }, props.error) : null,
            ]);
    },
});

// ── Modal ───────────────────────────────────────────────────────────────────

export const ForgeModal = defineComponent({
    name: 'ForgeModal',
    props: { modelValue: Boolean, title: { type: String, default: '' }, subtitle: { type: String, default: null }, size: { type: String, default: 'md' } },
    emits: ['update:modelValue'],
    setup(props, { slots, emit }) {
        const titleId = uid('pf-modal');
        const close = () => emit('update:modelValue', false);
        // The Tab key stays inside, and the focus goes back on close (forge-modal's x-trap).
        const panel = ref<HTMLElement | null>(null);
        let release: (() => void) | null = null;
        watch(
            () => props.modelValue && panel.value,
            (el) => {
                release?.();
                release = el ? trapFocus(el) : null;
            },
            { flush: 'post' },
        );
        onBeforeUnmount(() => release?.());
        const onKey = (event: KeyboardEvent) => {
            if (props.modelValue && event.key === 'Escape') {
                close();
            }
        };
        onMounted(() => document.addEventListener('keydown', onKey));
        onBeforeUnmount(() => document.removeEventListener('keydown', onKey));

        return () =>
            props.modelValue
                ? h('div', { class: 'pf pf-overlay', onMousedown: (event: MouseEvent) => event.target === event.currentTarget && close() }, [
                      h('div', { class: 'pf-modal', ref: panel, 'data-size': props.size, role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId }, [
                          h('div', { class: 'pf-modal-head' }, [
                              h('div', [h('h2', { class: 'pf-modal-title', id: titleId }, props.title), props.subtitle ? h('p', { class: 'pf-modal-subtitle' }, props.subtitle) : null]),
                              h('button', { type: 'button', class: 'pf-modal-close', 'aria-label': forgeWords().close, title: forgeWords().close, onClick: close }, icon('x', 20)),
                          ]),
                          h('div', { class: 'pf-modal-body' }, slots.default?.()),
                          slots.footer ? h('div', { class: 'pf-modal-foot' }, slots.footer()) : null,
                      ]),
                  ])
                : null;
    },
});

// ── Tabs, stepper, pagination, table ────────────────────────────────────────

export interface ForgeTabDef {
    id: string;
    label: string;
}

/** The content of a tab is the slot named after its id. */
export const ForgeTabs = defineComponent({
    name: 'ForgeTabs',
    props: { tabs: { type: Array as PropType<ForgeTabDef[]>, default: () => [] }, color, defaultTab: { type: String, default: null } },
    emits: ['change'],
    setup(props, { slots, emit }) {
        const base = uid('pf-tabs');
        const active = ref(props.defaultTab ?? props.tabs[0]?.id ?? '');
        const select = (id: string) => {
            active.value = id;
            emit('change', id);
        };
        const move = (delta: number) => {
            const index = props.tabs.findIndex((tab) => tab.id === active.value);
            const next = props.tabs[(index + delta + props.tabs.length) % props.tabs.length];
            if (next) {
                select(next.id);
                document.getElementById(`${base}-tab-${next.id}`)?.focus();
            }
        };

        return () =>
            h('div', { class: 'pf', 'data-color': props.color }, [
                h(
                    'div',
                    { class: 'pf-tablist', role: 'tablist' },
                    props.tabs.map((tab) =>
                        h(
                            'button',
                            {
                                key: tab.id,
                                type: 'button',
                                role: 'tab',
                                id: `${base}-tab-${tab.id}`,
                                class: 'pf-tab',
                                'aria-selected': tab.id === active.value,
                                'aria-controls': `${base}-panel-${tab.id}`,
                                tabindex: tab.id === active.value ? 0 : -1,
                                onClick: () => select(tab.id),
                                onKeydown: (event: KeyboardEvent) => {
                                    if (event.key === 'ArrowRight') {
                                        move(1);
                                    } else if (event.key === 'ArrowLeft') {
                                        move(-1);
                                    }
                                },
                            },
                            tab.label,
                        ),
                    ),
                ),
                ...props.tabs
                    .filter((tab) => tab.id === active.value)
                    .map((tab) => h('div', { key: tab.id, role: 'tabpanel', id: `${base}-panel-${tab.id}`, 'aria-labelledby': `${base}-tab-${tab.id}`, class: 'pf-tabpanel' }, slots[tab.id]?.())),
            ]);
    },
});

export const ForgeStepper = defineComponent({
    name: 'ForgeStepper',
    props: { steps: { type: Array as PropType<(string | { label: string })[]>, default: () => [] }, currentStep: { type: Number, default: 1 }, color },
    setup(props) {
        return () =>
            h(
                'ol',
                { class: 'pf pf-stepper', 'data-color': props.color },
                props.steps.map((step, index) => {
                    const number = index + 1;
                    const state = number < props.currentStep ? 'done' : number === props.currentStep ? 'current' : 'todo';

                    return h('li', { key: index, class: 'pf-step', 'data-state': state, 'aria-current': state === 'current' ? 'step' : undefined }, [
                        h('span', { class: 'pf-step-dot' }, state === 'done' ? '✓' : String(number)),
                        typeof step === 'string' ? step : step.label,
                    ]);
                }),
            );
    },
});

export const ForgePagination = defineComponent({
    name: 'ForgePagination',
    props: { page: { type: Number, required: true }, lastPage: { type: Number, required: true } },
    emits: ['change'],
    setup(props, { emit }) {
        return () =>
            h('nav', { class: 'pf pf-pagination', 'aria-label': 'Pagination' }, [
                h('button', { type: 'button', class: 'pf-page', disabled: props.page <= 1, 'aria-label': forgeWords().previousPage, onClick: () => emit('change', props.page - 1) }, '‹'),
                ...pagesAround(props.page, props.lastPage).map((number) =>
                    h('button', { key: number, type: 'button', class: 'pf-page', 'aria-current': number === props.page ? 'page' : undefined, onClick: () => emit('change', number) }, String(number)),
                ),
                h('button', { type: 'button', class: 'pf-page', disabled: props.page >= props.lastPage, 'aria-label': forgeWords().nextPage, onClick: () => emit('change', props.page + 1) }, '›'),
            ]);
    },
});

export interface ForgeTableHeader {
    key: string;
    label: string;
}

/** A cell can be customised with the slot `cell-<key>` (`{ row, value }`). */
export const ForgeTable = defineComponent({
    name: 'ForgeTable',
    props: {
        headers: { type: Array as PropType<(ForgeTableHeader | string)[]>, default: () => [] },
        rows: { type: Array as PropType<Record<string, unknown>[] | null>, default: null },
        searchable: Boolean,
        sortable: Boolean,
        emptyMessage: { type: String, default: 'Nenhum registro encontrado.' },
    },
    setup(props, { slots }) {
        const search = ref('');
        const sort = ref<{ key: string; asc: boolean } | null>(null);
        const columns = computed(() => props.headers.map((header) => (typeof header === 'string' ? { key: header, label: header } : header)));
        const shown = computed(() => {
            let list = props.rows ?? [];
            if (search.value !== '') {
                list = list.filter((row) => columns.value.some((column) => fold(String(row[column.key] ?? '')).includes(fold(search.value))));
            }
            if (sort.value) {
                const { key, asc } = sort.value;
                list = [...list].sort((a, b) => String(a[key] ?? '').localeCompare(String(b[key] ?? ''), undefined, { numeric: true }) * (asc ? 1 : -1));
            }

            return list;
        });

        return () =>
            h('div', { class: 'pf pf-table-wrap' }, [
                props.searchable
                    ? h('div', { class: 'pf-table-tools' }, [
                          h('input', { type: 'search', class: 'pf-control', 'aria-label': forgeWords().search, placeholder: forgeWords().searchPlaceholder, value: search.value, onInput: (event: Event) => (search.value = (event.target as HTMLInputElement).value) }),
                      ])
                    : null,
                h('table', { class: 'pf-table' }, [
                    h('thead', [
                        h(
                            'tr',
                            columns.value.map((column) =>
                                h('th', { key: column.key, 'aria-sort': sort.value?.key === column.key ? (sort.value.asc ? 'ascending' : 'descending') : undefined }, [
                                    props.sortable
                                        ? h(
                                              'button',
                                              { type: 'button', class: 'pf-th-btn', onClick: () => (sort.value = { key: column.key, asc: sort.value?.key === column.key ? !sort.value.asc : true }) },
                                              `${column.label} ${sort.value?.key === column.key ? (sort.value.asc ? '▲' : '▼') : ''}`,
                                          )
                                        : column.label,
                                ]),
                            ),
                        ),
                    ]),
                    h('tbody', [
                        shown.value.length === 0 ? h('tr', [h('td', { colspan: Math.max(1, columns.value.length) }, props.emptyMessage)]) : null,
                        ...shown.value.map((row, index) =>
                            h(
                                'tr',
                                { key: index },
                                columns.value.map((column) => h('td', { key: column.key }, slots[`cell-${column.key}`]?.({ row, value: row[column.key] }) ?? String(row[column.key] ?? ''))),
                            ),
                        ),
                    ]),
                ]),
            ]);
    },
});

// ── Notification and toast host ─────────────────────────────────────────────

export const ForgeNotification = defineComponent({
    name: 'ForgeNotification',
    props: { modelValue: Boolean, color, title: { type: String, default: '' }, text: { type: String, default: '' }, position: { type: String, default: 'top-right' }, duration: { type: Number, default: 4000 } },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        let timer: ReturnType<typeof setTimeout> | undefined;
        const close = () => emit('update:modelValue', false);

        watch(
            () => props.modelValue,
            (show) => {
                clearTimeout(timer);
                if (show && props.duration > 0) {
                    timer = setTimeout(close, props.duration);
                }
            },
            { immediate: true },
        );
        onBeforeUnmount(() => clearTimeout(timer));

        return () =>
            props.modelValue
                ? h('div', { class: 'pf pf-notification', 'data-color': props.color, 'data-position': props.position, role: 'status' }, [
                      h('div', { style: { flex: 1 } }, [props.title ? h('strong', { class: 'pf-notification-title' }, props.title) : null, props.text]),
                      h('button', { type: 'button', class: 'pf-x', 'aria-label': forgeWords().close, title: forgeWords().close, onClick: close }, icon('x')),
                  ])
                : null;
    },
});

export interface ForgeToast {
    id: number;
    title: string;
    color: string;
}

const toasts = ref<ForgeToast[]>([]);
let toastSeq = 0;

/** Shows a toast in the `<ForgeToastHost>` on the page. */
export function forgeToast(title: string, shade = 'primary', duration = 4000): void {
    const id = ++toastSeq;
    toasts.value = [...toasts.value, { id, title, color: shade }];

    if (duration > 0) {
        setTimeout(() => {
            toasts.value = toasts.value.filter((toast) => toast.id !== id);
        }, duration);
    }
}

/**
 * Where toasts appear — one per page. `forgeToast('Saved', 'success')` from
 * anywhere, or a `ptah-toast` window event (`detail: { title, color }`), the
 * event the Livewire stack dispatches.
 */
export const ForgeToastHost = defineComponent({
    name: 'ForgeToastHost',
    setup() {
        const onEvent = (event: Event) => {
            const detail = (event as CustomEvent).detail ?? {};
            forgeToast(String(detail.title ?? ''), String(detail.color ?? 'primary'));
        };
        onMounted(() => window.addEventListener('ptah-toast', onEvent));
        onBeforeUnmount(() => window.removeEventListener('ptah-toast', onEvent));

        return () =>
            h(
                'div',
                { class: 'pf pf-toast-host', 'aria-live': 'polite' },
                toasts.value.map((toast) =>
                    h('div', { key: toast.id, class: 'pf pf-toast', 'data-color': toast.color, role: 'status' }, [
                        h('span', { class: 'pf-toast-text' }, toast.title),
                        h('button', { type: 'button', class: 'pf-x', 'aria-label': forgeWords().close, title: forgeWords().close, onClick: () => (toasts.value = toasts.value.filter((item) => item.id !== toast.id)) }, icon('x')),
                    ]),
                ),
            );
    },
});
