<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { debounce } from '../core';
import type { LookupItem, Words } from '../core';

const props = defineProps<{
    id: string;
    value: string;
    label: string;
    disabled?: boolean;
    invalid?: boolean;
    t: Words;
    search: (query: string) => Promise<LookupItem[]>;
}>();
const emit = defineEmits<{ pick: [item: LookupItem | null] }>();

const text = ref(props.label || props.value);
const items = ref<LookupItem[] | null>(null);
const active = ref(-1);
const root = ref<HTMLElement | null>(null);
let seq = 0;

watch(
    () => [props.label, props.value],
    () => {
        text.value = props.label || props.value;
    },
);

const run = debounce((query: string) => {
    const mine = ++seq;
    void props.search(query).then((found) => {
        if (mine === seq) {
            items.value = found;
            active.value = -1;
        }
    });
}, 250);

const pick = (item: LookupItem) => {
    text.value = String(item.label);
    items.value = null;
    emit('pick', item);
};

const onInput = (event: Event) => {
    text.value = (event.target as HTMLInputElement).value;
    if (text.value === '') {
        emit('pick', null);
    }
    run(text.value);
};

const onKey = (event: KeyboardEvent) => {
    if (!items.value) {
        return;
    }
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        active.value = Math.min(items.value.length - 1, active.value + 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active.value = Math.max(0, active.value - 1);
    } else if (event.key === 'Enter' && active.value >= 0) {
        event.preventDefault();
        pick(items.value[active.value]);
    } else if (event.key === 'Escape') {
        event.stopPropagation();
        items.value = null;
    }
};

const onDown = (event: MouseEvent) => {
    if (!root.value?.contains(event.target as Node)) {
        items.value = null;
    }
};

onMounted(() => document.addEventListener('mousedown', onDown));
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDown);
    run.cancel();
});
</script>

<template>
    <div ref="root" class="pc-combo">
        <input
            :id="id"
            type="text"
            role="combobox"
            :aria-expanded="items !== null"
            :aria-controls="`${id}-list`"
            aria-autocomplete="list"
            :aria-invalid="invalid || undefined"
            autocomplete="off"
            :class="['pc-input', { 'pc-input--error': invalid }]"
            :placeholder="t.typeToSearch"
            :disabled="disabled"
            :value="text"
            @focus="run(text === (label || value) ? '' : text)"
            @input="onInput"
            @keydown="onKey"
        />
        <ul v-if="items !== null" :id="`${id}-list`" class="pc-combo-list" role="listbox">
            <li v-if="items.length === 0" class="pc-combo-note">{{ t.noResults }}</li>
            <li
                v-for="(item, index) in items"
                :key="String(item.value)"
                role="option"
                :aria-selected="index === active"
                class="pc-combo-item"
                @mousedown.prevent
                @click="pick(item)"
            >
                {{ item.label }}
            </li>
        </ul>
    </div>
</template>
