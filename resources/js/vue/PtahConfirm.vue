<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';

defineProps<{ text: string; confirm: string; cancel: string; danger?: boolean }>();
const emit = defineEmits<{ confirm: []; cancel: [] }>();

const onKey = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        emit('cancel');
    }
};

onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="pc-overlay" @mousedown.self="emit('cancel')">
        <div class="pc-modal pc-modal--sm" role="alertdialog" aria-modal="true" :aria-label="text">
            <div class="pc-modal-body">{{ text }}</div>
            <div class="pc-modal-foot">
                <button type="button" class="pc-btn" @click="emit('cancel')">{{ cancel }}</button>
                <button type="button" :class="['pc-btn', danger ? 'pc-btn--danger' : 'pc-btn--primary']" autofocus @click="emit('confirm')">
                    {{ confirm }}
                </button>
            </div>
        </div>
    </div>
</template>
