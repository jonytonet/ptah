/**
 * The BaseCrud config editor over the headless API — types and the client.
 * The rules (who may configure, the defaults and JOIN rules of a new column,
 * the duplicate-JOIN guard, the merge with the stored config, the schema
 * validation) are the server's: the Livewire component CrudConfig, run by
 * CrudConfigApiController. The client holds the document being edited and
 * sends it with every action, as the Livewire editor holds it between clicks.
 */
import type { PtahApi } from '../../api';

/** One entry of a list of the config: a column, a filter, a style, a join, a rule. */
export type ConfigEntry = Record<string, unknown>;

export interface ConfigSettings {
    displayName: string;
    displayNameSingular: string;
    displayNameGender: string;
    configLinkLinha: string;
    tableClass: string;
    theadClass: string;
    uiCompactMode: boolean;
    uiStickyHeader: boolean;
    uiCopyCell: boolean;
    groupBy: string;
    groupBreak: string;
    groupBreakPeriod: string;
    detailModel: string;
    detailForeignKey: string;
    detailTitle: string;
    cacheEnabled: boolean;
    cacheTtl: number;
    exportAsyncThreshold: number;
    exportMaxRows: number;
    exportOrientation: string;
    broadcastEnabled: boolean;
    broadcastChannel: string;
    broadcastEvent: string;
    broadcastType: string;
    broadcastPerCompany: boolean;
    featureImport: boolean;
    featureImportMode: string;
    featureImportKey: string;
    featureFormDraft: boolean;
    featureHistory: boolean;
    featureAttachments: boolean;
    kanbanField: string;
    calendarStart: string;
    calendarEnd: string;
    permissionCreate: string;
    permissionEdit: string;
    permissionDelete: string;
    permissionExport: string;
    permissionRestore: string;
    permissionIdentifier: string;
    showCreateButton: boolean;
    showEditButton: boolean;
    showDeleteButton: boolean;
    showTrashButton: boolean;
    hookBeforeCreate: string;
    hookAfterCreate: string;
    hookBeforeUpdate: string;
    hookAfterUpdate: string;
    hookBeforeDelete: string;
    hookAfterDelete: string;
}

/** The editor's state: the config's lists and settings, as CrudConfig holds them. */
export interface ConfigDocument {
    /** Columns AND row actions (`colsTipo: 'action'`), in order — `formEditFields`. */
    columns: ConfigEntry[];
    customFilters: ConfigEntry[];
    conditionStyles: ConfigEntry[];
    joins: ConfigEntry[];
    notificationRules: ConfigEntry[];
    settings: ConfigSettings;
}

export interface ConfigChoices {
    fieldTypes: string[];
    aligns: string[];
    renderers: string[];
    badgeColors: string[];
    currencies: string[];
    progressColors: string[];
    durationUnits: string[];
    masks: string[];
    maskTransforms: string[];
    validationRules: string[];
    validationParams: string[];
    sdModes: string[];
    sdStartList: string[];
    sdMasks: string[];
    totalizerFunctions: string[];
    actionTypes: string[];
    actionColors: string[];
    filterTypes: string[];
    filterOperators: string[];
    aggregates: string[];
    styleOperators: string[];
    joinTypes: string[];
    genders: string[];
    groupBreakPeriods: string[];
    exportOrientations: string[];
    broadcastTypes: string[];
    importModes: string[];
    notificationEvents: string[];
    notificationTypes: string[];
    notificationAudiences: string[];
}

export interface ConfigOptions {
    /** The column permission select exists only with the permissions module. */
    permissionsModule: boolean;
    permissionKeys: { value: string; label: string }[];
    /** Off: the Notifications tab explains how to install the module. */
    notificationsEnabled: boolean;
    broadcast: { base: string; defaultChannel: string; defaultEvent: string; companyId: number; prefixes: Record<string, string> };
}

/** What the Notifications tab derives from the columns (server-side rules). */
export interface ConfigHints {
    placeholders: string[];
    traitMissing: boolean;
}

export interface ConfigPayload {
    can: boolean;
    screen: string;
    model: string;
    /** The screen route the config is saved under; '' = the global config. */
    route: string;
    document: ConfigDocument;
    choices: ConfigChoices;
    options: ConfigOptions;
    hints: ConfigHints;
}

/** The sub-forms of the editor. */
export type ConfigSection = 'field' | 'action' | 'join' | 'notification' | 'filter' | 'style';

/** The component's actions — one per button of the editor. */
export type ConfigAction =
    | 'addField'
    | 'editField'
    | 'updateField'
    | 'cancelEditField'
    | 'removeField'
    | 'moveFieldUp'
    | 'moveFieldDown'
    | 'reorderFields'
    | 'addAction'
    | 'editAction'
    | 'cancelEditAction'
    | 'removeAction'
    | 'addJoin'
    | 'editJoin'
    | 'cancelEditJoin'
    | 'removeJoin'
    | 'addNotificationRule'
    | 'editNotificationRule'
    | 'cancelEditNotificationRule'
    | 'removeNotificationRule'
    | 'addCustomFilter'
    | 'removeCustomFilter'
    | 'addConditionStyle'
    | 'removeConditionStyle';

export const ACTION_SECTION: Record<ConfigAction, ConfigSection> = {
    addField: 'field',
    editField: 'field',
    updateField: 'field',
    cancelEditField: 'field',
    removeField: 'field',
    moveFieldUp: 'field',
    moveFieldDown: 'field',
    reorderFields: 'field',
    addAction: 'action',
    editAction: 'action',
    cancelEditAction: 'action',
    removeAction: 'action',
    addJoin: 'join',
    editJoin: 'join',
    cancelEditJoin: 'join',
    removeJoin: 'join',
    addNotificationRule: 'notification',
    editNotificationRule: 'notification',
    cancelEditNotificationRule: 'notification',
    removeNotificationRule: 'notification',
    addCustomFilter: 'filter',
    removeCustomFilter: 'filter',
    addConditionStyle: 'style',
    removeConditionStyle: 'style',
};

export interface ActionPayload {
    document: ConfigDocument;
    form?: ConfigEntry;
    editing?: number;
    index?: number;
    order?: number[];
}

/** A 422 answers `message` + `errors` (field → messages) instead. */
export interface ActionResult {
    document?: ConfigDocument;
    form?: ConfigEntry;
    editing?: number;
    /** False when the component did nothing (an incomplete form). */
    applied?: boolean;
    hints?: ConfigHints;
    message?: string;
    errors?: Record<string, string[]>;
}

export interface SaveResult {
    ok?: boolean;
    message?: string | null;
    event?: string;
    document?: ConfigDocument;
    hints?: ConfigHints;
    errors?: Record<string, string[]>;
    /** The JSON path a schema refusal points at. */
    path?: string | null;
}

export interface PreviewField {
    field: string;
    label: string;
    type: string;
    required: boolean;
    help: string | null;
    block: string;
    computed: boolean;
    mask: string;
    options: { label: string; value: string }[];
    dependsOn: string | null;
    dependsOnLabel: string | null;
    placeholder: string | null;
    lazy: boolean;
}

export interface PreviewResult {
    title: string;
    fields: PreviewField[];
}

const base = (screen: string) => `/crud/${encodeURIComponent(screen)}/config`;

/** Whether this user gets the gear button on the screen. Never throws. */
export async function canConfigure(api: PtahApi, screen: string): Promise<boolean> {
    try {
        const answer = await api.call<{ can?: boolean }>('GET', `${base(screen)}/can`);

        return answer?.can === true;
    } catch {
        return false;
    }
}

export function fetchConfig(api: PtahApi, screen: string): Promise<ConfigPayload> {
    return api.call('GET', base(screen));
}

export function runConfigAction(api: PtahApi, screen: string, action: ConfigAction, payload: ActionPayload): Promise<ActionResult> {
    return api.call('POST', `${base(screen)}/actions/${action}`, payload);
}

export function saveConfig(api: PtahApi, screen: string, document: ConfigDocument): Promise<SaveResult> {
    return api.call('PUT', base(screen), { document });
}

export function previewConfig(api: PtahApi, screen: string, document: ConfigDocument): Promise<PreviewResult> {
    return api.call('POST', `${base(screen)}/preview`, { document });
}

export async function audienceCount(api: PtahApi, screen: string, form: ConfigEntry): Promise<number> {
    const answer = await api.call<{ count?: number }>('POST', `${base(screen)}/audience-count`, { form });

    return Number(answer?.count ?? 0);
}

/** The first message of each field of a 422. */
export function firstErrors(errors: Record<string, string[] | string> | undefined): Record<string, string> {
    const out: Record<string, string> = {};
    for (const [key, value] of Object.entries(errors ?? {})) {
        out[key] = Array.isArray(value) ? String(value[0] ?? '') : String(value);
    }

    return out;
}
