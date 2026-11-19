import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vitest';
import { PtahMenuAdmin, PtahSettings } from '../../vue/admin/menu';
import { fakeMenu } from './menu.fake';

afterEach(cleanup);

const type = (label: RegExp | string, value: string) => fireEvent.update(screen.getByLabelText(label), value);
const click = (name: string | RegExp, role = 'button') => fireEvent.click(screen.getByRole(role, { name }));
const lastCall = (calls: ReturnType<typeof fakeMenu>['calls'], method: string, path: RegExp) => [...calls].reverse().find((call) => call.method === method && path.test(call.path));

describe('menu editor (Vue)', () => {
    it('lists the items, pages, and shows the empty state', async () => {
        render(PtahMenuAdmin, { props: { api: fakeMenu().api, locale: 'pt_BR' } });

        expect(await screen.findByText('Pedidos')).toBeTruthy();
        expect(screen.getByRole('heading', { name: 'Gerenciar Menu' })).toBeTruthy();
        expect(screen.getAllByText('Vendas').length).toBeGreaterThan(0);
        expect(screen.queryByText('Clientes')).toBeNull();

        await click('2');
        expect(await screen.findByText('Clientes')).toBeTruthy();
        expect(screen.getByText('Inativo')).toBeTruthy();
        cleanup();

        render(PtahMenuAdmin, { props: { api: fakeMenu({ empty: true }).api, locale: 'pt_BR' } });
        expect(await screen.findByText('Nenhum item de menu encontrado')).toBeTruthy();
    });

    it('searches, filters by type and sorts through the server', async () => {
        const { api, calls } = fakeMenu();
        render(PtahMenuAdmin, { props: { api, locale: 'pt_BR' } });
        await screen.findByText('Pedidos');

        await type('Buscar', 'ped');
        await waitFor(() => expect(lastCall(calls, 'GET', /^\/admin\/menu$/)?.query.search).toBe('ped'));
        await waitFor(() => expect(screen.queryByRole('button', { name: 'Editar: Vendas' })).toBeNull());
        await type('Buscar', '');
        await waitFor(() => expect(lastCall(calls, 'GET', /^\/admin\/menu$/)?.query.search).toBeUndefined());

        await click(/Filtrar por tipo/);
        await click('Grupo', 'option');
        await waitFor(() => expect(lastCall(calls, 'GET', /^\/admin\/menu$/)?.query.type).toBe('menuGroup'));

        await click(/^Texto/);
        await waitFor(() => expect(lastCall(calls, 'GET', /^\/admin\/menu$/)?.query).toMatchObject({ sort: 'text', direction: 'asc' }));
        await click(/^Texto/);
        await waitFor(() => expect(lastCall(calls, 'GET', /^\/admin\/menu$/)?.query).toMatchObject({ sort: 'text', direction: 'desc' }));
    });

    it('creates an item: a validation error first, then success', async () => {
        const { api, calls, items } = fakeMenu();
        render(PtahMenuAdmin, { props: { api, locale: 'pt_BR' } });
        await screen.findByText('Pedidos');

        await click('+ Novo Item');
        const dialog = await screen.findByRole('dialog', { name: 'Novo Item de Menu' });
        await click('Criar item');
        expect((await within(dialog).findByRole('alert')).textContent).toBe('The menu text is required.');
        expect(screen.getByLabelText(/Texto exibido/).getAttribute('aria-invalid')).toBe('true');

        await type(/Texto exibido/, 'Relatórios');
        await type('URL', 'javascript:alert(1)');
        await click('Criar item');
        await waitFor(() => expect(within(dialog).getByRole('alert').textContent).toContain('relative path'));

        await type('URL', '/reports');
        await type(/Ícone/, 'bx bx-bar-chart');
        expect(screen.getByTestId('menu-icon-preview').querySelector('i')?.className).toBe('bx bx-bar-chart');
        await click(/Grupo pai/);
        await click('Vendas', 'option');
        await click('Criar item');

        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(lastCall(calls, 'POST', /^\/admin\/menu$/)?.body).toMatchObject({ text: 'Relatórios', url: '/reports', icon: 'bx bx-bar-chart', parent_id: 1, type: 'menuLink', target: '_self', is_active: true });
        expect(items().some((item) => item.text === 'Relatórios')).toBe(true);
        expect(await screen.findByText('Item "Relatórios" criado.')).toBeTruthy();
    });

    it('a group has no URL field', async () => {
        render(PtahMenuAdmin, { props: { api: fakeMenu().api, locale: 'pt_BR' } });
        await screen.findByText('Pedidos');

        await click('+ Novo Item');
        expect(screen.getByLabelText('URL')).toBeTruthy();
        await fireEvent.click(screen.getByLabelText('Grupo (com sub-itens)'));
        expect(screen.queryByLabelText('URL')).toBeNull();
    });

    it('edits an item', async () => {
        const { api, calls } = fakeMenu();
        render(PtahMenuAdmin, { props: { api, locale: 'pt_BR' } });
        await screen.findByText('Pedidos');

        await click('Editar: Pedidos');
        await screen.findByRole('dialog', { name: 'Editar Item de Menu' });
        expect((screen.getByLabelText(/Texto exibido/) as HTMLInputElement).value).toBe('Pedidos');
        expect((screen.getByLabelText('URL') as HTMLInputElement).value).toBe('/orders');

        await type(/Texto exibido/, 'Pedidos de venda');
        await click(/Abertura/);
        await click('Nova aba', 'option');
        await click('Salvar alterações');

        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(lastCall(calls, 'PUT', /^\/admin\/menu\/2$/)?.body).toMatchObject({ text: 'Pedidos de venda', url: '/orders', parent_id: 1, target: '_blank' });
        expect(await screen.findByText('Pedidos de venda')).toBeTruthy();
    });

    it('deletes only after confirming', async () => {
        const { api, calls } = fakeMenu();
        render(PtahMenuAdmin, { props: { api, locale: 'pt_BR' } });
        await screen.findByText('Pedidos');

        await click('Excluir: Pedidos');
        await screen.findByRole('dialog', { name: 'Excluir item' });
        await click('Cancelar');
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(calls.some((call) => call.method === 'DELETE')).toBe(false);

        await click('Excluir: Pedidos');
        await click('Sim, excluir');
        await waitFor(() => expect(lastCall(calls, 'DELETE', /^\/admin\/menu\/2$/)).toBeTruthy());
        await waitFor(() => expect(screen.queryByText('Pedidos')).toBeNull());
    });

    it('toggles an item on and off', async () => {
        const { api, calls } = fakeMenu();
        render(PtahMenuAdmin, { props: { api, locale: 'pt_BR' } });
        await screen.findByText('Pedidos');

        await click('Clique para desativar: Pedidos');
        await waitFor(() => expect(lastCall(calls, 'POST', /^\/admin\/menu\/2\/toggle$/)).toBeTruthy());
        expect(await screen.findByRole('button', { name: 'Clique para ativar: Pedidos' })).toBeTruthy();
    });

    it('says when the user may not administer the menu', async () => {
        render(PtahMenuAdmin, { props: { api: fakeMenu({ forbidden: true }).api, locale: 'pt_BR' } });

        expect((await screen.findByRole('alert')).textContent).toContain('Você não tem permissão');
    });
});

describe('settings (Vue)', () => {
    it('shows the declared settings by group, their source and help, never a secret', async () => {
        render(PtahSettings, { props: { api: fakeMenu().api, locale: 'pt_BR' } });

        expect(await screen.findByRole('heading', { name: 'Financeiro' })).toBeTruthy();
        expect((screen.getByLabelText('Vencimento') as HTMLInputElement).value).toBe('30');
        expect(screen.getByLabelText('Vencimento').getAttribute('type')).toBe('number');
        expect(screen.getByText('Em dias.')).toBeTruthy();
        expect((screen.getByLabelText('Token') as HTMLInputElement).value).toBe('');
        expect(screen.getByLabelText('Token').getAttribute('type')).toBe('password');
        expect(screen.getByRole('switch', { name: 'Exige aprovação' })).toBeTruthy();
        expect(screen.getByText('Definido para esta empresa.')).toBeTruthy();
        expect(screen.getAllByText('Usando o padrão.').length).toBe(3);
    });

    it('saves: a validation error on its field, then success', async () => {
        const { api, calls, company } = fakeMenu();
        render(PtahSettings, { props: { api, locale: 'pt_BR' } });
        await screen.findByRole('heading', { name: 'Financeiro' });

        await type('Vencimento', '400');
        await click('Salvar');
        expect((await screen.findByRole('alert')).textContent).toContain('365');
        expect(screen.getByLabelText('Vencimento').getAttribute('aria-invalid')).toBe('true');

        await type('Vencimento', '15');
        await fireEvent.click(screen.getByRole('switch', { name: 'Exige aprovação' }));
        await click(/Transportadora/);
        await click('Jadlog', 'option');
        await click('Salvar');

        expect(await screen.findByText('Configurações salvas.')).toBeTruthy();
        expect(lastCall(calls, 'PUT', /^\/admin\/settings$/)?.body).toMatchObject({ scope: 'company', values: { invoice_due_days: '15', approval: true, carrier: 'jadlog', api_token: '' } });
        expect(company.invoice_due_days).toBe(15);
        expect(company.api_token).toBe('tok-secreto');
        await waitFor(() => expect(screen.queryByRole('alert')).toBeNull());
    });

    it('switches to the global values and resets a company value to them', async () => {
        const { api, calls } = fakeMenu();
        render(PtahSettings, { props: { api, locale: 'pt_BR' } });
        await screen.findByRole('heading', { name: 'Financeiro' });

        await click('Usar o valor global: Token');
        await waitFor(() => expect(lastCall(calls, 'POST', /^\/admin\/settings\/api_token\/reset$/)?.body).toEqual({ scope: 'company' }));
        await waitFor(() => expect(screen.queryByText('Definido para esta empresa.')).toBeNull());

        await click('Todas as empresas (global)');
        await waitFor(() => expect(lastCall(calls, 'GET', /^\/admin\/settings$/)?.query.scope).toBe('global'));
        await waitFor(() => expect(screen.queryByText('Usando o padrão.')).toBeNull());
        await type('Vencimento', '60');
        await click('Salvar');
        await waitFor(() => expect(lastCall(calls, 'PUT', /^\/admin\/settings$/)?.body).toMatchObject({ scope: 'global' }));
    });

    it('explains how to install, says when nothing is declared, and refuses a non-master', async () => {
        render(PtahSettings, { props: { api: fakeMenu({ installed: false }).api, locale: 'pt_BR' } });
        expect(await screen.findByText(/ptah:settings:install/)).toBeTruthy();
        cleanup();

        render(PtahSettings, { props: { api: fakeMenu({ noSettings: true }).api, locale: 'pt_BR' } });
        expect(await screen.findByText('Nenhuma configuração declarada ainda')).toBeTruthy();
        cleanup();

        render(PtahSettings, { props: { api: fakeMenu({ forbidden: true }).api, locale: 'pt_BR' } });
        expect((await screen.findByRole('alert')).textContent).toContain('Você não tem permissão');
    });
});
