import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vitest';
import { h } from 'vue';
import type { Row } from '../../core/types';
import { PtahCrudCalendar, PtahCrudCards, PtahCrudKanban, PtahCrudViewSwitch } from '../../vue/crud/boards';
import { fakeBoards, toasts } from './boards.fake';

afterEach(cleanup);

const column = (name: string) => screen.getByRole('region', { name });
const transfer = () => {
    const data: Record<string, string> = {};

    return { setData: (type: string, value: string) => (data[type] = value), getData: (type: string) => data[type] ?? '', dropEffect: 'move', effectAllowed: 'all' };
};

describe('boards (Vue)', () => {
    it('switches the view mode and the density through the preferences', async () => {
        const server = fakeBoards();
        const crud = await server.controller();
        render(PtahCrudViewSwitch, { props: { crud, locale: 'pt_BR' } });

        const group = screen.getByRole('group', { name: 'Modo de visualização' });
        expect(within(group).getAllByRole('button').map((button) => button.getAttribute('aria-label'))).toEqual([
            'Automático (tabela no desktop, cards no celular)',
            'Visão em tabela',
            'Visão em cards',
            'Quadro',
            'Calendário',
        ]);
        expect(screen.getByRole('button', { name: /Automático/ }).getAttribute('aria-pressed')).toBe('true');

        await fireEvent.click(screen.getByRole('button', { name: 'Quadro' }));
        await waitFor(() => expect(screen.getByRole('button', { name: 'Quadro' }).getAttribute('aria-pressed')).toBe('true'));
        expect(server.calls.find((call) => call.method === 'PUT')?.body).toEqual({ view_mode: 'kanban' });

        await fireEvent.click(screen.getByRole('button', { name: 'Densidade' }));
        await fireEvent.click(screen.getByRole('button', { name: /Compacto/ }));
        await waitFor(() => expect(server.preferences().view_density).toBe('compact'));
        expect(screen.queryByRole('button', { name: /Compacto/ })).toBeNull();
    });

    it('offers only the modes the screen has', async () => {
        const crud = await fakeBoards({ kanban: false, calendar: false }).controller();
        render(PtahCrudViewSwitch, { props: { crud, locale: 'pt_BR' } });

        expect(screen.queryByRole('button', { name: 'Quadro' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Calendário' })).toBeNull();
        expect(screen.getByRole('button', { name: 'Visão em cards' })).toBeTruthy();
    });

    it('draws the board of the current listing, with locked and other-values columns', async () => {
        const server = fakeBoards();
        const crud = await server.controller();
        render(PtahCrudKanban, { props: { crud, locale: 'pt_BR' } });

        expect(await screen.findByText('Orçar cliente A')).toBeTruthy();
        expect(within(column('A fazer')).getByText('urgente')).toBeTruthy();
        expect(within(column('Feito')).getByText('Concluir é feito pelo painel do setor.')).toBeTruthy();
        expect(column('Feito').hasAttribute('data-ptah-kanban-locked')).toBe(true);
        expect(within(column('Outros valores')).getByText(/não é uma das opções/)).toBeTruthy();

        await crud.patchList({ search: 'Entregar' });
        await waitFor(() => expect(screen.queryByText('Orçar cliente A')).toBeNull());
        expect(server.calls.filter((call) => call.path === '/crud/tasks/kanban').at(-1)?.query.get('search')).toBe('Entregar');
        expect(within(column('A fazer')).getByText('Nada aqui')).toBeTruthy();
    });

    it('moves a card from the keyboard to the allowed columns only', async () => {
        const server = fakeBoards();
        const crud = await server.controller();
        render(PtahCrudKanban, { props: { crud, locale: 'pt_BR' } });
        await screen.findByText('Orçar cliente A');

        const select = within(column('A fazer')).getByLabelText('Mover para') as HTMLSelectElement;
        expect([...select.options].map((option) => option.text)).toEqual(['Mover para: A fazer', 'Mover para: Fazendo']);
        expect(within(column('Feito')).queryByLabelText('Mover para')).toBeNull();

        await fireEvent.update(select, 'doing');
        await waitFor(() => expect(within(column('Fazendo')).getByText('Orçar cliente A')).toBeTruthy());
        expect(server.calls.find((call) => call.path === '/crud/tasks/1/move')?.body).toEqual({ value: 'doing' });
        await waitFor(() => expect(toasts(crud)).toContain('Registro salvo com sucesso'));
        expect(server.tasks().find((task) => task.id === 1)?.status).toBe('doing');
    });

    it('puts the card back and shows the reason when the server refuses', async () => {
        const server = fakeBoards({ refuseMoves: true });
        const crud = await server.controller();
        render(PtahCrudKanban, { props: { crud, locale: 'pt_BR' } });
        await screen.findByText('Orçar cliente A');

        await fireEvent.update(within(column('A fazer')).getByLabelText('Mover para'), 'doing');

        await waitFor(() => expect(toasts(crud)).toContain('Recusado pelo servidor.'));
        await waitFor(() => expect(within(column('A fazer')).getByText('Orçar cliente A')).toBeTruthy());
        expect(within(column('Fazendo')).queryByText('Orçar cliente A')).toBeNull();
        expect((within(column('A fazer')).getByLabelText('Mover para') as HTMLSelectElement).value).toBe('todo');
        expect(server.tasks().find((task) => task.id === 1)?.status).toBe('todo');
    });

    it('drags a card onto an allowed column, and nowhere else', async () => {
        const server = fakeBoards();
        const crud = await server.controller();
        render(PtahCrudKanban, { props: { crud, locale: 'pt_BR' } });
        const card = (await screen.findByText('Orçar cliente A')).closest('li')!;
        expect(card.getAttribute('draggable')).toBe('true');
        expect(within(column('Feito')).getByText('Fechar caixa').closest('li')!.getAttribute('draggable')).toBeNull();

        const data = transfer();
        await fireEvent.dragStart(card, { dataTransfer: data });
        await fireEvent.dragOver(column('Feito'), { dataTransfer: data });
        await fireEvent.drop(column('Feito'), { dataTransfer: data });
        expect(server.calls.some((call) => call.path.endsWith('/move'))).toBe(false);

        await fireEvent.dragOver(column('Fazendo'), { dataTransfer: data });
        await fireEvent.drop(column('Fazendo'), { dataTransfer: data });
        await fireEvent.dragEnd(card);
        await waitFor(() => expect(within(column('Fazendo')).getByText('Orçar cliente A')).toBeTruthy());
        expect(server.tasks().find((task) => task.id === 1)?.status).toBe('doing');
    });

    it('opens a card in the form, offers no move without permission, and is off when the screen has no board', async () => {
        const server = fakeBoards({ canUpdate: false });
        const crud = await server.controller();
        render(PtahCrudKanban, { props: { crud, locale: 'pt_BR' } });

        await fireEvent.click(await screen.findByRole('button', { name: 'Orçar cliente A' }));
        await waitFor(() => expect(crud.getState().form?.id).toBe(1));
        expect(screen.queryByLabelText('Mover para')).toBeNull();
        expect(screen.getByText('Orçar cliente A').closest('li')!.getAttribute('draggable')).toBeNull();
        cleanup();

        const off = await fakeBoards({ kanban: false }).controller();
        const { container } = render(PtahCrudKanban, { props: { crud: off, locale: 'pt_BR' } });
        expect(container.textContent).toBe('');
    });

    it('shows the refusal of the server as an alert', async () => {
        const crud = await fakeBoards({ forbidden: true }).controller();
        render(PtahCrudKanban, { props: { crud, locale: 'pt_BR' } });

        expect((await screen.findByRole('alert')).textContent).toContain('Você não tem permissão');
    });

    it('draws the month, navigates and opens an event', async () => {
        const server = fakeBoards({ overflow: true });
        const crud = await server.controller();
        render(PtahCrudCalendar, { props: { crud, locale: 'pt_BR' } });

        expect(await screen.findByRole('heading', { name: 'setembro de 2026' })).toBeTruthy();
        expect(screen.getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']);
        const day15 = document.querySelector('td[data-date="2026-09-15"]') as HTMLElement;
        expect(within(day15).getByRole('button', { name: 'Entregar pedido B' })).toBeTruthy();
        expect(within(day15).getByRole('button', { name: 'Fechar caixa' })).toBeTruthy();
        expect(screen.getByText('20').getAttribute('aria-current')).toBe('date');
        expect(screen.getByText(/Registros demais neste mês/)).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Próximo mês' }));
        expect(await screen.findByRole('heading', { name: 'outubro de 2026' })).toBeTruthy();
        const last = server.calls.filter((call) => call.path === '/crud/tasks/calendar').at(-1)!;
        expect([last.query.get('month'), last.query.get('shift')]).toEqual([null, '1']);
        expect(screen.getByRole('button', { name: 'Aguardando peça' })).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Próximo mês' }));
        expect(await screen.findByRole('heading', { name: 'novembro de 2026' })).toBeTruthy();
        expect(server.calls.filter((call) => call.path === '/crud/tasks/calendar').at(-1)!.query.get('month')).toBe('2026-10');

        await fireEvent.click(screen.getByRole('button', { name: 'Hoje' }));
        expect(await screen.findByRole('heading', { name: 'setembro de 2026' })).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Orçar cliente A' }));
        await waitFor(() => expect(crud.getState().form?.id).toBe(1));
    });

    it('draws the rows as cards with the table cells, sort and selection', async () => {
        const server = fakeBoards();
        const crud = await server.controller();
        render(PtahCrudCards, { props: { crud, locale: 'pt_BR' } });

        const card = screen.getByRole('article', { name: '#1' });
        expect(card.querySelector('strong')?.textContent).toBe('Orçar cliente A');
        expect(within(card).getByText('Nota')).toBeTruthy();
        expect(within(card).getByText('urgente')).toBeTruthy();
        expect(within(card).getByText('A fazer')).toBeTruthy();
        expect(card.style.color).toBe('red');

        await fireEvent.click(within(card).getByRole('checkbox', { name: 'Selecionar linha 1' }));
        expect(crud.getState().selection).toEqual(['1']);

        await fireEvent.update(screen.getByLabelText('Ordenar por'), 'title');
        await waitFor(() => expect(server.calls.filter((call) => call.path === '/crud/tasks').at(-1)?.query.get('sort')).toBe('title'));
        await fireEvent.click(screen.getByRole('button', { name: /Ordem decrescente/ }));
        await waitFor(() => expect(screen.getByRole('button', { name: /Ordem crescente/ }).getAttribute('aria-pressed')).toBe('true'));

        await fireEvent.click(within(screen.getByRole('article', { name: '#1' })).getByRole('button', { name: 'Aprovar' }));
        expect(within(screen.getByRole('dialog')).getByText('Aprovar esta tarefa?')).toBeTruthy();
        await fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Cancelar' }));
        expect(screen.queryByRole('dialog')).toBeNull();

        await fireEvent.click(within(screen.getByRole('article', { name: '#1' })).getByRole('button', { name: 'Excluir' }));
        expect(crud.getState().deleting).toBe(1);
    });

    it('uses the row actions it is given, and shows the empty state', async () => {
        const crud = await fakeBoards().controller();
        render(PtahCrudCards, { props: { crud, locale: 'pt_BR' }, slots: { actions: ({ row }: { row: Row }) => h('button', { type: 'button' }, `Ação ${row.id}`) } });

        expect(screen.getByRole('button', { name: 'Ação 1' })).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Excluir' })).toBeNull();
        cleanup();

        const viaProp = await fakeBoards().controller();
        render(PtahCrudCards, { props: { crud: viaProp, locale: 'pt_BR', renderActions: (row: Row) => h('button', { type: 'button' }, `Prop ${row.id}`) } });
        expect(screen.getByRole('button', { name: 'Prop 2' })).toBeTruthy();
        cleanup();

        const empty = await fakeBoards({ empty: true }).controller();
        render(PtahCrudCards, { props: { crud: empty, locale: 'pt_BR' } });
        expect(screen.getByText('Nenhum registro encontrado')).toBeTruthy();
    });
});
