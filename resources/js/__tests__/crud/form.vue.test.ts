import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { CrudController } from '../../core/controller';
import { words } from '../../core/i18n';
import PtahCrud from '../../vue/PtahCrud.vue';
import PtahForm from '../../vue/PtahForm.vue';
import { usePtahCrud } from '../../vue/usePtahCrud';
import { fakeForm, PREVIEW } from './form.fake';

/** The form's dialog once its fields arrived (it opens at once, loading). */
async function readyDialog() {
    const dialog = await screen.findByRole('dialog');
    await waitFor(() => expect(dialog.getAttribute('aria-busy')).toBeNull());

    return dialog;
}


afterEach(() => {
    cleanup();
    document.body.style.overflow = '';
});

async function mount(options: Parameters<typeof fakeForm>[0] = {}) {
    const server = fakeForm(options);
    const crud = new CrudController('items', { api: server.api, toastMs: 0, remember: false });
    crud.navigate = vi.fn();
    render(PtahCrud, { props: { screen: 'items', locale: 'pt_BR', controller: crud } });
    await screen.findByText('Alfa');

    return { server, crud };
}

async function openNew() {
    await fireEvent.click(screen.getByRole('button', { name: 'Novo' }));

    return readyDialog();
}

async function openEdit() {
    await fireEvent.click(within(screen.getByText('Alfa').closest('tr')!).getByRole('button', { name: 'Editar' }));
    const dialog = await readyDialog();
    await within(dialog).findByText('Editar Conta');

    return dialog;
}

describe('form parity (Vue)', () => {
    it('opens a new record as the Livewire modal: title by gender, subtitle, sections, focus, page locked', async () => {
        await mount();
        const dialog = await openNew();

        expect(within(dialog).getByRole('heading', { name: 'Nova Conta' })).toBeTruthy();
        expect(within(dialog).getByText('Preencha os campos abaixo')).toBeTruthy();
        expect(within(dialog).getAllByText(/Dados gerais|Endereço|Extras|Somente leitura/).map((node) => node.textContent)).toEqual(['Dados gerais', 'Somente leitura', 'Endereço', 'Extras']);
        expect(within(dialog).getByText('Como aparece na lista.')).toBeTruthy();
        expect(within(dialog).getByRole('button', { name: 'Salvar' })).toBeTruthy();
        expect(within(dialog).queryByRole('button', { name: 'Salvar alterações' })).toBeNull();

        await waitFor(() => expect(document.activeElement).toBe(within(dialog).getByLabelText(/^Nome/)));
        expect(document.body.style.overflow).toBe('hidden');

        const status = within(dialog).getByLabelText(/^Situação/) as HTMLSelectElement;
        expect([...status.options].find((option) => option.value === '')?.disabled).toBe(true);
        const kind = within(dialog).getByLabelText(/^Tipo/) as HTMLSelectElement;
        expect([...kind.options].find((option) => option.value === '')?.disabled).toBe(false);

        const secret = within(dialog).getByLabelText(/^Senha/) as HTMLInputElement;
        expect(secret.getAttribute('aria-required')).toBe('true');
        expect(secret.placeholder).toBe('');

        await fireEvent.click(within(dialog).getByRole('button', { name: 'Cancelar' }));
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(document.body.style.overflow).toBe('');
    });

    it('Escape on a searchdropdown still loading closes its list, not the form (1.63.2)', async () => {
        await mount({ holdLookup: true });
        const dialog = await openNew();
        const region = within(dialog).getByLabelText('Região') as HTMLInputElement;

        // ArrowDown searches at once; the server never answers, so the list stays "loading".
        await fireEvent.keyDown(region, { key: 'ArrowDown' });
        await waitFor(() => expect(region.getAttribute('aria-expanded')).toBe('true'));
        await fireEvent.keyDown(region, { key: 'Escape' });

        await waitFor(() => expect(region.getAttribute('aria-expanded')).toBe('false'));
        expect(screen.getByRole('dialog')).toBeTruthy();
    });

    it('asks before discarding changes — Cancel, Escape, ✕ and a click outside', async () => {
        const { crud } = await mount();
        const dialog = await openNew();

        await fireEvent.update(within(dialog).getByLabelText(/^Nome/), 'Rascunho');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Cancelar' }));
        const confirm = await screen.findByRole('alertdialog', { name: 'Descartar alterações?' });
        expect(within(confirm).getByText('Você tem alterações não salvas. Deseja fechar mesmo assim?')).toBeTruthy();
        await waitFor(() => expect(document.activeElement).toBe(within(confirm).getByRole('button', { name: 'Continuar editando' })));

        await fireEvent.click(within(confirm).getByRole('button', { name: 'Continuar editando' }));
        expect(screen.queryByRole('alertdialog')).toBeNull();
        expect(crud.getState().form?.data.name).toBe('Rascunho');

        await fireEvent.keyDown(document, { key: 'Escape' });
        expect(await screen.findByRole('alertdialog')).toBeTruthy();
        await fireEvent.keyDown(document, { key: 'Escape' });
        expect(screen.queryByRole('alertdialog')).toBeNull();
        expect(screen.getByRole('dialog')).toBeTruthy();

        await fireEvent.click(within(dialog).getByRole('button', { name: 'Fechar' }));
        expect(await screen.findByRole('alertdialog')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: 'Continuar editando' }));

        await fireEvent.mouseDown(dialog.parentElement!);
        await fireEvent.click(await screen.findByRole('button', { name: 'Descartar' }));
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(crud.getState().form).toBeNull();
    });

    it('closes an untouched form at once, and does not ask when a draft keeps what was typed', async () => {
        const { crud } = await mount({ formDraft: true });
        let dialog = await openNew();

        await fireEvent.keyDown(document, { key: 'Escape' });
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());

        dialog = await openNew();
        await fireEvent.update(within(dialog).getByLabelText(/^Nome/), 'Guardado');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Cancelar' }));
        await waitFor(() => expect(crud.getState().form).toBeNull());
        expect(screen.queryByRole('alertdialog')).toBeNull();
    });

    it('edits: "Save changes", password kept when empty, read-only fields as text, image preview', async () => {
        await mount();
        const dialog = await openEdit();

        expect(within(dialog).getByText('Altere os campos e salve')).toBeTruthy();
        expect(within(dialog).getByRole('button', { name: 'Salvar alterações' })).toBeTruthy();

        const secret = within(dialog).getByLabelText(/^Senha/) as HTMLInputElement;
        expect(secret.placeholder).toBe('Deixe vazio para manter a atual');
        expect(secret.getAttribute('aria-required')).toBeNull();

        expect(dialog.querySelector('[data-ptah-readonly="tier"]')?.textContent).toBe('Ouro');
        expect(dialog.querySelector('[data-ptah-readonly="owner"]')?.textContent).toBe('Ana');
        expect(dialog.querySelector('[data-ptah-readonly="vip"]')?.textContent).toBe('Sim');
        expect(dialog.querySelector('input#pc-items-tier, select#pc-items-tier')).toBeNull();

        expect((within(dialog).getByRole('img', { name: 'visualização' }) as HTMLImageElement).src).toBe(PREVIEW);
        expect((dialog.querySelector('#pc-items-photo-file') as HTMLInputElement).accept).toBe('.png,.jpg');
        expect((within(dialog).getByLabelText('Preço') as HTMLInputElement).value).toBe('R$ 1.234,50');
    });

    it('locks a dependent dropdown until its parent has a value, with its placeholder, list side and second line', async () => {
        const { crud } = await mount();
        const dialog = await openNew();

        const city = within(dialog).getByLabelText('Cidade') as HTMLInputElement;
        expect(city.disabled).toBe(true);
        expect(city.placeholder).toBe('Selecione Região primeiro…');
        const region = within(dialog).getByLabelText('Região') as HTMLInputElement;
        expect(region.placeholder).toBe('Buscar Região...');

        await fireEvent.focus(region);
        await fireEvent.click(await within(dialog).findByRole('option', { name: 'Sul' }));
        expect(crud.getState().form?.data.region).toBe('sul');

        await waitFor(() => expect(city.disabled).toBe(false));
        expect(city.placeholder).toBe('Escolha a cidade');
        await fireEvent.focus(city);
        const option = await within(dialog).findByRole('option', { name: /Porto Alegre/ });
        expect(within(option).getByText('RS')).toBeTruthy();
        expect(option.closest('ul')?.className).toContain('pc-combo-list--up');
        await fireEvent.click(option);
        expect(crud.getState().form?.labels.city).toBe('Porto Alegre');
    });

    it('formats money as typed, uppercases, and edits a colour by picker or hex', async () => {
        const { crud } = await mount();
        const dialog = await openNew();

        const price = within(dialog).getByLabelText('Preço') as HTMLInputElement;
        expect(price.value).toBe('R$ 0,00');
        await fireEvent.update(price, '123456');
        expect(price.value).toBe('R$ 1.234,56');
        expect(crud.getState().form?.data.price).toBe('R$ 1.234,56');

        const plate = within(dialog).getByLabelText('Placa') as HTMLInputElement;
        await fireEvent.update(plate, 'abc1d23');
        expect(crud.getState().form?.data.plate).toBe('ABC1D23');
        expect(plate.value).toBe('ABC1D23');

        const hex = within(dialog).getByLabelText('Cor') as HTMLInputElement;
        await fireEvent.update(hex, 'abc');
        expect(crud.getState().form?.data.color).toBe('abc');
        await fireEvent.blur(hex);
        expect(crud.getState().form?.data.color).toBe('#aabbcc');
        await fireEvent.update(within(dialog).getByLabelText('Escolher uma cor para Cor'), '#FF0000');
        expect(crud.getState().form?.data.color).toBe('#ff0000');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Limpar a cor' }));
        expect(crud.getState().form?.data.color).toBe('');

        expect(within(dialog).getByLabelText('Peso').getAttribute('step')).toBe('any');
    });

    it('takes an image as a file (preview, remove, uploading) or as a URL', async () => {
        const { crud, server } = await mount();
        const dialog = await openEdit();

        const file = new File(['png'], 'foto.png', { type: 'image/png' });
        await fireEvent.change(dialog.querySelector('#pc-items-photo-file')!, { target: { files: [file] } });
        expect(crud.getState().form?.files.photo).toBe(file);
        await waitFor(() => expect((within(dialog).getByRole('img') as HTMLImageElement).src).toMatch(/^data:image\/png/));

        await fireEvent.click(within(dialog).getByRole('button', { name: 'Remover arquivo' }));
        expect(crud.getState().form?.files.photo).toBeUndefined();
        await waitFor(() => expect((within(dialog).getByRole('img') as HTMLImageElement).src).toBe(PREVIEW));

        await fireEvent.update(within(dialog).getByLabelText('Foto'), 'https://cdn.test/x.png');
        expect(crud.getState().form?.data.photo).toBe('https://cdn.test/x.png');
        await waitFor(() => expect((within(dialog).getByRole('img') as HTMLImageElement).src).toBe('https://cdn.test/x.png'));

        await fireEvent.change(dialog.querySelector('#pc-items-photo-file')!, { target: { files: [file] } });
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar alterações' }));
        expect(within(dialog).getByText('Enviando…')).toBeTruthy();
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(server.calls.find((call) => call.method === 'POST' && call.path === '/1')?.body?.files).toEqual(['files[photo]']);
    });

    it('follows the redirect a save answers, without reloading the list', async () => {
        const { crud, server } = await mount();
        const dialog = await openNew();

        await fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar' }));
        expect(await within(dialog).findByText('Nome é obrigatório.')).toBeTruthy();
        expect(crud.navigate).not.toHaveBeenCalled();

        await fireEvent.update(within(dialog).getByLabelText(/^Nome/), 'Redirect');
        const lists = server.calls.filter((call) => call.method === 'GET' && call.path === '').length;
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar' }));

        await waitFor(() => expect(crud.navigate).toHaveBeenCalledWith('/elsewhere'));
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(server.calls.filter((call) => call.method === 'GET' && call.path === '').length).toBe(lists);
    });

    it('shows the modal loading while the form is on its way, and drops it when closed meanwhile', async () => {
        const server = fakeForm({ slowOpen: true });
        const crud = new CrudController('items', { api: server.api, toastMs: 0, remember: false });
        await crud.init();
        const t = words('pt_BR');
        const Host = defineComponent({
            setup() {
                const state = usePtahCrud(crud);

                return () => (state.value.form || crud.formOpening ? h(PtahForm, { crud, state: state.value, t, locale: 'pt_BR' }) : null);
            },
        });
        render(Host);

        const opening = crud.openCreate();
        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByRole('status').textContent).toContain('Carregando…');
        expect(dialog.getAttribute('aria-busy')).toBe('true');
        expect(within(dialog).queryByRole('button', { name: 'Salvar' })).toBeNull();

        await fireEvent.click(within(dialog).getByRole('button', { name: 'Cancelar' }));
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        server.release();
        await opening;
        expect(crud.getState().form).toBeNull();
        expect(screen.queryByRole('dialog')).toBeNull();

        await crud.openCreate();
        expect(await screen.findByRole('heading', { name: 'Nova Conta' })).toBeTruthy();
    });
});

describe('editing shows a loading at the click (Vue)', () => {
    it('a loading covers the screen at once; the modal opens with the record in it', async () => {
        await mount();
        fireEvent.click(within(screen.getByText('Alfa').closest('tr')!).getByRole('button', { name: 'Editar' }));

        // At the click: the loading, and no empty modal.
        await waitFor(() => expect(document.querySelector('[data-ptah-opening="edit"]')).not.toBeNull());
        expect(screen.queryByRole('dialog')).toBeNull();

        const dialog = await screen.findByRole('dialog');
        expect(dialog.getAttribute('aria-busy')).toBeNull();
        expect(document.querySelector('[data-ptah-opening="edit"]')).toBeNull();
    });
});
