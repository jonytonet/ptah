import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vitest';
import { PtahPages, PtahPermissionGuide, PtahRoles } from '../../vue/admin/roles';
import { fakeRoles } from './roles.fake';

afterEach(cleanup);

const type = (label: RegExp | string, value: string) => fireEvent.update(screen.getByLabelText(label), value);
const click = (name: string | RegExp) => fireEvent.click(screen.getByRole('button', { name }));
const dialog = () => screen.getByRole('dialog');

describe('roles (Vue)', () => {
    it('lists the roles, MASTER first and without a delete button, and searches', async () => {
        const server = fakeRoles();
        render(PtahRoles, { props: { api: server.api, locale: 'pt_BR' } });

        expect(screen.getByRole('status').textContent).toContain('Carregando');
        expect(await screen.findByText('Vendedor')).toBeTruthy();
        expect(screen.getByText('👑 MASTER')).toBeTruthy();
        expect(screen.getByText('Comercial')).toBeTruthy();
        expect(screen.getByText('1 objetos')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Excluir Master' })).toBeNull();
        expect(screen.getByRole('button', { name: 'Excluir Vendedor' })).toBeTruthy();

        await type('Buscar role...', 'vend');
        await waitFor(() => expect(server.calls.some((call) => call.path.includes('search=vend'))).toBe(true));
        await waitFor(() => expect(screen.queryByText('Master')).toBeNull());
    });

    it('shows the empty state and the refusal of a non-master', async () => {
        render(PtahRoles, { props: { api: fakeRoles({ empty: true }).api, locale: 'pt_BR' } });
        expect(await screen.findByText('Nenhum role encontrado')).toBeTruthy();
        cleanup();

        render(PtahRoles, { props: { api: fakeRoles({ forbidden: true }).api, locale: 'pt_BR' } });
        expect((await screen.findByRole('alert')).textContent).toContain('Você não tem permissão');
    });

    it('creates a role: a missing name stays on its field, then it saves', async () => {
        const server = fakeRoles();
        render(PtahRoles, { props: { api: server.api, locale: 'pt_BR' } });
        await screen.findByText('Vendedor');

        await click('+ Novo Role');
        expect(within(dialog()).getByRole('heading', { name: 'Novo Role' })).toBeTruthy();
        await click('Salvar');
        await waitFor(() => expect(screen.getByLabelText(/^Nome/).getAttribute('aria-invalid')).toBe('true'));
        expect(screen.getByRole('alert').textContent).toContain('O campo nome é obrigatório.');

        await type(/^Nome/, 'Financeiro');
        await click('Salvar');
        expect(await screen.findByText('Role criado.')).toBeTruthy();
        expect(await screen.findByText('Financeiro')).toBeTruthy();
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(server.roles().find((role) => role.name === 'Financeiro')?.is_active).toBe(true);
    });

    it('refuses a second MASTER with the server reason', async () => {
        render(PtahRoles, { props: { api: fakeRoles().api, locale: 'pt_BR' } });
        await screen.findByText('Vendedor');

        await click('+ Novo Role');
        await type(/^Nome/, 'Outro master');
        await fireEvent.click(screen.getByRole('switch', { name: /MASTER/ }));
        expect(screen.getByText(/Apenas 1 role pode ser MASTER/)).toBeTruthy();
        await click('Salvar');

        expect((await screen.findByRole('alert')).textContent).toContain('Já existe um role MASTER');
        expect(dialog()).toBeTruthy();
    });

    it('edits a role loaded from the server; the MASTER role has no MASTER switch', async () => {
        const server = fakeRoles();
        render(PtahRoles, { props: { api: server.api, locale: 'pt_BR' } });
        await screen.findByText('Vendedor');

        await click('Editar Master');
        await screen.findByText('👑 Este é o role MASTER');
        expect(screen.queryByRole('switch', { name: /MASTER \(bypass/ })).toBeNull();
        await click('Cancelar');

        await click('Editar Vendedor');
        await waitFor(() => expect((screen.getByLabelText(/^Nome/) as HTMLInputElement).value).toBe('Vendedor'));
        expect(within(dialog()).getByRole('heading', { name: 'Editar Role' })).toBeTruthy();
        await type(/^Nome/, 'Vendedor Sênior');
        await click('Salvar');

        expect(await screen.findByText('Role atualizado.')).toBeTruthy();
        expect(await screen.findByText('Vendedor Sênior')).toBeTruthy();
        expect(server.calls.some((call) => call.method === 'PUT' && call.path === '/admin/roles/2')).toBe(true);
    });

    it('deletes after a confirmation', async () => {
        const server = fakeRoles();
        render(PtahRoles, { props: { api: server.api, locale: 'pt_BR' } });
        await screen.findByText('Vendedor');

        await click('Excluir Vendedor');
        expect(within(dialog()).getByText(/Excluir este role\?/)).toBeTruthy();
        expect(server.calls.some((call) => call.method === 'DELETE')).toBe(false);
        await click('Excluir');

        expect(await screen.findByText('Role excluído.')).toBeTruthy();
        await waitFor(() => expect(screen.queryByText('Vendedor')).toBeNull());
    });

    it('edits the permission matrix grouped by page, with a filter', async () => {
        const server = fakeRoles();
        render(PtahRoles, { props: { api: server.api, locale: 'pt_BR' } });
        await screen.findByText('Vendedor');

        await fireEvent.click(screen.getAllByRole('button', { name: '🔑 Permissões' })[1]);
        expect(await screen.findByRole('heading', { name: 'Gerenciar Permissões Vendedor' })).toBeTruthy();

        // Collapsed on open, with the N/M summary.
        const group = () => screen.getByRole('button', { name: /Vendas — main/ });
        expect(group().getAttribute('aria-expanded')).toBe('false');
        expect(group().textContent).toContain('2/8');
        await fireEvent.click(group());
        expect(group().getAttribute('aria-expanded')).toBe('true');

        await fireEvent.click(within(screen.getByRole('group', { name: 'Exportar' })).getByLabelText('Ler'));
        await fireEvent.click(within(screen.getByRole('group', { name: 'Exportar' })).getByLabelText('Excluir'));
        expect(group().textContent).toContain('4/8');

        // The filter opens the matching groups and hides the other rows.
        await type('Filtrar páginas e objetos', 'criar');
        expect(screen.queryByRole('group', { name: 'Exportar' })).toBeNull();
        expect(screen.getByRole('group', { name: 'Criar pedido' })).toBeTruthy();
        await type('Filtrar páginas e objetos', 'zzz');
        expect(screen.getByText('Nenhuma página ou objeto encontrado para a busca.')).toBeTruthy();
        await type('Filtrar páginas e objetos', '');

        await click('Salvar Permissões');
        expect(await screen.findByText("Permissões de 'Vendedor' atualizadas.")).toBeTruthy();
        expect(server.grants[2][11]).toEqual({ can_create: false, can_read: true, can_update: false, can_delete: true });
        expect(server.grants[2][10].can_create).toBe(true);
    });
});

describe('pages and objects (Vue)', () => {
    it('lists pages and shows the objects of the selected one', async () => {
        render(PtahPages, { props: { api: fakeRoles().api, locale: 'pt_BR' } });

        expect(await screen.findByText('Vendas')).toBeTruthy();
        expect(screen.getByText('Selecione uma página para ver seus objetos')).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: /Vendas/, pressed: false }));
        expect(await screen.findByText('vendas.criar')).toBeTruthy();
        expect(screen.getByRole('heading', { name: 'Objetos Vendas' })).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: /Estoque/, pressed: false }));
        expect(await screen.findByText('Nenhum objeto nesta página')).toBeTruthy();
    });

    it('creates a page (with a validation error first), edits and deletes it', async () => {
        const server = fakeRoles();
        render(PtahPages, { props: { api: server.api, locale: 'pt_BR' } });
        await screen.findByText('Vendas');

        await click('+ Página');
        await click('Salvar');
        await waitFor(() => expect(screen.getByLabelText(/^Slug/).getAttribute('aria-invalid')).toBe('true'));
        expect(screen.getByLabelText(/^Nome/).getAttribute('aria-invalid')).toBe('true');

        await type(/^Slug/, 'admin.financeiro');
        await type(/^Nome/, 'Financeiro');
        await click('Salvar');
        expect(await screen.findByText('Página criada.')).toBeTruthy();
        expect(await screen.findByText('Financeiro')).toBeTruthy();

        await click('Editar Financeiro');
        expect((screen.getByLabelText(/^Slug/) as HTMLInputElement).value).toBe('admin.financeiro');
        await type(/^Nome/, 'Finanças');
        await click('Salvar');
        expect(await screen.findByText('Finanças')).toBeTruthy();

        await click('Excluir Finanças');
        expect(within(dialog()).getByText(/Excluir esta página\?/)).toBeTruthy();
        await click('Excluir');
        await waitFor(() => expect(screen.queryByText('Finanças')).toBeNull());
        expect(server.pages().some((page) => page.slug === 'admin.financeiro')).toBe(false);
    });

    it('creates, edits and deletes an object of the selected page', async () => {
        const server = fakeRoles();
        render(PtahPages, { props: { api: server.api, locale: 'pt_BR' } });
        await screen.findByText('Vendas');
        await fireEvent.click(screen.getByRole('button', { name: /Vendas/, pressed: false }));
        await screen.findByText('vendas.criar');

        await click('+ Objeto');
        await click('Salvar');
        await waitFor(() => expect(screen.getByLabelText(/^Chave/).getAttribute('aria-invalid')).toBe('true'));

        await type(/^Chave/, 'vendas.desconto');
        await type(/^Label/, 'Desconto');
        await click('Salvar');
        expect(await screen.findByText('vendas.desconto')).toBeTruthy();
        expect(server.objects().find((object) => object.obj_key === 'vendas.desconto')?.page_id).toBe(1);

        await click('Editar vendas.desconto');
        expect((screen.getByLabelText(/^Label/) as HTMLInputElement).value).toBe('Desconto');
        await type(/^Label/, 'Ver desconto');
        await click('Salvar');
        expect(await screen.findByText('Ver desconto')).toBeTruthy();

        await click('Excluir vendas.desconto');
        expect(within(dialog()).getByText(/Excluir este objeto\?/)).toBeTruthy();
        await click('Excluir');
        await waitFor(() => expect(screen.queryByText('vendas.desconto')).toBeNull());
    });
});

describe('permission guide (Vue)', () => {
    it('shows the tabs, links to the screens, jumps to the code examples and links the audit', async () => {
        render(PtahPermissionGuide, { props: { api: fakeRoles().api, locale: 'pt_BR', urls: { users: '/app/users' } } });

        expect(await screen.findByRole('heading', { name: 'Guia do Sistema de Permissões' })).toBeTruthy();
        expect(screen.getByText('ptah v1.59.0')).toBeTruthy();
        expect(screen.getByText('O que é o sistema de permissões do Ptah?')).toBeTruthy();

        await fireEvent.click(screen.getByRole('tab', { name: '🔧 Passo a Passo' }));
        expect(screen.getByRole('link', { name: 'Ir para Roles →' }).getAttribute('href')).toBe('/ptah-roles');
        expect(screen.getByRole('link', { name: 'Ir para Usuários →' }).getAttribute('href')).toBe('/app/users');

        await click('Ver exemplos →');
        expect(screen.getByRole('tab', { name: '💻 Exemplos de Código' }).getAttribute('aria-selected')).toBe('true');
        expect(screen.getByText('routes/web.php — Middleware ptah.can')).toBeTruthy();

        await fireEvent.click(screen.getByRole('tab', { name: '❓ Perguntas Frequentes' }));
        expect(screen.getByRole('link', { name: 'Auditoria' }).getAttribute('href')).toBe('/ptah-audit');
    });

    it('is refused to a non-master', async () => {
        render(PtahPermissionGuide, { props: { api: fakeRoles({ forbidden: true }).api, locale: 'en' } });
        expect((await screen.findByRole('alert')).textContent).toContain('You do not have permission');
    });
});
