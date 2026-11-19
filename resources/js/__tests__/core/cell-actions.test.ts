import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { CrudController } from '../../core/controller';

const filterByCell = vi.fn(() => Promise.resolve(true));
vi.mock('../../core/crud/filters', () => ({ filterByCell: (...args: unknown[]) => filterByCell(...(args as [])) }));

import { cellActionWords, installCellActions } from '../../core/crud/cell-actions';

const words = cellActionWords('pt_BR');

function screen(key: string, cells: string): HTMLElement {
    const root = document.createElement('div');
    root.dataset.ptahCrud = key;
    root.innerHTML = `<table><tbody><tr>${cells}</tr></tbody></table>`;
    document.body.appendChild(root);

    return root;
}

const fakeCrud = (key: string, toast = vi.fn()) => ({ screen: key, toast }) as unknown as CrudController;
const over = (element: Element) => element.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
const group = () => document.querySelector('.pc-cell-actions') as HTMLElement | null;
const button = (label: string) => document.querySelector(`.pc-cell-action[aria-label="${label}"]`) as HTMLButtonElement;

describe('copy / filter buttons over a cell', () => {
    let stop: () => void = () => undefined;

    beforeEach(() => {
        vi.useFakeTimers();
        filterByCell.mockClear();
    });
    afterEach(() => {
        stop();
        vi.useRealTimers();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('shows after the mouse rests on a cell, and copies the text the person sees', async () => {
        const writeText = vi.fn(() => Promise.resolve());
        vi.stubGlobal('navigator', { clipboard: { writeText } });
        const toast = vi.fn();
        const root = screen('items', '<td data-ptah-cell="email" data-ptah-row="7" data-ptah-filter=""><b>ana@example.test</b></td>');
        stop = installCellActions(fakeCrud('items', toast), { words, enabled: () => true });

        over(root.querySelector('b')!);
        expect(group()).toBeNull();
        vi.advanceTimersByTime(300);
        expect(group()!.classList.contains('is-visible')).toBe(true);

        button('Copiar').click();
        await vi.runAllTimersAsync();

        expect(writeText).toHaveBeenCalledWith('ana@example.test');
        expect(toast).toHaveBeenCalledWith('Copiado', 'success');
        expect(group()!.classList.contains('is-visible')).toBe(false);
    });

    it('filters by the value of the record, only on a filterable column', () => {
        const root = screen('items', '<td data-ptah-cell="email" data-ptah-row="7" data-ptah-filter="">a</td><td data-ptah-cell="phone" data-ptah-row="7">b</td>');
        const crud = fakeCrud('items');
        stop = installCellActions(crud, { words, enabled: () => true, delay: 0 });
        const [email, phone] = Array.from(root.querySelectorAll('td'));

        over(phone);
        vi.advanceTimersByTime(0);
        expect(button('Filtrar por este valor').style.display).toBe('none');

        over(email);
        vi.advanceTimersByTime(200);
        expect(button('Filtrar por este valor').style.display).toBe('');
        button('Filtrar por este valor').click();

        expect(filterByCell).toHaveBeenCalledWith(crud, '7', 'email');
    });

    it('says so when nothing could copy', async () => {
        vi.stubGlobal('navigator', { clipboard: { writeText: () => Promise.reject(new Error('denied')) } });
        document.execCommand = vi.fn(() => false);
        const toast = vi.fn();
        const root = screen('items', '<td data-ptah-cell="name" data-ptah-row="1">Alfa</td>');
        stop = installCellActions(fakeCrud('items', toast), { words, enabled: () => true, delay: 0 });

        over(root.querySelector('td')!);
        vi.advanceTimersByTime(0);
        button('Copiar').click();
        await vi.runAllTimersAsync();

        expect(toast).toHaveBeenCalledWith('Não foi possível copiar', 'danger');
    });

    it('leaves alone: an empty cell, a cell with a control, another screen, a screen with copy off', () => {
        const root = screen(
            'items',
            '<td data-ptah-cell="a" data-ptah-row="1">  </td><td data-ptah-cell="b" data-ptah-row="1"><button>x</button></td><td>no marker</td><td data-ptah-cell="d" data-ptah-row="1">ok</td>',
        );
        const other = screen('items..1.0', '<td data-ptah-cell="n" data-ptah-row="2">child</td>');
        let enabled = true;
        stop = installCellActions(fakeCrud('items'), { words, enabled: () => enabled, delay: 0 });
        const cells = Array.from(root.querySelectorAll('td'));

        for (const cell of [cells[0], cells[1], cells[2], other.querySelector('td')!]) {
            over(cell);
            vi.advanceTimersByTime(200);
            expect(group()?.classList.contains('is-visible') ?? false).toBe(false);
        }

        enabled = false;
        over(cells[3]);
        vi.advanceTimersByTime(200);
        expect(group()?.classList.contains('is-visible') ?? false).toBe(false);

        enabled = true;
        over(cells[2]);
        over(cells[3]);
        vi.advanceTimersByTime(200);
        expect(group()!.classList.contains('is-visible')).toBe(true);
    });

    it('removes itself when the screen goes away', () => {
        const root = screen('items', '<td data-ptah-cell="name" data-ptah-row="1">Alfa</td>');
        stop = installCellActions(fakeCrud('items'), { words, enabled: () => true, delay: 0 });
        over(root.querySelector('td')!);
        vi.advanceTimersByTime(0);
        expect(group()).not.toBeNull();

        stop();
        expect(group()).toBeNull();
    });
});
