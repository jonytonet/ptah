/**
 * The menu editor (/ptah-menu) and the system settings (/ptah-settings) —
 * types, the API client and the words. The rules (who may administer the
 * structure, the safe URLs of a menu item, the typed rules of each setting,
 * the company → global scope) are the server's: the Livewire components
 * MenuList and SettingsPage.
 */
import { toQuery } from '../api';
import type { PtahApi } from '../api';

// ── Types: menu ─────────────────────────────────────────────────────────────

export type MenuType = 'menuLink' | 'menuGroup';
export type MenuTarget = '_self' | '_blank';
export type MenuSort = 'text' | 'link_order';

export interface MenuRow {
    id: number;
    text: string;
    url: string | null;
    icon: string;
    type: MenuType;
    target: MenuTarget;
    parent_id: number | null;
    /** The parent group's text. */
    parent: string | null;
    link_order: number;
    is_active: boolean;
}

export interface MenuGroupOption {
    id: number;
    text: string;
}

export interface MenuListState {
    search: string;
    /** '' = every type. */
    type: '' | MenuType;
    sort: MenuSort;
    direction: 'asc' | 'desc';
    page: number;
}

export interface MenuListing {
    data: MenuRow[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
    state: { search: string; type: string; sort: MenuSort; direction: 'asc' | 'desc' };
    /** The groups an item may be placed under. */
    groups: MenuGroupOption[];
}

export interface MenuForm {
    text: string;
    url: string;
    icon: string;
    type: MenuType;
    target: MenuTarget;
    parent_id: number | null;
    link_order: number;
    is_active: boolean;
}

export interface MenuItemPayload {
    data: MenuForm & { id: number | null };
    /** Every group but the item itself. */
    groups: MenuGroupOption[];
}

export interface AdminMessage {
    text: string;
    level: string;
}

/** The outcome of an action; `errors` maps a field to its first message. */
export interface MenuActionResult {
    ok: boolean;
    error?: string | null;
    errors?: Record<string, string>;
    messages?: AdminMessage[];
    is_active?: boolean | null;
}

// ── Types: settings ─────────────────────────────────────────────────────────

export type SettingType = 'text' | 'textarea' | 'integer' | 'decimal' | 'boolean' | 'email' | 'url' | 'date' | 'select';
export type SettingScope = 'company' | 'global';
export type SettingSource = 'company' | 'global' | 'default';

export interface SettingField {
    key: string;
    label: string;
    type: SettingType;
    help: string | null;
    /** Its value never comes back (always ''); a blank one is "unchanged" on save. */
    secret: boolean;
    options: { value: string; label: string }[];
    value: unknown;
    source: SettingSource;
}

export interface SettingsPayload {
    /** False until `php artisan ptah:settings:install` + migrate. */
    installed: boolean;
    scope: SettingScope;
    /** Values per company, and a company is active. */
    per_company: boolean;
    can_edit_global: boolean;
    groups: { name: string; fields: SettingField[] }[];
}

export interface SettingsResult {
    ok: boolean;
    error?: string | null;
    errors?: Record<string, string>;
    messages?: AdminMessage[];
    settings?: SettingsPayload;
    installed?: boolean;
}

// ── Client ──────────────────────────────────────────────────────────────────

/**
 * A 422 of Laravel's validator (`{message, errors: {field: [..]}}`) and a
 * refusal of the screen (`{ok: false, error}`) in the same shape.
 */
function normalize<T extends { ok: boolean; error?: string | null; errors?: Record<string, string> }>(raw: Record<string, unknown> | null): T {
    if (raw && raw.ok === undefined) {
        const errors: Record<string, string> = {};
        for (const [field, messages] of Object.entries((raw.errors ?? {}) as Record<string, string[] | string>)) {
            errors[field] = Array.isArray(messages) ? String(messages[0] ?? '') : String(messages);
        }

        return { ok: false, errors, error: Object.keys(errors).length === 0 ? String(raw.message ?? '') : null } as unknown as T;
    }

    return (raw ?? { ok: false }) as unknown as T;
}

export function listMenu(api: PtahApi, state: Partial<MenuListState> = {}): Promise<MenuListing> {
    const query = toQuery({ ...state, search: state.search || undefined, type: state.type || undefined });

    return api.call('GET', `/admin/menu${query ? `?${query}` : ''}`);
}

export function showMenuItem(api: PtahApi, id: number): Promise<MenuItemPayload> {
    return api.call('GET', `/admin/menu/${id}`);
}

/** Create (`id` null) or update. */
export async function saveMenuItem(api: PtahApi, id: number | null, form: MenuForm): Promise<MenuActionResult> {
    const raw = await api.call<Record<string, unknown>>(id === null ? 'POST' : 'PUT', id === null ? '/admin/menu' : `/admin/menu/${id}`, form);

    return normalize<MenuActionResult>(raw);
}

export function toggleMenuItem(api: PtahApi, id: number): Promise<MenuActionResult> {
    return api.call('POST', `/admin/menu/${id}/toggle`);
}

export function deleteMenuItem(api: PtahApi, id: number): Promise<MenuActionResult> {
    return api.call('DELETE', `/admin/menu/${id}`);
}

export function fetchSettings(api: PtahApi, scope?: SettingScope): Promise<SettingsPayload> {
    return api.call('GET', `/admin/settings${scope ? `?scope=${scope}` : ''}`);
}

export async function saveSettings(api: PtahApi, scope: SettingScope, values: Record<string, unknown>): Promise<SettingsResult> {
    return normalize<SettingsResult>(await api.call<Record<string, unknown>>('PUT', '/admin/settings', { scope, values }));
}

/** Back to the global value (company scope only). */
export function resetSetting(api: PtahApi, key: string, scope: SettingScope): Promise<SettingsResult> {
    return api.call('POST', `/admin/settings/${encodeURIComponent(key)}/reset`, { scope });
}

// ── Helpers ─────────────────────────────────────────────────────────────────

export const blankMenuForm = (): MenuForm => ({ text: '', url: '', icon: 'bx bx-circle', type: 'menuLink', target: '_self', parent_id: null, link_order: 0, is_active: true });

/** As the Livewire header: the same column flips the direction, another starts ascending. */
export function nextSort(state: Pick<MenuListState, 'sort' | 'direction'>, column: MenuSort): Pick<MenuListState, 'sort' | 'direction'> {
    return { sort: column, direction: state.sort === column && state.direction === 'asc' ? 'desc' : 'asc' };
}

/** The `<input type>` of a setting, as the Blade view picks it. */
export function settingInputType(field: SettingField): string {
    switch (field.type) {
        case 'integer':
        case 'decimal':
            return 'number';
        case 'email':
        case 'url':
        case 'date':
            return field.type;
        default:
            return field.secret ? 'password' : 'text';
    }
}

/** A setting's value as the form holds it: a boolean stays a boolean, the rest a string. */
export function settingFormValue(field: SettingField): string | boolean {
    if (field.type === 'boolean') {
        return field.value === true || field.value === 1 || field.value === '1' || field.value === 'true';
    }

    return field.value === null || field.value === undefined ? '' : String(field.value);
}

// ── Words ───────────────────────────────────────────────────────────────────

const en = {
    // Menu
    menuTitle: 'Manage Menu',
    menuSubtitle: 'Register and organize the system sidebar items.',
    newItem: 'New Item',
    searchPlaceholder: 'Search menu item...',
    searchLabel: 'Search',
    filterByType: 'Filter by type:',
    allTypes: 'All types',
    typeLink: 'Link',
    typeGroup: 'Group',
    colIcon: 'Icon',
    colText: 'Text',
    colType: 'Type',
    colUrl: 'URL',
    colParent: 'Parent group',
    colOrder: 'Order',
    colStatus: 'Status',
    colActions: 'Actions',
    emptyFound: 'No menu items found',
    emptyHint: "Add the first item using the 'New Item' button",
    active: 'Active',
    inactive: 'Inactive',
    toggleDisable: 'Click to disable',
    toggleEnable: 'Click to enable',
    formNew: 'New Menu Item',
    formEdit: 'Edit Menu Item',
    formType: 'Type',
    directLink: 'Direct link',
    groupType: 'Group (with sub-items)',
    textLabel: 'Displayed text',
    textPlaceholder: 'e.g.: Products, Reports...',
    urlLabel: 'URL',
    urlPlaceholder: '/dashboard, /products, https://...',
    iconLabel: 'Icon',
    iconHint: '(CSS class Boxicons or FontAwesome)',
    iconPlaceholder: 'bx bx-home  /  fas fa-user',
    iconPreview: 'Preview',
    iconExamples: 'Examples:',
    parentGroup: 'Parent group',
    root: 'Root (top level)',
    order: 'Order',
    opening: 'Opening target',
    sameTab: 'Same tab',
    newTab: 'New tab',
    statusLabel: 'Status',
    activeCheck: 'Active',
    saveChanges: 'Save changes',
    createItem: 'Create item',
    deleteTitle: 'Delete item',
    deleteText: 'This action cannot be undone. If it is a group, the children will be unlinked.',
    deleteConfirm: 'Yes, delete',
    // Settings
    settingsTitle: 'Settings',
    settingsSubtitle: 'Values the system reads — changed here, no deploy needed.',
    notInstalled: 'Settings are not installed. Run php artisan ptah:settings:install and php artisan migrate.',
    none: 'No setting declared yet',
    noneHint: 'Declare them in config/ptah-settings.php (definitions).',
    scope: 'Which values to edit',
    scopeCompany: 'This company',
    scopeGlobal: 'All companies (global)',
    sourceCompany: 'Set for this company.',
    sourceGlobal: 'Using the global value.',
    sourceDefault: 'Using the default.',
    useGlobal: 'Use the global value',
    choose: 'Select…',
    // Shared
    edit: 'Edit',
    delete: 'Delete',
    cancel: 'Cancel',
    save: 'Save',
    saving: 'Saving…',
    loading: 'Loading…',
    error: 'Something went wrong. Try again.',
    forbidden: 'You do not have permission to perform this action.',
};

export type MenuWords = typeof en;

const ptBR: MenuWords = {
    menuTitle: 'Gerenciar Menu',
    menuSubtitle: 'Cadastre e organize os itens da barra lateral do sistema.',
    newItem: 'Novo Item',
    searchPlaceholder: 'Buscar item de menu...',
    searchLabel: 'Buscar',
    filterByType: 'Filtrar por tipo:',
    allTypes: 'Todos os tipos',
    typeLink: 'Link',
    typeGroup: 'Grupo',
    colIcon: 'Ícone',
    colText: 'Texto',
    colType: 'Tipo',
    colUrl: 'URL',
    colParent: 'Grupo pai',
    colOrder: 'Ordem',
    colStatus: 'Status',
    colActions: 'Ações',
    emptyFound: 'Nenhum item de menu encontrado',
    emptyHint: "Adicione o primeiro item usando o botão 'Novo Item'",
    active: 'Ativo',
    inactive: 'Inativo',
    toggleDisable: 'Clique para desativar',
    toggleEnable: 'Clique para ativar',
    formNew: 'Novo Item de Menu',
    formEdit: 'Editar Item de Menu',
    formType: 'Tipo',
    directLink: 'Link direto',
    groupType: 'Grupo (com sub-itens)',
    textLabel: 'Texto exibido',
    textPlaceholder: 'ex: Produtos, Relatórios…',
    urlLabel: 'URL',
    urlPlaceholder: '/dashboard, /produtos, https://…',
    iconLabel: 'Ícone',
    iconHint: '(classe CSS — Boxicons ou FontAwesome)',
    iconPlaceholder: 'bx bx-home  /  fas fa-user',
    iconPreview: 'Pré-visualização',
    iconExamples: 'Exemplos:',
    parentGroup: 'Grupo pai',
    root: 'Raiz (nível superior)',
    order: 'Ordem',
    opening: 'Abertura',
    sameTab: 'Mesma aba',
    newTab: 'Nova aba',
    statusLabel: 'Status',
    activeCheck: 'Ativo',
    saveChanges: 'Salvar alterações',
    createItem: 'Criar item',
    deleteTitle: 'Excluir item',
    deleteText: 'Essa ação não pode ser desfeita. Se for um grupo, os filhos serão desvinculados.',
    deleteConfirm: 'Sim, excluir',
    settingsTitle: 'Configurações',
    settingsSubtitle: 'Valores que o sistema lê — alterados aqui, sem deploy.',
    notInstalled: 'As configurações não estão instaladas. Rode php artisan ptah:settings:install e php artisan migrate.',
    none: 'Nenhuma configuração declarada ainda',
    noneHint: 'Declare em config/ptah-settings.php (definitions).',
    scope: 'Quais valores editar',
    scopeCompany: 'Esta empresa',
    scopeGlobal: 'Todas as empresas (global)',
    sourceCompany: 'Definido para esta empresa.',
    sourceGlobal: 'Usando o valor global.',
    sourceDefault: 'Usando o padrão.',
    useGlobal: 'Usar o valor global',
    choose: 'Selecione…',
    edit: 'Editar',
    delete: 'Excluir',
    cancel: 'Cancelar',
    save: 'Salvar',
    saving: 'Salvando…',
    loading: 'Carregando…',
    error: 'Algo deu errado. Tente novamente.',
    forbidden: 'Você não tem permissão para realizar esta ação.',
};

const dictionaries: Record<string, MenuWords> = { en, pt: ptBR };

/** The words for a locale (`pt_BR`, `pt-BR`, `en`…); English when unknown. */
export function menuWords(locale?: string | null): MenuWords {
    const lang = (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').toLowerCase().slice(0, 2);

    return dictionaries[lang] ?? en;
}
