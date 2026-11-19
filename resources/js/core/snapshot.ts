/**
 * The last listing of each screen, kept for the first paint (1.59.0): a
 * `<PtahCrud>` with `instant` draws it at once — busy — and replaces it with
 * the server's answer, so opening a screen again never shows "Loading…".
 *
 * Where: `sessionStorage` (this tab only, gone when it closes — never
 * `localStorage`), under the signed-in user and company the page was served
 * for. The rows are HTML the server rendered for that user: they are only
 * ever replayed to the same user in the same tab session. Signing out and
 * switching company forget them all.
 */
import { PtahApi } from './api';
import type { GroupBreak, Listing, ListState, Meta, Preferences, Row, Schema, Total } from './types';

export const SNAPSHOT_PREFIX = 'ptah:snap:';

/** A listing over this many characters (~200 KB) is not kept. */
export const SNAPSHOT_LIMIT = 200_000;

export interface Snapshot {
    schema: Schema;
    preferences: Preferences | null;
    rows: Row[];
    meta: Meta | null;
    totals: Total[];
    breaks: GroupBreak[];
    list: ListState;
}

let scope: string | null = null;

/**
 * Whose snapshots these are: the signed-in user (and company). Without one
 * nothing is kept nor read. `PtahApp` sets it from the page's boot data.
 */
export function setSnapshotScope(user: string | number | null | undefined, company?: string | number | null): void {
    scope = user === null || user === undefined || user === '' ? null : `${user}:${company ?? ''}`;
}

export function snapshotScope(): string | null {
    return scope;
}

function storage(): Storage | null {
    try {
        return typeof sessionStorage === 'undefined' ? null : sessionStorage;
    } catch {
        return null;
    }
}

const keyOf = (screen: string): string | null => (scope === null ? null : `${SNAPSHOT_PREFIX}${scope}:${screen}`);

export function readSnapshot(screen: string): Snapshot | null {
    const key = keyOf(screen);
    if (!key) {
        return null;
    }
    try {
        const raw = storage()?.getItem(key);
        if (!raw) {
            return null;
        }
        const value = JSON.parse(raw) as Partial<Snapshot> | null;
        if (!value || typeof value !== 'object' || !value.schema || typeof value.schema !== 'object' || !Array.isArray(value.rows) || !value.list) {
            return null;
        }

        return {
            schema: value.schema,
            preferences: value.preferences ?? null,
            rows: value.rows,
            meta: value.meta ?? null,
            totals: Array.isArray(value.totals) ? value.totals : [],
            breaks: Array.isArray(value.breaks) ? value.breaks : [],
            list: value.list,
        };
    } catch {
        return null;
    }
}

export function writeSnapshot(screen: string, snapshot: Snapshot): void {
    const key = keyOf(screen);
    if (!key) {
        return;
    }
    try {
        const raw = JSON.stringify(snapshot);
        if (raw.length > SNAPSHOT_LIMIT) {
            storage()?.removeItem(key);

            return;
        }
        storage()?.setItem(key, raw);
    } catch {
        // Full or blocked: the screen simply loads as before.
    }
}

export function dropSnapshot(screen: string): void {
    const key = keyOf(screen);
    if (!key) {
        return;
    }
    try {
        storage()?.removeItem(key);
    } catch {
        // Nothing to drop.
    }
}

// ── Warming a screen before it is shown ─────────────────────────────────────

/** A warmed answer is used by the screen if it opens within this time (ms). */
export const WARM_MS = 20_000;

const warm = new Map<string, { promise: Promise<[Schema, Listing]>; at: number }>();

/**
 * Asks the server for a screen's schema and first listing before the screen
 * is shown — on the way to it, or on a hover over its link. The answer is
 * kept (memory, and the snapshot for the first paint) and the screen's
 * `init()` takes it instead of asking again. Resolves when it arrived (or
 * failed: the screen then shows the refusal itself).
 */
export function warmScreen(api: PtahApi, screen: string): Promise<void> {
    const known = warm.get(screen);
    if (known && Date.now() - known.at < WARM_MS) {
        return known.promise.then(
            () => undefined,
            () => undefined,
        );
    }
    const promise = Promise.all([api.schema(screen), api.list(screen, {}, { restore: true })]);
    warm.set(screen, { promise, at: Date.now() });

    return promise.then(
        ([schema, listing]) => {
            writeSnapshot(screen, { schema, preferences: schema.preferences, rows: listing.data, meta: listing.meta, totals: listing.totals, breaks: listing.breaks ?? [], list: listing.state });
        },
        () => {
            dropSnapshot(screen);
        },
    );
}

/**
 * The navigator's `prepare` for the pages of `PtahApp`: a CRUD page warms its
 * screen; the others have nothing to wait for.
 */
export function warmPage(apiUrl?: string, api?: PtahApi): (page: { page: string; screen: string }) => Promise<void> | undefined {
    let client = api ?? null;

    return (page) => {
        if (page.page === 'dashboard') {
            const dashboard = (client ??= new PtahApi({ baseUrl: apiUrl }));

            return warmCall('dashboard', () => dashboard.dashboardPage());
        }
        if (page.page !== 'crud' || page.screen === '') {
            return undefined;
        }
        client ??= new PtahApi({ baseUrl: apiUrl });
        const warming = warmScreen(client, page.screen);

        // A screen this tab already saw swaps at once (its kept listing, refreshed by
        // the warmed answer); a new one waits for its first data.
        return readSnapshot(page.screen) ? undefined : warming;
    };
}

/**
 * What `mountPtah` gives its navigator: `prepare` (above) and `early`, which
 * warms the CRUD screen named by the URL's path — the key `ptah:forge`
 * registers — while the document is still on its way, when the user has it
 * (`screens` of `/me`).
 */
export function warmOptions(apiUrl?: string, screens: string[] = []): { prepare: ReturnType<typeof warmPage>; early: (url: URL) => void } {
    const api = new PtahApi({ baseUrl: apiUrl });

    return {
        prepare: warmPage(apiUrl, api),
        early: (url) => {
            const key = decodeURIComponent(url.pathname).replace(/^\/+|\/+$/g, '');
            if (key !== '' && screens.includes(key)) {
                void warmScreen(api, key);
            }
        },
    };
}

// ── Warming any other request (the dashboard's widgets) ─────────────────────

interface WarmCall {
    promise: Promise<unknown>;
    at: number;
    done: boolean;
    value?: unknown;
}

const calls = new Map<string, WarmCall>();

/** Asks for `key` ahead (`run`), kept in memory for the page that opens next. */
export function warmCall<T>(key: string, run: () => Promise<T>): Promise<void> {
    const known = calls.get(key);
    if (known && Date.now() - known.at < WARM_MS) {
        return known.promise.then(
            () => undefined,
            () => undefined,
        );
    }
    const entry: WarmCall = { promise: run(), at: Date.now(), done: false };
    entry.promise.then(
        (value) => {
            entry.done = true;
            entry.value = value;
        },
        () => undefined,
    );
    calls.set(key, entry);

    return entry.promise.then(
        () => undefined,
        () => undefined,
    );
}

/** The warmed request of `key`, once — `done`/`value` when it already answered (a first frame with it). */
export function takeWarmCall<T>(key: string): { promise: Promise<T>; done: boolean; value?: T } | null {
    const known = calls.get(key);
    calls.delete(key);

    return known && Date.now() - known.at < WARM_MS ? (known as { promise: Promise<T>; done: boolean; value?: T }) : null;
}

/** The warmed answer of a screen, once: null when there is none (or it is old). */
export function takeWarm(screen: string): Promise<[Schema, Listing]> | null {
    const known = warm.get(screen);
    warm.delete(screen);

    return known && Date.now() - known.at < WARM_MS ? known.promise : null;
}

/** Forgets every kept listing — signing out, switching company. */
export function forgetSnapshots(): void {
    warm.clear();
    try {
        const store = storage();
        if (!store) {
            return;
        }
        const keys: string[] = [];
        for (let index = 0; index < store.length; index++) {
            const key = store.key(index);
            if (key?.startsWith(SNAPSHOT_PREFIX)) {
                keys.push(key);
            }
        }
        keys.forEach((key) => store.removeItem(key));
    } catch {
        // Nothing to forget.
    }
}
