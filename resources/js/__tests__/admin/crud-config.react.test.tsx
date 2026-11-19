import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { CrudController } from '../../core';
import { PtahCrudConfig, PtahCrudConfigButton } from '../../react/admin/crud-config';
import { fakeCrudConfig } from './crud-config.fake';

afterEach(cleanup);

const type = (label: RegExp | string, value: string) => fireEvent.change(screen.getByLabelText(label), { target: { value } });
const click = (name: string | RegExp, role = 'button') => fireEvent.click(screen.getByRole(role, { name }));
const choose = (select: string | RegExp, option: string | RegExp) => {
    click(select);
    click(option, 'option');
};
const lastCall = (calls: ReturnType<typeof fakeCrudConfig>['calls'], method: string, path: RegExp) => [...calls].reverse().find((call) => call.method === method && path.test(call.path));

const open = async (fake = fakeCrudConfig(), props: { onSaved?: () => void; onClose?: () => void; locale?: string } = {}) => {
    render(<PtahCrudConfig api={fake.api} screen="items" locale={props.locale ?? 'en'} onSaved={props.onSaved} onClose={props.onClose} />);
    await screen.findByRole('tab', { name: 'Columns (3)' });

    return fake;
};

describe('crud config editor (React)', () => {
    it('loads the document: tabs with counts, the columns table, the route badge', async () => {
        await open();

        expect(screen.getByRole('dialog', { name: 'CRUD Config' })).toBeTruthy();
        expect(screen.getByRole('tab', { name: 'Actions (1)' })).toBeTruthy();
        expect(screen.getByRole('tab', { name: 'Notifications (0)' })).toBeTruthy();
        expect(screen.getByText('/items')).toBeTruthy();
        expect(screen.getByText('App\\Models\\Item')).toBeTruthy();
        const table = screen.getAllByRole('table')[0];
        expect(within(table).getByText('Nome')).toBeTruthy();
        expect(within(table).getByText('money')).toBeTruthy();
        // A row action is not a table column.
        expect(within(table).queryByText('Ver')).toBeNull();
        expect(screen.getByText('4 columns · 0 filters · 0 styles')).toBeTruthy();
    });

    it('shows a loading state, then the empty columns state', async () => {
        render(<PtahCrudConfig api={fakeCrudConfig({ empty: true }).api} screen="items" locale="en" />);
        expect(screen.getByRole('status', { name: 'Loading the configuration…' })).toBeTruthy();
        expect(await screen.findByText('No columns configured. Add below.')).toBeTruthy();
    });

    it('a user the editor refuses sees why', async () => {
        render(<PtahCrudConfig api={fakeCrudConfig({ forbidden: true }).api} screen="items" locale="en" />);
        expect((await screen.findByRole('alert')).textContent).toBe('You may not configure this screen.');
    });

    it('adds a column: nothing without a physical name, then the component defaults', async () => {
        const { calls } = await open();

        click('Add Column');
        expect((await screen.findByText('Fill in the physical field first.')).getAttribute('role')).toBe('alert');

        type('Physical Field (DB) *', 'sku');
        choose('Type', 'number — Number');
        click('Add Column');

        await screen.findByRole('tab', { name: 'Columns (4)' });
        expect(lastCall(calls, 'POST', /actions\/addField$/)?.body).toMatchObject({ form: { colsNomeFisico: 'sku', colsTipo: 'number' }, editing: -1 });
        expect(within(screen.getAllByRole('table')[0]).getByText('Sku')).toBeTruthy();
        expect((screen.getByLabelText('Physical Field (DB) *') as HTMLInputElement).value).toBe('');
    });

    it('edits a column through its sub-tabs and saves it back', async () => {
        const { calls } = await open();

        click('Edit: Valor');
        await waitFor(() => expect((screen.getByLabelText('Label (display)') as HTMLInputElement).value).toBe('Valor'));
        expect(screen.getByText('✏ Editing column')).toBeTruthy();
        type('Label (display)', 'Total');

        click('Validation', 'tab');
        fireEvent.click(screen.getByLabelText('Valid e-mail'));
        type('Minimum Value (min:X)', '5');
        expect(screen.getByText('min:5')).toBeTruthy();

        click('Totalizer', 'tab');
        choose('Function', 'COUNT — Count');

        click('Save Column Changes');
        await waitFor(() => expect(within(screen.getAllByRole('table')[0]).getByText('Total')).toBeTruthy());
        expect(lastCall(calls, 'POST', /actions\/updateField$/)?.body).toMatchObject({ editing: 2, form: { colsNomeLogico: 'Total', colsValidations: ['email', 'min:5'], totalizadorType: 'count' } });
        expect(screen.getByText('+ New Column')).toBeTruthy();
    });

    it('the SearchDropdown sub-tab exists only for that type', async () => {
        await open();
        expect(screen.queryByRole('tab', { name: 'SearchDropdown' })).toBeNull();
        choose('Type', 'searchdropdown — Relational Search');
        click('SearchDropdown', 'tab');
        expect(screen.getByLabelText('Model (relative to App\\Models)')).toBeTruthy();
        fireEvent.click(screen.getByLabelText('Custom Service'));
        expect(screen.getByLabelText('Service Method')).toBeTruthy();
    });

    it('removes a column after the confirmation, and reorders', async () => {
        const { calls } = await open();

        click('Remove: Valor');
        const dialog = await screen.findByRole('dialog', { name: 'Confirm' });
        expect(dialog.textContent).toContain("Remove column 'Valor'?");
        fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));
        expect(screen.queryByRole('dialog', { name: 'Confirm' })).toBeNull();

        click('Remove: Valor');
        fireEvent.click(within(await screen.findByRole('dialog', { name: 'Confirm' })).getByRole('button', { name: 'Remove' }));
        await screen.findByRole('tab', { name: 'Columns (2)' });
        expect(lastCall(calls, 'POST', /actions\/removeField$/)?.body).toMatchObject({ index: 2 });

        click('Move down: Nome');
        await waitFor(() => expect(lastCall(calls, 'POST', /actions\/reorderFields$/)?.body).toMatchObject({ order: [1, 0, 2] }));
    });

    it('row actions: add and edit', async () => {
        const { calls } = await open();
        click('Actions (1)', 'tab');

        type('Action Name', 'Aprovar');
        type('Value', 'approve(%id%)');
        type('Icon (Boxicons CSS class)', 'bx bx-check');
        expect(screen.getByTestId('cfg-icon-preview').querySelector('i')?.className).toBe('bx bx-check');
        click('+ Add Action');
        await screen.findByRole('tab', { name: 'Actions (2)' });
        expect(lastCall(calls, 'POST', /actions\/addAction$/)?.body).toMatchObject({ form: { colsNomeLogico: 'Aprovar', actionValue: 'approve(%id%)' } });

        click('Edit: Ver');
        await waitFor(() => expect((screen.getByLabelText('Action Name') as HTMLInputElement).value).toBe('Ver'));
        expect(screen.getByRole('button', { name: '💾 Save Changes' })).toBeTruthy();
    });

    it('custom filters and conditional styles: add (with a preset) and remove', async () => {
        await open();

        click('Custom Filters (0)', 'tab');
        type('Field (identifier)', 'supplier_name');
        type('whereHas (relation name)', 'supplier');
        click('+ Add Filter');
        await screen.findByRole('tab', { name: 'Custom Filters (1)' });
        expect(screen.getByText('whereHas: supplier')).toBeTruthy();
        click('Remove: supplier_name');
        fireEvent.click(within(await screen.findByRole('dialog', { name: 'Confirm' })).getByRole('button', { name: 'Remove' }));
        await screen.findByRole('tab', { name: 'Custom Filters (0)' });

        click('Styles (0)', 'tab');
        type('Field', 'status');
        click('Success');
        expect((screen.getByLabelText('Inline CSS') as HTMLInputElement).value).toBe('background:#D4EDDA;color:#155724;');
        click('+ Add Style');
        await screen.findByRole('tab', { name: 'Styles (1)' });
    });

    it('JOINs: the duplicate table is refused, edit rebuilds the columns text', async () => {
        const { calls } = await open();
        click('JOINs (0)', 'tab');
        expect(screen.getByText('No JOINs configured')).toBeTruthy();

        type('Table (name in the database)', 'suppliers');
        type('Columns to select (one per line — format table.column:alias)', 'suppliers.name:supplier_name');
        click('+ Add JOIN');
        await screen.findByRole('tab', { name: 'JOINs (1)' });
        expect(screen.getByText('LEFT JOIN')).toBeTruthy();

        type('Table (name in the database)', 'suppliers');
        expect(screen.getByText(/A JOIN for table suppliers already exists/)).toBeTruthy();
        expect((screen.getByRole('button', { name: '+ Add JOIN' }) as HTMLButtonElement).disabled).toBe(true);

        click('Edit: suppliers');
        await waitFor(() => expect((screen.getByLabelText(/Columns to select/) as HTMLTextAreaElement).value).toBe('suppliers.name:supplier_name'));
        expect(lastCall(calls, 'POST', /actions\/editJoin$/)?.body).toMatchObject({ index: 0 });
        expect(screen.getByRole('button', { name: 'Update JOIN' })).toBeTruthy();
    });

    it('notification rules: the audience count, then a rule', async () => {
        await open();
        click('Notifications (0)', 'tab');

        choose('Audience', 'All staff');
        await waitFor(() => expect(screen.getByLabelText(/Audience value \(7 recipient/)).toBeTruthy());
        expect((screen.getByLabelText(/Audience value/) as HTMLInputElement).disabled).toBe(true);

        click('Add rule');
        expect(await screen.findByText('Fill in the event and the title first.')).toBeTruthy();
        choose('Event', 'Created');
        type('Title', 'Novo item');
        click('Add rule');
        await screen.findByRole('tab', { name: 'Notifications (1)' });
        expect(screen.getByText('Novo item')).toBeTruthy();
    });

    it('the notifications tab explains how to install the module when it is off', async () => {
        await open(fakeCrudConfig({ notifications: false }));
        click('Notifications (0)', 'tab');
        expect(screen.getByText('Notification module disabled')).toBeTruthy();
    });

    it('general settings, permissions and hooks are saved with the document', async () => {
        const onSaved = vi.fn();
        const onClose = vi.fn();
        const fake = await open(fakeCrudConfig(), { onSaved, onClose });

        click('General', 'tab');
        type('Display Name', 'Produtos');
        fireEvent.click(screen.getAllByLabelText('Enabled')[1]);
        expect(screen.getByText(/"echo-private:page-item-observer,\.pageItemObserver" => 'handleBaseCrudUpdate'/)).toBeTruthy();
        type('TTL (seconds)', '60');

        click('Permissions', 'tab');
        type('Gate: Create', 'manage-items');
        fireEvent.click(screen.getByLabelText('Trash Button'));

        click('Lifecycle Hooks', 'tab');
        type(/^Before Create/, "merge(data, {'status': 'new'})");

        click('Save Configuration');
        await waitFor(() => expect(onSaved).toHaveBeenCalled());
        expect(onClose).toHaveBeenCalled();
        expect(fake.saved().settings).toMatchObject({ displayName: 'Produtos', broadcastEnabled: true, cacheTtl: 60, permissionCreate: 'manage-items', showTrashButton: false, hookBeforeCreate: "merge(data, {'status': 'new'})" });
    });

    it('shows the schema refusal in the editor', async () => {
        const fake = fakeCrudConfig();
        const original = fake.api.call.bind(fake.api);
        // The editor sends what it holds; make the stored column bogus first.
        vi.spyOn(fake.api, 'call').mockImplementation((method: string, path: string, body?: unknown) => {
            if (method === 'PUT') {
                const document = (body as { document: { columns: Record<string, unknown>[] } }).document;
                document.columns[0] = { ...document.columns[0], colsTipo: 'bogus' };
            }

            return original(method, path, body);
        });
        const onSaved = vi.fn();
        await open(fake, { onSaved });

        click('Save Configuration');
        expect((await screen.findByText(/Invalid column type "bogus"/)).closest('[role="alert"]')).toBeTruthy();
        expect(onSaved).not.toHaveBeenCalled();
        expect(screen.getByRole('dialog', { name: 'CRUD Config' })).toBeTruthy();
    });

    it('previews the form from the columns being edited', async () => {
        await open();
        click('Preview form');
        const dialog = await screen.findByRole('dialog', { name: 'Form preview — Itens' });
        expect(within(dialog).getByLabelText(/Nome/)).toBeTruthy();
        expect((within(dialog).getByLabelText(/Situação/) as HTMLSelectElement).disabled).toBe(true);
        expect(within(dialog).getByText('Arquivado')).toBeTruthy();
    });

    it('speaks Portuguese', async () => {
        render(<PtahCrudConfig api={fakeCrudConfig().api} screen="items" locale="pt_BR" />);
        expect(await screen.findByRole('tab', { name: 'Colunas (3)' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Salvar Configuração' })).toBeTruthy();
    });
});

describe('crud config button (React)', () => {
    const crudOf = (api: ReturnType<typeof fakeCrudConfig>['api']) => ({ api, screen: 'items', savePreferences: vi.fn(async () => undefined) }) as unknown as CrudController & { savePreferences: ReturnType<typeof vi.fn> };

    it('renders nothing for a user who may not configure', async () => {
        const fake = fakeCrudConfig({ forbidden: true });
        const { container } = render(<PtahCrudConfigButton crud={crudOf(fake.api)} locale="en" />);
        await waitFor(() => expect(fake.calls.some((call) => call.path === '/crud/items/config/can')).toBe(true));
        expect(container.innerHTML).toBe('');
    });

    it('opens the editor and makes the listing re-read its config after a save', async () => {
        const fake = fakeCrudConfig();
        const crud = crudOf(fake.api);
        render(<PtahCrudConfigButton crud={crud} locale="en" />);

        fireEvent.click(await screen.findByRole('button', { name: 'Configure CRUD' }));
        await screen.findByRole('tab', { name: 'Columns (3)' });
        click('Save Configuration');

        await waitFor(() => expect(crud.savePreferences).toHaveBeenCalledWith({}));
        expect(screen.queryByRole('dialog', { name: 'CRUD Config' })).toBeNull();
    });
});
