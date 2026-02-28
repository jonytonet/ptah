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
            // Sem FK: o pacote não conhece o model User do app host.
            // Compatível com UUID ou BIGINT.
            $table->unsignedBigInteger('user_id')->index();
            $table->foreignId('role_id')
                  ->constrained('ptah_roles')
                  ->cascadeOnDelete();
            // company_id sem FK: pode apontar para ptah_companies ou qualquer outra tabela
            // null = associação global (sem empresa específica — sistemas single-tenant)
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Evita duplicação user+role+empresa
            // ⚠ LIMITAÇÃO MYSQL: índices UNIQUE com NULL permitem múltiplas linhas
            //   com company_id IS NULL (NULL ≠ NULL em SQL). Em PostgreSQL 15+
            //   e MySQL 8.0.13+ é possível usar partial/functional indexes.
            //   A aplicação garante unicidade via updateOrCreate com WHERE IS NULL.
            $table->unique(['user_id', 'role_id', 'company_id'], 'ptah_user_roles_unique');
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_user_roles');
    }
};
