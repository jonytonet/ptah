import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import type { PropType } from 'vue';
import { CrudController } from '../../core/controller';
import { applyFieldChange } from '../../core/crud/extras';
import type { DetailScreen } from '../../core/crud/extras';
import { PtahCrudColumnResizer, PtahCrudColumnsMenu, PtahCrudDetailRow, PtahCrudDetailToggle, PtahCrudFormChanges, PtahCrudFormDraft, PtahCrudSaveAndNew } from '../../vue/crud/extras';
import PtahCrud from '../../vue/PtahCrud.vue';
import { usePtahCrud } from '../../vue/usePtahCrud';
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

describe('form draft (Vue)', () => {
    it('keeps what is typed, restores it on the next opening through the server, and clears it', async () => {
        const { server, crud } = await setup();
        localStorage.setItem('ptah:draft:someone-else:scr0001:new', JSON.stringify({ v: { name: 'x' }, version: '', at: Date.now(), ttl: 7 }));
        render(PtahCrudFormDraft, { props: { crud, locale: 'pt_BR' } });

        await crud.openCreate();
        await waitFor(() => expect(server.calls.some((call) => call.path === '/form/draft')).toBe(true));
        await waitFor(() => expect(localStorage.getItem('ptah:draft:someone-else:scr0001:new')).toBeNull());

        crud.setField('name', 'Rascunho');
        await waitFor(() => expect(stored('new')?.v).toEqual({ name: 'Rascunho', status: 'active' }));
        expect(await screen.findByRole('button', { name: 'Limpar tudo' })).toBeTruthy();

        crud.closeForm();
        expect(stored('new')).not.toBeNull();

        await crud.openCreate();
        await waitFor(() => expect(crud.getState().form?.data.name).toBe('Rascunho'));
        expect(toasts(crud)).toContain('info: Seu rascunho não salvo foi restaurado.');

        await fireEvent.click(await screen.findByRole('button', { name: 'Limpar tudo' }));
        await waitFor(() => expect(crud.getState().form?.data.name).toBeNull());
        expect(stored('new')).toBeNull();
        await waitFor(() => expect(screen.queryByRole('button', { name: 'Limpar tudo' })).toBeNull());
    });

    it('forgets the draft once the record is saved', async () => {
        const { crud } = await setup();
        render(PtahCrudFormDraft, { props: { crud, locale: 'pt_BR' } });

        await crud.openCreate();
        crud.setField('name', 'Novo');
        await waitFor(() => expect(stored('new')).not.toBeNull());

        await crud.save();
        expect(crud.getState().form).toBeNull();
        expect(stored('new')).toBeNull();
    });

    it('discards the draft of a record that changed since, and reverts to the original', async () => {
        const { crud } = await setup();
        localStorage.setItem(draftKey('edit:1'), JSON.stringify({ v: { name: 'Velho', status: 'active' }, version: 'vOutro', at: Date.now(), ttl: 7 }));
        render(PtahCrudFormDraft, { props: { crud, locale: 'pt_BR' } });

        await crud.openEdit(1);
        await waitFor(() => expect(toasts(crud)).toContain('warn: Este registro mudou desde o seu rascunho, e o rascunho foi descartado.'));
        expect(stored('edit:1')).toBeNull();

        crud.setField('name', 'Alfa 2');
        await waitFor(() => expect(stored('edit:1')?.version).toBe('vAlfa'));
        await fireEvent.click(await screen.findByRole('button', { name: 'Voltar ao original' }));
        await waitFor(() => expect(crud.getState().form?.data.name).toBe('Alfa'));
        expect(stored('edit:1')).toBeNull();
    });

    it('offers the draft when typing already started, and restores or discards it', async () => {
        const { crud } = await setup();
        const draft = JSON.stringify({ v: { name: 'Guardado', status: 'archived' }, version: '', at: Date.now(), ttl: 7 });
        localStorage.setItem(draftKey('new'), draft);
        render(PtahCrudFormDraft, { props: { crud, locale: 'pt_BR' } });

        await crud.openCreate();
        crud.setField('name', 'Digitado');
        expect(await screen.findByText('Encontramos um rascunho não salvo deste formulário.')).toBeTruthy();
        await sleep(450);
        expect(stored('new')?.v.name).toBe('Guardado');

        await fireEvent.click(screen.getByRole('button', { name: 'Restaurar rascunho' }));
        await waitFor(() => expect(crud.getState().form?.data).toMatchObject({ name: 'Guardado', status: 'archived' }));
        crud.closeForm();

        localStorage.setItem(draftKey('new'), draft);
        await crud.openCreate();
        crud.setField('name', 'Outro');
        await fireEvent.click(await screen.findByRole('button', { name: 'Descartar rascunho' }));
        expect(crud.getState().form?.data.name).toBe('Outro');
        await waitFor(() => expect(stored('new')?.v.name).toBe('Outro'));
    });

    it('draws nothing and stores nothing when the screen has no draft', async () => {
        const { server, crud } = await setup({ features: false });
        const { container } = render(PtahCrudFormDraft, { props: { crud, locale: 'pt_BR' } });

        await crud.openCreate();
        crud.setField('name', 'X');
        await sleep(450);
        expect(container.textContent).toBe('');
        expect(localStorage.length).toBe(0);
        expect(server.calls.some((call) => call.path === '/form/draft')).toBe(false);
    });
});

describe('form behaviours (Vue)', () => {
    it('saves and opens a blank form again; a refused save keeps the form', async () => {
        const { server, crud } = await setup();
        const { container } = render(PtahCrudSaveAndNew, { props: { crud, locale: 'pt_BR' } });
        expect(container.textContent).toBe('');

        await crud.openCreate();
        await fireEvent.click(await screen.findByRole('button', { name: 'Salvar e criar outro' }));
        await waitFor(() => expect(crud.getState().form?.errors.name).toBe('O campo Nome é obrigatório.'));

        crud.setField('name', 'Delta');
        await fireEvent.click(screen.getByRole('button', { name: 'Salvar e criar outro' }));
        await waitFor(() => expect(server.items().some((item) => item.name === 'Delta')).toBe(true));
        await waitFor(() => expect(crud.getState().form).toMatchObject({ mode: 'create', data: { name: null } }));

        await crud.openEdit(1);
        await nextTick();
        expect(screen.queryByRole('button', { name: 'Salvar e criar outro' })).toBeNull();
    });

    it('runs the field formula on the server when a trigger field changes', async () => {
        const { server, crud } = await setup();
        render(PtahCrudFormChanges, { props: { crud } });
        await crud.openCreate();
        await waitFor(() => expect(server.calls.some((call) => call.path === '/form/triggers')).toBe(true));

        crud.setField('name', 'Arquivar');
        await waitFor(() => expect(crud.getState().form?.data.status).toBe('archived'));
        expect(server.calls.filter((call) => call.path === '/form/change')).toHaveLength(1);

        crud.setField('status', 'active');
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

const DetailTable = defineComponent({
    props: { crud: { type: Object as PropType<CrudController>, required: true } },
    setup(props) {
        const state = usePtahCrud(props.crud);

        return () =>
            h('table', [
                h(
                    'tbody',
                    state.value.rows.flatMap((row) => [
                        h('tr', { key: `r${row.id}` }, [h('td', [h(PtahCrudDetailToggle, { crud: props.crud, row, locale: 'pt_BR' })]), h('td', String(row.cells.name.value))]),
                        h(PtahCrudDetailRow, { key: `d${row.id}`, crud: props.crud, row, colSpan: 2, locale: 'pt_BR' }, { detail: ({ detail }: { detail: DetailScreen }) => h('p', `child:${detail.screen}`) }),
                    ]),
                ),
            ]);
    },
});

describe('master/detail (Vue)', () => {
    it('expands a row lazily into the child screens the server named, and keeps them while collapsed', async () => {
        const { server, crud } = await setup();
        render(DetailTable, { props: { crud } });
        const toggles = screen.getAllByRole('button', { name: 'Ver detalhes' });
        const detailsCalls = () => server.calls.filter((call) => call.path.startsWith('/1/details')).length;
        expect(detailsCalls()).toBe(0);

        await fireEvent.click(toggles[0]);
        expect(toggles[0].getAttribute('aria-expanded')).toBe('true');
        const region = await screen.findByRole('region', { name: 'Linhas' });
        expect(region.textContent).toBe('child:items..1.0');

        const before = detailsCalls();
        await fireEvent.click(toggles[0]);
        expect(screen.queryByRole('region', { name: 'Linhas' })).toBeNull();
        await fireEvent.click(toggles[0]);
        expect(screen.getByRole('region', { name: 'Linhas' })).toBeTruthy();
        expect(detailsCalls()).toBe(before);
    });

    it('shows the unavailable, empty and error states', async () => {
        const { crud } = await setup();
        render(DetailTable, { props: { crud } });
        const toggles = screen.getAllByRole('button', { name: 'Ver detalhes' });

        await fireEvent.click(toggles[1]);
        const notes = await screen.findByRole('region', { name: 'Notas' });
        expect(within(notes).getByText('Você não pode ver estes registros.')).toBeTruthy();
        expect(notes.textContent).not.toContain('child:');

        await fireEvent.click(toggles[2]);
        expect((await screen.findByRole('alert')).textContent).toContain('Não foi possível carregar os detalhes.');
        expect(screen.getByRole('button', { name: 'Tentar novamente' })).toBeTruthy();

        cleanup();
        const empty = await setup({ emptyDetails: true });
        render(DetailTable, { props: { crud: empty.crud } });
        await fireEvent.click(screen.getAllByRole('button', { name: 'Ver detalhes' })[1]);
        expect(await screen.findByText('Nenhum registro encontrado')).toBeTruthy();
    });

    it('draws nothing when the screen has no master/detail', async () => {
        const { crud } = await setup({ features: false });
        render(DetailTable, { props: { crud } });
        expect(screen.queryByRole('button', { name: 'Ver detalhes' })).toBeNull();
    });

    it('mounts a whole child PtahCrud under the expanded row of PtahCrud (no import cycle)', async () => {
        const server = fakeExtras();
        render(PtahCrud, { props: { screen: 'items', api: server.api, locale: 'pt_BR', remember: false, toastMs: 0 } });
        await screen.findByText('Alfa');

        await fireEvent.click(screen.getAllByRole('button', { name: 'Ver detalhes' })[0]);
        const region = await screen.findByRole('region', { name: 'Linhas' });
        // The child screen's own listing, title, search and create — on its own key.
        expect(await within(region).findByText('L1')).toBeTruthy();
        expect(within(region).getByText('L2')).toBeTruthy();
        expect(within(region).queryByText('L3')).toBeNull();
        expect(within(region).getByText('Linhas', { selector: '.pc-detail-title' })).toBeTruthy();
        expect(server.calls.some((call) => call.path === '..1.0/schema')).toBe(true);

        await fireEvent.click(within(region).getByRole('button', { name: /Novo/ }));
        const dialog = await screen.findByRole('dialog');
        await fireEvent.update(await within(dialog).findByLabelText(/Linha/), 'L9');
        await fireEvent.click(within(dialog).getByRole('button', { name: /^(Criar|Salvar)$/ }));
        await waitFor(() => expect(server.lines().some((line) => line.name === 'L9' && line.parent === 1)).toBe(true));
        expect(await within(region).findByText('L9')).toBeTruthy();
    });
});

describe('columns (Vue)', () => {
    it('shows, hides, reorders from the keyboard and resets the columns', async () => {
        const { server, crud } = await setup();
        render(PtahCrudColumnsMenu, { props: { crud, locale: 'pt_BR' } });
        const puts = () => server.calls.filter((call) => call.path === '/preferences' && call.method === 'PUT').map((call) => call.body);

        await fireEvent.click(screen.getByRole('button', { name: /Colunas/ }));
        expect(await screen.findByLabelText('Situação')).toBeTruthy();

        await fireEvent.click(screen.getByLabelText('Situação'));
        await waitFor(() => expect(puts()).toContainEqual({ columns: { status: false } }));
        expect(await screen.findByRole('button', { name: 'Colunas (1 ocultas)' })).toBeTruthy();

        expect((screen.getByRole('button', { name: 'Mover Nome para cima' }) as HTMLButtonElement).disabled).toBe(true);
        await fireEvent.click(screen.getByRole('button', { name: 'Mover Valor para cima' }));
        await waitFor(() => expect(puts()).toContainEqual({ column_order: ['id', 'name', 'amount', 'status'] }));
        await waitFor(() => expect(screen.getAllByRole('checkbox').map((box) => box.parentElement?.textContent)).toEqual(['Nome', 'Valor', 'Situação']));

        await fireEvent.click(screen.getByRole('button', { name: 'Restaurar padrão' }));
        const dialog = await screen.findByRole('dialog');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Restaurar' }));
        await waitFor(() => expect(server.calls.some((call) => call.path === '/columns/reset')).toBe(true));
        await waitFor(() => expect(screen.queryByRole('button', { name: 'Colunas (1 ocultas)' })).toBeNull());

        await fireEvent.click(screen.getByRole('button', { name: 'Ocultar todas' }));
        await waitFor(() => expect(puts()).toContainEqual({ columns: { id: false, name: false, status: false, amount: false } }));
    });

    it('says when the columns could not be loaded', async () => {
        const { crud } = await setup({ failColumns: true });
        render(PtahCrudColumnsMenu, { props: { crud, locale: 'pt_BR' } });

        await fireEvent.click(screen.getByRole('button', { name: /Colunas/ }));
        expect((await screen.findByRole('alert')).textContent).toBe('Não foi possível carregar as colunas.');
        expect(screen.getByRole('button', { name: 'Tentar novamente' })).toBeTruthy();
    });

    it('resizes a column from the keyboard and forgets a width on double-click', async () => {
        const { server, crud } = await setup();
        const Header = defineComponent({
            setup: () => () => h('table', [h('thead', [h('tr', [h('th', { style: { position: 'relative' } }, ['Nome', h(PtahCrudColumnResizer, { crud, field: 'name', label: 'Nome', locale: 'pt_BR' })])])])]),
        });
        render(Header);
        const handle = screen.getByRole('separator', { name: 'Redimensionar coluna Nome' });

        await fireEvent.keyDown(handle, { key: 'ArrowRight' });
        await waitFor(() => expect(server.calls.some((call) => call.method === 'PUT' && JSON.stringify(call.body) === '{"column_widths":{"name":130}}')).toBe(true));
        await waitFor(() => expect(handle.getAttribute('aria-valuenow')).toBe('130'));

        await fireEvent.dblClick(handle);
        await waitFor(() => expect(server.calls.some((call) => call.path === '/columns/reset' && call.body?.field === 'name')).toBe(true));
        await waitFor(() => expect(handle.getAttribute('aria-valuenow')).toBeNull());
    });
});
