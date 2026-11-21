<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\PaginationState;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Ptah\Http\Headless\HeadlessMenuList;
use Ptah\Http\Headless\HeadlessSettingsPage;
use Ptah\Models\Menu;
use Ptah\Models\Setting;

/**
 * The menu editor (/ptah-menu) and the system settings (/ptah-settings), for
 * the React and Vue stacks.
 *
 * Each action runs the Livewire screen as a plain object (Ptah\Http\Headless\*):
 * the structure-access gate in boot(), the validation rules (the SafeUrl check
 * on a menu URL, the typed rules of each setting), the queries and the writes
 * are the components' own code. This controller only carries the request in
 * and a whitelisted answer out. Nothing survives between two requests, so the
 * state the Livewire screen keeps between clicks (the item being edited, the
 * settings scope) travels in each request.
 */
class MenuApiController extends Controller
{
    /** The columns the Livewire table lets the user sort by. */
    private const SORTABLE = ['text', 'link_order'];

    private const TYPES = ['menuLink', 'menuGroup'];

    // ── Menu ────────────────────────────────────────────────────────────────

    /** The paginated list: `search`, `type`, `sort`, `direction`, `page`. */
    public function index(Request $request): JsonResponse
    {
        $page = HeadlessMenuList::headless();
        $this->applyListState($page, $request);

        $number = max(1, (int) $request->query('page', '1'));
        // WithPagination resolves the page through Livewire's hooks, which a
        // plain object does not have: point the paginator at the request's
        // page for this one query, then hand the resolver back.
        Paginator::currentPageResolver(fn () => $number);

        try {
            /** @var LengthAwarePaginator<int, Menu> $rows */
            $rows = $page->render()->getData()['rows'];
        } finally {
            PaginationState::resolveUsing(app());
        }

        return response()->json([
            'data' => array_map(fn (Menu $menu) => $this->menuRow($menu), $rows->items()),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'state' => [
                'search' => $page->search,
                'type' => $page->typeFilter,
                'sort' => $page->sort,
                'direction' => $page->direction,
            ],
            'groups' => $this->groups($page),
        ]);
    }

    /** One item, as the edit modal opens it, with the groups it may move under (never itself). */
    public function show(int $id): JsonResponse
    {
        $page = HeadlessMenuList::headless();
        $page->run('edit', ['id' => $id]);

        return response()->json(['data' => $this->form($page), 'groups' => $this->groups($page)]);
    }

    public function store(Request $request): JsonResponse
    {
        $page = HeadlessMenuList::headless();
        $page->run('create');
        $this->fill($page, $request);
        $page->run('save');

        return $this->menuAnswer($page);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // edit() first: it loads the stored values and puts the component in
        // "editing" mode, as the click on the pencil does.
        $page = HeadlessMenuList::headless();
        $page->run('edit', ['id' => $id]);
        $this->fill($page, $request);
        $page->run('save');

        return $this->menuAnswer($page);
    }

    public function toggle(int $id): JsonResponse
    {
        $page = HeadlessMenuList::headless();
        $page->run('toggleActive', ['id' => $id]);

        $menu = Menu::query()->find($id);

        return $this->menuAnswer($page, ['is_active' => $menu?->is_active]);
    }

    public function destroy(int $id): JsonResponse
    {
        // The two steps of the confirmation modal, in one request.
        $page = HeadlessMenuList::headless();
        $page->run('confirmDelete', ['id' => $id]);
        $page->run('delete');

        return $this->menuAnswer($page);
    }

    // ── Settings ────────────────────────────────────────────────────────────

    /** The declared settings by group, for `scope` company (default) or global. */
    public function settings(Request $request): JsonResponse
    {
        return response()->json($this->settingsPayload($this->settingsPage($request)));
    }

    /** `values` (key => value) saved in `scope`. Only declared keys are kept, by the page itself. */
    public function saveSettings(Request $request): JsonResponse
    {
        $page = $this->settingsPage($request);

        if ($refusal = $this->notInstalled()) {
            return $refusal;
        }

        $incoming = $request->input('values');
        // Over the loaded values, as the Livewire form posts every field:
        // a key the client left out keeps its value (a secret stays blank,
        // which the page reads as "unchanged").
        $page->values = array_merge($page->values, is_array($incoming) ? $incoming : []);
        $page->run('save');

        $ok = $page->errorsBag === [];

        return response()->json([
            'ok' => $ok,
            'errors' => (object) $page->errorsBag,
            'messages' => $page->toasts(),
            'settings' => $this->settingsPayload($page),
        ], $ok ? 200 : 422);
    }

    /** Drops the company's own value of `key`, back to the global one. */
    public function resetSetting(Request $request, string $key): JsonResponse
    {
        $page = $this->settingsPage($request);

        if ($refusal = $this->notInstalled()) {
            return $refusal;
        }

        $page->run('resetToGlobal', ['key' => $key]);

        return response()->json(['ok' => true, 'messages' => $page->toasts(), 'settings' => $this->settingsPayload($page)]);
    }

    // ── Helpers: menu ───────────────────────────────────────────────────────

    /**
     * The Livewire table only offers these values; its public properties
     * could be forged into any column name for orderBy(). Here a value
     * outside the choices is refused instead of reaching the query.
     */
    private function applyListState(HeadlessMenuList $page, Request $request): void
    {
        $sort = (string) $request->query('sort', 'link_order');
        $direction = strtolower((string) $request->query('direction', 'asc'));
        $type = (string) $request->query('type', '');
        $errors = [];

        if (! in_array($sort, self::SORTABLE, true)) {
            $errors['sort'] = 'The sort column must be one of: '.implode(', ', self::SORTABLE).'.';
        }
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $errors['direction'] = 'The direction must be asc or desc.';
        }
        if ($type !== '' && ! in_array($type, self::TYPES, true)) {
            $errors['type'] = 'The type must be menuLink or menuGroup.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $page->search = mb_substr((string) $request->query('search', ''), 0, 255);
        $page->typeFilter = $type;
        $page->sort = $sort;
        $page->direction = $direction;
    }

    /** The form fields from the request; a field the request does not carry keeps its value. */
    private function fill(HeadlessMenuList $page, Request $request): void
    {
        foreach (['text', 'url', 'icon', 'type', 'target'] as $field) {
            if ($request->has($field)) {
                $page->{$field} = (string) $request->input($field, '');
            }
        }

        if ($request->has('parent_id')) {
            $parent = $request->input('parent_id');
            // Not a number is not a group: -1 fails `exists` like any unknown id,
            // so the answer is the component's own 422 on parent_id.
            $page->parent_id = $parent === null || $parent === '' ? null : (is_numeric($parent) ? (int) $parent : -1);
        }
        if ($request->has('link_order')) {
            $page->link_order = (int) $request->input('link_order');
        }
        if ($request->has('is_active')) {
            $page->is_active = $request->boolean('is_active');
        }
    }

    /** @return array<string, mixed> */
    private function form(HeadlessMenuList $page): array
    {
        return [
            'id' => $page->editingId,
            'text' => $page->text,
            'url' => $page->url,
            'icon' => $page->icon,
            'type' => $page->type,
            'target' => $page->target,
            'parent_id' => $page->parent_id,
            'link_order' => $page->link_order,
            'is_active' => $page->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function menuRow(Menu $menu): array
    {
        // Eager loaded by the component's query (`parent:id,text`).
        $parent = $menu->parent;

        return [
            'id' => $menu->id,
            'text' => $menu->text,
            'url' => $menu->url,
            'icon' => $menu->icon ?: 'bx bx-circle',
            'type' => $menu->type,
            'target' => $menu->target,
            'parent_id' => $menu->parent_id,
            'parent' => $parent instanceof Menu ? $parent->text : null,
            'link_order' => $menu->link_order,
            'is_active' => $menu->is_active,
        ];
    }

    /** @return list<array{id: int, text: string}> */
    private function groups(HeadlessMenuList $page): array
    {
        // Ja vem como {id, text}, com o caminho no texto e sem o proprio item
        // nem os descendentes dele (1.60.1) — a mesma lista da tela Livewire.
        return $page->groups()->values()->all();
    }

    /**
     * save() and delete() report a failure with a danger toast, not an
     * exception: that toast is the refusal.
     *
     * @param  array<string, mixed>  $extra
     */
    private function menuAnswer(HeadlessMenuList $page, array $extra = []): JsonResponse
    {
        $messages = $page->toasts();
        $failure = null;

        foreach ($messages as $message) {
            if ($message['level'] === 'danger') {
                $failure = $message['text'];
            }
        }

        return response()->json(['ok' => $failure === null, 'error' => $failure, 'messages' => $messages] + $extra, $failure === null ? 200 : 422);
    }

    // ── Helpers: settings ───────────────────────────────────────────────────

    /**
     * The page in the scope the request asks for. The page's editGlobal()
     * decides who may edit the global values; when it refuses, the request
     * is refused too, instead of silently saving into the company's values.
     */
    private function settingsPage(Request $request): HeadlessSettingsPage
    {
        // First: boot() is the access gate, before anything of the input is read.
        $page = HeadlessSettingsPage::headless();
        $scope = $request->input('scope');

        if ($scope !== null && $scope !== '' && ! in_array($scope, ['company', 'global'], true)) {
            throw ValidationException::withMessages(['scope' => 'The scope must be company or global.']);
        }

        if ($scope === 'company' || $scope === 'global') {
            $page->run('editGlobal', ['global' => $scope === 'global']);
            abort_if($scope === 'global' && ! $page->editingGlobal, 403, trans('ptah::ui.permission_denied'));
        }

        return $page;
    }

    /**
     * What the settings screen draws. The values come from the page (a
     * secret is already blanked there); of `sources` only where each value
     * comes from is sent — it also holds the raw values, secrets included.
     *
     * @return array<string, mixed>
     */
    private function settingsPayload(HeadlessSettingsPage $page): array
    {
        $view = app()->call([$page, 'render']);
        $data = $view->getData();
        $groups = [];

        foreach ((array) $data['groups'] as $name => $defs) {
            $fields = [];
            foreach ((array) $defs as $key => $def) {
                $type = (string) ($def['type'] ?? 'text');
                $options = [];
                foreach ((array) ($def['options'] ?? []) as $value => $label) {
                    $options[] = ['value' => (string) $value, 'label' => (string) $label];
                }

                $fields[] = [
                    'key' => (string) $key,
                    'label' => (string) ($def['label'] ?? $key),
                    'type' => $type,
                    'help' => isset($def['help']) && $def['help'] !== '' ? (string) $def['help'] : null,
                    'secret' => ! empty($def['secret']),
                    'options' => $options,
                    'value' => $page->values[$key] ?? null,
                    'source' => (string) ($data['sources'][$key]['source'] ?? 'default'),
                ];
            }
            $groups[] = ['name' => (string) $name, 'fields' => $fields];
        }

        return [
            'installed' => (bool) $data['installed'],
            'scope' => $page->editingGlobal ? 'global' : 'company',
            'per_company' => (bool) $data['perCompany'],
            'can_edit_global' => (bool) $data['canEditGlobal'],
            'groups' => $groups,
        ];
    }

    /** Without the table there is nothing to write to: the screen explains how to install. */
    private function notInstalled(): ?JsonResponse
    {
        if (Setting::tableExists()) {
            return null;
        }

        return response()->json(['ok' => false, 'installed' => false, 'error' => trans('ptah::ui.settings_not_installed')], 409);
    }
}
