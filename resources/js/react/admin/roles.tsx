/**
 * The access-control screens for React — roles with their permission matrix,
 * pages with their objects, and the permission guide — over the roles API.
 * The rules (MASTER only, one MASTER, MASTER undeletable) are the server's;
 * these screens only ask and show.
 */
import { Fragment, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { ApiError, PtahApi } from '../../core';
import {
    blankObject,
    blankPage,
    blankRole,
    deleteObject,
    deletePage,
    deleteRole,
    getGuide,
    getMatrix,
    getRole,
    groupMatrix,
    GUIDE_CODE,
    GUIDE_MATRIX_EXAMPLE,
    listObjects,
    listPages,
    listRoles,
    objectFormOf,
    pageFormOf,
    PERMISSION_FLAGS,
    rangeText,
    rolesWords,
    saveMatrix,
    saveObject,
    savePage,
    saveRole,
    textParts,
} from '../../core/admin/roles';
import type { AdminMeta, AdminResult, GuideInfo, GuideUrls, MatrixRow, ObjectForm, ObjectListing, PageForm, PageListing, PageRow, PermissionFlag, RoleForm, RoleListing, RoleRow, RolesWords } from '../../core/admin/roles';
import { ForgeAlert, ForgeButton, ForgeCard, ForgeCheckbox, ForgeEmpty, ForgeInput, ForgeModal, ForgePageHeader, ForgePagination, ForgeSelect, ForgeSwitch, ForgeTable, ForgeTabs, ForgeTextarea, forgeToast, ForgeToastHost } from '../forge';

export interface RolesScreenProps {
    api?: PtahApi;
    locale?: string;
}

export interface PermissionGuideProps extends RolesScreenProps {
    /** Where the guide's "Go to …" buttons lead, over what the server answers (e.g. the stack's own pages). */
    urls?: Partial<GuideUrls>;
}

// ── Shared pieces ───────────────────────────────────────────────────────────

const failure = (error: unknown, t: RolesWords) => (error instanceof ApiError && error.status === 403 ? t.forbidden : t.loadError);

/** A small pill (the Blade screen's `ptah-c-chip`, which the kit's CSS does not have). */
const CHIP = { display: 'inline-block', padding: '1px 8px', borderRadius: 999, background: 'var(--pc-primary-soft)', color: 'var(--pc-text-strong)', fontSize: 12, fontWeight: 600 } as const;

/** A listing that reloads when its search (debounced, as `wire:model.live.debounce.300ms`), page or version change. */
function useListing<T>(load: (search: string, page: number) => Promise<T>, t: RolesWords, deps: unknown[] = []) {
    const [search, setSearch] = useState('');
    const [term, setTerm] = useState('');
    const [page, setPage] = useState(1);
    const [version, setVersion] = useState(0);
    const [data, setData] = useState<T | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (search === term) {
            return;
        }
        const timer = setTimeout(() => {
            setTerm(search);
            setPage(1);
        }, 300);

        return () => clearTimeout(timer);
    }, [search, term]);

    useEffect(() => {
        let alive = true;
        setLoading(true);
        load(term, page)
            .then((value) => {
                if (alive) {
                    setData(value);
                    setError(null);
                }
            })
            .catch((reason) => alive && setError(failure(reason, t)))
            .finally(() => alive && setLoading(false));

        return () => {
            alive = false;
        };
    }, [term, page, version, ...deps]); // eslint-disable-line react-hooks/exhaustive-deps

    return { search, setSearch, page, setPage, data, error, loading, reload: () => setVersion((value) => value + 1) };
}

function Pager({ meta, t, onChange }: { meta: AdminMeta; t: RolesWords; onChange: (page: number) => void }) {
    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <div className="pc-footer">
            <span>{rangeText(t.range, meta)}</span>
            <div className="pc-pager">
                <ForgePagination page={meta.page} lastPage={meta.last_page} onChange={onChange} />
            </div>
        </div>
    );
}

function ConfirmDelete({ open, text, t, busy, onClose, onConfirm }: { open: boolean; text: string; t: RolesWords; busy: boolean; onClose: () => void; onConfirm: () => void }) {
    return (
        <ForgeModal
            open={open}
            onClose={onClose}
            title={t.deleteTitle}
            size="sm"
            footer={
                <>
                    <ForgeButton color="light" onClick={onClose}>
                        {t.cancel}
                    </ForgeButton>
                    <ForgeButton color="danger" loading={busy} onClick={onConfirm}>
                        {t.delete}
                    </ForgeButton>
                </>
            }
        >
            <p>{text}</p>
        </ForgeModal>
    );
}

const Loading = ({ t }: { t: RolesWords }) => (
    <div className="pf pc-state" role="status">
        {t.loading}
    </div>
);

const searchBox = (value: string, placeholder: string, onChange: (value: string) => void) => (
    <div className="pc-search">
        <ForgeInput type="search" aria-label={placeholder} placeholder={placeholder} value={value} onChange={onChange} />
    </div>
);

// ── Roles ───────────────────────────────────────────────────────────────────

export function PtahRoles({ api, locale }: RolesScreenProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = rolesWords(locale);
    const list = useListing<RoleListing>((search, page) => listRoles(client, { search, page }), t, [client]);

    // Create / edit
    const [formOpen, setFormOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingMaster, setEditingMaster] = useState(false);
    const [form, setForm] = useState<RoleForm>(blankRole());
    const [result, setResult] = useState<AdminResult | null>(null);
    const [saving, setSaving] = useState(false);
    const set = <K extends keyof RoleForm>(key: K, value: RoleForm[K]) => setForm((current) => ({ ...current, [key]: value }));

    // Permission matrix
    const [matrixRole, setMatrixRole] = useState<{ id: number; name: string } | null>(null);
    const [rows, setRows] = useState<MatrixRow[]>([]);
    const [filter, setFilter] = useState('');
    const [expanded, setExpanded] = useState<string[]>([]);
    const [binding, setBinding] = useState(false);

    // Delete
    const [deleting, setDeleting] = useState<RoleRow | null>(null);
    const [removing, setRemoving] = useState(false);

    const openCreate = () => {
        setEditingId(null);
        setEditingMaster(false);
        setForm(blankRole());
        setResult(null);
        setFormOpen(true);
    };

    const openEdit = async (row: RoleRow) => {
        try {
            const loaded = await getRole(client, row.id);
            setEditingId(row.id);
            setEditingMaster(loaded.is_master);
            setForm({ ...loaded, description: loaded.description ?? '', color: loaded.color ?? '' });
            setResult(null);
            setFormOpen(true);
        } catch (error) {
            forgeToast(error instanceof Error ? error.message : t.loadError, 'danger');
        }
    };

    const save = async () => {
        setSaving(true);
        try {
            const answer = await saveRole(client, editingId, form);
            if (answer.ok) {
                forgeToast(editingId === null ? t.roleCreated : t.roleUpdated, 'success');
                setFormOpen(false);
                list.reload();
            } else {
                setResult(answer);
            }
        } catch (error) {
            setResult({ ok: false, error: error instanceof Error ? error.message : t.loadError, errors: {}, messages: [] });
        } finally {
            setSaving(false);
        }
    };

    const openBind = async (row: RoleRow) => {
        try {
            const matrix = await getMatrix(client, row.id);
            setRows(matrix.data);
            setFilter('');
            setExpanded([]);
            setMatrixRole(matrix.role);
        } catch (error) {
            forgeToast(error instanceof Error ? error.message : t.loadError, 'danger');
        }
    };

    const toggle = (index: number, flag: PermissionFlag, value: boolean) => setRows((current) => current.map((row, at) => (at === index ? { ...row, [flag]: value } : row)));

    const saveBind = async () => {
        if (!matrixRole) {
            return;
        }
        setBinding(true);
        try {
            const answer = await saveMatrix(client, matrixRole.id, rows);
            if (answer.ok) {
                forgeToast(t.bindSaved.replace(':role', matrixRole.name), 'success');
                setMatrixRole(null);
                list.reload();
            } else {
                forgeToast(answer.error ?? t.loadError, 'danger');
            }
        } catch (error) {
            forgeToast(error instanceof Error ? error.message : t.loadError, 'danger');
        } finally {
            setBinding(false);
        }
    };

    const remove = async () => {
        if (!deleting) {
            return;
        }
        setRemoving(true);
        try {
            const answer = await deleteRole(client, deleting.id);
            forgeToast(answer.ok ? t.roleDeleted : (answer.error ?? t.loadError), answer.ok ? 'success' : 'danger');
            if (answer.ok) {
                list.reload();
            }
        } catch (error) {
            forgeToast(error instanceof Error ? error.message : t.loadError, 'danger');
        } finally {
            setRemoving(false);
            setDeleting(null);
        }
    };

    const departments = [{ value: '', label: t.noDepartment }, ...(list.data?.departments ?? []).map((department) => ({ value: department.id, label: department.name }))];
    const flagLabels: Record<PermissionFlag, string> = { can_read: t.permRead, can_create: t.permCreate, can_update: t.permEdit, can_delete: t.permDelete };
    const filtering = filter.trim() !== '';
    const groups = groupMatrix(rows, filter).filter((group) => !filtering || group.matches);

    const table = (data: RoleRow[]) => (
        <ForgeTable
            headers={[
                { key: 'name', label: t.colName },
                { key: 'department', label: t.colDepartment },
                { key: 'permissions', label: t.colPermissions },
                { key: 'status', label: t.colStatus },
                { key: 'actions', label: t.colActions },
            ]}
            rows={data.map((row) => ({
                name: (
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                        {row.color && <span aria-hidden="true" style={{ width: 12, height: 12, borderRadius: '50%', background: row.color, display: 'inline-block' }} />}
                        <strong>{row.name}</strong>
                        {row.is_master && <span style={CHIP}>👑 MASTER</span>}
                    </span>
                ),
                department: row.department ?? '—',
                permissions: t.objectsCount.replace(':count', String(row.permissions_count)),
                status: row.is_active ? t.active : t.inactive,
                actions: (
                    <span style={{ display: 'inline-flex', gap: 4 }}>
                        <button type="button" className="pc-icon-btn" title={t.managePermissionsTitle} onClick={() => void openBind(row)}>
                            {t.managePermissions}
                        </button>
                        <button type="button" className="pc-icon-btn" title={t.edit} aria-label={`${t.edit} ${row.name}`} onClick={() => void openEdit(row)}>
                            ✎
                        </button>
                        {!row.is_master && (
                            <button type="button" className="pc-icon-btn pc-icon-btn--danger" title={t.delete} aria-label={`${t.delete} ${row.name}`} onClick={() => setDeleting(row)}>
                                🗑
                            </button>
                        )}
                    </span>
                ),
            }))}
        />
    );

    return (
        <div className="pf pc-root">
            <ForgePageHeader title={t.rolesTitle} subtitle={t.rolesSubtitle} />
            <div className="pc-toolbar">
                <ForgeButton size="sm" onClick={openCreate}>
                    + {t.newRole}
                </ForgeButton>
                {searchBox(list.search, t.searchRole, list.setSearch)}
            </div>

            {list.error ? (
                <ForgeAlert type="danger">{list.error}</ForgeAlert>
            ) : !list.data ? (
                <Loading t={t} />
            ) : list.data.data.length === 0 ? (
                <ForgeEmpty title={t.rolesEmpty} description={t.rolesEmptyHint} />
            ) : (
                <div aria-busy={list.loading || undefined}>
                    {table(list.data.data)}
                    <Pager meta={list.data.meta} t={t} onChange={list.setPage} />
                </div>
            )}

            <ForgeModal
                open={formOpen}
                onClose={() => setFormOpen(false)}
                title={editingId === null ? t.newRole : t.editRole}
                footer={
                    <>
                        <ForgeButton color="light" onClick={() => setFormOpen(false)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton loading={saving} onClick={() => void save()}>
                            {t.save}
                        </ForgeButton>
                    </>
                }
            >
                <div className="pa-section" style={{ maxWidth: 'none' }}>
                    {result?.error && <ForgeAlert type="danger">{result.error}</ForgeAlert>}
                    <ForgeInput label={t.roleName} required value={form.name} error={result?.errors.name} onChange={(value) => set('name', value)} />
                    <ForgeTextarea label={t.roleDescription} rows={2} value={form.description} onChange={(value) => set('description', value)} />
                    <ForgeInput label={t.roleColor} type="color" value={form.color || '#6b7280'} error={result?.errors.color} onChange={(value) => set('color', value)} />
                    <ForgeSelect
                        label={t.roleDepartment}
                        options={departments}
                        selected={form.department_id ?? ''}
                        placeholder={t.noDepartment}
                        error={result?.errors.department_id}
                        onChange={(value) => set('department_id', value === '' || value === null ? null : Number(value))}
                    />
                    <div className="pa-row">
                        <ForgeSwitch label={t.roleActive} checked={form.is_active} onChange={(value) => set('is_active', value)} />
                        {editingMaster ? <span>{t.roleIsMaster}</span> : <ForgeSwitch label={t.roleMaster} checked={form.is_master} onChange={(value) => set('is_master', value)} />}
                    </div>
                    {form.is_master && <ForgeAlert type="warn">{t.roleMasterWarn}</ForgeAlert>}
                </div>
            </ForgeModal>

            <ForgeModal
                open={matrixRole !== null}
                onClose={() => setMatrixRole(null)}
                title={`${t.bindTitle} ${matrixRole?.name ?? ''}`}
                size="xl"
                footer={
                    <>
                        <ForgeButton color="light" onClick={() => setMatrixRole(null)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton loading={binding} onClick={() => void saveBind()}>
                            {t.bindSave}
                        </ForgeButton>
                    </>
                }
            >
                {rows.length > 0 && <ForgeInput type="search" aria-label={t.bindFilterLabel} placeholder={t.bindFilter} value={filter} onChange={setFilter} />}
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 12, maxHeight: '60vh', overflowY: 'auto' }}>
                    {groups.map((group) => {
                        const open = filtering || expanded.includes(group.page);
                        const id = `ptah-bind-${group.page.replace(/\W+/g, '-')}`;

                        return (
                            <div key={group.page}>
                                <button
                                    type="button"
                                    className="pc-btn"
                                    style={{ width: '100%', justifyContent: 'space-between', display: 'flex' }}
                                    aria-expanded={open}
                                    aria-controls={id}
                                    onClick={() => !filtering && setExpanded((current) => (current.includes(group.page) ? current.filter((page) => page !== group.page) : [...current, group.page]))}
                                >
                                    <span>
                                        {open ? '▾' : '▸'} 📄 {group.page} — {group.section}
                                    </span>
                                    <span>
                                        {group.checked}/{group.total}
                                    </span>
                                </button>
                                {open && (
                                    <div id={id} style={{ display: 'flex', flexDirection: 'column', gap: 4, paddingTop: 4 }}>
                                        {group.rows.map(({ row, index }) => (
                                            <div key={row.id} role="group" aria-label={row.obj_label} className="pa-row" style={{ border: '1px solid var(--pc-line)', borderRadius: 6, padding: '6px 10px' }}>
                                                <div>
                                                    <div>{row.obj_label}</div>
                                                    <small>
                                                        <code>{row.obj_key}</code> · {row.obj_type}
                                                    </small>
                                                </div>
                                                <div style={{ display: 'flex', gap: 12 }}>
                                                    {PERMISSION_FLAGS.map((flag) => (
                                                        <ForgeCheckbox key={flag} label={flagLabels[flag]} checked={row[flag]} onChange={(value) => toggle(index, flag, value)} />
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                    {rows.length === 0 && <ForgeEmpty description={t.bindEmpty} />}
                    {rows.length > 0 && filtering && groups.length === 0 && <ForgeEmpty description={t.bindFilterEmpty} />}
                </div>
            </ForgeModal>

            <ConfirmDelete open={deleting !== null} text={t.roleDeleteText} t={t} busy={removing} onClose={() => setDeleting(null)} onConfirm={() => void remove()} />
            <ForgeToastHost />
        </div>
    );
}

// ── Pages and their objects ─────────────────────────────────────────────────

export function PtahPages({ api, locale }: RolesScreenProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = rolesWords(locale);
    const pages = useListing<PageListing>((search, page) => listPages(client, { search, page }), t, [client]);
    const [selected, setSelected] = useState<PageRow | null>(null);
    const objects = useListing<ObjectListing | null>((search, page) => (selected ? listObjects(client, selected.id, { search, page }) : Promise.resolve(null)), t, [client, selected?.id]);

    const [pageOpen, setPageOpen] = useState(false);
    const [editingPage, setEditingPage] = useState<number | null>(null);
    const [pageForm, setPageForm] = useState<PageForm>(blankPage());
    const [objectOpen, setObjectOpen] = useState(false);
    const [editingObject, setEditingObject] = useState<number | null>(null);
    const [objectForm, setObjectForm] = useState<ObjectForm>(blankObject());
    const [result, setResult] = useState<AdminResult | null>(null);
    const [saving, setSaving] = useState(false);
    const [deleting, setDeleting] = useState<{ target: 'page' | 'object'; id: number } | null>(null);
    const [removing, setRemoving] = useState(false);
    const types = pages.data?.types ?? ['page', 'button', 'field', 'link', 'section', 'api', 'report', 'tab'];

    const select = (row: PageRow) => {
        setSelected(row);
        objects.setSearch('');
        objects.setPage(1);
    };

    const openPage = (row: PageRow | null) => {
        setEditingPage(row?.id ?? null);
        setPageForm(row ? pageFormOf(row) : blankPage());
        setResult(null);
        setPageOpen(true);
    };

    const openObject = (row: ObjectListing['data'][number] | null) => {
        setEditingObject(row?.id ?? null);
        setObjectForm(row ? objectFormOf(row) : blankObject());
        setResult(null);
        setObjectOpen(true);
    };

    const submit = async (action: () => Promise<AdminResult>, done: string, close: () => void) => {
        setSaving(true);
        try {
            const answer = await action();
            if (answer.ok) {
                forgeToast(done, 'success');
                close();
                pages.reload();
                objects.reload();
            } else {
                setResult(answer);
            }
        } catch (error) {
            setResult({ ok: false, error: error instanceof Error ? error.message : t.loadError, errors: {}, messages: [] });
        } finally {
            setSaving(false);
        }
    };

    const remove = async () => {
        if (!deleting) {
            return;
        }
        setRemoving(true);
        try {
            const answer = deleting.target === 'page' ? await deletePage(client, deleting.id) : await deleteObject(client, selected?.id ?? 0, deleting.id);
            forgeToast(answer.ok ? (deleting.target === 'page' ? t.pageDeleted : t.objectDeleted) : (answer.error ?? t.loadError), answer.ok ? 'success' : 'danger');
            if (answer.ok) {
                if (deleting.target === 'page' && selected?.id === deleting.id) {
                    setSelected(null);
                }
                pages.reload();
                objects.reload();
            }
        } catch (error) {
            forgeToast(error instanceof Error ? error.message : t.loadError, 'danger');
        } finally {
            setRemoving(false);
            setDeleting(null);
        }
    };

    const setPage = <K extends keyof PageForm>(key: K, value: PageForm[K]) => setPageForm((current) => ({ ...current, [key]: value }));
    const setObject = <K extends keyof ObjectForm>(key: K, value: ObjectForm[K]) => setObjectForm((current) => ({ ...current, [key]: value }));
    const objectData = objects.data;

    return (
        <div className="pf pc-root">
            <ForgePageHeader title={t.pagesTitle} subtitle={t.pagesSubtitle} />
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 24, alignItems: 'start' }}>
                <section aria-label={t.pagesColumn}>
                    <div className="pc-toolbar">
                        <h2 className="pc-title">{t.pagesColumn}</h2>
                        <ForgeButton size="sm" onClick={() => openPage(null)}>
                            + {t.newPage}
                        </ForgeButton>
                        {searchBox(pages.search, t.searchPage, pages.setSearch)}
                    </div>
                    {pages.error ? (
                        <ForgeAlert type="danger">{pages.error}</ForgeAlert>
                    ) : !pages.data ? (
                        <Loading t={t} />
                    ) : pages.data.data.length === 0 ? (
                        <ForgeEmpty title={t.pagesEmpty} description={t.pagesEmptyHint} />
                    ) : (
                        <div aria-busy={pages.loading || undefined}>
                            <ul className="pf pf-list" style={{ listStyle: 'none', margin: 0, padding: 0 }}>
                                {pages.data.data.map((row) => (
                                    <li key={row.id} className="pf-list-item pa-row" style={{ padding: '8px 12px', background: selected?.id === row.id ? 'var(--pc-primary-soft)' : undefined }}>
                                        <button type="button" className="pc-link" aria-pressed={selected?.id === row.id} style={{ flex: 1, textAlign: 'left', background: 'none', border: 0, cursor: 'pointer', font: 'inherit' }} onClick={() => select(row)}>
                                            {row.icon && <span aria-hidden="true">{row.icon} </span>}
                                            <strong>{row.name}</strong> <small>{t.objCount.replace(':count', String(row.objects_count))}</small>
                                            <br />
                                            <small>
                                                <code>{row.slug}</code>
                                            </small>
                                        </button>
                                        <span>
                                            <button type="button" className="pc-icon-btn" aria-label={`${t.edit} ${row.name}`} title={t.edit} onClick={() => openPage(row)}>
                                                ✎
                                            </button>
                                            <button type="button" className="pc-icon-btn pc-icon-btn--danger" aria-label={`${t.delete} ${row.name}`} title={t.delete} onClick={() => setDeleting({ target: 'page', id: row.id })}>
                                                🗑
                                            </button>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            <Pager meta={pages.data.meta} t={t} onChange={pages.setPage} />
                        </div>
                    )}
                </section>

                <section aria-label={selected ? t.objectsOf.replace(':page', selected.name) : t.selectPageHint}>
                    {!selected ? (
                        <ForgeEmpty description={t.selectPageHint} />
                    ) : (
                        <>
                            <div className="pc-toolbar">
                                <h2 className="pc-title">{t.objectsOf.replace(':page', selected.name)}</h2>
                                <ForgeButton size="sm" onClick={() => openObject(null)}>
                                    + {t.newObject}
                                </ForgeButton>
                                {searchBox(objects.search, t.searchObject, objects.setSearch)}
                            </div>
                            {objects.error ? (
                                <ForgeAlert type="danger">{objects.error}</ForgeAlert>
                            ) : !objectData || objectData.page.id !== selected.id ? (
                                <Loading t={t} />
                            ) : objectData.data.length === 0 ? (
                                <ForgeEmpty title={t.objectsEmpty} description={t.objectsEmptyHint} />
                            ) : (
                                <div aria-busy={objects.loading || undefined}>
                                    <ForgeTable
                                        headers={[
                                            { key: 'key', label: t.colKeyLabel },
                                            { key: 'type', label: t.colType },
                                            { key: 'section', label: t.colSection },
                                            { key: 'actions', label: t.colActions },
                                        ]}
                                        rows={objectData.data.map((row) => ({
                                            key: (
                                                <>
                                                    <div>{row.obj_label}</div>
                                                    <small>
                                                        <code>{row.obj_key}</code>
                                                    </small>
                                                </>
                                            ),
                                            type: row.obj_type,
                                            section: row.section,
                                            actions: (
                                                <span style={{ display: 'inline-flex', gap: 4 }}>
                                                    <button type="button" className="pc-icon-btn" aria-label={`${t.edit} ${row.obj_key}`} title={t.edit} onClick={() => openObject(row)}>
                                                        ✎
                                                    </button>
                                                    <button type="button" className="pc-icon-btn pc-icon-btn--danger" aria-label={`${t.delete} ${row.obj_key}`} title={t.delete} onClick={() => setDeleting({ target: 'object', id: row.id })}>
                                                        🗑
                                                    </button>
                                                </span>
                                            ),
                                        }))}
                                    />
                                    <Pager meta={objectData.meta} t={t} onChange={objects.setPage} />
                                </div>
                            )}
                        </>
                    )}
                </section>
            </div>

            <ForgeModal
                open={pageOpen}
                onClose={() => setPageOpen(false)}
                title={editingPage === null ? t.pageModalNew : t.pageModalEdit}
                footer={
                    <>
                        <ForgeButton color="light" onClick={() => setPageOpen(false)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton loading={saving} onClick={() => void submit(() => savePage(client, editingPage, pageForm), editingPage === null ? t.pageCreated : t.pageUpdated, () => setPageOpen(false))}>
                            {t.save}
                        </ForgeButton>
                    </>
                }
            >
                <div className="pa-section" style={{ maxWidth: 'none' }}>
                    {result?.error && <ForgeAlert type="danger">{result.error}</ForgeAlert>}
                    <ForgeInput label={t.pageSlug} required placeholder="admin.users" value={pageForm.slug} error={result?.errors.slug} onChange={(value) => setPage('slug', value)} />
                    <ForgeInput label={t.pageName} required value={pageForm.name} error={result?.errors.name} onChange={(value) => setPage('name', value)} />
                    <ForgeTextarea label={t.pageDescription} rows={2} value={pageForm.description} onChange={(value) => setPage('description', value)} />
                    <ForgeInput label={t.pageRoute} placeholder="admin.users.index" value={pageForm.route} error={result?.errors.route} onChange={(value) => setPage('route', value)} />
                    <ForgeInput label={t.pageIcon} placeholder="bx bx-home" value={pageForm.icon} error={result?.errors.icon} onChange={(value) => setPage('icon', value)} />
                    <div className="pa-row">
                        <ForgeSwitch label={t.pageActive} checked={pageForm.is_active} onChange={(value) => setPage('is_active', value)} />
                        <ForgeInput label={t.pageOrder} type="number" min={0} value={String(pageForm.sort_order)} error={result?.errors.sort_order} onChange={(value) => setPage('sort_order', Number(value) || 0)} />
                    </div>
                </div>
            </ForgeModal>

            <ForgeModal
                open={objectOpen}
                onClose={() => setObjectOpen(false)}
                title={editingObject === null ? t.objectModalNew : t.objectModalEdit}
                footer={
                    <>
                        <ForgeButton color="light" onClick={() => setObjectOpen(false)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton
                            loading={saving}
                            onClick={() => void submit(() => saveObject(client, selected?.id ?? 0, editingObject, objectForm), editingObject === null ? t.objectCreated : t.objectUpdated, () => setObjectOpen(false))}
                        >
                            {t.save}
                        </ForgeButton>
                    </>
                }
            >
                <div className="pa-section" style={{ maxWidth: 'none' }}>
                    {result?.error && <ForgeAlert type="danger">{result.error}</ForgeAlert>}
                    <ForgeInput label={t.objectSection} required placeholder="main / toolbar / form" value={objectForm.section} error={result?.errors.section} onChange={(value) => setObject('section', value)} />
                    <ForgeSelect label={t.objectType} required options={types.map((type) => ({ value: type, label: type }))} selected={objectForm.obj_type} error={result?.errors.obj_type} onChange={(value) => setObject('obj_type', String(value ?? ''))} />
                    <ForgeInput label={t.objectKey} required placeholder="users.store" value={objectForm.obj_key} error={result?.errors.obj_key} onChange={(value) => setObject('obj_key', value)} />
                    <ForgeInput label={t.objectLabel} required value={objectForm.obj_label} error={result?.errors.obj_label} onChange={(value) => setObject('obj_label', value)} />
                    <div className="pa-row">
                        <ForgeSwitch label={t.objectActive} checked={objectForm.is_active} onChange={(value) => setObject('is_active', value)} />
                        <ForgeInput label={t.objectOrder} type="number" min={0} value={String(objectForm.obj_order)} error={result?.errors.obj_order} onChange={(value) => setObject('obj_order', Number(value) || 0)} />
                    </div>
                </div>
            </ForgeModal>

            <ConfirmDelete open={deleting !== null} text={deleting?.target === 'page' ? t.pageDeleteText : t.objectDeleteText} t={t} busy={removing} onClose={() => setDeleting(null)} onConfirm={() => void remove()} />
            <ForgeToastHost />
        </div>
    );
}

// ── Permission guide ────────────────────────────────────────────────────────

function Rich({ text, urls }: { text: string; urls?: GuideUrls | null }) {
    return (
        <>
            {textParts(text).map((part, index) => {
                if (part.kind === 'code') {
                    return <code key={index}>{part.text}</code>;
                }
                if (part.kind === 'strong') {
                    return <strong key={index}>{part.text}</strong>;
                }
                if (part.kind === 'link') {
                    const href = part.target ? urls?.[part.target] : null;

                    return href ? (
                        <a key={index} className="pc-link" href={href}>
                            {part.text}
                        </a>
                    ) : (
                        <Fragment key={index}>{part.text}</Fragment>
                    );
                }

                return <Fragment key={index}>{part.text}</Fragment>;
            })}
        </>
    );
}

function Step({ number, title, desc, action, children }: { number: number; title: string; desc: string; action?: ReactNode; children: ReactNode }) {
    return (
        <ForgeCard
            header={
                <div className="pa-row">
                    <div>
                        <h3 style={{ margin: 0 }}>
                            {number}. {title}
                        </h3>
                        <small>{desc}</small>
                    </div>
                    {action}
                </div>
            }
        >
            {children}
        </ForgeCard>
    );
}

export function PtahPermissionGuide({ api, locale, urls }: PermissionGuideProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const words = rolesWords(locale);
    const g = words.guide;
    const [info, setInfo] = useState<GuideInfo | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [tab, setTab] = useState('overview');
    // ForgeTabs only takes a starting tab: "See examples →" remounts it on another one.
    const [jumps, setJumps] = useState(0);
    const jump = (id: string) => {
        setTab(id);
        setJumps((value) => value + 1);
    };

    useEffect(() => {
        let alive = true;
        getGuide(client)
            .then((value) => alive && setInfo(value))
            .catch((reason) => alive && setError(failure(reason, words)));

        return () => {
            alive = false;
        };
    }, [client]); // eslint-disable-line react-hooks/exhaustive-deps

    if (error) {
        return (
            <div className="pf pc-root">
                <ForgeAlert type="danger">{error}</ForgeAlert>
            </div>
        );
    }
    if (!info) {
        return <Loading t={words} />;
    }

    const links: GuideUrls = { ...info.urls, ...urls } as GuideUrls;
    const go = (href: string | null, label: string) =>
        href ? (
            <ForgeButton size="sm" href={href}>
                {label}
            </ForgeButton>
        ) : null;
    const perms = [words.permRead, words.permCreate, words.permEdit, words.permDelete];

    const overview = (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
            <ForgeAlert type="primary" title={g.ovTitle}>
                {g.ovBody.map((paragraph, index) => (
                    <p key={index}>
                        <Rich text={paragraph} />
                    </p>
                ))}
            </ForgeAlert>
            <section>
                <h2>1. {g.archTitle}</h2>
                <ol style={{ display: 'flex', flexWrap: 'wrap', gap: 12, listStyle: 'none', padding: 0 }}>
                    {g.arch.map((node, index) => (
                        <li key={node.title} style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                            <ForgeCard>
                                <div style={{ textAlign: 'center', maxWidth: 160 }}>
                                    <div aria-hidden="true">{node.icon}</div>
                                    <strong>{node.title}</strong>
                                    <div>
                                        <small>{node.desc}</small>
                                    </div>
                                    <div>
                                        <small>{node.example}</small>
                                    </div>
                                </div>
                            </ForgeCard>
                            {index < g.arch.length - 1 && <span aria-hidden="true">→</span>}
                        </li>
                    ))}
                </ol>
            </section>
            <section>
                <h2>2. {g.conceptsTitle}</h2>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 12 }}>
                    {g.concepts.map((concept, index) => (
                        <ForgeCard key={concept.title} header={`${concept.icon} ${concept.title}`}>
                            <p>
                                <Rich text={concept.body} />
                            </p>
                            {concept.chips.length > 0 && (
                                <p style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                                    {concept.chips.map((chip) => (
                                        <code key={chip}>{chip}</code>
                                    ))}
                                </p>
                            )}
                            {index === 2 && (
                                <p style={{ display: 'flex', gap: 12 }}>
                                    {g.permsPreview.map((perm) => (
                                        <span key={perm}>✓ {perm}</span>
                                    ))}
                                </p>
                            )}
                            {concept.warn && <ForgeAlert type="warn">{concept.warn}</ForgeAlert>}
                        </ForgeCard>
                    ))}
                </div>
            </section>
            <section>
                <h2>3. {g.flowTitle}</h2>
                <ForgeCard>
                    <ol style={{ display: 'flex', flexDirection: 'column', gap: 8, paddingLeft: 0, listStyle: 'none' }}>
                        {g.flow.map((step) => (
                            <li key={step.text} className="pa-row">
                                <span>{step.text}</span>
                                {step.outcome === 'no' && <strong>{g.flowNo}</strong>}
                                {step.outcome === 'granted' && <strong>{g.flowGranted}</strong>}
                            </li>
                        ))}
                    </ol>
                    <p className="pa-row" style={{ justifyContent: 'flex-start' }}>
                        <strong>{g.flowGranted}</strong>
                        <strong>{g.flowDenied}</strong>
                    </p>
                </ForgeCard>
            </section>
        </div>
    );

    const setup = (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <ForgeAlert type="primary">
                <Rich text={g.prereq} />
            </ForgeAlert>
            <Step number={1} title={g.s1Title} desc={g.s1Desc} action={go(links.departments, g.s1Btn)}>
                <p>{g.s1Body}</p>
                <strong>{g.s1Example}:</strong>
                <ul>
                    {g.s1Examples.map((line) => (
                        <li key={line}>
                            <Rich text={line} />
                        </li>
                    ))}
                </ul>
            </Step>
            <Step number={2} title={g.s2Title} desc={g.s2Desc} action={go(links.pages, g.s2Btn)}>
                <p>
                    <Rich text={g.s2Body} />
                </p>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 12 }}>
                    <ForgeCard header={g.s2PageTitle}>
                        <table className="pf-table">
                            <tbody>
                                <tr>
                                    <th scope="row">{g.s2PageSlug}</th>
                                    <td>
                                        <code>admin.vendas</code>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">{g.s2PageName}</th>
                                    <td>{g.s2PageNameValue}</td>
                                </tr>
                                <tr>
                                    <th scope="row">{g.s2PageIcon}</th>
                                    <td>🛒</td>
                                </tr>
                            </tbody>
                        </table>
                    </ForgeCard>
                    <ForgeCard header={g.s2ObjTitle}>
                        <ul style={{ listStyle: 'none', padding: 0 }}>
                            {[
                                ['vendas.criar-pedido', 'button'],
                                ['vendas.ver-desconto', 'field'],
                                ['vendas.exportar', 'action'],
                            ].map(([key, type]) => (
                                <li key={key} className="pa-row">
                                    <code>{key}</code>
                                    <span>{type}</span>
                                </li>
                            ))}
                        </ul>
                    </ForgeCard>
                </div>
            </Step>
            <Step number={3} title={g.s3Title} desc={g.s3Desc} action={go(links.roles, g.s3Btn)}>
                <p>
                    <Rich text={g.s3Body} />
                </p>
                <h4>{g.s3ExTitle}</h4>
                <table className="pf-table">
                    <thead>
                        <tr>
                            <th>{g.s3ColObj}</th>
                            {perms.map((perm) => (
                                <th key={perm}>{perm}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {GUIDE_MATRIX_EXAMPLE.map(([key, ...flags]) => (
                            <tr key={key}>
                                <td>
                                    <code>{key}</code>
                                </td>
                                {flags.map((flag, index) => (
                                    <td key={index}>{flag ? '✓' : '—'}</td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
                <p>
                    <small>
                        <Rich text={g.s3Note} />
                    </small>
                </p>
            </Step>
            <Step number={4} title={g.s4Title} desc={g.s4Desc} action={go(links.users, g.s4Btn)}>
                <p>
                    <Rich text={g.s4Body} />
                </p>
                <h4>
                    <Rich text={g.s4ExTitle} />
                </h4>
                {g.s4Examples.map((line) => (
                    <p key={line}>
                        <Rich text={line} />
                    </p>
                ))}
            </Step>
            <Step
                number={5}
                title={g.s5Title}
                desc={g.s5Desc}
                action={
                    <ForgeButton size="sm" onClick={() => jump('code')}>
                        {g.s5Btn}
                    </ForgeButton>
                }
            >
                <p>
                    <Rich text={g.s5Body} />
                </p>
                <p>
                    <Rich text={g.s5Note} />
                </p>
            </Step>
            <ForgeCard header={g.colTitle}>
                <p>
                    <small>{g.colDesc}</small>
                </p>
                <p>
                    <Rich text={g.colBody} />
                </p>
                <ForgeAlert type="warn">
                    <Rich text={g.colWarn} />
                </ForgeAlert>
            </ForgeCard>
        </div>
    );

    const code = (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            {GUIDE_CODE.map((sample) => (
                <figure key={sample.caption} style={{ margin: 0 }}>
                    <figcaption>
                        <code>{sample.caption}</code>
                    </figcaption>
                    <pre style={{ overflowX: 'auto', padding: 16, border: '1px solid var(--pc-line)', borderRadius: 6 }}>
                        <code>{sample.code}</code>
                    </pre>
                </figure>
            ))}
        </div>
    );

    const faq = (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {g.faq.map((item) => (
                <details key={item.q} className="pf-card" style={{ padding: '12px 16px' }}>
                    <summary style={{ cursor: 'pointer', fontWeight: 600 }}>{item.q}</summary>
                    <p>
                        <Rich text={item.a} urls={links} />
                    </p>
                </details>
            ))}
            <ForgeAlert type="primary" title={`🙋 ${g.helpTitle}`}>
                <Rich text={g.helpBody} />
            </ForgeAlert>
        </div>
    );

    const tabs = [
        { id: 'overview', label: g.tabOverview, content: overview },
        { id: 'setup', label: g.tabSetup, content: setup },
        { id: 'code', label: g.tabCode, content: code },
        { id: 'faq', label: g.tabFaq, content: faq },
    ];

    return (
        <div className="pf pc-root">
            <ForgePageHeader title={g.title} subtitle={g.subtitle}>
                <span style={CHIP}>{g.badge}</span>
            </ForgePageHeader>
            <ForgeTabs key={jumps} tabs={tabs} defaultTab={tab} onChange={setTab} />
            <p style={{ textAlign: 'center', fontSize: 11, marginTop: 24 }} title={g.versionTitle}>
                ptah v{info.version}
            </p>
        </div>
    );
}
