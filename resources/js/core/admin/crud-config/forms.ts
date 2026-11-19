/**
 * What each tab of the config editor shows, framework-free: the fields of
 * every sub-form as a list of nodes, built from the Blade view of the
 * Livewire editor (crud-config.blade.php) — same fields, same conditions,
 * same option values. React and Vue each draw these nodes; neither decides
 * what a tab holds.
 *
 * A node edits one key of its source (a sub-form, or the document's
 * settings). Two kinds of virtual keys edit the column's `colsValidations`
 * list as the Blade checkboxes and inputs do: `rule:email` (present or not)
 * and `param:min` (the `min:` entry's value).
 */
import type { ConfigChoices, ConfigDocument, ConfigEntry, ConfigHints, ConfigOptions, ConfigSection, ConfigSettings } from './client';
import { fill } from './words';
import type { CrudConfigWords } from './words';

export interface Opt {
    value: string;
    label: string;
}

export interface GuideCard {
    title?: string;
    text?: string;
    /** Label → value pairs, drawn as a small two-column grid. */
    rows?: [string, string][];
    /** A second grid ("in the Columns tab…"). */
    after?: { title: string; rows: [string, string][] };
    /** An example row with its CSS applied. */
    sample?: { style: string; text: string };
    items?: string[];
    foot?: string;
}

export type FormNode =
    | { kind: 'text'; key: string; label: string; tip?: string; placeholder?: string; mono?: boolean; hint?: string; wide?: boolean; disabled?: boolean; maxLength?: number; error?: string | null; icon?: boolean }
    | { kind: 'textarea'; key: string; label: string; tip?: string; placeholder?: string; rows: number; mono?: boolean; hint?: string; badge?: string }
    | { kind: 'number'; key: string; label: string; tip?: string; placeholder?: string; min?: number; max?: number; step?: string; hint?: string; wide?: boolean; int?: boolean }
    | { kind: 'check'; key: string; label: string; tip?: string; hint?: string; wide?: boolean }
    | { kind: 'select'; key: string; label: string; tip?: string; options: Opt[]; searchable?: boolean; hint?: string; wide?: boolean }
    | { kind: 'radio'; key: string; label: string; options: Opt[] }
    | { kind: 'note'; tone: 'info' | 'warn' | 'muted' | 'danger'; title?: string; text?: string; items?: string[]; collapsible?: boolean }
    | { kind: 'guide'; title: string; open?: boolean; intro?: string; cards: GuideCard[]; foot?: string }
    | { kind: 'heading'; text: string }
    | { kind: 'chips'; label?: string; items: string[] }
    | { kind: 'path'; parts: string[]; foot: string }
    | { kind: 'sample'; label: string; style: string; className?: string; icon?: string; text: string; code?: string }
    | { kind: 'code'; lines: { text: string; tone?: 'muted' | 'accent' | 'warn' }[] }
    | { kind: 'presets'; key: string; label: string; items: { label: string; value: string }[] }
    | {
          kind: 'badges';
          key: string;
          label: string;
          hint: string;
          colors: { key: string; hex: string; label: string }[];
          words: { add: string; value: string; label: string; icon: string; remove: string; color: string; custom: string };
      };

export interface FormBlock {
    title?: string;
    /** Grid columns of the block (2 by default). */
    columns?: 1 | 2 | 3;
    nodes: FormNode[];
    /** A switch drawn in the block's header (cache on, broadcast on). */
    toggle?: { key: string; label: string; tip?: string };
}

export interface BuildContext {
    t: CrudConfigWords;
    choices: ConfigChoices;
    options: ConfigOptions;
    document: ConfigDocument;
    hints: ConfigHints;
}

// ── Values ──────────────────────────────────────────────────────────────────

/** The Blade's `in_array($v, [true, 'S', 1, '1'], true)`. */
export const truthy = (value: unknown): boolean => value === true || value === 'S' || value === 1 || value === '1';

export const text = (value: unknown): string => (value === null || value === undefined || typeof value === 'object' ? '' : String(value));

/** A column's `colsValidations`, as a list of strings. */
export const rulesOf = (form: ConfigEntry): string[] => (Array.isArray(form.colsValidations) ? form.colsValidations.map((rule) => String(rule)) : []);

/** The value a node shows. */
export function readValue(source: ConfigEntry | ConfigSettings, key: string): unknown {
    const entry = source as ConfigEntry;
    if (key.startsWith('rule:')) {
        return rulesOf(entry).includes(key.slice(5));
    }
    if (key.startsWith('param:')) {
        const prefix = `${key.slice(6)}:`;
        const found = rulesOf(entry).find((rule) => rule.startsWith(prefix));

        return found === undefined ? '' : found.slice(prefix.length);
    }

    return entry[key];
}

/** The source with a node's new value — a copy; the Blade's toggle/param logic for the virtual keys. */
export function writeValue<T extends ConfigEntry | ConfigSettings>(source: T, key: string, value: unknown): T {
    const entry = source as ConfigEntry;
    if (key.startsWith('rule:')) {
        const rule = key.slice(5);
        const rules = rulesOf(entry);
        const next = rules.includes(rule) ? rules.filter((item) => item !== rule) : [...rules, rule];

        return { ...entry, colsValidations: next } as unknown as T;
    }
    if (key.startsWith('param:')) {
        const prefix = `${key.slice(6)}:`;
        const next = rulesOf(entry).filter((rule) => !rule.startsWith(prefix));
        if (text(value) !== '') {
            next.push(prefix + text(value));
        }

        return { ...entry, colsValidations: next } as unknown as T;
    }

    return { ...entry, [key]: value } as unknown as T;
}

/** A CSS declaration list (`a:b;c:d`) as a style object; camelCase keys for React. */
export function cssStyle(css: string, camel = true): Record<string, string> {
    const out: Record<string, string> = {};
    for (const part of css.split(';')) {
        const at = part.indexOf(':');
        if (at <= 0) {
            continue;
        }
        const name = part.slice(0, at).trim().toLowerCase();
        const value = part.slice(at + 1).trim();
        if (!/^-?[a-z][a-z-]*$/.test(name) || value === '' || /url\s*\(|expression\s*\(/i.test(value)) {
            continue;
        }
        out[camel ? name.replace(/-([a-z])/g, (_, letter: string) => letter.toUpperCase()) : name] = value;
    }

    return out;
}

const word = (t: CrudConfigWords, key: string, fallback?: string): string => (t as unknown as Record<string, string>)[key] || fallback || key;

// ── Columns ─────────────────────────────────────────────────────────────────

export const isAction = (column: ConfigEntry): boolean => column.colsTipo === 'action';

/** The table columns (no row actions), with their index in the document. */
export const dataColumns = (document: ConfigDocument) => document.columns.map((column, index) => ({ column, index })).filter((item) => !isAction(item.column));

/** The row actions, with their index in the document. */
export const actionColumns = (document: ConfigDocument) => document.columns.map((column, index) => ({ column, index })).filter((item) => isAction(item.column));

/**
 * The full order for reorderFields() after dragging the table column at
 * `from` to the slot of `to` (both document indexes). Row actions keep their
 * places: the component only reorders when every index is in the list.
 */
export function dragOrder(columns: ConfigEntry[], from: number, to: number): number[] {
    const slots = columns.map((column, index) => (isAction(column) ? -1 : index)).filter((index) => index >= 0);
    const moving = slots.filter((index) => index !== from);
    const at = moving.indexOf(to);
    moving.splice(at < 0 ? moving.length : slots.indexOf(from) < slots.indexOf(to) ? at + 1 : at, 0, from);

    let next = 0;

    return columns.map((column, index) => (isAction(column) ? index : moving[next++]));
}

/** The editor's field lists for the Features block (the Blade's $cfgFieldList…). */
export function fieldLists(document: ConfigDocument): { all: Opt[]; selects: Opt[]; dates: Opt[] } {
    const fields = document.columns.filter((column) => text(column.colsNomeFisico) !== '' && !isAction(column));
    const opt = (column: ConfigEntry): Opt => ({ value: text(column.colsNomeFisico), label: text(column.colsNomeLogico) || text(column.colsNomeFisico) });
    const isDate = (column: ConfigEntry) => {
        const name = text(column.colsNomeFisico);

        return ['date', 'datetime', 'datetime-local'].includes(text(column.colsTipo)) || name.endsWith('_at') || name.endsWith('_date');
    };
    const hasSelect = (column: ConfigEntry) => {
        const value = column.colsSelect;

        return Array.isArray(value) ? value.length > 0 : typeof value === 'object' && value !== null ? Object.keys(value).length > 0 : text(value) !== '' && text(value) !== '0';
    };

    return { all: fields.map(opt), selects: fields.filter(hasSelect).map(opt), dates: fields.filter(isDate).map(opt) };
}

/** The Blade's red "a JOIN for this table already exists" while adding. */
export const joinDuplicate = (form: ConfigEntry, document: ConfigDocument, editing: number): boolean =>
    editing < 0 && text(form.table) !== '' && document.joins.some((join) => text(join.table) === text(form.table));

/** `echo-private:channel.1,.event` — BroadcastListener::key() for the preview. */
export function broadcastKey(settings: ConfigSettings, options: ConfigOptions): string {
    const broadcast = options.broadcast;
    const type = settings.broadcastType in broadcast.prefixes ? settings.broadcastType : 'public';
    const channel = (settings.broadcastChannel || broadcast.defaultChannel) + (settings.broadcastPerCompany && broadcast.companyId > 0 ? `.${broadcast.companyId}` : '');

    return `${broadcast.prefixes[type] ?? 'echo'}:${channel},${settings.broadcastEvent || broadcast.defaultEvent}`;
}

/** The sub-tabs of the column form; SearchDropdown only for that type. */
export function columnSubTabs(form: ConfigEntry, t: CrudConfigWords): Opt[] {
    const tabs: Opt[] = [
        { value: 'basic', label: t.cfg_col_subtab_basic },
        { value: 'renderer', label: t.cfg_col_subtab_display },
        { value: 'mask', label: t.cfg_col_subtab_mask },
        { value: 'validation', label: t.cfg_col_subtab_validation },
        { value: 'relation', label: t.cfg_col_subtab_relation },
    ];
    if (form.colsTipo === 'searchdropdown') {
        tabs.push({ value: 'sd', label: t.cfg_col_subtab_sd });
    }
    tabs.push({ value: 'total', label: t.cfg_col_subtab_totalizer });

    return tabs;
}

/** "Fill in … first" for an action the component ignored. */
export function notAppliedText(section: ConfigSection, t: CrudConfigWords): string {
    return {
        field: t.notAppliedField,
        action: t.notAppliedAction,
        join: t.notAppliedJoin,
        notification: t.notAppliedNotification,
        filter: t.notAppliedFilter,
        style: t.notAppliedStyle,
    }[section];
}

// ── Option labels ───────────────────────────────────────────────────────────

const MASK_LABELS: Record<string, string> = {
    money_brl: 'money_brl — R$ 1.253,08',
    money_usd: 'money_usd — $ 1,253.08',
    percent: 'percent — 99,99%',
    cpf: 'cpf — 000.000.000-00',
    cnpj: 'cnpj — 00.000.000/0000-00',
    rg: 'rg — 00.000.000-0',
    pis: 'pis — 000.00000.00-0',
    ncm: 'ncm — 0000.00.00',
    phone: 'phone — (00) 0 0000-0000',
    cep: 'cep — 00000-000',
    plate: 'plate — ABC-1234 / Mercosul ABC1A23',
    credit_card: 'credit_card — 0000 0000 0000 0000',
    date: 'date — 00/00/0000',
    datetime: 'datetime — 00/00/0000 00:00',
    time: 'time — 00:00',
};

const TRANSFORM_LABELS: Record<string, string> = {
    money_to_float: 'money_to_float — "R$ 1.253,08" → 1253.08',
    digits_only: 'digits_only — "055.465.309-52" → "05546530952"',
    plate_clean: 'plate_clean — "ABC-1234" → "ABC1234"',
    date_br_to_iso: 'date_br_to_iso — "01/12/2024" → "2024-12-01"',
    date_iso_to_br: 'date_iso_to_br — "2024-12-01" → "01/12/2024"',
    uppercase: 'uppercase — "texto" → "TEXTO"',
    lowercase: 'lowercase — "TEXTO" → "texto"',
};

const TRANSFORM_PREVIEW: Record<string, string> = {
    money_to_float: 'R$ 1.253,08 → 1253.08',
    digits_only: '055.465.309-52 → 05546530952',
    plate_clean: '"ABC-1234" → ABC1234',
    date_br_to_iso: '"01/12/2024" → 2024-12-01',
    date_iso_to_br: '"2024-12-01" → 01/12/2024',
    uppercase: '"texto" → "TEXTO"',
    lowercase: '"TEXTO" → "texto"',
    trim: '" texto " → "texto"',
};

const BADGE_HEX: Record<string, string> = { green: '#22c55e', yellow: '#eab308', red: '#ef4444', blue: '#3b82f6', indigo: '#6366f1', purple: '#a855f7', pink: '#ec4899', gray: '#9ca3af' };

const OPERATOR_WORDS: Record<string, string> = { '=': 'op_eq', '==': 'op_eq2', '!=': 'op_neq', LIKE: 'op_like', '>': 'op_gt', '<': 'op_lt', '>=': 'op_gte', '<=': 'op_lte' };

/** Labels for every select of the editor, by list (the Blade's option texts). */
export function optionsFor(list: keyof ConfigChoices, ctx: BuildContext): Opt[] {
    const { t } = ctx;
    const values = ctx.choices[list] ?? [];
    const label = (value: string): string => {
        switch (list) {
            case 'fieldTypes':
                return word(t, value === 'searchdropdown' ? 'cfg_col_type_sd' : `cfg_col_type_${value}`, value);
            case 'aligns':
                return word(t, { 'text-start': 'cfg_col_align_left', 'text-center': 'cfg_col_align_center', 'text-end': 'cfg_col_align_right' }[value] ?? value, value);
            case 'renderers':
                return value === '' ? t.cfg_col_renderer_none : word(t, value === 'color' ? 'cfg_col_renderer_opt_color_sw' : `cfg_col_renderer_opt_${value}`, value);
            case 'currencies':
                return word(t, `cfg_col_currency_${value.toLowerCase()}`, value);
            case 'durationUnits':
                return value === 'minutes' ? t.durationMinutes : value === 'seconds' ? t.durationSeconds : value;
            case 'masks':
                return value === '' ? t.cfg_col_mask_none : value === 'ean13' ? t.cfg_col_mask_ean13 : value === 'uppercase' ? t.cfg_col_mask_uppercase_opt : value === 'integer' ? t.maskInteger : (MASK_LABELS[value] ?? value);
            case 'maskTransforms':
                return value === '' ? t.transformNone : value === 'trim' ? t.cfg_col_mask_trim_opt : value === 'plate_clean' ? `${TRANSFORM_LABELS[value]} ${t.cfg_col_mask_plate_sfx}` : (TRANSFORM_LABELS[value] ?? value);
            case 'sdMasks':
                return value === 'defaultMask' ? t.cfg_col_mask_none : value;
            case 'sdStartList':
                return word(t, `cfg_col_sd_start_list_${value}`, value);
            case 'sdModes':
                return value === 'model' ? t.sdModel : value === 'service' ? t.cfg_col_sd_mode_service : value;
            case 'totalizerFunctions':
                return word(t, `cfg_col_total_${value}`, value);
            case 'actionTypes':
                return word(t, value === 'javascript' ? 'cfg_act_type_js' : `cfg_act_type_${value}`, value);
            case 'filterTypes':
                return word(t, `cfg_filter_type_${value}`, value);
            case 'filterOperators':
            case 'styleOperators':
                return word(t, OPERATOR_WORDS[value] ?? value, value);
            case 'aggregates':
                return value === '' ? t.cfg_filter_agg_none : word(t, `cfg_filter_agg_${value.toLowerCase()}`, value);
            case 'joinTypes':
                return word(t, `cfg_join_type_${value}`, value);
            case 'genders':
                return `${value === 'f' ? t.modal_new_prefix_f : t.modal_new_prefix} …`;
            case 'groupBreakPeriods':
                return value === '' ? t.cfg_gen_group_break_period_value : word(t, `cfg_gen_group_break_period_${value}`, value);
            case 'exportOrientations':
                return word(t, `cfg_gen_export_${value}`, value);
            case 'broadcastTypes':
                return word(t, `cfg_gen_broadcast_${value}`, value);
            case 'importModes':
                return word(t, `cfg_feature_import_${value}`, value);
            case 'notificationEvents':
                return word(t, `cfg_notif_event_${value}`, value);
            case 'notificationTypes':
                return word(t, `cfg_notif_type_${value}`, value);
            case 'notificationAudiences':
                return word(t, `cfg_notif_audience_${value}`, value);
            default:
                return value;
        }
    };

    return values.map((value) => ({ value, label: label(value) }));
}

// ── Column form ─────────────────────────────────────────────────────────────

const VALIDATION_PARAMS: Record<string, { label: string; tip: string; placeholder: string; number?: boolean; any?: boolean; hint?: string }> = {
    min: { label: 'cfg_col_valid_min', tip: 'cfg_tip_valid_min', placeholder: 'ex: 0', number: true, any: true },
    max: { label: 'cfg_col_valid_max', tip: 'cfg_tip_valid_max', placeholder: 'ex: 9999', number: true, any: true },
    minLength: { label: 'cfg_col_valid_min_len', tip: 'cfg_tip_valid_min_len', placeholder: 'ex: 3', number: true },
    maxLength: { label: 'cfg_col_valid_max_len', tip: 'cfg_tip_valid_max_len', placeholder: 'ex: 255', number: true },
    regex: { label: 'cfg_col_valid_regex', tip: 'cfg_tip_valid_regex', placeholder: 'Ex: ^[A-Z]{2,5}$ ou /^\\d{5}$/' },
    digits: { label: 'cfg_col_valid_digits', tip: 'cfg_tip_valid_digits', placeholder: 'ex: 8', number: true },
    digitsBetween: { label: 'cfg_col_valid_digits_btw', tip: 'cfg_tip_valid_digits_btw', placeholder: 'ex: 8,11' },
    after: { label: 'cfg_col_valid_after', tip: 'cfg_tip_valid_after', placeholder: 'today ou 2020-01-01' },
    before: { label: 'cfg_col_valid_before', tip: 'cfg_tip_valid_before', placeholder: 'today ou 2030-12-31' },
    dateFormat: { label: 'cfg_col_valid_date_fmt', tip: 'cfg_tip_valid_date_fmt', placeholder: 'd/m/Y ou Y-m-d' },
    confirmed: { label: 'cfg_col_valid_confirmed', tip: 'cfg_tip_valid_confirmed', placeholder: 'password_confirmation', hint: 'cfg_col_valid_confirmed_hint' },
    unique: { label: 'cfg_col_valid_unique', tip: 'cfg_tip_valid_unique', placeholder: 'Product,email', hint: 'cfg_col_valid_unique_hint' },
    in: { label: 'cfg_col_valid_in', tip: 'cfg_tip_valid_in', placeholder: 'ativo,inativo,pendente' },
    notIn: { label: 'cfg_col_valid_not_in', tip: 'cfg_tip_valid_not_in', placeholder: 'deletado,arquivado' },
};

/** The fields of one sub-tab of the column form (Basic, Display, Mask…). */
export function columnBlocks(sub: string, form: ConfigEntry, ctx: BuildContext): FormBlock[] {
    const { t } = ctx;
    const tip = (key: string) => word(t, `cfg_tip_${key}`, '');

    switch (sub) {
        case 'basic': {
            const nodes: FormNode[] = [
                { kind: 'text', key: 'colsNomeFisico', label: t.cfg_col_field_label, tip: tip('cols_nome_fisico'), placeholder: 'ex: supplier_id', mono: true },
                { kind: 'text', key: 'colsNomeLogico', label: t.cfg_col_logic_label, tip: tip('cols_nome_logico'), placeholder: 'ex: Fornecedor' },
                { kind: 'text', key: 'colsSource', label: `${t.cfg_col_sql_label} · JOIN ${t.cfg_col_sql_optional}`, tip: tip('cols_fonte_sql'), placeholder: 'ex: suppliers.name', mono: true, wide: true },
                {
                    kind: 'note',
                    tone: 'info',
                    title: t.cfg_col_field_guide_title,
                    items: [
                        `${t.cfg_term_phys_name} ${t.cfg_col_field_guide_phys_desc}`,
                        `${t.cfg_term_sql_source} ${t.cfg_col_field_guide_sql_desc}`,
                        t.cfg_col_field_guide_formats,
                        `suppliers.name ${t.cfg_col_field_guide_qualified}`,
                        `supplier.name ${t.cfg_col_field_guide_singular} suppliers.name`,
                        `product_supplier.product.name — ${t.cfg_col_field_guide_enc} products.name`,
                        t.cfg_col_write_warn,
                    ],
                },
                { kind: 'select', key: 'colsTipo', label: t.cfg_col_type_label, tip: tip('cols_tipo'), options: optionsFor('fieldTypes', ctx) },
                { kind: 'select', key: 'colsAlign', label: t.cfg_col_align_label, tip: tip('cols_align'), options: optionsFor('aligns', ctx) },
                { kind: 'check', key: 'colsGravar', label: t.cfg_col_cb_save, tip: tip('cols_gravar') },
                { kind: 'check', key: 'colsRequired', label: t.cfg_col_cb_required, tip: tip('cols_required') },
                { kind: 'check', key: 'colsIsFilterable', label: t.cfg_col_cb_filterable, tip: tip('cols_is_filterable') },
                { kind: 'check', key: 'colsReadonly', label: t.cfg_col_cb_readonly, tip: tip('cols_readonly') },
                { kind: 'text', key: 'colsDefaultValue', label: t.cfg_col_default_value, tip: tip('cols_default_value'), wide: true },
            ];
            if (ctx.options.permissionsModule) {
                nodes.push({
                    kind: 'select',
                    key: 'colsPermission',
                    label: t.cfg_col_permission_label,
                    tip: tip('cols_permission'),
                    options: [{ value: '', label: t.cfg_col_permission_none }, ...ctx.options.permissionKeys],
                    searchable: true,
                    hint: t.cfg_col_permission_hint,
                    wide: true,
                });
            }
            nodes.push(
                { kind: 'text', key: 'colsFormBlock', label: t.cfg_col_form_block, tip: tip('cols_form_block'), placeholder: 'ex: Endereço', hint: t.cfg_col_form_block_hint },
                { kind: 'text', key: 'colsOnChange', label: t.cfg_col_on_change, tip: tip('cols_on_change'), placeholder: "merge(data, {'total': data['qty'] * data['price']})", mono: true, hint: t.cfg_col_on_change_hint },
            );
            if (form.colsTipo === 'select') {
                nodes.push({ kind: 'text', key: 'colsSelect', label: t.cfg_col_select_opts, tip: tip('cols_select'), placeholder: t.cfg_col_select_opts_ph, mono: true, hint: t.cfg_col_select_fmt_hint, wide: true });
            }
            nodes.push(
                { kind: 'text', key: 'colsMetodoCustom', label: t.cfg_col_custom_method, tip: tip('cols_metodo_custom'), placeholder: 'App\\Services\\MyService\\formatValue(%campo%)', mono: true },
                { kind: 'text', key: 'colsOrderBy', label: t.cfg_col_order_by, tip: tip('cols_order_by'), placeholder: 'campo_db ou relation.campo' },
                { kind: 'check', key: 'colsMetodoRaw', label: t.cfg_col_method_raw_label, tip: tip('cols_metodo_raw'), wide: true },
                {
                    kind: 'note',
                    tone: 'muted',
                    collapsible: true,
                    title: t.cfg_col_method_syntax,
                    items: [
                        `${t.syntax} Namespace\\Classe\\metodo(%campo1%, %campo2%, 'literal')`,
                        `%campo% → ${t.cfg_col_method_guide_sub}`,
                        `'literal' / "literal" → ${t.literalString}`,
                        t.cfg_col_method_guide_multi,
                        `${t.prefixThe} App\\Services\\ ${t.cfg_col_method_guide_prefix}`,
                        `${t.examples} Branch\\MyService\\getLabel(%id%) · Branch\\MyService\\format(%id%, %status%, 'active') · Branch\\MyService\\badge(%type%) ${t.rawForHtml}`,
                    ],
                },
            );
            const style: FormNode[] = [
                { kind: 'text', key: 'colsCellStyle', label: t.cellStyleLabel, tip: tip('cols_cell_style'), placeholder: 'font-weight:700; color:#1a56db; font-size:13px;', mono: true, hint: t.cfg_col_cell_style_hint },
                { kind: 'text', key: 'colsMinWidth', label: t.cfg_col_cell_min_width, tip: tip('cols_min_width'), placeholder: '140px ou 8rem' },
                { kind: 'text', key: 'colsCellIcon', label: t.cfg_col_cell_icon_prefix, tip: tip('cols_cell_icon'), placeholder: 'bx bx-user-circle mr-1', mono: true, hint: t.cfg_col_cell_icon_hint },
                { kind: 'text', key: 'colsCellClass', label: t.cfg_col_cell_tw_class, tip: tip('cols_cell_class'), placeholder: 'font-bold text-blue-600 uppercase', mono: true },
            ];
            if (text(form.colsCellStyle) !== '' || text(form.colsCellClass) !== '' || text(form.colsCellIcon) !== '') {
                style.push({ kind: 'sample', label: t.cfg_col_cell_preview, style: text(form.colsCellStyle), className: text(form.colsCellClass), icon: text(form.colsCellIcon), text: t.cfg_col_cell_example, code: text(form.colsCellStyle) });
            }

            return [{ nodes }, { title: t.cfg_col_cell_style_title, nodes: style }];
        }

        case 'renderer': {
            const renderer = text(form.colsRenderer);
            const nodes: FormNode[] = [{ kind: 'select', key: 'colsRenderer', label: t.cfg_col_renderer_label, tip: tip('cols_renderer'), options: optionsFor('renderers', ctx), wide: true }];
            const colors = ctx.choices.badgeColors.map((key) => ({ key, hex: BADGE_HEX[key] ?? '#9ca3af', label: word(t, `color_${key}`, key) }));
            if (renderer === 'badge' || renderer === 'pill') {
                nodes.push({
                    kind: 'badges',
                    key: 'colsRendererBadges',
                    label: t.cfg_col_renderer_badge_map,
                    hint: t.cfg_col_badge_map_hint,
                    colors,
                    words: { add: t.cfg_col_renderer_add_badge, value: t.badgeValuePh, label: t.cfg_col_badge_label_ph, icon: t.badgeIconPh, remove: t.badgeRemove, color: t.badgeColor, custom: t.customColor },
                });
            } else if (renderer === 'boolean') {
                nodes.push(
                    { kind: 'text', key: 'colsRendererBoolTrue', label: t.cfg_col_renderer_bool_true, tip: tip('renderer_bool_true'), placeholder: 'Sim' },
                    { kind: 'text', key: 'colsRendererBoolFalse', label: t.cfg_col_renderer_bool_false, tip: tip('renderer_bool_false'), placeholder: t.cfg_col_bool_false_ph },
                );
            } else if (renderer === 'money') {
                nodes.push(
                    { kind: 'select', key: 'colsRendererCurrency', label: t.cfg_col_renderer_currency, tip: tip('renderer_currency'), options: optionsFor('currencies', ctx) },
                    { kind: 'number', key: 'colsRendererDecimals', label: t.cfg_col_renderer_decimals, tip: tip('renderer_money_decimals'), min: 0, max: 4 },
                    { kind: 'check', key: 'colsRendererCents', label: t.cfg_col_renderer_cents, tip: tip('renderer_money_cents'), wide: true },
                );
            } else if (renderer === 'link') {
                nodes.push(
                    { kind: 'text', key: 'colsRendererLinkTemplate', label: t.cfg_col_renderer_url_tmpl, tip: tip('renderer_link_template'), placeholder: '/pedidos/%id%/detalhe', mono: true, hint: t.linkHint, wide: true },
                    { kind: 'text', key: 'colsRendererLinkLabel', label: t.cfg_col_renderer_link_label, tip: tip('renderer_link_label'), placeholder: 'Ver detalhes' },
                    { kind: 'check', key: 'colsRendererLinkNewTab', label: t.cfg_col_renderer_new_tab, tip: tip('renderer_link_new_tab') },
                );
            } else if (renderer === 'image') {
                nodes.push(
                    { kind: 'number', key: 'colsRendererImageWidth', label: t.cfg_col_renderer_width, tip: tip('renderer_image_width'), placeholder: '40' },
                    { kind: 'number', key: 'colsRendererImageHeight', label: t.cfg_col_renderer_height, tip: tip('renderer_image_height'), placeholder: '40' },
                );
            } else if (renderer === 'truncate') {
                nodes.push({ kind: 'number', key: 'colsRendererMaxChars', label: t.cfg_col_renderer_max_chars, tip: tip('renderer_max_chars'), placeholder: '50' });
            } else if (renderer === 'number') {
                nodes.push(
                    { kind: 'number', key: 'colsRendererDecimals', label: `${t.cfg_col_renderer_decimals} ${t.decimalsSuffix}`, tip: tip('renderer_number_decimals'), placeholder: '2', min: 0, max: 6 },
                    { kind: 'text', key: 'colsRendererLocale', label: t.localeLabel, tip: tip('renderer_locale'), placeholder: 'pt-BR', mono: true, hint: t.localeHint },
                );
            } else if (renderer === 'progress') {
                nodes.push(
                    { kind: 'number', key: 'colsRendererMax', label: `${t.cfg_col_renderer_max_val} ${t.maxSuffix}`, tip: tip('renderer_progress_max'), placeholder: '100' },
                    { kind: 'select', key: 'colsRendererColor', label: `${t.cfg_col_renderer_color} ${t.colorSuffix}`, tip: tip('renderer_progress_color'), options: optionsFor('progressColors', ctx) },
                );
            } else if (renderer === 'rating') {
                nodes.push({ kind: 'number', key: 'colsRendererMax', label: `${t.cfg_col_renderer_max_stars} ${t.maxSuffix}`, tip: tip('renderer_rating_max'), placeholder: '5', min: 1, max: 10 });
            } else if (renderer === 'duration') {
                nodes.push({ kind: 'select', key: 'colsRendererDurationUnit', label: `${t.cfg_col_renderer_duration} ${t.durationSuffix}`, tip: tip('renderer_duration_unit'), options: optionsFor('durationUnits', ctx) });
            } else if (renderer === 'qrcode') {
                nodes.push({ kind: 'number', key: 'colsRendererQrSize', label: `${t.cfg_col_renderer_qr_size} ${t.qrSuffix}`, tip: tip('renderer_qr_size'), placeholder: '64', min: 32, max: 256, hint: t.qrHint });
            }

            return [{ nodes }];
        }

        case 'mask': {
            const nodes: FormNode[] = [
                { kind: 'select', key: 'colsMask', label: t.cfg_col_mask_label, tip: tip('cols_mask'), options: optionsFor('masks', ctx) },
                { kind: 'select', key: 'colsMaskTransform', label: t.cfg_col_mask_transform, tip: tip('cols_mask_transform'), options: optionsFor('maskTransforms', ctx) },
            ];
            const transform = text(form.colsMaskTransform);
            if (transform !== '') {
                const extra = transform === 'digits_only' ? ` ${t.removesNonDigits}` : transform === 'plate_clean' ? ` ${t.cfg_col_mask_plate_case}` : '';
                nodes.push({ kind: 'note', tone: 'warn', title: t.cfg_col_mask_transform_save, text: (TRANSFORM_PREVIEW[transform] ?? transform) + extra });
            }

            return [{ nodes }];
        }

        case 'validation': {
            const checks: FormNode[] = ctx.choices.validationRules.map((rule) => ({ kind: 'check', key: `rule:${rule}`, label: word(t, `cfg_col_valid_${rule}`, rule), tip: word(t, `cfg_tip_valid_${rule}`, '') }));
            const params: FormNode[] = ctx.choices.validationParams.map((param) => {
                const def = VALIDATION_PARAMS[param] ?? { label: param, tip: '', placeholder: '' };
                const wide = param === 'regex';
                const hint = def.hint ? word(t, def.hint) : undefined;

                return def.number
                    ? { kind: 'number', key: `param:${param}`, label: word(t, def.label, param), tip: word(t, def.tip, ''), placeholder: def.placeholder, min: def.any ? undefined : param === 'digits' ? 1 : 0, step: def.any ? 'any' : undefined, hint }
                    : { kind: 'text', key: `param:${param}`, label: word(t, def.label, param), tip: word(t, def.tip, ''), placeholder: def.placeholder, mono: true, hint, wide };
            });
            const rules = rulesOf(form);
            const blocks: FormBlock[] = [
                { columns: 1, nodes: [{ kind: 'note', tone: 'muted', text: t.cfg_col_valid_hint }] },
                { columns: 3, nodes: checks },
                { nodes: params },
            ];
            if (rules.length > 0) {
                blocks.push({ columns: 1, nodes: [{ kind: 'chips', label: t.cfg_col_valid_rules_active, items: rules }] });
            }

            return blocks;
        }

        case 'relation': {
            const nested = text(form.colsRelacaoNested);
            const nodes: FormNode[] = [
                { kind: 'text', key: 'colsRelacao', label: t.cfg_col_rel_name, tip: tip('cols_relacao'), placeholder: 'ex: supplier', mono: true, hint: t.cfg_col_rel_name_hint },
                { kind: 'text', key: 'colsRelacaoExibe', label: t.cfg_col_rel_display, tip: tip('cols_relacao_exibe'), placeholder: 'ex: name', mono: true },
                { kind: 'note', tone: 'info', title: t.cfg_col_rel_nested_title, text: `${t.cfg_col_rel_nested_desc} address.city.name` },
                { kind: 'text', key: 'colsRelacaoNested', label: t.cfg_col_rel_nested_label, tip: tip('cols_relacao_nested'), placeholder: 'ex: address.city.name ou supplier.contact.email', mono: true, hint: t.cfg_col_rel_nested_auto, wide: true },
            ];
            if (nested !== '') {
                const parts = nested.split('.');
                nodes.push({ kind: 'path', parts, foot: `${t.eagerLoads} ${parts.slice(0, -1).join('.')}` });
            }

            return [{ nodes }];
        }

        case 'sd': {
            const service = text(form.colsSDTipo || 'model') !== 'model';
            const sdMask = (key: string, label: string, tipKey: string): FormNode => ({ kind: 'select', key, label, tip: tip(tipKey), options: optionsFor('sdMasks', ctx) });

            return [
                {
                    nodes: [
                        { kind: 'note', tone: 'info', text: t.cfg_col_sd_type_hint },
                        { kind: 'check', key: 'colsSDInitWithData', label: t.cfg_col_sd_init_with_data, tip: tip('sd_init_with_data'), hint: t.cfg_col_sd_init_with_data_hint, wide: true },
                        { kind: 'number', key: 'colsSDLimit', label: t.cfg_col_sd_limit, tip: tip('sd_limit'), placeholder: '10', min: 1, max: 100 },
                        { kind: 'radio', key: 'colsSDTipo', label: t.cfg_col_sd_search_mode, options: optionsFor('sdModes', ctx) },
                        ...(service
                            ? ([
                                  { kind: 'text', key: 'colsSDService', label: t.cfg_col_sd_service, tip: tip('sd_service'), placeholder: 'ex: Entrie/ShippingCompaniesService', mono: true },
                                  { kind: 'text', key: 'colsSDServiceMethod', label: t.cfg_col_sd_method, tip: tip('sd_service_method'), placeholder: 'ex: searchDropDownOfShippingCompanies', mono: true },
                              ] as FormNode[])
                            : ([{ kind: 'text', key: 'colsSDModel', label: t.cfg_col_sd_model, tip: tip('sd_model'), placeholder: 'ex: Entrie/ShippingCompanies', mono: true, wide: true }] as FormNode[])),
                        { kind: 'text', key: 'colsSDValor', label: t.cfg_col_sd_value_field, tip: tip('sd_value_field'), placeholder: 'id', mono: true },
                        { kind: 'text', key: 'colsSDLabel', label: t.cfg_col_sd_label_field, tip: tip('sd_label_field'), placeholder: 'name', mono: true },
                        { kind: 'text', key: 'colsSDLabelTwo', label: t.cfg_col_sd_label_two, tip: tip('sd_label_two'), placeholder: 'cnpj', mono: true },
                        { kind: 'text', key: 'colsSDLabelThree', label: t.cfg_col_sd_label_three, tip: tip('sd_label_three'), placeholder: 'city', mono: true },
                        { kind: 'text', key: 'colsSDOrder', label: t.cfg_col_sd_order_by, tip: tip('sd_order_by'), placeholder: 'id asc', mono: true },
                        { kind: 'text', key: 'colsSDPlaceholder', label: t.placeholderLabel, tip: tip('sd_placeholder'), placeholder: 'Buscar...' },
                        { kind: 'select', key: 'colsSDStartList', label: t.cfg_col_sd_start_list, tip: tip('sd_start_list'), options: optionsFor('sdStartList', ctx) },
                        { kind: 'text', key: 'colsSDArraySearch', label: t.cfg_col_sd_array_search, tip: tip('sd_array_search'), placeholder: 'cnpj,email', mono: true, hint: t.cfg_col_sd_array_search_hint, wide: true },
                    ],
                },
                { columns: 3, nodes: [sdMask('colsSDMaskOne', t.cfg_col_sd_mask_one, 'sd_mask_one'), sdMask('colsSDMaskTwo', t.cfg_col_sd_mask_two, 'sd_mask_two'), sdMask('colsSDMaskThree', t.cfg_col_sd_mask_three, 'sd_mask_three')] },
                { columns: 1, nodes: [{ kind: 'text', key: 'colsSDFilters', label: t.cfg_col_sd_filters, tip: tip('sd_filters'), placeholder: '[{"field":"active","value":"S"}]', mono: true, wide: true }] },
                {
                    title: t.cfg_col_sd_cascade_title,
                    nodes: [
                        { kind: 'note', tone: 'info', text: t.cfg_col_sd_cascade_hint },
                        { kind: 'text', key: 'colsSDDependsOn', label: t.cfg_col_sd_depends_on, tip: tip('sd_depends_on'), placeholder: 'ex: state_id', mono: true, hint: t.cfg_col_sd_depends_on_hint },
                        { kind: 'text', key: 'colsSDFilterColumn', label: t.cfg_col_sd_filter_column, tip: tip('sd_filter_column'), placeholder: 'ex: state_id', mono: true, hint: t.cfg_col_sd_filter_column_hint },
                    ],
                },
            ];
        }

        case 'total': {
            const nodes: FormNode[] = [{ kind: 'check', key: 'totalizadorEnabled', label: t.cfg_col_total_enable, tip: tip('totalizador_enable'), wide: true }];
            if (form.totalizadorEnabled) {
                nodes.push({ kind: 'select', key: 'totalizadorType', label: t.cfg_col_total_func, tip: tip('totalizador_type'), options: optionsFor('totalizerFunctions', ctx) });
            }

            return [{ nodes }];
        }

        default:
            return [];
    }
}

// ── Actions, filters, styles, joins, notifications ──────────────────────────

export function actionBlocks(ctx: BuildContext): FormBlock[] {
    const { t } = ctx;
    const tip = (key: string) => word(t, `cfg_tip_${key}`, '');

    return [
        {
            nodes: [
                { kind: 'text', key: 'colsNomeLogico', label: t.cfg_act_name_label, tip: tip('action_name'), placeholder: 'ex: Ver Detalhes' },
                { kind: 'select', key: 'actionType', label: t.cfg_act_type_label, tip: tip('action_type'), options: optionsFor('actionTypes', ctx) },
                { kind: 'text', key: 'actionValue', label: t.cfg_act_value_label, tip: tip('action_value'), placeholder: 'link: /pedidos/%id%  |  livewire: approve(%id%)  |  js: confirm(%id%)', mono: true, hint: t.actionValueHint, wide: true },
                { kind: 'text', key: 'actionIcon', label: t.cfg_act_icon_label, tip: tip('action_icon'), placeholder: 'bx bx-show', mono: true, hint: t.actionIconHint, icon: true },
                { kind: 'select', key: 'actionColor', label: t.cfg_act_color_label, tip: tip('action_color'), options: optionsFor('actionColors', ctx) },
                { kind: 'text', key: 'actionPermission', label: t.cfg_act_permission_label, tip: tip('action_permission'), placeholder: 'ex: agenda:update', mono: true },
                { kind: 'check', key: 'actionConfirm', label: t.cfg_act_confirm_label, tip: tip('action_confirm') },
                { kind: 'text', key: 'actionConfirmMessage', label: t.cfg_act_confirm_message_label, tip: tip('action_confirm_message'), placeholder: t.row_action_confirm },
            ],
        },
    ];
}

export function filterGuide(t: CrudConfigWords): FormNode {
    const rows = (field: string, label: string, type: string, op: string, rel: string, relField: string, agg?: string): [string, string][] => {
        const out: [string, string][] = [
            [t.guideField, field],
            [t.guideLabel, label],
            [t.guideType, type],
            [t.guideOperator, op],
            ['whereHas', rel],
            [t.cfg_filter_rel_field, relField],
        ];
        if (agg) {
            out.push([t.cfg_filter_aggregate, agg]);
        }

        return out;
    };

    return {
        kind: 'guide',
        title: t.cfg_filter_guide_title,
        cards: [
            { title: t.cfg_filter_guide_s1_title, text: t.cfg_filter_guide_s1_desc, rows: rows('status', 'Status', 'select', '=', t.guideEmpty, t.guideEmpty), foot: `${t.guideGenerates} WHERE status = 'ativo'` },
            { title: t.cfg_filter_guide_s2_title, text: t.cfg_filter_guide_s2_desc, rows: rows('supplier_name', 'Fornecedor', 'text', 'LIKE', 'supplier', 'name'), foot: `${t.guideGenerates} WHERE EXISTS (SELECT * FROM suppliers WHERE name LIKE '%...%')` },
            { title: t.cfg_filter_guide_s3_title, text: t.cfg_filter_guide_s3_desc, rows: rows('stock_qty', 'Qtd. Estoque', 'number', '>=', 'stockMovements', 'quantity', 'SUM'), foot: `${t.guideGenerates} HAVING SUM(quantity) >= 100` },
        ],
        foot: t.cfg_filter_guide_tip,
    };
}

export function filterBlocks(ctx: BuildContext): FormBlock[] {
    const { t } = ctx;
    const tip = (key: string) => word(t, `cfg_tip_${key}`, '');

    return [
        {
            nodes: [
                { kind: 'text', key: 'field', label: `${t.cfg_filter_field_label} ${t.filterFieldSuffix}`, tip: tip('filter_field'), placeholder: 'ex: supplier_name', mono: true, hint: t.cfg_filter_field_hint },
                { kind: 'text', key: 'label', label: `${t.cfg_filter_lbl_label} ${t.filterLabelSuffix}`, tip: tip('filter_label'), placeholder: 'ex: Fornecedor' },
                { kind: 'select', key: 'colsFilterType', label: t.cfg_filter_type_label, tip: tip('filter_type'), options: optionsFor('filterTypes', ctx) },
                { kind: 'select', key: 'defaultOperator', label: t.cfg_filter_op_label, tip: tip('filter_operator'), options: optionsFor('filterOperators', ctx) },
            ],
        },
        {
            title: t.cfg_filter_rel_sep,
            nodes: [
                { kind: 'text', key: 'whereHas', label: `whereHas ${t.cfg_term_rel_name_ph}`, tip: tip('filter_where_has'), placeholder: 'ex: supplier', mono: true, hint: t.cfg_filter_rel_method_hint },
                { kind: 'text', key: 'field_relation', label: t.cfg_filter_rel_field, tip: tip('filter_field_relation'), placeholder: 'ex: name', mono: true, hint: t.cfg_filter_rel_col_hint },
                { kind: 'select', key: 'aggregate', label: `${t.cfg_filter_aggregate} ${t.filterAggSuffix}`, tip: tip('filter_aggregate'), options: optionsFor('aggregates', ctx), hint: t.cfg_filter_agg_hint },
            ],
        },
    ];
}

export function styleGuide(t: CrudConfigWords): FormNode {
    const rows = (field: string, op: string, value: string, css: string): [string, string][] => [
        [t.guideFieldShort, field],
        [t.guideOperator, op],
        [t.guideValue, value],
        [t.guideCss, css],
    ];

    return {
        kind: 'guide',
        title: t.cfg_style_guide_title,
        intro: t.cfg_style_guide_intro,
        cards: [
            { title: t.styleGuideEx1Title, rows: rows('status', '==', 'Active', 'background:#D4EDDA;color:#155724;'), sample: { style: 'background:#D4EDDA;color:#155724;', text: t.styleGuideEx1Sample } },
            { title: t.cfg_style_guide_ex2_title, rows: rows('stock', '<=', '5', 'background:#F8D7DA;color:#721C24;font-weight:bold;'), sample: { style: 'background:#F8D7DA;color:#721C24;font-weight:bold;', text: t.cfg_style_guide_ex2_sample } },
            { title: t.styleGuideEx3Title, rows: rows('type', '==', 'Supplier', 'color:#999;text-decoration:line-through;background:#F5F5F5;'), sample: { style: 'color:#6b6b6b;text-decoration:line-through;background:#F5F5F5;', text: t.styleGuideEx3Sample } },
        ],
        foot: `${t.styleGuideTip} ${t.cfg_style_guide_tip}`,
    };
}

/** The Blade's quick presets of the style form. */
export const STYLE_PRESETS = [
    { word: 'cfg_style_preset_cancelled', value: 'color:#6b6b6b;text-decoration:line-through;background:#F5F5F5;' },
    { word: 'cfg_style_preset_urgent', value: 'color:#856404;background:#FFF3CD;font-weight:bold;border-left:4px solid #FFC107;' },
    { word: 'cfg_style_preset_success', value: 'background:#D4EDDA;color:#155724;' },
    { word: 'cfg_style_preset_alert', value: 'background:#F8D7DA;color:#721C24;font-weight:bold;' },
    { word: 'cfg_style_preset_info', value: 'background:#CCE5FF;color:#004085;' },
];

export function styleBlocks(form: ConfigEntry, ctx: BuildContext): FormBlock[] {
    const { t } = ctx;
    const tip = (key: string) => word(t, `cfg_tip_${key}`, '');
    const nodes: FormNode[] = [{ kind: 'text', key: 'style', label: t.cfg_style_css_label, tip: tip('style_css'), placeholder: 'background:#D4EDDA;color:#155724;', mono: true, hint: t.styleCssHint, wide: true }];
    if (text(form.style) !== '') {
        nodes.push({ kind: 'sample', label: t.cfg_style_preview_label, style: text(form.style), text: t.stylePreviewRow });
    }
    nodes.push({ kind: 'presets', key: 'style', label: t.cfg_style_presets, items: STYLE_PRESETS.map((preset) => ({ label: word(t, preset.word), value: preset.value })) });

    return [
        {
            columns: 3,
            nodes: [
                { kind: 'text', key: 'field', label: t.cfg_style_field_label, tip: tip('style_field'), placeholder: 'ex: status', mono: true, hint: t.styleFieldHint },
                { kind: 'select', key: 'condition', label: t.cfg_style_op_label, tip: tip('style_operator'), options: optionsFor('styleOperators', ctx) },
                { kind: 'text', key: 'value', label: t.cfg_style_val_label, tip: tip('style_value'), placeholder: 'ex: Active', mono: true, hint: t.op_style_case_hint },
            ],
        },
        { columns: 1, nodes },
    ];
}

export function joinGuide(t: CrudConfigWords): FormNode {
    return {
        kind: 'guide',
        title: t.cfg_join_guide_title,
        open: true,
        intro: t.cfg_join_guide_intro,
        cards: [
            {
                title: t.cfg_join_guide_ex1_title,
                text: t.joinGuideEx1Hint,
                rows: [
                    [t.guideType, 'left'],
                    [t.joinGuideTable, 'suppliers'],
                    [t.joinGuideLeft, 'products.supplier_id'],
                    [t.joinGuideRight, 'suppliers.id'],
                    [t.joinGuideColumns, 'suppliers.name:supplier_name'],
                ],
                after: {
                    title: t.joinGuideInColumns,
                    rows: [
                        [t.cfg_term_phys_name, 'supplier_name'],
                        [t.cfg_term_sql_source, 'suppliers.name'],
                        [t.joinGuideSave, t.joinGuideOff],
                    ],
                },
            },
            {
                title: t.cfg_join_guide_ex2_title,
                text: t.joinGuideEx2Hint,
                items: [
                    `${t.cfg_join_guide_j1_title}: ${t.joinGuideTable} product_suppliers · ${t.joinGuideLeft} product_stocks.product_supplier_id · ${t.joinGuideRight} product_suppliers.id · ${t.joinGuideColumns} ${t.joinGuideLeaveEmpty}`,
                    `${t.cfg_join_guide_j2_title}: ${t.joinGuideTable} products · ${t.joinGuideLeft} product_suppliers.product_id · ${t.joinGuideRight} products.id · ${t.joinGuideColumns} products.name:product_name`,
                ],
                after: {
                    title: t.joinGuideInColumns,
                    rows: [
                        [t.cfg_term_phys_name, 'product_name'],
                        [t.cfg_term_sql_source, 'products.name'],
                        [t.joinGuideSave, t.joinGuideOff],
                    ],
                },
                foot: t.cfg_join_guide_chain_note,
            },
            { title: t.joinGuideRulesTitle, items: [t.cfg_join_guide_rule_phys, t.joinGuideRuleSql, t.joinGuideRuleSave, t.cfg_join_guide_rule_left] },
        ],
    };
}

export function joinBlocks(form: ConfigEntry, editing: number, ctx: BuildContext, serverError: string | null): FormBlock[] {
    const { t } = ctx;
    const tip = (key: string) => word(t, `cfg_tip_${key}`, '');
    const duplicate = joinDuplicate(form, ctx.document, editing);

    return [
        {
            nodes: [
                { kind: 'select', key: 'type', label: t.cfg_join_type_label, tip: tip('join_type'), options: optionsFor('joinTypes', ctx) },
                {
                    kind: 'text',
                    key: 'table',
                    label: `${t.cfg_join_table_label} ${t.joinTableSuffix}`,
                    tip: tip('join_table'),
                    placeholder: 'ex: suppliers',
                    mono: true,
                    hint: t.joinTableHint,
                    error: duplicate ? `${t.cfg_join_exist_err_prefix} ${text(form.table)} ${t.cfg_join_exist_err_suffix}` : serverError,
                },
                { kind: 'text', key: 'first', label: `${t.cfg_join_left_col} ${t.joinLeftSuffix}`, tip: tip('join_left_col'), placeholder: 'ex: products.supplier_id', mono: true, hint: t.joinLeftHint },
                { kind: 'text', key: 'second', label: `${t.cfg_join_right_col} ${t.joinRightSuffix}`, tip: tip('join_right_col'), placeholder: 'ex: suppliers.id', mono: true, hint: t.joinRightHint },
                { kind: 'check', key: 'distinct', label: t.cfg_join_distinct, tip: tip('join_distinct'), hint: t.cfg_join_distinct_hint, wide: true },
            ],
        },
        {
            columns: 1,
            nodes: [
                { kind: 'textarea', key: 'selectRaw', label: `${t.cfg_join_cols_label} ${t.joinColsSuffix}`, tip: tip('join_select_raw'), placeholder: 'suppliers.name:supplier_name\nsuppliers.phone:supplier_phone\nsuppliers.cnpj:supplier_cnpj', rows: 4, mono: true, hint: t.cfg_join_selectraw_hint },
                { kind: 'note', tone: 'warn', title: t.cfg_join_notice_title, items: [t.joinNoticeFor, t.cfg_join_notice_phys, t.cfg_join_notice_sql, t.joinNoticeSave], text: t.cfg_join_notice_footer },
            ],
        },
    ];
}

export function notificationBlocks(form: ConfigEntry, count: number | null, ctx: BuildContext): FormBlock[] {
    const { t } = ctx;
    const tip = (key: string) => word(t, `cfg_tip_${key}`, '');

    return [
        {
            nodes: [
                { kind: 'select', key: 'event', label: t.cfg_notif_event_label, tip: tip('notif_event'), options: optionsFor('notificationEvents', ctx) },
                { kind: 'select', key: 'type', label: t.cfg_notif_type_label, tip: tip('notif_type'), options: optionsFor('notificationTypes', ctx) },
                { kind: 'select', key: 'audience', label: t.cfg_notif_audience_label, tip: tip('notif_audience'), options: optionsFor('notificationAudiences', ctx) },
                {
                    kind: 'text',
                    key: 'audienceValue',
                    label: `${t.cfg_notif_audience_value_label} (${fill(t.cfg_notif_audience_count, { count: count ?? 0 })})`,
                    tip: tip('notif_audience_value'),
                    placeholder: t.cfg_notif_audience_value_placeholder,
                    disabled: text(form.audience || 'user') === 'staff',
                },
            ],
        },
        {
            columns: 1,
            nodes: [
                { kind: 'text', key: 'title', label: t.cfg_notif_title_label, tip: tip('notif_title'), maxLength: 180, wide: true },
                { kind: 'textarea', key: 'body', label: t.cfg_notif_body_label, tip: tip('notif_body'), rows: 3 },
                { kind: 'chips', label: t.cfg_notif_placeholders_hint, items: ctx.hints.placeholders.map((placeholder) => `%${placeholder}%`) },
            ],
        },
        {
            nodes: [
                { kind: 'text', key: 'url', label: t.cfg_notif_url_label, tip: tip('notif_url'), mono: true },
                { kind: 'text', key: 'actionLabel', label: t.cfg_notif_action_label_label, tip: tip('notif_action_label') },
                { kind: 'text', key: 'category', label: t.cfg_notif_category_label, tip: tip('notif_category') },
                { kind: 'text', key: 'icon', label: t.cfg_notif_icon_label, tip: tip('notif_icon') },
                { kind: 'check', key: 'notifySelf', label: t.cfg_notif_notify_self_label, tip: tip('notif_notify_self'), hint: t.cfg_notif_notify_self_hint, wide: true },
            ],
        },
    ];
}

// ── General, permissions, hooks (the document's settings) ───────────────────

export function generalBlocks(ctx: BuildContext): FormBlock[] {
    const { t } = ctx;
    const settings = ctx.document.settings;
    const tip = (key: string) => word(t, `cfg_tip_${key}`, '');
    const lists = fieldLists(ctx.document);
    const broadcast = ctx.options.broadcast;

    const broadcastNodes: FormNode[] = [{ kind: 'note', tone: 'muted', text: t.cfg_gen_broadcast_desc }];
    if (settings.broadcastEnabled) {
        const channel = settings.broadcastChannel || broadcast.defaultChannel;
        const event = settings.broadcastEvent || broadcast.defaultEvent;
        broadcastNodes.push(
            { kind: 'text', key: 'broadcastChannel', label: t.cfg_gen_channel, tip: tip('broadcast_channel'), placeholder: channel, mono: true, hint: `${t.emptyIs} ${channel}` },
            { kind: 'text', key: 'broadcastEvent', label: t.cfg_gen_event, tip: tip('gen_event'), placeholder: event, mono: true, hint: `${t.emptyIs} ${event}` },
            { kind: 'select', key: 'broadcastType', label: t.cfg_gen_broadcast_type, tip: t.cfg_gen_broadcast_type, options: optionsFor('broadcastTypes', ctx) },
            { kind: 'check', key: 'broadcastPerCompany', label: t.cfg_gen_broadcast_per_company, tip: t.cfg_gen_broadcast_per_company },
        );
        if (settings.broadcastType === 'public') {
            broadcastNodes.push({ kind: 'note', tone: 'warn', text: t.cfg_gen_broadcast_public_warn });
        }
        broadcastNodes.push({
            kind: 'code',
            lines: [
                { text: t.listenerComment, tone: 'muted' },
                { text: `"${broadcastKey(settings, ctx.options)}" => 'handleBaseCrudUpdate'`, tone: 'accent' },
                { text: `${t.observerHint} broadcast(new \\App\\Events\\${broadcast.base}Updated())->toOthers();`, tone: 'warn' },
            ],
        });
    } else {
        broadcastNodes.push({ kind: 'note', tone: 'muted', text: t.cfg_gen_broadcast_off_hint });
    }

    const features: FormNode[] = [
        { kind: 'check', key: 'featureImport', label: t.cfg_feature_import, tip: t.cfg_feature_import },
        { kind: 'check', key: 'featureFormDraft', label: t.cfg_feature_form_draft, tip: t.cfg_feature_form_draft },
        { kind: 'check', key: 'featureHistory', label: t.cfg_feature_history, tip: t.cfg_feature_history },
        { kind: 'check', key: 'featureAttachments', label: t.cfg_feature_attachments, tip: t.cfg_feature_attachments },
        { kind: 'note', tone: 'muted', text: t.cfg_feature_model_hint },
    ];
    if (settings.featureImport) {
        features.push({ kind: 'select', key: 'featureImportMode', label: t.cfg_feature_import_mode, tip: t.cfg_feature_import_mode, options: optionsFor('importModes', ctx) });
        if (settings.featureImportMode === 'upsert') {
            features.push({ kind: 'select', key: 'featureImportKey', label: t.cfg_feature_import_key, tip: t.cfg_feature_import_key, options: [{ value: '', label: '—' }, ...lists.all] });
        }
    }
    features.push(
        { kind: 'select', key: 'kanbanField', label: t.cfg_feature_kanban, tip: t.cfg_feature_kanban, options: [{ value: '', label: t.cfg_feature_off }, ...lists.selects] },
        { kind: 'select', key: 'calendarStart', label: t.cfg_feature_calendar_start, tip: t.cfg_feature_calendar_start, options: [{ value: '', label: t.cfg_feature_off }, ...lists.dates] },
        { kind: 'select', key: 'calendarEnd', label: t.cfg_feature_calendar_end, tip: t.cfg_feature_calendar_end, options: [{ value: '', label: '—' }, ...lists.dates] },
    );

    return [
        {
            title: t.cfg_gen_appearance,
            nodes: [
                { kind: 'text', key: 'displayName', label: t.cfg_gen_display_name, tip: tip('gen_display_name'), placeholder: t.cfg_gen_display_name_ph, hint: t.cfg_gen_display_name_hint, wide: true },
                { kind: 'text', key: 'displayNameSingular', label: t.cfg_gen_display_name_singular, tip: tip('gen_display_name_singular'), placeholder: t.cfg_gen_display_name_singular_ph },
                { kind: 'select', key: 'displayNameGender', label: t.cfg_gen_display_name_gender, tip: tip('gen_display_name_gender'), options: optionsFor('genders', ctx) },
                { kind: 'text', key: 'configLinkLinha', label: t.cfg_gen_link_linha, tip: tip('gen_link_linha'), placeholder: '/rota/%id%', mono: true },
                { kind: 'text', key: 'tableClass', label: t.cfg_gen_table_class, tip: tip('gen_table_class'), placeholder: 'table table-hover', mono: true },
                { kind: 'text', key: 'theadClass', label: t.cfg_gen_thead_class, tip: tip('gen_thead_class'), mono: true },
                { kind: 'check', key: 'uiCompactMode', label: t.cfg_gen_compact, tip: tip('gen_compact') },
                { kind: 'check', key: 'uiStickyHeader', label: t.cfg_gen_sticky, tip: tip('gen_sticky') },
                { kind: 'check', key: 'uiCopyCell', label: t.cfg_gen_copy_cell, tip: tip('gen_copy_cell') },
            ],
        },
        {
            title: `${t.cfg_gen_detail_title} & ${t.cfg_gen_group_break}`,
            nodes: [
                { kind: 'text', key: 'groupBreak', label: t.cfg_gen_group_break, tip: tip('gen_group_break'), placeholder: 'ex: status', mono: true, hint: t.cfg_gen_group_break_hint },
                { kind: 'select', key: 'groupBreakPeriod', label: t.cfg_gen_group_break_period, tip: tip('gen_group_break_period'), options: optionsFor('groupBreakPeriods', ctx) },
                { kind: 'text', key: 'detailModel', label: t.cfg_gen_detail_model, tip: tip('gen_detail_model'), placeholder: 'ex: OrderItem', mono: true, hint: t.cfg_gen_detail_hint, wide: true },
                { kind: 'text', key: 'detailForeignKey', label: t.cfg_gen_detail_fk, tip: tip('gen_detail_fk'), placeholder: 'ex: order_id', mono: true },
                { kind: 'text', key: 'detailTitle', label: t.cfg_gen_detail_label, tip: tip('gen_detail_label'), placeholder: 'ex: Itens do pedido' },
            ],
        },
        {
            title: t.cfg_gen_cache,
            toggle: { key: 'cacheEnabled', label: t.cfg_gen_cache_enabled, tip: tip('gen_cache_enabled') },
            nodes: settings.cacheEnabled ? [{ kind: 'number', key: 'cacheTtl', label: t.cfg_gen_ttl, tip: tip('gen_ttl'), min: 0, int: true, hint: t.ttlHint }] : [],
        },
        {
            title: t.cfg_gen_export,
            columns: 3,
            nodes: [
                { kind: 'number', key: 'exportAsyncThreshold', label: t.cfg_gen_export_async, tip: tip('gen_export_async'), min: 1, int: true },
                { kind: 'number', key: 'exportMaxRows', label: t.cfg_gen_export_max, tip: tip('gen_export_max'), min: 1, int: true },
                { kind: 'select', key: 'exportOrientation', label: t.cfg_gen_export_orientation, tip: tip('gen_export_orientation'), options: optionsFor('exportOrientations', ctx) },
            ],
        },
        { title: t.cfg_gen_broadcast, toggle: { key: 'broadcastEnabled', label: t.cfg_gen_broadcast_enabled, tip: tip('gen_broadcast_enabled') }, nodes: broadcastNodes },
        { title: t.cfg_features, columns: 3, nodes: features },
    ];
}

export function permissionBlocks(ctx: BuildContext): FormBlock[] {
    const { t } = ctx;
    const gates: [keyof ConfigSettings, string, string][] = [
        ['permissionCreate', t.cfg_perm_create, 'cfg_tip_perm_create'],
        ['permissionEdit', t.cfg_perm_edit, 'cfg_tip_perm_edit'],
        ['permissionDelete', t.cfg_perm_delete, 'cfg_tip_perm_delete'],
        ['permissionExport', t.cfg_perm_export, 'cfg_tip_perm_export'],
        ['permissionRestore', t.cfg_perm_restore, 'cfg_tip_perm_restore'],
    ];
    const buttons: [keyof ConfigSettings, string, string][] = [
        ['showCreateButton', t.cfg_perm_btn_create, 'cfg_tip_perm_btn_create'],
        ['showEditButton', t.cfg_perm_btn_edit, 'cfg_tip_perm_btn_edit'],
        ['showDeleteButton', t.cfg_perm_btn_delete, 'cfg_tip_perm_btn_delete'],
        ['showTrashButton', t.cfg_perm_btn_trash, 'cfg_tip_perm_btn_trash'],
    ];

    return [
        {
            title: t.cfg_perm_gates_title,
            nodes: [
                ...gates.map(([key, label, tipKey]): FormNode => ({ kind: 'text', key, label: `${t.gate} ${label}`, tip: word(t, tipKey, ''), placeholder: fill(t.gatePlaceholder, { name: label.toLowerCase() }), mono: true })),
                { kind: 'text', key: 'permissionIdentifier', label: t.cfg_perm_identifier, tip: t.cfg_tip_perm_identifier, placeholder: 'pageMinhaRotina', mono: true },
            ],
        },
        { title: t.cfg_perm_visibility_title, nodes: buttons.map(([key, label, tipKey]): FormNode => ({ kind: 'check', key, label, tip: word(t, tipKey, '') })) },
    ];
}

export function hookBlocks(ctx: BuildContext): FormBlock[] {
    const { t } = ctx;
    const inline = (example: string, cls: string) => `${t.cfg_hooks_example_syntax}\n\n// ${t.cfg_hooks_example_inline}\n${example}\n\n// ${t.cfg_hooks_example_class}\n${cls}\n@App\\CrudHooks\\ProductHooks`;
    const classOnly = (method: string, desc: string) => `${t.cfg_hooks_example_class}\n@ProductHooks::${method}\n@App\\CrudHooks\\ProductHooks@${method}\n\n// ${desc}`;

    return [
        {
            columns: 1,
            nodes: [
                { kind: 'note', tone: 'info', title: t.cfg_hooks_info_title, text: t.cfg_hooks_info_desc },
                { kind: 'textarea', key: 'hookBeforeCreate', label: t.cfg_hooks_before_create, badge: 'function(&$data)', hint: t.cfg_hooks_before_create_desc, tip: t.cfg_tip_hooks_before_create, rows: 6, mono: true, placeholder: inline("merge(data, {'status': 'pending', 'uuid': uuid()})", '@ProductHooks::beforeCreate') },
                { kind: 'textarea', key: 'hookAfterCreate', label: t.cfg_hooks_after_create, badge: 'function($record)', hint: t.cfg_hooks_after_create_desc, tip: t.cfg_tip_hooks_after_create, rows: 6, mono: true, placeholder: classOnly('afterCreate', t.cfg_hooks_after_create_desc) },
                { kind: 'textarea', key: 'hookBeforeUpdate', label: t.cfg_hooks_before_update, badge: 'function(&$data, $record)', hint: t.cfg_hooks_before_update_desc, tip: t.cfg_tip_hooks_before_update, rows: 6, mono: true, placeholder: inline("merge(data, {'published_at': now()})", '@ProductHooks::beforeUpdate') },
                { kind: 'textarea', key: 'hookAfterUpdate', label: t.cfg_hooks_after_update, badge: 'function($record)', hint: t.cfg_hooks_after_update_desc, tip: t.cfg_tip_hooks_after_update, rows: 6, mono: true, placeholder: classOnly('afterUpdate', t.cfg_hooks_after_update_desc) },
                { kind: 'textarea', key: 'hookBeforeDelete', label: t.cfg_hooks_before_delete, badge: 'function($record)', hint: t.cfg_hooks_before_delete_desc, tip: t.cfg_tip_hooks_before_delete, rows: 4, mono: true, placeholder: `${t.cfg_hooks_example_class}\n@BrandHooks::beforeDelete` },
                { kind: 'textarea', key: 'hookAfterDelete', label: t.cfg_hooks_after_delete, badge: 'function($record)', hint: t.cfg_hooks_after_delete_desc, tip: t.cfg_tip_hooks_after_delete, rows: 4, mono: true, placeholder: `${t.cfg_hooks_example_class}\n@BrandHooks::afterDelete` },
                { kind: 'note', tone: 'warn', title: t.cfg_hooks_warning_title, text: t.cfg_hooks_warning_desc },
            ],
        },
    ];
}

/** The tabs of the editor, with the Blade's counts. */
export function mainTabs(document: ConfigDocument, t: CrudConfigWords): { id: string; label: string; count: number | null; title: string; description: string }[] {
    const columns = dataColumns(document).length;
    const actions = actionColumns(document).length;

    return [
        { id: 'cols', label: t.cfg_nav_cols, count: columns, title: t.cfg_tab_title_cols, description: t.cfg_tab_desc_cols },
        { id: 'actions', label: t.cfg_nav_actions, count: actions, title: t.cfg_tab_title_actions, description: t.cfg_tab_desc_actions },
        { id: 'filters', label: t.cfg_nav_filters, count: document.customFilters.length, title: t.cfg_tab_title_filters, description: t.cfg_tab_desc_filters },
        { id: 'styles', label: t.cfg_nav_styles, count: document.conditionStyles.length, title: t.cfg_tab_title_styles, description: t.cfg_tab_desc_styles },
        { id: 'joins', label: t.cfg_nav_joins, count: document.joins.length, title: t.cfg_tab_title_joins, description: t.cfg_tab_desc_joins },
        { id: 'general', label: t.cfg_nav_general, count: null, title: t.cfg_tab_title_general, description: t.cfg_tab_desc_general },
        { id: 'permissions', label: t.cfg_nav_permissions, count: null, title: t.cfg_tab_title_permissions, description: t.cfg_tab_desc_permissions },
        { id: 'hooks', label: t.cfg_nav_hooks, count: null, title: t.cfg_tab_title_hooks, description: t.cfg_tab_desc_hooks },
        { id: 'notifications', label: t.cfg_nav_notifications, count: document.notificationRules.length, title: t.cfg_tab_title_notifications, description: t.cfg_tab_desc_notifications },
    ];
}

/** The footer's "N columns · N filters · N styles". */
export const footerSummary = (document: ConfigDocument, t: CrudConfigWords): string =>
    `${document.columns.length} ${t.cfg_footer_unit_cols} · ${document.customFilters.length} ${t.cfg_footer_unit_filters} · ${document.conditionStyles.length} ${t.cfg_footer_unit_styles}`;
