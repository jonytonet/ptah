<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub table used by the async export multi-tenant scoping test — has a
 * `company_id` column (the `items` stub table used elsewhere does not), so
 * BaseCrud's companyFilter scope actually applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_scope_stubs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_scope_stubs');
    }
};
