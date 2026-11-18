import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PtahApp, PtahDashboard, PtahForgotPassword, PtahLogin, PtahProfile, PtahResetPassword, PtahTwoFactor } from '../../react';
import { fakeAuth } from '../fake-auth';

afterEach(cleanup);

const type = (label: RegExp | string, value: string) => fireEvent.change(screen.getByLabelText(label), { target: { value } });

describe('sign-in pages (React)', () => {
    it('signs in and goes where the server says', async () => {
        const { api } = fakeAuth();
        const go = vi.fn();
        render(<PtahLogin api={api} onNavigate={go} />);

        await screen.findByText('ERP');
        type(/E-mail/, 'ana@example.test');
        type(/Senha/, 'secret-123');
        fireEvent.click(screen.getByRole('button', { name: 'Entrar' }));

        await waitFor(() => expect(go).toHaveBeenCalledWith('/dashboard'));
    });

    it('shows a refusal and clears the password; a field error stays on its field', async () => {
        const { api } = fakeAuth();
        const go = vi.fn();
        render(<PtahLogin api={api} onNavigate={go} />);
        await screen.findByText('ERP');

        type(/E-mail/, 'ana@example.test');
        type(/Senha/, 'errada');
        fireEvent.click(screen.getByRole('button', { name: 'Entrar' }));
        expect((await screen.findByRole('alert')).textContent).toContain('Credenciais inválidas.');
        expect((screen.getByLabelText(/Senha/) as HTMLInputElement).value).toBe('');
        expect(go).not.toHaveBeenCalled();

        type(/E-mail/, '');
        fireEvent.click(screen.getByRole('button', { name: 'Entrar' }));
        await waitFor(() => expect(screen.getByLabelText(/E-mail/).getAttribute('aria-invalid')).toBe('true'));
    });

    it('sends a two-factor user to the challenge, and the code signs in', async () => {
        const { api } = fakeAuth();
        const go = vi.fn();
        const view = render(<PtahLogin api={api} onNavigate={go} />);
        await screen.findByText('ERP');

        type(/E-mail/, '2fa@example.test');
        type(/Senha/, 'secret-123');
        fireEvent.click(screen.getByRole('button', { name: 'Entrar' }));
        await waitFor(() => expect(go).toHaveBeenCalledWith('/two-factor-challenge'));
        view.unmount();

        render(<PtahTwoFactor api={fakeAuth({ twoFactorPending: true }).api} onNavigate={go} />);
        await screen.findByText('ERP');
        type(/Código de verificação/, '000000');
        fireEvent.click(screen.getByRole('button', { name: 'Verificar' }));
        expect((await screen.findByRole('alert')).textContent).toContain('Código inválido.');

        type(/Código de verificação/, '123456');
        fireEvent.click(screen.getByRole('button', { name: 'Verificar' }));
        await waitFor(() => expect(go).toHaveBeenLastCalledWith('/dashboard'));
    });

    it('a signed-in visitor leaves the login page; a challenge with nothing pending goes back', async () => {
        const go = vi.fn();
        render(<PtahLogin api={fakeAuth({ signedIn: true }).api} onNavigate={go} />);
        await waitFor(() => expect(go).toHaveBeenCalledWith('/dashboard'));
        cleanup();

        const back = vi.fn();
        render(<PtahTwoFactor api={fakeAuth().api} onNavigate={back} />);
        await waitFor(() => expect(back).toHaveBeenCalledWith('/login'));
    });

    it('forgot and reset password', async () => {
        const go = vi.fn();
        const first = render(<PtahForgotPassword api={fakeAuth().api} />);
        await screen.findByText('ERP');
        type(/E-mail/, 'ana@example.test');
        fireEvent.click(screen.getByRole('button', { name: 'Enviar link de redefinição' }));
        expect(await screen.findByText('Se o e-mail existir, enviamos o link.')).toBeTruthy();
        first.unmount();

        const fake = fakeAuth();
        render(<PtahResetPassword api={fake.api} token="tok" email="ana@example.test" onNavigate={go} />);
        await screen.findByText('ERP');
        type(/Nova senha/, 'nova-senha-1');
        type(/Confirmar senha/, 'outra');
        fireEvent.click(screen.getByRole('button', { name: 'Redefinir senha' }));
        expect(await screen.findByText('A confirmação não confere.')).toBeTruthy();

        type(/Confirmar senha/, 'nova-senha-1');
        fireEvent.click(screen.getByRole('button', { name: 'Redefinir senha' }));
        await waitFor(() => expect(go).toHaveBeenCalledWith('/login'));
        expect(fake.calls.find((call) => call.path === '/auth/reset-password')?.body).toMatchObject({ token: 'tok', email: 'ana@example.test' });
    });
});

describe('<PtahProfile> (React)', () => {
    const open = async () => {
        const fake = fakeAuth();
        render(<PtahProfile api={fake.api} locale="pt_BR" />);
        await screen.findByLabelText(/Nome/);

        return fake;
    };

    it('saves the name; a new e-mail asks for the current password', async () => {
        const fake = await open();

        type(/Nome/, 'Ana Maria');
        fireEvent.click(screen.getByRole('button', { name: 'Salvar' }));
        expect(await screen.findByText('Perfil atualizado.')).toBeTruthy();
        expect(fake.profile().name).toBe('Ana Maria');

        expect(screen.queryByLabelText(/Senha atual/)).toBeNull();
        type(/E-mail/, 'nova@example.test');
        type(/Senha atual/, 'errada');
        fireEvent.click(screen.getByRole('button', { name: 'Salvar' }));
        expect(await screen.findByText('Senha atual incorreta.')).toBeTruthy();
        expect(fake.profile().email).toBe('ana@example.test');
    });

    it('sets up two-factor: password, QR, code', async () => {
        const fake = await open();
        fireEvent.click(screen.getByRole('tab', { name: 'Segurança' }));

        fireEvent.click(await screen.findByRole('button', { name: 'Usar um aplicativo autenticador' }));
        expect(await screen.findByText('Senha atual incorreta.')).toBeTruthy();

        const passwords = screen.getAllByLabelText(/Senha atual/);
        fireEvent.change(passwords[passwords.length - 1], { target: { value: 'secret-123' } });
        fireEvent.click(screen.getByRole('button', { name: 'Usar um aplicativo autenticador' }));
        expect(await screen.findByText('ABCDEF123456')).toBeTruthy();
        expect(screen.getByText('aaaa-1111')).toBeTruthy();

        type(/Código de verificação/, '123456');
        fireEvent.click(screen.getByRole('button', { name: 'Verificar' }));
        await waitFor(() => expect(fake.profile().two_factor.enabled).toBe(true));
        expect(await screen.findByRole('button', { name: 'Desligar' })).toBeTruthy();
    });

    it('lists sessions and ends another one; changes the appearance', async () => {
        const fake = await open();

        fireEvent.click(screen.getByRole('tab', { name: 'Sessões' }));
        expect(await screen.findByText(/Firefox/)).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Encerrar' }));
        await waitFor(() => expect(screen.queryByText(/Firefox/)).toBeNull());
        expect(screen.getByText(/Este dispositivo/)).toBeTruthy();

        fireEvent.click(screen.getByRole('tab', { name: 'Aparência' }));
        fireEvent.click(await screen.findByRole('button', { name: 'verde' }));
        await waitFor(() => expect(fake.profile().appearance.accent).toBe('verde'));
        expect(screen.getByRole('button', { name: 'verde' }).getAttribute('aria-pressed')).toBe('true');
    });
});

describe('dashboard and page router (React)', () => {
    it('draws the four kinds of widget and a widget that failed', async () => {
        render(<PtahDashboard api={fakeAuth().api} />);

        expect(await screen.findByText('Bem-vindo, Ana')).toBeTruthy();
        expect(await screen.findByText('42')).toBeTruthy();
        expect(screen.getByText('Pedidos hoje').closest('a')?.getAttribute('href')).toBe('/orders');
        expect(screen.getByRole('img', { name: /Vendas: Seg R\$ 400,00, Ter R\$ 500,00/ })).toBeTruthy();
        expect(screen.getByText('Loja')).toBeTruthy();
        expect(screen.getByText('#2')).toBeTruthy();
        expect(screen.getByRole('alert').textContent).toBe('Não foi possível calcular.');
    });

    it('PtahApp draws the page the server named', async () => {
        vi.stubGlobal('fetch', (input: RequestInfo | URL, init?: RequestInit) => fakeAuth().api['doFetch'](input, init));

        const login = render(<PtahApp page="login" locale="pt_BR" />);
        expect(await screen.findByRole('button', { name: 'Entrar' })).toBeTruthy();
        expect(screen.queryByRole('navigation')).toBeNull();
        login.unmount();

        render(<PtahApp page="dashboard" locale="pt_BR" brand="ERP" canLogout profileUrl="/profile" />);
        expect(await screen.findByText('Bem-vindo, Ana')).toBeTruthy();
        expect(await screen.findByRole('link', { name: 'Itens' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Sair' })).toBeTruthy();

        // Profile and log out live in the account menu.
        fireEvent.click(await screen.findByRole('button', { name: 'Conta: Ana' }));
        expect(screen.getByRole('link', { name: 'Perfil' }).getAttribute('href')).toBe('/profile');
        expect(screen.getAllByRole('button', { name: 'Sair' })).toHaveLength(2);

        vi.unstubAllGlobals();
    });
});
