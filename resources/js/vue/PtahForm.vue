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
import { focusFirstField, formSections, formSubtitle, formTitle, lockBodyScroll, needsDiscardConfirm, submitLabel } from '../core/crud/form';
import { PtahFormField, PtahFormSection } from './crud/form';
import { PtahCrudFormChanges, PtahCrudFormDraft, PtahCrudSaveAndNew } from './crud/extras';
import { PtahCrudRecordButtons } from './crud/records';

const props = defineProps<{ crud: CrudController; state: CrudState; t: Words; locale?: string }>();

const titleId = computed(() => `pc-form-title-${props.crud.screen}`);
const dirty = ref(false);
const confirming = ref(false);
const body = ref<HTMLElement | null>(null);
const form = computed(() => props.state.form);
const entries = computed(() => formSections(props.state.schema?.form ?? []));
const title = computed(() => {
    const schema = props.state.schema;
    if (!schema) {
        return '';
    }

    return form.value ? formTitle(form.value, schema, props.t) : `${props.crud.formOpening === 'edit' ? props.t.editPrefix : props.t.newPrefix} ${schema.titleSingular}`;
});

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
    <div v-if="state.schema && (form || crud.formOpening)" class="pc-overlay" @mousedown.self="tryClose()">
        <div class="pc-modal" role="dialog" aria-modal="true" :aria-labelledby="titleId" :aria-busy="form ? undefined : true">
            <form novalidate @submit.prevent="crud.save()">
                <div class="pc-modal-head">
                    <div class="pc-modal-heading">
                        <h3 :id="titleId" class="pc-modal-title">{{ title }}</h3>
                        <p v-if="form" class="pc-modal-subtitle">{{ formSubtitle(form, t) }}</p>
                    </div>
                    <button type="button" class="pc-icon-btn" :aria-label="t.close" @click="tryClose()">✕</button>
                </div>
                <div ref="body" class="pc-modal-body">
                    <div v-if="!form" class="pc-form-loading" role="status">
                        <span class="pc-form-spinner" aria-hidden="true" />
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
                <div class="pc-modal-foot">
                    <!-- History and attachments of the record being edited, as in the Livewire modal. -->
                    <PtahCrudRecordButtons v-if="form && form.mode === 'edit' && form.id !== null" :crud="crud" :row="{ id: form.id }" :locale="locale" />
                    <span class="pc-spacer" />
                    <button type="button" class="pc-btn" :disabled="form?.saving" @click="tryClose()">{{ t.cancel }}</button>
                    <PtahCrudSaveAndNew v-if="form" :crud="crud" :locale="locale" />
                    <button v-if="form" type="submit" class="pc-btn pc-btn--primary" :disabled="form.saving" :aria-busy="form.saving || undefined">
                        {{ form.saving ? t.saving : submitLabel(form, t) }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div v-if="confirming" class="pc-overlay pc-overlay--top" @mousedown.self="confirming = false">
        <div class="pc-modal pc-modal--sm" role="alertdialog" aria-modal="true" :aria-labelledby="`${titleId}-discard`" :aria-describedby="`${titleId}-discard-text`">
            <div class="pc-modal-body">
                <p :id="`${titleId}-discard`" class="pc-modal-title">{{ t.unsavedTitle }}</p>
                <p :id="`${titleId}-discard-text`" class="pc-modal-subtitle">{{ t.unsavedConfirm }}</p>
            </div>
            <div class="pc-modal-foot">
                <button ref="keepButton" type="button" class="pc-btn" @click="confirming = false">{{ t.unsavedKeep }}</button>
                <button type="button" class="pc-btn pc-btn--danger" @click="crud.closeForm()">{{ t.unsavedDiscard }}</button>
            </div>
        </div>
    </div>
</template>
