import { PtahApi } from '../../core/api';
import { NO_FEATURES } from '../../core/types';
import type { Field, Schema } from '../../core/types';

export interface FormCall {
    method: string;
    path: string;
    body: Record<string, unknown> | null;
}

const field = (over: Partial<Field> & Pick<Field, 'field' | 'label' | 'type'>): Field => ({
    required: false,
    readonly: false,
    options: null,
    mask: null,
    maskPatterns: null,
    placeholder: null,
    help: null,
    ...over,
});

/** The form of the Livewire modal, one of each kind it draws. */
export const formFields: Field[] = [
    field({ field: 'name', label: 'Nome', type: 'text', required: true, block: 'Dados gerais', help: 'Como aparece na lista.' }),
    field({ field: 'status', label: 'Situação', type: 'select', required: true, block: 'Dados gerais', options: [{ label: 'Ativo', value: 'active' }, { label: 'Arquivado', value: 'archived' }] }),
    field({ field: 'kind', label: 'Tipo', type: 'select', block: 'Dados gerais', options: [{ label: 'Loja', value: 'store' }] }),
    field({ field: 'secret', label: 'Senha', type: 'password', required: true }),
    field({ field: 'tier', label: 'Nível', type: 'select', readonly: true, block: 'Somente leitura', options: [{ label: 'Ouro', value: 'gold' }] }),
    field({ field: 'owner', label: 'Dono', type: 'searchdropdown', readonly: true, block: 'Somente leitura', lookup: { placeholder: null, startList: 'bottom', dependsOn: null, dependsOnLabel: null } }),
    field({ field: 'vip', label: 'VIP', type: 'switch', readonly: true, block: 'Somente leitura' }),
    field({ field: 'region', label: 'Região', type: 'searchdropdown', block: 'Endereço', lookup: { placeholder: null, startList: 'bottom', dependsOn: null, dependsOnLabel: null } }),
    field({ field: 'city', label: 'Cidade', type: 'searchdropdown', block: 'Endereço', lookup: { placeholder: 'Escolha a cidade', startList: 'top', dependsOn: 'region', dependsOnLabel: 'Região' } }),
    field({ field: 'price', label: 'Preço', type: 'text', mask: 'money_brl', block: 'Extras' }),
    field({ field: 'plate', label: 'Placa', type: 'text', mask: 'uppercase', block: 'Extras' }),
    field({ field: 'color', label: 'Cor', type: 'color', block: 'Extras' }),
    field({ field: 'weight', label: 'Peso', type: 'number', block: 'Extras' }),
    field({ field: 'photo', label: 'Foto', type: 'image', accept: '.png,.jpg', block: 'Extras' }),
];

export const PREVIEW = 'http://localhost/storage/fotos/alfa.png';

/**
 * A stand-in for the CRUD API of one screen (`items`) as the form sees it:
 * the schema with the parity keys, the modal title from the server, image
 * previews, cascading lookups with a second line, and a save that may answer
 * a redirect (name "Redirect") or a 422 (no name). `slowOpen` holds the
 * "create" answer until `release()`.
 */
export function fakeForm(options: { formDraft?: boolean; slowOpen?: boolean } = {}) {
    const calls: FormCall[] = [];
    let release: () => void = () => undefined;
    const held = new Promise<void>((resolve) => {
        release = resolve;
    });

    const schema = (): Schema => ({
        title: 'Contas',
        titleSingular: 'Conta',
        key: 'id',
        columns: [{ field: 'name', label: 'Nome', type: 'text', renderer: null, align: null, sortBy: 'name' }],
        form: formFields,
        filters: [],
        permissions: { canRead: true, canCreate: true, canUpdate: true, canDelete: true, canRestore: true, canExport: false },
        bulkActions: [],
        selectable: false,
        export: { enabled: false, formats: [] },
        trash: false,
        defaults: { sort: 'id', direction: 'DESC', per_page: 25 },
        preferences: { columns: { name: true }, column_order: [], column_widths: {}, view_mode: 'auto', view_density: 'global', saved_filters: {}, search_history: [] },
        features: { ...NO_FEATURES, formDraft: Boolean(options.formDraft) },
    });

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(String(input), 'http://localhost');
        const method = (init.method ?? 'GET').toUpperCase();
        const path = url.pathname.replace('/ptah/api/crud/items', '');
        let body: Record<string, unknown> | null = null;
        if (typeof init.body === 'string') {
            body = JSON.parse(init.body) as Record<string, unknown>;
        } else if (init.body instanceof FormData) {
            body = { data: JSON.parse(String(init.body.get('data') ?? '{}')), files: [...init.body.keys()].filter((key) => key.startsWith('files')) };
        }
        calls.push({ method, path, body });

        if (path === '/schema') {
            return json(200, schema());
        }
        if (path === '' && method === 'GET') {
            const row = { id: 1, cells: { name: { value: 'Alfa', html: 'Alfa' } }, style: null, actions: [], trashed: false };
            const state = { page: 1, per_page: 25, sort: 'id', direction: 'DESC', search: '', filters: {}, operators: {}, date_ranges: {}, date_operators: {}, quick_date: '', trashed: false };

            return json(200, { data: [row], meta: { page: 1, per_page: 25, total: 1, last_page: 1, sort: 'id', direction: 'DESC', search: '', trashed: false }, state, totals: [], messages: [] });
        }
        if (path === '/create') {
            if (options.slowOpen) {
                await held;
            }

            return json(200, { data: { status: '', price: null }, labels: [], title: 'Nova Conta', subtitle: 'Preencha os campos abaixo', previews: [] });
        }
        if (path === '/1' && method === 'GET') {
            return json(200, {
                data: { id: 1, name: 'Alfa', status: 'active', secret: '', tier: 'gold', owner: 7, vip: true, region: null, city: null, price: 1234.5, plate: 'ABC1D23', color: '#3b82f6', photo: 'fotos/alfa.png' },
                labels: { owner: 'Ana' },
                title: 'Editar Conta',
                subtitle: 'Altere os campos e salve',
                previews: { photo: PREVIEW },
            });
        }
        if (path === '/lookup/region') {
            return json(200, { data: [{ value: 'sul', label: 'Sul' }] });
        }
        if (path === '/lookup/city') {
            const context = (body?.context ?? {}) as Record<string, unknown>;

            return json(200, { data: context.region ? [{ value: 'poa', label: 'Porto Alegre', labelTwo: 'RS' }] : [] });
        }
        if ((path === '' && method === 'POST') || path === '/1') {
            const data = (body?.data ?? {}) as Record<string, unknown>;
            if (!data.name) {
                return json(422, { ok: false, id: null, errors: { name: 'Nome é obrigatório.' }, messages: [], redirect: null });
            }

            return json(path === '' ? 201 : 200, { ok: true, id: 2, errors: [], messages: [{ text: 'Salvo!', level: 'success' }], redirect: data.name === 'Redirect' ? '/elsewhere' : null });
        }

        return json(404, { message: `No fake for ${method} ${path}` });
    };

    return { api: new PtahApi({ fetch: handler as typeof fetch }), calls, release: () => release() };
}
