<?php

declare(strict_types=1);

namespace Ptah\Livewire\Company;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Ptah\Models\Company;

#[Layout('ptah::layouts.forge-dashboard')]
class CompanyList extends Component
{
    use WithPagination;

    // ── Lista ──────────────────────────────────────────────────────────
    public string $search     = '';
    public string $sort       = 'name';
    public string $direction  = 'asc';
    public int    $perPage    = 20;

    // ── Modal ──────────────────────────────────────────────────────────
    public bool $showModal  = false;
    public bool $isEditing  = false;
    public ?int $editingId  = null;

    // ── Form fields ─────────────────────────────────────────────────────────
    public string $name       = '';
    public string $label      = '';
    public string $email      = '';
    public string $phone      = '';
    public string $tax_id     = '';
    public string $tax_type   = 'cnpj';
    public bool   $is_default = false;
    public bool   $is_active  = true;
    public array  $address    = [];
    public array  $settings   = [];

    // ── Delete confirmation ────────────────────────────────────────────
    public ?int  $deleteId        = null;
    public bool  $showDeleteModal = false;

    // ── Feedback ──────────────────────────────────────────────────────
    public string $successMsg = '';
    public string $errorMsg   = '';

    protected function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'label'     => [
                'nullable',
                'string',
                'max:4',
                Rule::unique('ptah_companies', 'label')->ignore($this->editingId),
            ],
            'email'     => 'nullable|email|max:255',
            'phone'     => 'nullable|string|max:30',
            'tax_id'    => 'nullable|string|max:50',
            'tax_type'  => 'nullable|in:cnpj,cpf,ein,vat,other',
            'is_active' => 'boolean',
        ];
    }

    // ── Pagination ─────────────────────────────────────────────────────

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort      = $column;
            $this->direction = 'asc';
        }
    }

    // ── CRUD ───────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $company = Company::findOrFail($id);

        $this->editingId  = $id;
        $this->name       = $company->name;
        $this->label      = $company->label ?? '';
        $this->email      = $company->email ?? '';
        $this->phone      = $company->phone ?? '';
        $this->tax_id     = $company->tax_id ?? '';
        $this->tax_type   = $company->tax_type ?? 'cnpj';
        $this->is_default = $company->is_default;
        $this->is_active  = $company->is_active;
        $this->address    = $company->address ?? [];
        $this->settings   = $company->settings ?? [];

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        try {
            $data = [
                'name'       => $this->name,
                'label'      => strtoupper(trim($this->label)) ?: null,
                'email'      => $this->email ?: null,
                'phone'      => $this->phone ?: null,
                'tax_id'     => $this->tax_id ?: null,
                'tax_type'   => $this->tax_type ?: null,
                'is_default' => $this->is_default,
                'is_active'  => $this->is_active,
            ];

            if ($this->isEditing) {
                Company::findOrFail($this->editingId)->update($data);
                $this->flash('Company updated successfully!');
            } else {
                Company::create($data);
                $this->flash('Company created successfully!');
            }

            // Invalidate companies cache
            app(\Ptah\Services\Company\CompanyService::class)->forgetListCache();

            $this->showModal = false;
            $this->resetForm();
        } catch (\Throwable $e) {
            $this->errorMsg = 'Error saving: ' . $e->getMessage();
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
            $company = Company::findOrFail($this->deleteId);

            if ($company->is_default) {
                $this->errorMsg = 'The default company cannot be deleted.';
                $this->showDeleteModal = false;
                return;
            }

            $company->delete();
            $this->flash('Company deleted.');
        } catch (\Throwable $e) {
            $this->errorMsg = 'Error deleting: ' . $e->getMessage();
        }

        $this->showDeleteModal = false;
        $this->deleteId = null;
    }

    // ── Helpers ────────────────────────────────────────────────────────

    protected function resetForm(): void
    {
        $this->editingId  = null;
        $this->name       = '';
        $this->label      = '';
        $this->email      = '';
        $this->phone      = '';
        $this->tax_id     = '';
        $this->tax_type   = 'cnpj';
        $this->is_default = false;
        $this->is_active  = true;
        $this->address    = [];
        $this->settings   = [];
        $this->resetValidation();
    }

    protected function flash(string $message): void
    {
        $this->successMsg = $message;
    }

    // ── Render ─────────────────────────────────────────────────────────

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return Company::query()
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('name', 'like', "%{$this->search}%")
                   ->orWhere('email', 'like', "%{$this->search}%")
                   ->orWhere('tax_id', 'like', "%{$this->search}%");
            }))
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('ptah::livewire.company.company-list', [
            'rows' => $this->rows,
        ]);
    }
}
