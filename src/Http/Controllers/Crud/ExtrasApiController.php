<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers\Crud;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Ptah\Crud\CrudRuntime;
use Ptah\Crud\CrudRuntimeResolver;
use Ptah\Crud\CrudScreen;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Livewire\store;

/**
 * Four smaller BaseCrud features over the headless API (1.59.0): the form
 * draft, master/detail, the form's server-side behaviours (onChange
 * formulas, cascading dropdowns) and column management.
 *
 * Every answer is built from the engine's own methods on the runtime —
 * `restoreFormDraft()`, `updatedFormData()`, `resetColumnsToDefault()`… —
 * and only named keys leave: never the raw config, a class name or a
 * `$hidden` attribute.
 */
class ExtrasApiController extends Controller
{
    public function __construct(private readonly CrudRuntimeResolver $resolver) {}

    // ── Form draft ──────────────────────────────────────────────────────────

    /**
     * What the browser needs to keep the draft of one opening of the form:
     * its localStorage key, the user part of the key (to purge the drafts of
     * another user on a shared browser), the fields a draft may carry, the
     * values the form opened with and, on an edit, the fingerprint of the
     * record. `id` = the record being edited; none = the create form.
     *
     * With `draft` ({values, version}) it also restores: the engine takes
     * only draftable fields, refuses a draft of a record that changed since
     * (`discarded`), and answers the restored values with their
     * searchdropdown labels — the same path the Livewire modal uses when it
     * sends the draft along with the opening.
     */
    public function draft(Request $request, string $screen): JsonResponse
    {
        $id = $this->recordId($request);
        $crud = $this->resolver->resolve($request, $screen, $id === null ? 'canCreate' : 'canUpdate');

        abort_unless($crud->formDraftEnabled(), 404);

        $draft = $request->input('draft');
        if ($draft !== null && (! is_array($draft) || ! is_array($draft['values'] ?? null))) {
            throw ValidationException::withMessages(['draft' => trans('validation.array', ['attribute' => 'draft'])]);
        }

        if (is_array($draft)) {
            // The engine's own "draft sent with the opening" (formDraftIncoming):
            // the target must match the opening, or nothing is taken.
            $crud->formDraftIncoming = [
                'target' => $id === null ? 'new' : 'edit:'.$id,
                'values' => $draft['values'],
                'version' => is_scalar($draft['version'] ?? null) ? (string) $draft['version'] : '',
            ];
        }

        if ($id === null) {
            $crud->blank();
        } else {
            // Scoped: a record outside the screen's locks is not found.
            abort_if($crud->record($id) === null, 404);
        }

        $context = $this->dispatchedParams($crud, 'ptah:form-draft');
        abort_if($context === null, 404);

        $fields = array_values(array_map('strval', (array) ($context['fields'] ?? [])));
        $restored = (bool) ($context['restored'] ?? false);
        $draftable = array_flip($fields);

        return response()->json([
            'key' => (string) ($context['key'] ?? ''),
            'prefix' => $crud->formDraftPrefix(),
            'user' => (string) ($context['user'] ?? ''),
            'fields' => $fields,
            'original' => (object) array_intersect_key((array) ($context['original'] ?? []), $draftable),
            'version' => (string) ($context['version'] ?? ''),
            'ttlDays' => (int) ($context['ttlDays'] ?? 7),
            'restored' => $restored,
            'discarded' => (bool) ($context['discarded'] ?? false),
            // Only after a restore, and only draftable fields.
            'data' => (object) ($restored ? array_intersect_key($crud->formData, $draftable) : []),
            'labels' => (object) ($restored ? array_intersect_key($crud->sdLabels, $draftable) : []),
        ]);
    }

    // ── Form behaviours ─────────────────────────────────────────────────────

    /**
     * A field of the form changed: what the server does then in the Livewire
     * modal (`updatedFormData`) — clears the searchdropdowns that depend on
     * it and runs its `colsOnChange` formula. Answers the patch: the form
     * fields whose value changed (null = cleared) and their labels.
     */
    public function change(Request $request, string $screen): JsonResponse
    {
        $id = $this->recordId($request);
        $crud = $this->resolver->resolve($request, $screen, $id === null ? 'canCreate' : 'canUpdate');

        $data = $request->input('data', []);
        if (! is_array($data)) {
            throw ValidationException::withMessages(['data' => trans('validation.array', ['attribute' => 'data'])]);
        }

        $fields = array_column($crud->schema()['form'], 'field');
        $field = $request->input('field');
        if (! is_string($field) || ! in_array($field, $fields, true)) {
            throw ValidationException::withMessages(['field' => trans('validation.in', ['attribute' => 'field'])]);
        }

        if ($id === null) {
            $crud->blank();
        } else {
            abort_if($crud->record($id) === null, 404);
        }

        // What the browser holds now, over what the opening loaded.
        $crud->formData = array_merge($crud->formData, $data);
        $before = $crud->formData;
        $labelsBefore = $crud->sdLabels;

        $crud->updatedFormData($crud->formData[$field] ?? null, $field);

        $patch = [];
        $labels = [];
        foreach ($fields as $name) {
            $had = array_key_exists($name, $before);
            $has = array_key_exists($name, $crud->formData);
            $value = $has ? $crud->formData[$name] : null;

            // Only form fields: a formula may return anything, the client
            // learns only what its own form shows.
            if (($had || $has) && ($before[$name] ?? null) !== $value && (is_scalar($value) || $value === null)) {
                $patch[$name] = $value;
            }
            if (($labelsBefore[$name] ?? null) !== ($crud->sdLabels[$name] ?? null)) {
                $labels[$name] = (string) ($crud->sdLabels[$name] ?? '');
            }
        }

        return response()->json(['data' => (object) $patch, 'labels' => (object) $labels]);
    }

    /**
     * The form fields whose change the server must hear about — those with a
     * `colsOnChange` formula and the parents of a cascading searchdropdown —
     * so a client asks only for them, not on every keystroke of every field.
     */
    public function triggers(Request $request, string $screen): JsonResponse
    {
        $crud = $this->resolver->resolve($request, $screen);
        $fields = array_column($crud->schema()['form'], 'field');
        $triggers = [];

        foreach ($crud->crudConfig['cols'] ?? [] as $col) {
            if (is_string($col['colsOnChange'] ?? null) && trim($col['colsOnChange']) !== '') {
                $triggers[] = (string) ($col['colsNomeFisico'] ?? '');
            }
            if (is_string($col['colsSDDependsOn'] ?? null) && $col['colsSDDependsOn'] !== '') {
                $triggers[] = $col['colsSDDependsOn'];
            }
        }

        return response()->json(['fields' => array_values(array_intersect($fields, $triggers))]);
    }

    // ── Master / detail ─────────────────────────────────────────────────────

    /**
     * The detail screens of one row — what the Livewire screen mounts under
     * an expanded row: a full BaseCrud of each `masterDetail` model, locked to
     * the row's key. Each comes as the key of a screen the whole API serves
     * (`{screen}..{id}.{index}`): listing, filters, form, export, row
     * actions… all through the usual endpoints, all bound on the server to
     * this row (see CrudRuntimeResolver). The row is looked up through this
     * screen's scope (outside its locks: 404); a detail the user may not read
     * (or whose model has no config) is `available: false`.
     */
    public function details(Request $request, string $screen, int $id): JsonResponse
    {
        $crud = $this->resolver->resolve($request, $screen);

        abort_if(empty($crud->crudConfig['masterDetail']), 404);
        abort_unless($crud->reaches($id), 404);

        $out = [];
        foreach (array_keys((array) $crud->crudConfig['masterDetail']) as $index) {
            if (! is_int($index) || ($detail = $crud->detailConfig($index)) === null) {
                continue;
            }

            $key = CrudScreen::detailKey($screen, $id, $index);

            try {
                $child = $this->resolver->resolve($request, $key);
            } catch (HttpException) {
                $child = null;
            }

            $out[] = [
                'index' => $index,
                // Never the model path as a title: it is a class name.
                'title' => $detail['title'] ?? ($child === null ? '' : $child->humanTitle()),
                'screen' => $key,
                'available' => $child !== null,
            ];
        }

        return response()->json(['data' => $out]);
    }

    // ── Columns ─────────────────────────────────────────────────────────────

    /**
     * Every column the user may show or hide, in the current order, with its
     * label — the schema only carries the visible ones, and the columns menu
     * also lists the hidden.
     */
    public function columns(Request $request, string $screen): JsonResponse
    {
        $crud = $this->resolver->resolve($request, $screen);

        return response()->json(['data' => $this->columnList($crud)]);
    }

    /**
     * Back to the screen's layout: every column visible (the engine's
     * `resetColumnsToDefault()`), no saved order, no saved widths. With
     * `field`, only that column's width is forgotten — the double-click on
     * a resize edge.
     */
    public function resetColumns(Request $request, string $screen): JsonResponse
    {
        // restore: the saved search/filters are part of the same record.
        $crud = $this->resolver->resolve($request, $screen, restore: true)->remember();
        $field = $request->input('field');

        if ($field !== null) {
            $known = array_column($this->columnList($crud), 'field');
            if (! is_string($field) || ! in_array($field, $known, true)) {
                throw ValidationException::withMessages(['field' => trans('validation.in', ['attribute' => 'field'])]);
            }
            unset($crud->columnWidths[$field]);
            $crud->savePreferences();
        } else {
            $crud->columnOrder = [];
            $crud->columnWidths = [];
            $crud->resetColumnsToDefault();
        }

        return response()->json(['preferences' => $crud->preferences(), 'data' => $this->columnList($crud)]);
    }

    /**
     * @return list<array{field: string, label: string, visible: bool}>
     */
    private function columnList(CrudRuntime $crud): array
    {
        // preferences() already drops action columns, denied columns and
        // `$hidden` attributes — the same set the menu may name.
        $preferences = $crud->preferences();
        $visible = (array) $preferences['columns'];
        $labels = [];

        foreach ($crud->crudConfig['cols'] ?? [] as $col) {
            $field = (string) ($col['colsNomeFisico'] ?? '');
            if (array_key_exists($field, $visible) && $field !== 'id') {
                $labels[$field] = (string) ($col['colsNomeLogico'] ?? $field);
            }
        }

        $order = array_values(array_unique(array_merge(
            array_values(array_filter($preferences['column_order'], fn ($field) => isset($labels[$field]))),
            array_keys($labels),
        )));

        return array_map(fn (string $field) => [
            'field' => $field,
            'label' => $labels[$field],
            'visible' => (bool) $visible[$field],
        ], $order);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** The record of the form (`id`), or null for the create form; 422 when forged. */
    private function recordId(Request $request): ?int
    {
        $id = $request->input('id');

        if ($id === null || $id === '') {
            return null;
        }
        if (! is_scalar($id) || ! ctype_digit((string) $id)) {
            throw ValidationException::withMessages(['id' => trans('validation.integer', ['attribute' => 'id'])]);
        }

        return (int) $id;
    }

    /**
     * The params of the last event of that name the engine dispatched — what
     * it would have handed the browser.
     *
     * @return array<string, mixed>|null
     */
    private function dispatchedParams(CrudRuntime $crud, string $name): ?array
    {
        $found = null;

        foreach ((array) store($crud)->get('dispatched', []) as $event) {
            if (is_object($event) && method_exists($event, 'serialize')) {
                $serialized = (array) $event->serialize();
                if (($serialized['name'] ?? null) === $name) {
                    $found = (array) ($serialized['params'] ?? []);
                }
            }
        }

        return $found;
    }
}
