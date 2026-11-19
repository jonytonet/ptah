import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { forgetSnapshots, parsePtahDocument, PREPARE_MS, PtahNavigator, readBoot, readPage, readSnapshot, setSnapshotScope, SNAPSHOT_LIMIT, writeSnapshot } from '../../core';
import type { PtahPage, Snapshot } from '../../core';
import { schema } from '../fake-api';
import { shellHtml } from '../navigation.fake';

const page = (over: Partial<PtahPage> = {}): PtahPage => ({ url: 'http://localhost:3000/items', page: 'crud', screen: 'items', title: 'Itens', props: {}, logout: true, documentTitle: 'Itens — ERP', ...over });

const html = (body: string, type = 'text/html; charset=UTF-8', status = 200, url = '') => {
    const response = new Response(body, { status, headers: { 'Content-Type': type } });
    if (url) {
        Object.defineProperty(response, 'url', { value: url });
    }

    return response;
};

describe('the document parser', () => {
    it('reads the shell element, the title and the CSRF token of an answer', () => {
        const parsed = parsePtahDocument(shellHtml({ page: 'crud', screen: 'product', title: 'Produtos & cia' }, 'abc'), 'http://localhost:3000/product');

        expect(parsed?.page).toMatchObject({ page: 'crud', screen: 'product', title: 'Produtos & cia', documentTitle: 'Produtos & cia — ERP', url: 'http://localhost:3000/product', logout: true, locale: 'pt_BR', api: '/ptah/api' });
        expect(parsed?.csrf).toBe('abc');
    });

    it('answers null for a page without the shell element', () => {
        expect(parsePtahDocument('<html><body><div id="app"></div></body></html>', '/x')).toBeNull();
    });

    it('survives broken props and boot data', () => {
        const element = document.createElement('div');
        element.dataset.props = '{broken';
        element.dataset.boot = '[1,2]';

        expect(readPage(element, '/x').props).toEqual({});
        expect(readPage(element, '/x').page).toBe('crud');
        expect(readBoot(element)).toBeNull();

        element.dataset.boot = JSON.stringify({ menu: { data: [] }, me: { user: { id: 1 } } });
        expect(readBoot(element)).toEqual({ menu: { data: [] }, me: { user: { id: 1 } }, switcher: null });
    });
});

describe('PtahNavigator', () => {
    beforeEach(() => {
        window.history.replaceState(null, '', '/items');
        window.scrollTo = vi.fn() as unknown as typeof window.scrollTo;
    });
    afterEach(() => vi.restoreAllMocks());

    it('follows redirects: the history entry is the final URL', async () => {
        const fetch = vi.fn(async () => html(shellHtml({ page: 'crud', screen: 'orders', title: 'Pedidos' }), undefined, 200, 'http://localhost:3000/orders?page=2'));
        const assign = vi.fn();
        const navigator = new PtahNavigator(page(), { fetch: fetch as unknown as typeof globalThis.fetch, assign });

        expect(await navigator.navigate('/old-orders')).toBe(true);

        expect(window.location.pathname + window.location.search).toBe('/orders?page=2');
        expect(navigator.getState()).toMatchObject({ busy: false, seq: 1, page: { screen: 'orders', url: 'http://localhost:3000/orders?page=2' } });
        expect(fetch).toHaveBeenCalledWith('http://localhost:3000/old-orders', expect.objectContaining({ credentials: 'same-origin', cache: 'no-store', headers: { 'X-Ptah-Navigate': '1', Accept: 'text/html' } }));
        expect(assign).not.toHaveBeenCalled();
    });

    it.each([
        ['an error', () => html('nope', 'text/html', 500)],
        ['a session that ended', () => html('', 'text/html', 419)],
        ['a file', () => html('a,b', 'text/csv')],
        ['a host page', () => html('<html><body><h1>Blade</h1></body></html>')],
        ['a guest page', () => html(shellHtml({ page: 'login' }))],
        ['another site', () => html(shellHtml({ page: 'crud' }), undefined, 200, 'https://elsewhere.test/x')],
    ])('loads the document for %s', async (_, answer) => {
        const assign = vi.fn();
        const navigator = new PtahNavigator(page(), { fetch: (async () => answer()) as unknown as typeof fetch, assign });

        expect(await navigator.navigate('/target')).toBe(false);
        expect(assign).toHaveBeenCalledWith('http://localhost:3000/target');
        expect(navigator.getState()).toMatchObject({ busy: false, seq: 0 });
        expect(window.location.pathname).toBe('/items');
    });

    it('a failed request is a document load too', async () => {
        const assign = vi.fn();
        const navigator = new PtahNavigator(page(), { fetch: (async () => Promise.reject(new TypeError('offline'))) as unknown as typeof fetch, assign });

        expect(await navigator.navigate('/target')).toBe(false);
        expect(assign).toHaveBeenCalledWith('http://localhost:3000/target');
    });

    it('a second navigation cancels the first; busy while in flight', async () => {
        const signals: AbortSignal[] = [];
        let finishFirst: (value: Response) => void = () => undefined;
        const fetch = vi.fn((url: string, init: RequestInit) => {
            signals.push(init.signal as AbortSignal);
            if (url.endsWith('/slow')) {
                return new Promise<Response>((resolve) => {
                    finishFirst = resolve;
                });
            }

            return Promise.resolve(html(shellHtml({ page: 'crud', screen: 'fast' })));
        });
        const navigator = new PtahNavigator(page(), { fetch: fetch as unknown as typeof globalThis.fetch, assign: vi.fn() });

        const slow = navigator.navigate('/slow');
        expect(navigator.getState().busy).toBe(true);
        const fast = navigator.navigate('/fast');

        expect(signals[0].aborted).toBe(true);
        expect(await fast).toBe(true);
        finishFirst(html(shellHtml({ page: 'crud', screen: 'slow' })));
        expect(await slow).toBe(false);
        expect(navigator.getState().page.screen).toBe('fast');
        expect(window.location.pathname).toBe('/fast');
    });

    it('back/forward use the description kept in the entry, without a request', async () => {
        const fetch = vi.fn(async () => html(shellHtml({ page: 'crud', screen: 'orders' })));
        const navigator = new PtahNavigator(page(), { fetch: fetch as unknown as typeof globalThis.fetch, assign: vi.fn() });
        const root = document.createElement('div');
        document.body.appendChild(root);
        const uninstall = navigator.install(root);

        await navigator.navigate('/orders');
        window.history.back();
        await vi.waitFor(() => expect(navigator.getState().page.screen).toBe('items'));
        expect(fetch).toHaveBeenCalledTimes(1);

        uninstall();
        root.remove();
    });

    it('keeps the old page until the next one is prepared, at most PREPARE_MS', async () => {
        vi.useFakeTimers();
        try {
            let finish: () => void = () => undefined;
            const prepare = vi.fn(
                () =>
                    new Promise<void>((resolve) => {
                        finish = resolve;
                    }),
            );
            const fetch = vi.fn(async () => html(shellHtml({ page: 'crud', screen: 'orders' })));
            const navigator = new PtahNavigator(page(), { fetch: fetch as unknown as typeof globalThis.fetch, assign: vi.fn(), prepare });

            const going = navigator.navigate('/orders');
            await vi.advanceTimersByTimeAsync(10);
            expect(prepare).toHaveBeenCalledWith(expect.objectContaining({ screen: 'orders' }));
            expect(navigator.getState()).toMatchObject({ busy: true, page: { screen: 'items' } });
            finish();
            expect(await going).toBe(true);
            expect(navigator.getState().page.screen).toBe('orders');

            // A server that never answers: the swap happens anyway after the cap.
            const stuck = new PtahNavigator(page(), { fetch: fetch as unknown as typeof globalThis.fetch, assign: vi.fn(), prepare: () => new Promise(() => undefined) });
            const late = stuck.navigate('/orders');
            await vi.advanceTimersByTimeAsync(PREPARE_MS - 100);
            expect(stuck.getState().page.screen).toBe('items');
            await vi.advanceTimersByTimeAsync(200);
            expect(await late).toBe(true);
            expect(stuck.getState().page.screen).toBe('orders');
        } finally {
            vi.useRealTimers();
        }
    });

    it('prefetches a menu link on intent, once, and the click uses it', async () => {
        const fetch = vi.fn(async () => html(shellHtml({ page: 'crud', screen: 'orders' })));
        const prepare = vi.fn(async () => undefined);
        const navigator = new PtahNavigator(page(), { fetch: fetch as unknown as typeof globalThis.fetch, assign: vi.fn(), prepare });
        const root = document.createElement('div');
        root.innerHTML = '<aside class="pl-sidebar"><a id="menu" href="/orders"><span>Pedidos</span></a></aside><a id="row" href="/clients">Clientes</a>';
        document.body.appendChild(root);
        const uninstall = navigator.install(root);

        root.querySelector('#row')?.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
        root.querySelector('#menu span')?.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
        await vi.waitFor(() => expect(prepare).toHaveBeenCalledTimes(1));
        // Only the menu link, and only once.
        expect(fetch).toHaveBeenCalledTimes(1);
        expect(fetch).toHaveBeenCalledWith('http://localhost:3000/orders', expect.anything());
        expect(await navigator.prefetch('/orders')).toBe(true);
        expect(fetch).toHaveBeenCalledTimes(1);

        expect(await navigator.navigate('/orders')).toBe(true);
        expect(fetch).toHaveBeenCalledTimes(1);
        expect(navigator.getState().page.screen).toBe('orders');

        uninstall();
        root.remove();
    });

    it('does not prefetch on a data-saving connection', async () => {
        const fetch = vi.fn(async () => html(shellHtml({ page: 'crud', screen: 'orders' })));
        Object.defineProperty(window.navigator, 'connection', { value: { saveData: true }, configurable: true });
        try {
            const navigator = new PtahNavigator(page(), { fetch: fetch as unknown as typeof globalThis.fetch });
            expect(await navigator.prefetch('/orders')).toBe(false);
            expect(fetch).not.toHaveBeenCalled();
        } finally {
            Object.defineProperty(window.navigator, 'connection', { value: undefined, configurable: true });
        }
    });

    it('a link taken out of the page by an earlier handler (a dropdown closing) still swaps', async () => {
        const fetch = vi.fn(async () => html(shellHtml({ page: 'crud', screen: 'orders' })));
        const assign = vi.fn();
        const navigator = new PtahNavigator(page(), { fetch: fetch as unknown as typeof globalThis.fetch, assign });
        const root = document.createElement('div');
        root.innerHTML = '<div class="dd"><a id="go" href="/orders">Pedidos</a></div>';
        document.body.appendChild(root);
        const uninstall = navigator.install(root);
        const link = root.querySelector('#go') as HTMLElement;
        link.addEventListener('click', () => root.querySelector('.dd')?.remove());

        const event = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
        link.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
        await vi.waitFor(() => expect(navigator.getState().page.screen).toBe('orders'));
        expect(assign).not.toHaveBeenCalled();
        uninstall();
        root.remove();
    });

    it('says which clicks it takes', () => {
        const navigator = new PtahNavigator(page(), { exclude: (url) => url.pathname.startsWith('/legacy') });
        const root = document.createElement('div');
        root.innerHTML = [
            '<a id="plain" href="/orders"><span id="inner">Pedidos</span></a>',
            '<a id="blank" href="/orders" target="_blank">x</a>',
            '<a id="self" href="/orders" target="_self">x</a>',
            '<a id="download" href="/orders" download>x</a>',
            '<a id="hash" href="#top">x</a>',
            '<a id="here" href="/items#top">x</a>',
            '<a id="other" href="https://elsewhere.test/x">x</a>',
            '<a id="file" href="/reports/sales.pdf">x</a>',
            '<a id="legacy" href="/legacy/screen">x</a>',
            '<a id="mail" href="mailto:a@b.c">x</a>',
            '<div data-ptah-reload><a id="optout" href="/orders">x</a></div>',
        ].join('');
        document.body.appendChild(root);
        const click = (id: string, init: MouseEventInit = {}) => {
            const event = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...init });
            Object.defineProperty(event, 'target', { value: root.querySelector(`#${id}`) });

            return navigator.linkOf(event, root);
        };

        expect(click('plain')).toBe('http://localhost:3000/orders');
        expect(click('inner')).toBe('http://localhost:3000/orders');
        expect(click('self')).toBe('http://localhost:3000/orders');
        for (const id of ['blank', 'download', 'hash', 'here', 'other', 'file', 'legacy', 'mail', 'optout']) {
            expect([id, click(id)]).toEqual([id, null]);
        }
        expect(click('plain', { ctrlKey: true })).toBeNull();
        expect(click('plain', { shiftKey: true })).toBeNull();
        expect(click('plain', { button: 1 })).toBeNull();

        root.remove();
    });
});

describe('kept listings (instant)', () => {
    const snapshot = (rows = 1): Snapshot => ({
        schema,
        preferences: null,
        rows: Array.from({ length: rows }, (_, id) => ({ id, cells: { name: { value: 'x', html: 'x'.repeat(100) } }, style: null, actions: [], trashed: false })),
        meta: null,
        totals: [],
        breaks: [],
        list: { page: 1, per_page: 25, sort: 'id', direction: 'DESC', search: '', filters: {}, operators: {}, date_ranges: {}, date_operators: {}, quick_date: '', trashed: false },
    });

    beforeEach(() => sessionStorage.clear());
    afterEach(() => setSnapshotScope(null));

    it('belong to a user and company, and live in sessionStorage only', () => {
        writeSnapshot('items', snapshot());
        expect(sessionStorage.length).toBe(0);

        setSnapshotScope(9, 1);
        writeSnapshot('items', snapshot());
        expect(readSnapshot('items')?.rows).toHaveLength(1);
        expect(Object.keys(sessionStorage)).toEqual(['ptah:snap:9:1:items']);
        expect(localStorage.getItem('ptah:snap:9:1:items')).toBeNull();

        setSnapshotScope(10, 1);
        expect(readSnapshot('items')).toBeNull();
    });

    it('a listing over the limit is not kept; signing out forgets them all', () => {
        setSnapshotScope(9, 1);
        writeSnapshot('small', snapshot());
        writeSnapshot('big', snapshot(Math.ceil(SNAPSHOT_LIMIT / 100)));
        expect(readSnapshot('big')).toBeNull();
        expect(readSnapshot('small')).not.toBeNull();

        sessionStorage.setItem('other', 'kept');
        forgetSnapshots();
        expect(readSnapshot('small')).toBeNull();
        expect(sessionStorage.getItem('other')).toBe('kept');
    });
});
