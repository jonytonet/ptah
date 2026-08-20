<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub `sessions` table used by SessionServiceRevokeSessionTest — the real
 * package/host migration is Laravel's own database session driver table
 * (database/migrations/....create_sessions_table.php), which is never loaded
 * in this test suite (session.driver is 'array', see TestCase). Same shape
 * SessionService reads/writes via DB::table('sessions').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
