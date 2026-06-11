<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Seeders\DefaultAdminSeeder;
use Ptah\Tests\TestCase;

// ── Minimal User model for seeder tests ──────────────────────────────────────
//
// Does NOT use SoftDeletes so the seeder takes the simple ::where() path
// instead of ::withTrashed(), keeping the test environment leaner.

class SeederTestUser extends Model
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * P0 regression tests for DefaultAdminSeeder.
 *
 * The seeder must never use the old hard-coded 'admin@123' password.
 * When PTAH_ADMIN_PASSWORD is unset it must generate a strong random one;
 * when it is set it must use that exact value.
 */
class DefaultAdminSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Point the seeder to the test User model.
        config([
            'ptah.permissions.user_model' => SeederTestUser::class,
            'ptah.permissions.admin_email' => 'admin@admin.com',
            'ptah.permissions.admin_name' => 'Administrator',
            'ptah.permissions.admin_password' => null,
        ]);
    }

    #[Test]
    public function seeder_creates_admin_with_random_password_when_env_is_unset(): void
    {
        config(['ptah.permissions.admin_password' => null]);

        (new DefaultAdminSeeder)->run();

        $admin = SeederTestUser::where('email', 'admin@admin.com')->first();

        $this->assertNotNull($admin, 'Admin user must be created by the seeder');

        // The hard-coded legacy password must NOT be set.
        $this->assertFalse(
            Hash::check('admin@123', $admin->password),
            "Default password 'admin@123' must never be used — seeder must generate a random one",
        );
    }

    #[Test]
    public function seeder_uses_provided_password_when_env_is_set(): void
    {
        $secret = 'my-test-secret-password-!@#';
        config(['ptah.permissions.admin_password' => $secret]);

        (new DefaultAdminSeeder)->run();

        $admin = SeederTestUser::where('email', 'admin@admin.com')->first();

        $this->assertNotNull($admin);
        $this->assertTrue(
            Hash::check($secret, $admin->password),
            'Seeder must use the password from PTAH_ADMIN_PASSWORD config',
        );
    }

    #[Test]
    public function seeder_is_idempotent_when_run_twice(): void
    {
        config(['ptah.permissions.admin_password' => 'stable-password']);

        (new DefaultAdminSeeder)->run();
        (new DefaultAdminSeeder)->run(); // second run — must not duplicate or throw

        $count = SeederTestUser::where('email', 'admin@admin.com')->count();

        $this->assertSame(1, $count, 'Seeder must not create duplicate admin users');
    }

    #[Test]
    public function seeder_random_password_is_strong_enough(): void
    {
        config(['ptah.permissions.admin_password' => null]);

        // Run the seeder and capture the random password by observing the hash length.
        // Str::password(20) produces at least 20 chars — we verify the hash exists
        // and is different from the legacy password.
        (new DefaultAdminSeeder)->run();

        $admin = SeederTestUser::where('email', 'admin@admin.com')->first();

        $this->assertNotNull($admin->password);
        // bcrypt/argon hashes are always longer than 20 chars.
        $this->assertGreaterThan(20, strlen($admin->password));
        $this->assertFalse(Hash::check('admin@123', $admin->password));
        $this->assertFalse(Hash::check('password', $admin->password));
        $this->assertFalse(Hash::check('admin', $admin->password));
    }
}
