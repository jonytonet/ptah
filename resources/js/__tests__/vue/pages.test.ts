import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PtahApp, PtahDashboard, PtahForgotPassword, PtahLogin, PtahProfile, PtahResetPassword, PtahTwoFactor } from '../../vue';
import { PtahApi } from '../../core';
import { fakeAuth } from '../fake-auth';

afterEach(cleanup);

const type = (label: RegExp | string, value: string) => fireEvent.update(screen.getByLabelText(label), value);
const click = (name: string | RegExp, role = 'button') => fireEvent.click(screen.getByRole(role, { name }));

describe('sign-in pages (Vue)', () => {
    it('signs in and goes where the server says', async () => {
        const go = vi.fn();
        render(PtahLogin, { props: { api: fakeAuth().api, onNavigate: go } });

        await screen.findByText('ERP');
        await type(/E-mail/, 'ana@example.test');
        await type(/Senha/, 'secret-123');
        await click('Entrar');

        await waitFor(() => expect(go).toHaveBeenCalledWith('/dashboard'));
    });

    it('shows a refusal and clears the password; a field error stays on its field', async () => {
        const go = vi.fn();
        render(PtahLogin, { props: { api: fakeAuth().api, onNavigate: go } });
        await screen.findByText('ERP');

        await type(/E-mail/, 'ana@example.test');
        await type(/Senha/, 'errada');
        await click('Entrar');
        expect((await screen.findByRole('alert')).textContent).toContain('Credenciais inválidas.');
        await waitFor(() => expect((screen.getByLabelText(/Senha/) as HTMLInputElement).value).toBe(''));
        expect(go).not.toHaveBeenCalled();

        await type(/E-mail/, '');
        await click('Entrar');
        await waitFor(() => expect(screen.getByLabelText(/E-mail/).getAttribute('aria-invalid')).toBe('true'));
    });

    it('sends a two-factor user to the challenge, and the code signs in', async () => {
        const go = vi.fn();
        const view = render(PtahLogin, { props: { api: fakeAuth().api, onNavigate: go } });
        await screen.findByText('ERP');

        await type(/E-mail/, '2fa@example.test');
        await type(/Senha/, 'secret-123');
        await click('Entrar');
        await waitFor(() => expect(go).toHaveBeenCalledWith('/two-factor-challenge'));
        view.unmount();

        render(PtahTwoFactor, { props: { api: fakeAuth({ twoFactorPending: true }).api, onNavigate: go } });
        await screen.findByText('ERP');
        await type(/Código de verificação/, '000000');
        await click('Verificar');
        expect((await screen.findByRole('alert')).textContent).toContain('Código inválido.');

        await type(/Código de verificação/, '123456');
        await click('Verificar');
        await waitFor(() => expect(go).toHaveBeenLastCalledWith('/dashboard'));
    });

    it('a signed-in visitor leaves the login page; a challenge with nothing pending goes back', async () => {
        const go = vi.fn();
        render(PtahLogin, { props: { api: fakeAuth({ signedIn: true }).api, onNavigate: go } });
        await waitFor(() => expect(go).toHaveBeenCalledWith('/dashboard'));
        cleanup();

        const back = vi.fn();
        render(PtahTwoFactor, { props: { api: fakeAuth().api, onNavigate: back } });
        await waitFor(() => expect(back).toHaveBeenCalledWith('/login'));
    });

    it('forgot and reset password', async () => {
        const go = vi.fn();
        const first = render(PtahForgotPassword, { props: { api: fakeAuth().api } });
        await screen.findByText('ERP');
        await type(/E-mail/, 'ana@example.test');
        await click('Enviar link de recuperação');
        expect(await screen.findByText('Se o e-mail existir, enviamos o link.')).toBeTruthy();
        first.unmount();

        const fake = fakeAuth();
        render(PtahResetPassword, { props: { api: fake.api, token: 'tok', email: 'ana@example.test', onNavigate: go } });
        await screen.findByText('ERP');
        await type(/Nova senha/, 'nova-senha-1');
        await type(/Confirmar nova senha/, 'outra');
        await click('Redefinir senha');
        expect(await screen.findByText('A confirmação não confere.')).toBeTruthy();

        await type(/Confirmar nova senha/, 'nova-senha-1');
        await click('Redefinir senha');
        await waitFor(() => expect(go).toHaveBeenCalledWith('/login'));
        expect(fake.calls.find((call) => call.path === '/auth/reset-password')?.body).toMatchObject({ token: 'tok', email: 'ana@example.test' });
    });
});

describe('PtahProfile (Vue)', () => {
    const open = async () => {
        const fake = fakeAuth();
        render(PtahProfile, { props: { api: fake.api, locale: 'pt_BR' } });
        await screen.findByLabelText(/Nome/);

        return fake;
    };

    it('saves the name; a new e-mail asks for the current password (always shown, as on Livewire)', async () => {
        const fake = await open();

        await type(/Nome/, 'Ana Maria');
        await click('Salvar perfil');
        expect(await screen.findByText('Perfil atualizado.')).toBeTruthy();
        expect(fake.profile().name).toBe('Ana Maria');

        expect(screen.getByLabelText(/necessária para trocar o e-mail/)).toBeTruthy();
        await type(/E-mail/, 'nova@example.test');
        await type(/Senha atual/, 'errada');
        await click('Salvar perfil');
        expect(await screen.findByText('Senha atual incorreta.')).toBeTruthy();
        expect(fake.profile().email).toBe('ana@example.test');
    });

    it('sets up two-factor: password, QR, code', async () => {
        const fake = await open();
        await click('Autenticação 2FA', 'tab');

        await fireEvent.click(await screen.findByRole('button', { name: 'Configurar' }));
        expect(await screen.findByText('Senha atual incorreta.')).toBeTruthy();

        const passwords = screen.getAllByLabelText(/Senha atual/);
        await fireEvent.update(passwords[passwords.length - 1], 'secret-123');
        await click('Configurar');
        expect(await screen.findByText('ABCDEF123456')).toBeTruthy();
        
        await type(/Código de verificação/, '123456');
        await click('Confirmar');
        await waitFor(() => expect(fake.profile().two_factor.enabled).toBe(true));
        expect(await screen.findByRole('button', { name: 'Desativar 2FA' })).toBeTruthy();
        // The recovery codes of the new setup, in the active block, as on Livewire.
        expect(screen.getByText('aaaa-1111')).toBeTruthy();
    });

    it('lists sessions and ends another one; changes the appearance', async () => {
        const fake = await open();

        await click('Sessões', 'tab');
        expect(await screen.findByText(/Firefox/)).toBeTruthy();
        await click('Revogar');
        await waitFor(() => expect(screen.queryByText(/Firefox/)).toBeNull());
        expect(screen.getByText(/esta sessão/)).toBeTruthy();

        await click('Aparência', 'tab');
        await fireEvent.click(await screen.findByRole('button', { name: 'verde' }));
        await waitFor(() => expect(fake.profile().appearance.accent).toBe('verde'));
        await waitFor(() => expect(screen.getByRole('button', { name: 'verde' }).getAttribute('aria-pressed')).toBe('true'));
    });
});

describe('dashboard and page router (Vue)', () => {
    it('draws the four kinds of widget and a widget that failed', async () => {
        render(PtahDashboard, { props: { api: fakeAuth().api } });

        expect(await screen.findByRole('heading', { name: 'Dashboard' })).toBeTruthy();
        expect(await screen.findByText('42')).toBeTruthy();
        expect(screen.getByText('Pedidos hoje').closest('a')?.getAttribute('href')).toBe('/orders');
        expect(screen.getByRole('img', { name: /Vendas: Seg R\$ 400,00, Ter R\$ 500,00/ })).toBeTruthy();
        expect(screen.getByText('Loja')).toBeTruthy();
        expect(screen.getByText('#2')).toBeTruthy();
        expect(screen.getByRole('alert').textContent).toBe('Não foi possível calcular.');
    });

    it('without widgets draws the four cards of the Livewire dashboard', async () => {
        const api = new PtahApi({
            fetch: (async (input: RequestInfo | URL, init?: RequestInit) => {
                if (String(input).includes('/dashboard')) {
                    return new Response(JSON.stringify({ data: [], fallback: { app: 'ERP', environment: 'Local', laravel: '12.0.0' } }), { status: 200, headers: { 'Content-Type': 'application/json' } });
                }

                return fakeAuth().api['doFetch'](input, init);
            }) as typeof fetch,
        });
        render(PtahDashboard, { props: { api, locale: 'pt_BR' } });

        expect(await screen.findByText('Visão geral do sistema')).toBeTruthy();
        expect(await screen.findByText('Ambiente')).toBeTruthy();
        expect(screen.getByText('Local')).toBeTruthy();
        expect(screen.getByText('12.0.0')).toBeTruthy();
        expect(await screen.findByText('Ana')).toBeTruthy();
    });

    it('PtahApp draws the page the server named', async () => {
        vi.stubGlobal('fetch', (input: RequestInfo | URL, init?: RequestInit) => fakeAuth().api['doFetch'](input, init));

        const login = render(PtahApp, { props: { page: 'login', locale: 'pt_BR' } });
        expect(await screen.findByRole('button', { name: 'Entrar' })).toBeTruthy();
        expect(screen.queryByRole('navigation')).toBeNull();
        login.unmount();

        render(PtahApp, { props: { page: 'dashboard', locale: 'pt_BR', brand: 'ERP', canLogout: true, profileUrl: '/profile' } });
        expect(await screen.findByRole('heading', { name: 'Dashboard' })).toBeTruthy();
        expect(await screen.findByRole('link', { name: 'Itens' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Sair' })).toBeTruthy();

        // Profile and log out live in the account menu.
        await fireEvent.click(await screen.findByRole('button', { name: 'Conta: Ana' }));
        expect(screen.getByRole('link', { name: 'Perfil' }).getAttribute('href')).toBe('/profile');
        expect(screen.getAllByRole('button', { name: 'Sair' })).toHaveLength(2);

        vi.unstubAllGlobals();
    });
});
