/**
 * What the sidebar and the top bar do, without a framework — the behaviours
 * of forge-sidebar / forge-navbar, so the React and the Vue layouts only draw.
 */
import type { MenuItem, MenuLink } from './types';

export const COLLAPSED_KEY = 'ptah_sidebar_collapsed';

/** Lower case, no accents: "cotacao" finds "Cotação" (the fold of MenuResolver). */
export function foldText(value: string | null | undefined): string {
    return (value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '');
}

/**
 * Laravel's `request()->is($pattern)`: `*` matches anything, the rest is literal.
 * `path` is a URL path with or without the leading slash.
 */
export function pathIs(pattern: string, path: string): boolean {
    const clean = (value: string) => value.replace(/^\/+|\/+$/g, '');
    const target = clean(path);
    const source = clean(pattern);

    if (source === '') {
        return target === '';
    }

    const regex = new RegExp(
        '^' +
            source
                .split('*')
                .map((part) => part.replace(/[.+?^${}()|[\]\\]/g, '\\$&'))
                .join('.*') +
            '$',
    );

    return regex.test(target);
}

function pathOf(url: string | undefined): string {
    if (!url || url === '#') {
        return '';
    }
    try {
        return new URL(url, 'http://localhost').pathname;
    } catch {
        return url;
    }
}

/** Is this the page the user is on? The item's `match`, or its own URL, or anything under it. */
export function isActive(item: MenuItem, path: string): boolean {
    const match = (item.match ?? pathOf(item.url)).replace(/^\/+|\/+$/g, '');

    return match !== '' && (pathIs(match, path) || pathIs(`${match}/*`, path));
}

/** A group is active when the page is one of its links, at any depth. */
export function hasActive(item: MenuItem, path: string): boolean {
    return isActive(item, path) || (item.children ?? []).some((child) => hasActive(child, path));
}

/** An item the menu admin switched off is not drawn. */
export function visible(items: MenuItem[]): MenuItem[] {
    return items.filter((item) => item.is_active !== false && item.is_active !== 0);
}

/**
 * The "jump to a screen" search: word by word, in any order, over the label
 * and the trail — "cotacao compras" finds "Compras > Cotação". At most 12.
 */
export function searchMenu(links: MenuLink[], query: string): MenuLink[] {
    const words = foldText(query)
        .split(/\s+/)
        .filter((word) => word !== '');

    if (words.length === 0) {
        return [];
    }

    return links.filter((link) => words.every((word) => link.search.includes(word))).slice(0, 12);
}

/** Leaf first, then the way to it: the leaf is what was typed and what the eye looks for. */
export function trailOf(link: MenuLink): string[] {
    return link.breadcrumb.slice(0, -1).reverse();
}

export function readCollapsed(): boolean {
    try {
        return localStorage.getItem(COLLAPSED_KEY) === 'true';
    } catch {
        return false;
    }
}

export function writeCollapsed(collapsed: boolean): void {
    try {
        localStorage.setItem(COLLAPSED_KEY, String(collapsed));
    } catch {
        // Private mode: the choice lasts for this page only.
    }
}

/** Signing out forgets the form drafts kept in this browser, as the Livewire layout does. */
export function forgetDrafts(): void {
    try {
        Object.keys(localStorage)
            .filter((key) => key.startsWith('ptah:draft:'))
            .forEach((key) => localStorage.removeItem(key));
    } catch {
        // Nothing to forget.
    }
}

/** Ctrl+B / Cmd+B — the sidebar's shortcut. */
export function isCollapseShortcut(event: KeyboardEvent): boolean {
    return (event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && event.key.toLowerCase() === 'b';
}

/** The breakpoints of forge-dashboard-layout: md (the rail) and lg (the full sidebar). */
export const MEDIA_MD = '(min-width: 768px)';
export const MEDIA_LG = '(min-width: 1024px)';

export interface SidebarState {
    /** The mouse is over the sidebar. */
    hovered: boolean;
    /** Opened for a moment on a tablet (a group or the magnifier clicked on the rail). */
    peek: boolean;
    /** Opened by the hamburger (phone and tablet). */
    open: boolean;
    collapsed: boolean;
    md: boolean;
    lg: boolean;
}

/**
 * forge-sidebar's iconOnly(): only the icons when nobody is over it and it is
 * either a tablet (always a rail) or a desktop with the sidebar collapsed.
 */
export function iconOnlyOf(state: SidebarState): boolean {
    return !state.hovered && !state.peek && !(state.open && !state.lg) && ((!state.lg && state.md) || (state.lg && state.collapsed));
}

export interface AdminEntry {
    label: string;
    url: string;
    /** A key of core/icons.ts, or null to draw `boxicon`. */
    icon: string | null;
    boxicon: string;
    /** The navbar draws a rule before "Manage menu" and before "AI models" when links precede them. */
    rule: boolean;
}

/** The administration links in the navbar's order, with its icons and its rules. */
export function adminEntries(links: { label: string; url: string; icon: string }[], icons: Record<string, string>): AdminEntry[] {
    return links.map((link, index) => ({
        label: link.label,
        url: link.url,
        icon: icons[link.icon] ?? null,
        boxicon: link.icon,
        rule: index > 0 && (link.icon === 'bx bx-menu' || link.icon === 'bx bx-bot'),
    }));
}

/** The navbar's avatar: the first letter of the name (`mb_substr($name, 0, 1)`). */
export function firstLetter(name: string | null | undefined): string {
    return Array.from((name ?? '').trim())[0]?.toUpperCase() ?? 'U';
}

/** The initials of a name, for an avatar without a photo. */
export function initials(name: string | null | undefined): string {
    return (name ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase() ?? '')
        .join('');
}
