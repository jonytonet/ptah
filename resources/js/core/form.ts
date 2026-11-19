import { applyMask } from './mask';
import type { Field } from './types';

/** How a field is drawn — one answer for the React and the Vue form. */
export type Control = 'input' | 'textarea' | 'select' | 'yesno' | 'switch' | 'file' | 'lookup';

export function controlOf(field: Pick<Field, 'type' | 'options'>): Control {
    switch (field.type) {
        case 'textarea':
            return 'textarea';
        case 'select':
            return 'select';
        case 'boolean':
            return 'yesno';
        case 'switch':
            return 'switch';
        case 'image':
        case 'file':
            return 'file';
        case 'searchdropdown':
            return 'lookup';
        default:
            return 'input';
    }
}

const INPUT_TYPES: Record<string, string> = {
    number: 'number',
    date: 'date',
    datetime: 'datetime-local',
    'datetime-local': 'datetime-local',
    time: 'time',
    color: 'color',
    range: 'range',
    email: 'email',
    url: 'url',
    tel: 'tel',
    password: 'password',
};

export function inputType(field: Pick<Field, 'type'>): string {
    return INPUT_TYPES[field.type] ?? 'text';
}

/** A textarea, a file and an image take the whole row of the form. */
export function isWide(field: Pick<Field, 'type'>): boolean {
    return ['textarea', 'image', 'file'].includes(field.type);
}

/**
 * The stored value as the input wants it: a datetime `2026-11-18 14:30:00`
 * becomes `2026-11-18T14:30`, a date keeps its first ten characters, a masked
 * field is shown masked.
 */
export function toInput(field: Pick<Field, 'type' | 'maskPatterns'>, value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'boolean') {
        return value ? '1' : '0';
    }

    const text = String(value);
    const type = inputType(field);

    if (type === 'datetime-local') {
        return text.replace(' ', 'T').slice(0, 16);
    }
    if (type === 'date') {
        return text.slice(0, 10);
    }
    if (type === 'time') {
        return text.slice(0, 5);
    }
    if (type === 'text' && field.maskPatterns?.length) {
        return applyMask(field.maskPatterns, text);
    }

    return text;
}

/** What the user typed, as it goes to the server. */
export function fromInput(field: Pick<Field, 'type' | 'maskPatterns'>, text: string): unknown {
    if (inputType(field) === 'text' && field.maskPatterns?.length) {
        return applyMask(field.maskPatterns, text);
    }

    return text;
}

/** A debounced call: the last one wins. `cancel()` drops a pending call. */
export function debounce<A extends unknown[]>(fn: (...args: A) => void, ms: number): ((...args: A) => void) & { cancel(): void } {
    let timer: ReturnType<typeof setTimeout> | undefined;

    const call = (...args: A) => {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(() => fn(...args), ms);
    };
    call.cancel = () => {
        if (timer) {
            clearTimeout(timer);
        }
    };

    return call;
}

/** The page numbers around the current one, e.g. `[1, 2, 3]`. */
export function pagesAround(page: number, last: number, span = 2): number[] {
    const out: number[] = [];
    for (let n = Math.max(1, page - span); n <= Math.min(last, page + span); n++) {
        out.push(n);
    }

    return out;
}
