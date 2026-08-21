<?php

declare(strict_types=1);

namespace Ptah\Services\Permission;

use Ptah\Traits\ResolvesUser;

/**
 * Filters CRUD column definitions by a per-column permission key
 * (`colsPermission`), so a column the current user may not READ never
 * reaches the Livewire component's public properties, the query builder or
 * the view — instead of merely being hidden by CSS/formDataColumns.
 *
 * Column-level READ gate only (this wave). Create/update/delete authorization
 * for the CRUD as a whole is unaffected — see HasCrudForm::authorizeCrudAction().
 */
class ColumnPermissionService
{
    use ResolvesUser;

    /**
     * The CrudConfig column key that opts a column into permission gating.
     * Absent, empty (after trim) or non-string → the column is PUBLIC (no
     * gate) — this is a decision, not a "missing config" default, so it is
     * never made via isset()/empty() (an explicit `''` must still mean
     * public, matching the rest of this codebase's isset('') footgun notes).
     */
    public const TAG = 'colsPermission';

    public function __construct(private PermissionService $permissionService) {}

    /**
     * Filters $cols down to the ones the user may READ.
     *
     * Feature flag off (`ptah.modules.permissions` disabled) → byte-identical
     * passthrough: `cols` unchanged, `denied` empty. This wave must be a
     * complete no-op for every existing screen until the module is enabled.
     *
     * @param  array<int, array<string, mixed>>  $cols
     * @return array{cols: array<int, array<string, mixed>>, denied: string[]}
     */
    public function apply(array $cols, mixed $user = null, ?int $companyId = null): array
    {
        if (! config('ptah.modules.permissions')) {
            return ['cols' => $cols, 'denied' => []];
        }

        $keys = [];
        foreach ($cols as $col) {
            $key = $this->extractKey($col);
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        $granted = $this->resolveKeys(array_values(array_unique($keys)), $user, $companyId);

        $kept = [];
        $denied = [];

        foreach ($cols as $col) {
            $key = $this->extractKey($col);

            if ($key === '' || ($granted[$key] ?? false)) {
                $kept[] = $col;

                continue;
            }

            $field = (string) ($col['colsNomeFisico'] ?? '');
            if ($field !== '') {
                $denied[] = $field;
            }
        }

        return ['cols' => $kept, 'denied' => $denied];
    }

    /**
     * Extracts the permission key configured on a single column.
     */
    private function extractKey(array $col): string
    {
        $v = $col[self::TAG] ?? null;

        return is_string($v) ? trim($v) : '';
    }

    /**
     * Filters an EXPORT column list — the `field`/`label`/`type`/`permission`
     * shape built by `HasCrudExport::getVisibleColumnsForExport()`, NOT the
     * raw CrudConfig `cols` shape `apply()` consumes — down to the columns
     * $user may READ.
     *
     * Called again at file-generation time (ExportController::download(),
     * GenerateCrudExportJob::handle()) instead of trusting the permission
     * baked into a cached/queued payload at dispatch time: a grant revoked
     * between queuing the export and the worker/controller actually building
     * the file must still exclude the column — authorization is never frozen.
     *
     * `permission` is an OPTIONAL key (added by this wave): absent means
     * public, exactly like `apply()`'s `colsPermission` tag — so a payload
     * queued/cached before this wave (no `permission` key on any column at
     * all) is filtered as entirely public and keeps working unchanged.
     *
     * @param  array<int, array{field: string, label: string, type: string, permission?: string}>  $columns
     * @return array<int, array{field: string, label: string, type: string, permission?: string}>
     */
    public function filterExportColumns(array $columns, mixed $user = null, ?int $companyId = null): array
    {
        if (! config('ptah.modules.permissions')) {
            return $columns;
        }

        $keys = [];
        foreach ($columns as $column) {
            $key = $this->extractExportKey($column);
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        $granted = $this->resolveKeys(array_values(array_unique($keys)), $user, $companyId);

        return array_values(array_filter(
            $columns,
            function (array $column) use ($granted) {
                $key = $this->extractExportKey($column);

                return $key === '' || ($granted[$key] ?? false);
            }
        ));
    }

    /**
     * Extracts the permission key configured on a single EXPORT column (the
     * `field`/`label`/`type`/`permission` shape — see `filterExportColumns()`).
     */
    private function extractExportKey(array $column): string
    {
        $v = $column['permission'] ?? null;

        return is_string($v) ? trim($v) : '';
    }

    /**
     * Resolves each distinct permission key to a `read` grant, WITHOUT ever
     * calling `check()` in a loop — that would write one audit row per
     * column, per render (N inserts). Instead:
     *
     *  - MASTER (isMaster()) → every key grants, resolved BEFORE the map is
     *    even consulted, so a key that was never registered as a PageObject
     *    cannot deny a master user (a map miss would otherwise read as denied).
     *  - Guest (no resolvable user id) → every key resolves to
     *    `ptah.permissions.allow_guest` (default false).
     *  - Otherwise → `getPermissions()` is read ONCE for every key (the bare
     *    map). `getQualifiedPermissions()` is only read — at most once more —
     *    when at least one key contains `PermissionService::KEY_QUALIFIER`
     *    AND missed the bare lookup, mirroring check()'s literal-first
     *    resolution order (PermissionService::check(), ~line 232-247): a key
     *    present in the bare map (even one that happens to contain "::"
     *    literally) is never treated as qualified.
     *
     * @param  string[]  $keys
     * @return array<string, bool>
     */
    private function resolveKeys(array $keys, mixed $user, ?int $companyId): array
    {
        if ($keys === []) {
            return [];
        }

        if ($this->permissionService->isMaster($user)) {
            return array_fill_keys($keys, true);
        }

        if ($this->resolveUserId($user) === null) {
            $allowGuest = (bool) config('ptah.permissions.allow_guest', false);

            return array_fill_keys($keys, $allowGuest);
        }

        $map = $this->permissionService->getPermissions($user, $companyId);

        $resolved = [];
        $needsQualified = false;

        foreach ($keys as $key) {
            if (isset($map[$key])) {
                // Literal (bare) match — same precedence as check(): never
                // falls through to the qualified map, even if $key itself
                // contains the "::" qualifier.
                $resolved[$key] = (bool) $map[$key]['read'];
            } elseif (str_contains($key, PermissionService::KEY_QUALIFIER)) {
                $needsQualified = true;
            } else {
                $resolved[$key] = false;
            }
        }

        if ($needsQualified) {
            $qmap = $this->permissionService->getQualifiedPermissions($user, $companyId);

            foreach ($keys as $key) {
                if (array_key_exists($key, $resolved)) {
                    continue;
                }

                $resolved[$key] = (bool) ($qmap[$key]['read'] ?? false);
            }
        }

        return $resolved;
    }
}
