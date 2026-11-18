import { PtahApi } from '../../core/api';
import { CrudController } from '../../core/controller';
import type { Listing } from '../../core/types';
import { schema } from '../fake-api';

export interface ImportCall {
    method: string;
    path: string;
    body: Record<string, unknown> | null;
    /** The name of the uploaded file, on the upload. */
    file: string | null;
}

const TOKEN = 'T'.repeat(40);

/**
 * A stand-in for the import API: the same routes and shapes as
 * ImportApiController, over a spreadsheet with the headers SKU, Nome,
 * Situação (auto-mapped: SKU and Nome). File names choose the case:
 * `vazio.csv` is refused, `ruim.csv` has a row with problems. A token is
 * gone after a run, and with `expired` from the start.
 */
export function fakeImport(options: { off?: boolean; expired?: boolean; dbRefuses?: boolean; failInfo?: boolean } = {}) {
    const calls: ImportCall[] = [];
    let file = '';
    let live = false;
    let listings = 0;
    let rows = 3;

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

    const listing = (): Listing => ({
        data: [],
        meta: { page: 1, per_page: 25, total: rows, last_page: 1, sort: 'id', direction: 'DESC', search: '', trashed: false },
        state: { page: 1, per_page: 25, sort: 'id', direction: 'DESC', search: '', filters: {}, operators: {}, date_ranges: {}, date_operators: {}, quick_date: '', trashed: false },
        totals: [],
        messages: [],
    });

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(String(input), 'http://localhost');
        const path = url.pathname.replace('/ptah/api', '');
        const method = (init.method ?? 'GET').toUpperCase();
        const form = init.body instanceof FormData ? init.body : null;
        const body = typeof init.body === 'string' ? (JSON.parse(init.body) as Record<string, unknown>) : null;
        const upload = form?.get('file');
        calls.push({ method, path, body, file: upload instanceof File ? upload.name : null });

        if (path === '/crud/items/schema') {
            return json(200, { ...schema, features: { ...schema.features, import: !options.off } });
        }
        if (path === '/crud/items' && method === 'GET') {
            listings++;

            return json(200, listing());
        }
        if (options.off && path.startsWith('/crud/items/import')) {
            return json(403, { message: 'Você não tem permissão para realizar esta ação.' });
        }
        if (path === '/crud/items/import' && method === 'GET') {
            if (options.failInfo) {
                return json(500, { message: 'Server Error' });
            }

            return json(200, { max_rows: 2000, max_kb: 10240, extensions: ['csv', 'txt', 'xlsx', 'xls'], fields: [
                { field: 'sku', label: 'SKU', required: true },
                { field: 'name', label: 'Nome', required: true },
                { field: 'status', label: 'Situação', required: true },
            ] });
        }
        if (path === '/crud/items/import' && method === 'POST') {
            file = upload instanceof File ? upload.name : '';
            if (file === 'vazio.csv') {
                return json(422, { ok: false, errors: { file: 'O arquivo não tem linhas de dados abaixo do cabeçalho.' } });
            }
            live = !options.expired;

            return json(200, {
                ok: true,
                token: TOKEN,
                headers: ['SKU', 'Nome', 'Situação'],
                mapping: { 0: 'sku', 1: 'name' },
                fields: [
                    { field: 'sku', label: 'SKU', required: true },
                    { field: 'name', label: 'Nome', required: true },
                    { field: 'status', label: 'Situação', required: true },
                ],
            });
        }

        const step = path.match(/^\/crud\/items\/import\/(preview|run)$/);
        if (step && method === 'POST') {
            if (body?.token !== TOKEN || !live) {
                return json(404, { message: 'Not Found' });
            }
            const mapping = (body.mapping ?? {}) as Record<string, string>;
            const fields = Object.values(mapping);
            const unmapped = [['sku', 'SKU'], ['name', 'Nome'], ['status', 'Situação']].filter(([field]) => !fields.includes(field)).map(([, label]) => label);
            const errors = file === 'ruim.csv' ? [{ line: 3, field: 'status', message: 'O campo Situação é inválido.' }] : [];
            const preview = { total: 2, valid: 2 - errors.length, errors, more_errors: 0, unmapped_required: unmapped };

            if (step[1] === 'preview') {
                return json(200, { ok: true, preview });
            }
            if (errors.length > 0 || unmapped.length > 0) {
                return json(422, { ok: false, preview, messages: [] });
            }
            live = false;
            if (options.dbRefuses) {
                return json(422, { ok: false, result: { created: 0, updated: 0, error: 'Nada foi importado — a linha 3 foi recusada: duplicado' }, messages: [] });
            }
            rows += 2;

            return json(200, {
                ok: true,
                result: { created: 2, updated: 0, error: null },
                messages: [{ text: 'Importação concluída: 2 criados, 0 atualizados.', level: 'success' }],
            });
        }
        if (path === `/crud/items/import/${TOKEN}` && method === 'DELETE') {
            live = false;

            return json(200, { ok: true });
        }

        return json(404, { message: 'Not Found' });
    };

    const api = new PtahApi({ fetch: handler as typeof fetch });

    return {
        api,
        calls,
        token: TOKEN,
        listings: () => listings,
        /** A screen controller over this server, loaded. */
        async crud(): Promise<CrudController> {
            const crud = new CrudController('items', { api, toastMs: 0, remember: false });
            await crud.init();

            return crud;
        },
    };
}
