<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ptah\Traits\HasAuditFields;

/**
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property bool        $is_active
 */
class Department extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $table = 'ptah_departments';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
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

    // ─────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'department_id');
    }
}
