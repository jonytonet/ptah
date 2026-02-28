<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptah_permission_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable();
            // obj_key salvo como string (não FK) para preservar histórico mesmo
            // se o objeto for excluído futuramente
            $table->string('resource_key')->nullable();
            $table->string('action', 20); // create|read|update|delete
            $table->enum('result', ['granted', 'denied'])->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            // Contexto extra: request URI, method, etc.
            $table->json('context')->nullable();
            // Apenas created_at — sem updated_at (log imutável)
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['user_id', 'created_at']);
            $table->index(['result', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_permission_audits');
    }
};
