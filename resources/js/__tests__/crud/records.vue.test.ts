import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { defineComponent, h, nextTick } from 'vue';
import type { PropType } from 'vue';
import { afterEach, describe, expect, it } from 'vitest';
import type { CrudController } from '../../core/controller';
import { PtahCrudAttachments, PtahCrudHistory, PtahCrudRecordButtons } from '../../vue/crud/records';
import { usePtahCrud } from '../../vue/usePtahCrud';
import { fakeRecords } from './records.fake';

afterEach(cleanup);

/** The pieces where PtahCrud mounts them: the buttons in each row, the panels over the screen. */
const Screen = defineComponent({
    props: { crud: { type: Object as PropType<CrudController>, required: true } },
    setup(props) {
        const state = usePtahCrud(props.crud);

        return () => [
            ...state.value.rows.map((row) => h('div', { key: row.id, 'data-testid': `row-${row.id}` }, [h(PtahCrudRecordButtons, { crud: props.crud, row, locale: 'pt_BR' })])),
            h(PtahCrudHistory, { crud: props.crud, locale: 'pt_BR' }),
            h(PtahCrudAttachments, { crud: props.crud, locale: 'pt_BR' }),
        ];
    },
});

async function mount(options: Parameters<typeof fakeRecords>[0] = {}) {
    const server = fakeRecords(options);
    const crud = await server.controller();
    render(Screen, { props: { crud } });

    return { server, crud };
}

const row = (id: number) => within(screen.getByTestId(`row-${id}`));
const dialog = () => screen.getByRole('dialog');
const open = async (crud: CrudController, name: string, id: number) => {
    crud.openPanel(name, id);
    await nextTick();
};

describe('records (Vue)', () => {
    it('shows the buttons with the counts of the page in one request, none on a trashed row', async () => {
        const { server } = await mount();

        expect(await row(1).findByRole('button', { name: 'Anexos (1)' })).toBeTruthy();
        expect(row(2).getByRole('button', { name: 'Anexos (0)' })).toBeTruthy();
        expect(row(1).getByRole('button', { name: 'Histórico' })).toBeTruthy();
        expect(row(3).queryByRole('button')).toBeNull();
        expect(server.calls.filter((call) => call.path.includes('/attachments/counts'))).toEqual([{ method: 'GET', path: '/crud/items/attachments/counts?ids[]=1&ids[]=2' }]);
    });

    it('renders nothing when the features are off', async () => {
        const { server, crud } = await mount({ features: { history: false, attachments: false, attachmentsWrite: false } });

        expect(row(1).queryByRole('button')).toBeNull();
        await open(crud, 'history', 1);
        await open(crud, 'attachments', 1);
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(server.calls.some((call) => call.path.includes('/1/'))).toBe(false);
    });

    it('opens the history of a row: events, who, old → new, hidden fields, and closes', async () => {
        const { crud } = await mount();

        await fireEvent.click(row(1).getByRole('button', { name: 'Histórico' }));
        expect(within(dialog()).getByRole('heading', { name: 'Histórico de alterações' })).toBeTruthy();
        expect(screen.getByRole('status').textContent).toContain('Carregando');

        expect(await screen.findByText('Alterado')).toBeTruthy();
        expect(screen.getByText('· Ana Souza')).toBeTruthy();
        expect(screen.getByText('Aberto').style.textDecoration).toBe('line-through');
        expect(screen.getByText('passou a')).toBeTruthy();
        expect(screen.getByText('Fechado')).toBeTruthy();
        expect(screen.getByText('e 2 campos que esta tela não mostra')).toBeTruthy();
        expect(screen.getByText('Criado')).toBeTruthy();
        expect(screen.getAllByText('passou a')).toHaveLength(1);

        await fireEvent.click(screen.getByRole('button', { name: 'Fechar' }));
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(crud.getState().panel).toBeNull();
    });

    it('shows the empty history and the refusal of a record out of reach', async () => {
        const { crud } = await mount({ empty: true });

        await open(crud, 'history', 1);
        expect(await screen.findByText('Nenhuma alteração registrada para este registro ainda.')).toBeTruthy();

        await open(crud, 'history', 9);
        expect((await screen.findByRole('alert')).textContent).toContain('Este registro não está disponível.');
    });

    it('lists, uploads (a refused file stays as an alert), and removes after confirming', async () => {
        const { crud, server } = await mount();

        await fireEvent.click(await row(1).findByRole('button', { name: 'Anexos (1)' }));
        const link = (await screen.findByRole('link', { name: 'Baixar nota.pdf' })) as HTMLAnchorElement;
        expect(link.getAttribute('href')).toBe('/ptah/api/crud/items/1/attachments/50/download');
        expect(link.getAttribute('download')).toBe('nota.pdf');
        expect(screen.getByText('120 KB · 18/11/2026 10:00')).toBeTruthy();

        const input = screen.getByLabelText(/Escolher arquivos/);
        await fireEvent.change(input, { target: { files: [new File(['x'], 'shell.php', { type: 'application/x-php' })] } });
        await fireEvent.click(screen.getByRole('button', { name: 'Enviar 1 arquivo' }));
        expect((await screen.findByRole('alert')).textContent).toContain('files.0');

        await fireEvent.change(input, { target: { files: [new File(['a'], 'a.pdf', { type: 'application/pdf' }), new File(['b'], 'b.pdf', { type: 'application/pdf' })] } });
        expect(screen.queryByRole('alert')).toBeNull();
        await fireEvent.click(screen.getByRole('button', { name: 'Enviar 2 arquivos' }));
        expect(await screen.findByRole('link', { name: 'Baixar b.pdf' })).toBeTruthy();
        expect(crud.getState().toasts.map((toast) => toast.text)).toEqual(['Arquivos anexados.']);
        await waitFor(() => expect(row(1).getByRole('button', { name: 'Anexos (3)' })).toBeTruthy());
        expect(server.calls.filter((call) => call.method === 'POST')).toHaveLength(2);

        await fireEvent.click(screen.getByRole('button', { name: 'Excluir nota.pdf' }));
        expect(screen.getByText('Remover o anexo nota.pdf?')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));
        expect(await screen.findByRole('link', { name: 'Baixar nota.pdf' })).toBeTruthy();
        expect(server.calls.some((call) => call.method === 'DELETE')).toBe(false);

        await fireEvent.click(screen.getByRole('button', { name: 'Excluir nota.pdf' }));
        await fireEvent.click(within(dialog()).getByRole('button', { name: 'Excluir' }));
        await waitFor(() => expect(screen.queryByRole('link', { name: 'Baixar nota.pdf' })).toBeNull());
        await waitFor(() => expect(row(1).getByRole('button', { name: 'Anexos (2)' })).toBeTruthy());
        expect(server.files()[1].map((item) => item.name)).toEqual(['b.pdf', 'a.pdf']);
    });

    it('shows the empty list, and no upload or removal without the write permission', async () => {
        const { crud } = await mount({ features: { attachmentsWrite: false } });

        await open(crud, 'attachments', 2);
        expect(await screen.findByText('Nenhum arquivo anexado ainda.')).toBeTruthy();

        await open(crud, 'attachments', 1);
        expect(await screen.findByRole('link', { name: 'Baixar nota.pdf' })).toBeTruthy();
        expect(screen.queryByLabelText(/Escolher arquivos/)).toBeNull();
        expect(screen.queryByRole('button', { name: 'Excluir nota.pdf' })).toBeNull();
    });

    it('shows the refusal of the server', async () => {
        const { crud } = await mount({ forbidden: true });

        await open(crud, 'attachments', 1);
        expect((await screen.findByRole('alert')).textContent).toContain('Você não tem permissão');
    });
});
