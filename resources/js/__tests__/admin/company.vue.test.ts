import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PtahCompanies, PtahCompanySwitcher, PtahDepartments } from '../../vue/admin/company';
import { fakeCompany } from './company.fake';

afterEach(cleanup);

const type = (label: RegExp | string, value: string) => fireEvent.update(screen.getByLabelText(label), value);
const rowOf = (text: string) => screen.getByText(text).closest('tr') as HTMLElement;

describe('companies screen (Vue)', () => {
    it('lists the companies; the default one cannot be deleted; a search with no match shows the empty state', async () => {
        const fake = fakeCompany();
        render(PtahCompanies, { props: { api: fake.api, locale: 'pt_BR' } });

        expect(screen.getByRole('status').textContent).toContain('Carregando');
        await screen.findByText('Matriz');
        expect(screen.getByRole('heading', { name: 'Empresas' })).toBeTruthy();
        expect(within(rowOf('Matriz')).getByText('PADRÃO')).toBeTruthy();
        expect(within(rowOf('Matriz')).getByText('CNPJ 11.111.111/0001-11')).toBeTruthy();
        expect(within(rowOf('Matriz')).queryByRole('button', { name: 'Excluir' })).toBeNull();
        expect(within(rowOf('Filial Sul')).getByText('Inativo')).toBeTruthy();

        await fireEvent.update(screen.getByRole('searchbox', { name: 'Buscar' }), 'nada-disso');
        expect(await screen.findByText('Nenhuma empresa encontrada')).toBeTruthy();
        expect(screen.getByText('Ajuste o filtro de busca')).toBeTruthy();
        expect(fake.calls.at(-1)?.query.get('search')).toBe('nada-disso');
    });

    it('creates a company: a validation error stays on its field, then it saves', async () => {
        const fake = fakeCompany();
        render(PtahCompanies, { props: { api: fake.api, locale: 'pt_BR' } });
        await screen.findByText('Matriz');

        await fireEvent.click(screen.getByRole('button', { name: /Nova Empresa/ }));
        const dialog = await screen.findByRole('dialog');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar' }));
        await waitFor(() => expect(screen.getByLabelText(/^Nome/).getAttribute('aria-invalid')).toBe('true'));
        expect(within(dialog).getByRole('alert').textContent).toContain('obrigatório');

        await type(/^Nome/, 'Loja Norte');
        await type(/^Sigla/, 'mtz');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar' }));
        await waitFor(() => expect(screen.getByLabelText(/^Sigla/).getAttribute('aria-invalid')).toBe('true'));

        await type(/^Sigla/, 'ln');
        await fireEvent.click(screen.getByRole('switch', { name: 'Empresa padrão' }));
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(await screen.findByText('Loja Norte')).toBeTruthy();
        expect(screen.getByText('Company created successfully!')).toBeTruthy();
        expect(fake.calls.filter((call) => call.method === 'POST' && call.body?.name === 'Loja Norte').at(-1)?.body).toMatchObject({ label: 'ln', is_default: true, is_active: true, tax_type: 'cnpj' });
    });

    it('edits a company with what the server holds', async () => {
        const fake = fakeCompany();
        render(PtahCompanies, { props: { api: fake.api, locale: 'pt_BR' } });
        await screen.findByText('Matriz');

        await fireEvent.click(within(rowOf('Matriz')).getByRole('button', { name: 'Editar' }));
        const dialog = await screen.findByRole('dialog', { name: 'Editar Empresa' });
        expect((screen.getByLabelText(/^E-mail/) as HTMLInputElement).value).toBe('contato@matriz.test');

        await type(/^Nome/, 'Matriz Central');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar' }));

        expect(await screen.findByText('Matriz Central')).toBeTruthy();
        expect(fake.calls.some((call) => call.method === 'PUT' && call.path === '/admin/companies/1')).toBe(true);
    });

    it('asks before deleting, and a refusal of the server is shown', async () => {
        const fake = fakeCompany();
        render(PtahCompanies, { props: { api: fake.api, locale: 'pt_BR' } });
        await screen.findByText('Filial Sul');

        await fireEvent.click(within(rowOf('Filial Sul')).getByRole('button', { name: 'Excluir' }));
        const dialog = await screen.findByRole('dialog', { name: 'Confirmar exclusão' });
        expect(dialog.textContent).toContain('Esta ação não pode ser desfeita.');
        expect(fake.calls.some((call) => call.method === 'DELETE')).toBe(false);

        await fireEvent.click(within(dialog).getByRole('button', { name: 'Excluir' }));
        await waitFor(() => expect(screen.queryByText('Filial Sul')).toBeNull());
        expect(screen.getByText('Company deleted.')).toBeTruthy();
    });

    it('sorts by name and pages through the server', async () => {
        const fake = fakeCompany({ companies: 25 });
        render(PtahCompanies, { props: { api: fake.api, locale: 'pt_BR' } });
        await screen.findByText('Matriz');
        expect(screen.getByText('1–20 de 27')).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: '2' }));
        expect(await screen.findByText('21–27 de 27')).toBeTruthy();
        expect(fake.calls.at(-1)?.query.get('page')).toBe('2');

        await fireEvent.click(screen.getByRole('button', { name: /^Nome/ }));
        await waitFor(() => expect(fake.calls.at(-1)?.query.get('direction')).toBe('desc'));
        expect(fake.calls.at(-1)?.query.get('page')).toBe('1');
        await waitFor(() => expect(screen.getAllByRole('row')[1].textContent).toContain('Zeta 24'));
    });

    it('a user the server refuses sees why', async () => {
        render(PtahCompanies, { props: { api: fakeCompany({ forbidden: true }).api, locale: 'pt_BR' } });

        expect((await screen.findByRole('alert')).textContent).toContain('não tem permissão');
    });
});

describe('departments screen (Vue)', () => {
    it('lists, creates (after a validation error), edits and deletes with confirmation', async () => {
        const fake = fakeCompany();
        render(PtahDepartments, { props: { api: fake.api, locale: 'pt_BR' } });

        await screen.findByText('Vendas');
        expect(within(rowOf('Vendas')).getByText('3')).toBeTruthy();
        expect(within(rowOf('Compras')).getByText('Inativo')).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: /Novo Departamento/ }));
        let dialog = await screen.findByRole('dialog');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar' }));
        await waitFor(() => expect(screen.getByLabelText(/^Nome/).getAttribute('aria-invalid')).toBe('true'));
        await type(/^Nome/, 'RH');
        await type(/Descrição/, 'Pessoas');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar' }));
        expect(await screen.findByText('RH')).toBeTruthy();
        expect(screen.getByText('Pessoas')).toBeTruthy();

        await fireEvent.click(within(rowOf('Vendas')).getByRole('button', { name: 'Editar' }));
        dialog = await screen.findByRole('dialog', { name: 'Editar Departamento' });
        expect((screen.getByLabelText(/Descrição/) as HTMLTextAreaElement).value).toBe('Time comercial');
        await fireEvent.click(screen.getByRole('switch', { name: 'Departamento ativo' }));
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar' }));
        await waitFor(() => expect(within(rowOf('Vendas')).getByText('Inativo')).toBeTruthy());

        await fireEvent.click(within(rowOf('Compras')).getByRole('button', { name: 'Excluir' }));
        dialog = await screen.findByRole('dialog', { name: 'Confirmar exclusão' });
        expect(dialog.textContent).toContain('perderão o departamento');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Excluir' }));
        await waitFor(() => expect(screen.queryByText('Compras')).toBeNull());
    });
});

describe('company switcher (Vue)', () => {
    it('shows the active company and switches to another', async () => {
        const fake = fakeCompany();
        const switched = vi.fn();
        render(PtahCompanySwitcher, { props: { api: fake.api, locale: 'pt_BR', onSwitched: switched } });

        const tab = await screen.findByRole('tab', { name: 'LC' });
        expect(screen.getByText('Matriz')).toBeTruthy();
        expect(screen.getByRole('tab', { name: 'MTZ' }).getAttribute('aria-selected')).toBe('true');

        await fireEvent.click(tab);
        await waitFor(() => expect(switched).toHaveBeenCalledWith(3));
        expect(fake.activeId()).toBe(3);
        expect(screen.getByRole('tab', { name: 'LC' }).getAttribute('aria-selected')).toBe('true');
    });

    it('a refused switch is told, not followed', async () => {
        const fake = fakeCompany();
        const switched = vi.fn();
        render(PtahCompanySwitcher, { props: { api: fake.api, locale: 'pt_BR', layout: 'stacked', onSwitched: switched } });

        const item = await screen.findByRole('button', { name: /Loja Centro/ });
        expect(screen.getByRole('group', { name: 'Selecionar empresa' })).toBeTruthy();
        expect(screen.getByRole('button', { name: /Matriz/ }).getAttribute('aria-current')).toBe('true');

        // The membership changed on the server after the list was drawn.
        fake.setSwitchable([{ id: 1, name: 'Matriz', label: 'MTZ', is_default: true }]);
        await fireEvent.click(item);
        await waitFor(() => expect(fake.calls.some((call) => call.method === 'POST')).toBe(true));
        expect(switched).not.toHaveBeenCalled();
        expect(fake.activeId()).toBe(1);
    });

    it('draws nothing with a single company', async () => {
        const fake = fakeCompany({ switchable: [{ id: 1, name: 'Matriz', label: 'MTZ', is_default: true }] });
        const { container } = render(PtahCompanySwitcher, { props: { api: fake.api, locale: 'pt_BR' } });

        await waitFor(() => expect(fake.calls.length).toBe(1));
        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(container.querySelector('button')).toBeNull();
        expect(container.textContent).toBe('');
    });
});
