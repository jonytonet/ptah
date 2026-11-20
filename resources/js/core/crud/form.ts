/**
 * Form parity with the Livewire modal (`_modal-form.blade.php`), framework
 * free: the React and the Vue form draw these answers. What a field shows
 * read-only, when a password is required, when a dependent dropdown is
 * locked, where a section heading goes, how a money or colour field formats,
 * when closing must ask first.
 */
import type { CrudController, FormState } from '../controller';
import { controlOf } from '../form';
import type { Words } from '../i18n';
import type { Field, Schema } from '../types';

/** `:name` placeholders of a word, filled. */
export const fillWord = (template: string, values: Record<string, string>) => template.replace(/:(\w+)/g, (whole, name: string) => (name in values ? values[name] : whole));

// ── Modal ───────────────────────────────────────────────────────────────────

/** "Nova conta" / "Editar conta": the server's (gender, host translations), else the words. */
export function formTitle(form: FormState, schema: Schema, t: Words): string {
    if (form.title) {
        return form.title;
    }

    return `${form.mode === 'edit' ? t.editPrefix : t.newPrefix} ${schema.titleSingular}`;
}

export function formSubtitle(form: FormState, t: Words): string {
    return form.subtitle ?? (form.mode === 'edit' ? t.editSubtitle : t.createSubtitle);
}

/** "Create" on a new record, "Save changes" on an edit. */
export function submitLabel(form: FormState, t: Words): string {
    return form.mode === 'edit' ? t.saveChanges : t.create;
}

/**
 * Closing a changed form asks first — unless a draft keeps what was typed
 * (the form draft is on and this opening is not a copy), as the Livewire
 * modal does: with the draft, closing loses nothing.
 */
export function needsDiscardConfirm(crud: CrudController, dirty: boolean): boolean {
    const form = crud.getState().form;

    return dirty && !!form && !(crud.features.formDraft && !form.copy);
}

/** What the modal focuses when it opens (the Livewire `_focusFirst`). */
export const FIRST_FIELD = 'input:not([type=hidden]):not([type=checkbox]):not([type=file]):not([readonly]):not([disabled]), textarea:not([disabled]), select:not([disabled])';

export function focusFirstField(root: ParentNode | null | undefined): void {
    const field = root?.querySelector<HTMLElement>(FIRST_FIELD);
    field?.focus();
}

/** The page behind an open modal does not scroll. Returns the release. */
export function lockBodyScroll(): () => void {
    if (typeof document === 'undefined') {
        return () => undefined;
    }
    const body = document.body;
    const before = body.style.overflow;
    body.style.overflow = 'hidden';

    return () => {
        body.style.overflow = before;
    };
}

// ── Sections ────────────────────────────────────────────────────────────────

export interface FormEntry {
    field: Field;
    /** A section heading drawn before this field (`colsFormBlock` changed). */
    heading: string | null;
}

/** The fields with their section headings: one whenever `block` changes to a non-empty name. */
export function formSections(fields: Field[]): FormEntry[] {
    let previous: string | null = null;

    return fields.map((field) => {
        const block = (field.block ?? '').trim();
        const heading = block !== '' && block !== previous ? block : null;
        previous = block;

        return { field, heading };
    });
}

// ── Per field ───────────────────────────────────────────────────────────────

const isPassword = (field: Field) => field.type === 'password';

/** A password on an edit is not required: empty keeps the current one. */
export function isRequired(field: Field, form: FormState): boolean {
    return isPassword(field) && form.mode === 'edit' ? false : field.required;
}

export function placeholderOf(field: Field, form: FormState, t: Words): string | undefined {
    const own = (field.placeholder ?? '').trim();
    if (own === '' && isPassword(field) && form.mode === 'edit') {
        return t.passwordKeep;
    }
    if (own === '' && field.maskPatterns?.length) {
        return field.maskPlaceholder || undefined;
    }

    return own === '' ? undefined : own;
}

const isEmpty = (value: unknown) => value === null || value === undefined || value === '';

/**
 * A read-only field as text: the option's label, the dropdown's label,
 * Yes/No — never a password. `—` when empty.
 */
export function readonlyText(field: Field, form: FormState, t: Words): string {
    const value = form.data[field.field];
    const raw = typeof value === 'boolean' ? (value ? '1' : '0') : value;
    let text = '';

    if (isPassword(field)) {
        text = '';
    } else if (field.type === 'switch') {
        text = isEmpty(raw) ? '' : [true, 1, '1', 'true'].includes(raw as never) ? t.yes : t.no;
    } else if (field.type === 'searchdropdown') {
        text = form.labels[field.field] ?? (isEmpty(raw) ? '' : String(raw));
    } else if (field.type === 'boolean' && !field.options?.length) {
        text = isEmpty(raw) ? '' : String(raw) === '1' ? t.yes : t.no;
    } else if (field.options?.length) {
        const option = field.options.find((item) => item.value === String(raw ?? ''));
        text = option ? option.label : isEmpty(raw) ? '' : String(raw);
    } else if (raw !== null && raw !== undefined && typeof raw !== 'object') {
        text = String(raw);
    }

    return text !== '' ? text : '—';
}

/** A dependent dropdown waits for its parent: locked while the parent is empty. */
export function lookupLocked(field: Field, form: FormState): boolean {
    const parent = field.lookup?.dependsOn;

    return !!parent && isEmpty(form.data[parent]);
}

export function lookupPlaceholder(field: Field, form: FormState, t: Words): string {
    if (lookupLocked(field, form)) {
        return fillWord(t.selectParentFirst, { parent: field.lookup?.dependsOnLabel || field.lookup?.dependsOn || '' });
    }

    return field.lookup?.placeholder || fillWord(t.searchEntity, { label: field.label });
}

/** The options open above the field (`colsSDStartList: top`). */
export function lookupOpensUp(field: Field): boolean {
    return field.lookup?.startList === 'top';
}

/** The second and third lines of a dropdown option. */
export function optionLines(item: { labelTwo?: unknown; labelThree?: unknown }): string[] {
    return (['labelTwo', 'labelThree'] as const).filter((key) => key in item && item[key] !== undefined).map((key) => String(item[key] ?? ''));
}

/** Special inputs of the Livewire modal: money (BRL), uppercase, colour. */
export type Special = 'money' | 'uppercase' | 'color' | null;

export function specialOf(field: Field): Special {
    if (field.type === 'color') {
        return 'color';
    }
    if (field.mask === 'money_brl') {
        return 'money';
    }
    if (field.mask === 'uppercase') {
        return 'uppercase';
    }

    return null;
}

/** A number without a mask takes any decimal (`step="any"`), as the Blade input. */
export function stepOf(field: Field): string | undefined {
    return field.type === 'number' && !field.mask ? 'any' : undefined;
}

// ── Money (money_brl) ───────────────────────────────────────────────────────

/** `1234.5` → `R$ 1.234,50`. */
export function formatMoney(value: unknown): string {
    const number = parseFloat(String(value ?? '')) || 0;

    return 'R$ ' + number.toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
}

/**
 * What the field shows: a stored number formatted, or what was typed (already
 * formatted). With `blank` (the column takes null: `field.emptyNull`) an
 * empty value stays empty — "not informed" is not R$ 0,00.
 */
export function moneyDisplay(value: unknown, blank = false): string {
    if (blank && (value === null || value === undefined || value === '')) {
        return '';
    }
    if (typeof value === 'string' && /[a-zA-Z$]/.test(value)) {
        return value;
    }

    return formatMoney(value);
}

/**
 * Typing: the digits are cents — `12345` → `R$ 123,45`. This text goes to the
 * server, as in Livewire. With `blank`, erasing every digit leaves the field
 * empty (saved as null) instead of R$ 0,00.
 */
export function moneyFromTyping(text: string, blank = false): string {
    const digits = text.replace(/\D/g, '');
    if (blank && digits === '') {
        return '';
    }

    return formatMoney(parseInt(digits || '0', 10) / 100);
}

// ── Colour ──────────────────────────────────────────────────────────────────

/** `abc` → `#aabbcc`; null when it is not a colour. */
export function normalizeHex(value: unknown): string | null {
    let text = String(value ?? '').trim();
    if (text === '') {
        return null;
    }
    if (text[0] !== '#') {
        text = '#' + text;
    }
    if (/^#[0-9a-fA-F]{3}$/.test(text)) {
        text = '#' + text[1] + text[1] + text[2] + text[2] + text[3] + text[3];
    }

    return /^#[0-9a-fA-F]{6}$/.test(text) ? text.toLowerCase() : null;
}

/** The native picker needs a valid colour: a neutral grey when empty, never written. */
export function colorSwatch(value: unknown): string {
    return normalizeHex(value) ?? '#9ca3af';
}

// ── Image ───────────────────────────────────────────────────────────────────

/** The stored image's URL: the server's preview, or the value itself when it is a URL. */
export function savedPreview(field: Field, form: FormState): string {
    const own = form.previews?.[field.field];
    if (own) {
        return own;
    }
    const value = form.data[field.field];

    return typeof value === 'string' && /^(https?:\/\/|data:image\/|\/)/i.test(value) ? value : '';
}

// ── Look (shared by the React and Vue modals) ───────────────────────────────

/** A forge-button of the modals: solid primary/danger, or flat dark/primary/danger. */
export type ModalButton = 'primary' | 'danger' | 'flat' | 'flat-primary' | 'flat-danger';

/** The classes of a modal button (`pc-fm-btn`), `small` for forge-button size="sm". */
export function modalButton(kind: ModalButton = 'primary', small = false): string {
    return `pc-fm-btn${kind === 'primary' ? '' : ` pc-fm-btn--${kind}`}${small ? ' pc-fm-btn--sm' : ''}`;
}

/**
 * The label of a field as the Livewire modal draws it: a plain input is a
 * forge-input (sentence-case label); every custom block (select, lookup,
 * switch, masked, money, colour, image, read-only…) has the uppercase one.
 */
export function capsLabel(field: Field): boolean {
    const control = controlOf(field);
    if (field.readonly || field.type === 'image' || ['switch', 'select', 'yesno', 'lookup', 'file'].includes(control)) {
        return true;
    }

    return specialOf(field) !== null || Boolean(field.maskPatterns?.length);
}
