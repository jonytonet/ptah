import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    forgetDrafts,
    hasActive,
    initials,
    isActive,
    isCollapseShortcut,
    isDark,
    onDarkChange,
    PtahApi,
    readCollapsed,
    searchMenu,
    toggleDark,
    trailOf,
    visible,
    words,
    writeCollapsed,
} from '../core';
import type { Me, MenuItem, MenuLink, Shell, Words } from '../core';
import { PtahAiChat } from './admin/ai';
import { PtahCompanySwitcher } from './admin/company';
import { PtahNotificationBell } from './admin/shell';

export interface PtahLayoutProps {
    children: ReactNode;
    /** The name shown above the menu. Defaults to the app's name. */
    brand?: ReactNode;
    /** The title in the top bar. */
    title?: ReactNode;
    /** Extra content in the top bar, before the account menu (a company switcher, the notification bell). */
    topbar?: ReactNode;
    api?: PtahApi;
    locale?: string;
    /** The current path, to mark the active item. Defaults to the browser's. */
    path?: string;
    /** Navigate without a full page load (a router's `navigate`). Defaults to a normal link. */
    onNavigate?: (url: string) => void;
    /** The profile page, in the account menu. */
    profileUrl?: string;
    /** Runs "log out" (the account menu and the foot of the sidebar). Omit to hide it. */
    onLogout?: () => void;
    /** Where the light/dark choice of a signed-in user is saved (`ptah.appearance.theme-mode`). */
    themeModeUrl?: string;
}

const DESKTOP = '(min-width: 1024px)';
const EMPTY: Shell = { data: [], flat: [], jump: false, brand: { name: '', logo: null, logo_dark: null }, shell: { company_switcher: false, notifications: false, admin: [] } };

/**
 * The shell around the screens, with everything the Livewire layout does: the
 * server's menu (same tree, same permissions) that collapses to a rail and
 * opens by itself on hover, the "go to a screen" search, the mobile overlay,
 * Ctrl+B, the light/dark switch and the account menu.
 */
export function PtahLayout({ children, brand, title, topbar, api, locale, path, onNavigate, profileUrl, onLogout, themeModeUrl }: PtahLayoutProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const [shell, setShell] = useState<Shell>(EMPTY);
    const [me, setMe] = useState<Me | null>(null);
    const [collapsed, setCollapsed] = useState(readCollapsed);
    const [hovered, setHovered] = useState(false);
    const [open, setOpen] = useState(false);
    const [desktop, setDesktop] = useState(() => (typeof window === 'undefined' || !window.matchMedia ? true : window.matchMedia(DESKTOP).matches));
    const [dark, setDark] = useState(isDark);
    const jumpRef = useRef<HTMLInputElement>(null);
    const t = words(locale ?? me?.locale);
    const current = path ?? (typeof window !== 'undefined' ? window.location.pathname : '/');
    // Only the icons: collapsed on a desktop, and the mouse is not over it.
    const iconOnly = desktop && collapsed && !hovered;

    useEffect(() => {
        let alive = true;
        void client
            .shell()
            .then((value) => alive && setShell(value))
            .catch(() => undefined);
        void client
            .me()
            .then((user) => alive && setMe(user))
            .catch(() => undefined);

        return () => {
            alive = false;
        };
    }, [client]);

    const collapse = useCallback((value: boolean) => {
        setCollapsed(value);
        writeCollapsed(value);
    }, []);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (isCollapseShortcut(event)) {
                event.preventDefault();
                setCollapsed((value) => {
                    writeCollapsed(!value);

                    return !value;
                });
            } else if (event.key === 'Escape') {
                setOpen(false);
            }
        };
        document.addEventListener('keydown', onKey);
        const stop = onDarkChange(setDark);
        const media = typeof window !== 'undefined' && window.matchMedia ? window.matchMedia(DESKTOP) : null;
        const onMedia = () => media && setDesktop(media.matches);
        media?.addEventListener?.('change', onMedia);

        return () => {
            document.removeEventListener('keydown', onKey);
            stop();
            media?.removeEventListener?.('change', onMedia);
        };
    }, []);

    const follow = (url: string, target?: string) => {
        setOpen(false);
        if (target === '_blank') {
            window.open(url, '_blank', 'noopener');
        } else if (onNavigate) {
            onNavigate(url);
        } else {
            window.location.assign(url);
        }
    };

    // Icon-only cannot show a group or the search: expand first, then act.
    const expand = () => {
        if (desktop) {
            collapse(false);
        }
    };

    const logout = onLogout
        ? () => {
              forgetDrafts();
              onLogout();
          }
        : undefined;

    const name = shell.brand.name || '';

    return (
        <div className="pl-shell" data-collapsed={collapsed} data-icon-only={iconOnly} data-open={open}>
            <button type="button" className="pl-overlay" aria-label={t.closeMenu} onClick={() => setOpen(false)} />
            <aside className="pl-sidebar" aria-label={t.menu} data-hover={hovered} onMouseEnter={() => setHovered(true)} onMouseLeave={() => setHovered(false)}>
                <div className="pl-brand">
                    {shell.brand.logo ? (
                        <>
                            <img className={`pl-logo${shell.brand.logo_dark ? ' pl-logo--light' : ''}`} src={shell.brand.logo} alt={name} />
                            {shell.brand.logo_dark && <img className="pl-logo pl-logo--dark" src={shell.brand.logo_dark} alt={name} />}
                        </>
                    ) : (
                        <>
                            <span className="pl-logo-mark" aria-hidden="true">
                                {(typeof brand === 'string' ? brand : name).trim().charAt(0).toUpperCase() || 'P'}
                            </span>
                            <span className="pl-brand-name">{brand ?? name}</span>
                        </>
                    )}
                </div>

                {shell.jump && (
                    <MenuJump
                        links={shell.flat}
                        t={t}
                        inputRef={jumpRef}
                        onGo={(link) => follow(link.url, link.target)}
                        onIcon={() => {
                            expand();
                            setTimeout(() => jumpRef.current?.focus(), 0);
                        }}
                    />
                )}

                <nav className="pl-nav-scroll">
                    <MenuList items={shell.data} path={current} iconOnly={iconOnly} onExpand={expand} onFollow={follow} routed={Boolean(onNavigate)} />
                </nav>

                {logout && (
                    <div className="pl-foot">
                        <button type="button" className="pl-item" title={t.logOut} onClick={logout}>
                            <span className="pl-item-icon" aria-hidden="true">
                                <i className="bx bx-log-out" />
                            </span>
                            <span className="pl-item-label">{t.logOut}</span>
                        </button>
                    </div>
                )}
            </aside>

            <div className="pl-main">
                <header className="pl-topbar">
                    <button type="button" className="pl-icon-btn pl-only-mobile" aria-label={open ? t.closeMenu : t.openMenu} aria-expanded={open} onClick={() => setOpen(!open)}>
                        ☰
                    </button>
                    <button type="button" className="pl-icon-btn pl-only-desktop" aria-label={collapsed ? t.expandMenu : t.collapseMenu} aria-pressed={collapsed} title="Ctrl+B" onClick={() => collapse(!collapsed)}>
                        {collapsed ? '»' : '«'}
                    </button>
                    {title && <span className="pl-topbar-title">{title}</span>}
                    <div className="pl-topbar-end">
                        {shell.shell.company_switcher && (
                            <span className="pl-only-desktop">
                                <PtahCompanySwitcher api={client} locale={locale ?? me?.locale} />
                            </span>
                        )}
                        {topbar}
                        {shell.shell.notifications && <PtahNotificationBell api={client} locale={locale ?? me?.locale} onNavigate={onNavigate} />}
                        <button type="button" className="pl-icon-btn" aria-label={dark ? t.lightMode : t.darkMode} aria-pressed={dark} onClick={() => toggleDark(themeModeUrl)}>
                            {dark ? '☀' : '☾'}
                        </button>
                        {me?.user && (
                            <AccountMenu
                                me={me}
                                t={t}
                                profileUrl={profileUrl}
                                onLogout={logout}
                                admin={shell.shell.admin}
                                current={current}
                                switcher={shell.shell.company_switcher ? <PtahCompanySwitcher api={client} locale={locale ?? me?.locale} layout="stacked" /> : null}
                            />
                        )}
                    </div>
                </header>
                <main className="pl-content">{children}</main>
            </div>
            {shell.shell.ai_chat && <PtahAiChat api={client} locale={locale ?? me?.locale} />}
        </div>
    );
}

// ── Menu ────────────────────────────────────────────────────────────────────

interface MenuProps {
    items: MenuItem[];
    path: string;
    iconOnly: boolean;
    onExpand: () => void;
    onFollow: (url: string, target?: string) => void;
    /** With a router, a plain click is handled in the page; otherwise the link navigates by itself. */
    routed: boolean;
    nested?: boolean;
}

function MenuList({ items, nested, ...rest }: MenuProps) {
    return (
        <ul className={nested ? 'pl-nav pl-subnav' : 'pl-nav'}>
            {visible(items).map((item, index) => (
                <MenuEntry key={`${item.label}-${index}`} item={item} {...rest} />
            ))}
        </ul>
    );
}

function MenuEntry({ item, path, iconOnly, onExpand, onFollow, routed }: Omit<MenuProps, 'items' | 'nested'> & { item: MenuItem }) {
    const children = visible(item.children ?? []);
    const groupActive = children.length > 0 && hasActive(item, path);
    const [open, setOpen] = useState(groupActive);
    const label = item.label || item.text || '';
    const icon = (
        <span className="pl-item-icon" aria-hidden="true">
            <i className={item.icon?.trim() ? item.icon : 'bx bx-circle'} />
        </span>
    );

    if (children.length > 0) {
        return (
            <li>
                <button
                    type="button"
                    className={`pl-item${groupActive ? ' pl-item--group-active' : ''}`}
                    aria-expanded={open && !iconOnly}
                    title={label}
                    onClick={() => {
                        // Icon-only: expand the sidebar and open the group, in one gesture.
                        if (iconOnly) {
                            onExpand();
                            setOpen(true);
                        } else {
                            setOpen(!open);
                        }
                    }}
                >
                    {icon}
                    <span className="pl-item-label">{label}</span>
                    <span className="pl-caret" data-open={open} aria-hidden="true">
                        ▶
                    </span>
                </button>
                {open && !iconOnly && <MenuList items={children} path={path} iconOnly={iconOnly} onExpand={onExpand} onFollow={onFollow} routed={routed} nested />}
            </li>
        );
    }

    const active = isActive(item, path);
    const external = Boolean(item.target && item.target !== '_self');

    return (
        <li>
            <a
                className="pl-item"
                href={item.url ?? '#'}
                title={label}
                target={external ? item.target : undefined}
                rel={item.target === '_blank' ? 'noopener noreferrer' : undefined}
                aria-current={active ? 'page' : undefined}
                onClick={(event) => {
                    if (routed && item.url && !external && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
                        event.preventDefault();
                        onFollow(item.url);
                    }
                }}
            >
                {icon}
                <span className="pl-item-label">{label}</span>
            </a>
        </li>
    );
}

// ── "Go to a screen" ────────────────────────────────────────────────────────

function MenuJump({ links, t, inputRef, onGo, onIcon }: { links: MenuLink[]; t: Words; inputRef: React.RefObject<HTMLInputElement | null>; onGo: (link: MenuLink) => void; onIcon: () => void }) {
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(-1);
    const results = useMemo(() => searchMenu(links, query), [links, query]);
    const reset = () => {
        setQuery('');
        setActive(-1);
    };

    return (
        <div className="pl-jump">
            {/* Collapsed there is no room for a field: a magnifier that expands and focuses it. */}
            <button type="button" className="pl-item pl-jump-icon" aria-label={t.jumpTo} title={t.jumpTo} onClick={onIcon}>
                <span className="pl-item-icon" aria-hidden="true">
                    <i className="bx bx-search" />
                </span>
            </button>
            <input
                ref={inputRef}
                type="text"
                role="combobox"
                className="pl-jump-input"
                placeholder={t.jumpTo}
                aria-label={t.jumpTo}
                aria-expanded={results.length > 0}
                aria-controls="pl-jump-list"
                aria-autocomplete="list"
                autoComplete="off"
                value={query}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setActive(-1);
                }}
                onKeyDown={(event) => {
                    if (event.key === 'ArrowDown' && results.length > 0) {
                        event.preventDefault();
                        setActive((active + 1) % results.length);
                    } else if (event.key === 'ArrowUp' && results.length > 0) {
                        event.preventDefault();
                        setActive((active - 1 + results.length) % results.length);
                    } else if (event.key === 'Enter') {
                        event.preventDefault();
                        const link = results[active] ?? results[0];
                        if (link) {
                            reset();
                            onGo(link);
                        }
                    } else if (event.key === 'Escape') {
                        event.stopPropagation();
                        reset();
                    }
                }}
            />
            {query !== '' && (
                <button
                    type="button"
                    className="pf-x pl-jump-clear"
                    aria-label={t.clear}
                    onClick={() => {
                        reset();
                        inputRef.current?.focus();
                    }}
                >
                    ✕
                </button>
            )}
            {results.length > 0 && (
                <ul className="pl-jump-list" id="pl-jump-list" role="listbox">
                    {results.map((link, index) => (
                        <li key={link.url + index} role="option" aria-selected={index === active}>
                            <button
                                type="button"
                                className="pl-jump-item"
                                data-active={index === active}
                                onMouseEnter={() => setActive(index)}
                                onClick={() => {
                                    reset();
                                    onGo(link);
                                }}
                            >
                                <span>{link.label}</span>
                                {trailOf(link).length > 0 && <span className="pl-jump-trail">{trailOf(link).join(' ‹ ')}</span>}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {query.trim() !== '' && results.length === 0 && (
                <div className="pl-jump-list pl-jump-empty" role="status">
                    {t.nothingFound}
                </div>
            )}
        </div>
    );
}

// ── Account ─────────────────────────────────────────────────────────────────

function AccountMenu({
    me,
    t,
    profileUrl,
    onLogout,
    admin = [],
    current = '',
    switcher = null,
}: {
    me: Me;
    t: Words;
    profileUrl?: string;
    onLogout?: () => void;
    admin?: Shell['shell']['admin'];
    current?: string;
    switcher?: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const user = me.user!;

    useEffect(() => {
        if (!open) {
            return;
        }
        const close = (event: MouseEvent | KeyboardEvent) => {
            if (event instanceof KeyboardEvent ? event.key === 'Escape' : !ref.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, [open]);

    return (
        <div className="pl-account" ref={ref}>
            <button type="button" className="pl-account-btn" aria-haspopup="true" aria-expanded={open} aria-label={`${t.account}: ${user.name}`} onClick={() => setOpen(!open)}>
                <span className="pl-avatar">{user.photo_url ? <img src={user.photo_url} alt="" /> : initials(user.name)}</span>
                <span className="pl-only-desktop">{user.name}</span>
            </button>
            {open && (
                <ul className="pl-account-menu">
                    <li className="pl-account-head">
                        <span className="pl-account-name">{user.name}</span>
                        <span className="pl-account-mail">{user.email}</span>
                    </li>
                    {profileUrl && (
                        <li>
                            <a className="pl-account-item" href={profileUrl}>
                                {t.profile}
                            </a>
                        </li>
                    )}
                    {/* On a phone the switcher of the bar is hidden; it lives here. */}
                    {switcher && <li className="pl-only-mobile pl-account-group">{switcher}</li>}
                    {admin.length > 0 && <li className="pl-account-label">{t.administration}</li>}
                    {admin.map((link) => (
                        <li key={link.url}>
                            <a className="pl-account-item" href={link.url} aria-current={isActive({ label: link.label, url: link.url }, current) ? 'page' : undefined}>
                                <i className={link.icon} aria-hidden="true" /> {link.label}
                            </a>
                        </li>
                    ))}
                    {onLogout && (
                        <li>
                            <button type="button" className="pl-account-item" onClick={onLogout}>
                                {t.logOut}
                            </button>
                        </li>
                    )}
                </ul>
            )}
        </div>
    );
}
