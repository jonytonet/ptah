import { afterEach, describe, expect, it, vi } from 'vitest';
import { CrudController } from '../../core/controller';
import { fakeApi } from '../fake-api';

afterEach(() => vi.useRealTimers());

describe('"Undo" on the toast of a delete', () => {
    it('a message that carries the record becomes a toast with Undo, which stays longer', async () => {
        const crud = new CrudController('items', { api: fakeApi().api });
        await crud.init();
        vi.useFakeTimers();

        crud.toast('Excluído.', 'warn', 2);
        crud.toast('Salvo!', 'success');
        expect(crud.getState().toasts.map((toast) => toast.undo ?? null)).toEqual([2, null]);

        // The plain toast goes at 4 s; the one with Undo waits until 6 s.
        vi.advanceTimersByTime(4100);
        expect(crud.getState().toasts.map((toast) => toast.text)).toEqual(['Excluído.']);
        vi.advanceTimersByTime(2000);
        expect(crud.getState().toasts).toEqual([]);
        crud.destroy();
    });

    it('Undo restores that record and closes the toast', async () => {
        const fake = fakeApi();
        const crud = new CrudController('items', { api: fake.api });
        await crud.init();

        crud.toast('Excluído.', 'warn', 2);
        const [toast] = crud.getState().toasts;
        await crud.undo(toast.id);

        expect(crud.getState().toasts.find((item) => item.id === toast.id)).toBeUndefined();
        expect(fake.calls.some((call) => call.method === 'POST' && call.url.endsWith('/crud/items/2/restore'))).toBe(true);
        crud.destroy();
    });

    it('a toast without a record has nothing to undo', async () => {
        const fake = fakeApi();
        const crud = new CrudController('items', { api: fake.api });
        await crud.init();

        crud.toast('Salvo!', 'success');
        await crud.undo(crud.getState().toasts[0].id);

        expect(fake.calls.some((call) => call.url.includes('/restore'))).toBe(false);
        crud.destroy();
    });
});
