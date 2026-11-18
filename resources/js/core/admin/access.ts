/**
 * The users-ACL screen and the permission audit log, over the access API
 * (`/admin/access`). The rules — MASTER only, the host's user scope, "MASTER
 * cannot be removed" — are the server's; the screens only ask and show.
 */
import type { PtahApi } from '../api';
import { toQuery } from '../api';

// ── Types ───────────────────────────────────────────────────────────────────

export interface AccessMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

/** A role a user holds, as the list shows it. */
export interface AccessRoleChip {
    id: number;
    name: string;
    master: boolean;
    company: string | null;
}

export interface AccessUser {
    id: number;
    name: string;
    email: string;
    roles: AccessRoleChip[];
}

export interface AccessRoleOption {
    id: number;
    name: string;
    master: boolean;
}

export interface AccessCompanyOption {
    id: number;
    name: string;
}

export interface AccessUsersPage {
    data: AccessUser[];
    meta: AccessMeta;
    roles: AccessRoleOption[];
    companies: AccessCompanyOption[];
    /** Whether a password link can be sent (the auth module's reset page exists). */
    can_send_link: boolean;
}

export interface AccessUsersQuery {
    search?: string;
    role?: number;
    page?: number;
}

export interface AccessUserForm {
    name: string;
    email: string;
    password: string;
    send_link: boolean;
}

export interface AccessMessage {
    text: string;
    level: string;
}

/** The outcome of an action: `ok`, a refusal (`error`), field errors, the toasts. */
export interface AccessAnswer {
    ok: boolean;
    error?: string | null;
    errors?: Record<string, string>;
    messages?: AccessMessage[];
}

export interface AccessAssignedRole {
    id: number;
    role_id: number;
    role_name: string;
    role_master: boolean;
    company_id: number | null;
    /** null: global (no company). */
    company_name: string | null;
}

export interface AccessUserRoles {
    user: { id: number; name: string };
    assigned: AccessAssignedRole[];
}

export interface AuditRow {
    id: number;
    created_at: string | null;
    created_at_display: string | null;
    user_id: number | null;
    resource_key: string | null;
    action: string;
    result: string;
    ip_address: string | null;
}

export interface AuditFilters {
    search: string;
    /** '' | 'granted' | 'denied' */
    result: string;
    /** '' | 'create' | 'read' | 'update' | 'delete' */
    action: string;
    date_from: string;
    date_to: string;
}

export interface AuditPage {
    data: AuditRow[];
    meta: AccessMeta;
}

export const emptyAuditFilters = (): AuditFilters => ({ search: '', result: '', action: '', date_from: '', date_to: '' });

// ── Client ──────────────────────────────────────────────────────────────────

const base = '/admin/access';

const withQuery = (path: string, params: Record<string, unknown>) => {
    const query = toQuery(Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== 0 && value !== undefined)));

    return query ? `${path}?${query}` : path;
};

export function listAccessUsers(api: PtahApi, query: AccessUsersQuery = {}): Promise<AccessUsersPage> {
    return api.call('GET', withQuery(`${base}/users`, { search: query.search, role: query.role, page: query.page }));
}

export function getAccessUser(api: PtahApi, id: number): Promise<{ id: number; name: string; email: string }> {
    return api.call('GET', `${base}/users/${id}`);
}

/** Creates (`id` null) or updates a user. A blank password keeps the current one. */
export function saveAccessUser(api: PtahApi, id: number | null, form: AccessUserForm): Promise<AccessAnswer> {
    return api.call(id === null ? 'POST' : 'PUT', id === null ? `${base}/users` : `${base}/users/${id}`, form);
}

export function sendAccessPasswordLink(api: PtahApi, id: number): Promise<AccessAnswer> {
    return api.call('POST', `${base}/users/${id}/password-link`);
}

export function getAccessUserRoles(api: PtahApi, id: number): Promise<AccessUserRoles> {
    return api.call('GET', `${base}/users/${id}/roles`);
}

/** Binds a role; `companyId` 0 is global. */
export function addAccessUserRole(api: PtahApi, id: number, roleId: number, companyId: number): Promise<AccessAnswer & Partial<AccessUserRoles>> {
    return api.call('POST', `${base}/users/${id}/roles`, { role_id: roleId, company_id: companyId });
}

export function removeAccessUserRole(api: PtahApi, id: number, userRoleId: number): Promise<AccessAnswer & Partial<AccessUserRoles>> {
    return api.call('DELETE', `${base}/users/${id}/roles/${userRoleId}`);
}

export function listAudit(api: PtahApi, filters: Partial<AuditFilters> = {}, page = 1): Promise<AuditPage> {
    return api.call('GET', withQuery(`${base}/audit`, { ...filters, page: page > 1 ? page : undefined }));
}

// ── Words ───────────────────────────────────────────────────────────────────

const en = {
    usersTitle: 'Users Access Control',
    usersSubtitle: 'Assign roles and companies to system users.',
    searchUsers: 'Search by name or e-mail...',
    filterRole: 'Filter by role',
    allRoles: 'All roles',
    colUser: 'User',
    colRoles: 'Assigned roles',
    colActions: 'Actions',
    noRoles: 'No roles',
    master: 'Master',
    manageAccess: 'Manage access',
    usersEmpty: 'No users found',
    usersEmptyHint: 'Try adjusting the search filters.',
    accessOf: 'Access',
    assignedRoles: 'Assigned roles',
    remove: 'Remove',
    removeRoleConfirm: 'Remove this role from the user?',
    protectedRole: 'Protected',
    noAssigned: 'No roles assigned.',
    addRole: 'Add role',
    role: 'Role',
    company: 'Company',
    global: 'Global (no company)',
    add: 'Add',
    close: 'Close',
    choose: 'Select...',
    newUser: 'New user',
    newUserTitle: 'New user',
    edit: 'Edit',
    editUserTitle: 'Edit user',
    fieldName: 'Name',
    fieldEmail: 'E-mail',
    fieldPassword: 'Password',
    passwordHintNew: 'Leave blank to let the person choose it: they receive a link to set their password.',
    passwordHintEdit: 'Leave blank to keep the current password.',
    sendLinkOnSave: 'Send a link to set the password',
    sendLink: 'Send password link',
    linkConfirm: (email: string) => `Send a link to set the password to ${email}?`,
    cancel: 'Cancel',
    save: 'Save',
    confirm: 'Confirm',
    loading: 'Loading…',
    error: 'Something went wrong. Try again.',
    forbidden: 'You are not allowed to see this screen.',
    pagination: (first: number, last: number, total: number) => `${first}–${last} of ${total}`,
    // Audit
    auditTitle: 'Permission Auditing',
    auditSubtitle: 'Log of granted and denied accesses. Read-only.',
    auditSearch: 'Search resource, IP, user...',
    allResults: 'All results',
    granted: 'Granted',
    denied: 'Denied',
    result: 'Result',
    allActions: 'All actions',
    action: 'Action',
    actions: { create: 'Create', read: 'Read', update: 'Edit', delete: 'Delete' } as Record<string, string>,
    from: 'From',
    to: 'To',
    clear: 'Clear',
    colDatetime: 'Date/Time',
    colResource: 'Resource',
    colIp: 'IP',
    auditEmptyFiltered: 'No records found',
    auditEmptyFilteredHint: 'Try adjusting the applied filters.',
    auditEmptyTitle: 'No audit records',
    auditEmptyHint: 'Activate with PTAH_PERMISSION_AUDIT=true in .env.',
};

export type AccessWords = typeof en;

const ptBR: AccessWords = {
    usersTitle: 'Usuários — Controle de Acesso',
    usersSubtitle: 'Atribua roles e empresas aos usuários do sistema.',
    searchUsers: 'Buscar por nome ou e-mail...',
    filterRole: 'Filtrar por role',
    allRoles: 'Todos os roles',
    colUser: 'Usuário',
    colRoles: 'Roles atribuídos',
    colActions: 'Ações',
    noRoles: 'Sem roles',
    master: 'Master',
    manageAccess: 'Gerenciar acesso',
    usersEmpty: 'Nenhum usuário encontrado',
    usersEmptyHint: 'Tente ajustar os filtros de busca.',
    accessOf: 'Acesso',
    assignedRoles: 'Roles atribuídos',
    remove: 'Remover',
    removeRoleConfirm: 'Remover este role do usuário?',
    protectedRole: 'Protegido',
    noAssigned: 'Nenhum role atribuído.',
    addRole: 'Adicionar role',
    role: 'Role',
    company: 'Empresa',
    global: 'Global (sem empresa)',
    add: 'Adicionar',
    close: 'Fechar',
    choose: 'Selecione...',
    newUser: 'Novo usuário',
    newUserTitle: 'Novo usuário',
    edit: 'Editar',
    editUserTitle: 'Editar usuário',
    fieldName: 'Nome',
    fieldEmail: 'E-mail',
    fieldPassword: 'Senha',
    passwordHintNew: 'Deixe em branco para a pessoa escolher: ela recebe um link para definir a senha.',
    passwordHintEdit: 'Deixe em branco para manter a senha atual.',
    sendLinkOnSave: 'Enviar link para definir a senha',
    sendLink: 'Enviar link de senha',
    linkConfirm: (email: string) => `Enviar um link para definir a senha para ${email}?`,
    cancel: 'Cancelar',
    save: 'Salvar',
    confirm: 'Confirmar',
    loading: 'Carregando…',
    error: 'Algo deu errado. Tente de novo.',
    forbidden: 'Você não tem permissão para ver esta tela.',
    pagination: (first: number, last: number, total: number) => `${first}–${last} de ${total}`,
    auditTitle: 'Auditoria de Permissões',
    auditSubtitle: 'Log de acessos concedidos e negados. Somente leitura.',
    auditSearch: 'Buscar recurso, IP, usuário...',
    allResults: 'Todos os resultados',
    granted: 'Concedido',
    denied: 'Negado',
    result: 'Resultado',
    allActions: 'Todas as ações',
    action: 'Ação',
    actions: { create: 'Criar', read: 'Ler', update: 'Editar', delete: 'Excluir' },
    from: 'De',
    to: 'Até',
    clear: 'Limpar',
    colDatetime: 'Data/Hora',
    colResource: 'Recurso',
    colIp: 'IP',
    auditEmptyFiltered: 'Nenhum registro encontrado',
    auditEmptyFilteredHint: 'Tente ajustar os filtros aplicados.',
    auditEmptyTitle: 'Nenhum registro de auditoria',
    auditEmptyHint: 'Ative com PTAH_PERMISSION_AUDIT=true no .env.',
};

const dictionaries: Record<string, AccessWords> = { en, pt: ptBR };

/** The words of the access screens for a locale (`pt_BR`, `pt-BR`, `en`…); English when unknown. */
export function accessWords(locale?: string | null): AccessWords {
    const lang = (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').toLowerCase().slice(0, 2);

    return dictionaries[lang] ?? en;
}

/** "Global" when a binding has no company. */
export const companyLabel = (t: AccessWords, name: string | null) => name ?? t.global;

/** The label of an audit action, the raw value when unknown (as the Blade view does). */
export const auditActionLabel = (t: AccessWords, action: string) => t.actions[action] ?? action.charAt(0).toUpperCase() + action.slice(1);
