<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers\Admin;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\PaginationState;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Ptah\Http\Headless\HeadlessCompanyList;
use Ptah\Http\Headless\HeadlessCompanySwitcher;
use Ptah\Http\Headless\HeadlessDepartmentList;
use Ptah\Models\Company;
use Ptah\Models\Department;
use Ptah\Services\Company\CompanyService;
use Ptah\Support\StackBoot;

/**
 * The company area (companies, departments, the company switcher) for the
 * React and Vue stacks (1.59.0).
 *
 * Each action runs the Livewire component of the screen as a plain object
 * (Ptah\Http\Headless\*): the access checks in boot(), the validation rules,
 * the "default company cannot be deleted" rule and the membership check of the
 * switcher are the components' own code. This controller only carries the
 * request in and a whitelisted answer out.
 *
 * Stateless: the Livewire screen remembers "the record being edited" between
 * two clicks; here every request rebuilds that state by calling the screen's
 * own edit()/confirmDelete() first.
 */
class CompanyApiController extends Controller
{
    /** The tax types the company form offers (the Blade view's select). */
    private const TAX_TYPES = ['cnpj', 'cpf', 'ein', 'vat', 'other'];

    // ── Companies ───────────────────────────────────────────────────────────

    public function companies(Request $request): JsonResponse
    {
        $state = $this->listState($request);
        $page = HeadlessCompanyList::headless();
        $page->search = $state['search'];
        $page->sort = $state['sort'];
        $page->direction = $state['direction'];

        $rows = $this->paginate(fn () => $page->rows(), $state['page']);

        return response()->json([
            'data' => array_map(fn (Company $company) => $this->companyRow($company), $rows->items()),
            'meta' => $this->meta($rows),
            'state' => ['search' => $page->search, 'sort' => $page->sort, 'direction' => $page->direction],
            'tax_types' => self::TAX_TYPES,
        ]);
    }

    public function company(int $company): JsonResponse
    {
        $page = HeadlessCompanyList::headless();
        $page->run('edit', ['id' => $company]);

        return response()->json(['id' => $company, 'form' => $this->companyForm($page)]);
    }

    public function storeCompany(Request $request): JsonResponse
    {
        $page = HeadlessCompanyList::headless();
        $page->run('create');

        return $this->saveCompany($page, $request);
    }

    public function updateCompany(Request $request, int $company): JsonResponse
    {
        $page = HeadlessCompanyList::headless();
        // The record being edited, as the screen keeps it between two clicks.
        $page->run('edit', ['id' => $company]);

        return $this->saveCompany($page, $request);
    }

    public function destroyCompany(int $company): JsonResponse
    {
        $page = HeadlessCompanyList::headless();
        $page->run('confirmDelete', ['id' => $company]);
        $page->run('delete');

        return $this->outcome($page, $this->succeeded($page));
    }

    // ── Departments ─────────────────────────────────────────────────────────

    public function departments(Request $request): JsonResponse
    {
        $state = $this->listState($request);
        $page = HeadlessDepartmentList::headless();
        $page->search = $state['search'];
        $page->sort = $state['sort'];
        $page->direction = $state['direction'];

        $rows = $this->paginate(fn () => $page->rows(), $state['page']);

        return response()->json([
            'data' => array_map(fn (Department $department) => [
                'id' => (int) $department->id,
                'name' => (string) $department->name,
                'description' => $department->description,
                'roles_count' => (int) ($department->roles_count ?? 0),
                'is_active' => (bool) $department->is_active,
            ], $rows->items()),
            'meta' => $this->meta($rows),
            'state' => ['search' => $page->search, 'sort' => $page->sort, 'direction' => $page->direction],
        ]);
    }

    public function department(int $department): JsonResponse
    {
        $page = HeadlessDepartmentList::headless();
        $page->run('edit', ['id' => $department]);

        return response()->json(['id' => $department, 'form' => $this->departmentForm($page)]);
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $page = HeadlessDepartmentList::headless();
        $page->run('create');

        return $this->saveDepartment($page, $request);
    }

    public function updateDepartment(Request $request, int $department): JsonResponse
    {
        $page = HeadlessDepartmentList::headless();
        $page->run('edit', ['id' => $department]);

        return $this->saveDepartment($page, $request);
    }

    public function destroyDepartment(int $department): JsonResponse
    {
        $page = HeadlessDepartmentList::headless();
        $page->run('confirmDelete', ['id' => $department]);
        $page->run('delete');

        return $this->outcome($page, $this->succeeded($page));
    }

    // ── Company switcher ────────────────────────────────────────────────────

    /** The companies this user may switch to, and the active one. */
    public function switcher(): JsonResponse
    {
        return response()->json($this->switcherPayload(HeadlessCompanySwitcher::headless()));
    }

    public function switchCompany(Request $request, CompanyService $companies): JsonResponse
    {
        $request->validate(['id' => 'required|integer|min:1']);
        $id = (int) $request->input('id');

        $switcher = HeadlessCompanySwitcher::headless();
        $switcher->run('switchTo', ['id' => $id]);

        // setActive() ignores a company the user does not belong to without a
        // word (the Livewire screen just reloads); an API client is told so.
        abort_unless($companies->activeId() === $id, 403, trans('ptah::ui.permission_denied'));

        return response()->json(['ok' => true] + $this->switcherPayload($switcher, $id));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function saveCompany(HeadlessCompanyList $page, Request $request): JsonResponse
    {
        foreach (['name', 'label', 'email', 'phone', 'tax_id', 'tax_type'] as $field) {
            if ($request->has($field)) {
                $page->{$field} = $this->text($request, $field);
            }
        }
        foreach (['is_default', 'is_active'] as $field) {
            if ($request->has($field)) {
                $page->{$field} = $request->boolean($field);
            }
        }

        // validate() inside save() throws a 422 with the field named.
        $page->run('save');

        return $this->outcome($page, ! $page->showModal);
    }

    private function saveDepartment(HeadlessDepartmentList $page, Request $request): JsonResponse
    {
        foreach (['name', 'description'] as $field) {
            if ($request->has($field)) {
                $page->{$field} = $this->text($request, $field);
            }
        }
        if ($request->has('is_active')) {
            $page->is_active = $request->boolean('is_active');
        }

        $page->run('save');

        return $this->outcome($page, ! $page->showModal);
    }

    /**
     * The listing state the screen keeps in its properties. Only the columns
     * the screen lets the user sort by: `orderBy()` takes the property as a
     * column name.
     *
     * @return array{search: string, sort: string, direction: string, page: int}
     */
    private function listState(Request $request): array
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'sort' => ['nullable', Rule::in(['name'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => 'nullable|integer|min:1',
        ]);

        return [
            'search' => $this->text($request, 'search'),
            'sort' => $this->text($request, 'sort') ?: 'name',
            'direction' => $this->text($request, 'direction') ?: 'asc',
            'page' => max(1, $request->integer('page', 1)),
        ];
    }

    /**
     * One page of a screen's `rows()` without Livewire's page state.
     *
     * @param  Closure(): LengthAwarePaginator  $rows
     */
    private function paginate(Closure $rows, int $page): LengthAwarePaginator
    {
        // By reference: the retry below moves the page.
        Paginator::currentPageResolver(function () use (&$page) {
            return $page;
        });

        try {
            $list = $rows();

            // A page past the last one (the last row of it was deleted) shows
            // the last page, not an empty list.
            if ($list->isEmpty() && $page > 1 && $list->lastPage() < $page) {
                $page = $list->lastPage();
                $list = $rows();
            }
        } finally {
            // Back to the framework's resolvers: the closure must not outlive this call.
            PaginationState::resolveUsing(app());
        }

        return $list;
    }

    /** @return array<string, int|null> */
    private function meta(LengthAwarePaginator $rows): array
    {
        return [
            'page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
            'from' => $rows->firstItem(),
            'to' => $rows->lastItem(),
        ];
    }

    /** @return array<string, mixed> */
    private function companyRow(Company $company): array
    {
        return [
            'id' => (int) $company->id,
            'name' => (string) $company->name,
            'label' => $company->getAttribute('label'),
            'label_display' => $company->getLabelDisplay(),
            'email' => $company->email,
            'phone' => $company->phone,
            'tax_id' => $company->tax_id,
            'tax_type' => $company->tax_type,
            'is_default' => (bool) $company->is_default,
            'is_active' => (bool) $company->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function companyForm(HeadlessCompanyList $page): array
    {
        return [
            'name' => $page->name,
            'label' => $page->label,
            'email' => $page->email,
            'phone' => $page->phone,
            'tax_id' => $page->tax_id,
            'tax_type' => $page->tax_type,
            'is_default' => $page->is_default,
            'is_active' => $page->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function departmentForm(HeadlessDepartmentList $page): array
    {
        return [
            'name' => $page->name,
            'description' => $page->description,
            'is_active' => $page->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function switcherPayload(HeadlessCompanySwitcher $switcher, ?int $activeId = null): array
    {
        // The same payload the stack page embeds (StackBoot, `data-boot`).
        return StackBoot::companySwitcher($switcher, $activeId);
    }

    /**
     * A screen's action counts as failed when it showed a danger toast (the
     * screens report every refusal and every caught error that way).
     */
    private function succeeded(HeadlessCompanyList|HeadlessDepartmentList $page): bool
    {
        return $this->dangerToast($page) === null;
    }

    private function dangerToast(HeadlessCompanyList|HeadlessDepartmentList $page): ?string
    {
        foreach ($page->toasts() as $toast) {
            if ($toast['level'] === 'danger') {
                return $toast['text'];
            }
        }

        return null;
    }

    private function outcome(HeadlessCompanyList|HeadlessDepartmentList $page, bool $ok): JsonResponse
    {
        return response()->json([
            'ok' => $ok,
            'error' => $ok ? null : $this->dangerToast($page),
            'messages' => $page->toasts(),
        ], $ok ? 200 : 422);
    }

    /** A scalar input as a string; anything else (an array, an object) as empty. */
    private function text(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
