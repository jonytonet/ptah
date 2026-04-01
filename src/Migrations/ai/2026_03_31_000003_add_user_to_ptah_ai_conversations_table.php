<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds user_id and title to ptah_ai_conversations.
 *
 * Only applied when the AI Agent module is enabled (loaded conditionally
 * via PtahServiceProvider::loadMigrations). Safe to run on existing installations:
 * uses hasColumn() checks so it won't fail if the columns already exist
 * (e.g. on a fresh install where the base migration already created them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ptah_ai_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('ptah_ai_conversations', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
            }

            if (! Schema::hasColumn('ptah_ai_conversations', 'title')) {
                $table->string('title', 160)->nullable()->after('session_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ptah_ai_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('ptah_ai_conversations', 'user_id')) {
                $table->dropIndex(['user_id']);
                $table->dropColumn('user_id');
            }

            if (Schema::hasColumn('ptah_ai_conversations', 'title')) {
                $table->dropColumn('title');
            }
        });
    }
};
