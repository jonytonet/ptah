<?php

declare(strict_types=1);

namespace Ptah\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\PaginationState;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Ptah\Crud\Concerns\FiltersRuntime;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\Attachment;
use Ptah\Support\BroadcastListener;
use Ptah\Support\IconClass;
use Ptah\Support\PtahMask;
use Ptah\Support\SafeUrl;
use Ptah\Support\Scalar;
use Ptah\Support\UploadDisk;

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
    use FiltersRuntime;

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

    /**
     * The definition of the detail screen `$index` of the row `$id` — what
     * the Livewire table mounts under an expanded row: a BaseCrud of the
     * `masterDetail` model, its rows locked to the parent's key, the config
     * of the same page route (falling back to the model's global config, as
     * the nested component does). Null when the detail does not exist or the
     * row is outside this screen's scope: nothing of the child is reachable
     * through a parent row the user cannot see.
     */
    public function detailScreen(string $key, int $id, int $index): ?CrudScreen
    {
        $detail = $this->detailConfig($index);

        if ($detail === null || ! $this->reaches($id)) {
            return null;
        }

        return (new CrudScreen($key, $detail['model']))
            ->configRoute($this->configRoute)
            ->lockedFilters([$detail['foreignKey'] => $id]);
    }

    /**
     * A usable `masterDetail` entry (a model and a foreign key), or null.
     *
     * @return array{model: string, foreignKey: string, title: ?string}|null
     */
    public function detailConfig(int $index): ?array
    {
        $detail = ((array) ($this->crudConfig['masterDetail'] ?? []))[$index] ?? null;

        if (! is_array($detail) || ! is_string($detail['model'] ?? null) || $detail['model'] === ''
            || ! is_string($detail['foreignKey'] ?? null) || $detail['foreignKey'] === '') {
            return null;
        }

        $title = is_string($detail['title'] ?? null) && trim($detail['title']) !== '' ? trim($detail['title']) : null;

        return ['model' => $detail['model'], 'foreignKey' => $detail['foreignKey'], 'title' => $title];
    }

    /**
     * The row is one this screen lists — its company, locks, pre-filter and
     * scope (scopedQuery(), the query edit and delete use). A trashed row
     * counts where the screen offers the trash: the Livewire trash view
     * expands its rows too.
     */
    public function reaches(int $id): bool
    {
        $query = $this->scopedQuery();

        if ($query === null) {
            return false;
        }
        if ($this->crudConfigAllows('restore') && $this->modelSoftDeletes()) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $query->whereKey($id)->exists();
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

        // The advanced rows, the link filters (?f) and the search history —
        // after `filters`, whose hook discards the link filters.
        $this->applyFiltersState($input);

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
        ] + $this->filtersState();
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
                // colsRequired, or NOT NULL without a DEFAULT: the * shows
                // before the error, as in the Livewire form (1.61.0).
                'required' => $this->fieldIsRequired($col),
                'readonly' => $this->ptahBool($col['colsReadonly'] ?? false),
                'options' => self::options($col['colsSelect'] ?? null),
                'mask' => $col['colsMask'] ?? null,
                // The shapes of the mask, so a client masks while typing; what is
                // stored is still decided here, on save.
                'maskPatterns' => PtahMask::get($col['colsMask'] ?? null)['patterns'] ?? null,
                'placeholder' => $col['colsPlaceholder'] ?? null,
                'help' => $col['colsHelpText'] ?? null,
                // Form parity: what the Livewire modal reads from the column
                // and a client cannot work out on its own.
                ...$this->formFieldExtras($col),
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
                // The formats the config offers, as the Livewire menu lists them
                // (`exportConfig.formats`, default excel); only the two the export knows.
                'formats' => array_values(array_intersect(['excel', 'pdf'], (array) ($this->crudConfig['exportConfig']['formats'] ?? ['excel']))),
            ],
            'trash' => $this->crudConfigAllows('restore') && $this->modelSoftDeletes(),
            'defaults' => [
                'sort' => $this->sort,
                'direction' => $this->direction,
                'per_page' => $this->safePerPage(),
            ],
            'preferences' => $this->preferences(),
            'features' => $this->features(),
            // The websocket listener of the screen (`broadcast` in its config):
            // the same channel and event the Livewire screen registers.
            'broadcast' => BroadcastListener::describe(
                BroadcastListener::key((array) ($this->crudConfig['broadcast'] ?? []), $this->model, $this->companyFilter)
            ),
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
            // ptah.crud.responsive_cards = false: view mode `auto` stays a
            // table on a narrow screen, as base-crud.blade.php does.
            'responsiveCards' => (bool) config('ptah.crud.responsive_cards', true),
            // The copy / filter buttons over a cell (editor → General).
            'copyCell' => (bool) ($this->crudConfig['uiPreferences']['copyCell'] ?? true),
            // Delete goes to the deleted list and the toast offers Undo: the
            // confirmation must not say "cannot be undone" (1.61.0).
            'undoDelete' => $this->deleteIsUndoable(),
            // ptah.crud.filter_panel = 'modal': the filters open in a dialog.
            'filterModal' => $this->filterPanelIsModal(),
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
                'link' => $this->rowLink($row),
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
            'totals' => $this->formattedTotals(),
            'breaks' => $this->groupBreaks($rows->items()),
            'messages' => $this->toastMessages(),
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

    /**
     * The form as it opens. `title`/`subtitle` are the Livewire modal's own
     * ("Nova conta" follows `displayNameGender`, and a host's translation
     * overrides apply); `previews` are the URLs of the stored images, which
     * only the server can build (the upload disk is configuration).
     *
     * @return array{data: array<string, mixed>, labels: array<string, mixed>, title: string, subtitle: string, previews: array<string, string>}
     */
    private function formPayload(): array
    {
        $editing = $this->editingId !== null;

        return [
            'data' => $this->formData,
            'labels' => $this->sdLabels,
            'title' => trim(($editing ? (string) __('ptah::ui.modal_edit_prefix') : $this->modalNewPrefix()).' '.$this->humanTitle(singular: true)),
            // As the Livewire modal (1.61.0): with a required field, the
            // subtitle says what the * means.
            'subtitle' => (string) __(collect($this->getFormViewCols())->contains(fn (array $c): bool => $this->fieldIsRequired($c))
                ? 'ptah::ui.form_required_hint'
                : ($editing ? 'ptah::ui.modal_edit_subtitle' : 'ptah::ui.modal_create_subtitle')),
            'previews' => $this->imagePreviews(),
        ];
    }

    /**
     * Preview URL of each image field that holds a stored value — the same
     * `UploadDisk::url()` the Livewire modal draws its `<img>` with.
     *
     * @return array<string, string>
     */
    private function imagePreviews(): array
    {
        $out = [];
        foreach ($this->getFormViewCols() as $col) {
            $field = (string) ($col['colsNomeFisico'] ?? '');
            $value = $this->formData[$field] ?? null;
            if (($col['colsTipo'] ?? '') === 'image' && $field !== '' && is_scalar($value) && (string) $value !== '') {
                $out[$field] = UploadDisk::url($col, (string) $value);
            }
        }

        return $out;
    }

    /**
     * What the form needs of a column beyond label and type: the section it
     * opens (`colsFormBlock`), the file types an image accepts, and the
     * searchdropdown's own placeholder, list direction and parent. Named keys
     * only — never the dropdown's model, service or filters.
     *
     * @param  array<string, mixed>  $col
     * @return array{block: string|null, accept: string|null, lookup: array{placeholder: string|null, startList: string, dependsOn: string|null, dependsOnLabel: string|null}|null, maskPlaceholder: string|null, inputMode: string|null, maxLength: int|null}
     */
    private function formFieldExtras(array $col): array
    {
        $type = (string) ($col['colsTipo'] ?? 'text');
        $block = trim(Scalar::text($col['colsFormBlock'] ?? ''));

        $accept = null;
        if ($type === 'image') {
            // As the Blade partial: `.png,.jpg` from colsUploadAllowedTypes, else any image.
            $allowed = $col['colsUploadAllowedTypes'] ?? null;
            $types = is_array($allowed) ? $allowed : (is_string($allowed) && $allowed !== '' ? explode(',', $allowed) : []);
            $types = array_values(array_filter(array_map(fn ($t) => trim(Scalar::text($t)), $types), fn ($t) => $t !== ''));
            $accept = $types === [] ? 'image/*' : implode(',', array_map(fn ($t) => '.'.ltrim($t, '.'), $types));
        }

        $lookup = null;
        if ($type === 'searchdropdown') {
            $settings = $this->sdSettings($col);
            $parent = $col['colsSDDependsOn'] ?? null;
            $parent = is_string($parent) && $parent !== '' ? $parent : null;
            $parentLabel = null;
            if ($parent !== null) {
                $parentCol = $this->findColByField($parent);
                $parentLabel = trim(Scalar::text($parentCol['colsNomeLogico'] ?? '')) ?: $parent;
            }
            $placeholder = $settings['placeholder'];
            $lookup = [
                'placeholder' => is_string($placeholder) && $placeholder !== '' ? $placeholder : null,
                'startList' => $settings['startList'] === 'top' ? 'top' : 'bottom',
                'dependsOn' => $parent,
                'dependsOnLabel' => $parentLabel,
            ];
        }

        // A registered mask's own hints, which the Blade input uses when the
        // column names no placeholder.
        $mask = PtahMask::get(is_string($col['colsMask'] ?? null) ? $col['colsMask'] : null);

        return [
            'block' => $block === '' ? null : $block,
            'accept' => $accept,
            'lookup' => $lookup,
            'maskPlaceholder' => $mask !== null && $mask['placeholder'] !== '' ? $mask['placeholder'] : null,
            'inputMode' => $mask !== null && $mask['inputmode'] !== '' ? $mask['inputmode'] : null,
            'maxLength' => $mask['maxlength'] ?? null,
            // An emptied field saves null, and the money input shows empty
            // instead of R$ 0,00 (maskEmptyIsNull).
            'emptyNull' => ! empty($col['colsMask']) || ! empty($col['colsMaskTransform']) ? $this->maskEmptyIsNull($col) : false,
        ];
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

            return self::lookupItems($this->sdResults['filter_'.$field] ?? []);
        }

        $this->formData = $context;
        $query === '' ? $this->openDropdown($field) : $this->searchDropdown($field, $query);

        return self::lookupItems($this->sdResults[$field] ?? []);
    }

    /**
     * The options as the Livewire dropdown draws them: value, label and the
     * optional second and third lines. A service source may answer more keys;
     * those stay on the server.
     *
     * @return list<array{value: mixed, label: string, labelTwo?: string, labelThree?: string}>
     */
    private static function lookupItems(mixed $results): array
    {
        $out = [];
        foreach ((array) $results as $item) {
            if (! is_array($item) || ! array_key_exists('value', $item)) {
                continue;
            }
            $row = ['value' => is_scalar($item['value']) ? $item['value'] : Scalar::text($item['value']), 'label' => Scalar::text($item['label'] ?? $item['value'])];
            foreach (['labelTwo', 'labelThree'] as $extra) {
                if (array_key_exists($extra, $item)) {
                    $row[$extra] = Scalar::text($item[$extra]);
                }
            }
            $out[] = $row;
        }

        return $out;
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
            'messages' => $this->toastMessages(),
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

        $messages = $this->toastMessages();

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

    /**
     * Where a click on the row goes (`configLinkLinha`), as _table/_cards
     * build it: only `%id%` is replaced, and a URL whose scheme could run
     * something is dropped (SafeUrl) — null, no link, never `#`.
     */
    private function rowLink(mixed $row): ?string
    {
        $template = $this->crudConfig['configLinkLinha'] ?? null;
        $id = $row instanceof Model ? $row->getKey() : ($row['id'] ?? null);

        if (! is_string($template) || $template === '' || ! is_scalar($id) || (string) $id === '' || (string) $id === '0') {
            return null;
        }

        $link = SafeUrl::sanitize(str_replace('%id%', (string) $id, $template), '');

        return $link !== '' ? $link : null;
    }

    /**
     * The totals, each with `text`: the value as the footer of _table.blade.php
     * prints it — a money column in its currency, a `currencyFormat` column
     * with the locale's prefix, a count never as money. Plain text (the Blade
     * escapes it with {{ }}).
     *
     * @return list<array{field: string, aggregate: string, value: mixed, text: string|null}>
     */
    private function formattedTotals(): array
    {
        $cols = array_column($this->crudConfig['cols'] ?? [], null, 'colsNomeFisico');
        $out = [];
        foreach ($this->totalizadoresItems() as $total) {
            $total['text'] = $total['value'] === null ? null : $this->totalText($cols[$total['field']] ?? [], $total['value'], $total['aggregate'] !== 'count');
            $out[] = $total;
        }

        return $out;
    }

    /**
     * One total or subtotal, formatted like _table / _break-subtotal.
     *
     * @param  array<string, mixed>  $col
     */
    private function totalText(array $col, mixed $value, bool $asMoney, bool $subtotal = false): string
    {
        if ($asMoney && ($col['colsRenderer'] ?? '') === 'money') {
            return $this->renderMoney($col, (float) $value);
        }
        if ($asMoney && ($col['colsHelper'] ?? '') === 'currencyFormat') {
            return __('ptah::ui.currency_prefix').number_format((float) $value, 2, (string) __('ptah::ui.number_dec_point'), (string) __('ptah::ui.number_thousands'));
        }
        if ($subtotal) {
            $dec = (string) __('ptah::ui.number_dec_point');

            return rtrim(rtrim(number_format((float) $value, 2, $dec, (string) __('ptah::ui.number_thousands')), '0'), $dec);
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * The group breaks of this page (`groupBreak`, `groupBreakPeriod`), as
     * _table.blade.php draws them: a header row before the first row of each
     * group (`start` is that row's index in `data`), and a subtotal row after
     * its last one with the sum of each totalled column over the group's rows
     * of this page. Empty when the screen has no break.
     *
     * @param  array<int, mixed>  $rows
     * @return list<array{start: int, count: int, label: string, title: string, subtotals: array<string, string>}>
     */
    private function groupBreaks(array $rows): array
    {
        $field = $this->crudConfig['groupBreak'] ?? null;
        if (! is_string($field) || $field === '' || $rows === []) {
            return [];
        }

        $period = in_array($this->crudConfig['groupBreakPeriod'] ?? null, ['day', 'month', 'year'], true) ? $this->crudConfig['groupBreakPeriod'] : null;
        $cols = array_column($this->crudConfig['cols'] ?? [], null, 'colsNomeFisico');
        $breakCol = $cols[$field] ?? null;
        $label = (string) ($breakCol['colsNomeLogico'] ?? $field);
        $totalled = array_keys(self::totalsByField($this->totalizadoresItems()));

        $groups = [];
        $previous = null;
        $first = true;
        foreach (array_values($rows) as $index => $row) {
            $current = data_get($row, $field);
            $title = null;
            if ($period !== null && $current !== null && $current !== '') {
                try {
                    $date = Carbon::parse($current)->locale(app()->getLocale());
                    $current = $date->format(['day' => 'Y-m-d', 'month' => 'Y-m', 'year' => 'Y'][$period]);
                    $title = match ($period) {
                        'day' => $date->isoFormat('L'),
                        'month' => $date->translatedFormat('F Y'),
                        default => $date->format('Y'),
                    };
                } catch (\Throwable) {
                    $title = null;
                }
            }

            if ($first || $current !== $previous) {
                $groups[] = [
                    'start' => $index,
                    'count' => 0,
                    'label' => $label,
                    'title' => $title !== null ? e(Str::ucfirst($title)) : ($breakCol ? $this->formatCell($breakCol, $row) : e((string) Scalar::of($current))),
                    'sums' => [],
                ];
                $first = false;
            }
            $previous = $current;
            $last = count($groups) - 1;
            $groups[$last]['count']++;
            foreach ($totalled as $total) {
                $groups[$last]['sums'][$total] = ($groups[$last]['sums'][$total] ?? 0) + (float) data_get($row, $total);
            }
        }

        return array_map(function (array $group) use ($cols) {
            $subtotals = [];
            foreach ($group['sums'] as $total => $sum) {
                $subtotals[$total] = $this->totalText($cols[$total] ?? [], $sum, true, true);
            }
            unset($group['sums']);

            return $group + ['subtotals' => $subtotals];
        }, $groups);
    }

    /**
     * attachmentCount() of many rows at once: the same scoping (an id the
     * screen does not reach — company, locks, pre-filter, host scope —
     * counts 0), in two queries instead of two per id: the ids in scope, then
     * one grouped count. Not one joined query: `subject_id` is a string
     * column and the key usually an integer, a comparison PostgreSQL refuses.
     *
     * @param  list<int>  $ids
     * @return array<int, int> count by id
     */
    public function attachmentCounts(array $ids): array
    {
        $counts = array_fill_keys($ids, 0);
        $model = $this->resolveEloquentModel();
        $query = $this->attachmentsEnabled() ? $this->scopedQuery() : null;

        if ($ids === [] || $model === null || $query === null) {
            return $counts;
        }

        $reachable = $query->whereKey($ids)->pluck($model->getQualifiedKeyName())
            ->map(fn ($key) => (string) $key)->all();

        if ($reachable === []) {
            return $counts;
        }

        $found = Attachment::query()
            ->where('subject_type', $model->getMorphClass())
            ->whereIn('subject_id', $reachable)
            ->groupBy('subject_id')
            ->selectRaw('subject_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'subject_id');

        foreach ($found as $subject => $count) {
            if (array_key_exists((int) $subject, $counts)) {
                $counts[(int) $subject] = (int) $count;
            }
        }

        return $counts;
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

        return ['ok' => $this->dispatched('crud-deleted'), 'messages' => $this->toastMessages()];
    }

    /** @return array{ok: bool, messages: list<array<string, mixed>>} */
    public function restore(int $id): array
    {
        $this->restoreRecord($id);

        // restoreRecord() announces even when it restored nothing: ask the scope.
        return ['ok' => $this->scopedQuery()?->find($id) !== null, 'messages' => $this->toastMessages()];
    }

    /** @return array{ok: bool, id: mixed, errors: array<string, mixed>, messages: list<array<string, mixed>>, redirect: string|null} */
    private function saveResult(): array
    {
        $ok = $this->formErrors === [] && $this->dispatched('crud-saved');

        return [
            'ok' => $ok,
            'id' => $ok ? $this->savedRecord?->getKey() : null,
            'errors' => $this->formErrors,
            'messages' => $this->toastMessages(),
            'redirect' => $ok ? $this->saveRedirect() : null,
        ];
    }

    /**
     * Where an `afterCreate`/`afterUpdate` hook sent the user: save() hands a
     * returned RedirectResponse to Livewire's `redirect()`, which keeps it in
     * the component store even without a Livewire request (as RunsHeadless
     * reads it). A scheme that could run script never reaches the client.
     */
    private function saveRedirect(): ?string
    {
        $target = \Livewire\store($this)->get('redirect');

        return is_string($target) && $target !== '' && SafeUrl::isSafe($target) ? $target : null;
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
    public function toastMessages(): array
    {
        $out = [];
        foreach ($this->captured as $event) {
            if ($event['name'] === 'ptah-toast') {
                $message = [
                    'text' => (string) ($event['params']['title'] ?? ''),
                    'level' => (string) ($event['params']['color'] ?? 'info'),
                ];
                // The "deleted" toast of a soft delete carries the record: the
                // client offers Undo for a few seconds, as the Livewire toast.
                if (($event['params']['undoId'] ?? null) !== null) {
                    $message['undo'] = $event['params']['undoId'];
                }
                $out[] = $message;
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
