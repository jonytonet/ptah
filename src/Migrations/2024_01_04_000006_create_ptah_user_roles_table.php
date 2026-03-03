<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptah_user_roles', function (Blueprint $table) {
            $table->id();
            // No FK: the package does not know the host app's User model.
            // Compatible with UUID or BIGINT.
            $table->unsignedBigInteger('user_id')->index();
            $table->foreignId('role_id')
                  ->constrained('ptah_roles')
                  ->cascadeOnDelete();
            // company_id without FK: can point to ptah_companies or any other table
            // null = global association (no specific company — single-tenant systems)
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Prevents user+role+company duplication
            // ⚠ MYSQL LIMITATION: UNIQUE indexes with NULL allow multiple rows
            //   with company_id IS NULL (NULL ≠ NULL in SQL). In PostgreSQL 15+
            //   and MySQL 8.0.13+ it is possible to use partial/functional indexes.
            //   The application ensures uniqueness via updateOrCreate with WHERE IS NULL.
            $table->unique(['user_id', 'role_id', 'company_id'], 'ptah_user_roles_unique');
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_user_roles');
    }
};
