{{--
    ptah-notification-bell — Camada 2 navbar bell.

    $open is server-driven (Livewire #[Locked]) on purpose: items() only runs
    its query when $open is true, so a dropdown nobody opened never touches
    the database. Esc / click-away close it via a normal Livewire round trip
    ($wire.close()) — no local Alpine state to keep in sync with the server.

    Reuses .ptah-admin-dropdown (forge-navbar.blade.php) for the panel/link/
    button/icon dark-mode tokens — no new CSS. Every row is either an <a>
    (has a url — the whole row is the "ver detalhes" affordance, href kept
    for copy/open-in-new-tab, click still goes through the server via
    wire:click.prevent) or a disabled <button> (no url — informational only),
    so its text always inherits the token-driven color from the existing
    ".ptah-admin-dropdown a/button" rule instead of a hardcoded gray.
--}}
<div x-data="{}" class="relative">
    <button
        type="button"
        wire:click="toggle"
        @if($this->pollInterval()) wire:poll.{{ $this->pollInterval() }}="$refresh" @endif
        aria-haspopup="menu"
        aria-expanded="{{ $open ? 'true' : 'false' }}"
        aria-label="{{ __('ptah::ui.notif_bell_title') }}"
        title="{{ __('ptah::ui.notif_bell_title') }}"
        class="ptah-navbar-icon-btn relative p-2 rounded-md hover:text-primary transition-colors"
    >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        @if($unreadCount > 0)
            <span
                aria-label="{{ __('ptah::ui.notif_unread_badge_label', ['count' => $unreadCount]) }}"
                class="absolute -top-1 -right-1 min-w-[1.1rem] h-[1.1rem] px-1 inline-flex items-center justify-center rounded-full bg-danger text-white text-[10px] font-bold leading-none"
            >
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    @if($open)
        <div
            @click.away="$wire.close()"
            @keydown.escape.window="$wire.close()"
            class="ptah-admin-dropdown absolute right-0 mt-2 w-80 max-w-[90vw] rounded-md border py-1 z-50"
            role="menu"
        >
            @forelse($notifications as $notification)
                @php($__safeUrl = \Ptah\Services\Notification\NotificationService::safeUrl($notification->url))

                @if($__safeUrl)
                    <a
                        href="{{ $__safeUrl }}"
                        wire:click.prevent="openItem({{ $notification->id }})"
                        role="menuitem"
                        class="flex flex-col gap-0.5 px-4 py-2.5 text-sm transition-colors"
                    >
                        <span class="font-medium">{{ $notification->title }}</span>
                        @if($notification->body)
                            <span class="text-xs opacity-80 line-clamp-2">{{ $notification->body }}</span>
                        @endif
                        <span class="text-[11px] opacity-60">{{ $notification->created_at?->diffForHumans() }}</span>
                        <span class="text-xs font-semibold mt-0.5">
                            {{ $notification->action_label ?: __('ptah::ui.notif_action_default') }}
                        </span>
                    </a>
                @else
                    <button
                        type="button"
                        disabled
                        aria-disabled="true"
                        role="menuitem"
                        class="flex w-full flex-col gap-0.5 px-4 py-2.5 text-sm text-left cursor-default"
                    >
                        <span class="font-medium">{{ $notification->title }}</span>
                        @if($notification->body)
                            <span class="text-xs opacity-80 line-clamp-2">{{ $notification->body }}</span>
                        @endif
                        <span class="text-[11px] opacity-60">{{ $notification->created_at?->diffForHumans() }}</span>
                    </button>
                @endif

                <button
                    type="button"
                    wire:click="dismiss({{ $notification->id }})"
                    title="{{ __('ptah::ui.notif_dismiss') }}"
                    class="w-full flex items-center gap-2 px-4 pb-2 text-xs opacity-70 hover:opacity-100 transition-opacity"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    {{ __('ptah::ui.notif_dismiss') }}
                </button>

                <hr class="my-1">
            @empty
                {{-- <button disabled>, not <div>: only a/button inherit the
                     token-driven text color from .ptah-admin-dropdown. --}}
                <button type="button" disabled aria-disabled="true" class="w-full px-4 py-6 text-sm text-center opacity-70 cursor-default">
                    {{ __('ptah::ui.notif_empty') }}
                </button>
            @endforelse

            <div class="flex items-center justify-between px-4 pt-1">
                <button type="button" wire:click="markAllRead" class="text-xs font-semibold">
                    {{ __('ptah::ui.notif_mark_all_read') }}
                </button>

                @if(\Illuminate\Support\Facades\Route::has('ptah.notifications'))
                    <a href="{{ route('ptah.notifications') }}" role="menuitem" class="text-xs font-semibold">
                        {{ __('ptah::ui.notif_view_all') }}
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
