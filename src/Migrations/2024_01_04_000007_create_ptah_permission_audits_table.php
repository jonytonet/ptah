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
            // obj_key saved as string (not FK) to preserve history even
            // if the object is deleted in the future
            $table->string('resource_key')->nullable();
            $table->string('action', 20); // create|read|update|delete
            $table->enum('result', ['granted', 'denied'])->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            // Extra context: request URI, method, etc.
            $table->json('context')->nullable();
            // Only created_at — no updated_at (immutable log)
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
