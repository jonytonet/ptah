/**
 * Dragging a dialog by its header (1.63.2) — forge-modal's `draggable`
 * (_dragStart / _dragMove / _dragEnd in forge-modal.blade.php). The panel
 * moves with `translate`, so the opening animation's `transform` and the
 * move compose without a fight. The pointer is held to the window, so the
 * header never leaves it. A press on a button, link or field of the header
 * is not a drag. Framework-free: the React and Vue dialogs bind these.
 */
export interface DialogDrag {
    down(event: PointerEvent): void;
    move(event: PointerEvent): void;
    up(): void;
    /** Back to the centre (the dialog closed). */
    reset(): void;
}

export function dialogDrag(panel: () => HTMLElement | null | undefined): DialogDrag {
    let dx = 0;
    let dy = 0;
    let from: { x: number; y: number } | null = null;

    const apply = () => {
        const el = panel();
        if (el) {
            el.style.translate = dx === 0 && dy === 0 ? '' : `${dx}px ${dy}px`;
        }
    };

    return {
        down(event) {
            const target = event.target as Element | null;
            if (event.button !== 0 || target?.closest('button, a, input, select, textarea')) {
                return;
            }
            from = { x: event.clientX - dx, y: event.clientY - dy };
            (event.currentTarget as Element | null)?.setPointerCapture?.(event.pointerId);
        },
        move(event) {
            if (!from) {
                return;
            }
            const x = Math.min(Math.max(event.clientX, 0), window.innerWidth);
            const y = Math.min(Math.max(event.clientY, 0), window.innerHeight - 24);
            dx = x - from.x;
            dy = y - from.y;
            apply();
        },
        up() {
            from = null;
        },
        reset() {
            from = null;
            dx = 0;
            dy = 0;
            apply();
        },
    };
}

/**
 * Whether dialogs drag (1.63.2, `PTAH_MODAL_DRAGGABLE`, default on): the
 * layout writes it on `<html data-ptah-modal-drag>`; a page without it drags.
 */
export function modalDragEnabled(): boolean {
    return typeof document === 'undefined' || document.documentElement.getAttribute('data-ptah-modal-drag') !== '0';
}
