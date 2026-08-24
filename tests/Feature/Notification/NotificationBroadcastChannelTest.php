<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Notification;

use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Events\PtahNotificationCreated;
use Ptah\Tests\TestCase;
use ReflectionClass;

/**
 * FASE 4 of the notification hook plan: PtahServiceProvider::registerNotificationBroadcastChannel()
 * — the boot-time `ptah.notifications.{userId}` private channel authorization
 * used by {@see PtahNotificationCreated}. Extends the base
 * TestCase (not NotificationTestCase): channel registration does not touch
 * the opt-in `ptah_notifications` table at all.
 */
class NotificationBroadcastChannelTest extends TestCase
{
    protected function enableBroadcast($app): void
    {
        $app['config']->set('ptah.notifications.broadcast', true);
    }

    /**
     * Simulates a host whose BROADCAST_CONNECTION points at a driver SDK
     * that is not installed (no `pusher/pusher-php-server` in this
     * package's own vendor/) — exactly the situation the try/catch around
     * Broadcast::channel() exists for.
     */
    protected function enableBroadcastWithAnUnavailableDriver($app): void
    {
        $app['config']->set('ptah.notifications.broadcast', true);
        $app['config']->set('broadcasting.default', 'pusher');
    }

    #[Test]
    public function with_broadcast_disabled_the_default_no_channel_is_registered(): void
    {
        $this->assertArrayNotHasKey('ptah.notifications.{userId}', $this->registeredChannels());
    }

    #[Test]
    #[DefineEnvironment('enableBroadcast')]
    public function with_broadcast_enabled_the_private_channel_is_registered_and_authorizes_only_its_owner(): void
    {
        $channels = $this->registeredChannels();

        $this->assertArrayHasKey('ptah.notifications.{userId}', $channels);

        $authorize = $channels['ptah.notifications.{userId}'];
        $user = new class
        {
            public function getAuthIdentifier(): int
            {
                return 9;
            }
        };

        $this->assertTrue($authorize($user, '9'));
        $this->assertFalse($authorize($user, '10'));
    }

    #[Test]
    #[DefineEnvironment('enableBroadcastWithAnUnavailableDriver')]
    public function a_broadcast_driver_whose_sdk_is_missing_never_breaks_the_boot(): void
    {
        // Reaching this line already proves PtahServiceProvider::boot() did
        // not let Broadcast::channel()'s exception escape — an uncaught
        // Throwable during boot would have failed setUp() itself, before
        // this test body ever ran.
        $this->assertTrue(true);
    }

    /**
     * @return array<string, callable>
     */
    private function registeredChannels(): array
    {
        $broadcaster = app(BroadcastFactory::class)->connection();

        $property = (new ReflectionClass($broadcaster))->getProperty('channels');
        $property->setAccessible(true);

        return $property->getValue($broadcaster);
    }
}
