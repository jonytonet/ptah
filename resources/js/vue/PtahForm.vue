<script setup lang="ts">
/**
 * The create/edit modal, as the Livewire one: title by gender and subtitle,
 * sections, focus on the first field, the page locked behind it, "discard
 * changes?" before closing a changed form (Cancel, ✕, Escape, a click
 * outside), and a loading state while the form is on its way — mounted with
 * `state.form || crud.formOpening`. Mirrors the React FormModal.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { CrudController, CrudState, Words } from '../core';
import { focusFirstField, formSections, formSubtitle, formTitle, lockBodyScroll, modalButton, needsDiscardConfirm, submitLabel } from '../core/crud/form';
import { PtahFormField, PtahFormSection } from './crud/form';
import { PtahCrudFormChanges, PtahCrudFormDraft, PtahCrudSaveAndNew } from './crud/extras';
import { PtahCrudRecordButtons } from './crud/records';
import { Icon } from './icons';

const props = defineProps<{ crud: CrudController; state: CrudState; t: Words; locale?: string }>();

const titleId = computed(() => `pc-form-title-${props.crud.screen}`);
const dirty = ref(false);
const confirming = ref(false);
const body = ref<HTMLElement | null>(null);
const form = computed(() => props.state.form);
const editing = computed(() => (form.value ? form.value.mode === 'edit' : props.crud.formOpening === 'edit'));
const entries = computed(() => formSections(props.state.schema?.form ?? []));
const title = computed(() => {
    const schema = props.state.schema;
    if (!schema) {
        return '';
    }

    return form.value ? formTitle(form.value, schema, props.t) : `${editing.value ? props.t.editPrefix : props.t.newPrefix} ${schema.titleSingular}`;
});
const subtitle = computed(() => (form.value ? formSubtitle(form.value, props.t) : editing.value ? props.t.editSubtitle : props.t.createSubtitle));

// A new opening (also "save & new", a draft reverted) starts clean and focused.
// Two sources, each compared on its own: a new state object (every keystroke) is not a new opening.
watch(
    [() => form.value?.instance ?? 0, () => form.value === null],
    () => {
        dirty.value = false;
        confirming.value = false;
        if (form.value) {
            void nextTick(() => focusFirstField(body.value));
        }
    },
    { immediate: true },
);

// The confirmation opens on "Keep editing", the safe choice.
const keepButton = ref<HTMLButtonElement | null>(null);
watch(confirming, (open) => {
    if (open) {
        void nextTick(() => keepButton.value?.focus());
    }
});

const touch = () => {
    dirty.value = true;
};

const tryClose = () => {
    if (needsDiscardConfirm(props.crud, dirty.value)) {
        confirming.value = true;
    } else {
        props.crud.closeForm();
    }
};

// The confirmation takes the Escape first; a panel over the form (history, attachments) too.
const onKey = (event: KeyboardEvent) => {
    if (event.key !== 'Escape') {
        return;
    }
    if (confirming.value) {
        confirming.value = false;
    } else if (!props.crud.getState().panel) {
        tryClose();
    }
};

let release: (() => void) | null = null;
onMounted(() => {
    document.addEventListener('keydown', onKey);
    release = lockBodyScroll();
});
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    release?.();
});
</script>

<template>
    <div v-if="state.schema && (form || crud.formOpening)" class="pc-root pc-fm-back" @mousedown.self="tryClose()">
        <div class="pc-fm-panel pc-fm" data-size="2xl" role="dialog" aria-modal="true" :aria-labelledby="titleId" :aria-busy="form ? undefined : true">
            <form novalidate @submit.prevent="crud.save()">
                <div class="pc-fm-head">
                    <div>
                        <h3 :id="titleId" class="pc-fm-title">{{ title }}</h3>
                        <p class="pc-fm-sub">{{ subtitle }}</p>
                    </div>
                    <button type="button" class="pc-fm-close" :aria-label="t.close" :title="t.close" @click="tryClose()"><Icon name="x" :size="20" /></button>
                </div>
                <div ref="body" class="pc-fm-body">
                    <div v-if="!form" class="pc-fm-loading" role="status">
                        <span class="pc-fm-spin" aria-hidden="true" />
                        {{ t.formLoading }}
                    </div>
                    <template v-else>
                        <p v-if="form.errors._general" class="pc-alert" role="alert">{{ form.errors._general }}</p>
                        <PtahCrudFormDraft :crud="crud" :locale="locale" />
                        <PtahCrudFormChanges :crud="crud" />
                        <!-- Typing anywhere in the fields makes the form dirty, as the Blade's @input/@change. -->
                        <div class="pc-form" @input="touch" @change="touch">
                            <template v-for="entry in entries" :key="entry.field.field">
                                <PtahFormSection v-if="entry.heading !== null" :title="entry.heading" />
                                <PtahFormField :crud="crud" :form="form" :field="entry.field" :t="t" @touch="touch" />
                            </template>
                        </div>
                    </template>
                </div>
                <div class="pc-fm-foot">
                    <!-- Anexos (n) and Histórico of the record being edited; then the rest pushed right. -->
                    <PtahCrudRecordButtons v-if="form && form.mode === 'edit' && form.id !== null" :crud="crud" :row="{ id: form.id }" :locale="locale" />
                    <span class="pc-fm-push" aria-hidden="true" />
                    <button type="button" :class="modalButton('flat')" :disabled="form?.saving" @click="tryClose()">{{ t.cancel }}</button>
                    <PtahCrudSaveAndNew v-if="form" :crud="crud" :locale="locale" />
                    <button v-if="form" type="submit" :class="modalButton('primary')" :disabled="form.saving" :aria-busy="form.saving || undefined">
                        <span v-if="form.saving" class="pc-fm-spin" aria-hidden="true" />
                        {{ form.saving ? t.saving : submitLabel(form, t) }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div v-if="confirming" class="pc-root pc-fm-back pc-fm-back--top" @mousedown.self="confirming = false">
        <div class="pc-fm-mini" role="alertdialog" aria-modal="true" :aria-labelledby="`${titleId}-discard`" :aria-describedby="`${titleId}-discard-text`">
            <p :id="`${titleId}-discard`" class="pc-fm-mini-ttl">{{ t.unsavedTitle }}</p>
            <p :id="`${titleId}-discard-text`" class="pc-fm-mini-sub">{{ t.unsavedConfirm }}</p>
            <div class="pc-fm-mini-foot">
                <button ref="keepButton" type="button" :class="modalButton('flat')" @click="confirming = false">{{ t.unsavedKeep }}</button>
                <button type="button" :class="modalButton('danger')" @click="crud.closeForm()">{{ t.unsavedDiscard }}</button>
            </div>
        </div>
    </div>
</template>

