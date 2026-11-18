import { PtahApi } from '../../core/api';
import { CrudController } from '../../core/controller';
import { NO_FEATURES } from '../../core/types';
import type { Features, Listing, Row } from '../../core/types';
import type { AttachmentItem, HistoryEntry } from '../../core/crud/records';
import { schema } from '../fake-api';

export interface RecordsCall {
    method: string;
    path: string;
}

const row = (id: number, name: string, trashed = false): Row => ({
    id,
    cells: { id: { value: id, html: String(id) }, name: { value: name, html: name } },
    style: null,
    actions: [],
    trashed,
});

/**
 * A stand-in for the records API: the same routes and shapes as
 * RecordsApiController, over records 1 and 2 (record 9 is out of the
 * screen's scope: 404). An upload of a `.php` file is a 422, as the
 * engine's `mimes` rule.
 */
export function fakeRecords(options: { features?: Partial<Features>; empty?: boolean; forbidden?: boolean } = {}) {
    const calls: RecordsCall[] = [];
    const features: Features = { ...NO_FEATURES, history: true, attachments: true, attachmentsWrite: true, ...options.features };
    const history: Record<number, HistoryEntry[]> = options.empty
        ? {}
        : {
              1: [
                  { when: '18/11/2026 10:00', who: 'Ana Souza', event: 'updated', changes: [{ label: 'Situação', old: 'Aberto', new: 'Fechado' }], hidden: 2 },
                  { when: '17/11/2026 09:00', who: 'sistema', event: 'created', changes: [{ label: 'Código', old: '—', new: 'PED-1' }], hidden: 0 },
              ],
          };
    const files: Record<number, AttachmentItem[]> = options.empty
        ? {}
        : { 1: [{ id: 50, name: 'nota.pdf', size: '120 KB', when: '18/11/2026 10:00', mime: 'application/pdf', url: '/ptah/api/crud/items/1/attachments/50/download' }], 2: [] };
    let nextId = 100;

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    const listOf = (id: number) => ({ data: files[id] ?? [], count: (files[id] ?? []).length });

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(String(input), 'http://localhost');
        const path = url.pathname.replace('/ptah/api', '');
        const method = (init.method ?? 'GET').toUpperCase();
        calls.push({ method, path: `${path}${url.search}` });

        if (path === '/crud/items/schema') {
            return json(200, { ...schema, features });
        }
        if (path === '/crud/items' && method === 'GET') {
            const listing: Listing = {
                data: [row(1, 'Alfa'), row(2, 'Beta'), row(3, 'Gama', true)],
                meta: { page: 1, per_page: 25, total: 3, last_page: 1, sort: 'id', direction: 'DESC', search: '', trashed: false },
                state: { page: 1, per_page: 25, sort: 'id', direction: 'DESC', search: '', filters: {}, operators: {}, date_ranges: {}, date_operators: {}, quick_date: '', trashed: false },
                totals: [],
                messages: [],
            } as Listing;

            return json(200, listing);
        }
        if (path === '/crud/items/attachments/counts') {
            const counts: Record<string, number> = {};
            url.searchParams.getAll('ids[]').forEach((id) => (counts[id] = (files[Number(id)] ?? []).length));

            return json(200, { counts });
        }

        if (options.forbidden) {
            return json(403, { message: 'This action is unauthorized.' });
        }

        const match = path.match(/^\/crud\/items\/(\d+)\/(history|attachments)(?:\/(\d+))?$/);
        if (match) {
            const id = Number(match[1]);
            if (id === 9) {
                return json(404, { message: 'Not Found' });
            }
            if (match[2] === 'history') {
                return json(200, { data: history[id] ?? [] });
            }
            if (method === 'GET') {
                return json(200, listOf(id));
            }
            if (method === 'POST') {
                const sent = (init.body as FormData).getAll('files[]') as File[];
                if (sent.length === 0) {
                    return json(422, { message: 'The files field is required.', errors: { files: ['The files field is required.'] } });
                }
                const bad = sent.findIndex((file) => file.name.endsWith('.php'));
                if (bad >= 0) {
                    return json(422, { message: 'invalid', errors: { [`files.${bad}`]: [`The files.${bad} field must be a file of type: pdf, png.`] } });
                }
                files[id] = [...sent.map((file) => ({ id: ++nextId, name: file.name, size: '1 KB', when: '18/11/2026 11:00', mime: file.type, url: `/ptah/api/crud/items/${id}/attachments/${nextId}/download` })).reverse(), ...(files[id] ?? [])];

                return json(201, { ok: true, ...listOf(id), messages: [{ text: 'Arquivos anexados.', level: 'success' }] });
            }
            if (method === 'DELETE' && match[3]) {
                const attachment = Number(match[3]);
                if (!(files[id] ?? []).some((item) => item.id === attachment)) {
                    return json(404, { message: 'Not Found' });
                }
                files[id] = (files[id] ?? []).filter((item) => item.id !== attachment);

                return json(200, { ok: true, ...listOf(id), messages: [] });
            }
        }

        return json(404, { message: `No route ${method} ${path}` });
    };

    const api = new PtahApi({ fetch: handler as typeof fetch });

    /** A screen controller over this fake, with its schema and first page loaded. */
    const controller = async () => {
        const crud = new CrudController('items', { api, remember: false, toastMs: 0 });
        await crud.init();

        return crud;
    };

    return { api, calls, controller, files: () => files };
}
