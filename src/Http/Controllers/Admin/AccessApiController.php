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
use Ptah\Http\Headless\HeadlessAuditList;
use Ptah\Http\Headless\HeadlessUserPermissionList;
use Ptah\Models\Company;
use Ptah\Models\PermissionAudit;
use Ptah\Models\Role;
use Ptah\Models\UserRole;

/**
 * The users-ACL screen and the permission audit log, for the React and Vue
 * stacks.
 *
 * Each action runs the Livewire screen (UserPermissionList, AuditList) as a
 * plain object: the MASTER gate in boot(), the host's `user_query_scope`, the
 * "MASTER cannot be removed" rule and the validation of the user form are the
 * component's code. This controller only carries the request in and the
 * answer out.
 *
 * Nothing of the component survives between two requests, so the user a
 * modal was opened for travels in the URL: each request opens the modal again
 * (openUserModal/editUser), which re-applies the scope before the action.
 */
class AccessApiController extends Controller
{
    // ── Users ───────────────────────────────────────────────────────────────

    /** The users list, with the roles each one holds, and the choices of the filters and modals. */
    public function users(Request $request): JsonResponse
    {
        $page = HeadlessUserPermissionList::headless();
        $page->search = (string) $request->query('search', '');
        $page->filterRole = $request->integer('role');

        $rows = $this->paginate($request, fn () => $page->rows());

        $ids = array_map(fn ($user) => $user->getKey(), $rows->items());

        // The Blade view looks the roles up per row; one query for the page here.
        $bindings = [];
        foreach (UserRole::with(['role', 'company'])->whereIn('user_id', $ids)->active()->get() as $ur) {
            $role = $ur->getRelation('role');
            $company = $ur->getRelation('company');
            $bindings[$ur->user_id][] = [
                'id' => $ur->id,
                'name' => $role instanceof Role ? (string) $role->name : '',
                'master' => $role instanceof Role && $role->is_master,
                'company' => $company instanceof Company ? (string) $company->name : null,
            ];
        }

        $data = [];
        foreach ($rows->items() as $user) {
            $data[] = [
                'id' => (int) $user->getKey(),
                'name' => (string) $user->getAttribute('name'),
                'email' => (string) $user->getAttribute('email'),
                'roles' => $bindings[$user->getKey()] ?? [],
            ];
        }

        return response()->json([
            'data' => $data,
            'meta' => $this->meta($rows),
            'roles' => $page->roles()->map(fn (Role $role) => ['id' => (int) $role->id, 'name' => (string) $role->name, 'master' => (bool) $role->is_master])->values()->all(),
            'companies' => $page->companies()->map(fn (Company $company) => ['id' => (int) $company->id, 'name' => (string) $company->name])->values()->all(),
            'can_send_link' => $page->canSendPasswordLink(),
        ]);
    }

    /** One user, as the edit form shows it. 404 outside the screen's query (the host's scope). */
    public function showUser(int $user): JsonResponse
    {
        $page = HeadlessUserPermissionList::headless();
        $page->run('editUser', ['userId' => $user]);
        abort_if($page->editingUserId === null, 404);

        return response()->json([
            'id' => $page->editingUserId,
            'name' => (string) ($page->userForm['name'] ?? ''),
            'email' => (string) ($page->userForm['email'] ?? ''),
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $page = HeadlessUserPermissionList::headless();
        $page->run('newUser');

        return $this->saveUser($page, $request);
    }

    public function updateUser(Request $request, int $user): JsonResponse
    {
        $page = HeadlessUserPermissionList::headless();
        $page->run('editUser', ['userId' => $user]);
        abort_if($page->editingUserId === null, 404);

        return $this->saveUser($page, $request);
    }

    /** "Send password link" — the same link "forgot password" sends. */
    public function sendPasswordLink(int $user): JsonResponse
    {
        $page = HeadlessUserPermissionList::headless();
        $page->run('sendPasswordLink', ['userId' => $user]);

        // No toast: the user is not in the screen's query.
        abort_if($page->toasts() === [], 404);

        return $this->answer($page);
    }

    // ── A user's roles (the "manage access" modal) ──────────────────────────

    public function roles(int $user): JsonResponse
    {
        $page = $this->openModal($user);

        return response()->json($this->modalPayload($page));
    }

    /** Binds a role, globally or for one company. */
    public function addRole(Request $request, int $user): JsonResponse
    {
        $page = $this->openModal($user);
        $page->newRoleId = (int) $request->input('role_id', 0);
        $page->newCompanyId = (int) $request->input('company_id', 0);
        $page->run('addRole');

        // The screen refuses an empty role with a toast; here the field is named too.
        $refusal = $this->firstDanger($page);
        $errors = $refusal !== null && (int) $request->input('role_id', 0) === 0 ? ['role_id' => $refusal] : [];

        return $this->answer($page, $this->modalPayload($page), $errors);
    }

    public function removeRole(int $user, int $userRole): JsonResponse
    {
        $page = $this->openModal($user);
        $page->run('removeRole', ['userRoleId' => $userRole]);

        return $this->answer($page, $this->modalPayload($page));
    }

    // ── Audit log ───────────────────────────────────────────────────────────

    /** The permission audit log — read-only — with the screen's filters. */
    public function audit(Request $request): JsonResponse
    {
        $page = HeadlessAuditList::headless();
        $page->search = (string) $request->query('search', '');
        $page->filterResult = (string) $request->query('result', '');
        $page->filterAction = (string) $request->query('action', '');
        $page->dateFrom = (string) $request->query('date_from', '');
        $page->dateTo = (string) $request->query('date_to', '');

        $rows = $this->paginate($request, fn () => $page->rows());

        $data = [];
        foreach ($rows->items() as $row) {
            /** @var PermissionAudit $row */
            $at = $row->getAttribute('created_at');
            $data[] = [
                'id' => (int) $row->id,
                // The context and the user agent are not on the screen; they stay out.
                'created_at' => $at?->toIso8601String(),
                'created_at_display' => $at?->format('d/m/Y H:i:s'),
                'user_id' => $row->user_id,
                'resource_key' => $row->resource_key,
                'action' => (string) $row->action,
                'result' => (string) $row->result,
                'ip_address' => $row->ip_address,
            ];
        }

        return response()->json(['data' => $data, 'meta' => $this->meta($rows)]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** The screen with the roles modal open for this user; 404 outside its query. */
    private function openModal(int $user): HeadlessUserPermissionList
    {
        $page = HeadlessUserPermissionList::headless();
        $page->run('openUserModal', ['userId' => $user, 'userName' => '']);
        abort_if($page->bindingUserId === null, 404);

        return $page;
    }

    /** @return array<string, mixed> */
    private function modalPayload(HeadlessUserPermissionList $page): array
    {
        return [
            'user' => ['id' => $page->bindingUserId, 'name' => $page->bindingUserName],
            'assigned' => array_map(fn (array $ar) => [
                'id' => (int) $ar['id'],
                'role_id' => (int) $ar['role_id'],
                'role_name' => (string) $ar['role_name'],
                'role_master' => (bool) $ar['role_master'],
                'company_id' => $ar['company_id'] !== null ? (int) $ar['company_id'] : null,
                'company_name' => $ar['company_id'] !== null ? (string) $ar['company_name'] : null,
            ], $page->assignedRoles),
        ];
    }

    private function saveUser(HeadlessUserPermissionList $page, Request $request): JsonResponse
    {
        // Only the four fields of the form reach the component. The switch is
        // drawn only where a link can be sent, as on the Livewire screen.
        $page->userForm = [
            'name' => (string) $request->input('name', ''),
            'email' => (string) $request->input('email', ''),
            'password' => (string) $request->input('password', ''),
            'send_link' => $request->boolean('send_link') && $page->canSendPasswordLink(),
        ];
        $page->run('saveUser');

        return $this->answer($page, [], $page->userFormErrors);
    }

    /**
     * Runs a listing with the page of the request, without Livewire's
     * pagination hooks. A page past the last one shows the last page.
     *
     * @param  Closure(): LengthAwarePaginator  $rows
     */
    private function paginate(Request $request, Closure $rows): LengthAwarePaginator
    {
        $current = max(1, $request->integer('page', 1));
        Paginator::currentPageResolver(fn () => $current);

        try {
            $result = $rows();

            if ($result->isEmpty() && $current > 1 && $result->lastPage() < $current) {
                $last = $result->lastPage();
                Paginator::currentPageResolver(fn () => $last);
                $result = $rows();
            }
        } finally {
            // Back to the framework's resolvers: the closure must not outlive this call.
            PaginationState::resolveUsing(app());
        }

        return $result;
    }

    /** @return array<string, int|null> */
    private function meta(LengthAwarePaginator $rows): array
    {
        return [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
            'from' => $rows->firstItem(),
            'to' => $rows->lastItem(),
        ];
    }

    private function firstDanger(HeadlessUserPermissionList $page): ?string
    {
        foreach ($page->toasts() as $toast) {
            if ($toast['level'] === 'danger') {
                return $toast['text'];
            }
        }

        return null;
    }

    /**
     * The outcome of an action: refused (422) when the screen answered with a
     * danger toast or a field error, as the Livewire screen would show them.
     *
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $errors
     */
    private function answer(HeadlessUserPermissionList $page, array $extra = [], array $errors = []): JsonResponse
    {
        $error = $this->firstDanger($page);
        $ok = $error === null && $errors === [];

        return response()->json(array_merge([
            'ok' => $ok,
            'error' => $errors === [] ? $error : null,
            'errors' => (object) $errors,
            'messages' => $page->toasts(),
        ], $extra), $ok ? 200 : 422);
    }
}
