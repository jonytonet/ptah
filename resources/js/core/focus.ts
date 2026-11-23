/**
 * Keeps the keyboard inside an open dialog and gives the focus back when it
 * closes (1.61.0) — what `x-trap` does on the Livewire screen. Without it the
 * Tab key walked out of the React/Vue modal into the blurred page behind it,
 * and closing the modal left the focus on <body>.
 *
 * Dialogs stack (the delete confirmation over the form): only the newest one
 * holds the Tab key.
 */

const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const stack: HTMLElement[] = [];

function focusables(panel: HTMLElement): HTMLElement[] {
    return Array.from(panel.querySelectorAll<HTMLElement>(FOCUSABLE)).filter((el) => el.offsetParent !== null || el === document.activeElement);
}

function onKey(event: KeyboardEvent): void {
    const panel = stack[stack.length - 1];
    if (!panel || event.key !== 'Tab') {
        return;
    }

    const items = focusables(panel);
    if (items.length === 0) {
        event.preventDefault();

        return;
    }

    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement as HTMLElement | null;

    if (!active || !panel.contains(active)) {
        event.preventDefault();
        (event.shiftKey ? last : first).focus();
    } else if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
    }
}

/**
 * Traps the Tab key in `panel` until the returned function is called; that
 * call also puts the focus back where it was when the dialog opened.
 */
export function trapFocus(panel: HTMLElement): () => void {
    const before = document.activeElement as HTMLElement | null;
    if (stack.length === 0) {
        document.addEventListener('keydown', onKey);
    }
    stack.push(panel);

    // Something inside already asked for the focus (autoFocus): keep it.
    // Otherwise the panel itself, not its first button — that is the ✕ of
    // the header, and the form moves the focus to its first field anyway.
    if (!panel.contains(document.activeElement)) {
        if (!panel.hasAttribute('tabindex')) {
            panel.setAttribute('tabindex', '-1');
        }
        panel.focus({ preventScroll: true });
    }

    return () => {
        const at = stack.indexOf(panel);
        if (at >= 0) {
            stack.splice(at, 1);
        }
        if (stack.length === 0) {
            document.removeEventListener('keydown', onKey);
        }
        if (before && document.contains(before) && typeof before.focus === 'function') {
            before.focus();
        }
    };
}
