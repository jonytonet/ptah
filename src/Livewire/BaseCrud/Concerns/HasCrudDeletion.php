<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Handles record deletion, restoration and soft-delete count.
 */
trait HasCrudDeletion
{
    // ── Delete confirmation ──────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->deletingId        = $id;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->deletingId        = null;
        $this->showDeleteConfirm = false;
    }

    public function deleteRecord(): void
    {
        if (! $this->deletingId) {
            return;
        }

        // Ptah permission check
        if (config('ptah.modules.permissions') && \Illuminate\Support\Facades\Auth::check()) {
            $key = $this->crudConfig['permissions']['permissionIdentifier'] ?? null;
            if ($key && ! ptah_can($key, 'delete')) {
                $this->cancelDelete();
                return;
            }
        }

        $modelInstance = $this->resolveEloquentModel();
        $record        = $modelInstance->newQuery()->find($this->deletingId);

        if ($record) {
            // Record who deleted (SoftDelete)
            $fillable = $record->getFillable();
            if (\Illuminate\Support\Facades\Auth::id()
                && in_array('deleted_by', $fillable, true)
                && method_exists($record, 'getDeletedAtColumn')
            ) {
                $record->deleted_by = \Illuminate\Support\Facades\Auth::id();
                $record->saveQuietly();
            }
            $record->delete();
            $this->cacheService->invalidateModel($this->model);
            $this->updateTrashedCount();
        }

        $this->cancelDelete();
        $this->dispatch('crud-deleted', model: $this->model);
    }

    public function restoreRecord(int $id): void
    {
        // Ptah permission check (restore requires update permission)
        if (config('ptah.modules.permissions') && \Illuminate\Support\Facades\Auth::check()) {
            $key = $this->crudConfig['permissions']['permissionIdentifier'] ?? null;
            if ($key && ! ptah_can($key, 'update')) {
                return;
            }
        }

        $modelInstance = $this->resolveEloquentModel();
        $record        = $modelInstance->newQuery()->withTrashed()->find($id);

        if ($record && method_exists($record, 'restore')) {
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
