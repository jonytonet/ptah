<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudBulkActions;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudColumns;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudDeletion;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudExport;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudFilters;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudForm;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudLifecycle;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudPreferences;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudQuery;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudRenderers;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudSearchDropdown;
use Ptah\Services\Cache\CacheService;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Services\Crud\FilterService;
use Ptah\Services\Crud\FormValidatorService;
use Ptah\Services\Permission\ColumnPermissionService;

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
 *
 *   @livewire('ptah-base-crud', ['model' => 'Product'])
 */
class BaseCrud extends Component
{
    // Bulk actions (select-all, bulk-delete, custom actions)
    use HasCrudBulkActions;

    // Column visibility and ordering
    use HasCrudColumns;

    // Delete / restore / soft-delete
    use HasCrudDeletion;

    // Export (sync / async)
    use HasCrudExport;

    // Sort, search, date quick-filters, advanced search, named filters
    use HasCrudFilters;

    // Create / edit modal and cell helpers
    use HasCrudForm;

    // Lifecycle, configuration reload
    use HasCrudLifecycle;

    // User preferences (save / load / defaults)
    use HasCrudPreferences;

    // Data querying, filtering, totals
    use HasCrudQuery;

    // Cell renderers, row styles, helper formatters
    use HasCrudRenderers;

    // SearchDropdown (inline + filter-panel)
    use HasCrudSearchDropdown;
    use WithFileUploads;
    use WithPagination;

    // ── Configuration ──────────────────────────────────────────────────────────

    /**
     * Model identifier (e.g. "Product", "Purchase/Order/PurchaseOrders").
     *
     * #[Locked]: only ever assigned server-side, once, in mount() (a
     * constructor param — never re-assigned afterwards). A client-writable
     * $model would let a forged request point resolveEloquentModel() (and
     * everything derived from it — queries, exports, permission checks) at an
     * unrelated model while the rest of the component's state stays put.
     * Locked only blocks client-side updates; mount() is unaffected.
     */
    #[Locked]
    public string $model = '';

    /** Route path captured from request (e.g. 'categories') — used to load screen-specific config */
    public string $configRoute = '';

    /**
     * Full CrudConfig configuration array — governs cols, rows, totals, print,
     * export, hooks, bulkActions and permissions.
     *
     * #[Locked]: only ever assigned server-side, in boot()/mount() and in
     * reloadCrudConfig() (the #[On('ptah:crud-config-updated')] handler fired
     * by the CrudConfig editor), always reading from CrudConfigService/DB —
     * never from the client payload. No view writes to it via wire:model/$set/
     * $wire (grep confirms it is read-only from Blade). A client-writable
     * crudConfig would let a forged request override every permission check,
     * export limit and hook derived from it. Locked only blocks client-side
     * updates; the editor→event→reload flow (server-side) is unaffected.
     */
    #[Locked]
    public array $crudConfig = [];

    // ── Table state ───────────────────────────────────────────────────────────

    public string $sort = 'id';

    public string $direction = 'DESC';

    public int $perPage = 25;

    public string $search = '';

    public bool $showTrashed = false;

    public int $trashedCount = 0;

    // ── External whereHas ─────────────────────────────────────────────────────

    /** Pre-filter the CRUD by a parent relation */
    public string $whereHasFilter = '';

    public array $whereHasCondition = [];

    // ── Column visibility ─────────────────────────────────────────────────────

    /** Map [fieldName => bool] of visible columns */
    public array $formDataColumns = [];

    public int $hiddenColumnsCount = 0;

    /**
     * `colsNomeFisico` of every column the current user may not READ (see
     * ColumnPermissionService), computed by HasCrudLifecycle::applyColumnPermissions()
     * every time $crudConfig is (re)loaded. Deliberately NOT public: unlike
     * `crudConfig`, this never needs a client-visible payload, and a plain
     * `protected` property is not serialised to/from the browser at all
     * (stronger than #[Locked], which only blocks client WRITES) — boot()
     * recomputes it every request regardless.
     *
     * @var string[]
     */
    protected array $deniedColumns = [];

    // ── Active filter badge summary ───────────────────────────────────────────

    /** Active filter badges: [{label, value}] */
    public array $textFilter = [];

    // ── Bulk actions ──────────────────────────────────────────────────────────

    /**
     * NOT #[Locked]: the row checkboxes write to it via wire:model.live
     * (_table.blade.php / _cards.blade.php) — locking it would break selection.
     * The IDOR/authorization guard instead lives in HasCrudBulkActions, which
     * intersects these ids with scopedQuery()/buildBaseQuery() before any
     * write or export, so a forged id here can never reach another
     * company/master-detail scope.
     */
    public array $selectedRows = [];

    public bool $selectAll = false;

    public bool $bulkActionInProgress = false;

    public bool $showBulkActions = false;

    // ── Quick date filter ─────────────────────────────────────────────────────

    /** 'today'|'week'|'month'|'quarter'|'year'|'' */
    public string $quickDateFilter = '';

    /** Date column used by the quick date filter */
    public string $quickDateColumn = '';

    // ── Advanced search ───────────────────────────────────────────────────────

    public bool $advancedSearchActive = false;

    public array $advancedSearchFields = [];

    public array $searchHistory = [];

    // ── Multi-tenant ──────────────────────────────────────────────────────────

    /**
     * Active company ID (0 = no filter).
     *
     * #[Locked]: only ever assigned server-side, once, in mount() (a
     * constructor param, defaulting to ptah_company_id() from the session —
     * never re-assigned afterwards). Switching company is a full-page reload
     * (CompanySwitcher::switchTo() — grep confirms no wire:model/$set/$wire.
     * assignment targets this property anywhere), so there is no legitimate
     * client-side setter to preserve. A client-writable companyFilter would
     * let a forged request scope the listing — and this property's export —
     * to a company the user was never granted. Locked only blocks client-side
     * updates; mount() is unaffected.
     */
    #[Locked]
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

    /**
     * Filters captured from the URL query string (?f[field]=value). Override
     * saved preferences while active but are never persisted. Keyed by field:
     * [field => ['op' => string, 'val' => mixed]].
     *
     * #[Locked]: captureUrlFilters() is the ONLY legitimate writer and it only
     * runs server-side (mount()), reading from request()->query() — never from
     * the client payload. Without this, any subsequent Livewire request could
     * rewrite this property directly (e.g. ->set('urlFilters', [...])) and
     * apply an arbitrary field/operator that never went through the whitelist,
     * defeating captureUrlFilters() entirely. Locked only blocks client-side
     * updates — server-side assignments (captureUrlFilters()/clearUrlFilters()/
     * the precedence resets in HasCrudFilters) are unaffected.
     */
    #[Locked]
    public array $urlFilters = [];

    /** @var string|null Name of filter currently being saved */
    public ?string $savingFilterName = null;

    public bool $showFilters = false;

    // ── Create / edit modal ───────────────────────────────────────────────────

    public array $formData = [];

    public array $imageUploads = [];

    public ?int $editingId = null;

    public bool $showModal = false;

    public bool $creating = false;

    public int $formInstanceKey = 0;

    /** Form validation errors */
    public array $formErrors = [];

    // ── Deletion ──────────────────────────────────────────────────────────────

    public bool $showDeleteConfirm = false;

    public ?int $deletingId = null;

    // ── Lifecycle: clear bulk selection on page change ────────────────────────

    public function updatingPage(): void
    {
        $this->selectedRows = [];
        $this->selectAll = false;
    }

    // ── SearchDropdown ────────────────────────────────────────────────────────

    /** Search term per searchdropdown field: [fieldName => query] */
    public array $sdSearches = [];

    /** Results per field: [fieldName => [{value, label}]] */
    public array $sdResults = [];

    /** Displayed labels per form field: [fieldName => label] */
    public array $sdLabels = [];

    /** Displayed labels per filter SD field: [fieldName => label] */
    public array $sdFilterLabels = [];

    // ── Master / Detail ───────────────────────────────────────────────────────

    /** Row IDs currently expanded to show their detail grids */
    public array $expandedRows = [];

    /**
     * Filters enforced on every query and untouchable from the UI — used by
     * nested detail grids (child rows locked to the parent's FK).
     * [column => value]
     *
     * #[Locked]: only ever assigned server-side, once, in mount() (a
     * constructor param — see HasCrudLifecycle::mount()). No view writes to it
     * via wire:model/$set/$wire (grep confirms it is read-only from Blade), so
     * there is no legitimate client-side setter to preserve. A client-writable
     * lockedFilters would let a forged request escape the master/detail lock
     * it exists to enforce. Locked only blocks client-side updates; mount() is
     * unaffected.
     */
    #[Locked]
    public array $lockedFilters = [];

    public function toggleDetail(int $id): void
    {
        if (in_array($id, $this->expandedRows, true)) {
            $this->expandedRows = array_values(array_diff($this->expandedRows, [$id]));
        } else {
            $this->expandedRows[] = $id;
        }
    }

    // ── Preferences ───────────────────────────────────────────────────────────

    public array $columnOrder = [];

    public array $columnWidths = [];

    public string $viewDensity = 'global'; // global (segue o perfil) | compact | comfortable | spacious

    /**
     * 'auto' | 'table' | 'cards'.
     *
     * 'auto' is the default and means "follow the viewport": the table on wide
     * screens, cards on narrow ones. It exists because this property is a
     * PERSISTED per-user preference while the layout question is per-DEVICE —
     * writing 'cards' because someone opened the screen on a phone would hand
     * their desktop session a card grid the next morning. With 'auto' the
     * decision is made by CSS at render time and nothing device-specific is
     * ever stored; 'table'/'cards' are explicit pins that hold on every
     * viewport.
     */
    public string $viewMode = 'auto';

    // ── Export ────────────────────────────────────────────────────────────────

    public bool $showExportMenu = false;

    public string $exportStatus = '';

    // ── Services (injected via boot) ──────────────────────────────────────────

    protected CrudConfigService $configService;

    protected FilterService $filterService;

    protected CacheService $cacheService;

    protected FormValidatorService $formValidator;

    protected ColumnPermissionService $columnPermissionService;

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
            $channel = $bc['channel'] ?? 'page-'.Str::kebab($baseName).'-observer';
            // event: .pageProductObserver (must start with "." for private Echo events)
            $event = $bc['event'] ?? '.page'.$baseName.'Observer';

            $base["echo:{$channel},{$event}"] = 'handleBaseCrudUpdate';
        }

        return $base;
    }

    /**
     * Called via Echo/broadcast when the Observer fires the event.
     * Livewire automatically re-executes the #[Computed] rows() on re-render.
     */
    public function handleBaseCrudUpdate(): void
    {
        // Silent refresh — no extra visual feedback
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        // Computed property — memoized per request, so reusing it below (for the
        // export-limit badge) does not trigger a second query.
        $rows = $this->rows;

        // groupBy makes total() imprecise (it counts groups, not raw rows), so the
        // badge is only meaningful for the plain (non-grouped) listing.
        $hasGroupBy = ! empty($this->crudConfig['groupBy']);
        $maxExportRows = (int) ($this->crudConfig['exportConfig']['maxRows'] ?? 5000);

        return view('ptah::livewire.base-crud.base-crud', [
            'rows' => $rows,
            'visibleCols' => $this->getVisibleColumns(),
            'formCols' => $this->getFormCols(),
            'permissions' => $this->crudConfig['permissions'] ?? [],
            'effectivePerms' => $this->getEffectivePermissions(),
            'exportCfg' => $this->crudConfig['exportConfig'] ?? [],
            'totData' => $this->totalizadoresData,
            'crudTitle' => $this->crudConfig['displayName']
                                    ?? $this->crudConfig['crud']
                                    ?? class_basename(str_replace('/', '\\', $this->model)),
            'bulkActions' => $this->crudConfig['bulkActions'] ?? [],
            'hasActiveFilters' => ! empty($this->textFilter)
                                    || $this->search !== ''
                                    || $this->quickDateFilter !== '',
            'exportOverLimit' => ! $hasGroupBy && $rows->total() > $maxExportRows,
        ]);
    }

    /**
     * Computes effective permission booleans combining:
     *   1. show*Button flags (CrudConfig)
     *   2. Laravel Gate checks (permissions['create'/'edit'/'delete'])
     *   3. Ptah RBAC checks via ptah_can() (when module is active + permissionIdentifier configured)
     *
     * Cached per render to avoid redundant ptah_can() calls.
     *
     * `permissionIdentifier` may itself be a QUALIFIED key
     * (`page::obj_key` / `page::section::obj_key`, see
     * `PermissionService::KEY_QUALIFIER`) — it passes through unchanged.
     */
    protected function getEffectivePermissions(): array
    {
        $p = $this->crudConfig['permissions'] ?? [];
        $key = $p['permissionIdentifier'] ?? null;

        // Only enforce ptah checks when module is active, a key is configured and user is authenticated
        $ptahActive = config('ptah.modules.permissions') && $key && Auth::check();

        $gateCheck = function (?string $gate): bool {
            if (! $gate) {
                return true;
            }

            return Auth::check() && Auth::user()->can($gate);
        };

        $ptahCheck = function (string $action) use ($ptahActive, $key): bool {
            if (! $ptahActive) {
                return true;
            }

            return ptah_can($key, $action);
        };

        return [
            'canCreate' => ($p['showCreateButton'] ?? true) && $gateCheck($p['create'] ?? null) && $ptahCheck('create'),
            'canUpdate' => ($p['showEditButton'] ?? true) && $gateCheck($p['edit'] ?? null) && $ptahCheck('update'),
            'canDelete' => ($p['showDeleteButton'] ?? true) && $gateCheck($p['delete'] ?? null) && $ptahCheck('delete'),
            'canRestore' => ($p['showTrashButton'] ?? true) && $ptahCheck('update'),
        ];
    }
}
