<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `ptah_notifications` — opt-in schema for the "baterias inclusas" notification
 * center (NotificationService, NotificationBell). Deliberately NOT under
 * src/Migrations: that directory is auto-discovered by
 * PtahServiceProvider::loadMigrations() the instant ANY module is enabled
 * (see loadMigrations() and tests/Unit/Support/SchemaIsFrozenTest.php, which
 * pins that directory's manifest). A consumer who only wants the Camada 1
 * navbar slot — or any other Ptah module, without notifications — must never
 * get this table pushed onto them by an unrelated `php artisan migrate`.
 *
 * Instead this file lives in database/migrations/ at the package root and is
 * published on demand via `php artisan vendor:publish --tag=ptah-notifications`
 * (see PtahServiceProvider::registerPublishing()). The consumer reviews it,
 * copies it into their own database/migrations/, and runs `migrate` themselves
 * — exactly like any other Laravel migration they own.
 *
 * Dedupe: `unique(['user_id', 'dedupe_key'])` is a SIMPLE unique index, not a
 * partial/filtered one. NULL is never equal to another NULL under a unique
 * index on MySQL, PostgreSQL or SQLite, so this already means "unique only
 * when dedupe_key is not null" on all 3 drivers — a partial index with a
 * WHERE clause (as an earlier draft of the spec suggested) is unnecessary and
 * would not even be portable: MySQL has no partial/filtered unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptah_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('type', 20)->default('info');
            $table->string('category', 60)->nullable();
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('action_label', 60)->nullable();
            $table->string('dedupe_key', 191)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            // Idempotent push(): a second push() with the same (user_id, dedupe_key)
            // updates this row instead of inserting a duplicate. See the note above —
            // NULL dedupe_key never collides, so untagged notifications are unaffected.
            $table->unique(['user_id', 'dedupe_key']);

            // Poll query (unread count / dropdown list) filters by user + read state.
            $table->index(['user_id', 'read_at']);

            // Retention prune (NotificationService::purgeRead()) scans by age.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptah_notifications');
    }
};
