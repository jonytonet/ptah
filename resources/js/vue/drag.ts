/**
 * Vue side of dragging a dialog by its header (1.63.2, core/drag.ts). In a
 * template: `:class="drag.className" v-on="drag.on"`; in a render function:
 * `{ class: drag.className, ...drag.props }`. Off when
 * `PTAH_MODAL_DRAGGABLE=false`. `open` false puts the dialog back in the centre.
 */
import { watch } from 'vue';
import type { Ref } from 'vue';
import { dialogDrag, modalDragEnabled } from '../core/drag';

export interface VueDialogDrag {
    className: string | undefined;
    /** For `v-on` in a template. */
    on: Record<string, (event: PointerEvent) => void>;
    /** For `h()`. */
    props: Record<string, (event: PointerEvent) => void>;
}

export function useDialogDrag(panel: Ref<HTMLElement | null | undefined>, open: () => boolean = () => true): VueDialogDrag {
    const drag = dialogDrag(() => panel.value);
    watch(open, (isOpen) => {
        if (!isOpen) {
            drag.reset();
        }
    });

    if (!modalDragEnabled()) {
        return { className: undefined, on: {}, props: {} };
    }

    return {
        className: 'pc-drag-handle',
        on: { pointerdown: (e) => drag.down(e), pointermove: (e) => drag.move(e), pointerup: () => drag.up(), pointercancel: () => drag.up() },
        props: { onPointerdown: (e) => drag.down(e), onPointermove: (e) => drag.move(e), onPointerup: () => drag.up(), onPointercancel: () => drag.up() },
    };
}
