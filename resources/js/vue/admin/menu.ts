/**
 * The menu editor and the system settings for Vue, over the admin API.
 * Who may administer the structure, the safe URL of a menu item, the typed
 * rules of each setting and the company → global scope are the server's
 * (MenuList, SettingsPage); these screens only ask and show.
 */
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { CSSProperties, PropType, VNode } from 'vue';
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

const screenProps = {
    api: { type: Object as PropType<PtahApi>, default: undefined },
    locale: { type: String, default: undefined },
};

const pillStyle: CSSProperties = { display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '2px 8px', borderRadius: '999px', fontSize: '12px', fontWeight: 500, background: 'var(--pc-bg-soft)', color: 'var(--pc-text-strong)', border: '1px solid var(--pc-line)' };
const chipStyle: CSSProperties = { display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '36px', height: '36px', borderRadius: '6px', fontSize: '20px', background: 'var(--pc-bg-soft)', flexShrink: 0 };
const mutedStyle: CSSProperties = { fontSize: '12px', color: 'var(--pc-text-muted)' };
const errorStyle: CSSProperties = { margin: '4px 0 0', fontSize: '12px', color: 'var(--pc-danger)' };
const sectionStyle: CSSProperties = { padding: '16px', border: '1px solid var(--pc-line)', borderRadius: 'var(--pc-radius)', background: 'var(--pc-bg)' };

/** 403 says so; anything else is a generic failure. */
const failureText = (error: unknown, t: MenuWords) => (error instanceof ApiError && error.status === 403 ? t.forbidden : error instanceof ApiError && error.message ? error.message : t.error);

const showMessages = (messages: AdminMessage[] | undefined) => (messages ?? []).forEach((message) => forgeToast(message.text, message.level));

const fieldError = (text?: string | null) => (text ? h('p', { class: 'pf-field-error', role: 'alert', style: errorStyle }, text) : null);

const svgIcon = (d: string) =>
    h('svg', { width: 16, height: 16, fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor', 'aria-hidden': 'true' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': 2, d })]);
const editIcon = () => svgIcon('M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z');
const trashIcon = () => svgIcon('M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16');

// ── Menu ────────────────────────────────────────────────────────────────────

export const PtahMenuAdmin = defineComponent({
    name: 'PtahMenuAdmin',
    props: screenProps,
    setup(props) {
        const client = props.api ?? new PtahApi();
        const t = computed(() => menuWords(props.locale));
        const state = ref<MenuListState>({ search: '', type: '', sort: 'link_order', direction: 'asc', page: 1 });
        const search = ref('');
        const listing = ref<MenuListing | null>(null);
        const loading = ref(true);
        const failure = ref<string | null>(null);
        // The modal: `editingId` null = a new item.
        const formOpen = ref(false);
        const editingId = ref<number | null>(null);
        const form = ref<MenuForm>(blankMenuForm());
        const groups = ref<MenuGroupOption[]>([]);
        const errors = ref<Record<string, string>>({});
        const saving = ref(false);
        const deleteId = ref<number | null>(null);
        const deleting = ref(false);
        let seq = 0;

        const load = async (next: MenuListState) => {
            const mine = ++seq;
            loading.value = true;
            try {
                const answer = await listMenu(client, next);
                if (mine === seq) {
                    listing.value = answer;
                    failure.value = null;
                }
            } catch (error) {
                if (mine === seq) {
                    failure.value = failureText(error, t.value);
                }
            } finally {
                if (mine === seq) {
                    loading.value = false;
                }
            }
        };

        onMounted(() => void load(state.value));
        watch(state, (next) => void load(next), { deep: true });

        // The search waits for the typing to stop (300 ms, as wire:model.live.debounce).
        let timer: ReturnType<typeof setTimeout> | undefined;
        watch(search, (value) => {
            clearTimeout(timer);
            if (value !== state.value.search) {
                timer = setTimeout(() => (state.value = { ...state.value, search: value, page: 1 }), 300);
            }
        });
        onBeforeUnmount(() => clearTimeout(timer));

        const set = <K extends keyof MenuForm>(key: K, value: MenuForm[K]) => (form.value = { ...form.value, [key]: value });

        const openCreate = () => {
            editingId.value = null;
            form.value = blankMenuForm();
            groups.value = listing.value?.groups ?? [];
            errors.value = {};
            formOpen.value = true;
        };

        const openEdit = async (id: number) => {
            try {
                const item = await showMenuItem(client, id);
                const { id: _id, ...fields } = item.data; // eslint-disable-line @typescript-eslint/no-unused-vars
                editingId.value = id;
                form.value = fields;
                groups.value = item.groups;
                errors.value = {};
                formOpen.value = true;
            } catch (error) {
                forgeToast(failureText(error, t.value), 'danger');
            }
        };

        const submit = async () => {
            saving.value = true;
            try {
                const answer = await saveMenuItem(client, editingId.value, form.value);
                showMessages(answer.messages);
                if (answer.ok) {
                    formOpen.value = false;
                    void load(state.value);
                } else {
                    errors.value = answer.errors ?? {};
                    if (answer.error) {
                        forgeToast(answer.error, 'danger');
                    }
                }
            } catch (error) {
                forgeToast(failureText(error, t.value), 'danger');
            } finally {
                saving.value = false;
            }
        };

        const toggle = async (id: number) => {
            try {
                showMessages((await toggleMenuItem(client, id)).messages);
            } catch (error) {
                forgeToast(failureText(error, t.value), 'danger');
            }
            void load(state.value);
        };

        const confirmDelete = async () => {
            if (deleteId.value === null) {
                return;
            }
            deleting.value = true;
            try {
                showMessages((await deleteMenuItem(client, deleteId.value)).messages);
            } catch (error) {
                forgeToast(failureText(error, t.value), 'danger');
            } finally {
                deleting.value = false;
                deleteId.value = null;
            }
            void load(state.value);
        };

        const sortBy = (column: MenuSort) => (state.value = { ...state.value, ...nextSort(state.value, column), page: 1 });
        const arrow = (column: MenuSort) =>
            h('span', { class: state.value.sort === column ? 'pc-sort pc-sort--on' : 'pc-sort', 'aria-hidden': 'true' }, state.value.sort === column ? (state.value.direction === 'asc' ? '↑' : '↓') : '↕');
        const ariaSort = (column: MenuSort) => (state.value.sort === column ? (state.value.direction === 'asc' ? 'ascending' : 'descending') : undefined);

        const table = (w: MenuWords, current: MenuListing): VNode => {
            const rows = current.data;

            return h('table', { class: 'pc-table' }, [
                h('thead', [
                    h('tr', [
                        h('th', { scope: 'col' }, w.colIcon),
                        h('th', { scope: 'col', 'aria-sort': ariaSort('text') }, [h('button', { type: 'button', class: 'pc-th-btn', onClick: () => sortBy('text') }, [`${w.colText} `, arrow('text')])]),
                        h('th', { scope: 'col' }, w.colType),
                        h('th', { scope: 'col' }, w.colUrl),
                        h('th', { scope: 'col' }, w.colParent),
                        h('th', { scope: 'col', class: 'pc-align-center', 'aria-sort': ariaSort('link_order') }, [h('button', { type: 'button', class: 'pc-th-btn', onClick: () => sortBy('link_order') }, [`${w.colOrder} `, arrow('link_order')])]),
                        h('th', { scope: 'col', class: 'pc-align-center' }, w.colStatus),
                        h('th', { scope: 'col', class: 'pc-col-actions' }, w.colActions),
                    ]),
                ]),
                h('tbody', [
                    rows.length === 0 ? h('tr', [h('td', { colspan: 8 }, [h(ForgeEmpty, { title: w.emptyFound, description: w.emptyHint })])]) : null,
                    ...rows.map((row) =>
                        h('tr', { key: row.id, style: row.is_active ? undefined : { opacity: 0.5 } }, [
                            h('td', { class: 'pc-align-center', title: row.icon }, [h('i', { class: row.icon || 'bx bx-circle', 'aria-hidden': 'true' })]),
                            h('td', { style: { fontWeight: 500 } }, row.text),
                            h('td', [h('span', { style: pillStyle }, [h('i', { class: row.type === 'menuGroup' ? 'bx bx-folder' : 'bx bx-link', 'aria-hidden': 'true' }), ` ${row.type === 'menuGroup' ? w.typeGroup : w.typeLink}`])]),
                            h('td', { style: { fontFamily: 'monospace', fontSize: '12px', maxWidth: '180px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }, title: row.url ?? undefined }, row.url ?? '—'),
                            h('td', row.parent ?? '—'),
                            h('td', { class: 'pc-align-center' }, [h('span', { style: pillStyle }, String(row.link_order))]),
                            h('td', { class: 'pc-align-center' }, [
                                h(
                                    'button',
                                    {
                                        type: 'button',
                                        class: 'pc-btn',
                                        style: { padding: '2px 10px', fontSize: '12px', color: row.is_active ? 'var(--pc-success)' : 'var(--pc-danger)' },
                                        title: row.is_active ? w.toggleDisable : w.toggleEnable,
                                        'aria-label': `${row.is_active ? w.toggleDisable : w.toggleEnable}: ${row.text}`,
                                        onClick: () => void toggle(row.id),
                                    },
                                    row.is_active ? w.active : w.inactive,
                                ),
                            ]),
                            h('td', { class: 'pc-col-actions' }, [
                                h('button', { type: 'button', class: 'pc-icon-btn', title: w.edit, 'aria-label': `${w.edit}: ${row.text}`, onClick: () => void openEdit(row.id) }, [editIcon()]),
                                h('button', { type: 'button', class: 'pc-icon-btn pc-icon-btn--danger', title: w.delete, 'aria-label': `${w.delete}: ${row.text}`, onClick: () => (deleteId.value = row.id) }, [trashIcon()]),
                            ]),
                        ]),
                    ),
                ]),
            ]);
        };

        const formBody = (w: MenuWords) => {
            const groupOptions = [{ value: '', label: w.root }, ...groups.value.map((group) => ({ value: String(group.id), label: group.text }))];

            return h(
                'form',
                {
                    class: 'pa-form',
                    novalidate: true,
                    onSubmit: (event: Event) => {
                        event.preventDefault();
                        void submit();
                    },
                },
                [
                    h('fieldset', { style: { border: 0, padding: 0, margin: 0 } }, [
                        h('legend', { class: 'pf-field-label' }, [`${w.formType} `, h('span', { 'aria-hidden': 'true' }, '*')]),
                        h('div', { style: { display: 'flex', gap: '12px', flexWrap: 'wrap' } }, [
                            h(ForgeRadio, { name: 'menu-type', value: 'menuLink', label: w.directLink, modelValue: form.value.type, 'onUpdate:modelValue': () => set('type', 'menuLink') }),
                            h(ForgeRadio, { name: 'menu-type', value: 'menuGroup', label: w.groupType, modelValue: form.value.type, 'onUpdate:modelValue': () => set('type', 'menuGroup') }),
                        ]),
                        fieldError(errors.value.type),
                    ]),
                    h(ForgeInput, { label: w.textLabel, placeholder: w.textPlaceholder, required: true, modelValue: form.value.text, error: errors.value.text ?? null, 'onUpdate:modelValue': (value: string) => set('text', value) }),
                    form.value.type === 'menuLink'
                        ? h(ForgeInput, { label: w.urlLabel, placeholder: w.urlPlaceholder, style: { fontFamily: 'monospace' }, modelValue: form.value.url, error: errors.value.url ?? null, 'onUpdate:modelValue': (value: string) => set('url', value) })
                        : null,
                    h('div', [
                        h('div', { style: { display: 'flex', gap: '8px', alignItems: 'flex-end' } }, [
                            h('div', { style: { flex: 1 } }, [
                                h(ForgeInput, {
                                    label: `${w.iconLabel} ${w.iconHint}`,
                                    placeholder: w.iconPlaceholder,
                                    style: { fontFamily: 'monospace' },
                                    modelValue: form.value.icon,
                                    error: errors.value.icon ?? null,
                                    'onUpdate:modelValue': (value: string) => set('icon', value),
                                }),
                            ]),
                            h('span', { style: chipStyle, title: w.iconPreview, 'data-testid': 'menu-icon-preview' }, [h('i', { class: form.value.icon || 'bx bx-circle', 'aria-hidden': 'true' })]),
                        ]),
                        h('p', { class: 'pc-help' }, [`${w.iconExamples} `, h('code', 'bx bx-home'), ' ', h('code', 'fas fa-cog'), ' ', h('code', 'bx bxs-shopping-bag')]),
                    ]),
                    h(ForgeSelect, {
                        label: w.parentGroup,
                        placeholder: w.root,
                        options: groupOptions,
                        modelValue: form.value.parent_id === null ? '' : String(form.value.parent_id),
                        error: errors.value.parent_id ?? null,
                        'onUpdate:modelValue': (value: string | number | null) => set('parent_id', value === '' || value === null ? null : Number(value)),
                    }),
                    h('div', { class: 'pc-form' }, [
                        h(ForgeInput, { label: w.order, type: 'number', min: 0, modelValue: String(form.value.link_order), error: errors.value.link_order ?? null, 'onUpdate:modelValue': (value: string) => set('link_order', Number(value) || 0) }),
                        h(ForgeSelect, {
                            label: w.opening,
                            modelValue: form.value.target,
                            error: errors.value.target ?? null,
                            options: [
                                { value: '_self', label: w.sameTab },
                                { value: '_blank', label: w.newTab },
                            ],
                            'onUpdate:modelValue': (value: string | number | null) => set('target', value === '_blank' ? '_blank' : '_self'),
                        }),
                        h('div', [
                            h('span', { class: 'pf-field-label' }, w.statusLabel),
                            h(ForgeCheckbox, { label: w.activeCheck, modelValue: form.value.is_active, 'onUpdate:modelValue': (value: boolean) => set('is_active', value) }),
                            fieldError(errors.value.is_active),
                        ]),
                    ]),
                    // Enter in a field submits, as the Livewire form does on the save button.
                    h('button', { type: 'submit', hidden: true, 'aria-hidden': 'true', tabindex: -1 }),
                ],
            );
        };

        return () => {
            const w = t.value;
            const current = listing.value;

            return h('div', { class: 'pf pc-root' }, [
                h(ForgePageHeader, { title: w.menuTitle, subtitle: w.menuSubtitle }),
                h('div', { class: 'pc-toolbar' }, [
                    h(ForgeButton, { size: 'sm', onClick: openCreate }, () => `+ ${w.newItem}`),
                    h('div', { class: 'pc-search' }, [h(ForgeInput, { type: 'search', 'aria-label': w.searchLabel, placeholder: w.searchPlaceholder, modelValue: search.value, 'onUpdate:modelValue': (value: string) => (search.value = value) })]),
                    h('div', { style: { minWidth: '180px' } }, [
                        h(ForgeSelect, {
                            label: w.filterByType,
                            placeholder: w.allTypes,
                            modelValue: state.value.type,
                            options: [
                                { value: '', label: w.allTypes },
                                { value: 'menuLink', label: w.typeLink },
                                { value: 'menuGroup', label: w.typeGroup },
                            ],
                            'onUpdate:modelValue': (value: string | number | null) => (state.value = { ...state.value, type: (String(value ?? '') as MenuListState['type']) || '', page: 1 }),
                        }),
                    ]),
                ]),
                failure.value ? h(ForgeAlert, { type: 'danger' }, () => failure.value) : null,
                h('div', { class: 'pc-table-wrap', 'aria-busy': loading.value }, [
                    loading.value && !current ? h('div', { class: 'pc-empty' }, [h(ForgeSpinner, { label: w.loading }), ` ${w.loading}`]) : null,
                    current ? table(w, current) : null,
                ]),
                current && current.meta.last_page > 1
                    ? h('div', { class: 'pc-footer' }, [h(ForgePagination, { page: current.meta.current_page, lastPage: current.meta.last_page, onChange: (page: number) => (state.value = { ...state.value, page }) })])
                    : null,
                h(
                    ForgeModal,
                    { modelValue: formOpen.value, 'onUpdate:modelValue': (open: boolean) => (formOpen.value = open), title: editingId.value === null ? w.formNew : w.formEdit, size: 'lg' },
                    {
                        default: () => formBody(w),
                        footer: () => [
                            h(ForgeButton, { color: 'dark', flat: true, onClick: () => (formOpen.value = false) }, () => w.cancel),
                            h(ForgeButton, { loading: saving.value, onClick: () => void submit() }, () => (saving.value ? w.saving : editingId.value === null ? w.createItem : w.saveChanges)),
                        ],
                    },
                ),
                h(
                    ForgeModal,
                    { modelValue: deleteId.value !== null, 'onUpdate:modelValue': (open: boolean) => !open && (deleteId.value = null), title: w.deleteTitle, size: 'sm' },
                    {
                        default: () => h('p', w.deleteText),
                        footer: () => [
                            h(ForgeButton, { color: 'dark', flat: true, onClick: () => (deleteId.value = null) }, () => w.cancel),
                            h(ForgeButton, { color: 'danger', loading: deleting.value, onClick: () => void confirmDelete() }, () => w.deleteConfirm),
                        ],
                    },
                ),
                h(ForgeToastHost),
            ]);
        };
    },
});

// ── Settings ────────────────────────────────────────────────────────────────

export const PtahSettings = defineComponent({
    name: 'PtahSettings',
    props: screenProps,
    setup(props) {
        const client = props.api ?? new PtahApi();
        const t = computed(() => menuWords(props.locale));
        const payload = ref<SettingsPayload | null>(null);
        const values = ref<Record<string, string | boolean>>({});
        const errors = ref<Record<string, string>>({});
        const failure = ref<string | null>(null);
        const saving = ref(false);

        const take = (next: SettingsPayload) => {
            payload.value = next;
            const fresh: Record<string, string | boolean> = {};
            next.groups.forEach((group) => group.fields.forEach((field) => (fresh[field.key] = settingFormValue(field))));
            values.value = fresh;
        };

        const load = async (scope?: SettingScope) => {
            try {
                take(await fetchSettings(client, scope));
                errors.value = {};
                failure.value = null;
            } catch (error) {
                failure.value = failureText(error, t.value);
            }
        };

        onMounted(() => void load());

        const submit = async () => {
            const current = payload.value;
            if (!current) {
                return;
            }
            saving.value = true;
            try {
                const answer = await saveSettings(client, current.scope, values.value);
                showMessages(answer.messages);
                if (answer.ok && answer.settings) {
                    take(answer.settings);
                    errors.value = {};
                } else {
                    errors.value = answer.errors ?? {};
                    if (answer.error) {
                        forgeToast(answer.error, 'danger');
                    }
                }
            } catch (error) {
                forgeToast(failureText(error, t.value), 'danger');
                // The table may have gone missing: the reload explains it.
                void load(current.scope);
            } finally {
                saving.value = false;
            }
        };

        const toGlobal = async (key: string) => {
            if (!payload.value) {
                return;
            }
            try {
                const answer = await resetSetting(client, key, payload.value.scope);
                showMessages(answer.messages);
                if (answer.settings) {
                    take(answer.settings);
                }
            } catch (error) {
                forgeToast(failureText(error, t.value), 'danger');
            }
        };

        const control = (w: MenuWords, field: SettingField): (VNode | null)[] => {
            const value = values.value[field.key];
            const update = (next: string | boolean) => (values.value = { ...values.value, [field.key]: next });
            const error = errors.value[field.key];

            switch (field.type) {
                case 'boolean':
                    return [h(ForgeSwitch, { name: `values.${field.key}`, label: field.label, modelValue: value === true, 'onUpdate:modelValue': update }), fieldError(error)];
                case 'select':
                    return [
                        h(ForgeSelect, {
                            name: `values.${field.key}`,
                            label: field.label,
                            placeholder: w.choose,
                            options: field.options,
                            modelValue: String(value ?? ''),
                            error: error ?? null,
                            'onUpdate:modelValue': (next: string | number | null) => update(String(next ?? '')),
                        }),
                    ];
                case 'textarea':
                    return [
                        h(ForgeTextarea, { name: `values.${field.key}`, label: field.label, rows: 4, state: error ? 'danger' : 'normal', 'aria-invalid': error ? true : undefined, modelValue: String(value ?? ''), 'onUpdate:modelValue': update }),
                        fieldError(error),
                    ];
                default:
                    return [
                        h(ForgeInput, {
                            name: `values.${field.key}`,
                            label: field.label,
                            type: settingInputType(field),
                            step: field.type === 'decimal' ? 'any' : undefined,
                            autocomplete: field.secret ? 'new-password' : undefined,
                            modelValue: String(value ?? ''),
                            error: error ?? null,
                            'onUpdate:modelValue': update,
                        }),
                    ];
            }
        };

        const sourceText = (w: MenuWords, field: SettingField) => (field.source === 'company' ? w.sourceCompany : field.source === 'global' ? w.sourceGlobal : w.sourceDefault);

        const body = (w: MenuWords): (VNode | null)[] => {
            const current = payload.value;

            if (failure.value && !current) {
                return [h(ForgeAlert, { type: 'danger' }, () => failure.value)];
            }
            if (!current) {
                return [h('div', { class: 'pc-state', role: 'status' }, [h(ForgeSpinner, { label: w.loading }), ` ${w.loading}`])];
            }
            if (!current.installed) {
                return [h(ForgeAlert, { type: 'warn' }, () => w.notInstalled)];
            }
            if (current.groups.length === 0) {
                return [h(ForgeEmpty, { title: w.none, description: w.noneHint })];
            }

            const global = current.scope === 'global';

            return [
                failure.value ? h(ForgeAlert, { type: 'danger' }, () => failure.value) : null,
                current.per_company && current.can_edit_global
                    ? h('div', { role: 'group', 'aria-label': w.scope, style: { display: 'flex', flexWrap: 'wrap', gap: '8px', marginBottom: '16px' } }, [
                          h(ForgeButton, { size: 'sm', color: global ? 'dark' : 'primary', flat: global, 'aria-pressed': !global, onClick: () => void load('company') }, () => w.scopeCompany),
                          h(ForgeButton, { size: 'sm', color: global ? 'primary' : 'dark', flat: !global, 'aria-pressed': global, onClick: () => void load('global') }, () => w.scopeGlobal),
                      ])
                    : null,
                h(
                    'form',
                    {
                        novalidate: true,
                        style: { display: 'flex', flexDirection: 'column', gap: '24px' },
                        onSubmit: (event: Event) => {
                            event.preventDefault();
                            void submit();
                        },
                    },
                    [
                        ...current.groups.map((group) =>
                            h('section', { key: group.name, style: sectionStyle, 'aria-labelledby': `ptah-settings-${group.name}` }, [
                                h('h2', { id: `ptah-settings-${group.name}`, style: { margin: '0 0 16px', fontSize: '14px', fontWeight: 600, color: 'var(--pc-text-strong)' } }, group.name),
                                h(
                                    'div',
                                    { class: 'pc-form' },
                                    group.fields.map((field) =>
                                        h('div', { key: field.key, class: field.type === 'textarea' ? 'pc-form-field--wide' : undefined }, [
                                            ...control(w, field),
                                            h('div', { style: { ...mutedStyle, display: 'flex', flexWrap: 'wrap', gap: '8px', marginTop: '4px' } }, [
                                                field.help ? h('span', field.help) : null,
                                                !global && current.per_company ? h('span', sourceText(w, field)) : null,
                                                !global && current.per_company && field.source === 'company'
                                                    ? h(
                                                          'button',
                                                          {
                                                              type: 'button',
                                                              class: 'pc-link',
                                                              style: { background: 'none', border: 0, padding: 0, font: 'inherit', cursor: 'pointer', textDecoration: 'underline' },
                                                              'aria-label': `${w.useGlobal}: ${field.label}`,
                                                              onClick: () => void toGlobal(field.key),
                                                          },
                                                          w.useGlobal,
                                                      )
                                                    : null,
                                            ]),
                                        ]),
                                    ),
                                ),
                            ]),
                        ),
                        h('div', { style: { display: 'flex', justifyContent: 'flex-end' } }, [h(ForgeButton, { type: 'submit', loading: saving.value }, () => (saving.value ? w.saving : w.save))]),
                    ],
                ),
            ];
        };

        return () => {
            const w = t.value;

            return h('div', { class: 'pf pc-root' }, [h(ForgePageHeader, { title: w.settingsTitle, subtitle: w.settingsSubtitle }), ...body(w), h(ForgeToastHost)]);
        };
    },
});
