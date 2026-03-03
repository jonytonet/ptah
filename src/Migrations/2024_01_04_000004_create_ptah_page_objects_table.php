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
            // Section groups objects within a page (e.g. 'toolbar', 'table', 'form')
            $table->string('section')->default('main');
            // obj_key: unique business key (e.g. 'users.store', 'reports.export.excel')
            $table->string('obj_key');
            $table->string('obj_label');
            // Object type: determines the validation behaviour and the binding UI
            $table->enum('obj_type', ['page', 'button', 'field', 'link', 'section', 'api', 'report', 'tab'])
                  ->default('button');
            $table->integer('obj_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Ensures obj_key is unique per page (may repeat across different pages)
            $table->unique(['page_id', 'section', 'obj_key']);
            $table->index(['page_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_page_objects');
    }
};
