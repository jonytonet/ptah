/**
 * The page router of the Vue stack: reads what the server put on `#ptah-app`
 * (ptah::stack.app) and draws that page. The entry file of an app is one
 * call — `mountPtah(document.getElementById('ptah-app'))` — so a page ptah
 * adds later arrives with `composer update`, with nothing to edit.
 */
import { createApp, defineComponent, Fragment, h, markRaw, onBeforeUnmount, shallowRef } from 'vue';
import type { Component, PropType, VNode } from 'vue';
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
import { PtahPageHead } from './page-head';
import PtahCrud from './PtahCrud.vue';
import PtahLayout from './PtahLayout.vue';

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
} as Record<string, Component>;

export const PtahApp = defineComponent({
    name: 'PtahApp',
    props: {
        /** `crud`, `login`, `forgot-password`, `reset-password`, `two-factor`, `profile`, `dashboard`, or an administration screen: `companies`, `departments`, `roles`, `pages`, `access-users`, `access-audit`, `permission-guide`, `menu`, `settings`, `exports`, `ai-models`. */
        page: { type: String, required: true },
        /** The key of `CrudScreen::register()`, for a `crud` page. */
        screen: { type: String, default: '' },
        title: { type: String, default: undefined },
        brand: { type: String, default: undefined },
        locale: { type: String, default: undefined },
        /** Where the API is mounted. */
        apiUrl: { type: String, default: undefined },
        /** Extra values of the page (the reset token, the e-mail of the link). */
        props: { type: Object as PropType<Record<string, unknown>>, default: () => ({}) },
        /** The profile page, linked from the top bar. */
        profileUrl: { type: String, default: undefined },
        /** Shows "log out" (the account menu and the foot of the sidebar). */
        canLogout: Boolean,
        /** Where the light/dark choice of a signed-in user is saved. */
        themeModeUrl: { type: String, default: undefined },
        onAction: { type: Function as PropType<ControllerOptions['onAction']>, default: undefined },
        /** Your own pages: `{ reports: Reports }` for `data-page="reports"`. */
        pages: { type: Object as PropType<Record<string, Component>>, default: () => ({}) },
        /**
         * A router of your own: every ptah link goes through it. With it, ptah
         * does not intercept links (mountPtah creates no navigator).
         */
        onNavigate: { type: Function as PropType<(url: string) => void>, default: undefined },
        /** Swaps pages without a document load (mountPtah creates it): the page drawn is the one it holds. */
        navigator: { type: Object as PropType<PtahNavigator>, default: undefined },
        /** The menu and user the page embedded (`data-boot`): the layout paints complete on the first frame. */
        boot: { type: Object as PropType<PtahBoot | null>, default: null },
    },
    setup(props, { slots }) {
        const api = new PtahApi({ baseUrl: props.apiUrl });
        const logout = async () => {
            const answer = await api.logout().catch(() => null);
            window.location.assign(answer?.redirect ?? '/');
        };
        const navigator = props.navigator;
        const nav = shallowRef<NavigatorState | null>(navigator?.getState() ?? null);
        const stop = navigator?.subscribe(() => {
            nav.value = navigator.getState();
        });
        onBeforeUnmount(() => stop?.());
        const go = props.onNavigate ?? (navigator ? (url: string) => void navigator.navigate(url) : undefined);

        return () => {
            const t = words(props.locale);
            const common = { api, locale: props.locale };
            // With a navigator the page on screen is the one it last swapped in.
            const described = nav.value?.page;
            const page = described?.page ?? props.page;
            const screen = (described ? described.screen : props.screen) ?? '';
            const title = described ? described.title : props.title;
            const values = (described ? described.props : props.props) ?? {};
            const text = (key: string) => (typeof values[key] === 'string' ? (values[key] as string) : '');
            // Each page mounts fresh: nothing of one screen leaks into the next.
            const key = described ? described.url.split('#')[0] : `${page}:${screen}`;

            switch (page) {
                case 'login':
                    return h(PtahLogin, { key, ...common });
                case 'forgot-password':
                    return h(PtahForgotPassword, { key, ...common });
                case 'reset-password':
                    return h(PtahResetPassword, { key, ...common, token: text('token'), email: text('email') });
                case 'two-factor':
                    return h(PtahTwoFactor, { key, ...common });
            }

            let content: VNode | VNode[];
            if (page in props.pages) {
                content = h(props.pages[page]);
            } else if (page === 'profile') {
                content = [h(PtahPageHead, { title: t.profileTitle, subtitle: t.profileSubtitle }), h(PtahProfile, common)];
            } else if (page === 'dashboard') {
                content = h(PtahDashboard, common);
            } else if (page in ADMIN) {
                content = h(ADMIN[page], common);
            } else {
                // The header of the page ptah:forge writes (view.index.stub): the title, and Dashboard / title.
                // instant: the last listing this tab saw is drawn at once, then refreshed.
                const crud = h(PtahCrud, { screen, ...common, onAction: props.onAction, instant: true });
                content = title ? [h(PtahPageHead, { title, crumbs: [{ label: t.dashboard, url: '/dashboard' }, { label: title }] }), crud] : crud;
            }

            const state = nav.value;

            return h(
                PtahLayout,
                {
                    api,
                    brand: props.brand,
                    locale: props.locale,
                    profileUrl: props.profileUrl,
                    themeModeUrl: props.themeModeUrl,
                    onLogout: props.canLogout ? () => void logout() : undefined,
                    onNavigate: go,
                    path: described ? new URL(described.url).pathname : undefined,
                    boot: props.boot,
                    busy: state?.busy ?? false,
                    announce: state && state.seq > 0 ? title || described?.documentTitle || '' : '',
                },
                // The `topbar` slot of PtahApp goes into the bar (a company switcher, the notification bell).
                { default: () => h(Fragment, { key }, Array.isArray(content) ? content : [content]), topbar: () => slots.topbar?.() },
            );
        };
    },
});

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
export function mountPtah(element: HTMLElement | null, extra: Record<string, unknown> = {}, options: MountOptions = {}): () => void {
    if (!element) {
        return () => undefined;
    }

    const read = initialPage(element);
    const first: PtahPage = {
        ...read,
        page: typeof extra.page === 'string' ? extra.page : read.page,
        screen: typeof extra.screen === 'string' ? extra.screen : read.screen,
        title: typeof extra.title === 'string' ? extra.title : read.title,
        props: extra.props && typeof extra.props === 'object' ? (extra.props as Record<string, unknown>) : read.props,
    };
    const boot = readBoot(element);
    // The kept listings (instant) belong to this user and company.
    setSnapshotScope(boot?.me?.user?.id, boot?.me?.company_id);
    // The first answers of the screen came with the page: it opens without asking again.
    preloadAnswers(readAnswers(element));
    const own = typeof extra.onNavigate === 'function' ? (extra.onNavigate as (url: string) => void) : null;
    const navigator = own || options.navigate === false ? undefined : markRaw(new PtahNavigator(first, { ...warmOptions(read.api, boot?.me?.screens, embedsAnswers(element)), ...options }));

    const app = createApp(PtahApp, {
        page: first.page,
        screen: first.screen,
        title: first.title,
        brand: read.brand,
        locale: read.locale,
        apiUrl: read.api,
        profileUrl: read.profile,
        themeModeUrl: read.themeMode,
        canLogout: read.logout,
        props: first.props,
        boot,
        navigator,
        ...extra,
    });
    app.mount(element);
    const uninstall = navigator ? navigator.install(element) : () => undefined;
    const unhandle = setNavigationHandler(own ?? (navigator ? (url: string) => void navigator.navigate(url) : null));

    return () => {
        uninstall();
        unhandle();
        app.unmount();
    };
}
