import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PtahLayout from '../../vue/PtahLayout.vue';
import { fakeApi } from '../fake-api';

beforeEach(() => {
    localStorage.clear();
    document.documentElement.className = '';
});
afterEach(cleanup);

const mount = async (props: Record<string, unknown> = {}) => {
    const fake = fakeApi();
    const view = render(PtahLayout, { props: { api: fake.api, locale: 'pt_BR', path: '/items', ...props }, slots: { default: '<p>conteúdo</p>' } });
    await screen.findByRole('link', { name: 'Itens' });
    const shell = view.container.querySelector('.pl-shell') as HTMLElement;
    const sidebar = view.container.querySelector('.pl-sidebar') as HTMLElement;

    return { ...fake, shell, sidebar };
};

const click = (name: string | RegExp, role = 'button') => fireEvent.click(screen.getByRole(role, { name }));

describe('PtahLayout (Vue)', () => {
    it('draws the server menu: active item, hidden item, groups', async () => {
        await mount();

        expect(screen.getByRole('link', { name: 'Itens' }).getAttribute('aria-current')).toBe('page');
        expect(screen.queryByRole('link', { name: 'Oculto' })).toBeNull();
        expect(screen.queryByRole('link', { name: 'Clientes' })).toBeNull();

        await click('Cadastros');
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

        await click('Recolher o menu');
        expect(shell.dataset.collapsed).toBe('true');
        expect(shell.dataset.iconOnly).toBe('true');
        expect(localStorage.getItem('ptah_sidebar_collapsed')).toBe('true');

        await fireEvent.keyDown(document, { key: 'b', ctrlKey: true });
        expect(shell.dataset.collapsed).toBe('false');
        expect(localStorage.getItem('ptah_sidebar_collapsed')).toBe('false');
    });

    it('starts collapsed when the browser remembers it, and opens by itself on hover', async () => {
        localStorage.setItem('ptah_sidebar_collapsed', 'true');
        const { shell, sidebar } = await mount();
        expect(shell.dataset.iconOnly).toBe('true');

        await fireEvent.mouseEnter(sidebar);
        expect(shell.dataset.iconOnly).toBe('false');
        expect(sidebar.dataset.hover).toBe('true');
        // Still collapsed: leaving puts the rail back.
        expect(shell.dataset.collapsed).toBe('true');

        await fireEvent.mouseLeave(sidebar);
        expect(shell.dataset.iconOnly).toBe('true');
    });

    it('a group clicked on the rail expands the sidebar and opens it', async () => {
        localStorage.setItem('ptah_sidebar_collapsed', 'true');
        const { shell } = await mount();

        await click('Cadastros');

        expect(shell.dataset.collapsed).toBe('false');
        expect(screen.getByRole('link', { name: 'Clientes' })).toBeTruthy();
    });

    it('"go to a screen": finds without accents, arrows and Enter go, Escape clears', async () => {
        const go = vi.fn();
        await mount({ onNavigate: go });
        const input = screen.getByRole('combobox', { name: 'Ir para uma tela' }) as HTMLInputElement;

        await fireEvent.update(input, 'cotacao');
        expect(screen.getByRole('option').textContent).toContain('Cotação');
        expect(screen.getByRole('option').textContent).toContain('Cadastros');

        await fireEvent.keyDown(input, { key: 'ArrowDown' });
        await fireEvent.keyDown(input, { key: 'Enter' });
        expect(go).toHaveBeenCalledWith('/compras/cotacao');
        await waitFor(() => expect(input.value).toBe(''));

        await fireEvent.update(input, 'zzz');
        expect(screen.getByText('Nenhuma tela corresponde.')).toBeTruthy();
        await fireEvent.keyDown(input, { key: 'Escape' });
        expect(screen.queryByText('Nenhuma tela corresponde.')).toBeNull();
    });

    it('the magnifier of the rail expands the sidebar', async () => {
        localStorage.setItem('ptah_sidebar_collapsed', 'true');
        const { shell } = await mount();

        await click('Ir para uma tela');
        expect(shell.dataset.collapsed).toBe('false');
    });

    it('switches light/dark on <html>, remembers it and tells the server', async () => {
        const fetchMock = vi.fn(() => Promise.resolve(new Response(null, { status: 204 })));
        vi.stubGlobal('fetch', fetchMock);
        await mount({ themeModeUrl: '/appearance/theme-mode' });

        await click('Ativar modo escuro');
        expect(document.documentElement.classList.contains('ptah-dark')).toBe(true);
        expect(localStorage.getItem('ptah_dark_mode')).toBe('true');
        expect(fetchMock).toHaveBeenCalledWith('/appearance/theme-mode', expect.objectContaining({ method: 'POST', body: JSON.stringify({ mode: 'dark' }) }));

        await fireEvent.click(await screen.findByRole('button', { name: 'Ativar modo claro' }));
        expect(document.documentElement.classList.contains('ptah-dark')).toBe(false);
        vi.unstubAllGlobals();
    });

    it('logging out forgets the form drafts of this browser', async () => {
        localStorage.setItem('ptah:draft:9:abc:new', '{}');
        localStorage.setItem('other', 'kept');
        const onLogout = vi.fn();
        await mount({ onLogout });

        await click('Sair');

        expect(onLogout).toHaveBeenCalled();
        expect(localStorage.getItem('ptah:draft:9:abc:new')).toBeNull();
        expect(localStorage.getItem('other')).toBe('kept');
    });

    it('the mobile button opens the menu over the page, the overlay closes it', async () => {
        const { shell } = await mount();

        await click('Abrir o menu');
        expect(shell.dataset.open).toBe('true');

        await fireEvent.click(screen.getAllByRole('button', { name: 'Fechar o menu' })[0]);
        await waitFor(() => expect(shell.dataset.open).toBe('false'));
    });
});
