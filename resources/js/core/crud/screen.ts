/**
 * The BaseCrud screen itself — toolbar, table, row buttons, pagination, bulk
 * bar, shortcuts — as base-crud.blade.php and its partials draw it. Framework-
 * free: the words (the lang file's, `ptah::ui.*`) and the measuring of the
 * toolbar labels; the markup is in react/PtahCrud.tsx and vue/PtahCrud.vue.
 */

import type { ListState, Permissions, Row } from '../types';

const en = {
    newButton: 'New', // btn_new
    searchPlaceholder: 'Search...', // search_placeholder
    filters: 'Filters', // btn_filters
    trash: 'Trash', // btn_trash
    back: 'Back', // btn_back
    viewTrash: 'View deleted', // btn_view_trash
    viewActive: 'View active', // btn_view_active
    export: 'Export', // btn_export
    print: 'Print', // btn_print
    exportAsync: 'Export in background', // btn_export_async
    exportLimit: 'Limit', // export_limit_badge
    columns: 'Columns', // btn_columns
    more: 'More actions', // toolbar_more
    moreHide: 'Fewer actions', // toolbar_more_hide
    refresh: 'Refresh', // btn_refresh
    clearFilters: 'Clear filters', // btn_clear_filters
    perPageLabel: 'Rows per page', // per_page_label
    perPageSuffix: '/ page', // per_page_suffix
    edit: 'Edit', // btn_edit_title
    duplicate: 'Duplicate record', // btn_duplicate_title
    delete: 'Delete', // btn_delete_title
    restore: 'Restore', // btn_restore_title
    actions: 'Actions', // col_actions
    action: 'Action', // col_default_action
    selectAll: 'Select all rows', // bulk_select_all
    selectRow: 'Select row :id', // bulk_select_row
    sortBy: 'Sort by :column', // sort_by_column
    dragColumn: 'Drag to reorder', // col_drag_title
    emptyTitle: 'No records found', // empty_title
    emptySubtitle: 'Adjust filters or add a new item', // empty_subtitle
    emptyFilteredTitle: 'No results found', // empty_filtered_title
    emptyFilteredSubtitle: 'Try adjusting or clearing your active filters', // empty_filtered_subtitle
    pagination: 'Showing :first–:last of :total records', // pagination
    goTo: 'Go to:', // pagination_goto
    previous: '← Previous', // pagination_previous
    next: 'Next →', // pagination_next
    previousPage: 'Previous page', // pagination_previous_page
    nextPage: 'Next page', // pagination_next_page
    currentPage: 'Current page', // pagination_current_page
    pageOf: 'Page :current of :last', // pagination_page_of
    paginationNav: 'Pagination', // pagination_nav_label
    nSelected: ':n selected', // bulk_n_selected
    bulkDelete: 'Delete selected', // bulk_delete_btn
    bulkForceDelete: 'Empty trash', // bulk_force_delete_btn
    bulkRestore: 'Restore selected', // bulk_restore_btn
    bulkCancel: 'Cancel selection', // bulk_cancel
    bulkDeleteConfirm: 'Delete :n selected record(s)? This action cannot be undone.', // bulk_delete_confirm
    bulkForceConfirm: 'Permanently remove :n record(s)? This action is irreversible.', // bulk_force_delete_confirm
    bulkIrreversible: 'This action cannot be undone.', // bulk_force_irreversible
    bulkActionConfirm: 'Apply to the :n selected record(s)?', // bulk_action_confirm
    cancel: 'Cancel', // btn_cancel
    deleteButton: 'Delete', // btn_delete
    confirm: 'Confirm',
    close: 'Close', // modal_close
    subtotal: 'Subtotal (:n records)', // break_subtotal
    sum: 'Sum', // export_sum
    avg: 'Average', // export_avg
    count: 'Count', // export_count
    min: 'Minimum', // export_min
    max: 'Maximum', // export_max
    shortcutsTitle: 'Keyboard shortcuts', // shortcuts_title
    shortcutsSearch: 'Focus search', // shortcuts_search
    shortcutsNew: 'New record', // shortcuts_new
    shortcutsFilters: 'Toggle the filter panel', // shortcuts_filters
    shortcutsView: 'Switch table / cards', // shortcuts_view_mode
    shortcutsRefresh: 'Reload the listing', // shortcuts_refresh
    shortcutsSidebar: 'Collapse / expand the menu', // shortcuts_sidebar
    shortcutsHelp: 'Show this list', // shortcuts_help
};

export type ScreenWords = typeof en;

const ptBR: ScreenWords = {
    newButton: 'Novo',
    searchPlaceholder: 'Buscar...',
    filters: 'Filtros',
    trash: 'Lixeira',
    back: 'Voltar',
    viewTrash: 'Ver excluídos',
    viewActive: 'Ver ativos',
    export: 'Exportar',
    print: 'Imprimir',
    exportAsync: 'Exportar em segundo plano',
    exportLimit: 'Limite',
    columns: 'Colunas',
    more: 'Mais ações',
    moreHide: 'Menos ações',
    refresh: 'Atualizar',
    clearFilters: 'Limpar filtros',
    perPageLabel: 'Linhas por página',
    perPageSuffix: '/ pág.',
    edit: 'Editar',
    duplicate: 'Duplicar registro',
    delete: 'Excluir',
    restore: 'Restaurar',
    actions: 'Ações',
    action: 'Ação',
    selectAll: 'Selecionar todas as linhas',
    selectRow: 'Selecionar linha :id',
    sortBy: 'Ordenar por :column',
    dragColumn: 'Arrastar para reordenar',
    emptyTitle: 'Nenhum registro encontrado',
    emptySubtitle: 'Ajuste os filtros ou adicione um novo item',
    emptyFilteredTitle: 'Nenhum resultado encontrado',
    emptyFilteredSubtitle: 'Tente ajustar ou limpar os filtros ativos',
    pagination: 'Exibindo :first–:last de :total registros',
    goTo: 'Ir para:',
    previous: '← Anterior',
    next: 'Próxima →',
    previousPage: 'Página anterior',
    nextPage: 'Próxima página',
    currentPage: 'Página atual',
    pageOf: 'Página :current de :last',
    paginationNav: 'Paginação',
    nSelected: ':n selecionado(s)',
    bulkDelete: 'Excluir selecionados',
    bulkForceDelete: 'Limpar lixeira',
    bulkRestore: 'Restaurar selecionados',
    bulkCancel: 'Cancelar seleção',
    bulkDeleteConfirm: 'Excluir :n registro(s) selecionado(s)? Esta ação não pode ser desfeita.',
    bulkForceConfirm: 'Remover permanentemente :n registro(s)? Esta ação é irreversível.',
    bulkIrreversible: 'Esta ação não pode ser desfeita.',
    bulkActionConfirm: 'Aplicar aos :n registro(s) selecionado(s)?',
    cancel: 'Cancelar',
    deleteButton: 'Excluir',
    confirm: 'Confirmar',
    close: 'Fechar',
    subtotal: 'Subtotal (:n registros)',
    sum: 'Soma',
    avg: 'Média',
    count: 'Contagem',
    min: 'Mínimo',
    max: 'Máximo',
    shortcutsTitle: 'Atalhos de teclado',
    shortcutsSearch: 'Focar a busca',
    shortcutsNew: 'Novo registro',
    shortcutsFilters: 'Abrir/fechar filtros',
    shortcutsView: 'Alternar tabela / cards',
    shortcutsRefresh: 'Recarregar a listagem',
    shortcutsSidebar: 'Recolher / expandir o menu',
    shortcutsHelp: 'Mostrar esta lista',
};

const dictionaries: Record<string, ScreenWords> = { en, pt: ptBR };

/** The words of the screen for a locale (`pt_BR`, `pt-BR`, `en`…); English when unknown. */
export function screenWords(locale?: string | null): ScreenWords {
    const lang = (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').toLowerCase().slice(0, 2);

    return dictionaries[lang] ?? en;
}

/** `:name` placeholders, the longest first (`:n` must not eat `:name`). */
export function fillWords(template: string, params: Record<string, string | number>): string {
    return Object.keys(params)
        .sort((a, b) => b.length - a.length)
        .reduce((text, key) => text.split(`:${key}`).join(String(params[key])), template);
}

/** The label of a total's aggregate (`sum` → "Soma"). */
export function aggregateLabel(t: ScreenWords, aggregate: string): string {
    return ({ sum: t.sum, avg: t.avg, count: t.count, min: t.min, max: t.max } as Record<string, string>)[aggregate] ?? aggregate;
}

/** The per-page choices of the toolbar select ([10, 15, 25, 50, 100], plus the current one). */
export function perPageChoices(current: number): number[] {
    return [...new Set([10, 15, 25, 50, 100, current])].sort((a, b) => a - b);
}

/** The pages to draw, with `'…'` gaps, as Laravel's paginator `$elements` (window of 3 around the current page). */
export function pageElements(page: number, last: number): (number | '…')[] {
    if (last <= 1) {
        return [1];
    }
    if (last <= 13) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }
    const around = 3;
    if (page <= around * 2) {
        return [...Array.from({ length: around * 2 + 2 }, (_, i) => i + 1), '…', last - 1, last];
    }
    if (page > last - around * 2) {
        return [1, 2, '…', ...Array.from({ length: around * 2 + 2 }, (_, i) => last - around * 2 - 1 + i)];
    }

    return [1, 2, '…', ...Array.from({ length: around * 2 + 1 }, (_, i) => page - around + i), '…', last - 1, last];
}

// ── What the screen shows, from the state ───────────────────────────────────

type ListLike = Pick<ListState, 'search' | 'filters' | 'date_ranges' | 'quick_date' | 'trashed'>;

const filled = (value: unknown) => value !== null && value !== undefined && value !== '' && !(Array.isArray(value) && value.length === 0);

/** The count on the Filters button: filters + date ranges + the quick date, as the Blade's `$activeFilterCount`. */
export function activeFilterCount(list: ListLike): number {
    return Object.values(list.filters ?? {}).filter(filled).length + Object.values(list.date_ranges ?? {}).filter(filled).length + (list.quick_date ? 1 : 0);
}

/** A filtered listing (the empty state offers to clear): search, filters, date ranges or quick date. */
export function isFiltered(list: ListLike): boolean {
    return list.search !== '' || activeFilterCount(list) > 0;
}

/** The toolbar's ✕ "Clear filters": a search, a filter, or the trash on. */
export function toolbarClearable(list: ListLike): boolean {
    return list.search !== '' || Object.values(list.filters ?? {}).some(filled) || list.trashed;
}

/** The sticky actions column: edit, delete, or duplicate outside the trash. */
export function showsRowButtons(permissions: Pick<Permissions, 'canUpdate' | 'canDelete' | 'canCreate'>, trashed: boolean): boolean {
    return permissions.canUpdate || permissions.canDelete || (permissions.canCreate && !trashed);
}

/** The configured `action` columns of the page, by label, in order: one table column each. */
export function actionColumns(rows: Pick<Row, 'actions'>[]): string[] {
    const labels: string[] = [];
    for (const row of rows) {
        for (const action of row.actions) {
            if (!labels.includes(action.label)) {
                labels.push(action.label);
            }
        }
    }

    return labels;
}

/** `background:#fee;color:red` → a style object (camelCase keys; custom properties kept). */
export function rowStyle(style: string | null | undefined): Record<string, string> | undefined {
    if (!style) {
        return undefined;
    }
    const out: Record<string, string> = {};
    for (const rule of style.split(';')) {
        const [name, ...rest] = rule.split(':');
        if (name && rest.length) {
            const key = name.trim();
            out[key.startsWith('--') ? key : key.replace(/-([a-z])/g, (_, letter: string) => letter.toUpperCase())] = rest.join(':').trim();
        }
    }

    return out;
}

// ── Collapsible toolbar labels ──────────────────────────────────────────────

/** True when a visible child of `el` sits on another line (centres compared, ±2px). */
function wrapped(el: Element | null): boolean {
    if (!el) {
        return false;
    }
    const kids = Array.from(el.children).filter((kid) => (kid as HTMLElement).offsetParent !== null) as HTMLElement[];
    if (kids.length < 2) {
        return false;
    }
    const center = (kid: HTMLElement) => kid.offsetTop + kid.offsetHeight / 2;
    const first = center(kids[0]);

    return kids.some((kid) => Math.abs(center(kid) - first) > 2);
}

/**
 * The labels of the toolbar buttons show whenever they fit and collapse to
 * icons only when the row would wrap — measured, not a breakpoint (it
 * depends on the container, the density and the translated words), as the
 * Blade's x-data does: turn the labels on, and off again if the row or its
 * actions group wrapped. Re-measured on width changes and when the fonts
 * load. Returns the cleanup.
 */
export function watchToolbarLabels(row: HTMLElement, labelsClass = 'pc-tb-row--labels', actionsSelector = '.pc-tb-actions'): () => void {
    let lastWidth = -1;
    let frame = 0;
    const measure = () => {
        row.classList.add(labelsClass);
        if (wrapped(row) || wrapped(row.querySelector(actionsSelector))) {
            row.classList.remove(labelsClass);
        }
    };
    measure();
    if (typeof document !== 'undefined' && document.fonts?.ready) {
        void document.fonts.ready.then(measure);
    }
    if (typeof ResizeObserver === 'undefined') {
        return () => undefined;
    }
    const observer = new ResizeObserver((entries) => {
        const width = Math.round(entries[0]?.contentRect.width ?? 0);
        if (width === lastWidth) {
            return;
        }
        lastWidth = width;
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(measure);
    });
    observer.observe(row);

    return () => {
        observer.disconnect();
        cancelAnimationFrame(frame);
    };
}

/** The toolbar measures again (a control appeared or went away). */
export function remeasureToolbar(row: HTMLElement | null, labelsClass = 'pc-tb-row--labels', actionsSelector = '.pc-tb-actions'): void {
    if (!row) {
        return;
    }
    row.classList.add(labelsClass);
    if (wrapped(row) || wrapped(row.querySelector(actionsSelector))) {
        row.classList.remove(labelsClass);
    }
}

// ── Keyboard shortcuts (base-crud.blade.php `_hotkeys`) ─────────────────────

export type ShortcutAction = 'help' | 'filters' | 'view' | 'refresh' | 'search' | 'new';

/** Some dialog of the page is open (any visible `[aria-modal=true]`). */
export function anyDialogOpen(): boolean {
    return Array.from(document.querySelectorAll('[aria-modal="true"]')).some((el) =>
        typeof (el as HTMLElement).checkVisibility === 'function' ? (el as HTMLElement).checkVisibility() : el.getClientRects().length > 0,
    );
}

/** The shortcut a key press asks for, or null: `?` `f` `v` `r` `/` `n`, never while typing or with a dialog open. */
export function shortcutOf(event: KeyboardEvent, canCreate: boolean): ShortcutAction | null {
    const target = event.target as HTMLElement | null;
    if (target?.closest?.('input, textarea, select, [contenteditable]') || target?.isContentEditable) {
        return null;
    }
    if (event.ctrlKey || event.metaKey || event.altKey) {
        return null;
    }
    const map: Record<string, ShortcutAction> = { '?': 'help', f: 'filters', v: 'view', r: 'refresh', '/': 'search' };
    const action = map[event.key] ?? (canCreate && event.key.toLowerCase() === 'n' ? 'new' : null);
    if (!action || anyDialogOpen()) {
        return null;
    }

    return action;
}
