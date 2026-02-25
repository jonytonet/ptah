<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model para configurações do BaseCrud.
 *
 * Cada linha representa a configuração completa de uma entidade.
 * A coluna `config` armazena o JSON completo (cols, customFilters, permissions, etc.).
 *
 * @property int    $id
 * @property string $model
 * @property array  $config
 */
class CrudConfig extends Model
{
    protected $table = 'crud_configs';

    protected $fillable = ['model', 'config'];

    protected $casts = [
        'config' => 'array',
    ];

    // ── Accessors para sub-seções do config ────────────────────────────────

    /**
     * Colunas da tabela/formulário.
     */
    public function cols(): array
    {
        return $this->config['cols'] ?? [];
    }

    /**
     * Filtros customizados (barra de filtros).
     */
    public function customFilters(): array
    {
        return $this->config['customFilters'] ?? [];
    }

    /**
     * Permissões do CRUD.
     */
    public function permissions(): array
    {
        return array_merge([
            'create'           => null,
            'edit'             => null,
            'delete'           => null,
            'export'           => null,
            'restore'          => null,
            'showCreateButton' => true,
            'showEditButton'   => true,
            'showDeleteButton' => true,
            'showTrashButton'  => true,
            'identifier'       => '',
        ], $this->config['permissions'] ?? []);
    }

    /**
     * Configuração de exportação.
     */
    public function exportConfig(): array
    {
        return array_merge([
            'enabled'             => false,
            'asyncThreshold'      => 1000,
            'maxRows'             => 10000,
            'orientation'         => 'landscape',
            'formats'             => ['excel'],
            'chunkSize'           => 500,
            'notificationChannel' => 'database',
        ], $this->config['exportConfig'] ?? []);
    }

    /**
     * Estratégia de cache.
     */
    public function cacheStrategy(): array
    {
        return array_merge([
            'enabled' => true,
            'ttl'     => 3600,
            'tags'    => [],
        ], $this->config['cacheStrategy'] ?? []);
    }

    /**
     * Estilos condicionais de linha.
     */
    public function conditionStyles(): array
    {
        return $this->config['contitionStyles'] ?? [];
    }

    /**
     * Filtros por intervalo de datas.
     */
    public function dateRangeFilters(): array
    {
        return $this->config['dateRangeFilters'] ?? [];
    }

    /**
     * Preferências de UI padrão para este CRUD.
     */
    public function uiPreferences(): array
    {
        return array_merge([
            'theme'            => 'light',
            'compactMode'      => false,
            'stickyHeader'     => true,
            'showTotalizador'  => true,
            'highlightOnHover' => true,
        ], $this->config['uiPreferences'] ?? []);
    }

    /**
     * Totalizadores.
     */
    public function totalizadores(): array
    {
        return array_merge([
            'enabled' => false,
            'columns' => [],
        ], $this->config['totalizadores'] ?? []);
    }

    /**
     * Colunas filtráveis (colsIsFilterable == 'S').
     */
    public function filterableCols(): array
    {
        return array_values(array_filter($this->cols(), fn($c) => ($c['colsIsFilterable'] ?? 'N') === 'S'));
    }

    /**
     * Colunas de formulário (colsGravar == 'S').
     */
    public function formCols(): array
    {
        return array_values(array_filter($this->cols(), fn($c) => ($c['colsGravar'] ?? 'N') === 'S'));
    }
}
