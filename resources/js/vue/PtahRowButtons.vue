<script setup lang="ts">
/**
 * The buttons of a row — history/attachments, the configured row actions,
 * edit / duplicate / delete / restore. One component for the table cell and
 * the card footer, so both views offer the same (as React's RowButtons).
 * A row action that asks for a confirmation is handed to the screen
 * (`run`), which owns the confirm dialog.
 */
import type { CrudController, Row, RowAction, Schema, Words } from '../core';
import { PtahCrudRecordButtons } from './crud/records';

defineProps<{
    crud: CrudController;
    row: Row;
    permissions: Schema['permissions'];
    t: Words;
    locale?: string;
}>();

const emit = defineEmits<{ run: [action: RowAction] }>();

const onLink = (event: MouseEvent, action: RowAction) => {
    if (action.confirm && !window.confirm(action.confirm)) {
        event.preventDefault();
    }
};
</script>

<template>
    <PtahCrudRecordButtons :crud="crud" :row="row" :locale="locale" />
    <template v-for="(action, index) in row.actions" :key="index">
        <a v-if="action.type === 'link'" class="pc-icon-btn" :href="action.value" :title="action.label" :aria-label="action.label" @click="onLink($event, action)">
            <i v-if="action.icon" :class="action.icon" aria-hidden="true" /><template v-else>{{ action.label || '→' }}</template>
        </a>
        <button v-else type="button" class="pc-icon-btn" :title="action.label" :aria-label="action.label" @click="emit('run', action)">
            <i v-if="action.icon" :class="action.icon" aria-hidden="true" /><template v-else>{{ action.label || '→' }}</template>
        </button>
    </template>
    <template v-if="row.trashed">
        <button v-if="permissions.canRestore" type="button" class="pc-icon-btn" @click="crud.restore(row.id)">{{ t.restore }}</button>
    </template>
    <template v-else>
        <button v-if="permissions.canUpdate" type="button" class="pc-icon-btn" @click="crud.openEdit(row.id)">{{ t.edit }}</button>
        <button v-if="permissions.canCreate" type="button" class="pc-icon-btn" @click="crud.duplicate(row.id)">{{ t.duplicate }}</button>
        <button v-if="permissions.canDelete" type="button" class="pc-icon-btn pc-icon-btn--danger" @click="crud.askDelete(row.id)">{{ t.delete }}</button>
    </template>
</template>
