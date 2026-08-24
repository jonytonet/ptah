<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Notification;

use Ptah\Services\Notification\NotificationService;
use Ptah\Tests\TestCase;

/**
 * Shared base for NotificationService/NotificationBell tests: loads the
 * opt-in database/migrations/ (create_ptah_notifications_table) ON TOP OF
 * the base TestCase's tests/migrations + src/Migrations, and enables
 * `ptah.notifications.enabled` — mirrors the pattern already used by
 * tests/Unit/Traits/HasAuditFieldsTest.php for its own extra migration dir.
 */
abstract class NotificationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // NotificationService::tableExists() memoizes across calls within the
        // same PHP process — reset it so one test's cached result never
        // leaks into the next (see forgetTableExistsCache()'s own docblock).
        NotificationService::forgetTableExistsCache();
    }

    protected function tearDown(): void
    {
        NotificationService::forgetTableExistsCache();

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ptah.notifications.enabled', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
    }
}
