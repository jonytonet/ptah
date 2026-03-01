<?php

declare(strict_types=1);

namespace Ptah\Livewire\Menu;

use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Ptah\Models\Menu;
use Ptah\Services\Menu\MenuService;

#[Layout('ptah::layouts.forge-dashboard')]
class MenuList extends Component
{
    use WithPagination;

    // ── Filtros ────────────────────────────────────────────────────────
    public string $search     = '';
    public string $typeFilter = '';            // '' | 'menuLink' | 'menuGroup'
    public string $sort       = 'link_order';
    public string $direction  = 'asc';

    // ── Modal ──────────────────────────────────────────────────────────
    public bool  $showModal = false;
    public bool  $isEditing = false;
    public ?int  $editingId = null;

    // ── Campos do formulário ───────────────────────────────────────────
    public string $text       = '';
    public string $url        = '';
    public string $icon       = 'bx bx-circle';
    public string $type       = 'menuLink';
    public string $target     = '_self';
    public ?int   $parent_id  = null;
    public int    $link_order = 0;
    public bool   $is_active  = true;

    // ── Delete ─────────────────────────────────────────────────────────
    public ?int  $deleteId        = null;
    public bool  $showDeleteModal = false;

    // ── Feedback ───────────────────────────────────────────────────────
    public string $successMsg = '';
    public string $errorMsg   = '';

    // ── Regras de validação ────────────────────────────────────────────
    protected function rules(): array
    {
        return [
            'text'       => 'required|string|max:255',
            'url'        => 'nullable|string|max:2048',
            'icon'       => 'nullable|string|max:100',
            'type'       => 'required|in:menuLink,menuGroup',
            'target'     => 'required|in:_self,_blank',
            'parent_id'  => 'nullable|integer|exists:menus,id',
            'link_order' => 'integer|min:0',
            'is_active'  => 'boolean',
        ];
    }

    protected $messages = [
        'text.required'    => 'O texto do menu é obrigatório.',
        'parent_id.exists' => 'O grupo pai selecionado não existe.',
    ];

    // ── Lifecycle ──────────────────────────────────────────────────────
    public function updatingSearch(): void  { $this->resetPage(); }
    public function updatingTypeFilter(): void { $this->resetPage(); }

    // ── Sort ───────────────────────────────────────────────────────────
    public function sort(string $column): void
    {
        $this->direction = ($this->sort === $column && $this->direction === 'asc') ? 'desc' : 'asc';
        $this->sort      = $column;
    }

    // ── CRUD ───────────────────────────────────────────────────────────
    public function create(): void
    {
        $this->reset(['text', 'url', 'icon', 'type', 'target', 'parent_id', 'link_order', 'is_active', 'editingId', 'errorMsg']);
        $this->icon      = 'bx bx-circle';
        $this->type      = 'menuLink';
        $this->target    = '_self';
        $this->is_active = true;
        $this->isEditing = false;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $menu = Menu::findOrFail($id);
        $this->editingId    = $id;
        $this->text         = $menu->text;
        $this->url          = $menu->url ?? '';
        $this->icon         = $menu->icon ?? 'bx bx-circle';
        $this->type         = $menu->type;
        $this->target       = $menu->target;
        $this->parent_id    = $menu->parent_id;
        $this->link_order   = $menu->link_order;
        $this->is_active    = $menu->is_active;
        $this->isEditing    = true;
        $this->showModal    = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        try {
            $data = [
                'text'       => $this->text,
                'url'        => ($this->type === 'menuGroup') ? null : ($this->url ?: null),
                'icon'       => $this->icon ?: 'bx bx-circle',
                'type'       => $this->type,
                'target'     => $this->target,
                'parent_id'  => $this->parent_id ?: null,
                'link_order' => $this->link_order,
                'is_active'  => $this->is_active,
            ];

            if ($this->isEditing) {
                Menu::findOrFail($this->editingId)->update($data);
                $this->successMsg = "Item \"{$this->text}\" atualizado.";
            } else {
                Menu::create($data);
                $this->successMsg = "Item \"{$this->text}\" criado.";
            }

            app(MenuService::class)->clearCache();
            $this->showModal = false;
            $this->resetPage();

        } catch (\Throwable $e) {
            $this->errorMsg = 'Erro ao salvar: ' . $e->getMessage();
        }
    }

    public function toggleActive(int $id): void
    {
        $menu = Menu::findOrFail($id);
        $menu->update(['is_active' => !$menu->is_active]);
        app(MenuService::class)->clearCache();
        $this->successMsg = $menu->is_active ? 'Item ativado.' : 'Item desativado.';
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId        = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (!$this->deleteId) {
            return;
        }

        try {
            $menu = Menu::findOrFail($this->deleteId);

            // Desvincula filhos antes de excluir o grupo
            if ($menu->type === 'menuGroup') {
                Menu::where('parent_id', $menu->id)->update(['parent_id' => null]);
            }

            $menu->delete();
            app(MenuService::class)->clearCache();
            $this->successMsg = "Item excluído.";

        } catch (\Throwable $e) {
            $this->errorMsg = 'Erro ao excluir: ' . $e->getMessage();
        }

        $this->showDeleteModal = false;
        $this->deleteId        = null;
        $this->resetPage();
    }

    // ── Select de grupos (para parent_id) ─────────────────────────────
    public function getGroupsProperty(): Collection
    {
        $query = Menu::where('type', 'menuGroup')->orderBy('link_order')->orderBy('text');

        if ($this->isEditing && $this->editingId) {
            $query->where('id', '!=', $this->editingId);
        }

        return $query->get(['id', 'text']);
    }

    // ── Query principal ────────────────────────────────────────────────
    public function render()
    {
        $rows = Menu::withoutTrashed()
            ->when($this->search, fn($q) => $q->where('text', 'like', "%{$this->search}%"))
            ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
            ->with('parent:id,text')
            ->orderBy($this->sort, $this->direction)
            ->paginate(20);

        return view('ptah::livewire.menu.menu-list', ['rows' => $rows]);
    }
}
