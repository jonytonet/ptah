import type {
    ActionResult,
    AuthConfig,
    AuthResult,
    FormPayload,
    Listing,
    ListState,
    LookupItem,
    Me,
    MenuItem,
    Preferences,
    Profile,
    RowId,
    SaveResult,
    Schema,
    SessionInfo,
    Shell,
    Widget,
} from './types';

export interface ApiOptions {
    /** Where the API is mounted: ptah.crud_api.prefix. */
    baseUrl?: string;
    /** A fetch of your own (tests, a token, a different transport). */
    fetch?: typeof fetch;
    /** Extra headers on every request, e.g. `Authorization: Bearer …`. */
    headers?: Record<string, string>;
}

/** A response the API refused: 403, 404, 419, 500… (422 is not an error: it carries the reasons). */
export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly body: unknown = null,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

/** `{filters: {status: 'a'}}` → `filters[status]=a`, the shape Laravel reads back as an array. */
export function toQuery(params: Record<string, unknown>, prefix = ''): string {
    const out: string[] = [];

    for (const [key, value] of Object.entries(params)) {
        if (value === undefined || value === null) {
            continue;
        }
        const name = prefix ? `${prefix}[${key}]` : key;

        if (typeof value === 'object') {
            const entries = Object.keys(value as object).length;
            // An emptied map is sent as an empty value: "the filters were cleared".
            out.push(entries === 0 ? `${encodeURIComponent(name)}=` : toQuery(value as Record<string, unknown>, name));
        } else {
            out.push(`${encodeURIComponent(name)}=${encodeURIComponent(typeof value === 'boolean' ? (value ? '1' : '0') : String(value))}`);
        }
    }

    return out.filter(Boolean).join('&');
}

function cookie(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }
    const found = document.cookie.split('; ').find((row) => row.startsWith(`${name}=`));

    return found ? decodeURIComponent(found.slice(name.length + 1)) : null;
}

function csrfHeaders(): Record<string, string> {
    const xsrf = cookie('XSRF-TOKEN');
    if (xsrf) {
        return { 'X-XSRF-TOKEN': xsrf };
    }
    const meta = typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]') : null;
    const token = meta?.getAttribute('content');

    return token ? { 'X-CSRF-TOKEN': token } : {};
}

/** The ptah headless CRUD API, one method per endpoint. */
export class PtahApi {
    private readonly baseUrl: string;
    private readonly doFetch: typeof fetch;
    private readonly headers: Record<string, string>;

    constructor(options: ApiOptions = {}) {
        this.baseUrl = (options.baseUrl ?? '/ptah/api').replace(/\/+$/, '');
        this.doFetch = options.fetch ?? ((...args) => fetch(...args));
        this.headers = options.headers ?? {};
    }

    /**
     * Any endpoint under the API prefix — what the clients of the admin
     * screens (`core/admin/*`) are built on. A 422 answers its body (the
     * reasons); any other failure throws ApiError.
     */
    call<T>(method: string, path: string, body?: unknown): Promise<T> {
        return this.request<T>(method, path, body);
    }

    private async request<T>(method: string, path: string, body?: unknown): Promise<T> {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...csrfHeaders(),
            ...this.headers,
        };
        let payload: BodyInit | undefined;

        if (body instanceof FormData) {
            payload = body;
        } else if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
            payload = JSON.stringify(body);
        }

        const response = await this.doFetch(`${this.baseUrl}${path}`, {
            method,
            headers,
            body: payload,
            credentials: 'same-origin',
        });

        const text = await response.text();
        let json: unknown = null;
        try {
            json = text ? JSON.parse(text) : null;
        } catch {
            json = null;
        }

        // 422 carries the answer (validation errors, a refusal with its reason).
        if (!response.ok && response.status !== 422) {
            const message = (json as { message?: string } | null)?.message || `HTTP ${response.status}`;
            throw new ApiError(response.status, message, json);
        }

        return json as T;
    }

    private crud(screen: string, rest = ''): string {
        return `/crud/${encodeURIComponent(screen)}${rest}`;
    }

    schema(screen: string): Promise<Schema> {
        return this.request('GET', this.crud(screen, '/schema'));
    }

    list(screen: string, state: Partial<ListState> = {}, flags: { restore?: boolean; remember?: boolean } = {}): Promise<Listing> {
        const query = toQuery({ ...state, ...(flags.restore ? { restore: 1 } : {}), ...(flags.remember ? { remember: 1 } : {}) });

        return this.request('GET', this.crud(screen, query ? `?${query}` : ''));
    }

    show(screen: string, id: RowId): Promise<FormPayload> {
        return this.request('GET', this.crud(screen, `/${id}`));
    }

    blank(screen: string): Promise<FormPayload> {
        return this.request('GET', this.crud(screen, '/create'));
    }

    duplicate(screen: string, id: RowId): Promise<FormPayload> {
        return this.request('POST', this.crud(screen, `/${id}/duplicate`));
    }

    /** Create (`id` null) or update. With files the request is multipart, on POST. */
    save(screen: string, id: RowId | null, data: Record<string, unknown>, files: Record<string, File> = {}): Promise<SaveResult> {
        const path = this.crud(screen, id === null ? '' : `/${id}`);
        const names = Object.keys(files);

        if (names.length === 0) {
            return this.request(id === null ? 'POST' : 'PUT', path, { data });
        }

        const form = new FormData();
        form.append('data', JSON.stringify(data));
        for (const name of names) {
            form.append(`files[${name}]`, files[name]);
        }

        return this.request('POST', path, form);
    }

    destroy(screen: string, id: RowId): Promise<ActionResult> {
        return this.request('DELETE', this.crud(screen, `/${id}`));
    }

    restore(screen: string, id: RowId): Promise<ActionResult> {
        return this.request('POST', this.crud(screen, `/${id}/restore`));
    }

    async lookup(screen: string, field: string, q: string, context: Record<string, unknown> = {}, filter = false): Promise<LookupItem[]> {
        const result = await this.request<{ data: LookupItem[] }>('POST', this.crud(screen, `/lookup/${encodeURIComponent(field)}`), {
            q,
            context,
            filter,
        });

        return result.data ?? [];
    }

    bulk(screen: string, action: string, ids: RowId[], state: Partial<ListState> = {}): Promise<ActionResult> {
        return this.request('POST', this.crud(screen, '/bulk'), { action, ids, state });
    }

    export(screen: string, format: string, state: Partial<ListState> = {}, ids: RowId[] = []): Promise<ActionResult> {
        return this.request('POST', this.crud(screen, '/export'), { format, state, ids });
    }

    preferences(screen: string): Promise<Preferences> {
        return this.request('GET', this.crud(screen, '/preferences'));
    }

    updatePreferences(screen: string, changes: Partial<Preferences> & { clear_search_history?: boolean }): Promise<Preferences> {
        return this.request('PUT', this.crud(screen, '/preferences'), changes);
    }

    saveFilter(screen: string, name: string, filters: Record<string, unknown>): Promise<Preferences> {
        return this.request('POST', this.crud(screen, '/saved-filters'), { name, filters });
    }

    forgetFilter(screen: string, name: string): Promise<Preferences> {
        return this.request('DELETE', this.crud(screen, '/saved-filters'), { name });
    }

    async menu(): Promise<MenuItem[]> {
        return (await this.request<{ data: MenuItem[] }>('GET', '/menu')).data ?? [];
    }

    /** The menu with what the sidebar needs around it: the search list and the brand. */
    async shell(): Promise<Shell> {
        const raw = await this.request<Partial<Shell>>('GET', '/menu');

        return { data: raw.data ?? [], flat: raw.flat ?? [], jump: raw.jump ?? false, brand: raw.brand ?? { name: '', logo: null, logo_dark: null } };
    }

    me(): Promise<Me> {
        return this.request('GET', '/me');
    }

    /** The dashboard widgets this user may see; `group` names a group of them. */
    async dashboard(group?: string): Promise<Widget[]> {
        return (await this.request<{ data: Widget[] }>('GET', `/dashboard${group ? `?group=${encodeURIComponent(group)}` : ''}`)).data ?? [];
    }

    // ── Auth (ptah's auth module) ───────────────────────────────────────────

    /**
     * An auth action. A 422 of Laravel's validator (`{message, errors: {field:
     * [..]}}`) and a refusal of the page (`{ok: false, error, errors}`) come
     * back in the same shape.
     */
    private async auth(method: string, path: string, body?: unknown): Promise<AuthResult> {
        const raw = await this.request<Record<string, unknown>>(method, `/auth${path}`, body);

        if (raw && raw.ok === undefined) {
            const errors: Record<string, string> = {};
            for (const [field, messages] of Object.entries((raw.errors ?? {}) as Record<string, string[] | string>)) {
                errors[field] = Array.isArray(messages) ? String(messages[0] ?? '') : String(messages);
            }

            return { ok: false, errors, error: Object.keys(errors).length === 0 ? String(raw.message ?? '') : null };
        }

        return raw as unknown as AuthResult;
    }

    authConfig(): Promise<AuthConfig> {
        return this.request('GET', '/auth/config');
    }

    login(email: string, password: string, remember = false): Promise<AuthResult> {
        return this.auth('POST', '/login', { email, password, remember });
    }

    twoFactor(code: string, recovery = false): Promise<AuthResult> {
        return this.auth('POST', '/two-factor', { code, recovery });
    }

    twoFactorEmail(): Promise<AuthResult> {
        return this.auth('POST', '/two-factor/email');
    }

    forgotPassword(email: string): Promise<AuthResult> {
        return this.auth('POST', '/forgot-password', { email });
    }

    resetPassword(token: string, email: string, password: string, password_confirmation: string): Promise<AuthResult> {
        return this.auth('POST', '/reset-password', { token, email, password, password_confirmation });
    }

    logout(): Promise<AuthResult> {
        return this.auth('POST', '/logout');
    }

    profile(): Promise<Profile> {
        return this.request('GET', '/auth/profile');
    }

    updateProfile(name: string, email: string, current_password = ''): Promise<AuthResult> {
        return this.auth('PUT', '/profile', { name, email, current_password });
    }

    updatePassword(current_password: string, password: string, password_confirmation: string): Promise<AuthResult> {
        return this.auth('PUT', '/password', { current_password, password, password_confirmation });
    }

    updatePhoto(photo: File): Promise<AuthResult> {
        const form = new FormData();
        form.append('photo', photo);

        return this.auth('POST', '/photo', form);
    }

    removePhoto(): Promise<AuthResult> {
        return this.auth('DELETE', '/photo');
    }

    /** One or more axes (light, dark, accent, text, density, fontsize), or `{ reset: true }`. */
    updateAppearance(changes: Record<string, string | boolean>): Promise<AuthResult> {
        return this.auth('PUT', '/appearance', changes);
    }

    startTotp(current_password: string): Promise<AuthResult> {
        return this.auth('POST', '/two-factor/totp', { current_password });
    }

    confirmTotp(code: string): Promise<AuthResult> {
        return this.auth('POST', '/two-factor/totp/confirm', { code });
    }

    enableEmailTwoFactor(current_password: string): Promise<AuthResult> {
        return this.auth('POST', '/two-factor/email-method', { current_password });
    }

    disableTwoFactor(current_password: string): Promise<AuthResult> {
        return this.auth('POST', '/two-factor/disable', { current_password });
    }

    recoveryCodes(current_password: string, regenerate = false): Promise<AuthResult> {
        return this.auth('POST', '/two-factor/recovery-codes', { current_password, regenerate });
    }

    async sessions(): Promise<SessionInfo[]> {
        return (await this.request<{ data: SessionInfo[] }>('GET', '/auth/sessions')).data ?? [];
    }

    revokeSession(id: string): Promise<AuthResult> {
        return this.auth('DELETE', `/sessions/${encodeURIComponent(id)}`);
    }

    revokeOtherSessions(): Promise<AuthResult> {
        return this.auth('DELETE', '/sessions');
    }
}
