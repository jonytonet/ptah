<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Async export jobs (Fase 3 — "grande volume"): one row per queued export
 * request. The BaseCrud component resolves the filtered/sorted ids and stores
 * them (+ visible columns + sort) in `payload`; GenerateCrudExportJob only
 * generates the file from those ids and updates status/file_disk/file_path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptah_exports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('model');
            $table->string('route')->default('');
            $table->string('format');
            $table->string('status')->default('queued'); // queued|processing|done|failed
            $table->string('file_disk')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('rows')->nullable();
            $table->json('payload');
            $table->text('error')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_exports');
    }
};
