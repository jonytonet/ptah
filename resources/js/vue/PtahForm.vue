<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';
import { controlOf, fromInput, inputType, isWide, toInput } from '../core';
import type { CrudController, CrudState, Field, Option, Words } from '../core';
import PtahLookup from './PtahLookup.vue';

const props = defineProps<{ crud: CrudController; state: CrudState; t: Words }>();

const id = (field: Field) => `pc-${props.crud.screen}-${field.field}`;
const error = (field: Field) => props.state.form?.errors[field.field];
const value = (field: Field) => props.state.form?.data[field.field];
const text = (field: Field) => toInput(field, value(field));
const isOn = (field: Field) => [true, 1, '1'].includes(value(field) as never);
const optionsOf = (field: Field): Option[] =>
    controlOf(field) === 'yesno'
        ? [
              { label: props.t.yes, value: '1' },
              { label: props.t.no, value: '0' },
          ]
        : (field.options ?? []);
const target = (event: Event) => event.target as HTMLInputElement;

// A masked field shows the masked text even when the stored value did not
// change (a letter typed into a digits-only mask).
const onText = (field: Field, event: Event) => {
    const element = target(event);
    const next = fromInput(field, element.value);
    props.crud.setField(field.field, next);
    element.value = toInput(field, next);
};

const onKey = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        props.crud.closeForm();
    }
};

onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <div v-if="state.form && state.schema" class="pc-overlay" @mousedown.self="crud.closeForm()">
        <div class="pc-modal" role="dialog" aria-modal="true" :aria-labelledby="`pc-form-title-${crud.screen}`">
            <form novalidate @submit.prevent="crud.save()">
                <div class="pc-modal-head">
                    <h3 :id="`pc-form-title-${crud.screen}`" class="pc-modal-title">
                        {{ state.form.mode === 'create' ? t.newRecord : t.editRecord }} — {{ state.schema.titleSingular }}
                    </h3>
                    <button type="button" class="pc-icon-btn" :aria-label="t.close" @click="crud.closeForm()">✕</button>
                </div>
                <div class="pc-modal-body">
                    <p v-if="state.form.errors._general" class="pc-alert" role="alert">{{ state.form.errors._general }}</p>
                    <div class="pc-form">
                        <div v-for="field in state.schema.form" :key="field.field" :class="['pc-form-field', { 'pc-form-field--wide': isWide(field) }]">
                            <label v-if="controlOf(field) !== 'switch'" class="pc-label" :for="id(field)">
                                {{ field.label }}<span v-if="field.required" class="pc-required" aria-hidden="true"> *</span>
                            </label>

                            <label v-if="controlOf(field) === 'switch'" class="pc-switch">
                                <input :id="id(field)" type="checkbox" :checked="isOn(field)" :disabled="field.readonly" @change="crud.setField(field.field, target($event).checked)" />
                                <span class="pc-switch-track" />
                                <span>{{ field.label }}</span>
                            </label>

                            <textarea
                                v-else-if="controlOf(field) === 'textarea'"
                                :id="id(field)"
                                :class="['pc-input', { 'pc-input--error': error(field) }]"
                                :disabled="field.readonly"
                                :placeholder="field.placeholder ?? undefined"
                                :aria-invalid="error(field) ? true : undefined"
                                :value="text(field)"
                                @input="crud.setField(field.field, target($event).value)"
                            />

                            <select
                                v-else-if="controlOf(field) === 'select' || controlOf(field) === 'yesno'"
                                :id="id(field)"
                                :class="['pc-input', { 'pc-input--error': error(field) }]"
                                :disabled="field.readonly"
                                :aria-invalid="error(field) ? true : undefined"
                                :value="text(field)"
                                @change="crud.setField(field.field, target($event).value)"
                            >
                                <option value="">{{ t.choose }}</option>
                                <option v-for="option in optionsOf(field)" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>

                            <template v-else-if="controlOf(field) === 'file'">
                                <input
                                    :id="id(field)"
                                    type="file"
                                    :class="['pc-input', { 'pc-input--error': error(field) }]"
                                    :accept="field.type === 'image' ? 'image/*' : undefined"
                                    :disabled="field.readonly"
                                    @change="crud.setFile(field.field, target($event).files?.[0] ?? null)"
                                />
                                <p v-if="typeof value(field) === 'string' && value(field) !== ''" class="pc-help">{{ value(field) }}</p>
                            </template>

                            <PtahLookup
                                v-else-if="controlOf(field) === 'lookup'"
                                :id="id(field)"
                                :value="value(field) === null || value(field) === undefined ? '' : String(value(field))"
                                :label="state.form.labels[field.field] ?? ''"
                                :disabled="field.readonly"
                                :invalid="Boolean(error(field))"
                                :t="t"
                                :search="(query: string) => crud.lookup(field.field, query)"
                                @pick="(item) => crud.setField(field.field, item?.value ?? '', item?.label ?? '')"
                            />

                            <input
                                v-else
                                :id="id(field)"
                                :type="inputType(field)"
                                :class="['pc-input', { 'pc-input--error': error(field) }]"
                                :disabled="field.readonly"
                                :placeholder="field.placeholder ?? undefined"
                                :autocomplete="field.type === 'password' ? 'new-password' : undefined"
                                :aria-invalid="error(field) ? true : undefined"
                                :aria-describedby="error(field) ? `${id(field)}-error` : undefined"
                                :value="text(field)"
                                @input="onText(field, $event)"
                            />

                            <p v-if="field.help" class="pc-help">{{ field.help }}</p>
                            <p v-if="error(field)" :id="`${id(field)}-error`" class="pc-error" role="alert">{{ error(field) }}</p>
                        </div>
                    </div>
                </div>
                <div class="pc-modal-foot">
                    <button type="button" class="pc-btn" @click="crud.closeForm()">{{ t.cancel }}</button>
                    <button type="submit" class="pc-btn pc-btn--primary" :disabled="state.form.saving">{{ state.form.saving ? t.saving : t.save }}</button>
                </div>
            </form>
        </div>
    </div>
</template>
