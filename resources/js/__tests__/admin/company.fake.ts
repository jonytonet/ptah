import { PtahApi } from '../../core/api';
import type { CompanyForm, CompanyRow, DepartmentForm, DepartmentRow, SwitcherCompany } from '../../core/admin/company';

export interface FakeCall {
    method: string;
    path: string;
    query: URLSearchParams;
    body: Record<string, unknown> | null;
}

const PER_PAGE = 20;

/**
 * A stand-in for the company area of the admin API: the same routes and shapes
 * as CompanyApiController, over records in memory. `forbidden` answers 403 to
 * the list screens, as the server does to a user who is not master.
 */
export function fakeCompany(options: { companies?: number; forbidden?: boolean; switchable?: SwitcherCompany[] } = {}) {
    const calls: FakeCall[] = [];
    let seq = 100;

    const companies: CompanyRow[] = [
        { id: 1, name: 'Matriz', label: 'MTZ', label_display: 'MTZ', email: 'contato@matriz.test', phone: null, tax_id: '11.111.111/0001-11', tax_type: 'cnpj', is_default: true, is_active: true },
        { id: 2, name: 'Filial Sul', label: null, label_display: 'FS', email: null, phone: null, tax_id: null, tax_type: 'cnpj', is_default: false, is_active: false },
    ];
    for (let i = 0; i < (options.companies ?? 0); i++) {
        companies.push({ id: ++seq, name: `Zeta ${String(i).padStart(2, '0')}`, label: null, label_display: 'Z', email: null, phone: null, tax_id: null, tax_type: 'cnpj', is_default: false, is_active: true });
    }
    const departments: (DepartmentRow & { deleted?: boolean })[] = [
        { id: 1, name: 'Vendas', description: 'Time comercial', roles_count: 3, is_active: true },
        { id: 2, name: 'Compras', description: null, roles_count: 0, is_active: false },
    ];
    let switchable: SwitcherCompany[] = options.switchable ?? [
        { id: 1, name: 'Matriz', label: 'MTZ', is_default: true },
        { id: 3, name: 'Loja Centro', label: 'LC', is_default: false },
    ];
    let activeId = switchable[0]?.id ?? 0;

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    const invalid = (errors: Record<string, string>) => json(422, { message: 'The given data was invalid.', errors: Object.fromEntries(Object.entries(errors).map(([field, text]) => [field, [text]])) });
    const done = (text: string) => json(200, { ok: true, error: null, messages: [{ text, level: 'success' }] });
    const refused = (text: string) => json(422, { ok: false, error: text, messages: [{ text, level: 'danger' }] });

    function page<Row extends { name: string }>(all: Row[], query: URLSearchParams, extra: Record<string, unknown> = {}) {
        const search = (query.get('search') ?? '').toLowerCase();
        const direction = query.get('direction') === 'desc' ? 'desc' : 'asc';
        const list = all.filter((row) => search === '' || JSON.stringify(row).toLowerCase().includes(search)).sort((a, b) => a.name.localeCompare(b.name) * (direction === 'asc' ? 1 : -1));
        const last = Math.max(1, Math.ceil(list.length / PER_PAGE));
        const current = Math.min(last, Math.max(1, Number(query.get('page') ?? 1)));
        const slice = list.slice((current - 1) * PER_PAGE, current * PER_PAGE);

        return json(200, {
            data: slice,
            meta: { page: current, last_page: last, per_page: PER_PAGE, total: list.length, from: slice.length ? (current - 1) * PER_PAGE + 1 : null, to: slice.length ? (current - 1) * PER_PAGE + slice.length : null },
            state: { search, sort: 'name', direction },
            ...extra,
        });
    }

    function saveCompany(id: number | null, body: Record<string, unknown>) {
        const form = body as unknown as CompanyForm;
        const errors: Record<string, string> = {};
        if (!form.name) {
            errors.name = 'O campo nome é obrigatório.';
        }
        if (form.email && !String(form.email).includes('@')) {
            errors.email = 'O campo e-mail deve ser um endereço de e-mail válido.';
        }
        const label = String(form.label ?? '').toUpperCase();
        if (label && companies.some((company) => company.label === label && company.id !== id)) {
            errors.label = 'O valor informado para o campo sigla já está em uso.';
        }
        if (Object.keys(errors).length) {
            return invalid(errors);
        }
        const row: CompanyRow = {
            id: id ?? ++seq,
            name: form.name,
            label: label || null,
            label_display: label || form.name.slice(0, 2).toUpperCase(),
            email: form.email || null,
            phone: form.phone || null,
            tax_id: form.tax_id || null,
            tax_type: form.tax_type || null,
            is_default: Boolean(form.is_default),
            is_active: Boolean(form.is_active),
        };
        if (id === null) {
            companies.push(row);

            return done('Company created successfully!');
        }
        companies.splice(companies.findIndex((company) => company.id === id), 1, row);

        return done('Company updated successfully!');
    }

    function saveDepartment(id: number | null, body: Record<string, unknown>) {
        const form = body as unknown as DepartmentForm;
        if (!form.name) {
            return invalid({ name: 'O campo nome é obrigatório.' });
        }
        if (id === null) {
            departments.push({ id: ++seq, name: form.name, description: form.description || null, roles_count: 0, is_active: Boolean(form.is_active) });

            return done('Department created.');
        }
        const found = departments.find((department) => department.id === id)!;
        Object.assign(found, { name: form.name, description: form.description || null, is_active: Boolean(form.is_active) });

        return done('Department updated.');
    }

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(String(input), 'http://localhost');
        const path = url.pathname.replace('/ptah/api', '');
        const method = (init.method ?? 'GET').toUpperCase();
        const body = typeof init.body === 'string' ? (JSON.parse(init.body) as Record<string, unknown>) : null;
        calls.push({ method, path, query: url.searchParams, body });

        const match = path.match(/^\/admin\/(companies|departments)(?:\/(\d+))?$/);
        if (match) {
            if (options.forbidden) {
                return json(403, { message: 'Você não tem permissão para acessar esta página.' });
            }
            const [, resource, raw] = match;
            const id = raw === undefined ? null : Number(raw);

            if (resource === 'companies') {
                const company = companies.find((row) => row.id === id);
                switch (method) {
                    case 'GET':
                        if (id === null) {
                            return page(companies, url.searchParams, { tax_types: ['cnpj', 'cpf', 'ein', 'vat', 'other'] });
                        }

                        return company
                            ? json(200, { id, form: { name: company.name, label: company.label ?? '', email: company.email ?? '', phone: company.phone ?? '', tax_id: company.tax_id ?? '', tax_type: company.tax_type ?? '', is_default: company.is_default, is_active: company.is_active } })
                            : json(404, { message: 'Not found.' });
                    case 'POST':
                        return saveCompany(null, body ?? {});
                    case 'PUT':
                        return saveCompany(id, body ?? {});
                    case 'DELETE':
                        if (company?.is_default) {
                            return refused('The default company cannot be deleted.');
                        }
                        companies.splice(companies.findIndex((row) => row.id === id), 1);

                        return done('Company deleted.');
                }
            } else {
                const department = departments.find((row) => row.id === id);
                switch (method) {
                    case 'GET':
                        if (id === null) {
                            return page(departments, url.searchParams);
                        }

                        return department ? json(200, { id, form: { name: department.name, description: department.description ?? '', is_active: department.is_active } }) : json(404, { message: 'Not found.' });
                    case 'POST':
                        return saveDepartment(null, body ?? {});
                    case 'PUT':
                        return saveDepartment(id, body ?? {});
                    case 'DELETE':
                        if (!department) {
                            return refused('Error: No query results.');
                        }
                        departments.splice(departments.indexOf(department), 1);

                        return done('Department deleted.');
                }
            }
        }

        if (path === '/admin/company-switcher') {
            if (method === 'GET') {
                return json(200, { companies: switchable, active_id: activeId });
            }
            const id = Number(body?.id);
            if (!switchable.some((company) => company.id === id)) {
                return json(403, { message: 'Você não tem permissão para acessar esta página.' });
            }
            activeId = id;

            return json(200, { ok: true, companies: switchable, active_id: activeId });
        }

        return json(404, { message: `No fake for ${method} ${path}` });
    };

    return {
        api: new PtahApi({ fetch: handler as typeof fetch }),
        calls,
        companies,
        departments,
        activeId: () => activeId,
        setSwitchable: (list: SwitcherCompany[]) => {
            switchable = list;
        },
    };
}
