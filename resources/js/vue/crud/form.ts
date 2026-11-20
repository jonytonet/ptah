/**
 * The fields of the `<PtahCrud>` form for Vue, at parity with the Livewire
 * modal (`_modal-form.blade.php`): read-only text, switch, select, dependent
 * searchdropdown, image (file, URL, preview), colour, money, uppercase,
 * masked and plain inputs, and section headings. The decisions live in
 * core/crud/form; this only draws them. Mirrors react/crud/form.tsx.
 */
import { defineComponent, h, onBeforeUnmount, ref, watch } from 'vue';
import type { PropType, VNode } from 'vue';
import { controlOf, fromInput, inputType, isWide, toInput } from '../../core/form';
import type { CrudController, FormState } from '../../core/controller';
import type { Words } from '../../core/i18n';
import type { Field, LookupItem } from '../../core/types';
import {
    capsLabel,
    colorSwatch,
    fillWord,
    isRequired,
    lookupLocked,
    lookupOpensUp,
    lookupPlaceholder,
    moneyDisplay,
    moneyFromTyping,
    normalizeHex,
    placeholderOf,
    readonlyText,
    savedPreview,
    specialOf,
    stepOf,
} from '../../core/crud/form';
import PtahLookup from '../PtahLookup.vue';
import { icon } from '../icons';

/** The searchdropdown of the form (the same component the form draws). */
export const PtahFormLookup = PtahLookup;

/** A section of the form (`colsFormBlock`): a heading across the whole row. */
export const PtahFormSection = defineComponent({
    name: 'PtahFormSection',
    props: { title: { type: String, required: true } },
    setup(props) {
        return () =>
            h('div', { class: 'pc-form-block', role: 'presentation' }, [
                h('span', { class: 'pc-form-block-label' }, props.title),
                h('span', { class: 'pc-form-block-line', 'aria-hidden': 'true' }),
            ]);
    },
});

const target = (event: Event) => event.target as HTMLInputElement;
const star = (required: boolean) => (required ? h('span', { class: 'pc-required', 'aria-hidden': 'true' }, ' *') : null);
/** forge-input's label, or the uppercase one of the custom blocks (`capsLabel`). */
const labelOf = (field: Field, required: boolean, htmlFor?: string) => h('label', { class: capsLabel(field) ? 'pc-label pc-label--caps' : 'pc-label', for: htmlFor }, [field.label, star(required)]);

// ── Image: file, or a URL, with a preview ───────────────────────────────────

const ImageField = defineComponent({
    name: 'PtahFormImage',
    props: {
        crud: { type: Object as PropType<CrudController>, required: true },
        form: { type: Object as PropType<FormState>, required: true },
        field: { type: Object as PropType<Field>, required: true },
        t: { type: Object as PropType<Words>, required: true },
        id: { type: String, required: true },
        invalid: { type: Boolean, default: false },
        describedBy: { type: String, default: undefined },
    },
    emits: ['touch'],
    setup(props, { emit }) {
        const typed = ref<string | null>(null);
        const picked = ref('');
        const broken = ref(false);
        const fileInput = ref<HTMLInputElement | null>(null);
        let reading = 0;

        const file = () => props.form.files[props.field.field] ?? null;

        watch(
            file,
            (chosen) => {
                const mine = ++reading;
                picked.value = '';
                if (!chosen) {
                    return;
                }
                const reader = new FileReader();
                reader.onload = () => {
                    if (mine === reading) {
                        picked.value = typeof reader.result === 'string' ? reader.result : '';
                    }
                };
                reader.readAsDataURL(chosen);
            },
            { immediate: true },
        );
        onBeforeUnmount(() => {
            reading++;
        });

        const preview = () => (file() ? picked.value : (typed.value ?? savedPreview(props.field, props.form)));
        watch(preview, () => {
            broken.value = false;
        });

        const dropFile = () => {
            if (fileInput.value) {
                fileInput.value.value = '';
            }
            props.crud.setFile(props.field.field, null);
        };

        return () => {
            const { form, field, t, id } = props;
            const chosen = file();
            const value = form.data[field.field];
            const url = preview();

            return h('div', { class: 'pc-image' }, [
                h('div', { class: 'pc-image-row' }, [
                    h('label', { class: 'pc-image-pick' }, [
                        icon('upload'),
                        t.imagePickFile,
                        h('input', {
                            ref: fileInput,
                            id: `${id}-file`,
                            type: 'file',
                            class: 'pc-visually-hidden',
                            accept: field.accept ?? 'image/*',
                            'aria-label': `${field.label}: ${t.imagePickFile}`,
                            'aria-describedby': props.describedBy,
                            onChange: (event: Event) => props.crud.setFile(field.field, target(event).files?.[0] ?? null),
                        }),
                    ]),
                    chosen && form.saving ? h('span', { class: 'pc-help pc-image-busy', role: 'status' }, t.imageUploading) : null,
                    chosen
                        ? h(
                              'button',
                              {
                                  type: 'button',
                                  class: 'pc-link pc-image-remove',
                                  onClick: () => {
                                      dropFile();
                                      emit('touch');
                                  },
                              },
                              t.imageRemoveFile,
                          )
                        : null,
                ]),
                h('p', { class: 'pc-help' }, t.imageOrUrl),
                h('input', {
                    id: `${id}-url`,
                    type: 'text',
                    class: ['pc-input', { 'pc-input--error': props.invalid }],
                    placeholder: 'https://...',
                    'aria-invalid': props.invalid || undefined,
                    'aria-describedby': props.describedBy,
                    value: value === null || value === undefined ? '' : String(value),
                    onInput: (event: Event) => {
                        // What is seen is what is saved: a typed URL replaces a chosen file.
                        if (file()) {
                            dropFile();
                        }
                        typed.value = target(event).value;
                        props.crud.setField(field.field, target(event).value);
                    },
                }),
                url && !broken.value
                    ? h('div', { class: 'pc-image-preview' }, [
                          h('img', {
                              src: url,
                              alt: t.imagePreview,
                              onError: () => {
                                  broken.value = true;
                              },
                          }),
                      ])
                    : null,
            ]);
        };
    },
});

// ── The field ───────────────────────────────────────────────────────────────

/** One field of the form, drawn as the Livewire modal draws it. */
export const PtahFormField = defineComponent({
    name: 'PtahFormField',
    props: {
        crud: { type: Object as PropType<CrudController>, required: true },
        form: { type: Object as PropType<FormState>, required: true },
        field: { type: Object as PropType<Field>, required: true },
        t: { type: Object as PropType<Words>, required: true },
    },
    /** `touch`: a change the DOM does not announce (a dropdown pick, a file removed). */
    emits: ['touch'],
    setup(props, { emit }) {
        return () => {
            const { crud, form, field, t } = props;
            const id = `pc-${crud.screen}-${field.field}`;
            const error = form.errors[field.field];
            const value = form.data[field.field];
            const required = isRequired(field, form);
            const control = controlOf(field);
            const special = specialOf(field);
            const errorId = `${id}-error`;
            const common = {
                id,
                class: ['pc-input', { 'pc-input--error': Boolean(error) }],
                'aria-invalid': error ? true : undefined,
                'aria-describedby': error ? errorId : undefined,
                'aria-required': required || undefined,
            };
            const set = (next: unknown, label?: string) => crud.setField(field.field, next, label);
            /** The input shows `shown` even when the stored value did not change (a rejected key). */
            const keep = (event: Event, shown: string) => {
                target(event).value = shown;
            };
            let label: VNode | null = labelOf(field, required, id);
            let input: VNode | VNode[];

            if (field.readonly) {
                // Shown, never written: text, not a disabled input.
                label = h('span', { class: 'pc-label pc-label--caps', id: `${id}-label` }, field.label);
                input = h('div', { class: 'pc-readonly', id, 'aria-labelledby': `${id}-label`, 'data-ptah-readonly': field.field }, readonlyText(field, form, t));
            } else if (control === 'switch') {
                // The label above, the switch alone under it (forge-switch with aria-label), as the Blade.
                input = h('label', { class: 'pc-switch' }, [
                    h('input', {
                        id,
                        type: 'checkbox',
                        checked: [true, 1, '1'].includes(value as never),
                        'aria-describedby': common['aria-describedby'],
                        onChange: (event: Event) => set(target(event).checked),
                    }),
                    h('span', { class: 'pc-switch-track' }),
                ]);
            } else if (control === 'textarea') {
                input = h('textarea', { ...common, placeholder: placeholderOf(field, form, t), value: toInput(field, value), onInput: (event: Event) => set(target(event).value) });
            } else if (control === 'select' || control === 'yesno') {
                const options = control === 'yesno' ? [{ label: t.yes, value: '1' }, { label: t.no, value: '0' }] : (field.options ?? []);
                input = h('select', { ...common, value: toInput(field, value), onChange: (event: Event) => set(target(event).value) }, [
                    // Required: the empty choice is only the placeholder, it cannot be picked back.
                    required ? h('option', { value: '', disabled: true, hidden: true }, t.choose) : h('option', { value: '' }, t.choose),
                    ...options.map((option) => h('option', { key: option.value, value: option.value }, option.label)),
                ]);
            } else if (field.type === 'image') {
                label = labelOf(field, required, `${id}-url`);
                input = h(ImageField, { crud, form, field, t, id, invalid: Boolean(error), describedBy: common['aria-describedby'], onTouch: () => emit('touch') });
            } else if (control === 'file') {
                input = [
                    h('input', { ...common, type: 'file', onChange: (event: Event) => crud.setFile(field.field, target(event).files?.[0] ?? null) }),
                    typeof value === 'string' && value !== '' ? h('p', { class: 'pc-help' }, value) : (null as unknown as VNode),
                ];
            } else if (control === 'lookup') {
                input = h(PtahLookup, {
                    id,
                    value: value === null || value === undefined ? '' : String(value),
                    label: form.labels[field.field] ?? '',
                    disabled: lookupLocked(field, form),
                    placeholder: lookupPlaceholder(field, form, t),
                    opensUp: lookupOpensUp(field),
                    invalid: Boolean(error),
                    describedBy: common['aria-describedby'],
                    t,
                    search: (query: string) => crud.lookup(field.field, query),
                    onPick: (item: LookupItem | null) => {
                        emit('touch');
                        set(item?.value ?? '', item?.label ?? '');
                    },
                });
            } else if (special === 'color') {
                const text = value === null || value === undefined ? '' : String(value);
                input = h('div', { class: 'pc-color' }, [
                    // The picker has its own name: the label points at the hex field.
                    h('input', {
                        type: 'color',
                        class: 'pc-color-pick',
                        value: colorSwatch(text),
                        'aria-label': fillWord(t.colorPick, { field: field.label }),
                        onInput: (event: Event) => set(target(event).value.toLowerCase()),
                    }),
                    h('input', {
                        ...common,
                        class: [...common.class, 'pc-color-hex'],
                        type: 'text',
                        placeholder: '#3b82f6',
                        spellcheck: false,
                        autocomplete: 'off',
                        maxlength: 7,
                        value: text,
                        // Raw while typing (normalising each key would block `#abc`), normalised on leaving.
                        onInput: (event: Event) => set(target(event).value),
                        onBlur: () => {
                            const normal = normalizeHex(text);
                            if (normal !== null && normal !== text) {
                                set(normal);
                            }
                        },
                    }),
                    !required && text !== ''
                        ? h('button', { type: 'button', class: 'pc-color-clear', 'aria-label': t.colorClear, title: t.colorClear, onClick: () => set('') }, [icon('x', 18)])
                        : null,
                ]);
            } else if (special === 'money') {
                input = h('input', {
                    ...common,
                    type: 'text',
                    inputmode: 'numeric',
                    placeholder: 'R$ 0,00',
                    value: moneyDisplay(value, field.emptyNull === true),
                    onFocus: (event: Event) => {
                        const element = target(event);
                        element.setSelectionRange(element.value.length, element.value.length);
                    },
                    onInput: (event: Event) => {
                        const next = moneyFromTyping(target(event).value, field.emptyNull === true);
                        set(next);
                        keep(event, next);
                    },
                });
            } else if (special === 'uppercase') {
                input = h('input', {
                    ...common,
                    type: 'text',
                    style: { textTransform: 'uppercase' },
                    value: toInput(field, value),
                    onInput: (event: Event) => {
                        const next = target(event).value.toUpperCase();
                        set(next);
                        keep(event, next);
                    },
                });
            } else {
                const masked = Boolean(field.maskPatterns?.length);
                input = h('input', {
                    ...common,
                    type: masked || field.mask ? 'text' : inputType(field),
                    step: stepOf(field),
                    placeholder: placeholderOf(field, form, t),
                    inputmode: masked ? (field.inputMode ?? undefined) : undefined,
                    maxlength: masked ? (field.maxLength ?? undefined) : undefined,
                    // A password opens empty and, on an edit, empty means "keep it".
                    autocomplete: field.type === 'password' ? 'new-password' : masked ? 'off' : undefined,
                    value: toInput(field, value),
                    onInput: (event: Event) => {
                        const next = fromInput(field, target(event).value);
                        set(next);
                        if (masked) {
                            keep(event, toInput(field, next));
                        }
                    },
                });
            }

            return h('div', { class: ['pc-form-field', { 'pc-form-field--wide': isWide(field) }] }, [
                label,
                ...(Array.isArray(input) ? input : [input]),
                field.help ? h('p', { class: 'pc-help' }, field.help) : null,
                error ? h('p', { class: 'pc-error', id: errorId, role: 'alert' }, error) : null,
            ]);
        };
    },
});
