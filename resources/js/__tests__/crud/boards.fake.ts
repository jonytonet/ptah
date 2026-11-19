import { PtahApi } from '../../core/api';
import { CrudController } from '../../core/controller';
import { NO_FEATURES } from '../../core/types';
import type { Listing, Preferences, Row, Schema } from '../../core/types';
import type { CalendarDay, KanbanBoard } from '../../core/crud/boards';

export interface BoardsCall {
    method: string;
    path: string;
    query: URLSearchParams;
    body: Record<string, unknown> | null;
}

interface Task {
    id: number;
    title: string;
    status: string;
    note: string;
    due: string;
}

export interface BoardsFakeOptions {
    kanban?: boolean;
    calendar?: boolean;
    /** Every move is refused by the server (a hook, a rule the client cannot know). */
    refuseMoves?: boolean;
    /** The board answers 403. */
    forbidden?: boolean;
    empty?: boolean;
    overflow?: boolean;
    canUpdate?: boolean;
    /** `ptah.crud.responsive_cards` (default on). */
    responsiveCards?: boolean;
    /** The record history is on (the History button of each row). */
    history?: boolean;
    /** The saved view mode. */
    viewMode?: string;
}

const OPTIONS = [
    { label: 'A fazer', value: 'todo' },
    { label: 'Fazendo', value: 'doing' },
    { label: 'Feito', value: 'done' },
];
const OTHER = '__ptah_other__';
const TARGETS: Record<string, string[]> = { todo: ['doing'], doing: ['todo'], done: [], [OTHER]: ['todo', 'doing'] };
const LOCKED = ['done'];
const LOCKED_MESSAGE = 'Concluir é feito pelo painel do setor.';

/**
 * A stand-in for the CRUD API and the boards API of one screen (`tasks`):
 * the same routes and shapes as CrudApiController and BoardsApiController.
 * `done` is a locked column; `todo` ↔ `doing` are the only transitions.
 */
export function fakeBoards(options: BoardsFakeOptions = {}) {
    const calls: BoardsCall[] = [];
    let tasks: Task[] = options.empty
        ? []
        : [
              { id: 1, title: 'Orçar cliente A', status: 'todo', note: 'urgente', due: '2026-09-10' },
              { id: 2, title: 'Entregar pedido B', status: 'doing', note: '', due: '2026-09-15' },
              { id: 3, title: 'Fechar caixa', status: 'done', note: '', due: '2026-09-15' },
              { id: 4, title: 'Aguardando peça', status: 'waiting', note: '', due: '2026-10-02' },
          ];
    let preferences: Preferences = { columns: { id: true, title: true, note: true, status: true }, column_order: [], column_widths: {}, view_mode: options.viewMode ?? 'auto', view_density: 'global', saved_filters: {}, search_history: [] };

    const schema = (): Schema => ({
        title: 'Tarefas',
        titleSingular: 'Tarefa',
        key: 'id',
        columns: [
            { field: 'id', label: 'ID', type: 'number', renderer: null, align: null, sortBy: 'id' },
            { field: 'title', label: 'Título', type: 'text', renderer: null, align: null, sortBy: 'title' },
            { field: 'note', label: 'Nota', type: 'text', renderer: null, align: null, sortBy: null },
            { field: 'status', label: 'Situação', type: 'select', renderer: null, align: null, sortBy: 'status' },
        ],
        form: [],
        filters: [],
        permissions: { canRead: true, canCreate: true, canUpdate: options.canUpdate ?? true, canDelete: true, canRestore: true, canExport: false },
        bulkActions: [],
        selectable: true,
        export: { enabled: false, formats: [] },
        trash: false,
        defaults: { sort: 'id', direction: 'DESC', per_page: 25 },
        preferences,
        features: { ...NO_FEATURES, kanban: options.kanban ?? true, calendar: options.calendar ?? true, history: options.history ?? false, responsiveCards: options.responsiveCards ?? true },
    });

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    const visible = (query: URLSearchParams) => {
        const search = (query.get('search') ?? '').toLowerCase();

        return tasks.filter((task) => task.title.toLowerCase().includes(search));
    };
    const label = (status: string) => OPTIONS.find((option) => option.value === status)?.label ?? status;
    const toRow = (task: Task): Row => ({
        id: task.id,
        cells: {
            id: { value: task.id, html: String(task.id) },
            title: { value: task.title, html: `<strong>${task.title}</strong>` },
            note: { value: task.note, html: task.note },
            status: { value: task.status, html: label(task.status) },
        },
        style: task.id === 1 ? 'color: red' : null,
        actions: task.id === 1 ? [{ label: 'Aprovar', type: 'action', value: 'approve', icon: null, color: 'primary', confirm: 'Aprovar esta tarefa?' }] : [],
        // configLinkLinha on task 2 only.
        link: task.id === 2 ? '/tarefas/2' : null,
        trashed: false,
    });

    const listing = (query: URLSearchParams): Listing => {
        const found = visible(query);
        const sort = (query.get('sort') ?? 'id') as keyof Task;
        const direction = (query.get('direction') ?? 'DESC').toUpperCase();
        found.sort((a, b) => (a[sort] > b[sort] ? 1 : -1) * (direction === 'ASC' ? 1 : -1));

        return {
            data: found.map(toRow),
            meta: { page: 1, per_page: 25, total: found.length, last_page: 1, sort: String(sort), direction, search: query.get('search') ?? '', trashed: false },
            state: { page: 1, per_page: 25, sort: String(sort), direction, search: query.get('search') ?? '', filters: {}, operators: {}, date_ranges: {}, date_operators: {}, quick_date: '', trashed: false },
            totals: [],
            messages: [],
        };
    };

    const board = (query: URLSearchParams): KanbanBoard => {
        const found = visible(query);
        const column = (value: string, title: string, items: Task[]) => ({
            label: title,
            value,
            total: items.length,
            cards: items.map((task) => ({ id: task.id, title: task.title, subtitle: task.note })),
            locked: LOCKED.includes(value),
            other: value === OTHER,
        });
        const columns = OPTIONS.map((option) => column(option.value, option.label, found.filter((task) => task.status === option.value)));
        const others = found.filter((task) => !OPTIONS.some((option) => option.value === task.status));
        if (others.length > 0) {
            columns.push(column(OTHER, 'Outros valores', others));
        }
        const canMove = options.canUpdate ?? true;

        return { columns, options: OPTIONS, targets: canMove ? TARGETS : {}, can_move: canMove, locked_message: LOCKED_MESSAGE };
    };

    // The real grid comes from the engine; this one is enough for a month view.
    const calendar = (query: URLSearchParams) => {
        const base = /^\d{4}-\d{2}$/.test(query.get('month') ?? '') ? (query.get('month') as string) : '2026-09';
        const [year, month] = base.split('-').map(Number);
        const first = new Date(Date.UTC(year, month - 1 + Number(query.get('shift') ?? 0), 1));
        const shown = `${first.getUTCFullYear()}-${String(first.getUTCMonth() + 1).padStart(2, '0')}`;
        const found = visible(query);
        const start = new Date(first);
        start.setUTCDate(1 - first.getUTCDay());
        const weeks: CalendarDay[][] = [];
        const cursor = new Date(start);
        do {
            const week: CalendarDay[] = [];
            for (let i = 0; i < 7; i++) {
                const date = cursor.toISOString().slice(0, 10);
                week.push({
                    date,
                    day: cursor.getUTCDate(),
                    in_month: date.slice(0, 7) === shown,
                    today: date === '2026-09-20',
                    items: found.filter((task) => task.due === date).map((task) => ({ id: task.id, title: task.title, subtitle: task.note })),
                });
                cursor.setUTCDate(cursor.getUTCDate() + 1);
            }
            weeks.push(week);
        } while (cursor.toISOString().slice(0, 7) === shown);

        return { month: shown, weeks, overflow: options.overflow ?? false };
    };

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const url = new URL(String(input), 'http://localhost');
        const path = url.pathname.replace('/ptah/api', '');
        const method = (init.method ?? 'GET').toUpperCase();
        const body = typeof init.body === 'string' ? (JSON.parse(init.body) as Record<string, unknown>) : null;
        calls.push({ method, path, query: url.searchParams, body });

        if (path === '/crud/tasks/schema') {
            return json(200, schema());
        }
        if (path === '/crud/tasks' && method === 'GET') {
            return json(200, listing(url.searchParams));
        }
        if (path === '/crud/tasks/preferences' && method === 'PUT') {
            preferences = { ...preferences, ...(body as Partial<Preferences>) };

            return json(200, preferences);
        }
        if (path === '/crud/tasks/kanban') {
            if (!(options.kanban ?? true)) {
                return json(404, { message: 'Not Found' });
            }

            return options.forbidden ? json(403, { message: 'Você não tem permissão para realizar esta ação.' }) : json(200, board(url.searchParams));
        }
        if (path === '/crud/tasks/calendar') {
            return options.forbidden ? json(403, { message: 'Você não tem permissão para realizar esta ação.' }) : json(200, calendar(url.searchParams));
        }

        const move = path.match(/^\/crud\/tasks\/(\d+)\/move$/);
        if (move && method === 'POST') {
            const task = tasks.find((item) => item.id === Number(move[1]));
            if (!task) {
                return json(404, { message: 'Not Found' });
            }
            const value = String(body?.value ?? '');
            const from = OPTIONS.some((option) => option.value === task.status) ? task.status : OTHER;
            if (options.refuseMoves) {
                return json(422, { ok: false, messages: [{ text: 'Recusado pelo servidor.', level: 'danger' }] });
            }
            if (!(TARGETS[from] ?? []).includes(value)) {
                return json(422, { ok: false, messages: [{ text: LOCKED_MESSAGE, level: 'warn' }] });
            }
            tasks = tasks.map((item) => (item.id === task.id ? { ...item, status: value } : item));

            return json(200, { ok: true, messages: [{ text: 'Registro salvo com sucesso', level: 'success' }] });
        }

        const show = path.match(/^\/crud\/tasks\/(\d+)$/);
        if (show && method === 'GET') {
            const task = tasks.find((item) => item.id === Number(show[1]));

            return task ? json(200, { data: { ...task }, labels: {} }) : json(404, { message: 'Not Found' });
        }

        return json(404, { message: `No route ${method} ${path}` });
    };

    const api = new PtahApi({ fetch: handler as typeof fetch });

    /** A controller over this server, with the screen loaded. */
    const controller = async () => {
        const crud = new CrudController('tasks', { api, toastMs: 0 });
        await crud.init();

        return crud;
    };

    return { api, calls, controller, tasks: () => tasks, preferences: () => preferences };
}

/** The texts of the toasts the controller holds. */
export const toasts = (crud: CrudController) => crud.getState().toasts.map((toast) => toast.text);
