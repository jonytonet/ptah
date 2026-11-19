import { PtahApi } from '../../core/api';
import type { MenuRow, SettingField, SettingScope, SettingsPayload } from '../../core/admin/menu';

export interface MenuCall {
    method: string;
    path: string;
    query: Record<string, string>;
    body: Record<string, unknown> | null;
}

/**
 * A stand-in for the menu and settings admin API: the same routes and
 * shapes as MenuApiController. `javascript:` URLs and blank texts are refused
 * as the component refuses them; `forbidden` answers 403 everywhere.
 */
export function fakeMenu(options: { forbidden?: boolean; installed?: boolean; noSettings?: boolean; empty?: boolean } = {}) {
    const calls: MenuCall[] = [];
    let seq = 3;
    let items: MenuRow[] = options.empty
        ? []
        : [
              { id: 1, text: 'Vendas', url: null, icon: 'bx bx-cart', type: 'menuGroup', target: '_self', parent_id: null, parent: null, link_order: 0, is_active: true },
              { id: 2, text: 'Pedidos', url: '/orders', icon: 'bx bx-list-ul', type: 'menuLink', target: '_self', parent_id: 1, parent: 'Vendas', link_order: 1, is_active: true },
              { id: 3, text: 'Clientes', url: '/clients', icon: 'bx bx-user', type: 'menuLink', target: '_self', parent_id: null, parent: null, link_order: 2, is_active: false },
          ];

    // Settings: company 7, global values and defaults.
    const defs: Omit<SettingField, 'value' | 'source'>[] = options.noSettings
        ? []
        : [
              { key: 'invoice_due_days', label: 'Vencimento', type: 'integer', help: 'Em dias.', secret: false, options: [] },
              { key: 'approval', label: 'Exige aprovação', type: 'boolean', help: null, secret: false, options: [] },
              { key: 'carrier', label: 'Transportadora', type: 'select', help: null, secret: false, options: [{ value: 'correios', label: 'Correios' }, { value: 'jadlog', label: 'Jadlog' }] },
              { key: 'api_token', label: 'Token', type: 'text', help: null, secret: true, options: [] },
          ];
    const groupOf: Record<string, string> = { invoice_due_days: 'Financeiro', approval: 'Vendas', carrier: 'General', api_token: 'Integração' };
    const defaults: Record<string, unknown> = { invoice_due_days: 30, approval: false, carrier: 'correios', api_token: '' };
    const global: Record<string, unknown> = {};
    const company: Record<string, unknown> = { api_token: 'tok-secreto' };

    const settings = (scope: SettingScope): SettingsPayload => {
        const groups: SettingsPayload['groups'] = [];
        for (const def of defs) {
            const own = scope === 'company' && def.key in company;
            const source = own ? 'company' : def.key in global ? 'global' : 'default';
            const value = own ? company[def.key] : def.key in global ? global[def.key] : defaults[def.key];
            const name = groupOf[def.key];
            let group = groups.find((item) => item.name === name);
            if (!group) {
                group = { name, fields: [] };
                groups.push(group);
            }
            group.fields.push({ ...def, value: def.secret ? '' : value, source });
        }

        return { installed: options.installed ?? true, scope, per_company: true, can_edit_global: true, groups };
    };

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    const groups = (except: number | null = null) => items.filter((item) => item.type === 'menuGroup' && item.id !== except).map((item) => ({ id: item.id, text: item.text }));

    const validate = (body: Record<string, unknown>) => {
        const errors: Record<string, string[]> = {};
        if (!String(body.text ?? '').trim()) {
            errors.text = ['The menu text is required.'];
        }
        if (String(body.url ?? '').startsWith('javascript:')) {
            errors.url = ['The URL must be http(s), mailto:, tel: or a relative path.'];
        }

        return Object.keys(errors).length ? json(422, { message: 'invalid', errors }) : null;
    };

    const store = (body: Record<string, unknown>, into: MenuRow | null): MenuRow => {
        const parentId = body.parent_id === null || body.parent_id === undefined || body.parent_id === '' ? null : Number(body.parent_id);
        const type = body.type === 'menuGroup' ? 'menuGroup' : 'menuLink';

        return {
            id: into?.id ?? ++seq,
            text: String(body.text),
            url: type === 'menuGroup' ? null : String(body.url ?? '') || null,
            icon: String(body.icon ?? '') || 'bx bx-circle',
            type,
            target: body.target === '_blank' ? '_blank' : '_self',
            parent_id: parentId,
            parent: items.find((item) => item.id === parentId)?.text ?? null,
            link_order: Number(body.link_order ?? 0),
            is_active: body.is_active !== false,
        };
    };

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(String(input), 'http://localhost');
        const path = url.pathname.replace('/ptah/api', '');
        const method = (init.method ?? 'GET').toUpperCase();
        const body = typeof init.body === 'string' ? (JSON.parse(init.body) as Record<string, unknown>) : null;
        const query = Object.fromEntries(url.searchParams.entries());
        calls.push({ method, path, query, body });

        if (options.forbidden) {
            return json(403, { message: 'Você não tem permissão para realizar esta ação.' });
        }

        if (method === 'GET' && path === '/admin/menu') {
            let rows = items.filter((item) => (!query.search || item.text.toLowerCase().includes(query.search.toLowerCase())) && (!query.type || item.type === query.type));
            const sort = (query.sort ?? 'link_order') as 'text' | 'link_order';
            const dir = query.direction === 'desc' ? -1 : 1;
            rows = [...rows].sort((a, b) => (sort === 'text' ? a.text.localeCompare(b.text) : a.link_order - b.link_order) * dir);
            const page = Number(query.page ?? 1);
            const perPage = 2;
            const lastPage = Math.max(1, Math.ceil(rows.length / perPage));

            return json(200, {
                data: rows.slice((page - 1) * perPage, page * perPage),
                meta: { current_page: page, last_page: lastPage, per_page: perPage, total: rows.length },
                state: { search: query.search ?? '', type: query.type ?? '', sort, direction: dir === 1 ? 'asc' : 'desc' },
                groups: groups(),
            });
        }
        if (method === 'POST' && path === '/admin/menu') {
            const refusal = validate(body ?? {});
            if (refusal) {
                return refusal;
            }
            const row = store(body ?? {}, null);
            items = [...items, row];

            return json(200, { ok: true, error: null, messages: [{ text: `Item "${row.text}" criado.`, level: 'success' }] });
        }

        const match = path.match(/^\/admin\/menu\/(\d+)(\/toggle)?$/);
        if (match) {
            const id = Number(match[1]);
            const item = items.find((row) => row.id === id);
            if (!item) {
                return json(404, { message: 'Not found.' });
            }
            if (method === 'GET') {
                const { parent: _parent, ...form } = item; // eslint-disable-line @typescript-eslint/no-unused-vars

                return json(200, { data: { ...form, url: form.url ?? '' }, groups: groups(id) });
            }
            if (method === 'PUT') {
                const refusal = validate({ ...item, ...(body ?? {}) });
                if (refusal) {
                    return refusal;
                }
                const row = store({ ...item, ...(body ?? {}) }, item);
                items = items.map((current) => (current.id === id ? row : current));

                return json(200, { ok: true, error: null, messages: [{ text: `Item "${row.text}" atualizado.`, level: 'success' }] });
            }
            if (method === 'POST' && match[2]) {
                item.is_active = !item.is_active;

                return json(200, { ok: true, error: null, messages: [{ text: item.is_active ? 'Item ativado.' : 'Item desativado.', level: 'success' }], is_active: item.is_active });
            }
            if (method === 'DELETE') {
                items = items.filter((row) => row.id !== id).map((row) => (row.parent_id === id ? { ...row, parent_id: null, parent: null } : row));

                return json(200, { ok: true, error: null, messages: [{ text: 'Item deleted.', level: 'success' }] });
            }
        }

        if (path === '/admin/settings' && method === 'GET') {
            return json(200, settings(query.scope === 'global' ? 'global' : 'company'));
        }
        if (path === '/admin/settings' && method === 'PUT') {
            if (options.installed === false) {
                return json(409, { ok: false, installed: false, error: 'not installed' });
            }
            const scope: SettingScope = body?.scope === 'global' ? 'global' : 'company';
            const values = (body?.values ?? {}) as Record<string, unknown>;
            const days = Number(values.invoice_due_days);
            if (Number.isNaN(days) || days > 365) {
                return json(422, { ok: false, errors: { invoice_due_days: 'O campo Vencimento não pode ser superior a 365.' }, messages: [], settings: settings(scope) });
            }
            const target = scope === 'global' ? global : company;
            for (const def of defs) {
                if (def.key in values && !(def.secret && values[def.key] === '')) {
                    target[def.key] = def.type === 'integer' ? Number(values[def.key]) : values[def.key];
                }
            }

            return json(200, { ok: true, errors: {}, messages: [{ text: 'Configurações salvas.', level: 'success' }], settings: settings(scope) });
        }
        const reset = path.match(/^\/admin\/settings\/([^/]+)\/reset$/);
        if (reset && method === 'POST') {
            delete company[decodeURIComponent(reset[1])];

            return json(200, { ok: true, messages: [], settings: settings('company') });
        }

        return json(404, { message: `No fake for ${method} ${path}` });
    };

    return { api: new PtahApi({ fetch: handler as typeof fetch }), calls, items: () => items, company, global };
}
