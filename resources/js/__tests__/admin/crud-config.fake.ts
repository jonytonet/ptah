import { PtahApi } from '../../core/api';
import type { ConfigDocument, ConfigEntry } from '../../core/admin/crud-config';

export interface ConfigCall {
    method: string;
    path: string;
    body: Record<string, unknown> | null;
}

const CHOICES = {
    fieldTypes: ['text', 'number', 'date', 'datetime', 'select', 'searchdropdown', 'boolean', 'textarea'],
    aligns: ['text-start', 'text-center', 'text-end'],
    renderers: ['', 'badge', 'pill', 'boolean', 'money', 'date', 'datetime', 'link', 'image', 'truncate', 'number', 'filesize', 'duration', 'code', 'color', 'progress', 'rating', 'qrcode'],
    badgeColors: ['green', 'yellow', 'red', 'blue', 'indigo', 'purple', 'pink', 'gray'],
    currencies: ['BRL', 'USD', 'EUR'],
    progressColors: ['indigo', 'green', 'blue', 'yellow', 'red', 'purple'],
    durationUnits: ['minutes', 'seconds'],
    masks: ['', 'money_brl', 'money_usd', 'percent', 'cpf', 'cnpj', 'rg', 'pis', 'ncm', 'ean13', 'phone', 'cep', 'plate', 'credit_card', 'date', 'datetime', 'time', 'integer', 'uppercase'],
    maskTransforms: ['', 'money_to_float', 'digits_only', 'plate_clean', 'date_br_to_iso', 'date_iso_to_br', 'uppercase', 'lowercase', 'trim'],
    validationRules: ['email', 'url', 'integer', 'numeric', 'cpf', 'cnpj', 'phone', 'alpha', 'alphanum', 'ncm'],
    validationParams: ['min', 'max', 'minLength', 'maxLength', 'regex', 'digits', 'digitsBetween', 'after', 'before', 'dateFormat', 'confirmed', 'unique', 'in', 'notIn'],
    sdModes: ['model', 'service'],
    sdStartList: ['bottom', 'top'],
    sdMasks: ['defaultMask', 'cnpj', 'cpf', 'money', 'phone', 'date'],
    totalizerFunctions: ['sum', 'avg', 'count', 'min', 'max'],
    actionTypes: ['link', 'livewire', 'javascript'],
    actionColors: ['primary', 'success', 'danger', 'warning', 'info', 'secondary'],
    filterTypes: ['text', 'number', 'date', 'select'],
    filterOperators: ['=', 'LIKE', '>', '<', '>=', '<='],
    aggregates: ['', 'SUM', 'COUNT', 'AVG', 'MAX', 'MIN'],
    styleOperators: ['==', '!=', '>', '<', '>=', '<='],
    joinTypes: ['left', 'inner'],
    genders: ['m', 'f'],
    groupBreakPeriods: ['', 'day', 'month', 'year'],
    exportOrientations: ['landscape', 'portrait'],
    broadcastTypes: ['private', 'presence', 'public'],
    importModes: ['create', 'upsert'],
    notificationEvents: ['created', 'updated', 'deleted'],
    notificationTypes: ['info', 'success', 'warning', 'danger'],
    notificationAudiences: ['user', 'role', 'staff'],
};

const settings = (): ConfigDocument['settings'] => ({
    displayName: 'Itens',
    displayNameSingular: '',
    displayNameGender: 'm',
    configLinkLinha: '',
    tableClass: '',
    theadClass: '',
    uiCompactMode: false,
    uiStickyHeader: true,
    uiCopyCell: true,
    groupBy: '',
    groupBreak: '',
    groupBreakPeriod: '',
    detailModel: '',
    detailForeignKey: '',
    detailTitle: '',
    cacheEnabled: true,
    cacheTtl: 300,
    exportAsyncThreshold: 1000,
    exportMaxRows: 10000,
    exportOrientation: 'landscape',
    broadcastEnabled: false,
    broadcastChannel: '',
    broadcastEvent: '',
    broadcastType: 'private',
    broadcastPerCompany: false,
    featureImport: false,
    featureImportMode: 'create',
    featureImportKey: '',
    featureFormDraft: false,
    featureHistory: true,
    featureAttachments: true,
    kanbanField: '',
    calendarStart: '',
    calendarEnd: '',
    permissionCreate: '',
    permissionEdit: '',
    permissionDelete: '',
    permissionExport: '',
    permissionRestore: '',
    permissionIdentifier: 'pageItem',
    showCreateButton: true,
    showEditButton: true,
    showDeleteButton: true,
    showTrashButton: true,
    hookBeforeCreate: '',
    hookAfterCreate: '',
    hookBeforeUpdate: '',
    hookAfterUpdate: '',
    hookBeforeDelete: '',
    hookAfterDelete: '',
});

/**
 * A stand-in for the crud-config API: the routes and shapes of
 * CrudConfigApiController, with the component's rules in small (addField's
 * defaults, addJoin's duplicate guard, the trimming of a notification rule).
 * `forbidden` answers 403; a column typed `bogus` is refused on save as the
 * schema validator refuses it.
 */
export function fakeCrudConfig(options: { forbidden?: boolean; empty?: boolean; notifications?: boolean } = {}) {
    const calls: ConfigCall[] = [];
    let saved: ConfigDocument = {
        columns: options.empty
            ? []
            : [
                  { colsNomeFisico: 'name', colsNomeLogico: 'Nome', colsTipo: 'text', colsGravar: true, colsRequired: true, colsIsFilterable: true },
                  { colsNomeFisico: 'status', colsNomeLogico: 'Situação', colsTipo: 'select', colsGravar: true, colsSelect: 'Ativo;active;;Arquivado;archived' },
                  { colsNomeFisico: 'amount', colsNomeLogico: 'Valor', colsTipo: 'number', colsGravar: true, colsRenderer: 'money', totalizadorEnabled: true, totalizadorType: 'sum' },
                  { colsNomeFisico: 'id', colsNomeLogico: 'Ver', colsTipo: 'action', actionType: 'link', actionValue: '/items/%id%', actionIcon: 'bx bx-show', colsGravar: false },
              ],
        customFilters: [],
        conditionStyles: [],
        joins: [],
        notificationRules: [],
        settings: settings(),
    };

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    const hints = (document: ConfigDocument) => ({ placeholders: [...document.columns.filter((column) => column.colsTipo !== 'action').map((column) => String(column.colsNomeFisico)), 'id'], traitMissing: false });
    const str = (value: unknown) => (value === undefined || value === null ? '' : String(value));

    const act = (action: string, body: Record<string, unknown>) => {
        const document = structuredClone(body.document as ConfigDocument);
        let form = (body.form ?? {}) as ConfigEntry;
        let editing = typeof body.editing === 'number' ? body.editing : -1;
        const index = Number(body.index ?? -1);
        const before = JSON.stringify([document, form, editing]);
        const splice = (list: ConfigEntry[]) => list.splice(index, 1);

        switch (action) {
            case 'addField':
                if (str(form.colsNomeFisico) !== '') {
                    const name = str(form.colsNomeFisico);
                    document.columns.push({ colsTipo: 'text', colsGravar: true, colsRequired: false, colsAlign: 'text-start', colsIsFilterable: true, colsNomeLogico: name.charAt(0).toUpperCase() + name.slice(1), ...form });
                    form = {};
                }
                break;
            case 'editField':
                editing = index;
                form = { ...document.columns[index] };
                break;
            case 'updateField':
                if (editing >= 0) {
                    document.columns[editing] = form;
                    form = {};
                    editing = -1;
                }
                break;
            case 'cancelEditField':
            case 'cancelEditAction':
            case 'cancelEditJoin':
            case 'cancelEditNotificationRule':
                form = {};
                editing = -1;
                break;
            case 'removeField':
                splice(document.columns);
                break;
            case 'reorderFields': {
                const order = (body.order as number[]) ?? [];
                if (order.length === document.columns.length) {
                    document.columns = order.map((at) => document.columns[at]);
                }
                break;
            }
            case 'addAction':
                if (str(form.colsNomeLogico) !== '') {
                    const data = Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '' && value !== null));
                    const entry = { actionType: 'link', actionValue: '', actionIcon: 'bx bx-link', actionColor: 'primary', actionPermission: '', ...data, colsNomeFisico: 'id', colsTipo: 'action', colsGravar: false, colsRequired: false, colsIsFilterable: false };
                    if (editing >= 0) {
                        document.columns[editing] = entry;
                    } else {
                        document.columns.push(entry);
                    }
                    form = {};
                    editing = -1;
                }
                break;
            case 'editAction':
                editing = index;
                form = { ...document.columns[index] };
                break;
            case 'removeAction':
                splice(document.columns);
                break;
            case 'addJoin': {
                const table = str(form.table).trim();
                if (table === '') {
                    break;
                }
                if (editing < 0 && document.joins.some((join) => join.table === table)) {
                    return json(422, { message: `A JOIN for table '${table}' already exists.`, errors: { table: [`A JOIN for table '${table}' already exists.`] } });
                }
                const select = str(form.selectRaw)
                    .split('\n')
                    .map((line) => line.trim())
                    .filter(Boolean)
                    .map((line) => (line.includes(':') ? { column: line.split(':')[0], alias: line.split(':')[1] } : { column: line, alias: line.replace('.', '_') }));
                const entry = { type: str(form.type) || 'left', table, first: str(form.first), second: str(form.second), distinct: form.distinct === true, select };
                if (editing >= 0) {
                    document.joins[editing] = entry;
                } else {
                    document.joins.push(entry);
                }
                form = {};
                editing = -1;
                break;
            }
            case 'editJoin': {
                const join = document.joins[index];
                editing = index;
                form = { type: join.type, table: join.table, first: join.first, second: join.second, distinct: join.distinct, selectRaw: (join.select as { column: string; alias: string }[]).map((entry) => `${entry.column}:${entry.alias}`).join('\n') };
                break;
            }
            case 'removeJoin':
                splice(document.joins);
                break;
            case 'addNotificationRule': {
                const event = str(form.event).trim();
                const title = str(form.title).trim();
                if (event && title) {
                    const entry = { event, audience: str(form.audience) || 'user', audienceValue: str(form.audienceValue).trim(), title, body: str(form.body).trim(), url: '', type: str(form.type) || 'info', category: '', icon: '', actionLabel: '', notifySelf: form.notifySelf === true };
                    if (editing >= 0) {
                        document.notificationRules[editing] = entry;
                    } else {
                        document.notificationRules.push(entry);
                    }
                    form = {};
                    editing = -1;
                }
                break;
            }
            case 'editNotificationRule':
                editing = index;
                form = { ...document.notificationRules[index] };
                break;
            case 'removeNotificationRule':
                splice(document.notificationRules);
                break;
            case 'addCustomFilter':
                if (str(form.field) !== '') {
                    document.customFilters.push(form);
                    form = {};
                }
                break;
            case 'removeCustomFilter':
                splice(document.customFilters);
                break;
            case 'addConditionStyle':
                if (str(form.field) !== '') {
                    document.conditionStyles.push(form);
                    form = {};
                }
                break;
            case 'removeConditionStyle':
                splice(document.conditionStyles);
                break;
            default:
                return json(404, { message: 'Not found.' });
        }

        return json(200, { document, form, editing, applied: before !== JSON.stringify([document, form, editing]), hints: hints(document) });
    };

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(String(input), 'http://localhost');
        const path = url.pathname.replace('/ptah/api', '');
        const method = (init.method ?? 'GET').toUpperCase();
        const body = typeof init.body === 'string' ? (JSON.parse(init.body) as Record<string, unknown>) : null;
        calls.push({ method, path, body });

        if (path === '/crud/items/config/can') {
            return json(200, { can: !options.forbidden });
        }
        if (options.forbidden) {
            return json(403, { message: 'Forbidden.' });
        }
        if (path === '/crud/items/config' && method === 'GET') {
            return json(200, {
                can: true,
                screen: 'items',
                model: 'App\\Models\\Item',
                route: 'items',
                document: structuredClone(saved),
                choices: CHOICES,
                options: {
                    permissionsModule: true,
                    permissionKeys: [{ value: 'items.amount', label: 'items.amount — Valor (items)' }],
                    notificationsEnabled: options.notifications ?? true,
                    broadcast: { base: 'Item', defaultChannel: 'page-item-observer', defaultEvent: '.pageItemObserver', companyId: 1, prefixes: { public: 'echo', private: 'echo-private', presence: 'echo-presence' } },
                },
                hints: hints(saved),
            });
        }
        if (path === '/crud/items/config' && method === 'PUT') {
            const document = (body?.document ?? null) as ConfigDocument | null;
            if (!document) {
                return json(422, { message: 'The document field is required.', errors: { document: ['The document field is required.'] } });
            }
            const bad = document.columns.findIndex((column) => column.colsTipo === 'bogus');
            if (bad >= 0) {
                const message = `Invalid column type "bogus" for field "${str(document.columns[bad].colsNomeFisico)}".`;

                return json(422, { message, errors: { config: [message] }, path: `$.cols[${bad}].colsTipo` });
            }
            saved = structuredClone(document);

            return json(200, { ok: true, message: 'Configuração salva com sucesso!', event: 'ptah:crud-config-updated', document: saved, hints: hints(saved) });
        }
        if (path === '/crud/items/config/preview' && method === 'POST') {
            const document = body?.document as ConfigDocument;
            const fields = document.columns
                .filter((column) => column.colsTipo !== 'action' && column.colsGravar === true)
                .map((column) => ({
                    field: str(column.colsNomeFisico),
                    label: str(column.colsNomeLogico) || str(column.colsNomeFisico),
                    type: str(column.colsTipo) || 'text',
                    required: column.colsRequired === true,
                    help: null,
                    block: str(column.colsFormBlock),
                    computed: false,
                    mask: str(column.colsMask),
                    options: str(column.colsSelect)
                        .split(';;')
                        .filter(Boolean)
                        .map((pair) => ({ label: pair.split(';')[0], value: pair.split(';')[1] ?? '' })),
                    dependsOn: null,
                    dependsOnLabel: null,
                    placeholder: null,
                    lazy: false,
                }));

            return json(200, { title: document.settings.displayName, fields });
        }
        if (path === '/crud/items/config/audience-count' && method === 'POST') {
            const form = (body?.form ?? {}) as ConfigEntry;
            const count = form.audience === 'staff' ? 7 : form.audience === 'user' && str(form.audienceValue) !== '' ? 1 : 0;

            return json(200, { count });
        }
        const action = path.match(/^\/crud\/items\/config\/actions\/(\w+)$/);
        if (action && method === 'POST') {
            return act(action[1], body ?? {});
        }

        return json(404, { message: `No fake for ${method} ${path}` });
    };

    return { api: new PtahApi({ fetch: handler as typeof fetch }), calls, saved: () => saved };
}
