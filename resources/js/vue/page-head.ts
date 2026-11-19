import { defineComponent, h } from 'vue';
import type { PropType } from 'vue';

/**
 * The header above a screen: the h1 of forge-page-header (and of the index
 * view ptah:forge writes) with its subtitle, or the breadcrumb "Dashboard / Ticket".
 */
export const PtahPageHead = defineComponent({
    name: 'PtahPageHead',
    props: {
        title: { type: String, required: true },
        /** The line under the title (forge-page-header's `subtitle`). */
        subtitle: { type: String, default: undefined },
        /** The trail under the title (forge-breadcrumb): the last one is the current page. */
        crumbs: { type: Array as PropType<{ label: string; url?: string }[]>, default: () => [] },
    },
    setup(props) {
        return () =>
            h('div', { class: 'pl-page-head' }, [
                h('h1', { class: 'pl-page-title' }, props.title),
                props.subtitle ? h('p', { class: 'pl-page-subtitle' }, props.subtitle) : null,
                props.crumbs.length > 0
                    ? h('nav', { 'aria-label': 'Breadcrumb' }, [
                          h(
                              'ol',
                              { class: 'pl-crumbs' },
                              props.crumbs.map((crumb, index) =>
                                  h('li', { key: index }, [
                                      index > 0 ? h('span', { class: 'pl-crumb-sep', 'aria-hidden': 'true' }, '/') : null,
                                      index < props.crumbs.length - 1
                                          ? h('a', { class: 'pl-crumb', href: crumb.url ?? '#' }, crumb.label)
                                          : h('span', { class: 'pl-crumb-current', 'aria-current': 'page' }, crumb.label),
                                  ]),
                              ),
                          ),
                      ])
                    : null,
            ]);
    },
});
