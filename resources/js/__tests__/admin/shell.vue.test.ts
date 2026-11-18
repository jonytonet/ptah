import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PtahExportsPanel, PtahNotificationBell } from '../../vue/admin/shell';
import { fakeShell } from './shell.fake';

afterEach(() => {
    cleanup();
    vi.useRealTimers();
    Reflect.deleteProperty(document, 'hidden');
});

const bell = () => screen.getByRole('button', { name: /^Notificações/ });
const setHidden = (hidden: boolean) => {
    Object.defineProperty(document, 'hidden', { configurable: true, get: () => hidden });
    document.dispatchEvent(new Event('visibilitychange'));
};

describe('notification bell (Vue)', () => {
    it('shows the unread badge, opens the dropdown and closes on Escape', async () => {
        const { api, calls } = fakeShell();
        render(PtahNotificationBell, { props: { api, locale: 'pt_BR' } });

        await waitFor(() => expect(bell().getAttribute('aria-label')).toContain('2 notificação(ões) não lida(s)'));
        expect(bell().textContent).toContain('2');
        // A closed dropdown never asks for the list.
        expect(calls.map((call) => call.path)).toEqual(['/notifications']);

        await fireEvent.click(bell());
        const menu = await screen.findByRole('menu');
        expect(bell().getAttribute('aria-expanded')).toBe('true');
        await within(menu).findByText('Pedido aprovado');
        expect(within(menu).getByText('O pedido #7 foi aprovado.')).toBeTruthy();
        expect(within(menu).getByText('Abrir pedido')).toBeTruthy();
        expect(calls.at(-1)?.path).toBe('/notifications?items=1');

        // Without a URL the row is informational only.
        const plain = within(menu).getByText('Backup concluído').closest('button') as HTMLButtonElement;
        expect(plain.disabled).toBe(true);

        await fireEvent.keyDown(document, { key: 'Escape' });
        await waitFor(() => expect(screen.queryByRole('menu')).toBeNull());
    });

    it('opening a notification marks it read and goes to its URL', async () => {
        const { api, notifications } = fakeShell();
        const go = vi.fn();
        render(PtahNotificationBell, { props: { api, locale: 'pt_BR', onNavigate: go } });
        await waitFor(() => bell());

        await fireEvent.click(bell());
        await fireEvent.click(await screen.findByRole('menuitem', { name: /^Pedido aprovado/ }));

        await waitFor(() => expect(go).toHaveBeenCalledWith('/orders/7'));
        expect(notifications().find((item) => item.id === 1)?.read).toBe(true);
    });

    it('dismisses a notification and marks all as read with a confirmation', async () => {
        const { api } = fakeShell();
        render(PtahNotificationBell, { props: { api, locale: 'pt_BR' } });
        await waitFor(() => bell());

        await fireEvent.click(bell());
        await fireEvent.click(await screen.findByRole('menuitem', { name: 'Dispensar: Backup concluído' }));
        await waitFor(() => expect(screen.queryByText('Backup concluído')).toBeNull());
        expect(bell().getAttribute('aria-label')).toContain('1 notificação');

        await fireEvent.click(screen.getByRole('menuitem', { name: 'Marcar todas como lidas' }));
        expect((await screen.findByRole('status')).textContent).toContain('1 notificações marcadas como lidas.');
        await waitFor(() => expect(bell().getAttribute('aria-label')).toBe('Notificações'));
    });

    it('shows the empty state', async () => {
        render(PtahNotificationBell, { props: { api: fakeShell({ noNotifications: true }).api, locale: 'pt_BR' } });
        await waitFor(() => bell());

        await fireEvent.click(bell());
        expect(await screen.findByText('Nenhuma notificação.')).toBeTruthy();
    });

    it('draws nothing when the feature is off', async () => {
        const { api, calls } = fakeShell({ enabled: false });
        const { container } = render(PtahNotificationBell, { props: { api, locale: 'pt_BR' } });

        await waitFor(() => expect(calls.length).toBe(1));
        await waitFor(() => expect(container.querySelector('button')).toBeNull());
    });

    it('polls at the server interval, pauses while hidden and stops on unmount', async () => {
        vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
        const fake = fakeShell({ poll: 60000 });
        const view = render(PtahNotificationBell, { props: { api: fake.api, locale: 'pt_BR' } });
        await waitFor(() => expect(bell().getAttribute('aria-label')).toContain('2 notificação'));

        fake.push({ title: 'Nova' });
        await vi.advanceTimersByTimeAsync(60000);
        await waitFor(() => expect(bell().getAttribute('aria-label')).toContain('3 notificação'));
        expect(fake.calls.length).toBe(2);

        setHidden(true);
        await vi.advanceTimersByTimeAsync(180000);
        expect(fake.calls.length).toBe(2);

        // Back in view: refreshes at once, then keeps polling.
        setHidden(false);
        await waitFor(() => expect(fake.calls.length).toBe(3));

        view.unmount();
        await vi.advanceTimersByTimeAsync(180000);
        expect(fake.calls.length).toBe(3);
    });

    it('the pollInterval prop overrides the server; null turns polling off', async () => {
        vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
        const fast = fakeShell({ poll: 60000 });
        const view = render(PtahNotificationBell, { props: { api: fast.api, locale: 'pt_BR', pollInterval: 1000 } });
        await waitFor(() => bell());
        await vi.advanceTimersByTimeAsync(3000);
        expect(fast.calls.length).toBe(4);
        view.unmount();

        const off = fakeShell({ poll: 60000 });
        render(PtahNotificationBell, { props: { api: off.api, locale: 'pt_BR', pollInterval: null } });
        await waitFor(() => bell());
        await vi.advanceTimersByTimeAsync(600000);
        expect(off.calls.length).toBe(1);
    });
});

describe('exports panel (Vue)', () => {
    it('lists the exports with status, error and download link', async () => {
        render(PtahExportsPanel, { props: { api: fakeShell().api, locale: 'pt_BR' } });

        expect(screen.getByRole('status', { name: 'Carregando…' })).toBeTruthy();
        expect(await screen.findByText('Order')).toBeTruthy();
        expect(screen.getByRole('heading', { name: 'Exportações' })).toBeTruthy();
        expect(screen.getByText('Falhou')).toBeTruthy();
        expect(screen.getByText('Sem memória')).toBeTruthy();
        const links = screen.getAllByRole('link', { name: 'Baixar' });
        expect(links.map((link) => link.getAttribute('href'))).toEqual(['/ptah/export/file/1', '/ptah/export/file/3']);
        expect(screen.getByText('—')).toBeTruthy();
    });

    it('deletes after a confirmation, and shows the empty state', async () => {
        const fake = fakeShell();
        render(PtahExportsPanel, { props: { api: fake.api, locale: 'pt_BR' } });
        await screen.findByText('Order');

        const row = screen.getByText('Customer').closest('tr') as HTMLElement;
        await fireEvent.click(within(row).getByRole('button', { name: 'Excluir' }));
        let dialog = await screen.findByRole('dialog');
        expect(dialog.textContent).toContain('Remover esta exportação?');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Cancelar' }));
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(fake.exports().length).toBe(3);

        await fireEvent.click(within(row).getByRole('button', { name: 'Excluir' }));
        dialog = await screen.findByRole('dialog');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Excluir' }));
        await waitFor(() => expect(screen.queryByText('Customer')).toBeNull());
        expect(fake.calls.at(-1)).toEqual({ method: 'DELETE', path: '/exports/2?page=1' });
        cleanup();

        render(PtahExportsPanel, { props: { api: fakeShell({ noExports: true }).api, locale: 'pt_BR' } });
        expect(await screen.findByText('Nenhuma exportação ainda.')).toBeTruthy();
    });

    it('paginates', async () => {
        render(PtahExportsPanel, { props: { api: fakeShell({ manyExports: true }).api, locale: 'pt_BR' } });
        await screen.findByText('Model1');

        await fireEvent.click(screen.getByRole('button', { name: '2' }));
        expect(await screen.findByText('Model11')).toBeTruthy();
        expect(screen.queryByText('Model1')).toBeNull();
    });

    it('polls while an export is pending and stops once all have settled', async () => {
        vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
        const fake = fakeShell({ pendingExports: true });
        render(PtahExportsPanel, { props: { api: fake.api, locale: 'pt_BR' } });
        expect(await screen.findByText('Processando')).toBeTruthy();

        fake.settle();
        await vi.advanceTimersByTimeAsync(5000);
        await waitFor(() => expect(screen.queryByText('Processando')).toBeNull());
        expect(fake.calls.length).toBe(2);

        await vi.advanceTimersByTimeAsync(60000);
        expect(fake.calls.length).toBe(2);
    });

    it('shows an error when the server fails', async () => {
        const { api } = fakeShell();
        vi.spyOn(api, 'call').mockRejectedValueOnce(new Error('boom'));
        render(PtahExportsPanel, { props: { api, locale: 'pt_BR' } });

        expect((await screen.findByRole('alert')).textContent).toContain('Algo deu errado');
    });
});
