/**
 * The users-ACL screen and the permission audit log for Vue, over the access
 * API. The rules — MASTER only, the host's user scope, "MASTER cannot be
 * removed", the user form's validation — are the server's (the Livewire
 * components UserPermissionList and AuditList); these screens only ask and show.
 */
import { computed, defineComponent, h, onBeforeUnmount, ref, watch } from 'vue';
import type { PropType, Ref, VNode } from 'vue';
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

const screenProps = {
    api: { type: Object as PropType<PtahApi>, default: undefined },
    locale: { type: String, default: undefined },
};

const toast = (answer: AccessAnswer) => (answer.messages ?? []).forEach((message) => forgeToast(message.text, message.level));

/** What a failed request means to the person: refused, or something broke. */
const failure = (t: AccessWords, error: unknown) => (error instanceof ApiError && (error.status === 403 || error.status === 401) ? t.forbidden : t.error);

/** A ref that settles 300 ms after the last change of `source`, like `wire:model.live.debounce.300ms`. */
function useDebounced<T>(source: () => T, delay = 300): Ref<T> {
    const settled = ref(source()) as Ref<T>;
    let timer: ReturnType<typeof setTimeout> | undefined;

    watch(source, (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => (settled.value = value), delay);
    });
    onBeforeUnmount(() => clearTimeout(timer));

    return settled;
}

const pager = (meta: AccessMeta, t: AccessWords, onPage: (page: number) => void) =>
    meta.last_page <= 1
        ? null
        : h('div', { class: 'pc-footer' }, [
              h('span', t.pagination(meta.from ?? 0, meta.to ?? 0, meta.total)),
              h('div', { class: 'pc-pager' }, [h(ForgePagination, { page: meta.current_page, lastPage: meta.last_page, onChange: onPage })]),
          ]);

/** A yes/no question before an action that cannot be undone from the screen. */
const confirmModal = (question: { text: string; run: () => void } | null, t: AccessWords, onConfirm: () => void, onClose: () => void) =>
    h(
        ForgeModal,
        { modelValue: question !== null, 'onUpdate:modelValue': (open: boolean) => !open && onClose(), title: t.confirm, size: 'sm' },
        {
            default: () => h('p', question?.text ?? ''),
            footer: () => [h(ForgeButton, { color: 'dark', flat: true, onClick: onClose }, () => t.cancel), h(ForgeButton, { color: 'danger', onClick: onConfirm }, () => t.confirm)],
        },
    );

const stateBox = (text: string) => h('div', { class: 'pf pc-state', role: 'status' }, text);

/** A ForgeInput bound to a key of a reactive object. */
const bound = <T extends object>(model: Ref<T>, key: keyof T & string, attrs: Record<string, unknown>) =>
    h(ForgeInput, { ...attrs, modelValue: String(model.value[key] ?? ''), 'onUpdate:modelValue': (value: string) => (model.value = { ...model.value, [key]: value }) });

// ── Users ───────────────────────────────────────────────────────────────────

const blankForm = (sendLink: boolean): AccessUserForm => ({ name: '', email: '', password: '', send_link: sendLink });

export const PtahUserPermissions = defineComponent({
    name: 'PtahUserPermissions',
    props: screenProps,
    setup(props) {
        const client = props.api ?? new PtahApi();
        const t = computed(() => accessWords(props.locale));

        const search = ref('');
        const role = ref(0);
        const page = ref(1);
        const listing = ref<AccessUsersPage | null>(null);
        const loading = ref(true);
        const loadError = ref<string | null>(null);
        const typed = useDebounced(() => search.value);

        // The user form
        const formOpen = ref(false);
        const editingId = ref<number | null>(null);
        const form = ref<AccessUserForm>(blankForm(false));
        const formErrors = ref<Record<string, string>>({});
        const saving = ref(false);

        // The roles modal
        const rolesOf = ref<AccessUserRoles | null>(null);
        const newRoleId = ref(0);
        const newCompanyId = ref(0);
        const roleError = ref<string | null>(null);
        const adding = ref(false);

        const question = ref<{ text: string; run: () => void } | null>(null);

        let request = 0;
        const load = () => {
            const mine = ++request;
            loading.value = true;
            listAccessUsers(client, { search: typed.value, role: role.value, page: page.value })
                .then((value) => {
                    if (mine === request) {
                        listing.value = value;
                        loadError.value = null;
                    }
                })
                .catch((error) => mine === request && (loadError.value = failure(t.value, error)))
                .finally(() => mine === request && (loading.value = false));
        };

        // A new search or filter starts from the first page.
        watch([typed, role], () => {
            if (page.value === 1) {
                load();
            } else {
                page.value = 1;
            }
        });
        watch(page, load);
        load();

        const openNew = () => {
            editingId.value = null;
            form.value = blankForm(listing.value?.can_send_link ?? false);
            formErrors.value = {};
            formOpen.value = true;
        };

        const openEdit = async (id: number) => {
            try {
                const user = await getAccessUser(client, id);
                editingId.value = user.id;
                form.value = { name: user.name, email: user.email, password: '', send_link: false };
                formErrors.value = {};
                formOpen.value = true;
            } catch (error) {
                forgeToast(failure(t.value, error), 'danger');
            }
        };

        const save = async () => {
            saving.value = true;
            try {
                const answer = await saveAccessUser(client, editingId.value, form.value);
                formErrors.value = answer.errors ?? {};
                if (answer.ok) {
                    toast(answer);
                    formOpen.value = false;
                    load();
                }
            } catch (error) {
                forgeToast(failure(t.value, error), 'danger');
            } finally {
                saving.value = false;
            }
        };

        const sendLink = (id: number, email: string) => {
            question.value = {
                text: t.value.linkConfirm(email),
                run: () => {
                    sendAccessPasswordLink(client, id)
                        .then(toast)
                        .catch((error) => forgeToast(failure(t.value, error), 'danger'));
                },
            };
        };

        const openRoles = async (id: number) => {
            try {
                rolesOf.value = await getAccessUserRoles(client, id);
                newRoleId.value = 0;
                newCompanyId.value = 0;
                roleError.value = null;
            } catch (error) {
                forgeToast(failure(t.value, error), 'danger');
            }
        };

        // Every role change answers the bindings as they now are.
        const took = (answer: AccessAnswer & Partial<AccessUserRoles>) => {
            toast(answer);
            roleError.value = answer.ok ? null : (answer.errors?.role_id ?? answer.error ?? null);
            if (answer.assigned && rolesOf.value) {
                rolesOf.value = { user: answer.user ?? rolesOf.value.user, assigned: answer.assigned };
            }
            if (answer.ok) {
                load();
            }
        };

        const addRole = async () => {
            if (!rolesOf.value) {
                return;
            }
            adding.value = true;
            try {
                const answer = await addAccessUserRole(client, rolesOf.value.user.id, newRoleId.value, newCompanyId.value);
                took(answer);
                if (answer.ok) {
                    newRoleId.value = 0;
                    newCompanyId.value = 0;
                }
            } catch (error) {
                forgeToast(failure(t.value, error), 'danger');
            } finally {
                adding.value = false;
            }
        };

        const removeRole = (userRoleId: number) => {
            question.value = {
                text: t.value.removeRoleConfirm,
                run: () => {
                    if (rolesOf.value) {
                        removeAccessUserRole(client, rolesOf.value.user.id, userRoleId)
                            .then(took)
                            .catch((error) => forgeToast(failure(t.value, error), 'danger'));
                    }
                },
            };
        };

        const table = (data: AccessUsersPage, w: AccessWords): VNode[] => [
            h('div', { class: 'pf pf-table-wrap', 'aria-busy': loading.value || undefined }, [
                h('table', { class: 'pf-table' }, [
                    h('thead', [h('tr', [h('th', { scope: 'col' }, w.colUser), h('th', { scope: 'col' }, w.colRoles), h('th', { scope: 'col', class: 'pc-col-actions' }, w.colActions)])]),
                    h('tbody', [
                        data.data.length === 0 ? h('tr', [h('td', { colspan: 3 }, [h(ForgeEmpty, { title: w.usersEmpty, description: w.usersEmptyHint })])]) : null,
                        ...data.data.map((user) =>
                            h('tr', { key: user.id }, [
                                h('td', [
                                    h('div', { class: 'pa-flex-center-g3' }, [
                                        h(ForgeAvatar, { text: user.name, size: 'sm' }),
                                        h('div', [h('div', user.name), h('small', user.email)]),
                                    ]),
                                ]),
                                h('td', [
                                    h('div', { class: 'pa-flex-wrap-g1' }, [
                                        user.roles.length === 0 ? h('small', w.noRoles) : null,
                                        ...user.roles.map((chip) =>
                                            h('span', { key: chip.id, class: 'pa-role-chip', 'data-master': chip.master || undefined }, [
                                                chip.name,
                                                chip.master ? h('small', `(${w.master})`) : null,
                                                chip.company ? h('small', `· ${chip.company}`) : null,
                                            ]),
                                        ),
                                    ]),
                                ]),
                                h('td', { class: 'pc-col-actions' }, [
                                    h(ForgeButton, { size: 'sm', flat: true, 'aria-label': `${w.edit} ${user.name}`, onClick: () => void openEdit(user.id) }, () => w.edit),
                                    h(ForgeButton, { size: 'sm', flat: true, 'aria-label': `${w.manageAccess} ${user.name}`, onClick: () => void openRoles(user.id) }, () => w.manageAccess),
                                    data.can_send_link ? h(ForgeButton, { size: 'sm', flat: true, 'aria-label': `${w.sendLink} ${user.name}`, onClick: () => sendLink(user.id, user.email) }, () => w.sendLink) : null,
                                ]),
                            ]),
                        ),
                    ]),
                ]),
            ]),
            pager(data.meta, w, (next) => (page.value = next)) ?? h('span'),
        ];

        return () => {
            const w = t.value;
            const roleOptions = (listing.value?.roles ?? []).map((item) => ({ value: item.id, label: item.master ? `${item.name} (${w.master})` : item.name }));
            const companyOptions = [{ value: 0, label: w.global }, ...(listing.value?.companies ?? []).map((item) => ({ value: item.id, label: item.name }))];
            const body = loadError.value ? [h(ForgeAlert, { type: 'danger' }, () => loadError.value)] : !listing.value ? [stateBox(w.loading)] : table(listing.value, w);
            const binding = rolesOf.value;

            return h('div', { class: 'pf' }, [
                h(ForgePageHeader, { title: w.usersTitle, subtitle: w.usersSubtitle }, () => [h(ForgeButton, { size: 'sm', onClick: openNew }, () => w.newUser)]),

                h('div', { class: 'pc-toolbar' }, [
                    h('div', { class: 'pc-search' }, [h(ForgeInput, { type: 'search', 'aria-label': w.searchUsers, placeholder: w.searchUsers, modelValue: search.value, 'onUpdate:modelValue': (value: string) => (search.value = value) })]),
                    h('div', { class: 'pc-spacer' }),
                    h(ForgeSelect, { label: w.filterRole, options: [{ value: 0, label: w.allRoles }, ...roleOptions], modelValue: role.value, 'onUpdate:modelValue': (value: unknown) => (role.value = Number(value) || 0) }),
                ]),

                ...body,

                h(
                    ForgeModal,
                    { modelValue: formOpen.value, 'onUpdate:modelValue': (open: boolean) => (formOpen.value = open), title: editingId.value !== null ? w.editUserTitle : w.newUserTitle },
                    {
                        default: () =>
                            h('div', { class: 'pa-form' }, [
                                bound(form, 'name', { label: w.fieldName, name: 'name', required: true, error: formErrors.value.name }),
                                bound(form, 'email', { label: w.fieldEmail, name: 'email', type: 'email', required: true, error: formErrors.value.email }),
                                bound(form, 'password', { label: w.fieldPassword, name: 'password', type: 'password', autocomplete: 'new-password', error: formErrors.value.password, message: editingId.value !== null ? w.passwordHintEdit : w.passwordHintNew }),
                                listing.value?.can_send_link
                                    ? h(ForgeSwitch, { label: w.sendLinkOnSave, name: 'send_link', modelValue: form.value.send_link, 'onUpdate:modelValue': (value: boolean) => (form.value = { ...form.value, send_link: value }) })
                                    : null,
                            ]),
                        footer: () => [
                            h(ForgeButton, { color: 'dark', flat: true, onClick: () => (formOpen.value = false) }, () => w.cancel),
                            h(ForgeButton, { loading: saving.value, onClick: () => void save() }, () => w.save),
                        ],
                    },
                ),

                h(
                    ForgeModal,
                    { modelValue: binding !== null, 'onUpdate:modelValue': (open: boolean) => !open && (rolesOf.value = null), title: `${w.accessOf} ${binding?.user.name ?? ''}`, size: 'lg' },
                    {
                        default: () =>
                            binding
                                ? h('div', { class: 'pa-section' }, [
                                      h('h3', w.assignedRoles),
                                      binding.assigned.length === 0
                                          ? h('p', w.noAssigned)
                                          : h(
                                                'ul',
                                                { class: 'pf pf-list' },
                                                binding.assigned.map((item) =>
                                                    h('li', { key: item.id, class: 'pf-list-item' }, [
                                                        h('span', { class: 'pf-list-text' }, [
                                                            h('span', { class: 'pf-list-title' }, [item.role_name, item.role_master ? ` (${w.master})` : '']),
                                                            h('span', { class: 'pf-list-sub' }, companyLabel(w, item.company_name)),
                                                        ]),
                                                        item.role_master
                                                            ? h('small', w.protectedRole)
                                                            : h(ForgeButton, { size: 'sm', color: 'danger', flat: true, 'aria-label': `${w.remove} ${item.role_name}`, onClick: () => removeRole(item.id) }, () => w.remove),
                                                    ]),
                                                ),
                                            ),
                                      h('h3', w.addRole),
                                      h(ForgeSelect, {
                                          label: w.role,
                                          placeholder: w.choose,
                                          options: [{ value: 0, label: w.choose }, ...roleOptions],
                                          modelValue: newRoleId.value,
                                          error: roleError.value ?? undefined,
                                          'onUpdate:modelValue': (value: unknown) => (newRoleId.value = Number(value) || 0),
                                      }),
                                      h(ForgeSelect, { label: w.company, options: companyOptions, modelValue: newCompanyId.value, 'onUpdate:modelValue': (value: unknown) => (newCompanyId.value = Number(value) || 0) }),
                                      h('div', [h(ForgeButton, { size: 'sm', loading: adding.value, onClick: () => void addRole() }, () => w.add)]),
                                  ])
                                : null,
                        footer: () => h(ForgeButton, { color: 'light', onClick: () => (rolesOf.value = null) }, () => w.close),
                    },
                ),

                confirmModal(
                    question.value,
                    w,
                    () => {
                        question.value?.run();
                        question.value = null;
                    },
                    () => (question.value = null),
                ),
                h(ForgeToastHost),
            ]);
        };
    },
});

// ── Audit ───────────────────────────────────────────────────────────────────

export const PtahAudit = defineComponent({
    name: 'PtahAudit',
    props: screenProps,
    setup(props) {
        const client = props.api ?? new PtahApi();
        const t = computed(() => accessWords(props.locale));

        const filters = ref<AuditFilters>(emptyAuditFilters());
        const page = ref(1);
        const listing = ref<AuditPage | null>(null);
        const loading = ref(true);
        const loadError = ref<string | null>(null);
        const search = useDebounced(() => filters.value.search);
        // The search waits for the typing to stop; the other filters apply at once.
        const applied = computed<AuditFilters>(() => ({ ...filters.value, search: search.value }));

        let request = 0;
        const load = () => {
            const mine = ++request;
            loading.value = true;
            listAudit(client, applied.value, page.value)
                .then((value) => {
                    if (mine === request) {
                        listing.value = value;
                        loadError.value = null;
                    }
                })
                .catch((error) => mine === request && (loadError.value = failure(t.value, error)))
                .finally(() => mine === request && (loading.value = false));
        };

        watch(
            () => JSON.stringify(applied.value),
            () => {
                if (page.value === 1) {
                    load();
                } else {
                    page.value = 1;
                }
            },
        );
        watch(page, load);
        load();

        const set = (key: keyof AuditFilters, value: string) => (filters.value = { ...filters.value, [key]: value });

        return () => {
            const w = t.value;
            const shown = applied.value;
            const filtered = shown.search !== '' || shown.result !== '' || shown.action !== '';
            let body: (VNode | null)[];

            if (loadError.value) {
                body = [h(ForgeAlert, { type: 'danger' }, () => loadError.value)];
            } else if (!listing.value) {
                body = [stateBox(w.loading)];
            } else {
                const data = listing.value;
                body = [
                    h('div', { class: 'pf pf-table-wrap', 'aria-busy': loading.value || undefined }, [
                        h('table', { class: 'pf-table' }, [
                            h('thead', [
                                h('tr', [
                                    h('th', { scope: 'col' }, w.colDatetime),
                                    h('th', { scope: 'col' }, w.colUser),
                                    h('th', { scope: 'col' }, w.colResource),
                                    h('th', { scope: 'col' }, w.action),
                                    h('th', { scope: 'col' }, w.result),
                                    h('th', { scope: 'col' }, w.colIp),
                                ]),
                            ]),
                            h('tbody', [
                                data.data.length === 0
                                    ? h('tr', [
                                          h('td', { colspan: 6 }, [
                                              h(ForgeEmpty, { title: filtered ? w.auditEmptyFiltered : w.auditEmptyTitle, description: filtered ? w.auditEmptyFilteredHint : w.auditEmptyHint }),
                                          ]),
                                      ])
                                    : null,
                                ...data.data.map((row) =>
                                    h('tr', { key: row.id, 'data-result': row.result }, [
                                        h('td', [h('small', row.created_at_display ?? '')]),
                                        h('td', [h('code', `#${row.user_id ?? '—'}`)]),
                                        h('td', [h('code', row.resource_key ?? '—')]),
                                        h('td', auditActionLabel(w, row.action)),
                                        h('td', row.result === 'granted' ? w.granted : w.denied),
                                        h('td', [h('code', row.ip_address ?? '—')]),
                                    ]),
                                ),
                            ]),
                        ]),
                    ]),
                    pager(data.meta, w, (next) => (page.value = next)),
                ];
            }

            return h('div', { class: 'pf' }, [
                h(ForgePageHeader, { title: w.auditTitle, subtitle: w.auditSubtitle }),
                h('div', { class: 'pc-toolbar' }, [
                    h('div', { class: 'pc-search' }, [
                        h(ForgeInput, { type: 'search', 'aria-label': w.auditSearch, placeholder: w.auditSearch, modelValue: filters.value.search, 'onUpdate:modelValue': (value: string) => set('search', value) }),
                    ]),
                    h(ForgeSelect, {
                        label: w.result,
                        placeholder: w.allResults,
                        options: [
                            { value: '', label: w.allResults },
                            { value: 'granted', label: w.granted },
                            { value: 'denied', label: w.denied },
                        ],
                        modelValue: filters.value.result,
                        'onUpdate:modelValue': (value: unknown) => set('result', String(value ?? '')),
                    }),
                    h(ForgeSelect, {
                        label: w.action,
                        placeholder: w.allActions,
                        options: [{ value: '', label: w.allActions }, ...Object.keys(w.actions).map((key) => ({ value: key, label: w.actions[key] }))],
                        modelValue: filters.value.action,
                        'onUpdate:modelValue': (value: unknown) => set('action', String(value ?? '')),
                    }),
                    h(ForgeInput, { label: w.from, type: 'date', modelValue: filters.value.date_from, 'onUpdate:modelValue': (value: string) => set('date_from', value) }),
                    h(ForgeInput, { label: w.to, type: 'date', modelValue: filters.value.date_to, 'onUpdate:modelValue': (value: string) => set('date_to', value) }),
                    h(ForgeButton, { size: 'sm', color: 'light', onClick: () => (filters.value = emptyAuditFilters()) }, () => w.clear),
                ]),
                ...body,
            ]);
        };
    },
});
