/**
 * The access-control screens for Vue — roles with their permission matrix,
 * pages with their objects, and the permission guide — over the roles API.
 * The rules (MASTER only, one MASTER, MASTER undeletable) are the server's;
 * these screens only ask and show. Mirrors react/admin/roles.tsx.
 */
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import type { PropType, VNode, WatchSource } from 'vue';
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
import type { AdminMeta, AdminResult, GuideInfo, GuideUrls, MatrixRow, ObjectForm, ObjectListing, ObjectRow, PageForm, PageListing, PageRow, PermissionFlag, RoleForm, RoleListing, RoleRow, RolesWords } from '../../core/admin/roles';
import { ForgeAlert, ForgeButton, ForgeCard, ForgeCheckbox, ForgeEmpty, ForgeInput, ForgeModal, ForgePageHeader, ForgePagination, ForgeSelect, ForgeSwitch, ForgeTable, ForgeTabs, ForgeTextarea, forgeToast, ForgeToastHost } from '../forge';

const screenProps = {
    api: { type: Object as PropType<PtahApi>, default: undefined },
    locale: { type: String, default: undefined },
};

// ── Shared pieces ───────────────────────────────────────────────────────────

const failure = (error: unknown, t: RolesWords) => (error instanceof ApiError && error.status === 403 ? t.forbidden : t.loadError);

/** A small pill (the Blade screen's `ptah-c-chip`, which the kit's CSS does not have). */
/** A listing that reloads when its search (debounced, as `wire:model.live.debounce.300ms`), page, version or `sources` change. */
function useListing<T>(load: (search: string, page: number) => Promise<T>, t: () => RolesWords, sources: WatchSource[] = []) {
    const search = ref('');
    const term = ref('');
    const page = ref(1);
    const version = ref(0);
    const data = shallowRef<T | null>(null);
    const error = ref<string | null>(null);
    const loading = ref(true);
    let timer: ReturnType<typeof setTimeout> | undefined;
    let sequence = 0;

    watch(search, (value) => {
        clearTimeout(timer);
        if (value === term.value) {
            return;
        }
        timer = setTimeout(() => {
            term.value = value;
            page.value = 1;
        }, 300);
    });

    const fetch = () => {
        const mine = ++sequence;
        loading.value = true;
        load(term.value, page.value)
            .then((value) => {
                if (mine === sequence) {
                    data.value = value;
                    error.value = null;
                }
            })
            .catch((reason) => {
                if (mine === sequence) {
                    error.value = failure(reason, t());
                }
            })
            .finally(() => {
                if (mine === sequence) {
                    loading.value = false;
                }
            });
    };

    watch([term, page, version, ...sources], fetch);
    onMounted(fetch);
    onBeforeUnmount(() => clearTimeout(timer));

    return { search, page, data, error, loading, reload: () => version.value++ };
}

const pager = (meta: AdminMeta, t: RolesWords, onChange: (page: number) => void) =>
    meta.last_page <= 1
        ? null
        : h('div', { class: 'pc-footer' }, [h('span', rangeText(t.range, meta)), h('div', { class: 'pc-pager' }, [h(ForgePagination, { page: meta.page, lastPage: meta.last_page, onChange })])]);

const confirmDelete = (open: boolean, text: string, t: RolesWords, busy: boolean, onClose: () => void, onConfirm: () => void) =>
    h(
        ForgeModal,
        { modelValue: open, 'onUpdate:modelValue': (value: boolean) => !value && onClose(), title: t.deleteTitle, size: 'sm' },
        {
            default: () => h('p', text),
            footer: () => [h(ForgeButton, { color: 'light', onClick: onClose }, () => t.cancel), h(ForgeButton, { color: 'danger', loading: busy, onClick: onConfirm }, () => t.delete)],
        },
    );

const loadingState = (t: RolesWords) => h('div', { class: 'pf pc-state', role: 'status' }, t.loading);

const searchBox = (value: string, placeholder: string, onChange: (value: string) => void) =>
    h('div', { class: 'pc-search' }, [h(ForgeInput, { type: 'search', 'aria-label': placeholder, placeholder, modelValue: value, 'onUpdate:modelValue': onChange })]);

const modal = (open: boolean, onClose: () => void, title: string, footer: () => VNode[], body: () => (VNode | null)[], size = 'md') =>
    h(ForgeModal, { modelValue: open, 'onUpdate:modelValue': (value: boolean) => !value && onClose(), title, size }, { default: body, footer });

/** A ForgeInput bound to one key of a form ref. */
function input<F extends object, K extends keyof F>(form: { value: F }, key: K, attrs: Record<string, unknown>, cast: (value: string) => F[K] = (value) => value as F[K]) {
    return h(ForgeInput, { ...attrs, modelValue: form.value[key] as unknown as string, 'onUpdate:modelValue': (value: string) => (form.value = { ...form.value, [key]: cast(value) }) });
}

function toggleOf<F extends object, K extends keyof F>(form: { value: F }, key: K, label: string) {
    return h(ForgeSwitch, { label, modelValue: form.value[key] as unknown as boolean, 'onUpdate:modelValue': (value: boolean) => (form.value = { ...form.value, [key]: value }) });
}

const section = (children: (VNode | null | false | undefined)[]) => h('div', { class: 'pa-section pa-unbound' }, children.filter(Boolean) as VNode[]);

const errorOf = (error: unknown, t: RolesWords) => (error instanceof Error ? error.message : t.loadError);

// ── Roles ───────────────────────────────────────────────────────────────────

export const PtahRoles = defineComponent({
    name: 'PtahRoles',
    props: screenProps,
    setup(props) {
        const client = props.api ?? new PtahApi();
        const t = computed(() => rolesWords(props.locale));
        const list = useListing<RoleListing>((search, page) => listRoles(client, { search, page }), () => t.value);

        // Create / edit
        const formOpen = ref(false);
        const editingId = ref<number | null>(null);
        const editingMaster = ref(false);
        const form = ref<RoleForm>(blankRole());
        const result = ref<AdminResult | null>(null);
        const saving = ref(false);

        // Permission matrix
        const matrixRole = ref<{ id: number; name: string } | null>(null);
        const rows = ref<MatrixRow[]>([]);
        const filter = ref('');
        const expanded = ref<string[]>([]);
        const binding = ref(false);

        // Delete
        const deleting = ref<RoleRow | null>(null);
        const removing = ref(false);

        const openCreate = () => {
            editingId.value = null;
            editingMaster.value = false;
            form.value = blankRole();
            result.value = null;
            formOpen.value = true;
        };

        const openEdit = async (row: RoleRow) => {
            try {
                const loaded = await getRole(client, row.id);
                editingId.value = row.id;
                editingMaster.value = loaded.is_master;
                form.value = { ...loaded, description: loaded.description ?? '', color: loaded.color ?? '' };
                result.value = null;
                formOpen.value = true;
            } catch (error) {
                forgeToast(errorOf(error, t.value), 'danger');
            }
        };

        const save = async () => {
            saving.value = true;
            try {
                const answer = await saveRole(client, editingId.value, form.value);
                if (answer.ok) {
                    forgeToast(editingId.value === null ? t.value.roleCreated : t.value.roleUpdated, 'success');
                    formOpen.value = false;
                    list.reload();
                } else {
                    result.value = answer;
                }
            } catch (error) {
                result.value = { ok: false, error: errorOf(error, t.value), errors: {}, messages: [] };
            } finally {
                saving.value = false;
            }
        };

        const openBind = async (row: RoleRow) => {
            try {
                const matrix = await getMatrix(client, row.id);
                rows.value = matrix.data;
                filter.value = '';
                expanded.value = [];
                matrixRole.value = matrix.role;
            } catch (error) {
                forgeToast(errorOf(error, t.value), 'danger');
            }
        };

        const toggle = (index: number, flag: PermissionFlag, value: boolean) => {
            rows.value = rows.value.map((row, at) => (at === index ? { ...row, [flag]: value } : row));
        };

        const saveBind = async () => {
            const role = matrixRole.value;
            if (!role) {
                return;
            }
            binding.value = true;
            try {
                const answer = await saveMatrix(client, role.id, rows.value);
                if (answer.ok) {
                    forgeToast(t.value.bindSaved.replace(':role', role.name), 'success');
                    matrixRole.value = null;
                    list.reload();
                } else {
                    forgeToast(answer.error ?? t.value.loadError, 'danger');
                }
            } catch (error) {
                forgeToast(errorOf(error, t.value), 'danger');
            } finally {
                binding.value = false;
            }
        };

        const remove = async () => {
            const row = deleting.value;
            if (!row) {
                return;
            }
            removing.value = true;
            try {
                const answer = await deleteRole(client, row.id);
                forgeToast(answer.ok ? t.value.roleDeleted : (answer.error ?? t.value.loadError), answer.ok ? 'success' : 'danger');
                if (answer.ok) {
                    list.reload();
                }
            } catch (error) {
                forgeToast(errorOf(error, t.value), 'danger');
            } finally {
                removing.value = false;
                deleting.value = null;
            }
        };

        const table = (data: RoleRow[]) =>
            h(
                ForgeTable,
                {
                    headers: [
                        { key: 'name', label: t.value.colName },
                        { key: 'department', label: t.value.colDepartment },
                        { key: 'permissions', label: t.value.colPermissions },
                        { key: 'status', label: t.value.colStatus },
                        { key: 'actions', label: t.value.colActions },
                    ],
                    rows: data.map((row) => ({
                        row,
                        name: row.name,
                        department: row.department ?? '—',
                        permissions: t.value.objectsCount.replace(':count', String(row.permissions_count)),
                        status: row.is_active ? t.value.active : t.value.inactive,
                        actions: '',
                    })),
                },
                {
                    'cell-name': ({ row }: { row: { row: RoleRow } }) =>
                        h('span', { class: 'pa-inline-center-g2' }, [
                            row.row.color ? h('span', { 'aria-hidden': 'true', class: 'pa-role-dot', style: { background: row.row.color } }) : null,
                            h('strong', row.row.name),
                            row.row.is_master ? h('span', { class: 'pa-role-badge' }, '👑 MASTER') : null,
                        ]),
                    'cell-actions': ({ row }: { row: { row: RoleRow } }) =>
                        h('span', { class: 'pa-inline-g1' }, [
                            h('button', { type: 'button', class: 'pc-icon-btn', title: t.value.managePermissionsTitle, onClick: () => void openBind(row.row) }, t.value.managePermissions),
                            h('button', { type: 'button', class: 'pc-icon-btn', title: t.value.edit, 'aria-label': `${t.value.edit} ${row.row.name}`, onClick: () => void openEdit(row.row) }, '✎'),
                            row.row.is_master
                                ? null
                                : h('button', { type: 'button', class: 'pc-icon-btn pc-icon-btn--danger', title: t.value.delete, 'aria-label': `${t.value.delete} ${row.row.name}`, onClick: () => (deleting.value = row.row) }, '🗑'),
                        ]),
                },
            );

        const matrixBody = () => {
            const tt = t.value;
            const flagLabels: Record<PermissionFlag, string> = { can_read: tt.permRead, can_create: tt.permCreate, can_update: tt.permEdit, can_delete: tt.permDelete };
            const filtering = filter.value.trim() !== '';
            const groups = groupMatrix(rows.value, filter.value).filter((group) => !filtering || group.matches);

            return [
                rows.value.length > 0 ? h(ForgeInput, { type: 'search', 'aria-label': tt.bindFilterLabel, placeholder: tt.bindFilter, modelValue: filter.value, 'onUpdate:modelValue': (value: string) => (filter.value = value) }) : null,
                h('div', { class: 'pa-role-perms' }, [
                    ...groups.map((group) => {
                        const open = filtering || expanded.value.includes(group.page);
                        const id = `ptah-bind-${group.page.replace(/\W+/g, '-')}`;

                        return h('div', { key: group.page }, [
                            h(
                                'button',
                                {
                                    type: 'button',
                                    class: 'pc-btn pa-role-group-toggle',
                                    'aria-expanded': open,
                                    'aria-controls': id,
                                    onClick: () => {
                                        if (!filtering) {
                                            expanded.value = expanded.value.includes(group.page) ? expanded.value.filter((page) => page !== group.page) : [...expanded.value, group.page];
                                        }
                                    },
                                },
                                [h('span', `${open ? '▾' : '▸'} 📄 ${group.page} — ${group.section}`), h('span', `${group.checked}/${group.total}`)],
                            ),
                            open
                                ? h(
                                      'div',
                                      { id, class: 'pa-role-group-body' },
                                      group.rows.map(({ row, index }) =>
                                          h('div', { key: row.id, role: 'group', 'aria-label': row.obj_label, class: 'pa-row pa-role-perm' }, [
                                              h('div', [h('div', row.obj_label), h('small', [h('code', row.obj_key), ` · ${row.obj_type}`])]),
                                              h(
                                                  'div',
                                                  { class: 'pa-flex-g3' },
                                                  PERMISSION_FLAGS.map((flag) => h(ForgeCheckbox, { key: flag, label: flagLabels[flag], modelValue: row[flag], 'onUpdate:modelValue': (value: boolean) => toggle(index, flag, value) })),
                                              ),
                                          ]),
                                      ),
                                  )
                                : null,
                        ]);
                    }),
                    rows.value.length === 0 ? h(ForgeEmpty, { description: tt.bindEmpty }) : null,
                    rows.value.length > 0 && filtering && groups.length === 0 ? h(ForgeEmpty, { description: tt.bindFilterEmpty }) : null,
                ]),
            ];
        };

        return () => {
            const tt = t.value;
            const data = list.data.value;
            const departments = [{ value: '', label: tt.noDepartment }, ...(data?.departments ?? []).map((department) => ({ value: department.id, label: department.name }))];
            const errors = result.value?.errors ?? {};

            return h('div', { class: 'pf pc-root' }, [
                h(ForgePageHeader, { title: tt.rolesTitle, subtitle: tt.rolesSubtitle }),
                h('div', { class: 'pc-toolbar' }, [
                    h(ForgeButton, { size: 'sm', onClick: openCreate }, () => `+ ${tt.newRole}`),
                    searchBox(list.search.value, tt.searchRole, (value) => (list.search.value = value)),
                ]),
                list.error.value
                    ? h(ForgeAlert, { type: 'danger' }, () => list.error.value)
                    : !data
                      ? loadingState(tt)
                      : data.data.length === 0
                        ? h(ForgeEmpty, { title: tt.rolesEmpty, description: tt.rolesEmptyHint })
                        : h('div', { 'aria-busy': list.loading.value || undefined }, [table(data.data), pager(data.meta, tt, (page) => (list.page.value = page))]),

                modal(
                    formOpen.value,
                    () => (formOpen.value = false),
                    editingId.value === null ? tt.newRole : tt.editRole,
                    () => [h(ForgeButton, { color: 'light', onClick: () => (formOpen.value = false) }, () => tt.cancel), h(ForgeButton, { loading: saving.value, onClick: () => void save() }, () => tt.save)],
                    () => [
                        section([
                            result.value?.error ? h(ForgeAlert, { type: 'danger' }, () => result.value?.error) : null,
                            input(form, 'name', { label: tt.roleName, required: true, error: errors.name }),
                            h(ForgeTextarea, { label: tt.roleDescription, rows: 2, modelValue: form.value.description, 'onUpdate:modelValue': (value: string) => (form.value = { ...form.value, description: value }) }),
                            h(ForgeInput, { label: tt.roleColor, type: 'color', modelValue: form.value.color || '#6b7280', error: errors.color, 'onUpdate:modelValue': (value: string) => (form.value = { ...form.value, color: value }) }),
                            h(ForgeSelect, {
                                label: tt.roleDepartment,
                                options: departments,
                                modelValue: form.value.department_id ?? '',
                                placeholder: tt.noDepartment,
                                error: errors.department_id,
                                'onUpdate:modelValue': (value: unknown) => (form.value = { ...form.value, department_id: value === '' || value === null ? null : Number(value) }),
                            }),
                            h('div', { class: 'pa-row' }, [toggleOf(form, 'is_active', tt.roleActive), editingMaster.value ? h('span', tt.roleIsMaster) : toggleOf(form, 'is_master', tt.roleMaster)]),
                            form.value.is_master ? h(ForgeAlert, { type: 'warn' }, () => tt.roleMasterWarn) : null,
                        ]),
                    ],
                ),

                modal(
                    matrixRole.value !== null,
                    () => (matrixRole.value = null),
                    `${tt.bindTitle} ${matrixRole.value?.name ?? ''}`,
                    () => [h(ForgeButton, { color: 'light', onClick: () => (matrixRole.value = null) }, () => tt.cancel), h(ForgeButton, { loading: binding.value, onClick: () => void saveBind() }, () => tt.bindSave)],
                    matrixBody,
                    'xl',
                ),

                confirmDelete(deleting.value !== null, tt.roleDeleteText, tt, removing.value, () => (deleting.value = null), () => void remove()),
                h(ForgeToastHost),
            ]);
        };
    },
});

// ── Pages and their objects ─────────────────────────────────────────────────

export const PtahPages = defineComponent({
    name: 'PtahPages',
    props: screenProps,
    setup(props) {
        const client = props.api ?? new PtahApi();
        const t = computed(() => rolesWords(props.locale));
        const pages = useListing<PageListing>((search, page) => listPages(client, { search, page }), () => t.value);
        const selected = ref<PageRow | null>(null);
        const objects = useListing<ObjectListing | null>(
            (search, page) => (selected.value ? listObjects(client, selected.value.id, { search, page }) : Promise.resolve(null)),
            () => t.value,
            [() => selected.value?.id],
        );

        const pageOpen = ref(false);
        const editingPage = ref<number | null>(null);
        const pageForm = ref<PageForm>(blankPage());
        const objectOpen = ref(false);
        const editingObject = ref<number | null>(null);
        const objectForm = ref<ObjectForm>(blankObject());
        const result = ref<AdminResult | null>(null);
        const saving = ref(false);
        const deleting = ref<{ target: 'page' | 'object'; id: number } | null>(null);
        const removing = ref(false);

        const select = (row: PageRow) => {
            selected.value = row;
            objects.search.value = '';
            objects.page.value = 1;
        };

        const openPage = (row: PageRow | null) => {
            editingPage.value = row?.id ?? null;
            pageForm.value = row ? pageFormOf(row) : blankPage();
            result.value = null;
            pageOpen.value = true;
        };

        const openObject = (row: ObjectRow | null) => {
            editingObject.value = row?.id ?? null;
            objectForm.value = row ? objectFormOf(row) : blankObject();
            result.value = null;
            objectOpen.value = true;
        };

        const submit = async (action: () => Promise<AdminResult>, done: string, close: () => void) => {
            saving.value = true;
            try {
                const answer = await action();
                if (answer.ok) {
                    forgeToast(done, 'success');
                    close();
                    pages.reload();
                    objects.reload();
                } else {
                    result.value = answer;
                }
            } catch (error) {
                result.value = { ok: false, error: errorOf(error, t.value), errors: {}, messages: [] };
            } finally {
                saving.value = false;
            }
        };

        const remove = async () => {
            const target = deleting.value;
            if (!target) {
                return;
            }
            removing.value = true;
            try {
                const answer = target.target === 'page' ? await deletePage(client, target.id) : await deleteObject(client, selected.value?.id ?? 0, target.id);
                forgeToast(answer.ok ? (target.target === 'page' ? t.value.pageDeleted : t.value.objectDeleted) : (answer.error ?? t.value.loadError), answer.ok ? 'success' : 'danger');
                if (answer.ok) {
                    if (target.target === 'page' && selected.value?.id === target.id) {
                        selected.value = null;
                    }
                    pages.reload();
                    objects.reload();
                }
            } catch (error) {
                forgeToast(errorOf(error, t.value), 'danger');
            } finally {
                removing.value = false;
                deleting.value = null;
            }
        };

        const pagesColumn = (tt: RolesWords) => {
            const data = pages.data.value;

            return h('section', { 'aria-label': tt.pagesColumn }, [
                h('div', { class: 'pc-toolbar' }, [
                    h('h2', { class: 'pc-title' }, tt.pagesColumn),
                    h(ForgeButton, { size: 'sm', onClick: () => openPage(null) }, () => `+ ${tt.newPage}`),
                    searchBox(pages.search.value, tt.searchPage, (value) => (pages.search.value = value)),
                ]),
                pages.error.value
                    ? h(ForgeAlert, { type: 'danger' }, () => pages.error.value)
                    : !data
                      ? loadingState(tt)
                      : data.data.length === 0
                        ? h(ForgeEmpty, { title: tt.pagesEmpty, description: tt.pagesEmptyHint })
                        : h('div', { 'aria-busy': pages.loading.value || undefined }, [
                              h(
                                  'ul',
                                  { class: 'pf pf-list pa-plain-list' },
                                  data.data.map((row) =>
                                      h('li', { key: row.id, class: 'pf-list-item pa-row pa-role-item', 'data-selected': selected.value?.id === row.id ? 'true' : undefined }, [
                                          h(
                                              'button',
                                              {
                                                  type: 'button',
                                                  class: 'pc-link pa-role-pick',
                                                  'aria-pressed': selected.value?.id === row.id,
                                                  onClick: () => select(row),
                                              },
                                              [
                                                  row.icon ? h('span', { 'aria-hidden': 'true' }, `${row.icon} `) : null,
                                                  h('strong', row.name),
                                                  ' ',
                                                  h('small', tt.objCount.replace(':count', String(row.objects_count))),
                                                  h('br'),
                                                  h('small', [h('code', row.slug)]),
                                              ],
                                          ),
                                          h('span', [
                                              h('button', { type: 'button', class: 'pc-icon-btn', 'aria-label': `${tt.edit} ${row.name}`, title: tt.edit, onClick: () => openPage(row) }, '✎'),
                                              h('button', { type: 'button', class: 'pc-icon-btn pc-icon-btn--danger', 'aria-label': `${tt.delete} ${row.name}`, title: tt.delete, onClick: () => (deleting.value = { target: 'page', id: row.id }) }, '🗑'),
                                          ]),
                                      ]),
                                  ),
                              ),
                              pager(data.meta, tt, (page) => (pages.page.value = page)),
                          ]),
            ]);
        };

        const objectsColumn = (tt: RolesWords) => {
            const page = selected.value;
            if (!page) {
                return h('section', { 'aria-label': tt.selectPageHint }, [h(ForgeEmpty, { description: tt.selectPageHint })]);
            }
            const data = objects.data.value;
            const title = tt.objectsOf.replace(':page', page.name);

            return h('section', { 'aria-label': title }, [
                h('div', { class: 'pc-toolbar' }, [
                    h('h2', { class: 'pc-title' }, title),
                    h(ForgeButton, { size: 'sm', onClick: () => openObject(null) }, () => `+ ${tt.newObject}`),
                    searchBox(objects.search.value, tt.searchObject, (value) => (objects.search.value = value)),
                ]),
                objects.error.value
                    ? h(ForgeAlert, { type: 'danger' }, () => objects.error.value)
                    : !data || data.page.id !== page.id
                      ? loadingState(tt)
                      : data.data.length === 0
                        ? h(ForgeEmpty, { title: tt.objectsEmpty, description: tt.objectsEmptyHint })
                        : h('div', { 'aria-busy': objects.loading.value || undefined }, [
                              h(
                                  ForgeTable,
                                  {
                                      headers: [
                                          { key: 'key', label: tt.colKeyLabel },
                                          { key: 'type', label: tt.colType },
                                          { key: 'section', label: tt.colSection },
                                          { key: 'actions', label: tt.colActions },
                                      ],
                                      rows: data.data.map((row) => ({ row, key: row.obj_key, type: row.obj_type, section: row.section, actions: '' })),
                                  },
                                  {
                                      'cell-key': ({ row }: { row: { row: ObjectRow } }) => [h('div', row.row.obj_label), h('small', [h('code', row.row.obj_key)])],
                                      'cell-actions': ({ row }: { row: { row: ObjectRow } }) =>
                                          h('span', { class: 'pa-inline-g1' }, [
                                              h('button', { type: 'button', class: 'pc-icon-btn', 'aria-label': `${tt.edit} ${row.row.obj_key}`, title: tt.edit, onClick: () => openObject(row.row) }, '✎'),
                                              h(
                                                  'button',
                                                  { type: 'button', class: 'pc-icon-btn pc-icon-btn--danger', 'aria-label': `${tt.delete} ${row.row.obj_key}`, title: tt.delete, onClick: () => (deleting.value = { target: 'object', id: row.row.id }) },
                                                  '🗑',
                                              ),
                                          ]),
                                  },
                              ),
                              pager(data.meta, tt, (number) => (objects.page.value = number)),
                          ]),
            ]);
        };

        return () => {
            const tt = t.value;
            const errors = result.value?.errors ?? {};
            const types = pages.data.value?.types ?? ['page', 'button', 'field', 'link', 'section', 'api', 'report', 'tab'];
            const failureAlert = result.value?.error ? h(ForgeAlert, { type: 'danger' }, () => result.value?.error) : null;

            return h('div', { class: 'pf pc-root' }, [
                h(ForgePageHeader, { title: tt.pagesTitle, subtitle: tt.pagesSubtitle }),
                h('div', { class: 'pa-role-cols' }, [pagesColumn(tt), objectsColumn(tt)]),

                modal(
                    pageOpen.value,
                    () => (pageOpen.value = false),
                    editingPage.value === null ? tt.pageModalNew : tt.pageModalEdit,
                    () => [
                        h(ForgeButton, { color: 'light', onClick: () => (pageOpen.value = false) }, () => tt.cancel),
                        h(
                            ForgeButton,
                            { loading: saving.value, onClick: () => void submit(() => savePage(client, editingPage.value, pageForm.value), editingPage.value === null ? tt.pageCreated : tt.pageUpdated, () => (pageOpen.value = false)) },
                            () => tt.save,
                        ),
                    ],
                    () => [
                        section([
                            failureAlert,
                            input(pageForm, 'slug', { label: tt.pageSlug, required: true, placeholder: 'admin.users', error: errors.slug }),
                            input(pageForm, 'name', { label: tt.pageName, required: true, error: errors.name }),
                            h(ForgeTextarea, { label: tt.pageDescription, rows: 2, modelValue: pageForm.value.description, 'onUpdate:modelValue': (value: string) => (pageForm.value = { ...pageForm.value, description: value }) }),
                            input(pageForm, 'route', { label: tt.pageRoute, placeholder: 'admin.users.index', error: errors.route }),
                            input(pageForm, 'icon', { label: tt.pageIcon, placeholder: 'bx bx-home', error: errors.icon }),
                            h('div', { class: 'pa-row' }, [toggleOf(pageForm, 'is_active', tt.pageActive), input(pageForm, 'sort_order', { label: tt.pageOrder, type: 'number', min: 0, error: errors.sort_order }, (value) => Number(value) || 0)]),
                        ]),
                    ],
                ),

                modal(
                    objectOpen.value,
                    () => (objectOpen.value = false),
                    editingObject.value === null ? tt.objectModalNew : tt.objectModalEdit,
                    () => [
                        h(ForgeButton, { color: 'light', onClick: () => (objectOpen.value = false) }, () => tt.cancel),
                        h(
                            ForgeButton,
                            {
                                loading: saving.value,
                                onClick: () =>
                                    void submit(
                                        () => saveObject(client, selected.value?.id ?? 0, editingObject.value, objectForm.value),
                                        editingObject.value === null ? tt.objectCreated : tt.objectUpdated,
                                        () => (objectOpen.value = false),
                                    ),
                            },
                            () => tt.save,
                        ),
                    ],
                    () => [
                        section([
                            failureAlert,
                            input(objectForm, 'section', { label: tt.objectSection, required: true, placeholder: 'main / toolbar / form', error: errors.section }),
                            h(ForgeSelect, {
                                label: tt.objectType,
                                required: true,
                                options: types.map((type) => ({ value: type, label: type })),
                                modelValue: objectForm.value.obj_type,
                                error: errors.obj_type,
                                'onUpdate:modelValue': (value: unknown) => (objectForm.value = { ...objectForm.value, obj_type: String(value ?? '') }),
                            }),
                            input(objectForm, 'obj_key', { label: tt.objectKey, required: true, placeholder: 'users.store', error: errors.obj_key }),
                            input(objectForm, 'obj_label', { label: tt.objectLabel, required: true, error: errors.obj_label }),
                            h('div', { class: 'pa-row' }, [toggleOf(objectForm, 'is_active', tt.objectActive), input(objectForm, 'obj_order', { label: tt.objectOrder, type: 'number', min: 0, error: errors.obj_order }, (value) => Number(value) || 0)]),
                        ]),
                    ],
                ),

                confirmDelete(deleting.value !== null, deleting.value?.target === 'page' ? tt.pageDeleteText : tt.objectDeleteText, tt, removing.value, () => (deleting.value = null), () => void remove()),
                h(ForgeToastHost),
            ]);
        };
    },
});

// ── Permission guide ────────────────────────────────────────────────────────

const rich = (text: string, urls?: GuideUrls | null): (VNode | string)[] =>
    textParts(text).map((part) => {
        if (part.kind === 'code') {
            return h('code', part.text);
        }
        if (part.kind === 'strong') {
            return h('strong', part.text);
        }
        if (part.kind === 'link') {
            const href = part.target ? urls?.[part.target] : null;

            return href ? h('a', { class: 'pc-link', href }, part.text) : part.text;
        }

        return part.text;
    });

const step = (number: number, title: string, desc: string, action: VNode | null, children: () => (VNode | null)[]) =>
    h(ForgeCard, null, {
        header: () => h('div', { class: 'pa-row' }, [h('div', [h('h3', { class: 'pa-flat' }, `${number}. ${title}`), h('small', desc)]), action]),
        default: children,
    });

export const PtahPermissionGuide = defineComponent({
    name: 'PtahPermissionGuide',
    props: {
        ...screenProps,
        /** Where the guide's "Go to …" buttons lead, over what the server answers (e.g. the stack's own pages). */
        urls: { type: Object as PropType<Partial<GuideUrls>>, default: undefined },
    },
    setup(props) {
        const client = props.api ?? new PtahApi();
        const words = computed(() => rolesWords(props.locale));
        const info = ref<GuideInfo | null>(null);
        const error = ref<string | null>(null);
        const tab = ref('overview');
        // ForgeTabs only takes a starting tab: "See examples →" remounts it on another one.
        const jumps = ref(0);
        const jump = (id: string) => {
            tab.value = id;
            jumps.value++;
        };

        onMounted(() => {
            getGuide(client)
                .then((value) => (info.value = value))
                .catch((reason) => (error.value = failure(reason, words.value)));
        });

        return () => {
            const w = words.value;
            const g = w.guide;
            if (error.value) {
                return h('div', { class: 'pf pc-root' }, [h(ForgeAlert, { type: 'danger' }, () => error.value)]);
            }
            if (!info.value) {
                return loadingState(w);
            }

            const links: GuideUrls = { ...info.value.urls, ...props.urls } as GuideUrls;
            const go = (href: string | null, label: string) => (href ? h(ForgeButton, { size: 'sm', href }, () => label) : null);
            const perms = [w.permRead, w.permCreate, w.permEdit, w.permDelete];

            const overview = () =>
                h('div', { class: 'pa-col-g6' }, [
                    h(ForgeAlert, { type: 'primary', title: g.ovTitle }, () => g.ovBody.map((paragraph) => h('p', rich(paragraph)))),
                    h('section', [
                        h('h2', `1. ${g.archTitle}`),
                        h(
                            'ol',
                            { class: 'pa-role-steps' },
                            g.arch.map((node, index) =>
                                h('li', { key: node.title, class: 'pa-flex-center-g3' }, [
                                    h(ForgeCard, null, () =>
                                        h('div', { class: 'pa-role-step' }, [h('div', { 'aria-hidden': 'true' }, node.icon), h('strong', node.title), h('div', [h('small', node.desc)]), h('div', [h('small', node.example)])]),
                                    ),
                                    index < g.arch.length - 1 ? h('span', { 'aria-hidden': 'true' }, '→') : null,
                                ]),
                            ),
                        ),
                    ]),
                    h('section', [
                        h('h2', `2. ${g.conceptsTitle}`),
                        h(
                            'div',
                            { class: 'pa-role-cards' },
                            g.concepts.map((concept, index) =>
                                h(
                                    ForgeCard,
                                    { key: concept.title },
                                    {
                                        header: () => `${concept.icon} ${concept.title}`,
                                        default: () => [
                                            h('p', rich(concept.body)),
                                            concept.chips.length > 0 ? h('p', { class: 'pa-flex-wrap-g1' }, concept.chips.map((chip) => h('code', { key: chip }, chip))) : null,
                                            index === 2 ? h('p', { class: 'pa-flex-g3' }, g.permsPreview.map((perm) => h('span', { key: perm }, `✓ ${perm}`))) : null,
                                            concept.warn ? h(ForgeAlert, { type: 'warn' }, () => concept.warn) : null,
                                        ],
                                    },
                                ),
                            ),
                        ),
                    ]),
                    h('section', [
                        h('h2', `3. ${g.flowTitle}`),
                        h(ForgeCard, null, () => [
                            h(
                                'ol',
                                { class: 'pa-role-flow' },
                                g.flow.map((item) =>
                                    h('li', { key: item.text, class: 'pa-row' }, [
                                        h('span', item.text),
                                        item.outcome === 'no' ? h('strong', g.flowNo) : null,
                                        item.outcome === 'granted' ? h('strong', g.flowGranted) : null,
                                    ]),
                                ),
                            ),
                            h('p', { class: 'pa-row pa-justify-start' }, [h('strong', g.flowGranted), h('strong', g.flowDenied)]),
                        ]),
                    ]),
                ]);

            const setup = () =>
                h('div', { class: 'pa-col-g4' }, [
                    h(ForgeAlert, { type: 'primary' }, () => rich(g.prereq)),
                    step(1, g.s1Title, g.s1Desc, go(links.departments, g.s1Btn), () => [h('p', g.s1Body), h('strong', `${g.s1Example}:`), h('ul', g.s1Examples.map((line) => h('li', { key: line }, rich(line))))]),
                    step(2, g.s2Title, g.s2Desc, go(links.pages, g.s2Btn), () => [
                        h('p', rich(g.s2Body)),
                        h('div', { class: 'pa-role-tiles' }, [
                            h(ForgeCard, null, {
                                header: () => g.s2PageTitle,
                                default: () =>
                                    h('table', { class: 'pf-table' }, [
                                        h('tbody', [
                                            h('tr', [h('th', { scope: 'row' }, g.s2PageSlug), h('td', [h('code', 'admin.vendas')])]),
                                            h('tr', [h('th', { scope: 'row' }, g.s2PageName), h('td', g.s2PageNameValue)]),
                                            h('tr', [h('th', { scope: 'row' }, g.s2PageIcon), h('td', '🛒')]),
                                        ]),
                                    ]),
                            }),
                            h(ForgeCard, null, {
                                header: () => g.s2ObjTitle,
                                default: () =>
                                    h(
                                        'ul',
                                        { class: 'pa-list-bare' },
                                        [
                                            ['vendas.criar-pedido', 'button'],
                                            ['vendas.ver-desconto', 'field'],
                                            ['vendas.exportar', 'action'],
                                        ].map(([key, type]) => h('li', { key, class: 'pa-row' }, [h('code', key), h('span', type)])),
                                    ),
                            }),
                        ]),
                    ]),
                    step(3, g.s3Title, g.s3Desc, go(links.roles, g.s3Btn), () => [
                        h('p', rich(g.s3Body)),
                        h('h4', g.s3ExTitle),
                        h('table', { class: 'pf-table' }, [
                            h('thead', [h('tr', [h('th', g.s3ColObj), ...perms.map((perm) => h('th', { key: perm }, perm))])]),
                            h(
                                'tbody',
                                GUIDE_MATRIX_EXAMPLE.map(([key, ...flags]) => h('tr', { key }, [h('td', [h('code', key)]), ...flags.map((flag, index) => h('td', { key: index }, flag ? '✓' : '—'))])),
                            ),
                        ]),
                        h('p', [h('small', rich(g.s3Note))]),
                    ]),
                    step(4, g.s4Title, g.s4Desc, go(links.users, g.s4Btn), () => [h('p', rich(g.s4Body)), h('h4', rich(g.s4ExTitle)), ...g.s4Examples.map((line) => h('p', { key: line }, rich(line)))]),
                    step(5, g.s5Title, g.s5Desc, h(ForgeButton, { size: 'sm', onClick: () => jump('code') }, () => g.s5Btn), () => [h('p', rich(g.s5Body)), h('p', rich(g.s5Note))]),
                    h(ForgeCard, null, {
                        header: () => g.colTitle,
                        default: () => [h('p', [h('small', g.colDesc)]), h('p', rich(g.colBody)), h(ForgeAlert, { type: 'warn' }, () => rich(g.colWarn))],
                    }),
                ]);

            const code = () =>
                h(
                    'div',
                    { class: 'pa-col-g4' },
                    GUIDE_CODE.map((sample) =>
                        h('figure', { key: sample.caption, class: 'pa-flat' }, [
                            h('figcaption', [h('code', sample.caption)]),
                            h('pre', { class: 'pa-role-code' }, [h('code', sample.code)]),
                        ]),
                    ),
                );

            const faq = () =>
                h('div', { class: 'pa-col-g2' }, [
                    ...g.faq.map((item) => h('details', { key: item.q, class: 'pf-card pa-role-faq-item' }, [h('summary', { class: 'pa-role-faq-q' }, item.q), h('p', rich(item.a, links))])),
                    h(ForgeAlert, { type: 'primary', title: `🙋 ${g.helpTitle}` }, () => rich(g.helpBody)),
                ]);

            return h('div', { class: 'pf pc-root' }, [
                h(ForgePageHeader, { title: g.title, subtitle: g.subtitle }, () => h('span', { class: 'pa-role-badge' }, g.badge)),
                h(
                    ForgeTabs,
                    {
                        key: jumps.value,
                        tabs: [
                            { id: 'overview', label: g.tabOverview },
                            { id: 'setup', label: g.tabSetup },
                            { id: 'code', label: g.tabCode },
                            { id: 'faq', label: g.tabFaq },
                        ],
                        defaultTab: tab.value,
                        onChange: (id: string) => (tab.value = id),
                    },
                    { overview, setup, code, faq },
                ),
                h('p', { class: 'pa-role-footnote', title: g.versionTitle }, `ptah v${info.value.version}`),
            ]);
        };
    },
});
