/**
 * The dashboard for Vue: the widgets of config/ptah-dashboard.php, computed
 * by the server — the same four kinds ptah::dashboard.widgets draws — or,
 * with none configured, the four cards of livewire/auth/dashboard.blade.php.
 */
import { computed, defineComponent, h, onMounted, ref, watch } from 'vue';
import type { PropType, VNode } from 'vue';
import { PtahApi, takeWarmCall, words } from '../core';
import type { DashboardPage, Me, Widget } from '../core';
import { icon } from './icons';
import { PtahPageHead } from './page-head';

/** ptah::dashboard.widgets' tones: the strong variants of the semantic colours. */
const TONE: Record<string, string> = {
    primary: 'var(--ptah-primary, var(--pc-primary))',
    success: 'var(--ptah-success-strong, var(--pc-success))',
    danger: 'var(--ptah-danger-strong, var(--pc-danger))',
    warn: 'var(--ptah-warn-strong, var(--pc-warn))',
};
const tone = (color: string) => TONE[color] ?? TONE.primary;

function body(widget: Widget): (VNode | null)[] {
    const color = tone(widget.color);

    if (widget.type === 'trend') {
        const points = widget.points ?? [];
        const max = Math.max(1, Number(widget.max) || 1);
        const width = 100 / Math.max(1, points.length);

        return [
            h('div', { class: 'pd-trend-head' }, [
                h('span', { class: 'pd-value', style: { color } }, widget.display_total),
                points.length > 0 ? h('span', { class: 'pd-muted' }, `${points[0].label} – ${points[points.length - 1].label}`) : null,
            ]),
            h(
                'svg',
                { class: 'pd-trend', viewBox: '0 0 100 40', preserveAspectRatio: 'none', role: 'img', 'aria-label': `${widget.label}: ${points.map((point) => `${point.label} ${point.display}`).join(', ')}` },
                points.map((point, index) => {
                    const height = point.value > 0 ? Math.max(1.5, (38 * point.value) / max) : 0.6;

                    return h('rect', { key: index, x: index * width + width * 0.15, y: 40 - height, width: width * 0.7, height, style: { fill: point.value > 0 ? color : 'var(--ptah-line, var(--pc-line))' } }, [h('title', `${point.label}: ${point.display}`)]);
                }),
            ),
        ];
    }

    if (widget.type === 'breakdown') {
        const items = widget.items ?? [];
        const max = Math.max(1, Number(widget.max) || 1);

        return [
            h('p', { class: 'pd-value', style: { color } }, widget.display_total),
            h('ul', { class: 'pd-bars' }, [
                items.length === 0 ? h('li', { class: 'pd-muted pd-small' }, '—') : null,
                ...items.map((item, index) =>
                    h('li', { key: index }, [
                        h('div', { class: 'pd-bar-head' }, [h('span', { class: item.others ? 'pd-bar-label pd-muted' : 'pd-bar-label' }, item.label), h('span', { class: 'pd-muted pd-num' }, item.display)]),
                        h('div', { class: 'pd-bar-track', 'aria-hidden': 'true' }, [
                            h('div', { class: 'pd-bar', style: { width: `${item.value > 0 ? Math.max(1, Math.round((1000 * item.value) / max) / 10) : 0}%`, background: item.others ? 'var(--ptah-line-strong, var(--pc-line-field))' : color } }),
                        ]),
                    ]),
                ),
            ]),
        ];
    }

    if (widget.type === 'latest') {
        const rows = widget.rows ?? [];

        return [
            h('table', { class: 'pd-table' }, [
                h('tbody', [
                    rows.length === 0 ? h('tr', [h('td', { class: 'pd-muted pd-small' }, '—')]) : null,
                    ...rows.map((row, index) =>
                        h(
                            'tr',
                            { key: index },
                            row.map((cell, cellIndex) => h('td', { key: cellIndex, class: cellIndex === 0 ? 'pd-cell-main' : 'pd-muted' }, cell === null ? '' : String(cell))),
                        ),
                    ),
                ]),
            ]),
        ];
    }

    return [h('p', { class: 'pd-value', style: { color } }, widget.value === undefined ? '' : String(widget.value))];
}

function card(widget: Widget, index: number): VNode {
    const wide = ['trend', 'latest', 'breakdown'].includes(widget.type);
    const className = ['pd-card', wide && 'pd-wide', widget.link && 'pd-card--link'];
    const content = [h('p', { class: 'pd-label' }, widget.label), ...(widget.error ? [h('p', { class: 'pd-error', role: 'alert' }, widget.error)] : body(widget))];

    return widget.link ? h('a', { key: index, class: className, href: widget.link }, content) : h('div', { key: index, class: className }, content);
}

/** A forge-stat-card: title, big value, the tinted icon square on the right. */
const statCard = (title: string, value: string, name: string, color: 'primary' | 'success') =>
    h('div', { class: 'pd-stat', 'data-color': color }, [
        h('div', { class: 'pd-stat-text' }, [h('p', { class: 'pd-stat-title' }, title), h('p', { class: 'pd-stat-value' }, value)]),
        h('div', { class: 'pd-stat-icon' }, [icon(name, 24)]),
    ]);

/** The widgets of a group (default: the dashboard's), anywhere in a page. */
export const PtahWidgets = defineComponent({
    name: 'PtahWidgets',
    props: { api: { type: Object as PropType<PtahApi>, default: undefined }, group: { type: String, default: undefined } },
    emits: ['load'],
    setup(props, { emit }) {
        const client = props.api ?? new PtahApi();
        // Asked already on the way to the dashboard (the navigator): drawn in the first frame.
        let warm = props.group === undefined ? takeWarmCall<DashboardPage>('dashboard') : null;
        const widgets = ref<Widget[] | null>(warm?.done && warm.value ? warm.value.data : null);
        const load = () => {
            const warmed = warm;
            warm = null;
            void (warmed ? warmed.promise : client.dashboardPage(props.group))
                .then((page) => {
                    widgets.value = page.data;
                    emit('load', page.data, page);
                })
                .catch(() => {
                    widgets.value = [];
                });
        };

        onMounted(load);
        watch(() => props.group, load);

        return () => {
            // While they are on the way: four placeholder cards (the shape of the default ones), not a blank.
            if (!widgets.value) {
                return h(
                    'div',
                    { class: 'pf pd-stats pc-skeleton', role: 'status', 'aria-busy': 'true' },
                    [0, 1, 2, 3].map((index) =>
                        h('div', { key: index, class: 'pd-card pc-skeleton-card', 'aria-hidden': 'true' }, [
                            h('span', { class: 'pc-skeleton-bar', style: { width: '40%' } }),
                            h('span', { class: 'pc-skeleton-bar pc-skeleton-bar--tall', style: { width: '65%' } }),
                        ]),
                    ),
                );
            }

            return widgets.value.length > 0 ? h('div', { class: 'pf pd-grid' }, widgets.value.map(card)) : null;
        };
    },
});

/** The dashboard page: the header of livewire/auth/dashboard, and the widgets (or its four default cards). */
export const PtahDashboard = defineComponent({
    name: 'PtahDashboard',
    props: { api: { type: Object as PropType<PtahApi>, default: undefined }, locale: { type: String, default: undefined }, group: { type: String, default: undefined } },
    setup(props) {
        const client = props.api ?? new PtahApi();
        const me = ref<Me | null>(null);
        const page = ref<DashboardPage | null>(null);
        const t = computed(() => words(props.locale ?? me.value?.locale));

        onMounted(() => {
            void client
                .me()
                .then((value) => {
                    me.value = value;
                })
                .catch(() => undefined);
        });

        return () => {
            const fallback = page.value?.fallback ?? null;

            return h('div', { class: 'pf' }, [
                h(PtahPageHead, { title: t.value.dashboard, subtitle: t.value.dashboardSubtitle }),
                h(PtahWidgets, { api: client, group: props.group, onLoad: (_list: Widget[], answer: DashboardPage) => (page.value = answer) }),
                fallback
                    ? h('div', { class: 'pd-stats' }, [
                          statCard(t.value.welcome, me.value?.user?.name ?? '', 'user', 'primary'),
                          statCard(t.value.dashboardSystem, fallback.app, 'server', 'primary'),
                          statCard(t.value.dashboardEnvironment, fallback.environment, 'checkCircle', 'success'),
                          statCard(t.value.dashboardLaravel, fallback.laravel, 'bolt', 'primary'),
                      ])
                    : null,
                page.value && page.value.data.length === 0 && !fallback ? h('p', { class: 'pd-muted pd-small' }, t.value.empty) : null,
            ]);
        };
    },
});
