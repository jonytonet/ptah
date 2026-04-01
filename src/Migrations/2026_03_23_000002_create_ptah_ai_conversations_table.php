<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptah_ai_conversations', function (Blueprint $table) {
            $table->id();

            // Authenticated user — nullable for guest sessions
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Browser/PHP session identifier (fallback for unauthenticated users)
            $table->string('session_id', 64)->index();

            // Auto-generated from the first message of the conversation
            $table->string('title', 160)->nullable();

            // Full message thread: JSON array of { role: user|assistant, content: string }
            $table->json('messages')->nullable();

            // Metadata
            $table->string('provider_used')->nullable();
            $table->string('model_used')->nullable();
            $table->integer('tokens_used')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_ai_conversations');
    }
};
