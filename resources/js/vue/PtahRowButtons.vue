<script setup lang="ts">
/**
 * Edit, duplicate, delete / restore as icon buttons — the sticky column of
 * the table (faded until the row is hovered) or the footer of a card, which
 * also carries the configured actions first (_table / _cards). Mirrors
 * React's RowButtons. A configured action that asks for a confirmation is
 * handed to the screen (`run`), which owns the confirm dialog.
 */
import type { CrudController, Row, RowAction, Schema } from '../core';
import type { ScreenWords } from '../core/crud/screen';
import PtahRowAction from './PtahRowAction.vue';
import { Icon } from './icons';

defineProps<{
    crud: CrudController;
    row: Row;
    permissions: Schema['permissions'];
    w: ScreenWords;
    trashed: boolean;
    card?: boolean;
}>();

const emit = defineEmits<{ run: [action: RowAction] }>();
</script>

<template>
    <div :class="card ? 'pc-card-btns' : 'pc-row-btns'">
        <template v-if="card">
            <PtahRowAction v-for="(action, index) in row.actions" :key="index" :action="action" @run="emit('run', action)" />
        </template>
        <button v-if="permissions.canUpdate && !trashed" type="button" class="pc-act pc-act--edit" :title="w.edit" :aria-label="w.edit" @click.stop="crud.openEdit(row.id)">
            <Icon name="edit" />
        </button>
        <button v-if="permissions.canCreate && !trashed" type="button" class="pc-act pc-act--dup" :title="w.duplicate" :aria-label="w.duplicate" @click.stop="crud.duplicate(row.id)">
            <Icon name="duplicate" />
        </button>
        <template v-if="trashed && row.trashed">
            <button v-if="permissions.canRestore" type="button" class="pc-act pc-act--restore" :title="w.restore" :aria-label="w.restore" @click.stop="crud.restore(row.id)">
                <Icon name="refresh" />
            </button>
        </template>
        <button v-else-if="permissions.canDelete" type="button" class="pc-act pc-act--del" :title="w.delete" :aria-label="w.delete" @click.stop="crud.askDelete(row.id)">
            <Icon name="trash" />
        </button>
    </div>
</template>
