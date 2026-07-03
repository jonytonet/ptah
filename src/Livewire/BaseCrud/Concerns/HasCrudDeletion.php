<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Handles record deletion, restoration and soft-delete count.
 */
trait HasCrudDeletion
{
    // ── Delete confirmation ──────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->deletingId = null;
        $this->showDeleteConfirm = false;
    }

    public function deleteRecord(): void
    {
        if (! $this->deletingId) {
            return;
        }

        // Ptah permission check — fail-closed (see HasCrudForm::authorizeCrudAction).
        if (! $this->authorizeCrudAction('delete')) {
            $this->cancelDelete();

            return;
        }

        // Scoped by company / master-detail lock so a client-supplied id cannot
        // delete a record outside the current scope (IDOR).
        $record = $this->scopedQuery()?->find($this->deletingId);
        $deletedId = null;
        $isSoftDelete = false;

        if ($record) {
            // Record who deleted (SoftDelete)
            $fillable = $record->getFillable();
            $isSoftDelete = method_exists($record, 'getDeletedAtColumn');
            if (Auth::id()
                && in_array('deleted_by', $fillable, true)
                && $isSoftDelete
            ) {
                $record->deleted_by = Auth::id();
                $record->saveQuietly();
            }
            $record->delete();
            $deletedId = $record->getKey();
            $this->cacheService->invalidateModel($this->model);
            $this->updateTrashedCount();
        }

        $this->cancelDelete();
        $this->dispatch('crud-deleted', model: $this->model);
        // Soft deletes are reversible: offer an inline Undo on the toast.
        $this->dispatch(
            'ptah-toast',
            title: trans('ptah::ui.toast_deleted'),
            color: 'warn',
            undoId: ($isSoftDelete && $deletedId) ? $deletedId : null,
        );
    }

    public function restoreRecord(int $id): void
    {
        // Ptah permission check — restore requires update permission (fail-closed).
        if (! $this->authorizeCrudAction('update')) {
            return;
        }

        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return;
        }

        // Restore needs withTrashed(), so fetch first and apply the company /
        // master-detail scope to the loaded record (IDOR guard).
        $record = $modelInstance->newQuery()->withTrashed()->find($id);

        if ($record && $this->recordInScope($record) && method_exists($record, 'restore')) {
            $record->restore();
        }

        $this->dispatch('crud-restored', model: $this->model);
    }

    // ── Soft-delete toggle & count ───────────────────────────────────────────

    public function toggleTrashed(): void
    {
        $this->showTrashed = ! $this->showTrashed;
        $this->resetPage();
    }

    public function updateTrashedCount(): void
    {
        $modelInstance = $this->resolveEloquentModel();

        if (! $modelInstance) {
            return;
        }

        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelInstance));

        if (! $usesSoftDeletes) {
            $this->trashedCount = 0;

            return;
        }

        try {
            $this->trashedCount = (int) $modelInstance->newQuery()->onlyTrashed()->count();
        } catch (\Throwable) {
            $this->trashedCount = 0;
        }
    }
}
