import { describe, expect, it, vi } from 'vitest';
import { toQuery } from '../../core/api';
import { CrudController } from '../../core/controller';
import { applyMask } from '../../core/mask';
import { words } from '../../core/i18n';
import { fakeApi } from '../fake-api';

describe('toQuery', () => {
    it('writes nested maps the way Laravel reads arrays', () => {
        expect(toQuery({ page: 2, filters: { status: 'active' }, trashed: true })).toBe('page=2&filters%5Bstatus%5D=active&trashed=1');
    });

    it('sends an emptied map as an empty value, so the server clears it', () => {
        expect(toQuery({ search: '', filters: {} })).toBe('search=&filters=');
    });
});

describe('applyMask', () => {
    const phone = ['(00) 0000-0000', '(00) 00000-0000'];

    it('picks the smallest shape that holds what was typed', () => {
        expect(applyMask(phone, '4133334444')).toBe('(41) 3333-4444');
        expect(applyMask(phone, '41988887777')).toBe('(41) 98888-7777');
        expect(applyMask(phone, '419')).toBe('(41) 9');
    });

    it('drops what does not fit a slot and leaves an unmasked field alone', () => {
        expect(applyMask(['000.000.000-00'], '529a982.247-25')).toBe('529.982.247-25');
        expect(applyMask(null, 'abc')).toBe('abc');
    });
});

describe('words', () => {
    it('follows the locale and falls back to English', () => {
        expect(words('pt_BR').save).toBe('Salvar');
        expect(words('xx').save).toBe('Save');
    });
});

describe('CrudController', () => {
    const boot = async () => {
        const fake = fakeApi();
        const crud = new CrudController('items', { api: fake.api, toastMs: 0 });
        await crud.init();

        return { ...fake, crud };
    };

    const names = (crud: CrudController) => crud.getState().rows.map((row) => row.cells.name.value);

    it('loads the schema and reopens where the user left', async () => {
        const { crud, calls } = await boot();

        expect(crud.getState().status).toBe('ready');
        expect(crud.getState().schema?.title).toBe('Itens');
        expect(names(crud)).toEqual(['Gama', 'Beta', 'Alfa']);
        expect(calls.some((call) => call.url.includes('restore=1'))).toBe(true);
    });

    it('searches, filters, sorts and pages — remembering each change', async () => {
        const { crud, calls } = await boot();

        await crud.setSearch('bet');
        expect(names(crud)).toEqual(['Beta']);
        expect(calls.at(-1)?.url).toContain('remember=1');

        await crud.clearFilters();
        await crud.setFilter('status', 'active');
        expect(names(crud)).toEqual(['Gama', 'Alfa']);

        await crud.sortBy('name');
        expect(names(crud)).toEqual(['Alfa', 'Gama']);
        await crud.sortBy('name');
        expect(names(crud)).toEqual(['Gama', 'Alfa']);

        await crud.clearFilters();
        await crud.setPerPage(2);
        await crud.setPage(2);
        expect(crud.getState().meta?.page).toBe(2);
        expect(crud.getState().rows).toHaveLength(1);
    });

    it('a new screen instance finds the remembered state', async () => {
        const fake = fakeApi();
        const first = new CrudController('items', { api: fake.api, toastMs: 0 });
        await first.init();
        await first.setSearch('alf');

        const second = new CrudController('items', { api: fake.api, toastMs: 0 });
        await second.init();

        expect(second.getState().list.search).toBe('alf');
        expect(names(second)).toEqual(['Alfa']);
    });

    it('keeps validation errors on the form and closes it once saved', async () => {
        const { crud } = await boot();

        await crud.openCreate();
        expect(crud.getState().form?.data.status).toBe('active');

        expect(await crud.save()).toBe(false);
        expect(crud.getState().form?.errors.name).toBe('Nome is required.');

        crud.setField('name', 'Delta');
        expect(crud.getState().form?.errors.name).toBeUndefined();
        expect(await crud.save()).toBe(true);

        expect(crud.getState().form).toBeNull();
        expect(names(crud)).toContain('Delta');
        expect(crud.getState().toasts.map((toast) => toast.text)).toContain('Salvo!');
    });

    it('edits, duplicates and deletes after confirmation', async () => {
        const { crud } = await boot();

        await crud.openEdit(1);
        expect(crud.getState().form).toMatchObject({ mode: 'edit', id: 1 });
        crud.setField('name', 'Alfa 2');
        await crud.save();
        expect(names(crud)).toContain('Alfa 2');

        await crud.duplicate(2);
        expect(crud.getState().form).toMatchObject({ mode: 'create', id: null, data: { name: 'Beta' } });
        crud.closeForm();

        crud.askDelete(2);
        expect(crud.getState().deleting).toBe(2);
        await crud.confirmDelete();
        expect(names(crud)).not.toContain('Beta');
    });

    it('selects rows and runs a bulk action over them', async () => {
        const { crud, calls } = await boot();

        crud.toggleAll();
        expect(crud.getState().selection).toHaveLength(3);
        crud.toggleRow(1);
        expect(crud.getState().selection).toEqual(['3', '2']);

        await crud.bulk('delete');
        expect(calls.find((call) => call.url.endsWith('/bulk'))?.body).toMatchObject({ action: 'delete', ids: ['3', '2'] });
        expect(names(crud)).toEqual(['Alfa']);
        expect(crud.getState().selection).toEqual([]);
    });

    it('opens the export URL and hands a named row action to the host', async () => {
        const fake = fakeApi();
        const open = vi.fn();
        const onAction = vi.fn();
        const crud = new CrudController('items', { api: fake.api, toastMs: 0, open, onAction });
        await crud.init();

        await crud.export('excel');
        expect(open).toHaveBeenCalledWith('/ptah/export/download/abc');

        const row = crud.getState().rows[0];
        crud.runAction(row, { label: 'Aprovar', type: 'action', value: 'approve(3)', icon: null, color: 'primary', confirm: null });
        expect(onAction).toHaveBeenCalledWith('approve(3)', row, expect.anything());
    });

    it('says why a screen cannot be shown', async () => {
        const fake = fakeApi();
        const crud = new CrudController('closed', { api: fake.api });
        await crud.init();

        expect(crud.getState().status).toBe('forbidden');
    });

    it('notifies subscribers and ignores a stale listing answer', async () => {
        const { crud } = await boot();
        const seen = vi.fn();
        const stop = crud.subscribe(seen);

        const slow = crud.setSearch('alf');
        const fast = crud.setSearch('gam');
        await Promise.all([slow, fast]);

        expect(names(crud)).toEqual(['Gama']);
        expect(seen).toHaveBeenCalled();
        stop();
    });
});
