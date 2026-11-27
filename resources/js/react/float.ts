/**
 * React side of the floating list (1.63.1, core/float.ts): while `open`, the
 * element the returned ref is put on stays fixed under `anchor` — a dialog
 * body that scrolls no longer hides it.
 */
import { useLayoutEffect, useRef } from 'react';
import type { RefObject } from 'react';
import { floatPanel } from '../core/float';

export function useFloating<T extends HTMLElement>(anchor: RefObject<HTMLElement | null>, open: boolean, up = false): RefObject<T | null> {
    const panel = useRef<T | null>(null);

    useLayoutEffect(() => {
        if (!open || !anchor.current || !panel.current) {
            return undefined;
        }

        return floatPanel(anchor.current, panel.current, up);
    }, [open, up, anchor]);

    return panel;
}
