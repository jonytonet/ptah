/**
 * The dashboard for Vue: the widgets of config/ptah-dashboard.php, computed
 * by the server — the same four kinds ptah::dashboard.widgets draws.
 */
import { computed, defineComponent, h, onMounted, ref, watch } from 'vue';
import type { PropType, VNode } from 'vue';
import { PtahApi, words } from '../core';
import type { Me, Widget } from '../core';

const TONE: Record<string, string> = { primary: 'var(--pc-primary)', success: 'var(--pc-success)', danger: 'var(--pc-danger)', warn: 'var(--pc-warn)' };
const tone = (color: string) => TONE[color] ?? TONE.primary;

function body(widget: Widget): (VNode | null)[] {
    const color = tone(widget.color);

    if (widget.type === 'trend') {
        const points = widget.points ?? [];
        const max = Math.max(1, Number(widget.max) || 1);
        const width = 100 / Math.max(1, points.length);

        return [
            h('div', { class: 'pa-row' }, [
                h('span', { class: 'pf-stat-value', style: { color } }, widget.display_total),
                points.length > 0 ? h('span', { class: 'pf-chart-subtitle' }, `${points[0].label} – ${points[points.length - 1].label}`) : null,
            ]),
            h(
                'svg',
                { viewBox: '0 0 100 40', preserveAspectRatio: 'none', role: 'img', style: { width: '100%', height: '64px', marginTop: '10px' }, 'aria-label': `${widget.label}: ${points.map((point) => `${point.label} ${point.display}`).join(', ')}` },
                points.map((point, index) => {
                    const height = point.value > 0 ? Math.max(1.5, (38 * point.value) / max) : 0.6;

                    return h('rect', { key: index, x: index * width + width * 0.15, y: 40 - height, width: width * 0.7, height, style: { fill: point.value > 0 ? color : 'var(--pc-line)' } }, [h('title', `${point.label}: ${point.display}`)]);
                }),
            ),
        ];
    }

    if (widget.type === 'breakdown') {
        const items = widget.items ?? [];
        const max = Math.max(1, Number(widget.max) || 1);

        return [
            h('div', { class: 'pf-stat-value', style: { color } }, widget.display_total),
            h('ul', { style: { listStyle: 'none', margin: '10px 0 0', padding: 0, display: 'flex', flexDirection: 'column', gap: '8px' } }, [
                items.length === 0 ? h('li', { class: 'pf-chart-subtitle' }, '—') : null,
                ...items.map((item, index) =>
                    h('li', { key: index }, [
                        h('div', { class: 'pa-row' }, [h('span', item.label), h('span', { class: 'pf-chart-subtitle' }, item.display)]),
                        h('div', { class: 'pf-progress-track', 'aria-hidden': 'true', style: { height: '6px', marginTop: '4px' } }, [
                            h('div', { class: 'pf-progress-bar', style: { width: `${item.value > 0 ? Math.max(1, Math.round((1000 * item.value) / max) / 10) : 0}%`, background: item.others ? 'var(--pc-line-field)' : color } }),
                        ]),
                    ]),
                ),
            ]),
        ];
    }

    if (widget.type === 'latest') {
        const rows = widget.rows ?? [];

        return [
            h('table', { class: 'pf-table', style: { marginTop: '6px' } }, [
                h('tbody', [
                    rows.length === 0 ? h('tr', [h('td', { class: 'pf-chart-subtitle' }, '—')]) : null,
                    ...rows.map((row, index) =>
                        h(
                            'tr',
                            { key: index },
                            row.map((cell, cellIndex) => h('td', { key: cellIndex, style: cellIndex === 0 ? { fontWeight: 500 } : { color: 'var(--pc-text-muted)' } }, cell === null ? '' : String(cell))),
                        ),
                    ),
                ]),
            ]),
        ];
    }

    return [h('div', { class: 'pf-stat-value', style: { color } }, widget.value === undefined ? '' : String(widget.value))];
}

function card(widget: Widget, index: number): VNode {
    const wide = ['trend', 'latest', 'breakdown'].includes(widget.type);
    const content = h('div', { class: 'pf-card-body' }, [h('div', { class: 'pf-stat-title' }, widget.label), ...(widget.error ? [h('p', { class: 'pf-field-error', role: 'alert' }, widget.error)] : body(widget))]);

    return widget.link
        ? h('a', { key: index, class: ['pf pf-card pf-card--hoverable', wide && 'pd-wide'], href: widget.link, style: { textDecoration: 'none', color: 'inherit' } }, [content])
        : h('div', { key: index, class: ['pf pf-card', wide && 'pd-wide'] }, [content]);
}

/** The widgets of a group (default: the dashboard's), anywhere in a page. */
export const PtahWidgets = defineComponent({
    name: 'PtahWidgets',
    props: { api: { type: Object as PropType<PtahApi>, default: undefined }, group: { type: String, default: undefined } },
    emits: ['load'],
    setup(props, { emit }) {
        const client = props.api ?? new PtahApi();
        const widgets = ref<Widget[] | null>(null);
        const load = () =>
            void client
                .dashboard(props.group)
                .then((list) => {
                    widgets.value = list;
                    emit('load', list);
                })
                .catch(() => {
                    widgets.value = [];
                });

        onMounted(load);
        watch(() => props.group, load);

        return () => (widgets.value ? h('div', { class: 'pf pd-grid' }, widgets.value.map(card)) : null);
    },
});

/** The dashboard page: a welcome and the widgets. */
export const PtahDashboard = defineComponent({
    name: 'PtahDashboard',
    props: { api: { type: Object as PropType<PtahApi>, default: undefined }, locale: { type: String, default: undefined }, group: { type: String, default: undefined } },
    setup(props) {
        const client = props.api ?? new PtahApi();
        const me = ref<Me | null>(null);
        const count = ref<number | null>(null);
        const t = computed(() => words(props.locale ?? me.value?.locale));

        onMounted(() => {
            void client
                .me()
                .then((value) => {
                    me.value = value;
                })
                .catch(() => undefined);
        });

        return () =>
            h('div', { class: 'pf' }, [
                h('h1', { class: 'pd-welcome' }, `${t.value.welcome}${me.value?.user?.name ? `, ${me.value.user.name}` : ''}`),
                h(PtahWidgets, { api: client, group: props.group, onLoad: (list: Widget[]) => (count.value = list.length) }),
                count.value === 0 ? h('p', { class: 'pf-chart-subtitle' }, t.value.empty) : null,
            ]);
    },
});
