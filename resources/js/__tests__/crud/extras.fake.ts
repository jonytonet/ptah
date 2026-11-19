import { PtahApi } from '../../core/api';
import { NO_FEATURES } from '../../core/types';
import type { Preferences, Row, Schema } from '../../core/types';

export interface ExtrasCall {
    method: string;
    path: string;
    body: Record<string, unknown> | null;
}

export const DRAFT_USER = 'u9';
export const draftKey = (target: string) => `ptah:draft:${DRAFT_USER}:scr0001:${target}`;

const LABELS: Record<string, string> = { name: 'Nome', status: 'Situação', amount: 'Valor' };

/**
 * A stand-in for the CRUD API of one screen (`items`) plus the extras routes
 * (ExtrasApiController): same paths and shapes. The draft fields are name and
 * status; the record's fingerprint is `v` + its name; a name of "Arquivar"
 * archives through the onChange formula. Row 1 has one detail screen
 * (`items..1.0`, its lines — served below as a whole CRUD screen), row 2 an
 * unavailable one (or none, `emptyDetails`), row 3 fails.
 */
export function fakeExtras(options: { features?: boolean; failColumns?: boolean; emptyDetails?: boolean } = {}) {
    const calls: ExtrasCall[] = [];
    const items = [
        { id: 1, name: 'Alfa', status: 'active', amount: 10 },
        { id: 2, name: 'Beta', status: 'archived', amount: 20 },
        { id: 3, name: 'Gama', status: 'active', amount: 30 },
    ];
    let nextId = 4;
    let preferences: Preferences = {
        columns: { id: true, name: true, status: true, amount: true },
        column_order: [],
        column_widths: {},
        view_mode: 'auto',
        view_density: 'global',
        saved_filters: {},
        search_history: [],
    };
    const on = options.features !== false;

    const schema = (): Schema => ({
        title: 'Itens',
        titleSingular: 'Item',
        key: 'id',
        columns: ['id', 'name', 'status', 'amount'].filter((field) => preferences.columns[field] !== false).map((field) => ({ field, label: LABELS[field] ?? 'ID', type: 'text', renderer: null, align: null, sortBy: field })),
        form: [
            { field: 'name', label: 'Nome', type: 'text', required: true, readonly: false, options: null, mask: null, maskPatterns: null, placeholder: null, help: null },
            { field: 'status', label: 'Situação', type: 'select', required: false, readonly: false, options: [{ label: 'Ativo', value: 'active' }, { label: 'Arquivado', value: 'archived' }], mask: null, maskPatterns: null, placeholder: null, help: null },
        ],
        filters: [],
        permissions: { canRead: true, canCreate: true, canUpdate: true, canDelete: true, canRestore: true, canExport: true },
        bulkActions: [],
        selectable: false,
        export: { enabled: false, formats: [] },
        trash: false,
        defaults: { sort: 'id', direction: 'DESC', per_page: 25 },
        preferences,
        features: { ...NO_FEATURES, formDraft: on, masterDetail: on },
    });

    const row = (item: (typeof items)[number]): Row => ({
        id: item.id,
        cells: { id: { value: item.id, html: String(item.id) }, name: { value: item.name, html: item.name }, status: { value: item.status, html: item.status }, amount: { value: item.amount, html: String(item.amount) } },
        style: null,
        actions: [],
        trashed: false,
    });

    // The lines of each item — the child screen `items..{id}.0`.
    const lines = [
        { id: 11, parent: 1, name: 'L1' },
        { id: 12, parent: 1, name: 'L2' },
        { id: 13, parent: 2, name: 'L3' },
    ];
    let nextLine = 14;
    const lineRow = (item: (typeof lines)[number]): Row => ({
        id: item.id,
        cells: { name: { value: item.name, html: `<b>${item.name}</b>` } },
        style: null,
        actions: [],
        trashed: false,
    });
    const lineSchema = (): Schema => ({
        ...schema(),
        title: 'Linhas do item',
        titleSingular: 'Linha',
        columns: [{ field: 'name', label: 'Linha', type: 'text', renderer: null, align: null, sortBy: 'name' }],
        form: [{ field: 'name', label: 'Linha', type: 'text', required: true, readonly: false, options: null, mask: null, maskPatterns: null, placeholder: null, help: null }],
        preferences: { ...preferences, columns: { name: true }, column_widths: {} },
        features: { ...NO_FEATURES },
    });

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    const defaults = { name: null, status: 'active' };
    const fieldsOf = (data: Record<string, unknown>) => ({ name: data.name ?? null, status: data.status ?? null });

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(String(input), 'http://localhost');
        const path = url.pathname.replace('/ptah/api/crud/items', '');
        const method = (init.method ?? 'GET').toUpperCase();
        const body = typeof init.body === 'string' ? (JSON.parse(init.body) as Record<string, unknown>) : null;
        calls.push({ method, path: `${path}${url.search}`, body });

        // ── A detail screen: `items..{row}.{index}` ──
        const child = path.match(/^\.\.(\d+)\.(\d+)(\/.*)?$/);
        if (child) {
            const parent = Number(child[1]);
            const rest = child[3] ?? '';
            if (child[2] !== '0' || !items.some((item) => item.id === parent)) {
                return json(404, { message: 'Not found.' });
            }
            if (rest === '/schema') {
                return json(200, lineSchema());
            }
            if (rest === '' && method === 'GET') {
                const search = (url.searchParams.get('search') ?? '').toLowerCase();
                const mine = lines.filter((item) => item.parent === parent && item.name.toLowerCase().includes(search));

                return json(200, {
                    data: mine.map(lineRow),
                    meta: { page: 1, per_page: 25, total: mine.length, last_page: 1, sort: 'id', direction: 'DESC', search, trashed: false },
                    state: { page: 1, per_page: 25, sort: 'id', direction: 'DESC', search, filters: {}, operators: {}, date_ranges: {}, date_operators: {}, quick_date: '', trashed: false },
                    totals: [],
                    messages: [],
                });
            }
            if (rest === '/create') {
                return json(200, { data: { name: null }, labels: [] });
            }
            if (rest === '' && method === 'POST') {
                const data = (body?.data ?? {}) as Record<string, string>;
                // The server binds the line to the row of the key, whatever was sent.
                lines.push({ id: nextLine++, parent, name: data.name });

                return json(201, { ok: true, id: nextLine - 1, errors: [], messages: [{ text: 'Salvo!', level: 'success' }] });
            }

            return json(404, { message: `No fake for ${method} child ${rest}` });
        }

        if (path === '/schema') {
            return json(200, schema());
        }
        if (path === '' && method === 'GET') {
            return json(200, {
                data: items.map(row),
                meta: { page: 1, per_page: 25, total: items.length, last_page: 1, sort: 'id', direction: 'DESC', search: '', trashed: false },
                state: { page: 1, per_page: 25, sort: 'id', direction: 'DESC', search: '', filters: {}, operators: {}, date_ranges: {}, date_operators: {}, quick_date: '', trashed: false },
                totals: [],
                messages: [],
            });
        }
        if (path === '/create') {
            return json(200, { data: { ...defaults }, labels: [] });
        }
        if (path === '' && method === 'POST') {
            const data = (body?.data ?? {}) as Record<string, string>;
            if (!data.name) {
                return json(422, { ok: false, id: null, errors: { name: 'O campo Nome é obrigatório.' }, messages: [] });
            }
            const id = nextId++;
            items.push({ id, name: data.name, status: data.status ?? 'active', amount: 0 });

            return json(201, { ok: true, id, errors: [], messages: [{ text: 'Salvo!', level: 'success' }] });
        }
        if (path === '/preferences' && method === 'PUT') {
            const changes = body as Partial<Preferences>;
            preferences = {
                ...preferences,
                columns: { ...preferences.columns, ...(changes.columns ?? {}) },
                column_order: changes.column_order ?? preferences.column_order,
                column_widths: { ...preferences.column_widths, ...(changes.column_widths ?? {}) },
            };

            return json(200, preferences);
        }

        // ── Extras ──
        if (path === '/form/draft' && method === 'POST') {
            const id = body?.id === undefined ? null : Number(body.id);
            const item = id === null ? null : items.find((candidate) => candidate.id === id);
            if (id !== null && !item) {
                return json(404, { message: 'Not found.' });
            }
            if (!on) {
                return json(404, { message: 'Not found.' });
            }
            const original = item ? { name: item.name, status: item.status } : { ...defaults };
            const version = item ? `v${item.name}` : '';
            const draft = body?.draft as { values: Record<string, unknown>; version: string } | undefined;
            const discarded = Boolean(draft) && draft!.version !== version;
            const restored = Boolean(draft) && !discarded;

            return json(200, {
                key: draftKey(item ? `edit:${item.id}` : 'new'),
                prefix: draftKey(''),
                user: DRAFT_USER,
                fields: ['name', 'status'],
                original,
                version,
                ttlDays: 7,
                restored,
                discarded,
                data: restored ? fieldsOf(draft!.values) : {},
                labels: {},
            });
        }
        if (path === '/form/change' && method === 'POST') {
            const data = (body?.data ?? {}) as Record<string, unknown>;
            if (data.name === 'Quebrar') {
                return json(500, { message: 'Server Error' });
            }

            return json(200, { data: data.name === 'Arquivar' && data.status !== 'archived' ? { status: 'archived' } : {}, labels: {} });
        }
        if (path === '/form/triggers') {
            return json(200, { fields: ['name'] });
        }
        if (path === '/columns' && method === 'GET') {
            if (options.failColumns) {
                return json(500, { message: 'Server Error' });
            }

            return json(200, { data: ['name', 'status', 'amount'].map((field) => ({ field, label: LABELS[field], visible: preferences.columns[field] !== false })) });
        }
        if (path === '/columns/reset' && method === 'POST') {
            if (body && typeof body.field === 'string') {
                const widths = { ...preferences.column_widths };
                delete widths[body.field];
                preferences = { ...preferences, column_widths: widths };
            } else {
                preferences = { ...preferences, columns: { id: true, name: true, status: true, amount: true }, column_order: [], column_widths: {} };
            }

            return json(200, { preferences, data: ['name', 'status', 'amount'].map((field) => ({ field, label: LABELS[field], visible: preferences.columns[field] !== false })) });
        }

        const details = path.match(/^\/(\d+)\/details$/);
        if (details) {
            const id = Number(details[1]);
            if (id === 3) {
                return json(500, { message: 'Server Error' });
            }
            if (id === 2) {
                return json(200, { data: options.emptyDetails ? [] : [{ index: 1, title: 'Notas', screen: 'items..2.1', available: false }] });
            }

            return json(200, { data: [{ index: 0, title: 'Linhas', screen: `items..${id}.0`, available: true }] });
        }

        const record = path.match(/^\/(\d+)$/);
        if (record && method === 'GET') {
            const item = items.find((candidate) => candidate.id === Number(record[1]));

            return item ? json(200, { data: { ...item }, labels: [] }) : json(404, { message: 'Not found.' });
        }
        if (record && (method === 'PUT' || method === 'POST')) {
            const item = items.find((candidate) => candidate.id === Number(record[1]));
            Object.assign(item ?? {}, (body?.data ?? {}) as object);

            return json(200, { ok: true, id: item?.id ?? null, errors: [], messages: [{ text: 'Salvo!', level: 'success' }] });
        }

        return json(404, { message: `No fake for ${method} ${path}` });
    };

    return { api: new PtahApi({ fetch: handler as typeof fetch }), calls, items: () => items, lines: () => lines, preferences: () => preferences };
}
