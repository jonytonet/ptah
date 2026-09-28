<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Ptah\Contracts\GuardsDeletion;
use Ptah\Exceptions\CrudHookAbort;
use Ptah\Traits\HasAuditFields;

/**
 * Handles record deletion, restoration and soft-delete count.
 *
 * Every delete — single, bulk, force — goes through attemptDelete(), which
 * can say no: the model's GuardsDeletion reason, the `beforeDelete` hook, a
 * `deleting` event that returns false or throws. A refused delete reports its
 * reason and leaves nothing behind — no "Deleted" toast, no Undo, no
 * `crud-deleted`, no `deleted_by` on a row that is still active.
 */
trait HasCrudDeletion
{
    // ── Delete confirmation ──────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        // Pergunta ao model ANTES da confirmacao: o usuario sabe o motivo na
        // hora, em vez de confirmar e ser recusado.
        $record = $this->scopedQuery()?->find($id);
        if ($record && ($reason = $this->deletionBlockedReason($record)) !== null) {
            $this->dispatch('ptah-toast', title: $reason, color: 'danger');

            return;
        }

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
        if (! $this->authorizeCrudAction('delete') || ! $this->crudConfigAllows('delete')) {
            $this->cancelDelete();

            return;
        }

        // Scoped by company / master-detail lock so a client-supplied id cannot
        // delete a record outside the current scope (IDOR).
        $record = $this->scopedQuery()?->find($this->deletingId);
        $this->cancelDelete();

        if (! $record) {
            return;
        }

        $refused = $this->attemptDelete($record);

        if ($refused !== null) {
            $this->dispatch('ptah-toast', title: $refused, color: 'danger');

            return;
        }

        $this->cacheService->invalidateModel($this->model);
        $this->updateTrashedCount();

        $this->dispatch('crud-deleted', model: $this->model);
        // Soft deletes are reversible: offer an inline Undo on the toast.
        $this->dispatch(
            'ptah-toast',
            title: trans('ptah::ui.toast_deleted'),
            color: 'warn',
            undoId: $this->usesSoftDeletes($record) ? $record->getKey() : null,
        );
    }

    /**
     * Deletes one record, or says why it did not. Null means deleted.
     */
    protected function attemptDelete(Model $record, bool $force = false): ?string
    {
        if (($reason = $this->deletionBlockedReason($record)) !== null) {
            return $reason;
        }

        try {
            // Um beforeDelete e uma barreira: qualquer falha dele barra a
            // exclusao (hookIsCritical), ao contrario dos hooks do save.
            $data = $record->attributesToArray();
            $this->executeDynamicHook('beforeDelete', $data, $record);

            $deleted = $force ? $record->forceDelete() : $record->delete();
        } catch (ValidationException $e) {
            return (string) (collect($e->errors())->flatten()->first() ?? $e->getMessage());
        } catch (CrudHookAbort $e) {
            // Lancada pelo hook: a mensagem dele e o motivo. Embrulhando outra
            // falha (hook quebrado), segue o caminho do erro abaixo.
            if ($e->getPrevious() === null) {
                return $e->getMessage();
            }

            return $this->deleteFailed($record, $e->getPrevious());
        } catch (\Throwable $e) {
            return $this->deleteFailed($record, $e);
        }

        // Um evento `deleting` que devolve false cancela a exclusao em silencio.
        if ($deleted === false || $deleted === null) {
            return trans('ptah::ui.crud_delete_not_done');
        }

        $this->stampDeletedBy($record, $force);

        return null;
    }

    private function deleteFailed(Model $record, \Throwable $e): string
    {
        Log::warning('[BaseCrud] delete refused', [
            'model' => $this->model,
            'id' => $record->getKey(),
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);

        return trans('ptah::ui.crud_delete_refused', ['message' => $e->getMessage()]);
    }

    /**
     * The model's own reason not to be deleted (GuardsDeletion), if any.
     */
    protected function deletionBlockedReason(Model $record): ?string
    {
        if (! $record instanceof GuardsDeletion) {
            return null;
        }

        $reason = $record->deletionBlockedReason();

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }

    /**
     * deleted_by only once the soft delete happened — stamping it first left
     * an active row marked as deleted whenever the delete was refused.
     * HasAuditFields stamps it on its own `deleted` event.
     */
    private function stampDeletedBy(Model $record, bool $force): void
    {
        if ($force || ! Auth::id() || ! $this->usesSoftDeletes($record)
            || ! in_array('deleted_by', $record->getFillable(), true)
            || in_array(HasAuditFields::class, class_uses_recursive($record), true)) {
            return;
        }

        $record->deleted_by = Auth::id();
        $record->saveQuietly();
    }

    private function usesSoftDeletes(Model $record): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($record), true);
    }

    public function restoreRecord(int $id): void
    {
        // Ptah permission check — restore requires update permission (fail-closed).
        // Restaurar e `update` para o RBAC, mas `showTrashButton` +
        // `permissions.restore` para a config.
        if (! $this->authorizeCrudAction('update') || ! $this->crudConfigAllows('restore')) {
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
