/**
 * The layout's notification bell and exports panel for Vue, over the shell
 * API. Who sees which notification or export, the safe URLs and the
 * ownership checks are the server's (NotificationBell, ExportsPanel); these
 * components only ask and show.
 */
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { CSSProperties, PropType } from 'vue';
import { PtahApi } from '../../core';
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
import { ForgeAlert, ForgeButton, ForgeEmpty, ForgeModal, ForgePagination, ForgeSpinner } from '../forge';

const shellProps = {
    api: { type: Object as PropType<PtahApi>, default: undefined },
    locale: { type: String, default: undefined },
    /** Milliseconds between refreshes; null turns polling off. Defaults to what the server reports. */
    pollInterval: { type: [Number, null] as PropType<number | null>, default: undefined },
};

const panelStyle: CSSProperties = { width: 'min(20rem, 90vw)', maxHeight: 'none', overflow: 'visible' };
const scrollStyle: CSSProperties = { maxHeight: 'min(24rem, 60vh)', overflowY: 'auto', overscrollBehavior: 'contain' };
const itemStyle: CSSProperties = { flexDirection: 'column', alignItems: 'flex-start', gap: '2px' };
const footStyle: CSSProperties = { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '8px', marginTop: '4px', paddingTop: '6px', borderTop: '1px solid var(--pc-line)' };
const statusStyle: CSSProperties = { display: 'inline-block', padding: '2px 8px', borderRadius: '999px', background: 'var(--pf-c)', color: 'var(--pf-on)', fontSize: '12px', fontWeight: 500 };
const muted: CSSProperties = { fontSize: '12px', opacity: 0.75 };

const bellIcon = () =>
    h('svg', { width: 20, height: 20, fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor', 'aria-hidden': 'true' }, [
        h('path', {
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round',
            'stroke-width': '2',
            d: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
        }),
    ]);

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
        /** Go to a URL without a full page load. Defaults to `window.location.assign`. */
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
                (props.onNavigate ?? ((url: string) => window.location.assign(url)))(answer.redirect);
            }
        };

        const markAll = async () => {
            const answer = await act(() => markAllNotificationsRead(client));
            notice.value = answer?.messages?.[0] ?? null;
        };

        const itemText = (item: ShellNotification) => [
            h('strong', item.title),
            item.body ? h('span', { style: muted }, item.body) : null,
            item.created_at_human ? h('span', { style: muted }, item.created_at_human) : null,
        ];

        return () => {
            const current = state.value;
            if (!current || !current.enabled) {
                return null;
            }
            const words = t.value;
            const items = current.items;
            const label = current.unread > 0 ? `${words.bellTitle} (${words.unreadBadge(current.unread)})` : words.bellTitle;

            return h('div', { class: 'pf pc-menu', ref: root }, [
                h('button', { type: 'button', class: 'pc-icon-btn', 'aria-haspopup': 'menu', 'aria-expanded': open.value ? 'true' : 'false', 'aria-label': label, title: words.bellTitle, onClick: toggle }, [
                    h('span', { class: 'pf-badge', 'data-position': 'top-right' }, [
                        bellIcon(),
                        current.unread > 0 ? h('span', { class: 'pf pf-badge-mark', 'data-color': 'danger', 'aria-hidden': 'true' }, badgeText(current.unread)) : null,
                    ]),
                ]),

                open.value
                    ? h('div', { class: 'pc-menu-list', role: 'menu', 'aria-label': words.bellTitle, 'aria-busy': busy.value || items === null ? 'true' : 'false', style: panelStyle }, [
                          // Only the items scroll: the footer stays reachable however long the list is.
                          h(
                              'div',
                              { role: 'none', style: scrollStyle },
                              items === null
                                  ? [h('div', { role: 'none', style: { padding: '12px', textAlign: 'center' } }, [h(ForgeSpinner, { size: 'sm', label: words.loading })])]
                                  : items.length === 0
                                    ? [h('p', { role: 'none', class: 'pf-empty', style: { padding: '16px', margin: 0 } }, words.empty)]
                                    : items.map((item) =>
                                          h('div', { role: 'none', key: item.id, style: { borderBottom: '1px solid var(--pc-line)', paddingBottom: '4px', marginBottom: '4px' } }, [
                                              item.url
                                                  ? h('a', { role: 'menuitem', class: 'pc-menu-item', href: item.url, style: itemStyle, onClick: (event: Event) => void openItem(event, item) }, [
                                                        ...itemText(item),
                                                        h('span', { style: { fontSize: '12px', fontWeight: 600 } }, item.action_label || words.actionDefault),
                                                    ])
                                                  : h('button', { type: 'button', role: 'menuitem', class: 'pc-menu-item', disabled: true, 'aria-disabled': 'true', style: { ...itemStyle, cursor: 'default' } }, itemText(item)),
                                              h(
                                                  'button',
                                                  {
                                                      type: 'button',
                                                      role: 'menuitem',
                                                      class: 'pc-menu-item',
                                                      style: { fontSize: '12px' },
                                                      'aria-label': `${words.dismiss}: ${item.title}`,
                                                      disabled: busy.value,
                                                      onClick: () => void act(() => dismissNotification(client, item.id)),
                                                  },
                                                  `✕ ${words.dismiss}`,
                                              ),
                                          ]),
                                      ),
                          ),

                          error.value ? h('p', { role: 'alert', class: 'pf-field-error', style: { margin: '6px 9px' } }, error.value) : null,
                          notice.value ? h('p', { role: 'status', style: { ...muted, margin: '6px 9px' } }, notice.value.text) : null,

                          h('div', { role: 'none', style: footStyle }, [
                              h(
                                  'button',
                                  { type: 'button', role: 'menuitem', class: 'pc-menu-item', style: { width: 'auto', fontWeight: 600, fontSize: '12px' }, disabled: busy.value, onClick: () => void markAll() },
                                  words.markAllRead,
                              ),
                              current.view_all_url
                                  ? h('a', { role: 'menuitem', class: 'pc-menu-item', style: { width: 'auto', fontWeight: 600, fontSize: '12px' }, href: current.view_all_url }, words.viewAll)
                                  : null,
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
