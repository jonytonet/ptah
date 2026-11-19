import { PtahApi } from '../../core/api';
import type { AccessAssignedRole, AuditRow } from '../../core/admin/access';

export interface AccessCall {
    method: string;
    path: string;
    query: Record<string, string>;
    body: Record<string, unknown> | null;
}

interface FakeUser {
    id: number;
    name: string;
    email: string;
}

interface FakeBinding {
    id: number;
    user_id: number;
    role_id: number;
    company_id: number | null;
}

const ROLES = [
    { id: 1, name: 'MASTER', master: true },
    { id: 2, name: 'Vendedor', master: false },
];
const COMPANIES = [{ id: 10, name: 'Acme' }];

/**
 * A stand-in for the access API: the same routes and shapes as
 * AccessApiController. `forbidden` answers 403 to everything (not a master);
 * `perPage` makes the users list paginate early.
 */
export function fakeAccess(options: { forbidden?: boolean; perPage?: number; audits?: AuditRow[]; canSendLink?: boolean } = {}) {
    const calls: AccessCall[] = [];
    const users: FakeUser[] = [
        { id: 1, name: 'Admin', email: 'admin@example.com' },
        { id: 2, name: 'Ana', email: 'ana@example.com' },
    ];
    const bindings: FakeBinding[] = [
        { id: 100, user_id: 1, role_id: 1, company_id: null },
        { id: 101, user_id: 2, role_id: 2, company_id: 10 },
    ];
    const audits: AuditRow[] = options.audits ?? [
        { id: 1, created_at: '2026-11-18T10:00:00+00:00', created_at_display: '18/11/2026 10:00:00', user_id: 7, resource_key: 'orders', action: 'read', result: 'granted', ip_address: '10.0.0.1' },
        { id: 2, created_at: '2026-11-18T11:00:00+00:00', created_at_display: '18/11/2026 11:00:00', user_id: 8, resource_key: 'invoices', action: 'delete', result: 'denied', ip_address: '10.0.0.2' },
    ];
    let seq = 200;
    const perPage = options.perPage ?? 25;

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    const meta = (total: number, page: number, size: number) => {
        const last = Math.max(1, Math.ceil(total / size));
        const current = Math.min(page, last);

        return { current_page: current, last_page: last, per_page: size, total, from: total ? (current - 1) * size + 1 : null, to: total ? Math.min(total, current * size) : null };
    };
    const assigned = (userId: number): AccessAssignedRole[] =>
        bindings
            .filter((binding) => binding.user_id === userId)
            .map((binding) => {
                const role = ROLES.find((item) => item.id === binding.role_id)!;
                const company = COMPANIES.find((item) => item.id === binding.company_id);

                return { id: binding.id, role_id: role.id, role_name: role.name, role_master: role.master, company_id: company?.id ?? null, company_name: company?.name ?? null };
            });
    const modal = (user: FakeUser) => ({ user: { id: user.id, name: user.name }, assigned: assigned(user.id) });
    const answer = (ok: boolean, body: Record<string, unknown> = {}) => json(ok ? 200 : 422, { ok, error: null, errors: {}, messages: [], ...body });

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(String(input), 'http://localhost');
        const path = url.pathname.replace('/ptah/api/admin/access', '');
        const query = Object.fromEntries(url.searchParams.entries());
        const method = (init.method ?? 'GET').toUpperCase();
        const body = typeof init.body === 'string' ? (JSON.parse(init.body) as Record<string, unknown>) : null;
        calls.push({ method, path, query, body });

        if (options.forbidden) {
            return json(403, { message: 'Você não tem permissão para realizar esta ação.', error: 'permission_denied' });
        }

        if (method === 'GET' && path === '/users') {
            const search = (query.search ?? '').toLowerCase();
            const role = Number(query.role ?? 0);
            const found = users
                .filter((user) => !search || user.name.toLowerCase().includes(search) || user.email.toLowerCase().includes(search))
                .filter((user) => !role || bindings.some((binding) => binding.user_id === user.id && binding.role_id === role))
                .sort((a, b) => a.name.localeCompare(b.name));
            const info = meta(found.length, Number(query.page ?? 1), perPage);

            return json(200, {
                data: found.slice((info.current_page - 1) * perPage, info.current_page * perPage).map((user) => ({
                    ...user,
                    roles: assigned(user.id).map((item) => ({ id: item.id, name: item.role_name, master: item.role_master, company: item.company_name })),
                })),
                meta: info,
                roles: ROLES,
                companies: COMPANIES,
                can_send_link: options.canSendLink ?? true,
            });
        }

        const validate = (form: Record<string, unknown>, ignore: number | null) => {
            const errors: Record<string, string> = {};
            if (!String(form.name ?? '').trim()) {
                errors.name = 'O campo Nome é obrigatório.';
            }
            if (!String(form.email ?? '').includes('@')) {
                errors.email = 'O campo E-mail deve ser um endereço de e-mail válido.';
            } else if (users.some((user) => user.email === form.email && user.id !== ignore)) {
                errors.email = 'O campo E-mail já está sendo utilizado.';
            }

            return errors;
        };

        if (method === 'POST' && path === '/users') {
            const errors = validate(body ?? {}, null);
            if (Object.keys(errors).length) {
                return answer(false, { errors });
            }
            users.push({ id: ++seq, name: String(body?.name), email: String(body?.email) });

            return answer(true, { messages: [{ text: body?.send_link ? 'Usuário criado. Link de senha enviado.' : 'Usuário criado.', level: 'success' }] });
        }

        const match = path.match(/^\/users\/(\d+)(\/.*)?$/);
        const user = match ? users.find((item) => item.id === Number(match[1])) : undefined;
        if (match && !user) {
            return json(404, { message: 'Not Found' });
        }

        if (user) {
            const rest = match?.[2] ?? '';

            if (method === 'GET' && rest === '') {
                return json(200, { id: user.id, name: user.name, email: user.email });
            }
            if (method === 'PUT' && rest === '') {
                const errors = validate(body ?? {}, user.id);
                if (Object.keys(errors).length) {
                    return answer(false, { errors });
                }
                user.name = String(body?.name);
                user.email = String(body?.email);

                return answer(true, { messages: [{ text: 'Usuário salvo.', level: 'success' }] });
            }
            if (method === 'POST' && rest === '/password-link') {
                return answer(true, { messages: [{ text: 'Link de senha enviado.', level: 'success' }] });
            }
            if (method === 'GET' && rest === '/roles') {
                return json(200, modal(user));
            }
            if (method === 'POST' && rest === '/roles') {
                const roleId = Number(body?.role_id ?? 0);
                if (!roleId) {
                    return answer(false, { errors: { role_id: 'Selecione um role.' }, messages: [{ text: 'Selecione um role.', level: 'danger' }], ...modal(user) });
                }
                const companyId = Number(body?.company_id ?? 0) || null;
                if (!bindings.some((item) => item.user_id === user.id && item.role_id === roleId && item.company_id === companyId)) {
                    bindings.push({ id: ++seq, user_id: user.id, role_id: roleId, company_id: companyId });
                }

                return answer(true, { messages: [{ text: 'Role adicionado.', level: 'success' }], ...modal(user) });
            }
            const remove = rest.match(/^\/roles\/(\d+)$/);
            if (method === 'DELETE' && remove) {
                const index = bindings.findIndex((item) => item.id === Number(remove[1]) && item.user_id === user.id);
                if (index < 0 || ROLES.find((role) => role.id === bindings[index].role_id)?.master) {
                    const text = index < 0 ? 'Erro: not found' : 'Cannot remove the MASTER role from a user directly.';

                    return answer(false, { error: text, messages: [{ text, level: 'danger' }], ...modal(user) });
                }
                bindings.splice(index, 1);

                return answer(true, { messages: [{ text: 'Role removido.', level: 'success' }], ...modal(user) });
            }
        }

        if (method === 'GET' && path === '/audit') {
            const search = query.search ?? '';
            const found = audits
                .filter((row) => !search || (row.resource_key ?? '').includes(search) || String(row.user_id) === search || (row.ip_address ?? '').includes(search))
                .filter((row) => !query.result || row.result === query.result)
                .filter((row) => !query.action || row.action === query.action)
                .filter((row) => !query.date_from || (row.created_at ?? '') >= query.date_from)
                .filter((row) => !query.date_to || (row.created_at ?? '').slice(0, 10) <= query.date_to);
            const info = meta(found.length, Number(query.page ?? 1), 50);

            return json(200, { data: found.slice((info.current_page - 1) * 50, info.current_page * 50), meta: info });
        }

        return json(404, { message: `No fake for ${method} ${path}` });
    };

    return { api: new PtahApi({ fetch: handler as typeof fetch }), calls, users, bindings };
}
