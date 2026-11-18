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
import { PtahForgotPassword, PtahLogin, PtahProfile, PtahResetPassword, PtahTwoFactor } from './auth';
import { PtahDashboard } from './dashboard';
import { ForgeButton, ForgePageHeader } from './forge';
import { PtahCrud } from './PtahCrud';
import { PtahLayout } from './PtahLayout';

export interface PtahAppProps {
    /** `crud`, `login`, `forgot-password`, `reset-password`, `two-factor`, `profile`, `dashboard`. */
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
    /** Shows "log out" in the top bar. */
    canLogout?: boolean;
    onAction?: ControllerOptions['onAction'];
    /** Your own pages: `{ reports: <Reports /> }` for `data-page="reports"`. */
    pages?: Record<string, ReactNode>;
}

export function PtahApp({ page, screen = '', title, brand, locale, apiUrl, props = {}, profileUrl, canLogout, onAction, pages = {} }: PtahAppProps) {
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
                <ForgePageHeader title={t.profile} />
                <PtahProfile {...common} />
            </>
        );
    } else if (page === 'dashboard') {
        content = <PtahDashboard {...common} />;
    } else {
        content = <PtahCrud screen={screen} {...common} onAction={onAction} />;
    }

    return (
        <PtahLayout
            api={api}
            brand={brand}
            title={title}
            locale={locale}
            topbar={
                <>
                    {profileUrl && (
                        <a className="pc-btn" href={profileUrl}>
                            {t.profile}
                        </a>
                    )}
                    {canLogout && (
                        <ForgeButton flat onClick={() => void logout()}>
                            {t.logOut}
                        </ForgeButton>
                    )}
                </>
            }
        >
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
            canLogout={data.logout !== undefined}
            props={props}
            {...extra}
        />,
    );
}
