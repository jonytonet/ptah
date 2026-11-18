/**
 * Access control — roles and their permission matrix, pages and their
 * objects, the permission guide — over the roles API (`/admin/roles`,
 * `/admin/pages`, `/admin/permission-guide`). The rules (MASTER only, one
 * MASTER, MASTER cannot be deleted or deactivated) are the server's.
 */
import type { PtahApi } from '../api';

// ── Types ───────────────────────────────────────────────────────────────────

export interface AdminMeta {
    page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number;
    to: number;
}

export interface AdminMessage {
    text: string;
    level: string;
}

/** The answer of an action: a refusal carries `error` (a business rule) or `errors` (per field). */
export interface AdminResult {
    ok: boolean;
    error: string | null;
    errors: Record<string, string>;
    messages: AdminMessage[];
}

export interface RoleRow {
    id: number;
    name: string;
    description: string | null;
    color: string | null;
    department_id: number | null;
    department: string | null;
    is_master: boolean;
    is_active: boolean;
    permissions_count: number;
}

export interface RoleForm {
    name: string;
    description: string;
    color: string;
    department_id: number | null;
    is_master: boolean;
    is_active: boolean;
}

export interface DepartmentOption {
    id: number;
    name: string;
}

export interface RoleListing {
    data: RoleRow[];
    meta: AdminMeta;
    departments: DepartmentOption[];
}

export type PermissionFlag = 'can_read' | 'can_create' | 'can_update' | 'can_delete';

export const PERMISSION_FLAGS: PermissionFlag[] = ['can_read', 'can_create', 'can_update', 'can_delete'];

export interface MatrixRow {
    id: number;
    page_id: number;
    page_name: string;
    section: string;
    obj_key: string;
    obj_label: string;
    obj_type: string;
    can_create: boolean;
    can_read: boolean;
    can_update: boolean;
    can_delete: boolean;
}

export interface Matrix {
    role: { id: number; name: string };
    data: MatrixRow[];
}

export interface PageRow {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    route: string | null;
    icon: string | null;
    is_active: boolean;
    sort_order: number;
    objects_count: number;
}

export interface PageForm {
    slug: string;
    name: string;
    description: string;
    route: string;
    icon: string;
    is_active: boolean;
    sort_order: number;
}

export interface PageListing {
    data: PageRow[];
    meta: AdminMeta;
    types: string[];
}

export interface ObjectRow {
    id: number;
    page_id: number;
    section: string;
    obj_key: string;
    obj_label: string;
    obj_type: string;
    obj_order: number;
    is_active: boolean;
}

export interface ObjectForm {
    section: string;
    obj_key: string;
    obj_label: string;
    obj_type: string;
    obj_order: number;
    is_active: boolean;
}

export interface ObjectListing {
    page: { id: number; name: string };
    data: ObjectRow[];
    meta: AdminMeta;
}

export interface GuideUrls {
    departments: string | null;
    pages: string | null;
    roles: string | null;
    users: string | null;
    audit: string | null;
}

export interface GuideInfo {
    version: string;
    urls: GuideUrls;
}

// ── Blank forms ─────────────────────────────────────────────────────────────

export const blankRole = (): RoleForm => ({ name: '', description: '', color: '', department_id: null, is_master: false, is_active: true });

export const roleFormOf = (row: RoleRow): RoleForm => ({
    name: row.name,
    description: row.description ?? '',
    color: row.color ?? '',
    department_id: row.department_id,
    is_master: row.is_master,
    is_active: row.is_active,
});

export const blankPage = (): PageForm => ({ slug: '', name: '', description: '', route: '', icon: '', is_active: true, sort_order: 0 });

export const pageFormOf = (row: PageRow): PageForm => ({
    slug: row.slug,
    name: row.name,
    description: row.description ?? '',
    route: row.route ?? '',
    icon: row.icon ?? '',
    is_active: row.is_active,
    sort_order: row.sort_order,
});

export const blankObject = (): ObjectForm => ({ section: 'main', obj_key: '', obj_label: '', obj_type: 'button', obj_order: 0, is_active: true });

export const objectFormOf = (row: ObjectRow): ObjectForm => ({
    section: row.section,
    obj_key: row.obj_key,
    obj_label: row.obj_label,
    obj_type: row.obj_type,
    obj_order: row.obj_order,
    is_active: row.is_active,
});

// ── Client ──────────────────────────────────────────────────────────────────

const query = (params: Record<string, string | number>) => {
    const parts = Object.entries(params)
        .filter(([, value]) => value !== '' && value !== undefined)
        .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`);

    return parts.length ? `?${parts.join('&')}` : '';
};

/** The component's property names (`page_slug`) → the request's (`slug`), so an error lands on its field. */
const PAGE_FIELDS: Record<string, string> = {
    page_slug: 'slug',
    page_name: 'name',
    page_description: 'description',
    page_route: 'route',
    page_icon: 'icon',
    page_is_active: 'is_active',
    page_sort_order: 'sort_order',
};
const OBJECT_FIELDS: Record<string, string> = { obj_section: 'section', obj_is_active: 'is_active' };

/**
 * An action. Laravel's validator (`{message, errors: {field: [..]}}`) and a
 * refusal of the component (`{ok: false, error}`) come back in one shape.
 */
async function act(api: PtahApi, method: string, path: string, body?: unknown, aliases: Record<string, string> = {}): Promise<AdminResult> {
    const raw = (await api.call<Record<string, unknown> | null>(method, path, body)) ?? {};
    const errors: Record<string, string> = {};

    for (const [field, messages] of Object.entries((raw.errors ?? {}) as Record<string, string[] | string>)) {
        errors[aliases[field] ?? field] = Array.isArray(messages) ? String(messages[0] ?? '') : String(messages);
    }

    if (raw.ok === undefined) {
        return { ok: false, error: Object.keys(errors).length === 0 ? String(raw.message ?? '') : null, errors, messages: [] };
    }

    return { ok: Boolean(raw.ok), error: (raw.error as string | null) ?? null, errors, messages: (raw.messages as AdminMessage[]) ?? [] };
}

export const listRoles = (api: PtahApi, params: { search?: string; page?: number } = {}) =>
    api.call<RoleListing>('GET', `/admin/roles${query({ search: params.search ?? '', page: params.page ?? 1 })}`);

export const getRole = async (api: PtahApi, id: number) => (await api.call<{ data: RoleForm }>('GET', `/admin/roles/${id}`)).data;

export const saveRole = (api: PtahApi, id: number | null, form: RoleForm) => act(api, id === null ? 'POST' : 'PUT', id === null ? '/admin/roles' : `/admin/roles/${id}`, form);

export const deleteRole = (api: PtahApi, id: number) => act(api, 'DELETE', `/admin/roles/${id}`);

export const getMatrix = (api: PtahApi, roleId: number) => api.call<Matrix>('GET', `/admin/roles/${roleId}/permissions`);

export const saveMatrix = (api: PtahApi, roleId: number, rows: MatrixRow[]) =>
    act(api, 'PUT', `/admin/roles/${roleId}/permissions`, {
        objects: rows.map((row) => ({ id: row.id, can_create: row.can_create, can_read: row.can_read, can_update: row.can_update, can_delete: row.can_delete })),
    });

export const listPages = (api: PtahApi, params: { search?: string; page?: number } = {}) =>
    api.call<PageListing>('GET', `/admin/pages${query({ search: params.search ?? '', page: params.page ?? 1 })}`);

export const savePage = (api: PtahApi, id: number | null, form: PageForm) => act(api, id === null ? 'POST' : 'PUT', id === null ? '/admin/pages' : `/admin/pages/${id}`, form, PAGE_FIELDS);

export const deletePage = (api: PtahApi, id: number) => act(api, 'DELETE', `/admin/pages/${id}`);

export const listObjects = (api: PtahApi, pageId: number, params: { search?: string; page?: number } = {}) =>
    api.call<ObjectListing>('GET', `/admin/pages/${pageId}/objects${query({ search: params.search ?? '', page: params.page ?? 1 })}`);

export const saveObject = (api: PtahApi, pageId: number, id: number | null, form: ObjectForm) =>
    act(api, id === null ? 'POST' : 'PUT', id === null ? `/admin/pages/${pageId}/objects` : `/admin/pages/${pageId}/objects/${id}`, form, OBJECT_FIELDS);

export const deleteObject = (api: PtahApi, pageId: number, id: number) => act(api, 'DELETE', `/admin/pages/${pageId}/objects/${id}`);

export const getGuide = (api: PtahApi) => api.call<GuideInfo>('GET', '/admin/permission-guide');

// ── The permission matrix, grouped by page as the Livewire modal draws it ──

export interface MatrixGroup {
    page: string;
    section: string;
    rows: { row: MatrixRow; index: number }[];
    checked: number;
    total: number;
    /** Whether a non-empty filter matches the page or one of its rows. */
    matches: boolean;
}

const fold = (text: string) =>
    text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

/** The rows a filter keeps: it looks at the page name, the label and the key. */
export function matrixMatches(row: MatrixRow, filter: string): boolean {
    const needle = fold(filter.trim());

    return needle === '' || fold(`${row.page_name} ${row.obj_label} ${row.obj_key}`).includes(needle);
}

export function groupMatrix(rows: MatrixRow[], filter = ''): MatrixGroup[] {
    const groups = new Map<string, MatrixGroup>();
    const needle = fold(filter.trim());

    rows.forEach((row, index) => {
        let group = groups.get(row.page_name);
        if (!group) {
            group = { page: row.page_name, section: row.section, rows: [], checked: 0, total: 0, matches: needle === '' || fold(row.page_name).includes(needle) };
            groups.set(row.page_name, group);
        }
        group.total += PERMISSION_FLAGS.length;
        group.checked += PERMISSION_FLAGS.filter((flag) => row[flag]).length;
        if (matrixMatches(row, filter)) {
            group.rows.push({ row, index });
            group.matches = true;
        }
    });

    return [...groups.values()];
}

// ── Inline text of the guide ────────────────────────────────────────────────

export interface TextPart {
    kind: 'text' | 'code' | 'strong' | 'link';
    text: string;
    /** For a link: which of the guide's URLs it points at. */
    target?: keyof GuideUrls;
}

/** The guide's words use `code`, **bold** and [label](target) — split into parts to draw. */
export function textParts(text: string): TextPart[] {
    const parts: TextPart[] = [];
    const pattern = /`([^`]+)`|\*\*([^*]+)\*\*|\[([^\]]+)\]\((\w+)\)/g;
    let last = 0;
    let match: RegExpExecArray | null;

    while ((match = pattern.exec(text)) !== null) {
        if (match.index > last) {
            parts.push({ kind: 'text', text: text.slice(last, match.index) });
        }
        if (match[1] !== undefined) {
            parts.push({ kind: 'code', text: match[1] });
        } else if (match[2] !== undefined) {
            parts.push({ kind: 'strong', text: match[2] });
        } else {
            parts.push({ kind: 'link', text: match[3], target: match[4] as keyof GuideUrls });
        }
        last = pattern.lastIndex;
    }
    if (last < text.length) {
        parts.push({ kind: 'text', text: text.slice(last) });
    }

    return parts;
}

/** "1–20 of 45" */
export const rangeText = (template: string, meta: AdminMeta) => template.replace(':first', String(meta.from)).replace(':last', String(meta.to)).replace(':total', String(meta.total));

/** The code samples of the guide, as the Livewire screen shows them (one language for both locales). */
export const GUIDE_CODE: { caption: string; code: string }[] = [
    {
        caption: 'resources/views/vendas/index.blade.php — Helper ptah_can()',
        code: `// Verificar permissão de leitura
@if (ptah_can('vendas.exportar', 'read'))
    <button>Exportar CSV</button>
@endif

// Verificar permissão de criação
@if (ptah_can('vendas.criar-pedido', 'create'))
    <button wire:click="novoPedido">+ Novo Pedido</button>
@endif

// Verificar com escopo de empresa explícito
@if (ptah_can('vendas.ver-desconto', 'read', companyId: $empresa->id))
    <span>Desconto: {{ $pedido->desconto }}%</span>
@endif

// Chave QUALIFICADA — use "pagina::obj_key" quando o mesmo obj_key existe em
// mais de uma Página (PermissionService::KEY_QUALIFIER = '::'). Sem isso o
// obj_key duplicado só resolve pelo mapa BARE, que é global — não por página.
@if (ptah_can('vendas::exportar', 'read'))
    <button>Exportar (só a Página "vendas")</button>
@endif

// Assinatura completa:
// ptah_can(string $objectKey, string $action, mixed $user = null, ?int $companyId = null): bool`,
    },
    {
        caption: 'routes/web.php — Middleware ptah.can',
        code: `// Proteger rota individual — verifica can_read
Route::get('/vendas/exportar', [VendasController::class, 'exportar'])
    ->middleware('ptah.can:vendas.exportar,read');

// Proteger rota de criar — verifica can_create
Route::post('/vendas/pedidos', [PedidoController::class, 'store'])
    ->middleware('ptah.can:vendas.criar-pedido,create');

// 3º parâmetro OPCIONAL: companyId explícito (sem ele, usa sessão/auth)
Route::get('/relatorios/vendas', [RelatorioController::class, 'index'])
    ->middleware('ptah.can:relatorios.vendas,read,1');

// action é OPCIONAL — se omitida, o middleware assume 'read'
Route::middleware(['auth', 'ptah.can:admin.usuarios'])
    ->group(function () {
        Route::resource('usuarios', UsuarioController::class);
    });

// Chave qualificada também funciona aqui — o '::' nunca colide com o
// parsing ':'/',' dos parâmetros do middleware
Route::get('/financeiro/exportar', [FinanceiroController::class, 'exportar'])
    ->middleware('ptah.can:financeiro::toolbar::exportar,read');

// Sintaxe: 'ptah.can:{obj_key},{action?},{companyId?}'
// Actions: read | create | update | delete (default: read)`,
    },
    {
        caption: 'app/Http/Controllers/PedidoController.php — PermissionServiceContract',
        code: `// Usando o contrato diretamente via injeção de dependência
use Ptah\\Contracts\\PermissionServiceContract;

class PedidoController extends Controller
{
    public function store(Request $request, PermissionServiceContract $permissions)
    {
        // check(mixed $user, string $objectKey, string $action, ?int $companyId = null): bool
        // — NÃO é can(userId:, key:, action:); esse método não existe no contrato.
        if (! $permissions->check(auth()->user(), 'vendas.criar-pedido', 'create')) {
            abort(403, 'Sem permissão para criar pedidos');
        }

        // ... criar pedido
    }
}

// Ou pela facade, fora de um construtor:
use Ptah\\Facades\\Permission;

Permission::check(auth()->user(), 'vendas.criar-pedido', 'create');`,
    },
    {
        caption: 'app/Livewire/Vendas/PedidoList.php — Componente Livewire (fora do BaseCrud)',
        code: `class PedidoList extends Component
{
    public function deletar(int $id): void
    {
        // Sem trait dedicado no pacote — verifique com o helper e aborte
        // manualmente.
        if (! ptah_can('vendas.criar-pedido', 'delete')) {
            abort(403);
        }

        Pedido::destroy($id);
    }

    public function render(): View
    {
        return view('livewire.vendas.pedido-list', [
            // No Blade: @if (ptah_can('vendas.exportar', 'read'))
            'podeExportar' => ptah_can('vendas.exportar', 'read'),
        ]);
    }
}

// Isto NÃO é um BaseCrud (Ptah\\Livewire\\BaseCrud\\BaseCrud): lá, basta
// configurar 'permissions.permissionIdentifier' no CrudConfig — o próprio
// componente já gate create/update/delete e também 'read': render() aborta
// 403 antes de a listagem consultar o banco. Sem permissionIdentifier
// configurado, o CRUD fica livre (grants não são consultados).`,
    },
    {
        caption: '.env — Configurações do módulo Ptah',
        code: `# Habilitar os módulos do Ptah
PTAH_MODULE_AUTH=true
PTAH_MODULE_COMPANY=true
PTAH_MODULE_PERMISSIONS=true

# Auditoria de permissões — não existe "número máximo de registros": a
# tabela ptah_permission_audits cresce sem teto até você agendar a poda.
PTAH_PERMISSION_AUDIT=true                  # grava acessos CONCEDIDOS (default: false)
PTAH_PERMISSION_AUDIT_DENIED=true           # também grava NEGADOS (default: true)
PTAH_PERMISSION_AUDIT_MASTER=false          # grava acessos de MASTER (default: false)
PTAH_PERMISSION_AUDIT_RETENTION_DAYS=90     # janela usada por "ptah:audit-prune"

# Cache do mapa de permissões (recomendado ligado)
PTAH_PERMISSION_CACHE=true
PTAH_PERMISSION_CACHE_TTL=3600

# Agende a poda (comando DESTRUTIVO — revise antes de rodar em produção):
#   php artisan ptah:audit-prune --days=90`,
    },
];

/** The "Standard seller" example of step 3: obj_key, read, create, edit, delete. */
export const GUIDE_MATRIX_EXAMPLE: [string, boolean, boolean, boolean, boolean][] = [
    ['vendas.criar-pedido', true, true, true, false],
    ['vendas.ver-desconto', false, false, false, false],
    ['vendas.exportar', true, false, false, false],
];

// ── Words ───────────────────────────────────────────────────────────────────

const en = {
    loading: 'Loading…',
    loadError: 'Could not load this screen. Try again.',
    forbidden: 'You do not have permission to perform this action.',
    cancel: 'Cancel',
    save: 'Save',
    delete: 'Delete',
    edit: 'Edit',
    deleteTitle: 'Confirm deletion',
    active: 'Active',
    inactive: 'Inactive',
    range: ':first–:last of :total',
    search: 'Search',
    // Roles
    rolesTitle: 'Roles / Profiles',
    rolesSubtitle: 'Manage access profiles and their permissions per object.',
    newRole: 'New Role',
    searchRole: 'Search role...',
    colName: 'Name',
    colDepartment: 'Department',
    colPermissions: 'Permissions',
    colStatus: 'Status',
    colActions: 'Actions',
    objectsCount: ':count objects',
    managePermissions: '🔑 Permissions',
    managePermissionsTitle: 'Manage permissions',
    editRole: 'Edit Role',
    roleName: 'Name',
    roleDescription: 'Description',
    roleColor: 'Color (hex)',
    roleDepartment: 'Department',
    roleActive: 'Active role',
    noDepartment: 'No department',
    roleMaster: 'MASTER Role (total bypass)',
    roleIsMaster: '👑 This is the MASTER role',
    roleMasterWarn: '⚠️ MASTER roles have unrestricted access. Only 1 role can be MASTER.',
    rolesEmpty: 'No roles found',
    rolesEmptyHint: 'Add the first access profile',
    roleDeleteText: 'Delete this role? Permissions and user bindings will be removed.',
    roleCreated: 'Role created.',
    roleUpdated: 'Role updated.',
    roleDeleted: 'Role deleted.',
    // Permission matrix
    bindTitle: 'Manage Permissions',
    bindFilter: 'Search pages or objects...',
    bindFilterLabel: 'Filter pages and objects',
    permRead: 'Read',
    permCreate: 'Create',
    permEdit: 'Edit',
    permDelete: 'Delete',
    bindEmpty: 'No objects registered. Go to Pages and register objects first.',
    bindFilterEmpty: 'No pages or objects match your search.',
    bindSave: 'Save Permissions',
    bindSaved: "Permissions for ':role' updated.",
    // Pages and objects
    pagesTitle: 'Pages and Objects',
    pagesSubtitle: 'Register system pages and their objects (buttons, fields, links) for access control.',
    pagesColumn: 'Pages',
    newPage: 'Page',
    searchPage: 'Search page...',
    pagesEmpty: 'No pages registered',
    pagesEmptyHint: 'Create the first page to get started.',
    objectsOf: 'Objects :page',
    newObject: 'Object',
    searchObject: 'Search object...',
    colKeyLabel: 'Key / Label',
    colType: 'Type',
    colSection: 'Section',
    objectsEmpty: 'No objects on this page',
    objectsEmptyHint: 'Add objects to control access.',
    selectPageHint: 'Select a page to see its objects',
    pageModalNew: 'New Page',
    pageModalEdit: 'Edit Page',
    pageSlug: 'Slug',
    pageName: 'Name',
    pageDescription: 'Description',
    pageRoute: 'Laravel Route',
    pageIcon: 'Icon',
    pageActive: 'Active page',
    pageOrder: 'Order',
    objectModalNew: 'New Object',
    objectModalEdit: 'Edit Object',
    objectSection: 'Section',
    objectType: 'Type',
    objectKey: 'Key',
    objectLabel: 'Label',
    objectActive: 'Active object',
    objectOrder: 'Order',
    pageDeleteText: 'Delete this page? All linked objects will also be removed.',
    objectDeleteText: 'Delete this object? Role permissions linked to it will be removed.',
    pageCreated: 'Page created.',
    pageUpdated: 'Page updated.',
    pageDeleted: 'Page deleted.',
    objectCreated: 'Object created.',
    objectUpdated: 'Object updated.',
    objectDeleted: 'Object deleted.',
    objCount: ':count obj',
    // Guide
    guide: {
        title: 'Permission System Guide',
        subtitle: 'How the Ptah ACL works and how to configure access step by step.',
        badge: '📖 Documentation',
        tabOverview: '🗺️ Overview',
        tabSetup: '🔧 Step by Step',
        tabCode: '💻 Code Examples',
        tabFaq: '❓ FAQ',
        versionTitle: 'Installed Ptah version',
        ovTitle: "What is Ptah's permission system?",
        ovBody: [
            'The Ptah ACL (Access Control List) is an access control system based on **Roles**, inspired by the RBAC standard. It allows you to define who can do what in each part of the system, with granularity down to a button or individual field level.',
            "Unlike Laravel's simple `Gate/Policy`, Ptah ACL is **dynamic and manageable via interface** as far as Pages/Objects/Roles/bindings go — but the CHECK POINT is still code (`ptah_can()`, the `ptah.can` middleware, `permissionIdentifier`). The 4 actions (Read/Create/Edit/Delete) are fixed; a new capability is modeled as a new OBJECT (e.g. `crud.config`), not as a new verb — a new verb would require a new column (a migration).",
        ],
        archTitle: 'Architecture — How concepts relate',
        arch: [
            { icon: '🏢', title: 'Departments', desc: 'Optional logical grouping of Roles', example: 'ex: IT, Sales, Finance' },
            { icon: '🎭', title: 'Roles / Profiles', desc: 'Carries permissions per object', example: 'ex: Admin, Seller, Support' },
            { icon: '📄', title: 'Pages + Objects', desc: 'What can be controlled', example: 'ex: /sales, "Export" button, "Discount" field' },
            { icon: '👤', title: 'Users', desc: 'Receive Roles per Company', example: 'ex: John — Admin in Company A' },
            { icon: '🏭', title: 'Companies', desc: 'Binding scope (optional)', example: 'ex: Multi-tenant or Global' },
        ],
        conceptsTitle: 'Core concepts',
        concepts: [
            { icon: '🎭', title: 'Role (Profile)', body: 'A Role is a set of permissions. Instead of giving permissions directly to the user, you create a Role with the permissions and assign the Role to the user.', chips: ['Admin', 'Vendedor', '👑 MASTER'], warn: '' },
            { icon: '📄', title: 'Page', body: 'Represents a module or route in the system. Each Page contains **Objects** — individual elements whose access can be controlled (buttons, fields, links, actions).', chips: ['admin.vendas', 'admin.estoque'], warn: '' },
            { icon: '🔑', title: 'Object + Permission', body: 'An Object is a granular element within a Page. Each object has 4 permission flags: **Read, Create, Edit, Delete**. A Role can have partial permission (read-only, for example).', chips: [], warn: '' },
            {
                icon: '👑',
                title: 'MASTER Role',
                body: 'A Role marked as MASTER has unrestricted access to **all resources**, bypassing checks — and is **GLOBAL by definition**: a MASTER binding with a company set restricts nothing, the company is simply ignored when resolving (silently normalised to NULL on save, and flagged by `ptah:config:doctor` if one already exists that way in the database). The "only 1 MASTER" rule is enforced by the interface, not the database — it is a possible race (check-then-act, no transaction), not a schema guarantee.',
                chips: [],
                warn: '⚠️ Use with care — bypasses all checks and is global; there is no "MASTER for Company X"',
            },
            {
                icon: '🏭',
                title: 'Company Scope',
                body: "A user can have different Roles in different companies. With an active company in the session, the check considers that company's grants **plus the global ones** (a binding with company `NULL`). **Without** a selected company — or with `PTAH_MULTI_COMPANY=false` — only global grants count; a grant scoped to one specific company denies everything in that case. MASTER (above) follows neither rule: it is always global.",
                chips: [],
                warn: '',
            },
            {
                icon: '📋',
                title: 'Audit',
                body: 'When `PTAH_PERMISSION_AUDIT=true`, every GRANTED check is logged. Denied ones are only logged with `PTAH_PERMISSION_AUDIT_DENIED=true` (default: true); MASTER accesses only with `PTAH_PERMISSION_AUDIT_MASTER=true` (default: **false**). Column-level checks (`colsPermission`) are never audited. A logging failure never brings the request down.',
                chips: [],
                warn: '',
            },
        ],
        permsPreview: ['Read', 'Create', 'Edit', 'Delete'],
        flowTitle: 'Access verification flow',
        flow: [
            { text: '① Is the user authenticated?', outcome: 'no' },
            { text: '② Is the requested action create, read, update or delete?', outcome: 'no' },
            { text: "③ Does any of the user's Roles have MASTER?", outcome: 'granted' },
            { text: '④ Resolve the scope: active company in the session (that company\'s grants + globals) — or, with no company/multi-company off, only globals', outcome: '' },
            { text: '⑤ Does the obj_key exist in the BARE map, with can_{action} = true on some active Role?', outcome: '' },
            { text: '⑥ Missed the bare map and the key is qualified (contains "::")? Consult the QUALIFIED map (page::obj_key)', outcome: '' },
        ],
        flowNo: 'DENIED',
        flowGranted: '✅ ACCESS GRANTED',
        flowDenied: '🚫 ACCESS DENIED',
        prereq: '**Prerequisite (human execution — do not automate on deploy):** Run `php artisan migrate` to create the Ptah tables, and `php artisan db:seed --class=Ptah\\Seeders\\DefaultCompanySeeder` to create the default company.',
        s1Title: 'Register Departments (Optional)',
        s1Desc: 'Group your Roles into departments for better organization.',
        s1Btn: 'Go to Departments →',
        s1Body: 'Departments are optional logical groupings for your Roles. Useful when the system has many profiles.',
        s1Example: 'Example',
        s1Examples: ['Department **IT** → Roles: Developer, DevOps, IT Support', 'Department **Sales** → Roles: Seller, Sales Manager, SDR', 'Department **Finance** → Roles: Financial Analyst, Controller'],
        s2Title: 'Register Pages and Objects',
        s2Desc: 'Register the system modules and what can be controlled in them.',
        s2Btn: 'Go to Pages →',
        s2Body: 'A **Page** represents a module or section of the system (ex: `admin.sales`). Each page can have multiple **Objects** — representing granular elements such as buttons, fields or actions. An `obj_key` is only unique within its own Page (more precisely, Page + section); if the same `obj_key` exists on another Page, use the QUALIFIED key `page::obj_key` (or `page::section::obj_key`) to disambiguate — see the **Code Examples** tab.',
        s2PageTitle: '📄 Page Example',
        s2PageSlug: 'Slug',
        s2PageName: 'Name',
        s2PageIcon: 'Icon',
        s2PageNameValue: 'Módulo de Vendas',
        s2ObjTitle: '🔑 Objects of this Page',
        s3Title: 'Create Roles and define permissions',
        s3Desc: 'Create access profiles and configure which objects each profile can access.',
        s3Btn: 'Go to Roles →',
        s3Body: 'Create a Role with name and color. Then click **🔑 Permissions** to define which objects this Role can Read, Create, Edit and Delete.',
        s3ExTitle: 'Example: Role "Standard Seller"',
        s3ColObj: 'Object',
        s3Note: '↑ Seller can create orders but cannot see discounts and cannot export without restriction. Unchecking "Read" on an object that is a CRUD screen\'s `permissionIdentifier` now closes the WHOLE screen (HTTP 403) for whoever lacks it — not just a hidden field anymore.',
        s4Title: 'Link users to Roles',
        s4Desc: 'Assign one or more Roles to each user, with company scope.',
        s4Btn: 'Go to Users →',
        s4Body: 'On the Access Control screen, click **🔑 Manage Access** next to the user. Select a Role and a Company (or "Global" for scope-free access).',
        s4ExTitle: 'Example: User **John Silva**',
        s4Examples: ['Role **Admin** at company **Company A Ltda** → full access in Company A', 'Role **Reader** at company **Company B SA** → read-only in Company B'],
        s5Title: 'Use permissions in code',
        s5Desc: 'See the "Code Examples" tab for full details.',
        s5Btn: 'See examples →',
        s5Body: "Use the helper `ptah_can('object.key', 'read')` in Blade views or the middleware `ptah.can:object.key,read` in routes to protect access.",
        s5Note: "On a Ptah CRUD screen (`Ptah\\Livewire\\BaseCrud\\BaseCrud`), you don't need to call the helper by hand: configure `permissions.permissionIdentifier` in the CrudConfig and the component itself already requires create/update/delete — and also read: without the permission, the screen responds HTTP 403 before the listing is even queried. With no `permissionIdentifier` configured, the CRUD stays open (grants are never consulted). After registering the Page/Objects, run `php artisan ptah:permission:sync` to check/generate the expected permission columns.",
        colTitle: '🔒 Restrict columns by permission (optional)',
        colDesc: 'Hide an entire CRUD column from whoever lacks read permission on that object.',
        colBody: 'In the CrudConfig, tag the column with `colsPermission` pointing at the `obj_key` that controls access to it. Required order: 1) register the Object (Step 2); 2) grant Read to the Roles that should see the column (Step 3); 3) only then tag `colsPermission` on the column. This also applies to exports — the column disappears from the generated file, not just from the screen.',
        colWarn: '⚠️ A column tagged with `colsPermission` is born CLOSED: hidden from EVERYONE until a read grant exists for that object — there is no grace period. A column without the tag stays public, as it always was.',
        faq: [
            { q: 'What happens if the user has no Role?', a: 'Without any Role, the user will not have access to any controlled object. Checks with `ptah_can()` return **false** and the middleware `ptah.can` returns HTTP 403.' },
            { q: 'Can a user have more than one Role?', a: "Yes! A user can have multiple Roles, including in different companies. If ANY of the user's Roles has the requested permission, access is granted — as long as that Role is active, the binding is active in the resolved scope, and the Object is active on an active Page (the 4 activity conditions)." },
            { q: 'What is the MASTER Role and when should I use it?', a: 'A MASTER Role bypasses **all** permission checks, granting unrestricted access — and is **global**, no matter which company the binding carries. Use exclusively for system superadmins. The interface prevents a 2nd MASTER, but that is application-level validation (not the database): it is a possible race, not a schema guarantee.' },
            { q: 'How does company scope work?', a: "When linking a user to a Role, you can specify a Company. (a) With an active company in the session: counts that company's grants + the global ones (a binding with company `NULL`). (b) With no company selected — or `PTAH_MULTI_COMPANY=false`: only globals count. (c) MASTER follows neither rule — it is always global." },
            { q: 'Are permissions cached?', a: 'Yes, by generation: the cache key embeds global and per-user counters, bumped when a Role/RolePermission/UserRole/PageObject/Page changes. Invalidation is instant on any cache driver — including bulk revocations. Manually clearing the framework cache is never required in the normal flow.' },
            { q: 'Can I create Pages and Objects automatically via code?', a: "Yes. Use the seeder or create records in `Ptah\\Models\\PtahPage` (the Page) and `Ptah\\Models\\PageObject` (the Object) directly. Prefer a seeder or `php artisan ptah:permission:sync` over a data migration: the package's migrations are auto-discovered and fire on any host's `php artisan migrate`, even when the feature is unused." },
            { q: 'What happens if I delete an Object that already has defined permissions?', a: 'The permission table entries associated with the object are cascade-removed (irreversible). Roles that had that object lose permission automatically. MASTER users are not affected (bypass). Reversible alternative: mark the Object inactive instead of deleting it — also revokes access, without losing the bindings.' },
            { q: 'How to audit who accessed what?', a: 'Enable `PTAH_PERMISSION_AUDIT=true` in .env — logs GRANTED accesses. Denied ones also need `PTAH_PERMISSION_AUDIT_DENIED=true` (default: true); MASTER accesses need `PTAH_PERMISSION_AUDIT_MASTER=true` (default: false). Column-level checks are never audited. Access the log at [Audit](audit).' },
            { q: "Why can't this user do X?", a: 'Run `php artisan ptah:permission:why {user} {obj_key} --action=read --company=`. It prints the single most specific missing piece, in this order: nonexistent object → inactive object → inactive page → no active Role → no binding → `can_{action}=false` → trashed binding → grant scoped to a different company → grant only on a different Page (suggesting the qualified key). It runs with auditing off, so it never pollutes `ptah_permission_audits`.' },
            { q: 'Does unchecking "Read" hide the whole screen?', a: 'Yes, when the CRUD has `permissions.permissionIdentifier` configured: `render()` aborts with HTTP 403 before the listing is even queried, and export/print follow the same rule. `can_read` defaults to **true**, so no existing binding changes behavior — it only kicks in for whoever explicitly unchecks "Read". With no `permissionIdentifier` configured, the screen stays open.' },
        ],
        helpTitle: 'Need more help?',
        helpBody: 'Check the **Overview** to understand the architecture, the **Step by Step** to configure, and the **Code Examples** to integrate into your project.',
    },
};

export type RolesWords = typeof en;

const ptBR: RolesWords = {
    loading: 'Carregando…',
    loadError: 'Não foi possível carregar esta tela. Tente de novo.',
    forbidden: 'Você não tem permissão para realizar esta ação.',
    cancel: 'Cancelar',
    save: 'Salvar',
    delete: 'Excluir',
    edit: 'Editar',
    deleteTitle: 'Confirmar exclusão',
    active: 'Ativo',
    inactive: 'Inativo',
    range: ':first–:last de :total',
    search: 'Buscar',
    rolesTitle: 'Roles / Perfis',
    rolesSubtitle: 'Gerencie os perfis de acesso e suas permissões por objeto.',
    newRole: 'Novo Role',
    searchRole: 'Buscar role...',
    colName: 'Nome',
    colDepartment: 'Departamento',
    colPermissions: 'Permissões',
    colStatus: 'Status',
    colActions: 'Ações',
    objectsCount: ':count objetos',
    managePermissions: '🔑 Permissões',
    managePermissionsTitle: 'Gerenciar permissões',
    editRole: 'Editar Role',
    roleName: 'Nome',
    roleDescription: 'Descrição',
    roleColor: 'Cor (hex)',
    roleDepartment: 'Departamento',
    roleActive: 'Role ativo',
    noDepartment: 'Sem departamento',
    roleMaster: 'Role MASTER (bypass total)',
    roleIsMaster: '👑 Este é o role MASTER',
    roleMasterWarn: '⚠️ Roles MASTER têm acesso irrestrito. Apenas 1 role pode ser MASTER.',
    rolesEmpty: 'Nenhum role encontrado',
    rolesEmptyHint: 'Adicione o primeiro perfil de acesso',
    roleDeleteText: 'Excluir este role? As permissões e vínculos com usuários serão removidos.',
    roleCreated: 'Role criado.',
    roleUpdated: 'Role atualizado.',
    roleDeleted: 'Role excluído.',
    bindTitle: 'Gerenciar Permissões',
    bindFilter: 'Buscar páginas ou objetos...',
    bindFilterLabel: 'Filtrar páginas e objetos',
    permRead: 'Ler',
    permCreate: 'Criar',
    permEdit: 'Editar',
    permDelete: 'Excluir',
    bindEmpty: 'Nenhum objeto cadastrado. Acesse Páginas e cadastre os objetos primeiro.',
    bindFilterEmpty: 'Nenhuma página ou objeto encontrado para a busca.',
    bindSave: 'Salvar Permissões',
    bindSaved: "Permissões de ':role' atualizadas.",
    pagesTitle: 'Páginas e Objetos',
    pagesSubtitle: 'Cadastre as páginas do sistema e seus objetos (botões, campos, links) para controle de acesso.',
    pagesColumn: 'Páginas',
    newPage: 'Página',
    searchPage: 'Buscar página...',
    pagesEmpty: 'Nenhuma página cadastrada',
    pagesEmptyHint: 'Crie a primeira página para começar.',
    objectsOf: 'Objetos :page',
    newObject: 'Objeto',
    searchObject: 'Buscar objeto...',
    colKeyLabel: 'Chave / Label',
    colType: 'Tipo',
    colSection: 'Seção',
    objectsEmpty: 'Nenhum objeto nesta página',
    objectsEmptyHint: 'Adicione objetos para controlar o acesso.',
    selectPageHint: 'Selecione uma página para ver seus objetos',
    pageModalNew: 'Nova Página',
    pageModalEdit: 'Editar Página',
    pageSlug: 'Slug',
    pageName: 'Nome',
    pageDescription: 'Descrição',
    pageRoute: 'Rota Laravel',
    pageIcon: 'Ícone',
    pageActive: 'Página ativa',
    pageOrder: 'Ordem',
    objectModalNew: 'Novo Objeto',
    objectModalEdit: 'Editar Objeto',
    objectSection: 'Seção',
    objectType: 'Tipo',
    objectKey: 'Chave',
    objectLabel: 'Label',
    objectActive: 'Objeto ativo',
    objectOrder: 'Ordem',
    pageDeleteText: 'Excluir esta página? Todos os objetos vinculados também serão removidos.',
    objectDeleteText: 'Excluir este objeto? As permissões de roles vinculadas serão removidas.',
    pageCreated: 'Página criada.',
    pageUpdated: 'Página atualizada.',
    pageDeleted: 'Página excluída.',
    objectCreated: 'Objeto criado.',
    objectUpdated: 'Objeto atualizado.',
    objectDeleted: 'Objeto excluído.',
    objCount: ':count obj',
    guide: {
        title: 'Guia do Sistema de Permissões',
        subtitle: 'Como funciona o ACL do Ptah e como configurar acessos passo a passo.',
        badge: 'Documentação',
        tabOverview: '🗺️ Visão Geral',
        tabSetup: '🔧 Passo a Passo',
        tabCode: '💻 Exemplos de Código',
        tabFaq: '❓ Perguntas Frequentes',
        versionTitle: 'Versão do Ptah instalada',
        ovTitle: 'O que é o sistema de permissões do Ptah?',
        ovBody: [
            'O ACL (Access Control List) do Ptah é um sistema de controle de acesso baseado em **Roles (perfis)**, inspirado no padrão RBAC. Ele permite definir quem pode fazer o quê em cada parte do sistema, com granularidade até o nível de botão ou campo individual.',
            'Ao contrário do simples `Gate/Policy` do Laravel, o Ptah ACL é **dinâmico e gerenciável pela interface** quanto a Páginas/Objetos/Roles/vínculos — mas o PONTO DE VERIFICAÇÃO continua sendo código (`ptah_can()`, middleware `ptah.can`, `permissionIdentifier`). As 4 ações (Ler/Criar/Editar/Excluir) são fixas; uma capacidade nova se modela como um OBJETO novo (ex.: `crud.config`), não como um verbo novo — um verbo novo exigiria uma coluna (migration).',
        ],
        archTitle: 'Arquitetura — Como os conceitos se relacionam',
        arch: [
            { icon: '🏢', title: 'Departamentos', desc: 'Agrupamento lógico opcional dos Roles', example: 'ex: TI, Comercial, Financeiro' },
            { icon: '🎭', title: 'Roles / Perfis', desc: 'Carrega as permissões por objeto', example: 'ex: Admin, Vendedor, Suporte' },
            { icon: '📄', title: 'Páginas + Objetos', desc: 'O que pode ser controlado', example: 'ex: /vendas, botão "Exportar", campo "Desconto"' },
            { icon: '👤', title: 'Usuários', desc: 'Recebem Roles por Empresa', example: 'ex: João — Admin na Empresa A' },
            { icon: '🏭', title: 'Empresas', desc: 'Escopo do vínculo (opcional)', example: 'ex: Multi-tenant ou Global' },
        ],
        conceptsTitle: 'Conceitos fundamentais',
        concepts: [
            { icon: '🎭', title: 'Role (Perfil)', body: 'Um Role é um conjunto de permissões. Em vez de dar permissões diretamente ao usuário, você cria um Role com as permissões e atribui o Role ao usuário.', chips: ['Admin', 'Vendedor', '👑 MASTER'], warn: '' },
            { icon: '📄', title: 'Página', body: 'Representa um módulo ou rota do sistema. Cada Página contém **Objetos** — elementos individuais cujo acesso pode ser controlado (botões, campos, links, ações).', chips: ['admin.vendas', 'admin.estoque'], warn: '' },
            { icon: '🔑', title: 'Objeto + Permissão', body: 'Um Objeto é um elemento granular dentro de uma Página. Cada objeto tem 4 flags de permissão: **Ler, Criar, Editar, Excluir**. Um Role pode ter permissão parcial (só ler, por exemplo).', chips: [], warn: '' },
            {
                icon: '👑',
                title: 'Role MASTER',
                body: 'Um Role marcado como MASTER tem acesso irrestrito a **todos os recursos**, ignorando verificações — e é **GLOBAL por definição**: um vínculo MASTER com empresa preenchida não restringe nada, a empresa é simplesmente ignorada na resolução (silenciosamente normalizada para NULL ao salvar, e sinalizada pelo `ptah:config:doctor` se já existir assim no banco). A unicidade de "só 1 MASTER" é validada pela interface, não pelo banco — é uma corrida possível (check-then-act sem transação), não uma garantia de schema.',
                chips: [],
                warn: '⚠️ Use com cuidado — bypassa todas as verificações e é global; não existe "MASTER da Empresa X"',
            },
            {
                icon: '🏭',
                title: 'Escopo por Empresa',
                body: 'Um usuário pode ter Roles diferentes em empresas diferentes. Com empresa ativa na sessão, a verificação considera os grants daquela empresa **+ os globais** (vínculo com empresa `NULL`). **Sem** empresa selecionada — ou com `PTAH_MULTI_COMPANY=false` — só os grants globais contam; um grant escopado a uma empresa específica nega tudo nesse caso. MASTER (acima) não segue nenhuma dessas regras: é sempre global.',
                chips: [],
                warn: '',
            },
            {
                icon: '📋',
                title: 'Auditoria',
                body: 'Quando `PTAH_PERMISSION_AUDIT=true`, cada verificação CONCEDIDA é registrada. Negadas só entram com `PTAH_PERMISSION_AUDIT_DENIED=true` (padrão: true); acessos de MASTER só com `PTAH_PERMISSION_AUDIT_MASTER=true` (padrão: **false**). Verificação por coluna (`colsPermission`) nunca é auditada. Uma falha ao gravar o log nunca derruba a requisição.',
                chips: [],
                warn: '',
            },
        ],
        permsPreview: ['Ler', 'Criar', 'Editar', 'Excluir'],
        flowTitle: 'Fluxo de verificação de acesso',
        flow: [
            { text: '① O usuário está autenticado?', outcome: 'no' },
            { text: '② A ação pedida é create, read, update ou delete?', outcome: 'no' },
            { text: '③ Algum Role do usuário é MASTER?', outcome: 'granted' },
            { text: '④ Resolve o escopo: empresa ativa na sessão (grants da empresa + globais) — ou, sem empresa/com multi-empresa desligado, só grants globais', outcome: '' },
            { text: '⑤ O obj_key existe no mapa BARE, com can_{ação} = true em algum Role ativo?', outcome: '' },
            { text: '⑥ Não achou no mapa bare e a chave é qualificada (contém "::")? Consulta o mapa QUALIFICADO (page::obj_key)', outcome: '' },
        ],
        flowNo: 'NEGADO',
        flowGranted: '✅ ACESSO LIBERADO',
        flowDenied: '🚫 ACESSO NEGADO',
        prereq: '**Pré-requisito (execução humana — não automatize em deploy):** Execute `php artisan migrate` para criar as tabelas do Ptah, e `php artisan db:seed --class=Ptah\\Seeders\\DefaultCompanySeeder` para criar a empresa padrão.',
        s1Title: 'Cadastrar Departamentos (Opcional)',
        s1Desc: 'Agrupe seus Roles em departamentos para melhor organização.',
        s1Btn: 'Ir para Departamentos →',
        s1Body: 'Departamentos são agrupamentos lógicos opcionais para seus Roles. Útil quando o sistema tem muitos perfis.',
        s1Example: 'Exemplo',
        s1Examples: ['Departamento **TI** → Roles: Desenvolvedor, DevOps, Suporte TI', 'Departamento **Comercial** → Roles: Vendedor, Gerente Comercial, SDR', 'Departamento **Financeiro** → Roles: Analista Financeiro, Controller'],
        s2Title: 'Cadastrar Páginas e Objetos',
        s2Desc: 'Registre os módulos do sistema e o que pode ser controlado neles.',
        s2Btn: 'Ir para Páginas →',
        s2Body: 'Uma **Página** representa um módulo ou seção do sistema (ex: `admin.vendas`). Cada página pode ter vários **Objetos** — que representam elementos granulares como botões, campos ou ações. Um `obj_key` só é único dentro da própria Página (mais precisamente, por Página + seção); se o mesmo `obj_key` existir em outra Página, use a chave QUALIFICADA `pagina::obj_key` (ou `pagina::secao::obj_key`) para desambiguar — veja a aba **Exemplos de Código**.',
        s2PageTitle: '📄 Exemplo de Página',
        s2PageSlug: 'Slug',
        s2PageName: 'Nome',
        s2PageIcon: 'Ícone',
        s2PageNameValue: 'Módulo de Vendas',
        s2ObjTitle: '🔑 Objetos desta Página',
        s3Title: 'Criar Roles e definir permissões',
        s3Desc: 'Crie os perfis de acesso e configure quais objetos cada perfil pode acessar.',
        s3Btn: 'Ir para Roles →',
        s3Body: 'Crie um Role com nome e cor. Depois clique em **🔑 Permissões** para definir quais objetos este Role pode Ler, Criar, Editar e Excluir.',
        s3ExTitle: 'Exemplo: Role "Vendedor Padrão"',
        s3ColObj: 'Objeto',
        s3Note: '↑ Vendedor pode criar pedidos mas não vê desconto e não pode exportar de forma irrestrita. Desmarcar a coluna "Ler" de um objeto que seja o `permissionIdentifier` de uma tela CRUD fecha a tela inteira (HTTP 403) para quem não tiver a permissão — não é mais só um campo escondido.',
        s4Title: 'Vincular usuários a Roles',
        s4Desc: 'Atribua um ou mais Roles a cada usuário, com escopo de empresa.',
        s4Btn: 'Ir para Usuários →',
        s4Body: 'Na tela de Controle de Acesso, clique em **🔑 Gerenciar Acesso** ao lado do usuário. Selecione um Role e uma Empresa (ou "Global" para acesso sem escopo).',
        s4ExTitle: 'Exemplo: Usuário **João Silva**',
        s4Examples: ['Role **Admin** na empresa **Empresa A Ltda** → acesso total na Empresa A', 'Role **Leitor** na empresa **Empresa B SA** → só pode ler na Empresa B'],
        s5Title: 'Usar as permissões no código',
        s5Desc: 'Veja a aba "Exemplos de Código" para detalhes completos.',
        s5Btn: 'Ver exemplos →',
        s5Body: "Use o helper `ptah_can('objeto.chave', 'read')` nas views Blade ou o middleware `ptah.can:objeto.chave,read` nas rotas para proteger o acesso.",
        s5Note: 'Numa tela CRUD do Ptah (`Ptah\\Livewire\\BaseCrud\\BaseCrud`), não é preciso chamar o helper manualmente: configure `permissions.permissionIdentifier` no CrudConfig e o próprio componente passa a exigir create/update/delete — e também read: sem a permissão, a tela responde HTTP 403 antes mesmo de consultar a listagem. Sem `permissionIdentifier` configurado, o CRUD fica livre (os grants nunca são consultados). Depois de cadastrar Página/Objetos, rode `php artisan ptah:permission:sync` para conferir/gerar as colunas de permissão esperadas.',
        colTitle: '🔒 Restringir colunas por permissão (opcional)',
        colDesc: 'Esconda uma coluna inteira do CRUD para quem não tiver a permissão de leitura daquele objeto.',
        colBody: 'No CrudConfig, marque a coluna com `colsPermission` apontando para o `obj_key` que controla o acesso a ela. Ordem obrigatória: 1) cadastre o Objeto (Passo 2); 2) conceda Ler aos Roles que devem ver a coluna (Passo 3); 3) só então marque `colsPermission` na coluna. Vale também no export — a coluna some do arquivo gerado, não só da tela.',
        colWarn: '⚠️ Uma coluna marcada com `colsPermission` nasce FECHADA: fica escondida para TODOS até que exista um grant de leitura para aquele objeto — não há período de carência. Uma coluna sem a tag continua pública, como sempre foi.',
        faq: [
            { q: 'O que acontece se o usuário não tiver nenhum Role?', a: 'Sem nenhum Role, o usuário não terá acesso a nenhum objeto controlado. As verificações com `ptah_can()` retornam **false** e o middleware `ptah.can` retorna HTTP 403.' },
            { q: 'Posso ter mais de um Role por usuário?', a: 'Sim! Um usuário pode ter múltiplos Roles, inclusive em empresas diferentes. Se QUALQUER Role do usuário tiver a permissão solicitada, o acesso é concedido — desde que o Role esteja ativo, o vínculo esteja ativo no escopo resolvido, e o Objeto esteja ativo numa Página ativa (as 4 condições de atividade).' },
            { q: 'O que é o Role MASTER e quando usar?', a: 'Um Role MASTER bypassa **todas** as verificações de permissão, concedendo acesso irrestrito — e é **global**, não importa qual empresa o vínculo carrega. Use exclusivamente para superadministradores do sistema. A interface impede um 2º MASTER, mas é validação de aplicação (não do banco): é uma corrida possível, não uma garantia de schema.' },
            { q: 'Como funciona o escopo por empresa?', a: 'Ao vincular um usuário a um Role, você pode especificar uma Empresa. (a) Com empresa ativa na sessão: conta os grants daquela empresa + os globais (vínculo com empresa `NULL`). (b) Sem empresa selecionada — ou com `PTAH_MULTI_COMPANY=false`: só os globais contam. (c) MASTER não segue nada disso — é sempre global.' },
            { q: 'As permissões são cacheadas?', a: 'Sim, por geração: a chave do cache embute contadores global e por usuário, incrementados quando Role/RolePermission/UserRole/PageObject/Página mudam. A invalidação é instantânea em qualquer driver de cache — inclusive revogações em massa. Limpar o cache do framework manualmente nunca é necessário no fluxo normal.' },
            { q: 'Posso criar Páginas e Objetos automaticamente via código?', a: 'Sim. Use o seeder ou crie registros em `Ptah\\Models\\PtahPage` (a Página) e `Ptah\\Models\\PageObject` (o Objeto) diretamente. Prefira um seeder ou `php artisan ptah:permission:sync` a uma migration de dados: as migrations do pacote são auto-descobertas e disparam em qualquer `php artisan migrate` do host, mesmo quando o recurso não é usado.' },
            { q: 'O que acontece se eu excluir um Objeto que já tem permissões definidas?', a: 'As entradas da tabela de permissões associadas ao objeto são removidas em cascata (irreversível). Os Roles que tinham aquele objeto perdem a permissão automaticamente. Usuários MASTER não são afetados (bypass). Alternativa reversível: marque o Objeto como inativo em vez de excluí-lo — também revoga o acesso, mas sem perder os vínculos.' },
            { q: 'Como auditar quem acessou o que?', a: 'Habilite `PTAH_PERMISSION_AUDIT=true` no .env — grava os acessos CONCEDIDOS. Negados exigem também `PTAH_PERMISSION_AUDIT_DENIED=true` (padrão: true); acessos de MASTER exigem `PTAH_PERMISSION_AUDIT_MASTER=true` (padrão: false). Verificação por coluna nunca é auditada. Acesse o log em [Auditoria](audit).' },
            { q: 'Por que este usuário não consegue fazer X?', a: 'Rode `php artisan ptah:permission:why {usuario} {obj_key} --action=read --company=`. O comando aponta o elo mais específico que falta, nesta ordem: objeto inexistente → objeto inativo → página inativa → sem Role ativo → sem vínculo → `can_{ação}=false` → vínculo excluído (trashed) → grant de outra empresa → grant só em outra Página (sugerindo a chave qualificada). Roda com auditoria desligada, então nunca polui `ptah_permission_audits`.' },
            { q: 'Marcar "Ler" como false esconde a tela inteira?', a: 'Sim, quando o CRUD tem `permissions.permissionIdentifier` configurado: `render()` aborta com HTTP 403 antes mesmo de consultar a listagem, e export/impressão seguem a mesma regra. `can_read` vem **true** por padrão, então nenhum vínculo existente muda de comportamento — só passa a valer para quem desmarcar "Ler" explicitamente. Sem `permissionIdentifier` configurado, a tela continua livre.' },
        ],
        helpTitle: 'Precisa de mais ajuda?',
        helpBody: 'Consulte a **Visão Geral** para entender a arquitetura, o **Passo a Passo** para configurar e os **Exemplos de Código** para integrar no seu projeto.',
    },
};

const dictionaries: Record<string, RolesWords> = { en, pt: ptBR };

/** The words of the access-control screens for a locale (`pt_BR`, `pt-BR`, `en`…); English when unknown. */
export function rolesWords(locale?: string | null): RolesWords {
    const lang = (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').toLowerCase().slice(0, 2);

    return dictionaries[lang] ?? en;
}
