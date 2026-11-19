import { cleanup, fireEvent, render, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vitest';
import type { PtahApi } from '../../core';
import PtahLayout from '../../vue/PtahLayout.vue';
import { fakeApi } from '../fake-api';
import { adminLinks, fakeApiWithShell } from '../shell-extras';

afterEach(cleanup);

const mount = (api: PtahApi, path: string) => render(PtahLayout, { props: { api, locale: 'pt_BR', path }, slots: { default: '<p>conteúdo</p>' } });

describe('PtahLayout administration menu (Vue)', () => {
    // forge-navbar: the links live in the gear "Administração", not in the account menu.
    it('lists under the gear the links the server allowed, marking the current one', async () => {
        const view = mount(fakeApiWithShell(adminLinks).api, '/ptah-roles');

        const gear = await screen.findByRole('button', { name: 'Administração' });
        expect(gear.getAttribute('title')).toBe('Administração');
        await fireEvent.click(gear);
        expect(gear.getAttribute('aria-expanded')).toBe('true');

        const roles = screen.getByRole('link', { name: 'Perfis de Acesso' });
        expect(roles.getAttribute('href')).toBe('/ptah-roles');
        expect(roles.getAttribute('aria-current')).toBe('page');
        expect(screen.getByRole('link', { name: 'Gerenciar Menu' }).getAttribute('aria-current')).toBeNull();
        // The navbar's rule before "Gerenciar Menu", and each link with its Heroicon.
        expect(view.container.querySelectorAll('.pl-dropdown--admin hr')).toHaveLength(1);
        expect(roles.querySelector('svg')).toBeTruthy();

        // Not in the account menu any more.
        await fireEvent.click(screen.getByRole('button', { name: 'Conta: Ana' }));
        expect(view.container.querySelector('.pl-dropdown--account')?.textContent).not.toContain('Perfis de Acesso');
    });

    it('shows no gear to who has no link and no company module', async () => {
        mount(fakeApi().api, '/items');

        await screen.findByRole('button', { name: 'Conta: Ana' });
        expect(screen.queryByRole('button', { name: 'Administração' })).toBeNull();
    });

    it('draws the plain bell of the navbar when the server asks for it', async () => {
        mount(fakeApiWithShell({ ...adminLinks, static_bell: true }).api, '/items');

        expect(await screen.findByRole('button', { name: 'Notificações' })).toBeTruthy();
    });
});
