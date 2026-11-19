import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { adminEntries, applyAppearance, firstLetter, iconOnlyOf, isActive, pathIs, searchMenu } from '../../core';
import { adminIcons } from '../../core/icons';
import { PtahLayout } from '../../react';
import { fakeApi } from '../fake-api';

beforeEach(() => {
    localStorage.clear();
    document.documentElement.className = '';
});
afterEach(cleanup);

const mount = async (props: Record<string, unknown> = {}) => {
    const fake = fakeApi();
    const view = render(
        <PtahLayout api={fake.api} locale="pt_BR" path="/items" {...props}>
            <p>conteúdo</p>
        </PtahLayout>,
    );
    await screen.findByRole('link', { name: 'Itens' });
    const shell = view.container.querySelector('.pl-shell') as HTMLElement;
    const sidebar = view.container.querySelector('.pl-sidebar') as HTMLElement;

    return { ...fake, shell, sidebar };
};

describe('layout rules (core)', () => {
    it('matches paths the way request()->is() does', () => {
        expect(pathIs('products*', '/products/12/edit')).toBe(true);
        expect(pathIs('products', '/products/12')).toBe(false);
        expect(isActive({ label: 'x', url: '/products' }, '/products/12')).toBe(true);
        expect(isActive({ label: 'x', url: '/products' }, '/productsx')).toBe(false);
        expect(isActive({ label: 'x', url: '#' }, '/')).toBe(false);
    });

    it('searches the menu word by word, without accents', () => {
        const links = [
            { label: 'Cotação', url: '/c', icon: '', target: '_self', breadcrumb: ['Compras', 'Cotação'], path: '', search: 'compras cotacao' },
            { label: 'Clientes', url: '/k', icon: '', target: '_self', breadcrumb: ['Clientes'], path: '', search: 'clientes' },
        ];

        expect(searchMenu(links, 'cotação compras').map((link) => link.label)).toEqual(['Cotação']);
        expect(searchMenu(links, '   ')).toEqual([]);
    });

    it('is a rail when forge-sidebar is: a tablet, or a collapsed desktop, unless hovered or opened', () => {
        const base = { hovered: false, peek: false, open: false, collapsed: false, md: true, lg: true };
        expect(iconOnlyOf(base)).toBe(false);
        expect(iconOnlyOf({ ...base, collapsed: true })).toBe(true);
        expect(iconOnlyOf({ ...base, collapsed: true, hovered: true })).toBe(false);
        expect(iconOnlyOf({ ...base, lg: false })).toBe(true);
        expect(iconOnlyOf({ ...base, lg: false, peek: true })).toBe(false);
        expect(iconOnlyOf({ ...base, lg: false, open: true })).toBe(false);
        // A phone: off-canvas, never a rail.
        expect(iconOnlyOf({ ...base, lg: false, md: false, collapsed: true })).toBe(false);
    });

    it('orders the administration links with the navbar rules and icons', () => {
        const entries = adminEntries(
            [
                { label: 'Empresa', url: '/c', icon: 'bx bx-buildings' },
                { label: 'Menu', url: '/m', icon: 'bx bx-menu' },
                { label: 'IA', url: '/ai', icon: 'bx bx-bot' },
            ],
            adminIcons,
        );
        expect(entries.map((entry) => [entry.icon, entry.rule])).toEqual([
            ['office', false],
            ['menuLeft', true],
            [null, true],
        ]);
        expect(adminEntries([{ label: 'IA', url: '/ai', icon: 'bx bx-bot' }], adminIcons)[0].rule).toBe(false);
        expect(firstLetter('ana souza')).toBe('A');
    });

    it('puts the appearance presets on <html>', () => {
        applyAppearance({ accent: 'verde', density: 'compacta' });

        expect(document.documentElement.getAttribute('data-ptah-accent')).toBe('verde');
        expect(document.documentElement.getAttribute('data-ptah-density')).toBe('compacta');
    });
});

describe('<PtahLayout> (React)', () => {
    it('draws the server menu: active item, hidden item, groups', async () => {
        await mount();

        expect(screen.getByRole('link', { name: 'Itens' }).getAttribute('aria-current')).toBe('page');
        expect(screen.queryByRole('link', { name: 'Oculto' })).toBeNull();
        expect(screen.queryByRole('link', { name: 'Clientes' })).toBeNull();

        fireEvent.click(screen.getByRole('button', { name: 'Cadastros' }));
        expect(screen.getByRole('link', { name: 'Clientes' })).toBeTruthy();
    });

    it('opens the group that holds the current page', async () => {
        await mount({ path: '/clientes' });

        expect(screen.getByRole('link', { name: 'Clientes' }).getAttribute('aria-current')).toBe('page');
        expect(screen.getByRole('button', { name: 'Cadastros' }).getAttribute('aria-expanded')).toBe('true');
    });

    it('collapses to a rail, remembers it, and Ctrl+B toggles it', async () => {
        const { shell } = await mount();
        expect(shell.dataset.collapsed).toBe('false');

        fireEvent.click(screen.getByRole('button', { name: 'Recolher o menu' }));
        expect(shell.dataset.collapsed).toBe('true');
        expect(shell.dataset.iconOnly).toBe('true');
        expect(localStorage.getItem('ptah_sidebar_collapsed')).toBe('true');

        fireEvent.keyDown(document, { key: 'b', ctrlKey: true });
        expect(shell.dataset.collapsed).toBe('false');
        expect(localStorage.getItem('ptah_sidebar_collapsed')).toBe('false');
    });

    it('starts collapsed when the browser remembers it, and opens by itself on hover', async () => {
        localStorage.setItem('ptah_sidebar_collapsed', 'true');
        const { shell, sidebar } = await mount();
        expect(shell.dataset.iconOnly).toBe('true');

        fireEvent.mouseEnter(sidebar);
        expect(shell.dataset.iconOnly).toBe('false');
        expect(sidebar.dataset.hover).toBe('true');
        // Still collapsed: leaving puts the rail back.
        expect(shell.dataset.collapsed).toBe('true');

        fireEvent.mouseLeave(sidebar);
        expect(shell.dataset.iconOnly).toBe('true');
    });

    it('a group clicked on the rail expands the sidebar and opens it', async () => {
        localStorage.setItem('ptah_sidebar_collapsed', 'true');
        const { shell } = await mount();

        fireEvent.click(screen.getByRole('button', { name: 'Cadastros' }));

        expect(shell.dataset.collapsed).toBe('false');
        expect(screen.getByRole('link', { name: 'Clientes' })).toBeTruthy();
    });

    it('"go to a screen": finds without accents, arrows and Enter go, Escape clears', async () => {
        const go = vi.fn();
        await mount({ onNavigate: go });
        const input = screen.getByRole('combobox', { name: 'Ir para uma tela' });

        fireEvent.change(input, { target: { value: 'cotacao' } });
        expect(screen.getByRole('option').textContent).toContain('Cotação');
        expect(screen.getByRole('option').textContent).toContain('Cadastros');

        fireEvent.keyDown(input, { key: 'ArrowDown' });
        fireEvent.keyDown(input, { key: 'Enter' });
        expect(go).toHaveBeenCalledWith('/compras/cotacao');
        expect((input as HTMLInputElement).value).toBe('');

        fireEvent.change(input, { target: { value: 'zzz' } });
        expect(screen.getByText('Nenhuma tela corresponde.')).toBeTruthy();
        fireEvent.keyDown(input, { key: 'Escape' });
        expect(screen.queryByText('Nenhuma tela corresponde.')).toBeNull();
    });

    it('the magnifier of the rail expands the sidebar', async () => {
        localStorage.setItem('ptah_sidebar_collapsed', 'true');
        const { shell } = await mount();

        fireEvent.click(screen.getByRole('button', { name: 'Ir para uma tela' }));
        expect(shell.dataset.collapsed).toBe('false');
    });

    it('switches light/dark on <html>, remembers it and tells the server', async () => {
        const fetchMock = vi.fn(() => Promise.resolve(new Response(null, { status: 204 })));
        vi.stubGlobal('fetch', fetchMock);
        await mount({ themeModeUrl: '/appearance/theme-mode' });

        fireEvent.click(screen.getByRole('button', { name: 'Ativar modo escuro' }));
        expect(document.documentElement.classList.contains('ptah-dark')).toBe(true);
        expect(localStorage.getItem('ptah_dark_mode')).toBe('true');
        expect(fetchMock).toHaveBeenCalledWith('/appearance/theme-mode', expect.objectContaining({ method: 'POST', body: JSON.stringify({ mode: 'dark' }) }));

        fireEvent.click(await screen.findByRole('button', { name: 'Ativar modo claro' }));
        expect(document.documentElement.classList.contains('ptah-dark')).toBe(false);
        vi.unstubAllGlobals();
    });

    it('logging out forgets the form drafts of this browser', async () => {
        localStorage.setItem('ptah:draft:9:abc:new', '{}');
        localStorage.setItem('other', 'kept');
        const onLogout = vi.fn();
        await mount({ onLogout });

        fireEvent.click(screen.getByRole('button', { name: 'Sair' }));

        expect(onLogout).toHaveBeenCalled();
        expect(localStorage.getItem('ptah:draft:9:abc:new')).toBeNull();
        expect(localStorage.getItem('other')).toBe('kept');
    });

    it('the mobile button opens the menu over the page, the overlay closes it', async () => {
        const { shell } = await mount();

        fireEvent.click(screen.getByRole('button', { name: 'Abrir o menu' }));
        expect(shell.dataset.open).toBe('true');

        fireEvent.click(screen.getAllByRole('button', { name: 'Fechar o menu' })[0]);
        await waitFor(() => expect(shell.dataset.open).toBe('false'));
    });
});
