<?php

declare(strict_types=1);

namespace Ptah\Livewire\SearchDropdown;

use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Ptah\DTO\SearchDropdownDTO;

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

    /** Column whose value is returned in the event (usually "id") */
    public string $value = 'id';

    /** Column displayed as the main label */
    public string $label = 'name';

    /** Column displayed as second label (optional) */
    public ?string $labelTwo = null;

    /** Column displayed as third label (optional) */
    public ?string $labelThree = null;

    /** Extra columns included in the LIKE search */
    public array $arraySearch = [];

    // ── Model / service configuration ───────────────────────────────────────

    /**
     * Model name for searching.
     * Supports sub-directories: "Product", "Purchase/Order".
     */
    public string $model = '';

    /** Resolved model FQCN class */
    public string $modelClass = '';

    /** Resolved service FQCN class */
    public string $serviceClass = '';

    /**
     * Service method name to be called for searching.
     * When set, uses $serviceClass->{$useService}(SearchDropdownDTO).
     */
    public ?string $useService = null;

    // ── Search and filters ─────────────────────────────────────────────────

    /** Term typed by the user */
    public ?string $searchTerm = null;

    /** Additional WHERE filters: [['col', 'op', 'val'], ...] or ['col' => 'val'] */
    public array $dataFilter = [];

    /** Result limit */
    public int $limit = 10;

    /** ORDER BY raw */
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

    /** Controls dropdown visibility */
    public bool $show = false;

    // ── Event ─────────────────────────────────────────────────────────────

    /** Livewire 4 event name fired when an item is selected */
    public string $listens = 'searchDropdownResult';

    /** Extra value passed in the event payload */
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
    public string $maskOne   = 'defaultMask';
    public string $maskTwo   = 'defaultMask';
    public string $maskThree = 'defaultMask';

    // ── Initialisation ─────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->resolveModelClass();
    }

    // ── Render ─────────────────────────────────────────────────────────────

    public function render(): \Illuminate\View\View
    {
        $this->loadData();

        $data = $this->initWithData ? $this->dataModel : [];

        return view('ptah::livewire.search-dropdown.search-dropdown', compact('data'));
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
            value:      $this->value,
            label:      $this->label,
            labelTwo:   $this->labelTwo,
            labelThree: $this->labelThree,
            orderByRaw: $this->orderByRaw,
            limit:      $this->limit,
            arraySearch: $this->arraySearch,
            dataFilter:  $this->dataFilter,
        );

        $result = app()->make($this->serviceClass)->{$this->useService}($dto);

        $this->dataModel = $result instanceof \Illuminate\Support\Collection
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

        /** @var \Illuminate\Database\Eloquent\Model $query */
        $query = app()->make($this->modelClass)->select(array_values($cols));

        // Apply LIKE on the configured fields
        if (!empty($this->searchTerm)) {
            $searchCols = array_merge(
                array_filter([$this->label, $this->labelTwo, $this->labelThree, $this->value]),
                $this->arraySearch
            );

            $query->where(function ($q) use ($searchCols) {
                foreach ($searchCols as $col) {
                    $q->orWhere($col, 'LIKE', '%' . $this->searchTerm . '%');
                }
            });
        }

        // Additional filters
        if (!empty($this->dataFilter)) {
            $query->where($this->dataFilter);
        }

        $this->dataModel = $query
            ->orderByRaw($this->orderByRaw)
            ->limit($this->limit)
            ->get()
            ->toArray();
    }

    // ── UI events ────────────────────────────────────────────────────────────

    /** Opens/closes the dropdown */
    public function toggleShow(): void
    {
        $this->show = !$this->show;
    }

    /** Receives external event to close/open */
    #[On('changeShow')]
    public function changeShow(): void
    {
        $this->toggleShow();
    }

    /** Clears the search term via external event */
    #[On('clearSearchDropdown')]
    public function clearSearchDropdown(): void
    {
        $this->searchTerm = '';
    }

    // ── Selection ────────────────────────────────────────────────────────────

    /**
     * Processes the selection of an item and fires the configured event.
     */
    public function selectedItem(array $item): void
    {
        $this->searchTerm = $item[$this->value] . ' - ' . $item[$this->label];

        $this->dispatch($this->listens, [
            'useService' => $this->useService,
            'value'      => $item[$this->value],
            'label'      => $item[$this->label],
            'searchTerm' => $this->searchTerm,
            'coringa'    => $this->coringa,
        ]);

        $this->show = false;
    }

    /**
     * Clears the selection and fires the event with empty values.
     */
    public function clearData(): void
    {
        $this->dispatch($this->listens, [
            'useService' => $this->useService,
            'value'      => '',
            'label'      => '',
            'searchTerm' => '',
            'coringa'    => $this->coringa,
        ]);

        $this->searchTerm = '';
        $this->show       = false;
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
            'cnpj'  => $this->applyMaskCnpj($v),
            'cpf'   => $this->applyMaskCpf($v),
            'money' => $this->applyMaskMoney($v),
            'phone' => $this->applyMaskPhone($v),
            'date'  => $this->applyMaskDate($v),
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
        $suffix   = implode('\\', $segments);

        $this->modelClass   = 'App\\Models\\' . $suffix;
        $this->serviceClass = 'App\\Services\\' . $suffix . 'Service';
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

        return 'R$ ' . number_format($num, 2, ',', '.');
    }

    private function applyMaskPhone(string $v): string
    {
        $digits = preg_replace('/\D/', '', $v);
        $len    = strlen($digits);

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
            return \Carbon\Carbon::parse($v)->format('d/m/Y');
        } catch (\Throwable) {
            return $v;
        }
    }
}
