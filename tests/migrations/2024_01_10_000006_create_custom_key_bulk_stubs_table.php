<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub table used by CrudBulkActionsCustomKeyTest — a CRUD whose primary key is
 * NOT named `id` (e.g. a natural/business key). HasCrudBulkActions used to
 * hardcode `whereIn('id', ...)` for bulk delete/restore/force-delete, so on a
 * table like this the bulk action either matched nothing (no `id` column) or,
 * worse, matched an unrelated `id` column if one happened to exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_key_bulk_stubs', function (Blueprint $table): void {
            $table->string('code')->primary();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_key_bulk_stubs');
    }
};
