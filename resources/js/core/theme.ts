/**
 * ptah's appearance in the React and Vue stacks — the SAME mechanism as the
 * Livewire layout, so a user's choices follow them across stacks:
 *
 *  - six presets on `<html>` (`data-ptah-light|dark|accent|text|density|fontsize`),
 *    which `ptah-components.css` turns into the `--ptah-*` tokens;
 *  - `.ptah-dark` (and `.dark`) on `<html>` for the dark mode, remembered in
 *    `localStorage.ptah_dark_mode` and, for a signed-in user, on the server.
 *
 * The first paint is the server's (ptah::stack.app sets the attributes and
 * runs the anti-flash boot script); this module only changes it afterwards.
 */
import type { Appearance } from './types';

const AXES = ['light', 'dark', 'accent', 'text', 'density', 'fontsize'] as const;
const STORAGE_KEY = 'ptah_dark_mode';
const listeners = new Set<(dark: boolean) => void>();

const root = (): HTMLElement | null => (typeof document === 'undefined' ? null : document.documentElement);

/** Puts the presets on `<html>` — call it with the profile's appearance after a change. */
export function applyAppearance(appearance: Partial<Pick<Appearance, (typeof AXES)[number]>>): void {
    const html = root();
    if (!html) {
        return;
    }

    for (const axis of AXES) {
        const value = appearance[axis];
        if (typeof value === 'string' && value !== '') {
            html.setAttribute(`data-ptah-${axis}`, value);
        }
    }
}

export function isDark(): boolean {
    return root()?.classList.contains('ptah-dark') ?? false;
}

/** Sets the mode on the page and remembers it in this browser. */
export function setDark(dark: boolean): void {
    const html = root();
    if (!html) {
        return;
    }

    html.classList.toggle('ptah-dark', dark);
    html.classList.toggle('dark', dark);
    html.style.colorScheme = dark ? 'dark' : 'light';

    try {
        localStorage.setItem(STORAGE_KEY, String(dark));
    } catch {
        // Private mode: the choice lasts for this page only.
    }

    listeners.forEach((listener) => listener(dark));
}

/**
 * Flips light/dark. With `endpoint` (the `ptah.appearance.theme-mode` route,
 * present for a signed-in user) the choice is also saved on the server —
 * fire-and-forget, the page does not wait for it.
 */
export function toggleDark(endpoint?: string | null): boolean {
    const dark = !isDark();
    setDark(dark);

    if (endpoint && typeof fetch !== 'undefined') {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        void fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ mode: dark ? 'dark' : 'light' }),
            credentials: 'same-origin',
            keepalive: true,
        }).catch(() => undefined);
    }

    return dark;
}

/** Tells you when the mode changes (the toggle may live far from what reacts to it). */
export function onDarkChange(listener: (dark: boolean) => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}
