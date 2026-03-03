<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds audit fields (created_by / updated_by / deleted_by) to
 * the Ptah package tables that did not yet have them.
 *
 * Uses hasColumn() to be idempotent — can run on fresh installations
 * and on projects already in production without causing errors.
 *
 * Tables covered:
 *  - ptah_departments   → + deleted_by
 *  - ptah_roles         → + deleted_by
 *  - menus              → + created_by, updated_by, deleted_by
 *  - crud_configs       → + created_by, updated_by
 *  - ptah_pages         → + created_by, updated_by
 *  - ptah_page_objects  → + created_by, updated_by
 *
 * (ptah_companies already has all fields since the original migration.)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── ptah_departments: add deleted_by ──────────────────────────────
        if (Schema::hasTable('ptah_departments') && ! Schema::hasColumn('ptah_departments', 'deleted_by')) {
            Schema::table('ptah_departments', function (Blueprint $table) {
                $table->unsignedBigInteger('deleted_by')->nullable()->index()->after('updated_by');
            });
        }

        // ── ptah_roles: add deleted_by ────────────────────────────────────
        if (Schema::hasTable('ptah_roles') && ! Schema::hasColumn('ptah_roles', 'deleted_by')) {
            Schema::table('ptah_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('deleted_by')->nullable()->index()->after('updated_by');
            });
        }

        // ── menus: add created_by / updated_by / deleted_by ─────────────
        if (Schema::hasTable('menus')) {
            Schema::table('menus', function (Blueprint $table) {
                if (! Schema::hasColumn('menus', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index()->after('is_active');
                }
                if (! Schema::hasColumn('menus', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->index()->after('created_by');
                }
                if (! Schema::hasColumn('menus', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable()->index()->after('updated_by');
                }
            });
        }

        // ── crud_configs: add created_by / updated_by ─────────────────────
        if (Schema::hasTable('crud_configs')) {
            Schema::table('crud_configs', function (Blueprint $table) {
                if (! Schema::hasColumn('crud_configs', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index()->after('config');
                }
                if (! Schema::hasColumn('crud_configs', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->index()->after('created_by');
                }
            });
        }

        // ── ptah_pages: add created_by / updated_by ──────────────────────
        if (Schema::hasTable('ptah_pages')) {
            Schema::table('ptah_pages', function (Blueprint $table) {
                if (! Schema::hasColumn('ptah_pages', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index()->after('sort_order');
                }
                if (! Schema::hasColumn('ptah_pages', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->index()->after('created_by');
                }
            });
        }

        // ── ptah_page_objects: add created_by / updated_by ─────────────
        if (Schema::hasTable('ptah_page_objects')) {
            Schema::table('ptah_page_objects', function (Blueprint $table) {
                if (! Schema::hasColumn('ptah_page_objects', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index()->after('is_active');
                }
                if (! Schema::hasColumn('ptah_page_objects', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->index()->after('created_by');
                }
            });
        }

        // ── ptah_user_roles: add updated_by / deleted_by ─────────────────
        if (Schema::hasTable('ptah_user_roles')) {
            Schema::table('ptah_user_roles', function (Blueprint $table) {
                if (! Schema::hasColumn('ptah_user_roles', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->index()->after('created_by');
                }
                if (! Schema::hasColumn('ptah_user_roles', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable()->index()->after('updated_by');
                }
            });
        }

        // ── ptah_role_permissions: add deleted_by ──────────────────────────
        if (Schema::hasTable('ptah_role_permissions') && ! Schema::hasColumn('ptah_role_permissions', 'deleted_by')) {
            Schema::table('ptah_role_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('deleted_by')->nullable()->index()->after('updated_by');
            });
        }
    }

    public function down(): void
    {
        $drops = [
            'ptah_departments'      => ['deleted_by'],
            'ptah_roles'            => ['deleted_by'],
            'menus'                 => ['created_by', 'updated_by', 'deleted_by'],
            'crud_configs'          => ['created_by', 'updated_by'],
            'ptah_pages'            => ['created_by', 'updated_by'],
            'ptah_page_objects'     => ['created_by', 'updated_by'],
            'ptah_user_roles'       => ['updated_by', 'deleted_by'],
            'ptah_role_permissions' => ['deleted_by'],
        ];

        foreach ($drops as $table => $columns) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                    foreach ($columns as $col) {
                        if (Schema::hasColumn($table, $col)) {
                            $t->dropColumn($col);
                        }
                    }
                });
            }
        }
    }
};
