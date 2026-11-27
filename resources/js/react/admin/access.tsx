/**
 * The users-ACL screen and the permission audit log for React, over the
 * access API. The rules — MASTER only, the host's user scope, "MASTER cannot
 * be removed", the user form's validation — are the server's (the Livewire
 * components UserPermissionList and AuditList); these screens only ask and show.
 */
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { ApiError, PtahApi } from '../../core';
import {
    accessWords,
    addAccessUserRole,
    auditActionLabel,
    companyLabel,
    emptyAuditFilters,
    getAccessUser,
    getAccessUserRoles,
    listAccessUsers,
    listAudit,
    removeAccessUserRole,
    saveAccessUser,
    sendAccessPasswordLink,
} from '../../core/admin/access';
import type { AccessAnswer, AccessMeta, AccessUserForm, AccessUserRoles, AccessUsersPage, AccessWords, AuditFilters, AuditPage } from '../../core/admin/access';
import { ForgeAlert, ForgeAvatar, ForgeButton, ForgeEmpty, ForgeInput, ForgeModal, ForgePageHeader, ForgePagination, ForgeSelect, ForgeSwitch, forgeToast, ForgeToastHost } from '../forge';

export interface AccessScreenProps {
    api?: PtahApi;
    locale?: string;
}

const toast = (answer: AccessAnswer) => (answer.messages ?? []).forEach((message) => forgeToast(message.text, message.level));

/** What a failed request means to the person: refused, or something broke. */
const failure = (t: AccessWords, error: unknown) => (error instanceof ApiError && (error.status === 403 || error.status === 401) ? t.forbidden : t.error);

/** A value that settles 300 ms after the last change, like `wire:model.live.debounce.300ms`. */
function useDebounced<T>(value: T, delay = 300): T {
    const [settled, setSettled] = useState(value);

    useEffect(() => {
        const timer = setTimeout(() => setSettled(value), delay);

        return () => clearTimeout(timer);
    }, [value, delay]);

    return settled;
}

function Pager({ meta, t, onPage }: { meta: AccessMeta; t: AccessWords; onPage: (page: number) => void }) {
    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <div className="pc-footer">
            <span>{t.pagination(meta.from ?? 0, meta.to ?? 0, meta.total)}</span>
            <div className="pc-pager">
                <ForgePagination page={meta.current_page} lastPage={meta.last_page} onChange={onPage} />
            </div>
        </div>
    );
}

/** A yes/no question before an action that cannot be undone from the screen. */
function Confirm({ question, t, onConfirm, onClose }: { question: { text: string; run: () => void } | null; t: AccessWords; onConfirm: () => void; onClose: () => void }) {
    return (
        <ForgeModal
            open={question !== null}
            onClose={onClose}
            title={t.confirm}
            size="sm"
            footer={
                <>
                    <ForgeButton color="dark" flat onClick={onClose}>
                        {t.cancel}
                    </ForgeButton>
                    <ForgeButton color="danger" onClick={onConfirm}>
                        {t.confirm}
                    </ForgeButton>
                </>
            }
        >
            <p>{question?.text}</p>
        </ForgeModal>
    );
}

// ── Users ───────────────────────────────────────────────────────────────────

const blankForm = (sendLink: boolean): AccessUserForm => ({ name: '', email: '', password: '', send_link: sendLink });

export function PtahUserPermissions({ api, locale }: AccessScreenProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = accessWords(locale);

    const [search, setSearch] = useState('');
    const [role, setRole] = useState(0);
    const [page, setPage] = useState(1);
    const [listing, setListing] = useState<AccessUsersPage | null>(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [reloads, setReloads] = useState(0);
    const typed = useDebounced(search);

    // The user form
    const [formOpen, setFormOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [form, setForm] = useState<AccessUserForm>(blankForm(false));
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    // The roles modal
    const [rolesOf, setRolesOf] = useState<AccessUserRoles | null>(null);
    const [newRoleId, setNewRoleId] = useState(0);
    const [newCompanyId, setNewCompanyId] = useState(0);
    const [roleError, setRoleError] = useState<string | null>(null);
    const [adding, setAdding] = useState(false);

    const [question, setQuestion] = useState<{ text: string; run: () => void } | null>(null);

    const reload = () => setReloads((value) => value + 1);

    useEffect(() => {
        let alive = true;
        setLoading(true);
        listAccessUsers(client, { search: typed, role, page })
            .then((value) => {
                if (alive) {
                    setListing(value);
                    setLoadError(null);
                }
            })
            .catch((error) => alive && setLoadError(failure(t, error)))
            .finally(() => alive && setLoading(false));

        return () => {
            alive = false;
        };
    }, [client, typed, role, page, reloads]); // eslint-disable-line react-hooks/exhaustive-deps

    // A new search or filter starts from the first page.
    useEffect(() => setPage(1), [typed, role]);

    const openNew = () => {
        setEditingId(null);
        setForm(blankForm(listing?.can_send_link ?? false));
        setFormErrors({});
        setFormOpen(true);
    };

    const openEdit = async (id: number) => {
        try {
            const user = await getAccessUser(client, id);
            setEditingId(user.id);
            setForm({ name: user.name, email: user.email, password: '', send_link: false });
            setFormErrors({});
            setFormOpen(true);
        } catch (error) {
            forgeToast(failure(t, error), 'danger');
        }
    };

    const save = async () => {
        setSaving(true);
        try {
            const answer = await saveAccessUser(client, editingId, form);
            setFormErrors(answer.errors ?? {});
            if (answer.ok) {
                toast(answer);
                setFormOpen(false);
                reload();
            }
        } catch (error) {
            forgeToast(failure(t, error), 'danger');
        } finally {
            setSaving(false);
        }
    };

    const sendLink = (id: number, email: string) =>
        setQuestion({
            text: t.linkConfirm(email),
            run: () => {
                sendAccessPasswordLink(client, id)
                    .then(toast)
                    .catch((error) => forgeToast(failure(t, error), 'danger'));
            },
        });

    const openRoles = async (id: number) => {
        try {
            setRolesOf(await getAccessUserRoles(client, id));
            setNewRoleId(0);
            setNewCompanyId(0);
            setRoleError(null);
        } catch (error) {
            forgeToast(failure(t, error), 'danger');
        }
    };

    // Every role change answers the bindings as they now are.
    const took = (answer: AccessAnswer & Partial<AccessUserRoles>) => {
        toast(answer);
        setRoleError(answer.ok ? null : (answer.errors?.role_id ?? answer.error ?? null));
        if (answer.assigned && rolesOf) {
            setRolesOf({ user: answer.user ?? rolesOf.user, assigned: answer.assigned });
        }
        if (answer.ok) {
            reload();
        }
    };

    const addRole = async () => {
        if (!rolesOf) {
            return;
        }
        setAdding(true);
        try {
            const answer = await addAccessUserRole(client, rolesOf.user.id, newRoleId, newCompanyId);
            took(answer);
            if (answer.ok) {
                setNewRoleId(0);
                setNewCompanyId(0);
            }
        } catch (error) {
            forgeToast(failure(t, error), 'danger');
        } finally {
            setAdding(false);
        }
    };

    const removeRole = (userRoleId: number) =>
        setQuestion({
            text: t.removeRoleConfirm,
            run: () => {
                if (rolesOf) {
                    removeAccessUserRole(client, rolesOf.user.id, userRoleId)
                        .then(took)
                        .catch((error) => forgeToast(failure(t, error), 'danger'));
                }
            },
        });

    const roleOptions = (listing?.roles ?? []).map((item) => ({ value: item.id, label: item.master ? `${item.name} (${t.master})` : item.name }));
    const companyOptions = [{ value: 0, label: t.global }, ...(listing?.companies ?? []).map((item) => ({ value: item.id, label: item.name }))];

    let body: ReactNode;
    if (loadError) {
        body = <ForgeAlert type="danger">{loadError}</ForgeAlert>;
    } else if (!listing) {
        body = (
            <div className="pf pc-state" role="status">
                {t.loading}
            </div>
        );
    } else {
        body = (
            <>
                <div className="pf pf-table-wrap" aria-busy={loading || undefined}>
                    <table className="pf-table">
                        <thead>
                            <tr>
                                <th scope="col">{t.colUser}</th>
                                <th scope="col">{t.colRoles}</th>
                                <th scope="col" className="pc-col-actions">
                                    {t.colActions}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {listing.data.length === 0 && (
                                <tr>
                                    <td colSpan={3}>
                                        <ForgeEmpty title={t.usersEmpty} description={t.usersEmptyHint} />
                                    </td>
                                </tr>
                            )}
                            {listing.data.map((user) => (
                                <tr key={user.id}>
                                    <td>
                                        <div className="pa-flex-center-g3">
                                            <ForgeAvatar text={user.name} size="sm" />
                                            <div>
                                                <div>{user.name}</div>
                                                <small>{user.email}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div className="pa-flex-wrap-g1">
                                            {user.roles.length === 0 && <small>{t.noRoles}</small>}
                                            {user.roles.map((chip) => (
                                                <span key={chip.id} className="pa-role-chip" data-master={chip.master || undefined}>
                                                    {chip.name}
                                                    {chip.master && <small>({t.master})</small>}
                                                    {chip.company && <small>· {chip.company}</small>}
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="pc-col-actions">
                                        <ForgeButton size="sm" flat onClick={() => void openEdit(user.id)} aria-label={`${t.edit} ${user.name}`}>
                                            {t.edit}
                                        </ForgeButton>
                                        <ForgeButton size="sm" flat onClick={() => void openRoles(user.id)} aria-label={`${t.manageAccess} ${user.name}`}>
                                            {t.manageAccess}
                                        </ForgeButton>
                                        {listing.can_send_link && (
                                            <ForgeButton size="sm" flat onClick={() => sendLink(user.id, user.email)} aria-label={`${t.sendLink} ${user.name}`}>
                                                {t.sendLink}
                                            </ForgeButton>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pager meta={listing.meta} t={t} onPage={setPage} />
            </>
        );
    }

    return (
        <div className="pf">
            <ForgePageHeader title={t.usersTitle} subtitle={t.usersSubtitle}>
                <ForgeButton size="sm" onClick={openNew}>
                    {t.newUser}
                </ForgeButton>
            </ForgePageHeader>

            <div className="pc-toolbar">
                <div className="pc-search">
                    <ForgeInput type="search" aria-label={t.searchUsers} placeholder={t.searchUsers} value={search} onChange={setSearch} />
                </div>
                <div className="pc-spacer" />
                <ForgeSelect label={t.filterRole} options={[{ value: 0, label: t.allRoles }, ...roleOptions]} selected={role} onChange={(value) => setRole(Number(value) || 0)} />
            </div>

            {body}

            <ForgeModal
                open={formOpen}
                onClose={() => setFormOpen(false)}
                title={editingId !== null ? t.editUserTitle : t.newUserTitle}
                footer={
                    <>
                        <ForgeButton color="dark" flat onClick={() => setFormOpen(false)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton loading={saving} onClick={() => void save()}>
                            {t.save}
                        </ForgeButton>
                    </>
                }
            >
                <div className="pa-form">
                    <ForgeInput label={t.fieldName} name="name" required value={form.name} error={formErrors.name} onChange={(value) => setForm({ ...form, name: value })} />
                    <ForgeInput label={t.fieldEmail} name="email" type="email" required value={form.email} error={formErrors.email} onChange={(value) => setForm({ ...form, email: value })} />
                    <ForgeInput
                        label={t.fieldPassword}
                        name="password"
                        type="password"
                        autoComplete="new-password"
                        value={form.password}
                        error={formErrors.password}
                        message={editingId !== null ? t.passwordHintEdit : t.passwordHintNew}
                        onChange={(value) => setForm({ ...form, password: value })}
                    />
                    {listing?.can_send_link && <ForgeSwitch label={t.sendLinkOnSave} name="send_link" checked={form.send_link} onChange={(value) => setForm({ ...form, send_link: value })} />}
                </div>
            </ForgeModal>

            <ForgeModal
                open={rolesOf !== null}
                onClose={() => setRolesOf(null)}
                title={`${t.accessOf} ${rolesOf?.user.name ?? ''}`}
                size="lg"
                footer={
                    <ForgeButton color="light" onClick={() => setRolesOf(null)}>
                        {t.close}
                    </ForgeButton>
                }
            >
                {rolesOf && (
                    <div className="pa-section">
                        <h3>{t.assignedRoles}</h3>
                        {rolesOf.assigned.length === 0 ? (
                            <p>{t.noAssigned}</p>
                        ) : (
                            <ul className="pf pf-list">
                                {rolesOf.assigned.map((binding) => (
                                    <li key={binding.id} className="pf-list-item">
                                        <span className="pf-list-text">
                                            <span className="pf-list-title">
                                                {binding.role_name}
                                                {binding.role_master && ` (${t.master})`}
                                            </span>
                                            <span className="pf-list-sub">{companyLabel(t, binding.company_name)}</span>
                                        </span>
                                        {binding.role_master ? (
                                            <small>{t.protectedRole}</small>
                                        ) : (
                                            <ForgeButton size="sm" color="danger" flat onClick={() => removeRole(binding.id)} aria-label={`${t.remove} ${binding.role_name}`}>
                                                {t.remove}
                                            </ForgeButton>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}

                        <h3>{t.addRole}</h3>
                        <ForgeSelect label={t.role} placeholder={t.choose} options={[{ value: 0, label: t.choose }, ...roleOptions]} selected={newRoleId} error={roleError} onChange={(value) => setNewRoleId(Number(value) || 0)} />
                        <ForgeSelect label={t.company} options={companyOptions} selected={newCompanyId} onChange={(value) => setNewCompanyId(Number(value) || 0)} />
                        <div>
                            <ForgeButton size="sm" loading={adding} onClick={() => void addRole()}>
                                {t.add}
                            </ForgeButton>
                        </div>
                    </div>
                )}
            </ForgeModal>

            <Confirm
                question={question}
                t={t}
                onClose={() => setQuestion(null)}
                onConfirm={() => {
                    question?.run();
                    setQuestion(null);
                }}
            />
            <ForgeToastHost />
        </div>
    );
}

// ── Audit ───────────────────────────────────────────────────────────────────

export function PtahAudit({ api, locale }: AccessScreenProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = accessWords(locale);

    const [filters, setFilters] = useState<AuditFilters>(emptyAuditFilters());
    const [page, setPage] = useState(1);
    const [listing, setListing] = useState<AuditPage | null>(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);
    const search = useDebounced(filters.search);
    // The search waits for the typing to stop; the other filters apply at once.
    const applied = useMemo(() => ({ ...filters, search }), [filters.result, filters.action, filters.date_from, filters.date_to, search]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        let alive = true;
        setLoading(true);
        listAudit(client, applied, page)
            .then((value) => {
                if (alive) {
                    setListing(value);
                    setLoadError(null);
                }
            })
            .catch((error) => alive && setLoadError(failure(t, error)))
            .finally(() => alive && setLoading(false));

        return () => {
            alive = false;
        };
    }, [client, applied, page]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => setPage(1), [applied]);

    const set = (key: keyof AuditFilters, value: string) => setFilters({ ...filters, [key]: value });
    const filtered = applied.search !== '' || applied.result !== '' || applied.action !== '';

    let body: ReactNode;
    if (loadError) {
        body = <ForgeAlert type="danger">{loadError}</ForgeAlert>;
    } else if (!listing) {
        body = (
            <div className="pf pc-state" role="status">
                {t.loading}
            </div>
        );
    } else {
        body = (
            <>
                <div className="pf pf-table-wrap" aria-busy={loading || undefined}>
                    <table className="pf-table">
                        <thead>
                            <tr>
                                <th scope="col">{t.colDatetime}</th>
                                <th scope="col">{t.colUser}</th>
                                <th scope="col">{t.colResource}</th>
                                <th scope="col">{t.action}</th>
                                <th scope="col">{t.result}</th>
                                <th scope="col">{t.colIp}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {listing.data.length === 0 && (
                                <tr>
                                    <td colSpan={6}>
                                        <ForgeEmpty title={filtered ? t.auditEmptyFiltered : t.auditEmptyTitle} description={filtered ? t.auditEmptyFilteredHint : t.auditEmptyHint} />
                                    </td>
                                </tr>
                            )}
                            {listing.data.map((row) => (
                                <tr key={row.id} data-result={row.result}>
                                    <td>
                                        <small>{row.created_at_display}</small>
                                    </td>
                                    <td>
                                        <code>#{row.user_id ?? '—'}</code>
                                    </td>
                                    <td>
                                        <code>{row.resource_key ?? '—'}</code>
                                    </td>
                                    <td>{auditActionLabel(t, row.action)}</td>
                                    <td>{row.result === 'granted' ? t.granted : t.denied}</td>
                                    <td>
                                        <code>{row.ip_address ?? '—'}</code>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pager meta={listing.meta} t={t} onPage={setPage} />
            </>
        );
    }

    return (
        <div className="pf">
            <ForgePageHeader title={t.auditTitle} subtitle={t.auditSubtitle} />

            <div className="pc-toolbar">
                <div className="pc-search">
                    <ForgeInput type="search" aria-label={t.auditSearch} placeholder={t.auditSearch} value={filters.search} onChange={(value) => set('search', value)} />
                </div>
                <ForgeSelect
                    label={t.result}
                    placeholder={t.allResults}
                    options={[
                        { value: '', label: t.allResults },
                        { value: 'granted', label: t.granted },
                        { value: 'denied', label: t.denied },
                    ]}
                    selected={filters.result}
                    onChange={(value) => set('result', String(value ?? ''))}
                />
                <ForgeSelect
                    label={t.action}
                    placeholder={t.allActions}
                    options={[{ value: '', label: t.allActions }, ...Object.keys(t.actions).map((key) => ({ value: key, label: t.actions[key] }))]}
                    selected={filters.action}
                    onChange={(value) => set('action', String(value ?? ''))}
                />
                <ForgeInput label={t.from} type="date" value={filters.date_from} onChange={(value) => set('date_from', value)} />
                <ForgeInput label={t.to} type="date" value={filters.date_to} onChange={(value) => set('date_to', value)} />
                <ForgeButton size="sm" color="light" onClick={() => setFilters(emptyAuditFilters())}>
                    {t.clear}
                </ForgeButton>
            </div>

            {body}
        </div>
    );
}
