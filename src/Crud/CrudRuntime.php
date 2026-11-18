<?php

declare(strict_types=1);

namespace Ptah\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\PaginationState;
use Illuminate\Pagination\Paginator;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Support\IconClass;
use Ptah\Support\PtahMask;
use Ptah\Support\SafeUrl;
use Ptah\Support\Scalar;

/**
 * The BaseCrud, run without Livewire (1.59.0).
 *
 * It IS the BaseCrud — same query, same form, same hooks, same guards — only
 * driven by a plain request instead of a Livewire snapshot. Nothing is
 * rewritten: every check a laudo ever added (scopedQuery, crudConfigAllows,
 * denied columns, the select/searchdropdown value checks) runs here because
 * it is the same code.
 *
 * What does NOT happen: no snapshot travels to the browser and back, no
 * hydration, no HTML render, no morph. A request builds the runtime from the
 * server-side CrudScreen, applies what the user asked (search, sort, page…),
 * answers in JSON, and is gone.
 *
 * State rules:
 *  - what the screen IS (model, config, scope) comes from CrudScreen — never
 *    from the request;
 *  - what the user ASKED comes from the request and goes through the same
 *    validation hooks a Livewire property update triggers (`updatedSort()`…);
 *  - a request answers what it was asked and nothing else: by default the
 *    saved search/filters are not applied and nothing is written back;
 *  - "reopen where I left" is explicit, so no feature of the Livewire screen
 *    is lost: `restore` applies what the user left saved (search, filters,
 *    page…), `remember` saves what this request applied. Both use the very
 *    preferences the Livewire screen reads and writes — a user moves between
 *    the two and finds the same screen.
 */
final class CrudRuntime extends BaseCrud
{
    /** @var list<array{name: string, params: array<int|string, mixed>}> */
    private array $captured = [];

    private int $currentPage = 1;

    private ?Model $savedRecord = null;

    /** Write the applied state back to the user's preferences. */
    private bool $remember = false;

    /** Several setters in a row: one write at the end, not one each. */
    private bool $batching = false;

    /**
     * @param  bool  $restore  keep what the user left saved (search, filters,
     *                         page) instead of starting from a clean listing
     */
    public static function for(CrudScreen $screen, Request $request, bool $restore = false): self
    {
        $runtime = new self;

        app()->call([$runtime, 'boot']);
        $runtime->mount(...$screen->mountArguments($request));

        if ($restore) {
            // loadPreferences() leaves the saved page where Livewire reads it.
            $runtime->currentPage = max(1, (int) ($runtime->paginators['page'] ?? 1));
        } else {
            $runtime->forgetSavedListingState();
        }

        return $runtime;
    }

    public function remember(bool $remember = true): self
    {
        $this->remember = $remember;

        return $this;
    }

    public function hasConfig(): bool
    {
        return $this->crudConfig !== [];
    }

    // ── What Livewire would provide ─────────────────────────────────────────

    public function dispatch($event, ...$params)
    {
        $this->captured[] = ['name' => (string) $event, 'params' => $params];

        return parent::dispatch($event, ...$params);
    }

    public function getPage($pageName = 'page')
    {
        return $this->currentPage;
    }

    public function setPage($page, $pageName = 'page')
    {
        $this->currentPage = max(1, (int) $page);
    }

    public function resetPage($pageName = 'page')
    {
        $this->currentPage = 1;
    }

    /** Only when asked to remember — and once per request, not once per setter. */
    public function savePreferences(): void
    {
        if ($this->remember && ! $this->batching) {
            parent::savePreferences();
        }
    }

    /** Runs the setters with a single preferences write at the end. */
    private function batch(\Closure $changes): void
    {
        $this->batching = true;

        try {
            $changes();
        } finally {
            $this->batching = false;
        }

        $this->savePreferences();
    }

    protected function afterCreate(Model $record): mixed
    {
        $this->savedRecord = $record;

        return parent::afterCreate($record);
    }

    protected function afterUpdate(Model $record): mixed
    {
        $this->savedRecord = $record;

        return parent::afterUpdate($record);
    }

    private function forgetSavedListingState(): void
    {
        $this->search = '';
        $this->filters = [];
        $this->filterOperators = [];
        $this->dateRanges = [];
        $this->dateRangeOperators = [];
        $this->quickDateFilter = '';
        $this->textFilter = [];
        $this->showTrashed = false;
        $this->advancedSearchActive = false;
        $this->advancedSearchFields = [];
        $this->selectedRows = [];
        $this->currentPage = 1;
    }

    // ── What the user asked ─────────────────────────────────────────────────

    /**
     * Applies the listing state of a request. Each value goes through the
     * hook that validates it when Livewire updates the same property.
     *
     * @param  array<string, mixed>  $input
     */
    public function applyState(array $input): self
    {
        $this->batch(fn () => $this->applyStateNow($input));

        return $this;
    }

    /** @param  array<string, mixed>  $input */
    private function applyStateNow(array $input): void
    {
        // A cleared filter panel arrives as an empty value, not as an array.
        foreach (['filters', 'operators', 'date_ranges', 'date_operators'] as $key) {
            if (array_key_exists($key, $input) && ! is_array($input[$key])) {
                $input[$key] = [];
            }
        }

        if (isset($input['per_page']) && is_numeric($input['per_page'])) {
            $this->perPage = (int) $input['per_page'];
            $this->updatedPerPage();
        }

        if (isset($input['sort']) && is_string($input['sort'])) {
            $this->sort = $input['sort'];
            $this->updatedSort();
        }

        if (isset($input['direction']) && is_string($input['direction'])) {
            $this->direction = $input['direction'];
            $this->updatedDirection();
        }

        if (isset($input['search']) && is_scalar($input['search'])) {
            $this->search = trim((string) $input['search']);
            $this->updatedSearch();
        }

        foreach (['operators' => 'filterOperators', 'date_operators' => 'dateRangeOperators'] as $key => $property) {
            if (isset($input[$key]) && is_array($input[$key])) {
                $this->{$property} = $input[$key];
            }
        }

        if (isset($input['filters']) && is_array($input['filters'])) {
            $this->filters = $input['filters'];
            $this->updatedFilters();
        }

        if (isset($input['date_ranges']) && is_array($input['date_ranges'])) {
            $this->dateRanges = $input['date_ranges'];
            $this->updatedDateRanges();
        }

        if (isset($input['quick_date']) && is_string($input['quick_date'])) {
            $this->quickDateFilter = $input['quick_date'];
            $this->updatedQuickDateFilter();
        }

        // toggleTrashed() refuses where the screen does not offer the trash.
        if (filter_var($input['trashed'] ?? false, FILTER_VALIDATE_BOOLEAN) && ! $this->showTrashed) {
            $this->toggleTrashed();
        }

        // Last: every hook above resets the page. Without a page in the
        // request the current one stands — page 1 after any change above, or
        // the restored one when nothing was asked.
        if (is_numeric($input['page'] ?? null)) {
            $this->setPage((int) $input['page']);
        }
    }

    /**
     * The listing state in effect — what a client mirrors in its controls
     * (and in the URL) after a `restore`.
     *
     * @return array<string, mixed>
     */
    public function state(): array
    {
        return [
            'page' => $this->currentPage,
            'per_page' => $this->safePerPage(),
            'sort' => $this->sort,
            'direction' => $this->direction,
            'search' => $this->search,
            'filters' => (object) array_filter($this->filters, fn ($v) => $v !== null && $v !== ''),
            'operators' => (object) $this->filterOperators,
            'date_ranges' => (object) array_filter($this->dateRanges, fn ($v) => $v !== null && $v !== ''),
            'date_operators' => (object) $this->dateRangeOperators,
            'quick_date' => $this->quickDateFilter,
            'trashed' => $this->showTrashed,
        ];
    }

    /**
     * The user's choices for this screen that are not the listing state.
     *
     * @return array<string, mixed>
     */
    public function preferences(): array
    {
        $fields = array_values(array_filter(array_map(
            fn (array $col) => (string) ($col['colsNomeFisico'] ?? ''),
            array_filter($this->crudConfig['cols'] ?? [], fn (array $col) => ($col['colsTipo'] ?? '') !== 'action')
        )));
        $known = array_flip(array_diff($fields, $this->hiddenModelAttributes()));

        return [
            'columns' => (object) array_map('boolval', array_intersect_key($this->formDataColumns, $known)),
            'column_order' => array_values(array_filter($this->columnOrder, fn ($f) => is_string($f) && isset($known[$f]))),
            'column_widths' => (object) array_intersect_key($this->columnWidths, $known),
            'view_mode' => $this->viewMode,
            'view_density' => $this->viewDensity,
            'saved_filters' => (object) $this->savedFilters,
            'search_history' => array_values($this->searchHistory),
        ];
    }

    /**
     * Changes the choices above — only the keys sent. Each goes through the
     * setter the Livewire screen calls.
     *
     * @param  array<string, mixed>  $input
     */
    public function updatePreferences(array $input): self
    {
        $this->remember = true;

        $this->batch(function () use ($input) {
            if (is_array($input['columns'] ?? null)) {
                foreach ($input['columns'] as $field => $visible) {
                    // Only a column the screen has: the map is not a place to
                    // park arbitrary keys.
                    if (is_string($field) && array_key_exists($field, $this->formDataColumns)) {
                        $this->formDataColumns[$field] = filter_var($visible, FILTER_VALIDATE_BOOLEAN);
                    }
                }
                $this->updateColumns();
            }

            if (is_array($input['column_order'] ?? null)) {
                $this->reorderColumns(array_values(array_filter(
                    $input['column_order'],
                    fn ($field) => is_string($field) && array_key_exists($field, $this->formDataColumns)
                )));
            }

            if (is_array($input['column_widths'] ?? null)) {
                foreach ($input['column_widths'] as $field => $width) {
                    if (is_string($field) && is_numeric($width) && array_key_exists($field, $this->formDataColumns)) {
                        $this->saveColumnWidth($field, (int) $width);
                    }
                }
            }

            if (is_string($input['view_density'] ?? null)) {
                $this->setViewDensity($input['view_density']);
            }

            if (is_string($input['view_mode'] ?? null)) {
                $this->setViewMode($input['view_mode']);
            }

            if (($input['clear_search_history'] ?? false) === true) {
                $this->clearSearchHistory();
            }
        });

        return $this;
    }

    /**
     * Saves a set of filters under a name.
     *
     * @param  array<string, mixed>  $filters
     */
    public function saveFilter(string $name, array $filters): self
    {
        $name = trim($name);

        if ($name !== '' && mb_strlen($name) <= 80) {
            $this->remember = true;
            $current = $this->filters;
            $this->filters = $filters;
            $this->batch(fn () => $this->saveNamedFilter($name));
            // Naming a set of filters does not make it the applied one.
            $this->filters = $current;
            parent::savePreferences();
        }

        return $this;
    }

    public function forgetFilter(string $name): self
    {
        $this->remember = true;
        $this->deleteNamedFilter($name);

        return $this;
    }

    // ── Answers ─────────────────────────────────────────────────────────────

    /** @return array<string, bool> */
    public function permissions(): array
    {
        return $this->getEffectivePermissions();
    }

    /**
     * What a client needs to draw the screen: columns, form fields, filters
     * and what this user may do. Only named keys leave — never the raw
     * config (hooks, class names, permission identifiers stay on the server).
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $sortable = array_column($this->sortableColumns(), 'sortBy', 'field');

        $columns = [];
        foreach ($this->getVisibleColumns() as $col) {
            if (($col['colsTipo'] ?? '') === 'action') {
                continue;
            }
            $field = (string) ($col['colsNomeFisico'] ?? '');
            $columns[] = [
                'field' => $field,
                'label' => (string) ($col['colsNomeLogico'] ?? $field),
                'type' => (string) ($col['colsTipo'] ?? 'text'),
                'renderer' => $col['colsRenderer'] ?? null,
                'align' => self::alignment($col['colsAlign'] ?? null),
                'sortBy' => $sortable[$field] ?? null,
            ];
        }

        $fields = [];
        foreach ($this->getFormViewCols() as $col) {
            $field = (string) ($col['colsNomeFisico'] ?? '');
            $fields[] = [
                'field' => $field,
                'label' => (string) ($col['colsNomeLogico'] ?? $field),
                'type' => $this->isBooleanToggle($col) ? 'switch' : (string) ($col['colsTipo'] ?? 'text'),
                'required' => $this->ptahBool($col['colsRequired'] ?? false),
                'readonly' => $this->ptahBool($col['colsReadonly'] ?? false),
                'options' => self::options($col['colsSelect'] ?? null),
                'mask' => $col['colsMask'] ?? null,
                // The shapes of the mask, so a client masks while typing; what is
                // stored is still decided here, on save.
                'maskPatterns' => PtahMask::get($col['colsMask'] ?? null)['patterns'] ?? null,
                'placeholder' => $col['colsPlaceholder'] ?? null,
                'help' => $col['colsHelpText'] ?? null,
            ];
        }

        $filters = [];
        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            $field = (string) ($col['colsNomeFisico'] ?? '');
            if ($field === '' || ! $this->ptahBool($col['colsIsFilterable'] ?? false) || in_array($field, $this->hiddenModelAttributes(), true)) {
                continue;
            }
            $filters[] = [
                'field' => $field,
                'label' => (string) ($col['colsNomeLogico'] ?? $field),
                'type' => (string) ($col['colsTipo'] ?? 'text'),
                'options' => self::options($col['colsSelect'] ?? null),
            ];
        }

        return [
            'title' => $this->humanTitle(),
            'titleSingular' => $this->humanTitle(singular: true),
            'key' => 'id',
            'columns' => $columns,
            'form' => $fields,
            'filters' => $filters,
            'permissions' => $this->permissions(),
            'bulkActions' => array_map(fn (array $ba) => [
                'action' => (string) $ba['action'],
                'label' => (string) ($ba['label'] ?? $ba['action']),
                'icon' => isset($ba['icon']) ? IconClass::normalize((string) $ba['icon']) : null,
                'confirm' => $ba['confirm'] ?? null,
            ], $this->availableBulkActions()),
            'selectable' => $this->canSelectRows($this->permissions()),
            'export' => [
                'enabled' => ! empty($this->crudConfig['exportConfig']['enabled']) && $this->permissions()['canExport'],
                'formats' => ['excel', 'pdf'],
            ],
            'trash' => $this->crudConfigAllows('restore') && $this->modelSoftDeletes(),
            'defaults' => [
                'sort' => $this->sort,
                'direction' => $this->direction,
                'per_page' => $this->safePerPage(),
            ],
            'preferences' => $this->preferences(),
            'features' => $this->features(),
        ];
    }

    /**
     * What else the screen has on for this user — the switches the Livewire
     * views read before drawing a button. A client hides what is off; each
     * feature endpoint checks its own switch again.
     *
     * @return array<string, bool>
     */
    public function features(): array
    {
        return [
            'history' => $this->historyEnabled(),
            'attachments' => $this->attachmentsEnabled(),
            'attachmentsWrite' => $this->attachmentsEnabled() && $this->attachmentsCanWrite(),
            'import' => $this->importEnabled(),
            'kanban' => $this->kanbanEnabled(),
            'calendar' => $this->calendarEnabled(),
            'formDraft' => $this->formDraftEnabled(),
            'masterDetail' => ! empty($this->crudConfig['masterDetail']),
            // Part of every filter panel of the Livewire screen.
            'quickDates' => true,
            'advancedSearch' => true,
        ];
    }

    /**
     * One page of the listing. Each cell carries the raw value and the HTML
     * the Livewire screen shows for it (the same renderer, already escaped).
     *
     * @return array<string, mixed>
     */
    public function listing(): array
    {
        Paginator::currentPageResolver(fn () => $this->currentPage);

        try {
            $rows = $this->rows();

            // A page past the last one shows the last page, not an empty list.
            if ($rows->isEmpty() && $rows->currentPage() > 1 && $rows->lastPage() < $rows->currentPage()) {
                $this->setPage($rows->lastPage());
                $rows = $this->rows();
            }
        } finally {
            // Back to the framework's resolvers: the closure above must not
            // outlive this call.
            PaginationState::resolveUsing(app());
        }

        $columns = array_values(array_filter($this->getVisibleColumns(), fn (array $col) => ($col['colsTipo'] ?? '') !== 'action'));

        $data = [];
        foreach ($rows->items() as $row) {
            $cells = [];
            foreach ($columns as $col) {
                $value = Scalar::of($this->getCellValue($col, $row));
                $cells[(string) ($col['colsNomeFisico'] ?? '')] = [
                    'value' => is_scalar($value) || $value === null ? $value : null,
                    'html' => $this->formatCell($col, $row),
                ];
            }

            $data[] = [
                'id' => $row instanceof Model ? $row->getKey() : ($row['id'] ?? null),
                'cells' => $cells,
                'style' => $this->getRowStyle($row) ?: null,
                'actions' => $this->rowActions($row),
                'trashed' => $row instanceof Model && method_exists($row, 'trashed') && $row->trashed(),
            ];
        }

        return [
            'data' => $data,
            'meta' => [
                'page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
                'sort' => $this->sort,
                'direction' => $this->direction,
                'search' => $this->search,
                'trashed' => $this->showTrashed,
            ],
            'state' => $this->state(),
            'totals' => $this->totalizadoresItems(),
            'messages' => $this->messages(),
        ];
    }

    /**
     * The form data of a record, or null when it is out of this screen's scope.
     *
     * @return array<string, mixed>|null
     */
    public function record(int $id): ?array
    {
        $this->openEdit($id);

        return $this->editingId === null ? null : $this->formPayload();
    }

    /**
     * A copy of a record as a new form (never saved by this call), or null
     * when the record is out of scope or creating is not allowed.
     *
     * @return array<string, mixed>|null
     */
    public function duplicate(int $id): ?array
    {
        $this->duplicateRecord($id);

        return $this->showModal ? $this->formPayload() : null;
    }

    /**
     * The defaults of a new record's form.
     *
     * @return array<string, mixed>
     */
    public function blank(): array
    {
        $this->prepareCreate();

        return $this->formPayload();
    }

    /** @return array{data: array<string, mixed>, labels: array<string, mixed>} */
    private function formPayload(): array
    {
        return ['data' => $this->formData, 'labels' => $this->sdLabels];
    }

    /**
     * Options of a searchdropdown field. `$context` is the form (or the
     * filter panel) so far — a cascading dropdown depends on its parent.
     *
     * @param  array<string, mixed>  $context
     * @return list<mixed>
     */
    public function lookup(string $field, string $query, array $context = [], bool $forFilter = false): array
    {
        if ($forFilter) {
            $this->filters = array_merge($this->filters, $context);
            $query === '' ? $this->openFilterDropdown($field) : $this->filterSearchDropdown($field, $query);

            return array_values((array) ($this->sdResults['filter_'.$field] ?? []));
        }

        $this->formData = $context;
        $query === '' ? $this->openDropdown($field) : $this->searchDropdown($field, $query);

        return array_values((array) ($this->sdResults[$field] ?? []));
    }

    /**
     * Runs a bulk action over the given ids: `delete`, `restore`,
     * `force-delete`, or a configured action. The engine re-scopes the ids —
     * `remaining` are the ones it did not process.
     *
     * @param  array<int, mixed>  $ids
     * @return array{ok: bool, remaining: list<string>, messages: list<array<string, mixed>>}
     */
    public function bulk(string $action, array $ids): array
    {
        $this->selectedRows = array_values(array_map('strval', array_filter($ids, 'is_scalar')));

        if ($this->selectedRows !== []) {
            match ($action) {
                'delete' => $this->bulkDelete(),
                'restore' => $this->bulkRestore(),
                'force-delete' => $this->bulkForceDelete(),
                default => $this->executeBulkAction($action),
            };
        }

        return [
            'ok' => $ids !== [] && $this->selectedRows === [],
            'remaining' => array_values($this->selectedRows),
            'messages' => $this->messages(),
        ];
    }

    /**
     * Starts an export (or the print view) of the current listing — or of the
     * given ids. Answers the one-time URL to open, or the queued notice.
     *
     * @param  array<int, mixed>  $ids
     * @return array{ok: bool, url: ?string, messages: list<array<string, mixed>>}
     */
    public function exportListing(string $format, array $ids = []): array
    {
        if ($format === 'print') {
            $this->printView();
        } elseif ($ids !== []) {
            $this->selectedRows = array_values(array_map('strval', array_filter($ids, 'is_scalar')));
            $this->bulkExport($format === 'pdf' ? 'pdf' : 'excel');
        } else {
            $this->export($format === 'pdf' ? 'pdf' : 'excel');
        }

        $url = null;
        foreach ($this->captured as $event) {
            if (in_array($event['name'], ['ptah:export-download', 'ptah:open-print'], true)) {
                $url = (string) ($event['params']['url'] ?? '');
            }
        }

        $messages = $this->messages();

        return ['ok' => $url !== null || $messages !== [], 'url' => $url, 'messages' => $messages];
    }

    /**
     * The configured row actions this user may run, resolved for one row —
     * what _row-action.blade.php draws.
     *
     * @return list<array<string, mixed>>
     */
    private function rowActions(mixed $row): array
    {
        $out = [];

        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            if (($col['colsTipo'] ?? '') !== 'action' || ! $this->rowActionAllowed($col)) {
                continue;
            }

            $type = (string) ($col['actionType'] ?? 'javascript');
            $rowId = $row instanceof Model ? $row->getKey() : ($row['id'] ?? 0);
            $value = str_replace('%id%', (string) $rowId, (string) ($col['actionValue'] ?? ($col['actionCall'] ?? '')));

            if ($value === '') {
                continue;
            }

            if ($type === 'link') {
                // %field% only in a link, URL-encoded; never a denied or $hidden one.
                $never = array_merge($this->deniedColumns, $this->hiddenModelAttributes());
                $value = preg_replace_callback('/%([A-Za-z_][A-Za-z0-9_]*)%/', function (array $m) use ($row, $never) {
                    if (in_array($m[1], $never, true)) {
                        return '';
                    }
                    $v = Scalar::of(data_get($row, $m[1]));

                    return is_scalar($v) ? rawurlencode((string) $v) : '';
                }, $value) ?? $value;

                if (! SafeUrl::isSafe($value)) {
                    $value = '#';
                }
            }

            $out[] = [
                'label' => (string) ($col['colsNomeLogico'] ?? ''),
                // link: an href. livewire: the name of a host action — the client
                // hands it to its own handler. javascript: an expression.
                'type' => $type === 'livewire' ? 'action' : $type,
                'value' => $value,
                'icon' => IconClass::normalize((string) (($col['actionIcon'] ?? '') ?: ($col['actionIcone'] ?? ''))) ?: null,
                'color' => (string) ($col['actionColor'] ?? 'primary'),
                'confirm' => $this->rowActionConfirm($col),
            ];
        }

        return $out;
    }

    private function modelSoftDeletes(): bool
    {
        $model = $this->resolveEloquentModel();

        return $model !== null && in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }

    /**
     * Uploaded files of `image` fields, keyed by field — stored by the same
     * code (size, real MIME, allowed types) as a Livewire upload.
     *
     * @param  array<array-key, mixed>  $files
     */
    private function attachUploads(array $files): void
    {
        foreach ($files as $field => $file) {
            if (is_string($field) && $file instanceof UploadedFile && $file->isValid()) {
                $this->imageUploads[$field] = $file;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<array-key, mixed>  $files  uploads of `image` fields, by field
     * @return array{ok: bool, id: mixed, errors: array<string, mixed>, messages: list<array<string, mixed>>}
     */
    public function store(array $data, array $files = []): array
    {
        $this->prepareCreate();
        $this->formData = array_merge($this->formData, $data);
        $this->attachUploads($files);
        $this->save();

        return $this->saveResult();
    }

    /**
     * @param  array<string, mixed>  $data  only the fields being changed
     * @param  array<array-key, mixed>  $files  uploads of `image` fields, by field
     * @return array{ok: bool, id: mixed, errors: array<string, mixed>, messages: list<array<string, mixed>>}|null null: not in scope
     */
    public function update(int $id, array $data, array $files = []): ?array
    {
        $this->openEdit($id);

        if ($this->editingId === null) {
            return null;
        }

        $this->formData = array_merge($this->formData, $data);
        $this->attachUploads($files);
        $this->save();

        return $this->saveResult();
    }

    /** @return array{ok: bool, messages: list<array<string, mixed>>} */
    public function destroy(int $id): array
    {
        $this->confirmDelete($id);

        if ($this->showDeleteConfirm) {
            $this->deleteRecord();
        }

        return ['ok' => $this->dispatched('crud-deleted'), 'messages' => $this->messages()];
    }

    /** @return array{ok: bool, messages: list<array<string, mixed>>} */
    public function restore(int $id): array
    {
        $this->restoreRecord($id);

        // restoreRecord() announces even when it restored nothing: ask the scope.
        return ['ok' => $this->scopedQuery()?->find($id) !== null, 'messages' => $this->messages()];
    }

    /** @return array{ok: bool, id: mixed, errors: array<string, mixed>, messages: list<array<string, mixed>>} */
    private function saveResult(): array
    {
        $ok = $this->formErrors === [] && $this->dispatched('crud-saved');

        return [
            'ok' => $ok,
            'id' => $ok ? $this->savedRecord?->getKey() : null,
            'errors' => $this->formErrors,
            'messages' => $this->messages(),
        ];
    }

    private function dispatched(string $event): bool
    {
        return in_array($event, array_column($this->captured, 'name'), true);
    }

    /**
     * The toasts the Livewire screen would have shown.
     *
     * @return list<array<string, mixed>>
     */
    private function messages(): array
    {
        $out = [];
        foreach ($this->captured as $event) {
            if ($event['name'] === 'ptah-toast') {
                $out[] = [
                    'text' => (string) ($event['params']['title'] ?? ''),
                    'level' => (string) ($event['params']['color'] ?? 'info'),
                ];
            }
        }

        return $out;
    }

    /**
     * `colsAlign` holds a CSS class of the Livewire table (`text-center`,
     * `text-end`); a client gets the alignment itself.
     */
    private static function alignment(mixed $align): ?string
    {
        return match (true) {
            ! is_string($align) => null,
            str_contains($align, 'center') => 'center',
            str_contains($align, 'end') || str_contains($align, 'right') => 'right',
            default => 'left',
        };
    }

    /**
     * `colsSelect` (label => value) as a list a client can iterate in order.
     *
     * @return list<array{label: string, value: string}>|null
     */
    private static function options(mixed $select): ?array
    {
        if (! is_array($select) || $select === []) {
            return null;
        }

        $out = [];
        foreach ($select as $label => $value) {
            $out[] = ['label' => (string) $label, 'value' => Scalar::text($value)];
        }

        return $out;
    }
}
