import { PtahApi } from '../../core/api';
import type { MatrixRow, ObjectRow, PageRow, RoleRow } from '../../core/admin/roles';

export interface RolesCall {
    method: string;
    path: string;
    body: Record<string, unknown> | null;
}

/**
 * A stand-in for the roles API: the same routes and shapes as
 * RolesApiController. One MASTER role exists; a second one is refused, the
 * MASTER cannot be deleted; an empty name/slug/key is a validation error.
 */
export function fakeRoles(options: { forbidden?: boolean; empty?: boolean } = {}) {
    const calls: RolesCall[] = [];
    const departments = [{ id: 1, name: 'Comercial' }];
    let roles: RoleRow[] = options.empty
        ? []
        : [
              { id: 1, name: 'Master', description: null, color: '#fbbf24', department_id: null, department: null, is_master: true, is_active: true, permissions_count: 0 },
              { id: 2, name: 'Vendedor', description: 'Vende', color: '#ff0000', department_id: 1, department: 'Comercial', is_master: false, is_active: true, permissions_count: 1 },
          ];
    let pages: PageRow[] = options.empty
        ? []
        : [
              { id: 1, slug: 'admin.vendas', name: 'Vendas', description: null, route: null, icon: '🛒', is_active: true, sort_order: 0, objects_count: 2 },
              { id: 2, slug: 'admin.estoque', name: 'Estoque', description: null, route: null, icon: null, is_active: true, sort_order: 1, objects_count: 0 },
          ];
    let objects: ObjectRow[] = options.empty
        ? []
        : [
              { id: 10, page_id: 1, section: 'main', obj_key: 'vendas.criar', obj_label: 'Criar pedido', obj_type: 'button', obj_order: 1, is_active: true },
              { id: 11, page_id: 1, section: 'toolbar', obj_key: 'vendas.exportar', obj_label: 'Exportar', obj_type: 'button', obj_order: 2, is_active: true },
          ];
    // role id → page object id → flags
    const grants: Record<number, Record<number, Pick<MatrixRow, 'can_create' | 'can_read' | 'can_update' | 'can_delete'>>> = { 2: { 10: { can_create: true, can_read: true, can_update: false, can_delete: false } } };
    let nextId = 100;

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    const ok = () => json(200, { ok: true, error: null, errors: {}, messages: [{ text: 'Done.', level: 'success' }] });
    const refuse = (error: string) => json(422, { ok: false, error, errors: {}, messages: [{ text: error, level: 'danger' }] });
    const invalid = (errors: Record<string, string>) => json(422, { message: 'invalid', errors: Object.fromEntries(Object.entries(errors).map(([field, message]) => [field, [message]])) });
    const meta = (total: number) => ({ page: 1, per_page: 20, total, last_page: 1, from: total ? 1 : 0, to: total });

    const matrix = (roleId: number): MatrixRow[] =>
        objects
            .filter((object) => object.is_active)
            .map((object) => ({
                id: object.id,
                page_id: object.page_id,
                page_name: pages.find((page) => page.id === object.page_id)?.name ?? '—',
                section: object.section,
                obj_key: object.obj_key,
                obj_label: object.obj_label,
                obj_type: object.obj_type,
                ...(grants[roleId]?.[object.id] ?? { can_create: false, can_read: false, can_update: false, can_delete: false }),
            }));

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(String(input), 'http://localhost');
        const path = url.pathname.replace('/ptah/api', '');
        const method = (init.method ?? 'GET').toUpperCase();
        const body = typeof init.body === 'string' ? (JSON.parse(init.body) as Record<string, unknown>) : null;
        const search = (url.searchParams.get('search') ?? '').toLowerCase();
        calls.push({ method, path: `${path}${url.search}`, body });

        if (options.forbidden) {
            return json(403, { message: 'Você não tem permissão para realizar esta ação.', error: 'permission_denied' });
        }

        let match: RegExpMatchArray | null;

        if (path === '/admin/roles' && method === 'GET') {
            const data = roles.filter((role) => role.name.toLowerCase().includes(search));

            return json(200, { data, meta: meta(data.length), departments });
        }
        if (path === '/admin/roles' && method === 'POST') {
            if (!body?.name) {
                return invalid({ name: 'O campo nome é obrigatório.' });
            }
            if (body.is_master && roles.some((role) => role.is_master)) {
                return refuse('Já existe um role MASTER. Só é permitido um role MASTER no sistema.');
            }
            const department = departments.find((item) => item.id === body.department_id);
            roles = [...roles, { id: ++nextId, name: String(body.name), description: String(body.description ?? '') || null, color: String(body.color ?? '') || null, department_id: department?.id ?? null, department: department?.name ?? null, is_master: Boolean(body.is_master), is_active: Boolean(body.is_active), permissions_count: 0 }];

            return ok();
        }
        if ((match = path.match(/^\/admin\/roles\/(\d+)$/))) {
            const id = Number(match[1]);
            const role = roles.find((item) => item.id === id);
            if (!role) {
                return json(404, { message: 'Not found.' });
            }
            if (method === 'GET') {
                return json(200, { data: { id, name: role.name, description: role.description ?? '', color: role.color ?? '', department_id: role.department_id, is_master: role.is_master, is_active: role.is_active } });
            }
            if (method === 'PUT') {
                if (!body?.name) {
                    return invalid({ name: 'O campo nome é obrigatório.' });
                }
                roles = roles.map((item) => (item.id === id ? { ...item, name: String(body.name), is_active: Boolean(body.is_active) } : item));

                return ok();
            }
            if (method === 'DELETE') {
                if (role.is_master) {
                    return refuse('O role MASTER não pode ser excluído.');
                }
                roles = roles.filter((item) => item.id !== id);

                return ok();
            }
        }
        if ((match = path.match(/^\/admin\/roles\/(\d+)\/permissions$/))) {
            const id = Number(match[1]);
            const role = roles.find((item) => item.id === id);
            if (!role) {
                return json(404, { message: 'Not found.' });
            }
            if (method === 'GET') {
                return json(200, { role: { id, name: role.name }, data: matrix(id) });
            }
            grants[id] = {};
            for (const row of (body?.objects ?? []) as MatrixRow[]) {
                if (row.can_create || row.can_read || row.can_update || row.can_delete) {
                    grants[id][row.id] = { can_create: row.can_create, can_read: row.can_read, can_update: row.can_update, can_delete: row.can_delete };
                }
            }
            roles = roles.map((item) => (item.id === id ? { ...item, permissions_count: Object.keys(grants[id]).length } : item));

            return ok();
        }

        if (path === '/admin/pages' && method === 'GET') {
            const data = pages.filter((page) => `${page.name} ${page.slug}`.toLowerCase().includes(search)).map((page) => ({ ...page, objects_count: objects.filter((object) => object.page_id === page.id).length }));

            return json(200, { data, meta: meta(data.length), types: ['page', 'button', 'field', 'link', 'section', 'api', 'report', 'tab'] });
        }
        if (path === '/admin/pages' && method === 'POST') {
            if (!body?.slug || !body?.name) {
                return invalid({ ...(body?.slug ? {} : { page_slug: 'O campo slug é obrigatório.' }), ...(body?.name ? {} : { page_name: 'O campo nome é obrigatório.' }) });
            }
            pages = [...pages, { id: ++nextId, slug: String(body.slug), name: String(body.name), description: null, route: null, icon: null, is_active: true, sort_order: Number(body.sort_order ?? 0), objects_count: 0 }];

            return ok();
        }
        if ((match = path.match(/^\/admin\/pages\/(\d+)$/))) {
            const id = Number(match[1]);
            if (method === 'PUT') {
                pages = pages.map((page) => (page.id === id ? { ...page, slug: String(body?.slug), name: String(body?.name) } : page));

                return ok();
            }
            if (method === 'DELETE') {
                pages = pages.filter((page) => page.id !== id);
                objects = objects.filter((object) => object.page_id !== id);

                return ok();
            }
        }
        if ((match = path.match(/^\/admin\/pages\/(\d+)\/objects(?:\/(\d+))?$/))) {
            const pageId = Number(match[1]);
            const objectId = match[2] ? Number(match[2]) : null;
            const page = pages.find((item) => item.id === pageId);
            if (!page || (objectId !== null && !objects.some((object) => object.id === objectId && object.page_id === pageId))) {
                return json(404, { message: 'Not found.' });
            }
            if (method === 'GET') {
                const data = objects.filter((object) => object.page_id === pageId && `${object.obj_key} ${object.obj_label}`.toLowerCase().includes(search));

                return json(200, { page: { id: page.id, name: page.name }, data, meta: meta(data.length) });
            }
            if (method === 'DELETE') {
                objects = objects.filter((object) => object.id !== objectId);

                return ok();
            }
            if (!body?.obj_key || !body?.obj_label) {
                return invalid({ ...(body?.obj_key ? {} : { obj_key: 'O campo chave é obrigatório.' }), ...(body?.obj_label ? {} : { obj_label: 'O campo label é obrigatório.' }) });
            }
            const values = { section: String(body.section), obj_key: String(body.obj_key), obj_label: String(body.obj_label), obj_type: String(body.obj_type), obj_order: Number(body.obj_order ?? 0), is_active: Boolean(body.is_active) };
            objects = objectId === null ? [...objects, { id: ++nextId, page_id: pageId, ...values }] : objects.map((object) => (object.id === objectId ? { ...object, ...values } : object));

            return ok();
        }

        if (path === '/admin/permission-guide' && method === 'GET') {
            return json(200, { version: '1.59.0', urls: { departments: '/ptah-departments', pages: '/ptah-pages', roles: '/ptah-roles', users: '/ptah-users-acl', audit: '/ptah-audit' } });
        }

        return json(404, { message: `No fake for ${method} ${path}` });
    };

    return { api: new PtahApi({ fetch: handler as typeof fetch }), calls, roles: () => roles, pages: () => pages, objects: () => objects, grants };
}
