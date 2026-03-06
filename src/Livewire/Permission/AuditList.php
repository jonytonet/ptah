<?php

declare(strict_types=1);

namespace Ptah\Livewire\Permission;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Ptah\Models\PermissionAudit;

#[Layout('ptah::layouts.forge-dashboard')]
class AuditList extends Component
{
    use WithPagination;

    public string $search      = '';
    public string $filterResult = '';  // '' | 'granted' | 'denied'
    public string $filterAction = '';  // '' | 'create' | 'read' | 'update' | 'delete'
    public string $dateFrom    = '';
    public string $dateTo      = '';
    public int    $perPage     = 50;

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingFilterResult(): void { $this->resetPage(); }
    public function updatingFilterAction(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filterResult', 'filterAction', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return PermissionAudit::query()
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('resource_key', 'like', "%{$this->search}%")
                   ->orWhere('user_id', $this->search)
                   ->orWhere('ip_address', 'like', "%{$this->search}%");
            }))
            ->when($this->filterResult, fn ($q) => $q->where('result', $this->filterResult))
            ->when($this->filterAction, fn ($q) => $q->where('action', $this->filterAction))
            ->when($this->dateFrom, fn ($q) => $q->where('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn ($q) => $q->where('created_at', '<=', $this->dateTo . ' 23:59:59'))
            ->latest('created_at')
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('ptah::livewire.permission.audit-list', [
            'rows' => $this->rows,
        ]);
    }
}
