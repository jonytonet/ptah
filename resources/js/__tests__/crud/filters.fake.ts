import { CrudController } from '../../core/controller';
import { PtahApi } from '../../core/api';
import { NO_FEATURES } from '../../core/types';
import type { Listing, Preferences, Schema } from '../../core/types';
import type { FilterLabel, FiltersMeta } from '../../core/crud/filters';

export interface FakeCall {
    method: string;
    path: string;
    query: URLSearchParams;
    body: Record<string, unknown> | null;
}

const items = [
    { id: 1, name: 'Alfa', status: 'active', category_id: 1 },
    { id: 2, name: 'Beta', status: 'archived', category_id: 2 },
    { id: 3, name: 'Gama', status: 'active', category_id: 1 },
];

export const filtersMeta: FiltersMeta = {
    filters: [
        { field: 'name', label: 'Nome', type: 'text', kind: 'text', custom: false, operators: ['LIKE', '=', '!=', 'LIKE_START', 'LIKE_END', 'IS NULL', 'IS NOT NULL'], date_operators: null, options: null, depends_on: null, placeholder: null, start_list: null },
        { field: 'amount', label: 'Valor', type: 'number', kind: 'number', custom: false, operators: ['=', '!=', '>', '>=', '<', '<=', 'IS NULL', 'IS NOT NULL'], date_operators: null, options: null, depends_on: null, placeholder: null, start_list: null },
        {
            field: 'status',
            label: 'Situação',
            type: 'select',
            kind: 'select',
            custom: false,
            operators: [],
            date_operators: null,
            options: [
                { label: 'Ativo', value: 'active' },
                { label: 'Arquivado', value: 'archived' },
            ],
            depends_on: null,
            placeholder: null,
            start_list: null,
        },
        { field: 'category_id', label: 'Categoria', type: 'searchdropdown', kind: 'searchdropdown', custom: false, operators: ['=', '!=', 'IS NULL', 'IS NOT NULL'], date_operators: null, options: null, depends_on: null, placeholder: null, start_list: 'bottom' },
        { field: 'owner_id', label: 'Dono', type: 'searchdropdown', kind: 'searchdropdown', custom: false, operators: ['=', '!=', 'IS NULL', 'IS NOT NULL'], date_operators: null, options: null, depends_on: 'category_id', placeholder: 'Escolha o dono', start_list: 'top' },
        { field: 'created_at', label: 'Criado em', type: 'date', kind: 'date', custom: false, operators: [], date_operators: { start: ['>=', '>', '='], end: ['<=', '<', '='] }, options: null, depends_on: null, placeholder: null, start_list: null },
        { field: 'region', label: 'Região', type: 'text', kind: 'text', custom: true, operators: [], date_operators: null, options: null, depends_on: null, placeholder: null, start_list: null },
    ],
    sentinels: { empty: '__ptah_empty__', filled: '__ptah_filled__' },
    quick_dates: { enabled: true, column: 'created_at', periods: ['today', 'yesterday', 'last7', 'last30', 'week', 'month', 'lastMonth', 'quarter', 'year'] },
    advanced: {
        enabled: true,
        fields: [
            { field: 'name', label: 'Nome', kind: 'text' },
            { field: 'amount', label: 'Valor', kind: 'number' },
        ],
        operators: { text: ['LIKE', 'NOT LIKE', '=', '!=', 'LIKE_START', 'LIKE_END'], number: ['=', '!=', '>', '>=', '<', '<='], date: ['=', '!=', '>', '>=', '<', '<='] },
    },
    cell_fields: ['name', 'status', 'amount', 'category_id', 'owner_id', 'created_at'],
    labels: {},
};

/**
 * A stand-in for the server: the schema and listing of CrudApiController
 * plus the routes of FiltersApiController, over three rows in memory.
 * Records every call; `metaFails` answers 500 to the meta, `noFilters` a
 * screen with nothing to filter.
 */
export function fakeFilters(options: { metaFails?: boolean; noFilters?: boolean; history?: string[]; saved?: Record<string, Record<string, unknown>>; labels?: Record<string, FilterLabel>; multiple?: string[] } = {}) {
    const calls: FakeCall[] = [];
    let metaFails = options.metaFails ?? false;
    let history = [...(options.history ?? [])];
    let saved: Record<string, Record<string, unknown>> = { ...(options.saved ?? {}) };
    let labels: Record<string, FilterLabel> = { ...(options.labels ?? {}) };

    const preferences = (): Preferences => ({ columns: {}, column_order: [], column_widths: {}, view_mode: 'auto', view_density: 'global', saved_filters: saved, search_history: history });

    const schema = (): Schema => ({
        title: 'Itens',
        titleSingular: 'Item',
        key: 'id',
        columns: [{ field: 'name', label: 'Nome', type: 'text', renderer: null, align: null, sortBy: 'name' }],
        form: [],
        filters: options.noFilters ? [] : [{ field: 'name', label: 'Nome', type: 'text', options: null }],
        permissions: { canRead: true, canCreate: true, canUpdate: true, canDelete: true, canRestore: true, canExport: true },
        bulkActions: [],
        selectable: false,
        export: { enabled: false, formats: [] },
        trash: false,
        defaults: { sort: 'id', direction: 'DESC', per_page: 25 },
        preferences: preferences(),
        features: { ...NO_FEATURES, quickDates: true, advancedSearch: true },
    });

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

    const listing = (query: URLSearchParams): Listing => {
        const search = (query.get('search') ?? '').toLowerCase();
        const status = query.get('filters[status]');
        const found = items.filter((item) => (!search || item.name.toLowerCase().includes(search)) && (!status || item.status === status));

        return {
            data: found.map((item) => ({ id: item.id, cells: { name: { value: item.name, html: item.name } }, style: null, actions: [], trashed: false })),
            meta: { page: 1, per_page: 25, total: found.length, last_page: 1, sort: 'id', direction: 'DESC', search, trashed: false },
            state: { page: 1, per_page: 25, sort: 'id', direction: 'DESC', search, filters: {}, operators: {}, date_ranges: {}, date_operators: {}, quick_date: '', trashed: false },
            totals: [],
            messages: [],
        };
    };

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(String(input), 'http://localhost');
        const method = (init.method ?? 'GET').toUpperCase();
        const body = typeof init.body === 'string' ? (JSON.parse(init.body) as Record<string, unknown>) : null;
        const path = url.pathname.replace('/ptah/api', '');
        calls.push({ method, path, query: url.searchParams, body });

        if (path === '/crud/items/schema') {
            return json(200, schema());
        }
        if (path === '/crud/items' && method === 'GET') {
            return json(200, listing(url.searchParams));
        }
        if (path === '/crud/items/filters/meta') {
            if (metaFails) {
                return json(500, { message: 'Server Error' });
            }
            const meta: FiltersMeta = options.noFilters
                ? { ...filtersMeta, filters: [], quick_dates: { ...filtersMeta.quick_dates, enabled: false }, advanced: { ...filtersMeta.advanced, fields: [] }, cell_fields: [], labels: {} }
                : { ...filtersMeta, filters: filtersMeta.filters.map((item) => (options.multiple?.includes(item.field) ? { ...item, multiple: true } : item)), labels };

            return json(200, meta);
        }
        if (path === '/crud/items/filters/url') {
            const data: { field: string; label: string; op: string; value: string }[] = [];
            const status = url.searchParams.get('f[status]');
            if (status) {
                data.push({ field: 'status', label: 'Situação', op: '=', value: status });
            }

            return json(200, { data });
        }
        if (path === '/crud/items/filters/labels' && method === 'PUT') {
            const changes = (body?.labels ?? {}) as Record<string, FilterLabel | null>;
            if (body?.replace) {
                labels = {};
            }
            for (const [field, label] of Object.entries(changes)) {
                if (label === null) {
                    delete labels[field];
                } else {
                    labels[field] = label;
                }
            }

            return json(200, { ok: true, labels });
        }
        if (path === '/crud/items/filters/by-cell' && method === 'POST') {
            if (body?.field === 'status' && body?.row === 2) {
                return json(200, { ok: true, state: { filters: { status: 'archived' }, operators: { status: '=' }, date_ranges: {}, date_operators: {} } });
            }

            return json(422, { ok: false, state: null });
        }
        if (path === '/crud/items/lookup/category_id') {
            const q = String(body?.q ?? '').toLowerCase();
            const all = [
                { value: 1, label: 'Ferramentas', labelTwo: 'FER' },
                { value: 2, label: 'Peças' },
            ];

            return json(200, { data: all.filter((item) => item.label.toLowerCase().includes(q)) });
        }
        if (path === '/crud/items/lookup/owner_id') {
            const context = (body?.context ?? {}) as Record<string, unknown>;

            return json(200, { data: context.category_id ? [{ value: 10, label: 'Ana' }] : [] });
        }
        if (path === '/crud/items/saved-filters' && method === 'POST') {
            saved = { ...saved, [String(body?.name)]: (body?.filters ?? {}) as Record<string, unknown> };

            return json(200, preferences());
        }
        if (path === '/crud/items/saved-filters' && method === 'DELETE') {
            const next = { ...saved };
            delete next[String(body?.name)];
            saved = next;

            return json(200, preferences());
        }
        if (path === '/crud/items/preferences' && method === 'GET') {
            return json(200, preferences());
        }
        if (path === '/crud/items/preferences' && method === 'PUT') {
            if (body?.clear_search_history === true) {
                history = [];
            }

            return json(200, preferences());
        }

        return json(404, { message: 'Not found' });
    };

    const api = new PtahApi({ fetch: handler as typeof fetch });

    return {
        api,
        calls,
        /** A ready screen controller over this fake. */
        async controller(): Promise<CrudController> {
            const crud = new CrudController('items', { api, toastMs: 0 });
            await crud.init();

            return crud;
        },
        /** The listing requests, newest last. */
        lists: () => calls.filter((call) => call.method === 'GET' && call.path === '/crud/items'),
        lastList: () => calls.filter((call) => call.method === 'GET' && call.path === '/crud/items').at(-1)?.query,
        setMetaFails(fails: boolean) {
            metaFails = fails;
        },
        setHistory(next: string[]) {
            history = next;
        },
    };
}
