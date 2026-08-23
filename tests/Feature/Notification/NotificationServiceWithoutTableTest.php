<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Notification;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Services\Notification\NotificationService;
use Ptah\Tests\TestCase;

/**
 * Every NotificationService method must degrade to a neutral value — never
 * an exception — when the `ptah_notifications` table is unavailable, whether
 * because the module is off (default) or because it is enabled but the
 * opt-in migration was never published/run. This is the base TestCase
 * WITHOUT database/migrations/ layered in (see NotificationTestCase), so the
 * table genuinely does not exist here.
 */
class NotificationServiceWithoutTableTest extends TestCase
{
    protected function tearDown(): void
    {
        NotificationService::forgetTableExistsCache();

        parent::tearDown();
    }

    private function service(): NotificationService
    {
        return $this->app->make(NotificationService::class);
    }

    #[Test]
    public function module_disabled_by_default_every_method_returns_a_neutral_value(): void
    {
        NotificationService::forgetTableExistsCache();
        $service = $this->service();

        $this->assertNull($service->push(1, ['title' => 'x']));
        $this->assertSame(0, $service->pushMany([1, 2], ['title' => 'x']));
        $this->assertSame(0, $service->toUser(1, ['title' => 'x']));
        $this->assertSame(0, $service->toRole('Financeiro', ['title' => 'x']));
        $this->assertSame(0, $service->toAll(['title' => 'x']));
        $this->assertSame(0, $service->unreadCount(1));
        $this->assertTrue($service->list(1)->isEmpty());
        $this->assertSame(0, $service->paginate(1, null, [], 20)->total());
        $this->assertFalse($service->markRead(1, 1));
        $this->assertSame(0, $service->markAllRead(1));
        $this->assertFalse($service->dismiss(1, 1));
        $this->assertSame(0, $service->purgeRead());
        $this->assertFalse($service->tableExists());
    }

    #[Test]
    public function module_enabled_but_table_not_migrated_still_degrades_neutrally(): void
    {
        config(['ptah.notifications.enabled' => true]);
        NotificationService::forgetTableExistsCache();
        $service = $this->service();

        $this->assertNull($service->push(1, ['title' => 'x']));
        $this->assertSame(0, $service->toAll(['title' => 'x'], null, false));
        $this->assertFalse($service->tableExists());
    }
}
