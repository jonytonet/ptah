<?php

declare(strict_types=1);

namespace Ptah\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Ptah\DTO\FilterDTO;
use Ptah\Models\CrudConfig;
use Ptah\Models\UserPreference;
use Ptah\Services\Cache\CacheService;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Services\Crud\FilterService;
use Ptah\Services\Crud\FormValidatorService;

/**
 * Componente Livewire BaseCrud.
 *
 * Renderiza uma tela completa de listagem com:
 *  - Tabela dinâmica com sort, filtros, paginação
 *  - Modal de criação/edição
 *  - Soft delete / restauração
 *  - Exportação (sincr./assincr.)
 *  - Preferências por usuário (V2)
 *  - Estilos condicionais de linha
 *  - Helpers de formatação de célula
 *  - SearchDropdown para campos com colsSDModel
 *  - CustomFilters com whereHas
 *
 * Uso:
 *   @livewire('ptah::base-crud', ['model' => 'Product'])
 */
class BaseCrud extends Component
{
    use WithPagination;

    // ── Configuração ───────────────────────────────────────────────────────

    /** Identificador do model (ex: "Product", "Purchase/Order/PurchaseOrders") */
    public string $model = '';

    /** Configuração completa do CrudConfig */
    public array $crudConfig = [];

    // ── Estado da tabela ────────────────────────────────────────────────────

    public string  $sort        = 'id';
    public string  $direction   = 'DESC';
    public int     $perPage     = 25;
    public string  $search      = '';
    public bool    $showTrashed = false;
    public int     $trashedCount = 0;

    // ── whereHas externo ────────────────────────────────────────────────────

    /** Permite abrir o CRUD pré-filtrado por uma relação pai */
    public string $whereHasFilter    = '';
    public array  $whereHasCondition = [];

    // ── Visibilidade de colunas ─────────────────────────────────────────────

    /** Mapa [fieldName => bool] de colunas visíveis */
    public array $formDataColumns  = [];
    public int   $hiddenColumnsCount = 0;

    // ── Resumo visual de filtros ─────────────────────────────────────────────

    /** Badges de filtros ativos: [{label, value}] */
    public array $textFilter = [];

    // ── Bulk actions ────────────────────────────────────────────────────────

    public array $selectedRows          = [];
    public bool  $selectAll             = false;
    public bool  $bulkActionInProgress  = false;
    public bool  $showBulkActions       = false;

    // ── Filtros rápidos de data ──────────────────────────────────────────────

    /** 'today'|'week'|'month'|'quarter'|'year'|'' */
    public string $quickDateFilter = '';
    /** Coluna de data usada pelo quick date filter */
    public string $quickDateColumn = '';

    // ── Busca avançada ───────────────────────────────────────────────────────

    public bool  $advancedSearchActive = false;
    public array $advancedSearchFields = [];
    public array $searchHistory        = [];

    // ── Multi-tenant ────────────────────────────────────────────────────────

    /** ID da empresa ativa (0 = sem filtro) */
    public int $companyFilter = 0;

    // ── Filtros ─────────────────────────────────────────────────────────────

    /** Filtros do formulário de filtros (campo => valor) */
    public array $filters = [];

    /** Operadores por campo: (campo => '='|'LIKE'|'>'|'>='|'<'|'<=') */
    public array $filterOperators = [];

    /** Date range filters (campo_start/campo_end) */
    public array $dateRanges = [];

    /** Operadores para date ranges (campo_start/campo_end => '='|'>='|'<='|'>'|'<') */
    public array $dateRangeOperators = [];

    /** Filtros salvos com nome */
    public array $savedFilters = [];

    /** @var string|null Nome do filtro sendo salvo */
    public ?string $savingFilterName = null;

    public bool $showFilters = false;

    // ── Modal de criação/edição ──────────────────────────────────────────────

    public array  $formData  = [];
    public ?int   $editingId = null;
    public bool   $showModal = false;
    public bool   $creating  = false;

    /** Erros de validação do formulário */
    public array $formErrors = [];

    // ── Exclusão ────────────────────────────────────────────────────────────

    public bool $showDeleteConfirm = false;
    public ?int $deletingId        = null;

    // ── SearchDropdown ──────────────────────────────────────────────────────

    /** Termo de busca para cada campo searchdropdown: [fieldName => query] */
    public array $sdSearches = [];

    /** Resultados para cada campo: [fieldName => [{value, label}]] */
    public array $sdResults  = [];

    /** Labels exibidos para cada campo (modal): [fieldName => label] */
    public array $sdLabels   = [];

    /** Labels exibidos para cada campo SD de filtro: [fieldName => label] */
    public array $sdFilterLabels = [];

    // ── Preferências ────────────────────────────────────────────────────────

    public array  $columnOrder   = [];
    public array  $columnWidths  = [];
    public string $viewDensity   = 'comfortable'; // compact | comfortable | spacious
    public string $viewMode      = 'table';

    // ── Exportação ──────────────────────────────────────────────────────────

    public bool   $showExportMenu = false;
    public string $exportStatus   = '';

    // ── Serviços ─────────────────────────────────────────────────────────────

    protected CrudConfigService  $configService;
    protected FilterService      $filterService;
    protected CacheService       $cacheService;
    protected FormValidatorService $formValidator;

    /** Eloquent model resolvido */
    protected ?Model $eloquentModel = null;

    // ── Ciclo de vida ───────────────────────────────────────────────────────

    public function boot(CrudConfigService $configService, FilterService $filterService, CacheService $cacheService, FormValidatorService $formValidator): void
    {
        $this->configService  = $configService;
        $this->filterService  = $filterService;
        $this->cacheService   = $cacheService;
        $this->formValidator  = $formValidator;

        // Recarrega crudConfig em todo request para garantir dados atualizados do banco
        if ($this->model) {
            $config = $this->configService->find($this->model);
            $this->crudConfig = $config?->config ?? [];
        }
    }

    public function mount(
        string  $model,
        array   $initialFilter        = [],
        string  $whereHasFilter       = '',
        array   $whereHasCondition    = [],
        int     $companyFilter        = 0,
    ): void {
        $this->model             = $model;
        $this->whereHasFilter    = $whereHasFilter;
        $this->whereHasCondition = $whereHasCondition;
        $this->companyFilter     = $companyFilter ?: (int) session('company_id', 0);

        // Carrega a configuração
        $config = $this->configService->find($model);

        if (! $config) {
            $this->crudConfig = [];
            return;
        }

        $this->crudConfig = $config->config;

        // Resolve model Eloquent
        $this->resolveEloquentModel();

        // Inicializa coluna padrão de data para quick date filter
        $this->quickDateColumn = $this->crudConfig['quickDateColumn'] ?? 'created_at';

        // Inicializa visibilidade de colunas
        $this->initFormDataColumns();

        // Carrega preferências do usuário
        $this->loadPreferences();

        // Conta registros deletados
        $this->updateTrashedCount();

        // Aplica filtros iniciais
        if (! empty($initialFilter)) {
            foreach ($initialFilter as $filterItem) {
                if (is_array($filterItem) && count($filterItem) >= 3) {
                    [$field, , $value] = $filterItem;
                    $this->filters[$field] = $value;
                }
            }
        }
    }

    // ── Computed properties ──────────────────────────────────────────────────

    /**
     * Retorna os dados paginados aplicando todos os filtros ativos.
     * Inclui error recovery: limpa preferências corrompidas e retorna lista vazia.
     */
    public function getRowsProperty(): LengthAwarePaginator
    {
        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return new LengthAwarePaginator([], 0, $this->perPage);
        }

        try {
            /** @var Builder $query */
            $query = $modelInstance->newQuery();

            // Soft delete
            $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelInstance));
            if ($usesSoftDeletes && $this->showTrashed) {
                $query->withTrashed();
            }

            // Eager load relacionamentos visíveis na tabela
            $relations = $this->getVisibleRelations();
            if (! empty($relations)) {
                $query->with($relations);
            }

            // Filtro de empresa (multi-tenant)
            if ($this->companyFilter > 0) {
                $table        = $modelInstance->getTable();
                $companyField = $this->crudConfig['companyField'] ?? 'company_id';
                $query->where("{$table}.{$companyField}", $this->companyFilter);
            }

            // whereHasFilter externo (pré-filtrado por entidade pai)
            if ($this->whereHasFilter !== '') {
                [$col, $op, $val] = array_pad($this->whereHasCondition, 3, null);
                if ($col && $val !== null) {
                    $query->whereHas($this->whereHasFilter, function (Builder $q) use ($col, $op, $val) {
                        $q->where($col, $op ?? '=', $val);
                    });
                }
            }

            // Busca global com OR em campos de texto e relações
            if ($this->search !== '') {
                $searchFilters = $this->filterService->buildGlobalSearchFilters(
                    $this->crudConfig['cols'] ?? [],
                    $this->search
                );

                if (! empty($searchFilters)) {
                    $this->filterService->applyFilters($query, $searchFilters);
                }

                // Salva histórico de busca
                $this->addToSearchHistory($this->search);
            }

            // Filtros do formulário
            $activeFilters = $this->buildActiveFilters();
            if (! empty($activeFilters)) {
                $this->filterService->applyFilters($query, $activeFilters);
            }

            // Date ranges (padrão ERP: _start/_end e legado: _from/_to)
            $drFilters = $this->filterService->processDateRangeFilters($this->dateRanges, $this->dateRangeOperators);
            if (! empty($drFilters)) {
                $this->filterService->applyFilters($query, $drFilters);
            }

            // Filtro rápido de data
            if ($this->quickDateFilter !== '' && $this->quickDateColumn !== '') {
                [$from, $to] = $this->getQuickDateRange($this->quickDateFilter);
                if ($from && $to) {
                    $query->whereBetween($this->quickDateColumn, [$from, $to]);
                }
            }

            // Filtros customizados
            $customFilterConfig = $this->crudConfig['customFilters'] ?? [];
            $cfFilters          = $this->filterService->processCustomFilters($customFilterConfig, $this->filters);
            if (! empty($cfFilters)) {
                $this->filterService->applyFilters($query, $cfFilters);
            }

            // Ordenação (suporta relação via JOIN)
            $relationInfo = $this->getOrderByRelationInfo($this->sort);

            if ($relationInfo) {
                [$rel, $displayCol, $fk, $relTable] = $relationInfo;
                $mainTable = $modelInstance->getTable();
                $query->leftJoin(
                    $relTable,
                    "{$mainTable}.{$fk}",
                    '=',
                    "{$relTable}.id"
                )->orderBy("{$relTable}.{$displayCol}", $this->direction)
                ->select("{$mainTable}.*");
            } else {
                $sortCol = $this->resolveSortColumn();
                $query->orderBy($sortCol, $this->direction);
            }

            return $query->paginate($this->perPage);

        } catch (\Exception $e) {
            // Limpa preferências potencialmente corrompidas
            $userId = Auth::id();
            if ($userId) {
                UserPreference::remove($userId, 'crud.' . $this->model);
                $this->cacheService->forgetPreferences($userId, $this->model);
            }

            Log::error('Ptah BaseCrud: erro ao carregar dados', [
                'model' => $this->model,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->flash('error', 'Erro ao carregar os dados. Preferências resetadas.');

            return new LengthAwarePaginator([], 0, $this->perPage, 1, [
                'path' => request()->url(),
            ]);
        }
    }


    /**
     * Totalizadores (somas/contagens/médias de colunas configuradas).
     * Cada agregado clona a query para evitar interferência mútua.
     */
    public function getTotalizadoresDataProperty(): array
    {
        $totConfig = $this->crudConfig['totalizadores'] ?? [];

        if (empty($totConfig['enabled']) || empty($totConfig['columns'])) {
            return [];
        }

        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return [];
        }

        // Monta a query base com os filtros
        $baseQuery     = $modelInstance->newQuery();
        $activeFilters = $this->buildActiveFilters();

        if (! empty($activeFilters)) {
            $this->filterService->applyFilters($baseQuery, $activeFilters);
        }

        $drFilters = $this->filterService->processDateRangeFilters($this->dateRanges, $this->dateRangeOperators);
        if (! empty($drFilters)) {
            $this->filterService->applyFilters($baseQuery, $drFilters);
        }

        $result = [];

        foreach ($totConfig['columns'] as $totCol) {
            $field     = $totCol['field']     ?? null;
            $aggregate = $totCol['aggregate'] ?? 'sum';

            if (! $field) {
                continue;
            }

            // Clona a query para cada agregado (evita SELECT acumulado)
            $cloned = clone $baseQuery;

            $result[$field] = match ($aggregate) {
                'sum'   => $cloned->sum($field),
                'count' => $cloned->count($field),
                'avg'   => round((float) $cloned->avg($field), 2),
                'max'   => $cloned->max($field),
                'min'   => $cloned->min($field),
                default => null,
            };
        }

        return $result;
    }


    // ── Ações de tabela ─────────────────────────────────────────────────────

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'ASC' ? 'DESC' : 'ASC';
        } else {
            $this->sort      = $column;
            $this->direction = 'ASC';
        }

        $this->resetPage();
        $this->savePreferences();
    }

    /**
     * Salva a nova ordem de colunas arrastadas pelo usuário.
     * Chamado via $wire.call('reorderColumns', ['campo1','campo2',...]) no JS.
     */
    public function reorderColumns(array $newOrder): void
    {
        $this->columnOrder = $newOrder;
        $this->savePreferences();
    }

    /**
     * Salva a largura de uma coluna redimensionada pelo usuário.
     * Chamado via $wire.call('saveColumnWidth', 'campo', 150) no JS.
     */
    public function saveColumnWidth(string $column, int $width): void
    {
        $this->columnWidths[$column] = max(60, $width);
        $this->savePreferences();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->savePreferences();
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
        $this->buildTextFilter();
        $this->savePreferences();
    }

    public function updatedFilterOperators(): void
    {
        $this->resetPage();
        $this->savePreferences();
    }

    public function updatedDateRanges(): void
    {
        $this->resetPage();
        $this->buildTextFilter();
        $this->savePreferences();
    }

    public function updatedDateRangeOperators(): void
    {
        $this->resetPage();
        $this->savePreferences();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
        $this->savePreferences();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function clearFilters(): void
    {
        $this->filters              = [];
        $this->filterOperators      = [];
        $this->dateRanges           = [];
        $this->dateRangeOperators   = [];
        $this->sdSearches           = [];
        $this->sdResults            = [];
        $this->sdLabels             = [];
        $this->sdFilterLabels       = [];
        $this->quickDateFilter      = '';
        $this->textFilter           = [];
        $this->advancedSearchActive = false;
        $this->search               = '';
        $this->resetPage();
        $this->savePreferences();
    }

    public function toggleTrashed(): void
    {
        $this->showTrashed = ! $this->showTrashed;
        $this->resetPage();
    }

    public function setViewDensity(string $density): void
    {
        $allowed = ['compact', 'comfortable', 'spacious'];
        if (! in_array($density, $allowed, true)) {
            return;
        }
        $this->viewDensity = $density;
        $this->savePreferences();
    }

    // ── Modal de formulário ──────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->formData   = [];
        $this->formErrors = [];
        $this->editingId  = null;
        $this->sdSearches = [];
        $this->sdResults  = [];
        $this->sdLabels   = [];
        $this->showModal  = true;
    }

    public function openEdit(int $id): void
    {
        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return;
        }

        $record = $modelInstance->newQuery()->find($id);

        if (! $record) {
            return;
        }

        $this->editingId  = $id;
        $this->formData   = $record->toArray();
        $this->formErrors = [];
        $this->sdSearches = [];
        $this->sdResults  = [];

        // Pré-popula labels dos searchdropdowns
        $this->preloadSdLabels($record);

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal  = false;
        $this->editingId  = null;
        $this->formData   = [];
        $this->formErrors = [];
    }

    public function save(): void
    {
        if ($this->creating) {
            return;
        }

        $this->creating   = true;
        $this->formErrors = [];

        // Validação rica via FormValidatorService (required + email, min, max, regex, CPF, etc.)
        $formCols = $this->getFormCols();
        $this->formErrors = $this->formValidator->validate($this->formData, $formCols);

        if (! empty($this->formErrors)) {
            $this->creating = false;
            return;
        }

        // Monta dados apenas das colunas com colsGravar == 'S'
        $savableFields = array_column($formCols, 'colsNomeFisico');
        $data          = array_intersect_key($this->formData, array_flip($savableFields));

        // Aplica transformações de máscara antes de persistir (money→float, CPF→dígitos, etc.)
        $data = $this->applyMaskTransforms($data, $formCols);

        try {
            $modelInstance = $this->resolveEloquentModel();

            if ($this->editingId) {
                $record = $modelInstance->newQuery()->findOrFail($this->editingId);
                $record->update($data);
            } else {
                $modelInstance->newQuery()->create($data);
            }

            // Invalida cache
            $this->cacheService->invalidateModel($this->model);

            $this->closeModal();
            $this->dispatch('crud-saved', model: $this->model);
        } catch (\Throwable $e) {
            $this->formErrors['_general'] = 'Erro ao salvar: ' . $e->getMessage();
        }

        $this->creating = false;
    }

    // ── Exclusão ──────────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->deletingId        = $id;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->deletingId        = null;
        $this->showDeleteConfirm = false;
    }

    public function deleteRecord(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $modelInstance = $this->resolveEloquentModel();
        $record        = $modelInstance->newQuery()->find($this->deletingId);

        if ($record) {
            $record->delete();
            $this->cacheService->invalidateModel($this->model);
            $this->updateTrashedCount();
        }

        $this->cancelDelete();
        $this->dispatch('crud-deleted', model: $this->model);
    }

    public function restoreRecord(int $id): void
    {
        $modelInstance = $this->resolveEloquentModel();
        $record        = $modelInstance->newQuery()->withTrashed()->find($id);

        if ($record && method_exists($record, 'restore')) {
            $record->restore();
        }

        $this->dispatch('crud-restored', model: $this->model);
    }

    // ── SearchDropdown ─────────────────────────────────────────────────────

    /**
     * Chamado em tempo real quando o usuário digita em um campo searchdropdown.
     * Retorna sugestões em $this->sdResults[fieldName].
     */
    public function searchDropdown(string $field, string $query): void
    {
        $this->sdSearches[$field] = $query;

        if (strlen($query) < 1) {
            $this->sdResults[$field] = [];
            return;
        }

        // Encontra a config da coluna
        $col = $this->findColByField($field);

        if (! $col) {
            return;
        }

        $sdModel = $col['colsSDModel'] ?? null;
        $sdLabel = $col['colsSDLabel'] ?? 'name';
        $sdValue = $col['colsSDValor'] ?? 'id';
        $sdOrder = $col['colsSDOrder'] ?? "{$sdLabel} ASC";
        $sdTipo  = $col['colsSDTipo']  ?? 'model';
        $sdLimit = (int) ($col['colsSDLimit'] ?? 15);

        if (! $sdModel) {
            return;
        }

        $this->sdResults[$field] = $this->resolveSearchDropdownResults(
            $sdTipo, $sdModel, $sdLabel, $sdValue, $sdOrder, $query, $sdLimit
        );
    }

    /**
     * Chamado no foco/clique do campo: carrega os primeiros itens sem filtro.
     */
    public function openDropdown(string $field): void
    {
        // Se já há resultados visíveis, não recarrega
        if (! empty($this->sdResults[$field])) {
            return;
        }

        $col = $this->findColByField($field);

        if (! $col) {
            return;
        }

        $sdModel = $col['colsSDModel'] ?? null;
        $sdLabel = $col['colsSDLabel'] ?? 'name';
        $sdValue = $col['colsSDValor'] ?? 'id';
        $sdOrder = $col['colsSDOrder'] ?? "{$sdLabel} ASC";
        $sdTipo  = $col['colsSDTipo']  ?? 'model';
        $sdLimit = (int) ($col['colsSDLimit'] ?? 15);

        if (! $sdModel) {
            return;
        }

        // Usa o texto que o usuário já digitou (se houver) ou carrega tudo
        $currentQuery = $this->sdSearches[$field] ?? '';

        $this->sdResults[$field] = $this->resolveSearchDropdownResults(
            $sdTipo, $sdModel, $sdLabel, $sdValue, $sdOrder, $currentQuery, $sdLimit, true
        );
    }

    public function selectDropdownOption(string $field, mixed $value, string $label): void
    {
        $this->formData[$field]  = $value;
        $this->sdLabels[$field]  = $label;
        $this->sdResults[$field] = [];
        $this->sdSearches[$field] = '';
    }

    public function filterSearchDropdown(string $field, string $query): void
    {
        $col = $this->findColByField($field);

        if (! $col) {
            return;
        }

        $sdModel = $col['colsSDModel'] ?? null;
        $sdLabel = $col['colsSDLabel'] ?? 'name';
        $sdValue = $col['colsSDValor'] ?? 'id';
        $sdOrder = $col['colsSDOrder'] ?? "{$sdLabel} ASC";
        $sdTipo  = $col['colsSDTipo']  ?? 'model';
        $sdLimit = (int) ($col['colsSDLimit'] ?? 15);

        if (! $sdModel) {
            return;
        }

        // Se o usuário apagou o texto, limpa o filtro ativo
        if ($query === '') {
            unset($this->filters[$field], $this->sdFilterLabels[$field]);
            $this->sdResults['filter_' . $field] = [];
            return;
        }

        $this->sdResults['filter_' . $field] = $this->resolveSearchDropdownResults(
            $sdTipo, $sdModel, $sdLabel, $sdValue, $sdOrder, $query, $sdLimit
        );
    }

    /**
     * Carrega os primeiros itens do SD de filtro ao focar no campo.
     */
    public function openFilterDropdown(string $field): void
    {
        if (! empty($this->sdResults['filter_' . $field])) {
            return;
        }

        $col = $this->findColByField($field);

        if (! $col) {
            return;
        }

        $sdModel = $col['colsSDModel'] ?? null;
        $sdLabel = $col['colsSDLabel'] ?? 'name';
        $sdValue = $col['colsSDValor'] ?? 'id';
        $sdOrder = $col['colsSDOrder'] ?? "{$sdLabel} ASC";
        $sdTipo  = $col['colsSDTipo']  ?? 'model';
        $sdLimit = (int) ($col['colsSDLimit'] ?? 15);

        if (! $sdModel) {
            return;
        }

        $this->sdResults['filter_' . $field] = $this->resolveSearchDropdownResults(
            $sdTipo, $sdModel, $sdLabel, $sdValue, $sdOrder, '', $sdLimit, true
        );
    }

    /**
     * Confirma a seleção de um item no SD de filtro.
     * Grava o ID em $filters[$field] e fecha o dropdown.
     */
    public function selectFilterDropdownOption(string $field, mixed $value, string $label): void
    {
        $this->filters[$field]               = $value;
        $this->filterOperators[$field]        = '=';
        $this->sdFilterLabels[$field]         = $label;
        $this->sdResults['filter_' . $field]  = [];
        $this->resetPage();
    }

    /**
     * Limpa a seleção ativa de um SD de filtro.
     */
    public function clearFilterDropdownSelection(string $field): void
    {
        unset($this->filters[$field], $this->filterOperators[$field], $this->sdFilterLabels[$field]);
        $this->sdResults['filter_' . $field] = [];
        $this->resetPage();
    }

    // ── Exportação ────────────────────────────────────────────────────────

    public function export(string $format = 'excel'): void
    {
        $exportConfig = $this->crudConfig['exportConfig'] ?? [];

        if (empty($exportConfig['enabled'])) {
            return;
        }

        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return;
        }

        $query         = $modelInstance->newQuery();
        $activeFilters = $this->buildActiveFilters();

        if (! empty($activeFilters)) {
            $this->filterService->applyFilters($query, $activeFilters);
        }

        $count = $query->count();
        $async = $count > (int) ($exportConfig['asyncThreshold'] ?? 1000);

        if ($async) {
            $this->dispatchExportJob($format, $exportConfig);
            $this->exportStatus = 'Em processamento... você receberá uma notificação.';
        } else {
            // Exportação síncrona básica via download
            $this->dispatch('ptah:export-sync', [
                'model'   => $this->model,
                'format'  => $format,
                'filters' => $this->filters,
            ]);
            $this->exportStatus = '';
        }

        $this->showExportMenu = false;
    }

    // ── Preferências V2 ──────────────────────────────────────────────────

    public function savePreferences(): void
    {
        $prefs = [
            '_version'      => '2.1.0',
            '_lastModified' => now()->toIso8601String(),
            'company'       => $this->companyFilter ?: session('company_id', 1),
            'table'         => [
                'orderBy'     => $this->sort,
                'direction'   => $this->direction,
                'perPage'     => $this->perPage,
                'columns'     => $this->columnOrder,
                'currentPage' => 1,
            ],
            'filters'       => [
                'lastUsed'            => array_filter($this->filters),
                'operators'           => $this->filterOperators,
                'dateRanges'          => array_filter($this->dateRanges),
                'dateRangeOperators'  => $this->dateRangeOperators,
                'saved'               => $this->savedFilters,
                'customFilter'        => [],
                'quickDate'           => $this->quickDateFilter,
                'quickDateColumn'     => $this->quickDateColumn,
                'search'              => $this->search,
                'sdLabels'            => $this->sdLabels,
                'sdFilterLabels'      => $this->sdFilterLabels,
            ],
            'columns'       => $this->formDataColumns,
            'columnWidths'  => $this->columnWidths,
            'columnOrder'   => $this->columnOrder,
            'viewMode'      => $this->viewMode,
            'viewDensity'   => $this->viewDensity,
            'searchHistory' => array_slice($this->searchHistory, 0, 20),
            'advancedSearch'=> [
                'active' => $this->advancedSearchActive,
                'fields' => $this->advancedSearchFields,
            ],
            'ui'            => null,
            'export'        => null,
        ];

        $userId = Auth::id();

        if ($userId) {
            UserPreference::set(
                userId: $userId,
                key:    'crud.' . $this->model,
                value:  $prefs,
                group:  'crud',
            );
            $this->cacheService->forgetPreferences($userId, $this->model);
        } else {
            // Fallback: persiste na session quando não há usuário autenticado
            session(['ptah.crud.' . $this->model => $prefs]);
        }
    }

    protected function loadPreferences(): void
    {
        $userId = Auth::id();

        if ($userId) {
            $prefs = UserPreference::get($userId, 'crud.' . $this->model, null);
        } else {
            // Fallback: carrega da session quando não há usuário autenticado
            $prefs = session('ptah.crud.' . $this->model, null);
        }

        if (! $prefs || ! is_array($prefs)) {
            $this->applyDefaultUiPreferences();
            return;
        }

        // Tabela
        $table = $prefs['table'] ?? [];
        $this->sort        = $table['orderBy']  ?? 'id';
        $this->direction   = $table['direction'] ?? 'DESC';
        $this->perPage     = (int) ($table['perPage']   ?? config('ptah.crud.per_page', 25));

        // Colunas
        $this->columnOrder        = $prefs['columnOrder'] ?? [];
        $this->columnWidths       = $prefs['columnWidths'] ?? [];
        $this->formDataColumns    = $prefs['columns'] ?? $this->formDataColumns;
        $this->viewMode           = $prefs['viewMode']    ?? 'table';
        $this->viewDensity        = $prefs['viewDensity'] ?? 'comfortable';

        // Filtros
        $filterPrefs                  = $prefs['filters']     ?? [];
        $this->filters                = $filterPrefs['lastUsed']             ?? [];
        $this->filterOperators        = $filterPrefs['operators']            ?? [];
        $this->dateRanges             = $filterPrefs['dateRanges']           ?? [];
        $this->dateRangeOperators     = $filterPrefs['dateRangeOperators']   ?? [];
        $this->savedFilters           = $filterPrefs['saved']               ?? [];
        $this->quickDateFilter        = $filterPrefs['quickDate']            ?? '';
        $this->quickDateColumn        = $filterPrefs['quickDateColumn']      ?? ($this->crudConfig['quickDateColumn'] ?? 'created_at');
        $this->search                 = $filterPrefs['search']               ?? '';
        $this->sdLabels               = $filterPrefs['sdLabels']             ?? [];
        $this->sdFilterLabels         = $filterPrefs['sdFilterLabels']      ?? [];

        // Busca avançada
        $advPrefs                  = $prefs['advancedSearch'] ?? [];
        $this->advancedSearchActive = (bool) ($advPrefs['active'] ?? false);
        $this->advancedSearchFields = $advPrefs['fields'] ?? [];

        // Histórico
        $this->searchHistory = $prefs['searchHistory'] ?? [];

        // Reconstrói texto de resumo dos filtros ativos
        $this->buildTextFilter();

        // Recalcula hidden columns
        $this->updateHiddenColumnsCount();
    }

    protected function applyDefaultUiPreferences(): void
    {
        $ui = $this->crudConfig['uiPreferences'] ?? [];
        $this->viewDensity = ! empty($ui['compactMode']) ? 'compact' : 'comfortable';
        $this->perPage     = (int) ($ui['perPage'] ?? config('ptah.crud.per_page', 25));
    }

    /**
     * Recarrega a configuração do CRUD após salvar no modal de configuração.
     */
    #[On('ptah:crud-config-updated')]
    public function reloadCrudConfig(): void
    {
        // Invalida o cache para forçar re-leitura do banco
        $this->configService->forget($this->model);

        // Recarrega a config atualizada
        $config = $this->configService->find($this->model);

        if ($config) {
            $this->crudConfig = $config->config;
        }

        // Atualiza visibilidade de colunas para refletir mudanças
        $this->initFormDataColumns();
    }


    public function saveNamedFilter(string $name): void
    {
        if ($name === '') {
            return;
        }

        $this->savedFilters[$name] = array_filter($this->filters);
        $this->savingFilterName    = null;
        $this->savePreferences();
    }

    public function loadNamedFilter(string $name): void
    {
        if (isset($this->savedFilters[$name])) {
            $this->filters = $this->savedFilters[$name];
            $this->resetPage();
        }
    }

    public function deleteNamedFilter(string $name): void
    {
        unset($this->savedFilters[$name]);
        $this->savePreferences();
    }

    // ── Visibilidade de colunas ──────────────────────────────────────────────

    /**
     * Inicializa o mapa de visibilidade com base nas colunas do CrudConfig.
     * Respeita preferências já carregadas em $formDataColumns.
     */
    protected function initFormDataColumns(): void
    {
        $defaults = [];

        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            $field = $col['colsNomeFisico'] ?? null;
            if ($field) {
                $defaults[$field] = true; // visível por padrão
            }
        }

        // Mescla com preferências salvas (preserva escolhas do usuário)
        $this->formDataColumns    = array_merge($defaults, $this->formDataColumns);
        $this->updateHiddenColumnsCount();
    }

    public function updateColumns(): void
    {
        $this->updateHiddenColumnsCount();
        $this->savePreferences();
    }

    public function showAllColumns(): void
    {
        foreach ($this->formDataColumns as $field => $_) {
            $this->formDataColumns[$field] = true;
        }
        $this->hiddenColumnsCount = 0;
        $this->savePreferences();
    }

    public function hideAllColumns(): void
    {
        foreach ($this->formDataColumns as $field => $_) {
            $this->formDataColumns[$field] = false;
        }

        $this->updateHiddenColumnsCount();
        $this->savePreferences();
    }

    public function resetColumnsToDefault(): void
    {
        foreach ($this->formDataColumns as $field => $_) {
            $this->formDataColumns[$field] = true;
        }
        $this->hiddenColumnsCount = 0;
        $this->savePreferences();
    }

    protected function updateHiddenColumnsCount(): void
    {
        $this->hiddenColumnsCount = (int) count(
            array_filter($this->formDataColumns, fn($v) => ! $v)
        );
    }

    /**
     * Retorna as colunas visíveis aplicando formDataColumns.
     */
    public function getVisibleColumns(): array
    {
        $cols = $this->crudConfig['cols'] ?? [];

        // ── 1. Filtrar por visibilidade ──────────────────────────────────────
        if (! empty($this->formDataColumns)) {
            $cols = array_values(array_filter($cols, function ($col) {
                $field = $col['colsNomeFisico'] ?? '';
                return $this->formDataColumns[$field] ?? true;
            }));
        }

        // ── 2. Aplicar ordem salva pelo usuário (só colunas não-action) ──────
        if (! empty($this->columnOrder)) {
            $actionCols = array_values(array_filter($cols, fn($c) => ($c['colsTipo'] ?? '') === 'action'));
            $dataCols   = array_values(array_filter($cols, fn($c) => ($c['colsTipo'] ?? '') !== 'action'));

            // Mapa campo => definição
            $colMap = [];
            foreach ($dataCols as $col) {
                $colMap[$col['colsNomeFisico'] ?? ''] = $col;
            }

            // Ordena conforme o array salvo; cols sem referência vão ao final
            $ordered = [];
            foreach ($this->columnOrder as $field) {
                if (isset($colMap[$field])) {
                    $ordered[] = $colMap[$field];
                    unset($colMap[$field]);
                }
            }
            $ordered = array_merge($ordered, array_values($colMap));
            $cols    = array_merge($ordered, $actionCols);
        }

        return $cols;
    }

    // ── Resumo visual de filtros ─────────────────────────────────────────────

    /**
     * Constrói o array de badges para exibição dos filtros ativos.
     * Chamado após alteração em filters/dateRanges/quickDateFilter.
     */
    public function buildTextFilter(): void
    {
        $badges = [];
        $cols   = $this->crudConfig['cols'] ?? [];

        // Mapa campo => rótulo
        $labelMap = [];
        foreach ($cols as $col) {
            $labelMap[$col['colsNomeFisico'] ?? ''] = $col['colsNomeLogico'] ?? $col['colsNomeFisico'] ?? '';
        }

        foreach ($this->filters as $field => $value) {
            if ($value === null || $value === '') continue;
            $label = $labelMap[$field] ?? $field;
            $badges[] = ['label' => $label, 'field' => $field, 'value' => $value];
        }

        foreach ($this->dateRanges as $key => $value) {
            if ($value === null || $value === '') continue;

            // Determina o campo base (_start/_end/_from/_to)
            $field = preg_replace('/_(start|end|from|to)$/', '', $key);
            $label = $labelMap[$field] ?? $field;
            $suffix = str_ends_with($key, '_start') || str_ends_with($key, '_from') ? 'de' : 'até';
            $badges[] = ['label' => "{$label} {$suffix}", 'field' => $key, 'value' => $value];
        }

        if ($this->quickDateFilter !== '') {
            $labels = [
                'today'     => 'Hoje',
                'yesterday' => 'Ontem',
                'last7'     => 'Últimos 7 dias',
                'last30'    => 'Últimos 30 dias',
                'week'      => 'Esta semana',
                'month'     => 'Este mês',
                'lastMonth' => 'Mês passado',
                'quarter'   => 'Este trimestre',
                'year'      => 'Este ano',
            ];
            $badges[] = ['label' => 'Período', 'field' => 'quickDate', 'value' => $labels[$this->quickDateFilter] ?? $this->quickDateFilter];
        }

        $this->textFilter = $badges;
    }

    public function removeTextFilterBadge(string $field): void
    {
        // Verifica se é um date range key
        if (preg_match('/_(start|end|from|to)$/', $field)) {
            unset($this->dateRanges[$field]);
        } elseif ($field === 'quickDate') {
            $this->quickDateFilter = '';
        } else {
            unset($this->filters[$field]);
        }

        $this->buildTextFilter();
        $this->resetPage();
    }

    // ── Bulk Actions ─────────────────────────────────────────────────────────

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;

        if ($this->selectAll) {
            $this->selectedRows = $this->rows->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedRows = [];
        }
    }

    public function toggleSelectRow(int|string $id): void
    {
        $idStr = (string) $id;

        if (in_array($idStr, $this->selectedRows, true)) {
            $this->selectedRows = array_values(array_filter(
                $this->selectedRows,
                fn($r) => $r !== $idStr
            ));
            $this->selectAll = false;
        } else {
            $this->selectedRows[] = $idStr;
        }
    }

    public function bulkDelete(): void
    {
        if (empty($this->selectedRows) || $this->bulkActionInProgress) {
            return;
        }

        $this->bulkActionInProgress = true;
        $modelInstance = $this->resolveEloquentModel();

        if ($modelInstance) {
            $modelInstance->newQuery()->whereIn('id', $this->selectedRows)->delete();
            $this->cacheService->invalidateModel($this->model);
            $this->updateTrashedCount();
        }

        $deletedCount               = count($this->selectedRows);
        $this->selectedRows         = [];
        $this->selectAll            = false;
        $this->bulkActionInProgress = false;

        $this->dispatch('crud-bulk-deleted', model: $this->model, count: $deletedCount);
    }

    public function bulkExport(string $format = 'excel'): void
    {
        if (empty($this->selectedRows)) {
            return;
        }

        $this->dispatch('ptah:bulk-export', [
            'model'   => $this->model,
            'ids'     => $this->selectedRows,
            'format'  => $format,
        ]);
    }

    /**
     * Executa uma ação bulk personalizada do config.
     * Configuração: "bulkActions": [{"label": "Aprovar", "action": "aprovar", "method": "App\\Services\\ProductService@bulkAprovar"}]
     */
    public function executeBulkAction(string $action): void
    {
        if (empty($this->selectedRows) || $this->bulkActionInProgress) {
            return;
        }

        $bulkActions = $this->crudConfig['bulkActions'] ?? [];
        $config      = null;

        foreach ($bulkActions as $ba) {
            if (($ba['action'] ?? '') === $action) {
                $config = $ba;
                break;
            }
        }

        if (! $config) {
            return;
        }

        $this->bulkActionInProgress = true;

        // Dispara evento para o host tratar ou chama método via service
        $methodStr = $config['method'] ?? null;

        if ($methodStr && str_contains($methodStr, '@')) {
            [$class, $method] = explode('@', $methodStr, 2);

            try {
                if (class_exists($class) && method_exists($class, $method)) {
                    app($class)->{$method}($this->selectedRows, $this->model);
                }
            } catch (\Throwable $e) {
                Log::error('Ptah bulk action failed', ['action' => $action, 'error' => $e->getMessage()]);
            }
        }

        $this->dispatch('crud-bulk-action', model: $this->model, action: $action, ids: $this->selectedRows);

        $this->selectedRows         = [];
        $this->selectAll            = false;
        $this->bulkActionInProgress = false;
        $this->cacheService->invalidateModel($this->model);
    }

    // ── Filtros rápidos de data ─────────────────────────────────────────────

    public function applyQuickDateFilter(string $period): void
    {
        $this->quickDateFilter = ($this->quickDateFilter === $period) ? '' : $period;
        $this->resetPage();
        $this->buildTextFilter();
        $this->savePreferences();
    }

    public function updatedQuickDateFilter(): void
    {
        $this->resetPage();
        $this->buildTextFilter();
    }

    /**
     * Retorna [from, to] de datas para o período selecionado.
     * @return array{0: string, 1: string}
     */
    protected function getQuickDateRange(string $period): array
    {
        $now  = Carbon::now();
        $copy = $now->copy();

        return match ($period) {
            'today'     => [$now->startOfDay()->toDateTimeString(),                  $copy->endOfDay()->toDateTimeString()],
            'yesterday' => [$now->subDay()->startOfDay()->toDateTimeString(),         $now->copy()->endOfDay()->toDateTimeString()],
            'last7'     => [$now->subDays(7)->startOfDay()->toDateTimeString(),       $copy->endOfDay()->toDateTimeString()],
            'last30'    => [$now->subDays(30)->startOfDay()->toDateTimeString(),      $copy->endOfDay()->toDateTimeString()],
            'week'      => [$now->startOfWeek()->toDateTimeString(),                  $copy->endOfWeek()->toDateTimeString()],
            'month'     => [$now->startOfMonth()->toDateTimeString(),                 $copy->endOfMonth()->toDateTimeString()],
            'lastMonth' => [$now->subMonth()->startOfMonth()->toDateTimeString(),     $now->copy()->endOfMonth()->toDateTimeString()],
            'quarter'   => [$now->startOfQuarter()->toDateTimeString(),               $copy->endOfQuarter()->toDateTimeString()],
            'year'      => [$now->startOfYear()->toDateTimeString(),                  $copy->endOfYear()->toDateTimeString()],
            default     => ['', ''],
        };
    }

    // ── Contagem de registros deletados ─────────────────────────────────────

    public function updateTrashedCount(): void
    {
        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return;
        }

        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelInstance));

        if (! $usesSoftDeletes) {
            $this->trashedCount = 0;
            return;
        }

        try {
            $this->trashedCount = (int) $modelInstance->newQuery()->onlyTrashed()->count();
        } catch (\Throwable) {
            $this->trashedCount = 0;
        }
    }

    // ── Busca avançada ───────────────────────────────────────────────────────

    public function toggleAdvancedSearch(): void
    {
        $this->advancedSearchActive = ! $this->advancedSearchActive;

        if (! $this->advancedSearchActive) {
            $this->advancedSearchFields = [];
        }

        $this->savePreferences();
    }

    public function addAdvancedSearchField(string $field, string $operator, mixed $value, string $logic = 'AND'): void
    {
        $this->advancedSearchFields[] = compact('field', 'operator', 'value', 'logic');
        $this->resetPage();
        $this->buildTextFilter();
    }

    public function removeAdvancedSearchField(int $index): void
    {
        array_splice($this->advancedSearchFields, $index, 1);
        $this->resetPage();
        $this->buildTextFilter();
    }

    // ── Histórico de busca ───────────────────────────────────────────────────

    protected function addToSearchHistory(string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        // Remove duplicatas e coloca no início
        $this->searchHistory = array_values(array_filter(
            $this->searchHistory,
            fn($t) => $t !== $term
        ));

        array_unshift($this->searchHistory, $term);

        // Limita a 10 entradas
        $this->searchHistory = array_slice($this->searchHistory, 0, 10);
    }

    public function clearSearchHistory(): void
    {
        $this->searchHistory = [];
        $this->savePreferences();
    }

    // ── Sort por relação ─────────────────────────────────────────────────────

    /**
     * Detecta se a coluna de sort é uma FK que pode ser resolvida via JOIN.
     *
     * @return array{0: string, 1: string, 2: string, 3: string}|null
     *         [relationName, displayColumn, foreignKey, tableName]
     */
    protected function getOrderByRelationInfo(string $column): ?array
    {
        $col = $this->findColByField($column);

        if (! $col) {
            return null;
        }

        $rel   = $col['colsRelacao']      ?? null;
        $exibe = $col['colsRelacaoExibe'] ?? null;
        $fk    = $col['colsNomeFisico']   ?? null; // ex: category_id

        if (! $rel || ! $exibe || ! $fk) {
            return null;
        }

        // Precisa que o FK seja a coluna sendo sortada
        if ($fk !== $column) {
            return null;
        }

        $tableName = $this->relationToTableName($rel);

        return [$rel, $exibe, $fk, $tableName];
    }

    /**
     * Converte nome de relação camelCase para nome de tabela snake_plural.
     * Ex: "businessPartner" → "business_partners"
     */
    protected function relationToTableName(string $relation): string
    {
        // Resolve a relação no model para obter a tabela real
        try {
            $modelInstance = $this->resolveEloquentModel();

            if ($modelInstance && method_exists($modelInstance, $relation)) {
                $relInstance = $modelInstance->{$relation}();
                return $relInstance->getRelated()->getTable();
            }
        } catch (\Throwable) {
            // Fallback para conversão automática
        }

        return \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($relation));
    }

    // ── Permissões ───────────────────────────────────────────────────────────

    /**
     * Retorna o identificador padrão de permissão para a tela.
     * Ex: "products.index", "purchase.orders.index"
     */
    public function getDefaultPermissionIdentifier(): string
    {
        $model = str_replace(['/', '\\'], '.', strtolower($this->model));
        return $model . '.index';
    }



    /**
     * Formata o valor de uma célula de acordo com a config da coluna.
     * Aplica colsRenderer DSL, colsRelacaoNested (dot notation), colsRelacao/colsRelacaoExibe,
     * colsHelper (legado), colsMetodoCustom e select map.
     */
    public function formatCell(array $col, mixed $row): string
    {
        $value = $this->getCellValue($col, $row);

        // colsMetodoCustom tem prioridade máxima
        if (! empty($col['colsMetodoCustom'])) {
            $result = $this->resolveCustomMethod($col['colsMetodoCustom'], $row, $value);
            // colsMetodoRaw: true → retorna HTML bruto (opt-in explícito, confia no desenvolvedor)
            return ($col['colsMetodoRaw'] ?? false) ? $result : e($result);
        }

        // Nested dot notation: "address.city.name" → data_get($row, 'address.city.name')
        if (! empty($col['colsRelacaoNested'])) {
            $value = $this->resolveNestedValue($row, $col['colsRelacaoNested']);
        } elseif (! empty($col['colsRelacao']) && ! empty($col['colsRelacaoExibe'])) {
            $rel   = $col['colsRelacao'];
            $exibe = $col['colsRelacaoExibe'];
            $value = $row->{$rel}?->{$exibe} ?? $value;
        }

        // Select: converte valor para label mapeado
        if (($col['colsTipo'] ?? '') === 'select' && ! empty($col['colsSelect'])) {
            $flip  = array_flip($col['colsSelect']);
            $value = $flip[(string) $value] ?? $value;
        }

        $rendered = $this->applyCellRenderer($col, $value, $row);

        // Wrapper de ícone e estilo configurável por coluna
        $cellIcon  = ! empty($col['colsCellIcon'])  ? '<span class="' . e($col['colsCellIcon']) . ' mr-1"></span>' : '';
        $cellStyle = ! empty($col['colsCellStyle']) ? ' style="' . e($col['colsCellStyle']) . '"' : '';
        $cellClass = ! empty($col['colsCellClass']) ? ' ' . e($col['colsCellClass']) : '';

        if ($cellIcon || $cellStyle || $cellClass) {
            return "<span{$cellStyle} class=\"inline-flex items-center{$cellClass}\">{$cellIcon}{$rendered}</span>";
        }

        return $rendered;
    }

    /**
     * Retorna o style inline de uma linha baseado em contitionStyles.
     */
    public function getRowStyle(mixed $row): string
    {
        $styles = $this->crudConfig['contitionStyles'] ?? [];

        foreach ($styles as $style) {
            $field     = $style['field']           ?? $style['colsNomeFisico'] ?? null;
            $condition = $style['condition']        ?? '==';
            $target    = $style['value']            ?? null;
            $css       = $style['style']            ?? '';

            if (! $field) {
                continue;
            }

            $rowValue = $row instanceof Model
                ? $row->getAttribute($field)
                : ($row[$field] ?? null);

            // Campo não existe no model — ignora silenciosamente
            if ($rowValue === null && $row instanceof Model && ! array_key_exists($field, $row->getAttributes())) {
                continue;
            }

            $match = match ($condition) {
                '==' => (string) $rowValue == (string) $target,
                '!=' => (string) $rowValue != (string) $target,
                '>'  => (float)  $rowValue >  (float)  $target,
                '<'  => (float)  $rowValue <  (float)  $target,
                '>=' => (float)  $rowValue >= (float)  $target,
                '<=' => (float)  $rowValue <= (float)  $target,
                default => false,
            };

            if ($match) {
                return $css;
            }
        }

        return '';
    }

    // ── Helpers internos ─────────────────────────────────────────────────

    protected function resolveEloquentModel(): ?Model
    {
        if ($this->eloquentModel) {
            return $this->eloquentModel;
        }

        $modelName = $this->crudConfig['crud'] ?? $this->model;

        // Converte "Purchase/Order/PurchaseOrders" para namespace
        $class = str_replace('/', '\\', $modelName);

        // Tenta prefixos conhecidos
        $candidates = [
            $class,
            'App\\Models\\' . $class,
            app()->getNamespace() . 'Models\\' . $class,
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                $this->eloquentModel = app($candidate);
                return $this->eloquentModel;
            }
        }

        return null;
    }

    protected function getVisibleRelations(): array
    {
        $relations = [];

        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            $rel = $col['colsRelacao'] ?? null;
            if ($rel && $rel !== '') {
                $relations[] = $rel;
            }

            // Nested dot notation — eager load todos os segmentos exceto o último (que é o campo)
            // Ex: "address.city.name" → eager load "address.city"
            // Ex: "supplier.name"     → eager load "supplier"
            $nested = $col['colsRelacaoNested'] ?? null;
            if ($nested && $nested !== '') {
                $parts = explode('.', $nested);
                if (count($parts) > 1) {
                    $relations[] = implode('.', array_slice($parts, 0, count($parts) - 1));
                }
            }
        }

        return array_unique($relations);
    }

    protected function getSearchableFields(): array
    {
        $fields = [];

        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            $tipo = $col['colsTipo'] ?? 'text';
            if ($tipo === 'text' && empty($col['colsRelacao'])) {
                $fields[] = $col['colsNomeFisico'];
            }
        }

        return array_unique($fields);
    }

    protected function buildActiveFilters(): array
    {
        $domainFilters = [];

        foreach ($this->filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // Verifica se é filtro customizado (tratado separadamente)
            $isCustom = false;

            foreach ($this->crudConfig['customFilters'] ?? [] as $cf) {
                if (($cf['field'] ?? null) === $field) {
                    $isCustom = true;
                    break;
                }
            }

            if ($isCustom) {
                continue;
            }

            // Operador definido pelo usuário, ou auto-detectado
            $explicitOp = $this->filterOperators[$field] ?? null;

            // Verifica se filtra via relação
            $col = $this->findColByField($field);

            if ($col && ! empty($col['colsRelacao']) && ! empty($col['colsRelacaoExibe'])) {
                $domainFilters[] = new FilterDTO(
                    field:    $col['colsNomeFisico'],
                    value:    $value,
                    operator: $explicitOp ?? '=',
                    type:     'text',
                );
            } else {
                $autoOp = (is_string($value) && strlen($value) > 1 && FilterDTO::inferType($field, $value) === 'text')
                    ? 'LIKE'
                    : '=';
                $domainFilters[] = new FilterDTO(
                    field:    $field,
                    value:    $value,
                    operator: $explicitOp ?? $autoOp,
                    type:     FilterDTO::inferType($field, $value),
                );
            }
        }

        // Busca avançada (campos adicionados pelo usuário)
        if ($this->advancedSearchActive && ! empty($this->advancedSearchFields)) {
            foreach ($this->advancedSearchFields as $asf) {
                $field    = $asf['field']    ?? null;
                $operator = $asf['operator'] ?? '=';
                $value    = $asf['value']    ?? null;
                $logic    = $asf['logic']    ?? 'AND';

                if (! $field || $value === null || $value === '') {
                    continue;
                }

                $col = $this->findColByField($field);
                $domainFilters[] = new FilterDTO(
                    field:    $field,
                    value:    $value,
                    operator: $operator,
                    type:     FilterDTO::inferType($field, $value),
                    options:  ['logic' => $logic],
                );
            }
        }

        return $domainFilters;
    }

    protected function resolveSortColumn(): string
    {
        $col = $this->findColByField($this->sort);

        if ($col && ! empty($col['colsOrderBy'])) {
            return $col['colsOrderBy'];
        }

        return $this->sort;
    }

    protected function getFormCols(): array
    {
        return array_values(
            array_filter(
                $this->crudConfig['cols'] ?? [],
                fn($c) => $this->ptahBool($c['colsGravar'] ?? false)
            )
        );
    }

    protected function findColByField(string $field): ?array
    {
        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            if (($col['colsNomeFisico'] ?? null) === $field) {
                return $col;
            }
        }

        return null;
    }

    protected function getCellValue(array $col, mixed $row): mixed
    {
        $field = $col['colsNomeFisico'] ?? '';

        if ($row instanceof Model) {
            return $row->getAttribute($field);
        }

        return $row[$field] ?? null;
    }

    protected function applyHelper(string $helper, mixed $value): mixed
    {
        return match ($helper) {
            'dateFormat'     => $this->helperDateFormat($value),
            'dateTimeFormat' => $this->helperDateTimeFormat($value),
            'currencyFormat' => $this->helperCurrencyFormat($value),
            'yesOrNot'       => $this->helperYesOrNot($value),
            'flagChannel'    => $this->helperFlagChannel($value),
            default          => $value,
        };
    }

    protected function helperDateFormat(mixed $value): string
    {
        if (! $value) return '';
        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    protected function helperDateTimeFormat(mixed $value): string
    {
        if (! $value) return '';
        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    protected function helperCurrencyFormat(mixed $value): string
    {
        if ($value === null || $value === '') return '';
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    protected function helperYesOrNot(mixed $value): string
    {
        return in_array($value, [1, '1', 'S', 'true', true], true) ? 'Sim' : 'Não';
    }

    protected function helperFlagChannel(mixed $value): string
    {
        return match (strtoupper((string) $value)) {
            'G' => '<span class="badge" style="background:#28a745">Verde</span>',
            'Y' => '<span class="badge" style="background:#ffc107;color:#000">Amarelo</span>',
            'R' => '<span class="badge" style="background:#dc3545">Vermelho</span>',
            default => (string) $value,
        };
    }

    // ── Renderer DSL ─────────────────────────────────────────────────────

    /**
     * Aplica o renderer configurado na coluna (DSL).
     * Rota para os métodos específicos conforme colsRenderer.
     * Mantém compatibilidade com colsHelper legado.
     */
    protected function applyCellRenderer(array $col, mixed $value, mixed $row): string
    {
        $renderer = $col['colsRenderer'] ?? null;

        // Compat legado: mapeia colsHelper para renderer
        if (! $renderer && ! empty($col['colsHelper'])) {
            $renderer = match ($col['colsHelper']) {
                'dateFormat'     => 'date',
                'dateTimeFormat' => 'datetime',
                'currencyFormat' => 'money',
                'yesOrNot'       => 'boolean',
                'flagChannel'    => 'badge',
                default          => null,
            };

            // Para badge via compat, usa a lógica de flagChannel
            if ($renderer === 'badge' && $col['colsHelper'] === 'flagChannel') {
                return $this->helperFlagChannel($value);
            }
        }

        if (! $renderer) {
            return e((string) ($value ?? ''));
        }

        return match ($renderer) {
            'badge'                  => $this->renderBadge($col, $value),
            'pill'                   => $this->renderPill($col, $value),
            'boolean'                => $this->renderBoolean($col, $value),
            'money'                  => $this->renderMoney($col, $value),
            'date'                   => $this->helperDateFormat($value),
            'datetime'               => $this->helperDateTimeFormat($value),
            'link'                   => $this->renderLink($col, $value, $row),
            'image'                  => $this->renderImage($col, $value),
            'truncate'               => $this->renderTruncate($col, $value),
            'number'                 => $this->renderNumber($col, $value),
            'progress'               => $this->renderProgress($col, $value),
            'rating'                 => $this->renderRating($col, $value),
            'color'     => $this->renderColor($value),
            'code'                   => $this->renderCode($value),
            'filesize'               => $this->renderFilesize($value),
            'duration'               => $this->renderDuration($col, $value),
            'qrcode'                 => $this->renderQrcode($col, $value),
            default                  => e((string) ($value ?? '')),
        };
    }

    /**
     * Renderiza um badge colorido baseado em mapeamento de valores.
     * Config: colsRendererBadges => [{value, label, color, icon?}]
     */
    protected function renderBadge(array $col, mixed $value): string
    {
        $badges   = $col['colsRendererBadges'] ?? [];
        $valueStr = strtolower((string) ($value ?? ''));

        foreach ($badges as $badge) {
            if (strtolower((string) ($badge['value'] ?? '')) === $valueStr) {
                $label    = e($badge['label'] ?? $value);
                $colorVal = $badge['color'] ?? 'gray';
                $icon     = ! empty($badge['icon'])
                    ? '<span class="' . e($badge['icon']) . ' mr-1 text-[10px]"></span>'
                    : '';

                if (str_starts_with($colorVal, '#')) {
                    $hex = e($colorVal);
                    return "<span class=\"inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium\" style=\"background-color:{$hex}22;color:{$hex};border:1px solid {$hex}55\">{$icon}{$label}</span>";
                }

                $color = match (strtolower($colorVal)) {
                    'green', 'success'   => 'bg-green-100 text-green-800',
                    'yellow', 'warning'  => 'bg-yellow-100 text-yellow-800',
                    'red', 'danger'      => 'bg-red-100 text-red-800',
                    'blue', 'info'       => 'bg-blue-100 text-blue-800',
                    'indigo', 'primary'  => 'bg-indigo-100 text-indigo-800',
                    'purple'             => 'bg-purple-100 text-purple-800',
                    'pink'               => 'bg-pink-100 text-pink-800',
                    default              => 'bg-gray-100 text-gray-700',
                };
                return "<span class=\"inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {$color}\">{$icon}{$label}</span>";
            }
        }

        // Fallback quando nenhum badge faz match
        return '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700">' . e((string) ($value ?? '')) . '</span>';
    }

    /**
     * Renderiza um pill (badge arredondado) baseado em mapeamento de valores.
     */
    protected function renderPill(array $col, mixed $value): string
    {
        $badges   = $col['colsRendererBadges'] ?? [];
        $valueStr = strtolower((string) ($value ?? ''));

        foreach ($badges as $badge) {
            if (strtolower((string) ($badge['value'] ?? '')) === $valueStr) {
                $label    = e($badge['label'] ?? $value);
                $colorVal = $badge['color'] ?? 'gray';
                $icon     = ! empty($badge['icon'])
                    ? '<span class="' . e($badge['icon']) . ' mr-1 text-[10px]"></span>'
                    : '';

                if (str_starts_with($colorVal, '#')) {
                    $hex = e($colorVal);
                    return "<span class=\"inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold\" style=\"background-color:{$hex}22;color:{$hex};border:1px solid {$hex}55\">{$icon}{$label}</span>";
                }

                $color = match (strtolower($colorVal)) {
                    'green', 'success'   => 'bg-green-100 text-green-800',
                    'yellow', 'warning'  => 'bg-yellow-100 text-yellow-800',
                    'red', 'danger'      => 'bg-red-100 text-red-800',
                    'blue', 'info'       => 'bg-blue-100 text-blue-800',
                    'indigo', 'primary'  => 'bg-indigo-100 text-indigo-800',
                    'purple'             => 'bg-purple-100 text-purple-800',
                    default              => 'bg-gray-100 text-gray-700',
                };
                return "<span class=\"inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {$color}\">{$icon}{$label}</span>";
            }
        }

        return '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700">' . e((string) ($value ?? '')) . '</span>';
    }

    /**
     * Renderiza booleano como badge Sim/Não.
     * Config: colsRendererBoolTrue, colsRendererBoolFalse
     */
    protected function renderBoolean(array $col, mixed $value): string
    {
        $isTrue = in_array($value, [1, '1', 'S', 's', 'true', true, 'Y', 'y'], true);

        if ($isTrue) {
            $label = e($col['colsRendererBoolTrue'] ?? 'Sim');
            return "<span class=\"inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800\">{$label}</span>";
        }

        $label = e($col['colsRendererBoolFalse'] ?? 'Não');
        return "<span class=\"inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500\">{$label}</span>";
    }

    /**
     * Renderiza valor monetário formatado.
     * Config: colsRendererCurrency (BRL/USD/EUR), colsRendererDecimals
     */
    protected function renderMoney(array $col, mixed $value): string
    {
        if ($value === null || $value === '') return '';

        $currency = $col['colsRendererCurrency'] ?? 'BRL';
        $decimals = (int) ($col['colsRendererDecimals'] ?? 2);

        return match ($currency) {
            'USD'   => '$ '  . number_format((float) $value, $decimals, '.', ','),
            'EUR'   => '€ '  . number_format((float) $value, $decimals, ',', '.'),
            default => 'R$ ' . number_format((float) $value, $decimals, ',', '.'),
        };
    }

    /**
     * Renderiza um link clicável.
     * Config: colsRendererLinkTemplate (/path/%id%), colsRendererLinkLabel, colsRendererLinkNewTab
     * Suporta %fieldName% como placeholder para qualquer campo do registro.
     */
    protected function renderLink(array $col, mixed $value, mixed $row): string
    {
        $template = $col['colsRendererLinkTemplate'] ?? '#';
        $label    = $col['colsRendererLinkLabel']    ?? $value;
        $newTab   = ($col['colsRendererLinkNewTab']  ?? false)
            ? ' target="_blank" rel="noopener noreferrer"'
            : '';

        // Substitui placeholders %campo% por valores do registro
        $url = str_replace('%value%', e((string) $value), $template);

        if ($row instanceof \Illuminate\Database\Eloquent\Model) {
            foreach ($row->getAttributes() as $k => $v) {
                $url = str_replace('%' . $k . '%', e((string) ($v ?? '')), $url);
            }
        }

        return "<a href=\"{$url}\"{$newTab} class=\"text-indigo-600 hover:text-indigo-800 hover:underline font-medium\">" . e((string) $label) . '</a>';
    }

    /**
     * Renderiza imagem miniatura.
     * Config: colsRendererImageWidth, colsRendererImageHeight
     */
    protected function renderImage(array $col, mixed $value): string
    {
        if (! $value) return '';

        $width  = (int) ($col['colsRendererImageWidth']  ?? 40);
        $height = (int) ($col['colsRendererImageHeight'] ?? $width);

        return "<img src=\"" . e((string) $value) . "\" width=\"{$width}\" height=\"{$height}\" class=\"rounded object-cover inline-block\" loading=\"lazy\" />";
    }

    /**
     * Renderiza texto truncado com tooltip no hover.
     * Config: colsRendererMaxChars (default 50)
     */
    protected function renderTruncate(array $col, mixed $value): string
    {
        if ($value === null || $value === '') return '';

        $max = (int) ($col['colsRendererMaxChars'] ?? 50);
        $str = (string) $value;

        if (mb_strlen($str) <= $max) {
            return e($str);
        }

        $truncated = mb_substr($str, 0, $max) . '…';
        return '<span title="' . e($str) . '" class="cursor-help">' . e($truncated) . '</span>';
    }

    /**
     * Número formatado com separadores de milhar e decimais.
     * Config: colsRendererDecimals (padrão 2), colsRendererThousands (padrão 'pt-BR')
     */
    protected function renderNumber(array $col, mixed $value): string
    {
        if ($value === null || $value === '') return '';
        $decimals   = (int) ($col['colsRendererDecimals'] ?? 2);
        $locale     = $col['colsRendererLocale'] ?? 'pt-BR';
        if ($locale === 'pt-BR') {
            return number_format((float) $value, $decimals, ',', '.');
        }
        return number_format((float) $value, $decimals, '.', ',');
    }

    /**
     * Barra de progresso visual (0–100 por padrão).
     * Config: colsRendererMax (padrão 100), colsRendererColor (green|blue|red|yellow|purple|indigo)
     */
    protected function renderProgress(array $col, mixed $value): string
    {
        if ($value === null || $value === '') return '';
        $max      = (float) ($col['colsRendererMax'] ?? 100);
        $pct      = $max > 0 ? min(100, round((float) $value * 100 / $max)) : 0;
        $colorKey = $col['colsRendererColor'] ?? 'indigo';
        $bgBar    = match ($colorKey) {
            'green'  => 'bg-green-500',
            'red'    => 'bg-red-500',
            'yellow' => 'bg-yellow-500',
            'purple' => 'bg-purple-500',
            'blue'   => 'bg-blue-500',
            default  => 'bg-indigo-500',
        };
        return "<div class=\"flex items-center gap-2\">"
            . "<div class=\"flex-1 h-2 bg-gray-200 rounded-full overflow-hidden\">"
            . "<div class=\"{$bgBar} h-full rounded-full\" style=\"width:{$pct}%\"></div>"
            . "</div>"
            . "<span class=\"text-xs text-gray-600 tabular-nums w-9 text-right\">{$pct}%</span>"
            . "</div>";
    }

    /**
     * Avaliação em estrelas (1–5 por padrão).
     * Config: colsRendererMax (padrão 5)
     */
    protected function renderRating(array $col, mixed $value): string
    {
        if ($value === null || $value === '') return '';
        $max   = (int) ($col['colsRendererMax'] ?? 5);
        $score = (float) $value;
        $html  = '<span class="inline-flex items-center gap-0.5" aria-label="' . e($score) . ' de ' . $max . '">';
        for ($i = 1; $i <= $max; $i++) {
            if ($score >= $i) {
                $html .= '<svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
            } elseif ($score >= $i - 0.5) {
                $html .= '<svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0L6.6 15.207c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" clip-path="inset(0 50% 0 0)"/></svg>';
            } else {
                $html .= '<svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
            }
        }
        return $html . '</span>';
    }

    /**
     * Amostra de cor hexadecimal com o código ao lado.
     * Ex: valor "#FF5733" → ■ #FF5733
     * Config: colsRendererColorSize (padrão 16, em px)
     */
    protected function renderColor(mixed $value): string
    {
        if (! $value) return '';
        $hex = e((string) $value);
        return "<span class=\"inline-flex items-center gap-1.5\">"
            . "<span class=\"inline-block rounded border border-gray-300\" style=\"width:16px;height:16px;background:{$hex};flex-shrink:0\"></span>"
            . "<code class=\"text-xs font-mono text-gray-700\">{$hex}</code>"
            . "</span>";
    }

    /**
     * Texto em formato de código monospace.
     */
    protected function renderCode(mixed $value): string
    {
        if ($value === null || $value === '') return '';
        return '<code class="text-xs font-mono bg-gray-100 text-gray-800 px-1.5 py-0.5 rounded border border-gray-200">'
            . e((string) $value) . '</code>';
    }

    /**
     * Tamanho de arquivo humanizado (campo em bytes).
     * Ex: 1536000 → "1,5 MB"
     */
    protected function renderFilesize(mixed $value): string
    {
        if ($value === null || $value === '') return '';
        $bytes = (float) $value;
        if ($bytes < 1024)        return number_format($bytes, 0, ',', '.') . ' B';
        if ($bytes < 1_048_576)   return number_format($bytes / 1_024, 1, ',', '.') . ' KB';
        if ($bytes < 1_073_741_824) return number_format($bytes / 1_048_576, 1, ',', '.') . ' MB';
        return number_format($bytes / 1_073_741_824, 2, ',', '.') . ' GB';
    }

    /**
     * Duração humanizada.
     * Config: colsRendererDurationUnit (minutes|seconds, padrão minutes)
     * Ex: 95 minutos → "1h 35min"
     */
    protected function renderDuration(array $col, mixed $value): string
    {
        if ($value === null || $value === '') return '';
        $unit    = $col['colsRendererDurationUnit'] ?? 'minutes';
        $seconds = $unit === 'seconds' ? (int) $value : (int) $value * 60;
        $h       = intdiv($seconds, 3600);
        $m       = intdiv($seconds % 3600, 60);
        $s       = $seconds % 60;
        if ($h > 0 && $unit !== 'seconds') return "{$h}h {$m}min";
        if ($h > 0)  return "{$h}h {$m}min {$s}s";
        if ($m > 0)  return "{$m}min" . ($s > 0 ? " {$s}s" : '');
        return "{$s}s";
    }

    /**
     * QR Code renderizado client-side via qrcode.js (CDN).
     * O QR é gerado inline com <canvas> por Alpine.js ao montar o componente.
     * Config: colsRendererQrSize (padrão 64, em px)
     */
    protected function renderQrcode(array $col, mixed $value): string
    {
        if (! $value) return '';
        $size    = (int) ($col['colsRendererQrSize'] ?? 64);
        $escaped = e((string) $value);
        return "<span x-data x-init=\"\$nextTick(() => { if(window.QRCode) new QRCode(\$el.querySelector('div'), {text:'{$escaped}',width:{$size},height:{$size},colorDark:'#1a1a1a',colorLight:'#fff'}); })\">"
            . "<div title=\"{$escaped}\"></div>"
            . "</span>";
    }

    /**
     * Resolve valor via dot notation usando o helper nativo data_get() do Laravel.
     * Suporta: "address.city.name", "items.0.price", objetos, arrays, null-safe.
     */
    protected function resolveNestedValue(mixed $row, string $path): mixed
    {
        return data_get($row, $path);
    }

    /**
     * Aplica transformações de máscara nos dados do formulário antes de persistir no banco.
     * Ex: money_brl → float, CPF/CNPJ → apenas dígitos, uppercase, etc.
     */
    protected function applyMaskTransforms(array $data, array $formCols): array
    {
        foreach ($formCols as $col) {
            $field     = $col['colsNomeFisico'] ?? null;
            $transform = $col['colsMaskTransform'] ?? null;

            if (! $field || ! $transform || ! array_key_exists($field, $data)) {
                continue;
            }

            $val = $data[$field];

            $data[$field] = match ($transform) {
                // "R$ 1.253,08" → 1253.08
                'money_to_float' => (float) str_replace(
                    ['.', ','],
                    ['',  '.'],
                    preg_replace('/[^0-9,]/', '', (string) $val)
                ),
                // "055.465.309-52" → "05546530952"
                'digits_only' => preg_replace('/\D/', '', (string) $val),
                // "ABC-1234" | "ABC1A23" → "ABC1234" / "ABC1A23" (uppercase + somente alfanumérico)
                'plate_clean' => preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $val)),
                // "01/12/2024" → "2024-12-01"
                'date_br_to_iso' => (function () use ($val): string {
                    $d = \DateTime::createFromFormat('d/m/Y', (string) $val);
                    return $d ? $d->format('Y-m-d') : (string) $val;
                })(),
                // "2024-12-01" → "01/12/2024"
                'date_iso_to_br' => (function () use ($val): string {
                    $d = \DateTime::createFromFormat('Y-m-d', (string) $val);
                    return $d ? $d->format('d/m/Y') : (string) $val;
                })(),
                'uppercase'   => mb_strtoupper((string) $val),
                'lowercase'   => mb_strtolower((string) $val),
                'trim'        => trim((string) $val),
                default       => $val,
            };
        }

        return $data;
    }

    /**
     * Resolve o padrão "Namespace\Class\Method(%field1%, %field2%, 'literal')" de colsMetodoCustom.
     *
     * Sintaxe:
     *   "Caminho\Servico\metodo(%campo%)"                        → 1 argumento
     *   "Caminho\Servico\metodo(%campo1%, %campo2%, 'literal')"  → N argumentos, cada um como arg separado
     *
     * O prefixo "App\Services\" é adicionado automaticamente.
     * O retorno é sempre (string) — use colsMetodoRaw: true na ColDef para saída HTML sem escape.
     */
    protected function resolveCustomMethod(string $pattern, mixed $row, mixed $value): string
    {
        if (! preg_match('/^(.+)\\\\(\w+)\((.*)\)$/', $pattern, $m)) {
            return (string) $value;
        }

        $classPath = $m[1];
        $method    = $m[2];
        $paramStr  = trim($m[3]);

        // Resolve cada argumento separado por vírgula (via str_getcsv para respeitar aspas)
        $args = $paramStr !== ''
            ? array_map(function (string $token) use ($row): mixed {
                $token = trim($token);

                // %fieldName% → valor do campo no registro
                if (preg_match('/^%([\w\.]+)%$/', $token, $pm)) {
                    $f = $pm[1];
                    return $row instanceof Model
                        ? ($row->getAttribute($f) ?? data_get($row, $f) ?? '')
                        : ($row[$f] ?? '');
                }

                // String literal entre aspas simples: 'valor' → valor
                if (preg_match("/^'(.*)'$/s", $token, $pm)) {
                    return $pm[1];
                }

                // String literal entre aspas duplas: "valor" → valor
                if (preg_match('/^"(.*)"$/s', $token, $pm)) {
                    return $pm[1];
                }

                // Numérico
                if (is_numeric($token)) {
                    return $token + 0;
                }

                return $token;
            }, str_getcsv($paramStr))
            : [];

        $class = 'App\\Services\\' . str_replace('/', '\\', $classPath);

        try {
            if (class_exists($class) && method_exists($class, $method)) {
                $result = app($class)->{$method}(...$args);
                return (string) $result;
            }
        } catch (\Throwable) {
            // Silencia e retorna valor original
        }

        return e((string) $value);
    }

    /**
     * Resolve resultados para um searchdropdown.
     */
    protected function resolveSearchDropdownResults(
        string $tipo,
        string $sdModel,
        string $sdLabel,
        string $sdValue,
        string $sdOrder,
        string $query,
        int    $limit      = 15,
        bool   $allowEmpty = false
    ): array {
        if (! $allowEmpty && strlen($query) < 1) {
            return [];
        }

        // Normaliza o model (/ → \)
        $modelClass = str_replace('/', '\\', $sdModel);

        try {
            if ($tipo === 'model') {
                // Eloquent direto
                $fullClass = class_exists($modelClass)
                    ? $modelClass
                    : 'App\\Models\\' . $modelClass;

                if (! class_exists($fullClass)) {
                    return [];
                }

                [$orderCol, $orderDir] = array_pad(explode(' ', $sdOrder, 2), 2, 'ASC');

                $q = app($fullClass)
                    ->newQuery()
                    ->orderBy($orderCol, $orderDir)
                    ->limit($limit);

                // Filtro case-insensitive via LOWER() para compatibilidade MySQL/SQLite
                if ($query !== '') {
                    $q->whereRaw('LOWER(' . $sdLabel . ') LIKE ?', ['%' . mb_strtolower($query) . '%']);
                }

                return $q->get()
                    ->map(fn($item) => [
                        'value' => $item->{$sdValue},
                        'label' => $item->{$sdLabel},
                    ])
                    ->toArray();
            }

            if ($tipo === 'service') {
                // Chama método estático/não-estático em um Service
                if (str_contains($modelClass, '\\')) {
                    $parts      = explode('\\', $modelClass);
                    $methodName = array_pop($parts);
                    $class      = implode('\\', $parts);

                    $fullClass = class_exists($class)
                        ? $class
                        : 'App\\Services\\' . $class;

                    if (class_exists($fullClass) && method_exists($fullClass, $methodName)) {
                        $result = app($fullClass)->{$methodName}($query);
                        return is_array($result) ? $result : [];
                    }
                }
            }
        } catch (\Throwable) {
            // Silencia
        }

        return [];
    }

    protected function preloadSdLabels(Model $record): void
    {
        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            if (($col['colsTipo'] ?? '') !== 'searchdropdown') {
                continue;
            }

            $field = $col['colsNomeFisico'] ?? '';
            $rel   = $col['colsRelacao']      ?? null;
            $exibe = $col['colsRelacaoExibe'] ?? null;

            if ($rel && $exibe && $record->{$rel}) {
                $this->sdLabels[$field] = $record->{$rel}->{$exibe} ?? '';
            }
        }
    }

    protected function dispatchExportJob(string $format, array $exportConfig): void
    {
        // Despacha via queue se o job existir
        $jobClass = 'Ptah\\Jobs\\BaseCrudExportJob';

        if (class_exists($jobClass)) {
            dispatch(new $jobClass(
                model:   $this->model,
                filters: $this->filters,
                format:  $format,
                userId:  Auth::id(),
                config:  $exportConfig,
            ));
        }
    }

    // ── Listeners (Echo / Broadcast) ─────────────────────────────────

    public function getListeners(): array
    {
        $base = ['refreshData' => '$refresh'];

        $bc = $this->crudConfig['broadcast'] ?? [];
        if (! empty($bc['enabled'])) {
            $baseName = class_basename(str_replace('/', '\\', $this->model));
            // canal: page-product-observer  (kebab)
            $channel  = $bc['channel'] ?? 'page-' . Str::kebab($baseName) . '-observer';
            // evento: .pageProductObserver  (deve iniciar com "." para eventos privados Echo)
            $event    = $bc['event']   ?? '.page' . $baseName . 'Observer';

            $base["echo:{$channel},{$event}"] = 'handleBaseCrudUpdate';
        }

        return $base;
    }

    /**
     * Chamado via Echo/broadcast quando o Observer emite o evento.
     * O Livewire já re-executa getRowsProperty() automaticamente no re-render.
     */
    public function handleBaseCrudUpdate(): void
    {
        // silent refresh — sem feedback visual extra
    }

    // ── Render ────────────────────────────────────────────────────────────

    public function render()
    {
        return view('ptah::livewire.base-crud', [
            'rows'             => $this->rows,
            'visibleCols'      => $this->getVisibleColumns(),
            'formCols'         => $this->getFormCols(),
            'permissions'      => $this->crudConfig['permissions']  ?? [],
            'exportCfg'        => $this->crudConfig['exportConfig'] ?? [],
            'totData'          => $this->totalizadoresData,
            'crudTitle'        => $this->crudConfig['displayName'] ?? $this->crudConfig['crud'] ?? class_basename(str_replace('/', '\\', $this->model)),
            'bulkActions'      => $this->crudConfig['bulkActions']  ?? [],
            'hasActiveFilters' => ! empty($this->textFilter) || $this->search !== '' || $this->quickDateFilter !== '',
        ]);
    }

    /**
     * Aceita tanto booleano (true/false) quanto legado string ('S'/'N').
     * Retorna true para: true, 'S', 1, '1'.
     */
    protected function ptahBool(mixed $value): bool
    {
        return $value === true || $value === 'S' || $value === 1 || $value === '1';
    }
}
