/**
 * The tabs of the config editor for Vue — the lists of each tab (columns,
 * row actions, filters, styles, JOINs, notification rules) and their
 * sub-forms. Every button runs one of the component's actions through the
 * ConfigEditor. Mirrors react/admin/crud-config/tabs.tsx tab by tab.
 */
import { defineComponent, h, ref } from 'vue';
import type { CSSProperties, PropType, VNode } from 'vue';
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
import type { BuildContext, ConfigAction, ConfigEditor, ConfigSection, CrudConfigWords, EditorState, FormBlock, PreviewResult } from '../../../core/admin/crud-config';
import { ForgeAlert, ForgeButton, ForgeEmpty, ForgeModal, ForgeTabs } from '../../forge';
import { monoStyle, mutedStyle, renderBlocks, renderNode, sectionStyle, tagStyle } from './nodes';

export const tabProps = {
    editor: { type: Object as PropType<ConfigEditor>, required: true as const },
    state: { type: Object as PropType<EditorState>, required: true as const },
    t: { type: Object as PropType<CrudConfigWords>, required: true as const },
    ctx: { type: Object as PropType<BuildContext>, required: true as const },
};

const headStyle: CSSProperties = { display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: '8px' };
const titleStyle: CSSProperties = { margin: 0, fontSize: '14px', fontWeight: 600, color: 'var(--pc-text-strong)' };
const footStyle: CSSProperties = { display: 'flex', flexWrap: 'wrap', justifyContent: 'flex-end', alignItems: 'center', gap: '8px', paddingTop: '12px', borderTop: '1px solid var(--pc-line)' };
const srOnly: CSSProperties = { position: 'absolute', width: '1px', height: '1px', overflow: 'hidden', clip: 'rect(0 0 0 0)' };
const dot = (on: boolean, color: string): CSSProperties => ({ display: 'inline-block', width: '14px', height: '14px', borderRadius: '999px', background: on ? color : 'var(--pc-line)' });
const nothing = () => undefined;

/** The sub-form of a section, bound to the editor. */
const sectionForm = (editor: ConfigEditor, state: EditorState, section: ConfigSection, blocks: FormBlock[]) =>
    renderBlocks(
        blocks,
        (key) => readValue(state.forms[section], key),
        (key, value) => editor.setForm(section, key, value),
    );

/** "Fill in … first", after the component ignored an incomplete form. */
const notice = (state: EditorState, section: ConfigSection) =>
    state.notice?.section === section ? h('p', { class: 'pf-field-error', role: 'alert', style: { margin: 0, fontSize: '12px', color: 'var(--pc-danger)' } }, state.notice.text) : null;

const runButton = (editor: ConfigEditor, state: EditorState, action: ConfigAction, label: string, options: { color?: 'primary' | 'light'; disabled?: boolean; args?: { index?: number } } = {}) =>
    h(
        ForgeButton,
        {
            color: options.color ?? 'primary',
            size: 'sm',
            flat: options.color === 'light',
            loading: state.busy === action,
            disabled: options.disabled || (state.busy !== null && state.busy !== action),
            onClick: () => void editor.run(action, options.args),
        },
        () => label,
    );

export const tabHeader = (title: string, description: string) => h('div', [h('h3', { style: { margin: 0, fontSize: '16px', fontWeight: 600, color: 'var(--pc-text-strong)' } }, title), h('p', { style: mutedStyle }, description)]);

const iconButton = (label: string, symbol: string, onClick: () => void, danger = false, disabled = false) =>
    h('button', { type: 'button', class: danger ? 'pc-icon-btn pc-icon-btn--danger' : 'pc-icon-btn', 'aria-label': label, disabled, onClick }, symbol);

// ── Columns ─────────────────────────────────────────────────────────────────

export const ColumnsTab = defineComponent({
    name: 'PtahCrudConfigColumns',
    props: tabProps,
    setup(props) {
        const dragging = ref<number | null>(null);

        return () => {
            const { editor, state, t, ctx } = props;
            const document = state.document!;
            const form = state.forms.field;
            const editing = state.editing.field;
            const rows = dataColumns(document);
            const reorder = (from: number, to: number) => {
                if (from !== to) {
                    void editor.run('reorderFields', { order: dragOrder(document.columns, from, to) });
                }
            };
            const tabs = columnSubTabs(form, t);
            const tag = (value: string) => h('span', { style: tagStyle }, value);

            return h('div', { style: { display: 'grid', gap: '16px' } }, [
                h('section', { style: sectionStyle }, [
                    h('div', [h('h4', { style: titleStyle }, t.cfg_col_table_title), h('p', { style: mutedStyle }, t.cfg_col_table_hint)]),
                    h('div', { class: 'pc-table-wrap' }, [
                        h('table', { class: 'pc-table' }, [
                            h('thead', [
                                h('tr', [
                                    h('th', { scope: 'col' }, [h('span', { style: srOnly }, t.dragHandle)]),
                                    h('th', { scope: 'col' }, t.cfg_col_th_field),
                                    h('th', { scope: 'col' }, t.cfg_col_th_label),
                                    h('th', { scope: 'col' }, t.cfg_col_th_type),
                                    h('th', { scope: 'col' }, t.cfg_col_th_renderer),
                                    h('th', { scope: 'col' }, t.cfg_col_th_mask),
                                    h('th', { scope: 'col' }, t.cfg_col_th_save),
                                    h('th', { scope: 'col' }, t.cfg_col_th_filterable),
                                    h('th', { scope: 'col' }, t.cfg_col_th_actions),
                                ]),
                            ]),
                            h('tbody', [
                                rows.length === 0 ? h('tr', [h('td', { colspan: 9 }, [h(ForgeEmpty, { title: t.cfg_col_empty })])]) : null,
                                ...rows.map(({ column, index }, position) => {
                                    const name = text(column.colsNomeLogico) || text(column.colsNomeFisico);
                                    const permission = text(column.colsPermission);

                                    return h(
                                        'tr',
                                        {
                                            key: `${index}-${text(column.colsNomeFisico)}`,
                                            'data-index': index,
                                            draggable: true,
                                            onDragstart: (event: DragEvent) => {
                                                dragging.value = index;
                                                event.dataTransfer?.setData('text/plain', String(index));
                                            },
                                            onDragover: (event: DragEvent) => event.preventDefault(),
                                            onDrop: (event: DragEvent) => {
                                                event.preventDefault();
                                                if (dragging.value !== null) {
                                                    reorder(dragging.value, index);
                                                }
                                                dragging.value = null;
                                            },
                                            style: { opacity: dragging.value === index ? 0.5 : 1, background: editing === index ? 'var(--pc-bg-soft)' : undefined },
                                        },
                                        [
                                            h('td', { style: { whiteSpace: 'nowrap', cursor: 'move' }, title: t.dragHandle }, [
                                                h('span', { 'aria-hidden': 'true' }, '⋮⋮'),
                                                iconButton(`${t.moveUp}: ${name}`, '↑', () => reorder(index, rows[position - 1].index), false, position === 0),
                                                iconButton(`${t.moveDown}: ${name}`, '↓', () => reorder(index, rows[position + 1].index), false, position === rows.length - 1),
                                            ]),
                                            h('td', [
                                                h('code', { style: monoStyle }, text(column.colsNomeFisico)),
                                                text(column.colsRelacaoNested) !== '' ? h('span', { style: { ...mutedStyle, marginLeft: '4px' } }, `→ ${text(column.colsRelacaoNested)}`) : null,
                                                permission !== ''
                                                    ? h('span', { style: { marginLeft: '4px' }, title: fill(t.cfg_col_permission_badge_title, { key: permission }), 'aria-label': fill(t.cfg_col_permission_badge_title, { key: permission }) }, '🔒')
                                                    : null,
                                            ]),
                                            h('td', text(column.colsNomeLogico)),
                                            h('td', [tag(text(column.colsTipo) || 'text')]),
                                            h('td', [text(column.colsRenderer) !== '' ? tag(text(column.colsRenderer)) : text(column.colsHelper) !== '' ? tag(text(column.colsHelper)) : '—']),
                                            h('td', [text(column.colsMask) !== '' ? tag(text(column.colsMask)) : '—']),
                                            h('td', [h('span', { style: dot(truthy(column.colsGravar), '#4ade80'), role: 'img', 'aria-label': `${t.cfg_col_th_save}: ${truthy(column.colsGravar) ? '✓' : '—'}` })]),
                                            h('td', [h('span', { style: dot(truthy(column.colsIsFilterable), '#60a5fa'), role: 'img', 'aria-label': `${t.cfg_col_th_filterable}: ${truthy(column.colsIsFilterable) ? '✓' : '—'}` })]),
                                            h('td', { style: { whiteSpace: 'nowrap' } }, [
                                                iconButton(`${t.edit}: ${name}`, '✏', () => void editor.run('editField', { index })),
                                                iconButton(`${t.remove}: ${name}`, '✕', () => editor.askRemove('removeField', index, fill(t.cfg_col_remove_confirm, { col: name })), true),
                                            ]),
                                        ],
                                    );
                                }),
                            ]),
                        ]),
                    ]),
                ]),
                h('section', { style: sectionStyle, 'aria-label': editing >= 0 ? t.cfg_col_form_editing : t.cfg_col_form_new }, [
                    h('div', { style: headStyle }, [
                        h('h4', { style: titleStyle }, editing >= 0 ? t.cfg_col_form_editing : t.cfg_col_form_new),
                        editing >= 0 ? runButton(editor, state, 'cancelEditField', t.cfg_col_cancel_edit, { color: 'light' }) : null,
                    ]),
                    h(
                        ForgeTabs,
                        { key: `sub-${editing}`, tabs: tabs.map((sub) => ({ id: sub.value, label: sub.label })) },
                        Object.fromEntries(tabs.map((sub) => [sub.value, () => sectionForm(editor, state, 'field', columnBlocks(sub.value, form, ctx))])),
                    ),
                    notice(state, 'field'),
                    h('div', { style: footStyle }, [editing >= 0 ? runButton(editor, state, 'updateField', t.cfg_col_btn_save) : runButton(editor, state, 'addField', t.cfg_col_btn_add)]),
                ]),
            ]);
        };
    },
});

// ── Row actions ─────────────────────────────────────────────────────────────

export const ActionsTab = defineComponent({
    name: 'PtahCrudConfigActions',
    props: tabProps,
    setup(props) {
        return () => {
            const { editor, state, t, ctx } = props;
            const rows = actionColumns(state.document!);
            const editing = state.editing.action;

            return h('div', { style: { display: 'grid', gap: '16px' } }, [
                h('section', { style: sectionStyle }, [
                    h('h4', { style: titleStyle }, t.cfg_act_tab_title),
                    h('div', { class: 'pc-table-wrap' }, [
                        h('table', { class: 'pc-table' }, [
                            h('thead', [
                                h('tr', [
                                    h('th', { scope: 'col' }, t.cfg_act_th_name),
                                    h('th', { scope: 'col' }, t.cfg_act_th_type),
                                    h('th', { scope: 'col' }, t.cfg_act_th_value),
                                    h('th', { scope: 'col' }, t.cfg_act_th_icon),
                                    h('th', { scope: 'col' }, t.cfg_act_th_color),
                                    h('th', { scope: 'col' }, t.cfg_act_th_permission),
                                    h('th', { scope: 'col' }, [h('span', { style: srOnly }, t.cfg_col_th_actions)]),
                                ]),
                            ]),
                            h(
                                'tbody',
                                rows.map(({ column, index }) => {
                                    const icon = text(column.actionIcon) || 'bx bx-link';
                                    const name = text(column.colsNomeLogico);

                                    return h('tr', { key: index, style: { background: editing === index ? 'var(--pc-bg-soft)' : undefined } }, [
                                        h('td', name),
                                        h('td', [h('span', { style: tagStyle }, text(column.actionType) || 'link')]),
                                        h('td', { style: monoStyle }, text(column.actionValue)),
                                        h('td', [h('i', { class: icon, 'aria-hidden': 'true' }), ' ', h('span', { style: { ...monoStyle, fontSize: '10px' } }, icon)]),
                                        h('td', [h('span', { style: tagStyle }, text(column.actionColor) || 'primary')]),
                                        h('td', { style: monoStyle }, text(column.actionPermission) || '—'),
                                        h('td', { style: { whiteSpace: 'nowrap' } }, [
                                            iconButton(`${t.edit}: ${name}`, '✏', () => void editor.run('editAction', { index })),
                                            iconButton(`${t.remove}: ${name}`, '✕', () => editor.askRemove('removeAction', index, t.cfg_act_remove_confirm), true),
                                        ]),
                                    ]);
                                }),
                            ),
                        ]),
                    ]),
                ]),
                h('section', { style: sectionStyle, 'aria-label': editing >= 0 ? t.cfg_act_form_editing : t.cfg_act_form_new }, [
                    h('div', { style: headStyle }, [
                        h('h4', { style: titleStyle }, editing >= 0 ? t.cfg_act_form_editing : t.cfg_act_form_new),
                        editing >= 0 ? runButton(editor, state, 'cancelEditAction', t.cfg_act_cancel_edit, { color: 'light' }) : null,
                    ]),
                    sectionForm(editor, state, 'action', actionBlocks(ctx)),
                    notice(state, 'action'),
                    h('div', { style: footStyle }, [runButton(editor, state, 'addAction', editing >= 0 ? t.cfg_act_btn_save : t.cfg_act_btn_add)]),
                ]),
            ]);
        };
    },
});

// ── Custom filters ──────────────────────────────────────────────────────────

export const FiltersTab = defineComponent({
    name: 'PtahCrudConfigFilters',
    props: tabProps,
    setup(props) {
        return () => {
            const { editor, state, t, ctx } = props;
            const filters = state.document!.customFilters;
            const tag = (value: string) => h('span', { style: tagStyle }, value);

            return h('div', { style: { display: 'grid', gap: '16px' } }, [
                renderNode(filterGuide(t), nothing, nothing),
                ...filters.map((filter, index) =>
                    h('section', { key: index, style: sectionStyle }, [
                        h('div', { style: headStyle }, [
                            h('div', [h('strong', text(filter.label) || text(filter.field) || fill(t.filterNumber, { n: index })), h('p', { style: { ...mutedStyle, ...monoStyle } }, text(filter.field))]),
                            iconButton(`${t.remove}: ${text(filter.label) || text(filter.field)}`, '✕', () => editor.askRemove('removeCustomFilter', index, t.cfg_filter_remove_confirm), true),
                        ]),
                        h('div', { style: { display: 'flex', flexWrap: 'wrap', gap: '6px' } }, [
                            text(filter.whereHas) !== '' ? tag(`whereHas: ${text(filter.whereHas)}`) : null,
                            text(filter.field_relation) !== '' ? tag(`${t.tagField}: ${text(filter.field_relation)}`) : null,
                            text(filter.aggregate) !== '' ? tag(text(filter.aggregate)) : null,
                            text(filter.defaultOperator) !== '' ? tag(`${t.tagOperator}: ${text(filter.defaultOperator)}`) : null,
                            text(filter.colsFilterType) !== '' ? tag(`${t.tagType}: ${text(filter.colsFilterType)}`) : null,
                        ]),
                    ]),
                ),
                h('section', { style: sectionStyle, 'aria-label': t.cfg_filter_form_title }, [
                    h('h4', { style: titleStyle }, t.cfg_filter_form_title),
                    sectionForm(editor, state, 'filter', filterBlocks(ctx)),
                    notice(state, 'filter'),
                    h('div', { style: footStyle }, [runButton(editor, state, 'addCustomFilter', t.cfg_filter_btn_add)]),
                ]),
            ]);
        };
    },
});

// ── Conditional styles ──────────────────────────────────────────────────────

export const StylesTab = defineComponent({
    name: 'PtahCrudConfigStyles',
    props: tabProps,
    setup(props) {
        return () => {
            const { editor, state, t, ctx } = props;
            const styles = state.document!.conditionStyles;

            return h('div', { style: { display: 'grid', gap: '16px' } }, [
                renderNode(styleGuide(t), nothing, nothing),
                ...styles.map((style, index) =>
                    h('section', { key: index, style: sectionStyle }, [
                        h('div', { style: headStyle }, [
                            h('div', { style: { display: 'flex', gap: '8px', alignItems: 'center' } }, [h('code', { style: monoStyle }, text(style.field)), h('strong', text(style.condition) || '=='), h('code', { style: monoStyle }, text(style.value))]),
                            iconButton(`${t.remove}: ${text(style.field)} ${text(style.condition) || '=='} ${text(style.value)}`, '✕', () => editor.askRemove('removeConditionStyle', index, t.cfg_style_remove_confirm), true),
                        ]),
                        h('code', { style: { ...monoStyle, padding: '2px 8px', borderRadius: '4px', background: 'var(--pc-bg-soft)' } }, text(style.style)),
                        text(style.style) !== '' ? h('div', { style: { padding: '6px 12px', borderRadius: '4px', fontSize: '12px', ...cssStyle(text(style.style)) } }, t.cfg_style_preview_row) : null,
                    ]),
                ),
                h('section', { style: sectionStyle, 'aria-label': t.cfg_style_form_title }, [
                    h('h4', { style: titleStyle }, t.cfg_style_form_title),
                    sectionForm(editor, state, 'style', styleBlocks(state.forms.style, ctx)),
                    notice(state, 'style'),
                    h('div', { style: footStyle }, [runButton(editor, state, 'addConditionStyle', t.cfg_style_btn_add)]),
                ]),
            ]);
        };
    },
});

// ── JOINs ───────────────────────────────────────────────────────────────────

export const JoinsTab = defineComponent({
    name: 'PtahCrudConfigJoins',
    props: tabProps,
    setup(props) {
        return () => {
            const { editor, state, t, ctx } = props;
            const document = state.document!;
            const form = state.forms.join;
            const editing = state.editing.join;
            const duplicate = joinDuplicate(form, document, editing);

            return h('div', { style: { display: 'grid', gap: '16px' } }, [
                state.joinError ? h(ForgeAlert, { color: 'danger' }, () => state.joinError) : null,
                renderNode(joinGuide(t), nothing, nothing),
                document.joins.length === 0 ? h(ForgeEmpty, { title: t.cfg_join_empty, description: t.cfg_join_empty_hint }) : null,
                ...document.joins.map((join, index) => {
                    const left = text(join.type || 'left').toLowerCase() === 'left';
                    const select = Array.isArray(join.select) ? (join.select as Record<string, unknown>[]) : [];

                    return h('section', { key: index, style: { ...sectionStyle, borderLeft: `4px solid ${left ? '#38bdf8' : '#fbbf24'}` } }, [
                        h('div', { style: headStyle }, [
                            h('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, [
                                h('span', { style: tagStyle }, `${text(join.type || 'left').toUpperCase()} JOIN`),
                                h('strong', { style: monoStyle }, text(join.table) || '—'),
                                truthy(join.distinct) ? h('span', { style: tagStyle }, 'DISTINCT') : null,
                            ]),
                            h('div', { style: { display: 'flex', gap: '6px' } }, [
                                h(ForgeButton, { size: 'sm', flat: true, 'aria-label': `${t.cfg_join_edit_btn}: ${text(join.table)}`, onClick: () => void editor.run('editJoin', { index }) }, () => t.cfg_join_edit_btn),
                                h(
                                    ForgeButton,
                                    { size: 'sm', color: 'danger', flat: true, 'aria-label': `${t.cfg_join_remove_btn}: ${text(join.table)}`, onClick: () => editor.askRemove('removeJoin', index, fill(t.cfg_join_remove_confirm, { table: text(join.table) })) },
                                    () => t.cfg_join_remove_btn,
                                ),
                            ]),
                        ]),
                        h('p', { style: { ...monoStyle, margin: 0 } }, `ON ${text(join.first)} = ${text(join.second)}`),
                        select.length > 0
                            ? h('div', [
                                  h('p', { style: mutedStyle }, t.cfg_join_cols_show),
                                  h(
                                      'div',
                                      { style: { display: 'flex', flexWrap: 'wrap', gap: '6px' } },
                                      select.map((entry, at) => h('span', { key: at, style: { ...tagStyle, ...monoStyle } }, [`${text(entry.column)} → `, h('strong', text(entry.alias))])),
                                  ),
                              ])
                            : h('p', { style: { ...mutedStyle, color: '#b45309' } }, t.cfg_join_no_cols_warn),
                    ]);
                }),
                h('section', { style: sectionStyle, 'aria-label': editing >= 0 ? t.cfg_join_form_editing : t.cfg_join_form_new }, [
                    h('h4', { style: titleStyle }, editing >= 0 ? `${t.cfg_join_form_editing} — ${t.joinWith} ${text(document.joins[editing]?.table)}` : t.cfg_join_form_new),
                    sectionForm(editor, state, 'join', joinBlocks(form, editing, ctx, null)),
                    notice(state, 'join'),
                    h(
                        'div',
                        { style: footStyle },
                        editing >= 0
                            ? [runButton(editor, state, 'cancelEditJoin', t.cfg_join_cancel_edit, { color: 'light' }), runButton(editor, state, 'addJoin', t.cfg_join_btn_update)]
                            : [runButton(editor, state, 'addJoin', t.cfg_join_btn_add, { disabled: duplicate })],
                    ),
                ]),
            ]);
        };
    },
});

// ── General, permissions, hooks (the settings) ──────────────────────────────

const settingsBlocks = (editor: ConfigEditor, state: EditorState, blocks: FormBlock[]) =>
    renderBlocks(
        blocks,
        (key) => readValue(state.document!.settings, key),
        (key, value) => editor.setSetting(key, value),
    );

export const GeneralTab = defineComponent({
    name: 'PtahCrudConfigGeneral',
    props: tabProps,
    setup(props) {
        return () => settingsBlocks(props.editor, props.state, generalBlocks(props.ctx));
    },
});

export const PermissionsTab = defineComponent({
    name: 'PtahCrudConfigPermissions',
    props: tabProps,
    setup(props) {
        return () => settingsBlocks(props.editor, props.state, permissionBlocks(props.ctx));
    },
});

export const HooksTab = defineComponent({
    name: 'PtahCrudConfigHooks',
    props: tabProps,
    setup(props) {
        return () => settingsBlocks(props.editor, props.state, hookBlocks(props.ctx));
    },
});

// ── Notifications ───────────────────────────────────────────────────────────

export const NotificationsTab = defineComponent({
    name: 'PtahCrudConfigNotifications',
    props: tabProps,
    setup(props) {
        return () => {
            const { editor, state, t, ctx } = props;
            const rules = state.document!.notificationRules;
            const editing = state.editing.notification;

            if (!state.payload?.options.notificationsEnabled) {
                return h('section', { style: sectionStyle }, [
                    h('h4', { style: titleStyle }, t.cfg_notif_off_title),
                    h('p', { style: mutedStyle }, t.cfg_notif_off_desc),
                    h('ol', { style: { margin: 0, paddingLeft: '18px', fontSize: '12px' } }, [h('li', t.cfg_notif_off_step_env), h('li', t.cfg_notif_off_step_publish), h('li', t.cfg_notif_off_step_migrate)]),
                ]);
            }

            return h('div', { style: { display: 'grid', gap: '16px' } }, [
                state.hints.traitMissing ? h(ForgeAlert, { color: 'warn' }, () => fill(t.cfg_notif_trait_missing, { trait: 'SendsCrudNotifications' })) : null,
                rules.length === 0 ? h(ForgeEmpty, { title: t.cfg_notif_empty, description: t.cfg_notif_empty_hint }) : null,
                ...rules.map((rule, index) =>
                    h('section', { key: index, style: sectionStyle }, [
                        h('div', { style: headStyle }, [
                            h('div', { style: { display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '6px' } }, [
                                h('span', { style: { ...tagStyle, textTransform: 'uppercase' } }, text(rule.event)),
                                h('span', { style: tagStyle }, `${text(rule.audience)}${text(rule.audienceValue) !== '' ? `: ${text(rule.audienceValue)}` : ''}`),
                                h('strong', text(rule.title)),
                            ]),
                            h('div', { style: { display: 'flex', gap: '6px' } }, [
                                h(ForgeButton, { size: 'sm', flat: true, 'aria-label': `${t.cfg_notif_edit_btn}: ${text(rule.title)}`, onClick: () => void editor.run('editNotificationRule', { index }) }, () => t.cfg_notif_edit_btn),
                                h(
                                    ForgeButton,
                                    { size: 'sm', color: 'danger', flat: true, 'aria-label': `${t.cfg_notif_remove_btn}: ${text(rule.title)}`, onClick: () => editor.askRemove('removeNotificationRule', index, t.cfg_notif_remove_confirm) },
                                    () => t.cfg_notif_remove_btn,
                                ),
                            ]),
                        ]),
                        text(rule.body) !== '' ? h('p', { style: mutedStyle }, text(rule.body)) : null,
                    ]),
                ),
                h('section', { style: sectionStyle, 'aria-label': editing >= 0 ? t.cfg_notif_form_editing : t.cfg_notif_form_new }, [
                    h('h4', { style: titleStyle }, editing >= 0 ? t.cfg_notif_form_editing : t.cfg_notif_form_new),
                    sectionForm(editor, state, 'notification', notificationBlocks(state.forms.notification, state.audience, ctx)),
                    notice(state, 'notification'),
                    h('div', { style: footStyle }, [
                        editing >= 0 ? runButton(editor, state, 'cancelEditNotificationRule', t.cfg_notif_cancel_edit, { color: 'light' }) : null,
                        runButton(editor, state, 'addNotificationRule', editing >= 0 ? t.cfg_notif_btn_update : t.cfg_notif_btn_add),
                    ]),
                ]),
            ]);
        };
    },
});

// ── Form preview ────────────────────────────────────────────────────────────

const inert: CSSProperties = { cursor: 'not-allowed', opacity: 0.7 };

/** The inert preview of the create/edit form (the Blade's _config-form-preview). */
export function previewModal(preview: PreviewResult, t: CrudConfigWords, onClose: () => void): VNode {
    let previous: string | null = null;

    return h(
        ForgeModal,
        {
            modelValue: true,
            'onUpdate:modelValue': (value: boolean) => !value && onClose(),
            size: 'lg',
            title: preview.title !== '' ? `${t.cfg_preview_title} — ${preview.title}` : t.cfg_preview_title,
            subtitle: t.cfg_preview_inert_notice,
        },
        {
            default: () =>
                preview.fields.length === 0
                    ? h(ForgeEmpty, { title: t.cfg_preview_empty })
                    : h(
                          'div',
                          { style: { display: 'grid', gap: '14px' } },
                          preview.fields.map((field) => {
                              const heading = field.block !== '' && field.block !== previous ? field.block : null;
                              previous = field.block;
                              const id = `cfg-preview-${field.field}`;
                              const sdPlaceholder = field.dependsOn ? fill(t.sd_select_parent_first, { parent: field.dependsOnLabel ?? '' }) : (field.placeholder ?? fill(t.search_entity, { label: field.label }));
                              let control: VNode;
                              if (field.type === 'select' || field.type === 'boolean') {
                                  control = h(
                                      'select',
                                      { id, class: 'pf-control', disabled: true, style: inert },
                                      field.type === 'boolean' ? [h('option', t.bool_yes), h('option', t.bool_no)] : [h('option', t.select_placeholder), ...field.options.map((option) => h('option', { key: option.value }, option.label))],
                                  );
                              } else if (field.type === 'textarea') {
                                  control = h('textarea', { id, class: 'pf-control', rows: 3, disabled: true, style: inert });
                              } else if (field.type === 'image') {
                                  control = h('div', { id, style: { height: '96px', border: '2px dashed var(--pc-line)', borderRadius: '6px' } });
                              } else {
                                  control = h('input', { id, class: 'pf-control', disabled: true, style: inert, placeholder: field.type === 'searchdropdown' ? sdPlaceholder : field.mask });
                              }

                              return h('div', { key: field.field }, [
                                  heading ? h('p', { style: { ...mutedStyle, fontWeight: 600, textTransform: 'uppercase', margin: '8px 0' } }, heading) : null,
                                  h('label', { for: id, class: 'pf-field-label' }, [
                                      field.label,
                                      field.required ? h('span', { 'aria-hidden': 'true' }, ' *') : null,
                                      field.computed ? h('span', { style: { ...tagStyle, marginLeft: '6px' } }, `⚡ ${t.cfg_preview_calc_badge}`) : null,
                                  ]),
                                  control,
                                  field.type === 'searchdropdown' && field.dependsOn ? h('p', { style: mutedStyle }, `🔗 ${fill(t.cfg_preview_depends, { parent: field.dependsOnLabel ?? '' })}`) : null,
                                  field.type === 'searchdropdown' && field.lazy ? h('p', { style: mutedStyle }, `⏳ ${t.cfg_preview_sd_lazy}`) : null,
                                  field.help ? h('p', { style: mutedStyle }, field.help) : null,
                              ]);
                          }),
                      ),
            footer: () => [
                h('span', { style: { ...mutedStyle, marginRight: 'auto' } }, t.cfg_preview_footer_hint),
                h('button', { type: 'button', class: 'pc-btn', disabled: true, style: inert }, t.btn_cancel),
                h('button', { type: 'button', class: 'pc-btn pc-btn--primary', disabled: true, style: inert }, t.btn_create),
            ],
        },
    );
}
