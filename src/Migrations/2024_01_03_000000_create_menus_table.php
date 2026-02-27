<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable()->comment('ID do menu pai (null = raiz)');
            $table->string('text')->comment('Texto exibido no menu');
            $table->string('url')->nullable()->comment('URL de destino (menuLink)');
            $table->string('icon')->nullable()->default('bx bx-circle')->comment('Classe do ícone (ex: bx bx-home)');
            $table->enum('type', ['menuLink', 'menuGroup'])->default('menuLink');
            $table->enum('target', ['_self', '_blank'])->default('_self');
            $table->unsignedSmallInteger('link_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('menus')->nullOnDelete();
            $table->index(['parent_id', 'is_active', 'link_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
