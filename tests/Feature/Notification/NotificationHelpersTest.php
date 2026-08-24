<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Notification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Notification;
use Ptah\Models\Role;
use Ptah\Models\UserRole;

/**
 * Global helpers ptah_notify()/ptah_notify_role()/ptah_notify_all() — thin
 * bridges to NotificationService, resolving the $user argument the same way
 * ptah_can()/ptah_has_role() already do (see src/helpers.php).
 */
class NotificationHelpersTest extends NotificationTestCase
{
    #[Test]
    public function ptah_notify_accepts_an_explicit_user_id(): void
    {
        $count = ptah_notify(7, ['title' => 'Ola']);

        $this->assertSame(1, $count);
        $this->assertSame(7, Notification::query()->first()->user_id);
    }

    #[Test]
    public function ptah_notify_resolves_the_current_authenticated_user_when_given_null(): void
    {
        /** @var class-string<Model> $userClass */
        $userClass = config('auth.providers.users.model');
        $user = $userClass::forceCreate(['name' => 'T', 'email' => 't@test.com', 'password' => bcrypt('x')]);
        Auth::loginUsingId($user->id);

        $count = ptah_notify(null, ['title' => 'Ola']);

        $this->assertSame(1, $count);
        $this->assertSame($user->id, Notification::query()->first()->user_id);
    }

    #[Test]
    public function ptah_notify_returns_zero_when_the_user_cannot_be_resolved(): void
    {
        $count = ptah_notify(null, ['title' => 'Ola']);

        $this->assertSame(0, $count);
        $this->assertSame(0, Notification::query()->count());
    }

    #[Test]
    public function ptah_notify_role_broadcasts_to_the_role(): void
    {
        $role = Role::create(['name' => 'Financeiro', 'is_active' => true]);
        UserRole::create(['user_id' => 10, 'role_id' => $role->id, 'company_id' => 1, 'is_active' => true]);

        $count = ptah_notify_role('Financeiro', ['title' => 'x'], 1);

        $this->assertSame(1, $count);
    }

    #[Test]
    public function ptah_notify_all_broadcasts_to_staff(): void
    {
        $role = Role::create(['name' => 'Qualquer', 'is_active' => true]);
        UserRole::create(['user_id' => 10, 'role_id' => $role->id, 'company_id' => 1, 'is_active' => true]);

        $count = ptah_notify_all(['title' => 'x'], 1, true);

        $this->assertSame(1, $count);
    }
}
