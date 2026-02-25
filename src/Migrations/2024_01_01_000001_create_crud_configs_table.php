<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crud_configs', function (Blueprint $table) {
            $table->id();
            $table->string('model')->unique()->comment('Nome da entidade, ex: Product ou Purchase/Order/PurchaseOrders');
            $table->json('config')->comment('Configuração completa do BaseCrud em JSON');
            $table->timestamps();

            $table->index('model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crud_configs');
    }
};
