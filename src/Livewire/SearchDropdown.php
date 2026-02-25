<?php

declare(strict_types=1);

namespace Ptah\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Ptah\DTO\SearchDropdownDTO;

/**
 * Componente Livewire SearchDropdown.
 *
 * Dropdown de busca dinâmica com suporte a:
 *  - Busca em modelo direto ou via serviço personalizado
 *  - Múltiplos campos de exibição (label, labelSecondary, labelLast)
 *  - Filtros adicionais na query (dataFilter)
 *  - Máscaras de formatação por campo (formatValue)
 *  - Evento de seleção configurável via $listens
 *
 * Livewire 3 — usa dispatch() e #[On(...)].
 *
 * Uso básico:
 *   @livewire('ptah::search-dropdown', [
 *       'model'  => 'Product',
 *       'label'  => 'name',
 *       'listens' => 'onProductSelected',
 *   ])
 *
 * Uso com serviço:
 *   @livewire('ptah::search-dropdown', [
 *       'model'      => 'Product',
 *       'label'      => 'name',
 *       'useService' => 'search',
 *   ])
 */
class SearchDropdown extends Component
{
    // ── Dados ──────────────────────────────────────────────────────────────

    /** Resultados da busca */
    public array $dataModel = [];

    // ── Configuração de campos ─────────────────────────────────────────────

    /** Coluna cujo valor será retornado no evento (geralmente "id") */
    public string $value = 'id';

    /** Coluna exibida como label principal */
    public string $label = 'name';

    /** Coluna exibida como label secundário (opcional) */
    public ?string $labelSecondary = null;

    /** Coluna exibida como terceiro label (opcional) */
    public ?string $labelLast = null;

    /** Colunas extras incluídas no LIKE de busca */
    public array $arraySearch = [];

    // ── Configuração do modelo / serviço ───────────────────────────────────

    /**
     * Nome do modelo para busca.
     * Suporta subdiretórios: "Product", "Purchase/Order".
     */
    public string $model = '';

    /** Classe FQCN resolvida do modelo */
    public string $modelClass = '';

    /** Classe FQCN resolvida do serviço */
    public string $serviceClass = '';

    /**
     * Nome do método do serviço a ser chamado para busca.
     * Quando preenchido, usa $serviceClass->{$useService}(SearchDropdownDTO).
     */
    public ?string $useService = null;

    // ── Busca e filtros ────────────────────────────────────────────────────

    /** Termo digitado pelo usuário */
    public ?string $searchTerm = null;

    /** Filtros WHERE adicionais: [['col', 'op', 'val'], ...] ou ['col' => 'val'] */
    public array $dataFilter = [];

    /** Limite de resultados */
    public int $limit = 10;

    /** ORDER BY raw */
    public string $orderByRaw = 'id asc';

    // ── UI ─────────────────────────────────────────────────────────────────

    /** Chave única do componente (para wire:key) */
    public string $key = '';

    /** Placeholder do input */
    public string $placeholder = 'Selecione';

    /** Posição inicial da lista: "top" ou "bottom" */
    public string $startList = 'bottom';

    /** Se true, carrega dados mesmo sem termo de busca. */
    public bool $initWithData = true;

    /** Controla visibilidade do dropdown */
    public bool $show = false;

    // ── Evento ────────────────────────────────────────────────────────────

    /** Nome do evento Livewire 3 disparado ao selecionar um item */
    public string $listens = 'searchDropdownResult';

    /** Valor extra passado no payload do evento */
    public string $coringa = '';

    // ── Máscaras de formatação ─────────────────────────────────────────────

    /**
     * Máscaras de formatação por slot.
     * Cada máscara pode ser:
     *   - "defaultMask"         → exibe o valor sem transformação
     *   - "cnpj"                → formata como CNPJ
     *   - "cpf"                 → formata como CPF
     *   - "money"               → R$ 1.234,56
     *   - "phone"               → (11) 9 9999-9999
     *   - "date"                → dd/mm/yyyy
     *   - nome de método público do componente filho
     */
    public string $maskLabel     = 'defaultMask';
    public string $maskSecondary = 'defaultMask';
    public string $maskLast      = 'defaultMask';

    // ── Inicialização ──────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->resolveModelClass();
    }

    // ── Render ─────────────────────────────────────────────────────────────

    public function render(): \Illuminate\View\View
    {
        $this->loadData();

        $data = $this->initWithData ? $this->dataModel : [];

        return view('ptah::livewire.search-dropdown', compact('data'));
    }

    // ── Dados ──────────────────────────────────────────────────────────────

    /**
     * Carrega os dados via serviço ou modelo.
     */
    private function loadData(): void
    {
        if ($this->useService) {
            $this->loadDataViaService();
        } else {
            $this->loadDataViaModel();
        }
    }

    /**
     * Busca usando um serviço personalizado.
     * O serviço deve aceitar um SearchDropdownDTO como argumento.
     */
    private function loadDataViaService(): void
    {
        $dto = new SearchDropdownDTO(
            searchTerm:     $this->searchTerm,
            value:          $this->value,
            label:          $this->label,
            labelSecondary: $this->labelSecondary,
            labelLast:      $this->labelLast,
            orderByRaw:     $this->orderByRaw,
            limit:          $this->limit,
            arraySearch:    $this->arraySearch,
            dataFilter:     $this->dataFilter,
        );

        $this->dataModel = app()->make($this->serviceClass)->{$this->useService}($dto);
    }

    /**
     * Busca diretamente no modelo Eloquent.
     */
    private function loadDataViaModel(): void
    {
        // Se já temos um termo, garante que initWithData fique ativo
        if (strlen((string) $this->searchTerm) > 1) {
            $this->initWithData = true;
        }

        $cols = array_filter([$this->value, $this->label, $this->labelSecondary, $this->labelLast]);

        /** @var \Illuminate\Database\Eloquent\Model $query */
        $query = app()->make($this->modelClass)->select(array_values($cols));

        // Aplica LIKE nos campos configurados
        if (!empty($this->searchTerm)) {
            $searchCols = array_merge(
                array_filter([$this->label, $this->labelSecondary, $this->labelLast, $this->value]),
                $this->arraySearch
            );

            $query->where(function ($q) use ($searchCols) {
                foreach ($searchCols as $col) {
                    $q->orWhere($col, 'LIKE', '%' . $this->searchTerm . '%');
                }
            });
        }

        // Filtros adicionais
        if (!empty($this->dataFilter)) {
            $query->where($this->dataFilter);
        }

        $this->dataModel = $query
            ->orderByRaw($this->orderByRaw)
            ->limit($this->limit)
            ->get()
            ->toArray();
    }

    // ── Eventos UI ─────────────────────────────────────────────────────────

    /** Abre/fecha o dropdown */
    public function toggleShow(): void
    {
        $this->show = !$this->show;
    }

    /** Recebe evento externo para fechar/abrir */
    #[On('changeShow')]
    public function changeShow(): void
    {
        $this->toggleShow();
    }

    /** Limpa o termo de busca via evento externo */
    #[On('clearSearchDropdown')]
    public function clearSearchDropdown(): void
    {
        $this->searchTerm = '';
    }

    // ── Seleção ────────────────────────────────────────────────────────────

    /**
     * Processa a seleção de um item e dispara o evento configurado.
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
     * Limpa a seleção e dispara o evento com valores vazios.
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

    // ── Formatação ─────────────────────────────────────────────────────────

    /**
     * Aplica uma máscara de formatação a um valor.
     *
     * Máscaras suportadas:
     *   defaultMask → sem transformação
     *   cnpj        → 00.000.000/0000-00
     *   cpf         → 000.000.000-00
     *   money       → R$ 1.234,56
     *   phone       → (11) 9 9999-9999
     *   date        → dd/mm/yyyy
     */
    public function formatValue(mixed $value, string $mask): string
    {
        if ($value === null) {
            return '';
        }

        $v = (string) $value;

        return match ($mask) {
            'cnpj'  => $this->applyMaskCnpj($v),
            'cpf'   => $this->applyMaskCpf($v),
            'money' => $this->applyMaskMoney($v),
            'phone' => $this->applyMaskPhone($v),
            'date'  => $this->applyMaskDate($v),
            default => $v,
        };
    }

    // ── Helpers internos ───────────────────────────────────────────────────

    /**
     * Resolve as classes FQCN do modelo e serviço com base em $model.
     * Suporta subdiretórios separados por "/": "Purchase/Order" → App\Models\Purchase\Order.
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
