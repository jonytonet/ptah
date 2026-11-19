<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ptah\Crud\CrudRuntime;
use Ptah\Crud\CrudRuntimeResolver;

/**
 * The headless CRUD API (1.59.0): the BaseCrud over JSON, for a React, Vue
 * or any other client. See Ptah\Crud\CrudRuntime — it is the same engine as
 * the Livewire screen.
 *
 * Every action re-checks the screen's permissions here (403) before the
 * engine checks them again where it always did.
 */
class CrudApiController extends Controller
{
    public function __construct(private readonly CrudRuntimeResolver $resolver) {}

    public function schema(Request $request, string $screen): JsonResponse
    {
        $crud = $this->runtime($request, $screen);

        return response()->json($crud->schema());
    }

    public function index(Request $request, string $screen): JsonResponse
    {
        // restore: reopen where the user left (saved search, filters, page).
        // remember: save what this request applied, for the next reopening.
        $crud = $this->runtime($request, $screen, restore: $request->boolean('restore'));

        return response()->json(
            $crud->remember($request->boolean('remember'))->applyState($request->query())->listing()
        );
    }

    public function preferences(Request $request, string $screen): JsonResponse
    {
        return response()->json($this->runtime($request, $screen, restore: true)->preferences());
    }

    public function updatePreferences(Request $request, string $screen): JsonResponse
    {
        // restore: the saved search/filters are part of the same record and
        // must survive a change of columns.
        $crud = $this->runtime($request, $screen, restore: true);

        return response()->json($crud->updatePreferences($request->all())->preferences());
    }

    public function saveFilter(Request $request, string $screen): JsonResponse
    {
        $crud = $this->runtime($request, $screen, restore: true);
        $filters = $request->input('filters', []);

        return response()->json(
            $crud->saveFilter((string) $request->input('name', ''), is_array($filters) ? $filters : [])->preferences()
        );
    }

    public function forgetFilter(Request $request, string $screen): JsonResponse
    {
        $crud = $this->runtime($request, $screen, restore: true);

        return response()->json($crud->forgetFilter((string) $request->input('name', ''))->preferences());
    }

    public function show(Request $request, string $screen, int $id): JsonResponse
    {
        $crud = $this->runtime($request, $screen);

        $record = $crud->record($id);
        abort_if($record === null, 404);

        return response()->json($record);
    }

    /** The defaults of a new record's form. */
    public function create(Request $request, string $screen): JsonResponse
    {
        return response()->json($this->runtime($request, $screen, 'canCreate')->blank());
    }

    /** A record copied into a new form — nothing is saved. */
    public function duplicate(Request $request, string $screen, int $id): JsonResponse
    {
        $record = $this->runtime($request, $screen, 'canCreate')->duplicate($id);
        abort_if($record === null, 404);

        return response()->json($record);
    }

    /** Options of a searchdropdown field (form) or filter. */
    public function lookup(Request $request, string $screen, string $field): JsonResponse
    {
        $crud = $this->runtime($request, $screen);
        $context = $request->input('context', []);

        return response()->json(['data' => $crud->lookup(
            $field,
            trim((string) $request->input('q', '')),
            is_array($context) ? $context : [],
            $request->boolean('filter'),
        )]);
    }

    public function bulk(Request $request, string $screen): JsonResponse
    {
        $crud = $this->runtime($request, $screen);
        $ids = $request->input('ids', []);

        $result = $crud->applyState((array) $request->input('state', []))
            ->bulk((string) $request->input('action', ''), is_array($ids) ? $ids : []);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function export(Request $request, string $screen): JsonResponse
    {
        $crud = $this->runtime($request, $screen, 'canExport');
        $ids = $request->input('ids', []);

        // The listing state decides WHICH rows: the same search and filters.
        $result = $crud->applyState((array) $request->input('state', []))
            ->exportListing((string) $request->input('format', 'excel'), is_array($ids) ? $ids : []);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function store(Request $request, string $screen): JsonResponse
    {
        $crud = $this->runtime($request, $screen, 'canCreate');

        $result = $crud->store($this->data($request), $this->files($request));

        return response()->json($result, $result['ok'] ? 201 : 422);
    }

    public function update(Request $request, string $screen, int $id): JsonResponse
    {
        $crud = $this->runtime($request, $screen, 'canUpdate');

        $result = $crud->update($id, $this->data($request), $this->files($request));
        abort_if($result === null, 404);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function destroy(Request $request, string $screen, int $id): JsonResponse
    {
        $crud = $this->runtime($request, $screen, 'canDelete');

        $result = $crud->destroy($id);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function restore(Request $request, string $screen, int $id): JsonResponse
    {
        $crud = $this->runtime($request, $screen, 'canRestore');

        $result = $crud->restore($id);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /** The engine for this screen and request, or the refusal — see CrudRuntimeResolver. */
    private function runtime(Request $request, string $screen, ?string $ability = null, bool $restore = false): CrudRuntime
    {
        return $this->resolver->resolve($request, $screen, $ability, $restore);
    }

    /**
     * The form data: `data` as an object (JSON), or as a JSON string in a
     * multipart request that also carries files.
     *
     * @return array<string, mixed>
     */
    private function data(Request $request): array
    {
        $data = $request->input('data', []);

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return is_array($data) ? $data : [];
    }

    /** @return array<array-key, mixed> uploads keyed by field */
    private function files(Request $request): array
    {
        $files = $request->file('files', []);

        return is_array($files) ? $files : [];
    }
}
