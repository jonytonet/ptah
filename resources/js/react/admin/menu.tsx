/**
 * The menu editor and the system settings for React, over the admin API.
 * Who may administer the structure, the safe URL of a menu item, the typed
 * rules of each setting and the company → global scope are the server's
 * (MenuList, SettingsPage); these screens only ask and show.
 */
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { CSSProperties, FormEvent, ReactNode } from 'react';
import { ApiError, PtahApi } from '../../core';
import {
    blankMenuForm,
    deleteMenuItem,
    fetchSettings,
    listMenu,
    menuWords,
    nextSort,
    resetSetting,
    saveMenuItem,
    saveSettings,
    settingFormValue,
    settingInputType,
    showMenuItem,
    toggleMenuItem,
} from '../../core/admin/menu';
import type { AdminMessage, MenuForm, MenuGroupOption, MenuListing, MenuListState, MenuSort, MenuWords, SettingField, SettingScope, SettingsPayload } from '../../core/admin/menu';
import { ForgeAlert, ForgeButton, ForgeCheckbox, ForgeEmpty, ForgeInput, ForgeModal, ForgePageHeader, ForgePagination, ForgeRadio, ForgeSelect, ForgeSpinner, ForgeSwitch, ForgeTextarea, forgeToast, ForgeToastHost } from '../forge';

export interface MenuAdminProps {
    api?: PtahApi;
    locale?: string;
}

const pillStyle: CSSProperties = { display: 'inline-flex', alignItems: 'center', gap: 4, padding: '2px 8px', borderRadius: 999, fontSize: 12, fontWeight: 500, background: 'var(--pc-bg-soft)', color: 'var(--pc-text-strong)', border: '1px solid var(--pc-line)' };
const chipStyle: CSSProperties = { display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 36, height: 36, borderRadius: 6, fontSize: 20, background: 'var(--pc-bg-soft)', flexShrink: 0 };
const mutedStyle: CSSProperties = { fontSize: 12, color: 'var(--pc-text-muted)' };
const errorStyle: CSSProperties = { margin: '4px 0 0', fontSize: 12, color: 'var(--pc-danger)' };
const sectionStyle: CSSProperties = { padding: 16, border: '1px solid var(--pc-line)', borderRadius: 'var(--pc-radius)', background: 'var(--pc-bg)' };

/** 403 says so; anything else is a generic failure. */
const failureText = (error: unknown, t: MenuWords) => (error instanceof ApiError && error.status === 403 ? t.forbidden : error instanceof ApiError && error.message ? error.message : t.error);

const showMessages = (messages: AdminMessage[] | undefined) => (messages ?? []).forEach((message) => forgeToast(message.text, message.level));

function FieldError({ text }: { text?: string | null }) {
    return text ? (
        <p className="pf-field-error" role="alert" style={errorStyle}>
            {text}
        </p>
    ) : null;
}

const EditIcon = () => (
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
    </svg>
);

const TrashIcon = () => (
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
    </svg>
);

// ── Menu ────────────────────────────────────────────────────────────────────

export function PtahMenuAdmin({ api, locale }: MenuAdminProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = menuWords(locale);
    const [state, setState] = useState<MenuListState>({ search: '', type: '', sort: 'link_order', direction: 'asc', page: 1 });
    const [search, setSearch] = useState('');
    const [listing, setListing] = useState<MenuListing | null>(null);
    const [loading, setLoading] = useState(true);
    const [failure, setFailure] = useState<string | null>(null);
    // The modal: `editingId` null = a new item.
    const [formOpen, setFormOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [form, setForm] = useState<MenuForm>(blankMenuForm());
    const [groups, setGroups] = useState<MenuGroupOption[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);
    const seq = useRef(0);

    const load = useCallback(
        async (next: MenuListState) => {
            const mine = ++seq.current;
            setLoading(true);
            try {
                const answer = await listMenu(client, next);
                if (mine === seq.current) {
                    setListing(answer);
                    setFailure(null);
                }
            } catch (error) {
                if (mine === seq.current) {
                    setFailure(failureText(error, t));
                }
            } finally {
                if (mine === seq.current) {
                    setLoading(false);
                }
            }
        },
        [client], // eslint-disable-line react-hooks/exhaustive-deps
    );

    useEffect(() => {
        void load(state);
    }, [state, load]);

    // The search waits for the typing to stop (300 ms, as wire:model.live.debounce).
    useEffect(() => {
        if (search === state.search) {
            return;
        }
        const timer = setTimeout(() => setState((current) => ({ ...current, search, page: 1 })), 300);

        return () => clearTimeout(timer);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    const set = <K extends keyof MenuForm>(key: K, value: MenuForm[K]) => setForm((current) => ({ ...current, [key]: value }));

    const openCreate = () => {
        setEditingId(null);
        setForm(blankMenuForm());
        setGroups(listing?.groups ?? []);
        setErrors({});
        setFormOpen(true);
    };

    const openEdit = async (id: number) => {
        try {
            const item = await showMenuItem(client, id);
            const { id: _id, ...fields } = item.data; // eslint-disable-line @typescript-eslint/no-unused-vars
            setEditingId(id);
            setForm(fields);
            setGroups(item.groups);
            setErrors({});
            setFormOpen(true);
        } catch (error) {
            forgeToast(failureText(error, t), 'danger');
        }
    };

    const submit = async (event?: FormEvent) => {
        event?.preventDefault();
        setSaving(true);
        try {
            const answer = await saveMenuItem(client, editingId, form);
            showMessages(answer.messages);
            if (answer.ok) {
                setFormOpen(false);
                void load(state);
            } else {
                setErrors(answer.errors ?? {});
                if (answer.error) {
                    forgeToast(answer.error, 'danger');
                }
            }
        } catch (error) {
            forgeToast(failureText(error, t), 'danger');
        } finally {
            setSaving(false);
        }
    };

    const toggle = async (id: number) => {
        try {
            showMessages((await toggleMenuItem(client, id)).messages);
        } catch (error) {
            forgeToast(failureText(error, t), 'danger');
        }
        void load(state);
    };

    const confirmDelete = async () => {
        if (deleteId === null) {
            return;
        }
        setDeleting(true);
        try {
            showMessages((await deleteMenuItem(client, deleteId)).messages);
        } catch (error) {
            forgeToast(failureText(error, t), 'danger');
        } finally {
            setDeleting(false);
            setDeleteId(null);
        }
        void load({ ...state });
    };

    const sortBy = (column: MenuSort) => setState((current) => ({ ...current, ...nextSort(current, column), page: 1 }));
    const arrow = (column: MenuSort) => (
        <span className={state.sort === column ? 'pc-sort pc-sort--on' : 'pc-sort'} aria-hidden="true">
            {state.sort === column ? (state.direction === 'asc' ? '↑' : '↓') : '↕'}
        </span>
    );
    const ariaSort = (column: MenuSort) => (state.sort === column ? (state.direction === 'asc' ? 'ascending' : 'descending') : undefined);

    const rows = listing?.data ?? [];
    const groupOptions = [{ value: '', label: t.root }, ...groups.map((group) => ({ value: String(group.id), label: group.text }))];

    return (
        <div className="pf pc-root">
            <ForgePageHeader title={t.menuTitle} subtitle={t.menuSubtitle} />

            <div className="pc-toolbar">
                <ForgeButton size="sm" onClick={openCreate}>
                    + {t.newItem}
                </ForgeButton>
                <div className="pc-search">
                    <ForgeInput type="search" aria-label={t.searchLabel} placeholder={t.searchPlaceholder} value={search} onChange={setSearch} />
                </div>
                <div style={{ minWidth: 180 }}>
                    <ForgeSelect
                        label={t.filterByType}
                        placeholder={t.allTypes}
                        selected={state.type}
                        options={[
                            { value: '', label: t.allTypes },
                            { value: 'menuLink', label: t.typeLink },
                            { value: 'menuGroup', label: t.typeGroup },
                        ]}
                        onChange={(value) => setState((current) => ({ ...current, type: (String(value ?? '') as MenuListState['type']) || '', page: 1 }))}
                    />
                </div>
            </div>

            {failure && <ForgeAlert type="danger">{failure}</ForgeAlert>}

            <div className="pc-table-wrap" aria-busy={loading}>
                {loading && !listing && (
                    <div className="pc-empty">
                        <ForgeSpinner label={t.loading} /> {t.loading}
                    </div>
                )}
                {listing && (
                    <table className="pc-table">
                        <thead>
                            <tr>
                                <th scope="col">{t.colIcon}</th>
                                <th scope="col" aria-sort={ariaSort('text')}>
                                    <button type="button" className="pc-th-btn" onClick={() => sortBy('text')}>
                                        {t.colText} {arrow('text')}
                                    </button>
                                </th>
                                <th scope="col">{t.colType}</th>
                                <th scope="col">{t.colUrl}</th>
                                <th scope="col">{t.colParent}</th>
                                <th scope="col" className="pc-align-center" aria-sort={ariaSort('link_order')}>
                                    <button type="button" className="pc-th-btn" onClick={() => sortBy('link_order')}>
                                        {t.colOrder} {arrow('link_order')}
                                    </button>
                                </th>
                                <th scope="col" className="pc-align-center">
                                    {t.colStatus}
                                </th>
                                <th scope="col" className="pc-col-actions">
                                    {t.colActions}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={8}>
                                        <ForgeEmpty title={t.emptyFound} description={t.emptyHint} />
                                    </td>
                                </tr>
                            )}
                            {rows.map((row) => (
                                <tr key={row.id} style={row.is_active ? undefined : { opacity: 0.5 }}>
                                    <td className="pc-align-center" title={row.icon}>
                                        <i className={row.icon || 'bx bx-circle'} aria-hidden="true" />
                                    </td>
                                    <td style={{ fontWeight: 500 }}>{row.text}</td>
                                    <td>
                                        <span style={pillStyle}>
                                            <i className={row.type === 'menuGroup' ? 'bx bx-folder' : 'bx bx-link'} aria-hidden="true" /> {row.type === 'menuGroup' ? t.typeGroup : t.typeLink}
                                        </span>
                                    </td>
                                    <td style={{ fontFamily: 'monospace', fontSize: 12, maxWidth: 180, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }} title={row.url ?? undefined}>
                                        {row.url ?? '—'}
                                    </td>
                                    <td>{row.parent ?? '—'}</td>
                                    <td className="pc-align-center">
                                        <span style={pillStyle}>{row.link_order}</span>
                                    </td>
                                    <td className="pc-align-center">
                                        <button
                                            type="button"
                                            className="pc-btn"
                                            style={{ padding: '2px 10px', fontSize: 12, color: row.is_active ? 'var(--pc-success)' : 'var(--pc-danger)' }}
                                            title={row.is_active ? t.toggleDisable : t.toggleEnable}
                                            aria-label={`${row.is_active ? t.toggleDisable : t.toggleEnable}: ${row.text}`}
                                            onClick={() => void toggle(row.id)}
                                        >
                                            {row.is_active ? t.active : t.inactive}
                                        </button>
                                    </td>
                                    <td className="pc-col-actions">
                                        <button type="button" className="pc-icon-btn" title={t.edit} aria-label={`${t.edit}: ${row.text}`} onClick={() => void openEdit(row.id)}>
                                            <EditIcon />
                                        </button>
                                        <button type="button" className="pc-icon-btn pc-icon-btn--danger" title={t.delete} aria-label={`${t.delete}: ${row.text}`} onClick={() => setDeleteId(row.id)}>
                                            <TrashIcon />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {listing && listing.meta.last_page > 1 && (
                <div className="pc-footer">
                    <ForgePagination page={listing.meta.current_page} lastPage={listing.meta.last_page} onChange={(page) => setState((current) => ({ ...current, page }))} />
                </div>
            )}

            <ForgeModal
                open={formOpen}
                onClose={() => setFormOpen(false)}
                title={editingId === null ? t.formNew : t.formEdit}
                size="lg"
                footer={
                    <>
                        <ForgeButton color="dark" flat onClick={() => setFormOpen(false)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton loading={saving} onClick={() => void submit()}>
                            {saving ? t.saving : editingId === null ? t.createItem : t.saveChanges}
                        </ForgeButton>
                    </>
                }
            >
                <form className="pa-form" onSubmit={(event) => void submit(event)} noValidate>
                    <fieldset style={{ border: 0, padding: 0, margin: 0 }}>
                        <legend className="pf-field-label">
                            {t.formType} <span aria-hidden="true">*</span>
                        </legend>
                        <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
                            <ForgeRadio name="menu-type" value="menuLink" label={t.directLink} checked={form.type === 'menuLink'} onChange={() => set('type', 'menuLink')} />
                            <ForgeRadio name="menu-type" value="menuGroup" label={t.groupType} checked={form.type === 'menuGroup'} onChange={() => set('type', 'menuGroup')} />
                        </div>
                        <FieldError text={errors.type} />
                    </fieldset>

                    <ForgeInput label={t.textLabel} placeholder={t.textPlaceholder} required value={form.text} error={errors.text} onChange={(value) => set('text', value)} />

                    {form.type === 'menuLink' && <ForgeInput label={t.urlLabel} placeholder={t.urlPlaceholder} style={{ fontFamily: 'monospace' }} value={form.url} error={errors.url} onChange={(value) => set('url', value)} />}

                    <div>
                        <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end' }}>
                            <div style={{ flex: 1 }}>
                                <ForgeInput
                                    label={
                                        <>
                                            {t.iconLabel} <span style={{ fontWeight: 400 }}>{t.iconHint}</span>
                                        </>
                                    }
                                    placeholder={t.iconPlaceholder}
                                    style={{ fontFamily: 'monospace' }}
                                    value={form.icon}
                                    error={errors.icon}
                                    onChange={(value) => set('icon', value)}
                                />
                            </div>
                            <span style={chipStyle} title={t.iconPreview} data-testid="menu-icon-preview">
                                <i className={form.icon || 'bx bx-circle'} aria-hidden="true" />
                            </span>
                        </div>
                        <p className="pc-help">
                            {t.iconExamples} <code>bx bx-home</code> <code>fas fa-cog</code> <code>bx bxs-shopping-bag</code>
                        </p>
                    </div>

                    <ForgeSelect label={t.parentGroup} placeholder={t.root} options={groupOptions} selected={form.parent_id === null ? '' : String(form.parent_id)} error={errors.parent_id} onChange={(value) => set('parent_id', value === '' || value === null ? null : Number(value))} />

                    <div className="pc-form">
                        <ForgeInput label={t.order} type="number" min={0} value={String(form.link_order)} error={errors.link_order} onChange={(value) => set('link_order', Number(value) || 0)} />
                        <ForgeSelect
                            label={t.opening}
                            selected={form.target}
                            error={errors.target}
                            options={[
                                { value: '_self', label: t.sameTab },
                                { value: '_blank', label: t.newTab },
                            ]}
                            onChange={(value) => set('target', value === '_blank' ? '_blank' : '_self')}
                        />
                        <div>
                            <span className="pf-field-label">{t.statusLabel}</span>
                            <ForgeCheckbox label={t.activeCheck} checked={form.is_active} onChange={(value) => set('is_active', value)} />
                            <FieldError text={errors.is_active} />
                        </div>
                    </div>
                    {/* Enter in a field submits, as the Livewire form does on the save button. */}
                    <button type="submit" hidden aria-hidden="true" tabIndex={-1} />
                </form>
            </ForgeModal>

            <ForgeModal
                open={deleteId !== null}
                onClose={() => setDeleteId(null)}
                title={t.deleteTitle}
                size="sm"
                footer={
                    <>
                        <ForgeButton color="dark" flat onClick={() => setDeleteId(null)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton color="danger" loading={deleting} onClick={() => void confirmDelete()}>
                            {t.deleteConfirm}
                        </ForgeButton>
                    </>
                }
            >
                <p>{t.deleteText}</p>
            </ForgeModal>

            <ForgeToastHost />
        </div>
    );
}

// ── Settings ────────────────────────────────────────────────────────────────

export function PtahSettings({ api, locale }: MenuAdminProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = menuWords(locale);
    const [payload, setPayload] = useState<SettingsPayload | null>(null);
    const [values, setValues] = useState<Record<string, string | boolean>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [failure, setFailure] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    const take = (next: SettingsPayload) => {
        setPayload(next);
        const fresh: Record<string, string | boolean> = {};
        next.groups.forEach((group) => group.fields.forEach((field) => (fresh[field.key] = settingFormValue(field))));
        setValues(fresh);
    };

    const load = async (scope?: SettingScope) => {
        try {
            take(await fetchSettings(client, scope));
            setErrors({});
            setFailure(null);
        } catch (error) {
            setFailure(failureText(error, t));
        }
    };

    useEffect(() => {
        void load();
    }, [client]); // eslint-disable-line react-hooks/exhaustive-deps

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        if (!payload) {
            return;
        }
        setSaving(true);
        try {
            const answer = await saveSettings(client, payload.scope, values);
            showMessages(answer.messages);
            if (answer.ok && answer.settings) {
                take(answer.settings);
                setErrors({});
            } else {
                setErrors(answer.errors ?? {});
                if (answer.error) {
                    forgeToast(answer.error, 'danger');
                }
            }
        } catch (error) {
            forgeToast(failureText(error, t), 'danger');
            // The table may have gone missing: the reload explains it.
            void load(payload.scope);
        } finally {
            setSaving(false);
        }
    };

    const toGlobal = async (key: string) => {
        if (!payload) {
            return;
        }
        try {
            const answer = await resetSetting(client, key, payload.scope);
            showMessages(answer.messages);
            if (answer.settings) {
                take(answer.settings);
            }
        } catch (error) {
            forgeToast(failureText(error, t), 'danger');
        }
    };

    const control = (field: SettingField): ReactNode => {
        const value = values[field.key];
        const update = (next: string | boolean) => setValues((current) => ({ ...current, [field.key]: next }));
        const error = errors[field.key];

        switch (field.type) {
            case 'boolean':
                return (
                    <>
                        <ForgeSwitch name={`values.${field.key}`} label={field.label} checked={value === true} onChange={update} />
                        <FieldError text={error} />
                    </>
                );
            case 'select':
                return <ForgeSelect name={`values.${field.key}`} label={field.label} placeholder={t.choose} options={field.options} selected={String(value ?? '')} error={error} onChange={(next) => update(String(next ?? ''))} />;
            case 'textarea':
                return (
                    <>
                        <ForgeTextarea name={`values.${field.key}`} label={field.label} rows={4} state={error ? 'danger' : null} aria-invalid={error ? true : undefined} value={String(value ?? '')} onChange={update} />
                        <FieldError text={error} />
                    </>
                );
            default:
                return (
                    <ForgeInput
                        name={`values.${field.key}`}
                        label={field.label}
                        type={settingInputType(field)}
                        step={field.type === 'decimal' ? 'any' : undefined}
                        autoComplete={field.secret ? 'new-password' : undefined}
                        value={String(value ?? '')}
                        error={error}
                        onChange={update}
                    />
                );
        }
    };

    const sourceText = (field: SettingField) => (field.source === 'company' ? t.sourceCompany : field.source === 'global' ? t.sourceGlobal : t.sourceDefault);

    let body: ReactNode;
    if (failure && !payload) {
        body = <ForgeAlert type="danger">{failure}</ForgeAlert>;
    } else if (!payload) {
        body = (
            <div className="pc-state" role="status">
                <ForgeSpinner label={t.loading} /> {t.loading}
            </div>
        );
    } else if (!payload.installed) {
        body = <ForgeAlert type="warn">{t.notInstalled}</ForgeAlert>;
    } else if (payload.groups.length === 0) {
        body = <ForgeEmpty title={t.none} description={t.noneHint} />;
    } else {
        const global = payload.scope === 'global';
        body = (
            <>
                {failure && <ForgeAlert type="danger">{failure}</ForgeAlert>}
                {payload.per_company && payload.can_edit_global && (
                    <div role="group" aria-label={t.scope} style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 16 }}>
                        <ForgeButton size="sm" color={global ? 'dark' : 'primary'} flat={global} aria-pressed={!global} onClick={() => void load('company')}>
                            {t.scopeCompany}
                        </ForgeButton>
                        <ForgeButton size="sm" color={global ? 'primary' : 'dark'} flat={!global} aria-pressed={global} onClick={() => void load('global')}>
                            {t.scopeGlobal}
                        </ForgeButton>
                    </div>
                )}
                <form onSubmit={(event) => void submit(event)} noValidate style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                    {payload.groups.map((group) => (
                        <section key={group.name} style={sectionStyle} aria-labelledby={`ptah-settings-${group.name}`}>
                            <h2 id={`ptah-settings-${group.name}`} style={{ margin: '0 0 16px', fontSize: 14, fontWeight: 600, color: 'var(--pc-text-strong)' }}>
                                {group.name}
                            </h2>
                            <div className="pc-form">
                                {group.fields.map((field) => (
                                    <div key={field.key} className={field.type === 'textarea' ? 'pc-form-field--wide' : undefined}>
                                        {control(field)}
                                        <div style={{ ...mutedStyle, display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 4 }}>
                                            {field.help && <span>{field.help}</span>}
                                            {!global && payload.per_company && <span>{sourceText(field)}</span>}
                                            {!global && payload.per_company && field.source === 'company' && (
                                                <button type="button" className="pc-link" style={{ background: 'none', border: 0, padding: 0, font: 'inherit', cursor: 'pointer', textDecoration: 'underline' }} aria-label={`${t.useGlobal}: ${field.label}`} onClick={() => void toGlobal(field.key)}>
                                                    {t.useGlobal}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    ))}
                    <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <ForgeButton type="submit" loading={saving}>
                            {saving ? t.saving : t.save}
                        </ForgeButton>
                    </div>
                </form>
            </>
        );
    }

    return (
        <div className="pf pc-root">
            <ForgePageHeader title={t.settingsTitle} subtitle={t.settingsSubtitle} />
            {body}
            <ForgeToastHost />
        </div>
    );
}
