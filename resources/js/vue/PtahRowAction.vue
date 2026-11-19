<script setup lang="ts">
/**
 * One configured action (_row-action.blade.php): its icon, or its label. A
 * link with a confirmation asks the browser; any other action is handed to
 * the screen (`run`), which confirms when configured. Mirrors React's
 * ActionButton.
 */
import { computed } from 'vue';
import type { RowAction } from '../core';

const props = defineProps<{ action: RowAction }>();
const emit = defineEmits<{ run: [] }>();

const color = computed(() => (props.action.color && props.action.color !== 'primary' ? props.action.color : undefined));
const fallback = computed(() => props.action.label || (props.action.type === 'link' ? '→' : '▶'));

const onLink = (event: MouseEvent) => {
    if (props.action.confirm && !window.confirm(props.action.confirm)) {
        event.preventDefault();
    }
};
</script>

<template>
    <a
        v-if="action.type === 'link'"
        class="pc-act pc-act--custom"
        :data-color="color"
        :href="action.value"
        :title="action.label"
        :aria-label="action.label || undefined"
        @click.stop="onLink"
    >
        <i v-if="action.icon" :class="action.icon" aria-hidden="true" /><template v-else>{{ fallback }}</template>
    </a>
    <button v-else type="button" class="pc-act pc-act--custom" :data-color="color" :title="action.label" :aria-label="action.label || undefined" @click.stop="emit('run')">
        <i v-if="action.icon" :class="action.icon" aria-hidden="true" /><template v-else>{{ fallback }}</template>
    </button>
</template>
