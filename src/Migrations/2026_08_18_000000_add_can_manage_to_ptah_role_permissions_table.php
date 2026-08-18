<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `manage` verb as its own column: `can_manage`.
 *
 * `manage` authorizes *configuring* a resource (e.g. the in-app CRUD config
 * editor, `crud.config`) — it is deliberately independent from the CRUD
 * quartet (`can_create`/`can_read`/`can_update`/`can_delete`), which governs
 * operating a resource's *data*. Before this column existed, `PermissionService::ACTIONS`
 * whitelisted only the CRUD quartet, so `ptah_can($key, 'manage')` was always
 * `false` by construction — the two call sites that used it
 * (`ptah_can_manage_config()` and `AiModelConfigList`) were, in practice,
 * MASTER-only regardless of any `crud.config` grant an admin configured.
 *
 * Idempotent (hasColumn guard) — safe to run on fresh installs and on
 * projects already in production.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ptah_role_permissions') && ! Schema::hasColumn('ptah_role_permissions', 'can_manage')) {
            Schema::table('ptah_role_permissions', function (Blueprint $table) {
                $table->boolean('can_manage')->default(false)->after('can_delete');
            });
        }
    }

    /**
     * WARNING — destructive and unrecoverable: dropping `can_manage` discards
     * every `manage` grant recorded on ptah_role_permissions. There is no
     * backup of this column anywhere in the package; once dropped, the only
     * way back is for an administrator to re-grant `manage` by hand on every
     * affected role. Do not run this down() against a database that has any
     * real `manage` grant without exporting/backing it up first.
     */
    public function down(): void
    {
        if (Schema::hasTable('ptah_role_permissions') && Schema::hasColumn('ptah_role_permissions', 'can_manage')) {
            Schema::table('ptah_role_permissions', function (Blueprint $table) {
                $table->dropColumn('can_manage');
            });
        }
    }
};
