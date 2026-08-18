<?php

declare(strict_types=1);

namespace Ptah\Commands\Permission;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ptah\Models\CrudConfig;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Services\Permission\PermissionService;
use Ptah\Services\Permission\RoleService;
use Ptah\Support\ModelKey;

/**
 * Turnkey bridge between BaseCrud configs and the RBAC tables.
 *
 * ptah_can() only ever grants an action for an `obj_key` that already has a row
 * in `ptah_page_objects` (belonging to a `ptah_pages` row) AND a matching
 * `ptah_role_permissions` grant. Without this command, an admin has to create
 * those rows by hand in the Role UI before a single permissionIdentifier
 * configured in a CRUD screen can ever be granted.
 *
 * For every `crud_configs` row with a non-empty RBAC key
 * (`permissions.permissionIdentifier`, falling back to the legacy
 * `permissions.identifier` so un-migrated rows are still synced), this command:
 *
 *   1. Ensures a `PtahPage` exists (slug = the canonical model key).
 *   2. Ensures a `PageObject` exists (section "main", obj_key = the RBAC key).
 *   3. Optionally grants a role on that object (--role + --grant).
 *
 * Idempotent: re-running never duplicates pages/objects (firstOrCreate on the
 * unique keys) or grants (RoleService::bindPageObject upserts).
 */
class PermissionSyncCommand extends Command
{
    protected $signature = 'ptah:permission:sync
        {--role= : Role name to grant the synced objects to}
        {--grant= : Comma-separated actions to grant (create,read,update,delete or "all")}
        {--dry-run : Preview the changes without persisting anything}';

    protected $description = 'Sync BaseCrud permissionIdentifier keys into ptah_pages/ptah_page_objects (and optionally grant a role)';

    public function handle(RoleService $roleService): int
    {
        $rows = CrudConfig::query()->get();

        if ($rows->isEmpty()) {
            $this->info('No crud_configs found — nothing to sync.');

            return self::SUCCESS;
        }

        $roleName = $this->option('role');
        $grantRaw = $this->option('grant');

        if (($roleName && ! $grantRaw) || (! $roleName && $grantRaw)) {
            $this->components->error('--role and --grant must be used together.');

            return self::FAILURE;
        }

        $grant = null;
        if ($grantRaw) {
            $grant = $this->resolveGrant($grantRaw);
            if ($grant === null) {
                return self::FAILURE;
            }
        }

        $role = null;
        if ($roleName) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                $this->components->error("Role '{$roleName}' not found.");

                return self::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');

        $checked = 0;
        $skippedEmpty = 0;
        $pagesCreated = 0;
        $objectsCreated = 0;
        $granted = 0;

        // The dry-run branch below is read-only (only ->exists() checks) — it must
        // never open a transaction. The persisting branch creates/updates several
        // rows per config (page, object, grant); wrapping the whole batch keeps a
        // mid-run failure from leaving a partially-synced set of pages/objects.
        $sync = function () use (
            $rows, $dryRun, $role, $roleName, $grant, $roleService,
            &$checked, &$skippedEmpty, &$pagesCreated, &$objectsCreated, &$granted
        ): void {
            foreach ($rows as $row) {
                $config = $row->config ?? [];
                $key = $config['permissions']['permissionIdentifier']
                    ?? $config['permissions']['identifier']
                    ?? null;

                if (empty($key)) {
                    $skippedEmpty++;

                    continue;
                }

                $checked++;
                $canonical = ModelKey::canonical((string) $row->model);
                $label = ($config['displayName'] ?? '') ?: $canonical;

                if ($dryRun) {
                    $pageExists = PtahPage::where('slug', $canonical)->exists();
                    $objectExists = $pageExists && PageObject::whereHas(
                        'page',
                        fn ($q) => $q->where('slug', $canonical)
                    )->where('section', 'main')->where('obj_key', $key)->exists();

                    if (! $pageExists) {
                        $pagesCreated++;
                    }
                    if (! $objectExists) {
                        $objectsCreated++;
                    }
                    if ($role) {
                        $granted++;
                    }

                    $this->line(sprintf(
                        '  <fg=cyan>preview</> [%s] key=%s → page %s, object %s%s',
                        $canonical,
                        $key,
                        $pageExists ? 'exists' : 'would be created',
                        $objectExists ? 'exists' : 'would be created',
                        $role ? ", would grant '{$roleName}' [".implode(',', $grant).']' : ''
                    ));

                    continue;
                }

                $page = PtahPage::firstOrCreate(
                    ['slug' => $canonical],
                    ['name' => $label]
                );
                if ($page->wasRecentlyCreated) {
                    $pagesCreated++;
                }

                $pageObject = PageObject::firstOrCreate(
                    ['page_id' => $page->id, 'section' => 'main', 'obj_key' => $key],
                    ['obj_label' => $label, 'obj_type' => 'page']
                );
                if ($pageObject->wasRecentlyCreated) {
                    $objectsCreated++;
                }

                if ($role) {
                    $roleService->bindPageObject($role, $pageObject->id, [
                        'can_create' => in_array('create', $grant, true),
                        'can_read' => in_array('read', $grant, true),
                        'can_update' => in_array('update', $grant, true),
                        'can_delete' => in_array('delete', $grant, true),
                    ]);
                    $granted++;
                }
            }
        };

        if ($dryRun) {
            $sync();
        } else {
            DB::transaction($sync);
        }

        $this->newLine();
        $prefix = $dryRun ? 'Dry-run: ' : '';
        $summary = "{$prefix}{$checked} config(s) with a permission key ({$skippedEmpty} skipped, no permissionIdentifier): "
            ."{$pagesCreated} page(s) ".($dryRun ? 'to create' : 'created').', '
            ."{$objectsCreated} object(s) ".($dryRun ? 'to create' : 'created');

        if ($role) {
            $summary .= ', '.$granted.($dryRun ? " grant(s) to preview for role '{$roleName}'" : " grant(s) applied to role '{$roleName}'");
        }

        $this->info($summary);

        return self::SUCCESS;
    }

    /**
     * Validates and normalises the --grant option into a list of canonical
     * actions. Returns null (after printing an error) when the input is invalid.
     *
     * @return array<int, string>|null
     */
    protected function resolveGrant(string $raw): ?array
    {
        if (trim(strtolower($raw)) === 'all') {
            return PermissionService::ACTIONS;
        }

        $actions = array_values(array_unique(array_map(
            fn (string $a) => strtolower(trim($a)),
            explode(',', $raw)
        )));

        $invalid = array_diff($actions, PermissionService::ACTIONS);

        if ($invalid !== []) {
            $this->components->error(
                'Invalid --grant action(s): '.implode(', ', $invalid)
                .'. Valid: '.implode(', ', PermissionService::ACTIONS).' or "all".'
            );

            return null;
        }

        return $actions;
    }
}
