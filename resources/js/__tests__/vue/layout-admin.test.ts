import { cleanup, fireEvent, render, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vitest';
import type { PtahApi } from '../../core';
import PtahLayout from '../../vue/PtahLayout.vue';
import { fakeApi } from '../fake-api';
import { adminLinks, fakeApiWithShell } from '../shell-extras';

afterEach(cleanup);

const mount = (api: PtahApi, path: string) => render(PtahLayout, { props: { api, locale: 'pt_BR', path }, slots: { default: '<p>conteúdo</p>' } });

describe('PtahLayout administration links (Vue)', () => {
    it('lists in the account menu the links the server allowed, marking the current one', async () => {
        mount(fakeApiWithShell(adminLinks).api, '/ptah-roles');

        await fireEvent.click(await screen.findByRole('button', { name: 'Conta: Ana' }));

        expect(await screen.findByText('Administração')).toBeTruthy();
        const roles = screen.getByRole('link', { name: 'Perfis de Acesso' });
        expect(roles.getAttribute('href')).toBe('/ptah-roles');
        expect(roles.getAttribute('aria-current')).toBe('page');
        expect(screen.getByRole('link', { name: 'Gerenciar Menu' }).getAttribute('aria-current')).toBeNull();
    });

    it('shows no administration block to who has no link', async () => {
        mount(fakeApi().api, '/items');

        await fireEvent.click(await screen.findByRole('button', { name: 'Conta: Ana' }));

        expect(screen.queryByText('Administração')).toBeNull();
    });
});
