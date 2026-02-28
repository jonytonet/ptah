<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int        $id
 * @property int        $role_id
 * @property int        $page_object_id
 * @property bool       $can_create
 * @property bool       $can_read
 * @property bool       $can_update
 * @property bool       $can_delete
 * @property array|null $extra
 */
class RolePermission extends Model
{
    use SoftDeletes;

    protected $table = 'ptah_role_permissions';

    protected $fillable = [
        'role_id',
        'page_object_id',
        'can_create',
        'can_read',
        'can_update',
        'can_delete',
        'extra',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'can_create' => 'boolean',
        'can_read'   => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'extra'      => 'array',
    ];

    // ─────────────────────────────────────────
    // Relacionamentos
    // ─────────────────────────────────────────

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function pageObject(): BelongsTo
    {
        return $this->belongsTo(PageObject::class, 'page_object_id');
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    /**
     * Verifica se a ação está permitida neste registro.
     *
     * @param  string $action  create|read|update|delete
     */
    public function allows(string $action): bool
    {
        return match (strtolower($action)) {
            'create' => $this->can_create,
            'read'   => $this->can_read,
            'update' => $this->can_update,
            'delete' => $this->can_delete,
            default  => false,
        };
    }

    /**
     * Retorna o array de flags CRUD como array associativo.
     *
     * @return array{create: bool, read: bool, update: bool, delete: bool}
     */
    public function toCrudArray(): array
    {
        return [
            'create' => $this->can_create,
            'read'   => $this->can_read,
            'update' => $this->can_update,
            'delete' => $this->can_delete,
        ];
    }
}
