<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { CrudController, debounce, words } from '../core';
import type { ControllerOptions, GroupBreak, PtahApi, Row, RowAction } from '../core';
import { followRowLink, nextViewMode } from '../core/crud/boards';
import { fullOrder, moveColumn, saveColumnOrder } from '../core/crud/extras';
import { cellActionWords, installCellActions } from '../core/crud/cell-actions';
import { clearAllFilters, filterSession, isCellFilterable } from '../core/crud/filters';
import {
    actionColumns,
    activeFilterCount,
    aggregateLabel,
    fillWords,
    isFiltered,
    pageElements,
    perPageChoices,
    remeasureToolbar,
    rowStyle,
    screenWords,
    shortcutOf,
    showsRowButtons,
    toolbarClearable,
    watchToolbarLabels,
} from '../core/crud/screen';
import { PtahCrudConfigButton } from './admin/crud-config';
import { PtahCrudCalendar, PtahCrudCards, PtahCrudKanban, PtahCrudViewSwitch, useResolvedView } from './crud/boards';
import { PtahCrudColumnResizer, PtahCrudColumnsMenu, PtahCrudDetailRow, PtahCrudDetailToggle } from './crud/extras';
import { PtahCrudFilterBadges, PtahCrudFilterPanel, PtahCrudUrlFilters } from './crud/filters';
import { PtahCrudImport, PtahCrudImportButton } from './crud/import';
import { PtahCrudAttachments, PtahCrudHistory } from './crud/records';
import { Icon } from './icons';
import PtahConfirm from './PtahConfirm.vue';
import PtahForm from './PtahForm.vue';
import PtahRowAction from './PtahRowAction.vue';
import PtahRowButtons from './PtahRowButtons.vue';
import { PtahCrudSkeleton } from './skeleton';
import { usePtahCrud } from './usePtahCrud';

const props = withDefaults(
    defineProps<{
    /** The key the screen was registered with: `CrudScreen::register('products', …)`. */
    screen: string;
    /** `pt_BR`, `en`… Defaults to the page's `<html lang>`. */
    locale?: string;
    /**
     * A title row at the start of the toolbar. The Livewire screen has none
     * (the page header above it names the screen): give one only when the
     * screen is mounted alone.
     */
    title?: string;
    api?: PtahApi;
    /** Reopen where the user left and keep remembering (default true). */
    remember?: boolean;
    toastMs?: number;
    /** A row action of type `action`: yours to run. */
    onAction?: ControllerOptions['onAction'];
    open?: ControllerOptions['open'];
    /** Draw the last listing this tab saw at once, then refresh it (see ControllerOptions.instant). */
    instant?: boolean;
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
        instant: props.instant,
    });
const state = usePtahCrud(crud);
const t = computed(() => words(props.locale));
const w = computed(() => screenWords(props.locale));
const view = useResolvedView(crud);
const nested = crud.screen.includes('..');
const board = computed(() => view.value === 'kanban' || view.value === 'calendar');

const showFilters = ref(false);
const showKeys = ref(false);
const searchInput = ref<HTMLInputElement | null>(null);

// The shortcuts of the Livewire screen (`_hotkeys`): ? f v r / n. A detail grid has none.
const onKey = (event: KeyboardEvent) => {
    const now = crud.getState();
    const action = shortcutOf(event, Boolean(now.schema?.permissions.canCreate) && !now.list.trashed);
    if (nested || !action || now.form || now.panel || now.deleting !== null) {
        return;
    }
    event.preventDefault();
    if (action === 'help') {
        showKeys.value = true;
    } else if (action === 'filters') {
        showFilters.value = !showFilters.value;
    } else if (action === 'view') {
        void crud.savePreferences({ view_mode: nextViewMode(now.preferences?.view_mode) });
    } else if (action === 'refresh') {
        void crud.reload();
    } else if (action === 'search') {
        searchInput.value?.focus();
    } else {
        void crud.openCreate();
    }
};
const onEscape = (event: KeyboardEvent) => {
    if (event.key !== 'Escape') {
        return;
    }
    showKeys.value = false;
    exportOpen.value = false;
    if (bulkAsk.value) {
        bulkAsk.value = null;
    }
};

// ── Toolbar ─────────────────────────────────────────────────────────────────

const search = ref('');
const more = ref(false);
const row = ref<HTMLElement | null>(null);
const exportOpen = ref(false);
const exportRef = ref<HTMLElement | null>(null);
const applySearch = debounce((value: string) => void crud.setSearch(value), 400);
let stopLabels: (() => void) | null = null;

// The search box follows the state when it changes from outside (restore, clear).
watch(
    () => state.value.list.search,
    (value) => {
        search.value = value;
    },
    { immediate: true },
);
// The toolbar mounts once the schema is in; its labels are measured from then on.
watch(row, (element) => {
    stopLabels?.();
    stopLabels = element ? watchToolbarLabels(element) : null;
});
// Opening `⋯` re-renders the row's class: measure again.
watch(more, () => void nextTick(() => remeasureToolbar(row.value)));

const onSearch = (event: Event) => {
    search.value = (event.target as HTMLInputElement).value;
    applySearch(search.value);
};
const clearSearch = () => {
    applySearch.cancel();
    search.value = '';
    void crud.setSearch('');
};
const clearAll = () => void clearAllFilters(crud, filterSession(crud));
const filterCount = computed(() => activeFilterCount(state.value.list));
const clearable = computed(() => toolbarClearable(state.value.list));
const runExport = (format: string) => {
    exportOpen.value = false;
    void crud.export(format);
};
const formatIcon = (format: string) => (format === 'excel' ? 'fileChart' : format === 'pdf' ? 'file' : 'download');
const formatClass = (format: string) => (format === 'excel' ? 'pc-dd-ico-excel' : format === 'pdf' ? 'pc-dd-ico-pdf' : 'pc-dd-ico-muted');
const formatName = (format: string) => (format === 'excel' ? 'Excel' : format === 'pdf' ? 'PDF' : format.toUpperCase());
const onOutside = (event: MouseEvent) => {
    if (exportOpen.value && !exportRef.value?.contains(event.target as Node)) {
        exportOpen.value = false;
    }
};

onMounted(() => {
    void crud.init();
    stopCellActions = installCellActions(crud, { words: cellActionWords(props.locale), enabled: () => crud.features.copyCell });
    document.addEventListener('keydown', onKey);
    document.addEventListener('keydown', onEscape);
    document.addEventListener('mousedown', onOutside);
});
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.removeEventListener('keydown', onEscape);
    document.removeEventListener('mousedown', onOutside);
    stopLabels?.();
    stopCellActions?.();
    applySearch.cancel();
    if (!props.controller) {
        crud.destroy();
    }
});

// ── Bulk and row actions ────────────────────────────────────────────────────

const bulkAsk = ref<'delete' | 'force' | null>(null);
const pendingBulk = ref<{ action: string; text: string } | null>(null);
const runBulk = (action: string, confirm: string | boolean | null) => {
    if (confirm) {
        pendingBulk.value = { action, text: typeof confirm === 'string' ? confirm : fillWords(w.value.bulkActionConfirm, { n: state.value.selection.length }) };
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
const confirmBulkDelete = () => {
    const force = bulkAsk.value === 'force';
    bulkAsk.value = null;
    void crud.bulk(force ? 'force-delete' : 'delete');
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
// configLinkLinha: a click (or Enter) on a row with a link opens its record.
const followLink = (event: MouseEvent | KeyboardEvent, row: Row) => {
    if (row.link) {
        followRowLink(event, row.link);
    }
};

// ── Table ───────────────────────────────────────────────────────────────────

const alignClass = (align: string | null) => (align === 'right' ? 'pc-align-right' : align === 'center' ? 'pc-align-center' : undefined);
const isSelected = (row: Row) => state.value.selection.includes(String(row.id));
const allSelected = computed(() => state.value.rows.length > 0 && state.value.rows.every(isSelected));
const rowButtons = computed(() => (state.value.schema ? showsRowButtons(state.value.schema.permissions, state.value.list.trashed) : false));
const actions = computed(() => actionColumns(state.value.rows));
const detail = computed(() => Boolean(state.value.schema?.features?.masterDetail));
const lead = computed(() => (detail.value ? 1 : 0) + (state.value.schema?.selectable ? 1 : 0));
const span = computed(() => (state.value.schema?.columns.length ?? 0) + lead.value + (rowButtons.value ? 1 : 0) + actions.value.length);
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
// "Copy" and "Filter by this value" over the cell the mouse rests on.
let stopCellActions: (() => void) | null = null;
const totalsOf = (field: string) => state.value.totals.filter((total) => total.field === field && total.value !== null);
const sorted = (sortBy: string | null) => sortBy !== null && state.value.list.sort === sortBy;
const ascending = computed(() => state.value.list.direction.toUpperCase() === 'ASC');
const ariaSort = (sortBy: string | null) => (sortBy === null ? undefined : sorted(sortBy) ? (ascending.value ? 'ascending' : 'descending') : 'none');
const breakAt = (index: number): GroupBreak | undefined => state.value.breaks.find((group) => group.start === index);
const breakEndingAt = (index: number): GroupBreak | undefined => state.value.breaks.find((group) => group.start + group.count - 1 === index);
const filtered = computed(() => isFiltered(state.value.list));
const actionOf = (row: Row, label: string) => row.actions.find((item) => item.label === label);

// ── Pagination ──────────────────────────────────────────────────────────────

const meta = computed(() => state.value.meta);
const jump = ref(1);
watch(
    () => state.value.meta?.page,
    (page) => {
        jump.value = page ?? 1;
    },
    { immediate: true },
);
const summary = computed(() => {
    const m = meta.value;
    if (!m) {
        return '';
    }
    const first = m.total === 0 ? 0 : (m.page - 1) * m.per_page + 1;

    return fillWords(w.value.pagination, { first, last: Math.min(m.total, m.page * m.per_page), total: m.total });
});
const go = (page: number) => {
    if (meta.value) {
        void crud.setPage(Math.min(Math.max(1, page), meta.value.last_page));
    }
};
const onJumpBlur = () => {
    if (jump.value !== meta.value?.page) {
        go(jump.value);
    }
};
const inputValue = (event: Event) => (event.target as HTMLInputElement).value;
const statusText = computed(() => ({ loading: t.value.loading, forbidden: t.value.forbidden, 'not-found': t.value.notFound, error: t.value.error, ready: t.value.loading })[state.value.status]);

const shortcuts = computed(() => [
    [w.value.shortcutsSearch, '/'],
    ...(state.value.schema?.permissions.canCreate ? [[w.value.shortcutsNew, 'n']] : []),
    [w.value.shortcutsFilters, 'f'],
    [w.value.shortcutsView, 'v'],
    [w.value.shortcutsRefresh, 'r'],
    [w.value.shortcutsSidebar, 'Ctrl+B'],
    [w.value.shortcutsHelp, '?'],
]);
const TOAST_ICONS: Record<string, string> = { success: 'checkCircle', warn: 'alertTriangle', danger: 'xCircle' };
</script>

<template>
    <!-- The first load: the shape of the screen (toolbar and rows), never a bare "Loading…". -->
    <PtahCrudSkeleton v-if="state.status === 'loading'" :label="t.loading" />
    <div v-else-if="state.status !== 'ready' || !state.schema" class="pc-root">
        <div class="pc-state" role="alert">{{ statusText }}</div>
    </div>

    <div v-else class="pc-root pc-crud" :data-ptah-crud="crud.screen" :data-density="state.preferences?.view_density ?? 'global'">
        <!-- A detail grid (a child screen) does not read the page's address. -->
        <PtahCrudUrlFilters :crud="crud" :locale="locale" :read-location="!nested" />

        <!-- Toolbar (_toolbar.blade.php) -->
        <div class="pc-tb">
            <div ref="row" :class="['pc-tb-row', { 'pc-tb-row--more': more }]">
                <h2 v-if="title" class="pc-tb-title">{{ title }}</h2>
                <button v-if="state.schema.permissions.canCreate && !state.list.trashed" type="button" class="pc-tbtn pc-tbtn--primary pc-tb-new" @click="crud.openCreate()">
                    <Icon name="plus" />{{ w.newButton }}
                </button>
                <div class="pc-tb-search">
                    <label class="pc-search-box">
                        <span class="pc-sr-only">{{ w.searchPlaceholder }}</span>
                        <Icon name="search" />
                        <input ref="searchInput" type="search" class="pc-search-input" :placeholder="w.searchPlaceholder" :value="search" @input="onSearch" />
                    </label>
                    <button v-if="search !== ''" type="button" class="pc-search-x" :title="w.clearFilters" :aria-label="w.clearFilters" @click="clearSearch">
                        <Icon name="x" :size="14" :stroke-width="2.5" />
                    </button>
                </div>
                <div class="pc-tb-actions">
                    <!-- Always: quick dates and the advanced search need no filterable column. -->
                    <button type="button" :class="['pc-tbtn', { 'pc-tbtn--on': showFilters }]" :title="w.filters" :aria-label="w.filters" :aria-expanded="showFilters" @click="showFilters = !showFilters">
                        <Icon name="funnel" />
                        <span class="pc-tbtn-label">{{ w.filters }}</span>
                        <span v-if="filterCount > 0" class="pc-count">{{ filterCount }}</span>
                    </button>
                    <button
                        v-if="state.schema.trash"
                        type="button"
                        :class="['pc-tbtn pc-tb-secondary', { 'pc-tbtn--trash-on': state.list.trashed }]"
                        :title="state.list.trashed ? w.viewActive : w.viewTrash"
                        :aria-label="state.list.trashed ? w.viewActive : w.viewTrash"
                        :aria-pressed="state.list.trashed"
                        @click="crud.toggleTrashed()"
                    >
                        <Icon :name="state.list.trashed ? 'arrowLeft' : 'trash'" />
                        <span class="pc-tbtn-label">{{ state.list.trashed ? w.back : w.trash }}</span>
                    </button>
                    <PtahCrudImportButton :crud="crud" :locale="locale" />
                    <!-- Exportar ▾ — one entry per format, then Print. -->
                    <div v-if="state.schema.export.enabled" ref="exportRef" class="pc-dd-wrap pc-tb-secondary">
                        <button type="button" class="pc-tbtn" :title="w.export" :aria-label="w.export" aria-haspopup="true" :aria-expanded="exportOpen" @click="exportOpen = !exportOpen">
                            <Icon name="fileDown" />
                            <span class="pc-tbtn-label">{{ w.export }}</span>
                            <Icon name="chevronDown" :size="12" />
                        </button>
                        <div v-if="exportOpen" class="pc-dd" style="min-width: 10rem">
                            <button v-for="format in state.schema.export.formats" :key="format" type="button" class="pc-dd-item" @click="runExport(format)">
                                <Icon :name="formatIcon(format)" :class-name="formatClass(format)" />{{ formatName(format) }}
                            </button>
                            <div class="pc-dd-sep" />
                            <button type="button" class="pc-dd-item" @click="runExport('print')"><Icon name="printer" class-name="pc-dd-ico-muted" />{{ w.print }}</button>
                        </div>
                    </div>
                    <PtahCrudColumnsMenu :crud="crud" :locale="locale" />
                    <button type="button" :class="['pc-tbtn pc-tb-more', { 'pc-tbtn--on': more }]" :aria-expanded="more" :title="more ? w.moreHide : w.more" :aria-label="more ? w.moreHide : w.more" @click="more = !more">
                        <Icon name="dots" />
                    </button>
                    <PtahCrudViewSwitch :crud="crud" :locale="locale" />
                    <PtahCrudConfigButton :crud="crud" :locale="locale" />
                    <button type="button" class="pc-tbtn pc-tb-secondary" :title="w.refresh" :aria-label="w.refresh" @click="crud.reload()">
                        <Icon name="refresh" :class-name="state.busy ? 'pc-spin' : undefined" />
                    </button>
                    <button v-if="clearable" type="button" class="pc-tbtn pc-tbtn--clear" :title="w.clearFilters" :aria-label="w.clearFilters" @click="clearAll">
                        <Icon name="x" />
                    </button>
                    <select v-if="meta" class="pc-perpage pc-tb-secondary" :aria-label="w.perPageLabel" :value="meta.per_page" @change="crud.setPerPage(Number(inputValue($event)))">
                        <option v-for="size in perPageChoices(meta.per_page)" :key="size" :value="size">{{ size }} {{ w.perPageSuffix }}</option>
                    </select>
                    <slot name="toolbar" />
                </div>
            </div>
            <PtahCrudFilterBadges :crud="crud" :locale="locale" />
        </div>

        <PtahCrudFilterPanel v-if="showFilters" :crud="crud" :locale="locale" />
        <div class="pc-loadbar" aria-hidden="true"><span v-if="state.busy" /></div>

        <PtahCrudKanban v-if="view === 'kanban'" :crud="crud" :locale="locale" />
        <PtahCrudCalendar v-else-if="view === 'calendar'" :crud="crud" :locale="locale" />
        <PtahCrudCards v-else-if="view === 'cards'" :crud="crud" :locale="locale">
            <!-- The card footer: the configured actions, then edit / duplicate / delete. -->
            <template #actions="{ row: cardRow }">
                <PtahRowButtons :crud="crud" :row="cardRow" :permissions="state.schema.permissions" :w="w" :trashed="state.list.trashed" card @run="runAction(cardRow, $event)" />
            </template>
        </PtahCrudCards>

        <!-- Table (_table.blade.php) -->
        <div v-else class="pc-grid-wrap" :aria-busy="state.busy">
            <table class="pc-grid">
                <thead>
                    <tr>
                        <th v-if="detail" class="pc-th-narrow" />
                        <th v-if="state.schema.selectable" class="pc-th-narrow">
                            <input type="checkbox" :aria-label="w.selectAll" :checked="allSelected" @change="crud.toggleAll()" />
                        </th>
                        <th
                            v-for="column in state.schema.columns"
                            :key="column.field"
                            :class="[alignClass(column.align), { 'pc-th-dragging': dragged === column.field }]"
                            :aria-sort="ariaSort(column.sortBy)"
                            :style="widthOf(column.field)"
                            draggable="true"
                            @dragstart="dragged = column.field"
                            @dragover="dragged && $event.preventDefault()"
                            @drop="dropOn(column.field)"
                            @dragend="dragged = null"
                        >
                            <div class="pc-th-in">
                                <span class="pc-th-grip" :title="w.dragColumn" aria-hidden="true">
                                    <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <circle cx="7" cy="5" r="1.5" /><circle cx="13" cy="5" r="1.5" /><circle cx="7" cy="10" r="1.5" />
                                        <circle cx="13" cy="10" r="1.5" /><circle cx="7" cy="15" r="1.5" /><circle cx="13" cy="15" r="1.5" />
                                    </svg>
                                </span>
                                <button v-if="column.sortBy !== null" type="button" class="pc-th-sort" :aria-label="fillWords(w.sortBy, { column: column.label })" @click="crud.sortBy(column.sortBy)">
                                    {{ column.label }}
                                    <Icon v-if="sorted(column.sortBy)" :name="ascending ? 'chevronUp' : 'chevronDown'" :size="12" :stroke-width="2.5" class-name="pc-sort-on" />
                                    <Icon v-else name="sortIdle" :size="12" class-name="pc-sort-idle" />
                                </button>
                                <span v-else class="pc-th-label">{{ column.label }}</span>
                            </div>
                            <PtahCrudColumnResizer :crud="crud" :field="column.field" :label="column.label" :locale="locale" />
                        </th>
                        <th v-for="label in actions" :key="`action-${label}`" class="pc-th-action">{{ label || w.action }}</th>
                        <th v-if="rowButtons" class="pc-sticky">{{ w.actions }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="state.rows.length === 0">
                        <td class="pc-empty-cell" :colspan="span">
                            <!-- forge-empty: a filtered listing offers to clear the filters. -->
                            <div class="pc-empty-state">
                                <div class="pc-empty-box"><Icon :name="filtered ? 'funnel' : 'inbox'" :size="32" :stroke-width="1.5" /></div>
                                <div>
                                    <p class="pc-empty-ttl">{{ filtered ? w.emptyFilteredTitle : w.emptyTitle }}</p>
                                    <p class="pc-empty-sub">{{ filtered ? w.emptyFilteredSubtitle : w.emptySubtitle }}</p>
                                </div>
                                <button v-if="filtered" type="button" class="pc-tbtn pc-empty-cta" @click="clearAll"><Icon name="x" :size="14" />{{ w.clearFilters }}</button>
                            </div>
                        </td>
                    </tr>
                    <template v-for="(row, index) in state.rows" :key="row.id">
                        <tr v-if="breakAt(index)" class="pc-break">
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <td :colspan="span"><span class="pc-break-key">{{ breakAt(index)!.label }}:</span> <span v-html="breakAt(index)!.title" /></td>
                        </tr>
                        <!-- configLinkLinha: the row opens its record (Enter when focused). -->
                        <tr
                            :class="['pc-tr', { 'pc-tr--selected': isSelected(row), 'pc-tr--link': row.link }]"
                            :style="rowStyle(row.style)"
                            :tabindex="row.link ? 0 : undefined"
                            :data-link="row.link ?? undefined"
                            @click="followLink($event, row)"
                            @auxclick="followLink($event, row)"
                            @keydown="followLink($event, row)"
                        >
                            <td v-if="detail" class="pc-td-narrow" @click.stop>
                                <PtahCrudDetailToggle :crud="crud" :row="row" :locale="locale" />
                            </td>
                            <td v-if="state.schema.selectable" class="pc-td-narrow" @click.stop>
                                <input type="checkbox" :aria-label="fillWords(w.selectRow, { id: row.id })" :checked="isSelected(row)" @change="crud.toggleRow(row.id)" />
                            </td>
                            <!-- The HTML comes rendered and escaped by the server: the same
                                 string the Livewire screen prints for this cell. -->
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <td
                                v-for="column in state.schema.columns"
                                :key="column.field"
                                :class="alignClass(column.align)"
                                :style="widthOf(column.field)"
                                :data-ptah-cell="column.field"
                                :data-ptah-row="row.id"
                                :data-ptah-filter="cellFilter(column.field) ? '' : undefined"
                                v-html="row.cells[column.field]?.html ?? ''"
                            />
                            <td v-for="label in actions" :key="`action-${label}`" class="pc-align-center" @click.stop>
                                <PtahRowAction v-if="actionOf(row, label)" :action="actionOf(row, label)!" @run="runAction(row, actionOf(row, label)!)" />
                            </td>
                            <td v-if="rowButtons" class="pc-sticky">
                                <PtahRowButtons :crud="crud" :row="row" :permissions="state.schema.permissions" :w="w" :trashed="state.list.trashed" @run="runAction(row, $event)" />
                            </td>
                        </tr>
                        <tr v-if="breakEndingAt(index)" class="pc-subtotal">
                            <td v-if="lead > 0" :colspan="lead" />
                            <td v-for="(column, columnIndex) in state.schema.columns" :key="column.field" :class="alignClass(column.align)">
                                <template v-if="breakEndingAt(index)!.subtotals[column.field] !== undefined">{{ breakEndingAt(index)!.subtotals[column.field] }}</template>
                                <span v-else-if="columnIndex === 0" class="pc-break-key">{{ fillWords(w.subtotal, { n: breakEndingAt(index)!.count }) }}</span>
                            </td>
                            <td v-for="label in actions" :key="`action-${label}`" />
                            <td v-if="rowButtons" />
                        </tr>
                        <PtahCrudDetailRow v-if="detail" :crud="crud" :row="row" :col-span="span" :locale="locale">
                            <!-- The child screen, bound by the server to this row: its own
                                 controller, nothing remembered (the Livewire nested grid starts
                                 clean on every expansion too). Its title is the small heading
                                 above it, as in _table.blade.php. -->
                            <template #detail="{ detail: child }">
                                <p v-if="child.title" class="pc-detail-title">{{ child.title }}</p>
                                <PtahCrud :screen="child.screen" :locale="locale" :api="crud.api" :remember="false" :toast-ms="toastMs" :on-action="onAction" :open="open" />
                            </template>
                        </PtahCrudDetailRow>
                    </template>
                </tbody>
                <tfoot v-if="state.totals.length > 0">
                    <tr>
                        <td v-if="lead > 0" :colspan="lead" />
                        <td v-for="column in state.schema.columns" :key="column.field" :class="alignClass(column.align)">
                            <div v-for="total in totalsOf(column.field)" :key="total.aggregate">
                                <span v-if="totalsOf(column.field).length > 1" class="pc-total-agg">{{ aggregateLabel(w, total.aggregate) }}</span>{{ total.text ?? String(total.value ?? '') }}
                            </div>
                        </td>
                        <td v-for="label in actions" :key="`action-${label}`" />
                        <td v-if="rowButtons" />
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Pagination (_pagination + forge-pagination). A board shows every record of the filter: no pages. -->
        <div v-if="meta && !board" class="pc-pag">
            <span>{{ summary }}</span>
            <div class="pc-pag-end">
                <nav v-if="meta.last_page > 1" class="pc-pag-nav" :aria-label="w.paginationNav">
                    <div class="pc-pag-mobile">
                        <button type="button" class="pc-pag-mbtn" :disabled="meta.page <= 1" @click="go(meta.page - 1)">{{ w.previous }}</button>
                        <span>{{ meta.page }} / {{ meta.last_page }}</span>
                        <button type="button" class="pc-pag-mbtn" :disabled="meta.page >= meta.last_page" @click="go(meta.page + 1)">{{ w.next }}</button>
                    </div>
                    <div class="pc-pag-pages">
                        <button type="button" class="pc-pag-icon" :disabled="meta.page <= 1" :aria-label="w.previousPage" :title="w.previousPage" @click="go(meta.page - 1)">
                            <Icon name="chevronLeft" />
                        </button>
                        <template v-for="(page, index) in pageElements(meta.page, meta.last_page)" :key="`${page}-${index}`">
                            <span v-if="page === '…'" class="pc-pag-gap">…</span>
                            <button
                                v-else
                                type="button"
                                class="pc-pag-num"
                                :aria-current="page === meta.page ? 'page' : undefined"
                                :aria-label="page === meta.page ? w.currentPage : undefined"
                                @click="go(page)"
                            >
                                {{ page }}
                            </button>
                        </template>
                        <button type="button" class="pc-pag-icon" :disabled="meta.page >= meta.last_page" :aria-label="w.nextPage" :title="w.nextPage" @click="go(meta.page + 1)">
                            <Icon name="chevronRight" />
                        </button>
                    </div>
                    <p class="pc-pag-sum">{{ fillWords(w.pageOf, { current: meta.page, last: meta.last_page }) }}</p>
                </nav>
                <label v-if="meta.last_page > 2" class="pc-pag-jump">
                    <span>{{ w.goTo }}</span>
                    <input v-model.number="jump" type="number" :min="1" :max="meta.last_page" @keydown.enter="go(jump)" @blur="onJumpBlur" />
                </label>
            </div>
        </div>

        <!-- Bulk bar (floating, base-crud.blade.php) -->
        <template v-if="state.selection.length > 0">
            <div class="pc-bulkbar" role="region" :aria-label="fillWords(w.nSelected, { n: state.selection.length })">
                <Icon name="clipboard" />
                <span class="pc-bulkbar-count">{{ fillWords(w.nSelected, { n: state.selection.length }) }}</span>
                <template v-if="state.list.trashed">
                    <button v-if="state.schema.permissions.canDelete" type="button" class="pc-bulkbar-btn pc-bulkbar-btn--danger" @click="bulkAsk = 'force'">
                        <Icon name="trash" :size="14" />{{ w.bulkForceDelete }}
                    </button>
                    <button v-if="state.schema.permissions.canRestore" type="button" class="pc-bulkbar-btn" @click="crud.bulk('restore')">
                        <Icon name="refresh" :size="14" />{{ w.bulkRestore }}
                    </button>
                </template>
                <template v-else>
                    <button v-for="action in state.schema.bulkActions" :key="action.action" type="button" class="pc-bulkbar-btn" @click="runBulk(action.action, action.confirm)">
                        <i v-if="action.icon" :class="action.icon" aria-hidden="true" />{{ action.label }}
                    </button>
                    <button v-if="state.schema.permissions.canDelete" type="button" class="pc-bulkbar-btn pc-bulkbar-btn--danger" @click="bulkAsk = 'delete'">
                        <Icon name="trash" :size="14" />{{ w.bulkDelete }}
                    </button>
                </template>
                <button type="button" class="pc-bulkbar-btn" @click="crud.clearSelection()">{{ w.bulkCancel }}</button>
            </div>
            <!-- The floating bar never covers the pagination. -->
            <div class="pc-bulkbar-spacer" aria-hidden="true" />
        </template>
        <div v-if="bulkAsk" class="pc-dialog-back" @mousedown.self="bulkAsk = null">
            <div
                class="pc-dialog"
                role="alertdialog"
                aria-modal="true"
                :aria-label="fillWords(bulkAsk === 'force' ? w.bulkForceConfirm : w.bulkDeleteConfirm, { n: state.selection.length })"
            >
                <div class="pc-dialog-row">
                    <div class="pc-dialog-icon"><Icon name="warning" :size="20" /></div>
                    <div>
                        <p class="pc-dialog-ttl">{{ fillWords(bulkAsk === 'force' ? w.bulkForceConfirm : w.bulkDeleteConfirm, { n: state.selection.length }) }}</p>
                        <p v-if="bulkAsk === 'force'" class="pc-dialog-sub pc-dialog-sub--danger">{{ w.bulkIrreversible }}</p>
                    </div>
                </div>
                <div class="pc-dialog-foot">
                    <button type="button" class="pc-dbtn" @click="bulkAsk = null">{{ w.cancel }}</button>
                    <button type="button" class="pc-dbtn pc-dbtn--danger" autofocus @click="confirmBulkDelete">{{ w.deleteButton }}</button>
                </div>
            </div>
        </div>

        <PtahForm v-if="state.form || crud.formOpening" :crud="crud" :state="state" :t="t" :locale="locale" />
        <!-- After the form in the DOM: they open over it. -->
        <PtahCrudHistory :crud="crud" :locale="locale" />
        <PtahCrudAttachments :crud="crud" :locale="locale" />
        <PtahCrudImport :crud="crud" :locale="locale" />

        <PtahConfirm
            v-if="state.deleting !== null"
            :title="t.deleteTitle"
            :text="t.deleteMessage"
            :confirm="t.delete"
            :cancel="t.cancel"
            :close-label="t.close"
            danger
            @confirm="crud.confirmDelete()"
            @cancel="crud.cancelDelete()"
        />
        <PtahConfirm v-if="pendingBulk" :text="pendingBulk.text" :confirm="w.confirm" :cancel="w.cancel" @confirm="confirmBulk" @cancel="pendingBulk = null" />
        <PtahConfirm v-if="pendingAction" :text="pendingAction.action.confirm ?? ''" :confirm="w.confirm" :cancel="w.cancel" @confirm="confirmAction" @cancel="pendingAction = null" />

        <!-- The `?` list of the keyboard shortcuts. -->
        <div v-if="showKeys" class="pc-dialog-back" @mousedown.self="showKeys = false">
            <div class="pc-dialog pc-dialog--sm" role="dialog" aria-modal="true" aria-labelledby="pc-shortcuts-title">
                <div class="pc-dialog-head">
                    <h3 id="pc-shortcuts-title">{{ w.shortcutsTitle }}</h3>
                    <button type="button" class="pc-x" :aria-label="w.close" :title="w.close" @click="showKeys = false"><Icon name="x" :size="20" /></button>
                </div>
                <dl class="pc-keys">
                    <div v-for="[label, key] in shortcuts" :key="key">
                        <dt>{{ label }}</dt>
                        <dd><kbd class="pc-kbd">{{ key }}</kbd></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Toasts (forge-toast-host) -->
        <div class="pc-toasts pc-tst-host" aria-live="polite">
            <div v-for="toast in state.toasts" :key="toast.id" class="pc-tst" :data-color="toast.level" role="status">
                <Icon v-if="TOAST_ICONS[toast.level]" :name="TOAST_ICONS[toast.level]" />
                <span class="pc-toast-text">{{ toast.text }}</span>
                <button v-if="toast.undo !== undefined && toast.undo !== null" type="button" class="pc-tst-undo" @click="crud.undo(toast.id)">{{ t.undo }}</button>
                <button type="button" class="pc-tst-x" :aria-label="w.close" :title="w.close" @click="crud.dismiss(toast.id)"><Icon name="x" :size="14" :stroke-width="2.5" /></button>
            </div>
        </div>
    </div>
</template>
