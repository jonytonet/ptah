/**
 * Draws the config editor's form nodes (core/admin/crud-config/forms.ts) with
 * the forge kit. What a tab holds is decided in core; this only draws it.
 * Mirrors react/admin/crud-config/nodes.tsx.
 */
import { defineComponent, h } from 'vue';
import type { PropType, VNode } from 'vue';
import { cssStyle, text, truthy } from '../../../core/admin/crud-config';
import type { FormBlock, FormNode, GuideCard } from '../../../core/admin/crud-config';
import { ForgeAlert, ForgeCheckbox, ForgeInput, ForgeRadio, ForgeSelect, ForgeTextarea } from '../../forge';

export type Read = (key: string) => unknown;
export type Write = (key: string, value: unknown) => void;


/** Nodes that always take the whole row. */
const isWide = (node: FormNode): boolean => !['text', 'number', 'check', 'select'].includes(node.kind) || ('wide' in node && node.wide === true);

let radios = 0;

const hint = (value?: string) => (value ? h('p', { class: 'pf-field-message pa-muted' }, value) : null);

const rowsGrid = (rows: [string, string][]) =>
    h(
        'dl',
        { class: 'pa-cc-defs' },
        rows.map(([label, value], index) => h('div', { key: index, class: 'pa-contents' }, [h('dt', { class: 'pa-text-muted' }, label), h('dd', { class: 'pa-flat' }, value)])),
    );

const card = (item: GuideCard, index: number) =>
    h('div', { key: index, class: 'pa-panel pa-panel--tight' }, [
        item.title ? h('strong', { class: 'pa-text-sm' }, item.title) : null,
        item.text ? h('p', { class: 'pa-muted' }, item.text) : null,
        item.rows ? rowsGrid(item.rows) : null,
        item.items ? h('ul', { class: 'pa-cc-list' }, item.items.map((entry, at) => h('li', { key: at }, entry))) : null,
        ...(item.after ? [h('p', { class: 'pa-muted' }, item.after.title), rowsGrid(item.after.rows)] : []),
        item.sample ? h('div', { class: 'pa-cc-sample', style: cssStyle(item.sample.style) }, item.sample.text) : null,
        item.foot ? h('p', { class: 'pa-muted pa-muted--italic' }, item.foot) : null,
    ]);

function badges(node: Extract<FormNode, { kind: 'badges' }>, read: Read, write: Write): VNode {
    const raw = read(node.key);
    const list = (Array.isArray(raw) ? raw : []) as Record<string, unknown>[];
    const update = (index: number, key: string, value: string) => write(node.key, list.map((badge, at) => (at === index ? { ...badge, [key]: value } : badge)));
    const w = node.words;

    return h('fieldset', { class: 'pa-fieldset-grid' }, [
        h('legend', { class: 'pf-field-label' }, node.label),
        hint(node.hint),
        ...list.map((badge, index) => {
            const color = text(badge.color) || 'gray';
            const custom = color.startsWith('#');

            return h('div', { key: index, class: 'pa-flex-wrap-center-g2' }, [
                h('input', { class: 'pf-control pa-mono pa-flex-120', 'aria-label': `${w.value} ${index + 1}`, placeholder: w.value, value: text(badge.value), onInput: (event: Event) => update(index, 'value', (event.target as HTMLInputElement).value) }),
                h('input', { class: 'pf-control pa-flex-120', 'aria-label': `${w.label} ${index + 1}`, placeholder: w.label, value: text(badge.label), onInput: (event: Event) => update(index, 'label', (event.target as HTMLInputElement).value) }),
                h('span', { role: 'radiogroup', 'aria-label': `${w.color} ${index + 1}`, class: 'pa-inline-center-g1' }, [
                    ...node.colors.map((swatch) =>
                        h('button', {
                            key: swatch.key,
                            type: 'button',
                            role: 'radio',
                            'aria-checked': color === swatch.key,
                            'aria-label': swatch.label,
                            title: swatch.label,
                            onClick: () => update(index, 'color', swatch.key),
                            class: 'pa-cc-swatch',
                            style: { background: swatch.hex },
                        }),
                    ),
                    h('input', { type: 'color', 'aria-label': `${w.custom} ${index + 1}`, title: w.custom, value: custom ? color : '#6366f1', onInput: (event: Event) => update(index, 'color', (event.target as HTMLInputElement).value), class: 'pa-cc-colorpick' }),
                    custom ? h('span', { class: 'pa-mono', style: { color } }, color) : null,
                ]),
                h('input', { class: 'pf-control pa-flex-120', 'aria-label': `${w.icon} ${index + 1}`, placeholder: w.icon, value: text(badge.icon), onInput: (event: Event) => update(index, 'icon', (event.target as HTMLInputElement).value) }),
                h('button', { type: 'button', class: 'pc-icon-btn pc-icon-btn--danger', 'aria-label': `${w.remove} ${index + 1}`, onClick: () => write(node.key, list.filter((_, at) => at !== index)) }, '✕'),
            ]);
        }),
        h('div', [h('button', { type: 'button', class: 'pc-btn', onClick: () => write(node.key, [...list, { value: '', label: '', color: 'gray', icon: '' }]) }, w.add)]),
    ]);
}

/** One node. */
export function renderNode(node: FormNode, read: Read, write: Write): VNode | null {
    switch (node.kind) {
        case 'text': {
            const value = text(read(node.key));

            return h('div', [
                h(ForgeInput, {
                    label: node.label,
                    title: node.tip || undefined,
                    placeholder: node.placeholder ?? '',
                    modelValue: value,
                    disabled: node.disabled ?? false,
                    maxlength: node.maxLength,
                    message: node.hint ?? '',
                    ...(node.error ? { error: node.error } : {}),
                    'data-mono': node.mono ? 'true' : undefined,
                    'onUpdate:modelValue': (next: string) => write(node.key, next),
                }),
                node.icon && value !== '' ? h('span', { class: 'pa-cc-icon-preview', 'data-testid': 'cfg-icon-preview' }, [h('i', { class: value, 'aria-hidden': 'true' })]) : null,
            ]);
        }
        case 'textarea':
            return h('div', [
                h(ForgeTextarea, {
                    label: node.badge ? `${node.label} — ${node.badge}` : node.label,
                    title: node.tip || undefined,
                    placeholder: node.placeholder ?? '',
                    rows: node.rows,
                    modelValue: text(read(node.key)),
                    helper: node.hint ?? '',
                    'data-mono': node.mono ? 'true' : undefined,
                    'onUpdate:modelValue': (next: string) => write(node.key, next),
                }),
            ]);
        case 'number':
            return h(ForgeInput, {
                type: 'number',
                label: node.label,
                title: node.tip || undefined,
                placeholder: node.placeholder ?? '',
                min: node.min,
                max: node.max,
                step: node.step,
                message: node.hint ?? '',
                modelValue: text(read(node.key)),
                'onUpdate:modelValue': (next: string) => write(node.key, node.int ? (next === '' ? '' : Number(next)) : next),
            });
        case 'check':
            return h('div', { title: node.tip || undefined }, [h(ForgeCheckbox, { label: node.label, modelValue: truthy(read(node.key)), 'onUpdate:modelValue': (checked: boolean) => write(node.key, checked) }), hint(node.hint)]);
        case 'select': {
            const empty = node.options.find((option) => option.value === '');

            return h('div', { title: node.tip || undefined }, [
                h(ForgeSelect, {
                    label: node.label,
                    options: node.options,
                    modelValue: text(read(node.key)),
                    placeholder: empty?.label ?? '—',
                    searchable: node.searchable ?? false,
                    'onUpdate:modelValue': (value: unknown) => write(node.key, Array.isArray(value) ? String(value[0] ?? '') : String(value ?? '')),
                }),
                hint(node.hint),
            ]);
        }
        case 'radio': {
            const name = `cfg-radio-${++radios}`;

            return h('fieldset', { class: 'pa-fieldset' }, [
                h('legend', { class: 'pf-field-label' }, node.label),
                h(
                    'div',
                    { class: 'pa-flex-wrap-g4' },
                    node.options.map((option) => h(ForgeRadio, { key: option.value, name, label: option.label, value: option.value, modelValue: text(read(node.key)), 'onUpdate:modelValue': (value: string) => write(node.key, value) })),
                ),
            ]);
        }
        case 'note': {
            const body = [
                node.text ? h('p', { class: 'pa-text-xs-flat' }, node.text) : null,
                node.items ? h('ul', { class: 'pa-cc-list-spaced' }, node.items.map((item, index) => h('li', { key: index }, item))) : null,
            ];
            if (node.collapsible) {
                return h('details', { class: 'pa-text-xs' }, [h('summary', { class: 'pa-cc-link' }, node.title), h('div', { class: 'pa-mt-2' }, body)]);
            }
            if (node.tone === 'muted') {
                return h('div', { class: 'pa-muted' }, [node.title ? h('strong', `${node.title} `) : null, ...body]);
            }

            return h(ForgeAlert, { color: node.tone === 'info' ? 'primary' : node.tone, title: node.title ?? '' }, () => body);
        }
        case 'guide':
            return h('details', { open: node.open, class: 'pa-panel pa-panel--soft' }, [
                h('summary', { class: 'pa-cc-summary' }, node.title),
                h('div', { class: 'pa-grid-g3-top' }, [node.intro ? h('p', { class: 'pa-muted' }, node.intro) : null, ...node.cards.map(card), node.foot ? h('p', { class: 'pa-muted' }, node.foot) : null]),
            ]);
        case 'heading':
            return h('h4', { class: 'pa-cc-title' }, node.text);
        case 'chips':
            return h('div', [
                node.label ? h('p', { class: 'pa-cc-caption' }, node.label) : null,
                h('div', { class: 'pa-flex-wrap-g1-5' }, node.items.map((item) => h('span', { key: item, class: 'pa-tag pa-mono' }, item))),
            ]);
        case 'path':
            return h('div', { class: 'pa-grid-g1-5' }, [
                h(
                    'div',
                    { class: 'pa-flex-wrap-center-g1-5' },
                    node.parts.map((part, index) =>
                        h('span', { key: index, class: 'pa-inline-center-g1-5' }, [
                            h('span', { class: 'pa-tag pa-mono pa-cc-path-part', 'data-last': index === node.parts.length - 1 ? 'true' : undefined }, part),
                            index < node.parts.length - 1 ? h('span', { 'aria-hidden': 'true' }, '→') : null,
                        ]),
                    ),
                ),
                h('p', { class: 'pa-muted pa-mono' }, node.foot),
            ]);
        case 'sample':
            return h('div', { class: 'pa-cc-callout' }, [
                h('span', { class: 'pa-muted' }, node.label),
                h('span', { class: ['pa-cc-sample-chip', node.className], style: cssStyle(node.style), 'data-testid': 'cfg-sample' }, [
                    node.icon ? h('span', { class: node.icon, 'aria-hidden': 'true' }) : null,
                    node.text,
                ]),
                node.code ? h('code', { class: 'pa-cc-mono-end' }, node.code) : null,
            ]);
        case 'code':
            return h(
                'pre',
                { class: 'pa-cc-code-box' },
                node.lines.map((line, index) => h('div', { key: index, 'data-tone': line.tone }, line.text)),
            );
        case 'presets':
            return h('div', [
                h('p', { class: 'pa-cc-caption-strong' }, node.label),
                h(
                    'div',
                    { class: 'pa-flex-wrap-g1-5' },
                    node.items.map((item) => h('button', { key: item.label, type: 'button', class: 'pa-tag pa-cc-tag-pick', style: cssStyle(item.value), onClick: () => write(node.key, item.value) }, item.label)),
                ),
            ]);
        case 'badges':
            return badges(node, read, write);
        default:
            return null;
    }
}

/** A list of blocks — each a titled section with its grid of nodes. */
export function renderBlocks(blocks: FormBlock[], read: Read, write: Write): VNode {
    return h(
        'div',
        { class: 'pa-grid-g4' },
        blocks.map((block, index) =>
            h('section', { key: index, class: 'pa-panel' }, [
                block.title || block.toggle
                    ? h('div', { class: 'pa-cc-block-head' }, [
                          block.title ? h('h4', { class: 'pa-cc-title' }, block.title) : h('span'),
                          block.toggle
                              ? h('span', { title: block.toggle.tip || undefined }, [
                                    h(ForgeCheckbox, { label: block.toggle.label, modelValue: truthy(read(block.toggle.key)), 'onUpdate:modelValue': (checked: boolean) => write(block.toggle!.key, checked) }),
                                ])
                              : null,
                      ])
                    : null,
                block.nodes.length > 0
                    ? h(
                          'div',
                          { class: 'pa-cc-grid', 'data-cols': block.columns },
                          block.nodes.map((node, at) => h('div', { key: at, class: isWide(node) ? 'pc-form-field--wide' : undefined }, [renderNode(node, read, write)])),
                      )
                    : null,
            ]),
        ),
    );
}

/** The blocks as a component, for a template. */
export const PtahConfigBlocks = defineComponent({
    name: 'PtahConfigBlocks',
    props: {
        blocks: { type: Array as PropType<FormBlock[]>, required: true },
        read: { type: Function as PropType<Read>, required: true },
        write: { type: Function as PropType<Write>, required: true },
    },
    setup(props) {
        return () => renderBlocks(props.blocks, props.read, props.write);
    },
});
