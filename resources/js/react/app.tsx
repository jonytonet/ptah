/**
 * The page router of the React stack: reads what the server put on
 * `#ptah-app` (ptah::stack.app) and draws that page. The entry file of an
 * app is one call — `mountPtah(document.getElementById('ptah-app'))` — so a
 * page ptah adds later arrives with `composer update`, with nothing to edit.
 */
import { Fragment, useMemo, useSyncExternalStore } from 'react';
import type { ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { embedsAnswers, initialPage, preloadAnswers, PtahApi, PtahNavigator, readAnswers, readBoot, setNavigationHandler, setSnapshotScope, warmOptions, words } from '../core';
import type { ControllerOptions, NavigatorOptions, NavigatorState, PtahBoot, PtahPage } from '../core';
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
    /**
     * A router of your own: every ptah link goes through it. With it, ptah
     * does not intercept links (mountPtah creates no navigator).
     */
    onNavigate?: (url: string) => void;
    /** Swaps pages without a document load (mountPtah creates it): the page drawn is the one it holds. */
    navigator?: PtahNavigator;
    /** The menu and user the page embedded (`data-boot`): the layout paints complete on the first frame. */
    boot?: PtahBoot | null;
}

const noSubscribe = () => () => undefined;
const noState = () => null;

/** The navigator's state, or null without one. */
function useNavigator(navigator: PtahNavigator | undefined): NavigatorState | null {
    return useSyncExternalStore(navigator?.subscribe ?? noSubscribe, navigator?.getState ?? noState, navigator?.getState ?? noState);
}

export function PtahApp(appProps: PtahAppProps) {
    const { brand, locale, apiUrl, profileUrl, canLogout, themeModeUrl, topbar, onAction, pages = {}, navigator, onNavigate, boot } = appProps;
    const nav = useNavigator(navigator);
    // With a navigator the page on screen is the one it last swapped in.
    const described = nav?.page;
    const page = described?.page ?? appProps.page;
    const screen = (described ? described.screen : appProps.screen) ?? '';
    const title = described ? described.title : appProps.title;
    const props = (described ? described.props : appProps.props) ?? {};
    const api = useMemo(() => new PtahApi({ baseUrl: apiUrl }), [apiUrl]);
    const t = words(locale);
    const common = { api, locale };
    const text = (key: string) => (typeof props[key] === 'string' ? (props[key] as string) : '');
    // Each page mounts fresh: nothing of one screen leaks into the next.
    const pageKey = described ? described.url.split('#')[0] : `${page}:${screen}`;
    const go = onNavigate ?? (navigator ? (url: string) => void navigator.navigate(url) : undefined);

    switch (page) {
        case 'login':
            return <PtahLogin key={pageKey} {...common} />;
        case 'forgot-password':
            return <PtahForgotPassword key={pageKey} {...common} />;
        case 'reset-password':
            return <PtahResetPassword key={pageKey} {...common} token={text('token')} email={text('email')} />;
        case 'two-factor':
            return <PtahTwoFactor key={pageKey} {...common} />;
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
                {/* instant: the last listing this tab saw is drawn at once, then refreshed. */}
                <PtahCrud screen={screen} {...common} onAction={onAction} instant />
            </>
        );
    }

    return (
        <PtahLayout
            api={api}
            brand={brand}
            locale={locale}
            topbar={topbar}
            profileUrl={profileUrl}
            themeModeUrl={themeModeUrl}
            onLogout={canLogout ? () => void logout() : undefined}
            onNavigate={go}
            path={described ? new URL(described.url).pathname : undefined}
            boot={boot}
            busy={nav?.busy ?? false}
            announce={nav && nav.seq > 0 ? title || described?.documentTitle || '' : ''}
        >
            <Fragment key={pageKey}>{content}</Fragment>
        </PtahLayout>
    );
}

export interface MountOptions extends NavigatorOptions {
    /** `false` keeps every link a document load, as before 1.59.0 (default true). */
    navigate?: boolean;
}

/**
 * Mounts the page the server described on the element (`#ptah-app`) and,
 * unless `extra.onNavigate` brings a router of your own, takes over the links
 * inside it: ptah pages swap without a document load. Returns the function
 * that unmounts it and gives the links back.
 */
export function mountPtah(element: HTMLElement | null, extra: Partial<PtahAppProps> = {}, options: MountOptions = {}): () => void {
    if (!element) {
        return () => undefined;
    }

    const read = initialPage(element);
    const first: PtahPage = {
        ...read,
        page: extra.page ?? read.page,
        screen: extra.screen ?? read.screen,
        title: extra.title ?? read.title,
        props: extra.props ?? read.props,
    };
    const boot = readBoot(element);
    // The kept listings (instant) belong to this user and company.
    setSnapshotScope(boot?.me?.user?.id, boot?.me?.company_id);
    // The first answers of the screen came with the page: it opens without asking again.
    preloadAnswers(readAnswers(element));
    const navigator = extra.onNavigate || options.navigate === false ? undefined : new PtahNavigator(first, { ...warmOptions(read.api, boot?.me?.screens, embedsAnswers(element)), ...options });

    const root = createRoot(element);
    root.render(
        <PtahApp
            page={first.page}
            screen={first.screen}
            title={first.title}
            brand={read.brand}
            locale={read.locale}
            apiUrl={read.api}
            profileUrl={read.profile}
            themeModeUrl={read.themeMode}
            canLogout={read.logout}
            props={first.props}
            boot={boot}
            navigator={navigator}
            {...extra}
        />,
    );
    // After createRoot: React's own click handlers (the sidebar's) run first.
    const uninstall = navigator ? navigator.install(element) : () => undefined;
    const unhandle = setNavigationHandler(extra.onNavigate ?? (navigator ? (url: string) => void navigator.navigate(url) : null));

    return () => {
        uninstall();
        unhandle();
        root.unmount();
    };
}
