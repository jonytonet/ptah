/**
 * Draws the config editor's form nodes (core/admin/crud-config/forms.ts) with
 * the forge kit. What a tab holds is decided in core; this only draws it.
 * Mirrors vue/admin/crud-config/nodes.ts.
 */
import { useId } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import { cssStyle, text, truthy } from '../../../core/admin/crud-config';
import type { FormBlock, FormNode, GuideCard } from '../../../core/admin/crud-config';
import { ForgeAlert, ForgeCheckbox, ForgeInput, ForgeRadio, ForgeSelect, ForgeTextarea } from '../../forge';

export type Read = (key: string) => unknown;
export type Write = (key: string, value: unknown) => void;

export const sectionStyle: CSSProperties = { padding: 16, border: '1px solid var(--pc-line)', borderRadius: 'var(--pc-radius)', background: 'var(--pc-bg)', display: 'grid', gap: 12 };
export const mutedStyle: CSSProperties = { margin: 0, fontSize: 12, color: 'var(--pc-text-muted)' };
export const monoStyle: CSSProperties = { fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace', fontSize: 12 };
export const tagStyle: CSSProperties = { display: 'inline-flex', alignItems: 'center', gap: 4, padding: '2px 8px', borderRadius: 999, fontSize: 11, background: 'var(--pc-bg-soft)', color: 'var(--pc-text-strong)', border: '1px solid var(--pc-line)' };
const headStyle: CSSProperties = { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, paddingBottom: 8, borderBottom: '1px solid var(--pc-line)' };
const titleStyle: CSSProperties = { margin: 0, fontSize: 14, fontWeight: 600, color: 'var(--pc-text-strong)' };
const codeBox: CSSProperties = { ...monoStyle, margin: 0, padding: 12, borderRadius: 6, background: '#0f172a', color: '#e2e8f0', lineHeight: 1.6, overflowX: 'auto' };
const toneColor = { muted: '#94a3b8', accent: '#a5b4fc', warn: '#fde047' } as const;

const grid = (columns: 1 | 2 | 3 = 2): CSSProperties => ({
    display: 'grid',
    gap: 12,
    alignItems: 'start',
    gridTemplateColumns: columns === 1 ? 'minmax(0, 1fr)' : `repeat(auto-fit, minmax(min(100%, ${columns === 3 ? 180 : 240}px), 1fr))`,
});

/** Nodes that always take the whole row. */
const isWide = (node: FormNode): boolean => !['text', 'number', 'check', 'select'].includes(node.kind) || ('wide' in node && node.wide === true);

function Hint({ text: value }: { text?: string }) {
    return value ? <p className="pf-field-message" style={mutedStyle}>{value}</p> : null;
}

function Rows({ rows }: { rows: [string, string][] }) {
    return (
        <dl style={{ display: 'grid', gridTemplateColumns: 'auto 1fr', gap: '2px 16px', margin: 0, padding: 8, borderRadius: 4, background: 'var(--pc-bg-soft)', ...monoStyle }}>
            {rows.map(([label, value], index) => (
                <div key={index} style={{ display: 'contents' }}>
                    <dt style={{ color: 'var(--pc-text-muted)' }}>{label}</dt>
                    <dd style={{ margin: 0 }}>{value}</dd>
                </div>
            ))}
        </dl>
    );
}

function Card({ card }: { card: GuideCard }) {
    return (
        <div style={{ ...sectionStyle, gap: 6 }}>
            {card.title && <strong style={{ fontSize: 13 }}>{card.title}</strong>}
            {card.text && <p style={mutedStyle}>{card.text}</p>}
            {card.rows && <Rows rows={card.rows} />}
            {card.items && (
                <ul style={{ margin: 0, paddingLeft: 18, fontSize: 12 }}>
                    {card.items.map((item, index) => (
                        <li key={index}>{item}</li>
                    ))}
                </ul>
            )}
            {card.after && (
                <>
                    <p style={mutedStyle}>{card.after.title}</p>
                    <Rows rows={card.after.rows} />
                </>
            )}
            {card.sample && (
                <div style={{ padding: '6px 12px', borderRadius: 4, fontSize: 12, ...cssStyle(card.sample.style) }}>{card.sample.text}</div>
            )}
            {card.foot && <p style={{ ...mutedStyle, fontStyle: 'italic' }}>{card.foot}</p>}
        </div>
    );
}

function Badges({ node, read, write }: { node: Extract<FormNode, { kind: 'badges' }>; read: Read; write: Write }) {
    const raw = read(node.key);
    const badges = (Array.isArray(raw) ? raw : []) as Record<string, unknown>[];
    const update = (index: number, key: string, value: string) => write(node.key, badges.map((badge, at) => (at === index ? { ...badge, [key]: value } : badge)));
    const w = node.words;

    return (
        <fieldset style={{ border: 0, margin: 0, padding: 0, display: 'grid', gap: 8 }}>
            <legend className="pf-field-label">{node.label}</legend>
            <Hint text={node.hint} />
            {badges.map((badge, index) => {
                const color = text(badge.color) || 'gray';
                const custom = color.startsWith('#');

                return (
                    <div key={index} style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 8 }}>
                        <input className="pf-control" style={{ ...monoStyle, flex: '1 1 120px' }} aria-label={`${w.value} ${index + 1}`} placeholder={w.value} value={text(badge.value)} onChange={(event) => update(index, 'value', event.target.value)} />
                        <input className="pf-control" style={{ flex: '1 1 120px' }} aria-label={`${w.label} ${index + 1}`} placeholder={w.label} value={text(badge.label)} onChange={(event) => update(index, 'label', event.target.value)} />
                        <span role="radiogroup" aria-label={`${w.color} ${index + 1}`} style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                            {node.colors.map((swatch) => (
                                <button
                                    key={swatch.key}
                                    type="button"
                                    role="radio"
                                    aria-checked={color === swatch.key}
                                    aria-label={swatch.label}
                                    title={swatch.label}
                                    onClick={() => update(index, 'color', swatch.key)}
                                    style={{ width: 16, height: 16, borderRadius: 999, padding: 0, cursor: 'pointer', background: swatch.hex, border: color === swatch.key ? '2px solid var(--pc-text-strong)' : '2px solid var(--pc-bg)' }}
                                />
                            ))}
                            <input type="color" aria-label={`${w.custom} ${index + 1}`} title={w.custom} value={custom ? color : '#6366f1'} onChange={(event) => update(index, 'color', event.target.value)} style={{ width: 24, height: 20, padding: 0, border: '1px solid var(--pc-line)' }} />
                            {custom && <span style={{ ...monoStyle, fontSize: 10, color }}>{color}</span>}
                        </span>
                        <input className="pf-control" style={{ flex: '1 1 120px' }} aria-label={`${w.icon} ${index + 1}`} placeholder={w.icon} value={text(badge.icon)} onChange={(event) => update(index, 'icon', event.target.value)} />
                        <button type="button" className="pc-icon-btn pc-icon-btn--danger" aria-label={`${w.remove} ${index + 1}`} onClick={() => write(node.key, badges.filter((_, at) => at !== index))}>
                            ✕
                        </button>
                    </div>
                );
            })}
            <div>
                <button type="button" className="pc-btn" onClick={() => write(node.key, [...badges, { value: '', label: '', color: 'gray', icon: '' }])}>
                    {w.add}
                </button>
            </div>
        </fieldset>
    );
}

export function Node({ node, read, write }: { node: FormNode; read: Read; write: Write }) {
    const radioName = useId();

    switch (node.kind) {
        case 'text': {
            const value = text(read(node.key));

            return (
                <div>
                    <ForgeInput
                        label={node.label}
                        title={node.tip || undefined}
                        placeholder={node.placeholder}
                        value={value}
                        disabled={node.disabled}
                        maxLength={node.maxLength}
                        message={node.hint}
                        error={node.error ?? undefined}
                        style={node.mono ? monoStyle : undefined}
                        onChange={(next) => write(node.key, next)}
                    />
                    {node.icon && value !== '' && (
                        <span style={{ display: 'inline-flex', marginTop: 6, fontSize: 20 }} data-testid="cfg-icon-preview">
                            <i className={value} aria-hidden="true" />
                        </span>
                    )}
                </div>
            );
        }
        case 'textarea':
            return (
                <div>
                    <ForgeTextarea label={node.badge ? `${node.label} — ${node.badge}` : node.label} title={node.tip || undefined} placeholder={node.placeholder} rows={node.rows} value={text(read(node.key))} helper={node.hint} style={node.mono ? monoStyle : undefined} onChange={(next) => write(node.key, next)} />
                </div>
            );
        case 'number':
            return (
                <ForgeInput
                    type="number"
                    label={node.label}
                    title={node.tip || undefined}
                    placeholder={node.placeholder}
                    min={node.min}
                    max={node.max}
                    step={node.step}
                    message={node.hint}
                    value={text(read(node.key))}
                    onChange={(next) => write(node.key, node.int ? (next === '' ? '' : Number(next)) : next)}
                />
            );
        case 'check':
            return (
                <div title={node.tip || undefined}>
                    <ForgeCheckbox label={node.label} checked={truthy(read(node.key))} onChange={(checked) => write(node.key, checked)} />
                    <Hint text={node.hint} />
                </div>
            );
        case 'select': {
            const empty = node.options.find((option) => option.value === '');

            return (
                <div title={node.tip || undefined}>
                    <ForgeSelect label={node.label} options={node.options} selected={text(read(node.key))} placeholder={empty?.label ?? '—'} searchable={node.searchable} onChange={(value) => write(node.key, Array.isArray(value) ? String(value[0] ?? '') : String(value ?? ''))} />
                    <Hint text={node.hint} />
                </div>
            );
        }
        case 'radio':
            return (
                <fieldset style={{ border: 0, margin: 0, padding: 0 }}>
                    <legend className="pf-field-label">{node.label}</legend>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16 }}>
                        {node.options.map((option) => (
                            <ForgeRadio key={option.value} name={radioName} label={option.label} value={option.value} checked={text(read(node.key)) === option.value} onChange={(value) => write(node.key, value)} />
                        ))}
                    </div>
                </fieldset>
            );
        case 'note': {
            const body: ReactNode = (
                <>
                    {node.text && <p style={{ margin: 0, fontSize: 12 }}>{node.text}</p>}
                    {node.items && (
                        <ul style={{ margin: '4px 0 0', paddingLeft: 18, fontSize: 12 }}>
                            {node.items.map((item, index) => (
                                <li key={index}>{item}</li>
                            ))}
                        </ul>
                    )}
                </>
            );
            if (node.collapsible) {
                return (
                    <details style={{ fontSize: 12 }}>
                        <summary style={{ cursor: 'pointer', color: 'var(--pc-primary)' }}>{node.title}</summary>
                        <div style={{ marginTop: 8 }}>{body}</div>
                    </details>
                );
            }
            if (node.tone === 'muted') {
                return (
                    <div style={mutedStyle}>
                        {node.title && <strong>{node.title} </strong>}
                        {body}
                    </div>
                );
            }

            return (
                <ForgeAlert color={node.tone === 'info' ? 'primary' : node.tone} title={node.title}>
                    {body}
                </ForgeAlert>
            );
        }
        case 'guide':
            return (
                <details open={node.open} style={{ ...sectionStyle, background: 'var(--pc-bg-soft)' }}>
                    <summary style={{ cursor: 'pointer', fontWeight: 600, fontSize: 13 }}>{node.title}</summary>
                    <div style={{ display: 'grid', gap: 12, marginTop: 12 }}>
                        {node.intro && <p style={mutedStyle}>{node.intro}</p>}
                        {node.cards.map((card, index) => (
                            <Card key={index} card={card} />
                        ))}
                        {node.foot && <p style={mutedStyle}>{node.foot}</p>}
                    </div>
                </details>
            );
        case 'heading':
            return <h4 style={titleStyle}>{node.text}</h4>;
        case 'chips':
            return (
                <div>
                    {node.label && <p style={{ ...mutedStyle, marginBottom: 6 }}>{node.label}</p>}
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                        {node.items.map((item) => (
                            <span key={item} style={{ ...tagStyle, ...monoStyle }}>
                                {item}
                            </span>
                        ))}
                    </div>
                </div>
            );
        case 'path':
            return (
                <div style={{ display: 'grid', gap: 6 }}>
                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 6 }}>
                        {node.parts.map((part, index) => (
                            <span key={index} style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                <span style={{ ...tagStyle, ...monoStyle, fontWeight: index === node.parts.length - 1 ? 700 : 400 }}>{part}</span>
                                {index < node.parts.length - 1 && <span aria-hidden="true">→</span>}
                            </span>
                        ))}
                    </div>
                    <p style={{ ...mutedStyle, ...monoStyle }}>{node.foot}</p>
                </div>
            );
        case 'sample':
            return (
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 12px', border: '1px solid var(--pc-line)', borderRadius: 6, background: 'var(--pc-bg-soft)' }}>
                    <span style={mutedStyle}>{node.label}</span>
                    <span className={node.className} style={{ display: 'inline-flex', alignItems: 'center', gap: 4, padding: '4px 8px', borderRadius: 4, ...cssStyle(node.style) }} data-testid="cfg-sample">
                        {node.icon && <span className={node.icon} aria-hidden="true" />}
                        {node.text}
                    </span>
                    {node.code && <code style={{ ...monoStyle, marginLeft: 'auto', color: 'var(--pc-text-muted)' }}>{node.code}</code>}
                </div>
            );
        case 'code':
            return (
                <pre style={codeBox}>
                    {node.lines.map((line, index) => (
                        <div key={index} style={{ color: line.tone ? toneColor[line.tone] : undefined }}>
                            {line.text}
                        </div>
                    ))}
                </pre>
            );
        case 'presets':
            return (
                <div>
                    <p style={{ ...mutedStyle, fontWeight: 600, marginBottom: 6 }}>{node.label}</p>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                        {node.items.map((item) => (
                            <button key={item.label} type="button" style={{ ...tagStyle, cursor: 'pointer', ...cssStyle(item.value) }} onClick={() => write(node.key, item.value)}>
                                {item.label}
                            </button>
                        ))}
                    </div>
                </div>
            );
        case 'badges':
            return <Badges node={node} read={read} write={write} />;
        default:
            return null;
    }
}

/** A list of blocks — each a titled section with its grid of nodes. */
export function Blocks({ blocks, read, write }: { blocks: FormBlock[]; read: Read; write: Write }) {
    return (
        <div style={{ display: 'grid', gap: 16 }}>
            {blocks.map((block, index) => (
                <section key={index} style={sectionStyle}>
                    {(block.title || block.toggle) && (
                        <div style={headStyle}>
                            {block.title ? <h4 style={titleStyle}>{block.title}</h4> : <span />}
                            {block.toggle && (
                                <span title={block.toggle.tip || undefined}>
                                    <ForgeCheckbox label={block.toggle.label} checked={truthy(read(block.toggle.key))} onChange={(checked) => write(block.toggle!.key, checked)} />
                                </span>
                            )}
                        </div>
                    )}
                    {block.nodes.length > 0 && (
                        <div style={grid(block.columns)}>
                            {block.nodes.map((node, at) => (
                                <div key={at} style={isWide(node) ? { gridColumn: '1 / -1' } : undefined}>
                                    <Node node={node} read={read} write={write} />
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            ))}
        </div>
    );
}
