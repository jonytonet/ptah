<?php

declare(strict_types=1);

namespace Ptah\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Ptah\Livewire\Concerns\HasCrudBulkActions;
use Ptah\Livewire\Concerns\HasCrudColumns;
use Ptah\Livewire\Concerns\HasCrudDeletion;
use Ptah\Livewire\Concerns\HasCrudExport;
use Ptah\Livewire\Concerns\HasCrudFilters;
use Ptah\Livewire\Concerns\HasCrudForm;
use Ptah\Livewire\Concerns\HasCrudLifecycle;
use Ptah\Livewire\Concerns\HasCrudPreferences;
use Ptah\Livewire\Concerns\HasCrudQuery;
use Ptah\Livewire\Concerns\HasCrudRenderers;
use Ptah\Livewire\Concerns\HasCrudSearchDropdown;
use Ptah\Services\Cache\CacheService;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Services\Crud\FilterService;
use Ptah\Services\Crud\FormValidatorService;

/**
 * Livewire BaseCrud component.
 *
 * Renders a full listing screen with:
 *  - Dynamic table with sort, filters, pagination
 *  - Create / edit modal
 *  - Soft delete / restore
 *  - Export (sync / async)
 *  - Per-user preferences (V2)
 *  - Conditional row styles
 *  - Cell formatting helpers
 *  - SearchDropdown for fields with colsSDModel
 *  - CustomFilters with whereHas
 *
 * Usage:
 *   @livewire('ptah-base-crud', ['model' => 'Product'])
 */
class BaseCrud extends Component
{
    use WithPagination;

    // Lifecycle, configuration reload
    use HasCrudLifecycle;

    // Data querying, filtering, totals
    use HasCrudQuery;

    // Create / edit modal and cell helpers
    use HasCrudForm;

    // Delete / restore / soft-delete
    use HasCrudDeletion;

    // Sort, search, date quick-filters, advanced search, named filters
    use HasCrudFilters;

    // Column visibility and ordering
    use HasCrudColumns;

    // SearchDropdown (inline + filter-panel)
    use HasCrudSearchDropdown;

    // Export (sync / async)
    use HasCrudExport;

    // Bulk actions (select-all, bulk-delete, custom actions)
    use HasCrudBulkActions;

    // User preferences (save / load / defaults)
    use HasCrudPreferences;

    // Cell renderers, row styles, helper formatters
    use HasCrudRenderers;

    // ── Configuration ──────────────────────────────────────────────────────────

    /** Model identifier (e.g. "Product", "Purchase/Order/PurchaseOrders") */
    public string $model = '';

    /** Full CrudConfig configuration array */
    public array $crudConfig = [];

    // ── Table state ───────────────────────────────────────────────────────────

    public string $sort       = 'id';
    public string $direction  = 'DESC';
    public int    $perPage    = 25;
    public string $search     = '';
    public bool   $showTrashed  = false;
    public int    $trashedCount = 0;

    // ── External whereHas ─────────────────────────────────────────────────────

    /** Pre-filter the CRUD by a parent relation */
    public string $whereHasFilter    = '';
    public array  $whereHasCondition = [];

    // ── Column visibility ─────────────────────────────────────────────────────

    /** Map [fieldName => bool] of visible columns */
    public array $formDataColumns    = [];
    public int   $hiddenColumnsCount = 0;

    // ── Active filter badge summary ───────────────────────────────────────────

    /** Active filter badges: [{label, value}] */
    public array $textFilter = [];

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public array $selectedRows         = [];
    public bool  $selectAll            = false;
    public bool  $bulkActionInProgress = false;
    public bool  $showBulkActions      = false;

    // ── Quick date filter ─────────────────────────────────────────────────────

    /** 'today'|'week'|'month'|'quarter'|'year'|'' */
    public string $quickDateFilter = '';

    /** Date column used by the quick date filter */
    public string $quickDateColumn = '';

    // ── Advanced search ───────────────────────────────────────────────────────

    public bool  $advancedSearchActive = false;
    public array $advancedSearchFields = [];
    public array $searchHistory        = [];

    // ── Multi-tenant ──────────────────────────────────────────────────────────

    /** Active company ID (0 = no filter) */
    public int $companyFilter = 0;

    // ── Filters ───────────────────────────────────────────────────────────────

    /** Filter form values (field => value) */
    public array $filters = [];

    /** Per-field operators (field => '='|'LIKE'|'>'|'>='|'<'|'<=') */
    public array $filterOperators = [];

    /** Date range filters (field_start / field_end) */
    public array $dateRanges = [];

    /** Operators for date ranges (field_start/field_end => '='|'>='|'<='|'>'|'<') */
    public array $dateRangeOperators = [];

    /** Named saved filters */
    public array $savedFilters = [];

    /** @var string|null Name of filter currently being saved */
    public ?string $savingFilterName = null;

    public bool $showFilters = false;

    // ── Create / edit modal ───────────────────────────────────────────────────

    public array  $formData  = [];
    public ?int   $editingId = null;
    public bool   $showModal = false;
    public bool   $creating  = false;

    /** Form validation errors */
    public array $formErrors = [];

    // ── Deletion ──────────────────────────────────────────────────────────────

    public bool $showDeleteConfirm = false;
    public ?int $deletingId        = null;

    // ── SearchDropdown ────────────────────────────────────────────────────────

    /** Search term per searchdropdown field: [fieldName => query] */
    public array $sdSearches = [];

    /** Results per field: [fieldName => [{value, label}]] */
    public array $sdResults = [];

    /** Displayed labels per form field: [fieldName => label] */
    public array $sdLabels = [];

    /** Displayed labels per filter SD field: [fieldName => label] */
    public array $sdFilterLabels = [];

    // ── Preferences ───────────────────────────────────────────────────────────

    public array  $columnOrder = [];
    public array  $columnWidths = [];
    public string $viewDensity = 'comfortable'; // compact | comfortable | spacious
    public string $viewMode    = 'table';

    // ── Export ────────────────────────────────────────────────────────────────

    public bool   $showExportMenu = false;
    public string $exportStatus   = '';

    // ── Services (injected via boot) ──────────────────────────────────────────

    protected CrudConfigService    $configService;
    protected FilterService        $filterService;
    protected CacheService         $cacheService;
    protected FormValidatorService $formValidator;

    /** Resolved Eloquent model instance */
    protected ?Model $eloquentModel = null;

    // ── Listeners (Echo / Broadcast) ──────────────────────────────────────────

    public function getListeners(): array
    {
        $base = ['refreshData' => '$refresh'];

        $bc = $this->crudConfig['broadcast'] ?? [];
        if (! empty($bc['enabled'])) {
            $baseName = class_basename(str_replace('/', '\\', $this->model));
            // channel: page-product-observer (kebab)
            $channel  = $bc['channel'] ?? 'page-' . Str::kebab($baseName) . '-observer';
            // event: .pageProductObserver (must start with "." for private Echo events)
            $event    = $bc['event']   ?? '.page' . $baseName . 'Observer';

            $base["echo:{$channel},{$event}"] = 'handleBaseCrudUpdate';
        }

        return $base;
    }

    /**
     * Called via Echo/broadcast when the Observer fires the event.
     * Livewire automatically re-executes getRowsProperty() on re-render.
     */
    public function handleBaseCrudUpdate(): void
    {
        // Silent refresh — no extra visual feedback
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('ptah::livewire.base-crud', [
            'rows'             => $this->rows,
            'visibleCols'      => $this->getVisibleColumns(),
            'formCols'         => $this->getFormCols(),
            'permissions'      => $this->crudConfig['permissions']  ?? [],
            'exportCfg'        => $this->crudConfig['exportConfig'] ?? [],
            'totData'          => $this->totalizadoresData,
            'crudTitle'        => $this->crudConfig['displayName']
                                    ?? $this->crudConfig['crud']
                                    ?? class_basename(str_replace('/', '\\', $this->model)),
            'bulkActions'      => $this->crudConfig['bulkActions']  ?? [],
            'hasActiveFilters' => ! empty($this->textFilter)
                                    || $this->search !== ''
                                    || $this->quickDateFilter !== '',
        ]);
    }
}
