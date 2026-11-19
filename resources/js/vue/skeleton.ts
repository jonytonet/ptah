import { defineComponent, h } from 'vue';

/** The shape of a CRUD screen while its first data is on the way: a toolbar and placeholder rows. */
export const PtahCrudSkeleton = defineComponent({
    name: 'PtahCrudSkeleton',
    props: {
        label: { type: String, required: true },
        rows: { type: Number, default: 8 },
    },
    setup(props) {
        return () =>
            h('div', { class: 'pc-root pc-skeleton', role: 'status', 'aria-busy': 'true', 'aria-label': props.label }, [
                h('span', { class: 'pc-visually-hidden' }, props.label),
                h('div', { class: 'pc-skeleton-toolbar', 'aria-hidden': 'true' }, [
                    h('span', { class: 'pc-skeleton-bar pc-skeleton-bar--search' }),
                    h('span', { class: 'pc-skeleton-spacer' }),
                    h('span', { class: 'pc-skeleton-bar pc-skeleton-bar--button' }),
                    h('span', { class: 'pc-skeleton-bar pc-skeleton-bar--button' }),
                ]),
                h(
                    'div',
                    { class: 'pc-skeleton-table', 'aria-hidden': 'true' },
                    Array.from({ length: props.rows + 1 }, (_, row) =>
                        h(
                            'div',
                            { key: row, class: row === 0 ? 'pc-skeleton-row pc-skeleton-row--head' : 'pc-skeleton-row' },
                            [18, 34, 22, 14].map((width, cell) => h('span', { key: cell, class: 'pc-skeleton-bar', style: { width: `${width}%` } })),
                        ),
                    ),
                ),
            ]);
    },
});
