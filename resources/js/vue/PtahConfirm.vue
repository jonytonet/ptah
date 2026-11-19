<script setup lang="ts">
/**
 * A yes/no question: with a `title`, the delete confirmation of the Livewire
 * screen (_modal-delete: forge-modal sm, warning icon + message, Cancelar /
 * Excluir); without, a plain question. Mirrors React's PtahConfirmDialog.
 */
import { modalButton } from '../core/crud/form';
import { PtahCrudModal } from './crud/modal';
import { Icon } from './icons';

defineProps<{ text: string; confirm: string; cancel: string; danger?: boolean; title?: string; closeLabel?: string }>();
const emit = defineEmits<{ confirm: []; cancel: [] }>();
</script>

<template>
    <PtahCrudModal :title="title ?? text" size="sm" role="alertdialog" :close-label="closeLabel ?? cancel" @close="emit('cancel')">
        <div v-if="title" class="pc-fm-warn">
            <span v-if="danger" class="pc-fm-warn-icon"><Icon name="warning" :size="20" /></span>
            <p>{{ text }}</p>
        </div>
        <template #footer>
            <button type="button" :class="modalButton('flat')" @click="emit('cancel')">{{ cancel }}</button>
            <button type="button" :class="modalButton(danger ? 'danger' : 'primary')" autofocus @click="emit('confirm')">
                {{ confirm }}
            </button>
        </template>
    </PtahCrudModal>
</template>
