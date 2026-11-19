/**
 * Draws the config editor's form nodes (core/admin/crud-config/forms.ts) with
 * the forge kit. What a tab holds is decided in core; this only draws it.
 * Mirrors react/admin/crud-config/nodes.tsx.
 */
import { defineComponent, h } from 'vue';
import type { CSSProperties, PropType, VNode } from 'vue';
import { cssStyle, text, truthy } from '../../../core/admin/crud-config';
import type { FormBlock, FormNode, GuideCard } from '../../../core/admin/crud-config';
import { ForgeAlert, ForgeCheckbox, ForgeInput, ForgeRadio, ForgeSelect, ForgeTextarea } from '../../forge';

export type Read = (key: string) => unknown;
export type Write = (key: string, value: unknown) => void;

export const sectionStyle: CSSProperties = { padding: '16px', border: '1px solid var(--pc-line)', borderRadius: 'var(--pc-radius)', background: 'var(--pc-bg)', display: 'grid', gap: '12px' };
export const mutedStyle: CSSProperties = { margin: 0, fontSize: '12px', color: 'var(--pc-text-muted)' };
export const monoStyle: CSSProperties = { fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace', fontSize: '12px' };
export const tagStyle: CSSProperties = { display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '2px 8px', borderRadius: '999px', fontSize: '11px', background: 'var(--pc-bg-soft)', color: 'var(--pc-text-strong)', border: '1px solid var(--pc-line)' };
const headStyle: CSSProperties = { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px', paddingBottom: '8px', borderBottom: '1px solid var(--pc-line)' };
const titleStyle: CSSProperties = { margin: 0, fontSize: '14px', fontWeight: 600, color: 'var(--pc-text-strong)' };
const codeBox: CSSProperties = { ...monoStyle, margin: 0, padding: '12px', borderRadius: '6px', background: '#0f172a', color: '#e2e8f0', lineHeight: 1.6, overflowX: 'auto' };
const toneColor = { muted: '#94a3b8', accent: '#a5b4fc', warn: '#fde047' } as const;

const grid = (columns: 1 | 2 | 3 = 2): CSSProperties => ({
    display: 'grid',
    gap: '12px',
    alignItems: 'start',
    gridTemplateColumns: columns === 1 ? 'minmax(0, 1fr)' : `repeat(auto-fit, minmax(min(100%, ${columns === 3 ? 180 : 240}px), 1fr))`,
});

/** Nodes that always take the whole row. */
const isWide = (node: FormNode): boolean => !['text', 'number', 'check', 'select'].includes(node.kind) || ('wide' in node && node.wide === true);

let radios = 0;

const hint = (value?: string) => (value ? h('p', { class: 'pf-field-message', style: mutedStyle }, value) : null);

const rowsGrid = (rows: [string, string][]) =>
    h(
        'dl',
        { style: { display: 'grid', gridTemplateColumns: 'auto 1fr', gap: '2px 16px', margin: 0, padding: '8px', borderRadius: '4px', background: 'var(--pc-bg-soft)', ...monoStyle } },
        rows.map(([label, value], index) => h('div', { key: index, style: { display: 'contents' } }, [h('dt', { style: { color: 'var(--pc-text-muted)' } }, label), h('dd', { style: { margin: 0 } }, value)])),
    );

const card = (item: GuideCard, index: number) =>
    h('div', { key: index, style: { ...sectionStyle, gap: '6px' } }, [
        item.title ? h('strong', { style: { fontSize: '13px' } }, item.title) : null,
        item.text ? h('p', { style: mutedStyle }, item.text) : null,
        item.rows ? rowsGrid(item.rows) : null,
        item.items ? h('ul', { style: { margin: 0, paddingLeft: '18px', fontSize: '12px' } }, item.items.map((entry, at) => h('li', { key: at }, entry))) : null,
        ...(item.after ? [h('p', { style: mutedStyle }, item.after.title), rowsGrid(item.after.rows)] : []),
        item.sample ? h('div', { style: { padding: '6px 12px', borderRadius: '4px', fontSize: '12px', ...cssStyle(item.sample.style) } }, item.sample.text) : null,
        item.foot ? h('p', { style: { ...mutedStyle, fontStyle: 'italic' } }, item.foot) : null,
    ]);

function badges(node: Extract<FormNode, { kind: 'badges' }>, read: Read, write: Write): VNode {
    const raw = read(node.key);
    const list = (Array.isArray(raw) ? raw : []) as Record<string, unknown>[];
    const update = (index: number, key: string, value: string) => write(node.key, list.map((badge, at) => (at === index ? { ...badge, [key]: value } : badge)));
    const w = node.words;

    return h('fieldset', { style: { border: 0, margin: 0, padding: 0, display: 'grid', gap: '8px' } }, [
        h('legend', { class: 'pf-field-label' }, node.label),
        hint(node.hint),
        ...list.map((badge, index) => {
            const color = text(badge.color) || 'gray';
            const custom = color.startsWith('#');

            return h('div', { key: index, style: { display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '8px' } }, [
                h('input', { class: 'pf-control', style: { ...monoStyle, flex: '1 1 120px' }, 'aria-label': `${w.value} ${index + 1}`, placeholder: w.value, value: text(badge.value), onInput: (event: Event) => update(index, 'value', (event.target as HTMLInputElement).value) }),
                h('input', { class: 'pf-control', style: { flex: '1 1 120px' }, 'aria-label': `${w.label} ${index + 1}`, placeholder: w.label, value: text(badge.label), onInput: (event: Event) => update(index, 'label', (event.target as HTMLInputElement).value) }),
                h('span', { role: 'radiogroup', 'aria-label': `${w.color} ${index + 1}`, style: { display: 'inline-flex', alignItems: 'center', gap: '4px' } }, [
                    ...node.colors.map((swatch) =>
                        h('button', {
                            key: swatch.key,
                            type: 'button',
                            role: 'radio',
                            'aria-checked': color === swatch.key,
                            'aria-label': swatch.label,
                            title: swatch.label,
                            onClick: () => update(index, 'color', swatch.key),
                            style: { width: '16px', height: '16px', borderRadius: '999px', padding: 0, cursor: 'pointer', background: swatch.hex, border: color === swatch.key ? '2px solid var(--pc-text-strong)' : '2px solid var(--pc-bg)' },
                        }),
                    ),
                    h('input', { type: 'color', 'aria-label': `${w.custom} ${index + 1}`, title: w.custom, value: custom ? color : '#6366f1', onInput: (event: Event) => update(index, 'color', (event.target as HTMLInputElement).value), style: { width: '24px', height: '20px', padding: 0, border: '1px solid var(--pc-line)' } }),
                    custom ? h('span', { style: { ...monoStyle, fontSize: '10px', color } }, color) : null,
                ]),
                h('input', { class: 'pf-control', style: { flex: '1 1 120px' }, 'aria-label': `${w.icon} ${index + 1}`, placeholder: w.icon, value: text(badge.icon), onInput: (event: Event) => update(index, 'icon', (event.target as HTMLInputElement).value) }),
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
                    style: node.mono ? monoStyle : undefined,
                    'onUpdate:modelValue': (next: string) => write(node.key, next),
                }),
                node.icon && value !== '' ? h('span', { style: { display: 'inline-flex', marginTop: '6px', fontSize: '20px' }, 'data-testid': 'cfg-icon-preview' }, [h('i', { class: value, 'aria-hidden': 'true' })]) : null,
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
                    style: node.mono ? monoStyle : undefined,
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

            return h('fieldset', { style: { border: 0, margin: 0, padding: 0 } }, [
                h('legend', { class: 'pf-field-label' }, node.label),
                h(
                    'div',
                    { style: { display: 'flex', flexWrap: 'wrap', gap: '16px' } },
                    node.options.map((option) => h(ForgeRadio, { key: option.value, name, label: option.label, value: option.value, modelValue: text(read(node.key)), 'onUpdate:modelValue': (value: string) => write(node.key, value) })),
                ),
            ]);
        }
        case 'note': {
            const body = [
                node.text ? h('p', { style: { margin: 0, fontSize: '12px' } }, node.text) : null,
                node.items ? h('ul', { style: { margin: '4px 0 0', paddingLeft: '18px', fontSize: '12px' } }, node.items.map((item, index) => h('li', { key: index }, item))) : null,
            ];
            if (node.collapsible) {
                return h('details', { style: { fontSize: '12px' } }, [h('summary', { style: { cursor: 'pointer', color: 'var(--pc-primary)' } }, node.title), h('div', { style: { marginTop: '8px' } }, body)]);
            }
            if (node.tone === 'muted') {
                return h('div', { style: mutedStyle }, [node.title ? h('strong', `${node.title} `) : null, ...body]);
            }

            return h(ForgeAlert, { color: node.tone === 'info' ? 'primary' : node.tone, title: node.title ?? '' }, () => body);
        }
        case 'guide':
            return h('details', { open: node.open, style: { ...sectionStyle, background: 'var(--pc-bg-soft)' } }, [
                h('summary', { style: { cursor: 'pointer', fontWeight: 600, fontSize: '13px' } }, node.title),
                h('div', { style: { display: 'grid', gap: '12px', marginTop: '12px' } }, [node.intro ? h('p', { style: mutedStyle }, node.intro) : null, ...node.cards.map(card), node.foot ? h('p', { style: mutedStyle }, node.foot) : null]),
            ]);
        case 'heading':
            return h('h4', { style: titleStyle }, node.text);
        case 'chips':
            return h('div', [
                node.label ? h('p', { style: { ...mutedStyle, marginBottom: '6px' } }, node.label) : null,
                h('div', { style: { display: 'flex', flexWrap: 'wrap', gap: '6px' } }, node.items.map((item) => h('span', { key: item, style: { ...tagStyle, ...monoStyle } }, item))),
            ]);
        case 'path':
            return h('div', { style: { display: 'grid', gap: '6px' } }, [
                h(
                    'div',
                    { style: { display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '6px' } },
                    node.parts.map((part, index) =>
                        h('span', { key: index, style: { display: 'inline-flex', alignItems: 'center', gap: '6px' } }, [
                            h('span', { style: { ...tagStyle, ...monoStyle, fontWeight: index === node.parts.length - 1 ? 700 : 400 } }, part),
                            index < node.parts.length - 1 ? h('span', { 'aria-hidden': 'true' }, '→') : null,
                        ]),
                    ),
                ),
                h('p', { style: { ...mutedStyle, ...monoStyle } }, node.foot),
            ]);
        case 'sample':
            return h('div', { style: { display: 'flex', alignItems: 'center', gap: '12px', padding: '10px 12px', border: '1px solid var(--pc-line)', borderRadius: '6px', background: 'var(--pc-bg-soft)' } }, [
                h('span', { style: mutedStyle }, node.label),
                h('span', { class: node.className, style: { display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '4px 8px', borderRadius: '4px', ...cssStyle(node.style) }, 'data-testid': 'cfg-sample' }, [
                    node.icon ? h('span', { class: node.icon, 'aria-hidden': 'true' }) : null,
                    node.text,
                ]),
                node.code ? h('code', { style: { ...monoStyle, marginLeft: 'auto', color: 'var(--pc-text-muted)' } }, node.code) : null,
            ]);
        case 'code':
            return h(
                'pre',
                { style: codeBox },
                node.lines.map((line, index) => h('div', { key: index, style: { color: line.tone ? toneColor[line.tone] : undefined } }, line.text)),
            );
        case 'presets':
            return h('div', [
                h('p', { style: { ...mutedStyle, fontWeight: 600, marginBottom: '6px' } }, node.label),
                h(
                    'div',
                    { style: { display: 'flex', flexWrap: 'wrap', gap: '6px' } },
                    node.items.map((item) => h('button', { key: item.label, type: 'button', style: { ...tagStyle, cursor: 'pointer', ...cssStyle(item.value) }, onClick: () => write(node.key, item.value) }, item.label)),
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
        { style: { display: 'grid', gap: '16px' } },
        blocks.map((block, index) =>
            h('section', { key: index, style: sectionStyle }, [
                block.title || block.toggle
                    ? h('div', { style: headStyle }, [
                          block.title ? h('h4', { style: titleStyle }, block.title) : h('span'),
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
                          { style: grid(block.columns) },
                          block.nodes.map((node, at) => h('div', { key: at, style: isWide(node) ? { gridColumn: '1 / -1' } : undefined }, [renderNode(node, read, write)])),
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
