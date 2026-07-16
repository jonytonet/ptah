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
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
