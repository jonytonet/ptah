/**
 * The company area for React — companies, departments and the company
 * switcher — over the admin API. The rules (who may see these screens, the
 * validation, "the default company cannot be deleted", which companies a user
 * may switch to) are the server's: the Livewire components run behind it.
 */
import { useCallback, useEffect, useId, useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { PtahApi } from '../../core';
import { badgeColor, companies, companySwitcher, companyWords, departments, nextSort, rangeText, switchCompany, TAX_TYPES } from '../../core/admin/company';
import type { AdminListing, AdminListQuery, AdminResource, AdminResult, CompanyForm, CompanyRow, CompanyWords, DepartmentForm, DepartmentRow, SwitcherState } from '../../core/admin/company';
import { ForgeAlert, ForgeButton, ForgeEmpty, ForgeInput, ForgeModal, ForgePageHeader, ForgePagination, ForgeSelect, ForgeSwitch, ForgeTextarea, forgeToast, ForgeToastHost } from '../forge';
import { Icon } from '../icons';

export interface CompanyScreenProps {
    api?: PtahApi;
    locale?: string;
}

const toast = (messages: { text: string; level: string }[]) => messages.forEach((message) => forgeToast(message.text, message.level));
const failure = (error: unknown) => (error instanceof Error ? error.message : 'Error');

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

/** A small coloured tag (status, "default"). */
function Pill({ color, children }: { color: string; children: ReactNode }) {
    return (
        <span className="pf pf-badge" data-color={color}>
            <span className="pf-badge-mark" style={{ position: 'static' }}>
                {children}
            </span>
        </span>
    );
}

// ── The list screen both resources share ────────────────────────────────────

interface Column<Row> {
    key: string;
    label: ReactNode;
    /** The server's sort key, when the column sorts. */
    sort?: string;
    align?: 'center';
    render: (row: Row) => ReactNode;
}

interface AdminScreenProps<Row extends { id: number }, Form> {
    client: PtahApi;
    resource: AdminResource<Row, Form>;
    t: CompanyWords;
    title: string;
    subtitle: string;
    newLabel: string;
    searchPlaceholder: string;
    columns: Column<Row>[];
    emptyTitle: string;
    emptyHint: (searching: boolean) => string;
    modalTitle: (editing: boolean) => string;
    modalSize: 'md' | 'lg';
    deleteText: string;
    canDelete?: (row: Row) => boolean;
    /** The fields of the form. */
    fields: (form: Form, set: (changes: Partial<Form>) => void, errors: Record<string, string>) => ReactNode;
    /** Each listing answer, for what the resource adds to it (e.g. the tax types). */
    onListing?: (listing: AdminListing<Row>) => void;
}

function AdminScreen<Row extends { id: number }, Form>(props: AdminScreenProps<Row, Form>) {
    const { client, resource, t } = props;
    const [query, setQuery] = useState<AdminListQuery>({ search: '', sort: 'name', direction: 'asc', page: 1 });
    const [search, setSearch] = useState('');
    const [listing, setListing] = useState<AdminListing<Row> | null>(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [modal, setModal] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [form, setForm] = useState<Form>(() => resource.blank());
    const [answer, setAnswer] = useState<AdminResult | null>(null);
    const [saving, setSaving] = useState(false);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);
    const formId = useId();

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const next = await resource.list(client, query);
            setListing(next);
            props.onListing?.(next);
            setLoadError(null);
        } catch (error) {
            setLoadError(failure(error));
        } finally {
            setLoading(false);
        }
    }, [client, resource, query]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        void load();
    }, [load]);

    // The Livewire screen searches 300 ms after the last key, from page 1.
    useEffect(() => {
        if (search === query.search) {
            return;
        }
        const timer = setTimeout(() => setQuery((current) => ({ ...current, search, page: 1 })), 300);

        return () => clearTimeout(timer);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    const openCreate = () => {
        setEditingId(null);
        setForm(resource.blank());
        setAnswer(null);
        setModal(true);
    };

    const openEdit = async (id: number) => {
        try {
            const record = await resource.show(client, id);
            setEditingId(id);
            setForm(record.form);
            setAnswer(null);
            setModal(true);
        } catch (error) {
            forgeToast(failure(error), 'danger');
        }
    };

    const save = async (event?: FormEvent) => {
        event?.preventDefault();
        setSaving(true);
        try {
            const result = await resource.save(client, editingId, form);
            setAnswer(result);
            if (result.ok) {
                setModal(false);
                toast(result.messages);
                await load();
            }
        } catch (error) {
            setAnswer({ ok: false, error: failure(error), errors: {}, messages: [] });
        } finally {
            setSaving(false);
        }
    };

    const remove = async () => {
        if (deleteId === null) {
            return;
        }
        setDeleting(true);
        try {
            const result = await resource.remove(client, deleteId);
            if (result.ok) {
                toast(result.messages);
            } else {
                forgeToast(result.error ?? t.loadError, 'danger');
            }
            await load();
        } catch (error) {
            forgeToast(failure(error), 'danger');
        } finally {
            setDeleting(false);
            setDeleteId(null);
        }
    };

    const rows = listing?.data ?? [];
    const meta = listing?.meta;
    const errors = answer?.errors ?? {};

    return (
        <div className="pf pc-root">
            <ForgePageHeader title={props.title} subtitle={props.subtitle} />

            <div className="pc-toolbar">
                <ForgeButton size="sm" onClick={openCreate}>
                    + {props.newLabel}
                </ForgeButton>
                <div className="pc-search">
                    <ForgeInput type="search" aria-label={t.search} placeholder={props.searchPlaceholder} value={search} onChange={setSearch} />
                </div>
            </div>

            {loadError && <ForgeAlert type="danger">{loadError}</ForgeAlert>}

            <div className="pc-table-wrap" aria-busy={loading}>
                <table className="pc-table">
                    <thead>
                        <tr>
                            {props.columns.map((column) => {
                                const sorted = column.sort !== undefined && query.sort === column.sort;

                                return (
                                    <th key={column.key} className={column.align === 'center' ? 'pc-align-center' : undefined} aria-sort={sorted ? (query.direction === 'asc' ? 'ascending' : 'descending') : undefined}>
                                        {column.sort !== undefined ? (
                                            <button type="button" className="pc-th-btn" onClick={() => setQuery(nextSort(query, column.sort!))}>
                                                {column.label}
                                                <span className={`pc-sort${sorted ? ' pc-sort--on' : ''}`} aria-hidden="true">
                                                    {sorted && query.direction === 'desc' ? '▼' : '▲'}
                                                </span>
                                            </button>
                                        ) : (
                                            column.label
                                        )}
                                    </th>
                                );
                            })}
                            <th className="pc-align-center">{t.actions}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {listing === null && loading && (
                            <tr>
                                <td className="pc-empty" colSpan={props.columns.length + 1} role="status">
                                    {t.loading}
                                </td>
                            </tr>
                        )}
                        {listing !== null && rows.length === 0 && (
                            <tr>
                                <td colSpan={props.columns.length + 1}>
                                    <ForgeEmpty title={props.emptyTitle} description={props.emptyHint(query.search !== '')} />
                                </td>
                            </tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id}>
                                {props.columns.map((column) => (
                                    <td key={column.key} className={column.align === 'center' ? 'pc-align-center' : undefined}>
                                        {column.render(row)}
                                    </td>
                                ))}
                                <td className="pc-align-center" style={{ whiteSpace: 'nowrap' }}>
                                    <button type="button" className="pc-icon-btn" title={t.edit} aria-label={t.edit} onClick={() => void openEdit(row.id)}>
                                        <EditIcon />
                                    </button>
                                    {(props.canDelete?.(row) ?? true) && (
                                        <button type="button" className="pc-icon-btn pc-icon-btn--danger" title={t.delete} aria-label={t.delete} onClick={() => setDeleteId(row.id)}>
                                            <TrashIcon />
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {meta && meta.last_page > 1 && (
                <div className="pc-footer">
                    <span>{rangeText(t, meta)}</span>
                    <span className="pc-spacer" />
                    <ForgePagination page={meta.page} lastPage={meta.last_page} onChange={(page) => setQuery({ ...query, page })} />
                </div>
            )}

            <ForgeModal
                open={modal}
                onClose={() => setModal(false)}
                title={props.modalTitle(editingId !== null)}
                size={props.modalSize}
                footer={
                    <>
                        <ForgeButton color="light" onClick={() => setModal(false)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton type="submit" form={formId} loading={saving}>
                            {saving ? t.saving : t.save}
                        </ForgeButton>
                    </>
                }
            >
                <form id={formId} className="pc-form" onSubmit={(event) => void save(event)} noValidate>
                    {answer && !answer.ok && answer.error && (
                        <div className="pc-form-field--wide">
                            <ForgeAlert type="danger">{answer.error}</ForgeAlert>
                        </div>
                    )}
                    {props.fields(form, (changes) => setForm((current) => ({ ...current, ...changes })), errors)}
                </form>
            </ForgeModal>

            <ForgeModal
                open={deleteId !== null}
                onClose={() => setDeleteId(null)}
                title={t.deleteTitle}
                size="sm"
                footer={
                    <>
                        <ForgeButton color="light" onClick={() => setDeleteId(null)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton color="danger" loading={deleting} onClick={() => void remove()}>
                            {t.delete}
                        </ForgeButton>
                    </>
                }
            >
                <p>{props.deleteText}</p>
            </ForgeModal>

            <ForgeToastHost />
        </div>
    );
}

const statusPill = (t: CompanyWords, active: boolean) => <Pill color={active ? 'success' : 'danger'}>{active ? t.active : t.inactive}</Pill>;

// ── Companies ───────────────────────────────────────────────────────────────

export function PtahCompanies({ api, locale }: CompanyScreenProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = companyWords(locale);
    const [taxTypes, setTaxTypes] = useState<string[]>(TAX_TYPES);

    const columns: Column<CompanyRow>[] = [
        {
            key: 'abbr',
            label: t.companyAbbr,
            align: 'center',
            render: (row) => (
                <span className="pf pf-avatar" data-size="sm" data-color={badgeColor(row.id)} style={{ borderRadius: 6, fontSize: 10 }} aria-hidden="true">
                    {row.label_display}
                </span>
            ),
        },
        {
            key: 'name',
            label: t.name,
            sort: 'name',
            render: (row) => (
                <>
                    <strong>{row.name}</strong> {row.is_default && <Pill color="warn">{t.defaultTag}</Pill>}
                </>
            ),
        },
        { key: 'email', label: t.companyEmail, render: (row) => row.email ?? '—' },
        { key: 'tax', label: t.companyTax, render: (row) => (row.tax_id ? `${(row.tax_type ?? '').toUpperCase()} ${row.tax_id}` : '—') },
        {
            key: 'default',
            label: t.companyDefault,
            align: 'center',
            render: (row) => (
                <span role="img" aria-label={row.is_default ? t.yes : t.no}>
                    {row.is_default ? '✓' : '—'}
                </span>
            ),
        },
        { key: 'status', label: t.status, align: 'center', render: (row) => statusPill(t, row.is_active) },
    ];

    return (
        <AdminScreen<CompanyRow, CompanyForm>
            client={client}
            resource={companies}
            t={t}
            title={t.companyTitle}
            subtitle={t.companySubtitle}
            newLabel={t.companyNew}
            searchPlaceholder={t.companySearch}
            columns={columns}
            emptyTitle={t.companyEmpty}
            emptyHint={(searching) => (searching ? t.companyEmptyAdjust : t.companyEmptyAdd)}
            modalTitle={(editing) => (editing ? t.companyModalEdit : t.companyModalNew)}
            modalSize="lg"
            deleteText={t.companyDeleteText}
            canDelete={(row) => !row.is_default}
            onListing={(listing) => setTaxTypes((listing as { tax_types?: string[] }).tax_types ?? TAX_TYPES)}
            fields={(form, set, errors) => (
                <>
                    <ForgeInput label={t.name} required placeholder={t.companyNamePh} value={form.name} error={errors.name} onChange={(name) => set({ name })} />
                    <ForgeInput label={t.companyLabel} placeholder="ACME" maxLength={4} message={t.companyLabelHint} value={form.label} error={errors.label} onChange={(label) => set({ label })} />
                    <ForgeInput label={t.companyEmail} type="email" placeholder={t.companyEmailPh} value={form.email} error={errors.email} onChange={(email) => set({ email })} />
                    <ForgeInput label={t.companyPhone} placeholder={t.companyPhonePh} value={form.phone} error={errors.phone} onChange={(phone) => set({ phone })} />
                    <ForgeSelect
                        label={t.companyDocType}
                        options={taxTypes.map((type) => ({ value: type, label: type.toUpperCase() }))}
                        selected={form.tax_type}
                        error={errors.tax_type}
                        onChange={(value) => set({ tax_type: String(value ?? '') })}
                    />
                    <ForgeInput label={t.companyDocNumber} placeholder="00.000.000/0001-00" value={form.tax_id} error={errors.tax_id} onChange={(tax_id) => set({ tax_id })} />
                    <ForgeSwitch label={t.companyIsActive} checked={form.is_active} onChange={(is_active) => set({ is_active })} />
                    <ForgeSwitch label={t.companyIsDefault} checked={form.is_default} onChange={(is_default) => set({ is_default })} />
                </>
            )}
        />
    );
}

// ── Departments ─────────────────────────────────────────────────────────────

export function PtahDepartments({ api, locale }: CompanyScreenProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = companyWords(locale);

    const columns: Column<DepartmentRow>[] = [
        { key: 'name', label: t.name, sort: 'name', render: (row) => <strong>{row.name}</strong> },
        { key: 'description', label: t.deptDescription, render: (row) => row.description ?? '—' },
        { key: 'roles', label: t.deptRoles, align: 'center', render: (row) => <Pill color="light">{row.roles_count}</Pill> },
        { key: 'status', label: t.status, align: 'center', render: (row) => statusPill(t, row.is_active) },
    ];

    return (
        <AdminScreen<DepartmentRow, DepartmentForm>
            client={client}
            resource={departments}
            t={t}
            title={t.deptTitle}
            subtitle={t.deptSubtitle}
            newLabel={t.deptNew}
            searchPlaceholder={t.deptSearch}
            columns={columns}
            emptyTitle={t.deptEmpty}
            emptyHint={() => t.deptEmptyHint}
            modalTitle={(editing) => (editing ? t.deptModalEdit : t.deptModalNew)}
            modalSize="md"
            deleteText={t.deptDeleteText}
            fields={(form, set, errors) => (
                <>
                    <div className="pc-form-field--wide">
                        <ForgeInput label={t.name} required value={form.name} error={errors.name} onChange={(name) => set({ name })} />
                    </div>
                    <div className="pc-form-field--wide">
                        <ForgeTextarea label={t.deptDescription} rows={3} value={form.description} state={errors.description ? 'danger' : null} onChange={(description) => set({ description })} />
                        {errors.description && (
                            <p className="pf-field-error" role="alert">
                                {errors.description}
                            </p>
                        )}
                    </div>
                    <div className="pc-form-field--wide">
                        <ForgeSwitch label={t.deptIsActive} checked={form.is_active} onChange={(is_active) => set({ is_active })} />
                    </div>
                </>
            )}
        />
    );
}

// ── Company switcher ────────────────────────────────────────────────────────

export interface CompanySwitcherProps {
    api?: PtahApi;
    locale?: string;
    /**
     * 'inline' — the active company's name and one tab per company, for a top
     * bar. 'stacked' — a vertical list of menu items, for inside a dropdown.
     */
    layout?: 'inline' | 'stacked';
    /**
     * After a switch. Defaults to reloading the page, as the Livewire switcher
     * does, so every screen reads the new company.
     */
    onSwitched?: (id: number) => void;
}

/** Shows nothing when the user has fewer than two companies to choose from. */
export function PtahCompanySwitcher({ api, locale, layout = 'inline', onSwitched }: CompanySwitcherProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = companyWords(locale);
    const [state, setState] = useState<SwitcherState | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        let alive = true;
        void companySwitcher(client)
            .then((value) => alive && setState(value))
            .catch(() => undefined);

        return () => {
            alive = false;
        };
    }, [client]);

    if (!state || state.companies.length < 2) {
        return null;
    }

    const pick = async (id: number) => {
        if (busy) {
            return;
        }
        setBusy(true);
        try {
            const answer = await switchCompany(client, id);
            setState({ companies: answer.companies, active_id: answer.active_id });
            (onSwitched ?? (() => window.location.reload()))(id);
        } catch (error) {
            forgeToast(failure(error), 'danger');
        } finally {
            setBusy(false);
        }
    };

    const active = state.companies.find((company) => company.id === state.active_id);

    if (layout === 'stacked') {
        return (
            <div className="pl-switcher-stack" role="group" aria-label={t.selectCompany} aria-busy={busy || undefined}>
                <p className="pl-switcher-heading">{t.selectCompany}</p>
                {state.companies.map((company) => {
                    const isActive = company.id === state.active_id;

                    return (
                        <button key={company.id} type="button" className="pl-switcher-item" aria-current={isActive ? 'true' : 'false'} onClick={() => void pick(company.id)}>
                            {/* The active one is marked by an icon, not only by colour. */}
                            {isActive ? <Icon name="check" size={16} className="pl-switcher-check" /> : <span className="pl-switcher-check" aria-hidden="true" />}
                            <span className="pl-switcher-label">{company.name}</span>
                            <span className="pl-switcher-code">{company.label}</span>
                        </button>
                    );
                })}
            </div>
        );
    }

    // company-switcher.blade.php, inline: the active company's name, a rule, one tab per company.
    return (
        <div className="pl-switcher" aria-busy={busy || undefined}>
            <span className="pl-switcher-name">{active?.name ?? ''}</span>
            <span className="pl-switcher-sep" aria-hidden="true" />
            <nav className="pl-switcher-bar" role="tablist" aria-label={t.selectCompany}>
                {state.companies.map((company) => (
                    <button key={company.id} type="button" role="tab" className="pl-switcher-tab" title={company.name} aria-selected={company.id === state.active_id} onClick={() => void pick(company.id)}>
                        {company.label}
                    </button>
                ))}
            </nav>
        </div>
    );
}
