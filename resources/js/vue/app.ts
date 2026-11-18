/**
 * The page router of the Vue stack: reads what the server put on `#ptah-app`
 * (ptah::stack.app) and draws that page. The entry file of an app is one
 * call — `mountPtah(document.getElementById('ptah-app'))` — so a page ptah
 * adds later arrives with `composer update`, with nothing to edit.
 */
import { createApp, defineComponent, h } from 'vue';
import type { Component, PropType, VNode } from 'vue';
import { PtahApi, words } from '../core';
import type { ControllerOptions } from '../core';
import { PtahForgotPassword, PtahLogin, PtahProfile, PtahResetPassword, PtahTwoFactor } from './auth';
import { PtahDashboard } from './dashboard';
import { ForgePageHeader } from './forge';
import PtahCrud from './PtahCrud.vue';
import PtahLayout from './PtahLayout.vue';

export const PtahApp = defineComponent({
    name: 'PtahApp',
    props: {
        /** `crud`, `login`, `forgot-password`, `reset-password`, `two-factor`, `profile`, `dashboard`. */
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
    },
    setup(props, { slots }) {
        const api = new PtahApi({ baseUrl: props.apiUrl });
        const text = (key: string) => (typeof props.props[key] === 'string' ? (props.props[key] as string) : '');
        const logout = async () => {
            const answer = await api.logout().catch(() => null);
            window.location.assign(answer?.redirect ?? '/');
        };

        return () => {
            const t = words(props.locale);
            const common = { api, locale: props.locale };

            switch (props.page) {
                case 'login':
                    return h(PtahLogin, common);
                case 'forgot-password':
                    return h(PtahForgotPassword, common);
                case 'reset-password':
                    return h(PtahResetPassword, { ...common, token: text('token'), email: text('email') });
                case 'two-factor':
                    return h(PtahTwoFactor, common);
            }

            let content: VNode | VNode[];
            if (props.page in props.pages) {
                content = h(props.pages[props.page]);
            } else if (props.page === 'profile') {
                content = [h(ForgePageHeader, { title: t.profile }), h(PtahProfile, common)];
            } else if (props.page === 'dashboard') {
                content = h(PtahDashboard, common);
            } else {
                content = h(PtahCrud, { screen: props.screen, ...common, onAction: props.onAction });
            }

            return h(
                PtahLayout,
                { api, brand: props.brand, title: props.title, locale: props.locale, profileUrl: props.profileUrl, themeModeUrl: props.themeModeUrl, onLogout: props.canLogout ? () => void logout() : undefined },
                // The `topbar` slot of PtahApp goes into the bar (a company switcher, the notification bell).
                { default: () => content, topbar: () => slots.topbar?.() },
            );
        };
    },
});

/** Mounts the page the server described on the element (`#ptah-app`). */
export function mountPtah(element: HTMLElement | null, extra: Record<string, unknown> = {}): void {
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

    createApp(PtahApp, {
        page: data.page ?? 'crud',
        screen: data.screen,
        title: data.title,
        brand: data.brand,
        locale: data.locale,
        apiUrl: data.api,
        profileUrl: data.profile,
        themeModeUrl: data.themeMode,
        canLogout: data.logout !== undefined,
        props,
        ...extra,
    }).mount(element);
}
