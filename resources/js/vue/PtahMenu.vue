<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{ label: string }>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);

const onDown = (event: MouseEvent) => {
    if (open.value && !root.value?.contains(event.target as Node)) {
        open.value = false;
    }
};
const onKey = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', onDown);
    document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDown);
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <div ref="root" class="pc-menu">
        <button type="button" class="pc-btn" aria-haspopup="true" :aria-expanded="open" @click="open = !open">{{ label }} ▾</button>
        <ul v-if="open" class="pc-menu-list">
            <slot />
        </ul>
    </div>
</template>
