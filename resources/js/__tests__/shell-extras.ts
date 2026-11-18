import { PtahApi } from '../core/api';
import type { ShellExtras } from '../core/types';
import { fakeApi } from './fake-api';

/** The fake server, with the top bar told what to offer (the `shell` of /menu). */
export function fakeApiWithShell(shell: ShellExtras) {
    const base = fakeApi();
    const api = new PtahApi({
        fetch: (async (input: RequestInfo | URL, init?: RequestInit) => {
            const answer: Response = await base.api['doFetch'](input, init);
            if (!new URL(String(input), 'http://localhost').pathname.endsWith('/menu')) {
                return answer;
            }

            return new Response(JSON.stringify({ ...(await answer.json()), shell }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }) as typeof fetch,
    });

    return { ...base, api };
}

export const adminLinks: ShellExtras = {
    company_switcher: false,
    notifications: false,
    admin: [
        { label: 'Perfis de Acesso', url: '/ptah-roles', icon: 'bx bx-shield-quarter' },
        { label: 'Gerenciar Menu', url: '/ptah-menu', icon: 'bx bx-menu' },
    ],
};
