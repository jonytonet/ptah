<script setup lang="ts">
import { reactive } from 'vue';
import { hasActive, isActive, visible } from '../core';
import type { MenuItem } from '../core';
import { Icon } from './icons';

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
const label = (item: MenuItem) => item.label || item.text || '';

// A group starts open when the current page is inside it.
const open = reactive<Record<number, boolean>>({});
shown().forEach((item, index) => {
    open[index] = groupActive(item);
});

const toggle = (index: number) => {
    // On the rail: expand the sidebar and open the group, in one gesture.
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
                    :aria-expanded="Boolean(open[index])"
                    aria-haspopup="true"
                    :aria-label="label(item)"
                    :title="iconOnly ? label(item) : undefined"
                    @click="toggle(index)"
                >
                    <span class="pl-item-icon" aria-hidden="true"><i :class="item.icon?.trim() ? item.icon : 'bx bx-circle'" /></span>
                    <span class="pl-item-label">{{ label(item) }}</span>
                    <Icon name="chevronDown" :size="14" class-name="pl-caret" />
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
            <!-- A group with nothing inside: a label that goes nowhere, as in forge-sidebar. -->
            <div v-else-if="item.type === 'menuGroup'" class="pl-item pl-item--empty" :title="iconOnly ? label(item) : undefined">
                <span class="pl-item-icon" aria-hidden="true"><i :class="item.icon?.trim() ? item.icon : 'bx bx-circle'" /></span>
                <span class="pl-item-label">{{ label(item) }}</span>
            </div>
            <a
                v-else
                :class="nested ? 'pl-item pl-item--child' : 'pl-item'"
                :href="item.url ?? '#'"
                :title="iconOnly ? label(item) : undefined"
                :target="external(item) ? item.target : undefined"
                :rel="item.target === '_blank' ? 'noopener noreferrer' : undefined"
                :aria-current="isActive(item, path) ? 'page' : undefined"
                @click="click($event, item)"
            >
                <span class="pl-item-icon" aria-hidden="true"><i :class="item.icon?.trim() ? item.icon : 'bx bx-circle'" /></span>
                <span class="pl-item-label">{{ label(item) }}</span>
            </a>
        </li>
    </ul>
</template>
