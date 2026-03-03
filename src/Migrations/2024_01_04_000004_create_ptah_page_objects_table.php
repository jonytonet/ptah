<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptah_page_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')
                  ->constrained('ptah_pages')
                  ->cascadeOnDelete();
            // Seção agrupa objetos dentro de uma página (ex: 'toolbar', 'table', 'form')
            $table->string('section')->default('main');
            // obj_key: chave única de negócio (ex: 'users.store', 'reports.export.excel')
            $table->string('obj_key');
            $table->string('obj_label');
            // Tipo do objeto: determina o comportamento de verificação e a UI de bind
            $table->enum('obj_type', ['page', 'button', 'field', 'link', 'section', 'api', 'report', 'tab'])
                  ->default('button');
            $table->integer('obj_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Garante que obj_key seja único por página (pode repetir entre páginas diferentes)
            $table->unique(['page_id', 'section', 'obj_key']);
            $table->index(['page_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_page_objects');
    }
};
