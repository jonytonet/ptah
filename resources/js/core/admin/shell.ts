/**
 * The layout's notification bell and exports panel — types, the API client
 * and the words. The rules (who sees what, safe URLs, ownership) are the
 * server's: the Livewire components NotificationBell and ExportsPanel.
 */
import type { PtahApi } from '../api';

// ── Types ───────────────────────────────────────────────────────────────────

export interface ShellNotification {
    id: number;
    type: string | null;
    title: string;
    body: string | null;
    icon: string | null;
    /** Already checked by the server: a dangerous scheme comes back null. */
    url: string | null;
    action_label: string | null;
    read: boolean;
    created_at: string | null;
    created_at_human: string | null;
}

export interface ShellMessage {
    text: string;
    level: string;
}

export interface BellState {
    /** `false` when ptah.notifications.enabled is off: draw nothing. */
    enabled: boolean;
    unread: number;
    /** The configured polling interval, null when polling is off. */
    poll_ms: number | null;
    /** Only when asked for (the dropdown is open). */
    items: ShellNotification[] | null;
    view_all_url: string | null;
    ok?: boolean;
    redirect?: string | null;
    messages?: ShellMessage[];
}

export type ExportStatus = 'queued' | 'processing' | 'done' | 'failed' | string;

export interface ExportRow {
    id: number;
    model: string;
    format: string;
    status: ExportStatus;
    error: string | null;
    rows: number | null;
    created_at: string | null;
    created_at_display: string | null;
    expired: boolean;
    /** Only when the file can be downloaded (done, not expired). */
    download_url: string | null;
}

export interface ExportsListing {
    data: ExportRow[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
    has_pending: boolean;
    /** Set while an export is still queued/processing, null once all have settled. */
    poll_ms: number | null;
    ok?: boolean;
}

// ── Client ──────────────────────────────────────────────────────────────────

export function fetchBell(api: PtahApi, withItems = false): Promise<BellState> {
    return api.call('GET', `/notifications${withItems ? '?items=1' : ''}`);
}

/** Marks it read; the answer says where to go (`redirect`). */
export function openNotification(api: PtahApi, id: number): Promise<BellState> {
    return api.call('POST', `/notifications/${id}/open`);
}

export function markAllNotificationsRead(api: PtahApi): Promise<BellState> {
    return api.call('POST', '/notifications/read-all');
}

export function dismissNotification(api: PtahApi, id: number): Promise<BellState> {
    return api.call('POST', `/notifications/${id}/dismiss`);
}

export function fetchExports(api: PtahApi, page = 1): Promise<ExportsListing> {
    return api.call('GET', `/exports?page=${page}`);
}

/** Deletes the file and the row; answers the page again. */
export function removeExport(api: PtahApi, id: number, page = 1): Promise<ExportsListing> {
    return api.call('DELETE', `/exports/${id}?page=${page}`);
}

// ── Helpers ─────────────────────────────────────────────────────────────────

/** The badge text: the count, or `99+`. */
export function badgeText(count: number): string {
    return count > 99 ? '99+' : String(count);
}

/** The forge colour of an export status; an unknown one is neutral. */
export function exportStatusColor(status: ExportStatus): 'warn' | 'primary' | 'success' | 'danger' | 'dark' {
    return ({ queued: 'warn', processing: 'primary', done: 'success', failed: 'danger' } as const)[status as 'queued'] ?? 'dark';
}

/**
 * Runs `task` every `interval` ms until the returned function is called. A
 * hidden tab does not poll; when it shows again the task runs at once and
 * the timer starts over. No interval (null, 0) means no polling.
 */
export function startPolling(task: () => void, interval: number | null | undefined): () => void {
    if (!interval || interval <= 0 || typeof document === 'undefined') {
        return () => undefined;
    }
    let timer: ReturnType<typeof setInterval> | null = null;
    const start = () => {
        if (timer === null) {
            timer = setInterval(task, interval);
        }
    };
    const stop = () => {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    };
    const onVisibility = () => {
        if (document.hidden) {
            stop();
        } else if (timer === null) {
            task();
            start();
        }
    };

    document.addEventListener('visibilitychange', onVisibility);
    if (!document.hidden) {
        start();
    }

    return () => {
        stop();
        document.removeEventListener('visibilitychange', onVisibility);
    };
}

// ── Words ───────────────────────────────────────────────────────────────────

const en = {
    bellTitle: 'Notifications',
    unreadBadge: (count: number) => `${count} unread notification(s)`,
    empty: 'No notifications.',
    actionDefault: 'View details',
    markAllRead: 'Mark all as read',
    viewAll: 'View all',
    dismiss: 'Dismiss',
    loading: 'Loading…',
    error: 'Something went wrong. Try again.',
    exportsTitle: 'Exports',
    colModel: 'Model',
    colFormat: 'Format',
    colStatus: 'Status',
    colRows: 'Rows',
    colCreated: 'Requested at',
    colActions: 'Actions',
    exportsEmpty: 'No exports yet.',
    removeConfirm: 'Remove this export? The file will be permanently deleted.',
    download: 'Download',
    delete: 'Delete',
    cancel: 'Cancel',
    status: { queued: 'Queued', processing: 'Processing', done: 'Done', failed: 'Failed' } as Record<string, string>,
};

export type ShellWords = typeof en;

const ptBR: ShellWords = {
    bellTitle: 'Notificações',
    unreadBadge: (count: number) => `${count} notificação(ões) não lida(s)`,
    empty: 'Nenhuma notificação.',
    actionDefault: 'Ver detalhes',
    markAllRead: 'Marcar todas como lidas',
    viewAll: 'Ver todas',
    dismiss: 'Dispensar',
    loading: 'Carregando…',
    error: 'Algo deu errado. Tente novamente.',
    exportsTitle: 'Exportações',
    colModel: 'Modelo',
    colFormat: 'Formato',
    colStatus: 'Status',
    colRows: 'Linhas',
    colCreated: 'Solicitado em',
    colActions: 'Ações',
    exportsEmpty: 'Nenhuma exportação ainda.',
    removeConfirm: 'Remover esta exportação? O arquivo será apagado definitivamente.',
    download: 'Baixar',
    delete: 'Excluir',
    cancel: 'Cancelar',
    status: { queued: 'Na fila', processing: 'Processando', done: 'Concluído', failed: 'Falhou' },
};

const dictionaries: Record<string, ShellWords> = { en, pt: ptBR };

/** The words for a locale (`pt_BR`, `pt-BR`, `en`…); English when unknown. */
export function shellWords(locale?: string | null): ShellWords {
    const lang = (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').toLowerCase().slice(0, 2);

    return dictionaries[lang] ?? en;
}
