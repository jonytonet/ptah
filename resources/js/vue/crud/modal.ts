/**
 * The dialog shell of the BaseCrud modals (forge-modal) for Vue: the form,
 * history, attachments and import wizard draw inside it. Slots: default
 * (body) and `footer`. Mirrors react/crud/modal.tsx.
 */
import { Comment, defineComponent, h, onBeforeUnmount, onMounted, ref } from 'vue';
import type { PropType, VNode } from 'vue';
import { icon } from '../icons';
import { trapFocus } from '../../core/focus';
import { useDialogDrag } from '../drag';

let next = 0;

/** A slot with something to draw (a v-if that is false leaves only a comment). */
const filled = (nodes: VNode[] | undefined) => Boolean(nodes?.some((node) => node.type !== Comment));

/**
 * The dialog of the Livewire screens (forge-modal): a blurred backdrop, a
 * rounded panel with a header (title, subtitle, ✕), a scrolling body and a
 * footer of buttons aligned right. `close`: the ✕, Escape, a click outside.
 */
export const PtahCrudModal = defineComponent({
    name: 'PtahCrudModal',
    props: {
        title: { type: String, required: true },
        subtitle: { type: String, default: undefined },
        /** forge-modal's size: sm 24rem, md 28rem, lg 32rem, xl 36rem, 2xl 42rem. */
        size: { type: String as PropType<'sm' | 'md' | 'lg' | 'xl' | '2xl' | '4xl'>, default: 'md' },
        /** Name of the ✕ (`modal_close`). */
        closeLabel: { type: String, required: true },
        role: { type: String as PropType<'dialog' | 'alertdialog'>, default: 'dialog' },
        /** Escape closes it (default). */
        escape: { type: Boolean, default: true },
    },
    emits: ['close'],
    setup(props, { emit, slots }) {
        const titleId = `pc-fm-${++next}`;
        const onKey = (event: KeyboardEvent) => {
            if (props.escape && event.key === 'Escape') {
                emit('close');
            }
        };
        // Tab stays in the dialog; closing gives the focus back (1.61.0).
        const panel = ref<HTMLElement | null>(null);
        // Every dialog drags by its header (1.63.2, PTAH_MODAL_DRAGGABLE).
        const drag = useDialogDrag(panel);
        let release: (() => void) | null = null;
        onMounted(() => {
            document.addEventListener('keydown', onKey);
            if (panel.value) {
                release = trapFocus(panel.value);
            }
        });
        onBeforeUnmount(() => {
            document.removeEventListener('keydown', onKey);
            release?.();
        });

        return () => {
            const body = slots.default?.();
            const footer = slots.footer?.();

            return h(
                'div',
                {
                    class: 'pc-root pc-fm-back',
                    onMousedown: (event: MouseEvent) => {
                        if (event.target === event.currentTarget) {
                            emit('close');
                        }
                    },
                },
                [
                    h('div', { ref: panel, class: 'pc-fm-panel', 'data-size': props.size, role: props.role, 'aria-modal': 'true', 'aria-labelledby': titleId }, [
                        h('div', { class: ['pc-fm-head', drag.className], ...drag.props }, [
                            h('div', [h('h3', { class: 'pc-fm-title', id: titleId }, props.title), props.subtitle ? h('p', { class: 'pc-fm-sub' }, props.subtitle) : null]),
                            h('button', { type: 'button', class: 'pc-fm-close', 'aria-label': props.closeLabel, title: props.closeLabel, onClick: () => emit('close') }, [icon('x', 20)]),
                        ]),
                        filled(body) ? h('div', { class: 'pc-fm-body' }, body) : null,
                        filled(footer) ? h('div', { class: 'pc-fm-foot' }, footer) : null,
                    ]),
                ],
            );
        };
    },
});

/** forge-empty, inside a modal. */
export const PtahModalEmpty = defineComponent({
    name: 'PtahModalEmpty',
    props: { title: { type: String, required: true } },
    setup(props) {
        return () => h('div', { class: 'pc-fm-empty' }, [h('span', [icon('inbox', 32, 1.5)]), h('p', props.title)]);
    },
});

/** forge-alert, inside a modal. */
export const PtahModalAlert = defineComponent({
    name: 'PtahModalAlert',
    props: { color: { type: String as PropType<'danger' | 'success' | 'warn'>, default: 'danger' } },
    setup(props, { slots }) {
        return () => h('div', { class: 'pc-fm-alert', 'data-color': props.color, role: props.color === 'danger' ? 'alert' : 'status' }, slots.default?.());
    },
});
