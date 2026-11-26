import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PtahCrud from '../../vue/PtahCrud.vue';
import PtahLayout from '../../vue/PtahLayout.vue';
import { fakeApi, schema } from '../fake-api';

/** The form's dialog once its fields arrived (it opens at once, loading). */
async function readyDialog() {
    const dialog = await screen.findByRole('dialog');
    await waitFor(() => expect(dialog.getAttribute('aria-busy')).toBeNull());

    return dialog;
}


afterEach(cleanup);

const mount = async (props: Record<string, unknown> = {}) => {
    const fake = fakeApi();
    render(PtahCrud, { props: { screen: 'items', api: fake.api, locale: 'pt_BR', toastMs: 0, ...props } });
    await screen.findByText('Gama');

    return fake;
};

const rowNames = () =>
    screen
        .getAllByRole('row')
        .slice(1)
        .map((row) => within(row).getAllByRole('cell')[2]?.textContent);

describe('<PtahCrud> (Vue)', () => {
    it('draws the listing the server answered', async () => {
        await mount();

        // No title row: the page header above the screen names it, as on the Livewire screen.
        expect(screen.queryByRole('heading', { name: 'Itens' })).toBeNull();
        expect(rowNames()).toEqual(['Gama', 'Beta', 'Alfa']);
        expect(screen.getByText('Exibindo 1–3 de 3 registros')).toBeTruthy();
        expect(screen.getAllByText('Ativo').length).toBe(2);
    });

    it('reopens where the user left by default', async () => {
        const { calls } = await mount();

        expect(calls.some((call) => call.url.includes('restore=1'))).toBe(true);
    });

    it('searches as the user types', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        const { calls } = await mount();

        await fireEvent.update(screen.getByRole('searchbox'), 'bet');
        await vi.advanceTimersByTimeAsync(400);
        vi.useRealTimers();

        await waitFor(() => expect(rowNames()).toEqual(['Beta']));
        expect(calls.at(-1)?.url).toContain('search=bet');
        expect(calls.at(-1)?.url).toContain('remember=1');
    });

    it('sorts by a header and flips on the second click', async () => {
        await mount();

        await fireEvent.click(screen.getByRole('button', { name: /Nome/ }));
        await waitFor(() => expect(rowNames()).toEqual(['Alfa', 'Beta', 'Gama']));
        expect(screen.getByRole('columnheader', { name: /Nome/ }).getAttribute('aria-sort')).toBe('ascending');

        await fireEvent.click(screen.getByRole('button', { name: /Nome/ }));
        await waitFor(() => expect(rowNames()).toEqual(['Gama', 'Beta', 'Alfa']));
    });

    it('filters from the panel', async () => {
        await mount();

        await fireEvent.click(screen.getByRole('button', { name: 'Filtros' }));
        await fireEvent.update(await screen.findByLabelText('Situação'), 'archived');

        await waitFor(() => expect(rowNames()).toEqual(['Beta']));
    });

    it('opens the filters in a dialog when the screen says modal (1.62.0)', async () => {
        const before = schema.features;
        schema.features = { ...before, filterModal: true };
        try {
            await mount();
            await fireEvent.click(screen.getByRole('button', { name: 'Filtros' }));
            const dialog = await screen.findByRole('dialog', { name: 'Filtros' });
            await fireEvent.update(await within(dialog).findByLabelText('Situação'), 'archived');
            await waitFor(() => expect(rowNames()).toEqual(['Beta']));

            await fireEvent.keyDown(document, { key: 'Escape' });
            await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Filtros' })).toBeNull());

            // "Ver resultados" in the footer closes it too (1.62.1).
            await fireEvent.click(screen.getByRole('button', { name: 'Filtros' }));
            await fireEvent.click(within(await screen.findByRole('dialog', { name: 'Filtros' })).getByRole('button', { name: 'Ver resultados' }));
            await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Filtros' })).toBeNull());
        } finally {
            schema.features = before;
        }
    });

    it('creates a record, showing the server errors on the field', async () => {
        await mount();

        await fireEvent.click(screen.getByRole('button', { name: 'Novo' }));
        const dialog = await readyDialog();
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar' }));

        expect(await within(dialog).findByText('Nome is required.')).toBeTruthy();
        expect(within(dialog).getByLabelText(/Nome/).getAttribute('aria-invalid')).toBe('true');

        await fireEvent.update(within(dialog).getByLabelText(/Nome/), 'Delta');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(await screen.findByText('Delta')).toBeTruthy();
        expect(screen.getByText('Salvo!')).toBeTruthy();
    });

    it('masks a field while typing', async () => {
        await mount();

        await fireEvent.click(screen.getByRole('button', { name: 'Novo' }));
        const phone = (await screen.findByLabelText('Telefone')) as HTMLInputElement;
        await fireEvent.update(phone, '41988887777');

        expect(phone.value).toBe('(41) 98888-7777');
    });

    it('edits a record', async () => {
        const { items } = await mount();

        const row = screen.getByText('Alfa').closest('tr')!;
        await fireEvent.click(within(row).getByRole('button', { name: 'Editar' }));
        const dialog = await readyDialog();
        expect((within(dialog).getByLabelText(/Nome/) as HTMLInputElement).value).toBe('Alfa');

        await fireEvent.update(within(dialog).getByLabelText(/Nome/), 'Alfa 2');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar alterações' }));

        expect(await screen.findByText('Alfa 2')).toBeTruthy();
        expect(items().find((item) => item.id === 1)?.name).toBe('Alfa 2');
    });

    it('deletes only after the confirmation', async () => {
        const { items } = await mount();

        const row = screen.getByText('Beta').closest('tr')!;
        await fireEvent.click(within(row).getByRole('button', { name: 'Excluir' }));
        const confirm = await screen.findByRole('alertdialog');
        expect(items()).toHaveLength(3);

        await fireEvent.click(within(confirm).getByRole('button', { name: 'Excluir' }));

        await waitFor(() => expect(screen.queryByText('Beta')).toBeNull());
        expect(items()).toHaveLength(2);
    });

    it('selects rows and runs a bulk action', async () => {
        const { calls } = await mount();

        await fireEvent.click(screen.getByRole('checkbox', { name: 'Selecionar todas as linhas' }));
        expect(await screen.findByText('3 selecionado(s)')).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Arquivar' }));

        await waitFor(() => expect(calls.some((call) => call.url.endsWith('/bulk'))).toBe(true));
        expect(calls.find((call) => call.url.endsWith('/bulk'))?.body).toMatchObject({ action: 'archive' });
    });

    it('says when the screen is not allowed', async () => {
        const fake = fakeApi();
        render(PtahCrud, { props: { screen: 'closed', api: fake.api, locale: 'pt_BR' } });

        expect(await screen.findByText('Você não tem permissão para ver esta tela.')).toBeTruthy();
    });
});

describe('<PtahLayout> (Vue)', () => {
    it('draws the menu of the server, groups included, and the user', async () => {
        const fake = fakeApi();
        render(PtahLayout, { props: { api: fake.api, path: '/items', brand: 'ERP' }, slots: { default: '<p>conteúdo</p>' } });

        const link = await screen.findByRole('link', { name: 'Itens' });
        expect(link.getAttribute('aria-current')).toBe('page');
        expect(await screen.findByText('Ana')).toBeTruthy();
        expect(screen.getByText('conteúdo')).toBeTruthy();

        expect(screen.queryByRole('link', { name: 'Clientes' })).toBeNull();
        await fireEvent.click(screen.getByRole('button', { name: /Cadastros/ }));
        expect(screen.getByRole('link', { name: 'Clientes' })).toBeTruthy();
    });
});
