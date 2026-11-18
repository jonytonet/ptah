import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { PtahPages, PtahPermissionGuide, PtahRoles } from '../../react/admin/roles';
import { fakeRoles } from './roles.fake';

afterEach(cleanup);

const type = (label: RegExp | string, value: string) => fireEvent.change(screen.getByLabelText(label), { target: { value } });
const click = (name: string | RegExp) => fireEvent.click(screen.getByRole('button', { name }));
const dialog = () => screen.getByRole('dialog');

describe('roles (React)', () => {
    it('lists the roles, MASTER first and without a delete button, and searches', async () => {
        const server = fakeRoles();
        render(<PtahRoles api={server.api} locale="pt_BR" />);

        expect(screen.getByRole('status').textContent).toContain('Carregando');
        expect(await screen.findByText('Vendedor')).toBeTruthy();
        expect(screen.getByText('👑 MASTER')).toBeTruthy();
        expect(screen.getByText('Comercial')).toBeTruthy();
        expect(screen.getByText('1 objetos')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Excluir Master' })).toBeNull();
        expect(screen.getByRole('button', { name: 'Excluir Vendedor' })).toBeTruthy();

        type('Buscar role...', 'vend');
        await waitFor(() => expect(server.calls.some((call) => call.path.includes('search=vend'))).toBe(true));
        await waitFor(() => expect(screen.queryByText('Master')).toBeNull());
    });

    it('shows the empty state and the refusal of a non-master', async () => {
        render(<PtahRoles api={fakeRoles({ empty: true }).api} locale="pt_BR" />);
        expect(await screen.findByText('Nenhum role encontrado')).toBeTruthy();
        cleanup();

        render(<PtahRoles api={fakeRoles({ forbidden: true }).api} locale="pt_BR" />);
        expect((await screen.findByRole('alert')).textContent).toContain('Você não tem permissão');
    });

    it('creates a role: a missing name stays on its field, then it saves', async () => {
        const server = fakeRoles();
        render(<PtahRoles api={server.api} locale="pt_BR" />);
        await screen.findByText('Vendedor');

        click('+ Novo Role');
        expect(within(dialog()).getByRole('heading', { name: 'Novo Role' })).toBeTruthy();
        click('Salvar');
        await waitFor(() => expect(screen.getByLabelText(/^Nome/).getAttribute('aria-invalid')).toBe('true'));
        expect(screen.getByRole('alert').textContent).toContain('O campo nome é obrigatório.');

        type(/^Nome/, 'Financeiro');
        click('Salvar');
        expect(await screen.findByText('Role criado.')).toBeTruthy();
        expect(await screen.findByText('Financeiro')).toBeTruthy();
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(server.roles().find((role) => role.name === 'Financeiro')?.is_active).toBe(true);
    });

    it('refuses a second MASTER with the server reason', async () => {
        render(<PtahRoles api={fakeRoles().api} locale="pt_BR" />);
        await screen.findByText('Vendedor');

        click('+ Novo Role');
        type(/^Nome/, 'Outro master');
        fireEvent.click(screen.getByRole('switch', { name: /MASTER/ }));
        expect(screen.getByText(/Apenas 1 role pode ser MASTER/)).toBeTruthy();
        click('Salvar');

        expect((await screen.findByRole('alert')).textContent).toContain('Já existe um role MASTER');
        expect(dialog()).toBeTruthy();
    });

    it('edits a role loaded from the server; the MASTER role has no MASTER switch', async () => {
        const server = fakeRoles();
        render(<PtahRoles api={server.api} locale="pt_BR" />);
        await screen.findByText('Vendedor');

        click('Editar Master');
        await screen.findByText('👑 Este é o role MASTER');
        expect(screen.queryByRole('switch', { name: /MASTER \(bypass/ })).toBeNull();
        click('Cancelar');

        click('Editar Vendedor');
        await waitFor(() => expect((screen.getByLabelText(/^Nome/) as HTMLInputElement).value).toBe('Vendedor'));
        expect(within(dialog()).getByRole('heading', { name: 'Editar Role' })).toBeTruthy();
        type(/^Nome/, 'Vendedor Sênior');
        click('Salvar');

        expect(await screen.findByText('Role atualizado.')).toBeTruthy();
        expect(await screen.findByText('Vendedor Sênior')).toBeTruthy();
        expect(server.calls.some((call) => call.method === 'PUT' && call.path === '/admin/roles/2')).toBe(true);
    });

    it('deletes after a confirmation', async () => {
        const server = fakeRoles();
        render(<PtahRoles api={server.api} locale="pt_BR" />);
        await screen.findByText('Vendedor');

        click('Excluir Vendedor');
        expect(within(dialog()).getByText(/Excluir este role\?/)).toBeTruthy();
        expect(server.calls.some((call) => call.method === 'DELETE')).toBe(false);
        click('Excluir');

        expect(await screen.findByText('Role excluído.')).toBeTruthy();
        await waitFor(() => expect(screen.queryByText('Vendedor')).toBeNull());
    });

    it('edits the permission matrix grouped by page, with a filter', async () => {
        const server = fakeRoles();
        render(<PtahRoles api={server.api} locale="pt_BR" />);
        await screen.findByText('Vendedor');

        fireEvent.click(screen.getAllByRole('button', { name: '🔑 Permissões' })[1]);
        expect(await screen.findByRole('heading', { name: 'Gerenciar Permissões Vendedor' })).toBeTruthy();

        // Collapsed on open, with the N/M summary.
        const group = screen.getByRole('button', { name: /Vendas — main/ });
        expect(group.getAttribute('aria-expanded')).toBe('false');
        expect(group.textContent).toContain('2/8');
        fireEvent.click(group);
        expect(group.getAttribute('aria-expanded')).toBe('true');

        const exportRow = screen.getByRole('group', { name: 'Exportar' });
        fireEvent.click(within(exportRow).getByLabelText('Ler'));
        fireEvent.click(within(exportRow).getByLabelText('Excluir'));
        expect(group.textContent).toContain('4/8');

        // The filter opens the matching groups and hides the other rows.
        type('Filtrar páginas e objetos', 'criar');
        expect(screen.queryByRole('group', { name: 'Exportar' })).toBeNull();
        expect(screen.getByRole('group', { name: 'Criar pedido' })).toBeTruthy();
        type('Filtrar páginas e objetos', 'zzz');
        expect(screen.getByText('Nenhuma página ou objeto encontrado para a busca.')).toBeTruthy();
        type('Filtrar páginas e objetos', '');

        click('Salvar Permissões');
        expect(await screen.findByText("Permissões de 'Vendedor' atualizadas.")).toBeTruthy();
        expect(server.grants[2][11]).toEqual({ can_create: false, can_read: true, can_update: false, can_delete: true });
        expect(server.grants[2][10].can_create).toBe(true);
    });
});

describe('pages and objects (React)', () => {
    it('lists pages and shows the objects of the selected one', async () => {
        render(<PtahPages api={fakeRoles().api} locale="pt_BR" />);

        expect(await screen.findByText('Vendas')).toBeTruthy();
        expect(screen.getByText('Selecione uma página para ver seus objetos')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: /Vendas/, pressed: false }));
        expect(await screen.findByText('vendas.criar')).toBeTruthy();
        expect(screen.getByRole('heading', { name: 'Objetos Vendas' })).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: /Estoque/, pressed: false }));
        expect(await screen.findByText('Nenhum objeto nesta página')).toBeTruthy();
    });

    it('creates a page (with a validation error first), edits and deletes it', async () => {
        const server = fakeRoles();
        render(<PtahPages api={server.api} locale="pt_BR" />);
        await screen.findByText('Vendas');

        click('+ Página');
        click('Salvar');
        await waitFor(() => expect(screen.getByLabelText(/^Slug/).getAttribute('aria-invalid')).toBe('true'));
        expect(screen.getByLabelText(/^Nome/).getAttribute('aria-invalid')).toBe('true');

        type(/^Slug/, 'admin.financeiro');
        type(/^Nome/, 'Financeiro');
        click('Salvar');
        expect(await screen.findByText('Página criada.')).toBeTruthy();
        expect(await screen.findByText('Financeiro')).toBeTruthy();

        click('Editar Financeiro');
        expect((screen.getByLabelText(/^Slug/) as HTMLInputElement).value).toBe('admin.financeiro');
        type(/^Nome/, 'Finanças');
        click('Salvar');
        expect(await screen.findByText('Finanças')).toBeTruthy();

        click('Excluir Finanças');
        expect(within(dialog()).getByText(/Excluir esta página\?/)).toBeTruthy();
        click('Excluir');
        await waitFor(() => expect(screen.queryByText('Finanças')).toBeNull());
        expect(server.pages().some((page) => page.slug === 'admin.financeiro')).toBe(false);
    });

    it('creates, edits and deletes an object of the selected page', async () => {
        const server = fakeRoles();
        render(<PtahPages api={server.api} locale="pt_BR" />);
        await screen.findByText('Vendas');
        fireEvent.click(screen.getByRole('button', { name: /Vendas/, pressed: false }));
        await screen.findByText('vendas.criar');

        click('+ Objeto');
        click('Salvar');
        await waitFor(() => expect(screen.getByLabelText(/^Chave/).getAttribute('aria-invalid')).toBe('true'));

        type(/^Chave/, 'vendas.desconto');
        type(/^Label/, 'Desconto');
        click('Salvar');
        expect(await screen.findByText('vendas.desconto')).toBeTruthy();
        expect(server.objects().find((object) => object.obj_key === 'vendas.desconto')?.page_id).toBe(1);

        click('Editar vendas.desconto');
        expect((screen.getByLabelText(/^Label/) as HTMLInputElement).value).toBe('Desconto');
        type(/^Label/, 'Ver desconto');
        click('Salvar');
        expect(await screen.findByText('Ver desconto')).toBeTruthy();

        click('Excluir vendas.desconto');
        expect(within(dialog()).getByText(/Excluir este objeto\?/)).toBeTruthy();
        click('Excluir');
        await waitFor(() => expect(screen.queryByText('vendas.desconto')).toBeNull());
    });
});

describe('permission guide (React)', () => {
    it('shows the tabs, links to the screens, jumps to the code examples and links the audit', async () => {
        render(<PtahPermissionGuide api={fakeRoles().api} locale="pt_BR" urls={{ users: '/app/users' }} />);

        expect(await screen.findByRole('heading', { name: 'Guia do Sistema de Permissões' })).toBeTruthy();
        expect(screen.getByText('ptah v1.59.0')).toBeTruthy();
        expect(screen.getByText('O que é o sistema de permissões do Ptah?')).toBeTruthy();

        fireEvent.click(screen.getByRole('tab', { name: '🔧 Passo a Passo' }));
        expect(screen.getByRole('link', { name: 'Ir para Roles →' }).getAttribute('href')).toBe('/ptah-roles');
        expect(screen.getByRole('link', { name: 'Ir para Usuários →' }).getAttribute('href')).toBe('/app/users');

        click('Ver exemplos →');
        expect(screen.getByRole('tab', { name: '💻 Exemplos de Código' }).getAttribute('aria-selected')).toBe('true');
        expect(screen.getByText('routes/web.php — Middleware ptah.can')).toBeTruthy();

        fireEvent.click(screen.getByRole('tab', { name: '❓ Perguntas Frequentes' }));
        expect(screen.getByRole('link', { name: 'Auditoria' }).getAttribute('href')).toBe('/ptah-audit');
    });

    it('is refused to a non-master', async () => {
        render(<PtahPermissionGuide api={fakeRoles({ forbidden: true }).api} locale="en" />);
        expect((await screen.findByRole('alert')).textContent).toContain('You do not have permission');
    });
});
