import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { PtahAiChat, PtahAiModels } from '../../react/admin/ai';
import { fakeAi } from './ai.fake';

afterEach(() => {
    cleanup();
    window.sessionStorage.clear();
    window.localStorage.clear();
});

const type = (label: RegExp | string, value: string) => fireEvent.change(screen.getByLabelText(label), { target: { value } });
const rowOf = (text: string) => screen.getByText(text).closest('tr') as HTMLElement;

describe('AI models screen (React)', () => {
    it('lists the configurations, opens the guide, and a search with no match shows the empty state', async () => {
        const fake = fakeAi();
        render(<PtahAiModels api={fake.api} locale="pt_BR" />);

        expect(screen.getByRole('status').textContent).toContain('Carregando');
        await screen.findByText('Principal');
        expect(screen.getByRole('heading', { name: 'Configuração de Modelos de IA' })).toBeTruthy();
        expect(within(rowOf('Principal')).getByText('Conta da empresa')).toBeTruthy();
        expect(within(rowOf('Principal')).getByText(/Padrão/)).toBeTruthy();
        expect(within(rowOf('Local')).getByText('Inativo')).toBeTruthy();
        expect(within(rowOf('Local')).getByText('Ollama (Local)')).toBeTruthy();

        const guide = screen.getByRole('button', { name: /Como configurar/ });
        expect(guide.getAttribute('aria-expanded')).toBe('false');
        fireEvent.click(guide);
        expect(screen.getByText('console.anthropic.com/settings/keys')).toBeTruthy();

        fireEvent.change(screen.getByRole('searchbox', { name: 'Buscar' }), { target: { value: 'nada' } });
        expect(await screen.findByText('Nenhum registro encontrado')).toBeTruthy();
        expect(fake.calls.at(-1)?.query.get('search')).toBe('nada');
    });

    it('creates a configuration: the validation errors stay on their fields, then it saves', async () => {
        const fake = fakeAi();
        render(<PtahAiModels api={fake.api} locale="pt_BR" />);
        await screen.findByText('Principal');

        fireEvent.click(screen.getByRole('button', { name: /Novo/ }));
        const dialog = await screen.findByRole('dialog', { name: 'Novo Configuração de Modelos de IA' });
        fireEvent.click(within(dialog).getByRole('button', { name: 'Criar' }));
        await waitFor(() => expect(screen.getByLabelText(/^Nome/).getAttribute('aria-invalid')).toBe('true'));
        expect(screen.getByLabelText(/^Chave de API/).getAttribute('aria-invalid')).toBe('true');

        type(/^Nome/, 'Claude');
        // Ollama makes the key optional, as the view says.
        fireEvent.click(within(dialog).getByRole('button', { name: /Provedor/ }));
        fireEvent.click(screen.getByRole('option', { name: 'Anthropic (Claude)' }));
        type(/^Modelo/, 'claude-sonnet');
        type(/^Chave de API/, 'sk-nova');
        fireEvent.click(screen.getByRole('checkbox', { name: 'Padrão' }));
        fireEvent.click(within(dialog).getByRole('button', { name: 'Criar' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(await screen.findByText('Claude')).toBeTruthy();
        expect(screen.getByText('Configuração criada.')).toBeTruthy();
        expect(fake.calls.filter((call) => call.method === 'POST').at(-1)?.body).toMatchObject({ name: 'Claude', provider: 'anthropic', api_key: 'sk-nova', is_default: true, max_tokens: 1024, temperature: '0.70' });
    });

    it('edits with the key left blank (the stored one is kept)', async () => {
        const fake = fakeAi();
        render(<PtahAiModels api={fake.api} locale="pt_BR" />);
        await screen.findByText('Principal');

        fireEvent.click(within(rowOf('Principal')).getByRole('button', { name: 'Editar' }));
        const dialog = await screen.findByRole('dialog', { name: 'Editar Configuração de Modelos de IA' });
        const key = screen.getByLabelText(/deixe em branco para manter a chave atual/) as HTMLInputElement;
        expect(key.value).toBe('');
        expect(key.type).toBe('password');

        type(/^Nome/, 'Principal 2');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Salvar Alterações' }));

        expect(await screen.findByText('Principal 2')).toBeTruthy();
        expect(fake.calls.find((call) => call.method === 'PUT')?.body).toMatchObject({ name: 'Principal 2', api_key: '' });
        expect(fake.keys[1]).toBe('sk-guardada');
    });

    it('sets the default and deletes after a confirmation', async () => {
        const fake = fakeAi();
        render(<PtahAiModels api={fake.api} locale="pt_BR" />);
        await screen.findByText('Local');

        fireEvent.click(within(rowOf('Local')).getByRole('button', { name: 'Definir como padrão' }));
        expect(await screen.findByText('Modelo padrão atualizado.')).toBeTruthy();
        await waitFor(() => expect(within(rowOf('Principal')).getByRole('button', { name: 'Definir como padrão' })).toBeTruthy());

        fireEvent.click(within(rowOf('Principal')).getByRole('button', { name: 'Excluir' }));
        const dialog = await screen.findByRole('dialog', { name: 'Confirmar exclusão' });
        expect(dialog.textContent).toContain('Esta ação não pode ser desfeita.');
        expect(fake.calls.some((call) => call.method === 'DELETE')).toBe(false);

        fireEvent.click(within(dialog).getByRole('button', { name: 'Excluir' }));
        await waitFor(() => expect(screen.queryByText('Principal')).toBeNull());
        expect(screen.getByText('Configuração excluída.')).toBeTruthy();
    });

    it('sorts by name and pages through the server', async () => {
        const fake = fakeAi({ models: 25 });
        render(<PtahAiModels api={fake.api} locale="pt_BR" />);
        await screen.findByText('Principal');
        expect(screen.getByText('1–20 de 27')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: '2' }));
        expect(await screen.findByText('21–27 de 27')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: /^Nome/ }));
        await waitFor(() => expect(fake.calls.at(-1)?.query.get('direction')).toBe('desc'));
        expect(fake.calls.at(-1)?.query.get('page')).toBe('1');
    });

    it('a user without ai.config sees why', async () => {
        render(<PtahAiModels api={fakeAi({ forbidden: true }).api} locale="pt_BR" />);

        expect((await screen.findByRole('alert')).textContent).toContain('não tem permissão');
    });
});

describe('AI chat (React)', () => {
    const openChat = async () => {
        fireEvent.click(await screen.findByRole('button', { name: 'Abrir assistente de IA' }));

        return screen.getByRole('dialog', { name: 'Assistente IA' });
    };

    it('renders nothing without an active provider', async () => {
        const fake = fakeAi({ unavailable: true });
        const { container } = render(<PtahAiChat api={fake.api} locale="pt_BR" />);

        await waitFor(() => expect(fake.calls.length).toBe(1));
        expect(container.innerHTML).toBe('');
    });

    it('opens on the latest conversation and streams the answer of a message', async () => {
        const fake = fakeAi();
        render(<PtahAiChat api={fake.api} locale="pt_BR" />);
        const panel = await openChat();

        expect(within(panel).getByText('resposta 2')).toBeTruthy();
        const input = within(panel).getByLabelText('Digite uma mensagem…');
        fireEvent.change(input, { target: { value: 'Oi' } });
        fireEvent.keyDown(input, { key: 'Enter' });

        expect(await within(panel).findByText('Resposta para: Oi')).toBeTruthy();
        expect(within(panel).getByText('Resposta para: Oi').tagName).toBe('STRONG');
        const call = fake.calls.find((item) => item.path === '/admin/ai/chat/messages');
        expect(call?.accept).toContain('text/event-stream');
        expect(call?.body).toMatchObject({ message: 'Oi', conversation_id: 2, config_id: 1 });
        expect((input as HTMLTextAreaElement).value).toBe('');
    });

    it('without streaming: a provider failure keeps the message, a refused one gives the text back', async () => {
        const fake = fakeAi();
        render(<PtahAiChat api={fake.api} locale="pt_BR" stream={false} />);
        const panel = await openChat();
        const input = within(panel).getByLabelText('Digite uma mensagem…') as HTMLTextAreaElement;

        fireEvent.change(input, { target: { value: 'erro' } });
        fireEvent.click(within(panel).getByRole('button', { name: 'Enviar' }));
        expect((await within(panel).findByRole('alert')).textContent).toBe('O provedor falhou.');
        expect(within(panel).getByText('erro')).toBeTruthy();
        expect(fake.calls.at(-1)?.accept).not.toContain('event-stream');

        fireEvent.change(input, { target: { value: 'uma mensagem longa demais' } });
        fireEvent.keyDown(input, { key: 'Enter' });
        await waitFor(() => expect(within(panel).getByRole('alert').textContent).toContain('20 caracteres'));
        expect(input.value).toBe('uma mensagem longa demais');
        expect(within(panel).queryByText('uma mensagem longa demais', { selector: 'div' })).toBeNull();
    });

    it('lists the history, opens a conversation, loads more and starts a new one', async () => {
        const fake = fakeAi({ history: 7 });
        render(<PtahAiChat api={fake.api} locale="pt_BR" />);
        const panel = await openChat();

        fireEvent.click(within(panel).getByRole('button', { name: 'Histórico de conversas' }));
        expect(await within(panel).findByText('Conversa 7')).toBeTruthy();
        expect(within(panel).queryByText('Conversa 2')).toBeNull();
        fireEvent.click(within(panel).getByRole('button', { name: 'Ver mais' }));
        expect(await within(panel).findByText('Conversa 2')).toBeTruthy();
        expect(fake.calls.at(-1)?.query.get('limit')).toBe('10');

        fireEvent.click(within(panel).getByText('Conversa 3'));
        expect(await within(panel).findByText('resposta 3')).toBeTruthy();

        fireEvent.click(within(panel).getByRole('button', { name: 'Nova conversa' }));
        expect(await within(panel).findByText('Pergunte qualquer coisa — estou aqui para ajudar!')).toBeTruthy();

        fireEvent.change(within(panel).getByLabelText('Digite uma mensagem…'), { target: { value: 'Nova' } });
        fireEvent.click(within(panel).getByRole('button', { name: 'Enviar' }));
        await within(panel).findByText('Resposta para: Nova');
        expect(fake.calls.filter((call) => call.path === '/admin/ai/chat/messages').at(-1)?.body?.conversation_id).toBeNull();
    });

    it('a /command from the autocomplete answers a preview card that is confirmed once', async () => {
        const fake = fakeAi();
        render(<PtahAiChat api={fake.api} locale="pt_BR" />);
        const panel = await openChat();
        const input = within(panel).getByLabelText('Digite uma mensagem…');

        fireEvent.change(input, { target: { value: '/pr' } });
        const commands = within(panel).getByRole('listbox', { name: 'Comandos' });
        fireEvent.click(within(commands).getByRole('option', { name: /preview/ }));
        expect((input as HTMLTextAreaElement).value).toBe('/preview');
        fireEvent.keyDown(input, { key: 'Enter' });

        expect(await within(panel).findByText('2 produtos vão mudar.')).toBeTruthy();
        expect(within(panel).getByRole('columnheader', { name: 'Qtd' })).toBeTruthy();
        fireEvent.click(within(panel).getByRole('button', { name: 'Confirmar' }));

        expect(await within(panel).findByText('Corrigi 2 produtos.')).toBeTruthy();
        expect(within(panel).getByText('Confirmado')).toBeTruthy();
        expect(within(panel).queryByRole('button', { name: 'Confirmar' })).toBeNull();
        expect(fake.calls.find((call) => call.path === '/admin/ai/chat/confirmations')?.body).toMatchObject({ token: 'Tok3nTok3nTok3nTok3nTok3n', confirm: true });
    });

    it('attaches files (filtered by the provider), removes one and sends the rest', async () => {
        const fake = fakeAi();
        render(<PtahAiChat api={fake.api} locale="pt_BR" />);
        const panel = await openChat();

        // The picker narrows the formats: Ollama takes no PDF.
        fireEvent.change(within(panel).getByLabelText('Provedor de IA'), { target: { value: '3' } });
        expect(await within(panel).findByText('Este provedor não aceita pdf.')).toBeTruthy();
        expect(fake.calls.at(-1)?.query.get('config_id')).toBe('3');

        const files = [new File(['x'], 'nota.pdf', { type: 'application/pdf' }), new File(['a'], 'lista.txt', { type: 'text/plain' }), new File(['b'], 'print.png', { type: 'image/png' })];
        fireEvent.change(within(panel).getByTestId('ptah-ai-file'), { target: { files } });
        expect(within(panel).queryByText(/nota\.pdf/)).toBeNull();
        expect(within(panel).getByText(/lista\.txt/)).toBeTruthy();
        expect(within(panel).getByRole('button', { name: 'Anexar arquivo' }).hasAttribute('disabled')).toBe(true);

        fireEvent.click(within(panel).getByRole('button', { name: 'Remover print.png' }));
        fireEvent.click(within(panel).getByRole('button', { name: 'Enviar' }));

        expect(await within(panel).findByText('Resposta para: lista.txt')).toBeTruthy();
        const form = fake.calls.find((call) => call.path === '/admin/ai/chat/messages')?.form;
        expect((form?.getAll('attachments[]') as File[]).map((file) => file.name)).toEqual(['lista.txt']);
        expect(form?.get('config_id')).toBe('3');
    });

    it('hides the launcher behind a handle that brings the chat back', async () => {
        render(<PtahAiChat api={fakeAi().api} locale="pt_BR" />);

        fireEvent.click(await screen.findByRole('button', { name: 'Ocultar o botão do assistente' }));
        expect(screen.queryByRole('button', { name: 'Abrir assistente de IA' })).toBeNull();
        expect(document.body.classList.contains('ptah-has-ai-launcher')).toBe(false);

        fireEvent.click(screen.getByRole('button', { name: 'Mostrar o assistente' }));
        expect(screen.getByRole('dialog', { name: 'Assistente IA' })).toBeTruthy();
    });
});
