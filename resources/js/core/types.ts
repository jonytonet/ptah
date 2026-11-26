/** The contract of the ptah headless CRUD API (docs/HeadlessApi.md). */

export interface Option {
    label: string;
    value: string;
}

export interface Column {
    field: string;
    label: string;
    type: string;
    renderer: string | null;
    align: string | null;
    /** The column to order by, or null when this column is not sortable. */
    sortBy: string | null;
}

export interface Field {
    field: string;
    label: string;
    type: string;
    required: boolean;
    readonly: boolean;
    options: Option[] | null;
    mask: string | null;
    maskPatterns: string[] | null;
    placeholder: string | null;
    help: string | null;
    /** The form section this field opens or belongs to (`colsFormBlock`). */
    block?: string | null;
    /** The `accept` of an image field (`colsUploadAllowedTypes`). */
    accept?: string | null;
    /** A searchdropdown's own settings; null for any other field. */
    lookup?: LookupSettings | null;
    /** A registered mask's hints: its placeholder, keyboard and longest shape. */
    maskPlaceholder?: string | null;
    inputMode?: string | null;
    maxLength?: number | null;
    /** An emptied field saves null: the money input stays empty instead of R$ 0,00 (1.59.2). */
    emptyNull?: boolean;
}

export interface LookupSettings {
    /** `colsSDPlaceholder`; null: "Search <label>…". */
    placeholder: string | null;
    /** Where the options open: under the field, or above it. */
    startList: 'top' | 'bottom' | string;
    /** The field this one depends on (a cascade): locked while it is empty. */
    dependsOn: string | null;
    dependsOnLabel: string | null;
}

export interface FilterDef {
    field: string;
    label: string;
    type: string;
    options: Option[] | null;
}

export interface Permissions {
    canRead: boolean;
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    canRestore: boolean;
    canExport: boolean;
}

export interface BulkAction {
    action: string;
    label: string;
    icon: string | null;
    confirm: string | boolean | null;
}

export interface Preferences {
    columns: Record<string, boolean>;
    column_order: string[];
    column_widths: Record<string, number>;
    view_mode: string;
    view_density: string;
    saved_filters: Record<string, Record<string, unknown>>;
    search_history: string[];
}

export interface Schema {
    title: string;
    titleSingular: string;
    key: string;
    columns: Column[];
    form: Field[];
    filters: FilterDef[];
    permissions: Permissions;
    bulkActions: BulkAction[];
    selectable: boolean;
    export: { enabled: boolean; formats: string[] };
    trash: boolean;
    defaults: { sort: string; direction: string; per_page: number };
    preferences: Preferences;
    /** What else this screen has on, for this user. */
    features: Features;
    /** The websocket listener of the screen, when its config has `broadcast` on. */
    broadcast?: import('./realtime').BroadcastSpec | null;
}

export interface Features {
    history: boolean;
    attachments: boolean;
    /** May add and remove attachments (reading them is `attachments`). */
    attachmentsWrite: boolean;
    import: boolean;
    kanban: boolean;
    calendar: boolean;
    formDraft: boolean;
    masterDetail: boolean;
    quickDates: boolean;
    advancedSearch: boolean;
    /**
     * `ptah.crud.responsive_cards`: view mode `auto` turns into cards on a
     * narrow screen. Off, `auto` stays a table (as the Livewire screen).
     */
    responsiveCards: boolean;
    /** The copy / filter buttons over a cell (`uiPreferences.copyCell`). */
    copyCell: boolean;
    /** Delete goes to the deleted list and the toast offers Undo (1.61.0). */
    undoDelete?: boolean;
    /** `ptah.crud.filter_panel` = 'modal': the filters open in a dialog (1.62.0). */
    filterModal?: boolean;
}

export const NO_FEATURES: Features = {
    history: false,
    attachments: false,
    attachmentsWrite: false,
    import: false,
    kanban: false,
    calendar: false,
    formDraft: false,
    masterDetail: false,
    quickDates: false,
    advancedSearch: false,
    // Not a feature but a layout switch: the server's default.
    responsiveCards: true,
    copyCell: true,
};

export interface Cell {
    value: string | number | boolean | null;
    /** What the Livewire screen shows for this cell — rendered and escaped by the server. */
    html: string;
}

export interface RowAction {
    label: string;
    type: 'link' | 'action' | 'javascript' | string;
    value: string;
    icon: string | null;
    color: string;
    confirm: string | null;
}

export type RowId = number | string;

export interface Row {
    id: RowId;
    cells: Record<string, Cell>;
    style: string | null;
    actions: RowAction[];
    /** Where a click on the row goes (`configLinkLinha`), already checked by the server; null: none. */
    link?: string | null;
    trashed: boolean;
}

export interface ListState {
    page: number;
    per_page: number;
    sort: string;
    direction: string;
    search: string;
    filters: Record<string, unknown>;
    operators: Record<string, string>;
    date_ranges: Record<string, string>;
    date_operators: Record<string, string>;
    quick_date: string;
    trashed: boolean;
}

export interface Meta {
    page: number;
    per_page: number;
    total: number;
    last_page: number;
    sort: string;
    direction: string;
    search: string;
    trashed: boolean;
}

export interface Total {
    field: string;
    aggregate: string;
    value: number | string | null;
    /** The total as the Livewire footer prints it (money in its currency…). */
    text?: string | null;
}

/** One group break of the page (`groupBreak`): header before `start`, subtotal after its last row. */
export interface GroupBreak {
    start: number;
    count: number;
    label: string;
    /** The group's header, HTML already escaped by the server. */
    title: string;
    subtotals: Record<string, string>;
}

export interface Message {
    text: string;
    level: string;
    /** The record a "deleted" message can bring back (a soft delete). */
    undo?: RowId | null;
}

export interface Listing {
    data: Row[];
    meta: Meta;
    state: ListState;
    totals: Total[];
    breaks?: GroupBreak[];
    messages: Message[];
}

export interface FormPayload {
    data: Record<string, unknown>;
    labels: Record<string, string>;
    /** The modal title ("Nova conta", "Editar conta") and subtitle, from the server. */
    title?: string;
    subtitle?: string;
    /** URLs of the stored images, by field. */
    previews?: Record<string, string>;
}

export interface SaveResult {
    ok: boolean;
    id: RowId | null;
    errors: Record<string, string>;
    messages: Message[];
    /** Where an after-save hook sends the user. */
    redirect?: string | null;
}

export interface ActionResult {
    ok: boolean;
    messages: Message[];
    remaining?: string[];
    url?: string | null;
}

export interface LookupItem {
    value: string | number;
    label: string;
    /** The second and third lines of an option (`colsSDLabelTwo`/`Three`). */
    labelTwo?: string;
    labelThree?: string;
    [key: string]: unknown;
}

export interface MenuItem {
    id?: number | string | null;
    label: string;
    text?: string;
    url?: string;
    icon?: string;
    type?: string;
    target?: string;
    match?: string;
    /** False for an item switched off in the menu admin. */
    is_active?: boolean | number;
    children?: MenuItem[];
}

/** A link of the menu with the trail that leads to it — what the "jump to a screen" search runs over. */
export interface MenuLink {
    label: string;
    url: string;
    icon: string;
    target: string;
    breadcrumb: string[];
    path: string;
    /** Label and trail, lower case and without accents. */
    search: string;
}

export interface Shell {
    data: MenuItem[];
    flat: MenuLink[];
    /** Whether the sidebar offers the search. */
    jump: boolean;
    brand: { name: string; logo: string | null; logo_dark: string | null };
    /** What the top bar offers this user (the rules of the Livewire navbar). */
    shell: ShellExtras;
}

export interface ShellExtras {
    company_switcher: boolean;
    notifications: boolean;
    /** The navbar's plain bell (no notifications module, `ptah.navbar.notifications` unset). */
    static_bell?: boolean;
    /** The floating AI assistant (ai_agent module). */
    ai_chat?: boolean;
    /** The administration links this user may open. */
    admin: { label: string; url: string; icon: string }[];
}

export interface Me {
    user: { id: RowId; name: string; email: string; photo_url?: string | null } | null;
    company_id: number | null;
    locale: string;
    screens: string[];
}

// ── Auth (ptah's auth module) ───────────────────────────────────────────────

export interface AuthConfig {
    app: string;
    /** The brand logo above the card (ptah.brand), and its dark variant. */
    logo?: string | null;
    logo_dark?: string | null;
    locale: string;
    remember_me: boolean;
    home: string;
    urls: { login: string | null; forgot: string | null; two_factor: string | null };
    signed_in: boolean;
    two_factor_pending: boolean;
    status: string | null;
}

/** The answer of an auth action. `errors` are per field (a 422 of the validator or of the page). */
export interface AuthResult {
    ok: boolean;
    error?: string | null;
    errors?: Record<string, string>;
    message?: string | null;
    redirect?: string | null;
    two_factor?: boolean;
    messages?: Message[];
    profile?: Profile;
    secret?: string | null;
    qr?: string | null;
    recovery_codes?: string[];
    sessions?: SessionInfo[];
}

export interface Appearance {
    light: string;
    dark: string;
    accent: string;
    text: string;
    density: string;
    fontsize: string;
    mode: string | null;
    choices: Record<'light' | 'dark' | 'accent' | 'text' | 'density' | 'fontsize', string[]>;
    /** Each option's name, and `_axis` with each axis' title (ptah::ui.profile_appearance_*). */
    labels?: Record<string, Record<string, string>>;
    /** The colour dot of each accent option. */
    swatches?: Record<string, string>;
}

export interface Profile {
    name: string;
    email: string;
    photo_url: string | null;
    two_factor: { available: boolean; enabled: boolean; type: string };
    sessions_available: boolean;
    appearance: Appearance;
}

export interface SessionInfo {
    id: string;
    ip_address: string;
    browser: string;
    platform: string;
    last_activity_human: string;
    is_current: boolean;
}

// ── Dashboard ───────────────────────────────────────────────────────────────

/** GET /dashboard: the widgets, or (none configured) what the four default cards show. */
export interface DashboardPage {
    data: Widget[];
    fallback: { app: string; environment: string; laravel: string } | null;
}

/** A widget of config/ptah-dashboard.php, already computed by the server. */
export interface Widget {
    type: 'stat' | 'trend' | 'breakdown' | 'latest' | string;
    label: string;
    color: string;
    link: string | null;
    /** Why this widget could not be computed, when it could not. */
    error: string | null;
    value?: string | number;
    display_total?: string;
    max?: number;
    points?: { label: string; value: number; display: string }[];
    items?: { label: string; value: number; display: string; others?: boolean }[];
    rows?: (string | number | null)[][];
}
