<?php

declare(strict_types=1);

namespace Ptah\Livewire\Permission;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Ptah\Models\Department;

#[Layout('ptah::layouts.forge-dashboard')]
class DepartmentList extends Component
{
    use WithPagination;

    public string $search    = '';
    public string $sort      = 'name';
    public string $direction = 'asc';

    public bool  $showModal = false;
    public bool  $isEditing = false;
    public ?int  $editingId = null;

    public string $name        = '';
    public string $description = '';
    public bool   $is_active   = true;

    public ?int  $deleteId        = null;
    public bool  $showDeleteModal = false;

    public string $successMsg = '';
    public string $errorMsg   = '';

    protected function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ];
    }

    public function updatingSearch(): void { $this->resetPage(); }

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
        $this->editingId    = $id;
        $this->name         = $dept->name;
        $this->description  = $dept->description ?? '';
        $this->is_active    = $dept->is_active;
        $this->isEditing    = true;
        $this->showModal    = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        try {
            $data = [
                'name'        => $this->name,
                'description' => $this->description ?: null,
                'is_active'   => $this->is_active,
            ];

            if ($this->isEditing) {
                Department::findOrFail($this->editingId)->update($data);
                $this->successMsg = 'Department updated.';
            } else {
                Department::create($data);
                $this->successMsg = 'Department created.';
            }

            $this->showModal = false;
        } catch (\Throwable $e) {
            $this->errorMsg = 'Error: ' . $e->getMessage();
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId        = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        try {
            Department::findOrFail($this->deleteId)->delete();
            $this->successMsg = 'Department deleted.';
        } catch (\Throwable $e) {
            $this->errorMsg = 'Error: ' . $e->getMessage();
        }

        $this->showDeleteModal = false;
        $this->deleteId = null;
    }

    public function getRowsProperty(): LengthAwarePaginator
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
