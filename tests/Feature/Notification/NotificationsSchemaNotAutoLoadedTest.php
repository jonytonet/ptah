<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Notification;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * `ptah.notifications.enabled` only gates the SERVICE — it must never make
 * PtahServiceProvider auto-load database/migrations/ (see that migration's
 * docblock and OptInSchemaIsFrozenTest). Enabling the config flag with the
 * package's regular migrations already run (as every test in this suite
 * does via TestCase::defineDatabaseMigrations()) must still leave
 * `ptah_notifications` absent — the table only exists once the CONSUMER
 * publishes and runs the opt-in migration themselves.
 */
class NotificationsSchemaNotAutoLoadedTest extends TestCase
{
    #[Test]
    public function the_notifications_table_does_not_exist_even_when_the_flag_is_enabled(): void
    {
        config(['ptah.notifications.enabled' => true]);

        $this->assertFalse(Schema::hasTable('ptah_notifications'));
    }

    #[Test]
    public function the_notifications_table_does_not_exist_when_the_flag_is_disabled_either(): void
    {
        config(['ptah.notifications.enabled' => false]);

        $this->assertFalse(Schema::hasTable('ptah_notifications'));
    }
}
