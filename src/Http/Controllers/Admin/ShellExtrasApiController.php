<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\PaginationState;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Ptah\Http\Headless\HeadlessExportsPanel;
use Ptah\Http\Headless\HeadlessNotificationBell;
use Ptah\Models\Export;
use Ptah\Models\Notification;
use Ptah\Services\Notification\NotificationService;

/**
 * The two pieces of the layout that are Livewire components and not pages —
 * the notification bell and the exports panel — for the React and Vue stacks
 * (1.59.0).
 *
 * Each action runs the component as a plain object (Ptah\Http\Headless\*):
 * the user/company scoping, the safe-URL check and the ownership guards are
 * the component's own code. This controller only carries the request in and
 * a whitelisted answer out.
 */
class ShellExtrasApiController extends Controller
{
    /**
     * The exports panel polls every 5s while a row is still queued or
     * processing (`wire:poll.5s` in its view), and not at all once every row
     * has settled.
     */
    private const EXPORTS_POLL_MS = 5000;

    // ── Notification bell ───────────────────────────────────────────────────

    /**
     * The badge, and the dropdown's items when `items=1` (the dropdown is
     * open). Like the Livewire bell, a closed dropdown never queries the list.
     */
    public function notifications(Request $request): JsonResponse
    {
        if (! $this->notificationsEnabled()) {
            // The Livewire bell is not even registered with the flag off: the
            // client draws nothing (it is not an error, the feature is off).
            return response()->json([
                'enabled' => false,
                'unread' => 0,
                'poll_ms' => null,
                'items' => null,
                'view_all_url' => null,
            ]);
        }

        return response()->json($this->bellPayload(HeadlessNotificationBell::headless(), $request->boolean('items')));
    }

    /**
     * A click on a notification: marks it read and answers where to go. The
     * id must be in the user's own list — exactly what openItem() looks it up
     * in — so another user's notification is a 404 and nothing is marked.
     */
    public function openNotification(string $id): JsonResponse
    {
        abort_unless($this->notificationsEnabled(), 404);

        $bell = HeadlessNotificationBell::headless();

        // The same lookup openItem() makes; checked first only to tell the
        // client "not yours / gone" instead of a silent nothing.
        abort_unless($bell->items()->contains('id', (int) $id), 404);

        $bell->run('openItem', ['id' => (int) $id]);

        return response()->json(['ok' => true, 'redirect' => $bell->redirectTarget()] + $this->bellPayload($bell, true));
    }

    public function markAllNotificationsRead(): JsonResponse
    {
        abort_unless($this->notificationsEnabled(), 404);

        $bell = HeadlessNotificationBell::headless()->run('markAllRead');

        return response()->json(['ok' => true, 'messages' => $bell->toasts()] + $this->bellPayload($bell, true));
    }

    /** dismiss() scopes its UPDATE by the signed-in user: another user's id changes nothing. */
    public function dismissNotification(string $id): JsonResponse
    {
        abort_unless($this->notificationsEnabled(), 404);

        $bell = HeadlessNotificationBell::headless()->run('dismiss', ['id' => (int) $id]);

        return response()->json(['ok' => true] + $this->bellPayload($bell, true));
    }

    // ── Exports panel ───────────────────────────────────────────────────────

    /** The signed-in user's exports, 10 a page, newest first. */
    public function exports(Request $request): JsonResponse
    {
        return response()->json($this->exportsPayload(HeadlessExportsPanel::headless(), max(1, $request->integer('page', 1))));
    }

    /**
     * Removes one export (file and row). remove() only finds the user's own
     * exports, so an id that is someone else's — or that does not exist — is
     * left alone and answered with a 404.
     */
    public function removeExport(Request $request, string $id): JsonResponse
    {
        $existed = Export::query()->whereKey((int) $id)->exists();

        $panel = HeadlessExportsPanel::headless()->run('remove', ['exportId' => (int) $id]);

        abort_unless($existed && ! Export::query()->whereKey((int) $id)->exists(), 404);

        return response()->json(['ok' => true] + $this->exportsPayload($panel, max(1, $request->integer('page', 1))));
    }

    // ── Answers ─────────────────────────────────────────────────────────────

    private function notificationsEnabled(): bool
    {
        return (bool) config('ptah.notifications.enabled');
    }

    /** @return array<string, mixed> */
    private function bellPayload(HeadlessNotificationBell $bell, bool $withItems): array
    {
        return [
            'enabled' => true,
            'unread' => $bell->unread(),
            'poll_ms' => $this->milliseconds($bell->pollInterval()),
            'items' => $withItems ? $bell->items()->map(fn (Notification $item) => $this->notificationRow($item))->values()->all() : null,
            'view_all_url' => Route::has('ptah.notifications') ? route('ptah.notifications') : null,
        ];
    }

    /**
     * What the dropdown draws of a notification — never the row itself
     * (user_id, company_id, dedupe_key stay on the server). The URL is the
     * safe one, as the view renders it: a dangerous scheme comes back null.
     *
     * @return array<string, mixed>
     */
    private function notificationRow(Notification $item): array
    {
        /** @var Carbon|null $created */
        $created = $item->getAttribute('created_at');

        return [
            'id' => (int) $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'body' => $item->body,
            'icon' => $item->icon,
            'url' => NotificationService::safeUrl($item->url),
            'action_label' => $item->action_label ?: null,
            'read' => $item->read_at !== null,
            'created_at' => $created?->toIso8601String(),
            'created_at_human' => $created?->diffForHumans(),
        ];
    }

    /** `wire:poll` syntax ('60s', '30000ms') → milliseconds; null keeps polling off. */
    private function milliseconds(?string $interval): ?int
    {
        if ($interval === null || ! preg_match('/^(\d+)(ms|s)$/', $interval, $match)) {
            return null;
        }

        $ms = $match[2] === 's' ? (int) $match[1] * 1000 : (int) $match[1];

        return $ms > 0 ? $ms : null;
    }

    /** @return array<string, mixed> */
    private function exportsPayload(HeadlessExportsPanel $panel, int $page): array
    {
        Paginator::currentPageResolver(fn () => $page);

        try {
            $rows = $panel->exports();

            // A page past the last one (the last row of it was just removed)
            // shows the last page, not an empty list.
            if ($rows->isEmpty() && $page > 1 && $rows->lastPage() < $page) {
                $last = $rows->lastPage();
                Paginator::currentPageResolver(fn () => $last);
                $rows = $panel->exports();
            }

            $pending = $panel->hasPending();
        } finally {
            // Back to the framework's resolvers: the closures above must not
            // outlive this call.
            PaginationState::resolveUsing(app());
        }

        return [
            'data' => array_map(fn (Export $row) => $this->exportRow($row), $rows->items()),
            'meta' => $this->meta($rows),
            'has_pending' => $pending,
            'poll_ms' => $pending ? self::EXPORTS_POLL_MS : null,
        ];
    }

    /**
     * What the panel draws of an export — not the payload (ids, columns,
     * guard), the file path or the owner. The download link is only given
     * when the view would show it: done and not expired.
     *
     * @return array<string, mixed>
     */
    private function exportRow(Export $row): array
    {
        /** @var Carbon|null $created */
        $created = $row->getAttribute('created_at');
        $expired = $row->expires_at !== null && $row->expires_at->isPast();
        $canDownload = $row->status === 'done' && ! $expired;

        return [
            'id' => (int) $row->id,
            'model' => class_basename(str_replace('/', '\\', (string) $row->model)),
            'format' => $row->format,
            'status' => $row->status,
            'error' => $row->status === 'failed' ? ($row->error ?: null) : null,
            'rows' => $row->rows,
            'created_at' => $created?->toIso8601String(),
            'created_at_display' => $created?->format('d/m/Y H:i:s'),
            'expired' => $expired,
            'download_url' => $canDownload ? route('ptah.export.file', ['export' => $row->id]) : null,
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Export>  $rows
     * @return array<string, int>
     */
    private function meta(LengthAwarePaginator $rows): array
    {
        return [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
        ];
    }
}
