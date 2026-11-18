<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { CrudController, debounce, pagesAround, words } from '../core';
import type { ControllerOptions, PtahApi, Row, RowAction } from '../core';
import { nextViewMode } from '../core/crud/boards';
import { fullOrder, moveColumn, saveColumnOrder } from '../core/crud/extras';
import { clearAllFilters, filterByCell, filterSession, isCellFilterable } from '../core/crud/filters';
import { PtahCrudCalendar, PtahCrudCards, PtahCrudKanban, PtahCrudViewSwitch, useResolvedView } from './crud/boards';
import { PtahCrudColumnResizer, PtahCrudColumnsMenu, PtahCrudDetailRow, PtahCrudDetailToggle } from './crud/extras';
import { PtahCrudFilterBadges, PtahCrudFilterPanel, PtahCrudSearchHistory, PtahCrudUrlFilters } from './crud/filters';
import { PtahCrudImport, PtahCrudImportButton } from './crud/import';
import { PtahCrudAttachments, PtahCrudHistory, PtahCrudRecordButtons } from './crud/records';
import PtahConfirm from './PtahConfirm.vue';
import PtahForm from './PtahForm.vue';
import PtahMenu from './PtahMenu.vue';
import { usePtahCrud } from './usePtahCrud';

const props = withDefaults(
    defineProps<{
    /** The key the screen was registered with: `CrudScreen::register('products', …)`. */
    screen: string;
    /** `pt_BR`, `en`… Defaults to the page's `<html lang>`. */
    locale?: string;
    /** Replaces the screen's title. */
    title?: string;
    api?: PtahApi;
    /** Reopen where the user left and keep remembering (default true). */
    remember?: boolean;
    toastMs?: number;
    /** A row action of type `action`: yours to run. */
    onAction?: ControllerOptions['onAction'];
    open?: ControllerOptions['open'];
    /** A controller of your own (to drive the screen from outside). */
    controller?: CrudController;
    }>(),
    // A boolean prop that is absent is `false` in Vue; remembering is the default.
    { remember: true },
);

const crud =
    props.controller ??
    new CrudController(props.screen, {
        api: props.api,
        remember: props.remember,
        toastMs: props.toastMs,
        onAction: props.onAction,
        open: props.open,
    });
const state = usePtahCrud(crud);
const t = computed(() => words(props.locale));
const view = useResolvedView(crud);

/** Typing in a field: a single-key shortcut must not fire. */
const typing = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null;

    return !!target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
};
// `v` walks the views (auto → table → cards), as on the Livewire screen.
const onKey = (event: KeyboardEvent) => {
    const now = crud.getState();
    if (event.key !== 'v' || event.ctrlKey || event.metaKey || event.altKey || typing(event) || now.form || now.panel || now.deleting !== null) {
        return;
    }
    void crud.savePreferences({ view_mode: nextViewMode(now.preferences?.view_mode) });
};

onMounted(() => {
    void crud.init();
    document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    if (!props.controller) {
        crud.destroy();
    }
});

// ── Toolbar ─────────────────────────────────────────────────────────────────

const search = ref('');
const showFilters = ref(false);
const applySearch = debounce((value: string) => void crud.setSearch(value), 350);

// The search box follows the state when it changes from outside (restore, clear).
watch(
    () => state.value.list.search,
    (value) => {
        search.value = value;
    },
    { immediate: true },
);

const onSearch = (event: Event) => {
    search.value = (event.target as HTMLInputElement).value;
    applySearch(search.value);
};

// Reads the listing state, so it follows every change of it.
const hasFilters = computed(() => Boolean(state.value.list) && crud.hasFilters);
const clearAll = () => void clearAllFilters(crud, filterSession(crud));

const formatLabel = (format: string) => (t.value as Record<string, string>)[format] ?? format;
const inputValue = (event: Event) => (event.target as HTMLInputElement).value;

// ── Bulk and row actions ────────────────────────────────────────────────────

const pendingBulk = ref<{ action: string; text: string } | null>(null);
const runBulk = (action: string, confirm: string | boolean | null) => {
    if (confirm) {
        pendingBulk.value = { action, text: typeof confirm === 'string' ? confirm : t.value.confirmBulk };
    } else {
        void crud.bulk(action);
    }
};
const confirmBulk = () => {
    if (pendingBulk.value) {
        void crud.bulk(pendingBulk.value.action);
    }
    pendingBulk.value = null;
};

const pendingAction = ref<{ row: Row; action: RowAction } | null>(null);
const runAction = (row: Row, action: RowAction) => {
    if (action.confirm) {
        pendingAction.value = { row, action };
    } else {
        crud.runAction(row, action);
    }
};
const confirmAction = () => {
    if (pendingAction.value) {
        crud.runAction(pendingAction.value.row, pendingAction.value.action);
    }
    pendingAction.value = null;
};
const onLink = (event: MouseEvent, action: RowAction) => {
    if (action.confirm && !window.confirm(action.confirm)) {
        event.preventDefault();
    }
};

// ── Table ───────────────────────────────────────────────────────────────────

const alignClass = (align: string | null) => (align === 'right' ? 'pc-align-right' : align === 'center' ? 'pc-align-center' : undefined);
const isSelected = (row: Row) => state.value.selection.includes(String(row.id));
const allSelected = computed(() => state.value.rows.length > 0 && state.value.rows.every(isSelected));
const hasRowButtons = computed(() => {
    const permissions = state.value.schema?.permissions;

    const features = state.value.schema?.features;

    return Boolean(
        permissions?.canUpdate || permissions?.canDelete || permissions?.canCreate || features?.history || features?.attachments || state.value.rows.some((row) => row.actions.length > 0),
    );
});
const detail = computed(() => Boolean(state.value.schema?.features?.masterDetail));
const span = computed(
    () => (state.value.schema?.columns.length ?? 0) + (state.value.schema?.selectable ? 1 : 0) + (hasRowButtons.value ? 1 : 0) + (detail.value ? 1 : 0),
);
const widthOf = (field: string) => {
    const width = state.value.preferences?.column_widths?.[field];

    return width ? { width: `${width}px`, minWidth: '60px' } : undefined;
};
const dragged = ref<string | null>(null);
// A header dropped on another one takes its place (the saved order of the user).
const dropOn = (target: string) => {
    const preferences = state.value.preferences;
    if (dragged.value && dragged.value !== target && preferences && state.value.schema) {
        const shown = state.value.schema.columns.map((column) => column.field);
        void saveColumnOrder(crud, moveColumn(fullOrder(preferences), shown, dragged.value, shown.indexOf(target)));
    }
    dragged.value = null;
};
const cellFilter = (field: string) => isCellFilterable(crud, field);
// Alt+click filters the listing by this cell's value.
const onCell = (event: MouseEvent, row: Row, field: string) => {
    if (event.altKey && cellFilter(field)) {
        void filterByCell(crud, row.id, field);
    }
};
const totalOf = (field: string) => state.value.totals.find((total) => total.field === field);
const sorted = (sortBy: string | null) => sortBy !== null && state.value.list.sort === sortBy;
const ascending = computed(() => state.value.list.direction.toUpperCase() === 'ASC');

// ── Footer ──────────────────────────────────────────────────────────────────

const summary = computed(() => {
    const meta = state.value.meta;
    if (!meta) {
        return '';
    }
    const first = meta.total === 0 ? 0 : (meta.page - 1) * meta.per_page + 1;

    return `${first}–${Math.min(meta.total, meta.page * meta.per_page)} ${t.value.of} ${meta.total}`;
});
const sizes = computed(() => [...new Set([10, 25, 50, 100, state.value.meta?.per_page ?? 25])].sort((a, b) => a - b));
const statusText = computed(() => ({ loading: t.value.loading, forbidden: t.value.forbidden, 'not-found': t.value.notFound, error: t.value.error, ready: t.value.loading })[state.value.status]);

onBeforeUnmount(() => {
    applySearch.cancel();
});
</script>

<template>
    <div v-if="state.status !== 'ready' || !state.schema" class="pc-root">
        <div class="pc-state" :role="state.status === 'loading' ? 'status' : 'alert'">{{ statusText }}</div>
    </div>

    <div v-else class="pc-root" :data-density="state.preferences?.view_density ?? 'global'">
        <PtahCrudUrlFilters :crud="crud" :locale="locale" />

        <!-- Toolbar -->
        <div class="pc-toolbar">
            <h2 class="pc-title">{{ title ?? state.schema.title }}</h2>
            <input type="search" class="pc-input pc-search" :placeholder="t.search" :aria-label="t.search" :value="search" @input="onSearch" />
            <PtahCrudSearchHistory :crud="crud" :locale="locale" />
            <!-- Always: quick dates and the advanced search need no filterable column. -->
            <button type="button" :class="['pc-btn', { 'pc-btn--active': showFilters || hasFilters }]" :aria-expanded="showFilters" @click="showFilters = !showFilters">
                {{ t.filters }}
            </button>
            <button v-if="hasFilters" type="button" class="pc-btn" @click="clearAll">{{ t.clear }}</button>
            <span class="pc-spacer" />
            <button v-if="state.schema.trash" type="button" :class="['pc-btn', { 'pc-btn--active': state.list.trashed }]" :aria-pressed="state.list.trashed" @click="crud.toggleTrashed()">
                {{ state.list.trashed ? t.backFromTrash : t.trash }}
            </button>
            <PtahCrudViewSwitch :crud="crud" :locale="locale" />
            <PtahCrudColumnsMenu :crud="crud" :locale="locale" />
            <PtahMenu v-if="state.schema.export.enabled" :label="t.export">
                <li v-for="format in [...state.schema.export.formats, 'print']" :key="format">
                    <button type="button" class="pc-menu-item" @click="crud.export(format)">{{ formatLabel(format) }}</button>
                </li>
            </PtahMenu>
            <PtahCrudImportButton :crud="crud" :locale="locale" />
            <slot name="toolbar" />
            <button v-if="state.schema.permissions.canCreate && !state.list.trashed" type="button" class="pc-btn pc-btn--primary" @click="crud.openCreate()">+ {{ t.new }}</button>
        </div>

        <PtahCrudFilterPanel v-if="showFilters" :crud="crud" :locale="locale" @close="showFilters = false" />
        <PtahCrudFilterBadges :crud="crud" :locale="locale" />

        <!-- Bulk bar -->
        <div v-if="state.selection.length > 0" class="pc-bulk" role="region" :aria-label="t.selected">
            <strong>{{ state.selection.length }} {{ t.selected }}</strong>
            <button v-for="action in state.schema.bulkActions" :key="action.action" type="button" class="pc-btn" @click="runBulk(action.action, action.confirm)">
                <i v-if="action.icon" :class="action.icon" aria-hidden="true" />{{ action.label }}
            </button>
            <template v-if="state.list.trashed">
                <button v-if="state.schema.permissions.canRestore" type="button" class="pc-btn" @click="crud.bulk('restore')">{{ t.restoreSelected }}</button>
            </template>
            <button v-else-if="state.schema.permissions.canDelete" type="button" class="pc-btn pc-btn--danger" @click="runBulk('delete', t.confirmBulk)">{{ t.deleteSelected }}</button>
            <button type="button" class="pc-btn" @click="crud.clearSelection()">{{ t.clear }}</button>
        </div>

        <PtahCrudKanban v-if="view === 'kanban'" :crud="crud" :locale="locale" />
        <PtahCrudCalendar v-else-if="view === 'calendar'" :crud="crud" :locale="locale" />
        <PtahCrudCards v-else-if="view === 'cards'" :crud="crud" :locale="locale" />

        <!-- Table -->
        <div v-else class="pc-table-wrap" :aria-busy="state.busy">
            <table class="pc-table">
                <thead>
                    <tr>
                        <th v-if="detail" class="pc-col-check" />
                        <th v-if="state.schema.selectable" class="pc-col-check">
                            <input type="checkbox" :aria-label="t.all" :checked="allSelected" @change="crud.toggleAll()" />
                        </th>
                        <th
                            v-for="column in state.schema.columns"
                            :key="column.field"
                            :class="alignClass(column.align)"
                            :aria-sort="sorted(column.sortBy) ? (ascending ? 'ascending' : 'descending') : undefined"
                            :style="{ position: 'relative', ...widthOf(column.field) }"
                            draggable="true"
                            @dragstart="dragged = column.field"
                            @dragover="dragged && $event.preventDefault()"
                            @drop="dropOn(column.field)"
                            @dragend="dragged = null"
                        >
                            <button v-if="column.sortBy !== null" type="button" class="pc-th-btn" @click="crud.sortBy(column.sortBy)">
                                {{ column.label }}
                                <span :class="['pc-sort', { 'pc-sort--on': sorted(column.sortBy) }]" aria-hidden="true">{{ sorted(column.sortBy) && !ascending ? '▼' : '▲' }}</span>
                            </button>
                            <template v-else>{{ column.label }}</template>
                            <PtahCrudColumnResizer :crud="crud" :field="column.field" :label="column.label" :locale="locale" />
                        </th>
                        <th v-if="hasRowButtons" class="pc-col-actions">{{ t.actions }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="state.rows.length === 0">
                        <td class="pc-empty" :colspan="span">{{ t.empty }}</td>
                    </tr>
                    <template v-for="row in state.rows" :key="row.id">
                    <tr :style="row.style ?? undefined">
                        <td v-if="detail" class="pc-col-check">
                            <PtahCrudDetailToggle :crud="crud" :row="row" :locale="locale" />
                        </td>
                        <td v-if="state.schema.selectable" class="pc-col-check">
                            <input type="checkbox" :aria-label="String(row.id)" :checked="isSelected(row)" @change="crud.toggleRow(row.id)" />
                        </td>
                        <!-- The HTML comes rendered and escaped by the server: the same
                             string the Livewire screen prints for this cell. -->
                        <!-- eslint-disable-next-line vue/no-v-html -->
                        <td
                            v-for="column in state.schema.columns"
                            :key="column.field"
                            :class="alignClass(column.align)"
                            :style="widthOf(column.field)"
                            :title="cellFilter(column.field) ? t.filterByCell : undefined"
                            @click="onCell($event, row, column.field)"
                            v-html="row.cells[column.field]?.html ?? ''"
                        />
                        <td v-if="hasRowButtons" class="pc-col-actions">
                            <PtahCrudRecordButtons :crud="crud" :row="row" :locale="locale" />
                            <template v-for="(action, index) in row.actions" :key="index">
                                <a v-if="action.type === 'link'" class="pc-icon-btn" :href="action.value" :title="action.label" :aria-label="action.label" @click="onLink($event, action)">
                                    <i v-if="action.icon" :class="action.icon" aria-hidden="true" /><template v-else>{{ action.label || '→' }}</template>
                                </a>
                                <button v-else type="button" class="pc-icon-btn" :title="action.label" :aria-label="action.label" @click="runAction(row, action)">
                                    <i v-if="action.icon" :class="action.icon" aria-hidden="true" /><template v-else>{{ action.label || '→' }}</template>
                                </button>
                            </template>
                            <template v-if="row.trashed">
                                <button v-if="state.schema.permissions.canRestore" type="button" class="pc-icon-btn" @click="crud.restore(row.id)">{{ t.restore }}</button>
                            </template>
                            <template v-else>
                                <button v-if="state.schema.permissions.canUpdate" type="button" class="pc-icon-btn" @click="crud.openEdit(row.id)">{{ t.edit }}</button>
                                <button v-if="state.schema.permissions.canCreate" type="button" class="pc-icon-btn" @click="crud.duplicate(row.id)">{{ t.duplicate }}</button>
                                <button v-if="state.schema.permissions.canDelete" type="button" class="pc-icon-btn pc-icon-btn--danger" @click="crud.askDelete(row.id)">{{ t.delete }}</button>
                            </template>
                        </td>
                    </tr>
                    <PtahCrudDetailRow v-if="detail" :crud="crud" :row="row" :col-span="span" :locale="locale" />
                    </template>
                </tbody>
                <tfoot v-if="state.totals.length > 0">
                    <tr>
                        <td v-if="detail" />
                        <td v-if="state.schema.selectable" />
                        <td v-for="(column, index) in state.schema.columns" :key="column.field" :class="alignClass(column.align)">
                            {{ totalOf(column.field) ? String(totalOf(column.field)?.value ?? '') : index === 0 ? t.total : '' }}
                        </td>
                        <td v-if="hasRowButtons" />
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Footer -->
        <!-- A board shows every record of the filter: no pages. -->
        <div v-if="state.meta && view !== 'kanban' && view !== 'calendar'" class="pc-footer">
            <span>{{ summary }}</span>
            <select class="pc-input" :aria-label="t.perPage" :value="state.meta.per_page" @change="crud.setPerPage(Number(inputValue($event)))">
                <option v-for="size in sizes" :key="size" :value="size">{{ size }} {{ t.perPage }}</option>
            </select>
            <nav class="pc-pager" aria-label="pagination">
                <button type="button" class="pc-btn" :disabled="state.meta.page <= 1" @click="crud.setPage(state.meta.page - 1)">{{ t.previous }}</button>
                <button
                    v-for="page in pagesAround(state.meta.page, state.meta.last_page)"
                    :key="page"
                    type="button"
                    :class="['pc-btn', { 'pc-btn--active': page === state.meta.page }]"
                    :aria-current="page === state.meta.page ? 'page' : undefined"
                    @click="crud.setPage(page)"
                >
                    {{ page }}
                </button>
                <button type="button" class="pc-btn" :disabled="state.meta.page >= state.meta.last_page" @click="crud.setPage(state.meta.page + 1)">{{ t.next }}</button>
            </nav>
        </div>

        <PtahForm v-if="state.form" :crud="crud" :state="state" :t="t" :locale="locale" />
        <!-- After the form in the DOM: they open over it. -->
        <PtahCrudHistory :crud="crud" :locale="locale" />
        <PtahCrudAttachments :crud="crud" :locale="locale" />
        <PtahCrudImport :crud="crud" :locale="locale" />

        <PtahConfirm v-if="state.deleting !== null" :text="t.confirmDelete" :confirm="t.delete" :cancel="t.cancel" danger @confirm="crud.confirmDelete()" @cancel="crud.cancelDelete()" />
        <PtahConfirm v-if="pendingBulk" :text="pendingBulk.text" :confirm="t.confirm" :cancel="t.cancel" @confirm="confirmBulk" @cancel="pendingBulk = null" />
        <PtahConfirm v-if="pendingAction" :text="pendingAction.action.confirm ?? ''" :confirm="t.confirm" :cancel="t.cancel" @confirm="confirmAction" @cancel="pendingAction = null" />

        <div class="pc-toasts" aria-live="polite">
            <div v-for="toast in state.toasts" :key="toast.id" :class="['pc-toast', `pc-toast--${toast.level}`]" role="status">
                <span class="pc-toast-text">{{ toast.text }}</span>
                <button type="button" class="pc-icon-btn" :aria-label="t.close" @click="crud.dismiss(toast.id)">✕</button>
            </div>
        </div>
    </div>
</template>
