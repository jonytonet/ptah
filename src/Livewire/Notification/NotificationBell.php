<?php

declare(strict_types=1);

namespace Ptah\Livewire\Notification;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Ptah\Events\PtahNotificationCreated;
use Ptah\Models\Notification;
use Ptah\Services\Notification\NotificationService;
use Ptah\Traits\ResolvesUser;

/**
 * "ptah-notification-bell" — the Camada 2 navbar bell: unread badge + dropdown
 * of recent, active notifications, backed by NotificationService. Registered
 * by PtahServiceProvider only when `ptah.notifications.enabled` is true, and
 * plugged into the navbar via `config('ptah.navbar.notifications')` (see
 * Ptah\Support\NavbarSlot / forge-navbar.blade.php).
 *
 * Everything here degrades neutrally (empty list, zero badge, no exception)
 * when the opt-in `ptah_notifications` table is not migrated yet — the same
 * guarantee NotificationService itself gives (see its tableExists()).
 */
class NotificationBell extends Component
{
    use ResolvesUser;

    /**
     * Whether the dropdown is open. #[Locked] so the client cannot flip it
     * directly via the request payload (Livewire only lets server-side
     * methods — toggle()/close() — change it) — that is also what gates
     * items() from ever running its query for a dropdown nobody opened.
     */
    #[Locked]
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function close(): void
    {
        $this->open = false;
    }

    #[Computed]
    public function unread(): int
    {
        return app(NotificationService::class)->unreadCount(null, $this->companyId());
    }

    /**
     * Recent, active notifications for the dropdown. The view only calls
     * this (via render()) when $open is true — the whole point of the
     * Locked $open flag is to keep this query from ever running for a
     * dropdown that was never opened.
     *
     * @return Collection<int, Notification>
     */
    #[Computed]
    public function items(): Collection
    {
        $limit = (int) config('ptah.notifications.dropdown_limit', 20);

        return app(NotificationService::class)->list(null, $this->companyId(), $limit);
    }

    /**
     * Marks one notification read and redirects to its `url` (safeUrl()
     * neutralises any dangerous scheme). The notification must already be
     * present in the CURRENT user's own items() — that collection is scoped
     * to the authenticated user/company by NotificationService::list(), so
     * an id belonging to another user is simply never found here, and
     * nothing is marked or redirected.
     */
    public function openItem(int $id): void
    {
        $notification = $this->items()->firstWhere('id', $id);

        if (! $notification) {
            return;
        }

        app(NotificationService::class)->markRead($id);

        $url = NotificationService::safeUrl($notification->url);

        if ($url !== null) {
            $this->redirect($url);
        }
    }

    public function markAllRead(): void
    {
        app(NotificationService::class)->markAllRead(null, $this->companyId());
    }

    public function dismiss(int $id): void
    {
        // dismiss() itself resolves the current user and scopes the WHERE
        // clause by it — no separate ownership check needed here.
        app(NotificationService::class)->dismiss($id);
    }

    /**
     * `wire:poll` interval for the unread badge, or null to disable polling.
     * Reads `ptah.notifications.poll` — 'none'/false/'' turns it off; any
     * value not matching \d+(ms|s) is treated the same way rather than being
     * emitted as a broken Alpine/Livewire attribute.
     */
    public function pollInterval(): ?string
    {
        $configured = config('ptah.notifications.poll', '60s');

        if ($configured === false || $configured === null) {
            return null;
        }

        $value = trim((string) $configured);

        if ($value === '' || strtolower($value) === 'none') {
            return null;
        }

        if (! preg_match('/^\d+(ms|s)$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * Same session-based company resolution PermissionService::resolveCompanyId()
     * uses (deliberately re-implemented rather than calling CompanyService,
     * which queries `ptah_companies` even when the company module is off).
     */
    private function companyId(): ?int
    {
        if (! config('ptah.permissions.multi_company', true)) {
            return null;
        }

        $key = config('ptah.permissions.company_session_key', 'ptah_company_id');

        return $key && session()->has($key) ? (int) session($key) : null;
    }

    /**
     * Registers the Echo private-channel listener that lets the bell
     * `$refresh` the instant {@see PtahNotificationCreated} is
     * broadcast for the current user — same dot-prefixed event-name
     * convention BaseCrud already uses for its own Echo listeners (see
     * BaseCrud::getListeners()).
     *
     * Only registered when BOTH `ptah.notifications.broadcast` is enabled
     * AND a user id can be resolved (a guest has no private channel to
     * listen on). With the flag off — the default — this returns an empty
     * array: without Laravel Echo loaded in the browser, Livewire simply
     * logs a console.warn for an unknown listener type and moves on, but
     * skipping the entry entirely avoids even that noise.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        if (! config('ptah.notifications.broadcast')) {
            return [];
        }

        $userId = $this->resolveUserId(null);

        if ($userId === null) {
            return [];
        }

        return ["echo-private:ptah.notifications.{$userId},.ptah.notification.created" => '$refresh'];
    }

    public function render()
    {
        return view('ptah::livewire.notification.notification-bell', [
            'unreadCount' => $this->unread(),
            'notifications' => $this->open ? $this->items() : new Collection,
        ]);
    }
}
