<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    adminEntries,
    firstLetter,
    forgetDrafts,
    iconOnlyOf,
    isActive,
    isCollapseShortcut,
    isDark,
    MEDIA_LG,
    MEDIA_MD,
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
import { adminIcons, iconPaths, iconStroke } from '../core/icons';
import { shellWords } from '../core/admin/shell';
import PtahNav from './PtahNav.vue';
import { PtahAiChat } from './admin/ai';
import { PtahCompanySwitcher } from './admin/company';
import { PtahNotificationBell } from './admin/shell';
import { Icon } from './icons';

const props = defineProps<{
    /** The name next to the logo. Defaults to the app's name. */
    brand?: string;
    /** Not drawn in the top bar any more (the navbar has no title): the page draws its own header. */
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

const matches = (query: string) => (typeof window === 'undefined' || !window.matchMedia ? true : window.matchMedia(query).matches);
const client = props.api ?? new PtahApi();
const shell = ref<Shell | null>(null);
const me = ref<Me | null>(null);
const collapsed = ref(readCollapsed());
const hovered = ref(false);
const peek = ref(false);
const open = ref(false);
const md = ref(matches(MEDIA_MD));
const lg = ref(matches(MEDIA_LG));
const dark = ref(isDark());
const adminOpen = ref(false);
const accountOpen = ref(false);
const aside = ref<HTMLElement | null>(null);
const admin = ref<HTMLElement | null>(null);
const account = ref<HTMLElement | null>(null);
const jump = ref<HTMLInputElement | null>(null);
const query = ref('');
const active = ref(-1);

const lang = computed(() => props.locale ?? me.value?.locale);
const t = computed(() => words(lang.value));
const current = computed(() => props.path ?? (typeof window !== 'undefined' ? window.location.pathname : '/'));
const iconOnly = computed(() => iconOnlyOf({ hovered: hovered.value, peek: peek.value, open: open.value, collapsed: collapsed.value, md: md.value, lg: lg.value }));
const name = computed(() => shell.value?.brand.name || props.brand || '');
const extras = computed(() => shell.value?.shell ?? { company_switcher: false, notifications: false, admin: [] });
const hasAdmin = computed(() => extras.value.admin.length > 0 || extras.value.company_switcher);
const adminLinks = computed(() => adminEntries(extras.value.admin, adminIcons));
const results = computed<MenuLink[]>(() => searchMenu(shell.value?.flat ?? [], query.value));
const bellTitle = computed(() => shellWords(lang.value).bellTitle);

const collapse = (value: boolean) => {
    collapsed.value = value;
    writeCollapsed(value);
};
// The rail cannot show a group or the search: expand first (desktop), or peek (tablet).
const expand = () => {
    if (lg.value) {
        collapse(false);
    } else {
        peek.value = true;
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

// ── "Jump to a screen" ──
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
        adminOpen.value = false;
        accountOpen.value = false;
    }
};
// A click outside closes the dropdowns (`@click.away`) and ends the tablet's peek.
const onDown = (event: MouseEvent) => {
    const target = event.target as Node;
    if (adminOpen.value && !admin.value?.contains(target)) {
        adminOpen.value = false;
    }
    if (accountOpen.value && !account.value?.contains(target)) {
        accountOpen.value = false;
    }
    if (peek.value && !aside.value?.contains(target)) {
        peek.value = false;
    }
};
const medias: { list: MediaQueryList; on: () => void }[] = [];
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
    if (typeof window !== 'undefined' && window.matchMedia) {
        for (const [query, target] of [
            [MEDIA_MD, md],
            [MEDIA_LG, lg],
        ] as const) {
            const list = window.matchMedia(query);
            const on = () => {
                target.value = list.matches;
            };
            list.addEventListener?.('change', on);
            medias.push({ list, on });
        }
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.removeEventListener('mousedown', onDown);
    stopDark?.();
    medias.forEach(({ list, on }) => list.removeEventListener?.('change', on));
});
</script>

<template>
    <div class="pl-shell" :data-collapsed="collapsed" :data-icon-only="iconOnly" :data-open="open">
        <button type="button" class="pl-overlay" :aria-label="t.closeMenu" :tabindex="open ? 0 : -1" @click="open = false" />

        <aside ref="aside" class="pl-sidebar" :aria-label="t.menu" :data-hover="hovered" :data-wide="!iconOnly" @mouseenter="hovered = true" @mouseleave="hovered = false">
            <!-- Under the navbar, as in forge-sidebar: the bar above shows the brand. -->
            <div class="pl-brand">
                <slot name="brand">
                    <template v-if="shell?.brand.logo">
                        <img :class="['pl-logo pl-logo--side', { 'pl-logo--light': shell.brand.logo_dark }]" :src="shell.brand.logo" :alt="name" />
                        <img v-if="shell.brand.logo_dark" class="pl-logo pl-logo--side pl-logo--dark" :src="shell.brand.logo_dark" :alt="name" />
                    </template>
                    <span v-else class="pl-logo-mark pl-logo-mark--side" aria-hidden="true">{{ firstLetter(name || 'P') }}</span>
                    <span class="pl-brand-name">{{ brand ?? name }}</span>
                </slot>
            </div>

            <div v-if="shell?.jump" class="pl-jump">
                <!-- On the rail there is no room for a field: a magnifier that expands and focuses it. -->
                <button type="button" class="pl-jump-icon" :aria-label="t.jumpTo" :title="t.jumpTo" @click="focusJump"><i class="bx bx-search" aria-hidden="true" /></button>
                <div class="pl-jump-field">
                    <i class="bx bx-search pl-jump-glyph" aria-hidden="true" />
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
                        class="pl-jump-clear"
                        :aria-label="t.clear"
                        :title="t.clear"
                        @click="
                            resetJump();
                            jump?.focus();
                        "
                    >
                        <i class="bx bx-x" aria-hidden="true" />
                    </button>
                    <ul v-if="results.length > 0" id="pl-jump-list" class="pl-jump-list" role="listbox">
                        <li v-for="(link, index) in results" :key="link.url + index" role="option" :aria-selected="index === active">
                            <button type="button" class="pl-jump-item" :data-active="index === active" @mouseenter="active = index" @click="go(link)">
                                <span class="pl-jump-label">{{ link.label }}</span>
                                <span v-if="trailOf(link).length > 0" class="pl-jump-trail">{{ trailOf(link).join(' · ') }}</span>
                            </button>
                        </li>
                    </ul>
                    <p v-else-if="query.trim() !== ''" class="pl-jump-list pl-jump-empty" role="status">{{ t.nothingFound }}</p>
                </div>
            </div>

            <nav class="pl-nav-scroll">
                <!-- Mounted once the menu is here: a group opens when the page is inside it. -->
                <PtahNav v-if="shell" :items="shell.data" :path="current" :icon-only="iconOnly" :routed="Boolean(onNavigate)" @expand="expand" @follow="follow" />
            </nav>

            <div v-if="onLogout" class="pl-foot">
                <button type="button" class="pl-item pl-logout" :title="iconOnly ? t.logOut : undefined" :aria-label="t.logOut" @click="logout">
                    <span class="pl-item-icon" aria-hidden="true"><i class="bx bx-log-out" /></span>
                    <span class="pl-item-label">{{ t.logOut }}</span>
                </button>
            </div>
        </aside>

        <header class="pl-topbar">
            <div class="pl-topbar-start">
                <button type="button" class="pl-icon-btn pl-hide-lg" :aria-label="open ? t.closeMenu : t.openMenu" :title="open ? t.closeMenu : t.openMenu" :aria-expanded="open" @click="open = !open">
                    <Icon name="menu" :size="20" class-name="pl-svg" />
                </button>
                <button
                    type="button"
                    class="pl-icon-btn pl-show-lg"
                    :aria-label="collapsed ? t.expandMenu : t.collapseMenu"
                    :title="`${collapsed ? t.expandMenu : t.collapseMenu} (Ctrl+B)`"
                    :aria-pressed="collapsed"
                    @click="collapse(!collapsed)"
                >
                    <!-- forge-navbar's toggle: a panel, filled when open, dashed with an arrow when collapsed. -->
                    <svg
                        class="pl-svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        :stroke-width="iconStroke[collapsed ? 'panelClosed' : 'panelOpen']"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                        focusable="false"
                    >
                        <path v-for="d in iconPaths(collapsed ? 'panelClosed' : 'panelOpen')" :key="d" :d="d" />
                        <path v-if="collapsed" d="M9 3v18" stroke-dasharray="2 2" />
                    </svg>
                </button>
                <a class="pl-topbar-brand" href="/">
                    <template v-if="shell?.brand.logo">
                        <img :class="['pl-logo pl-logo--bar', { 'pl-logo--light': shell.brand.logo_dark }]" :src="shell.brand.logo" :alt="name" />
                        <img v-if="shell.brand.logo_dark" class="pl-logo pl-logo--bar pl-logo--dark" :src="shell.brand.logo_dark" :alt="name" />
                    </template>
                    <span v-else class="pl-logo-mark pl-logo-mark--bar" aria-hidden="true">{{ firstLetter(name || 'P') }}</span>
                    <span class="pl-topbar-name">{{ brand ?? name }}</span>
                </a>
            </div>

            <!-- On a phone the switcher moves into the administration menu (when there is one). -->
            <div v-if="extras.company_switcher" :class="['pl-topbar-center', { 'pl-show-md': hasAdmin }]">
                <PtahCompanySwitcher :api="client" :locale="lang" />
            </div>

            <div class="pl-topbar-end">
                <slot name="topbar" />
                <button type="button" class="pl-icon-btn" :aria-label="dark ? t.lightMode : t.darkMode" :title="dark ? t.lightMode : t.darkMode" :aria-pressed="dark" @click="toggleDark(themeModeUrl)">
                    <Icon :name="dark ? 'moon' : 'sun'" :size="20" class-name="pl-svg" />
                </button>

                <div v-if="me?.user && hasAdmin" ref="admin" class="pl-dropdown-root">
                    <button type="button" class="pl-icon-btn" aria-haspopup="true" :aria-expanded="adminOpen" :aria-label="t.administration" :title="t.administration" @click="adminOpen = !adminOpen">
                        <Icon name="cog" :size="20" class-name="pl-svg" />
                    </button>
                    <div v-if="adminOpen" class="pl-dropdown pl-dropdown--admin">
                        <!-- On a phone the switcher of the bar is hidden; it lives here, stacked. -->
                        <div v-if="extras.company_switcher" class="pl-hide-md"><PtahCompanySwitcher :api="client" :locale="lang" layout="stacked" /></div>
                        <div v-for="link in adminLinks" :key="link.url">
                            <hr v-if="link.rule" class="pl-rule" />
                            <a class="pl-dropdown-item" :href="link.url" :aria-current="isActive({ label: link.label, url: link.url }, current) ? 'page' : undefined" @click="adminOpen = false">
                                <Icon v-if="link.icon" :name="link.icon" :size="16" class-name="pl-dropdown-icon" />
                                <i v-else :class="`${link.boxicon} pl-dropdown-icon pl-dropdown-bx`" aria-hidden="true" />
                                {{ link.label }}
                            </a>
                        </div>
                    </div>
                </div>

                <PtahNotificationBell v-if="extras.notifications" :api="client" :locale="lang" :on-navigate="onNavigate" />
                <button v-else-if="extras.static_bell" type="button" class="pl-icon-btn" :aria-label="bellTitle">
                    <Icon name="bell" :size="20" class-name="pl-svg" />
                    <span class="pl-dot" aria-hidden="true" />
                </button>

                <div v-if="me?.user" ref="account" class="pl-dropdown-root">
                    <button type="button" class="pl-account-btn" aria-haspopup="true" :aria-expanded="accountOpen" :aria-label="`${t.account}: ${me.user.name}`" @click="accountOpen = !accountOpen">
                        <img v-if="me.user.photo_url" class="pl-avatar pl-avatar--photo" :src="me.user.photo_url" :alt="me.user.name" />
                        <span v-else class="pl-avatar" aria-hidden="true">{{ firstLetter(me.user.name) }}</span>
                        <span class="pl-account-name pl-show-lg">{{ me.user.name }}</span>
                        <Icon name="chevronDown" :size="16" class-name="pl-account-chevron pl-show-lg" />
                    </button>
                    <div v-if="accountOpen" class="pl-dropdown pl-dropdown--account">
                        <a v-if="profileUrl" class="pl-dropdown-item pl-dropdown-item--tight" :href="profileUrl"><Icon name="user" :size="16" />{{ t.profile }}</a>
                        <hr v-if="profileUrl && onLogout" class="pl-rule" />
                        <button v-if="onLogout" type="button" class="pl-dropdown-item pl-dropdown-item--tight pl-logout" @click="logout"><Icon name="logout" :size="16" />{{ t.logOut }}</button>
                    </div>
                </div>
            </div>
        </header>

        <div class="pl-main">
            <main class="pl-content">
                <div class="pl-page"><slot /></div>
            </main>
        </div>
        <PtahAiChat v-if="extras.ai_chat" :api="client" :locale="lang" />
    </div>
</template>
