<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ptah\Traits\HasAuditFields;

/**
 * Uses "PtahPage" to avoid conflicts with \Page classes from other packages.
 *
 * @property int         $id
 * @property string      $slug
 * @property string      $name
 * @property string|null $description
 * @property string|null $route
 * @property string|null $icon
 * @property bool        $is_active
 * @property int         $sort_order
 */
class PtahPage extends Model
{
    use HasAuditFields;
    protected $table = 'ptah_pages';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'route',
        'icon',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ─────────────────────────────────────────
    // Relacionamentos
    // ─────────────────────────────────────────

    public function pageObjects(): HasMany
    {
        return $this->hasMany(PageObject::class, 'page_id')->orderBy('obj_order');
    }
}
