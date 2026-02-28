<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptah_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')
                  ->constrained('ptah_roles')
                  ->cascadeOnDelete();
            $table->foreignId('page_object_id')
                  ->constrained('ptah_page_objects')
                  ->cascadeOnDelete();
            // Flags CRUD individuais — granularidade total
            $table->boolean('can_create')->default(false);
            $table->boolean('can_read')->default(true);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            // Metadados adicionais: útil para sistemas que precisam de condições extras
            // ex: { "own_only": true, "max_rows": 100 }
            $table->json('extra')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['role_id', 'page_object_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_role_permissions');
    }
};
