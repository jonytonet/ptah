import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { Fragment, useSyncExternalStore } from 'react';
import { afterEach, describe, expect, it } from 'vitest';
import { CrudController } from '../../core/controller';
import { applyFieldChange, startColumnResize } from '../../core/crud/extras';
import { PtahCrudColumnResizer, PtahCrudColumnsMenu, PtahCrudDetailRow, PtahCrudDetailToggle, PtahCrudFormChanges, PtahCrudFormDraft, PtahCrudSaveAndNew } from '../../react/crud/extras';
import { PtahCrud } from '../../react/PtahCrud';
import { draftKey, fakeExtras } from './extras.fake';

afterEach(() => {
    cleanup();
    localStorage.clear();
});

async function setup(options: Parameters<typeof fakeExtras>[0] = {}) {
    const server = fakeExtras(options);
    const crud = new CrudController('items', { api: server.api, toastMs: 0, remember: false });
    await crud.init();

    return { server, crud };
}

const toasts = (crud: CrudController) => crud.getState().toasts.map((toast) => `${toast.level}: ${toast.text}`);
const stored = (target: string) => JSON.parse(localStorage.getItem(draftKey(target)) ?? 'null') as { v: Record<string, unknown>; version: string } | null;
const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

describe('form draft (React)', () => {
    it('keeps what is typed, restores it on the next opening through the server, and clears it', async () => {
        const { server, crud } = await setup();
        localStorage.setItem('ptah:draft:someone-else:scr0001:new', JSON.stringify({ v: { name: 'x' }, version: '', at: Date.now(), ttl: 7 }));
        render(<PtahCrudFormDraft crud={crud} locale="pt_BR" />);

        await act(() => crud.openCreate());
        await waitFor(() => expect(server.calls.some((call) => call.path === '/form/draft')).toBe(true));
        // Another user's draft in this browser is purged.
        await waitFor(() => expect(localStorage.getItem('ptah:draft:someone-else:scr0001:new')).toBeNull());

        act(() => crud.setField('name', 'Rascunho'));
        await waitFor(() => expect(stored('new')?.v).toEqual({ name: 'Rascunho', status: 'active' }));
        expect(await screen.findByRole('button', { name: 'Limpar tudo' })).toBeTruthy();

        // Closing keeps it.
        act(() => crud.closeForm());
        expect(stored('new')).not.toBeNull();

        await act(() => crud.openCreate());
        await waitFor(() => expect(crud.getState().form?.data.name).toBe('Rascunho'));
        expect(toasts(crud)).toContain('info: Seu rascunho não salvo foi restaurado.');
        expect(server.calls.some((call) => (call.body?.draft as { values?: { name?: string } } | undefined)?.values?.name === 'Rascunho')).toBe(true);

        fireEvent.click(await screen.findByRole('button', { name: 'Limpar tudo' }));
        await waitFor(() => expect(crud.getState().form?.data.name).toBeNull());
        expect(stored('new')).toBeNull();
        await waitFor(() => expect(screen.queryByRole('button', { name: 'Limpar tudo' })).toBeNull());
    });

    it('forgets the draft once the record is saved', async () => {
        const { crud } = await setup();
        render(<PtahCrudFormDraft crud={crud} locale="pt_BR" />);

        await act(() => crud.openCreate());
        act(() => crud.setField('name', 'Novo'));
        await waitFor(() => expect(stored('new')).not.toBeNull());

        await act(() => crud.save());
        expect(crud.getState().form).toBeNull();
        expect(stored('new')).toBeNull();
    });

    it('discards the draft of a record that changed since, and reverts to the original', async () => {
        const { crud } = await setup();
        localStorage.setItem(draftKey('edit:1'), JSON.stringify({ v: { name: 'Velho', status: 'active' }, version: 'vOutro', at: Date.now(), ttl: 7 }));
        render(<PtahCrudFormDraft crud={crud} locale="pt_BR" />);

        await act(() => crud.openEdit(1));
        await waitFor(() => expect(toasts(crud)).toContain('warn: Este registro mudou desde o seu rascunho, e o rascunho foi descartado.'));
        expect(stored('edit:1')).toBeNull();
        expect(crud.getState().form?.data.name).toBe('Alfa');

        act(() => crud.setField('name', 'Alfa 2'));
        await waitFor(() => expect(stored('edit:1')?.version).toBe('vAlfa'));
        fireEvent.click(await screen.findByRole('button', { name: 'Voltar ao original' }));
        await waitFor(() => expect(crud.getState().form?.data.name).toBe('Alfa'));
        expect(stored('edit:1')).toBeNull();
    });

    it('offers the draft when typing already started, and restores or discards it', async () => {
        const { crud } = await setup();
        const draft = JSON.stringify({ v: { name: 'Guardado', status: 'archived' }, version: '', at: Date.now(), ttl: 7 });
        localStorage.setItem(draftKey('new'), draft);
        render(<PtahCrudFormDraft crud={crud} locale="pt_BR" />);

        await act(async () => {
            await crud.openCreate();
            crud.setField('name', 'Digitado');
        });
        expect(await screen.findByText('Encontramos um rascunho não salvo deste formulário.')).toBeTruthy();
        // While offered, what is typed does not overwrite it.
        await sleep(450);
        expect(stored('new')?.v.name).toBe('Guardado');

        fireEvent.click(screen.getByRole('button', { name: 'Restaurar rascunho' }));
        await waitFor(() => expect(crud.getState().form?.data).toMatchObject({ name: 'Guardado', status: 'archived' }));
        act(() => crud.closeForm());

        localStorage.setItem(draftKey('new'), draft);
        await act(async () => {
            await crud.openCreate();
            crud.setField('name', 'Outro');
        });
        fireEvent.click(await screen.findByRole('button', { name: 'Descartar rascunho' }));
        expect(crud.getState().form?.data.name).toBe('Outro');
        await waitFor(() => expect(stored('new')?.v.name).toBe('Outro'));
    });

    it('draws nothing and stores nothing when the screen has no draft', async () => {
        const { server, crud } = await setup({ features: false });
        const { container } = render(<PtahCrudFormDraft crud={crud} locale="pt_BR" />);

        await act(() => crud.openCreate());
        act(() => crud.setField('name', 'X'));
        await sleep(450);
        expect(container.innerHTML).toBe('');
        expect(localStorage.length).toBe(0);
        expect(server.calls.some((call) => call.path === '/form/draft')).toBe(false);
    });
});

describe('form behaviours (React)', () => {
    it('saves and opens a blank form again; a refused save keeps the form', async () => {
        const { server, crud } = await setup();
        const { container } = render(<PtahCrudSaveAndNew crud={crud} locale="pt_BR" />);
        expect(container.innerHTML).toBe('');

        await act(() => crud.openCreate());
        fireEvent.click(screen.getByRole('button', { name: 'Salvar e criar outro' }));
        await waitFor(() => expect(crud.getState().form?.errors.name).toBe('O campo Nome é obrigatório.'));

        act(() => crud.setField('name', 'Delta'));
        fireEvent.click(screen.getByRole('button', { name: 'Salvar e criar outro' }));
        await waitFor(() => expect(server.items().some((item) => item.name === 'Delta')).toBe(true));
        await waitFor(() => expect(crud.getState().form).toMatchObject({ mode: 'create', data: { name: null } }));

        // Not on an edit.
        await act(() => crud.openEdit(1));
        expect(screen.queryByRole('button', { name: 'Salvar e criar outro' })).toBeNull();
    });

    it('runs the field formula on the server when a trigger field changes', async () => {
        const { server, crud } = await setup();
        render(<PtahCrudFormChanges crud={crud} />);
        await act(() => crud.openCreate());
        await waitFor(() => expect(server.calls.some((call) => call.path === '/form/triggers')).toBe(true));

        act(() => crud.setField('name', 'Arquivar'));
        await waitFor(() => expect(crud.getState().form?.data.status).toBe('archived'));
        expect(server.calls.filter((call) => call.path === '/form/change')).toHaveLength(1);
        expect(server.calls.find((call) => call.path === '/form/change')?.body).toMatchObject({ field: 'name', data: { name: 'Arquivar' } });

        // status is not a trigger; the patch applied did not trigger again.
        act(() => crud.setField('status', 'active'));
        await sleep(500);
        expect(server.calls.filter((call) => call.path === '/form/change')).toHaveLength(1);
    });

    it('says when the server could not answer a change', async () => {
        const { crud } = await setup();
        await crud.openCreate();
        crud.setField('name', 'Quebrar');

        await applyFieldChange(crud, 'name');
        expect(toasts(crud)).toContain('danger: Server Error');
    });
});

function DetailTable({ crud }: { crud: CrudController }) {
    const state = useSyncExternalStore(crud.subscribe, crud.getState);

    return (
        <table>
            <tbody>
                {state.rows.map((row) => (
                    <Fragment key={row.id}>
                        <tr>
                            <td>
                                <PtahCrudDetailToggle crud={crud} row={row} locale="pt_BR" />
                            </td>
                            <td>{String(row.cells.name.value)}</td>
                        </tr>
                        <PtahCrudDetailRow crud={crud} row={row} colSpan={2} locale="pt_BR" renderDetail={(detail) => <p>child:{detail.screen}</p>} />
                    </Fragment>
                ))}
            </tbody>
        </table>
    );
}

describe('master/detail (React)', () => {
    it('expands a row lazily into the child screens the server named, and keeps them while collapsed', async () => {
        const { server, crud } = await setup();
        render(<DetailTable crud={crud} />);
        const toggles = screen.getAllByRole('button', { name: 'Ver detalhes' });
        // Rows are listed 1, 2, 3.
        const detailsCalls = () => server.calls.filter((call) => call.path.startsWith('/1/details')).length;
        expect(detailsCalls()).toBe(0);

        fireEvent.click(toggles[0]);
        expect(toggles[0].getAttribute('aria-expanded')).toBe('true');
        expect(screen.getByRole('status', { name: 'Carregando…' })).toBeTruthy();
        const region = await screen.findByRole('region', { name: 'Linhas' });
        expect(region.textContent).toBe('child:items..1.0');

        const before = detailsCalls();
        fireEvent.click(toggles[0]);
        expect(screen.queryByRole('region', { name: 'Linhas' })).toBeNull();
        fireEvent.click(toggles[0]);
        expect(screen.getByRole('region', { name: 'Linhas' })).toBeTruthy();
        expect(detailsCalls()).toBe(before);
    });

    it('shows the unavailable, empty and error states', async () => {
        const { crud } = await setup();
        render(<DetailTable crud={crud} />);
        const toggles = screen.getAllByRole('button', { name: 'Ver detalhes' });

        fireEvent.click(toggles[1]);
        const notes = await screen.findByRole('region', { name: 'Notas' });
        expect(within(notes).getByText('Você não pode ver estes registros.')).toBeTruthy();
        expect(notes.textContent).not.toContain('child:');

        fireEvent.click(toggles[2]);
        expect((await screen.findByRole('alert')).textContent).toContain('Não foi possível carregar os detalhes.');
        expect(screen.getByRole('button', { name: 'Tentar novamente' })).toBeTruthy();

        cleanup();
        const empty = await setup({ emptyDetails: true });
        render(<DetailTable crud={empty.crud} />);
        fireEvent.click(screen.getAllByRole('button', { name: 'Ver detalhes' })[1]);
        expect(await screen.findByText('Nenhum registro encontrado')).toBeTruthy();
    });

    it('draws nothing when the screen has no master/detail', async () => {
        const { crud } = await setup({ features: false });
        render(<DetailTable crud={crud} />);
        expect(screen.queryByRole('button', { name: 'Ver detalhes' })).toBeNull();
    });

    it('mounts a whole child PtahCrud under the expanded row of PtahCrud (no import cycle)', async () => {
        const server = fakeExtras();
        render(<PtahCrud screen="items" api={server.api} locale="pt_BR" remember={false} toastMs={0} />);
        await screen.findByText('Alfa');

        fireEvent.click(screen.getAllByRole('button', { name: 'Ver detalhes' })[0]);
        const region = await screen.findByRole('region', { name: 'Linhas' });
        // The child screen's own listing, title, search and create — on its own key.
        expect(await within(region).findByText('L1')).toBeTruthy();
        expect(within(region).getByText('L2')).toBeTruthy();
        expect(within(region).queryByText('L3')).toBeNull();
        expect(within(region).getByText('Linhas', { selector: '.pc-detail-title' })).toBeTruthy();
        expect(server.calls.some((call) => call.path === '..1.0/schema')).toBe(true);

        fireEvent.click(within(region).getByRole('button', { name: /Novo/ }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.change(await within(dialog).findByLabelText(/Linha/), { target: { value: 'L9' } });
        fireEvent.click(within(dialog).getByRole('button', { name: /^(Criar|Salvar)$/ }));
        await waitFor(() => expect(server.lines().some((line) => line.name === 'L9' && line.parent === 1)).toBe(true));
        expect(await within(region).findByText('L9')).toBeTruthy();
    });
});

describe('columns (React)', () => {
    it('shows, hides, reorders from the keyboard and resets the columns', async () => {
        const { server, crud } = await setup();
        render(<PtahCrudColumnsMenu crud={crud} locale="pt_BR" />);
        const puts = () => server.calls.filter((call) => call.path === '/preferences' && call.method === 'PUT').map((call) => call.body);

        fireEvent.click(screen.getByRole('button', { name: /Colunas/ }));
        expect(await screen.findByLabelText('Situação')).toBeTruthy();

        fireEvent.click(screen.getByLabelText('Situação'));
        await waitFor(() => expect(puts()).toContainEqual({ columns: { status: false } }));
        expect(await screen.findByRole('button', { name: 'Colunas (1 ocultas)' })).toBeTruthy();
        expect((screen.getByLabelText('Situação') as HTMLInputElement).checked).toBe(false);

        // id is not in the menu but keeps its place.
        expect((screen.getByRole('button', { name: 'Mover Nome para cima' }) as HTMLButtonElement).disabled).toBe(true);
        fireEvent.click(screen.getByRole('button', { name: 'Mover Valor para cima' }));
        await waitFor(() => expect(puts()).toContainEqual({ column_order: ['id', 'name', 'amount', 'status'] }));
        await waitFor(() => expect(screen.getAllByRole('checkbox').map((box) => box.parentElement?.textContent)).toEqual(['Nome', 'Valor', 'Situação']));

        fireEvent.click(screen.getByRole('button', { name: 'Restaurar padrão' }));
        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByText(/esquecer a ordem/)).toBeTruthy();
        fireEvent.click(within(dialog).getByRole('button', { name: 'Restaurar' }));
        await waitFor(() => expect(server.calls.some((call) => call.path === '/columns/reset')).toBe(true));
        await waitFor(() => expect(screen.queryByRole('button', { name: 'Colunas (1 ocultas)' })).toBeNull());

        fireEvent.click(screen.getByRole('button', { name: 'Ocultar todas' }));
        await waitFor(() => expect(puts()).toContainEqual({ columns: { id: false, name: false, status: false, amount: false } }));
    });

    it('says when the columns could not be loaded', async () => {
        const { crud } = await setup({ failColumns: true });
        render(<PtahCrudColumnsMenu crud={crud} locale="pt_BR" />);

        fireEvent.click(screen.getByRole('button', { name: /Colunas/ }));
        expect((await screen.findByRole('alert')).textContent).toBe('Não foi possível carregar as colunas.');
        expect(screen.getByRole('button', { name: 'Tentar novamente' })).toBeTruthy();
    });

    it('resizes a column from the keyboard and forgets a width on double-click', async () => {
        const { server, crud } = await setup();
        render(
            <table>
                <thead>
                    <tr>
                        <th style={{ position: 'relative' }}>
                            Nome
                            <PtahCrudColumnResizer crud={crud} field="name" label="Nome" locale="pt_BR" />
                        </th>
                    </tr>
                </thead>
            </table>,
        );
        const handle = screen.getByRole('separator', { name: 'Redimensionar coluna Nome' });

        fireEvent.keyDown(handle, { key: 'ArrowRight' });
        await waitFor(() => expect(server.calls.some((call) => call.method === 'PUT' && JSON.stringify(call.body) === '{"column_widths":{"name":130}}')).toBe(true));
        await waitFor(() => expect(handle.getAttribute('aria-valuenow')).toBe('130'));

        fireEvent.doubleClick(handle);
        await waitFor(() => expect(server.calls.some((call) => call.path === '/columns/reset' && call.body?.field === 'name')).toBe(true));
        await waitFor(() => expect(handle.getAttribute('aria-valuenow')).toBeNull());
    });

    it('drags a header edge into a width (core helper)', () => {
        document.body.innerHTML = '<table><tr><th><span id="edge"></span></th></tr></table>';
        const handle = document.getElementById('edge')!;
        let committed = 0;

        startColumnResize(new MouseEvent('mousedown', { clientX: 100, button: 0 }), { handle, onCommit: (width) => (committed = width) });
        document.dispatchEvent(new MouseEvent('mousemove', { clientX: 230 }));
        expect(handle.closest('th')!.style.width).toBe('130px');
        document.dispatchEvent(new MouseEvent('mouseup'));
        expect(committed).toBe(130);

        // Never under the minimum.
        startColumnResize(new MouseEvent('mousedown', { clientX: 100, button: 0 }), { handle, th: document.createElement('th'), onCommit: (width) => (committed = width) });
        document.dispatchEvent(new MouseEvent('mousemove', { clientX: 10 }));
        document.dispatchEvent(new MouseEvent('mouseup'));
        expect(committed).toBe(60);
    });
});
