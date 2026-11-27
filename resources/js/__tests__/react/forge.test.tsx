import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { readdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import * as kit from '../../react';
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
} from '../../react';

afterEach(cleanup);

/** forge-stat-card → ForgeStatCard */
export const exportName = (file: string) =>
    file
        .replace('.blade.php', '')
        .split('-')
        .map((part) => part[0].toUpperCase() + part.slice(1))
        .join('');

export const bladeComponents = () => readdirSync(resolve(__dirname, '../../../views/components')).filter((file) => file.startsWith('forge-'));

// Drawn by ForgeTabs (a tab is an entry of `tabs`), and by the one layout component.
export const COVERED_BY: Record<string, string> = { ForgeTab: 'ForgeTabs', ForgeSidebar: 'ForgeDashboardLayout', ForgeNavbar: 'ForgeDashboardLayout' };

describe('forge kit (React)', () => {
    it('has every forge-* Blade component', () => {
        const missing = bladeComponents()
            .map(exportName)
            .map((name) => COVERED_BY[name] ?? name)
            .filter((name) => !(name in kit));

        expect(missing).toEqual([]);
    });

    it('button: loading disables it, href makes it a link', () => {
        render(
            <>
                <ForgeButton loading>Salvar</ForgeButton>
                <ForgeButton href="/x" color="danger">
                    Ir
                </ForgeButton>
            </>,
        );

        expect((screen.getByRole('button', { name: 'Salvar' }) as HTMLButtonElement).disabled).toBe(true);
        expect(screen.getByRole('link', { name: 'Ir' }).getAttribute('data-color')).toBe('danger');
    });

    it('alert closes; progress clamps', () => {
        const onClose = vi.fn();
        render(
            <>
                <ForgeAlert type="danger" title="Erro" closable onClose={onClose}>
                    Falhou
                </ForgeAlert>
                <ForgeProgress value={140} label />
            </>,
        );

        expect(screen.getByRole('alert').textContent).toContain('Falhou');
        fireEvent.click(screen.getByRole('button', { name: 'Close' }));
        expect(screen.queryByRole('alert')).toBeNull();
        expect(onClose).toHaveBeenCalled();
        expect(screen.getByRole('progressbar').getAttribute('aria-valuenow')).toBe('100');
    });

    it('a password input reveals and hides what is typed (1.63.1)', () => {
        render(<ForgeInput label="Senha" type="password" value="segredo" onChange={() => undefined} />);
        const input = screen.getByLabelText('Senha');
        expect(input.getAttribute('type')).toBe('password');

        fireEvent.click(screen.getByRole('button', { name: 'Show password' }));
        expect(input.getAttribute('type')).toBe('text');
        fireEvent.click(screen.getByRole('button', { name: 'Hide password' }));
        expect(input.getAttribute('type')).toBe('password');
    });

    it('input shows its error and reports what is typed', () => {
        const onChange = vi.fn();
        render(<ForgeInput label="E-mail" error="Inválido" required onChange={onChange} />);

        const input = screen.getByLabelText(/E-mail/);
        expect(input.getAttribute('aria-invalid')).toBe('true');
        expect(screen.getByRole('alert').textContent).toBe('Inválido');
        fireEvent.change(input, { target: { value: 'a@b.c' } });
        expect(onChange).toHaveBeenCalledWith('a@b.c');
    });

    it('textarea counts the text already there', () => {
        render(<ForgeTextarea label="Obs" defaultValue="texto salvo" counter maxLength={100} />);

        expect(screen.getByText('11/100')).toBeTruthy();
        fireEvent.change(screen.getByLabelText('Obs'), { target: { value: 'abc' } });
        expect(screen.getByText('3/100')).toBeTruthy();
    });

    it('switch shows the state it is given', () => {
        function Demo() {
            const [on, setOn] = useState(true);

            return <ForgeSwitch label="Ativo" checked={on} onChange={setOn} />;
        }
        render(<Demo />);

        const input = screen.getByRole('switch', { name: 'Ativo' }) as HTMLInputElement;
        expect(input.checked).toBe(true);
        fireEvent.click(input);
        expect(input.checked).toBe(false);
    });

    it('select: filters without diacritics, Escape clears then closes, multiple keeps a list', () => {
        const onChange = vi.fn();
        const options = [
            { value: 1, label: 'Permissão Especial' },
            { value: 2, label: 'Outra Coisa' },
        ];
        const view = render(<ForgeSelect label="Papel" options={options} searchable onChange={onChange} />);

        fireEvent.click(screen.getByRole('button', { name: /Papel/ }));
        const filter = screen.getByLabelText('Filter options');
        fireEvent.change(filter, { target: { value: 'permissao' } });
        expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Permissão Especial']);

        fireEvent.keyDown(filter, { key: 'Escape' });
        expect(screen.getAllByRole('option')).toHaveLength(2);
        fireEvent.keyDown(screen.getByLabelText('Filter options'), { key: 'ArrowDown' });
        fireEvent.keyDown(screen.getByLabelText('Filter options'), { key: 'Enter' });
        expect(onChange).toHaveBeenCalledWith(1);
        expect(screen.queryByRole('listbox')).toBeNull();

        view.rerender(<ForgeSelect label="Papel" options={options} multiple selected={[1]} onChange={onChange} />);
        fireEvent.click(screen.getByRole('button', { name: /Papel/ }));
        fireEvent.click(screen.getByRole('option', { name: 'Outra Coisa' }));
        expect(onChange).toHaveBeenLastCalledWith([1, 2]);
    });

    it('modal closes on Escape; tabs move with the arrows', () => {
        const onClose = vi.fn();
        render(
            <>
                <ForgeModal open onClose={onClose} title="Editar">
                    corpo
                </ForgeModal>
                <ForgeTabs
                    tabs={[
                        { id: 'a', label: 'Geral', content: 'painel A' },
                        { id: 'b', label: 'Avançado', content: 'painel B' },
                    ]}
                />
            </>,
        );

        expect(screen.getByRole('dialog', { name: 'Editar' }).textContent).toContain('corpo');
        fireEvent.keyDown(document, { key: 'Escape' });
        expect(onClose).toHaveBeenCalled();

        expect(screen.getByRole('tabpanel').textContent).toBe('painel A');
        fireEvent.keyDown(screen.getByRole('tab', { name: 'Geral' }), { key: 'ArrowRight' });
        expect(screen.getByRole('tabpanel').textContent).toBe('painel B');
        expect(screen.getByRole('tab', { name: 'Avançado' }).getAttribute('aria-selected')).toBe('true');
    });

    it('pagination, stepper and table (search + sort)', () => {
        const onChange = vi.fn();
        render(
            <>
                <ForgePagination page={2} lastPage={5} onChange={onChange} />
                <ForgeStepper steps={['Dados', 'Endereço', 'Fim']} currentStep={2} />
                <ForgeTable headers={[{ key: 'name', label: 'Nome' }]} rows={[{ name: 'Zeca' }, { name: 'Ana' }, { name: 'José' }]} searchable sortable />
            </>,
        );

        fireEvent.click(screen.getByRole('button', { name: '3' }));
        expect(onChange).toHaveBeenCalledWith(3);
        expect(screen.getByRole('button', { name: '2' }).getAttribute('aria-current')).toBe('page');
        expect(screen.getByText('Endereço').getAttribute('aria-current')).toBe('step');

        const names = () =>
            within(screen.getByRole('table'))
                .getAllByRole('row')
                .slice(1)
                .map((row) => row.textContent);
        fireEvent.click(screen.getByRole('button', { name: /Nome/ }));
        expect(names()).toEqual(['Ana', 'José', 'Zeca']);
        fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'jose' } });
        expect(names()).toEqual(['José']);
    });

    it('toast host shows toasts from the function and from the ptah-toast event', () => {
        render(<ForgeToastHost />);

        act(() => forgeToast('Salvo', 'success', 0));
        act(() => {
            window.dispatchEvent(new CustomEvent('ptah-toast', { detail: { title: 'Do evento', color: 'warn' } }));
        });

        expect(screen.getByText('Salvo').closest('.pf-toast')?.getAttribute('data-color')).toBe('success');
        expect(screen.getByText('Do evento')).toBeTruthy();
    });
});
