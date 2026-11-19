import { vi } from 'vitest';
import { fakeApi } from './fake-api';

/** The menu and "me" a signed-in shell page embeds (`data-boot`). */
export const bootData = {
    menu: {
        data: [
            { label: 'Itens', url: '/items', type: 'menuLink', icon: 'bx bx-cube', match: 'items', children: [] },
            { label: 'Relatórios', url: '/reports', type: 'menuLink', icon: 'bx bx-chart', children: [] },
        ],
        flat: [],
        jump: false,
        brand: { name: 'ERP', logo: null, logo_dark: null },
        shell: { company_switcher: false, notifications: false, admin: [] },
    },
    me: { user: { id: 9, name: 'Ana', email: 'ana@example.test' }, company_id: 1, locale: 'pt_BR', screens: ['items'] },
};

export interface ShellPage {
    page: string;
    screen?: string;
    title?: string;
}

const escape = (value: string) => value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

/** A whole document as ptah::stack.app answers it. */
export function shellHtml(page: ShellPage, csrf = 'token-2'): string {
    return `<!DOCTYPE html><html><head><meta name="csrf-token" content="${csrf}"><title>${page.title ? `${page.title} — ` : ''}ERP</title></head><body><div id="ptah-app" data-page="${page.page}" data-screen="${page.screen ?? ''}" data-props="{}" data-title="${escape(page.title ?? '')}" data-locale="pt_BR" data-api="/ptah/api" data-logout="/logout"></div></body></html>`;
}

/**
 * A document with the shell element, mounted where the test can reach it,
 * on `path`; the API is the in-memory one of fake-api (global fetch), and the
 * pages the navigator fetches are `pages` (anything else: a host's page).
 */
export function navigationWorld(pages: Record<string, ShellPage>, options: { boot?: boolean } = {}) {
    const fake = fakeApi();
    vi.stubGlobal('fetch', (input: RequestInfo | URL, init?: RequestInit) => (fake.api as unknown as { doFetch: typeof fetch }).doFetch(input, init));
    window.scrollTo = vi.fn() as unknown as typeof window.scrollTo;

    const documents: string[] = [];
    const navFetch = vi.fn(async (input: RequestInfo | URL) => {
        const url = new URL(String(input), window.location.href);
        documents.push(url.pathname);
        const page = pages[url.pathname];
        const response = page
            ? new Response(shellHtml(page), { status: 200, headers: { 'Content-Type': 'text/html; charset=UTF-8' } })
            : new Response('<!DOCTYPE html><html><body><h1>A Blade page of the host</h1></body></html>', { status: 200, headers: { 'Content-Type': 'text/html' } });
        Object.defineProperty(response, 'url', { value: url.href });

        return response;
    });
    const assign = vi.fn();

    const element = document.createElement('div');
    element.id = 'ptah-app';
    const start = pages[window.location.pathname] ?? { page: 'crud', screen: 'items', title: 'Itens' };
    element.dataset.page = start.page;
    element.dataset.screen = start.screen ?? '';
    element.dataset.title = start.title ?? '';
    element.dataset.props = '{}';
    element.dataset.locale = 'pt_BR';
    element.dataset.api = '/ptah/api';
    element.dataset.logout = '/logout';
    if (options.boot !== false) {
        element.dataset.boot = JSON.stringify(bootData);
    }
    document.body.appendChild(element);

    let meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) {
        meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        document.head.appendChild(meta);
    }
    meta.setAttribute('content', 'token-1');

    return {
        ...fake,
        element,
        /** The document was loaded on another page. */
        startAt: (path: string) => {
            window.history.replaceState(null, '', path);
            const page = pages[path];
            element.dataset.page = page.page;
            element.dataset.screen = page.screen ?? '';
            element.dataset.title = page.title ?? '';
        },
        navFetch,
        assign,
        documents,
        /** Requests the layout made to the API for these paths. */
        apiCalls: (path: string) => fake.calls.filter((call) => new URL(call.url, 'http://localhost').pathname === `/ptah/api${path}`).length,
        cleanup: () => {
            element.remove();
            vi.unstubAllGlobals();
        },
    };
}

/** Clicks like a user would and tells whether the browser would still follow the link. */
export function clickLink(link: Element, init: MouseEventInit = {}): boolean {
    let prevented = false;
    const spy = (event: Event) => {
        prevented = event.defaultPrevented;
        // jsdom cannot load documents: never let it try.
        event.preventDefault();
    };
    document.addEventListener('click', spy);
    link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...init }));
    document.removeEventListener('click', spy);

    return !prevented;
}
