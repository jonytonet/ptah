<?php

declare(strict_types=1);

namespace Ptah\Livewire\SearchDropdown;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Ptah\DTO\SearchDropdownDTO;
use Ptah\Support\SqlIdentifier;

/**
 * Livewire SearchDropdown component.
 *
 * Dynamic search dropdown with support for:
 *  - Direct-model search or via custom service
 *  - Multiple display fields (label, labelTwo, labelThree)
 *  - Additional query filters (dataFilter)
 *  - Per-field format masks (formatValue)
 *  - Configurable selection event via $listens
 *
 * Livewire 4 — uses dispatch() and #[On(...)].
 *
 * Basic usage:
 *
 *   @livewire('ptah-search-dropdown', [
 *       'model'  => 'Product',
 *       'label'  => 'name',
 *       'listens' => 'onProductSelected',
 *   ])
 *
 * With a service:
 *   @livewire('ptah-search-dropdown', [
 *       'model'      => 'Product',
 *       'label'      => 'name',
 *       'useService' => 'search',
 *   ])
 */
class SearchDropdown extends Component
{
    // ── Data ───────────────────────────────────────────────────────────────

    /** Search results */
    public array $dataModel = [];

    // ── Field configuration ─────────────────────────────────────────────────
    //
    // Everything below is set once by the parent at mount() and defines WHAT is
    // queried and HOW (model, service, columns, ORDER BY, filters). None of it is
    // user input, so all of it is #[Locked]: Livewire would otherwise let the
    // client rewrite these via the request payload, turning them into SQLi
    // (orderByRaw), arbitrary class/method execution (serviceClass + useService)
    // and arbitrary-model/column exfiltration (modelClass + label + _raw) vectors.
    // The only real user input — the search term — arrives as the search() argument.

    /** Column whose value is returned in the event (usually "id") */
    #[Locked]
    public string $value = 'id';

    /** Column displayed as the main label */
    #[Locked]
    public string $label = 'name';

    /** Column displayed as second label (optional) */
    #[Locked]
    public ?string $labelTwo = null;

    /** Column displayed as third label (optional) */
    #[Locked]
    public ?string $labelThree = null;

    /** Extra columns included in the LIKE search */
    #[Locked]
    public array $arraySearch = [];

    // ── Model / service configuration ───────────────────────────────────────

    /**
     * Model name for searching.
     * Supports sub-directories: "Product", "Purchase/Order".
     */
    #[Locked]
    public string $model = '';

    /** Resolved model FQCN class */
    #[Locked]
    public string $modelClass = '';

    /** Resolved service FQCN class */
    #[Locked]
    public string $serviceClass = '';

    /**
     * Service method name to be called for searching.
     * When set, uses $serviceClass->{$useService}(SearchDropdownDTO).
     */
    #[Locked]
    public ?string $useService = null;

    // ── Search and filters ─────────────────────────────────────────────────

    /** Term typed by the user */
    public ?string $searchTerm = null;

    /** Additional WHERE filters: [['col', 'op', 'val'], ...] or ['col' => 'val'] */
    #[Locked]
    public array $dataFilter = [];

    /** Result limit */
    #[Locked]
    public int $limit = 10;

    /** ORDER BY raw */
    #[Locked]
    public string $orderByRaw = 'id asc';

    // ── UI ─────────────────────────────────────────────────────────────────

    /** Unique component key (for wire:key) */
    public string $key = '';

    /** Input placeholder */
    public string $placeholder = 'Select';

    /** Initial list position: "top" or "bottom" */
    public string $startList = 'bottom';

    /** If true, loads data even without a search term. */
    public bool $initWithData = true;

    // ── Event ─────────────────────────────────────────────────────────────

    /** Livewire 4 event name fired when an item is selected */
    #[Locked]
    public string $listens = 'searchDropdownResult';

    /** Extra value passed in the event payload */
    #[Locked]
    public string $coringa = '';

    // ── Format masks ───────────────────────────────────────────────────────

    /**
     * Format masks per slot.
     * Each mask can be:
     *   - "defaultMask"               → displays the value without transformation
     *   - "cnpj"                      → formats as CNPJ
     *   - "cpf"                       → formats as CPF
     *   - "money"                     → R$ 1.234,56
     *   - "phone"                     → (11) 9 9999-9999
     *   - "date"                      → dd/mm/yyyy
     *   - "App\Helpers\Masks::format" → static call (Class::method)
     *   - "App\Services\Mask@format"  → IoC call (Class@method)
     *   - name of a public method of the component itself
     */
    #[Locked]
    public string $maskOne = 'defaultMask';

    #[Locked]
    public string $maskTwo = 'defaultMask';

    #[Locked]
    public string $maskThree = 'defaultMask';

    // ── Initialisation ─────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->resolveModelClass();
    }

    // ── Render ─────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('ptah::livewire.search-dropdown.search-dropdown');
    }

    // ── Search (called by Alpine via $wire.search()) ─────────────────────────

    /**
     * Executes the search and returns formatted results as a JSON-ready array.
     * Called by Alpine with $wire.search(term) — no full re-render triggered.
     *
     * Each item contains: _value, _label, _labelTwo, _labelThree, _raw, plus
     * the internal _ptahLabel/_ptahLabelTwo/_ptahLabelThree siblings that
     * selectedItem() reads back (see its docblock). "_raw" itself is always
     * the clean row — model columns (or the service-mode array) only, never
     * the "_ptahLabel*" keys loadDataViaModel() injects to resolve a camelCase
     * relation label (see readLabel()): those would otherwise leak into the
     * consumer-facing payload alongside real model columns.
     */
    public function search(?string $term): array
    {
        $this->searchTerm = $term;
        $this->loadData();

        return array_map(function (array $item): array {
            $rawLabel = $this->readLabel($item, '_ptahLabel', $this->label);
            $rawLabelTwo = $this->readLabel($item, '_ptahLabelTwo', $this->labelTwo);
            $rawLabelThree = $this->readLabel($item, '_ptahLabelThree', $this->labelThree);

            return [
                '_value' => $item[$this->value] ?? '',
                '_label' => $this->formatValue($rawLabel ?? '', $this->maskOne),
                '_labelTwo' => $this->labelTwo !== null ? $this->formatValue($rawLabelTwo ?? '', $this->maskTwo) : null,
                '_labelThree' => $this->labelThree !== null ? $this->formatValue($rawLabelThree ?? '', $this->maskThree) : null,
                '_raw' => $this->stripInternalKeys($item),
                '_ptahLabel' => $rawLabel,
                '_ptahLabelTwo' => $rawLabelTwo,
                '_ptahLabelThree' => $rawLabelThree,
            ];
        }, $this->dataModel);
    }

    /**
     * Removes the internal "_ptahLabel*" keys (injected by loadDataViaModel()
     * to resolve a camelCase relation label — see readLabel()) from a row
     * before it is exposed as "_raw", so the consumer only ever sees plain
     * model columns (or the plain service-mode array), never ptah-internal
     * bookkeeping.
     */
    private function stripInternalKeys(array $item): array
    {
        unset($item['_ptahLabel'], $item['_ptahLabelTwo'], $item['_ptahLabelThree']);

        return $item;
    }

    /**
     * Reads a label value from a result row.
     *
     * Model-mode rows carry a pre-resolved value under $resolvedKey — computed
     * from the Eloquent model instance before toArray() in loadDataViaModel(),
     * because Eloquent's array conversion snake-cases relation keys and a
     * camelCase relation path (e.g. "ownerCompany.name") would otherwise
     * silently resolve to null via data_get() on the array. Service-mode rows
     * have no such key, so we fall back to data_get() on the plain array
     * (equivalent to a direct key lookup for the flat, dot-free keys that
     * service-mode responses are documented to use).
     *
     * Used by search() only — selectedItem() has its own (simpler) resolution
     * because it must also tolerate a stale, already-published view still
     * calling it with the raw row alone (see selectedItem()'s docblock).
     */
    private function readLabel(array $item, string $resolvedKey, ?string $path): mixed
    {
        if ($path === null) {
            return null;
        }

        return array_key_exists($resolvedKey, $item) ? $item[$resolvedKey] : data_get($item, $path);
    }

    // ── Data ───────────────────────────────────────────────────────────────
    private function loadData(): void
    {
        if ($this->useService) {
            $this->loadDataViaService();
        } else {
            $this->loadDataViaModel();
        }
    }

    /**
     * Searches using a custom service.
     * The service must accept a SearchDropdownDTO as argument.
     */
    private function loadDataViaService(): void
    {
        $dto = new SearchDropdownDTO(
            searchTerm: $this->searchTerm,
            value: $this->value,
            label: $this->label,
            labelTwo: $this->labelTwo,
            labelThree: $this->labelThree,
            orderByRaw: $this->orderByRaw,
            limit: $this->limit,
            arraySearch: $this->arraySearch,
            dataFilter: $this->dataFilter,
        );

        $result = app()->make($this->serviceClass)->{$this->useService}($dto);

        $this->dataModel = $result instanceof Collection
            ? $result->toArray()
            : (array) $result;
    }

    /**
     * Queries directly from the Eloquent model.
     */
    private function loadDataViaModel(): void
    {
        // If we already have a term, ensure initWithData stays active
        if (strlen((string) $this->searchTerm) > 1) {
            $this->initWithData = true;
        }

        $cols = array_filter([$this->value, $this->label, $this->labelTwo, $this->labelThree]);

        // label/labelTwo/labelThree support dot-notation for a relation column
        // (e.g. "user.name"). When none of them uses one, behaviour is unchanged
        // (column-limited select). When at least one does, the base FK column
        // must survive, so we select everything and eager-load the relation.
        $labelSlots = array_filter([$this->label, $this->labelTwo, $this->labelThree]);
        $relPaths = [];
        foreach ($labelSlots as $slot) {
            if (str_contains($slot, '.')) {
                $relPaths[] = substr($slot, 0, strrpos($slot, '.'));
            }
        }

        /** @var Builder $query */
        $query = app()->make($this->modelClass)->newQuery();

        if ($relPaths === []) {
            $query->select(array_values($cols));
        } else {
            $query->with(array_values(array_unique($relPaths)));
        }

        // Apply LIKE on the configured fields.
        // The term is split into individual words so that "joão silva" matches
        // "João da Silva" regardless of word order — all words must appear in
        // the same column (AND), but any column can satisfy the rule (OR).
        // This is database-agnostic and has no extra performance cost over a
        // single LIKE, since both require a full scan when % leads the pattern.
        if (! empty($this->searchTerm)) {
            $searchCols = array_merge(
                array_filter([$this->label, $this->labelTwo, $this->labelThree, $this->value]),
                $this->arraySearch
            );

            $words = preg_split('/\s+/', trim($this->searchTerm), -1, PREG_SPLIT_NO_EMPTY);

            $query->where(function (Builder $q) use ($searchCols, $words) {
                foreach ($searchCols as $col) {
                    if (str_contains($col, '.')) {
                        $lastDot = strrpos($col, '.');
                        $relPath = substr($col, 0, $lastDot);
                        $relColumn = substr($col, $lastDot + 1);

                        if (! SqlIdentifier::isSafe($relColumn)) {
                            continue;
                        }

                        // Each column: ALL words must be present (AND), but any
                        // column match counts (OR between columns) — same rule
                        // as the base-model branch below, scoped to the relation.
                        $q->orWhereHas($relPath, function (Builder $sub) use ($relColumn, $words) {
                            foreach ($words as $word) {
                                $sub->where($relColumn, 'LIKE', '%'.$word.'%');
                            }
                        });

                        continue;
                    }

                    // Each column: ALL words must be present (AND), but any
                    // column match counts (OR between columns).
                    $q->orWhere(function (Builder $sub) use ($col, $words) {
                        foreach ($words as $word) {
                            $sub->where($col, 'LIKE', '%'.$word.'%');
                        }
                    });
                }
            });
        }

        // Additional filters
        if (! empty($this->dataFilter)) {
            $query->where($this->dataFilter);
        }

        $models = $query
            ->orderByRaw($this->orderByRaw)
            ->limit($this->limit)
            ->get();

        // Resolve label/labelTwo/labelThree from the MODEL instances — not from
        // the array produced by toArray(). Eloquent's array conversion
        // snake-cases relation keys (HasAttributes::relationsToArray()), so a
        // camelCase relation such as "ownerCompany" becomes "owner_company" in
        // the array; reading the original ("ownerCompany.name") dot-path back
        // via data_get() on that array would silently resolve to null (or, if
        // the label were rewritten to the snake_case path, orWhereHas() above
        // would still target the real "ownerCompany" relation and throw).
        // data_get() on the Model itself walks relations by their real
        // (camelCase) method name, so it is resolved here, once, before the
        // snake-casing ever happens.
        $this->dataModel = $models->map(function (Model $model) {
            $row = $model->toArray();
            $row['_ptahLabel'] = data_get($model, $this->label);
            $row['_ptahLabelTwo'] = $this->labelTwo !== null ? data_get($model, $this->labelTwo) : null;
            $row['_ptahLabelThree'] = $this->labelThree !== null ? data_get($model, $this->labelThree) : null;

            return $row;
        })->all();
    }

    // ── UI events ────────────────────────────────────────────────────────────

    /**
     * Toggles the dropdown visibility via a browser event caught by Alpine.
     * Kept for backward compatibility.
     */
    public function toggleShow(): void
    {
        $this->dispatch('ptah-sd-change-show-'.$this->key);
    }

    /** Receives external Livewire event to toggle the dropdown. */
    #[On('changeShow')]
    public function changeShow(): void
    {
        $this->toggleShow();
    }

    /**
     * Receives external Livewire event to reset the dropdown.
     * Dispatches a browser event that Alpine handles to clear term/results/show.
     */
    #[On('clearSearchDropdown')]
    public function clearSearchDropdown(): void
    {
        $this->dispatch('ptah-sd-clear-'.$this->key);
    }

    // ── Selection ────────────────────────────────────────────────────────────

    /**
     * Processes the selection of an item and fires the configured Livewire event.
     * show/term state is managed by Alpine — not touched here.
     *
     * $item accepts two shapes, so a stale, already-published view (the
     * standalone blade shipped in v1.9.0 called `$wire.selectedItem(item._raw)`)
     * keeps working after a package-only update — updating the blade too
     * (`--tag=ptah-views`) is not required for this to keep dispatching the
     * right value/label:
     *   - Current blade — full item (has a "_raw" key): $raw is that "_raw".
     *     The pre-resolved "_ptahLabel" sibling (set by search() — see its
     *     docblock) is read straight from $item, so a camelCase relation
     *     label still resolves correctly.
     *   - Stale blade — raw row alone (no "_raw" key): $item IS the row, so
     *     $raw falls back to $item itself. There is no "_ptahLabel" sibling
     *     here, so the label is read via data_get() on the row — which, for
     *     a camelCase relation label, is already snake-cased by toArray()
     *     and resolves to null (degrading gracefully to an empty label, never
     *     a crash); value and a plain (non-relation) label still resolve.
     */
    public function selectedItem(array $item): void
    {
        // $item may be the full result item (new blade → has an array "_raw")
        // or a bare row (a stale published blade passing item._raw). Guard the
        // type so a forged non-array "_raw" degrades cleanly instead of raising
        // an illegal-offset error.
        $raw = is_array($item['_raw'] ?? null) ? $item['_raw'] : $item;
        $value = $raw[$this->value] ?? null;
        $label = $item['_ptahLabel'] ?? data_get($raw, $this->label) ?? '';
        $displayTerm = $value.' - '.$label;

        $this->dispatch($this->listens, [
            'useService' => $this->useService,
            'value' => $value,
            'label' => $label,
            'searchTerm' => $displayTerm,
            'coringa' => $this->coringa,
        ]);
    }

    /**
     * Clears the selection and fires the configured Livewire event.
     * Alpine resets term/results/show on its own before calling this method.
     */
    public function clearData(): void
    {
        $this->dispatch($this->listens, [
            'useService' => $this->useService,
            'value' => '',
            'label' => '',
            'searchTerm' => '',
            'coringa' => $this->coringa,
        ]);
    }

    // ── Formatting ────────────────────────────────────────────────────────────

    /**
     * Applies a format mask to a value.
     *
     * Resolution order:
     *   1. 'defaultMask'          → returns value as-is
     *   2. built-in names         → cnpj | cpf | money | phone | date
     *   3. 'App\Helpers\Cls::m'   → static method call (contains '::')
     *   4. 'App\Services\Cls@m'   → IoC instance method call (contains '@')
     *   5. public method name     → $this->{$mask}($v)
     *   6. fallback               → returns value as-is
     */
    public function formatValue(mixed $value, string $mask): string
    {
        if ($value === null) {
            return '';
        }

        $v = (string) $value;

        if ($mask === 'defaultMask') {
            return $v;
        }

        // Built-in masks
        $builtin = match ($mask) {
            'cnpj' => $this->applyMaskCnpj($v),
            'cpf' => $this->applyMaskCpf($v),
            'money' => $this->applyMaskMoney($v),
            'phone' => $this->applyMaskPhone($v),
            'date' => $this->applyMaskDate($v),
            default => null,
        };

        if ($builtin !== null) {
            return $builtin;
        }

        // Dynamic: static call — 'App\Helpers\Masks::format'
        if (str_contains($mask, '::')) {
            [$class, $method] = explode('::', $mask, 2);
            if (class_exists($class) && method_exists($class, $method)) {
                return (string) $class::$method($v);
            }

            return $v;
        }

        // Dynamic: IoC instance call — 'App\Services\MaskService@format'
        if (str_contains($mask, '@')) {
            [$class, $method] = explode('@', $mask, 2);
            if (class_exists($class)) {
                return (string) app($class)->{$method}($v);
            }

            return $v;
        }

        // Component method
        if (method_exists($this, $mask)) {
            return (string) $this->{$mask}($v);
        }

        return $v;
    }

    // ── Internal helpers ───────────────────────────────────────────────────────────

    /**
     * Resolves the model and service FQCN classes based on $model.
     * Supports sub-directories separated by "/": "Purchase/Order" → App\Models\Purchase\Order.
     */
    protected function resolveModelClass(): void
    {
        $segments = array_map('ucfirst', explode('/', $this->model));
        $suffix = implode('\\', $segments);

        $this->modelClass = 'App\\Models\\'.$suffix;
        $this->serviceClass = 'App\\Services\\'.$suffix.'Service';
    }

    private function applyMaskCnpj(string $v): string
    {
        $digits = preg_replace('/\D/', '', $v);
        if (strlen($digits) !== 14) {
            return $v;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2)
        );
    }

    private function applyMaskCpf(string $v): string
    {
        $digits = preg_replace('/\D/', '', $v);
        if (strlen($digits) !== 11) {
            return $v;
        }

        return sprintf(
            '%s.%s.%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2)
        );
    }

    private function applyMaskMoney(string $v): string
    {
        $num = (float) str_replace(',', '.', preg_replace('/[^\d,.]/', '', $v));

        return 'R$ '.number_format($num, 2, ',', '.');
    }

    private function applyMaskPhone(string $v): string
    {
        $digits = preg_replace('/\D/', '', $v);
        $len = strlen($digits);

        if ($len === 11) {
            return sprintf('(%s) %s %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 1),
                substr($digits, 3, 4),
                substr($digits, 7, 4)
            );
        }

        if ($len === 10) {
            return sprintf('(%s) %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 4),
                substr($digits, 6, 4)
            );
        }

        return $v;
    }

    private function applyMaskDate(string $v): string
    {
        try {
            return Carbon::parse($v)->format('d/m/Y');
        } catch (\Throwable) {
            return $v;
        }
    }
}
