/**
 * The company area for Vue — companies, departments and the company
 * switcher — over the admin API. The rules (who may see these screens, the
 * validation, "the default company cannot be deleted", which companies a user
 * may switch to) are the server's: the Livewire components run behind it.
 */
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { PropType, Ref, VNode, VNodeChild } from 'vue';
import { forgetSnapshots, PtahApi } from '../../core';
import { badgeColor, companies, companySwitcher, companyWords, departments, nextSort, rangeText, switchCompany, TAX_TYPES } from '../../core/admin/company';
import type { AdminListing, AdminListQuery, AdminResource, AdminResult, CompanyForm, CompanyRow, CompanyWords, DepartmentForm, DepartmentRow, SwitcherState } from '../../core/admin/company';
import { ForgeAlert, ForgeButton, ForgeEmpty, ForgeInput, ForgeModal, ForgePageHeader, ForgePagination, ForgeSelect, ForgeSwitch, ForgeTextarea, forgeToast, ForgeToastHost } from '../forge';
import { icon as ptahIcon } from '../icons';

const screenProps = {
    api: { type: Object as PropType<PtahApi>, default: undefined },
    locale: { type: String, default: undefined },
};

const toast = (messages: { text: string; level: string }[]) => messages.forEach((message) => forgeToast(message.text, message.level));
const failure = (error: unknown) => (error instanceof Error ? error.message : 'Error');

let forms = 0;

const icon = (d: string) =>
    h('svg', { width: 16, height: 16, fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor', 'aria-hidden': 'true' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d })]);

const EDIT_ICON = 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z';
const TRASH_ICON = 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16';

/** A small coloured tag (status, "default"). */
const pill = (shade: string, text: VNodeChild) => h('span', { class: 'pf pf-badge', 'data-color': shade }, [h('span', { class: 'pf-badge-mark', style: { position: 'static' } }, [text])]);

const statusPill = (t: CompanyWords, active: boolean) => pill(active ? 'success' : 'danger', active ? t.active : t.inactive);

// ── The list screen both resources share ────────────────────────────────────

interface Column<Row> {
    key: string;
    label: string;
    /** The server's sort key, when the column sorts. */
    sort?: string;
    align?: 'center';
    render: (row: Row) => VNodeChild;
}

interface ScreenConfig<Row, Form> {
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
    fields: (form: Form, set: (changes: Partial<Form>) => void, errors: Record<string, string>) => VNodeChild[];
}

/**
 * The state and actions of a list screen — the React AdminScreen's hooks,
 * line for line.
 */
function useAdminScreen<Row extends { id: number }, Form>(client: PtahApi, resource: AdminResource<Row, Form>, onListing?: (listing: AdminListing<Row>) => void) {
    const query = ref<AdminListQuery>({ search: '', sort: 'name', direction: 'asc', page: 1 });
    const search = ref('');
    const listing = ref<AdminListing<Row> | null>(null) as Ref<AdminListing<Row> | null>;
    const loading = ref(true);
    const loadError = ref<string | null>(null);
    const modal = ref(false);
    const editingId = ref<number | null>(null);
    const form = ref(resource.blank()) as Ref<Form>;
    const answer = ref<AdminResult | null>(null);
    const saving = ref(false);
    const deleteId = ref<number | null>(null);
    const deleting = ref(false);
    const formId = `pa-form-${++forms}`;

    const load = async () => {
        loading.value = true;
        try {
            const next = await resource.list(client, query.value);
            listing.value = next;
            onListing?.(next);
            loadError.value = null;
        } catch (error) {
            loadError.value = failure(error);
        } finally {
            loading.value = false;
        }
    };

    onMounted(() => void load());
    watch(query, () => void load());

    // The Livewire screen searches 300 ms after the last key, from page 1.
    let timer: ReturnType<typeof setTimeout> | undefined;
    watch(search, (value) => {
        clearTimeout(timer);
        if (value !== query.value.search) {
            timer = setTimeout(() => (query.value = { ...query.value, search: value, page: 1 }), 300);
        }
    });
    onBeforeUnmount(() => clearTimeout(timer));

    const openCreate = () => {
        editingId.value = null;
        form.value = resource.blank();
        answer.value = null;
        modal.value = true;
    };

    const openEdit = async (id: number) => {
        try {
            const record = await resource.show(client, id);
            editingId.value = id;
            form.value = record.form;
            answer.value = null;
            modal.value = true;
        } catch (error) {
            forgeToast(failure(error), 'danger');
        }
    };

    const save = async () => {
        saving.value = true;
        try {
            const result = await resource.save(client, editingId.value, form.value);
            answer.value = result;
            if (result.ok) {
                modal.value = false;
                toast(result.messages);
                await load();
            }
        } catch (error) {
            answer.value = { ok: false, error: failure(error), errors: {}, messages: [] };
        } finally {
            saving.value = false;
        }
    };

    const remove = async (fallback: string) => {
        if (deleteId.value === null) {
            return;
        }
        deleting.value = true;
        try {
            const result = await resource.remove(client, deleteId.value);
            if (result.ok) {
                toast(result.messages);
            } else {
                forgeToast(result.error ?? fallback, 'danger');
            }
            await load();
        } catch (error) {
            forgeToast(failure(error), 'danger');
        } finally {
            deleting.value = false;
            deleteId.value = null;
        }
    };

    return { query, search, listing, loading, loadError, modal, editingId, form, answer, saving, deleteId, deleting, formId, openCreate, openEdit, save, remove };
}

function renderScreen<Row extends { id: number }, Form>(s: ReturnType<typeof useAdminScreen<Row, Form>>, t: CompanyWords, config: ScreenConfig<Row, Form>): VNode {
    const rows = s.listing.value?.data ?? [];
    const meta = s.listing.value?.meta;
    const errors = s.answer.value?.errors ?? {};
    const span = config.columns.length + 1;
    const center = (column: Column<Row>) => (column.align === 'center' ? 'pc-align-center' : undefined);

    const head = config.columns.map((column) => {
        const sorted = column.sort !== undefined && s.query.value.sort === column.sort;

        return h('th', { key: column.key, class: center(column), 'aria-sort': sorted ? (s.query.value.direction === 'asc' ? 'ascending' : 'descending') : undefined }, [
            column.sort !== undefined
                ? h('button', { type: 'button', class: 'pc-th-btn', onClick: () => (s.query.value = nextSort(s.query.value, column.sort!)) }, [
                      column.label,
                      h('span', { class: `pc-sort${sorted ? ' pc-sort--on' : ''}`, 'aria-hidden': 'true' }, sorted && s.query.value.direction === 'desc' ? '▼' : '▲'),
                  ])
                : column.label,
        ]);
    });

    const body: VNode[] = [];
    if (s.listing.value === null && s.loading.value) {
        body.push(h('tr', [h('td', { class: 'pc-empty', colspan: span, role: 'status' }, t.loading)]));
    }
    if (s.listing.value !== null && rows.length === 0) {
        body.push(h('tr', [h('td', { colspan: span }, [h(ForgeEmpty, { title: config.emptyTitle, description: config.emptyHint(s.query.value.search !== '') })])]));
    }
    for (const row of rows) {
        body.push(
            h('tr', { key: row.id }, [
                ...config.columns.map((column) => h('td', { key: column.key, class: center(column) }, [column.render(row)])),
                h('td', { class: 'pc-align-center', style: { whiteSpace: 'nowrap' } }, [
                    h('button', { type: 'button', class: 'pc-icon-btn', title: t.edit, 'aria-label': t.edit, onClick: () => void s.openEdit(row.id) }, [icon(EDIT_ICON)]),
                    (config.canDelete?.(row) ?? true)
                        ? h('button', { type: 'button', class: 'pc-icon-btn pc-icon-btn--danger', title: t.delete, 'aria-label': t.delete, onClick: () => (s.deleteId.value = row.id) }, [icon(TRASH_ICON)])
                        : null,
                ]),
            ]),
        );
    }

    const set = (changes: Partial<Form>) => (s.form.value = { ...s.form.value, ...changes });

    return h('div', { class: 'pf pc-root' }, [
        h(ForgePageHeader, { title: config.title, subtitle: config.subtitle }),

        h('div', { class: 'pc-toolbar' }, [
            h(ForgeButton, { size: 'sm', onClick: s.openCreate }, () => `+ ${config.newLabel}`),
            h('div', { class: 'pc-search' }, [
                h(ForgeInput, { type: 'search', 'aria-label': t.search, placeholder: config.searchPlaceholder, modelValue: s.search.value, 'onUpdate:modelValue': (value: string) => (s.search.value = value) }),
            ]),
        ]),

        s.loadError.value ? h(ForgeAlert, { type: 'danger' }, () => s.loadError.value) : null,

        h('div', { class: 'pc-table-wrap', 'aria-busy': s.loading.value }, [
            h('table', { class: 'pc-table' }, [h('thead', [h('tr', [...head, h('th', { class: 'pc-align-center' }, t.actions)])]), h('tbody', body)]),
        ]),

        meta && meta.last_page > 1
            ? h('div', { class: 'pc-footer' }, [
                  h('span', rangeText(t, meta)),
                  h('span', { class: 'pc-spacer' }),
                  h(ForgePagination, { page: meta.page, lastPage: meta.last_page, onChange: (page: number) => (s.query.value = { ...s.query.value, page }) }),
              ])
            : null,

        h(
            ForgeModal,
            { modelValue: s.modal.value, 'onUpdate:modelValue': (open: boolean) => (s.modal.value = open), title: config.modalTitle(s.editingId.value !== null), size: config.modalSize },
            {
                default: () =>
                    h(
                        'form',
                        {
                            id: s.formId,
                            class: 'pc-form',
                            novalidate: true,
                            onSubmit: (event: Event) => {
                                event.preventDefault();
                                void s.save();
                            },
                        },
                        [
                            s.answer.value && !s.answer.value.ok && s.answer.value.error ? h('div', { class: 'pc-form-field--wide' }, [h(ForgeAlert, { type: 'danger' }, () => s.answer.value?.error)]) : null,
                            ...config.fields(s.form.value, set, errors),
                        ],
                    ),
                footer: () => [
                    h(ForgeButton, { color: 'light', onClick: () => (s.modal.value = false) }, () => t.cancel),
                    h(ForgeButton, { type: 'submit', form: s.formId, loading: s.saving.value }, () => (s.saving.value ? t.saving : t.save)),
                ],
            },
        ),

        h(
            ForgeModal,
            { modelValue: s.deleteId.value !== null, 'onUpdate:modelValue': (open: boolean) => !open && (s.deleteId.value = null), title: t.deleteTitle, size: 'sm' },
            {
                default: () => h('p', config.deleteText),
                footer: () => [
                    h(ForgeButton, { color: 'light', onClick: () => (s.deleteId.value = null) }, () => t.cancel),
                    h(ForgeButton, { color: 'danger', loading: s.deleting.value, onClick: () => void s.remove(t.loadError) }, () => t.delete),
                ],
            },
        ),

        h(ForgeToastHost),
    ]);
}

// ── Companies ───────────────────────────────────────────────────────────────

export const PtahCompanies = defineComponent({
    name: 'PtahCompanies',
    props: screenProps,
    setup(props) {
        const client = props.api ?? new PtahApi();
        const t = computed(() => companyWords(props.locale));
        const taxTypes = ref<string[]>(TAX_TYPES);
        const screen = useAdminScreen<CompanyRow, CompanyForm>(client, companies, (listing) => (taxTypes.value = (listing as { tax_types?: string[] }).tax_types ?? TAX_TYPES));

        return () => {
            const w = t.value;
            const columns: Column<CompanyRow>[] = [
                {
                    key: 'abbr',
                    label: w.companyAbbr,
                    align: 'center',
                    render: (row) => h('span', { class: 'pf pf-avatar', 'data-size': 'sm', 'data-color': badgeColor(row.id), style: { borderRadius: '6px', fontSize: '10px' }, 'aria-hidden': 'true' }, row.label_display),
                },
                { key: 'name', label: w.name, sort: 'name', render: (row) => [h('strong', row.name), ' ', row.is_default ? pill('warn', w.defaultTag) : null] },
                { key: 'email', label: w.companyEmail, render: (row) => row.email ?? '—' },
                { key: 'tax', label: w.companyTax, render: (row) => (row.tax_id ? `${(row.tax_type ?? '').toUpperCase()} ${row.tax_id}` : '—') },
                { key: 'default', label: w.companyDefault, align: 'center', render: (row) => h('span', { role: 'img', 'aria-label': row.is_default ? w.yes : w.no }, row.is_default ? '✓' : '—') },
                { key: 'status', label: w.status, align: 'center', render: (row) => statusPill(w, row.is_active) },
            ];

            return renderScreen(screen, w, {
                title: w.companyTitle,
                subtitle: w.companySubtitle,
                newLabel: w.companyNew,
                searchPlaceholder: w.companySearch,
                columns,
                emptyTitle: w.companyEmpty,
                emptyHint: (searching) => (searching ? w.companyEmptyAdjust : w.companyEmptyAdd),
                modalTitle: (editing) => (editing ? w.companyModalEdit : w.companyModalNew),
                modalSize: 'lg',
                deleteText: w.companyDeleteText,
                canDelete: (row) => !row.is_default,
                fields: (form, set, errors) => [
                    h(ForgeInput, { label: w.name, required: true, placeholder: w.companyNamePh, modelValue: form.name, error: errors.name, 'onUpdate:modelValue': (name: string) => set({ name }) }),
                    h(ForgeInput, { label: w.companyLabel, placeholder: 'ACME', maxlength: 4, message: w.companyLabelHint, modelValue: form.label, error: errors.label, 'onUpdate:modelValue': (label: string) => set({ label }) }),
                    h(ForgeInput, { label: w.companyEmail, type: 'email', placeholder: w.companyEmailPh, modelValue: form.email, error: errors.email, 'onUpdate:modelValue': (email: string) => set({ email }) }),
                    h(ForgeInput, { label: w.companyPhone, placeholder: w.companyPhonePh, modelValue: form.phone, error: errors.phone, 'onUpdate:modelValue': (phone: string) => set({ phone }) }),
                    h(ForgeSelect, {
                        label: w.companyDocType,
                        options: taxTypes.value.map((type) => ({ value: type, label: type.toUpperCase() })),
                        modelValue: form.tax_type,
                        error: errors.tax_type,
                        'onUpdate:modelValue': (value: string | number | null) => set({ tax_type: String(value ?? '') }),
                    }),
                    h(ForgeInput, { label: w.companyDocNumber, placeholder: '00.000.000/0001-00', modelValue: form.tax_id, error: errors.tax_id, 'onUpdate:modelValue': (tax_id: string) => set({ tax_id }) }),
                    h(ForgeSwitch, { label: w.companyIsActive, modelValue: form.is_active, 'onUpdate:modelValue': (is_active: boolean) => set({ is_active }) }),
                    h(ForgeSwitch, { label: w.companyIsDefault, modelValue: form.is_default, 'onUpdate:modelValue': (is_default: boolean) => set({ is_default }) }),
                ],
            });
        };
    },
});

// ── Departments ─────────────────────────────────────────────────────────────

export const PtahDepartments = defineComponent({
    name: 'PtahDepartments',
    props: screenProps,
    setup(props) {
        const client = props.api ?? new PtahApi();
        const t = computed(() => companyWords(props.locale));
        const screen = useAdminScreen<DepartmentRow, DepartmentForm>(client, departments);

        return () => {
            const w = t.value;
            const columns: Column<DepartmentRow>[] = [
                { key: 'name', label: w.name, sort: 'name', render: (row) => h('strong', row.name) },
                { key: 'description', label: w.deptDescription, render: (row) => row.description ?? '—' },
                { key: 'roles', label: w.deptRoles, align: 'center', render: (row) => pill('light', String(row.roles_count)) },
                { key: 'status', label: w.status, align: 'center', render: (row) => statusPill(w, row.is_active) },
            ];

            return renderScreen(screen, w, {
                title: w.deptTitle,
                subtitle: w.deptSubtitle,
                newLabel: w.deptNew,
                searchPlaceholder: w.deptSearch,
                columns,
                emptyTitle: w.deptEmpty,
                emptyHint: () => w.deptEmptyHint,
                modalTitle: (editing) => (editing ? w.deptModalEdit : w.deptModalNew),
                modalSize: 'md',
                deleteText: w.deptDeleteText,
                fields: (form, set, errors) => [
                    h('div', { class: 'pc-form-field--wide' }, [
                        h(ForgeInput, { label: w.name, required: true, modelValue: form.name, error: errors.name, 'onUpdate:modelValue': (name: string) => set({ name }) }),
                    ]),
                    h('div', { class: 'pc-form-field--wide' }, [
                        h(ForgeTextarea, { label: w.deptDescription, rows: 3, modelValue: form.description, state: errors.description ? 'danger' : 'normal', 'onUpdate:modelValue': (description: string) => set({ description }) }),
                        errors.description ? h('p', { class: 'pf-field-error', role: 'alert' }, errors.description) : null,
                    ]),
                    h('div', { class: 'pc-form-field--wide' }, [
                        h(ForgeSwitch, { label: w.deptIsActive, modelValue: form.is_active, 'onUpdate:modelValue': (is_active: boolean) => set({ is_active }) }),
                    ]),
                ],
            });
        };
    },
});

// ── Company switcher ────────────────────────────────────────────────────────

/** Shows nothing when the user has fewer than two companies to choose from. */
export const PtahCompanySwitcher = defineComponent({
    name: 'PtahCompanySwitcher',
    props: {
        ...screenProps,
        /**
         * 'inline' — the active company's name and one tab per company, for a
         * top bar. 'stacked' — a vertical list of menu items, for inside a dropdown.
         */
        layout: { type: String as PropType<'inline' | 'stacked'>, default: 'inline' },
        /**
         * After a switch. Defaults to reloading the page, as the Livewire
         * switcher does, so every screen reads the new company.
         */
        onSwitched: { type: Function as PropType<(id: number) => void>, default: undefined },
        /** What the page embedded (`data-boot`): drawn on the first frame, not fetched. */
        initial: { type: Object as PropType<SwitcherState | null>, default: null },
    },
    setup(props) {
        const client = props.api ?? new PtahApi();
        const t = computed(() => companyWords(props.locale));
        const state = ref<SwitcherState | null>(props.initial ?? null);
        const busy = ref(false);

        onMounted(() => {
            if (props.initial) {
                return;
            }
            void companySwitcher(client)
                .then((value) => {
                    state.value = value;
                })
                .catch(() => undefined);
        });

        const pick = async (id: number) => {
            if (busy.value) {
                return;
            }
            busy.value = true;
            try {
                const answer = await switchCompany(client, id);
                state.value = { companies: answer.companies, active_id: answer.active_id };
                // Everything changes with the company: the kept listings go, and the page reloads.
                forgetSnapshots();
                (props.onSwitched ?? (() => window.location.reload()))(id);
            } catch (error) {
                forgeToast(failure(error), 'danger');
            } finally {
                busy.value = false;
            }
        };

        return () => {
            const current = state.value;
            if (!current || current.companies.length < 2) {
                return null;
            }
            const active = current.companies.find((company) => company.id === current.active_id);

            if (props.layout === 'stacked') {
                return h('div', { class: 'pl-switcher-stack', role: 'group', 'aria-label': t.value.selectCompany, 'aria-busy': busy.value || undefined }, [
                    h('p', { class: 'pl-switcher-heading' }, t.value.selectCompany),
                    ...current.companies.map((company) => {
                        const isActive = company.id === current.active_id;

                        return h('button', { key: company.id, type: 'button', class: 'pl-switcher-item', 'aria-current': isActive ? 'true' : 'false', onClick: () => void pick(company.id) }, [
                            // The active one is marked by an icon, not only by colour.
                            isActive ? ptahIcon('check', 16, undefined, 'pl-switcher-check') : h('span', { class: 'pl-switcher-check', 'aria-hidden': 'true' }),
                            h('span', { class: 'pl-switcher-label' }, company.name),
                            h('span', { class: 'pl-switcher-code' }, company.label),
                        ]);
                    }),
                ]);
            }

            // company-switcher.blade.php, inline: the active company's name, a rule, one tab per company.
            return h('div', { class: 'pl-switcher', 'aria-busy': busy.value || undefined }, [
                h('span', { class: 'pl-switcher-name' }, active?.name ?? ''),
                h('span', { class: 'pl-switcher-sep', 'aria-hidden': 'true' }),
                h(
                    'nav',
                    { class: 'pl-switcher-bar', role: 'tablist', 'aria-label': t.value.selectCompany },
                    current.companies.map((company) =>
                        h(
                            'button',
                            { key: company.id, type: 'button', role: 'tab', class: 'pl-switcher-tab', title: company.name, 'aria-selected': company.id === current.active_id ? 'true' : 'false', onClick: () => void pick(company.id) },
                            company.label,
                        ),
                    ),
                ),
            ]);
        };
    },
});
