<?php

declare(strict_types=1);

namespace Ptah\Traits;

use Illuminate\Support\Facades\Auth;

/**
 * Trait HasAuditFields
 *
 * Automatically fills audit columns on any Eloquent Model that declares
 * the corresponding columns in $fillable:
 *
 *  - created_by  → filled on the `creating` event
 *  - updated_by  → filled on `creating` and `updating` events
 *  - deleted_by  → filled on the `deleted` event (after soft-delete commits)
 *
 * The trait uses `in_array(...getFillable())` so it is tolerant of models
 * that do not declare all three columns — no error is thrown for missing ones.
 *
 * Usage:
 *   use Ptah\Traits\HasAuditFields;
 *   class MyModel extends Model {
 *       use HasAuditFields;
 *       protected $fillable = [..., 'created_by', 'updated_by', 'deleted_by'];
 *   }
 */
trait HasAuditFields
{
    public static function bootHasAuditFields(): void
    {
        // ── Create ────────────────────────────────────────────────────────
        static::creating(function ($model) {
            if (! Auth::check()) {
                return;
            }

            $userId   = Auth::id();
            $fillable = $model->getFillable();

            // Use === null instead of empty() to avoid false-positives on falsy IDs.
            if (in_array('created_by', $fillable, true) && $model->created_by === null) {
                $model->created_by = $userId;
            }

            if (in_array('updated_by', $fillable, true) && $model->updated_by === null) {
                $model->updated_by = $userId;
            }
        });

        // ── Update ────────────────────────────────────────────────────────
        static::updating(function ($model) {
            if (! Auth::check()) {
                return;
            }

            if (in_array('updated_by', $model->getFillable(), true)) {
                $model->updated_by = Auth::id();
            }
        });

        // ── Soft Delete ───────────────────────────────────────────────────
        // We listen to `deleted` (fires AFTER deleted_at is committed) rather
        // than `deleting` (fires before). This prevents the scenario where
        // deleted_by would be stamped but the soft-delete itself later fails,
        // leaving the row active with a stale deleted_by value.
        //
        // A direct query-builder UPDATE is used here so we do not re-trigger
        // model events (no extra `updating` / `saving` cycles).
        // Only runs when the model uses SoftDeletes and was soft-deleted
        // (not forceDelete — after forceDelete the row is gone, deleted_at is null).
        static::deleted(function ($model) {
            if (! Auth::check()) {
                return;
            }

            if (! in_array('deleted_by', $model->getFillable(), true)) {
                return;
            }

            // Not a SoftDeletes model — skip.
            if (! method_exists($model, 'trashed')) {
                return;
            }

            // forceDelete(): deleted_at is null → $model->trashed() is false → skip.
            if (! $model->trashed()) {
                return;
            }

            $userId = Auth::id();

            $model->getConnection()
                ->table($model->getTable())
                ->where($model->getKeyName(), $model->getKey())
                ->update(['deleted_by' => $userId]);
        });
    }

    // ── Audit relationships ───────────────────────────────────────────────────

    /**
     * User who created the record.
     */
    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo($this->resolveUserModel(), 'created_by');
    }

    /**
     * User who last updated the record.
     */
    public function updatedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo($this->resolveUserModel(), 'updated_by');
    }

    /**
     * User who soft-deleted the record.
     */
    public function deletedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo($this->resolveUserModel(), 'deleted_by');
    }

    /**
     * Resolves the User model configured for authentication.
     * Falls back to App\Models\User when the config key is absent.
     *
     * @return class-string
     */
    protected function resolveUserModel(): string
    {
        return config('auth.providers.users.model', \App\Models\User::class);
    }
}
