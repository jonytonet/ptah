/**
 * React side of dragging a dialog by its header (1.63.2, core/drag.ts):
 * spread `handle` on the header. Off when `PTAH_MODAL_DRAGGABLE=false`.
 * `open` false puts the dialog back in the centre for its next opening.
 */
import { useEffect, useMemo } from 'react';
import type { PointerEvent as ReactPointerEvent, RefObject } from 'react';
import { dialogDrag, modalDragEnabled } from '../core/drag';

export interface DialogDragHandle {
    className?: string;
    onPointerDown?: (event: ReactPointerEvent) => void;
    onPointerMove?: (event: ReactPointerEvent) => void;
    onPointerUp?: () => void;
    onPointerCancel?: () => void;
}

export function useDialogDrag(panel: RefObject<HTMLElement | null>, open = true): DialogDragHandle {
    const drag = useMemo(() => dialogDrag(() => panel.current), [panel]);

    useEffect(() => {
        if (!open) {
            drag.reset();
        }
    }, [open, drag]);

    if (!modalDragEnabled()) {
        return {};
    }

    return {
        className: 'pc-drag-handle',
        onPointerDown: (event) => drag.down(event.nativeEvent),
        onPointerMove: (event) => drag.move(event.nativeEvent),
        onPointerUp: () => drag.up(),
        onPointerCancel: () => drag.up(),
    };
}
