/**
 * The dashboard for React: the widgets of config/ptah-dashboard.php, computed
 * by the server — the same four kinds ptah::dashboard.widgets draws.
 */
import { useEffect, useMemo, useState } from 'react';
import { PtahApi, words } from '../core';
import type { Me, Widget } from '../core';

const TONE: Record<string, string> = { primary: 'var(--pc-primary)', success: 'var(--pc-success)', danger: 'var(--pc-danger)', warn: 'var(--pc-warn)' };
const tone = (color: string) => TONE[color] ?? TONE.primary;

/** The widgets of a group (default: the dashboard's), anywhere in a page. */
export function PtahWidgets({ api, group, onLoad }: { api?: PtahApi; group?: string; onLoad?: (widgets: Widget[]) => void }) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const [widgets, setWidgets] = useState<Widget[] | null>(null);

    useEffect(() => {
        let alive = true;
        void client
            .dashboard(group)
            .then((list) => {
                if (alive) {
                    setWidgets(list);
                    onLoad?.(list);
                }
            })
            .catch(() => alive && setWidgets([]));

        return () => {
            alive = false;
        };
    }, [client, group]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!widgets) {
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
    const body = (
        <div className="pf-card-body">
            <div className="pf-stat-title">{widget.label}</div>
            {widget.error ? (
                <p className="pf-field-error" role="alert">
                    {widget.error}
                </p>
            ) : (
                <WidgetBody widget={widget} />
            )}
        </div>
    );

    return widget.link ? (
        <a className={`pf pf-card pf-card--hoverable${wide ? ' pd-wide' : ''}`} href={widget.link} style={{ textDecoration: 'none', color: 'inherit' }}>
            {body}
        </a>
    ) : (
        <div className={`pf pf-card${wide ? ' pd-wide' : ''}`}>{body}</div>
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
                <div className="pa-row">
                    <span className="pf-stat-value" style={{ color }}>
                        {widget.display_total}
                    </span>
                    {points.length > 0 && (
                        <span className="pf-chart-subtitle">
                            {points[0].label} – {points[points.length - 1].label}
                        </span>
                    )}
                </div>
                <svg viewBox="0 0 100 40" preserveAspectRatio="none" role="img" style={{ width: '100%', height: 64, marginTop: 10 }} aria-label={`${widget.label}: ${points.map((point) => `${point.label} ${point.display}`).join(', ')}`}>
                    {points.map((point, index) => {
                        const height = point.value > 0 ? Math.max(1.5, (38 * point.value) / max) : 0.6;

                        return (
                            <rect key={index} x={index * width + width * 0.15} y={40 - height} width={width * 0.7} height={height} style={{ fill: point.value > 0 ? color : 'var(--pc-line)' }}>
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
                <div className="pf-stat-value" style={{ color }}>
                    {widget.display_total}
                </div>
                <ul style={{ listStyle: 'none', margin: '10px 0 0', padding: 0, display: 'flex', flexDirection: 'column', gap: 8 }}>
                    {items.length === 0 && <li className="pf-chart-subtitle">—</li>}
                    {items.map((item, index) => (
                        <li key={index}>
                            <div className="pa-row">
                                <span>{item.label}</span>
                                <span className="pf-chart-subtitle">{item.display}</span>
                            </div>
                            <div className="pf-progress-track" aria-hidden="true" style={{ height: 6, marginTop: 4 }}>
                                <div className="pf-progress-bar" style={{ width: `${item.value > 0 ? Math.max(1, Math.round((1000 * item.value) / max) / 10) : 0}%`, background: item.others ? 'var(--pc-line-field)' : color }} />
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
            <table className="pf-table" style={{ marginTop: 6 }}>
                <tbody>
                    {rows.length === 0 && (
                        <tr>
                            <td className="pf-chart-subtitle">—</td>
                        </tr>
                    )}
                    {rows.map((row, index) => (
                        <tr key={index}>
                            {row.map((cell, cellIndex) => (
                                <td key={cellIndex} style={cellIndex === 0 ? { fontWeight: 500 } : { color: 'var(--pc-text-muted)' }}>
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
        <div className="pf-stat-value" style={{ color }}>
            {widget.value}
        </div>
    );
}

/** The dashboard page: a welcome and the widgets. */
export function PtahDashboard({ api, locale, group }: { api?: PtahApi; locale?: string; group?: string }) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const [me, setMe] = useState<Me | null>(null);
    const [count, setCount] = useState<number | null>(null);
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

    return (
        <div className="pf">
            <h1 className="pd-welcome">
                {t.welcome}
                {me?.user?.name ? `, ${me.user.name}` : ''}
            </h1>
            <PtahWidgets api={client} group={group} onLoad={(list) => setCount(list.length)} />
            {count === 0 && <p className="pf-chart-subtitle">{t.empty}</p>}
        </div>
    );
}
