import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { embedsAnswers, forgetPreloaded, forgetSnapshots, parsePtahDocument, preloadAnswers, PRELOAD_MS, PtahApi, PtahNavigator, readAnswers, setSnapshotScope, takeWarm, warmOptions } from '../../core';
import type { PtahPage } from '../../core';
import { schema } from '../fake-api';

const listing = { data: [{ id: 1, name: 'Alfa' }], meta: { total: 1, per_page: 25, current_page: 1, last_page: 1 }, totals: [], breaks: [], state: { search: '', sort: 'id', direction: 'DESC', page: 1, per_page: 25, filters: {} }, messages: [] };

const answers = { '/crud/items/schema': schema, '/crud/items?restore=1': listing, '/crud/items/filters/meta': { filters: [], cell_fields: ['name'] }, '/crud/items/config/can': { can: true } };

const escape = (value: string) => value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

const pageHtml = (embedded: unknown) =>
    `<!DOCTYPE html><html><head><title>Itens — ERP</title></head><body><div id="ptah-app" data-page="crud" data-screen="items" data-props="{}" data-title="Itens" data-api="/ptah/api" data-logout="/logout" data-embed="1"${embedded ? ` data-answers="${escape(JSON.stringify(embedded))}"` : ''}></div></body></html>`;

const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200, headers: { 'Content-Type': 'application/json' } });

describe('the answers a page brings with it', () => {
    beforeEach(() => {
        forgetPreloaded();
        vi.useFakeTimers();
    });
    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('answers the first GET of a path without the network, once', async () => {
        const fetch = vi.fn(async () => json({ from: 'network' }));
        const api = new PtahApi({ fetch: fetch as unknown as typeof globalThis.fetch });
        preloadAnswers(answers);

        expect(await api.schema('items')).toEqual(schema);
        expect(await api.list('items', {}, { restore: true })).toEqual(listing);
        expect(await api.call('GET', '/crud/items/config/can')).toEqual({ can: true });
        expect(fetch).not.toHaveBeenCalled();

        // The second time is the server's.
        expect(await api.call('GET', '/crud/items/config/can')).toEqual({ from: 'network' });
        expect(fetch).toHaveBeenCalledTimes(1);
    });

    it('never answers another listing, a write, or an old answer', async () => {
        const fetch = vi.fn(async () => json({ from: 'network' }));
        const api = new PtahApi({ fetch: fetch as unknown as typeof globalThis.fetch });
        preloadAnswers(answers);

        // Page 2 is not the embedded listing.
        expect(await api.list('items', { page: 2 }, { restore: true })).toEqual({ from: 'network' });
        // A POST to an embedded path goes out.
        expect(await api.call('POST', '/crud/items/schema', {})).toEqual({ from: 'network' });

        vi.advanceTimersByTime(PRELOAD_MS + 1);
        expect(await api.schema('items')).toEqual({ from: 'network' });
        expect(fetch).toHaveBeenCalledTimes(3);
    });

    it('is forgotten with the kept listings (sign out, company switch) and ignores what is not a map', async () => {
        const fetch = vi.fn(async () => json({ from: 'network' }));
        const api = new PtahApi({ fetch: fetch as unknown as typeof globalThis.fetch });

        preloadAnswers(answers);
        forgetSnapshots();
        expect(await api.schema('items')).toEqual({ from: 'network' });

        preloadAnswers(null);
        preloadAnswers([1, 2]);
        preloadAnswers('x');
        expect(await api.schema('items')).toEqual({ from: 'network' });
    });

    it('is read off the shell element', () => {
        const parsed = parsePtahDocument(pageHtml(answers), 'http://localhost:3000/items');
        expect(parsed?.answers).toEqual(answers);
        // Never part of the page description (which goes into the history).
        expect(JSON.stringify(parsed?.page)).not.toContain('Alfa');

        expect(parsePtahDocument(pageHtml(null), 'http://localhost:3000/items')?.answers).toBeNull();

        const element = document.createElement('div');
        expect(embedsAnswers(element)).toBe(false);
        element.dataset.embed = '1';
        element.dataset.answers = '{broken';
        expect(embedsAnswers(element)).toBe(true);
        expect(readAnswers(element)).toBeNull();
    });
});

describe('moving to a screen whose page embeds its answers', () => {
    const start: PtahPage = { url: 'http://localhost:3000/home', page: 'dashboard', screen: '', title: 'Início', props: {}, logout: true, documentTitle: 'ERP' };

    beforeEach(() => {
        forgetSnapshots();
        setSnapshotScope(9, 1);
        window.history.replaceState(null, '', '/home');
        window.scrollTo = vi.fn() as unknown as typeof window.scrollTo;
    });
    afterEach(() => {
        vi.unstubAllGlobals();
        forgetSnapshots();
    });

    const world = (embedded: boolean) => {
        const calls: string[] = [];
        const fetch = vi.fn(async (input: RequestInfo | URL) => {
            const url = new URL(String(input), 'http://localhost:3000');
            calls.push(url.pathname + url.search);
            if (url.pathname === '/items') {
                const response = new Response(pageHtml(embedded ? answers : null), { status: 200, headers: { 'Content-Type': 'text/html' } });
                Object.defineProperty(response, 'url', { value: url.href });

                return response;
            }
            if (url.pathname === '/ptah/api/crud/items/schema') {
                return json(schema);
            }

            return json(listing);
        });
        vi.stubGlobal('fetch', fetch);

        return { calls, navigator: new PtahNavigator(start, { ...warmOptions('/ptah/api', ['items'], embedded), assign: vi.fn() }) };
    };

    it('is one request: the page', async () => {
        const { calls, navigator } = world(true);

        expect(await navigator.navigate('/items')).toBe(true);

        expect(calls).toEqual(['/items']);
        // The screen takes the warmed answer when it mounts: still no request.
        const warmed = await takeWarm('items');
        expect(warmed?.[0]).toEqual(schema);
        expect(warmed?.[1]).toEqual(listing);
        expect(calls).toEqual(['/items']);
    });

    it('asks beside the page, as before, when the server does not embed', async () => {
        const { calls, navigator } = world(false);

        expect(await navigator.navigate('/items')).toBe(true);

        expect([...calls].sort()).toEqual(['/items', '/ptah/api/crud/items/schema', '/ptah/api/crud/items?restore=1'].sort());
    });
});
