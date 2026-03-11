<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Model;
use Ptah\Traits\HasAuditFields;

/**
 * Model for BaseCrud configurations.
 *
 * Each row represents the complete configuration of an entity.
 * The `config` column stores the full JSON (cols, customFilters, permissions, etc.).
 *
 * @property int    $id
 * @property string $model
 * @property array  $config
 */
class CrudConfig extends Model
{
    use HasAuditFields;

    protected $table = 'crud_configs';

    protected $fillable = ['model', 'route', 'config', 'created_by', 'updated_by'];

    protected $casts = [
        'config'     => 'array',
        'route'      => 'string',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    // ── Accessors for config sub-sections ────────────────────────────────

    /**
     * Table/form columns.
     */
    public function cols(): array
    {
        return $this->config['cols'] ?? [];
    }

    /**
     * Custom filters (filter bar).
     */
    public function customFilters(): array
    {
        return $this->config['customFilters'] ?? [];
    }

    /**
     * CRUD permissions.
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
     * Export configuration.
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
     * Cache strategy.
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
     * Conditional row styles.
     */
    public function conditionStyles(): array
    {
        return $this->config['contitionStyles'] ?? [];
    }

    /**
     * Date range filters.
     */
    public function dateRangeFilters(): array
    {
        return $this->config['dateRangeFilters'] ?? [];
    }

    /**
     * Default UI preferences for this CRUD.
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
     * Totalizers.
     */
    public function totalizadores(): array
    {
        return array_merge([
            'enabled' => false,
            'columns' => [],
        ], $this->config['totalizadores'] ?? []);
    }

    /**
     * Filterable columns (colsIsFilterable == true|'S').
     */
    public function filterableCols(): array
    {
        return array_values(array_filter($this->cols(), fn($c) => $this->ptahBool($c['colsIsFilterable'] ?? false)));
    }

    /**
     * Form columns (colsGravar == true|'S').
     */
    public function formCols(): array
    {
        return array_values(array_filter($this->cols(), fn($c) => $this->ptahBool($c['colsGravar'] ?? false)));
    }

    /**
     * Accepts both boolean (true/false) and legacy string ('S'/'N').
     * Returns true for: true, 'S', 1, '1'.
     */
    protected function ptahBool(mixed $value): bool
    {
        return $value === true || $value === 'S' || $value === 1 || $value === '1';
    }
}
