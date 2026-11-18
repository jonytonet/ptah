<script setup lang="ts">
import { reactive } from 'vue';
import type { MenuItem } from '../core';

const props = defineProps<{ items: MenuItem[]; path: string; onNavigate?: (url: string) => void }>();

const trim = (value: string) => value.replace(/\/+$/, '') || '/';

const isActive = (item: MenuItem): boolean => {
    if (!item.url) {
        return false;
    }
    let target = item.url;
    try {
        target = new URL(item.url, 'http://localhost').pathname;
    } catch {
        // A relative URL the parser refuses: compared as written.
    }

    return trim(target) === trim(props.path);
};

const hasActive = (item: MenuItem): boolean => isActive(item) || (item.children ?? []).some(hasActive);

// A group starts open when the current page is inside it.
const open = reactive<Record<number, boolean>>({});
props.items.forEach((item, index) => {
    open[index] = hasActive(item);
});

const follow = (event: MouseEvent, item: MenuItem) => {
    if (props.onNavigate && item.url && (!item.target || item.target === '_self') && !event.metaKey && !event.ctrlKey) {
        event.preventDefault();
        props.onNavigate(item.url);
    }
};
</script>

<template>
    <ul class="pc-nav">
        <li v-for="(item, index) in items" :key="`${item.label}-${index}`">
            <template v-if="(item.children ?? []).length > 0">
                <button type="button" class="pc-nav-group" :aria-expanded="Boolean(open[index])" @click="open[index] = !open[index]">
                    <i v-if="item.icon" :class="item.icon" aria-hidden="true" />
                    {{ item.label || item.text }}
                    <span class="pc-nav-caret" aria-hidden="true">{{ open[index] ? '▾' : '▸' }}</span>
                </button>
                <PtahNav v-if="open[index]" :items="item.children ?? []" :path="path" :on-navigate="onNavigate" />
            </template>
            <a
                v-else
                :class="['pc-nav-link', { 'pc-nav-link--active': isActive(item) }]"
                :href="item.url ?? '#'"
                :target="item.target && item.target !== '_self' ? item.target : undefined"
                :rel="item.target === '_blank' ? 'noopener noreferrer' : undefined"
                :aria-current="isActive(item) ? 'page' : undefined"
                @click="follow($event, item)"
            >
                <i v-if="item.icon" :class="item.icon" aria-hidden="true" />
                {{ item.label || item.text }}
            </a>
        </li>
    </ul>
</template>
