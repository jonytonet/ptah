import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { PtahLayout } from '../../react';
import { fakeApi } from '../fake-api';
import { adminLinks, fakeApiWithShell } from '../shell-extras';

afterEach(cleanup);

describe('<PtahLayout> administration links (React)', () => {
    it('lists in the account menu the links the server allowed, marking the current one', async () => {
        render(
            <PtahLayout api={fakeApiWithShell(adminLinks).api} locale="pt_BR" path="/ptah-roles">
                <p>conteúdo</p>
            </PtahLayout>,
        );

        fireEvent.click(await screen.findByRole('button', { name: 'Conta: Ana' }));

        expect(await screen.findByText('Administração')).toBeTruthy();
        const roles = screen.getByRole('link', { name: 'Perfis de Acesso' });
        expect(roles.getAttribute('href')).toBe('/ptah-roles');
        expect(roles.getAttribute('aria-current')).toBe('page');
        expect(screen.getByRole('link', { name: 'Gerenciar Menu' }).getAttribute('aria-current')).toBeNull();
    });

    it('shows no administration block to who has no link', async () => {
        render(
            <PtahLayout api={fakeApi().api} locale="pt_BR" path="/items">
                <p>conteúdo</p>
            </PtahLayout>,
        );

        fireEvent.click(await screen.findByRole('button', { name: 'Conta: Ana' }));

        expect(screen.queryByText('Administração')).toBeNull();
    });
});
