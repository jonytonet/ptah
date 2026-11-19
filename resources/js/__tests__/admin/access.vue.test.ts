import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vitest';
import { PtahAudit, PtahUserPermissions } from '../../vue/admin/access';
import { fakeAccess } from './access.fake';

afterEach(cleanup);

const type = (label: RegExp | string, value: string, root: HTMLElement = document.body) => fireEvent.update(within(root).getByLabelText(label), value);
const click = (name: string | RegExp, root: HTMLElement = document.body) => fireEvent.click(within(root).getByRole('button', { name }));
const pick = async (select: RegExp | string, option: string, root: HTMLElement = document.body) => {
    await fireEvent.click(within(root).getByRole('button', { name: select }));
    await fireEvent.click(within(root).getByRole('option', { name: option }));
};

describe('users access screen (Vue)', () => {
    it('lists users with their roles, searches and filters by role', async () => {
        const fake = fakeAccess();
        render(PtahUserPermissions, { props: { api: fake.api, locale: 'pt_BR' } });

        expect(screen.getByRole('status').textContent).toBe('Carregando…');
        expect(await screen.findByText('ana@example.com')).toBeTruthy();
        expect(screen.getByText('Vendedor')).toBeTruthy();
        expect(screen.getByText('· Acme')).toBeTruthy();
        expect(screen.getByText('(Master)')).toBeTruthy();

        await type('Buscar por nome ou e-mail...', 'ninguem');
        expect(await screen.findByText('Nenhum usuário encontrado')).toBeTruthy();
        expect(fake.calls.some((call) => call.query.search === 'ninguem')).toBe(true);

        await type('Buscar por nome ou e-mail...', '');
        await screen.findByText('ana@example.com');
        await pick(/Filtrar por role/, 'Vendedor');
        await waitFor(() => expect(screen.queryByText('admin@example.com')).toBeNull());
        expect(fake.calls[fake.calls.length - 1].query.role).toBe('2');
    });

    it('creates a user: a validation error first, then success', async () => {
        const fake = fakeAccess();
        render(PtahUserPermissions, { props: { api: fake.api, locale: 'pt_BR' } });
        await screen.findByText('ana@example.com');

        await click('Novo usuário');
        const dialog = screen.getByRole('dialog', { name: 'Novo usuário' });
        expect((within(dialog).getByRole('switch') as HTMLInputElement).checked).toBe(true);
        await type(/E-mail/, 'ana@example.com', dialog);
        await click('Salvar', dialog);
        await waitFor(() => expect(within(dialog).getAllByRole('alert').map((node) => node.textContent)).toEqual(['O campo Nome é obrigatório.', 'O campo E-mail já está sendo utilizado.']));
        expect(within(dialog).getByLabelText(/Nome/).getAttribute('aria-invalid')).toBe('true');

        await type(/Nome/, 'Maria', dialog);
        await type(/E-mail/, 'maria@example.com', dialog);
        await click('Salvar', dialog);
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(await screen.findByText('Usuário criado. Link de senha enviado.')).toBeTruthy();
        expect(await screen.findByText('maria@example.com')).toBeTruthy();
        expect(fake.calls.filter((call) => call.method === 'POST' && call.path === '/users').pop()?.body).toMatchObject({ name: 'Maria', email: 'maria@example.com', password: '', send_link: true });
    });

    it('edits a user', async () => {
        const fake = fakeAccess();
        render(PtahUserPermissions, { props: { api: fake.api, locale: 'pt_BR' } });
        await screen.findByText('ana@example.com');

        await click('Editar Ana');
        const dialog = await screen.findByRole('dialog', { name: 'Editar usuário' });
        expect((within(dialog).getByLabelText(/Nome/) as HTMLInputElement).value).toBe('Ana');
        expect(within(dialog).getByText('Deixe em branco para manter a senha atual.')).toBeTruthy();
        await type(/Nome/, 'Ana Souza', dialog);
        await click('Salvar', dialog);

        expect(await screen.findByText('Ana Souza')).toBeTruthy();
        expect(fake.calls.some((call) => call.method === 'PUT' && call.path === '/users/2')).toBe(true);
    });

    it('manages the roles of a user: add (empty is refused), remove after confirming, MASTER protected', async () => {
        const fake = fakeAccess();
        render(PtahUserPermissions, { props: { api: fake.api, locale: 'pt_BR' } });
        await screen.findByText('ana@example.com');

        await click('Gerenciar acesso Admin');
        let dialog = await screen.findByRole('dialog', { name: 'Acesso Admin' });
        expect(within(dialog).getByText('Protegido')).toBeTruthy();
        expect(within(dialog).queryByRole('button', { name: /Remover/ })).toBeNull();
        await click('Fechar', dialog);
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());

        await click('Gerenciar acesso Ana');
        dialog = await screen.findByRole('dialog', { name: 'Acesso Ana' });
        await click('Adicionar', dialog);
        expect((await within(dialog).findByRole('alert')).textContent).toBe('Selecione um role.');

        await pick(/^Role$/, 'Vendedor', dialog);
        await click('Adicionar', dialog);
        expect(await within(dialog).findByText('Global (sem empresa)')).toBeTruthy();
        await waitFor(() => expect(within(dialog).queryByRole('alert')).toBeNull());
        expect(fake.bindings.filter((item) => item.user_id === 2)).toHaveLength(2);

        await fireEvent.click(within(dialog).getAllByRole('button', { name: 'Remover Vendedor' })[0]);
        const confirm = screen.getByRole('dialog', { name: 'Confirmar' });
        expect(confirm.textContent).toContain('Remover este role do usuário?');
        await click('Confirmar', confirm);
        await waitFor(() => expect(fake.bindings.filter((item) => item.user_id === 2)).toHaveLength(1));
        expect(await screen.findByText('Role removido.')).toBeTruthy();
    });

    it('sends a password link only after confirming', async () => {
        const fake = fakeAccess();
        render(PtahUserPermissions, { props: { api: fake.api, locale: 'pt_BR' } });
        await screen.findByText('ana@example.com');

        await click('Enviar link de senha Ana');
        let confirm = screen.getByRole('dialog', { name: 'Confirmar' });
        expect(confirm.textContent).toContain('ana@example.com');
        await click('Cancelar', confirm);
        expect(fake.calls.some((call) => call.path.endsWith('/password-link'))).toBe(false);

        await click('Enviar link de senha Ana');
        confirm = screen.getByRole('dialog', { name: 'Confirmar' });
        await click('Confirmar', confirm);
        expect(await screen.findByText('Link de senha enviado.')).toBeTruthy();
        expect(fake.calls.some((call) => call.method === 'POST' && call.path === '/users/2/password-link')).toBe(true);
    });

    it('hides the link actions where no link can be sent, and paginates', async () => {
        const fake = fakeAccess({ canSendLink: false, perPage: 1 });
        render(PtahUserPermissions, { props: { api: fake.api, locale: 'pt_BR' } });
        await screen.findByText('admin@example.com');

        expect(screen.queryByRole('button', { name: /Enviar link de senha/ })).toBeNull();
        expect(screen.getByText('1–1 de 2')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: '2' }));
        expect(await screen.findByText('ana@example.com')).toBeTruthy();

        await click('Novo usuário');
        expect(screen.queryByRole('switch')).toBeNull();
    });

    it('tells a user who is not a master that the screen is closed', async () => {
        render(PtahUserPermissions, { props: { api: fakeAccess({ forbidden: true }).api, locale: 'pt_BR' } });

        expect((await screen.findByRole('alert')).textContent).toBe('Você não tem permissão para ver esta tela.');
    });
});

describe('permission audit screen (Vue)', () => {
    it('lists the log, filters it and clears the filters', async () => {
        const fake = fakeAccess();
        render(PtahAudit, { props: { api: fake.api, locale: 'pt_BR' } });

        expect(await screen.findByText('orders')).toBeTruthy();
        expect(screen.getByText('invoices').closest('tr')?.getAttribute('data-result')).toBe('denied');
        expect(screen.getByText('Excluir')).toBeTruthy();

        await pick(/Resultado/, 'Negado');
        await waitFor(() => expect(screen.queryByText('orders')).toBeNull());
        expect(fake.calls[fake.calls.length - 1].query.result).toBe('denied');

        await pick(/Ação/, 'Ler');
        expect(await screen.findByText('Nenhum registro encontrado')).toBeTruthy();

        await type('De', '2026-11-01');
        await waitFor(() => expect(fake.calls[fake.calls.length - 1].query.date_from).toBe('2026-11-01'));

        await click('Limpar');
        expect(await screen.findByText('orders')).toBeTruthy();
        expect(screen.getByText('invoices')).toBeTruthy();

        await type('Buscar recurso, IP, usuário...', '10.0.0.2');
        await waitFor(() => expect(screen.queryByText('orders')).toBeNull());
    });

    it('explains how to turn the audit on when the log is empty', async () => {
        render(PtahAudit, { props: { api: fakeAccess({ audits: [] }).api, locale: 'pt_BR' } });

        expect(await screen.findByText('Nenhum registro de auditoria')).toBeTruthy();
        expect(screen.getByText('Ative com PTAH_PERMISSION_AUDIT=true no .env.')).toBeTruthy();
    });
});
