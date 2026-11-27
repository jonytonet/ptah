/**
 * Draws the config editor's form nodes (core/admin/crud-config/forms.ts) with
 * the forge kit. What a tab holds is decided in core; this only draws it.
 * Mirrors vue/admin/crud-config/nodes.ts.
 */
import { useId } from 'react';
import type { ReactNode } from 'react';
import { cssStyle, text, truthy } from '../../../core/admin/crud-config';
import type { FormBlock, FormNode, GuideCard } from '../../../core/admin/crud-config';
import { ForgeAlert, ForgeCheckbox, ForgeInput, ForgeRadio, ForgeSelect, ForgeTextarea } from '../../forge';

export type Read = (key: string) => unknown;
export type Write = (key: string, value: unknown) => void;


/** Nodes that always take the whole row. */
const isWide = (node: FormNode): boolean => !['text', 'number', 'check', 'select'].includes(node.kind) || ('wide' in node && node.wide === true);

function Hint({ text: value }: { text?: string }) {
    return value ? <p className="pf-field-message pa-muted">{value}</p> : null;
}

function Rows({ rows }: { rows: [string, string][] }) {
    return (
        <dl className="pa-cc-defs">
            {rows.map(([label, value], index) => (
                <div key={index} className="pa-contents">
                    <dt className="pa-text-muted">{label}</dt>
                    <dd className="pa-flat">{value}</dd>
                </div>
            ))}
        </dl>
    );
}

function Card({ card }: { card: GuideCard }) {
    return (
        <div className="pa-panel pa-panel--tight">
            {card.title && <strong className="pa-text-sm">{card.title}</strong>}
            {card.text && <p className="pa-muted">{card.text}</p>}
            {card.rows && <Rows rows={card.rows} />}
            {card.items && (
                <ul className="pa-cc-list">
                    {card.items.map((item, index) => (
                        <li key={index}>{item}</li>
                    ))}
                </ul>
            )}
            {card.after && (
                <>
                    <p className="pa-muted">{card.after.title}</p>
                    <Rows rows={card.after.rows} />
                </>
            )}
            {card.sample && (
                <div className="pa-cc-sample" style={cssStyle(card.sample.style)}>{card.sample.text}</div>
            )}
            {card.foot && <p className="pa-muted pa-muted--italic">{card.foot}</p>}
        </div>
    );
}

function Badges({ node, read, write }: { node: Extract<FormNode, { kind: 'badges' }>; read: Read; write: Write }) {
    const raw = read(node.key);
    const badges = (Array.isArray(raw) ? raw : []) as Record<string, unknown>[];
    const update = (index: number, key: string, value: string) => write(node.key, badges.map((badge, at) => (at === index ? { ...badge, [key]: value } : badge)));
    const w = node.words;

    return (
        <fieldset className="pa-fieldset-grid">
            <legend className="pf-field-label">{node.label}</legend>
            <Hint text={node.hint} />
            {badges.map((badge, index) => {
                const color = text(badge.color) || 'gray';
                const custom = color.startsWith('#');

                return (
                    <div key={index} className="pa-flex-wrap-center-g2">
                        <input className="pf-control pa-mono pa-flex-120" aria-label={`${w.value} ${index + 1}`} placeholder={w.value} value={text(badge.value)} onChange={(event) => update(index, 'value', event.target.value)} />
                        <input className="pf-control pa-flex-120" aria-label={`${w.label} ${index + 1}`} placeholder={w.label} value={text(badge.label)} onChange={(event) => update(index, 'label', event.target.value)} />
                        <span role="radiogroup" aria-label={`${w.color} ${index + 1}`} className="pa-inline-center-g1">
                            {node.colors.map((swatch) => (
                                <button
                                    key={swatch.key}
                                    type="button"
                                    role="radio"
                                    aria-checked={color === swatch.key}
                                    aria-label={swatch.label}
                                    title={swatch.label}
                                    onClick={() => update(index, 'color', swatch.key)}
                                    className="pa-cc-swatch"
                                    style={{ background: swatch.hex }}
                                />
                            ))}
                            <input type="color" aria-label={`${w.custom} ${index + 1}`} title={w.custom} value={custom ? color : '#6366f1'} onChange={(event) => update(index, 'color', event.target.value)} className="pa-cc-colorpick" />
                            {custom && <span className="pa-mono" style={{ color }}>{color}</span>}
                        </span>
                        <input className="pf-control pa-flex-120" aria-label={`${w.icon} ${index + 1}`} placeholder={w.icon} value={text(badge.icon)} onChange={(event) => update(index, 'icon', event.target.value)} />
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
                        data-mono={node.mono ? 'true' : undefined}
                        onChange={(next) => write(node.key, next)}
                    />
                    {node.icon && value !== '' && (
                        <span className="pa-cc-icon-preview" data-testid="cfg-icon-preview">
                            <i className={value} aria-hidden="true" />
                        </span>
                    )}
                </div>
            );
        }
        case 'textarea':
            return (
                <div>
                    <ForgeTextarea label={node.badge ? `${node.label} — ${node.badge}` : node.label} title={node.tip || undefined} placeholder={node.placeholder} rows={node.rows} value={text(read(node.key))} helper={node.hint} data-mono={node.mono ? 'true' : undefined} onChange={(next) => write(node.key, next)} />
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
                <fieldset className="pa-fieldset">
                    <legend className="pf-field-label">{node.label}</legend>
                    <div className="pa-flex-wrap-g4">
                        {node.options.map((option) => (
                            <ForgeRadio key={option.value} name={radioName} label={option.label} value={option.value} checked={text(read(node.key)) === option.value} onChange={(value) => write(node.key, value)} />
                        ))}
                    </div>
                </fieldset>
            );
        case 'note': {
            const body: ReactNode = (
                <>
                    {node.text && <p className="pa-text-xs-flat">{node.text}</p>}
                    {node.items && (
                        <ul className="pa-cc-list-spaced">
                            {node.items.map((item, index) => (
                                <li key={index}>{item}</li>
                            ))}
                        </ul>
                    )}
                </>
            );
            if (node.collapsible) {
                return (
                    <details className="pa-text-xs">
                        <summary className="pa-cc-link">{node.title}</summary>
                        <div className="pa-mt-2">{body}</div>
                    </details>
                );
            }
            if (node.tone === 'muted') {
                return (
                    <div className="pa-muted">
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
                <details open={node.open} className="pa-panel pa-panel--soft">
                    <summary className="pa-cc-summary">{node.title}</summary>
                    <div className="pa-grid-g3-top">
                        {node.intro && <p className="pa-muted">{node.intro}</p>}
                        {node.cards.map((card, index) => (
                            <Card key={index} card={card} />
                        ))}
                        {node.foot && <p className="pa-muted">{node.foot}</p>}
                    </div>
                </details>
            );
        case 'heading':
            return <h4 className="pa-cc-title">{node.text}</h4>;
        case 'chips':
            return (
                <div>
                    {node.label && <p className="pa-cc-caption">{node.label}</p>}
                    <div className="pa-flex-wrap-g1-5">
                        {node.items.map((item) => (
                            <span key={item} className="pa-tag pa-mono">
                                {item}
                            </span>
                        ))}
                    </div>
                </div>
            );
        case 'path':
            return (
                <div className="pa-grid-g1-5">
                    <div className="pa-flex-wrap-center-g1-5">
                        {node.parts.map((part, index) => (
                            <span key={index} className="pa-inline-center-g1-5">
                                <span className="pa-tag pa-mono pa-cc-path-part" data-last={index === node.parts.length - 1 ? 'true' : undefined}>{part}</span>
                                {index < node.parts.length - 1 && <span aria-hidden="true">→</span>}
                            </span>
                        ))}
                    </div>
                    <p className="pa-muted pa-mono">{node.foot}</p>
                </div>
            );
        case 'sample':
            return (
                <div className="pa-cc-callout">
                    <span className="pa-muted">{node.label}</span>
                    <span className={`pa-cc-sample-chip ${node.className ?? ''}`.trim()} style={cssStyle(node.style)} data-testid="cfg-sample">
                        {node.icon && <span className={node.icon} aria-hidden="true" />}
                        {node.text}
                    </span>
                    {node.code && <code className="pa-cc-mono-end">{node.code}</code>}
                </div>
            );
        case 'code':
            return (
                <pre className="pa-cc-code-box">
                    {node.lines.map((line, index) => (
                        <div key={index} data-tone={line.tone}>
                            {line.text}
                        </div>
                    ))}
                </pre>
            );
        case 'presets':
            return (
                <div>
                    <p className="pa-cc-caption-strong">{node.label}</p>
                    <div className="pa-flex-wrap-g1-5">
                        {node.items.map((item) => (
                            <button key={item.label} type="button" className="pa-tag pa-cc-tag-pick" style={cssStyle(item.value)} onClick={() => write(node.key, item.value)}>
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
        <div className="pa-grid-g4">
            {blocks.map((block, index) => (
                <section key={index} className="pa-panel">
                    {(block.title || block.toggle) && (
                        <div className="pa-cc-block-head">
                            {block.title ? <h4 className="pa-cc-title">{block.title}</h4> : <span />}
                            {block.toggle && (
                                <span title={block.toggle.tip || undefined}>
                                    <ForgeCheckbox label={block.toggle.label} checked={truthy(read(block.toggle.key))} onChange={(checked) => write(block.toggle!.key, checked)} />
                                </span>
                            )}
                        </div>
                    )}
                    {block.nodes.length > 0 && (
                        <div className="pa-cc-grid" data-cols={block.columns}>
                            {block.nodes.map((node, at) => (
                                <div key={at} className={isWide(node) ? 'pc-form-field--wide' : undefined}>
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
