<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub table used by BaseRepository, BaseService and HasCrud unit tests.
 *
 * The timestamp 2024_01_10_000002 ensures it runs AFTER the users table
 * (2014_...) and the HasAuditFields stub tables (2024_01_10_000001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->integer('amount')->default(0);
            // Nullable — used by tests exercising "_id"-suffixed columns that are
            // plain numeric fields (not an Eloquent relation), e.g. URL filters'
            // BETWEEN type resolution. Additive; existing rows/tests are unaffected.
            $table->integer('category_id')->nullable();
            // Ownership stub used by CrudLockedFiltersScopeTest — the column a
            // per-user scoped screen locks on. Nullable with no default, so every
            // other test sharing this table keeps NULL here and is unaffected.
            $table->integer('owner_id')->nullable();
            // Boolean stub used by CrudBooleanFormTest — additive, default true so
            // every other test using this shared table is unaffected.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
