import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { h } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { filterByCell, PtahCrudFilterBadges, PtahCrudFilterPanel, PtahCrudSearchHistory, PtahCrudUrlFilters } from '../../vue/crud/filters';
import { isCellFilterable, parseLinkFilters } from '../../core/crud/filters';
import { fakeFilters } from './filters.fake';

afterEach(cleanup);

const panel = () => screen.getByRole('region', { name: 'Filtros' });

describe('filter panel (Vue)', () => {
    it('draws every kind of filter and sends values and operators with the listing', async () => {
        const fake = fakeFilters();
        const crud = await fake.controller();
        render(PtahCrudFilterPanel, { props: { crud, locale: 'pt_BR' } });

        expect(screen.getByRole('status').textContent).toContain('Carregando');
        await screen.findByRole('region', { name: 'Filtros' });

        // Text: operator + value; number: symbols; select: options + empty/filled; date: from/to with operators.
        expect((screen.getByRole('combobox', { name: 'Nome — Operador' }) as HTMLSelectElement).value).toBe('LIKE');
        expect(within(screen.getByRole('combobox', { name: 'Valor — Operador' })).getByRole('option', { name: '≥' })).toBeTruthy();
        const status = screen.getByLabelText('Situação') as HTMLSelectElement;
        expect(Array.from(status.options).map((option) => option.textContent)).toEqual(['-- Todos --', 'Ativo', 'Arquivado', '(é vazio / sem valor)', '(tem valor)']);
        expect(screen.getByLabelText('Criado em — De')).toBeTruthy();
        expect(screen.getByLabelText('Criado em — Até — Operador')).toBeTruthy();
        expect(screen.getByLabelText('Dono').getAttribute('placeholder')).toBe('Escolha o dono');
        expect(screen.getByLabelText('Região')).toBeTruthy();

        await fireEvent.update(screen.getByLabelText('Nome'), 'al');
        await waitFor(() => expect(fake.lastList()?.get('filters[name]')).toBe('al'));

        await fireEvent.update(screen.getByRole('combobox', { name: 'Nome — Operador' }), 'LIKE_START');
        await waitFor(() => expect(fake.lastList()?.get('operators[name]')).toBe('LIKE_START'));

        // A value-less operator disables the value.
        await fireEvent.update(screen.getByRole('combobox', { name: 'Valor — Operador' }), 'IS NULL');
        await waitFor(() => expect((screen.getByLabelText('Valor') as HTMLInputElement).disabled).toBe(true));

        await fireEvent.update(status, '__ptah_empty__');
        await waitFor(() => expect(fake.lastList()?.get('filters[status]')).toBe('__ptah_empty__'));

        await fireEvent.update(screen.getByLabelText('Criado em — De — Operador'), '>');
        await fireEvent.update(screen.getByLabelText('Criado em — De'), '2026-02-01');
        await waitFor(() => expect(fake.lastList()?.get('date_ranges[created_at_start]')).toBe('2026-02-01'));
        expect(fake.lastList()?.get('date_operators[created_at_start]')).toBe('>');

        // The header counts what is active (the chips of the toolbar).
        expect(within(panel()).getByText(/ativos$/).textContent).toBe('4 ativos');
    });

    it('toggles a quick date', async () => {
        const fake = fakeFilters();
        const crud = await fake.controller();
        render(PtahCrudFilterPanel, { props: { crud, locale: 'pt_BR' } });
        await screen.findByRole('group', { name: 'Atalhos de data' });

        await fireEvent.click(screen.getByRole('button', { name: 'Hoje' }));
        await waitFor(() => expect(fake.lastList()?.get('quick_date')).toBe('today'));
        expect(screen.getByRole('button', { name: 'Hoje' }).getAttribute('aria-pressed')).toBe('true');

        await fireEvent.click(screen.getByRole('button', { name: 'Hoje' }));
        await waitFor(() => expect(fake.lastList()?.get('quick_date')).toBe(''));
    });

    it('a searchdropdown filter searches, picks by keyboard, and its dependents follow it', async () => {
        const fake = fakeFilters();
        const crud = await fake.controller();
        render(PtahCrudFilterPanel, { props: { crud, locale: 'pt_BR' } });
        const owner = (await screen.findByLabelText('Dono')) as HTMLInputElement;

        // The child is closed until the parent has a value.
        await fireEvent.focus(owner);
        expect(await screen.findByText('Nenhum resultado encontrado.')).toBeTruthy();
        await fireEvent.keyDown(owner, { key: 'Escape' });
        await waitFor(() => expect(screen.queryByText('Nenhum resultado encontrado.')).toBeNull());

        const category = screen.getByLabelText('Categoria') as HTMLInputElement;
        await fireEvent.focus(category);
        const options = await screen.findByRole('listbox', { name: 'Categoria' });
        expect(await within(options).findByText('FER')).toBeTruthy();
        await fireEvent.keyDown(category, { key: 'ArrowDown' });
        expect(category.getAttribute('aria-activedescendant')).toBeTruthy();
        await fireEvent.keyDown(category, { key: 'Enter' });

        await waitFor(() => expect(fake.lastList()?.get('filters[category_id]')).toBe('1'));
        expect(fake.lastList()?.get('operators[category_id]')).toBe('=');
        const labels = fake.calls.find((call) => call.path === '/crud/items/filters/labels');
        expect(labels?.body).toMatchObject({ labels: { category_id: 'Ferramentas' }, state: { filters: { category_id: 1 } } });
        expect(await screen.findByText('Ferramentas')).toBeTruthy();
        expect(category.getAttribute('placeholder')).toBe('Alterar...');

        // Now the child lists what belongs to the parent value (sent as context).
        await fireEvent.focus(owner);
        await fireEvent.click(await screen.findByRole('option', { name: 'Ana' }));
        await waitFor(() => expect(fake.lastList()?.get('filters[owner_id]')).toBe('10'));
        expect(fake.calls.filter((call) => call.path === '/crud/items/lookup/owner_id').at(-1)?.body).toMatchObject({ filter: true, context: { category_id: 1 } });

        // Clearing the parent clears the child too.
        await fireEvent.click(screen.getByRole('button', { name: 'Remover o filtro Categoria' }));
        await waitFor(() => expect(fake.lastList()?.get('filters[category_id]')).toBeNull());
        expect(fake.lastList()?.get('filters[owner_id]')).toBeNull();
        expect(screen.queryByText('Ana')).toBeNull();
    });

    it('typing in a searchdropdown waits for the last key, and erasing the text clears the filter', async () => {
        const fake = fakeFilters({ labels: { category_id: 'Peças' } });
        const crud = await fake.controller();
        await crud.patchList({ filters: { category_id: 2 } });
        render(PtahCrudFilterPanel, { props: { crud, locale: 'pt_BR' } });
        expect(await screen.findByText('Peças')).toBeTruthy();

        const category = screen.getByLabelText('Categoria');
        await fireEvent.update(category, 'fer');
        await waitFor(() => expect(fake.calls.filter((call) => call.path === '/crud/items/lookup/category_id').at(-1)?.body).toMatchObject({ q: 'fer', filter: true }));
        expect(await screen.findByRole('option', { name: /Ferramentas/ })).toBeTruthy();
        expect(screen.queryByRole('option', { name: /Peças/ })).toBeNull();

        await fireEvent.update(category, '');
        await waitFor(() => expect(fake.lastList()?.get('filters[category_id]')).toBeNull());
    });

    it('builds the advanced search rows with and/or and removes them', async () => {
        const fake = fakeFilters();
        const crud = await fake.controller();
        render(PtahCrudFilterPanel, { props: { crud, locale: 'pt_BR' } });
        await fireEvent.click(await screen.findByRole('switch', { name: 'Busca avançada' }));

        expect(screen.getByText(/Nenhuma condição ainda/)).toBeTruthy();
        const add = screen.getByRole('button', { name: 'Adicionar' }) as HTMLButtonElement;
        expect(add.disabled).toBe(true);

        await fireEvent.update(screen.getByRole('textbox', { name: 'Valor' }), 'al');
        await fireEvent.click(add);
        await waitFor(() => expect(fake.lastList()?.get('advanced[0][field]')).toBe('name'));
        expect(fake.lastList()?.get('advanced[0][operator]')).toBe('LIKE');
        expect(fake.lastList()?.get('advanced[0][value]')).toBe('al');

        const form = within(screen.getByRole('combobox', { name: 'Combinar' }).closest('form') as HTMLFormElement);
        await fireEvent.update(form.getByRole('combobox', { name: 'Combinar' }), 'OR');
        await fireEvent.update(form.getByRole('combobox', { name: 'Campo' }), 'amount');
        await fireEvent.update(form.getByRole('combobox', { name: 'Operador' }), '>');
        await fireEvent.update(form.getByRole('spinbutton', { name: 'Valor' }), '100');
        await fireEvent.submit(form.getByRole('spinbutton', { name: 'Valor' }).closest('form') as HTMLFormElement);
        await waitFor(() => expect(fake.lastList()?.get('advanced[1][logic]')).toBe('OR'));
        expect(screen.getByText('ou Valor maior que "100"')).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Remover a condição Nome contém "al"' }));
        await waitFor(() => expect(fake.lastList()?.get('advanced[0][field]')).toBe('amount'));

        // Off: the rows go.
        await fireEvent.click(screen.getByRole('switch', { name: 'Busca avançada' }));
        await waitFor(() => expect(fake.lastList()?.get('advanced')).toBe(''));
    });

    it('saves, loads and (after confirming) deletes a named filter', async () => {
        const fake = fakeFilters({ saved: { Arquivados: { status: 'archived' } } });
        const crud = await fake.controller();
        await crud.patchList({ filters: { name: 'al' } });
        render(PtahCrudFilterPanel, { props: { crud, locale: 'pt_BR' } });
        await screen.findByText('Salvos:');

        await fireEvent.click(screen.getByRole('button', { name: 'Salvar filtro atual com nome' }));
        const name = screen.getByRole('textbox', { name: 'Nome do filtro' });
        expect(document.activeElement).toBe(name);
        await fireEvent.keyDown(name, { key: 'Escape' });
        expect(screen.queryByRole('textbox', { name: 'Nome do filtro' })).toBeNull();

        await fireEvent.click(screen.getByRole('button', { name: 'Salvar filtro atual com nome' }));
        await fireEvent.update(screen.getByRole('textbox', { name: 'Nome do filtro' }), '  Com al  ');
        await fireEvent.click(screen.getByRole('button', { name: 'Salvar' }));
        expect(await screen.findByRole('button', { name: 'Com al' })).toBeTruthy();
        expect(fake.calls.find((call) => call.method === 'POST' && call.path === '/crud/items/saved-filters')?.body).toEqual({ name: 'Com al', filters: { name: 'al' } });

        await fireEvent.click(screen.getByRole('button', { name: 'Arquivados' }));
        await waitFor(() => expect(fake.lastList()?.get('filters[status]')).toBe('archived'));

        await fireEvent.click(screen.getByRole('button', { name: 'Excluir o filtro salvo "Arquivados"' }));
        const dialog = await screen.findByRole('dialog', { name: 'Excluir filtro salvo' });
        expect(fake.calls.some((call) => call.method === 'DELETE')).toBe(false);
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Excluir' }));
        await waitFor(() => expect(screen.queryByRole('button', { name: 'Arquivados' })).toBeNull());
        expect(fake.calls.find((call) => call.method === 'DELETE')?.body).toEqual({ name: 'Arquivados' });
    });

    it('clear all empties the panel, the search and the labels', async () => {
        const fake = fakeFilters();
        const crud = await fake.controller();
        await crud.patchList({ search: 'x', filters: { name: 'al' }, quick_date: 'today' });
        const onClose = vi.fn();
        render(PtahCrudFilterPanel, { props: { crud, locale: 'pt_BR', onClose } });
        await screen.findByRole('region', { name: 'Filtros' });

        await fireEvent.click(screen.getByRole('button', { name: 'Limpar tudo' }));
        await waitFor(() => expect(fake.lastList()?.get('quick_date')).toBe(''));
        expect(fake.lastList()?.get('search')).toBe('');
        expect(fake.lastList()?.get('filters')).toBe('');
        expect(fake.calls.find((call) => call.path === '/crud/items/filters/labels')?.body).toMatchObject({ labels: {}, replace: true });

        await fireEvent.click(screen.getByRole('button', { name: 'Fechar' }));
        expect(onClose).toHaveBeenCalled();
    });

    it('a failed meta shows the error and can be retried; a screen with nothing to filter draws nothing', async () => {
        const fake = fakeFilters({ metaFails: true });
        const crud = await fake.controller();
        render(PtahCrudFilterPanel, { props: { crud, locale: 'pt_BR' } });
        expect((await screen.findByRole('alert')).textContent).toContain('Não foi possível carregar os filtros.');

        fake.setMetaFails(false);
        await fireEvent.click(screen.getByRole('button', { name: 'Tentar de novo' }));
        expect(await screen.findByRole('region', { name: 'Filtros' })).toBeTruthy();
        cleanup();

        const empty = fakeFilters({ noFilters: true });
        const emptyCrud = await empty.controller();
        const { container } = render(PtahCrudFilterPanel, { props: { crud: emptyCrud, locale: 'pt_BR' } });
        await waitFor(() => expect(empty.calls.some((call) => call.path === '/crud/items/filters/meta')).toBe(true));
        await waitFor(() => expect(container.querySelector('*')).toBeNull());
    });
});

describe('filter chips (Vue)', () => {
    it('shows the search and each active filter, and removes them', async () => {
        const fake = fakeFilters({ labels: { category_id: 'Ferramentas' } });
        const crud = await fake.controller();
        const { container } = render(PtahCrudFilterBadges, { props: { crud, locale: 'pt_BR' } });
        await waitFor(() => expect(fake.calls.some((call) => call.path === '/crud/items/filters/meta')).toBe(true));
        expect(container.querySelector('*')).toBeNull();

        await crud.patchList({
            search: 'uma busca bem comprida que passa de trinta letras',
            filters: { status: 'archived', category_id: 1 },
            operators: { amount: 'IS NULL' },
            date_ranges: { created_at_start: '2026-02-01' },
            quick_date: 'month',
        });
        await screen.findByRole('group', { name: 'Filtros' });

        const chips = screen.getByRole('group', { name: 'Filtros' });
        // The search chip has the magnifier, not a label (as _toolbar.blade.php draws it).
        expect(chips.textContent?.startsWith('Filtros aplicados:uma busca bem comprida que pas...')).toBe(true);
        expect(chips.textContent).toContain('Situação:Arquivado');
        expect(chips.textContent).toContain('Categoria:Ferramentas');
        expect(chips.textContent).toContain('Valor:é vazio / sem valor');
        expect(chips.textContent).toContain('Criado em de:2026-02-01');
        expect(chips.textContent).toContain('Período:Este mês');

        // A NULL-operator chip goes with its operator.
        await fireEvent.click(screen.getByRole('button', { name: 'Remover o filtro Valor' }));
        await waitFor(() => expect(fake.lastList()?.get('operators')).toBe(''));

        await fireEvent.click(screen.getByRole('button', { name: 'Remover o filtro Criado em de' }));
        await waitFor(() => expect(fake.lastList()?.get('date_ranges')).toBe(''));

        await fireEvent.click(screen.getByRole('button', { name: 'Remover o filtro Período' }));
        await waitFor(() => expect(fake.lastList()?.get('quick_date')).toBe(''));

        await fireEvent.click(screen.getByRole('button', { name: 'Limpar busca' }));
        await waitFor(() => expect(fake.lastList()?.get('search')).toBe(''));
        expect(screen.queryByText(/Busca:/)).toBeNull();
    });
});

describe('link filters (Vue)', () => {
    it('reads ?f[...] into the listing, shows what the server accepted, and clears it', async () => {
        expect(parseLinkFilters('?f[status]=archived&f[name][op]=LIKE&f[name][val]=al&f[id][]=1&f[id][]=2&page=2')).toEqual({ status: 'archived', name: { op: 'LIKE', val: 'al' }, id: ['1', '2'] });

        const fake = fakeFilters();
        const crud = await fake.controller();
        window.history.replaceState(null, '', '/items?f[status]=archived');
        render(PtahCrudUrlFilters, { props: { crud, locale: 'pt_BR' } });

        const banner = await screen.findByRole('status');
        expect(banner.textContent).toContain('Filtros do link');
        expect(banner.textContent).toContain('Situação:archived');
        expect(fake.lastList()?.get('f[status]')).toBe('archived');

        await fireEvent.click(screen.getByRole('button', { name: 'Limpar filtros do link' }));
        await waitFor(() => expect(screen.queryByRole('status')).toBeNull());
        expect(fake.lastList()?.get('f')).toBe('');
        window.history.replaceState(null, '', '/');
    });

    it('the panel takes over from the link filters', async () => {
        const fake = fakeFilters();
        const crud = await fake.controller();
        render({ render: () => h('div', [h(PtahCrudUrlFilters, { crud, locale: 'pt_BR', readLocation: false }), h(PtahCrudFilterPanel, { crud, locale: 'pt_BR' })]) });
        await crud.patchList({ f: { status: 'archived' } } as never);
        expect(await screen.findByText('Filtros do link')).toBeTruthy();

        await fireEvent.update(screen.getByLabelText('Situação'), 'active');
        await waitFor(() => expect(screen.queryByText('Filtros do link')).toBeNull());
        expect(fake.lastList()?.get('f')).toBe('');
        expect(fake.lastList()?.get('filters[status]')).toBe('active');
    });
});

describe('recent searches (Vue)', () => {
    it('lists, searches again and clears after confirming; the empty state', async () => {
        const fake = fakeFilters({ history: ['beta', 'alfa'] });
        const crud = await fake.controller();
        render(PtahCrudSearchHistory, { props: { crud, locale: 'pt_BR' } });

        const button = screen.getByRole('button', { name: 'Buscas recentes' });
        await fireEvent.click(button);
        expect(button.getAttribute('aria-expanded')).toBe('true');
        await fireEvent.click(await screen.findByRole('button', { name: 'beta' }));
        await waitFor(() => expect(fake.lastList()?.get('search')).toBe('beta'));
        expect(screen.queryByRole('button', { name: 'alfa' })).toBeNull();

        await fireEvent.click(button);
        await fireEvent.click(await screen.findByRole('button', { name: 'Limpar histórico' }));
        const dialog = await screen.findByRole('dialog', { name: 'Limpar buscas recentes' });
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Limpar histórico' }));
        expect(await screen.findByText('Nenhuma busca recente.')).toBeTruthy();
        expect(fake.calls.find((call) => call.method === 'PUT' && call.path === '/crud/items/preferences')?.body).toEqual({ clear_search_history: true });

        await fireEvent.keyDown(document, { key: 'Escape' });
        await waitFor(() => expect(button.getAttribute('aria-expanded')).toBe('false'));
    });
});

describe('filter by this value (Vue)', () => {
    it('applies the filter the server answers, and changes nothing when it refuses', async () => {
        const fake = fakeFilters();
        const crud = await fake.controller();
        render(PtahCrudFilterPanel, { props: { crud, locale: 'pt_BR' } });
        await screen.findByRole('region', { name: 'Filtros' });
        expect(isCellFilterable(crud, 'status')).toBe(true);
        expect(isCellFilterable(crud, 'id')).toBe(false);

        expect(await filterByCell(crud, 2, 'status')).toBe(true);
        expect(fake.lastList()?.get('filters[status]')).toBe('archived');
        expect((screen.getByLabelText('Situação') as HTMLSelectElement).value).toBe('archived');

        const before = fake.lists().length;
        expect(await filterByCell(crud, 1, 'name')).toBe(false);
        expect(fake.lists().length).toBe(before);
    });
});
