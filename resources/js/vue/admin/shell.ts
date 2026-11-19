/**
 * The layout's notification bell and exports panel for Vue, over the shell
 * API. Who sees which notification or export, the safe URLs and the
 * ownership checks are the server's (NotificationBell, ExportsPanel); these
 * components only ask and show.
 */
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { CSSProperties, PropType } from 'vue';
import { navigateTo, PtahApi } from '../../core';
import {
    badgeText,
    dismissNotification,
    exportStatusColor,
    fetchBell,
    fetchExports,
    markAllNotificationsRead,
    openNotification,
    removeExport,
    shellWords,
    startPolling,
} from '../../core/admin/shell';
import type { BellState, ExportRow, ExportsListing, ShellMessage, ShellNotification } from '../../core/admin/shell';
import { listenBroadcast } from '../../core/realtime';
import { ForgeAlert, ForgeButton, ForgeEmpty, ForgeModal, ForgePagination, ForgeSpinner } from '../forge';
import { icon } from '../icons';

const shellProps = {
    api: { type: Object as PropType<PtahApi>, default: undefined },
    locale: { type: String, default: undefined },
    /** Milliseconds between refreshes; null turns polling off. Defaults to what the server reports. */
    pollInterval: { type: [Number, null] as PropType<number | null>, default: undefined },
};

const statusStyle: CSSProperties = { display: 'inline-block', padding: '2px 8px', borderRadius: '999px', background: 'var(--pf-c)', color: 'var(--pf-on)', fontSize: '12px', fontWeight: 500 };

/** Polls with the given interval while it is set; the timer is cleared on unmount. */
function usePolling(task: () => void, interval: () => number | null | undefined) {
    let stop: () => void = () => undefined;
    const restart = () => {
        stop();
        stop = startPolling(task, interval());
    };
    watch(interval, restart);
    onMounted(restart);
    onBeforeUnmount(() => stop());
}

// ── Notification bell ───────────────────────────────────────────────────────

export const PtahNotificationBell = defineComponent({
    name: 'PtahNotificationBell',
    props: {
        ...shellProps,
        /** Go to a URL without a full page load. Defaults to the mounted app's navigator, else a normal load. */
        onNavigate: { type: Function as PropType<(url: string) => void>, default: undefined },
    },
    setup(props) {
        const client = props.api ?? new PtahApi();
        const t = computed(() => shellWords(props.locale));
        const state = ref<BellState | null>(null);
        const open = ref(false);
        const busy = ref(false);
        const error = ref<string | null>(null);
        const notice = ref<ShellMessage | null>(null);
        const root = ref<HTMLElement | null>(null);

        const load = async (withItems: boolean) => {
            try {
                state.value = await fetchBell(client, withItems);
                error.value = null;
            } catch {
                error.value = t.value.error;
            }
        };

        onMounted(() => void load(false));

        // The prop wins; otherwise the interval the server is configured with.
        usePolling(
            () => void load(open.value),
            () => (state.value?.enabled ? (props.pollInterval !== undefined ? props.pollInterval : (state.value.poll_ms ?? null)) : null),
        );
        // And at once when the server says so (a websocket, when the app has Echo).
        let stopListening: () => void = () => undefined;
        watch(
            () => (state.value?.enabled ? (state.value.broadcast?.channel ?? '') : ''),
            () => {
                stopListening();
                stopListening = state.value?.enabled ? listenBroadcast(state.value.broadcast, () => void load(open.value)) : () => undefined;
            },
        );
        onBeforeUnmount(() => stopListening());

        const close = () => {
            open.value = false;
            notice.value = null;
        };

        // Esc and a click outside close the dropdown, as on the Livewire bell.
        const onKey = (event: KeyboardEvent) => open.value && event.key === 'Escape' && close();
        const onDown = (event: MouseEvent) => open.value && root.value && !root.value.contains(event.target as Node) && close();
        onMounted(() => {
            document.addEventListener('keydown', onKey);
            document.addEventListener('mousedown', onDown);
        });
        onBeforeUnmount(() => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('mousedown', onDown);
        });

        const toggle = () => {
            if (open.value) {
                close();

                return;
            }
            open.value = true;
            if (state.value) {
                state.value = { ...state.value, items: null };
            }
            void load(true);
        };

        const act = async (action: () => Promise<BellState>): Promise<BellState | null> => {
            busy.value = true;
            try {
                const answer = await action();
                state.value = answer;
                error.value = null;

                return answer;
            } catch {
                // Gone or not this user's: show the list as the server has it now.
                error.value = t.value.error;
                void load(true);

                return null;
            } finally {
                busy.value = false;
            }
        };

        const openItem = async (event: Event, item: ShellNotification) => {
            event.preventDefault();
            const answer = await act(() => openNotification(client, item.id));
            if (answer?.redirect) {
                (props.onNavigate ?? navigateTo)(answer.redirect);
            }
        };

        const markAll = async () => {
            const answer = await act(() => markAllNotificationsRead(client));
            notice.value = answer?.messages?.[0] ?? null;
        };

        const itemText = (item: ShellNotification) => [
            h('span', { class: 'pl-bell-title' }, item.title),
            item.body ? h('span', { class: 'pl-bell-body' }, item.body) : null,
            item.created_at_human ? h('span', { class: 'pl-bell-time' }, item.created_at_human) : null,
        ];

        // The markup and classes of notification-bell.blade.php: a navbar icon
        // button with the count, and a panel of the admin dropdown's look.
        return () => {
            const current = state.value;
            // Until the count arrives the bell is already in the bar (the layout knows it is on): no pop-in.
            if (!current) {
                return h('div', { class: 'pl-dropdown-root pl-bell', 'aria-hidden': 'true' }, [h('span', { class: 'pl-icon-btn' }, [icon('bell', 20, undefined, 'pl-svg')])]);
            }
            if (!current.enabled) {
                return null;
            }
            const words = t.value;
            const items = current.items;
            const label = current.unread > 0 ? `${words.bellTitle} (${words.unreadBadge(current.unread)})` : words.bellTitle;

            return h('div', { class: 'pl-dropdown-root pl-bell', ref: root }, [
                h('button', { type: 'button', class: 'pl-icon-btn', 'aria-haspopup': 'menu', 'aria-expanded': open.value ? 'true' : 'false', 'aria-label': label, title: words.bellTitle, onClick: toggle }, [
                    icon('bell', 20, undefined, 'pl-svg'),
                    current.unread > 0 ? h('span', { class: 'pl-badge', 'aria-hidden': 'true' }, badgeText(current.unread)) : null,
                ]),

                open.value
                    ? h('div', { class: 'pl-dropdown pl-dropdown--bell', role: 'menu', 'aria-label': words.bellTitle, 'aria-busy': busy.value || items === null ? 'true' : 'false' }, [
                          // Only the items scroll: the footer stays reachable however long the list is.
                          h(
                              'div',
                              { role: 'none', class: 'pl-bell-scroll' },
                              items === null
                                  ? [h('div', { role: 'none', class: 'pl-bell-empty' }, [h(ForgeSpinner, { size: 'sm', label: words.loading })])]
                                  : items.length === 0
                                    ? [h('p', { role: 'none', class: 'pl-bell-empty' }, words.empty)]
                                    : items.map((item) =>
                                          h('div', { role: 'none', key: item.id, class: 'pl-bell-row' }, [
                                              item.url
                                                  ? h('a', { role: 'menuitem', class: 'pl-bell-item', href: item.url, onClick: (event: Event) => void openItem(event, item) }, [
                                                        ...itemText(item),
                                                        h('span', { class: 'pl-bell-action' }, item.action_label || words.actionDefault),
                                                    ])
                                                  : h('button', { type: 'button', role: 'menuitem', class: 'pl-bell-item', disabled: true, 'aria-disabled': 'true' }, itemText(item)),
                                              h(
                                                  'button',
                                                  {
                                                      type: 'button',
                                                      role: 'menuitem',
                                                      class: 'pl-bell-dismiss',
                                                      'aria-label': `${words.dismiss}: ${item.title}`,
                                                      title: words.dismiss,
                                                      disabled: busy.value,
                                                      onClick: () => void act(() => dismissNotification(client, item.id)),
                                                  },
                                                  [icon('x', 14), words.dismiss],
                                              ),
                                          ]),
                                      ),
                          ),

                          error.value ? h('p', { role: 'alert', class: 'pl-bell-note pl-bell-note--error' }, error.value) : null,
                          notice.value ? h('p', { role: 'status', class: 'pl-bell-note' }, notice.value.text) : null,

                          h('div', { role: 'none', class: 'pl-bell-foot' }, [
                              h('button', { type: 'button', role: 'menuitem', class: 'pl-bell-link', disabled: busy.value, onClick: () => void markAll() }, words.markAllRead),
                              current.view_all_url ? h('a', { role: 'menuitem', class: 'pl-bell-link', href: current.view_all_url }, words.viewAll) : null,
                          ]),
                      ])
                    : null,
            ]);
        };
    },
});

// ── Exports panel ───────────────────────────────────────────────────────────

export const PtahExportsPanel = defineComponent({
    name: 'PtahExportsPanel',
    props: shellProps,
    setup(props) {
        const client = props.api ?? new PtahApi();
        const t = computed(() => shellWords(props.locale));
        const listing = ref<ExportsListing | null>(null);
        const loading = ref(false);
        const error = ref<string | null>(null);
        const confirming = ref<ExportRow | null>(null);
        const removing = ref<number | null>(null);
        const page = computed(() => listing.value?.meta.current_page ?? 1);

        const load = async (target: number) => {
            loading.value = true;
            try {
                listing.value = await fetchExports(client, target);
                error.value = null;
            } catch {
                error.value = t.value.error;
            } finally {
                loading.value = false;
            }
        };

        onMounted(() => void load(1));

        // Only while an export is still queued/processing, as `wire:poll.5s` does.
        usePolling(
            () => void load(page.value),
            () => (listing.value?.has_pending ? (props.pollInterval !== undefined ? props.pollInterval : (listing.value.poll_ms ?? null)) : null),
        );

        const remove = async () => {
            const row = confirming.value;
            confirming.value = null;
            if (!row) {
                return;
            }
            removing.value = row.id;
            try {
                listing.value = await removeExport(client, row.id, page.value);
                error.value = null;
            } catch {
                error.value = t.value.error;
                void load(page.value);
            } finally {
                removing.value = null;
            }
        };

        return () => {
            const words = t.value;
            const current = listing.value;
            const headers: [string, 'left' | 'center' | 'right'][] = [
                [words.colModel, 'left'],
                [words.colFormat, 'left'],
                [words.colStatus, 'center'],
                [words.colRows, 'right'],
                [words.colCreated, 'left'],
                [words.colActions, 'right'],
            ];

            let body;
            if (current === null) {
                body = error.value ? null : h(ForgeSpinner, { label: words.loading });
            } else if (current.data.length === 0) {
                body = h(ForgeEmpty, { title: words.exportsEmpty });
            } else {
                body = h('div', { class: 'pf-table-wrap', 'aria-busy': loading.value ? 'true' : undefined }, [
                    h('table', { class: 'pf-table' }, [
                        h('thead', [h('tr', headers.map(([header, align]) => h('th', { key: header, scope: 'col', style: { textAlign: align } }, header)))]),
                        // Announces queued → processing → done/failed as the panel refreshes.
                        h(
                            'tbody',
                            { 'aria-live': 'polite' },
                            current.data.map((row) =>
                                h('tr', { key: row.id }, [
                                    h('td', row.model),
                                    h('td', { style: { textTransform: 'uppercase', fontSize: '12px' } }, row.format),
                                    h('td', { style: { textAlign: 'center' } }, [
                                        h('span', { class: 'pf', 'data-color': exportStatusColor(row.status), style: statusStyle, title: row.error ?? undefined }, words.status[row.status] ?? row.status),
                                        row.error ? h('div', { class: 'pf-field-error', style: { fontSize: '11px' }, title: row.error }, row.error) : null,
                                    ]),
                                    h('td', { style: { textAlign: 'right' } }, row.rows ?? '—'),
                                    h('td', { style: { whiteSpace: 'nowrap', fontSize: '12px' } }, row.created_at_display ?? ''),
                                    h('td', { style: { textAlign: 'right', whiteSpace: 'nowrap' } }, [
                                        row.download_url ? h(ForgeButton, { size: 'sm', flat: true, href: row.download_url }, () => words.download) : null,
                                        ' ',
                                        h(ForgeButton, { size: 'sm', flat: true, color: 'danger', loading: removing.value === row.id, onClick: () => (confirming.value = row) }, () => words.delete),
                                    ]),
                                ]),
                            ),
                        ),
                    ]),
                ]);
            }

            return h('section', { class: 'pf' }, [
                h('header', { class: 'pf-page-header' }, [h('h2', { class: 'pf-page-title' }, words.exportsTitle)]),
                error.value ? h(ForgeAlert, { type: 'danger' }, () => error.value) : null,
                body,
                current && current.meta.last_page > 1 ? h(ForgePagination, { page: page.value, lastPage: current.meta.last_page, onChange: (target: number) => void load(target) }) : null,
                h(
                    ForgeModal,
                    { modelValue: confirming.value !== null, 'onUpdate:modelValue': (value: boolean) => !value && (confirming.value = null), title: words.delete, size: 'sm' },
                    {
                        default: () => h('p', words.removeConfirm),
                        footer: () => [
                            h(ForgeButton, { flat: true, color: 'dark', onClick: () => (confirming.value = null) }, () => words.cancel),
                            h(ForgeButton, { color: 'danger', onClick: () => void remove() }, () => words.delete),
                        ],
                    },
                ),
            ]);
        };
    },
});
