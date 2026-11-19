<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers\Admin;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\PaginationState;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Ptah\Http\Headless\HeadlessPageList;
use Ptah\Http\Headless\HeadlessPermissionGuide;
use Ptah\Http\Headless\HeadlessRoleList;
use Ptah\Models\Department;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Support\PtahVersion;

/**
 * The access-control screens — roles and their permission matrix, pages and
 * their objects, the permission guide — for the React and Vue stacks (1.59.0).
 *
 * Each action runs the Livewire component (RoleList, PageList,
 * PermissionGuide) as a plain object: the MASTER guard in their boot(), the
 * validation rules, RoleService's "only one MASTER" and "MASTER cannot be
 * deleted/deactivated" rules are the same code. Nothing survives between two
 * requests, so every request first puts the component in the state the screen
 * would be in (the record being edited, the selected page) and then runs the
 * action the button would run.
 */
class RolesApiController extends Controller
{
    private const FLAGS = ['can_create', 'can_read', 'can_update', 'can_delete'];

    // ── Roles ───────────────────────────────────────────────────────────────

    public function roles(Request $request): JsonResponse
    {
        $page = HeadlessRoleList::headless();
        $page->search = (string) $request->query('search', '');

        $rows = $this->paginate(fn () => $page->rows(), $request->integer('page', 1));

        return response()->json([
            'data' => array_map(fn (Role $role) => $this->roleRow($role), $rows->items()),
            'meta' => $this->meta($rows),
            // The options of the form's department select.
            'departments' => $page->departments()->map(fn (Department $department) => ['id' => $department->id, 'name' => $department->name])->values(),
        ]);
    }

    /** The role as the edit form loads it. */
    public function role(int $role): JsonResponse
    {
        $page = HeadlessRoleList::headless();
        $page->run('edit', ['id' => $role]);

        return response()->json(['data' => $this->roleForm($page)]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $page = HeadlessRoleList::headless();
        $page->run('create');
        $this->fillRole($page, $request);
        $page->run('save');

        return $this->answer($page);
    }

    public function updateRole(Request $request, int $role): JsonResponse
    {
        $page = HeadlessRoleList::headless();
        // Loads the record exactly as the edit button does (404 when it is gone).
        $page->run('edit', ['id' => $role]);
        $this->fillRole($page, $request);
        $page->run('save');

        return $this->answer($page);
    }

    public function destroyRole(int $role): JsonResponse
    {
        $page = HeadlessRoleList::headless();
        $page->run('confirmDelete', ['id' => $role]);
        $page->run('delete');

        return $this->answer($page);
    }

    /** The permission matrix of a role: every active page object with the four flags. */
    public function permissions(int $role): JsonResponse
    {
        $page = HeadlessRoleList::headless();
        $page->run('openBind', ['roleId' => $role]);

        return response()->json([
            'role' => ['id' => $page->bindingRoleId, 'name' => $page->bindingRoleName],
            'data' => array_map(fn (array $object) => $this->bindRow($object), array_values($page->bindObjects)),
        ]);
    }

    /**
     * Saves the matrix. `objects` is a list of {id, can_create, can_read,
     * can_update, can_delete}.
     */
    public function savePermissions(Request $request, int $role): JsonResponse
    {
        $page = HeadlessRoleList::headless();
        // The rows come from the server, as when the modal opens: only the
        // objects the screen would list can be bound. An id the client made up
        // (an inactive or unknown object) matches no row and is ignored.
        $page->run('openBind', ['roleId' => $role]);

        $sent = [];
        foreach ((array) $request->input('objects', []) as $item) {
            if (is_array($item) && isset($item['id']) && is_numeric($item['id'])) {
                $sent[(int) $item['id']] = $item;
            }
        }

        $page->bindObjects = array_map(function (array $object) use ($sent): array {
            $flags = $sent[(int) $object['id']] ?? null;

            // A row the client did not send keeps what the role already has.
            if ($flags !== null) {
                foreach (self::FLAGS as $flag) {
                    $object[$flag] = filter_var($flags[$flag] ?? false, FILTER_VALIDATE_BOOLEAN);
                }
            }

            return $object;
        }, $page->bindObjects);

        $page->run('saveBind');

        return $this->answer($page);
    }

    // ── Pages and their objects ─────────────────────────────────────────────

    public function pages(Request $request): JsonResponse
    {
        $page = HeadlessPageList::headless();
        $page->search = (string) $request->query('search', '');

        $rows = $this->paginate(fn () => $page->pageRows(), $request->integer('page', 1));

        return response()->json([
            'data' => array_map(fn (PtahPage $row) => $this->pageRow($row), $rows->items()),
            'meta' => $this->meta($rows),
            'types' => PageObject::TYPES,
        ]);
    }

    public function storePage(Request $request): JsonResponse
    {
        $page = HeadlessPageList::headless();
        $page->run('createPage');
        $this->fillPage($page, $request);
        $page->run('savePage');

        return $this->answer($page);
    }

    public function updatePage(Request $request, int $ptahPage): JsonResponse
    {
        $page = HeadlessPageList::headless();
        $page->run('editPage', ['id' => $ptahPage]);
        $this->fillPage($page, $request);
        $page->run('savePage');

        return $this->answer($page);
    }

    public function destroyPage(int $ptahPage): JsonResponse
    {
        $page = HeadlessPageList::headless();
        $page->run('confirmDeletePage', ['id' => $ptahPage]);
        $page->run('deleteConfirmed');

        return $this->answer($page);
    }

    /** The objects of one page — the right-hand column after a page is clicked. */
    public function objects(Request $request, int $ptahPage): JsonResponse
    {
        $page = $this->selecting($ptahPage);
        $page->objSearch = (string) $request->query('search', '');

        $rows = $this->paginate(fn () => $page->objRows(), $request->integer('page', 1));

        return response()->json([
            'page' => ['id' => $page->selectedPageId, 'name' => $page->selectedPageName],
            'data' => array_map(fn (PageObject $row) => $this->objectRow($row), $rows->items()),
            'meta' => $this->meta($rows),
        ]);
    }

    public function storeObject(Request $request, int $ptahPage): JsonResponse
    {
        $page = $this->selecting($ptahPage);
        $page->run('createObj');
        $this->fillObject($page, $request);
        $page->run('saveObj');

        return $this->answer($page);
    }

    public function updateObject(Request $request, int $ptahPage, int $object): JsonResponse
    {
        $page = $this->selecting($ptahPage, $object);
        $page->run('editObj', ['id' => $object]);
        $this->fillObject($page, $request);
        $page->run('saveObj');

        return $this->answer($page);
    }

    public function destroyObject(int $ptahPage, int $object): JsonResponse
    {
        $page = $this->selecting($ptahPage, $object);
        $page->run('confirmDeleteObj', ['id' => $object]);
        $page->run('deleteConfirmed');

        return $this->answer($page);
    }

    // ── Guide ───────────────────────────────────────────────────────────────

    /**
     * What the guide builds from PHP: the installed version and the links of
     * its "Go to …" buttons. Its text is static and lives in the client's words.
     */
    public function guide(): JsonResponse
    {
        // Booting the component is what runs its MASTER guard.
        HeadlessPermissionGuide::headless();

        $url = fn (string $name): ?string => Route::has($name) ? route($name) : null;

        return response()->json([
            'version' => PtahVersion::current(),
            'urls' => [
                'departments' => $url('ptah.acl.departments'),
                'pages' => $url('ptah.acl.pages'),
                'roles' => $url('ptah.acl.roles'),
                'users' => $url('ptah.acl.users'),
                'audit' => $url('ptah.acl.audit'),
            ],
        ]);
    }

    // ── Request in ──────────────────────────────────────────────────────────

    private function fillRole(HeadlessRoleList $page, Request $request): void
    {
        $page->name = (string) $request->input('name', '');
        $page->description = (string) $request->input('description', '');
        $page->color = (string) $request->input('color', '');
        $page->department_id = is_numeric($request->input('department_id')) ? (int) $request->input('department_id') : null;
        $page->is_active = $request->boolean('is_active', true);

        // The screen hides the MASTER switch while editing the MASTER role
        // (it shows a badge instead), so that flag is not an input there.
        $editingMaster = $page->editingId !== null && $page->is_master;
        if (! $editingMaster) {
            $page->is_master = $request->boolean('is_master');
        }
    }

    private function fillPage(HeadlessPageList $page, Request $request): void
    {
        $page->page_slug = (string) $request->input('slug', '');
        $page->page_name = (string) $request->input('name', '');
        $page->page_description = (string) $request->input('description', '');
        $page->page_route = (string) $request->input('route', '');
        $page->page_icon = (string) $request->input('icon', '');
        $page->page_is_active = $request->boolean('is_active', true);
        $page->page_sort_order = $request->integer('sort_order');
    }

    private function fillObject(HeadlessPageList $page, Request $request): void
    {
        $page->obj_section = (string) $request->input('section', 'main');
        $page->obj_key = (string) $request->input('obj_key', '');
        $page->obj_label = (string) $request->input('obj_label', '');
        $page->obj_type = (string) $request->input('obj_type', 'button');
        $page->obj_order = $request->integer('obj_order');
        $page->obj_is_active = $request->boolean('is_active', true);
    }

    /**
     * The page list with a page selected, as after clicking it. An object of
     * another page is refused: saveObj() writes the SELECTED page id, so an
     * object id from elsewhere would otherwise be moved to this page.
     */
    private function selecting(int $ptahPage, ?int $object = null): HeadlessPageList
    {
        $page = HeadlessPageList::headless();
        $selected = PtahPage::findOrFail($ptahPage);

        if ($object !== null) {
            abort_unless(PageObject::query()->whereKey($object)->where('page_id', $selected->id)->exists(), 404);
        }

        $page->run('selectPage', ['id' => $selected->id, 'name' => $selected->name]);

        return $page;
    }

    // ── Answer out ──────────────────────────────────────────────────────────

    /**
     * The outcome of an action. The components report a refusal (a business
     * rule, a missing record) as a danger toast instead of throwing, so that
     * toast is the error; its field errors, if any, come with it.
     *
     * @param  array<string, mixed>  $extra
     */
    private function answer(HeadlessRoleList|HeadlessPageList $page, array $extra = []): JsonResponse
    {
        $messages = $page->toasts();
        $failure = null;
        foreach ($messages as $message) {
            if ($message['level'] === 'danger') {
                $failure = $message['text'];
                break;
            }
        }

        $errors = array_map(fn (array $list) => (string) ($list[0] ?? ''), $page->getErrorBag()->toArray());
        $ok = $failure === null && $errors === [];

        return response()->json(array_merge([
            'ok' => $ok,
            'error' => $failure,
            'errors' => (object) $errors,
            'messages' => $messages,
        ], $extra), $ok ? 200 : 422);
    }

    /**
     * One page of a component's listing. Outside a Livewire request the page
     * number is handed to the paginator here, and the framework's resolver is
     * put back afterwards.
     *
     * @param  Closure(): LengthAwarePaginator  $query
     */
    private function paginate(Closure $query, int $number): LengthAwarePaginator
    {
        $number = max(1, $number);
        Paginator::currentPageResolver(function () use (&$number) {
            return $number;
        });

        try {
            $rows = $query();

            // A page past the last one (after a delete) shows the last page.
            if ($rows->isEmpty() && $number > 1 && $rows->lastPage() < $number) {
                $number = $rows->lastPage();
                $rows = $query();
            }
        } finally {
            PaginationState::resolveUsing(app());
        }

        return $rows;
    }

    /** @return array<string, int> */
    private function meta(LengthAwarePaginator $rows): array
    {
        return [
            'page' => $rows->currentPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
            'last_page' => $rows->lastPage(),
            'from' => (int) $rows->firstItem(),
            'to' => (int) $rows->lastItem(),
        ];
    }

    /** @return array<string, mixed> */
    private function roleRow(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'color' => $role->color,
            'department_id' => $role->department_id,
            'department' => $role->department instanceof Department ? $role->department->name : null,
            'is_master' => (bool) $role->is_master,
            'is_active' => (bool) $role->is_active,
            'permissions_count' => (int) $role->getAttribute('permissions_count'),
        ];
    }

    /** @return array<string, mixed> */
    private function roleForm(HeadlessRoleList $page): array
    {
        return [
            'id' => $page->editingId,
            'name' => $page->name,
            'description' => $page->description,
            'color' => $page->color,
            'department_id' => $page->department_id,
            'is_master' => $page->is_master,
            'is_active' => $page->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function bindRow(array $object): array
    {
        return [
            'id' => (int) $object['id'],
            'page_id' => (int) $object['page_id'],
            'page_name' => (string) $object['page_name'],
            'section' => (string) $object['section'],
            'obj_key' => (string) $object['obj_key'],
            'obj_label' => (string) $object['obj_label'],
            'obj_type' => (string) $object['obj_type'],
            'can_create' => (bool) $object['can_create'],
            'can_read' => (bool) $object['can_read'],
            'can_update' => (bool) $object['can_update'],
            'can_delete' => (bool) $object['can_delete'],
        ];
    }

    /** @return array<string, mixed> */
    private function pageRow(PtahPage $row): array
    {
        return [
            'id' => $row->id,
            'slug' => $row->slug,
            'name' => $row->name,
            'description' => $row->description,
            'route' => $row->route,
            'icon' => $row->icon,
            'is_active' => (bool) $row->is_active,
            'sort_order' => (int) $row->sort_order,
            'objects_count' => (int) $row->getAttribute('page_objects_count'),
        ];
    }

    /** @return array<string, mixed> */
    private function objectRow(PageObject $row): array
    {
        return [
            'id' => $row->id,
            'page_id' => $row->page_id,
            'section' => $row->section,
            'obj_key' => $row->obj_key,
            'obj_label' => $row->obj_label,
            'obj_type' => $row->obj_type,
            'obj_order' => (int) $row->obj_order,
            'is_active' => (bool) $row->is_active,
        ];
    }
}
