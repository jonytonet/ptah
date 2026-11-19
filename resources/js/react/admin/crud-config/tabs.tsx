/**
 * The tabs of the config editor for React — the lists of each tab (columns,
 * row actions, filters, styles, JOINs, notification rules) and their
 * sub-forms. Every button runs one of the component's actions through the
 * ConfigEditor. Mirrors vue/admin/crud-config/tabs.ts tab by tab.
 */
import { useState } from 'react';
import type { CSSProperties, DragEvent, ReactNode } from 'react';
import {
    actionBlocks,
    actionColumns,
    columnBlocks,
    columnSubTabs,
    cssStyle,
    dataColumns,
    dragOrder,
    filterBlocks,
    filterGuide,
    fill,
    generalBlocks,
    hookBlocks,
    joinBlocks,
    joinDuplicate,
    joinGuide,
    notificationBlocks,
    permissionBlocks,
    readValue,
    styleBlocks,
    styleGuide,
    text,
    truthy,
} from '../../../core/admin/crud-config';
import type { BuildContext, ConfigAction, ConfigEditor, ConfigSection, CrudConfigWords, EditorState, PreviewResult } from '../../../core/admin/crud-config';
import { ForgeAlert, ForgeButton, ForgeEmpty, ForgeModal, ForgeTabs } from '../../forge';
import { Blocks, monoStyle, mutedStyle, Node, sectionStyle, tagStyle } from './nodes';

export interface TabProps {
    editor: ConfigEditor;
    state: EditorState;
    t: CrudConfigWords;
    ctx: BuildContext;
}

const headStyle: CSSProperties = { display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 8 };
const titleStyle: CSSProperties = { margin: 0, fontSize: 14, fontWeight: 600, color: 'var(--pc-text-strong)' };
const footStyle: CSSProperties = { display: 'flex', flexWrap: 'wrap', justifyContent: 'flex-end', alignItems: 'center', gap: 8, paddingTop: 12, borderTop: '1px solid var(--pc-line)' };
const dot = (on: boolean, color: string): CSSProperties => ({ display: 'inline-block', width: 14, height: 14, borderRadius: 999, background: on ? color : 'var(--pc-line)' });

/** The sub-form of a section, bound to the editor. */
function SectionForm({ editor, state, section, blocks }: { editor: ConfigEditor; state: EditorState; section: ConfigSection; blocks: ReturnType<typeof actionBlocks> }) {
    return <Blocks blocks={blocks} read={(key) => readValue(state.forms[section], key)} write={(key, value) => editor.setForm(section, key, value)} />;
}

/** "Fill in … first", after the component ignored an incomplete form. */
function Notice({ state, section }: { state: EditorState; section: ConfigSection }) {
    return state.notice?.section === section ? (
        <p className="pf-field-error" role="alert" style={{ margin: 0, fontSize: 12, color: 'var(--pc-danger)' }}>
            {state.notice.text}
        </p>
    ) : null;
}

function RunButton({ editor, state, action, label, color = 'primary', disabled, args }: { editor: ConfigEditor; state: EditorState; action: ConfigAction; label: ReactNode; color?: 'primary' | 'light'; disabled?: boolean; args?: { index?: number } }) {
    return (
        <ForgeButton color={color} size="sm" flat={color === 'light'} loading={state.busy === action} disabled={disabled || (state.busy !== null && state.busy !== action)} onClick={() => void editor.run(action, args)}>
            {label}
        </ForgeButton>
    );
}

function TabHeader({ title, description }: { title: string; description: string }) {
    return (
        <div>
            <h3 style={{ margin: 0, fontSize: 16, fontWeight: 600, color: 'var(--pc-text-strong)' }}>{title}</h3>
            <p style={mutedStyle}>{description}</p>
        </div>
    );
}

// ── Columns ─────────────────────────────────────────────────────────────────

export function ColumnsTab({ editor, state, t, ctx }: TabProps) {
    const document = state.document!;
    const form = state.forms.field;
    const editing = state.editing.field;
    const rows = dataColumns(document);
    const [dragging, setDragging] = useState<number | null>(null);

    const reorder = (from: number, to: number) => {
        if (from !== to) {
            void editor.run('reorderFields', { order: dragOrder(document.columns, from, to) });
        }
    };

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            <section style={sectionStyle}>
                <div>
                    <h4 style={titleStyle}>{t.cfg_col_table_title}</h4>
                    <p style={mutedStyle}>{t.cfg_col_table_hint}</p>
                </div>
                <div className="pc-table-wrap">
                    <table className="pc-table">
                        <thead>
                            <tr>
                                <th scope="col">
                                    <span className="sr-only" style={{ position: 'absolute', width: 1, height: 1, overflow: 'hidden', clip: 'rect(0 0 0 0)' }}>
                                        {t.dragHandle}
                                    </span>
                                </th>
                                <th scope="col">{t.cfg_col_th_field}</th>
                                <th scope="col">{t.cfg_col_th_label}</th>
                                <th scope="col">{t.cfg_col_th_type}</th>
                                <th scope="col">{t.cfg_col_th_renderer}</th>
                                <th scope="col">{t.cfg_col_th_mask}</th>
                                <th scope="col">{t.cfg_col_th_save}</th>
                                <th scope="col">{t.cfg_col_th_filterable}</th>
                                <th scope="col">{t.cfg_col_th_actions}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={9}>
                                        <ForgeEmpty title={t.cfg_col_empty} />
                                    </td>
                                </tr>
                            )}
                            {rows.map(({ column, index }, position) => {
                                const name = text(column.colsNomeLogico) || text(column.colsNomeFisico);

                                return (
                                    <tr
                                        key={`${index}-${text(column.colsNomeFisico)}`}
                                        data-index={index}
                                        draggable
                                        onDragStart={(event: DragEvent) => {
                                            setDragging(index);
                                            event.dataTransfer?.setData('text/plain', String(index));
                                        }}
                                        onDragOver={(event: DragEvent) => event.preventDefault()}
                                        onDrop={(event: DragEvent) => {
                                            event.preventDefault();
                                            if (dragging !== null) {
                                                reorder(dragging, index);
                                            }
                                            setDragging(null);
                                        }}
                                        style={{ opacity: dragging === index ? 0.5 : 1, background: editing === index ? 'var(--pc-bg-soft)' : undefined }}
                                    >
                                        <td style={{ whiteSpace: 'nowrap', cursor: 'move' }} title={t.dragHandle}>
                                            <span aria-hidden="true">⋮⋮</span>
                                            <button type="button" className="pc-icon-btn" aria-label={`${t.moveUp}: ${name}`} disabled={position === 0} onClick={() => reorder(index, rows[position - 1].index)}>
                                                ↑
                                            </button>
                                            <button type="button" className="pc-icon-btn" aria-label={`${t.moveDown}: ${name}`} disabled={position === rows.length - 1} onClick={() => reorder(index, rows[position + 1].index)}>
                                                ↓
                                            </button>
                                        </td>
                                        <td>
                                            <code style={monoStyle}>{text(column.colsNomeFisico)}</code>
                                            {text(column.colsRelacaoNested) !== '' && <span style={{ ...mutedStyle, marginLeft: 4 }}>→ {text(column.colsRelacaoNested)}</span>}
                                            {text(column.colsPermission) !== '' && (
                                                <span style={{ marginLeft: 4 }} title={fill(t.cfg_col_permission_badge_title, { key: text(column.colsPermission) })} aria-label={fill(t.cfg_col_permission_badge_title, { key: text(column.colsPermission) })}>
                                                    🔒
                                                </span>
                                            )}
                                        </td>
                                        <td>{text(column.colsNomeLogico)}</td>
                                        <td>
                                            <span style={tagStyle}>{text(column.colsTipo) || 'text'}</span>
                                        </td>
                                        <td>{text(column.colsRenderer) !== '' ? <span style={tagStyle}>{text(column.colsRenderer)}</span> : text(column.colsHelper) !== '' ? <span style={tagStyle}>{text(column.colsHelper)}</span> : '—'}</td>
                                        <td>{text(column.colsMask) !== '' ? <span style={tagStyle}>{text(column.colsMask)}</span> : '—'}</td>
                                        <td>
                                            <span style={dot(truthy(column.colsGravar), '#4ade80')} role="img" aria-label={`${t.cfg_col_th_save}: ${truthy(column.colsGravar) ? '✓' : '—'}`} />
                                        </td>
                                        <td>
                                            <span style={dot(truthy(column.colsIsFilterable), '#60a5fa')} role="img" aria-label={`${t.cfg_col_th_filterable}: ${truthy(column.colsIsFilterable) ? '✓' : '—'}`} />
                                        </td>
                                        <td style={{ whiteSpace: 'nowrap' }}>
                                            <button type="button" className="pc-icon-btn" aria-label={`${t.edit}: ${name}`} onClick={() => void editor.run('editField', { index })}>
                                                ✏
                                            </button>
                                            <button type="button" className="pc-icon-btn pc-icon-btn--danger" aria-label={`${t.remove}: ${name}`} onClick={() => editor.askRemove('removeField', index, fill(t.cfg_col_remove_confirm, { col: name }))}>
                                                ✕
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </section>

            <section style={sectionStyle} aria-label={editing >= 0 ? t.cfg_col_form_editing : t.cfg_col_form_new}>
                <div style={headStyle}>
                    <h4 style={titleStyle}>{editing >= 0 ? t.cfg_col_form_editing : t.cfg_col_form_new}</h4>
                    {editing >= 0 && <RunButton editor={editor} state={state} action="cancelEditField" label={t.cfg_col_cancel_edit} color="light" />}
                </div>
                <ForgeTabs
                    key={`sub-${editing}`}
                    tabs={columnSubTabs(form, t).map((sub) => ({
                        id: sub.value,
                        label: sub.label,
                        content: <SectionForm editor={editor} state={state} section="field" blocks={columnBlocks(sub.value, form, ctx)} />,
                    }))}
                />
                <Notice state={state} section="field" />
                <div style={footStyle}>
                    {editing >= 0 ? <RunButton editor={editor} state={state} action="updateField" label={t.cfg_col_btn_save} /> : <RunButton editor={editor} state={state} action="addField" label={t.cfg_col_btn_add} />}
                </div>
            </section>
        </div>
    );
}

// ── Row actions ─────────────────────────────────────────────────────────────

export function ActionsTab({ editor, state, t, ctx }: TabProps) {
    const rows = actionColumns(state.document!);
    const editing = state.editing.action;

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            <section style={sectionStyle}>
                <h4 style={titleStyle}>{t.cfg_act_tab_title}</h4>
                <div className="pc-table-wrap">
                    <table className="pc-table">
                        <thead>
                            <tr>
                                <th scope="col">{t.cfg_act_th_name}</th>
                                <th scope="col">{t.cfg_act_th_type}</th>
                                <th scope="col">{t.cfg_act_th_value}</th>
                                <th scope="col">{t.cfg_act_th_icon}</th>
                                <th scope="col">{t.cfg_act_th_color}</th>
                                <th scope="col">{t.cfg_act_th_permission}</th>
                                <th scope="col">
                                    <span style={{ position: 'absolute', width: 1, height: 1, overflow: 'hidden', clip: 'rect(0 0 0 0)' }}>{t.cfg_col_th_actions}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map(({ column, index }) => {
                                const icon = text(column.actionIcon) || 'bx bx-link';
                                const name = text(column.colsNomeLogico);

                                return (
                                    <tr key={index} style={{ background: editing === index ? 'var(--pc-bg-soft)' : undefined }}>
                                        <td>{name}</td>
                                        <td>
                                            <span style={tagStyle}>{text(column.actionType) || 'link'}</span>
                                        </td>
                                        <td style={monoStyle}>{text(column.actionValue)}</td>
                                        <td>
                                            <i className={icon} aria-hidden="true" /> <span style={{ ...monoStyle, fontSize: 10 }}>{icon}</span>
                                        </td>
                                        <td>
                                            <span style={tagStyle}>{text(column.actionColor) || 'primary'}</span>
                                        </td>
                                        <td style={monoStyle}>{text(column.actionPermission) || '—'}</td>
                                        <td style={{ whiteSpace: 'nowrap' }}>
                                            <button type="button" className="pc-icon-btn" aria-label={`${t.edit}: ${name}`} onClick={() => void editor.run('editAction', { index })}>
                                                ✏
                                            </button>
                                            <button type="button" className="pc-icon-btn pc-icon-btn--danger" aria-label={`${t.remove}: ${name}`} onClick={() => editor.askRemove('removeAction', index, t.cfg_act_remove_confirm)}>
                                                ✕
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </section>
            <section style={sectionStyle} aria-label={editing >= 0 ? t.cfg_act_form_editing : t.cfg_act_form_new}>
                <div style={headStyle}>
                    <h4 style={titleStyle}>{editing >= 0 ? t.cfg_act_form_editing : t.cfg_act_form_new}</h4>
                    {editing >= 0 && <RunButton editor={editor} state={state} action="cancelEditAction" label={t.cfg_act_cancel_edit} color="light" />}
                </div>
                <SectionForm editor={editor} state={state} section="action" blocks={actionBlocks(ctx)} />
                <Notice state={state} section="action" />
                <div style={footStyle}>
                    <RunButton editor={editor} state={state} action="addAction" label={editing >= 0 ? t.cfg_act_btn_save : t.cfg_act_btn_add} />
                </div>
            </section>
        </div>
    );
}

// ── Custom filters ──────────────────────────────────────────────────────────

export function FiltersTab({ editor, state, t, ctx }: TabProps) {
    const filters = state.document!.customFilters;

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            <Node node={filterGuide(t)} read={() => undefined} write={() => undefined} />
            {filters.map((filter, index) => (
                <section key={index} style={sectionStyle}>
                    <div style={headStyle}>
                        <div>
                            <strong>{text(filter.label) || text(filter.field) || fill(t.filterNumber, { n: index })}</strong>
                            <p style={{ ...mutedStyle, ...monoStyle }}>{text(filter.field)}</p>
                        </div>
                        <button type="button" className="pc-icon-btn pc-icon-btn--danger" aria-label={`${t.remove}: ${text(filter.label) || text(filter.field)}`} onClick={() => editor.askRemove('removeCustomFilter', index, t.cfg_filter_remove_confirm)}>
                            ✕
                        </button>
                    </div>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                        {text(filter.whereHas) !== '' && <span style={tagStyle}>whereHas: {text(filter.whereHas)}</span>}
                        {text(filter.field_relation) !== '' && <span style={tagStyle}>{t.tagField}: {text(filter.field_relation)}</span>}
                        {text(filter.aggregate) !== '' && <span style={tagStyle}>{text(filter.aggregate)}</span>}
                        {text(filter.defaultOperator) !== '' && <span style={tagStyle}>{t.tagOperator}: {text(filter.defaultOperator)}</span>}
                        {text(filter.colsFilterType) !== '' && <span style={tagStyle}>{t.tagType}: {text(filter.colsFilterType)}</span>}
                    </div>
                </section>
            ))}
            <section style={sectionStyle} aria-label={t.cfg_filter_form_title}>
                <h4 style={titleStyle}>{t.cfg_filter_form_title}</h4>
                <SectionForm editor={editor} state={state} section="filter" blocks={filterBlocks(ctx)} />
                <Notice state={state} section="filter" />
                <div style={footStyle}>
                    <RunButton editor={editor} state={state} action="addCustomFilter" label={t.cfg_filter_btn_add} />
                </div>
            </section>
        </div>
    );
}

// ── Conditional styles ──────────────────────────────────────────────────────

export function StylesTab({ editor, state, t, ctx }: TabProps) {
    const styles = state.document!.conditionStyles;

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            <Node node={styleGuide(t)} read={() => undefined} write={() => undefined} />
            {styles.map((style, index) => (
                <section key={index} style={sectionStyle}>
                    <div style={headStyle}>
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                            <code style={monoStyle}>{text(style.field)}</code>
                            <strong>{text(style.condition) || '=='}</strong>
                            <code style={monoStyle}>{text(style.value)}</code>
                        </div>
                        <button type="button" className="pc-icon-btn pc-icon-btn--danger" aria-label={`${t.remove}: ${text(style.field)} ${text(style.condition) || '=='} ${text(style.value)}`} onClick={() => editor.askRemove('removeConditionStyle', index, t.cfg_style_remove_confirm)}>
                            ✕
                        </button>
                    </div>
                    <code style={{ ...monoStyle, padding: '2px 8px', borderRadius: 4, background: 'var(--pc-bg-soft)' }}>{text(style.style)}</code>
                    {text(style.style) !== '' && <div style={{ padding: '6px 12px', borderRadius: 4, fontSize: 12, ...cssStyle(text(style.style)) }}>{t.cfg_style_preview_row}</div>}
                </section>
            ))}
            <section style={sectionStyle} aria-label={t.cfg_style_form_title}>
                <h4 style={titleStyle}>{t.cfg_style_form_title}</h4>
                <SectionForm editor={editor} state={state} section="style" blocks={styleBlocks(state.forms.style, ctx)} />
                <Notice state={state} section="style" />
                <div style={footStyle}>
                    <RunButton editor={editor} state={state} action="addConditionStyle" label={t.cfg_style_btn_add} />
                </div>
            </section>
        </div>
    );
}

// ── JOINs ───────────────────────────────────────────────────────────────────

export function JoinsTab({ editor, state, t, ctx }: TabProps) {
    const document = state.document!;
    const form = state.forms.join;
    const editing = state.editing.join;
    const duplicate = joinDuplicate(form, document, editing);

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            {state.joinError && <ForgeAlert color="danger">{state.joinError}</ForgeAlert>}
            <Node node={joinGuide(t)} read={() => undefined} write={() => undefined} />
            {document.joins.length === 0 && <ForgeEmpty title={t.cfg_join_empty} description={t.cfg_join_empty_hint} />}
            {document.joins.map((join, index) => {
                const left = text(join.type || 'left').toLowerCase() === 'left';
                const select = Array.isArray(join.select) ? (join.select as Record<string, unknown>[]) : [];

                return (
                    <section key={index} style={{ ...sectionStyle, borderLeft: `4px solid ${left ? '#38bdf8' : '#fbbf24'}` }}>
                        <div style={headStyle}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <span style={tagStyle}>{text(join.type || 'left').toUpperCase()} JOIN</span>
                                <strong style={monoStyle}>{text(join.table) || '—'}</strong>
                                {truthy(join.distinct) && <span style={tagStyle}>DISTINCT</span>}
                            </div>
                            <div style={{ display: 'flex', gap: 6 }}>
                                <ForgeButton size="sm" flat onClick={() => void editor.run('editJoin', { index })} aria-label={`${t.cfg_join_edit_btn}: ${text(join.table)}`}>
                                    {t.cfg_join_edit_btn}
                                </ForgeButton>
                                <ForgeButton size="sm" color="danger" flat onClick={() => editor.askRemove('removeJoin', index, fill(t.cfg_join_remove_confirm, { table: text(join.table) }))} aria-label={`${t.cfg_join_remove_btn}: ${text(join.table)}`}>
                                    {t.cfg_join_remove_btn}
                                </ForgeButton>
                            </div>
                        </div>
                        <p style={{ ...monoStyle, margin: 0 }}>
                            ON {text(join.first)} = {text(join.second)}
                        </p>
                        {select.length > 0 ? (
                            <div>
                                <p style={mutedStyle}>{t.cfg_join_cols_show}</p>
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                    {select.map((entry, at) => (
                                        <span key={at} style={{ ...tagStyle, ...monoStyle }}>
                                            {text(entry.column)} → <strong>{text(entry.alias)}</strong>
                                        </span>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <p style={{ ...mutedStyle, color: '#b45309' }}>{t.cfg_join_no_cols_warn}</p>
                        )}
                    </section>
                );
            })}
            <section style={sectionStyle} aria-label={editing >= 0 ? t.cfg_join_form_editing : t.cfg_join_form_new}>
                <h4 style={titleStyle}>{editing >= 0 ? `${t.cfg_join_form_editing} — ${t.joinWith} ${text(document.joins[editing]?.table)}` : t.cfg_join_form_new}</h4>
                <SectionForm editor={editor} state={state} section="join" blocks={joinBlocks(form, editing, ctx, null)} />
                <Notice state={state} section="join" />
                <div style={footStyle}>
                    {editing >= 0 ? (
                        <>
                            <RunButton editor={editor} state={state} action="cancelEditJoin" label={t.cfg_join_cancel_edit} color="light" />
                            <RunButton editor={editor} state={state} action="addJoin" label={t.cfg_join_btn_update} />
                        </>
                    ) : (
                        <RunButton editor={editor} state={state} action="addJoin" label={t.cfg_join_btn_add} disabled={duplicate} />
                    )}
                </div>
            </section>
        </div>
    );
}

// ── General, permissions, hooks (the settings) ──────────────────────────────

function Settings({ editor, state, blocks }: { editor: ConfigEditor; state: EditorState; blocks: ReturnType<typeof generalBlocks> }) {
    const settings = state.document!.settings;

    return <Blocks blocks={blocks} read={(key) => readValue(settings, key)} write={(key, value) => editor.setSetting(key, value)} />;
}

export function GeneralTab({ editor, state, ctx }: TabProps) {
    return <Settings editor={editor} state={state} blocks={generalBlocks(ctx)} />;
}

export function PermissionsTab({ editor, state, ctx }: TabProps) {
    return <Settings editor={editor} state={state} blocks={permissionBlocks(ctx)} />;
}

export function HooksTab({ editor, state, ctx }: TabProps) {
    return <Settings editor={editor} state={state} blocks={hookBlocks(ctx)} />;
}

// ── Notifications ───────────────────────────────────────────────────────────

export function NotificationsTab({ editor, state, t, ctx }: TabProps) {
    const rules = state.document!.notificationRules;
    const editing = state.editing.notification;

    if (!state.payload?.options.notificationsEnabled) {
        return (
            <section style={sectionStyle}>
                <h4 style={titleStyle}>{t.cfg_notif_off_title}</h4>
                <p style={mutedStyle}>{t.cfg_notif_off_desc}</p>
                <ol style={{ margin: 0, paddingLeft: 18, fontSize: 12 }}>
                    <li>{t.cfg_notif_off_step_env}</li>
                    <li>{t.cfg_notif_off_step_publish}</li>
                    <li>{t.cfg_notif_off_step_migrate}</li>
                </ol>
            </section>
        );
    }

    return (
        <div style={{ display: 'grid', gap: 16 }}>
            {state.hints.traitMissing && <ForgeAlert color="warn">{fill(t.cfg_notif_trait_missing, { trait: 'SendsCrudNotifications' })}</ForgeAlert>}
            {rules.length === 0 && <ForgeEmpty title={t.cfg_notif_empty} description={t.cfg_notif_empty_hint} />}
            {rules.map((rule, index) => (
                <section key={index} style={sectionStyle}>
                    <div style={headStyle}>
                        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 6 }}>
                            <span style={{ ...tagStyle, textTransform: 'uppercase' }}>{text(rule.event)}</span>
                            <span style={tagStyle}>
                                {text(rule.audience)}
                                {text(rule.audienceValue) !== '' ? `: ${text(rule.audienceValue)}` : ''}
                            </span>
                            <strong>{text(rule.title)}</strong>
                        </div>
                        <div style={{ display: 'flex', gap: 6 }}>
                            <ForgeButton size="sm" flat onClick={() => void editor.run('editNotificationRule', { index })} aria-label={`${t.cfg_notif_edit_btn}: ${text(rule.title)}`}>
                                {t.cfg_notif_edit_btn}
                            </ForgeButton>
                            <ForgeButton size="sm" color="danger" flat onClick={() => editor.askRemove('removeNotificationRule', index, t.cfg_notif_remove_confirm)} aria-label={`${t.cfg_notif_remove_btn}: ${text(rule.title)}`}>
                                {t.cfg_notif_remove_btn}
                            </ForgeButton>
                        </div>
                    </div>
                    {text(rule.body) !== '' && <p style={mutedStyle}>{text(rule.body)}</p>}
                </section>
            ))}
            <section style={sectionStyle} aria-label={editing >= 0 ? t.cfg_notif_form_editing : t.cfg_notif_form_new}>
                <h4 style={titleStyle}>{editing >= 0 ? t.cfg_notif_form_editing : t.cfg_notif_form_new}</h4>
                <SectionForm editor={editor} state={state} section="notification" blocks={notificationBlocks(state.forms.notification, state.audience, ctx)} />
                <Notice state={state} section="notification" />
                <div style={footStyle}>
                    {editing >= 0 && <RunButton editor={editor} state={state} action="cancelEditNotificationRule" label={t.cfg_notif_cancel_edit} color="light" />}
                    <RunButton editor={editor} state={state} action="addNotificationRule" label={editing >= 0 ? t.cfg_notif_btn_update : t.cfg_notif_btn_add} />
                </div>
            </section>
        </div>
    );
}

export { TabHeader };

// ── Form preview ────────────────────────────────────────────────────────────

const inert: CSSProperties = { cursor: 'not-allowed', opacity: 0.7 };

/** The inert preview of the create/edit form (the Blade's _config-form-preview). */
export function PreviewModal({ preview, t, onClose }: { preview: PreviewResult; t: CrudConfigWords; onClose: () => void }) {
    let previous: string | null = null;

    return (
        <ForgeModal
            open
            onClose={onClose}
            size="lg"
            title={preview.title !== '' ? `${t.cfg_preview_title} — ${preview.title}` : t.cfg_preview_title}
            subtitle={t.cfg_preview_inert_notice}
            footer={
                <>
                    <span style={{ ...mutedStyle, marginRight: 'auto' }}>{t.cfg_preview_footer_hint}</span>
                    <button type="button" className="pc-btn" disabled style={inert}>
                        {t.btn_cancel}
                    </button>
                    <button type="button" className="pc-btn pc-btn--primary" disabled style={inert}>
                        {t.btn_create}
                    </button>
                </>
            }
        >
            {preview.fields.length === 0 ? (
                <ForgeEmpty title={t.cfg_preview_empty} />
            ) : (
                <div style={{ display: 'grid', gap: 14 }}>
                    {preview.fields.map((field) => {
                        const heading = field.block !== '' && field.block !== previous ? field.block : null;
                        previous = field.block;
                        const id = `cfg-preview-${field.field}`;

                        return (
                            <div key={field.field}>
                                {heading && <p style={{ ...mutedStyle, fontWeight: 600, textTransform: 'uppercase', margin: '8px 0' }}>{heading}</p>}
                                <label htmlFor={id} className="pf-field-label">
                                    {field.label}
                                    {field.required && <span aria-hidden="true"> *</span>}
                                    {field.computed && <span style={{ ...tagStyle, marginLeft: 6 }}>⚡ {t.cfg_preview_calc_badge}</span>}
                                </label>
                                {field.type === 'select' || field.type === 'boolean' ? (
                                    <select id={id} className="pf-control" disabled style={inert}>
                                        {field.type === 'boolean' ? (
                                            <>
                                                <option>{t.bool_yes}</option>
                                                <option>{t.bool_no}</option>
                                            </>
                                        ) : (
                                            <>
                                                <option>{t.select_placeholder}</option>
                                                {field.options.map((option) => (
                                                    <option key={option.value}>{option.label}</option>
                                                ))}
                                            </>
                                        )}
                                    </select>
                                ) : field.type === 'textarea' ? (
                                    <textarea id={id} className="pf-control" rows={3} disabled style={inert} />
                                ) : field.type === 'image' ? (
                                    <div id={id} style={{ height: 96, border: '2px dashed var(--pc-line)', borderRadius: 6 }} />
                                ) : (
                                    <input
                                        id={id}
                                        className="pf-control"
                                        disabled
                                        style={inert}
                                        placeholder={field.type === 'searchdropdown' ? (field.dependsOn ? fill(t.sd_select_parent_first, { parent: field.dependsOnLabel ?? '' }) : (field.placeholder ?? fill(t.search_entity, { label: field.label }))) : field.mask}
                                    />
                                )}
                                {field.type === 'searchdropdown' && field.dependsOn && <p style={mutedStyle}>🔗 {fill(t.cfg_preview_depends, { parent: field.dependsOnLabel ?? '' })}</p>}
                                {field.type === 'searchdropdown' && field.lazy && <p style={mutedStyle}>⏳ {t.cfg_preview_sd_lazy}</p>}
                                {field.help && <p style={mutedStyle}>{field.help}</p>}
                            </div>
                        );
                    })}
                </div>
            )}
        </ForgeModal>
    );
}
