<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Notification;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Notification;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Services\Notification\NotificationService;

/**
 * FASE 2 of the notification hook plan: NotificationService push/read/dismiss
 * semantics, dedupe, and role/company-scoped broadcasts. Uses
 * NotificationTestCase, which layers database/migrations/ (opt-in schema) on
 * top of the base TestCase.
 */
class NotificationServiceTest extends NotificationTestCase
{
    private function service(): NotificationService
    {
        return $this->app->make(NotificationService::class);
    }

    // ── push() / dedupe ─────────────────────────────────────────────────────

    #[Test]
    public function pushing_twice_with_the_same_dedupe_key_updates_the_single_row(): void
    {
        $service = $this->service();

        $first = $service->push(1, ['title' => 'Vacina vence em 3 dias', 'dedupe_key' => 'vacina:pet=42']);
        $second = $service->push(1, ['title' => 'Vacina vence HOJE', 'dedupe_key' => 'vacina:pet=42']);

        $this->assertSame(1, Notification::query()->count());
        $this->assertSame($first->id, $second->id);
        $this->assertSame('Vacina vence HOJE', $second->fresh()->title);
    }

    #[Test]
    public function pushing_twice_with_a_null_dedupe_key_creates_two_rows(): void
    {
        $service = $this->service();

        $service->push(1, ['title' => 'Aviso 1']);
        $service->push(1, ['title' => 'Aviso 2']);

        $this->assertSame(2, Notification::query()->count());
    }

    #[Test]
    public function the_same_dedupe_key_for_a_different_user_is_not_overwritten(): void
    {
        $service = $this->service();

        $service->push(1, ['title' => 'Para o usuario 1', 'dedupe_key' => 'shared-key']);
        $service->push(2, ['title' => 'Para o usuario 2', 'dedupe_key' => 'shared-key']);

        $this->assertSame(2, Notification::query()->count());
        $this->assertSame('Para o usuario 1', Notification::query()->where('user_id', 1)->first()->title);
        $this->assertSame('Para o usuario 2', Notification::query()->where('user_id', 2)->first()->title);
    }

    #[Test]
    public function user_id_inside_data_is_ignored_the_owner_is_always_the_argument(): void
    {
        $service = $this->service();

        $notification = $service->push(1, ['title' => 'x', 'user_id' => 999]);

        $this->assertSame(1, $notification->user_id);
        $this->assertNotSame(999, $notification->user_id);
    }

    // ── toRole() / toAll() ──────────────────────────────────────────────────

    #[Test]
    public function to_role_only_notifies_users_with_an_active_role_in_the_given_company(): void
    {
        $role = Role::create(['name' => 'Financeiro', 'is_active' => true]);

        UserRole::create(['user_id' => 10, 'role_id' => $role->id, 'company_id' => 1, 'is_active' => true]);
        // Same role, different company — must NOT be notified when scoped to company 1.
        UserRole::create(['user_id' => 20, 'role_id' => $role->id, 'company_id' => 2, 'is_active' => true]);
        // Same role, same company, but the ASSIGNMENT is inactive.
        UserRole::create(['user_id' => 30, 'role_id' => $role->id, 'company_id' => 1, 'is_active' => false]);

        $count = $this->service()->toRole('Financeiro', ['title' => 'Fatura vencida'], 1);

        $this->assertSame(1, $count);
        $this->assertSame([10], Notification::query()->pluck('user_id')->all());
    }

    #[Test]
    public function to_role_skips_users_whose_role_itself_is_inactive(): void
    {
        $role = Role::create(['name' => 'Financeiro', 'is_active' => false]);
        UserRole::create(['user_id' => 10, 'role_id' => $role->id, 'company_id' => 1, 'is_active' => true]);

        $count = $this->service()->toRole('Financeiro', ['title' => 'x'], 1);

        $this->assertSame(0, $count);
        $this->assertSame(0, Notification::query()->count());
    }

    #[Test]
    public function to_all_only_staff_notifies_every_user_with_an_active_role(): void
    {
        $role = Role::create(['name' => 'Qualquer', 'is_active' => true]);
        UserRole::create(['user_id' => 10, 'role_id' => $role->id, 'company_id' => 1, 'is_active' => true]);
        UserRole::create(['user_id' => 20, 'role_id' => $role->id, 'company_id' => 1, 'is_active' => true]);

        $count = $this->service()->toAll(['title' => 'Aviso geral'], 1, true);

        $this->assertSame(2, $count);
    }

    #[Test]
    public function to_all_not_only_staff_notifies_every_user_of_the_host_user_model(): void
    {
        /** @var class-string<Model> $userClass */
        $userClass = config('auth.providers.users.model');
        // toAll(onlyStaff: false) reads ptah.permissions.user_model, which
        // Testbench's own auth.providers.users.model default does not match.
        config(['ptah.permissions.user_model' => $userClass]);

        $userClass::forceCreate(['name' => 'A', 'email' => 'a@test.com', 'password' => bcrypt('x')]);
        $userClass::forceCreate(['name' => 'B', 'email' => 'b@test.com', 'password' => bcrypt('x')]);

        $count = $this->service()->toAll(['title' => 'Aviso geral'], null, false);

        $this->assertSame(2, $count);
        $this->assertSame(2, Notification::query()->count());
    }

    // ── unreadCount() / list() ───────────────────────────────────────────────

    #[Test]
    public function unread_count_and_list_are_scoped_by_company_and_skip_dismissed(): void
    {
        $service = $this->service();

        $service->push(1, ['title' => 'Global', 'company_id' => null]);
        $service->push(1, ['title' => 'Empresa 1', 'company_id' => 1]);
        $dismissed = $service->push(1, ['title' => 'Dispensada', 'company_id' => 1]);
        $service->push(1, ['title' => 'Empresa 2', 'company_id' => 2]);

        $service->dismiss($dismissed->id, 1);

        $this->assertSame(2, $service->unreadCount(1, 1));
        $this->assertSame(['Global', 'Empresa 1'], $service->list(1, 1)->pluck('title')->all());
    }

    // ── markRead() / dismiss() ownership ─────────────────────────────────────

    #[Test]
    public function mark_read_by_another_user_changes_nothing_and_returns_false(): void
    {
        $service = $this->service();
        $notification = $service->push(1, ['title' => 'x']);

        $result = $service->markRead($notification->id, 2);

        $this->assertFalse($result);
        $this->assertNull($notification->fresh()->read_at);
    }

    #[Test]
    public function dismiss_by_another_user_changes_nothing_and_returns_false(): void
    {
        $service = $this->service();
        $notification = $service->push(1, ['title' => 'x']);

        $result = $service->dismiss($notification->id, 2);

        $this->assertFalse($result);
        $this->assertNull($notification->fresh()->dismissed_at);
    }

    #[Test]
    public function mark_read_and_dismiss_by_the_owner_succeed(): void
    {
        $service = $this->service();
        $notification = $service->push(1, ['title' => 'x']);

        $this->assertTrue($service->markRead($notification->id, 1));
        $this->assertNotNull($notification->fresh()->read_at);

        $this->assertTrue($service->dismiss($notification->id, 1));
        $this->assertNotNull($notification->fresh()->dismissed_at);
    }

    #[Test]
    public function mark_all_read_only_touches_the_given_user_and_company(): void
    {
        $service = $this->service();
        $service->push(1, ['title' => 'a', 'company_id' => 1]);
        $service->push(1, ['title' => 'b', 'company_id' => 1]);
        $otherCompany = $service->push(1, ['title' => 'c', 'company_id' => 2]);
        $otherUser = $service->push(2, ['title' => 'd', 'company_id' => 1]);

        $count = $service->markAllRead(1, 1);

        $this->assertSame(2, $count);
        $this->assertNull($otherCompany->fresh()->read_at);
        $this->assertNull($otherUser->fresh()->read_at);
    }

    // ── purgeRead() ──────────────────────────────────────────────────────────

    #[Test]
    public function purge_read_deletes_only_read_notifications_older_than_the_window(): void
    {
        $service = $this->service();

        $old = $service->push(1, ['title' => 'old', 'dedupe_key' => 'old']);
        $old->forceFill(['read_at' => now()->subDays(40)])->save();

        $recent = $service->push(1, ['title' => 'recent', 'dedupe_key' => 'recent']);
        $recent->forceFill(['read_at' => now()->subDays(5)])->save();

        $unread = $service->push(1, ['title' => 'unread', 'dedupe_key' => 'unread']);

        $deleted = $service->purgeRead(30);

        $this->assertSame(1, $deleted);
        $this->assertNull($old->fresh());
        $this->assertNotNull($recent->fresh());
        $this->assertNotNull($unread->fresh());
    }

    #[Test]
    public function purge_read_dry_run_counts_without_deleting(): void
    {
        $service = $this->service();

        $old = $service->push(1, ['title' => 'old']);
        $old->forceFill(['read_at' => now()->subDays(40)])->save();

        $count = $service->purgeRead(30, 1000, true);

        $this->assertSame(1, $count);
        $this->assertNotNull($old->fresh());
    }
}
