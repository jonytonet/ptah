import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { PtahApi, words } from '../core';
import type { Me, MenuItem } from '../core';

export interface PtahLayoutProps {
    children: ReactNode;
    /** The name shown above the menu. */
    brand?: ReactNode;
    /** The title in the top bar. */
    title?: ReactNode;
    /** Extra content at the end of the top bar (a logout button, a theme switch). */
    topbar?: ReactNode;
    api?: PtahApi;
    locale?: string;
    /** The current path, to mark the active item. Defaults to the browser's. */
    path?: string;
    /** Navigate without a full page load (a router's `navigate`). Defaults to a normal link. */
    onNavigate?: (url: string) => void;
}

function isActive(item: MenuItem, path: string): boolean {
    if (!item.url) {
        return false;
    }
    let target = item.url;
    try {
        target = new URL(item.url, 'http://localhost').pathname;
    } catch {
        // A relative URL the parser refuses: compared as written.
    }
    const trim = (value: string) => value.replace(/\/+$/, '') || '/';

    return trim(target) === trim(path);
}

function hasActive(item: MenuItem, path: string): boolean {
    return isActive(item, path) || (item.children ?? []).some((child) => hasActive(child, path));
}

/**
 * The shell around the screens: the menu the Livewire sidebar draws (same
 * tree, same permissions) and a top bar with the signed-in user.
 */
export function PtahLayout({ children, brand = 'Ptah', title, topbar, api, locale, path, onNavigate }: PtahLayoutProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const [menu, setMenu] = useState<MenuItem[]>([]);
    const [me, setMe] = useState<Me | null>(null);
    const [open, setOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);
    const t = words(locale ?? me?.locale);
    const current = path ?? (typeof window !== 'undefined' ? window.location.pathname : '/');

    useEffect(() => {
        let alive = true;
        void client.menu().then((items) => alive && setMenu(items)).catch(() => undefined);
        void client.me().then((user) => alive && setMe(user)).catch(() => undefined);

        return () => {
            alive = false;
        };
    }, [client]);

    const toggle = () => {
        setOpen(!open);
        setCollapsed(!collapsed);
    };

    return (
        <div className={`pc-shell${open ? ' pc-shell--open' : ''}${collapsed ? ' pc-shell--collapsed' : ''}`}>
            <aside className="pc-sidebar" aria-label={t.menu}>
                <div className="pc-brand">{brand}</div>
                <MenuList items={menu} path={current} onNavigate={onNavigate} />
            </aside>
            <div className="pc-main">
                <header className="pc-topbar">
                    <button type="button" className="pc-icon-btn" aria-label={t.menu} aria-expanded={!collapsed} onClick={toggle}>
                        ☰
                    </button>
                    {title && <span className="pc-topbar-title">{title}</span>}
                    {me?.user && <span className="pc-user">{me.user.name}</span>}
                    {topbar}
                </header>
                <main className="pc-content">{children}</main>
            </div>
        </div>
    );
}

function MenuList({ items, path, onNavigate }: { items: MenuItem[]; path: string; onNavigate?: (url: string) => void }) {
    return (
        <ul className="pc-nav">
            {items.map((item, index) => (
                <MenuEntry key={`${item.label}-${index}`} item={item} path={path} onNavigate={onNavigate} />
            ))}
        </ul>
    );
}

function MenuEntry({ item, path, onNavigate }: { item: MenuItem; path: string; onNavigate?: (url: string) => void }) {
    const children = item.children ?? [];
    const [open, setOpen] = useState(() => hasActive(item, path));
    const label = item.label || item.text || '';
    const icon = item.icon ? <i className={item.icon} aria-hidden="true" /> : null;

    if (children.length > 0) {
        return (
            <li>
                <button type="button" className="pc-nav-group" aria-expanded={open} onClick={() => setOpen(!open)}>
                    {icon}
                    {label}
                    <span className="pc-nav-caret" aria-hidden="true">
                        {open ? '▾' : '▸'}
                    </span>
                </button>
                {open && <MenuList items={children} path={path} onNavigate={onNavigate} />}
            </li>
        );
    }

    const active = isActive(item, path);

    return (
        <li>
            <a
                className={`pc-nav-link${active ? ' pc-nav-link--active' : ''}`}
                href={item.url ?? '#'}
                target={item.target && item.target !== '_self' ? item.target : undefined}
                rel={item.target === '_blank' ? 'noopener noreferrer' : undefined}
                aria-current={active ? 'page' : undefined}
                onClick={(event) => {
                    if (onNavigate && item.url && (!item.target || item.target === '_self') && !event.metaKey && !event.ctrlKey) {
                        event.preventDefault();
                        onNavigate(item.url);
                    }
                }}
            >
                {icon}
                {label}
            </a>
        </li>
    );
}
