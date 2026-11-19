import { afterEach, describe, expect, it, vi } from 'vitest';
import { CrudController, EDIT_INTENT_MS, EDIT_WARM_MS } from '../../core/controller';
import { fakeApi } from '../fake-api';

afterEach(() => vi.useRealTimers());

const shows = (fake: ReturnType<typeof fakeApi>) => fake.calls.filter((call) => call.method === 'GET' && /^\/crud\/items\/\d+$/.test(new URL(call.url, 'http://localhost').pathname.replace('/ptah/api', ''))).length;

describe('the record asked for ahead, on the intent to edit it', () => {
    it('resting on the edit button asks for the record; the click that follows asks nothing', async () => {
        const fake = fakeApi();
        const crud = new CrudController('items', { api: fake.api });
        await crud.init();
        vi.useFakeTimers();

        crud.intendEdit(2);
        expect(shows(fake)).toBe(0);
        await vi.advanceTimersByTimeAsync(EDIT_INTENT_MS);
        expect(shows(fake)).toBe(1);

        await crud.openEdit(2);
        expect(shows(fake)).toBe(1);
        expect(crud.getState().form).toMatchObject({ mode: 'edit', id: 2 });
        expect(crud.formOpening).toBeNull();
        crud.destroy();
    });

    it('a pointer that only passes over the button asks nothing', async () => {
        const fake = fakeApi();
        const crud = new CrudController('items', { api: fake.api });
        await crud.init();
        vi.useFakeTimers();

        crud.intendEdit(2);
        await vi.advanceTimersByTimeAsync(EDIT_INTENT_MS - 10);
        crud.leaveEdit();
        await vi.advanceTimersByTimeAsync(EDIT_INTENT_MS * 3);

        expect(shows(fake)).toBe(0);
        crud.destroy();
    });

    it('never opens another record, nor an old answer, from what was asked ahead', async () => {
        const fake = fakeApi();
        const crud = new CrudController('items', { api: fake.api });
        await crud.init();
        vi.useFakeTimers();

        crud.intendEdit(2);
        await vi.advanceTimersByTimeAsync(EDIT_INTENT_MS);
        await crud.openEdit(1);
        expect(crud.getState().form).toMatchObject({ mode: 'edit', id: 1 });
        expect(shows(fake)).toBe(2);
        crud.closeForm();

        crud.intendEdit(2);
        await vi.advanceTimersByTimeAsync(EDIT_INTENT_MS);
        expect(shows(fake)).toBe(3);
        await vi.advanceTimersByTimeAsync(EDIT_WARM_MS + 1);
        await crud.openEdit(2);
        expect(shows(fake)).toBe(4);
        crud.destroy();
    });

    it('while the record is on its way the screen is told an edit is opening, with no form yet', async () => {
        const fake = fakeApi();
        const crud = new CrudController('items', { api: fake.api });
        await crud.init();

        const opening = crud.openEdit(2);
        expect(crud.formOpening).toBe('edit');
        expect(crud.getState().form).toBeNull();
        await opening;
        expect(crud.formOpening).toBeNull();
        expect(crud.getState().form?.id).toBe(2);
        crud.destroy();
    });
});
