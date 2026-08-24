<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Notification;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Events\PtahNotificationCreated;
use Ptah\Livewire\Notification\NotificationBell;
use Ptah\Models\Notification;
use Ptah\Services\Notification\NotificationService;
use RuntimeException;

/**
 * FASE 4 of the notification hook plan: the OPTIONAL Reverb/Echo broadcast
 * layer on top of the always-on database notification. `ptah.notifications.broadcast`
 * defaults to false — every scenario here that flips it back off proves the
 * event system is never touched, and the bell's `wire:poll` (also asserted
 * here) is what keeps the notification reachable for a host WITHOUT
 * Reverb/Echo installed.
 */
class PtahNotificationCreatedEventTest extends NotificationTestCase
{
    private function loginAs(int $id): void
    {
        /** @var class-string<Model> $userClass */
        $userClass = config('auth.providers.users.model');

        $userClass::forceCreate([
            'id' => $id,
            'name' => 'User '.$id,
            'email' => "user{$id}@test.com",
            'password' => bcrypt('secret'),
        ]);

        Auth::loginUsingId($id);
    }

    // ── Gate on NotificationService::push() ──────────────────────────────────

    #[Test]
    public function with_broadcast_disabled_pushing_a_notification_dispatches_no_event(): void
    {
        config(['ptah.notifications.broadcast' => false]);
        Event::fake();

        app(NotificationService::class)->push(1, ['title' => 'x']);

        Event::assertNotDispatched(PtahNotificationCreated::class);
    }

    #[Test]
    public function with_broadcast_enabled_one_event_is_dispatched_per_row_created(): void
    {
        config(['ptah.notifications.broadcast' => true]);
        Event::fake([PtahNotificationCreated::class]);

        $first = app(NotificationService::class)->push(7, ['title' => 'Uma']);
        $second = app(NotificationService::class)->push(7, ['title' => 'Duas']);

        Event::assertDispatchedTimes(PtahNotificationCreated::class, 2);
        Event::assertDispatched(
            PtahNotificationCreated::class,
            fn (PtahNotificationCreated $event) => $event->notificationId === $first->id && $event->userId === 7
        );
        Event::assertDispatched(
            PtahNotificationCreated::class,
            fn (PtahNotificationCreated $event) => $event->notificationId === $second->id && $event->userId === 7
        );
    }

    #[Test]
    public function an_update_via_dedupe_key_also_broadcasts_once_per_call(): void
    {
        config(['ptah.notifications.broadcast' => true]);
        Event::fake([PtahNotificationCreated::class]);

        $service = app(NotificationService::class);
        $service->push(1, ['title' => 'Primeiro', 'dedupe_key' => 'k']);
        $service->push(1, ['title' => 'Segundo', 'dedupe_key' => 'k']);

        // Same underlying row (updateOrCreate) — still one event per push() call.
        Event::assertDispatchedTimes(PtahNotificationCreated::class, 2);
        $this->assertSame(1, Notification::query()->count());
    }

    // ── Channel / event name / payload shape ─────────────────────────────────

    #[Test]
    public function the_event_broadcasts_on_the_users_private_channel_with_the_documented_name(): void
    {
        $event = new PtahNotificationCreated(123, 7);

        $channel = $event->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channel);
        $this->assertSame('private-ptah.notifications.7', $channel->name);
        $this->assertSame('ptah.notification.created', $event->broadcastAs());
    }

    #[Test]
    public function the_broadcast_payload_never_carries_title_or_body(): void
    {
        $event = new PtahNotificationCreated(123, 7);

        $payload = $event->broadcastWith();

        $this->assertSame(['id' => 123], $payload);
        $this->assertArrayNotHasKey('title', $payload);
        $this->assertArrayNotHasKey('body', $payload);
    }

    // ── A broken broadcast connection never blocks the write ─────────────────

    #[Test]
    public function a_broadcast_failure_is_logged_but_never_prevents_the_row_from_being_written(): void
    {
        config(['ptah.notifications.broadcast' => true]);

        // Replaces the real event dispatcher with one that throws ONLY for
        // our event — simulating a misconfigured broadcast connection (e.g.
        // BROADCAST_CONNECTION pointing at an SDK that is not installed)
        // without depending on any real driver's internals.
        $this->app->instance('events', new class($this->app) extends Dispatcher
        {
            public function dispatch($event, $payload = [], $halt = false)
            {
                if ($event instanceof PtahNotificationCreated) {
                    throw new RuntimeException('broadcast connection is not configured');
                }

                return parent::dispatch($event, $payload, $halt);
            }
        });

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) => $message === '[Ptah] Failed to broadcast notification created event'
                && isset($context['notification_id'])
            );

        $notification = app(NotificationService::class)->push(1, ['title' => 'x']);

        $this->assertNotNull($notification);
        $this->assertSame(1, Notification::query()->count());
    }

    // ── NotificationBell listener gating ─────────────────────────────────────

    #[Test]
    public function the_bell_registers_the_echo_private_listener_only_when_broadcast_is_enabled(): void
    {
        $this->loginAs(9);

        config(['ptah.notifications.broadcast' => false]);
        $this->assertSame([], Livewire::test(NotificationBell::class)->instance()->getListeners());

        config(['ptah.notifications.broadcast' => true]);
        $listeners = Livewire::test(NotificationBell::class)->instance()->getListeners();

        $this->assertSame(
            ['echo-private:ptah.notifications.9,.ptah.notification.created' => '$refresh'],
            $listeners
        );
    }

    #[Test]
    public function the_bell_registers_no_listener_for_a_guest_even_with_broadcast_enabled(): void
    {
        config(['ptah.notifications.broadcast' => true]);

        $listeners = Livewire::test(NotificationBell::class)->instance()->getListeners();

        $this->assertSame([], $listeners);
    }

    #[Test]
    public function the_bell_keeps_polling_regardless_of_the_broadcast_flag(): void
    {
        $this->loginAs(1);
        config(['ptah.notifications.poll' => '60s']);

        config(['ptah.notifications.broadcast' => false]);
        $this->assertStringContainsString('wire:poll.60s', Livewire::test(NotificationBell::class)->html());

        config(['ptah.notifications.broadcast' => true]);
        $this->assertStringContainsString('wire:poll.60s', Livewire::test(NotificationBell::class)->html());
    }
}
