<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only migration — creates a minimal users table for all Ptah tests.
 *
 * Orchestra Testbench 10 ships an empty laravel/database/migrations directory
 * (loadLaravelMigrations() registers no files). This migration fills that gap
 * so the users table exists before any Ptah migration that adds columns to it
 * (e.g. add_two_factor_columns_to_users_table at 2024_01_03_000001).
 *
 * Timestamp 2014_10_12 ensures this runs before all Ptah migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
