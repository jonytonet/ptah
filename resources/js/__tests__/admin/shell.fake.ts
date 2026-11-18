import { PtahApi } from '../../core/api';
import type { BellState, ExportRow, ExportsListing, ShellNotification } from '../../core/admin/shell';

export interface ShellCall {
    method: string;
    path: string;
}

const notification = (id: number, extra: Partial<ShellNotification> = {}): ShellNotification => ({
    id,
    type: 'info',
    title: `Aviso ${id}`,
    body: null,
    icon: null,
    url: null,
    action_label: null,
    read: false,
    created_at: '2026-11-18T10:00:00+00:00',
    created_at_human: 'há 5 minutos',
    ...extra,
});

const exportRow = (id: number, extra: Partial<ExportRow> = {}): ExportRow => ({
    id,
    model: 'Order',
    format: 'excel',
    status: 'done',
    error: null,
    rows: 10,
    created_at: '2026-11-18T10:00:00+00:00',
    created_at_display: '18/11/2026 10:00:00',
    expired: false,
    download_url: `/ptah/export/file/${id}`,
    ...extra,
});

/**
 * A stand-in for the shell API (notification bell + exports panel): the same
 * routes and shapes as ShellExtrasApiController. Notification 99 and export
 * 99 belong to another user (404).
 */
export function fakeShell(options: { enabled?: boolean; poll?: number | null; pendingExports?: boolean; manyExports?: boolean; noExports?: boolean; noNotifications?: boolean } = {}) {
    const calls: ShellCall[] = [];
    let notifications: ShellNotification[] = options.noNotifications
        ? []
        : [
              notification(1, { title: 'Pedido aprovado', body: 'O pedido #7 foi aprovado.', url: '/orders/7', action_label: 'Abrir pedido' }),
              notification(2, { title: 'Backup concluído' }),
          ];
    let exports: ExportRow[] = options.noExports
        ? []
        : options.manyExports
        ? Array.from({ length: 12 }, (_, index) => exportRow(index + 1, { model: `Model${index + 1}` }))
        : [
              exportRow(1),
              exportRow(2, { model: 'Customer', status: 'failed', error: 'Sem memória', download_url: null, rows: null }),
              exportRow(3, { model: 'Invoice', status: options.pendingExports ? 'processing' : 'done', download_url: options.pendingExports ? null : '/ptah/export/file/3' }),
          ];

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    const unread = () => notifications.filter((item) => !item.read).length;
    const bell = (withItems: boolean, extra: Partial<BellState> = {}): BellState => ({
        enabled: true,
        unread: unread(),
        poll_ms: options.poll === undefined ? 60000 : options.poll,
        items: withItems ? notifications : null,
        view_all_url: null,
        ...extra,
    });
    const listing = (page: number, extra: Partial<ExportsListing> = {}): ExportsListing => {
        const last = Math.max(1, Math.ceil(exports.length / 10));
        const current = Math.min(Math.max(1, page), last);
        const pending = exports.some((row) => row.status === 'queued' || row.status === 'processing');

        return {
            data: exports.slice((current - 1) * 10, current * 10),
            meta: { current_page: current, last_page: last, per_page: 10, total: exports.length },
            has_pending: pending,
            poll_ms: pending ? 5000 : null,
            ...extra,
        };
    };

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(String(input), 'http://localhost');
        const path = url.pathname.replace('/ptah/api', '');
        const method = (init.method ?? 'GET').toUpperCase();
        calls.push({ method, path: `${path}${url.search}` });

        if (path.startsWith('/notifications') && options.enabled === false) {
            return method === 'GET' ? json(200, { enabled: false, unread: 0, poll_ms: null, items: null, view_all_url: null }) : json(404, { message: 'Not Found' });
        }

        if (method === 'GET' && path === '/notifications') {
            return json(200, bell(url.searchParams.get('items') === '1'));
        }
        if (method === 'POST' && path === '/notifications/read-all') {
            const count = unread();
            notifications = notifications.map((item) => ({ ...item, read: true }));

            return json(200, { ok: true, messages: [{ text: count > 0 ? `${count} notificações marcadas como lidas.` : 'Nenhuma notificação não lida.', level: count > 0 ? 'success' : 'primary' }], ...bell(true) });
        }
        const open = path.match(/^\/notifications\/(\d+)\/open$/);
        if (method === 'POST' && open) {
            const item = notifications.find((row) => row.id === Number(open[1]));
            if (!item) {
                return json(404, { message: 'Not Found' });
            }
            item.read = true;

            return json(200, { ok: true, redirect: item.url, ...bell(true) });
        }
        const dismiss = path.match(/^\/notifications\/(\d+)\/dismiss$/);
        if (method === 'POST' && dismiss) {
            notifications = notifications.filter((row) => row.id !== Number(dismiss[1]));

            return json(200, { ok: true, ...bell(true) });
        }

        if (method === 'GET' && path === '/exports') {
            return json(200, listing(Number(url.searchParams.get('page') ?? 1)));
        }
        const remove = path.match(/^\/exports\/(\d+)$/);
        if (method === 'DELETE' && remove) {
            const id = Number(remove[1]);
            if (!exports.some((row) => row.id === id)) {
                return json(404, { message: 'Not Found' });
            }
            exports = exports.filter((row) => row.id !== id);

            return json(200, { ok: true, ...listing(Number(url.searchParams.get('page') ?? 1)) });
        }

        return json(404, { message: `No fake for ${method} ${path}` });
    };

    return {
        api: new PtahApi({ fetch: handler as typeof fetch }),
        calls,
        /** The server side changes between two polls. */
        push: (extra: Partial<ShellNotification> = {}) => {
            notifications = [notification(notifications.length + 10, extra), ...notifications];
        },
        settle: () => {
            exports = exports.map((row) => (row.status === 'processing' ? { ...row, status: 'done', download_url: `/ptah/export/file/${row.id}` } : row));
        },
        exports: () => exports,
        notifications: () => notifications,
    };
}
