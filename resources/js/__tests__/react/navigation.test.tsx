import { act, cleanup, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { PtahCrud, mountPtah } from '../../react';
import { navigateTo, PtahApi, readSnapshot, setSnapshotScope, writeSnapshot } from '../../core';
import type { Snapshot } from '../../core';
import { fakeApi, schema } from '../fake-api';
import { clickLink, navigationWorld } from '../navigation.fake';

const pages = {
    '/items': { page: 'crud', screen: 'items', title: 'Itens' },
    '/reports': { page: 'reports', title: 'Relatórios' },
    '/login': { page: 'login' },
};

let world: ReturnType<typeof navigationWorld>;
let unmount: () => void = () => undefined;

const mount = async (extra: Record<string, unknown> = {}) => {
    await act(async () => {
        unmount = mountPtah(
            world.element,
            {
                pages: {
                    reports: (
                        <div>
                            <h1>Relatórios aqui</h1>
                            <a href="/plain">Página do host</a>
                            <a href="/items" data-ptah-reload>
                                Recarregar itens
                            </a>
                            <a href="/login">Entrar de novo</a>
                        </div>
                    ),
                },
                ...extra,
            },
            { fetch: world.navFetch as unknown as typeof fetch, assign: world.assign },
        );
    });
};

beforeEach(() => {
    sessionStorage.clear();
    window.history.replaceState(null, '', '/items');
    world = navigationWorld(pages);
});
afterEach(() => {
    act(() => unmount());
    world.cleanup();
    setSnapshotScope(null);
    cleanup();
});

describe('navigation between pages (React)', () => {
    it('paints the menu from the boot data, with no request to /menu or /me', async () => {
        await mount();

        expect(screen.getByRole('link', { name: 'Itens' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Conta: Ana' })).toBeTruthy();
        expect(await screen.findByText('Alfa')).toBeTruthy();
        expect(world.apiCalls('/menu')).toBe(0);
        expect(world.apiCalls('/me')).toBe(0);
    });

    it('a sidebar link swaps the page and keeps the layout mounted; back comes back without a fetch', async () => {
        await mount();
        await screen.findByText('Alfa');
        const sidebar = world.element.querySelector('.pl-sidebar');

        expect(clickLink(screen.getByRole('link', { name: 'Relatórios' }))).toBe(false);

        expect(await screen.findByText('Relatórios aqui')).toBeTruthy();
        expect(window.location.pathname).toBe('/reports');
        expect(document.title).toBe('Relatórios — ERP');
        expect(world.element.querySelector('.pl-sidebar')).toBe(sidebar);
        expect(screen.getByRole('link', { name: 'Relatórios' }).getAttribute('aria-current')).toBe('page');
        expect(screen.queryByText('Alfa')).toBeNull();
        expect(world.assign).not.toHaveBeenCalled();
        // The CSRF token of the answer replaces the old one.
        expect(document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')).toBe('token-2');
        expect(world.documents).toEqual(['/reports']);

        await act(async () => {
            window.history.back();
        });
        await waitFor(() => expect(window.location.pathname).toBe('/items'));
        expect(await screen.findByText('Alfa')).toBeTruthy();
        expect(world.element.querySelector('.pl-sidebar')).toBe(sidebar);
        expect(world.documents).toEqual(['/reports']);

        await act(async () => {
            window.history.forward();
        });
        expect(await screen.findByText('Relatórios aqui')).toBeTruthy();
        expect(world.documents).toEqual(['/reports']);
    });

    it('a CRUD screen arrives with its rows: the old page stays until then, no skeleton, one request', async () => {
        world.startAt('/reports');
        await mount();
        await screen.findByText('Relatórios aqui');

        clickLink(screen.getByRole('link', { name: 'Itens' }));
        await waitFor(() => expect(screen.queryByText('Relatórios aqui')).toBeNull());

        // The very frame the page swapped: the rows are there.
        expect(screen.getByText('Alfa')).toBeTruthy();
        expect(screen.queryByRole('status', { name: 'Carregando…' })).toBeNull();
        await waitFor(() => expect(world.element.querySelector('.pc-crud')?.getAttribute('aria-busy')).not.toBe('true'));
        // The schema asked on the way is the one the screen used.
        expect(world.apiCalls('/crud/items/schema')).toBe(1);
    });

    it('row links and programmatic navigations go through the navigator', async () => {
        await mount();
        await screen.findByText('Alfa');

        await act(async () => navigateTo('/reports'));

        expect(await screen.findByText('Relatórios aqui')).toBeTruthy();
        expect(world.assign).not.toHaveBeenCalled();
    });

    it('falls back to a full load for a page that is not a stack page, or the other kind', async () => {
        world.startAt('/reports');
        await mount();
        await screen.findByText('Relatórios aqui');

        expect(clickLink(screen.getByRole('link', { name: 'Página do host' }))).toBe(false);
        await waitFor(() => expect(world.assign).toHaveBeenCalledWith('http://localhost:3000/plain'));
        expect(window.location.pathname).toBe('/reports');

        clickLink(screen.getByRole('link', { name: 'Entrar de novo' }));
        await waitFor(() => expect(world.assign).toHaveBeenCalledWith('http://localhost:3000/login'));
        expect(screen.getByText('Relatórios aqui')).toBeTruthy();
    });

    it('leaves alone an opted-out link and a modifier click', async () => {
        world.startAt('/reports');
        await mount();
        await screen.findByText('Relatórios aqui');

        expect(clickLink(screen.getByRole('link', { name: 'Recarregar itens' }))).toBe(true);
        expect(clickLink(screen.getByRole('link', { name: 'Página do host' }), { ctrlKey: true })).toBe(true);
        expect(clickLink(screen.getByRole('link', { name: 'Itens' }), { metaKey: true })).toBe(true);
        expect(world.navFetch).not.toHaveBeenCalled();
        expect(world.assign).not.toHaveBeenCalled();
    });

    it('a router of the host owns navigation: nothing is intercepted', async () => {
        const go = vi.fn();
        await mount({ onNavigate: go });
        await screen.findByText('Alfa');

        // The sidebar asks the host's router.
        expect(clickLink(screen.getByRole('link', { name: 'Relatórios' }))).toBe(false);
        expect(go).toHaveBeenCalledWith('/reports');
        expect(world.navFetch).not.toHaveBeenCalled();
    });
});

describe('instant first paint (React)', () => {
    const kept = (): Snapshot => ({
        schema,
        preferences: schema.preferences,
        rows: [{ id: 99, cells: { id: { value: 99, html: '99' }, name: { value: 'Zeta', html: 'Zeta' }, status: { value: 'active', html: 'Ativo' } }, style: null, actions: [], trashed: false }],
        meta: { page: 1, per_page: 25, total: 1, last_page: 1, sort: 'id', direction: 'DESC', search: '', trashed: false },
        totals: [],
        breaks: [],
        list: { page: 1, per_page: 25, sort: 'id', direction: 'DESC', search: '', filters: {}, operators: {}, date_ranges: {}, date_operators: {}, quick_date: '', trashed: false },
    });

    it('draws the kept listing before the request answers, then replaces it', async () => {
        setSnapshotScope(9, 1);
        writeSnapshot('items', kept());
        const fake = fakeApi();
        let release: () => void = () => undefined;
        const gate = new Promise<void>((resolve) => {
            release = resolve;
        });
        const doFetch = (fake.api as unknown as { doFetch: typeof fetch }).doFetch;
        const api = new PtahApi({ fetch: (async (input: RequestInfo | URL, init?: RequestInit) => {
            await gate;

            return doFetch(input, init);
        }) as typeof fetch });

        render(<PtahCrud screen="items" api={api} locale="pt_BR" instant />);

        expect(screen.getByText('Zeta')).toBeTruthy();
        expect(screen.queryByText('Carregando…')).toBeNull();

        await act(async () => release());
        expect(await screen.findByText('Alfa')).toBeTruthy();
        expect(screen.queryByText('Zeta')).toBeNull();
        expect(readSnapshot('items')?.rows.map((row) => row.id)).toEqual([3, 2, 1]);
    });

    it('a screen the server now refuses drops the kept rows and shows the refusal', async () => {
        setSnapshotScope(9, 1);
        writeSnapshot('closed', kept());

        render(<PtahCrud screen="closed" api={fakeApi().api} locale="pt_BR" instant />);
        expect(screen.getByText('Zeta')).toBeTruthy();

        expect(await screen.findByText('Você não tem permissão para ver esta tela.')).toBeTruthy();
        expect(screen.queryByText('Zeta')).toBeNull();
        expect(readSnapshot('closed')).toBeNull();
    });

    it('a first visit draws the shape of the screen, not a bare text', async () => {
        const view = render(<PtahCrud screen="items" api={fakeApi().api} locale="pt_BR" instant />);

        expect(screen.getByRole('status', { name: 'Carregando…' }).className).toContain('pc-skeleton');
        expect(view.container.querySelectorAll('.pc-skeleton-row').length).toBeGreaterThan(3);
        expect(await screen.findByText('Alfa')).toBeTruthy();
        expect(view.container.querySelector('.pc-skeleton')).toBeNull();
    });

    it('without instant nothing is read nor kept', async () => {
        setSnapshotScope(9, 1);
        writeSnapshot('items', kept());

        render(<PtahCrud screen="items" api={fakeApi().api} locale="pt_BR" />);
        expect(screen.queryByText('Zeta')).toBeNull();
        expect(await screen.findByText('Alfa')).toBeTruthy();
        expect(readSnapshot('items')?.rows[0].id).toBe(99);
    });
});
