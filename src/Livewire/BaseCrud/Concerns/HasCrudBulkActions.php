<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles bulk selection, bulk delete and custom bulk actions.
 */
trait HasCrudBulkActions
{
    // ── Selection ──────────────────────────────────────────────────────────────

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;

        if ($this->selectAll) {
            $keyName = (new ($this->model))->getKeyName();
            $this->selectedRows = $this->rows->pluck($keyName)->map(fn ($id) => (string) $id)->toArray();
        } else {
            $this->selectedRows = [];
        }
    }

    public function toggleSelectRow(int|string $id): void
    {
        $idStr = (string) $id;

        if (in_array($idStr, $this->selectedRows, true)) {
            $this->selectedRows = array_values(array_filter(
                $this->selectedRows,
                fn ($r) => $r !== $idStr
            ));
            $this->selectAll = false;
        } else {
            $this->selectedRows[] = $idStr;
        }
    }

    public function clearSelection(): void
    {
        $this->selectedRows = [];
        $this->selectAll = false;
    }

    // ── Bulk delete ────────────────────────────────────────────────────────────

    public function bulkDelete(): void
    {
        if (empty($this->selectedRows) || $this->bulkActionInProgress) {
            return;
        }

        // Ptah permission check — fail-closed (see HasCrudForm::authorizeCrudAction).
        if (! $this->authorizeCrudAction('delete')) {
            return;
        }

        $this->bulkActionInProgress = true;

        // Scoped by company / master-detail lock (scopedQuery()) so a
        // client-supplied id in selectedRows cannot delete a record outside the
        // current scope (IDOR) — same guard as the single-record deleteRecord().
        $query = $this->scopedQuery();

        if ($query) {
            // Match on the model's actual primary key, not a hardcoded 'id' —
            // same as bulkExport() (HasCrudExport.php) — so CRUDs with a
            // non-default key name are matched correctly instead of silently
            // deleting nothing (or the wrong rows, if an unrelated 'id' column
            // exists on the table).
            $keyName = (new ($this->model))->getKeyName();

            DB::transaction(function () use ($query, $keyName) {
                // Use each() + delete() individually to fire Eloquent events
                // and allow HasAuditFields trait to record deleted_by per record.
                $query->whereIn($keyName, $this->selectedRows)->each(
                    fn ($record) => $record->delete()
                );
            });
            $this->cacheService->invalidateModel($this->model);
            $this->updateTrashedCount();
        }

        $deletedCount = count($this->selectedRows);
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->bulkActionInProgress = false;

        $this->dispatch('crud-bulk-deleted', model: $this->model, count: $deletedCount);
        $this->dispatch('ptah-toast', title: trans('ptah::ui.bulk_toast_deleted', ['n' => $deletedCount]), color: 'warn');
    }

    public function bulkRestore(): void
    {
        if (empty($this->selectedRows) || $this->bulkActionInProgress) {
            return;
        }

        // Ptah permission check — restore requires update permission (fail-closed).
        if (! $this->authorizeCrudAction('update')) {
            return;
        }

        $this->bulkActionInProgress = true;

        // withTrashed() is chained onto the SCOPED query (never a raw
        // newQuery()) so restoring a soft-deleted row still respects the
        // company / master-detail lock (IDOR guard).
        $query = $this->scopedQuery();

        if ($query) {
            // Match on the model's actual primary key, not a hardcoded 'id' —
            // same as bulkExport() (HasCrudExport.php).
            $keyName = (new ($this->model))->getKeyName();

            DB::transaction(function () use ($query, $keyName) {
                $query->withTrashed()
                    ->whereIn($keyName, $this->selectedRows)
                    ->each(fn ($record) => $record->restore());
            });
            $this->cacheService->invalidateModel($this->model);
            $this->updateTrashedCount();
        }

        $restoredCount = count($this->selectedRows);
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->bulkActionInProgress = false;

        $this->dispatch('ptah-toast', title: trans('ptah::ui.bulk_toast_restored', ['n' => $restoredCount]), color: 'success');
    }

    public function bulkForceDelete(): void
    {
        if (empty($this->selectedRows) || $this->bulkActionInProgress) {
            return;
        }

        // Ptah permission check — fail-closed (see HasCrudForm::authorizeCrudAction).
        if (! $this->authorizeCrudAction('delete')) {
            return;
        }

        $this->bulkActionInProgress = true;

        // withTrashed() is chained onto the SCOPED query (never a raw
        // newQuery()) so force-deleting still respects the company /
        // master-detail lock (IDOR guard).
        $query = $this->scopedQuery();

        if ($query) {
            // Match on the model's actual primary key, not a hardcoded 'id' —
            // same as bulkExport() (HasCrudExport.php).
            $keyName = (new ($this->model))->getKeyName();

            DB::transaction(function () use ($query, $keyName) {
                $query->withTrashed()
                    ->whereIn($keyName, $this->selectedRows)
                    ->each(fn ($record) => $record->forceDelete());
            });
            $this->cacheService->invalidateModel($this->model);
            $this->updateTrashedCount();
        }

        $deletedCount = count($this->selectedRows);
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->bulkActionInProgress = false;

        $this->dispatch('ptah-toast', title: trans('ptah::ui.bulk_toast_force_deleted', ['n' => $deletedCount]), color: 'danger');
    }

    // ── Custom bulk actions ────────────────────────────────────────────────────

    /**
     * Executes a custom bulk action defined in crudConfig.
     * Config example: "bulkActions": [{"label": "Approve", "action": "approve", "method": "App\\Services\\ProductService@bulkApprove"}]
     */
    public function executeBulkAction(string $action): void
    {
        if (empty($this->selectedRows) || $this->bulkActionInProgress) {
            return;
        }

        // Ptah permission check — custom bulk actions mutate records, so they
        // require the same guard as save()/restore() (fail-closed).
        if (! $this->authorizeCrudAction('update')) {
            return;
        }

        $bulkActions = $this->crudConfig['bulkActions'] ?? [];
        $config = null;

        foreach ($bulkActions as $ba) {
            if (($ba['action'] ?? '') === $action) {
                $config = $ba;
                break;
            }
        }

        if (! $config) {
            return;
        }

        // The handler receives ids chosen by the CLIENT (selectedRows is a public
        // wire:model property), so they must be re-resolved through scopedQuery()
        // — company + master-detail lock — before leaving this component, exactly
        // like bulkDelete/bulkRestore/bulkForceDelete above. Otherwise a forged
        // selection reaches the configured service with out-of-scope ids.
        $modelInstance = new ($this->model);
        $keyName = $modelInstance->getKeyName();
        $scopedIds = $this->scopedQuery()
            ?->whereIn($keyName, $this->selectedRows)
            ->pluck($keyName)
            ->all() ?? [];

        if (empty($scopedIds)) {
            return;
        }

        $this->bulkActionInProgress = true;

        // Dispatch event for host to handle, or call method via service
        $methodStr = $config['method'] ?? null;

        if ($methodStr && str_contains($methodStr, '@')) {
            [$class, $method] = explode('@', $methodStr, 2);

            try {
                if (class_exists($class) && method_exists($class, $method)) {
                    app($class)->{$method}($scopedIds, $this->model);
                }
            } catch (\Throwable $e) {
                Log::error('Ptah bulk action failed', ['action' => $action, 'error' => $e->getMessage()]);
            }
        }

        $this->dispatch('crud-bulk-action', model: $this->model, action: $action, ids: $this->selectedRows);

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->bulkActionInProgress = false;
        $this->cacheService->invalidateModel($this->model);
    }
}
