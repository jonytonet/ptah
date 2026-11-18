<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    forgetDrafts,
    initials,
    isActive,
    isCollapseShortcut,
    isDark,
    onDarkChange,
    PtahApi,
    readCollapsed,
    searchMenu,
    toggleDark,
    trailOf,
    words,
    writeCollapsed,
} from '../core';
import type { Me, MenuLink, Shell } from '../core';
import PtahNav from './PtahNav.vue';
import { PtahCompanySwitcher } from './admin/company';
import { PtahNotificationBell } from './admin/shell';

const props = defineProps<{
    /** The name shown above the menu. Defaults to the app's name. */
    brand?: string;
    /** The title in the top bar. */
    title?: string;
    api?: PtahApi;
    locale?: string;
    /** The current path, to mark the active item. Defaults to the browser's. */
    path?: string;
    /** Navigate without a full page load (a router's `push`). Defaults to a normal link. */
    onNavigate?: (url: string) => void;
    /** The profile page, in the account menu. */
    profileUrl?: string;
    /** Runs "log out" (the account menu and the foot of the sidebar). Omit to hide it. */
    onLogout?: () => void;
    /** Where the light/dark choice of a signed-in user is saved (`ptah.appearance.theme-mode`). */
    themeModeUrl?: string;
}>();

const DESKTOP = '(min-width: 1024px)';
const client = props.api ?? new PtahApi();
const shell = ref<Shell | null>(null);
const me = ref<Me | null>(null);
const collapsed = ref(readCollapsed());
const hovered = ref(false);
const open = ref(false);
const desktop = ref(typeof window === 'undefined' || !window.matchMedia ? true : window.matchMedia(DESKTOP).matches);
const dark = ref(isDark());
const accountOpen = ref(false);
const account = ref<HTMLElement | null>(null);
const jump = ref<HTMLInputElement | null>(null);
const query = ref('');
const active = ref(-1);

const t = computed(() => words(props.locale ?? me.value?.locale));
const current = computed(() => props.path ?? (typeof window !== 'undefined' ? window.location.pathname : '/'));
// Only the icons: collapsed on a desktop, and the mouse is not over it.
const iconOnly = computed(() => desktop.value && collapsed.value && !hovered.value);
const name = computed(() => shell.value?.brand.name ?? '');
const extras = computed(() => shell.value?.shell ?? { company_switcher: false, notifications: false, admin: [] });
const lang = computed(() => props.locale ?? me.value?.locale);
const results = computed<MenuLink[]>(() => searchMenu(shell.value?.flat ?? [], query.value));

const collapse = (value: boolean) => {
    collapsed.value = value;
    writeCollapsed(value);
};
// Icon-only cannot show a group or the search: expand first, then act.
const expand = () => {
    if (desktop.value) {
        collapse(false);
    }
};

const follow = (url: string, target?: string) => {
    open.value = false;
    if (target === '_blank') {
        window.open(url, '_blank', 'noopener');
    } else if (props.onNavigate) {
        props.onNavigate(url);
    } else {
        window.location.assign(url);
    }
};

const logout = () => {
    forgetDrafts();
    props.onLogout?.();
};

// ── "Go to a screen" ──
const resetJump = () => {
    query.value = '';
    active.value = -1;
};
const go = (link: MenuLink | undefined) => {
    if (link) {
        resetJump();
        follow(link.url, link.target);
    }
};
const onJumpKey = (event: KeyboardEvent) => {
    const count = results.value.length;
    if (event.key === 'ArrowDown' && count > 0) {
        event.preventDefault();
        active.value = (active.value + 1) % count;
    } else if (event.key === 'ArrowUp' && count > 0) {
        event.preventDefault();
        active.value = (active.value - 1 + count) % count;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        go(results.value[active.value] ?? results.value[0]);
    } else if (event.key === 'Escape') {
        event.stopPropagation();
        resetJump();
    }
};
const onJumpInput = (event: Event) => {
    query.value = (event.target as HTMLInputElement).value;
    active.value = -1;
};
const focusJump = () => {
    expand();
    setTimeout(() => jump.value?.focus(), 0);
};

// ── Page-wide listeners ──
const onKey = (event: KeyboardEvent) => {
    if (isCollapseShortcut(event)) {
        event.preventDefault();
        collapse(!collapsed.value);
    } else if (event.key === 'Escape') {
        open.value = false;
        accountOpen.value = false;
    }
};
const onDown = (event: MouseEvent) => {
    if (accountOpen.value && !account.value?.contains(event.target as Node)) {
        accountOpen.value = false;
    }
};
let media: MediaQueryList | null = null;
const onMedia = () => {
    if (media) {
        desktop.value = media.matches;
    }
};
let stopDark: (() => void) | undefined;

onMounted(() => {
    void client
        .shell()
        .then((value) => {
            shell.value = value;
        })
        .catch(() => {
            shell.value = { data: [], flat: [], jump: false, brand: { name: '', logo: null, logo_dark: null }, shell: { company_switcher: false, notifications: false, admin: [] } };
        });
    void client
        .me()
        .then((user) => {
            me.value = user;
        })
        .catch(() => undefined);

    document.addEventListener('keydown', onKey);
    document.addEventListener('mousedown', onDown);
    stopDark = onDarkChange((value) => {
        dark.value = value;
    });
    media = typeof window !== 'undefined' && window.matchMedia ? window.matchMedia(DESKTOP) : null;
    media?.addEventListener?.('change', onMedia);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.removeEventListener('mousedown', onDown);
    stopDark?.();
    media?.removeEventListener?.('change', onMedia);
});
</script>

<template>
    <div class="pl-shell" :data-collapsed="collapsed" :data-icon-only="iconOnly" :data-open="open">
        <button type="button" class="pl-overlay" :aria-label="t.closeMenu" @click="open = false" />

        <aside class="pl-sidebar" :aria-label="t.menu" :data-hover="hovered" @mouseenter="hovered = true" @mouseleave="hovered = false">
            <div class="pl-brand">
                <slot name="brand">
                    <template v-if="shell?.brand.logo">
                        <img :class="['pl-logo', { 'pl-logo--light': shell.brand.logo_dark }]" :src="shell.brand.logo" :alt="name" />
                        <img v-if="shell.brand.logo_dark" class="pl-logo pl-logo--dark" :src="shell.brand.logo_dark" :alt="name" />
                    </template>
                    <template v-else>
                        <span class="pl-logo-mark" aria-hidden="true">{{ (brand ?? name).trim().charAt(0).toUpperCase() || 'P' }}</span>
                        <span class="pl-brand-name">{{ brand ?? name }}</span>
                    </template>
                </slot>
            </div>

            <div v-if="shell?.jump" class="pl-jump">
                <!-- Collapsed there is no room for a field: a magnifier that expands and focuses it. -->
                <button type="button" class="pl-item pl-jump-icon" :aria-label="t.jumpTo" :title="t.jumpTo" @click="focusJump">
                    <span class="pl-item-icon" aria-hidden="true"><i class="bx bx-search" /></span>
                </button>
                <input
                    ref="jump"
                    type="text"
                    role="combobox"
                    class="pl-jump-input"
                    :placeholder="t.jumpTo"
                    :aria-label="t.jumpTo"
                    :aria-expanded="results.length > 0"
                    aria-controls="pl-jump-list"
                    aria-autocomplete="list"
                    autocomplete="off"
                    :value="query"
                    @input="onJumpInput"
                    @keydown="onJumpKey"
                />
                <button
                    v-if="query !== ''"
                    type="button"
                    class="pf-x pl-jump-clear"
                    :aria-label="t.clear"
                    @click="
                        resetJump();
                        jump?.focus();
                    "
                >
                    ✕
                </button>
                <ul v-if="results.length > 0" id="pl-jump-list" class="pl-jump-list" role="listbox">
                    <li v-for="(link, index) in results" :key="link.url + index" role="option" :aria-selected="index === active">
                        <button type="button" class="pl-jump-item" :data-active="index === active" @mouseenter="active = index" @click="go(link)">
                            <span>{{ link.label }}</span>
                            <span v-if="trailOf(link).length > 0" class="pl-jump-trail">{{ trailOf(link).join(' ‹ ') }}</span>
                        </button>
                    </li>
                </ul>
                <div v-else-if="query.trim() !== ''" class="pl-jump-list pl-jump-empty" role="status">{{ t.nothingFound }}</div>
            </div>

            <nav class="pl-nav-scroll">
                <!-- Mounted once the menu is here: a group opens when the page is inside it. -->
                <PtahNav v-if="shell" :items="shell.data" :path="current" :icon-only="iconOnly" :routed="Boolean(onNavigate)" @expand="expand" @follow="follow" />
            </nav>

            <div v-if="onLogout" class="pl-foot">
                <button type="button" class="pl-item" :title="t.logOut" @click="logout">
                    <span class="pl-item-icon" aria-hidden="true"><i class="bx bx-log-out" /></span>
                    <span class="pl-item-label">{{ t.logOut }}</span>
                </button>
            </div>
        </aside>

        <div class="pl-main">
            <header class="pl-topbar">
                <button type="button" class="pl-icon-btn pl-only-mobile" :aria-label="open ? t.closeMenu : t.openMenu" :aria-expanded="open" @click="open = !open">☰</button>
                <button type="button" class="pl-icon-btn pl-only-desktop" :aria-label="collapsed ? t.expandMenu : t.collapseMenu" :aria-pressed="collapsed" title="Ctrl+B" @click="collapse(!collapsed)">
                    {{ collapsed ? '»' : '«' }}
                </button>
                <span v-if="title" class="pl-topbar-title">{{ title }}</span>
                <div class="pl-topbar-end">
                    <span v-if="extras.company_switcher" class="pl-only-desktop"><PtahCompanySwitcher :api="client" :locale="lang" /></span>
                    <slot name="topbar" />
                    <PtahNotificationBell v-if="extras.notifications" :api="client" :locale="lang" :on-navigate="onNavigate" />
                    <button type="button" class="pl-icon-btn" :aria-label="dark ? t.lightMode : t.darkMode" :aria-pressed="dark" @click="toggleDark(themeModeUrl)">{{ dark ? '☀' : '☾' }}</button>
                    <div v-if="me?.user" ref="account" class="pl-account">
                        <button type="button" class="pl-account-btn" aria-haspopup="true" :aria-expanded="accountOpen" :aria-label="`${t.account}: ${me.user.name}`" @click="accountOpen = !accountOpen">
                            <span class="pl-avatar">
                                <img v-if="me.user.photo_url" :src="me.user.photo_url" alt="" />
                                <template v-else>{{ initials(me.user.name) }}</template>
                            </span>
                            <span class="pl-only-desktop">{{ me.user.name }}</span>
                        </button>
                        <ul v-if="accountOpen" class="pl-account-menu">
                            <li class="pl-account-head">
                                <span class="pl-account-name">{{ me.user.name }}</span>
                                <span class="pl-account-mail">{{ me.user.email }}</span>
                            </li>
                            <li v-if="profileUrl">
                                <a class="pl-account-item" :href="profileUrl">{{ t.profile }}</a>
                            </li>
                            <!-- On a phone the switcher of the bar is hidden; it lives here. -->
                            <li v-if="extras.company_switcher" class="pl-only-mobile pl-account-group"><PtahCompanySwitcher :api="client" :locale="lang" layout="stacked" /></li>
                            <li v-if="extras.admin.length" class="pl-account-label">{{ t.administration }}</li>
                            <li v-for="link in extras.admin" :key="link.url">
                                <a class="pl-account-item" :href="link.url" :aria-current="isActive({ label: link.label, url: link.url }, current) ? 'page' : undefined"><i :class="link.icon" aria-hidden="true" /> {{ link.label }}</a>
                            </li>
                            <li v-if="onLogout">
                                <button type="button" class="pl-account-item" @click="logout">{{ t.logOut }}</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>
            <main class="pl-content">
                <slot />
            </main>
        </div>
    </div>
</template>
