<?php

declare(strict_types=1);

namespace Ptah\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Ptah\Services\Notification\CrudNotificationDispatcher;
use Ptah\Services\Notification\NotificationService;
use Throwable;

/**
 * Delivers ONE {@see CrudNotificationDispatcher}
 * rule to its resolved audience. Deliberately holds only scalars/arrays —
 * NEVER the triggering model, and NEVER {@see SerializesModels}
 * — the dispatcher already resolved every placeholder into $payload before
 * queueing, which is exactly what lets a `deleted` event notify correctly:
 * by the time this job runs the row may be gone, but the text was already
 * baked in.
 *
 * `$afterCommit = true` covers both a bulk delete wrapped in DB::transaction
 * (the job must not run — let alone insert a notification row — until the
 * transaction actually commits) AND the `sync` queue driver, whose
 * SyncQueue::push() honours afterCommit too.
 */
class SendCrudNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    /**
     * @param  'user'|'role'|'staff'|string  $audience
     * @param  array<string, mixed>  $payload  type,category,title,body,icon,url,action_label,company_id
     */
    public function __construct(
        public string $audience,
        public string $audienceValue,
        public array $payload,
        public ?int $companyId,
        public ?int $exceptUserId,
    ) {
        // Assigned here rather than as a class property: the Queueable trait
        // already declares `$afterCommit` with no default, and redeclaring
        // it in the class body with a different default is a FATAL "the
        // definition differs and is considered incompatible" error.
        $this->afterCommit = true;
    }

    public function backoff(): int
    {
        return 30;
    }

    public function handle(NotificationService $notifications): void
    {
        try {
            match ($this->audience) {
                'user' => $this->routeToUser($notifications),
                'role' => $notifications->toRole($this->audienceValue, $this->payload, $this->companyId, $this->exceptUserId),
                'staff' => $notifications->toAll($this->payload, $this->companyId, true, $this->exceptUserId),
                default => null,
            };
        } catch (Throwable $e) {
            // A notification must never break the CRUD save that triggered
            // it — with the `sync` driver this job runs inline, so without
            // this catch a delivery failure would surface as a save error.
            Log::warning('[Ptah] CRUD notification delivery failed', [
                'audience' => $this->audience,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function routeToUser(NotificationService $notifications): void
    {
        $userId = (int) $this->audienceValue;

        if ($userId <= 0) {
            return;
        }

        if ($this->exceptUserId !== null && $this->exceptUserId === $userId) {
            return;
        }

        $notifications->toUser($userId, $this->payload);
    }
}
