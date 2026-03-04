<?php

declare(strict_types=1);

namespace Ptah\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Ptah\Services\Crud\CrudConfigService;

/**
 * BaseCrud configuration component.
 *
 * Allows visually managing:
 *  - Columns (order, type, helpers, totalisers)
 *  - Row actions (link / livewire / javascript)
 *  - Custom filters (whereHas, aggregates)
 *  - Conditional row styles
 *  - General settings (link, cache, export, UI)
 *  - Permissions and button visibility
 *
 * Usage: @livewire('ptah-crud-config', ['model' => $model])
 */
class CrudConfig extends Component
{
    // ── Identification ────────────────────────────────────────────────────────────

    public string $model = '';
    public bool   $showModal = false;

    // ── Columns ──────────────────────────────────────────────────────────────

    /** All columns (including actions) */
    public array $formEditFields = [];

    /** Field being added/edited */
    public array $formDataField = [];

    /** Index being edited (-1 = new) */
    public int $editingFieldIndex = -1;

    // ── Row actions ────────────────────────────────────────────────────────────

    public array $formDataAction    = [];
    public int   $editingActionIndex = -1;

    // ── Custom filters ────────────────────────────────────────────────────────────

    public array $customFilters  = [];
    public array $formDataFilter = [];
    // ── Configured JOINs ─────────────────────────────────────────────

    public array $joins          = []; // saved joins
    public array $formDataJoin   = []; // form for new join being filled
    public int   $editingJoinIndex = -1; // index being edited (-1 = new)
    // ── Conditional styles ──────────────────────────────────────────────────

    public array $conditionStyles = [];
    public array $formDataStyle   = [];

    // ── General ────────────────────────────────────────────────────────────

    public string $displayName          = '';  // name displayed in modal and toolbar
    public string $configLinkLinha       = '';
    public string $tableClass            = '';
    public string $theadClass            = '';
    public bool   $cacheEnabled          = true;
    public int    $cacheTtl              = 300;
    public int    $exportAsyncThreshold  = 1000;
    public int    $exportMaxRows         = 10000;
    public string $exportOrientation     = 'landscape';
    public bool   $uiCompactMode         = false;
    public bool   $uiStickyHeader        = true;
    public bool   $showTotalizador       = false;

    // ── Broadcast (Echo listener) ────────────────────────────────────

    public bool   $broadcastEnabled = false;
    public string $broadcastChannel = ''; // empty = auto-generated
    public string $broadcastEvent   = ''; // empty = auto-generated

    // ── GroupBy ────────────────────────────────────────────────────────
    public string $groupBy = ''; // field name for GROUP BY, empty = disabled

    // ── Visual Theme ────────────────────────────────────────────────────
    public string $theme = 'light'; // 'light' | 'dark'

    // ── Permissions ─────────────────────────────────────────────────────────────

    public string $permissionCreate     = '';
    public string $permissionEdit       = '';
    public string $permissionDelete     = '';
    public string $permissionExport     = '';
    public string $permissionRestore    = '';
    public bool   $showCreateButton     = true;
    public bool   $showEditButton       = true;
    public bool   $showDeleteButton     = true;
    public bool   $showTrashButton      = true;
    public string $permissionIdentifier = '';

    // ── Service ───────────────────────────────────────────────────────────────────

    protected CrudConfigService $configService;

    public function boot(CrudConfigService $configService): void
    {
        $this->configService = $configService;
    }

    // ── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(string $model): void
    {
        $this->model = $model;
        $this->loadFromDb();
    }

    public function render(): \Illuminate\View\View
    {
        return view('ptah::livewire.crud-config');
    }

    // ── Modal ────────────────────────────────────────────────────────────────

    /**
     * Recarrega config do DB e reseta formulários sem abrir o modal.
     * Chamado via Alpine: @click="$wire.showModal = true; $wire.prepareModal()"
     */
    public function prepareModal(): void
    {
        $this->loadFromDb();
        $this->formDataField  = [];
        $this->formDataAction = [];
        $this->formDataFilter = [];
        $this->formDataStyle  = [];
        $this->formDataJoin   = [];
        $this->editingFieldIndex  = -1;
        $this->editingActionIndex = -1;
        $this->editingJoinIndex   = -1;
    }

    /** @deprecated Use Alpine: @click="$wire.showModal = true; $wire.prepareModal()" */
    public function openModal(): void
    {
        $this->prepareModal();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal         = false;
        $this->formDataField     = [];
        $this->formDataAction    = [];
        $this->formDataFilter    = [];
        $this->formDataStyle     = [];
        $this->formDataJoin      = [];
        $this->editingFieldIndex  = -1;
        $this->editingActionIndex = -1;
        $this->editingJoinIndex   = -1;
    }

    // ── Load config ──────────────────────────────────────────────────────

    protected function loadFromDb(): void
    {
        $record = $this->configService->find($this->model);
        if (! $record) {
            return;
        }

        $cfg = $record->config;

        // Cols — converts colsSelect array → string for editing
        $cols = $cfg['cols'] ?? [];
        foreach ($cols as &$col) {
            if (isset($col['colsSelect']) && is_array($col['colsSelect'])) {
                $parts = [];
                foreach ($col['colsSelect'] as $k => $v) {
                    $parts[] = $k . ';' . $v;
                }
                $col['colsSelect'] = implode(';;', $parts);
            }
        }
        $this->formEditFields = array_values($cols);

        // Filters and styles
        $this->customFilters   = $cfg['customFilters']   ?? [];
        $this->conditionStyles = $cfg['contitionStyles'] ?? [];

        // JOINs
        $this->joins = $cfg['joins'] ?? [];

        // General
        $this->displayName     = $cfg['displayName']     ?? '';
        $this->configLinkLinha = $cfg['configLinkLinha'] ?? '';
        $this->tableClass      = $cfg['tableClass']      ?? '';
        $this->theadClass      = $cfg['theadClass']      ?? '';

        $cache = $cfg['cacheStrategy'] ?? [];
        $this->cacheEnabled = (bool) ($cache['enabled'] ?? true);
        $this->cacheTtl     = (int)  ($cache['ttl']     ?? 300);

        $export = $cfg['exportConfig'] ?? [];
        $this->exportAsyncThreshold = (int) ($export['asyncThreshold'] ?? 1000);
        $this->exportMaxRows        = (int) ($export['maxRows']        ?? 10000);
        $this->exportOrientation    = $export['orientation']           ?? 'landscape';

        $ui = $cfg['uiPreferences'] ?? [];
        $this->uiCompactMode   = (bool) ($ui['compactMode']    ?? false);
        $this->uiStickyHeader  = (bool) ($ui['stickyHeader']   ?? true);
        $this->showTotalizador = (bool) ($ui['showTotalizador'] ?? false);

        // Broadcast
        $bc = $cfg['broadcast'] ?? [];
        $this->broadcastEnabled = (bool) ($bc['enabled'] ?? false);
        $this->broadcastChannel = $bc['channel'] ?? '';
        $this->broadcastEvent   = $bc['event']   ?? '';

        // GroupBy
        $this->groupBy = $cfg['groupBy'] ?? '';

        // Theme
        $this->theme = $cfg['theme'] ?? 'light';

        // Permissions
        $perms = $cfg['permissions'] ?? [];
        $this->permissionCreate     = $perms['create']  ?? '';
        $this->permissionEdit       = $perms['edit']    ?? '';
        $this->permissionDelete     = $perms['delete']  ?? '';
        $this->permissionExport     = $perms['export']  ?? '';
        $this->permissionRestore    = $perms['restore'] ?? '';
        $this->showCreateButton     = (bool) ($perms['showCreateButton'] ?? true);
        $this->showEditButton       = (bool) ($perms['showEditButton']   ?? true);
        $this->showDeleteButton     = (bool) ($perms['showDeleteButton'] ?? true);
        $this->showTrashButton      = (bool) ($perms['showTrashButton']  ?? true);
        $this->permissionIdentifier = $perms['identifier'] ?? $this->getDefaultPermissionIdentifier();
    }

    // ── Colunas — CRUD ───────────────────────────────────────────────────────

    public function addField(): void
    {
        if (empty($this->formDataField['colsNomeFisico'])) {
            return;
        }

        $defaults = [
            'colsTipo'              => 'text',
            'colsGravar'            => true,
            'colsRequired'          => false,
            'colsAlign'             => 'text-start',
            'colsIsFilterable'      => true,
            'colsNomeLogico'        => ucfirst($this->formDataField['colsNomeFisico']),
            // Renderer DSL
            'colsRenderer'          => '',
            'colsRendererBadges'    => [],
            'colsRendererCurrency'  => 'BRL',
            'colsRendererDecimals'  => 2,
            'colsRendererMaxChars'  => 50,
            'colsRendererLinkTemplate' => '',
            'colsRendererLinkLabel' => '',
            'colsRendererLinkNewTab'=> false,
            'colsRendererBoolTrue'  => 'Yes',
            'colsRendererBoolFalse' => 'No',
            'colsRendererImageWidth'=> 40,
            // Mask and cleanup
            'colsMask'              => '',
            'colsMaskTransform'     => '',
            // Nested relation (dot notation)
            'colsRelacaoNested'     => '',
            // Validations
            'colsValidations'       => [],
            // SearchDropdown
            'colsSDMode'            => 'model',
            // Cell style
            'colsCellStyle'         => '',
            'colsCellClass'         => '',
            'colsCellIcon'          => '',
            'colsMinWidth'          => '',
        ];

        $merged = array_merge($defaults, $this->formDataField);
        $merged = $this->resolveJoinDefaults($merged);

        $this->formEditFields[] = $merged;
        $this->formDataField    = [];
    }

    public function editField(int $index): void
    {
        if (! isset($this->formEditFields[$index])) {
            return;
        }

        $this->editingFieldIndex = $index;
        $this->formDataField     = $this->formEditFields[$index];
    }

    public function updateField(): void
    {
        if ($this->editingFieldIndex < 0 || ! isset($this->formEditFields[$this->editingFieldIndex])) {
            return;
        }

        $this->formEditFields[$this->editingFieldIndex] = $this->resolveJoinDefaults($this->formDataField);
        $this->formDataField     = [];
        $this->editingFieldIndex = -1;
    }

    /**
     * Resolves automatic defaults for columns sourced from JOINs.
     *
     * Rules applied:
     *  1. If colsNomeFisico matches the alias of a JOIN select
     *     → colsGravar = false (never writes external table column)
     *     → colsSource  = "table.column" (qualified for use in WHERE)
     *
     *  2. If colsSource uses Eloquent 2-part notation ("relation.column") and
     *     there is a JOIN for that table, converts to qualified SQL.
     *     e.g.: "product.name"  →  "products.name"
     *
     *  3. If colsSource uses chained Eloquent notation of 3+ parts
     *     ("a.b.column"), extracts the last two segments and resolves via JOIN.
     *     e.g.: "product_supplier.product.name" → "products.name",
     *         colsNomeFisico corrected to alias (e.g. "product_name"),
     *         colsGravar = false.
     */
    protected function resolveJoinDefaults(array $fieldData): array
    {
        if (empty($this->joins)) {
            return $fieldData;
        }

        $nomeFisico = $fieldData['colsNomeFisico'] ?? '';
        $source     = $fieldData['colsSource']     ?? '';

        // Build map: alias → ['table' => ..., 'column' => ...]
        // Build reverse map: "table.column" → alias
        $aliasMap    = [];  // alias → ['table', 'column']
        $qualifiedMap = []; // "table.col" → alias
        foreach ($this->joins as $join) {
            foreach ($join['select'] ?? [] as $sel) {
                $alias = trim($sel['alias']  ?? '');
                $col   = trim($sel['column'] ?? '');
                if ($alias && $col) {
                    $aliasMap[$alias]  = ['table' => $join['table'] ?? '', 'column' => $col];
                    $qualifiedMap[$col] = $alias;
                }
            }
        }

        // Rule 3 — chained notation of 3+ parts: "a.b.column"
        // Runs before the others to normalise $source and $nomeFisico
        if (! empty($source) && substr_count($source, '.') >= 2) {
            $segments  = explode('.', $source);
            $lastCol   = array_pop($segments);                 // "name"
            $lastRel   = array_pop($segments);                 // "product"

            // Resolves the table of the last relation (singular → plural and vice-versa)
            $resolved = null;
            foreach ($this->joins as $join) {
                $table = $join['table'] ?? '';
                if ($table === $lastRel || $table === $lastRel . 's' || rtrim($table, 's') === $lastRel) {
                    $resolved = $table;
                    break;
                }
            }

            if ($resolved) {
                $qualifiedCol            = "{$resolved}.{$lastCol}";
                $fieldData['colsSource'] = $qualifiedCol;
                $fieldData['colsGravar'] = false;
                // Correct colsNomeFisico to alias if it exists in the map
                if (isset($qualifiedMap[$qualifiedCol])) {
                    $fieldData['colsNomeFisico'] = $qualifiedMap[$qualifiedCol];
                }
                // Update source to the normalised version
                $source     = $qualifiedCol;
                $nomeFisico = $fieldData['colsNomeFisico'];
            }
        }

        // Rule 1 — colsNomeFisico matches JOIN alias
        if (isset($aliasMap[$nomeFisico])) {
            $fieldData['colsGravar'] = false;
            // Fill colsSource only if not set or incorrect
            if (empty($source) || ! str_contains($source, '.')) {
                $fieldData['colsSource'] = $aliasMap[$nomeFisico]['column'];
            }
        }

        // Rule 2 — correct Eloquent 2-part notation "relation.column" → "table.column"
        if (! empty($source) && substr_count($source, '.') === 1) {
            [$relation, $col] = explode('.', $source, 2);
            foreach ($this->joins as $join) {
                $table = $join['table'] ?? '';
                if ($table === $relation) {
                    break; // already correct — it is table.column
                }
                if ($table === $relation . 's' || rtrim($table, 's') === $relation) {
                    $fieldData['colsSource'] = "{$table}.{$col}";
                    $fieldData['colsGravar'] = false;
                    break;
                }
            }
        }

        return $fieldData;
    }

    public function cancelEditField(): void
    {
        $this->formDataField     = [];
        $this->editingFieldIndex = -1;
    }

    public function removeField(int $index): void
    {
        array_splice($this->formEditFields, $index, 1);
        $this->formEditFields = array_values($this->formEditFields);
    }

    public function moveFieldUp(int $index): void
    {
        if ($index <= 0 || ! isset($this->formEditFields[$index])) {
            return;
        }

        [$this->formEditFields[$index - 1], $this->formEditFields[$index]] =
            [$this->formEditFields[$index], $this->formEditFields[$index - 1]];

        $this->formEditFields = array_values($this->formEditFields);
    }

    public function moveFieldDown(int $index): void
    {
        $last = count($this->formEditFields) - 1;

        if ($index >= $last || ! isset($this->formEditFields[$index])) {
            return;
        }

        [$this->formEditFields[$index], $this->formEditFields[$index + 1]] =
            [$this->formEditFields[$index + 1], $this->formEditFields[$index]];

        $this->formEditFields = array_values($this->formEditFields);
    }

    /**
     * Reorders columns from an index array received from SortableJS.
     * Called via wire:sortable or via JS: $wire.reorderFields(newOrderArray)
     *
     * @param array $order  Array of indices in the new order — e.g.: [2, 0, 1, 3]
     */
    public function reorderFields(array $order): void
    {
        $reordered = [];

        foreach ($order as $index) {
            $idx = (int) $index;
            if (isset($this->formEditFields[$idx])) {
                $reordered[] = $this->formEditFields[$idx];
            }
        }

        // Ensures items not present in the new array (for safety) are kept
        if (count($reordered) === count($this->formEditFields)) {
            $this->formEditFields = $reordered;
        }
    }

    // ── Actions — CRUD ─────────────────────────────────────────────────────────

    public function addAction(): void
    {
        if (empty($this->formDataAction['colsNomeLogico'])) {
            return;
        }

        // Remove empty fields so that defaults are not overwritten
        $data = array_filter($this->formDataAction, fn($v) => $v !== '' && $v !== null);

        $merged = array_merge([
            'actionType'       => 'link',
            'actionValue'      => '',
            'actionIcon'       => 'bx bx-link',
            'actionColor'      => 'primary',
            'actionPermission' => '',
        ], $data, [
            'colsNomeFisico'   => 'id',
            'colsTipo'         => 'action',
            'colsGravar'       => false,
            'colsRequired'     => false,
            'colsIsFilterable' => false,
        ]);

        if ($this->editingActionIndex >= 0 && isset($this->formEditFields[$this->editingActionIndex])) {
            $this->formEditFields[$this->editingActionIndex] = $merged;
        } else {
            $this->formEditFields[] = $merged;
        }

        $this->formDataAction    = [];
        $this->editingActionIndex = -1;
    }

    public function editAction(int $index): void
    {
        if (! isset($this->formEditFields[$index]) || ($this->formEditFields[$index]['colsTipo'] ?? '') !== 'action') {
            return;
        }

        $this->formDataAction    = $this->formEditFields[$index];
        $this->editingActionIndex = $index;
    }

    public function cancelEditAction(): void
    {
        $this->formDataAction    = [];
        $this->editingActionIndex = -1;
    }

    public function removeAction(int $index): void
    {
        array_splice($this->formEditFields, $index, 1);
        $this->formEditFields = array_values($this->formEditFields);

        if ($this->editingActionIndex === $index) {
            $this->formDataAction    = [];
            $this->editingActionIndex = -1;
        }
    }
    // ── JOINs ───────────────────────────────────────────────────────────────

    public function addJoin(): void
    {
        $table = trim($this->formDataJoin['table'] ?? '');

        if (! $table) {
            return;
        }

        // Guard: detect duplicate table
        if ($this->editingJoinIndex < 0) {
            $existingTables = array_column($this->joins, 'table');
            if (in_array($table, $existingTables)) {
                session()->flash('joinError', "A JOIN for table '{$table}' already exists.");
                return;
            }
        }

        // Normaliza o array de colunas select (remove entradas vazias)
        $selectRaw = $this->formDataJoin['selectRaw'] ?? '';
        $selectCols = [];
        foreach (explode("\n", $selectRaw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // format: "table.column:alias" or "table.column" (alias = last segment)
            if (str_contains($line, ':')) {
                [$col, $alias] = array_map('trim', explode(':', $line, 2));
            } else {
                $col   = $line;
                $alias = str_replace('.', '_', $line);
            }
            if ($col) {
                $selectCols[] = ['column' => $col, 'alias' => $alias];
            }
        }

        $entry = [
            'type'     => $this->formDataJoin['type']     ?? 'left',
            'table'    => $table,
            'first'    => trim($this->formDataJoin['first']  ?? ''),
            'second'   => trim($this->formDataJoin['second'] ?? ''),
            'distinct' => (bool) ($this->formDataJoin['distinct'] ?? false),
            'select'   => $selectCols,
        ];

        if ($this->editingJoinIndex >= 0 && isset($this->joins[$this->editingJoinIndex])) {
            $this->joins[$this->editingJoinIndex] = $entry;
        } else {
            $this->joins[] = $entry;
        }

        $this->formDataJoin     = [];
        $this->editingJoinIndex = -1;
    }

    public function editJoin(int $index): void
    {
        if (! isset($this->joins[$index])) {
            return;
        }

        $join = $this->joins[$index];

        // Rebuild selectRaw from columns array
        $lines = [];
        foreach ($join['select'] ?? [] as $sel) {
            $lines[] = ($sel['column'] ?? '') . ':' . ($sel['alias'] ?? '');
        }

        $this->editingJoinIndex = $index;
        $this->formDataJoin = [
            'type'      => $join['type']     ?? 'left',
            'table'     => $join['table']    ?? '',
            'first'     => $join['first']    ?? '',
            'second'    => $join['second']   ?? '',
            'distinct'  => $join['distinct'] ?? false,
            'selectRaw' => implode("\n", $lines),
        ];
    }

    public function cancelEditJoin(): void
    {
        $this->formDataJoin     = [];
        $this->editingJoinIndex = -1;
    }

    public function removeJoin(int $index): void
    {
        array_splice($this->joins, $index, 1);
        $this->joins = array_values($this->joins);

        if ($this->editingJoinIndex === $index) {
            $this->formDataJoin     = [];
            $this->editingJoinIndex = -1;
        }
    }
    // ── Filtros personalizados — CRUD ─────────────────────────────────────────

    public function addCustomFilter(): void
    {
        if (empty($this->formDataFilter['field'])) {
            return;
        }

        $this->customFilters[] = $this->formDataFilter;
        $this->formDataFilter  = [];
    }

    public function removeCustomFilter(int $index): void
    {
        array_splice($this->customFilters, $index, 1);
    }

    // ── Estilos condicionais — CRUD ───────────────────────────────────────────

    public function addConditionStyle(): void
    {
        if (empty($this->formDataStyle['field'])) {
            return;
        }

        $this->conditionStyles[] = $this->formDataStyle;
        $this->formDataStyle     = [];
    }

    public function removeConditionStyle(int $index): void
    {
        array_splice($this->conditionStyles, $index, 1);
    }

    // ── Salvar ───────────────────────────────────────────────────────────────

    public function save(): void
    {
        $record   = $this->configService->find($this->model);
        $existing = $record ? ($record->config ?? []) : [];

        $this->configService->save($this->model, $this->buildConfigArray($existing));

        $this->showModal = false;
        $this->dispatch('ptah:crud-config-updated');

        session()->flash('crud-success', 'Configuration saved successfully!');
    }

    protected function buildConfigArray(array $existing = []): array
    {
        return array_merge($existing, [
            'displayName'     => $this->displayName,
            'crud'            => $existing['crud']            ?? $this->model,
            'configLinkLinha' => $this->configLinkLinha,
            'configEsconderId'=> $existing['configEsconderId'] ?? false,
            'tableClass'      => $this->tableClass,
            'theadClass'      => $this->theadClass,
            'cols'            => $this->formatFieldsForDb(),
            'customFilters'   => array_values($this->customFilters),
            'contitionStyles' => array_values($this->conditionStyles),
            'joins'           => array_values($this->joins),
            'permissions'     => [
                'create'            => $this->permissionCreate  ?: null,
                'edit'              => $this->permissionEdit    ?: null,
                'delete'            => $this->permissionDelete  ?: null,
                'export'            => $this->permissionExport  ?: null,
                'restore'           => $this->permissionRestore ?: null,
                'showCreateButton'  => $this->showCreateButton,
                'showEditButton'    => $this->showEditButton,
                'showDeleteButton'  => $this->showDeleteButton,
                'showTrashButton'   => $this->showTrashButton,
                'identifier'        => $this->permissionIdentifier ?: $this->getDefaultPermissionIdentifier(),
            ],
            'cacheStrategy'   => [
                'enabled' => $this->cacheEnabled,
                'ttl'     => $this->cacheTtl,
                'tags'    => $existing['cacheStrategy']['tags'] ?? [],
            ],
            'exportConfig'    => array_merge($existing['exportConfig'] ?? [], [
                'enabled'             => true,
                'asyncThreshold'      => $this->exportAsyncThreshold,
                'maxRows'             => $this->exportMaxRows,
                'orientation'         => $this->exportOrientation,
                'formats'             => ['excel', 'pdf'],
                'chunkSize'           => 500,
                'notificationChannel' => 'database',
            ]),
            'uiPreferences'   => array_merge($existing['uiPreferences'] ?? [], [
                'theme'             => $this->theme,
                'compactMode'       => $this->uiCompactMode,
                'stickyHeader'      => $this->uiStickyHeader,
                'showTotalizador'   => $this->showTotalizador,
                'highlightOnHover'  => $existing['uiPreferences']['highlightOnHover'] ?? true,
            ]),
            'broadcast'       => [
                'enabled' => $this->broadcastEnabled,
                'channel' => $this->broadcastChannel ?: null,
                'event'   => $this->broadcastEvent   ?: null,
            ],
            'theme'           => $this->theme,
            'groupBy'         => $this->groupBy ?: null,
        ]);
    }

    protected function formatFieldsForDb(): array
    {
        $fields = $this->formEditFields;

        foreach ($fields as &$field) {
            // Converts colsSelect string "k;v;;k2;v2" → associative array
            if (
                isset($field['colsSelect'])
                && is_string($field['colsSelect'])
                && ($field['colsTipo'] ?? '') === 'select'
                && $field['colsSelect'] !== ''
            ) {
                $map = [];
                foreach (explode(';;', $field['colsSelect']) as $pair) {
                    $parts = explode(';', $pair, 2);
                    if (count($parts) === 2 && $parts[0] !== '') {
                        $map[$parts[0]] = $parts[1];
                    }
                }
                $field['colsSelect'] = $map;
            }
        }

        return $fields;
    }

    protected function getDefaultPermissionIdentifier(): string
    {
        // e.g.: 'Purchase/Order/SalesOrders' → 'pageSalesOrders'
        return 'page' . class_basename(str_replace('/', '\\', $this->model));
    }
}
