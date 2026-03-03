<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ptah\Traits\HasAuditFields;

/**
 * @property int         $id
 * @property int         $page_id
 * @property string      $section
 * @property string      $obj_key
 * @property string      $obj_label
 * @property string      $obj_type
 * @property int         $obj_order
 * @property bool        $is_active
 */
class PageObject extends Model
{
    use HasAuditFields;
    protected $table = 'ptah_page_objects';

    /** Available object types */
    public const TYPES = ['page', 'button', 'field', 'link', 'section', 'api', 'report', 'tab'];

    protected $fillable = [
        'page_id',
        'section',
        'obj_key',
        'obj_label',
        'obj_type',
        'obj_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'obj_order' => 'integer',
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

    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('obj_key', $key);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('obj_type', $type);
    }

    // ─────────────────────────────────────────
    // Relacionamentos
    // ─────────────────────────────────────────

    public function page(): BelongsTo
    {
        return $this->belongsTo(PtahPage::class, 'page_id');
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'page_object_id');
    }
}
