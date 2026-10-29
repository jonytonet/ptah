<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Fortify;

/*
 * 1.53.0: Fortify and Jetstream create two_factor_secret,
 * two_factor_recovery_codes, two_factor_confirmed_at and profile_photo_path
 * too, so on a project that had them this migration failed ("duplicate
 * column"). Each column is now added only when missing — and the rollback no
 * longer drops the ones those packages own, which would have erased their
 * users' 2FA secrets.
 */
return new class extends Migration
{
    /** Columns Fortify/Jetstream also create. */
    private const SHARED = [
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'profile_photo_path',
    ];

    public function up(): void
    {
        $has = fn (string $column): bool => Schema::hasColumn('users', $column);

        Schema::table('users', function (Blueprint $table) use ($has) {
            if (! $has('two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('password');
            }
            if (! $has('two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            }
            if (! $has('two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            }
            if (! $has('two_factor_type')) {
                $table->string('two_factor_type', 10)->nullable()->after('two_factor_confirmed_at')
                    ->comment('totp | email');
            }
            if (! $has('profile_photo_path')) {
                $table->string('profile_photo_path', 2048)->nullable()->after('two_factor_type');
            }
        });
    }

    public function down(): void
    {
        // two_factor_type is ptah's alone; the shared columns go only when
        // Fortify (and so Jetstream) is not installed.
        $drop = class_exists(Fortify::class)
            ? ['two_factor_type']
            : ['two_factor_type', ...self::SHARED];

        $drop = array_values(array_filter($drop, fn (string $column): bool => Schema::hasColumn('users', $column)));

        if ($drop !== []) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn($drop));
        }
    }
};
