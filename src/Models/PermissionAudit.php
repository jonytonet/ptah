<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Permission audit log. Immutable — no updated_at, no SoftDeletes.
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property int|null    $company_id
 * @property string|null $resource_key
 * @property string      $action
 * @property string      $result
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array|null  $context
 * @property string      $created_at
 */
class PermissionAudit extends Model
{
    protected $table = 'ptah_permission_audits';

    // Immutable log: no updated_at
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'company_id',
        'resource_key',
        'action',
        'result',
        'ip_address',
        'user_agent',
        'context',
    ];

    protected $casts = [
        'context'    => 'array',
        'created_at' => 'datetime',
    ];

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeGranted(Builder $query): Builder
    {
        return $query->where('result', 'granted');
    }

    public function scopeDenied(Builder $query): Builder
    {
        return $query->where('result', 'denied');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForResource(Builder $query, string $key): Builder
    {
        return $query->where('resource_key', $key);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
