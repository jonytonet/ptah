<?php

declare(strict_types=1);

namespace Ptah\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Ptah\Services\Crud\CrudConfigService;

/**
 * Componente de configuração do BaseCrud.
 *
 * Permite gerenciar visualmente:
 *  - Colunas (ordem, tipo, helpers, totalizadores)
 *  - Ações por linha (link / livewire / javascript)
 *  - Filtros personalizados (whereHas, aggregates)
 *  - Estilos condicionais de linha
 *  - Configurações gerais (link, cache, exportação, UI)
 *  - Permissões e visibilidade de botões
 *
 * Uso: @livewire('ptah::crud-config', ['model' => $model])
 */
class CrudConfig extends Component
{
    // ── Identificação ────────────────────────────────────────────────────────

    public string $model = '';
    public bool   $showModal = false;

    // ── Colunas ──────────────────────────────────────────────────────────────

    /** Todas as colunas (incluindo actions) */
    public array $formEditFields = [];

    /** Campo sendo adicionado/editado */
    public array $formDataField = [];

    /** Índice sendo editado (-1 = novo) */
    public int $editingFieldIndex = -1;

    // ── Ações por linha ──────────────────────────────────────────────────────

    public array $formDataAction = [];

    // ── Filtros personalizados ────────────────────────────────────────────────

    public array $customFilters  = [];
    public array $formDataFilter = [];

    // ── Estilos condicionais ──────────────────────────────────────────────────

    public array $conditionStyles = [];
    public array $formDataStyle   = [];

    // ── Geral ────────────────────────────────────────────────────────────────

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

    // ── Permissões ───────────────────────────────────────────────────────────

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

    // ── Serviço ───────────────────────────────────────────────────────────────

    protected CrudConfigService $configService;

    public function boot(CrudConfigService $configService): void
    {
        $this->configService = $configService;
    }

    // ── Ciclo de vida ────────────────────────────────────────────────────────

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

    public function openModal(): void
    {
        $this->loadFromDb();
        $this->formDataField  = [];
        $this->formDataAction = [];
        $this->formDataFilter = [];
        $this->formDataStyle  = [];
        $this->editingFieldIndex = -1;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal         = false;
        $this->formDataField     = [];
        $this->formDataAction    = [];
        $this->formDataFilter    = [];
        $this->formDataStyle     = [];
        $this->editingFieldIndex = -1;
    }

    // ── Carregar config ──────────────────────────────────────────────────────

    protected function loadFromDb(): void
    {
        $record = $this->configService->find($this->model);
        if (! $record) {
            return;
        }

        $cfg = $record->config;

        // Cols — converte colsSelect array → string para edição
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

        // Filtros e estilos
        $this->customFilters   = $cfg['customFilters']   ?? [];
        $this->conditionStyles = $cfg['contitionStyles'] ?? [];

        // Geral
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

        // Permissões
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
            'colsTipo'         => 'text',
            'colsGravar'       => 'S',
            'colsRequired'     => 'N',
            'colsAlign'        => 'text-start',
            'colsIsFilterable' => 'S',
            'colsNomeLogico'   => ucfirst($this->formDataField['colsNomeFisico']),
        ];

        $this->formEditFields[] = array_merge($defaults, $this->formDataField);
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

        $this->formEditFields[$this->editingFieldIndex] = $this->formDataField;
        $this->formDataField     = [];
        $this->editingFieldIndex = -1;
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

    // ── Ações — CRUD ─────────────────────────────────────────────────────────

    public function addAction(): void
    {
        if (empty($this->formDataAction['colsNomeLogico'])) {
            return;
        }

        $this->formEditFields[] = array_merge([
            'actionType'       => 'link',
            'actionValue'      => '',
            'actionIcon'       => 'bx bx-link',
            'actionColor'      => 'primary',
            'actionPermission' => '',
        ], $this->formDataAction, [
            'colsNomeFisico'   => 'id',
            'colsTipo'         => 'action',
            'colsGravar'       => 'N',
            'colsRequired'     => 'N',
            'colsIsFilterable' => 'N',
        ]);

        $this->formDataAction = [];
    }

    public function removeAction(int $index): void
    {
        array_splice($this->formEditFields, $index, 1);
        $this->formEditFields = array_values($this->formEditFields);
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

        session()->flash('crud-success', 'Configuração salva com sucesso!');
    }

    protected function buildConfigArray(array $existing = []): array
    {
        return array_merge($existing, [
            'crud'            => $existing['crud']            ?? $this->model,
            'configLinkLinha' => $this->configLinkLinha,
            'configEsconderId'=> $existing['configEsconderId'] ?? 'N',
            'tableClass'      => $this->tableClass,
            'theadClass'      => $this->theadClass,
            'cols'            => $this->formatFieldsForDb(),
            'customFilters'   => array_values($this->customFilters),
            'contitionStyles' => array_values($this->conditionStyles),
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
                'theme'             => $existing['uiPreferences']['theme'] ?? 'light',
                'compactMode'       => $this->uiCompactMode,
                'stickyHeader'      => $this->uiStickyHeader,
                'showTotalizador'   => $this->showTotalizador,
                'highlightOnHover'  => $existing['uiPreferences']['highlightOnHover'] ?? true,
            ]),
        ]);
    }

    protected function formatFieldsForDb(): array
    {
        $fields = $this->formEditFields;

        foreach ($fields as &$field) {
            // Converte colsSelect string "k;v;;k2;v2" → associative array
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
        // Ex: 'Purchase/Order/SalesOrders' → 'pageSalesOrders'
        return 'page' . class_basename(str_replace('/', '\\', $this->model));
    }
}
