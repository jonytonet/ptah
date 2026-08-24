<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single per-user notification row (see database/migrations/…create_ptah_notifications_table.php,
 * an opt-in schema — this model only ever runs a query when the consumer has
 * published and migrated it, gated by `ptah.notifications.enabled` in
 * NotificationService::tableExists()).
 *
 * No SoftDeletes / HasAuditFields: these rows are machine-generated
 * ephemeral events, not user-edited records — `dismissed_at` already is the
 * soft-hide mechanism. Same posture as Export/PermissionAudit.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $company_id
 * @property string $type
 * @property string|null $category
 * @property string $title
 * @property string|null $body
 * @property string|null $icon
 * @property string|null $url
 * @property string|null $action_label
 * @property string|null $dedupe_key
 * @property Carbon|null $read_at
 * @property Carbon|null $dismissed_at
 */
class Notification extends Model
{
    protected $table = 'ptah_notifications';

    protected $fillable = [
        'user_id',
        'company_id',
        'type',
        'category',
        'title',
        'body',
        'icon',
        'url',
        'action_label',
        'dedupe_key',
        'read_at',
        'dismissed_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Same semantics as UserRole::scopeForCompany(): a null $companyId scopes
     * to global (company_id IS NULL) rows only; a given $companyId matches
     * that company's rows OR the global ones.
     */
    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        if ($companyId === null) {
            return $query->whereNull('company_id');
        }

        return $query->where(function (Builder $q) use ($companyId) {
            $q->where('company_id', $companyId)
                ->orWhereNull('company_id');
        });
    }
}
