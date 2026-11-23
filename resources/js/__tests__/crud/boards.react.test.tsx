import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { resolveView, rowNavigator } from '../../core/crud/boards';
import { PtahCrudCalendar, PtahCrudCards, PtahCrudKanban, PtahCrudViewSwitch } from '../../react/crud/boards';
import { PtahCrud } from '../../react/PtahCrud';
import { fakeBoards, toasts } from './boards.fake';

afterEach(cleanup);

const column = (name: string) => screen.getByRole('region', { name });
const transfer = () => {
    const data: Record<string, string> = {};

    return { setData: (type: string, value: string) => (data[type] = value), getData: (type: string) => data[type] ?? '', dropEffect: 'move', effectAllowed: 'all' };
};

describe('boards (React)', () => {
    it('switches the view mode and the density through the preferences', async () => {
        const server = fakeBoards();
        const crud = await server.controller();
        render(<PtahCrudViewSwitch crud={crud} locale="pt_BR" />);

        const group = screen.getByRole('group', { name: 'Modo de visualização' });
        expect(within(group).getAllByRole('button').map((button) => button.getAttribute('aria-label'))).toEqual([
            'Automático (tabela no desktop, cards no celular)',
            'Visão em tabela',
            'Visão em cards',
            'Quadro',
            'Calendário',
        ]);
        expect(screen.getByRole('button', { name: /Automático/ }).getAttribute('aria-pressed')).toBe('true');

        fireEvent.click(screen.getByRole('button', { name: 'Quadro' }));
        await waitFor(() => expect(screen.getByRole('button', { name: 'Quadro' }).getAttribute('aria-pressed')).toBe('true'));
        expect(server.calls.find((call) => call.method === 'PUT')?.body).toEqual({ view_mode: 'kanban' });

        fireEvent.click(screen.getByRole('button', { name: 'Densidade' }));
        fireEvent.click(screen.getByRole('button', { name: /Compacto/ }));
        await waitFor(() => expect(server.preferences().view_density).toBe('compact'));
        expect(screen.queryByRole('button', { name: /Compacto/ })).toBeNull();
    });

    it('offers only the modes the screen has', async () => {
        const crud = await fakeBoards({ kanban: false, calendar: false }).controller();
        render(<PtahCrudViewSwitch crud={crud} locale="pt_BR" />);

        expect(screen.queryByRole('button', { name: 'Quadro' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Calendário' })).toBeNull();
        expect(screen.getByRole('button', { name: 'Visão em cards' })).toBeTruthy();
    });

    it('draws the board of the current listing, with locked and other-values columns', async () => {
        const server = fakeBoards();
        const crud = await server.controller();
        render(<PtahCrudKanban crud={crud} locale="pt_BR" />);

        expect(await screen.findByText('Orçar cliente A')).toBeTruthy();
        expect(within(column('A fazer')).getByText('urgente')).toBeTruthy();
        expect(within(column('Feito')).getByText('Concluir é feito pelo painel do setor.')).toBeTruthy();
        expect(column('Feito').hasAttribute('data-ptah-kanban-locked')).toBe(true);
        expect(within(column('Outros valores')).getByText(/não é uma das opções/)).toBeTruthy();

        // The board follows the listing state.
        await act(() => crud.patchList({ search: 'Entregar' }));
        await waitFor(() => expect(screen.queryByText('Orçar cliente A')).toBeNull());
        expect(server.calls.filter((call) => call.path === '/crud/tasks/kanban').at(-1)?.query.get('search')).toBe('Entregar');
        expect(within(column('A fazer')).getByText('Nada aqui')).toBeTruthy();
    });

    it('moves a card from the keyboard to the allowed columns only', async () => {
        const server = fakeBoards();
        const crud = await server.controller();
        render(<PtahCrudKanban crud={crud} locale="pt_BR" />);
        await screen.findByText('Orçar cliente A');

        const select = within(column('A fazer')).getByLabelText('Mover para') as HTMLSelectElement;
        // 1.61.0: a disabled "Mover para…" first, then only the OTHER columns.
        expect([...select.options].map((option) => option.text)).toEqual(['Mover para…', 'Fazendo']);
        // A locked column lets no card out: no "Move to" there.
        expect(within(column('Feito')).queryByLabelText('Mover para')).toBeNull();

        fireEvent.change(select, { target: { value: 'doing' } });
        await waitFor(() => expect(within(column('Fazendo')).getByText('Orçar cliente A')).toBeTruthy());
        expect(server.calls.find((call) => call.path === '/crud/tasks/1/move')?.body).toEqual({ value: 'doing' });
        await waitFor(() => expect(toasts(crud)).toContain('Registro salvo com sucesso'));
        expect(server.tasks().find((task) => task.id === 1)?.status).toBe('doing');
    });

    it('puts the card back and shows the reason when the server refuses', async () => {
        const server = fakeBoards({ refuseMoves: true });
        const crud = await server.controller();
        render(<PtahCrudKanban crud={crud} locale="pt_BR" />);
        await screen.findByText('Orçar cliente A');

        fireEvent.change(within(column('A fazer')).getByLabelText('Mover para'), { target: { value: 'doing' } });

        await waitFor(() => expect(toasts(crud)).toContain('Recusado pelo servidor.'));
        expect(within(column('A fazer')).getByText('Orçar cliente A')).toBeTruthy();
        expect(within(column('Fazendo')).queryByText('Orçar cliente A')).toBeNull();
        expect(server.tasks().find((task) => task.id === 1)?.status).toBe('todo');
    });

    it('drags a card onto an allowed column, and nowhere else', async () => {
        const server = fakeBoards();
        const crud = await server.controller();
        render(<PtahCrudKanban crud={crud} locale="pt_BR" />);
        const card = (await screen.findByText('Orçar cliente A')).closest('li')!;
        expect(card.getAttribute('draggable')).toBe('true');
        expect(within(column('Feito')).getByText('Fechar caixa').closest('li')!.getAttribute('draggable')).toBeNull();

        const data = transfer();
        fireEvent.dragStart(card, { dataTransfer: data });
        // Not a target: the drop does nothing.
        fireEvent.dragOver(column('Feito'), { dataTransfer: data });
        fireEvent.drop(column('Feito'), { dataTransfer: data });
        expect(server.calls.some((call) => call.path.endsWith('/move'))).toBe(false);

        fireEvent.dragOver(column('Fazendo'), { dataTransfer: data });
        fireEvent.drop(column('Fazendo'), { dataTransfer: data });
        fireEvent.dragEnd(card);
        await waitFor(() => expect(within(column('Fazendo')).getByText('Orçar cliente A')).toBeTruthy());
        expect(server.tasks().find((task) => task.id === 1)?.status).toBe('doing');
    });

    it('opens a card in the form, offers no move without permission, and is off when the screen has no board', async () => {
        const server = fakeBoards({ canUpdate: false });
        const crud = await server.controller();
        render(<PtahCrudKanban crud={crud} locale="pt_BR" />);

        fireEvent.click(await screen.findByRole('button', { name: 'Orçar cliente A' }));
        await waitFor(() => expect(crud.getState().form?.id).toBe(1));
        expect(screen.queryByLabelText('Mover para')).toBeNull();
        expect(screen.getByText('Orçar cliente A').closest('li')!.getAttribute('draggable')).toBeNull();
        cleanup();

        const off = await fakeBoards({ kanban: false }).controller();
        const { container } = render(<PtahCrudKanban crud={off} locale="pt_BR" />);
        expect(container.innerHTML).toBe('');
    });

    it('shows the refusal of the server as an alert', async () => {
        const crud = await fakeBoards({ forbidden: true }).controller();
        render(<PtahCrudKanban crud={crud} locale="pt_BR" />);

        expect((await screen.findByRole('alert')).textContent).toContain('Você não tem permissão');
    });

    it('draws the month, navigates and opens an event', async () => {
        const server = fakeBoards({ overflow: true });
        const crud = await server.controller();
        render(<PtahCrudCalendar crud={crud} locale="pt_BR" />);

        expect(await screen.findByRole('heading', { name: 'setembro de 2026' })).toBeTruthy();
        expect(screen.getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']);
        const day15 = document.querySelector('td[data-date="2026-09-15"]') as HTMLElement;
        expect(within(day15).getByRole('button', { name: 'Entregar pedido B' })).toBeTruthy();
        expect(within(day15).getByRole('button', { name: 'Fechar caixa' })).toBeTruthy();
        expect(screen.getByText('20').getAttribute('aria-current')).toBe('date');
        expect(screen.getByText(/Registros demais neste mês/)).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Próximo mês' }));
        expect(await screen.findByRole('heading', { name: 'outubro de 2026' })).toBeTruthy();
        const last = server.calls.filter((call) => call.path === '/crud/tasks/calendar').at(-1)!;
        expect([last.query.get('month'), last.query.get('shift')]).toEqual([null, '1']);
        expect(screen.getByRole('button', { name: 'Aguardando peça' })).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Próximo mês' }));
        expect(await screen.findByRole('heading', { name: 'novembro de 2026' })).toBeTruthy();
        expect(server.calls.filter((call) => call.path === '/crud/tasks/calendar').at(-1)!.query.get('month')).toBe('2026-10');

        fireEvent.click(screen.getByRole('button', { name: 'Hoje' }));
        expect(await screen.findByRole('heading', { name: 'setembro de 2026' })).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Orçar cliente A' }));
        await waitFor(() => expect(crud.getState().form?.id).toBe(1));
    });

    it('draws the rows as cards with the table cells, sort and selection', async () => {
        const server = fakeBoards();
        const crud = await server.controller();
        render(<PtahCrudCards crud={crud} locale="pt_BR" />);

        const card = screen.getByRole('article', { name: '#1' });
        expect(card.querySelector('strong')?.textContent).toBe('Orçar cliente A');
        expect(within(card).getByText('Nota')).toBeTruthy();
        expect(within(card).getByText('urgente')).toBeTruthy();
        expect(within(card).getByText('A fazer')).toBeTruthy();
        expect(card.style.color).toBe('red');

        fireEvent.click(within(card).getByRole('checkbox', { name: 'Selecionar linha 1' }));
        expect(crud.getState().selection).toEqual(['1']);

        fireEvent.change(screen.getByLabelText('Ordenar por:'), { target: { value: 'title' } });
        await waitFor(() => expect(server.calls.filter((call) => call.path === '/crud/tasks').at(-1)?.query.get('sort')).toBe('title'));
        fireEvent.click(screen.getByRole('button', { name: /Ordem decrescente/ }));
        await waitFor(() => expect(screen.getByRole('button', { name: /Ordem crescente/ }).getAttribute('aria-pressed')).toBe('true'));

        // The default footer: the configured action asks first, then runs.
        fireEvent.click(within(card).getByRole('button', { name: 'Aprovar' }));
        expect(within(screen.getByRole('dialog')).getByText('Aprovar esta tarefa?')).toBeTruthy();
        fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Cancelar' }));
        expect(screen.queryByRole('dialog')).toBeNull();

        fireEvent.click(within(card).getByRole('button', { name: 'Excluir' }));
        expect(crud.getState().deleting).toBe(1);
    });

    it('uses the row actions it is given, and shows the empty state', async () => {
        const crud = await fakeBoards().controller();
        render(<PtahCrudCards crud={crud} locale="pt_BR" renderActions={(row) => <button type="button">Ação {String(row.id)}</button>} />);

        expect(screen.getByRole('button', { name: 'Ação 1' })).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Excluir' })).toBeNull();
        cleanup();

        const empty = await fakeBoards({ empty: true }).controller();
        render(<PtahCrudCards crud={empty} locale="pt_BR" />);
        expect(screen.getByText('Nenhum registro encontrado')).toBeTruthy();
    });
});

describe('row link and responsive cards (React)', () => {
    const go = rowNavigator.go;
    const open = rowNavigator.open;
    const narrowMedia = (matches: boolean) => {
        window.matchMedia = ((query: string) => ({ matches, media: query, addEventListener: () => undefined, removeEventListener: () => undefined })) as unknown as typeof window.matchMedia;
    };
    afterEach(() => {
        rowNavigator.go = go;
        rowNavigator.open = open;
        delete (window as { matchMedia?: unknown }).matchMedia;
    });
    const spy = () => {
        const went: string[] = [];
        const opened: string[] = [];
        rowNavigator.go = (url) => void went.push(url);
        rowNavigator.open = (url) => void opened.push(url);

        return { went, opened };
    };

    it('a card with a link opens its record; its buttons, checkbox and alt+click do not', async () => {
        const nav = spy();
        const crud = await fakeBoards().controller();
        render(<PtahCrudCards crud={crud} locale="pt_BR" />);
        const card = screen.getByRole('article', { name: '#2' });
        expect(card.getAttribute('tabindex')).toBe('0');

        fireEvent.click(within(card).getByText('Nota'));
        expect(nav.went).toEqual(['/tarefas/2']);
        fireEvent.click(card, { ctrlKey: true });
        expect(nav.opened).toEqual(['/tarefas/2']);

        fireEvent.click(within(card).getByRole('checkbox'));
        fireEvent.click(within(card).getByRole('button', { name: 'Excluir' }));
        fireEvent.click(card, { altKey: true });
        expect(nav.went).toHaveLength(1);

        fireEvent.keyDown(card, { key: 'Enter' });
        expect(nav.went).toEqual(['/tarefas/2', '/tarefas/2']);

        // A row without a link is no link.
        const plain = screen.getByRole('article', { name: '#1' });
        expect(plain.getAttribute('tabindex')).toBeNull();
        fireEvent.click(plain);
        expect(nav.went).toHaveLength(2);
    });

    it('a table row with a link opens its record; its buttons do not', async () => {
        const nav = spy();
        const server = fakeBoards({ viewMode: 'table' });
        render(<PtahCrud screen="tasks" api={server.api} locale="pt_BR" toastMs={0} remember={false} />);
        const cell = await screen.findByText('Entregar pedido B');
        const row = cell.closest('tr')!;
        expect(row.getAttribute('tabindex')).toBe('0');

        fireEvent.click(cell);
        expect(nav.went).toEqual(['/tarefas/2']);
        fireEvent(cell, new MouseEvent('auxclick', { bubbles: true, button: 1 }));
        expect(nav.opened).toEqual(['/tarefas/2']);

        fireEvent.click(within(row).getByRole('checkbox'));
        fireEvent.click(within(row).getByRole('button', { name: 'Excluir' }));
        expect(nav.went).toHaveLength(1);
        expect(screen.getByText('Orçar cliente A').closest('tr')!.getAttribute('tabindex')).toBeNull();
    });

    it("keeps 'auto' a table on a narrow screen when responsive cards are off", async () => {
        const features = { kanban: false, calendar: false };
        expect(resolveView('auto', features, true)).toBe('cards');
        expect(resolveView('auto', { ...features, responsiveCards: false }, true)).toBe('table');
        expect(resolveView('cards', { ...features, responsiveCards: false }, true)).toBe('cards');

        narrowMedia(true);
        render(<PtahCrud screen="tasks" api={fakeBoards({ responsiveCards: false }).api} locale="pt_BR" toastMs={0} remember={false} />);
        expect(await screen.findByRole('table')).toBeTruthy();
        expect(screen.queryByRole('article')).toBeNull();
        cleanup();

        render(<PtahCrud screen="tasks" api={fakeBoards().api} locale="pt_BR" toastMs={0} remember={false} />);
        expect(await screen.findByRole('article', { name: '#1' })).toBeTruthy();
        expect(screen.queryByRole('table')).toBeNull();
    });
});
