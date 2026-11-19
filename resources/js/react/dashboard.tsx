/**
 * The dashboard for React: the widgets of config/ptah-dashboard.php, computed
 * by the server — the same four kinds ptah::dashboard.widgets draws — or,
 * with none configured, the four cards of livewire/auth/dashboard.blade.php.
 */
import { useEffect, useMemo, useState } from 'react';
import { PtahApi, words } from '../core';
import type { DashboardPage, Me, Widget } from '../core';
import { Icon } from './icons';
import { PtahPageHead } from './page-head';

/** ptah::dashboard.widgets' tones: the strong variants of the semantic colours. */
const TONE: Record<string, string> = {
    primary: 'var(--ptah-primary, var(--pc-primary))',
    success: 'var(--ptah-success-strong, var(--pc-success))',
    danger: 'var(--ptah-danger-strong, var(--pc-danger))',
    warn: 'var(--ptah-warn-strong, var(--pc-warn))',
};
const tone = (color: string) => TONE[color] ?? TONE.primary;

/** The widgets of a group (default: the dashboard's), anywhere in a page. */
export function PtahWidgets({ api, group, onLoad }: { api?: PtahApi; group?: string; onLoad?: (widgets: Widget[], page: DashboardPage) => void }) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const [widgets, setWidgets] = useState<Widget[] | null>(null);

    useEffect(() => {
        let alive = true;
        void client
            .dashboardPage(group)
            .then((page) => {
                if (alive) {
                    setWidgets(page.data);
                    onLoad?.(page.data, page);
                }
            })
            .catch(() => alive && setWidgets([]));

        return () => {
            alive = false;
        };
    }, [client, group]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!widgets || widgets.length === 0) {
        return null;
    }

    return (
        <div className="pf pd-grid">
            {widgets.map((widget, index) => (
                <WidgetCard key={index} widget={widget} />
            ))}
        </div>
    );
}

function WidgetCard({ widget }: { widget: Widget }) {
    const wide = ['trend', 'latest', 'breakdown'].includes(widget.type);
    const className = `pd-card${wide ? ' pd-wide' : ''}${widget.link ? ' pd-card--link' : ''}`;
    const body = (
        <>
            <p className="pd-label">{widget.label}</p>
            {widget.error ? (
                <p className="pd-error" role="alert">
                    {widget.error}
                </p>
            ) : (
                <WidgetBody widget={widget} />
            )}
        </>
    );

    return widget.link ? (
        <a className={className} href={widget.link}>
            {body}
        </a>
    ) : (
        <div className={className}>{body}</div>
    );
}

function WidgetBody({ widget }: { widget: Widget }) {
    const color = tone(widget.color);

    if (widget.type === 'trend') {
        const points = widget.points ?? [];
        const max = Math.max(1, Number(widget.max) || 1);
        const width = 100 / Math.max(1, points.length);

        return (
            <>
                <div className="pd-trend-head">
                    <span className="pd-value" style={{ color }}>
                        {widget.display_total}
                    </span>
                    {points.length > 0 && (
                        <span className="pd-muted">
                            {points[0].label} – {points[points.length - 1].label}
                        </span>
                    )}
                </div>
                <svg className="pd-trend" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label={`${widget.label}: ${points.map((point) => `${point.label} ${point.display}`).join(', ')}`}>
                    {points.map((point, index) => {
                        const height = point.value > 0 ? Math.max(1.5, (38 * point.value) / max) : 0.6;

                        return (
                            <rect key={index} x={index * width + width * 0.15} y={40 - height} width={width * 0.7} height={height} style={{ fill: point.value > 0 ? color : 'var(--ptah-line, var(--pc-line))' }}>
                                <title>
                                    {point.label}: {point.display}
                                </title>
                            </rect>
                        );
                    })}
                </svg>
            </>
        );
    }

    if (widget.type === 'breakdown') {
        const items = widget.items ?? [];
        const max = Math.max(1, Number(widget.max) || 1);

        return (
            <>
                <p className="pd-value" style={{ color }}>
                    {widget.display_total}
                </p>
                <ul className="pd-bars">
                    {items.length === 0 && <li className="pd-muted pd-small">—</li>}
                    {items.map((item, index) => (
                        <li key={index}>
                            <div className="pd-bar-head">
                                <span className={item.others ? 'pd-bar-label pd-muted' : 'pd-bar-label'}>{item.label}</span>
                                <span className="pd-muted pd-num">{item.display}</span>
                            </div>
                            <div className="pd-bar-track" aria-hidden="true">
                                <div className="pd-bar" style={{ width: `${item.value > 0 ? Math.max(1, Math.round((1000 * item.value) / max) / 10) : 0}%`, background: item.others ? 'var(--ptah-line-strong, var(--pc-line-field))' : color }} />
                            </div>
                        </li>
                    ))}
                </ul>
            </>
        );
    }

    if (widget.type === 'latest') {
        const rows = widget.rows ?? [];

        return (
            <table className="pd-table">
                <tbody>
                    {rows.length === 0 && (
                        <tr>
                            <td className="pd-muted pd-small">—</td>
                        </tr>
                    )}
                    {rows.map((row, index) => (
                        <tr key={index}>
                            {row.map((cell, cellIndex) => (
                                <td key={cellIndex} className={cellIndex === 0 ? 'pd-cell-main' : 'pd-muted'}>
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        );
    }

    return (
        <p className="pd-value" style={{ color }}>
            {widget.value}
        </p>
    );
}

/** A forge-stat-card: title, big value, the tinted icon square on the right. */
function StatCard({ title, value, icon, color }: { title: string; value: string; icon: string; color: 'primary' | 'success' }) {
    return (
        <div className="pd-stat" data-color={color}>
            <div className="pd-stat-text">
                <p className="pd-stat-title">{title}</p>
                <p className="pd-stat-value">{value}</p>
            </div>
            <div className="pd-stat-icon">
                <Icon name={icon} size={24} />
            </div>
        </div>
    );
}

/** The dashboard page: the header of livewire/auth/dashboard, and the widgets (or its four default cards). */
export function PtahDashboard({ api, locale, group }: { api?: PtahApi; locale?: string; group?: string }) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const [me, setMe] = useState<Me | null>(null);
    const [page, setPage] = useState<DashboardPage | null>(null);
    const t = words(locale ?? me?.locale);

    useEffect(() => {
        let alive = true;
        void client
            .me()
            .then((value) => alive && setMe(value))
            .catch(() => undefined);

        return () => {
            alive = false;
        };
    }, [client]);

    const fallback = page?.fallback ?? null;

    return (
        <div className="pf">
            <PtahPageHead title={t.dashboard} subtitle={t.dashboardSubtitle} />
            <PtahWidgets api={client} group={group} onLoad={(_widgets, answer) => setPage(answer)} />
            {fallback && (
                <div className="pd-stats">
                    <StatCard title={t.welcome} value={me?.user?.name ?? ''} icon="user" color="primary" />
                    <StatCard title={t.dashboardSystem} value={fallback.app} icon="server" color="primary" />
                    <StatCard title={t.dashboardEnvironment} value={fallback.environment} icon="checkCircle" color="success" />
                    <StatCard title={t.dashboardLaravel} value={fallback.laravel} icon="bolt" color="primary" />
                </div>
            )}
            {page && page.data.length === 0 && !fallback && <p className="pd-muted pd-small">{t.empty}</p>}
        </div>
    );
}
