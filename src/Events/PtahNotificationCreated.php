<?php

declare(strict_types=1);

namespace Ptah\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ptah\Services\Notification\CrudNotificationDispatcher;
use Ptah\Services\Notification\NotificationService;

/**
 * Fired from the single {@see NotificationService::push()}
 * funnel — the same path used by CRUD-driven notifications
 * ({@see CrudNotificationDispatcher}) and by any
 * host call to `ptah_notify()`/`NotificationService::push()` directly.
 *
 * Deliberately carries ONLY the notification id and the recipient's user id
 * — never the title/body. The bell only needs a trigger to re-run
 * `$refresh` (it already re-reads the row, scoped to the authenticated
 * user, via NotificationService::list()); the broadcastWith() payload
 * travels through the websocket server, so it must never leak content that
 * could be sensitive.
 *
 * Only dispatched when `ptah.notifications.broadcast` is true — see
 * NotificationService::push() for the gate and the try/catch around it.
 */
class PtahNotificationCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $notificationId,
        public int $userId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('ptah.notifications.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'ptah.notification.created';
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->notificationId];
    }
}
