import { defineComponent, h } from 'vue';
import { iconPaths, iconStroke } from '../core/icons';

/** The <Icon> vnode, for render functions: `icon('edit')`. */
export function icon(name: string, size = 16, strokeWidth?: number, className?: string) {
    return h(
        'svg',
        {
            class: className ? `pc-ico ${className}` : 'pc-ico',
            width: size,
            height: size,
            viewBox: '0 0 24 24',
            fill: 'none',
            stroke: 'currentColor',
            'stroke-width': strokeWidth ?? iconStroke[name] ?? 2,
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round',
            'aria-hidden': 'true',
            focusable: 'false',
        },
        iconPaths(name).map((d) => h('path', { key: d, d })),
    );
}

/** One of ptah's icons: the very SVG of the Livewire screens. Decorative (aria-hidden). */
export const Icon = defineComponent({
    name: 'PtahIcon',
    props: {
        name: { type: String, required: true },
        size: { type: Number, default: 16 },
        strokeWidth: { type: Number, default: undefined },
        className: { type: String, default: undefined },
    },
    setup(props) {
        return () => icon(props.name, props.size, props.strokeWidth, props.className);
    },
});
