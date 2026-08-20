<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub table used by CrudBulkActionsTest — needs BOTH SoftDeletes (bulkRestore/
 * bulkForceDelete) and a `status` column (lockedFilters scoping), which none of
 * the existing shared stub tables combine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_action_stubs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_action_stubs');
    }
};
