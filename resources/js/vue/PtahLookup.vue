<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { debounce } from '../core';
import { isFailedLookup } from '../core/controller';
import { optionLines } from '../core/crud/form';
import type { LookupItem, Words } from '../core';
import { Icon } from './icons';
import { useFloating } from './float';

const props = defineProps<{
    id: string;
    value: string;
    label: string;
    disabled?: boolean;
    invalid?: boolean;
    describedBy?: string;
    placeholder?: string;
    /** The options open above the field (`colsSDStartList: top`). */
    opensUp?: boolean;
    t: Words;
    search: (query: string) => Promise<LookupItem[]>;
}>();
const emit = defineEmits<{ pick: [item: LookupItem | null] }>();

const text = ref(props.label || props.value);
const items = ref<LookupItem[] | null>(null);
const active = ref(-1);
// 1.61.0: on a slow server the list kept the previous answer while the new
// one was on its way, and a failed request read "No results".
const loading = ref(false);
const failed = ref(false);
const root = ref<HTMLElement | null>(null);
// Fixed under the field (1.63.1): the form dialog's body scrolls and hid the list.
const list = ref<HTMLElement | null>(null);
useFloating(root, list, () => items.value !== null, () => props.opensUp === true);
const input = ref<HTMLInputElement | null>(null);
let seq = 0;

const chosen = computed(() => props.label || props.value);
const shown = computed(() => (loading.value ? null : items.value));
const optionId = (index: number) => `${props.id}-opt-${index}`;

watch(
    () => [props.label, props.value],
    () => {
        text.value = props.label || props.value;
    },
);
// Locked again (the parent was cleared): the list closes.
watch(
    () => props.disabled,
    (disabled) => {
        if (disabled) {
            items.value = null;
        }
    },
);
// An error on the field: the list closes so the message under it shows.
watch(
    () => props.invalid,
    (invalid) => {
        if (invalid) {
            items.value = null;
        }
    },
);

const fetchNow = (query: string) => {
    const mine = ++seq;
    loading.value = true;
    if (items.value === null) {
        items.value = [];
    }
    void props.search(query).then((found) => {
        if (mine === seq) {
            failed.value = isFailedLookup(found);
            items.value = found;
            active.value = -1;
            loading.value = false;
        }
    });
};
const run = debounce(fetchNow, 250);

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

const moveTo = (next: number) => {
    active.value = next;
    document.getElementById(optionId(next))?.scrollIntoView?.({ block: 'nearest' });
};

const onKey = (event: KeyboardEvent) => {
    if (event.key === 'ArrowDown' && items.value === null) {
        event.preventDefault();
        fetchNow(text.value === chosen.value ? '' : text.value);

        return;
    }
    const list = shown.value;
    if (!list) {
        return;
    }
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveTo(Math.min(list.length - 1, active.value + 1));
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveTo(Math.max(0, active.value - 1));
    } else if (event.key === 'Enter' && active.value >= 0 && list[active.value]) {
        event.preventDefault();
        pick(list[active.value]);
    } else if (event.key === 'Escape') {
        event.stopPropagation();
        items.value = null;
    }
};

// Leaving the field (Tab) closes the list (1.61.0). A typed search that was
// not picked goes: the chosen item comes back — it looked chosen and was not.
const onFocusOut = (event: FocusEvent) => {
    if (root.value?.contains(event.relatedTarget as Node | null)) {
        return;
    }
    run.cancel();
    seq++;
    items.value = null;
    loading.value = false;
    active.value = -1;
    if (text.value !== '' && text.value !== chosen.value) {
        text.value = chosen.value;
    }
};

// The chevron opens or closes the list; out of the tab order, as in the Blade.
const toggle = () => {
    if (items.value !== null) {
        items.value = null;

        return;
    }
    input.value?.focus();
    fetchNow(text.value === chosen.value ? '' : text.value);
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
    <div ref="root" class="pc-combo" @focusout="onFocusOut">
        <div class="pc-combo-field">
            <input
                :id="id"
                ref="input"
                type="text"
                role="combobox"
                :aria-expanded="items !== null"
                :aria-controls="`${id}-list`"
                aria-autocomplete="list"
                :aria-activedescendant="shown !== null && active >= 0 ? optionId(active) : undefined"
                :aria-invalid="invalid || undefined"
                :aria-describedby="describedBy"
                autocomplete="off"
                :class="['pc-input', 'pc-combo-input', { 'pc-input--error': invalid }]"
                :placeholder="placeholder ?? t.typeToSearch"
                :disabled="disabled"
                :value="text"
                @focus="run(text === chosen ? '' : text)"
                @input="onInput"
                @keydown="onKey"
            />
            <button type="button" tabindex="-1" class="pc-combo-toggle" :aria-label="t.toggleOptions" :aria-expanded="items !== null" :disabled="disabled" @mousedown.prevent="toggle"><Icon name="chevronDown" /></button>
        </div>
        <ul v-if="items !== null" ref="list" :id="`${id}-list`" :class="['pc-combo-list', { 'pc-combo-list--up': opensUp }]" role="listbox" :aria-busy="loading || undefined">
            <li v-if="loading" class="pc-combo-note" role="status">{{ t.lookupLoading }}</li>
            <li v-else-if="failed" class="pc-combo-note pc-combo-note--error" role="alert">{{ t.lookupFailed }}</li>
            <li v-else-if="shown !== null && shown.length === 0" class="pc-combo-note">{{ t.noResults }}</li>
            <li
                v-for="(item, index) in shown ?? []"
                :id="optionId(index)"
                :key="String(item.value)"
                role="option"
                :aria-selected="index === active"
                class="pc-combo-item"
                @mousemove="active = index"
                @mousedown.prevent
                @click="pick(item)"
            >
                {{ item.label }}
                <span v-for="(line, n) in optionLines(item)" :key="n" class="pc-combo-line">{{ line }}</span>
            </li>
        </ul>
    </div>
</template>
