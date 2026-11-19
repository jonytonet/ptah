/**
 * The company area of the admin API — companies, departments and the company
 * switcher: the types, one client function per endpoint, and the words of the
 * screens. Nothing framework-specific: the React and Vue screens are drawn
 * over this.
 */
import { toQuery } from '../api';
import type { PtahApi } from '../api';

// ── Types ───────────────────────────────────────────────────────────────────

export type SortDirection = 'asc' | 'desc';

/** What a listing is asked for. The screens sort by `name` only, as on Livewire. */
export interface AdminListQuery {
    search: string;
    sort: string;
    direction: SortDirection;
    page: number;
}

export interface AdminListMeta {
    page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface AdminListing<Row> {
    data: Row[];
    meta: AdminListMeta;
    state: { search: string; sort: string; direction: SortDirection };
}

export interface AdminMessage {
    text: string;
    level: string;
}

/**
 * The answer of a save or a delete. A validation error (Laravel's `{message,
 * errors: {field: [..]}}`) and a refusal of the screen (`{ok: false, error}`)
 * come back in this one shape.
 */
export interface AdminResult {
    ok: boolean;
    error: string | null;
    errors: Record<string, string>;
    messages: AdminMessage[];
}

export interface CompanyRow {
    id: number;
    name: string;
    label: string | null;
    /** The abbreviation the screen shows: the label, or the initials of the name. */
    label_display: string;
    email: string | null;
    phone: string | null;
    tax_id: string | null;
    tax_type: string | null;
    is_default: boolean;
    is_active: boolean;
}

export interface CompanyForm {
    name: string;
    label: string;
    email: string;
    phone: string;
    tax_id: string;
    tax_type: string;
    is_default: boolean;
    is_active: boolean;
}

export interface CompanyListing extends AdminListing<CompanyRow> {
    tax_types: string[];
}

export interface DepartmentRow {
    id: number;
    name: string;
    description: string | null;
    roles_count: number;
    is_active: boolean;
}

export interface DepartmentForm {
    name: string;
    description: string;
    is_active: boolean;
}

export interface SwitcherCompany {
    id: number;
    name: string;
    /** The tab text: the abbreviation, or the first four letters of the name. */
    label: string;
    is_default: boolean;
}

export interface SwitcherState {
    companies: SwitcherCompany[];
    active_id: number;
}

/** The tax types of the company form (the server answers the same list with each listing). */
export const TAX_TYPES = ['cnpj', 'cpf', 'ein', 'vat', 'other'];

export const blankCompany = (): CompanyForm => ({ name: '', label: '', email: '', phone: '', tax_id: '', tax_type: 'cnpj', is_default: false, is_active: true });

export const blankDepartment = (): DepartmentForm => ({ name: '', description: '', is_active: true });

export const blankQuery = (): AdminListQuery => ({ search: '', sort: 'name', direction: 'asc', page: 1 });

/** The next sort after a click on a column header, as the Livewire screens toggle it. */
export function nextSort(query: AdminListQuery, column: string): AdminListQuery {
    return query.sort === column ? { ...query, direction: query.direction === 'asc' ? 'desc' : 'asc', page: 1 } : { ...query, sort: column, direction: 'asc', page: 1 };
}

// ── Client ──────────────────────────────────────────────────────────────────

const BASE = '/admin';

function result(raw: Record<string, unknown> | null): AdminResult {
    if (!raw) {
        return { ok: false, error: null, errors: {}, messages: [] };
    }
    if (raw.ok === undefined) {
        const errors: Record<string, string> = {};
        for (const [field, messages] of Object.entries((raw.errors ?? {}) as Record<string, string[] | string>)) {
            errors[field] = Array.isArray(messages) ? String(messages[0] ?? '') : String(messages);
        }

        return { ok: false, errors, error: Object.keys(errors).length === 0 ? String(raw.message ?? '') : null, messages: [] };
    }

    return {
        ok: Boolean(raw.ok),
        error: (raw.error as string | null | undefined) ?? null,
        errors: (raw.errors as Record<string, string> | undefined) ?? {},
        messages: (raw.messages as AdminMessage[] | undefined) ?? [],
    };
}

const listPath = (resource: string, query: Partial<AdminListQuery>) => {
    const params = toQuery({ ...query, search: query.search || undefined });

    return `${BASE}/${resource}${params ? `?${params}` : ''}`;
};

export function listCompanies(api: PtahApi, query: Partial<AdminListQuery> = {}): Promise<CompanyListing> {
    return api.call('GET', listPath('companies', query));
}

export function showCompany(api: PtahApi, id: number): Promise<{ id: number; form: CompanyForm }> {
    return api.call('GET', `${BASE}/companies/${id}`);
}

/** Create (`id` null) or update. */
export async function saveCompany(api: PtahApi, id: number | null, form: CompanyForm): Promise<AdminResult> {
    return result(await api.call<Record<string, unknown>>(id === null ? 'POST' : 'PUT', id === null ? `${BASE}/companies` : `${BASE}/companies/${id}`, form));
}

export async function deleteCompany(api: PtahApi, id: number): Promise<AdminResult> {
    return result(await api.call<Record<string, unknown>>('DELETE', `${BASE}/companies/${id}`));
}

export function listDepartments(api: PtahApi, query: Partial<AdminListQuery> = {}): Promise<AdminListing<DepartmentRow>> {
    return api.call('GET', listPath('departments', query));
}

export function showDepartment(api: PtahApi, id: number): Promise<{ id: number; form: DepartmentForm }> {
    return api.call('GET', `${BASE}/departments/${id}`);
}

export async function saveDepartment(api: PtahApi, id: number | null, form: DepartmentForm): Promise<AdminResult> {
    return result(await api.call<Record<string, unknown>>(id === null ? 'POST' : 'PUT', id === null ? `${BASE}/departments` : `${BASE}/departments/${id}`, form));
}

export async function deleteDepartment(api: PtahApi, id: number): Promise<AdminResult> {
    return result(await api.call<Record<string, unknown>>('DELETE', `${BASE}/departments/${id}`));
}

/** The companies the signed-in user may switch to, and the active one. */
export function companySwitcher(api: PtahApi): Promise<SwitcherState> {
    return api.call('GET', `${BASE}/company-switcher`);
}

/** Makes `id` the active company. A company the user does not belong to throws ApiError 403. */
export function switchCompany(api: PtahApi, id: number): Promise<SwitcherState & { ok: boolean }> {
    return api.call('POST', `${BASE}/company-switcher`, { id });
}

/**
 * What a list screen needs from its resource — so the companies and the
 * departments screens are one component per framework, fed by this.
 */
export interface AdminResource<Row, Form> {
    list(api: PtahApi, query: Partial<AdminListQuery>): Promise<AdminListing<Row>>;
    show(api: PtahApi, id: number): Promise<{ id: number; form: Form }>;
    save(api: PtahApi, id: number | null, form: Form): Promise<AdminResult>;
    remove(api: PtahApi, id: number): Promise<AdminResult>;
    blank(): Form;
}

export const companies: AdminResource<CompanyRow, CompanyForm> = { list: listCompanies, show: showCompany, save: saveCompany, remove: deleteCompany, blank: blankCompany };

export const departments: AdminResource<DepartmentRow, DepartmentForm> = { list: listDepartments, show: showDepartment, save: saveDepartment, remove: deleteDepartment, blank: blankDepartment };

// ── Words ───────────────────────────────────────────────────────────────────

const en = {
    // Shared by the screens
    search: 'Search',
    loading: 'Loading…',
    loadError: 'Could not load the list.',
    actions: 'Actions',
    edit: 'Edit',
    delete: 'Delete',
    cancel: 'Cancel',
    save: 'Save',
    saving: 'Saving…',
    deleteTitle: 'Confirm deletion',
    active: 'Active',
    inactive: 'Inactive',
    status: 'Status',
    yes: 'Yes',
    no: 'No',
    name: 'Name',
    range: '{first}–{last} of {total}',
    // Companies
    companyTitle: 'Companies',
    companySubtitle: 'Manage the companies and branches in the system.',
    companyNew: 'New Company',
    companySearch: 'Search by name, e-mail or tax ID...',
    companyAbbr: 'Abbr',
    companyEmail: 'E-mail',
    companyTax: 'Tax ID',
    companyDefault: 'Default',
    defaultTag: 'DEFAULT',
    companyEmpty: 'No companies found',
    companyEmptyAdjust: 'Adjust the search filter',
    companyEmptyAdd: 'Add the first company',
    companyModalNew: 'New Company',
    companyModalEdit: 'Edit Company',
    companyNamePh: 'Company name',
    companyLabel: 'Abbr (4 chars)',
    companyLabelHint: 'Displayed in the menu badge',
    companyEmailPh: 'contact@company.com',
    companyPhone: 'Phone',
    companyPhonePh: '(00) 00000-0000',
    companyDocType: 'Document type',
    companyDocNumber: 'Document number',
    companyIsActive: 'Active company',
    companyIsDefault: 'Default company',
    companyDeleteText: 'Are you sure you want to delete this company? This action cannot be undone.',
    // Departments
    deptTitle: 'Departments',
    deptSubtitle: 'Group profiles/roles by department.',
    deptNew: 'New Department',
    deptSearch: 'Search department...',
    deptDescription: 'Description',
    deptRoles: 'Roles',
    deptEmpty: 'No departments found',
    deptEmptyHint: 'Add the first department',
    deptModalNew: 'New Department',
    deptModalEdit: 'Edit Department',
    deptIsActive: 'Active department',
    deptDeleteText: 'Delete this department? Linked roles will lose their department.',
    // Switcher
    selectCompany: 'Select company',
};

export type CompanyWords = typeof en;

const ptBR: CompanyWords = {
    search: 'Buscar',
    loading: 'Carregando…',
    loadError: 'Não foi possível carregar a lista.',
    actions: 'Ações',
    edit: 'Editar',
    delete: 'Excluir',
    cancel: 'Cancelar',
    save: 'Salvar',
    saving: 'Salvando…',
    deleteTitle: 'Confirmar exclusão',
    active: 'Ativo',
    inactive: 'Inativo',
    status: 'Status',
    yes: 'Sim',
    no: 'Não',
    name: 'Nome',
    range: '{first}–{last} de {total}',
    companyTitle: 'Empresas',
    companySubtitle: 'Gerencie as empresas e filiais do sistema.',
    companyNew: 'Nova Empresa',
    companySearch: 'Buscar por nome, e-mail ou CNPJ...',
    companyAbbr: 'Sigla',
    companyEmail: 'E-mail',
    companyTax: 'CNPJ / Documento',
    companyDefault: 'Padrão',
    defaultTag: 'PADRÃO',
    companyEmpty: 'Nenhuma empresa encontrada',
    companyEmptyAdjust: 'Ajuste o filtro de busca',
    companyEmptyAdd: 'Adicione a primeira empresa',
    companyModalNew: 'Nova Empresa',
    companyModalEdit: 'Editar Empresa',
    companyNamePh: 'Nome da empresa',
    companyLabel: 'Sigla (4 chars)',
    companyLabelHint: 'Exibida no badge do menu',
    companyEmailPh: 'contato@empresa.com',
    companyPhone: 'Telefone',
    companyPhonePh: '(00) 00000-0000',
    companyDocType: 'Tipo de documento',
    companyDocNumber: 'Número do documento',
    companyIsActive: 'Empresa ativa',
    companyIsDefault: 'Empresa padrão',
    companyDeleteText: 'Tem certeza que deseja excluir esta empresa? Esta ação não pode ser desfeita.',
    deptTitle: 'Departamentos',
    deptSubtitle: 'Agrupe perfis/roles por departamento.',
    deptNew: 'Novo Departamento',
    deptSearch: 'Buscar departamento...',
    deptDescription: 'Descrição',
    deptRoles: 'Roles',
    deptEmpty: 'Nenhum departamento encontrado',
    deptEmptyHint: 'Adicione o primeiro departamento',
    deptModalNew: 'Novo Departamento',
    deptModalEdit: 'Editar Departamento',
    deptIsActive: 'Departamento ativo',
    deptDeleteText: 'Excluir este departamento? Roles vinculados perderão o departamento.',
    selectCompany: 'Selecionar empresa',
};

const dictionaries: Record<string, CompanyWords> = { en, pt: ptBR };

/** The words of the company screens for a locale (`pt_BR`, `pt-BR`, `en`…); English when unknown. */
export function companyWords(locale?: string | null): CompanyWords {
    const lang = (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').toLowerCase().slice(0, 2);

    return dictionaries[lang] ?? en;
}

/** `{first}–{last} of {total}` filled in. */
export function rangeText(t: CompanyWords, meta: AdminListMeta): string {
    return t.range
        .replace('{first}', String(meta.from ?? 0))
        .replace('{last}', String(meta.to ?? 0))
        .replace('{total}', String(meta.total));
}

/** The colour of a company's abbreviation badge — the Livewire screen's rotating palette, in kit colours. */
export function badgeColor(id: number): string {
    const palette = ['primary', 'warn', 'success', 'danger', 'dark'];

    return palette[id % palette.length];
}
