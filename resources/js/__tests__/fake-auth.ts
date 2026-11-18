import { PtahApi } from '../core/api';
import type { Profile } from '../core/types';

export interface AuthCall {
    method: string;
    path: string;
    body: Record<string, unknown> | null;
}

const baseProfile = (): Profile => ({
    name: 'Ana',
    email: 'ana@example.test',
    photo_url: null,
    two_factor: { available: true, enabled: false, type: '' },
    sessions_available: true,
    appearance: {
        light: 'puro',
        dark: 'grafite',
        accent: 'azul',
        text: 'neutra',
        density: 'confortavel',
        fontsize: 'normal',
        mode: null,
        choices: { light: ['puro', 'papel'], dark: ['grafite', 'carvao'], accent: ['azul', 'verde'], text: ['neutra', 'forte'], density: ['confortavel', 'compacta'], fontsize: ['normal', 'grande'] },
    },
});

/**
 * A stand-in for the auth API and the dashboard: the same routes and shapes as
 * the server. The password is `secret-123`; `2fa@example.test` has two-factor.
 */
export function fakeAuth(options: { signedIn?: boolean; twoFactorPending?: boolean } = {}) {
    const calls: AuthCall[] = [];
    let profile = baseProfile();
    let pending = options.twoFactorPending ?? false;

    const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    const answer = (ok: boolean, body: Record<string, unknown> = {}) => json(ok ? 200 : 422, { ok, profile, messages: [], errors: {}, ...body });

    const handler = async (input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> => {
        const path = new URL(String(input), 'http://localhost').pathname.replace('/ptah/api', '');
        const method = (init.method ?? 'GET').toUpperCase();
        const body = typeof init.body === 'string' ? (JSON.parse(init.body) as Record<string, unknown>) : null;
        calls.push({ method, path, body });

        switch (`${method} ${path}`) {
            case 'GET /auth/config':
                return json(200, {
                    app: 'ERP',
                    locale: 'pt_BR',
                    remember_me: true,
                    home: '/dashboard',
                    urls: { login: '/login', forgot: '/forgot-password', two_factor: '/two-factor-challenge' },
                    signed_in: options.signedIn ?? false,
                    two_factor_pending: pending,
                    status: null,
                });
            case 'POST /auth/login':
                if (!body?.email) {
                    return json(422, { message: 'invalid', errors: { email: ['O campo e-mail é obrigatório.'] } });
                }
                if (body.password !== 'secret-123') {
                    return json(422, { ok: false, error: 'Credenciais inválidas.', two_factor: false, redirect: null });
                }
                if (body.email === '2fa@example.test') {
                    pending = true;

                    return json(200, { ok: true, two_factor: true, redirect: '/two-factor-challenge', error: null });
                }

                return json(200, { ok: true, two_factor: false, redirect: '/dashboard', error: null });
            case 'POST /auth/two-factor':
                return body?.code === '123456' ? json(200, { ok: true, redirect: '/dashboard', error: null }) : json(422, { ok: false, error: 'Código inválido.', redirect: null });
            case 'POST /auth/two-factor/email':
                return json(200, { ok: true, message: 'Código enviado.', error: null });
            case 'POST /auth/forgot-password':
                return json(200, { ok: true, message: 'Se o e-mail existir, enviamos o link.', error: null });
            case 'POST /auth/reset-password':
                return body?.password === body?.password_confirmation ? json(200, { ok: true, redirect: '/login', error: null }) : json(422, { message: 'invalid', errors: { password: ['A confirmação não confere.'] } });
            case 'POST /auth/logout':
                return json(200, { ok: true, redirect: '/login' });
            case 'GET /auth/profile':
                return json(200, profile);
            case 'PUT /auth/profile':
                if (body?.email !== profile.email && body?.current_password !== 'secret-123') {
                    return answer(false, { errors: { current_password: 'Senha atual incorreta.' } });
                }
                profile = { ...profile, name: String(body?.name), email: String(body?.email) };

                return answer(true, { messages: [{ text: 'Perfil atualizado.', level: 'success' }] });
            case 'PUT /auth/password':
                return body?.current_password === 'secret-123' ? answer(true, { messages: [{ text: 'Senha atualizada.', level: 'success' }] }) : answer(false, { error: 'Senha atual incorreta.' });
            case 'PUT /auth/appearance':
                profile = { ...profile, appearance: { ...profile.appearance, ...(body?.reset ? baseProfile().appearance : (body as Record<string, string>)) } };

                return answer(true);
            case 'POST /auth/two-factor/totp':
                return body?.current_password === 'secret-123'
                    ? answer(true, { secret: 'ABCDEF123456', qr: 'data:image/svg+xml;base64,AAAA', recovery_codes: ['aaaa-1111', 'bbbb-2222'] })
                    : answer(false, { errors: { current_password: 'Senha atual incorreta.' } });
            case 'POST /auth/two-factor/totp/confirm':
                if (body?.code !== '123456') {
                    return answer(false, { error: 'Código inválido.' });
                }
                profile = { ...profile, two_factor: { available: true, enabled: true, type: 'totp' } };

                return answer(true);
            case 'POST /auth/two-factor/disable':
                profile = { ...profile, two_factor: { available: true, enabled: false, type: '' } };

                return answer(true);
            case 'GET /auth/sessions':
                return json(200, {
                    data: [
                        { id: 's1', ip_address: '10.0.0.1', browser: 'Chrome', platform: 'Windows', last_activity_human: 'agora', is_current: true },
                        { id: 's2', ip_address: '10.0.0.2', browser: 'Firefox', platform: 'Linux', last_activity_human: 'há 2 horas', is_current: false },
                    ],
                });
            case 'DELETE /auth/sessions/s2':
                return answer(true, { sessions: [{ id: 's1', ip_address: '10.0.0.1', browser: 'Chrome', platform: 'Windows', last_activity_human: 'agora', is_current: true }] });
            case 'GET /menu':
                return json(200, { data: [{ label: 'Itens', url: '/items', children: [] }] });
            case 'GET /me':
                return json(200, { user: { id: 9, name: 'Ana', email: 'ana@example.test' }, company_id: 1, locale: 'pt_BR', screens: [] });
            case 'GET /dashboard':
                return json(200, {
                    data: [
                        { type: 'stat', label: 'Pedidos hoje', color: 'primary', link: '/orders', error: null, value: '42' },
                        { type: 'trend', label: 'Vendas', color: 'success', link: null, error: null, display_total: 'R$ 900,00', max: 500, points: [{ label: 'Seg', value: 400, display: 'R$ 400,00' }, { label: 'Ter', value: 500, display: 'R$ 500,00' }] },
                        { type: 'breakdown', label: 'Por canal', color: 'warn', link: null, error: null, display_total: '10', max: 7, items: [{ label: 'Loja', value: 7, display: '7' }, { label: 'Outros', value: 3, display: '3', others: true }] },
                        { type: 'latest', label: 'Últimos', color: 'primary', link: null, error: null, rows: [['#1', 'Ana'], ['#2', 'Bia']] },
                        { type: 'stat', label: 'Quebrado', color: 'danger', link: null, error: 'Não foi possível calcular.' },
                    ],
                });
        }

        return json(404, { message: `No fake for ${method} ${path}` });
    };

    return { api: new PtahApi({ fetch: handler as typeof fetch }), calls, profile: () => profile };
}
