<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptah_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('color', 20)->nullable()->comment('Cor para exibição na UI (ex: #ff6b35)');
            // FK nullable: roles podem existir sem departamento em sistemas simples
            $table->foreignId('department_id')
                  ->nullable()
                  ->constrained('ptah_departments')
                  ->nullOnDelete();
            // Role MASTER: bypass total de todas as verificações de permissão
            $table->boolean('is_master')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_roles');
    }
};
