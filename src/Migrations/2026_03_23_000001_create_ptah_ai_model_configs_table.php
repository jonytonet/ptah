<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptah_ai_model_configs', function (Blueprint $table) {
            $table->id();

            // Display / identification
            $table->string('name');
            $table->text('notes')->nullable();

            // Provider & model
            $table->string('provider');   // openai | anthropic | gemini | ollama | groq | mistral
            $table->string('model')->default('gpt-4o-mini');

            // Credentials (api_key stored encrypted via Model cast)
            $table->text('api_key');
            $table->string('api_endpoint')->nullable()->comment('Custom base URL — useful for proxies, Azure OpenAI, Ollama');

            // Generation parameters
            $table->integer('max_tokens')->default(1024);
            $table->decimal('temperature', 3, 2)->default(0.70);

            // System prompt (overrides ptah.ai_agent.system_prompt for this provider)
            $table->text('system_prompt')->nullable();

            // State flags
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_default')->default(false)->index();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_ai_model_configs');
    }
};
