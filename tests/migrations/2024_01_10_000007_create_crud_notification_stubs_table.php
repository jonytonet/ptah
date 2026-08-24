<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub tables used by CrudNotificationDispatcher / SendsCrudNotifications
 * tests — two identical tables so the "recursion latch" tests can exercise
 * two DISTINCT model classes without touching production migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['crud_notification_stub_a', 'crud_notification_stub_b'] as $table) {
            Schema::create($table, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                // Deliberately restricted in some tests via `colsPermission` on
                // the CrudConfig row — never a valid resolveTemplate() placeholder.
                $table->string('secret')->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crud_notification_stub_a');
        Schema::dropIfExists('crud_notification_stub_b');
    }
};
