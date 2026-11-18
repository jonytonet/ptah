<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { PtahApi, words } from '../core';
import type { Me, MenuItem } from '../core';
import PtahNav from './PtahNav.vue';

const props = withDefaults(
    defineProps<{
        /** The name shown above the menu. */
        brand?: string;
        /** The title in the top bar. */
        title?: string;
        api?: PtahApi;
        locale?: string;
        /** The current path, to mark the active item. Defaults to the browser's. */
        path?: string;
        /** Navigate without a full page load (a router's `push`). Defaults to a normal link. */
        onNavigate?: (url: string) => void;
    }>(),
    { brand: 'Ptah' },
);

const client = props.api ?? new PtahApi();
const menu = ref<MenuItem[] | null>(null);
const me = ref<Me | null>(null);
const open = ref(false);
const collapsed = ref(false);
const t = computed(() => words(props.locale ?? me.value?.locale));
const current = computed(() => props.path ?? (typeof window !== 'undefined' ? window.location.pathname : '/'));

onMounted(() => {
    void client
        .menu()
        .then((items) => {
            menu.value = items;
        })
        .catch(() => {
            menu.value = [];
        });
    void client
        .me()
        .then((user) => {
            me.value = user;
        })
        .catch(() => undefined);
});

const toggle = () => {
    open.value = !open.value;
    collapsed.value = !collapsed.value;
};
</script>

<template>
    <div :class="['pc-shell', { 'pc-shell--open': open, 'pc-shell--collapsed': collapsed }]">
        <aside class="pc-sidebar" :aria-label="t.menu">
            <div class="pc-brand">
                <slot name="brand">{{ brand }}</slot>
            </div>
            <!-- Mounted once the menu is here: a group opens when the page is inside it. -->
            <PtahNav v-if="menu" :items="menu" :path="current" :on-navigate="onNavigate" />
        </aside>
        <div class="pc-main">
            <header class="pc-topbar">
                <button type="button" class="pc-icon-btn" :aria-label="t.menu" :aria-expanded="!collapsed" @click="toggle">☰</button>
                <span v-if="title" class="pc-topbar-title">{{ title }}</span>
                <span v-if="me?.user" class="pc-user">{{ me.user.name }}</span>
                <slot name="topbar" />
            </header>
            <main class="pc-content">
                <slot />
            </main>
        </div>
    </div>
</template>
