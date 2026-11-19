/**
 * The page router of the React stack: reads what the server put on
 * `#ptah-app` (ptah::stack.app) and draws that page. The entry file of an
 * app is one call — `mountPtah(document.getElementById('ptah-app'))` — so a
 * page ptah adds later arrives with `composer update`, with nothing to edit.
 */
import type { ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { PtahApi, words } from '../core';
import type { ControllerOptions } from '../core';
import { PtahAudit, PtahUserPermissions } from './admin/access';
import { PtahAiModels } from './admin/ai';
import { PtahCompanies, PtahDepartments } from './admin/company';
import { PtahMenuAdmin, PtahSettings } from './admin/menu';
import { PtahPages, PtahPermissionGuide, PtahRoles } from './admin/roles';
import { PtahExportsPanel } from './admin/shell';
import { PtahForgotPassword, PtahLogin, PtahProfile, PtahResetPassword, PtahTwoFactor } from './auth';
import { PtahDashboard } from './dashboard';
import { PtahCrud } from './PtahCrud';
import { PtahLayout } from './PtahLayout';
import { PtahPageHead } from './page-head';

/** The administration screens ptah ships, by the page key the server names. */
const ADMIN = {
    companies: PtahCompanies,
    departments: PtahDepartments,
    roles: PtahRoles,
    pages: PtahPages,
    'access-users': PtahUserPermissions,
    'access-audit': PtahAudit,
    'permission-guide': PtahPermissionGuide,
    menu: PtahMenuAdmin,
    settings: PtahSettings,
    exports: PtahExportsPanel,
    'ai-models': PtahAiModels,
} as const;

export interface PtahAppProps {
    /** `crud`, `login`, `forgot-password`, `reset-password`, `two-factor`, `profile`, `dashboard`, or an administration screen: `companies`, `departments`, `roles`, `pages`, `access-users`, `access-audit`, `permission-guide`, `menu`, `settings`, `exports`, `ai-models`. */
    page: string;
    /** The key of `CrudScreen::register()`, for a `crud` page. */
    screen?: string;
    title?: string;
    brand?: string;
    locale?: string;
    /** Where the API is mounted. */
    apiUrl?: string;
    /** Extra values of the page (the reset token, the e-mail of the link). */
    props?: Record<string, unknown>;
    /** The profile page, linked from the top bar. */
    profileUrl?: string;
    /** Shows "log out" (the account menu and the foot of the sidebar). */
    canLogout?: boolean;
    /** Where the light/dark choice of a signed-in user is saved. */
    themeModeUrl?: string;
    /** Extra content in the top bar (a company switcher, the notification bell). */
    topbar?: ReactNode;
    onAction?: ControllerOptions['onAction'];
    /** Your own pages: `{ reports: <Reports /> }` for `data-page="reports"`. */
    pages?: Record<string, ReactNode>;
}

export function PtahApp({ page, screen = '', title, brand, locale, apiUrl, props = {}, profileUrl, canLogout, themeModeUrl, topbar, onAction, pages = {} }: PtahAppProps) {
    const api = new PtahApi({ baseUrl: apiUrl });
    const t = words(locale);
    const common = { api, locale };
    const text = (key: string) => (typeof props[key] === 'string' ? (props[key] as string) : '');

    switch (page) {
        case 'login':
            return <PtahLogin {...common} />;
        case 'forgot-password':
            return <PtahForgotPassword {...common} />;
        case 'reset-password':
            return <PtahResetPassword {...common} token={text('token')} email={text('email')} />;
        case 'two-factor':
            return <PtahTwoFactor {...common} />;
    }

    const logout = async () => {
        const answer = await api.logout().catch(() => null);
        window.location.assign(answer?.redirect ?? '/');
    };

    let content: ReactNode;
    if (page in pages) {
        content = pages[page];
    } else if (page === 'profile') {
        content = (
            <>
                <PtahPageHead title={t.profileTitle} subtitle={t.profileSubtitle} />
                <PtahProfile {...common} />
            </>
        );
    } else if (page === 'dashboard') {
        content = <PtahDashboard {...common} />;
    } else if (page in ADMIN) {
        const Screen = ADMIN[page as keyof typeof ADMIN];
        content = <Screen {...common} />;
    } else {
        // The header of the page ptah:forge writes (view.index.stub): the title, and Dashboard / title.
        content = (
            <>
                {title && <PtahPageHead title={title} crumbs={[{ label: t.dashboard, url: '/dashboard' }, { label: title }]} />}
                <PtahCrud screen={screen} {...common} onAction={onAction} />
            </>
        );
    }

    return (
        <PtahLayout api={api} brand={brand} locale={locale} topbar={topbar} profileUrl={profileUrl} themeModeUrl={themeModeUrl} onLogout={canLogout ? () => void logout() : undefined}>
            {content}
        </PtahLayout>
    );
}

/** Mounts the page the server described on the element (`#ptah-app`). */
export function mountPtah(element: HTMLElement | null, extra: Partial<PtahAppProps> = {}): void {
    if (!element) {
        return;
    }

    const data = element.dataset;
    let props: Record<string, unknown> = {};
    try {
        props = JSON.parse(data.props ?? '{}') ?? {};
    } catch {
        props = {};
    }

    createRoot(element).render(
        <PtahApp
            page={data.page ?? 'crud'}
            screen={data.screen}
            title={data.title}
            brand={data.brand}
            locale={data.locale}
            apiUrl={data.api}
            profileUrl={data.profile}
            themeModeUrl={data.themeMode}
            canLogout={data.logout !== undefined}
            props={props}
            {...extra}
        />,
    );
}
