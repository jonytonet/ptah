<?php

declare(strict_types=1);

namespace Ptah\Crud;

use Illuminate\Http\Request;

/**
 * The engine for a screen and a request, or the refusal (1.59.0) — the one
 * door every controller of the headless API goes through, so a feature
 * endpoint (history, attachments, import…) cannot forget a lock the CRUD
 * endpoints apply.
 */
class CrudRuntimeResolver
{
    public function __construct(private readonly CrudScreenRegistry $screens) {}

    /**
     * Reading is the permission of the whole screen: without it nothing is
     * disclosed, not even the schema (same rule as the Livewire screen's
     * render()). `$ability` is one more key of CrudRuntime::permissions()
     * the action needs (`canCreate`, `canUpdate`, `canDelete`…).
     */
    public function resolve(Request $request, string $screen, ?string $ability = null, bool $restore = false): CrudRuntime
    {
        $definition = $this->screens->find($screen);
        abort_if($definition === null, 404);
        abort_unless($definition->allows($request), 403);

        $crud = CrudRuntime::for($definition, $request, $restore);
        abort_unless($crud->hasConfig(), 404, "Screen '{$screen}' has no CRUD config.");

        $permissions = $crud->permissions();
        abort_unless($permissions['canRead'], 403, trans('ptah::ui.permission_denied'));
        abort_unless($ability === null || ($permissions[$ability] ?? false), 403, trans('ptah::ui.crud_permission_denied'));

        return $crud;
    }
}
