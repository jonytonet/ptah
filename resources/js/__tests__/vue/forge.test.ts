import { cleanup, fireEvent, render, screen, within } from '@testing-library/vue';
import { readdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';
import * as kit from '../../vue';
import {
    ForgeAlert,
    ForgeButton,
    ForgeInput,
    ForgeModal,
    ForgePagination,
    ForgeProgress,
    ForgeSelect,
    ForgeStepper,
    ForgeSwitch,
    ForgeTable,
    ForgeTabs,
    ForgeTextarea,
    forgeToast,
    ForgeToastHost,
} from '../../vue';

afterEach(cleanup);

const exportName = (file: string) =>
    file
        .replace('.blade.php', '')
        .split('-')
        .map((part) => part[0].toUpperCase() + part.slice(1))
        .join('');

const COVERED_BY: Record<string, string> = { ForgeTab: 'ForgeTabs', ForgeSidebar: 'ForgeDashboardLayout', ForgeNavbar: 'ForgeDashboardLayout' };

describe('forge kit (Vue)', () => {
    it('has every forge-* Blade component', () => {
        const missing = readdirSync(resolve(__dirname, '../../../views/components'))
            .filter((file) => file.startsWith('forge-'))
            .map(exportName)
            .map((name) => COVERED_BY[name] ?? name)
            .filter((name) => !(name in kit));

        expect(missing).toEqual([]);
    });

    it('button: loading disables it, href makes it a link', () => {
        render(defineComponent({ render: () => [h(ForgeButton, { loading: true }, () => 'Salvar'), h(ForgeButton, { href: '/x', color: 'danger' }, () => 'Ir')] }));

        expect((screen.getByRole('button', { name: 'Salvar' }) as HTMLButtonElement).disabled).toBe(true);
        expect(screen.getByRole('link', { name: 'Ir' }).getAttribute('data-color')).toBe('danger');
    });

    it('alert closes; progress clamps', async () => {
        const view = render(defineComponent({ render: () => [h(ForgeAlert, { type: 'danger', title: 'Erro', closable: true }, () => 'Falhou'), h(ForgeProgress, { value: 140, label: true })] }));

        expect(screen.getByRole('alert').textContent).toContain('Falhou');
        await fireEvent.click(screen.getByRole('button', { name: 'Close' }));
        expect(screen.queryByRole('alert')).toBeNull();
        expect(screen.getByRole('progressbar').getAttribute('aria-valuenow')).toBe('100');
        view.unmount();
    });

    it('input shows its error and works with v-model', async () => {
        const value = ref('');
        render(defineComponent({ render: () => h(ForgeInput, { label: 'E-mail', error: 'Inválido', required: true, modelValue: value.value, 'onUpdate:modelValue': (next: string) => (value.value = next) }) }));

        const input = screen.getByLabelText(/E-mail/);
        expect(input.getAttribute('aria-invalid')).toBe('true');
        expect(screen.getByRole('alert').textContent).toBe('Inválido');
        await fireEvent.update(input, 'a@b.c');
        expect(value.value).toBe('a@b.c');
    });

    it('a password input reveals and hides what is typed (1.63.1)', async () => {
        render(ForgeInput, { props: { label: 'Senha', type: 'password', modelValue: 'segredo' } });
        const input = screen.getByLabelText('Senha');
        expect(input.getAttribute('type')).toBe('password');

        await fireEvent.click(screen.getByRole('button', { name: 'Show password' }));
        expect(input.getAttribute('type')).toBe('text');
        await fireEvent.click(screen.getByRole('button', { name: 'Hide password' }));
        expect(input.getAttribute('type')).toBe('password');
    });

    it('textarea counts the text already there', async () => {
        const value = ref('texto salvo');
        render(defineComponent({ render: () => h(ForgeTextarea, { label: 'Obs', counter: true, maxlength: 100, modelValue: value.value, 'onUpdate:modelValue': (next: string) => (value.value = next) }) }));

        expect(screen.getByText('11/100')).toBeTruthy();
        await fireEvent.update(screen.getByLabelText('Obs'), 'abc');
        expect(screen.getByText('3/100')).toBeTruthy();
    });

    it('switch shows the state it is given', async () => {
        const on = ref(true);
        render(defineComponent({ render: () => h(ForgeSwitch, { label: 'Ativo', modelValue: on.value, 'onUpdate:modelValue': (next: boolean) => (on.value = next) }) }));

        const input = screen.getByRole('switch', { name: 'Ativo' }) as HTMLInputElement;
        expect(input.checked).toBe(true);
        await fireEvent.click(input);
        expect(on.value).toBe(false);
        expect(input.checked).toBe(false);
    });

    it('select: filters without diacritics, Escape clears then closes, multiple keeps a list', async () => {
        const options = [
            { value: 1, label: 'Permissão Especial' },
            { value: 2, label: 'Outra Coisa' },
        ];
        const single = ref<unknown>(null);
        const first = render(defineComponent({ render: () => h(ForgeSelect, { label: 'Papel', options, searchable: true, modelValue: single.value as never, 'onUpdate:modelValue': (next: unknown) => (single.value = next) }) }));

        await fireEvent.click(screen.getByRole('button', { name: /Papel/ }));
        await fireEvent.update(screen.getByLabelText('Filter options'), 'permissao');
        expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Permissão Especial']);

        await fireEvent.keyDown(screen.getByLabelText('Filter options'), { key: 'Escape' });
        expect(screen.getAllByRole('option')).toHaveLength(2);
        await fireEvent.keyDown(screen.getByLabelText('Filter options'), { key: 'ArrowDown' });
        await fireEvent.keyDown(screen.getByLabelText('Filter options'), { key: 'Enter' });
        expect(single.value).toBe(1);
        expect(screen.queryByRole('listbox')).toBeNull();
        first.unmount();

        const many = ref<unknown>([1]);
        render(defineComponent({ render: () => h(ForgeSelect, { label: 'Papel', options, multiple: true, modelValue: many.value as never, 'onUpdate:modelValue': (next: unknown) => (many.value = next) }) }));
        await fireEvent.click(screen.getByRole('button', { name: /Papel/ }));
        await fireEvent.click(screen.getByRole('option', { name: 'Outra Coisa' }));
        expect(many.value).toEqual([1, 2]);
    });

    it('modal closes on Escape; tabs move with the arrows', async () => {
        const open = ref(true);
        render(
            defineComponent({
                render: () => [
                    h(ForgeModal, { modelValue: open.value, title: 'Editar', 'onUpdate:modelValue': (next: boolean) => (open.value = next) }, () => 'corpo'),
                    h(
                        ForgeTabs,
                        {
                            tabs: [
                                { id: 'a', label: 'Geral' },
                                { id: 'b', label: 'Avançado' },
                            ],
                        },
                        { a: () => 'painel A', b: () => 'painel B' },
                    ),
                ],
            }),
        );

        expect(screen.getByRole('dialog', { name: 'Editar' }).textContent).toContain('corpo');
        await fireEvent.keyDown(document, { key: 'Escape' });
        expect(open.value).toBe(false);
        await nextTick();
        expect(screen.queryByRole('dialog')).toBeNull();

        expect(screen.getByRole('tabpanel').textContent).toBe('painel A');
        await fireEvent.keyDown(screen.getByRole('tab', { name: 'Geral' }), { key: 'ArrowRight' });
        expect(screen.getByRole('tabpanel').textContent).toBe('painel B');
        expect(screen.getByRole('tab', { name: 'Avançado' }).getAttribute('aria-selected')).toBe('true');
    });

    it('pagination, stepper and table (search + sort)', async () => {
        const page = ref(2);
        render(
            defineComponent({
                render: () => [
                    h(ForgePagination, { page: page.value, lastPage: 5, onChange: (next: number) => (page.value = next) }),
                    h(ForgeStepper, { steps: ['Dados', 'Endereço', 'Fim'], currentStep: 2 }),
                    h(ForgeTable, { headers: [{ key: 'name', label: 'Nome' }], rows: [{ name: 'Zeca' }, { name: 'Ana' }, { name: 'José' }], searchable: true, sortable: true }),
                ],
            }),
        );

        expect(screen.getByRole('button', { name: '2' }).getAttribute('aria-current')).toBe('page');
        await fireEvent.click(screen.getByRole('button', { name: '3' }));
        expect(page.value).toBe(3);
        expect(screen.getByText('Endereço').getAttribute('aria-current')).toBe('step');

        const names = () =>
            within(screen.getByRole('table'))
                .getAllByRole('row')
                .slice(1)
                .map((row) => row.textContent);
        await fireEvent.click(screen.getByRole('button', { name: /Nome/ }));
        expect(names()).toEqual(['Ana', 'José', 'Zeca']);
        await fireEvent.update(screen.getByRole('searchbox'), 'jose');
        expect(names()).toEqual(['José']);
    });

    it('toast host shows toasts from the function and from the ptah-toast event', async () => {
        render(ForgeToastHost);

        forgeToast('Salvo', 'success', 0);
        window.dispatchEvent(new CustomEvent('ptah-toast', { detail: { title: 'Do evento', color: 'warn' } }));
        await nextTick();

        expect(screen.getByText('Salvo').closest('.pf-toast')?.getAttribute('data-color')).toBe('success');
        expect(screen.getByText('Do evento')).toBeTruthy();
    });
});
