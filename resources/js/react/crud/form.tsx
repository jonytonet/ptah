/**
 * The fields of the `<PtahCrud>` form, at parity with the Livewire modal
 * (`_modal-form.blade.php`): read-only text, switch, select, dependent
 * searchdropdown, image (file, URL, preview), colour, money, uppercase,
 * masked and plain inputs, section headings and the "discard changes?"
 * confirmation. The decisions live in core/crud/form; this only draws them.
 * Mirrors vue/crud/form.ts.
 */
import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import type { HTMLAttributes, ReactNode } from 'react';
import { controlOf, debounce, fromInput, inputType, isWide, toInput } from '../../core/form';
import { isFailedLookup } from '../../core/controller';
import { trapFocus } from '../../core/focus';
import type { CrudController, CrudState, FormState } from '../../core/controller';
import type { Words } from '../../core/i18n';
import type { Field, LookupItem } from '../../core/types';
import {
    capsLabel,
    colorSwatch,
    focusFirstField,
    formSections,
    formSubtitle,
    formTitle,
    lockBodyScroll,
    modalButton,
    needsDiscardConfirm,
    submitLabel,
    fillWord,
    isRequired,
    lookupLocked,
    lookupOpensUp,
    lookupPlaceholder,
    moneyDisplay,
    moneyFromTyping,
    normalizeHex,
    optionLines,
    placeholderOf,
    readonlyText,
    savedPreview,
    specialOf,
    stepOf,
} from '../../core/crud/form';
import { Icon } from '../icons';
import { PtahCrudFormChanges, PtahCrudFormDraft, PtahCrudSaveAndNew } from './extras';
import { PtahCrudRecordButtons } from './records';

export { PtahConfirmDialog, PtahCrudModal, PtahModalAlert, PtahModalEmpty } from './modal';
export type { PtahConfirmDialogProps, PtahCrudModalProps } from './modal';

export interface FormFieldProps {
    crud: CrudController;
    form: FormState;
    field: Field;
    t: Words;
    /** A change the DOM does not announce (a dropdown pick, a file removed): the form is dirty. */
    onTouch?: () => void;
}

/** A section of the form (`colsFormBlock`): a heading across the whole row. */
export function PtahFormSection({ title }: { title: string }) {
    return (
        <div className="pc-form-block" role="presentation">
            <span className="pc-form-block-label">{title}</span>
            <span className="pc-form-block-line" aria-hidden="true" />
        </div>
    );
}

/** forge-input's label, or the uppercase one of the custom blocks (`capsLabel`). */
function Label({ htmlFor, field, required }: { htmlFor?: string; field: Field; required: boolean }) {
    return (
        <label className={capsLabel(field) ? 'pc-label pc-label--caps' : 'pc-label'} htmlFor={htmlFor}>
            {field.label}
            {required && (
                <span className="pc-required" aria-hidden="true">
                    {' '}
                    *
                </span>
            )}
        </label>
    );
}

/** One field of the form, drawn as the Livewire modal draws it. */
export function PtahFormField({ crud, form, field, t, onTouch }: FormFieldProps) {
    const id = `pc-${crud.screen}-${field.field}`;
    const error = form.errors[field.field];
    const value = form.data[field.field];
    const required = isRequired(field, form);
    const control = controlOf(field);
    const special = specialOf(field);
    const errorId = `${id}-error`;
    const common = {
        id,
        className: `pc-input${error ? ' pc-input--error' : ''}`,
        'aria-invalid': error ? true : undefined,
        'aria-describedby': error ? errorId : undefined,
        'aria-required': required || undefined,
    };
    const set = (next: unknown, label?: string) => crud.setField(field.field, next, label);
    let input: ReactNode;
    let label: ReactNode = <Label htmlFor={id} field={field} required={required} />;

    if (field.readonly) {
        // Shown, never written: text, not a disabled input.
        label = (
            <span className="pc-label pc-label--caps" id={`${id}-label`}>
                {field.label}
            </span>
        );
        input = (
            <div className="pc-readonly" id={id} aria-labelledby={`${id}-label`} data-ptah-readonly={field.field}>
                {readonlyText(field, form, t)}
            </div>
        );
    } else if (control === 'switch') {
        // The label above, the switch alone under it (forge-switch with aria-label), as the Blade.
        input = (
            <label className="pc-switch">
                <input id={id} type="checkbox" checked={[true, 1, '1'].includes(value as never)} aria-describedby={common['aria-describedby']} onChange={(event) => set(event.target.checked)} />
                <span className="pc-switch-track" />
            </label>
        );
    } else if (control === 'textarea') {
        input = <textarea {...common} placeholder={placeholderOf(field, form, t)} value={toInput(field, value)} onChange={(event) => set(event.target.value)} />;
    } else if (control === 'select' || control === 'yesno') {
        const options = control === 'yesno' ? [{ label: t.yes, value: '1' }, { label: t.no, value: '0' }] : (field.options ?? []);
        input = (
            <select {...common} value={toInput(field, value)} onChange={(event) => set(event.target.value)}>
                {/* Required: the empty choice is only the placeholder, it cannot be picked back. */}
                {required ? (
                    <option value="" disabled hidden>
                        {t.choose}
                    </option>
                ) : (
                    <option value="">{t.choose}</option>
                )}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        );
    } else if (field.type === 'image') {
        label = <Label htmlFor={`${id}-url`} field={field} required={required} />;
        input = <ImageField crud={crud} form={form} field={field} t={t} id={id} invalid={Boolean(error)} describedBy={common['aria-describedby']} onTouch={onTouch} />;
    } else if (control === 'file') {
        input = (
            <>
                <input {...common} type="file" onChange={(event) => crud.setFile(field.field, event.target.files?.[0] ?? null)} />
                {typeof value === 'string' && value !== '' && <p className="pc-help">{value}</p>}
            </>
        );
    } else if (control === 'lookup') {
        input = (
            <PtahFormLookup
                id={id}
                value={value === null || value === undefined ? '' : String(value)}
                label={form.labels[field.field] ?? ''}
                disabled={lookupLocked(field, form)}
                placeholder={lookupPlaceholder(field, form, t)}
                opensUp={lookupOpensUp(field)}
                invalid={Boolean(error)}
                describedBy={common['aria-describedby']}
                t={t}
                search={(query) => crud.lookup(field.field, query)}
                onPick={(item) => {
                    onTouch?.();
                    set(item?.value ?? '', item?.label ?? '');
                }}
            />
        );
    } else if (special === 'color') {
        input = <ColorField {...common} field={field} value={value} required={required} t={t} onValue={(next) => set(next)} />;
    } else if (special === 'money') {
        input = (
            <input
                {...common}
                type="text"
                inputMode="numeric"
                placeholder="R$ 0,00"
                value={moneyDisplay(value, field.emptyNull === true)}
                onFocus={(event) => event.target.setSelectionRange(event.target.value.length, event.target.value.length)}
                onChange={(event) => set(moneyFromTyping(event.target.value, field.emptyNull === true))}
            />
        );
    } else if (special === 'uppercase') {
        input = <input {...common} type="text" style={{ textTransform: 'uppercase' }} value={toInput(field, value)} onChange={(event) => set(event.target.value.toUpperCase())} />;
    } else {
        const masked = Boolean(field.maskPatterns?.length);
        input = (
            <input
                {...common}
                type={masked || field.mask ? 'text' : inputType(field)}
                step={stepOf(field)}
                placeholder={placeholderOf(field, form, t)}
                inputMode={masked ? ((field.inputMode ?? undefined) as HTMLAttributes<HTMLInputElement>['inputMode']) : undefined}
                maxLength={masked ? (field.maxLength ?? undefined) : undefined}
                // A password opens empty and, on an edit, empty means "keep it".
                autoComplete={field.type === 'password' ? 'new-password' : masked ? 'off' : undefined}
                value={toInput(field, value)}
                onChange={(event) => set(fromInput(field, event.target.value))}
            />
        );
    }

    return (
        <div className={`pc-form-field${isWide(field) ? ' pc-form-field--wide' : ''}`}>
            {label}
            {input}
            {field.help && <p className="pc-help">{field.help}</p>}
            {error && (
                <p className="pc-error" id={errorId} role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}

// ── Colour: native picker + editable hex ────────────────────────────────────

interface ColorProps {
    id: string;
    className: string;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
    'aria-required'?: boolean;
    field: Field;
    value: unknown;
    required: boolean;
    t: Words;
    onValue: (value: string) => void;
}

function ColorField({ id, className, field, value, required, t, onValue, ...aria }: ColorProps) {
    const text = value === null || value === undefined ? '' : String(value);

    return (
        <div className="pc-color">
            {/* The picker has its own name: the label points at the hex field. */}
            <input type="color" className="pc-color-pick" value={colorSwatch(text)} aria-label={fillWord(t.colorPick, { field: field.label })} onChange={(event) => onValue(event.target.value.toLowerCase())} />
            <input
                id={id}
                type="text"
                className={`${className} pc-color-hex`}
                placeholder="#3b82f6"
                spellCheck={false}
                autoComplete="off"
                maxLength={7}
                aria-invalid={aria['aria-invalid']}
                aria-describedby={aria['aria-describedby']}
                aria-required={required || undefined}
                value={text}
                // Raw while typing (normalising each key would block `#abc`), normalised on leaving.
                onChange={(event) => onValue(event.target.value)}
                onBlur={() => {
                    const normal = normalizeHex(text);
                    if (normal !== null && normal !== text) {
                        onValue(normal);
                    }
                }}
            />
            {!required && text !== '' && (
                <button type="button" className="pc-color-clear" aria-label={t.colorClear} title={t.colorClear} onClick={() => onValue('')}>
                    <Icon name="x" size={18} />
                </button>
            )}
        </div>
    );
}

// ── Image: file, or a URL, with a preview ───────────────────────────────────

interface ImageProps {
    crud: CrudController;
    form: FormState;
    field: Field;
    t: Words;
    id: string;
    invalid: boolean;
    describedBy?: string;
    onTouch?: () => void;
}

function ImageField({ crud, form, field, t, id, invalid, describedBy, onTouch }: ImageProps) {
    const file = form.files[field.field] ?? null;
    const value = form.data[field.field];
    const saved = savedPreview(field, form);
    const [typed, setTyped] = useState<string | null>(null);
    const [picked, setPicked] = useState('');
    const [broken, setBroken] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);

    // The chosen file shown before it is sent.
    useEffect(() => {
        if (!file) {
            setPicked('');

            return;
        }
        let live = true;
        const reader = new FileReader();
        reader.onload = () => live && setPicked(typeof reader.result === 'string' ? reader.result : '');
        reader.readAsDataURL(file);

        return () => {
            live = false;
        };
    }, [file]);

    const preview = file ? picked : (typed ?? saved);
    useEffect(() => setBroken(false), [preview]);

    return (
        <div className="pc-image">
            <div className="pc-image-row">
                <label className="pc-image-pick">
                    <Icon name="upload" />
                    {t.imagePickFile}
                    <input
                        ref={fileInput}
                        id={`${id}-file`}
                        type="file"
                        className="pc-visually-hidden"
                        accept={field.accept ?? 'image/*'}
                        aria-label={`${field.label}: ${t.imagePickFile}`}
                        aria-describedby={describedBy}
                        onChange={(event) => crud.setFile(field.field, event.target.files?.[0] ?? null)}
                    />
                </label>
                {file && form.saving && (
                    <span className="pc-help pc-image-busy" role="status">
                        {t.imageUploading}
                    </span>
                )}
                {file && (
                    <button
                        type="button"
                        className="pc-link pc-image-remove"
                        onClick={() => {
                            if (fileInput.current) {
                                fileInput.current.value = '';
                            }
                            crud.setFile(field.field, null);
                            onTouch?.();
                        }}
                    >
                        {t.imageRemoveFile}
                    </button>
                )}
            </div>
            <p className="pc-help">{t.imageOrUrl}</p>
            <input
                id={`${id}-url`}
                type="text"
                className={`pc-input${invalid ? ' pc-input--error' : ''}`}
                placeholder="https://..."
                aria-invalid={invalid || undefined}
                aria-describedby={describedBy}
                value={typeof value === 'string' ? value : value === null || value === undefined ? '' : String(value)}
                onChange={(event) => {
                    // What is seen is what is saved: a typed URL replaces a chosen file.
                    if (file) {
                        if (fileInput.current) {
                            fileInput.current.value = '';
                        }
                        crud.setFile(field.field, null);
                    }
                    setTyped(event.target.value);
                    crud.setField(field.field, event.target.value);
                }}
            />
            {preview && !broken && (
                <div className="pc-image-preview">
                    <img src={preview} alt={t.imagePreview} onError={() => setBroken(true)} />
                </div>
            )}
        </div>
    );
}

// ── Lookup (searchdropdown) ─────────────────────────────────────────────────

export interface LookupProps {
    id: string;
    value: string;
    label: string;
    disabled?: boolean;
    invalid?: boolean;
    describedBy?: string;
    placeholder?: string;
    /** The options open above the field. */
    opensUp?: boolean;
    t: Words;
    search: (query: string) => Promise<LookupItem[]>;
    onPick: (item: LookupItem | null) => void;
}

export function PtahFormLookup({ id, value, label, disabled, invalid, describedBy, placeholder, opensUp, t, search, onPick }: LookupProps) {
    const [text, setText] = useState(label || value);
    const [items, setItems] = useState<LookupItem[] | null>(null);
    const [active, setActive] = useState(-1);
    // 1.61.0: on a slow server the list kept the previous answer while the new
    // one was on its way, and a failed request read "No results".
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const seq = useRef(0);
    const ref = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const chosen = label || value;

    useEffect(() => setText(label || value), [label, value]);
    // Locked again (the parent was cleared): the list closes.
    useEffect(() => {
        if (disabled) {
            setItems(null);
        }
    }, [disabled]);
    // An error on the field: the list closes so the message under it shows.
    useEffect(() => {
        if (invalid) {
            setItems(null);
        }
    }, [invalid]);

    const fetchNow = (query: string) => {
        const mine = ++seq.current;
        setLoading(true);
        setItems((current) => current ?? []);
        void search(query).then((found) => {
            if (mine === seq.current) {
                setFailed(isFailedLookup(found));
                setItems(found);
                setActive(-1);
                setLoading(false);
            }
        });
    };
    const run = useMemo(() => debounce((query: string) => fetchNow(query), 250), [search]); // eslint-disable-line react-hooks/exhaustive-deps
    useEffect(() => () => run.cancel(), [run]);

    useEffect(() => {
        const close = (event: MouseEvent) => {
            if (!ref.current?.contains(event.target as Node)) {
                setItems(null);
            }
        };
        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    const pick = (item: LookupItem) => {
        setText(String(item.label));
        setItems(null);
        onPick(item);
    };
    const optionId = (index: number) => `${id}-opt-${index}`;
    const shown = loading ? null : items;
    const moveTo = (next: number) => {
        setActive(next);
        document.getElementById(optionId(next))?.scrollIntoView?.({ block: 'nearest' });
    };

    return (
        <div
            className="pc-combo"
            ref={ref}
            onBlur={(event) => {
                // Leaving the field (Tab) closes the list (1.61.0). A typed search
                // that was not picked goes: the chosen item comes back — the text
                // looked chosen and was not.
                if (ref.current?.contains(event.relatedTarget as Node | null)) {
                    return;
                }
                run.cancel();
                seq.current++;
                setItems(null);
                setLoading(false);
                setActive(-1);
                if (text !== '' && text !== chosen) {
                    setText(chosen);
                }
            }}
        >
            <div className="pc-combo-field">
                <input
                    ref={inputRef}
                    id={id}
                    type="text"
                    role="combobox"
                    aria-expanded={items !== null}
                    aria-controls={`${id}-list`}
                    aria-autocomplete="list"
                    aria-activedescendant={shown !== null && active >= 0 ? optionId(active) : undefined}
                    aria-invalid={invalid || undefined}
                    aria-describedby={describedBy}
                    autoComplete="off"
                    className={`pc-input pc-combo-input${invalid ? ' pc-input--error' : ''}`}
                    placeholder={placeholder ?? t.typeToSearch}
                    disabled={disabled}
                    value={text}
                    onFocus={() => run(text === chosen ? '' : text)}
                    onChange={(event) => {
                        setText(event.target.value);
                        if (event.target.value === '') {
                            onPick(null);
                        }
                        run(event.target.value);
                    }}
                    onKeyDown={(event) => {
                        if (event.key === 'ArrowDown' && items === null) {
                            event.preventDefault();
                            fetchNow(text === chosen ? '' : text);

                            return;
                        }
                        if (!shown) {
                            return;
                        }
                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            moveTo(Math.min(shown.length - 1, active + 1));
                        } else if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            moveTo(Math.max(0, active - 1));
                        } else if (event.key === 'Enter' && active >= 0 && shown[active]) {
                            event.preventDefault();
                            pick(shown[active]);
                        } else if (event.key === 'Escape') {
                            event.stopPropagation();
                            setItems(null);
                        }
                    }}
                />
                {/* The chevron opens or closes the list; out of the tab order, as in the Blade. */}
                <button
                    type="button"
                    tabIndex={-1}
                    className="pc-combo-toggle"
                    aria-label={t.toggleOptions}
                    aria-expanded={items !== null}
                    disabled={disabled}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        if (items !== null) {
                            setItems(null);
                        } else {
                            inputRef.current?.focus();
                            fetchNow(text === chosen ? '' : text);
                        }
                    }}
                >
                    <Icon name="chevronDown" />
                </button>
            </div>
            {items !== null && (
                <ul className={`pc-combo-list${opensUp ? ' pc-combo-list--up' : ''}`} id={`${id}-list`} role="listbox" aria-busy={loading || undefined}>
                    {loading && (
                        <li className="pc-combo-note" role="status">
                            {t.lookupLoading}
                        </li>
                    )}
                    {!loading && failed && (
                        <li className="pc-combo-note pc-combo-note--error" role="alert">
                            {t.lookupFailed}
                        </li>
                    )}
                    {shown !== null && !failed && shown.length === 0 && <li className="pc-combo-note">{t.noResults}</li>}
                    {(shown ?? []).map((item, index) => (
                        <li
                            key={String(item.value)}
                            id={optionId(index)}
                            role="option"
                            aria-selected={index === active}
                            className="pc-combo-item"
                            onMouseMove={() => setActive(index)}
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => pick(item)}
                        >
                            {String(item.label)}
                            {optionLines(item).map((line, n) => (
                                <span key={n} className="pc-combo-line">
                                    {line}
                                </span>
                            ))}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

// ── The create/edit modal (_modal-form) ─────────────────────────────────────

export interface PtahCrudFormModalProps {
    crud: CrudController;
    state: CrudState;
    t: Words;
    locale?: string;
}

/**
 * The create/edit modal, as the Livewire one (forge-modal size 2xl): title by
 * gender and subtitle, sections, one column of fields, focus on the first
 * field, the page locked behind it, "discard changes?" before closing a
 * changed form (Cancel, ✕, Escape, a click outside), a loading state while
 * the form is on its way — mounted with `state.form || crud.formOpening` —
 * and the footer of the Blade: Anexos (n), Histórico, Cancelar, Salvar e
 * criar outro, Criar / Salvar alterações. Mirrors vue/PtahForm.vue.
 */
export function PtahCrudFormModal({ crud, state, t, locale }: PtahCrudFormModalProps) {
    const schema = state.schema!;
    const form = state.form;
    const titleId = `pc-form-title-${crud.screen}`;
    const [dirty, setDirty] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const body = useRef<HTMLDivElement>(null);
    const panel = useRef<HTMLDivElement>(null);
    const mini = useRef<HTMLDivElement>(null);
    const instance = form?.instance ?? 0;

    // Tab stays in the modal and the focus goes back on close (1.61.0); the
    // discard question traps it too while it is open.
    useEffect(() => (panel.current ? trapFocus(panel.current) : undefined), []);
    useEffect(() => (confirming && mini.current ? trapFocus(mini.current) : undefined), [confirming]);

    // A save that came back with errors: to the first one (1.61.0). In a long
    // form it was out of sight and nothing seemed to happen.
    const errorKeys = form ? Object.keys(form.errors).join('|') : '';
    useEffect(() => {
        if (errorKeys === '' || !body.current) {
            return;
        }
        const target = body.current.querySelector<HTMLElement>('[aria-invalid="true"]') ?? body.current.querySelector<HTMLElement>('[role="alert"]');
        target?.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
        if (target && target.matches('input, select, textarea, [tabindex]')) {
            target.focus({ preventScroll: true });
        }
    }, [errorKeys]);

    // A new opening (also "save & new", a draft reverted) starts clean and focused.
    useEffect(() => {
        setDirty(false);
        setConfirming(false);
        if (form) {
            focusFirstField(body.current);
        }
    }, [instance, form === null]); // eslint-disable-line react-hooks/exhaustive-deps
    useEffect(() => lockBodyScroll(), []);

    const tryClose = () => {
        if (needsDiscardConfirm(crud, dirty)) {
            setConfirming(true);
        } else {
            crud.closeForm();
        }
    };
    // The confirmation takes the Escape first; a panel over the form (history, attachments) too.
    const latest = useRef({ tryClose, confirming });
    latest.current = { tryClose, confirming };
    useEffect(() => {
        const listen = (event: KeyboardEvent) => {
            // Ctrl+Enter (or Cmd+Enter) saves (1.61.0), as on the Livewire modal.
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey) && !latest.current.confirming) {
                event.preventDefault();
                void crud.save();

                return;
            }
            if (event.key !== 'Escape') {
                return;
            }
            if (latest.current.confirming) {
                setConfirming(false);
            } else if (!crud.getState().panel) {
                latest.current.tryClose();
            }
        };
        document.addEventListener('keydown', listen);

        return () => document.removeEventListener('keydown', listen);
    }, [crud]);

    const editing = form ? form.mode === 'edit' : crud.formOpening === 'edit';
    const title = form ? formTitle(form, schema, t) : `${editing ? t.editPrefix : t.newPrefix} ${schema.titleSingular}`;

    return (
        <>
            <div className="pc-root pc-fm-back" onMouseDown={(event) => event.target === event.currentTarget && tryClose()}>
                <div ref={panel} className="pc-fm-panel pc-fm" data-size="2xl" role="dialog" aria-modal="true" aria-labelledby={titleId} aria-busy={form ? undefined : true}>
                    <form
                        noValidate
                        onSubmit={(event) => {
                            event.preventDefault();
                            void crud.save();
                        }}
                    >
                        <div className="pc-fm-head">
                            <div>
                                <h3 className="pc-fm-title" id={titleId}>
                                    {title}
                                </h3>
                                <p className="pc-fm-sub">{form ? formSubtitle(form, t) : editing ? t.editSubtitle : t.createSubtitle}</p>
                            </div>
                            <button type="button" className="pc-fm-close" aria-label={t.close} title={t.close} onClick={tryClose}>
                                <Icon name="x" size={20} />
                            </button>
                        </div>
                        <div className="pc-fm-body" ref={body}>
                            {!form ? (
                                <div className="pc-fm-loading" role="status">
                                    <span className="pc-fm-spin" aria-hidden="true" />
                                    {t.formLoading}
                                </div>
                            ) : (
                                <>
                                    {form.errors._general && (
                                        <p className="pc-alert" role="alert">
                                            {form.errors._general}
                                        </p>
                                    )}
                                    {form.slow && (
                                        <p className="pc-alert" role="alert">
                                            {t.saveSlow}
                                        </p>
                                    )}
                                    <PtahCrudFormDraft crud={crud} locale={locale} />
                                    <PtahCrudFormChanges crud={crud} />
                                    {/* Typing anywhere in the fields makes the form dirty, as the Blade's @input/@change. */}
                                    <div className="pc-form" onInput={() => setDirty(true)} onChange={() => setDirty(true)}>
                                        {formSections(schema.form).map(({ field, heading }) => (
                                            <Fragment key={field.field}>
                                                {heading !== null && <PtahFormSection title={heading} />}
                                                <PtahFormField field={field} crud={crud} form={form} t={t} onTouch={() => setDirty(true)} />
                                            </Fragment>
                                        ))}
                                    </div>
                                </>
                            )}
                        </div>
                        <div className="pc-fm-foot">
                            {/* Anexos (n) and Histórico of the record being edited; then the rest pushed right. */}
                            {form && form.mode === 'edit' && form.id !== null && <PtahCrudRecordButtons crud={crud} row={{ id: form.id }} locale={locale} />}
                            <span className="pc-fm-push" aria-hidden="true" />
                            <button type="button" className={modalButton('flat')} disabled={form?.saving} onClick={tryClose}>
                                {t.cancel}
                            </button>
                            {form && <PtahCrudSaveAndNew crud={crud} locale={locale} />}
                            {form && (
                                <button type="submit" className={modalButton('primary')} disabled={form.saving} aria-busy={form.saving || undefined}>
                                    {form.saving && <span className="pc-fm-spin" aria-hidden="true" />}
                                    {form.saving ? t.saving : submitLabel(form, t)}
                                </button>
                            )}
                        </div>
                    </form>
                </div>
            </div>
            {confirming && (
                <div className="pc-root pc-fm-back pc-fm-back--top" onMouseDown={(event) => event.target === event.currentTarget && setConfirming(false)}>
                    <div ref={mini} className="pc-fm-mini" role="alertdialog" aria-modal="true" aria-labelledby={`${titleId}-discard`} aria-describedby={`${titleId}-discard-text`}>
                        <p className="pc-fm-mini-ttl" id={`${titleId}-discard`}>
                            {t.unsavedTitle}
                        </p>
                        <p className="pc-fm-mini-sub" id={`${titleId}-discard-text`}>
                            {t.unsavedConfirm}
                        </p>
                        <div className="pc-fm-mini-foot">
                            <button type="button" className={modalButton('flat')} autoFocus onClick={() => setConfirming(false)}>
                                {t.unsavedKeep}
                            </button>
                            <button type="button" className={modalButton('danger')} onClick={() => crud.closeForm()}>
                                {t.unsavedDiscard}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
