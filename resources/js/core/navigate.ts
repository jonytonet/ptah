/**
 * Moving between ptah pages without a document load (1.59.0), without a
 * framework.
 *
 * Every stack page — ptah's own, the ones `ptah:forge` writes, a host's custom
 * page — ends in the same shell element, `#ptah-app` of ptah::stack.app, whose
 * data attributes describe the page (page, screen, title, props…). So the
 * navigator fetches the target URL as HTML, reads that element of the answer
 * and hands the description to the mounted app, which swaps only the content:
 * the layout (sidebar, bell, assistant, company switcher) stays mounted. Any
 * answer that is not a stack page of the same kind (signed-in / guest) — a
 * host's Blade page, a file, a redirect elsewhere, an error — becomes a normal
 * document load, so nothing ever breaks: the worst case is today's behaviour.
 */
import type { SwitcherState } from './admin/company';
import type { Me } from './types';

/** The pages drawn without the signed-in layout. Moving between the two kinds is a full load. */
export const GUEST_PAGES: readonly string[] = ['login', 'forgot-password', 'reset-password', 'two-factor'];

export function isGuestPage(page: string): boolean {
    return GUEST_PAGES.includes(page);
}

/** What `#ptah-app` says about one page — enough to draw it. */
export interface PtahPage {
    /** The absolute URL of the page (after redirects). */
    url: string;
    page: string;
    screen: string;
    title: string;
    props: Record<string, unknown>;
    brand?: string;
    locale?: string;
    /** Where the API is mounted (`data-api`). */
    api?: string;
    profile?: string;
    themeMode?: string;
    /** `data-logout` is present. */
    logout: boolean;
    /** The document's `<title>`. */
    documentTitle: string;
}

/** What the shell page embeds so the layout paints complete on the first frame (`data-boot`). */
export interface PtahBoot {
    /** The answer of `GET {api}/menu`. */
    menu: Record<string, unknown> | null;
    /** The answer of `GET {api}/me`. */
    me: Me | null;
    /** The answer of `GET {api}/admin/company-switcher`, when the top bar shows the switcher. */
    switcher?: SwitcherState | null;
}

function parseJson(value: string | undefined): unknown {
    if (!value) {
        return null;
    }
    try {
        return JSON.parse(value);
    } catch {
        return null;
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

/** Reads the page description off a `#ptah-app` element. */
export function readPage(element: HTMLElement, url: string, documentTitle = ''): PtahPage {
    const data = element.dataset;
    const props = parseJson(data.props);

    return {
        url,
        page: data.page || 'crud',
        screen: data.screen ?? '',
        title: data.title ?? '',
        props: isRecord(props) ? props : {},
        brand: data.brand,
        locale: data.locale,
        api: data.api,
        profile: data.profile,
        themeMode: data.themeMode,
        logout: data.logout !== undefined,
        documentTitle,
    };
}

/** The menu and the user the server embedded (`data-boot`), or null. */
export function readBoot(element: HTMLElement): PtahBoot | null {
    const boot = parseJson(element.dataset.boot);
    if (!isRecord(boot)) {
        return null;
    }

    return {
        menu: isRecord(boot.menu) ? boot.menu : null,
        me: isRecord(boot.me) ? (boot.me as unknown as Me) : null,
        switcher: isRecord(boot.switcher) && Array.isArray(boot.switcher.companies) ? (boot.switcher as unknown as PtahBoot['switcher']) : null,
    };
}

export interface ParsedDocument {
    page: PtahPage;
    /** The `<meta name="csrf-token">` of the answer. */
    csrf: string | null;
}

/** A whole HTML answer → its page description, or null when it is not a stack page. */
export function parsePtahDocument(html: string, url: string): ParsedDocument | null {
    if (typeof DOMParser === 'undefined') {
        return null;
    }
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const element = doc.getElementById('ptah-app');
    if (!element) {
        return null;
    }

    return {
        page: readPage(element, url, doc.querySelector('title')?.textContent?.trim() ?? ''),
        csrf: doc.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? null,
    };
}

// ── Where links go ──────────────────────────────────────────────────────────

let handler: ((url: string) => void) | null = null;

/**
 * Sets who answers `navigateTo()` — the mounted navigator, or a host's
 * router. Returns the function that unsets it.
 */
export function setNavigationHandler(next: ((url: string) => void) | null): () => void {
    handler = next;

    return () => {
        if (handler === next) {
            handler = null;
        }
    };
}

/**
 * Goes to a URL: through the mounted app when there is one (no document
 * load), else a normal load. Row links, the redirect of a save and the link
 * actions of the cards all go through here.
 */
export function navigateTo(url: string): void {
    if (handler) {
        handler(url);
    } else {
        window.location.assign(url);
    }
}

// ── The navigator ───────────────────────────────────────────────────────────

export interface NavigatorOptions {
    /** A fetch of your own (tests). */
    fetch?: typeof fetch;
    /** The full load — `window.location.assign` (tests inject their own). */
    assign?: (url: string) => void;
    /** Links that must always load the whole document. */
    exclude?: (url: URL) => boolean;
    /**
     * Gets the first data of a page before it is shown — the old page stays on
     * screen (with the progress bar) until it resolves, at most 8 s. `mountPtah`
     * warms a CRUD screen's schema and listing here.
     */
    prepare?: (page: PtahPage) => Promise<unknown> | void;
    /**
     * Called with the target URL as the request for the document leaves, so
     * the page's data can be asked for in parallel (mountPtah warms the CRUD
     * screen whose key is the URL's path, when the user has that screen).
     */
    early?: (url: URL) => void;
    /**
     * The links prefetched on intent (hover, focus, touch): those matching
     * this selector. Default: the sidebar, the dropdowns of the top bar and
     * the breadcrumb, plus any `[data-ptah-prefetch]`. `false` turns it off.
     */
    prefetch?: string | false;
}

/** The longest the old page waits for the next one's data before swapping to its skeleton (ms). */
export const PREPARE_MS = 8000;

/** A page description fetched on a hover is used if the click comes within this time (ms). */
export const PREFETCH_MS = 30_000;

/** How long the pointer rests on a link before it is prefetched (ms). */
export const INTENT_MS = 80;

const PREFETCH_LINKS = '.pl-sidebar a[href], .pl-dropdown a[href], .pl-crumbs a[href], a[data-ptah-prefetch], [data-ptah-prefetch] a[href]';

export interface NavigateOptions {
    /** Replace the current history entry instead of adding one. */
    replace?: boolean;
}

export interface NavigatorState {
    page: PtahPage;
    /** A navigation is in flight (the old page stays on screen meanwhile). */
    busy: boolean;
    /** Grows on every swap: 0 is the page the document was loaded with. */
    seq: number;
}

interface EntryState {
    ptah?: PtahPage;
    ptahKey?: string;
    ptahScroll?: number;
}

/** Files are never pages: a link to one is a plain load, without fetching it first. */
const FILE = /\.(pdf|csv|xlsx?|ods|docx?|zip|gz|rar|7z|png|jpe?g|gif|webp|svg|ico|mp[34]|webm|txt|json|xml)$/i;

let keySeq = 0;
const newKey = () => `${Date.now().toString(36)}-${(keySeq++).toString(36)}`;

/**
 * Swaps ptah pages in place. One per mounted app: `install(root)` intercepts
 * the links inside `root` and the back/forward buttons; `navigate(url)` is
 * the programmatic way (the sidebar, the jump search, row links).
 */
export class PtahNavigator {
    private state: NavigatorState;
    private readonly listeners = new Set<() => void>();
    private readonly doFetch: typeof fetch;
    private readonly assign: (url: string) => void;
    private readonly exclude: (url: URL) => boolean;
    private abort: AbortController | null = null;
    private ticket = 0;
    private pending: { url: string; promise: Promise<boolean> } | null = null;
    private key = newKey();
    /** Scroll position of each history entry this document visited. */
    private readonly scrolls = new Map<string, number>();
    private root: HTMLElement | null = null;
    private readonly prepare: NavigatorOptions['prepare'];
    private readonly early: NavigatorOptions['early'];
    private readonly prefetchLinks: string | false;
    /** Descriptions fetched on intent, by URL. */
    private readonly described = new Map<string, { parsed: ParsedDocument; at: number }>();
    /** The prefetch in flight (one at a time). */
    private prefetching: { href: string; promise: Promise<boolean> } | null = null;

    constructor(initial: PtahPage, options: NavigatorOptions = {}) {
        this.state = { page: initial, busy: false, seq: 0 };
        this.doFetch = options.fetch ?? ((...args) => fetch(...args));
        this.assign = options.assign ?? ((url) => window.location.assign(url));
        this.exclude = options.exclude ?? (() => false);
        this.prepare = options.prepare;
        this.prefetchLinks = options.prefetch === undefined ? PREFETCH_LINKS : options.prefetch;
        this.early = options.early;
    }

    getState = (): NavigatorState => this.state;

    subscribe = (listener: () => void): (() => void) => {
        this.listeners.add(listener);

        return () => {
            this.listeners.delete(listener);
        };
    };

    private set(patch: Partial<NavigatorState>): void {
        this.state = { ...this.state, ...patch };
        this.listeners.forEach((listener) => listener());
    }

    /** Whether the page on screen is a guest page (login…) rather than a signed-in one. */
    private get guest(): boolean {
        return isGuestPage(this.state.page.page);
    }

    private fallback(url: string): void {
        this.assign(url);
    }

    /**
     * Goes to `url`: swaps the page when the answer is a stack page of the
     * same kind, else loads the document. True when it swapped.
     */
    navigate(url: string, options: NavigateOptions = {}): Promise<boolean> {
        const target = this.resolve(url);
        if (!target) {
            this.fallback(url);

            return Promise.resolve(false);
        }
        // The same link twice (a framework handler and the delegated one): one request.
        if (this.pending && this.pending.url === target.href) {
            return this.pending.promise;
        }
        const promise = this.load(target, options.replace ? 'replace' : 'push');
        this.pending = { url: target.href, promise };

        return promise;
    }

    private resolve(url: string): URL | null {
        try {
            const target = new URL(url, window.location.href);

            return target.origin === window.location.origin && /^https?:$/.test(target.protocol) ? target : null;
        } catch {
            return null;
        }
    }

    /**
     * Fetches `target` and reads its page description: null when the answer
     * is not a stack page (an error, a 401/419, a file, another site, a host's
     * own page). Throws when the request fails or is aborted.
     */
    private async describe(target: URL, signal: AbortSignal | undefined): Promise<ParsedDocument | null> {
        const response = await this.doFetch(target.href, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Ptah-Navigate': '1', Accept: 'text/html' },
            signal,
            // Never stored: the HTTP cache must not hand this answer to a later full load.
            cache: 'no-store',
            redirect: 'follow',
        });

        const finalUrl = response.url || target.href;
        const type = response.headers.get('content-type') ?? '';
        const disposition = response.headers.get('content-disposition') ?? '';
        let sameOrigin = false;
        try {
            sameOrigin = new URL(finalUrl, window.location.href).origin === window.location.origin;
        } catch {
            sameOrigin = false;
        }
        if (response.status !== 200 || !sameOrigin || !/text\/html/i.test(type) || /attachment/i.test(disposition)) {
            // Do not download a file only to throw it away.
            try {
                await response.body?.cancel();
            } catch {
                // Already closed.
            }

            return null;
        }

        return parsePtahDocument(await response.text(), finalUrl);
    }

    /** A description fetched on a hover, still fresh, for this URL. */
    private fresh(href: string): ParsedDocument | null {
        const known = this.described.get(href);
        if (!known) {
            return null;
        }
        if (Date.now() - known.at > PREFETCH_MS) {
            this.described.delete(href);

            return null;
        }

        return known.parsed;
    }

    private async load(target: URL, mode: 'push' | 'replace' | 'pop'): Promise<boolean> {
        this.abort?.abort();
        const controller = typeof AbortController === 'undefined' ? null : new AbortController();
        this.abort = controller;
        const ticket = ++this.ticket;
        const current = () => ticket === this.ticket;
        this.set({ busy: true });
        const deadline = Date.now() + PREPARE_MS;

        try {
            // A hover already on its way to this page: wait for it rather than asking twice.
            if (this.prefetching?.href === target.href) {
                await this.prefetching.promise;
            }
            let parsed = this.fresh(target.href);
            if (!parsed) {
                this.callEarly(target);
                parsed = await this.describe(target, controller?.signal);
            }
            this.described.delete(target.href);
            if (!current()) {
                return false;
            }
            // Not a stack page, or the other kind (signed in ↔ guest): the browser does it.
            if (!parsed || isGuestPage(parsed.page.page) !== this.guest) {
                this.fallback(target.href);

                return false;
            }

            // The old page stays on screen until the new one has its first data
            // (a CRUD screen: schema and listing), at most PREPARE_MS.
            await this.ready(parsed.page, deadline);
            if (!current()) {
                return false;
            }

            if (parsed.csrf) {
                this.updateCsrf(parsed.csrf);
            }
            const landed = new URL(parsed.page.url, window.location.href);
            if (target.hash && !landed.hash) {
                landed.hash = target.hash;
            }
            this.commit({ ...parsed.page, url: landed.href }, mode, 0);

            return true;
        } catch {
            if (!current()) {
                return false;
            }
            this.fallback(target.href);

            return false;
        } finally {
            if (current()) {
                this.pending = null;
                this.abort = null;
                if (this.state.busy) {
                    this.set({ busy: false });
                }
            }
        }
    }

    /** `prepare(page)`, capped: a slow server swaps to the page's skeleton after PREPARE_MS. */
    private callEarly(target: URL): void {
        try {
            this.early?.(target);
        } catch {
            // Only a head start.
        }
    }

    private ready(page: PtahPage, deadline = Date.now() + PREPARE_MS): Promise<void> {
        if (!this.prepare) {
            return Promise.resolve();
        }
        let timer: ReturnType<typeof setTimeout> | undefined;
        const cap = new Promise<void>((resolve) => {
            timer = setTimeout(resolve, Math.max(0, deadline - Date.now()));
        });
        const work = Promise.resolve()
            .then(() => this.prepare?.(page))
            .then(
                () => undefined,
                () => undefined,
            );

        return Promise.race([work, cap]).finally(() => clearTimeout(timer));
    }

    /**
     * Fetches what `url` needs before the user clicks it (a hover, a focus, a
     * touch on a menu link): the page description and, through `prepare`,
     * the first data of the screen. One at a time; never on a data-saving
     * connection. Resolves true when the page is ready to swap.
     */
    async prefetch(url: string): Promise<boolean> {
        const target = this.resolve(url);
        const connection = typeof navigator !== 'undefined' ? (navigator as Navigator & { connection?: { saveData?: boolean } }).connection : undefined;
        if (!target || this.prefetching || connection?.saveData || this.pending) {
            return false;
        }
        const here = new URL(this.state.page.url, window.location.href);
        if (target.pathname === here.pathname && target.search === here.search) {
            return false;
        }
        if (this.fresh(target.href)) {
            return true;
        }
        const promise = (async () => {
            try {
                this.callEarly(target);
                const parsed = await this.describe(target, undefined);
                if (!parsed || isGuestPage(parsed.page.page) !== this.guest) {
                    return false;
                }
                this.described.set(target.href, { parsed, at: Date.now() });
                await this.ready(parsed.page);

                return true;
            } catch {
                return false;
            } finally {
                this.prefetching = null;
            }
        })();
        this.prefetching = { href: target.href, promise };

        return promise;
    }

    private updateCsrf(token: string): void {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content') !== token) {
            meta.setAttribute('content', token);
        }
    }

    /** Puts `page` on screen; `scroll` is where the window goes after it is drawn. */
    private commit(page: PtahPage, mode: 'push' | 'replace' | 'pop', scroll: number): void {
        if (mode !== 'pop') {
            this.scrolls.set(this.key, window.scrollY);
            const before = this.entry();
            if (mode === 'push') {
                // The entry we leave remembers its scroll for a back after a reload.
                this.writeEntry({ ...before, ptah: this.state.page, ptahKey: this.key, ptahScroll: window.scrollY }, 'replaceState', window.location.href);
                this.key = newKey();
            }
            this.writeEntry({ ptah: page, ptahKey: this.key, ptahScroll: 0 }, mode === 'push' ? 'pushState' : 'replaceState', page.url);
        }

        if (page.documentTitle) {
            document.title = page.documentTitle;
        }
        this.set({ page, busy: false, seq: this.state.seq + 1 });
        this.afterPaint(() => {
            const hash = new URL(page.url).hash;
            const anchor = hash && mode !== 'pop' ? document.getElementById(decodeURIComponent(hash.slice(1))) : null;
            if (anchor) {
                anchor.scrollIntoView();
            } else {
                window.scrollTo(0, scroll);
            }
            this.focusPage();
        });
    }

    private entry(): EntryState {
        const state: unknown = window.history.state;

        return isRecord(state) ? (state as EntryState) : {};
    }

    private writeEntry(state: EntryState, method: 'pushState' | 'replaceState', url: string): void {
        try {
            window.history[method](state, '', url);
        } catch {
            // A state the browser cannot store: the entry is refetched on back.
            window.history[method]({ ptahKey: state.ptahKey }, '', url);
        }
    }

    private afterPaint(callback: () => void): void {
        const frame = typeof requestAnimationFrame === 'function' ? requestAnimationFrame : (fn: () => void) => setTimeout(fn, 0);
        // Two frames: the framework draws the new page in the first.
        frame(() => frame(callback));
    }

    /** After a swap, focus goes to the page's heading (else the main region), as a screen reader expects. */
    private focusPage(): void {
        const root = this.root ?? document.body;
        const target = root.querySelector<HTMLElement>('main h1') ?? root.querySelector<HTMLElement>('main');
        if (!target) {
            return;
        }
        if (!target.hasAttribute('tabindex')) {
            target.setAttribute('tabindex', '-1');
        }
        target.focus({ preventScroll: true });
    }

    // ── Links and history ───────────────────────────────────────────────────

    /** The URL a click should swap to, or null when the browser should do it. */
    linkOf(event: MouseEvent, root: Element): string | null {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return null;
        }
        // The path as dispatched: a handler before this one (a dropdown closing on
        // click) may already have taken the link out of the document.
        const path = typeof event.composedPath === 'function' ? event.composedPath() : [];
        const origin = event.target as Element | null;
        const anchor = (path.find((node) => node instanceof HTMLAnchorElement && node.hasAttribute('href')) as HTMLAnchorElement | undefined) ?? (origin && typeof origin.closest === 'function' ? origin.closest('a[href]') : null);
        if (!(anchor instanceof HTMLAnchorElement) || !(path.includes(root) || root.contains(anchor))) {
            return null;
        }

        return this.anchorUrl(anchor, root);
    }

    /** The URL a link inside `root` swaps to, or null when it is the browser's. */
    private anchorUrl(anchor: HTMLAnchorElement, root: Element): string | null {
        if (anchor.isConnected && !root.contains(anchor)) {
            return null;
        }
        const target = anchor.getAttribute('target');
        if ((target && target !== '_self') || anchor.hasAttribute('download') || anchor.closest('[data-ptah-reload]') || anchor.getAttribute('rel')?.split(/\s+/).includes('external')) {
            return null;
        }
        const href = anchor.getAttribute('href') ?? '';
        if (href === '' || href.startsWith('#')) {
            return null;
        }
        const url = this.resolve(anchor.href);
        if (!url) {
            return null;
        }
        const here = new URL(window.location.href);
        // A jump inside this page.
        if (url.hash && url.pathname === here.pathname && url.search === here.search) {
            return null;
        }
        if (FILE.test(url.pathname) || this.exclude(url)) {
            return null;
        }

        return url.href;
    }

    /**
     * Takes over the links inside `root` and back/forward. Returns the
     * function that gives them back.
     */
    install(root: HTMLElement): () => void {
        this.root = root;
        const history = window.history;
        const restoration = history.scrollRestoration;
        const before = this.entry();
        // A reload keeps history.state: the scroll it saved is where the user was.
        const saved = before.ptah && before.ptah.url === this.state.page.url && typeof before.ptahScroll === 'number' ? before.ptahScroll : null;
        if (before.ptahKey) {
            this.key = before.ptahKey;
        }
        this.writeEntry({ ...before, ptah: this.state.page, ptahKey: this.key, ptahScroll: saved ?? 0 }, 'replaceState', window.location.href);
        try {
            history.scrollRestoration = 'manual';
        } catch {
            // Read-only in an old browser: it restores by itself.
        }
        if (saved) {
            this.afterPaint(() => window.scrollTo(0, saved));
        }

        const onClick = (event: MouseEvent) => {
            const url = this.linkOf(event, root);
            if (url) {
                event.preventDefault();
                void this.navigate(url);
            }
        };
        const onPop = (event: PopStateEvent) => this.pop(event.state);
        // Before a reload or leaving: where the user was, for when they come back.
        const onHide = () => {
            const entry = this.entry();
            if (entry.ptahKey === this.key) {
                this.writeEntry({ ...entry, ptahScroll: window.scrollY }, 'replaceState', window.location.href);
            }
        };

        // Intent (the pointer rests on a menu link, the keyboard focuses it, a
        // finger touches it): fetch what the page needs, so the click is instant.
        let intent: ReturnType<typeof setTimeout> | undefined;
        const onIntent = (event: Event) => {
            const selector = this.prefetchLinks;
            const origin = event.target as Element | null;
            const anchor = selector && origin && typeof origin.closest === 'function' ? origin.closest(selector) : null;
            if (!(anchor instanceof HTMLAnchorElement)) {
                return;
            }
            const url = this.anchorUrl(anchor, root);
            clearTimeout(intent);
            if (url) {
                intent = setTimeout(() => void this.prefetch(url), event.type === 'touchstart' ? 0 : INTENT_MS);
            }
        };
        const onLeave = () => clearTimeout(intent);

        root.addEventListener('click', onClick);
        root.addEventListener('mouseover', onIntent);
        root.addEventListener('focusin', onIntent);
        root.addEventListener('touchstart', onIntent, { passive: true });
        root.addEventListener('mouseout', onLeave);
        window.addEventListener('popstate', onPop);
        window.addEventListener('pagehide', onHide);

        return () => {
            clearTimeout(intent);
            root.removeEventListener('mouseover', onIntent);
            root.removeEventListener('focusin', onIntent);
            root.removeEventListener('touchstart', onIntent);
            root.removeEventListener('mouseout', onLeave);
            root.removeEventListener('click', onClick);
            window.removeEventListener('popstate', onPop);
            window.removeEventListener('pagehide', onHide);
            this.abort?.abort();
            this.root = null;
            try {
                history.scrollRestoration = restoration;
            } catch {
                // See above.
            }
        };
    }

    /** Back/forward: the description kept in the entry, without fetching the document again. */
    pop(raw: unknown): void {
        const entry: EntryState = isRecord(raw) ? (raw as EntryState) : {};
        this.scrolls.set(this.key, window.scrollY);
        const here = window.location.href;

        if (entry.ptah) {
            if (isGuestPage(entry.ptah.page) !== this.guest) {
                this.fallback(here);

                return;
            }
            // A navigation still in flight is dropped: the user went elsewhere.
            this.abort?.abort();
            this.ticket++;
            this.pending = null;
            this.key = entry.ptahKey ?? newKey();
            const scroll = this.scrolls.get(this.key) ?? entry.ptahScroll ?? 0;
            this.commit({ ...entry.ptah, url: here }, 'pop', scroll);

            return;
        }

        // An entry we did not write: a #hash of this page, or one from before the app.
        const now = new URL(here);
        const shown = new URL(this.state.page.url, here);
        if (now.pathname === shown.pathname && now.search === shown.search) {
            return;
        }
        this.key = entry.ptahKey ?? newKey();
        const target = this.resolve(here);
        if (!target) {
            this.fallback(here);

            return;
        }
        void this.load(target, 'replace');
    }
}

/** The page the document was loaded with, read off its `#ptah-app`. */
export function initialPage(element: HTMLElement): PtahPage {
    return readPage(element, window.location.href, document.title);
}
