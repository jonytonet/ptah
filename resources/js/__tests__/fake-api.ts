import { PtahApi } from '../core/api';
import type { Listing, ListState, Row, Schema } from '../core/types';

export interface Call {
    method: string;
    url: string;
    body: unknown;
}

const rowsSeed = [
    { id: 1, name: 'Alfa', status: 'active', amount: 10 },
    { id: 2, name: 'Beta', status: 'archived', amount: 500 },
    { id: 3, name: 'Gama', status: 'active', amount: 30 },
];

export const schema: Schema = {
    title: 'Itens',
    titleSingular: 'Item',
    key: 'id',
    columns: [
        { field: 'id', label: 'ID', type: 'number', renderer: null, align: null, sortBy: 'id' },
        { field: 'name', label: 'Nome', type: 'text', renderer: null, align: null, sortBy: 'name' },
        { field: 'status', label: 'Situação', type: 'select', renderer: null, align: null, sortBy: 'status' },
    ],
    form: [
        { field: 'name', label: 'Nome', type: 'text', required: true, readonly: false, options: null, mask: null, maskPatterns: null, placeholder: null, help: null },
        {
            field: 'status',
            label: 'Situação',
            type: 'select',
            required: false,
            readonly: false,
            options: [
                { label: 'Ativo', value: 'active' },
                { label: 'Arquivado', value: 'archived' },
            ],
            mask: null,
            maskPatterns: null,
            placeholder: null,
            help: null,
        },
        { field: 'phone', label: 'Telefone', type: 'text', required: false, readonly: false, options: null, mask: 'phone', maskPatterns: ['(00) 0000-0000', '(00) 00000-0000'], placeholder: null, help: null },
    ],
    filters: [
        {
            field: 'status',
            label: 'Situação',
            type: 'select',
            options: [
                { label: 'Ativo', value: 'active' },
                { label: 'Arquivado', value: 'archived' },
            ],
        },
    ],
    permissions: { canRead: true, canCreate: true, canUpdate: true, canDelete: true, canRestore: true, canExport: true },
    bulkActions: [{ action: 'archive', label: 'Arquivar', icon: null, confirm: null }],
    selectable: true,
    export: { enabled: true, formats: ['excel', 'pdf'] },
    trash: false,
    defaults: { sort: 'id', direction: 'DESC', per_page: 25 },
    preferences: { columns: { id: true, name: true, status: true }, column_order: [], column_widths: {}, view_mode: 'auto', view_density: 'global', saved_filters: {}, search_history: [] },
};

function toRow(item: (typeof rowsSeed)[number]): Row {
    return {
        id: item.id,
        cells: {
            id: { value: item.id, html: String(item.id) },
            name: { value: item.name, html: item.name },
            status: { value: item.status, html: item.status === 'active' ? 'Ativo' : 'Arquivado' },
        },
        style: null,
        actions: [],
        trashed: false,
    };
}

function parseQuery(url: string): Record<string, string> {
    const out: Record<string, string> = {};
    new URL(url, 'http://localhost').searchParams.forEach((value, key) => {
        out[key] = value;
    });

    return out;
}

/**
 * A stand-in for the server: the same routes and shapes as the real API, over
 * three rows in memory. Records every call.
 */
export function fakeApi() {
    const calls: Call[] = [];
    let items = rowsSeed.map((item) => ({ ...item }));
    let remembered: Record<string, string> = {};
    let nextId = 4;

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

    const listing = (query: Record<string, string>): Listing => {
        let found = [...items];
        const search = (query.search ?? '').toLowerCase();
        if (search) {
            found = found.filter((item) => item.name.toLowerCase().includes(search));
        }
        const status = query['filters[status]'];
        if (status) {
            found = found.filter((item) => item.status === status);
        }
        const sort = (query.sort ?? 'id') as 'id' | 'name' | 'status';
        const direction = (query.direction ?? 'DESC').toUpperCase();
        found.sort((a, b) => (a[sort] > b[sort] ? 1 : -1) * (direction === 'ASC' ? 1 : -1));

        const perPage = Number(query.per_page ?? 25);
        const lastPage = Math.max(1, Math.ceil(found.length / perPage));
        const page = Math.min(Number(query.page ?? 1), lastPage);

        const state: ListState = {
            page,
            per_page: perPage,
            sort,
            direction,
            search: query.search ?? '',
            filters: status ? { status } : {},
            operators: {},
            date_ranges: {},
            date_operators: {},
            quick_date: '',
            trashed: false,
        };

        return {
            data: found.slice((page - 1) * perPage, page * perPage).map(toRow),
            meta: { page, per_page: perPage, total: found.length, last_page: lastPage, sort, direction, search: state.search, trashed: false },
            state,
            totals: [],
            messages: [],
        };
    };

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = String(input);
        const method = (init.method ?? 'GET').toUpperCase();
        const body = typeof init.body === 'string' ? JSON.parse(init.body) : (init.body ?? null);
        calls.push({ method, url, body });

        const path = new URL(url, 'http://localhost').pathname.replace('/ptah/api', '');
        const query = parseQuery(url);

        if (path === '/crud/closed/schema' || path === '/crud/closed') {
            return json(403, { message: 'Forbidden' });
        }
        if (path === '/crud/items/schema') {
            return json(200, schema);
        }
        if (path === '/crud/items' && method === 'GET') {
            if (query.remember) {
                remembered = { ...query };
            }

            return json(200, listing(query.restore ? remembered : query));
        }
        if (path === '/crud/items/create') {
            return json(200, { data: { status: 'active' }, labels: [] });
        }
        if (path === '/crud/items' && method === 'POST') {
            const data = (body as { data: Record<string, string> }).data;
            if (!data.name) {
                return json(422, { ok: false, id: null, errors: { name: 'Nome is required.' }, messages: [] });
            }
            const id = nextId++;
            items.push({ id, name: data.name, status: data.status ?? 'active', amount: 0 });

            return json(201, { ok: true, id, errors: [], messages: [{ text: 'Salvo!', level: 'success' }] });
        }
        if (path === '/crud/items/bulk') {
            const { action, ids } = body as { action: string; ids: string[] };
            if (action === 'delete') {
                items = items.filter((item) => !ids.includes(String(item.id)));
            }

            return json(200, { ok: true, remaining: [], messages: [] });
        }
        if (path === '/crud/items/export') {
            return json(200, { ok: true, url: '/ptah/export/download/abc', messages: [] });
        }
        if (path === '/crud/items/lookup/owner') {
            return json(200, { data: [{ value: 7, label: 'Ana' }] });
        }
        if (path === '/crud/items/preferences' && method === 'PUT') {
            return json(200, { ...schema.preferences, ...(body as object) });
        }

        const match = path.match(/^\/crud\/items\/(\d+)(\/\w+)?$/);
        if (match) {
            const id = Number(match[1]);
            const item = items.find((candidate) => candidate.id === id);
            if (!item) {
                return json(404, { message: 'Not found' });
            }
            if (method === 'GET') {
                return json(200, { data: { ...item }, labels: [] });
            }
            if (method === 'PUT' || (method === 'POST' && !match[2])) {
                Object.assign(item, (body as { data: object }).data);

                return json(200, { ok: true, id, errors: [], messages: [{ text: 'Salvo!', level: 'success' }] });
            }
            if (method === 'DELETE') {
                items = items.filter((candidate) => candidate.id !== id);

                return json(200, { ok: true, messages: [{ text: 'Excluído.', level: 'warn' }] });
            }
            if (match[2] === '/duplicate') {
                return json(200, { data: { name: item.name, status: item.status }, labels: [] });
            }
        }
        if (path === '/menu') {
            return json(200, {
                data: [
                    { label: 'Itens', url: '/items', type: 'menuLink', icon: 'bx bx-cube', children: [] },
                    { label: 'Cadastros', type: 'menuGroup', children: [{ label: 'Clientes', url: '/clientes', children: [] }] },
                ],
            });
        }
        if (path === '/me') {
            return json(200, { user: { id: 9, name: 'Ana', email: 'ana@example.test' }, company_id: 1, locale: 'pt_BR', screens: ['items'] });
        }

        return json(404, { message: `No fake for ${method} ${path}` });
    };

    return { api: new PtahApi({ fetch: handler as typeof fetch }), calls, items: () => items };
}
