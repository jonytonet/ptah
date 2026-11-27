/**
 * The layout's notification bell and exports panel for React, over the shell
 * API. Who sees which notification or export, the safe URLs and the
 * ownership checks are the server's (NotificationBell, ExportsPanel); these
 * components only ask and show.
 */
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { MouseEvent as ReactMouseEvent } from 'react';
import { navigateTo, PtahApi } from '../../core';
import {
    badgeText,
    dismissNotification,
    exportStatusColor,
    fetchBell,
    fetchExports,
    markAllNotificationsRead,
    openNotification,
    removeExport,
    shellWords,
    startPolling,
} from '../../core/admin/shell';
import type { BellState, ExportRow, ExportsListing, ShellMessage, ShellNotification } from '../../core/admin/shell';
import { listenBroadcast } from '../../core/realtime';
import { ForgeAlert, ForgeButton, ForgeEmpty, ForgeModal, ForgePagination, ForgeSpinner } from '../forge';
import { Icon } from '../icons';

export interface ShellProps {
    api?: PtahApi;
    locale?: string;
    /** Milliseconds between refreshes; null turns polling off. Defaults to what the server reports. */
    pollInterval?: number | null;
}

export interface PtahNotificationBellProps extends ShellProps {
    /** Go to a URL without a full page load. Defaults to the mounted app's navigator, else a normal load. */
    onNavigate?: (url: string) => void;
}

/** Keeps `fn` current for a timer that was started with an older one. */
function useLatest<T>(value: T) {
    const ref = useRef(value);
    ref.current = value;

    return ref;
}

function BellIcon() {
    return <Icon name="bell" size={20} className="pl-svg" />;
}

// ── Notification bell ───────────────────────────────────────────────────────

export function PtahNotificationBell({ api, locale, pollInterval, onNavigate }: PtahNotificationBellProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = shellWords(locale);
    const [state, setState] = useState<BellState | null>(null);
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<ShellMessage | null>(null);
    const root = useRef<HTMLDivElement>(null);
    const openRef = useLatest(open);

    const load = useCallback(
        async (withItems: boolean) => {
            try {
                setState(await fetchBell(client, withItems));
                setError(null);
            } catch {
                setError(t.error);
            }
        },
        [client, t.error],
    );
    const refresh = useLatest(() => void load(openRef.current));

    useEffect(() => {
        void load(false);
    }, [load]);

    // The prop wins; otherwise the interval the server is configured with.
    const interval = pollInterval !== undefined ? pollInterval : (state?.poll_ms ?? null);
    const enabled = state?.enabled ?? false;
    useEffect(() => (enabled ? startPolling(() => refresh.current(), interval) : undefined), [enabled, interval, refresh]);
    // And at once when the server says so (a websocket, when the app has Echo).
    const channel = state?.broadcast?.channel;
    useEffect(() => (enabled ? listenBroadcast(state?.broadcast, () => refresh.current()) : undefined), [enabled, channel, refresh]); // eslint-disable-line react-hooks/exhaustive-deps

    const close = useCallback(() => {
        setOpen(false);
        setNotice(null);
    }, []);

    // Esc and a click outside close the dropdown, as on the Livewire bell.
    useEffect(() => {
        if (!open) {
            return;
        }
        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && close();
        const onDown = (event: MouseEvent) => root.current && !root.current.contains(event.target as Node) && close();
        document.addEventListener('keydown', onKey);
        document.addEventListener('mousedown', onDown);

        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('mousedown', onDown);
        };
    }, [open, close]);

    const toggle = () => {
        if (open) {
            close();

            return;
        }
        setOpen(true);
        setState((current) => (current ? { ...current, items: null } : current));
        void load(true);
    };

    const act = async (action: () => Promise<BellState>): Promise<BellState | null> => {
        setBusy(true);
        try {
            const answer = await action();
            setState(answer);
            setError(null);

            return answer;
        } catch {
            // Gone or not this user's: show the list as the server has it now.
            setError(t.error);
            void load(true);

            return null;
        } finally {
            setBusy(false);
        }
    };

    const openItem = async (event: ReactMouseEvent, item: ShellNotification) => {
        event.preventDefault();
        const answer = await act(() => openNotification(client, item.id));
        if (answer?.redirect) {
            (onNavigate ?? navigateTo)(answer.redirect);
        }
    };

    const markAll = async () => {
        const answer = await act(() => markAllNotificationsRead(client));
        setNotice(answer?.messages?.[0] ?? null);
    };

    // Until the count arrives the bell is already in the bar (the layout knows it is on): no pop-in.
    if (!state) {
        return (
            <div className="pl-dropdown-root pl-bell" aria-hidden="true">
                <span className="pl-icon-btn">
                    <BellIcon />
                </span>
            </div>
        );
    }
    if (!state.enabled) {
        return null;
    }

    const items = state.items;
    const label = state.unread > 0 ? `${t.bellTitle} (${t.unreadBadge(state.unread)})` : t.bellTitle;

    return (
        // The markup and classes of notification-bell.blade.php: a navbar icon
        // button with the count, and a panel of the admin dropdown's look.
        <div className="pl-dropdown-root pl-bell" ref={root}>
            <button type="button" className="pl-icon-btn" aria-haspopup="menu" aria-expanded={open} aria-label={label} title={t.bellTitle} onClick={toggle}>
                <BellIcon />
                {state.unread > 0 && (
                    <span className="pl-badge" aria-hidden="true">
                        {badgeText(state.unread)}
                    </span>
                )}
            </button>

            {open && (
                <div className="pl-dropdown pl-dropdown--bell" role="menu" aria-label={t.bellTitle} aria-busy={busy || items === null}>
                    {/* Only the items scroll: the footer stays reachable however long the list is. */}
                    <div role="none" className="pl-bell-scroll">
                        {items === null ? (
                            <div role="none" className="pl-bell-empty">
                                <ForgeSpinner size="sm" label={t.loading} />
                            </div>
                        ) : items.length === 0 ? (
                            <p role="none" className="pl-bell-empty">
                                {t.empty}
                            </p>
                        ) : (
                            items.map((item) => (
                                <div role="none" key={item.id} className="pl-bell-row">
                                    {item.url ? (
                                        <a role="menuitem" className="pl-bell-item" href={item.url} onClick={(event) => void openItem(event, item)}>
                                            <span className="pl-bell-title">{item.title}</span>
                                            {item.body && <span className="pl-bell-body">{item.body}</span>}
                                            {item.created_at_human && <span className="pl-bell-time">{item.created_at_human}</span>}
                                            <span className="pl-bell-action">{item.action_label || t.actionDefault}</span>
                                        </a>
                                    ) : (
                                        <button type="button" role="menuitem" className="pl-bell-item" disabled aria-disabled="true">
                                            <span className="pl-bell-title">{item.title}</span>
                                            {item.body && <span className="pl-bell-body">{item.body}</span>}
                                            {item.created_at_human && <span className="pl-bell-time">{item.created_at_human}</span>}
                                        </button>
                                    )}
                                    <button
                                        type="button"
                                        role="menuitem"
                                        className="pl-bell-dismiss"
                                        aria-label={`${t.dismiss}: ${item.title}`}
                                        title={t.dismiss}
                                        disabled={busy}
                                        onClick={() => void act(() => dismissNotification(client, item.id))}
                                    >
                                        <Icon name="x" size={14} />
                                        {t.dismiss}
                                    </button>
                                </div>
                            ))
                        )}
                    </div>

                    {error && (
                        <p role="alert" className="pl-bell-note pl-bell-note--error">
                            {error}
                        </p>
                    )}
                    {notice && (
                        <p role="status" className="pl-bell-note">
                            {notice.text}
                        </p>
                    )}

                    <div role="none" className="pl-bell-foot">
                        <button type="button" role="menuitem" className="pl-bell-link" disabled={busy} onClick={() => void markAll()}>
                            {t.markAllRead}
                        </button>
                        {state.view_all_url && (
                            <a role="menuitem" className="pl-bell-link" href={state.view_all_url}>
                                {t.viewAll}
                            </a>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

// ── Exports panel ───────────────────────────────────────────────────────────

export function PtahExportsPanel({ api, locale, pollInterval }: ShellProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = shellWords(locale);
    const [listing, setListing] = useState<ExportsListing | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [confirming, setConfirming] = useState<ExportRow | null>(null);
    const [removing, setRemoving] = useState<number | null>(null);
    const page = listing?.meta.current_page ?? 1;

    const load = useCallback(
        async (target: number) => {
            setLoading(true);
            try {
                setListing(await fetchExports(client, target));
                setError(null);
            } catch {
                setError(t.error);
            } finally {
                setLoading(false);
            }
        },
        [client, t.error],
    );
    const refresh = useLatest(() => void load(page));

    useEffect(() => {
        void load(1);
    }, [load]);

    // Only while an export is still queued/processing, as `wire:poll.5s` does.
    const pending = listing?.has_pending ?? false;
    const interval = pending ? (pollInterval !== undefined ? pollInterval : (listing?.poll_ms ?? null)) : null;
    useEffect(() => startPolling(() => refresh.current(), interval), [interval, refresh]);

    const remove = async () => {
        const row = confirming;
        setConfirming(null);
        if (!row) {
            return;
        }
        setRemoving(row.id);
        try {
            setListing(await removeExport(client, row.id, page));
            setError(null);
        } catch {
            setError(t.error);
            void load(page);
        } finally {
            setRemoving(null);
        }
    };

    const headers: [string, 'left' | 'center' | 'right'][] = [
        [t.colModel, 'left'],
        [t.colFormat, 'left'],
        [t.colStatus, 'center'],
        [t.colRows, 'right'],
        [t.colCreated, 'left'],
        [t.colActions, 'right'],
    ];

    return (
        <section className="pf">
            <header className="pf-page-header">
                <h2 className="pf-page-title">{t.exportsTitle}</h2>
            </header>

            {error && <ForgeAlert type="danger">{error}</ForgeAlert>}

            {listing === null ? (
                !error && <ForgeSpinner label={t.loading} />
            ) : listing.data.length === 0 ? (
                <ForgeEmpty title={t.exportsEmpty} />
            ) : (
                <div className="pf-table-wrap" aria-busy={loading || undefined}>
                    <table className="pf-table">
                        <thead>
                            <tr>
                                {headers.map(([header, align]) => (
                                    <th key={header} scope="col" className="pa-exp-th" data-align={align}>
                                        {header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        {/* Announces queued → processing → done/failed as the panel refreshes. */}
                        <tbody aria-live="polite">
                            {listing.data.map((row) => (
                                <tr key={row.id}>
                                    <td>{row.model}</td>
                                    <td className="pa-exp-format">{row.format}</td>
                                    <td className="pc-align-center">
                                        <span className="pf pa-exp-status" data-color={exportStatusColor(row.status)} title={row.error ?? undefined}>
                                            {t.status[row.status] ?? row.status}
                                        </span>
                                        {row.error && (
                                            <div className="pf-field-error pa-text-xs" title={row.error}>
                                                {row.error}
                                            </div>
                                        )}
                                    </td>
                                    <td className="pc-align-right">{row.rows ?? '—'}</td>
                                    <td className="pa-exp-date">{row.created_at_display}</td>
                                    <td className="pc-align-right pa-nowrap">
                                        {row.download_url && (
                                            <ForgeButton size="sm" flat href={row.download_url}>
                                                {t.download}
                                            </ForgeButton>
                                        )}{' '}
                                        <ForgeButton size="sm" flat color="danger" loading={removing === row.id} onClick={() => setConfirming(row)}>
                                            {t.delete}
                                        </ForgeButton>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {listing && listing.meta.last_page > 1 && <ForgePagination page={page} lastPage={listing.meta.last_page} onChange={(target) => void load(target)} />}

            <ForgeModal
                open={confirming !== null}
                onClose={() => setConfirming(null)}
                title={t.delete}
                size="sm"
                footer={
                    <>
                        <ForgeButton flat color="dark" onClick={() => setConfirming(null)}>
                            {t.cancel}
                        </ForgeButton>
                        <ForgeButton color="danger" onClick={() => void remove()}>
                            {t.delete}
                        </ForgeButton>
                    </>
                }
            >
                <p>{t.removeConfirm}</p>
            </ForgeModal>
        </section>
    );
}
