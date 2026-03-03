<?php

declare(strict_types=1);

namespace Ptah\Livewire\Concerns;

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
            $this->selectedRows = $this->rows->pluck('id')->map(fn($id) => (string) $id)->toArray();
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
                fn($r) => $r !== $idStr
            ));
            $this->selectAll = false;
        } else {
            $this->selectedRows[] = $idStr;
        }
    }

    // ── Bulk delete ────────────────────────────────────────────────────────────

    public function bulkDelete(): void
    {
        if (empty($this->selectedRows) || $this->bulkActionInProgress) {
            return;
        }

        $this->bulkActionInProgress = true;
        $modelInstance = $this->resolveEloquentModel();

        if ($modelInstance) {
            // Use each() + delete() individually to fire Eloquent events
            // and allow HasAuditFields trait to record deleted_by per record.
            $modelInstance->newQuery()->whereIn('id', $this->selectedRows)->each(
                fn ($record) => $record->delete()
            );
            $this->cacheService->invalidateModel($this->model);
            $this->updateTrashedCount();
        }

        $deletedCount               = count($this->selectedRows);
        $this->selectedRows         = [];
        $this->selectAll            = false;
        $this->bulkActionInProgress = false;

        $this->dispatch('crud-bulk-deleted', model: $this->model, count: $deletedCount);
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

        $bulkActions = $this->crudConfig['bulkActions'] ?? [];
        $config      = null;

        foreach ($bulkActions as $ba) {
            if (($ba['action'] ?? '') === $action) {
                $config = $ba;
                break;
            }
        }

        if (! $config) {
            return;
        }

        $this->bulkActionInProgress = true;

        // Dispatch event for host to handle, or call method via service
        $methodStr = $config['method'] ?? null;

        if ($methodStr && str_contains($methodStr, '@')) {
            [$class, $method] = explode('@', $methodStr, 2);

            try {
                if (class_exists($class) && method_exists($class, $method)) {
                    app($class)->{$method}($this->selectedRows, $this->model);
                }
            } catch (\Throwable $e) {
                Log::error('Ptah bulk action failed', ['action' => $action, 'error' => $e->getMessage()]);
            }
        }

        $this->dispatch('crud-bulk-action', model: $this->model, action: $action, ids: $this->selectedRows);

        $this->selectedRows         = [];
        $this->selectAll            = false;
        $this->bulkActionInProgress = false;
        $this->cacheService->invalidateModel($this->model);
    }
}
