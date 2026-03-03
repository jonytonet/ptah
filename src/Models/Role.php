<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ptah\Traits\HasAuditFields;

/**
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property string|null $color
 * @property int|null    $department_id
 * @property bool        $is_master
 * @property bool        $is_active
 */
class Role extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $table = 'ptah_roles';

    protected $fillable = [
        'name',
        'description',
        'color',
        'department_id',
        'is_master',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_master'  => 'boolean',
        'is_active'  => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
    ];

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeMaster(Builder $query): Builder
    {
        return $query->where('is_master', true);
    }

    // ─────────────────────────────────────────
    // Relacionamentos
    // ─────────────────────────────────────────

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'role_id');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'role_id');
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    /**
     * Retorna a cor para exibição, com fallback padrão.
     */
    public function getDisplayColor(): string
    {
        return $this->color ?? ($this->is_master ? '#fbbf24' : '#6b7280');
    }

    /**
     * Retorna a badge label para exibir na UI.
     */
    public function getBadgeLabel(): string
    {
        return $this->is_master ? '👑 MASTER' : $this->name;
    }
}
