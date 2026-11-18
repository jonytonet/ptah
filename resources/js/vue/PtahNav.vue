<script setup lang="ts">
import { reactive } from 'vue';
import { hasActive, isActive, visible } from '../core';
import type { MenuItem } from '../core';

const props = defineProps<{
    items: MenuItem[];
    path: string;
    /** Only the icons are drawn: a group cannot be shown open. */
    iconOnly: boolean;
    /** With a router, a plain click is handled in the page; otherwise the link navigates by itself. */
    routed: boolean;
    nested?: boolean;
}>();
const emit = defineEmits<{ expand: []; follow: [url: string, target?: string] }>();

const shown = () => visible(props.items);
const kids = (item: MenuItem) => visible(item.children ?? []);
const groupActive = (item: MenuItem) => kids(item).length > 0 && hasActive(item, props.path);
const external = (item: MenuItem) => Boolean(item.target && item.target !== '_self');

// A group starts open when the current page is inside it.
const open = reactive<Record<number, boolean>>({});
shown().forEach((item, index) => {
    open[index] = groupActive(item);
});

const toggle = (index: number) => {
    // Icon-only: expand the sidebar and open the group, in one gesture.
    if (props.iconOnly) {
        emit('expand');
        open[index] = true;
    } else {
        open[index] = !open[index];
    }
};

const click = (event: MouseEvent, item: MenuItem) => {
    if (props.routed && item.url && !external(item) && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
        event.preventDefault();
        emit('follow', item.url);
    }
};
</script>

<template>
    <ul :class="nested ? 'pl-nav pl-subnav' : 'pl-nav'">
        <li v-for="(item, index) in shown()" :key="`${item.label}-${index}`">
            <template v-if="kids(item).length > 0">
                <button
                    type="button"
                    :class="['pl-item', { 'pl-item--group-active': groupActive(item) }]"
                    :aria-expanded="Boolean(open[index]) && !iconOnly"
                    :title="item.label || item.text"
                    @click="toggle(index)"
                >
                    <span class="pl-item-icon" aria-hidden="true"><i :class="item.icon?.trim() ? item.icon : 'bx bx-circle'" /></span>
                    <span class="pl-item-label">{{ item.label || item.text }}</span>
                    <span class="pl-caret" :data-open="Boolean(open[index])" aria-hidden="true">▶</span>
                </button>
                <PtahNav
                    v-if="open[index] && !iconOnly"
                    :items="kids(item)"
                    :path="path"
                    :icon-only="iconOnly"
                    :routed="routed"
                    nested
                    @expand="emit('expand')"
                    @follow="(url, target) => emit('follow', url, target)"
                />
            </template>
            <a
                v-else
                class="pl-item"
                :href="item.url ?? '#'"
                :title="item.label || item.text"
                :target="external(item) ? item.target : undefined"
                :rel="item.target === '_blank' ? 'noopener noreferrer' : undefined"
                :aria-current="isActive(item, path) ? 'page' : undefined"
                @click="click($event, item)"
            >
                <span class="pl-item-icon" aria-hidden="true"><i :class="item.icon?.trim() ? item.icon : 'bx bx-circle'" /></span>
                <span class="pl-item-label">{{ item.label || item.text }}</span>
            </a>
        </li>
    </ul>
</template>
