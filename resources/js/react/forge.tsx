/**
 * The forge kit for React — the counterparts of ptah's `<x-forge-*>` Blade
 * components, with the same prop names. Styles: ptah-ui.css (`.pf-*`).
 */
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode, TextareaHTMLAttributes } from 'react';
import { pagesAround } from '../core';
import { trapFocus } from '../core/focus';
import { forgeWords } from '../core/forge-words';
import { Icon } from './icons';
import { useFloating } from './float';

export type ForgeColor = 'primary' | 'success' | 'danger' | 'warn' | 'dark' | 'light';
export type ForgeSize = 'sm' | 'md' | 'lg';

const cx = (...names: (string | false | null | undefined)[]) => names.filter(Boolean).join(' ');

// ── Button, spinner ─────────────────────────────────────────────────────────

export interface ForgeButtonProps extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'color'> {
    color?: ForgeColor;
    size?: ForgeSize;
    flat?: boolean;
    relief?: boolean;
    rounded?: boolean;
    loading?: boolean;
    /** Renders a link that looks like a button. */
    href?: string;
}

export function ForgeButton({ color = 'primary', size = 'md', flat, relief, rounded, loading, href, disabled, className, children, type = 'button', ...rest }: ForgeButtonProps) {
    const classes = cx('pf pf-button', flat && 'pf-button--flat', relief && 'pf-button--relief', rounded && 'pf-button--rounded', className);
    const content = (
        <>
            {loading && <span className="pf-spinner" aria-hidden="true" />}
            {children}
        </>
    );

    if (href !== undefined && !disabled) {
        return (
            <a className={classes} data-color={color} data-size={size} href={href}>
                {content}
            </a>
        );
    }

    return (
        <button {...rest} type={type} className={classes} data-color={color} data-size={size} disabled={disabled || loading} aria-busy={loading || undefined}>
            {content}
        </button>
    );
}

export function ForgeSpinner({ color = 'primary', size = 'md', type = 'circle', label = 'Loading' }: { color?: ForgeColor; size?: ForgeSize; type?: 'circle' | 'dots'; label?: string }) {
    return type === 'dots' ? (
        <span className="pf pf-spinner pf-spinner--dots" data-color={color} role="status" aria-label={label}>
            <i />
            <i />
            <i />
        </span>
    ) : (
        <span className="pf pf-spinner" data-color={color} data-size={size} role="status" aria-label={label} />
    );
}

export function ForgeSkeleton({ variant = 'text', count = 1 }: { variant?: 'text' | 'circle' | 'rect'; count?: number }) {
    return (
        <span className="pf" aria-hidden="true">
            {Array.from({ length: Math.max(1, count) }, (_, index) => (
                <span key={index} className="pf-skeleton" data-variant={variant} />
            ))}
        </span>
    );
}

export function ForgeProgress({ value = 0, color = 'primary', size = 'md', label = false, animated = false }: { value?: number; color?: ForgeColor; size?: ForgeSize; label?: boolean; animated?: boolean }) {
    const percent = Math.max(0, Math.min(100, Number(value) || 0));

    return (
        <div className="pf pf-progress" data-color={color} data-size={size}>
            <div className="pf-progress-track" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={percent}>
                <div className={cx('pf-progress-bar', animated && 'pf-progress-bar--animated')} style={{ width: `${percent}%` }} />
            </div>
            {label && <span>{percent}%</span>}
        </div>
    );
}

// ── Alert, badge, avatar ────────────────────────────────────────────────────

export interface ForgeAlertProps {
    color?: ForgeColor;
    /** Alias of `color` (`success`, `danger`, `warn`, `info`). */
    type?: string;
    title?: ReactNode;
    closable?: boolean;
    dismissible?: boolean;
    onClose?: () => void;
    children?: ReactNode;
}

export function ForgeAlert({ color = 'primary', type, title, closable, dismissible, onClose, children }: ForgeAlertProps) {
    const [open, setOpen] = useState(true);
    const shade = (type === 'info' ? 'primary' : type) ?? color;

    if (!open) {
        return null;
    }

    return (
        <div className="pf pf-alert" data-color={shade} role={shade === 'danger' ? 'alert' : 'status'}>
            <div className="pf-alert-body">
                {title && <strong className="pf-alert-title">{title}</strong>}
                {children}
            </div>
            {(closable || dismissible) && (
                <button
                    type="button"
                    className="pf-x" aria-label={forgeWords().close} title={forgeWords().close}
                    onClick={() => {
                        setOpen(false);
                        onClose?.();
                    }}
                >
                    <Icon name="x" />
                </button>
            )}
        </div>
    );
}

export function ForgeBadge({ color = 'danger', position = 'top-right', value = '', dot = false, children }: { color?: ForgeColor; position?: string; value?: ReactNode; dot?: boolean; children?: ReactNode }) {
    const show = dot || (value !== '' && value !== null && value !== undefined);

    return (
        <span className="pf pf-badge" data-color={color} data-position={position}>
            {children}
            {show && <span className={cx('pf-badge-mark', dot && 'pf-badge-mark--dot')}>{dot ? null : value}</span>}
        </span>
    );
}

export function ForgeAvatar({ src, alt = '', size = 'md', color = 'primary', text = '', badgeColor, badgePosition = 'bottom-right' }: { src?: string | null; alt?: string; size?: ForgeSize | 'xl'; color?: ForgeColor; text?: string; badgeColor?: string | null; badgePosition?: string }) {
    const initials = text
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase())
        .join('');

    return (
        <span className="pf pf-avatar" data-color={color} data-size={size} role="img" aria-label={alt || text}>
            {src ? <img src={src} alt="" /> : initials}
            {badgeColor && <span className="pf-avatar-dot" data-position={badgePosition} style={{ background: `var(--pc-${badgeColor}, ${badgeColor})` }} />}
        </span>
    );
}

// ── Card, stat card, chart card, empty, page header, breadcrumb, list ──────

export function ForgeCard({ type = 'default', flat, hoverable, header, footer, children }: { type?: string; flat?: boolean; hoverable?: boolean; header?: ReactNode; footer?: ReactNode; children?: ReactNode }) {
    return (
        <div className={cx('pf pf-card', flat && 'pf-card--flat', hoverable && 'pf-card--hoverable')} data-type={type}>
            {header && <div className="pf-card-head">{header}</div>}
            <div className="pf-card-body">{children}</div>
            {footer && <div className="pf-card-foot">{footer}</div>}
        </div>
    );
}

export function ForgeStatCard({ title = 'Metric', value = '0', icon, color = 'primary', trend, trendLabel }: { title?: ReactNode; value?: ReactNode; icon?: string | null; color?: ForgeColor; trend?: number | string | null; trendLabel?: ReactNode }) {
    const number = trend === null || trend === undefined || trend === '' ? null : Number(trend);

    return (
        <div className="pf pf-stat" data-color={color}>
            {icon && (
                <span className="pf-stat-icon" aria-hidden="true">
                    <i className={icon} />
                </span>
            )}
            <div>
                <div className="pf-stat-title">{title}</div>
                <div className="pf-stat-value">{value}</div>
                {number !== null && !Number.isNaN(number) && (
                    <div className={cx('pf-stat-trend', number >= 0 ? 'pf-stat-trend--up' : 'pf-stat-trend--down')}>
                        {number >= 0 ? '▲' : '▼'} {Math.abs(number)}% {trendLabel}
                    </div>
                )}
            </div>
        </div>
    );
}

export function ForgeChartCard({ title, subtitle, color = 'primary', children }: { title?: ReactNode; subtitle?: ReactNode; color?: ForgeColor; children?: ReactNode }) {
    return (
        <div className="pf pf-card" data-color={color}>
            <div className="pf-card-body">
                {title && <div className="pf-chart-title">{title}</div>}
                {subtitle && <div className="pf-chart-subtitle">{subtitle}</div>}
                <div className="pf-chart-rule" />
                {children}
            </div>
        </div>
    );
}

export function ForgeEmpty({ title = '', description = '', children }: { title?: ReactNode; description?: ReactNode; children?: ReactNode }) {
    return (
        <div className="pf pf-empty">
            {title && <strong className="pf-empty-title">{title}</strong>}
            {description && <div>{description}</div>}
            {children}
        </div>
    );
}

export function ForgePageHeader({ title = '', subtitle, back, children }: { title?: ReactNode; subtitle?: ReactNode; back?: string | null; children?: ReactNode }) {
    return (
        <header className="pf pf-page-header">
            {back && (
                <a className="pf pf-button pf-button--flat" href={back} aria-label={forgeWords().back} title={forgeWords().back}>
                    <Icon name="chevronLeft" />
                </a>
            )}
            <div>
                <h1 className="pf-page-title">{title}</h1>
                {subtitle && <p className="pf-page-subtitle">{subtitle}</p>}
            </div>
            {children && <div className="pf-page-actions">{children}</div>}
        </header>
    );
}

export interface ForgeCrumb {
    label: ReactNode;
    url?: string;
}

export function ForgeBreadcrumb({ items = [], separator = '/' }: { items?: ForgeCrumb[]; separator?: string }) {
    return (
        <nav className="pf" aria-label="Breadcrumb">
            <ol className="pf-breadcrumb">
                {items.map((item, index) => (
                    <li key={index} aria-current={index === items.length - 1 ? 'page' : undefined}>
                        {item.url && index < items.length - 1 ? <a href={item.url}>{item.label}</a> : item.label}
                        {index < items.length - 1 && <span aria-hidden="true"> {separator}</span>}
                    </li>
                ))}
            </ol>
        </nav>
    );
}

export interface ForgeListItem {
    title: ReactNode;
    subtitle?: ReactNode;
    icon?: string;
    action?: ReactNode;
}

export function ForgeList({ items = [] }: { items?: ForgeListItem[] }) {
    return (
        <ul className="pf pf-list">
            {items.map((item, index) => (
                <li key={index} className="pf-list-item">
                    {item.icon && <i className={item.icon} aria-hidden="true" />}
                    <span className="pf-list-text">
                        <span className="pf-list-title">{item.title}</span>
                        {item.subtitle && <span className="pf-list-sub">{item.subtitle}</span>}
                    </span>
                    {item.action}
                </li>
            ))}
        </ul>
    );
}

// ── Fields ──────────────────────────────────────────────────────────────────

export interface ForgeInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange'> {
    label?: ReactNode;
    state?: 'normal' | 'success' | 'danger' | 'warn';
    iconBefore?: string | null;
    iconAfter?: string | null;
    loading?: boolean;
    /** Help text under the field. */
    message?: ReactNode;
    error?: ReactNode;
    onChange?: (value: string) => void;
}

export function ForgeInput({ label = '', state = 'normal', iconBefore, iconAfter, loading, message = '', error, required, onChange, id, className, ...rest }: ForgeInputProps) {
    const auto = useId();
    const inputId = id ?? auto;
    // A password shows an eye that reveals it (forge-input's _show).
    const [shown, setShown] = useState(false);
    const isPassword = rest.type === 'password';
    const toggleText = shown ? forgeWords().hidePassword : forgeWords().showPassword;

    return (
        <div className="pf pf-field" data-state={error ? 'danger' : state}>
            {label && (
                <label className="pf-field-label" htmlFor={inputId}>
                    {label}
                    {required && <span aria-hidden="true"> *</span>}
                </label>
            )}
            <div className="pf-field-box">
                {iconBefore && (
                    <span className="pf-field-icon pf-field-icon--before" aria-hidden="true">
                        <i className={iconBefore} />
                    </span>
                )}
                <input
                    {...rest}
                    type={isPassword && shown ? 'text' : rest.type}
                    id={inputId}
                    required={required}
                    className={cx('pf-control', iconBefore && 'pf-control--before', (iconAfter || loading || isPassword) && 'pf-control--after', className)}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? `${inputId}-error` : message ? `${inputId}-message` : undefined}
                    onChange={(event) => onChange?.(event.target.value)}
                />
                {isPassword && !loading ? (
                    <button type="button" className="pf-field-toggle" aria-label={toggleText} title={toggleText} aria-pressed={shown} tabIndex={-1} onClick={() => setShown(!shown)}>
                        <Icon name={shown ? 'eyeOff' : 'eye'} />
                    </button>
                ) : (iconAfter || loading) && (
                    <span className="pf-field-icon pf-field-icon--after" aria-hidden="true">
                        {loading ? <span className="pf-spinner" data-size="sm" /> : <i className={iconAfter ?? undefined} />}
                    </span>
                )}
            </div>
            {error ? (
                <p className="pf-field-error" id={`${inputId}-error`} role="alert">
                    {error}
                </p>
            ) : (
                message && (
                    <p className="pf-field-message" id={`${inputId}-message`}>
                        {message}
                    </p>
                )
            )}
        </div>
    );
}

export interface ForgeTextareaProps extends Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'onChange' | 'color'> {
    label?: ReactNode;
    color?: ForgeColor;
    state?: 'normal' | 'success' | 'danger' | 'warn' | null;
    helper?: ReactNode;
    /** Shows `typed/maxlength`. */
    counter?: boolean;
    onChange?: (value: string) => void;
}

export function ForgeTextarea({ label, color = 'primary', state, helper, counter, rows = 4, maxLength, onChange, id, value, defaultValue, ...rest }: ForgeTextareaProps) {
    const auto = useId();
    const fieldId = id ?? auto;
    // Counted from the text already there, not from zero.
    const [typed, setTyped] = useState(() => String(value ?? defaultValue ?? '').length);

    useEffect(() => {
        if (value !== undefined) {
            setTyped(String(value ?? '').length);
        }
    }, [value]);

    return (
        <div className="pf pf-field" data-color={color} data-state={state ?? 'normal'}>
            {label && (
                <label className="pf-field-label" htmlFor={fieldId}>
                    {label}
                </label>
            )}
            <textarea
                {...rest}
                id={fieldId}
                rows={rows}
                maxLength={maxLength}
                value={value}
                defaultValue={defaultValue}
                className="pf-control"
                onChange={(event) => {
                    setTyped(event.target.value.length);
                    onChange?.(event.target.value);
                }}
            />
            <div className="pf-field-foot">
                {helper ? <p className="pf-field-message">{helper}</p> : <span />}
                {counter && maxLength !== undefined && (
                    <p className="pf-field-message">
                        {typed}/{maxLength}
                    </p>
                )}
            </div>
        </div>
    );
}

interface ForgeToggleProps {
    label?: ReactNode;
    color?: ForgeColor;
    checked?: boolean;
    disabled?: boolean;
    name?: string;
    onChange?: (checked: boolean) => void;
}

export function ForgeCheckbox({ label, color = 'primary', checked, disabled, name, onChange }: ForgeToggleProps) {
    return (
        <label className={cx('pf pf-check', disabled && 'pf-check--disabled')} data-color={color}>
            <input type="checkbox" name={name} checked={checked ?? false} disabled={disabled} onChange={(event) => onChange?.(event.target.checked)} />
            {label && <span>{label}</span>}
        </label>
    );
}

export function ForgeRadio({ label, color = 'primary', value = '', name = '', checked, disabled, onChange }: Omit<ForgeToggleProps, 'onChange'> & { value?: string; onChange?: (value: string) => void }) {
    return (
        <label className={cx('pf pf-check', disabled && 'pf-check--disabled')} data-color={color}>
            <input type="radio" name={name} value={value} checked={checked ?? false} disabled={disabled} onChange={() => onChange?.(value)} />
            {label && <span>{label}</span>}
        </label>
    );
}

/** The state is the `checked` prop — one source of truth, so it can never show off while on. */
export function ForgeSwitch({ label, color = 'primary', checked, disabled, name, size = 'md', onChange }: ForgeToggleProps & { size?: ForgeSize }) {
    return (
        <label className={cx('pf pf-switch', disabled && 'pf-switch--disabled')} data-color={color} data-size={size}>
            <input type="checkbox" role="switch" name={name} checked={checked ?? false} disabled={disabled} onChange={(event) => onChange?.(event.target.checked)} />
            <span className="pf-switch-track" />
            {label && <span>{label}</span>}
        </label>
    );
}

// ── Select ──────────────────────────────────────────────────────────────────

export interface ForgeOption {
    value: string | number;
    label: string;
}

export interface ForgeSelectProps {
    label?: ReactNode;
    options?: ForgeOption[];
    placeholder?: string | null;
    multiple?: boolean;
    disabled?: boolean;
    required?: boolean;
    error?: ReactNode;
    /** The value, or the list of values with `multiple`. */
    selected?: string | number | (string | number)[] | null;
    name?: string;
    searchable?: boolean;
    onChange?: (selected: string | number | (string | number)[] | null) => void;
}

const fold = (text: string) =>
    text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

export function ForgeSelect({ label = '', options = [], placeholder = forgeWords().select, multiple, disabled, required, error, selected = null, name, searchable, onChange }: ForgeSelectProps) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const [filter, setFilter] = useState('');
    const [active, setActive] = useState(-1);
    const root = useRef<HTMLDivElement>(null);
    // Fixed under the field (1.63.1): a dialog body that scrolls hid the list.
    const panel = useFloating<HTMLDivElement>(root, open);
    const chosen = useMemo(() => (Array.isArray(selected) ? selected : selected === null || selected === '' ? [] : [selected]).map(String), [selected]);
    // Diacritics-insensitive: "permissao" finds "Permissão".
    const shown = useMemo(() => (filter === '' ? options : options.filter((option) => String(option.value) === '' || fold(option.label).includes(fold(filter)))), [options, filter]);
    const text = options
        .filter((option) => chosen.includes(String(option.value)))
        .map((option) => option.label)
        .join(', ');

    useEffect(() => {
        if (!open) {
            return;
        }
        const close = (event: MouseEvent) => {
            if (!root.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    const toggle = () => {
        // Reopening starts from a clean filter.
        setFilter('');
        setActive(-1);
        setOpen(!open);
    };

    const pick = (option: ForgeOption) => {
        if (multiple) {
            const key = String(option.value);
            const next = chosen.includes(key) ? chosen.filter((item) => item !== key) : [...chosen, key];
            onChange?.(options.filter((item) => next.includes(String(item.value))).map((item) => item.value));
        } else {
            onChange?.(option.value);
            setOpen(false);
        }
    };

    const onKey = (event: React.KeyboardEvent) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive(Math.min(shown.length - 1, active + 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(Math.max(0, active - 1));
        } else if (event.key === 'Enter' && active >= 0 && shown[active]) {
            event.preventDefault();
            pick(shown[active]);
        } else if (event.key === 'Escape') {
            event.stopPropagation();
            // First Escape clears the filter, the second closes.
            if (filter !== '') {
                setFilter('');
            } else {
                setOpen(false);
            }
        }
    };

    return (
        <div className="pf pf-field" data-state={error ? 'danger' : 'normal'}>
            {label && (
                <label className="pf-field-label" id={`${id}-label`}>
                    {label}
                    {required && <span aria-hidden="true"> *</span>}
                </label>
            )}
            <div className="pf-select" ref={root}>
                <button
                    type="button"
                    className="pf-control pf-select-trigger"
                    aria-haspopup="listbox"
                    aria-expanded={open}
                    aria-labelledby={label ? `${id}-label` : undefined}
                    aria-invalid={error ? true : undefined}
                    disabled={disabled}
                    onClick={toggle}
                    onKeyDown={open && !searchable ? onKey : undefined}
                >
                    <span className={text ? undefined : 'pf-select-placeholder'}>{text || placeholder}</span>
                    <span aria-hidden="true">▾</span>
                </button>
                {open && (
                    <div className="pf-select-panel" ref={panel}>
                        {searchable && <input autoFocus type="text" className="pf-control pf-select-filter" aria-label={forgeWords().filter} value={filter} onChange={(event) => { setFilter(event.target.value); setActive(-1); }} onKeyDown={onKey} />}
                        <ul className="pf-select-list" role="listbox" aria-multiselectable={multiple || undefined}>
                            {shown.length === 0 && <li className="pf-select-empty">—</li>}
                            {shown.map((option, index) => (
                                <li key={String(option.value)} role="option" aria-selected={chosen.includes(String(option.value))} data-active={index === active} className="pf-select-option" onClick={() => pick(option)}>
                                    {multiple && <input type="checkbox" readOnly tabIndex={-1} checked={chosen.includes(String(option.value))} />}
                                    {option.label}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
                {name && chosen.map((value) => <input key={value} type="hidden" name={multiple ? `${name}[]` : name} value={value} />)}
            </div>
            {error && (
                <p className="pf-field-error" role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}

// ── Modal ───────────────────────────────────────────────────────────────────

export interface ForgeModalProps {
    open: boolean;
    onClose: () => void;
    title?: ReactNode;
    subtitle?: ReactNode;
    size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'full';
    footer?: ReactNode;
    children?: ReactNode;
}

export function ForgeModal({ open, onClose, title = '', subtitle, size = 'md', footer, children }: ForgeModalProps) {
    const titleId = useId();
    const panel = useRef<HTMLDivElement>(null);

    // The Tab key stays inside, and the focus goes back on close (forge-modal's x-trap).
    useEffect(() => (open && panel.current ? trapFocus(panel.current) : undefined), [open]);

    useEffect(() => {
        if (!open) {
            return;
        }
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open) {
        return null;
    }

    return (
        <div className="pf pf-overlay" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <div className="pf-modal" ref={panel} data-size={size} role="dialog" aria-modal="true" aria-labelledby={titleId}>
                <div className="pf-modal-head">
                    <div>
                        <h2 className="pf-modal-title" id={titleId}>
                            {title}
                        </h2>
                        {subtitle && <p className="pf-modal-subtitle">{subtitle}</p>}
                    </div>
                    <button type="button" className="pf-modal-close" aria-label={forgeWords().close} title={forgeWords().close} onClick={onClose}>
                        <Icon name="x" size={20} />
                    </button>
                </div>
                <div className="pf-modal-body">{children}</div>
                {footer && <div className="pf-modal-foot">{footer}</div>}
            </div>
        </div>
    );
}

// ── Tabs, stepper, pagination, table ────────────────────────────────────────

export interface ForgeTabDef {
    id: string;
    label: ReactNode;
    content?: ReactNode;
}

export function ForgeTabs({ tabs = [], color = 'primary', defaultTab, onChange }: { tabs?: ForgeTabDef[]; color?: ForgeColor; defaultTab?: string | null; onChange?: (id: string) => void }) {
    const base = useId();
    const [active, setActive] = useState(defaultTab ?? tabs[0]?.id ?? '');
    const select = (id: string) => {
        setActive(id);
        onChange?.(id);
    };
    const move = (delta: number) => {
        const index = tabs.findIndex((tab) => tab.id === active);
        const next = tabs[(index + delta + tabs.length) % tabs.length];
        if (next) {
            select(next.id);
            document.getElementById(`${base}-tab-${next.id}`)?.focus();
        }
    };

    return (
        <div className="pf" data-color={color}>
            <div className="pf-tablist" role="tablist">
                {tabs.map((tab) => (
                    <button
                        key={tab.id}
                        type="button"
                        role="tab"
                        id={`${base}-tab-${tab.id}`}
                        className="pf-tab"
                        aria-selected={tab.id === active}
                        aria-controls={`${base}-panel-${tab.id}`}
                        tabIndex={tab.id === active ? 0 : -1}
                        onClick={() => select(tab.id)}
                        onKeyDown={(event) => {
                            if (event.key === 'ArrowRight') {
                                move(1);
                            } else if (event.key === 'ArrowLeft') {
                                move(-1);
                            }
                        }}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>
            {tabs.map(
                (tab) =>
                    tab.id === active && (
                        <div key={tab.id} role="tabpanel" id={`${base}-panel-${tab.id}`} aria-labelledby={`${base}-tab-${tab.id}`} className="pf-tabpanel">
                            {tab.content}
                        </div>
                    ),
            )}
        </div>
    );
}

export function ForgeStepper({ steps = [], currentStep = 1, color = 'primary' }: { steps?: (string | { label: ReactNode })[]; currentStep?: number; color?: ForgeColor }) {
    return (
        <ol className="pf pf-stepper" data-color={color}>
            {steps.map((step, index) => {
                const number = index + 1;
                const state = number < currentStep ? 'done' : number === currentStep ? 'current' : 'todo';

                return (
                    <li key={index} className="pf-step" data-state={state} aria-current={state === 'current' ? 'step' : undefined}>
                        <span className="pf-step-dot">{state === 'done' ? '✓' : number}</span>
                        {typeof step === 'string' ? step : step.label}
                    </li>
                );
            })}
        </ol>
    );
}

export function ForgePagination({ page, lastPage, onChange, previous = '‹', next = '›' }: { page: number; lastPage: number; onChange: (page: number) => void; previous?: ReactNode; next?: ReactNode }) {
    return (
        <nav className="pf pf-pagination" aria-label="Pagination">
            <button type="button" className="pf-page" disabled={page <= 1} aria-label={forgeWords().previousPage} onClick={() => onChange(page - 1)}>
                {previous}
            </button>
            {pagesAround(page, lastPage).map((number) => (
                <button key={number} type="button" className="pf-page" aria-current={number === page ? 'page' : undefined} onClick={() => onChange(number)}>
                    {number}
                </button>
            ))}
            <button type="button" className="pf-page" disabled={page >= lastPage} aria-label={forgeWords().nextPage} onClick={() => onChange(page + 1)}>
                {next}
            </button>
        </nav>
    );
}

export interface ForgeTableHeader {
    key: string;
    label: ReactNode;
}

export interface ForgeTableProps {
    headers?: (ForgeTableHeader | string)[];
    rows?: Record<string, ReactNode>[] | null;
    searchable?: boolean;
    sortable?: boolean;
    emptyMessage?: ReactNode;
}

export function ForgeTable({ headers = [], rows, searchable, sortable, emptyMessage = 'Nenhum registro encontrado.' }: ForgeTableProps) {
    const [search, setSearch] = useState('');
    const [sort, setSort] = useState<{ key: string; asc: boolean } | null>(null);
    const columns = headers.map((header) => (typeof header === 'string' ? { key: header, label: header } : header));
    const shown = useMemo(() => {
        let list = rows ?? [];
        if (search !== '') {
            list = list.filter((row) => columns.some((column) => fold(String(row[column.key] ?? '')).includes(fold(search))));
        }
        if (sort) {
            list = [...list].sort((a, b) => String(a[sort.key] ?? '').localeCompare(String(b[sort.key] ?? ''), undefined, { numeric: true }) * (sort.asc ? 1 : -1));
        }

        return list;
    }, [rows, search, sort]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <div className="pf pf-table-wrap">
            {searchable && (
                <div className="pf-table-tools">
                    <input type="search" className="pf-control" aria-label={forgeWords().search} placeholder={forgeWords().searchPlaceholder} value={search} onChange={(event) => setSearch(event.target.value)} />
                </div>
            )}
            <table className="pf-table">
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th key={column.key} aria-sort={sort?.key === column.key ? (sort.asc ? 'ascending' : 'descending') : undefined}>
                                {sortable ? (
                                    <button type="button" className="pf-th-btn" onClick={() => setSort({ key: column.key, asc: sort?.key === column.key ? !sort.asc : true })}>
                                        {column.label} {sort?.key === column.key ? (sort.asc ? '▲' : '▼') : ''}
                                    </button>
                                ) : (
                                    column.label
                                )}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {shown.length === 0 && (
                        <tr>
                            <td colSpan={Math.max(1, columns.length)}>{emptyMessage}</td>
                        </tr>
                    )}
                    {shown.map((row, index) => (
                        <tr key={index}>
                            {columns.map((column) => (
                                <td key={column.key}>{row[column.key]}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ── Notification and toast host ─────────────────────────────────────────────

export function ForgeNotification({ show, onClose, color = 'primary', title = '', text = '', position = 'top-right', duration = 4000 }: { show: boolean; onClose: () => void; color?: ForgeColor; title?: ReactNode; text?: ReactNode; position?: string; duration?: number }) {
    useEffect(() => {
        if (!show || duration <= 0) {
            return;
        }
        const timer = setTimeout(onClose, duration);

        return () => clearTimeout(timer);
    }, [show, duration, onClose]);

    if (!show) {
        return null;
    }

    return (
        <div className="pf pf-notification" data-color={color} data-position={position} role="status">
            <div style={{ flex: 1 }}>
                {title && <strong className="pf-notification-title">{title}</strong>}
                {text}
            </div>
            <button type="button" className="pf-x" aria-label={forgeWords().close} title={forgeWords().close} onClick={onClose}>
                <Icon name="x" />
            </button>
        </div>
    );
}

export interface ForgeToast {
    id: number;
    title: string;
    color: string;
}

type ToastListener = (toasts: ForgeToast[]) => void;

let toastList: ForgeToast[] = [];
let toastSeq = 0;
const toastListeners = new Set<ToastListener>();
const publish = () => toastListeners.forEach((listener) => listener(toastList));

/** Shows a toast in the `<ForgeToastHost>` on the page. */
export function forgeToast(title: string, color = 'primary', duration = 4000): void {
    const id = ++toastSeq;
    toastList = [...toastList, { id, title, color }];
    publish();

    if (duration > 0) {
        setTimeout(() => {
            toastList = toastList.filter((toast) => toast.id !== id);
            publish();
        }, duration);
    }
}

/**
 * Where toasts appear — one per page. `forgeToast('Saved', 'success')` from
 * anywhere, or a `ptah-toast` window event (`detail: { title, color }`), the
 * event the Livewire stack dispatches.
 */
export function ForgeToastHost() {
    const [toasts, setToasts] = useState<ForgeToast[]>(toastList);

    useEffect(() => {
        toastListeners.add(setToasts);
        const onEvent = (event: Event) => {
            const detail = (event as CustomEvent).detail ?? {};
            forgeToast(String(detail.title ?? ''), String(detail.color ?? 'primary'));
        };
        window.addEventListener('ptah-toast', onEvent);

        return () => {
            toastListeners.delete(setToasts);
            window.removeEventListener('ptah-toast', onEvent);
        };
    }, []);

    return (
        <div className="pf pf-toast-host" aria-live="polite">
            {toasts.map((toast) => (
                <div key={toast.id} className="pf pf-toast" data-color={toast.color} role="status">
                    <span className="pf-toast-text">{toast.title}</span>
                    <button
                        type="button"
                        className="pf-x" aria-label={forgeWords().close} title={forgeWords().close}
                        onClick={() => {
                            toastList = toastList.filter((item) => item.id !== toast.id);
                            publish();
                        }}
                    >
                        <Icon name="x" />
                    </button>
                </div>
            ))}
        </div>
    );
}
