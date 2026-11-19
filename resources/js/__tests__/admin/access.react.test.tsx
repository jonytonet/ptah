import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { PtahAudit, PtahUserPermissions } from '../../react/admin/access';
import { fakeAccess } from './access.fake';

afterEach(cleanup);

const type = (label: RegExp | string, value: string, root: HTMLElement = document.body) => fireEvent.change(within(root).getByLabelText(label), { target: { value } });
const click = (name: string | RegExp, root: HTMLElement = document.body) => fireEvent.click(within(root).getByRole('button', { name }));
const pick = (select: RegExp | string, option: string, root: HTMLElement = document.body) => {
    fireEvent.click(within(root).getByRole('button', { name: select }));
    fireEvent.click(within(root).getByRole('option', { name: option }));
};

describe('users access screen (React)', () => {
    it('lists users with their roles, searches and filters by role', async () => {
        const fake = fakeAccess();
        render(<PtahUserPermissions api={fake.api} locale="pt_BR" />);

        expect(screen.getByRole('status').textContent).toBe('Carregando…');
        expect(await screen.findByText('ana@example.com')).toBeTruthy();
        expect(screen.getByText('Vendedor')).toBeTruthy();
        expect(screen.getByText('· Acme')).toBeTruthy();
        expect(screen.getByText('(Master)')).toBeTruthy();

        fireEvent.change(screen.getByLabelText('Buscar por nome ou e-mail...'), { target: { value: 'ninguem' } });
        expect(await screen.findByText('Nenhum usuário encontrado')).toBeTruthy();
        expect(fake.calls.some((call) => call.query.search === 'ninguem')).toBe(true);

        fireEvent.change(screen.getByLabelText('Buscar por nome ou e-mail...'), { target: { value: '' } });
        await screen.findByText('ana@example.com');
        pick(/Filtrar por role/, 'Vendedor');
        await waitFor(() => expect(screen.queryByText('admin@example.com')).toBeNull());
        expect(fake.calls[fake.calls.length - 1].query.role).toBe('2');
    });

    it('creates a user: a validation error first, then success', async () => {
        const fake = fakeAccess();
        render(<PtahUserPermissions api={fake.api} locale="pt_BR" />);
        await screen.findByText('ana@example.com');

        click('Novo usuário');
        const dialog = screen.getByRole('dialog', { name: 'Novo usuário' });
        expect((within(dialog).getByRole('switch') as HTMLInputElement).checked).toBe(true);
        type(/E-mail/, 'ana@example.com', dialog);
        click('Salvar', dialog);
        expect((await within(dialog).findAllByRole('alert')).map((node) => node.textContent)).toEqual(['O campo Nome é obrigatório.', 'O campo E-mail já está sendo utilizado.']);
        expect(within(dialog).getByLabelText(/Nome/).getAttribute('aria-invalid')).toBe('true');

        type(/Nome/, 'Maria', dialog);
        type(/E-mail/, 'maria@example.com', dialog);
        click('Salvar', dialog);
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(await screen.findByText('Usuário criado. Link de senha enviado.')).toBeTruthy();
        expect(await screen.findByText('maria@example.com')).toBeTruthy();
        expect(fake.calls.filter((call) => call.method === 'POST' && call.path === '/users').pop()?.body).toMatchObject({ name: 'Maria', email: 'maria@example.com', password: '', send_link: true });
    });

    it('edits a user', async () => {
        const fake = fakeAccess();
        render(<PtahUserPermissions api={fake.api} locale="pt_BR" />);
        await screen.findByText('ana@example.com');

        click('Editar Ana');
        const dialog = await screen.findByRole('dialog', { name: 'Editar usuário' });
        expect((within(dialog).getByLabelText(/Nome/) as HTMLInputElement).value).toBe('Ana');
        expect(within(dialog).getByText('Deixe em branco para manter a senha atual.')).toBeTruthy();
        type(/Nome/, 'Ana Souza', dialog);
        click('Salvar', dialog);

        expect(await screen.findByText('Ana Souza')).toBeTruthy();
        expect(fake.calls.some((call) => call.method === 'PUT' && call.path === '/users/2')).toBe(true);
    });

    it('manages the roles of a user: add (empty is refused), remove after confirming, MASTER protected', async () => {
        const fake = fakeAccess();
        render(<PtahUserPermissions api={fake.api} locale="pt_BR" />);
        await screen.findByText('ana@example.com');

        click('Gerenciar acesso Admin');
        let dialog = await screen.findByRole('dialog', { name: 'Acesso Admin' });
        expect(within(dialog).getByText('Protegido')).toBeTruthy();
        expect(within(dialog).queryByRole('button', { name: /Remover/ })).toBeNull();
        click('Fechar', dialog);
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());

        click('Gerenciar acesso Ana');
        dialog = await screen.findByRole('dialog', { name: 'Acesso Ana' });
        click('Adicionar', dialog);
        expect((await within(dialog).findByRole('alert')).textContent).toBe('Selecione um role.');

        pick(/^Role$/, 'Vendedor', dialog);
        click('Adicionar', dialog);
        expect(await within(dialog).findByText('Global (sem empresa)')).toBeTruthy();
        await waitFor(() => expect(within(dialog).queryByRole('alert')).toBeNull());
        expect(fake.bindings.filter((item) => item.user_id === 2)).toHaveLength(2);

        fireEvent.click(within(dialog).getAllByRole('button', { name: 'Remover Vendedor' })[0]);
        const confirm = screen.getByRole('dialog', { name: 'Confirmar' });
        expect(confirm.textContent).toContain('Remover este role do usuário?');
        click('Confirmar', confirm);
        await waitFor(() => expect(fake.bindings.filter((item) => item.user_id === 2)).toHaveLength(1));
        expect(await screen.findByText('Role removido.')).toBeTruthy();
    });

    it('sends a password link only after confirming', async () => {
        const fake = fakeAccess();
        render(<PtahUserPermissions api={fake.api} locale="pt_BR" />);
        await screen.findByText('ana@example.com');

        click('Enviar link de senha Ana');
        let confirm = screen.getByRole('dialog', { name: 'Confirmar' });
        expect(confirm.textContent).toContain('ana@example.com');
        click('Cancelar', confirm);
        expect(fake.calls.some((call) => call.path.endsWith('/password-link'))).toBe(false);

        click('Enviar link de senha Ana');
        confirm = screen.getByRole('dialog', { name: 'Confirmar' });
        click('Confirmar', confirm);
        expect(await screen.findByText('Link de senha enviado.')).toBeTruthy();
        expect(fake.calls.some((call) => call.method === 'POST' && call.path === '/users/2/password-link')).toBe(true);
    });

    it('hides the link actions where no link can be sent, and paginates', async () => {
        const fake = fakeAccess({ canSendLink: false, perPage: 1 });
        render(<PtahUserPermissions api={fake.api} locale="pt_BR" />);
        await screen.findByText('admin@example.com');

        expect(screen.queryByRole('button', { name: /Enviar link de senha/ })).toBeNull();
        expect(screen.getByText('1–1 de 2')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: '2' }));
        expect(await screen.findByText('ana@example.com')).toBeTruthy();

        click('Novo usuário');
        expect(screen.queryByRole('switch')).toBeNull();
    });

    it('tells a user who is not a master that the screen is closed', async () => {
        render(<PtahUserPermissions api={fakeAccess({ forbidden: true }).api} locale="pt_BR" />);

        expect((await screen.findByRole('alert')).textContent).toBe('Você não tem permissão para ver esta tela.');
    });
});

describe('permission audit screen (React)', () => {
    it('lists the log, filters it and clears the filters', async () => {
        const fake = fakeAccess();
        render(<PtahAudit api={fake.api} locale="pt_BR" />);

        expect(await screen.findByText('orders')).toBeTruthy();
        expect(screen.getByText('invoices').closest('tr')?.getAttribute('data-result')).toBe('denied');
        expect(screen.getByText('Excluir')).toBeTruthy();

        pick(/Resultado/, 'Negado');
        await waitFor(() => expect(screen.queryByText('orders')).toBeNull());
        expect(fake.calls[fake.calls.length - 1].query.result).toBe('denied');

        pick(/Ação/, 'Ler');
        expect(await screen.findByText('Nenhum registro encontrado')).toBeTruthy();

        type('De', '2026-11-01');
        await waitFor(() => expect(fake.calls[fake.calls.length - 1].query.date_from).toBe('2026-11-01'));

        click('Limpar');
        expect(await screen.findByText('orders')).toBeTruthy();
        expect(screen.getByText('invoices')).toBeTruthy();

        type('Buscar recurso, IP, usuário...', '10.0.0.2');
        await waitFor(() => expect(screen.queryByText('orders')).toBeNull());
    });

    it('explains how to turn the audit on when the log is empty', async () => {
        render(<PtahAudit api={fakeAccess({ audits: [] }).api} locale="pt_BR" />);

        expect(await screen.findByText('Nenhum registro de auditoria')).toBeTruthy();
        expect(screen.getByText('Ative com PTAH_PERMISSION_AUDIT=true no .env.')).toBeTruthy();
    });
});
