/**
 * A floating list (1.63.1) — the open list of a select or searchdropdown is
 * placed with position:fixed under (or over) its field, so the body of a
 * dialog that scrolls no longer hides it. Twin of
 * resources/views/partials/float.blade.php (Livewire).
 *
 * The list is measured against its containing block, so it lands right
 * inside a transformed ancestor too (a dialog in its opening animation, a
 * backdrop with a blur). It flips up when there is no room below, and
 * follows scroll, resize and its own size while open.
 */

const GAP = 4;

/** Puts `panel` under `anchor` (over it with `up`, or when only there it fits). */
export function placeFloating(anchor: HTMLElement, panel: HTMLElement, up = false): void {
    const s = panel.style;
    s.position = 'fixed';
    s.top = '0px';
    s.left = '0px';
    s.right = 'auto';
    s.bottom = 'auto';
    s.margin = '0';
    const origin = panel.getBoundingClientRect();
    const r = anchor.getBoundingClientRect();
    const h = panel.offsetHeight;
    const below = window.innerHeight - r.bottom - GAP;
    const above = r.top - GAP;
    const flip = up ? above >= h || above > below : h > below && above > below;
    s.top = `${(flip ? r.top - GAP - h : r.bottom + GAP) - origin.top}px`;
    s.left = `${r.left - origin.left}px`;
    s.width = `${r.width}px`;
}

/** Places the list now and keeps it in place until the returned function is called. */
export function floatPanel(anchor: HTMLElement, panel: HTMLElement, up = false): () => void {
    const go = () => placeFloating(anchor, panel, up);
    go();
    window.addEventListener('scroll', go, true);
    window.addEventListener('resize', go);
    const resized = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(go);
    resized?.observe(panel);

    return () => {
        resized?.disconnect();
        window.removeEventListener('scroll', go, true);
        window.removeEventListener('resize', go);
    };
}
