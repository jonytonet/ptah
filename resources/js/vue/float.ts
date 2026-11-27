/**
 * Vue side of the floating list (1.63.1, core/float.ts): while `open()` is
 * true, `panel` stays fixed under `anchor` — a dialog body that scrolls no
 * longer hides it. Call inside setup().
 */
import { nextTick, onBeforeUnmount, watch } from 'vue';
import type { Ref } from 'vue';
import { floatPanel } from '../core/float';

export function useFloating(anchor: Ref<HTMLElement | null | undefined>, panel: Ref<HTMLElement | null | undefined>, open: () => boolean, up: () => boolean = () => false): void {
    let off: (() => void) | null = null;
    const stop = () => {
        off?.();
        off = null;
    };

    watch(
        [open, up],
        async ([isOpen, isUp]) => {
            stop();
            if (!isOpen) {
                return;
            }
            await nextTick();
            if (anchor.value && panel.value) {
                off = floatPanel(anchor.value, panel.value, isUp);
            }
        },
        { flush: 'post', immediate: true },
    );
    onBeforeUnmount(stop);
}
