<?php

declare(strict_types=1);

namespace Ptah\Livewire\Permission;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Ptah\Livewire\Permission\Concerns\RequiresMasterAccess;
use Ptah\Models\Department;

#[Layout('ptah::layouts.forge-dashboard')]
class DepartmentList extends Component
{
    use RequiresMasterAccess;
    use WithPagination;

    public function boot(): void
    {
        $this->assertMasterAccess();
    }

    public string $search = '';

    public string $sort = 'name';

    public string $direction = 'asc';

    public bool $showModal = false;

    public bool $isEditing = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    public ?int $deleteId = null;

    public bool $showDeleteModal = false;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        $this->direction = ($this->sort === $column && $this->direction === 'asc') ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function create(): void
    {
        $this->reset(['name', 'description', 'is_active', 'editingId']);
        $this->is_active = true;
        $this->isEditing = false;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $dept = Department::findOrFail($id);
        $this->editingId = $id;
        $this->name = $dept->name;
        $this->description = $dept->description ?? '';
        $this->is_active = $dept->is_active;
        $this->isEditing = true;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        try {
            $data = [
                'name' => $this->name,
                'description' => $this->description ?: null,
                'is_active' => $this->is_active,
            ];

            if ($this->isEditing) {
                Department::findOrFail($this->editingId)->update($data);
                $this->dispatch('ptah-toast', title: 'Department updated.', color: 'success');
            } else {
                Department::create($data);
                $this->dispatch('ptah-toast', title: 'Department created.', color: 'success');
            }

            $this->showModal = false;
        } catch (\Throwable $e) {
            $this->dispatch('ptah-toast', title: 'Error: '.$e->getMessage(), color: 'danger');
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        try {
            Department::findOrFail($this->deleteId)->delete();
            $this->dispatch('ptah-toast', title: 'Department deleted.', color: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('ptah-toast', title: 'Error: '.$e->getMessage(), color: 'danger');
        }

        $this->showDeleteModal = false;
        $this->deleteId = null;
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return Department::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->withCount('roles')
            ->orderBy($this->sort, $this->direction)
            ->paginate(20);
    }

    public function render()
    {
        return view('ptah::livewire.permission.department-list', [
            'rows' => $this->rows,
        ]);
    }
}
