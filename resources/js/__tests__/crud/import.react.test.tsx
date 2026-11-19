import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import type { CrudController } from '../../core/controller';
import { PtahCrudImport, PtahCrudImportButton } from '../../react/crud/import';
import { fakeImport } from './import.fake';

afterEach(cleanup);

const csv = (name: string) => new File(['SKU,Nome,Situação\n'], name, { type: 'text/csv' });
const click = (name: string | RegExp) => fireEvent.click(screen.getByRole('button', { name }));
const dialog = () => screen.getByRole('dialog');

async function mount(options: Parameters<typeof fakeImport>[0] = {}) {
    const server = fakeImport(options);
    const crud: CrudController = await server.crud();
    render(
        <>
            <PtahCrudImportButton crud={crud} locale="pt_BR" />
            <PtahCrudImport crud={crud} locale="pt_BR" />
        </>,
    );

    return { server, crud };
}

async function open() {
    click('Importar');
    await screen.findByText(/Até 2000 linhas/);
}

async function choose(name: string) {
    fireEvent.change(screen.getByLabelText('Arquivo da planilha'), { target: { files: [csv(name)] } });
}

describe('import (React)', () => {
    it('runs file → columns → review → done, reloads the listing and toasts', async () => {
        const { server, crud } = await mount();
        await open();

        expect(within(dialog()).getByRole('heading', { name: 'Importar planilha' })).toBeTruthy();
        expect(within(dialog()).getAllByRole('listitem')[0].getAttribute('aria-current')).toBe('step');
        expect(screen.getByLabelText('Arquivo da planilha').getAttribute('accept')).toBe('.csv,.txt,.xlsx,.xls');

        await choose('produtos.csv');
        // Columns: the server's automatic mapping, the third left as Ignore.
        expect(((await screen.findByLabelText('SKU')) as HTMLSelectElement).value).toBe('sku');
        expect((screen.getByLabelText('Nome') as HTMLSelectElement).value).toBe('name');
        expect((screen.getByLabelText('Situação') as HTMLSelectElement).value).toBe('');
        expect(server.calls.find((call) => call.method === 'POST' && call.path === '/crud/items/import')?.file).toBe('produtos.csv');

        // A required field without a column: the review says which and offers no import.
        click('Avançar');
        expect(await screen.findByText('Campos obrigatórios sem coluna: Situação')).toBeTruthy();
        expect(screen.queryByRole('button', { name: /^Importar \d+ linhas$/ })).toBeNull();

        // Back keeps the mapping.
        click('Voltar');
        expect((screen.getByLabelText('SKU') as HTMLSelectElement).value).toBe('sku');
        fireEvent.change(screen.getByLabelText('Situação'), { target: { value: 'status' } });
        click('Avançar');
        expect(await screen.findByText('2 de 2 linhas prontas para importar.')).toBeTruthy();
        expect(server.calls.at(-1)?.body).toEqual({ token: server.token, mapping: { 0: 'sku', 1: 'name', 2: 'status' } });

        const before = server.listings();
        click('Importar 2 linhas');
        expect(await screen.findByText('Importação concluída: 2 criados, 0 atualizados.')).toBeTruthy();
        await waitFor(() => expect(server.listings()).toBe(before + 1));
        expect(crud.getState().toasts.map((toast) => toast.text)).toEqual(['Importação concluída: 2 criados, 0 atualizados.']);

        // Done: the token is spent, so closing does not discard anything.
        // The ✕ of the header is also "Fechar" (modal_close), as in the Blade: the footer button is the last one.
        fireEvent.click(screen.getAllByRole('button', { name: 'Fechar' }).at(-1)!);
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(crud.getState().panel).toBeNull();
        expect(server.calls.some((call) => call.method === 'DELETE')).toBe(false);
    });

    it('lists the rows with problems by line and does not offer the import', async () => {
        await mount();
        await open();
        await choose('ruim.csv');
        fireEvent.change(await screen.findByLabelText('Situação'), { target: { value: 'status' } });
        click('Avançar');

        expect(await screen.findByText('1 de 2 linhas prontas para importar.')).toBeTruthy();
        const table = screen.getByRole('table', { name: 'Linhas com problema' });
        const cells = within(table).getAllByRole('cell').map((cell) => cell.textContent);
        expect(cells).toEqual(['3', 'Situação', 'O campo Situação é inválido.']);
        expect(screen.getByText(/Corrija essas linhas na planilha/)).toBeTruthy();
        expect(screen.queryByRole('button', { name: /^Importar \d+ linhas$/ })).toBeNull();
    });

    it('keeps a refused file on step 1 with the reason, and a new choice goes on', async () => {
        await mount();
        await open();
        await choose('vazio.csv');

        expect((await screen.findByRole('alert')).textContent).toContain('O arquivo não tem linhas de dados');
        expect(screen.getByLabelText('Arquivo da planilha').getAttribute('aria-invalid')).toBe('true');

        await choose('produtos.csv');
        expect(await screen.findByLabelText('SKU')).toBeTruthy();
        expect(screen.queryByRole('alert')).toBeNull();
    });

    it('a database refusal is reported on the last step and nothing reloads', async () => {
        const { server } = await mount({ dbRefuses: true });
        await open();
        await choose('produtos.csv');
        fireEvent.change(await screen.findByLabelText('Situação'), { target: { value: 'status' } });
        click('Avançar');
        await screen.findByRole('button', { name: 'Importar 2 linhas' });
        const before = server.listings();
        click('Importar 2 linhas');

        expect((await screen.findByRole('alert')).textContent).toContain('a linha 3 foi recusada');
        expect(server.listings()).toBe(before);
    });

    it('an expired upload sends the user back to choose the file again', async () => {
        await mount({ expired: true });
        await open();
        await choose('produtos.csv');
        await screen.findByLabelText('SKU');
        click('Avançar');

        expect((await screen.findByRole('alert')).textContent).toContain('não está mais disponível');
        expect(screen.getByLabelText('Arquivo da planilha')).toBeTruthy();
    });

    it('closing midway discards the stored file; Back on the columns starts over', async () => {
        const { server, crud } = await mount();
        await open();
        await choose('produtos.csv');
        await screen.findByLabelText('SKU');

        click('Voltar');
        expect(await screen.findByLabelText('Arquivo da planilha')).toBeTruthy();
        await waitFor(() => expect(server.calls.filter((call) => call.method === 'DELETE')).toHaveLength(1));

        await choose('produtos.csv');
        await screen.findByLabelText('SKU');
        await act(async () => {
            fireEvent.keyDown(document, { key: 'Escape' });
        });
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(crud.getState().panel).toBeNull();
        expect(server.calls.filter((call) => call.method === 'DELETE')).toHaveLength(2);
        expect(server.calls.at(-1)?.path).toBe(`/crud/items/import/${server.token}`);
    });

    it('shows a failure to load the limits', async () => {
        await mount({ failInfo: true });
        click('Importar');

        expect((await screen.findByRole('alert')).textContent).toContain('Server Error');
        expect(screen.queryByLabelText('Arquivo da planilha')).toBeNull();
    });

    it('renders nothing when the screen has no import, even with the panel open', async () => {
        const { crud } = await mount({ off: true });

        expect(screen.queryByRole('button', { name: 'Importar' })).toBeNull();
        act(() => crud.openPanel('import'));
        expect(screen.queryByRole('dialog')).toBeNull();
    });
});
