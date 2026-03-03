<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptah_pages', function (Blueprint $table) {
            $table->id();
            // Slug único: identifica a página no sistema (ex: 'admin.users', 'reports.financial')
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            // Rota Laravel: usado para gerar links automáticos
            $table->string('route')->nullable();
            // Ícone heroicon / tabler / fontawesome — livre para o sistema definir
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            // Sem softDeletes: páginas são registros de sistema, nunca "deletadas"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_pages');
    }
};
