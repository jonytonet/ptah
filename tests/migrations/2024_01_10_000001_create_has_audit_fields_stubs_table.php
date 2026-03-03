<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only migration — creates stub tables used exclusively by HasAuditFieldsTest.
 *
 * Tables:
 *   has_audit_stubs          → model with all three audit columns + SoftDeletes
 *   no_soft_delete_stubs     → model with created_by/updated_by but NO SoftDeletes
 *
 * The users table is created by a separate migration file
 * (2014_10_12_000000_create_test_users_table.php) so it is always available
 * before any Ptah migration that adds columns to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('has_audit_stubs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('no_soft_delete_stubs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('no_soft_delete_stubs');
        Schema::dropIfExists('has_audit_stubs');
    }
};
