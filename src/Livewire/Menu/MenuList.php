<?php

declare(strict_types=1);

namespace Ptah\Livewire\Menu;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Ptah\Livewire\Concerns\RequiresStructureAccess;
use Ptah\Models\Menu;
use Ptah\Services\Menu\MenuService;
use Ptah\Support\SafeUrl;

#[Layout('ptah::layouts.forge-dashboard')]
class MenuList extends Component
{
    use RequiresStructureAccess;

    /**
     * Livewire runs boot() on the initial mount AND on every later action, so
     * the check covers save/delete as well as the page. This screen used to be
     * open to any authenticated user.
     */
    public function boot(): void
    {
        $this->assertStructureAccess();
    }

    use WithPagination;

    // ── Filtros ────────────────────────────────────────────────────────
    public string $search = '';

    public string $typeFilter = '';            // '' | 'menuLink' | 'menuGroup'

    public string $sort = 'link_order';

    public string $direction = 'asc';

    // ── Modal ──────────────────────────────────────────────────────────
    public bool $showModal = false;

    public bool $isEditing = false;

    public ?int $editingId = null;

    // ── Form fields ────────────────────────────────────────────────
    public string $text = '';

    public string $url = '';

    public string $icon = 'bx bx-circle';

    public string $type = 'menuLink';

    public string $target = '_self';

    public ?int $parent_id = null;

    public int $link_order = 0;

    public bool $is_active = true;

    // ── Delete ─────────────────────────────────────────────────────────
    public ?int $deleteId = null;

    public bool $showDeleteModal = false;

    // ── Validation rules ──────────────────────────────────────────────
    protected function rules(): array
    {
        return [
            'text' => 'required|string|max:255',
            // Escapar nao neutraliza esquema: `javascript:` numa URL de menu
            // executava na sessao de quem clicasse — inclusive a de um master.
            'url' => [
                'nullable', 'string', 'max:2048',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && ! SafeUrl::isSafe($value)) {
                        $fail(trans('ptah::ui.menu_url_unsafe'));
                    }
                },
            ],
            'icon' => 'nullable|string|max:100',
            'type' => 'required|in:menuLink,menuGroup',
            'target' => 'required|in:_self,_blank',
            'parent_id' => [
                'nullable', 'integer', 'exists:menus,id',
                // O formulario ja nao oferece, mas a requisicao pode trazer:
                // pai igual ao proprio item ou a um descendente fecha um ciclo.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value && $this->isEditing && $this->editingId
                        && in_array((int) $value, $this->selfAndDescendants((int) $this->editingId), true)) {
                        $fail(trans('ptah::ui.menu_parent_cycle'));
                    }
                },
            ],
            'link_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    protected $messages = [
        'text.required' => 'The menu text is required.',
        'parent_id.exists' => 'The selected parent group does not exist.',
    ];

    // ── Lifecycle ──────────────────────────────────────────────────────
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    // ── Sort ───────────────────────────────────────────────────────────
    public function sort(string $column): void
    {
        $this->direction = ($this->sort === $column && $this->direction === 'asc') ? 'desc' : 'asc';
        $this->sort = $column;
    }

    // ── CRUD ───────────────────────────────────────────────────────────
    public function create(): void
    {
        $this->reset(['text', 'url', 'icon', 'type', 'target', 'parent_id', 'link_order', 'is_active', 'editingId']);
        $this->icon = 'bx bx-circle';
        $this->type = 'menuLink';
        $this->target = '_self';
        $this->is_active = true;
        $this->isEditing = false;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $menu = Menu::findOrFail($id);
        $this->editingId = $id;
        $this->text = $menu->text;
        $this->url = $menu->url ?? '';
        $this->icon = $menu->icon ?? 'bx bx-circle';
        $this->type = $menu->type;
        $this->target = $menu->target;
        $this->parent_id = $menu->parent_id;
        $this->link_order = $menu->link_order;
        $this->is_active = $menu->is_active;
        $this->isEditing = true;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        try {
            $data = [
                'text' => $this->text,
                'url' => ($this->type === 'menuGroup') ? null : ($this->url ?: null),
                'icon' => $this->icon ?: 'bx bx-circle',
                'type' => $this->type,
                'target' => $this->target,
                'parent_id' => $this->parent_id ?: null,
                'link_order' => $this->link_order,
                'is_active' => $this->is_active,
            ];

            if ($this->isEditing) {
                Menu::findOrFail($this->editingId)->update($data);
                $this->dispatch('ptah-toast', title: "Item \"{$this->text}\" atualizado.", color: 'success');
            } else {
                Menu::create($data);
                $this->dispatch('ptah-toast', title: "Item \"{$this->text}\" criado.", color: 'success');
            }

            app(MenuService::class)->clearCache();
            $this->showModal = false;
            $this->resetPage();

        } catch (\Throwable $e) {
            $this->dispatch('ptah-toast', title: 'Erro ao salvar: '.$e->getMessage(), color: 'danger');
        }
    }

    public function toggleActive(int $id): void
    {
        $menu = Menu::findOrFail($id);
        $menu->update(['is_active' => ! $menu->is_active]);
        app(MenuService::class)->clearCache();
        $this->dispatch('ptah-toast', title: $menu->is_active ? 'Item ativado.' : 'Item desativado.', color: 'success');
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->deleteId) {
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
            $this->dispatch('ptah-toast', title: 'Item deleted.', color: 'success');

        } catch (\Throwable $e) {
            $this->dispatch('ptah-toast', title: 'Erro ao excluir: '.$e->getMessage(), color: 'danger');
        }

        $this->showDeleteModal = false;
        $this->deleteId = null;
        $this->resetPage();
    }

    // ── Select de grupos (para parent_id) ─────────────────────────────
    /**
     * Os grupos que podem ser pai, com o caminho no rotulo (1.60.1).
     *
     * A sidebar passou a desenhar todos os niveis, e o select listava so o
     * texto de cada grupo: "Cadastro" de Fiscal e "Cadastro" de Compras eram
     * indistinguiveis. Agora o rotulo e o caminho ("Fiscal > Cadastro").
     * Editando, o proprio item e os descendentes dele ficam de fora: escolher
     * um deles fecharia um ciclo, e o ramo inteiro sumiria do menu
     * (Menu::nestItems parte da raiz e nunca chega a ele).
     *
     * @return Collection<int, array{id: int, text: non-falsy-string}>
     */
    #[Computed]
    public function groups(): Collection
    {
        $all = Menu::where('type', 'menuGroup')->orderBy('link_order')->orderBy('text')->get(['id', 'text', 'parent_id']);
        $byId = $all->keyBy('id');
        $blocked = $this->isEditing && $this->editingId ? $this->selfAndDescendants((int) $this->editingId) : [];

        return $all
            ->reject(fn (Menu $g) => in_array((int) $g->id, $blocked, true))
            ->map(function (Menu $g) use ($byId): array {
                $names = [$g->text];
                $seen = [(int) $g->id => true];
                $parent = $g->parent_id;
                // `seen` corta um ciclo que ja exista no banco.
                while ($parent !== null && isset($byId[$parent]) && ! isset($seen[(int) $parent])) {
                    $seen[(int) $parent] = true;
                    array_unshift($names, $byId[$parent]->text);
                    $parent = $byId[$parent]->parent_id;
                }

                return ['id' => (int) $g->id, 'text' => implode(' > ', $names)];
            })
            ->sortBy('text', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * O item e todos os que estao abaixo dele, em qualquer nivel.
     *
     * @return list<int>
     */
    private function selfAndDescendants(int $id): array
    {
        $children = Menu::query()->whereNotNull('parent_id')->get(['id', 'parent_id'])->groupBy('parent_id');
        $out = [];
        $queue = [$id];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (in_array($current, $out, true)) {
                continue;
            }
            $out[] = $current;
            foreach ($children->get($current, collect()) as $child) {
                $queue[] = (int) $child->id;
            }
        }

        return $out;
    }

    // ── Query principal ────────────────────────────────────────────────
    public function render()
    {
        $rows = Menu::withoutTrashed()
            ->when($this->search, fn ($q) => $q->where('text', 'like', "%{$this->search}%"))
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->with('parent:id,text')
            ->orderBy($this->sort, $this->direction)
            ->paginate(20);

        return view('ptah::livewire.menu.menu-list', ['rows' => $rows]);
    }
}
