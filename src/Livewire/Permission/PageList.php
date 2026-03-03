<?php

declare(strict_types=1);

namespace Ptah\Livewire\Permission;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;

#[Layout('ptah::layouts.forge-dashboard')]
class PageList extends Component
{
    use WithPagination;

    // ── Pages ─────────────────────────────────────────────────────────
    public string $search    = '';
    public string $sort      = 'name';
    public string $direction = 'asc';

    public bool  $showPageModal = false;
    public bool  $isEditingPage = false;
    public ?int  $editingPageId = null;
    public string $page_slug      = '';
    public string $page_name      = '';
    public string $page_description = '';
    public string $page_route     = '';
    public string $page_icon      = '';
    public bool   $page_is_active = true;
    public int    $page_sort_order = 0;

    // ── Selected page objects ──────────────────────────────────────────
    public ?int  $selectedPageId   = null;
    public string $selectedPageName = '';
    public string $objSearch       = '';

    public bool  $showObjModal  = false;
    public bool  $isEditingObj  = false;
    public ?int  $editingObjId  = null;
    public string $obj_section   = 'main';
    public string $obj_key       = '';
    public string $obj_label     = '';
    public string $obj_type      = 'button';
    public int    $obj_order     = 0;
    public bool   $obj_is_active = true;

    // ── Confirmations ─────────────────────────────────────────────────────
    public ?int  $deletePageId    = null;
    public ?int  $deleteObjId     = null;
    public bool  $showDeleteModal = false;
    public string $deleteTarget   = ''; // 'page' | 'obj'

    public string $successMsg = '';
    public string $errorMsg   = '';

    protected function pageRules(): array
    {
        return [
            'page_slug'       => 'required|string|max:100',
            'page_name'       => 'required|string|max:255',
            'page_description'=> 'nullable|string|max:500',
            'page_route'      => 'nullable|string|max:255',
            'page_icon'       => 'nullable|string|max:100',
            'page_is_active'  => 'boolean',
            'page_sort_order' => 'integer|min:0',
        ];
    }

    protected function objRules(): array
    {
        return [
            'obj_section'   => 'required|string|max:100',
            'obj_key'       => 'required|string|max:255',
            'obj_label'     => 'required|string|max:255',
            'obj_type'      => 'required|in:' . implode(',', PageObject::TYPES),
            'obj_order'     => 'integer|min:0',
            'obj_is_active' => 'boolean',
        ];
    }

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingObjSearch(): void { $this->resetPage(); }

    // ── Pages CRUD ────────────────────────────────────────────────────

    public function createPage(): void
    {
        $this->reset(['page_slug','page_name','page_description','page_route','page_icon','editingPageId']);
        $this->page_is_active  = true;
        $this->page_sort_order = 0;
        $this->isEditingPage   = false;
        $this->showPageModal   = true;
        $this->resetValidation();
    }

    public function editPage(int $id): void
    {
        $page = PtahPage::findOrFail($id);
        $this->editingPageId     = $id;
        $this->page_slug         = $page->slug;
        $this->page_name         = $page->name;
        $this->page_description  = $page->description ?? '';
        $this->page_route        = $page->route ?? '';
        $this->page_icon         = $page->icon ?? '';
        $this->page_is_active    = $page->is_active;
        $this->page_sort_order   = $page->sort_order;
        $this->isEditingPage     = true;
        $this->showPageModal     = true;
        $this->resetValidation();
    }

    public function savePage(): void
    {
        $this->validate($this->pageRules());

        try {
            $data = [
                'slug'        => $this->page_slug,
                'name'        => $this->page_name,
                'description' => $this->page_description ?: null,
                'route'       => $this->page_route ?: null,
                'icon'        => $this->page_icon ?: null,
                'is_active'   => $this->page_is_active,
                'sort_order'  => $this->page_sort_order,
            ];

            if ($this->isEditingPage) {
                PtahPage::findOrFail($this->editingPageId)->update($data);
                $this->successMsg = 'Page updated.';
            } else {
                PtahPage::create($data);
                $this->successMsg = 'Page created.';
            }

            $this->showPageModal = false;
        } catch (\Throwable $e) {
            $this->errorMsg = 'Erro: ' . $e->getMessage();
        }
    }

    public function selectPage(int $id, string $name): void
    {
        $this->selectedPageId   = $id;
        $this->selectedPageName = $name;
        $this->objSearch        = '';
        $this->resetPage();
    }

    // ── CRUD de Objetos ────────────────────────────────────────────────

    public function createObj(): void
    {
        $this->reset(['obj_section','obj_key','obj_label','obj_type','obj_order','editingObjId']);
        $this->obj_section   = 'main';
        $this->obj_type      = 'button';
        $this->obj_order     = 0;
        $this->obj_is_active = true;
        $this->isEditingObj  = false;
        $this->showObjModal  = true;
        $this->resetValidation();
    }

    public function editObj(int $id): void
    {
        $obj = PageObject::findOrFail($id);
        $this->editingObjId  = $id;
        $this->obj_section   = $obj->section;
        $this->obj_key       = $obj->obj_key;
        $this->obj_label     = $obj->obj_label;
        $this->obj_type      = $obj->obj_type;
        $this->obj_order     = $obj->obj_order;
        $this->obj_is_active = $obj->is_active;
        $this->isEditingObj  = true;
        $this->showObjModal  = true;
        $this->resetValidation();
    }

    public function saveObj(): void
    {
        $this->validate($this->objRules());

        try {
            $data = [
                'page_id'    => $this->selectedPageId,
                'section'    => $this->obj_section,
                'obj_key'    => $this->obj_key,
                'obj_label'  => $this->obj_label,
                'obj_type'   => $this->obj_type,
                'obj_order'  => $this->obj_order,
                'is_active'  => $this->obj_is_active,
            ];

            if ($this->isEditingObj) {
                PageObject::findOrFail($this->editingObjId)->update($data);
                $this->successMsg = 'Object updated.';
            } else {
                PageObject::create($data);
                $this->successMsg = 'Object created.';
            }

            $this->showObjModal = false;
        } catch (\Throwable $e) {
            $this->errorMsg = 'Erro: ' . $e->getMessage();
        }
    }

    // ── Deletions ──────────────────────────────────────────────────────

    public function confirmDeletePage(int $id): void
    {
        $this->deletePageId    = $id;
        $this->deleteTarget    = 'page';
        $this->showDeleteModal = true;
    }

    public function confirmDeleteObj(int $id): void
    {
        $this->deleteObjId     = $id;
        $this->deleteTarget    = 'obj';
        $this->showDeleteModal = true;
    }

    public function deleteConfirmed(): void
    {
        try {
            if ($this->deleteTarget === 'page') {
                PtahPage::findOrFail($this->deletePageId)->delete();
                $this->successMsg = 'Page deleted.';
                if ($this->selectedPageId === $this->deletePageId) {
                    $this->selectedPageId = null;
                }
            } elseif ($this->deleteTarget === 'obj') {
                PageObject::findOrFail($this->deleteObjId)->delete();
                $this->successMsg = 'Object deleted.';
            }
        } catch (\Throwable $e) {
            $this->errorMsg = 'Erro: ' . $e->getMessage();
        }

        $this->showDeleteModal = false;
    }

    // ── Render ─────────────────────────────────────────────────────────

    public function getPageRowsProperty(): LengthAwarePaginator
    {
        return PtahPage::query()
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('name', 'like', "%{$this->search}%")
                   ->orWhere('slug', 'like', "%{$this->search}%");
            }))
            ->withCount('pageObjects')
            ->orderBy($this->sort, $this->direction)
            ->paginate(20);
    }

    public function getObjRowsProperty(): LengthAwarePaginator
    {
        return PageObject::query()
            ->where('page_id', $this->selectedPageId)
            ->when($this->objSearch, fn ($q) => $q->where(function ($q2) {
                $q2->where('obj_key', 'like', "%{$this->objSearch}%")
                   ->orWhere('obj_label', 'like', "%{$this->objSearch}%");
            }))
            ->orderBy('obj_order')
            ->paginate(20);
    }

    public function render()
    {
        return view('ptah::livewire.permission.page-list', [
            'pageRows' => $this->pageRows,
            'objRows'  => $this->selectedPageId ? $this->objRows : null,
            'objTypes' => PageObject::TYPES,
        ]);
    }
}
