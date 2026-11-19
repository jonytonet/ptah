import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode, RefObject } from 'react';
import {
    adminEntries,
    firstLetter,
    forgetDrafts,
    hasActive,
    iconOnlyOf,
    isActive,
    isCollapseShortcut,
    isDark,
    MEDIA_LG,
    MEDIA_MD,
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
import { adminIcons, iconPaths, iconStroke } from '../core/icons';
import { shellWords } from '../core/admin/shell';
import { PtahAiChat } from './admin/ai';
import { PtahCompanySwitcher } from './admin/company';
import { PtahNotificationBell } from './admin/shell';
import { Icon } from './icons';

export interface PtahLayoutProps {
    children: ReactNode;
    /** The name next to the logo. Defaults to the app's name. */
    brand?: ReactNode;
    /**
     * Not drawn in the top bar any more (the navbar has no title): the page
     * draws its own header. Kept for compatibility.
     */
    title?: ReactNode;
    /** Extra content at the start of the top bar's actions. */
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

const EMPTY: Shell = { data: [], flat: [], jump: false, brand: { name: '', logo: null, logo_dark: null }, shell: { company_switcher: false, notifications: false, admin: [] } };

function useMedia(query: string): boolean {
    const [matches, setMatches] = useState(() => (typeof window === 'undefined' || !window.matchMedia ? true : window.matchMedia(query).matches));
    useEffect(() => {
        const media = typeof window !== 'undefined' && window.matchMedia ? window.matchMedia(query) : null;
        const onChange = () => media && setMatches(media.matches);
        media?.addEventListener?.('change', onChange);

        return () => media?.removeEventListener?.('change', onChange);
    }, [query]);

    return matches;
}

/** Closes a dropdown on Esc and on a click outside it (`@click.away`). */
function useDismiss(ref: RefObject<HTMLElement | null>, open: boolean, close: () => void) {
    useEffect(() => {
        if (!open) {
            return;
        }
        const onEvent = (event: MouseEvent | KeyboardEvent) => {
            if (event instanceof KeyboardEvent ? event.key === 'Escape' : !ref.current?.contains(event.target as Node)) {
                close();
            }
        };
        document.addEventListener('mousedown', onEvent);
        document.addEventListener('keydown', onEvent);

        return () => {
            document.removeEventListener('mousedown', onEvent);
            document.removeEventListener('keydown', onEvent);
        };
    }, [ref, open, close]);
}

/** forge-navbar's collapse toggle: a panel, filled when open, dashed with an arrow when collapsed. */
function PanelIcon({ collapsed }: { collapsed: boolean }) {
    const name = collapsed ? 'panelClosed' : 'panelOpen';

    return (
        <svg className="pl-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={iconStroke[name]} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
            {iconPaths(name).map((d) => (
                <path key={d} d={d} />
            ))}
            {collapsed && <path d="M9 3v18" strokeDasharray="2 2" />}
        </svg>
    );
}

/** The logo of the brand: the image (and its dark variant), or the accent square with the initial. */
function BrandMark({ shell, name, size }: { shell: Shell; name: string; size: 'bar' | 'side' }) {
    if (shell.brand.logo) {
        return (
            <>
                <img className={`pl-logo pl-logo--${size}${shell.brand.logo_dark ? ' pl-logo--light' : ''}`} src={shell.brand.logo} alt={name} />
                {shell.brand.logo_dark && <img className={`pl-logo pl-logo--${size} pl-logo--dark`} src={shell.brand.logo_dark} alt={name} />}
            </>
        );
    }

    return (
        <span className={`pl-logo-mark pl-logo-mark--${size}`} aria-hidden="true">
            {firstLetter(name || 'P')}
        </span>
    );
}

/**
 * The shell around the screens, drawn as forge-dashboard-layout draws it: the
 * fixed navbar across the top (sidebar toggle, brand, company switcher,
 * light/dark, the administration gear, the bell, the account), the sidebar
 * with the server's menu (a rail when collapsed or on a tablet, opening on
 * hover; off-canvas on a phone), Ctrl+B, and the floating assistant.
 */
export function PtahLayout({ children, brand, topbar, api, locale, path, onNavigate, profileUrl, onLogout, themeModeUrl }: PtahLayoutProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const [shell, setShell] = useState<Shell>(EMPTY);
    const [me, setMe] = useState<Me | null>(null);
    const [collapsed, setCollapsed] = useState(readCollapsed);
    const [hovered, setHovered] = useState(false);
    const [peek, setPeek] = useState(false);
    const [open, setOpen] = useState(false);
    const md = useMedia(MEDIA_MD);
    const lg = useMedia(MEDIA_LG);
    const [dark, setDark] = useState(isDark);
    const jumpRef = useRef<HTMLInputElement>(null);
    const asideRef = useRef<HTMLElement>(null);
    const lang = locale ?? me?.locale;
    const t = words(lang);
    const current = path ?? (typeof window !== 'undefined' ? window.location.pathname : '/');
    const iconOnly = iconOnlyOf({ hovered, peek, open, collapsed, md, lg });
    const extras = shell.shell;
    const hasAdmin = extras.admin.length > 0 || extras.company_switcher;

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

        return () => {
            document.removeEventListener('keydown', onKey);
            stop();
        };
    }, []);

    // The tablet's temporary expansion ends with a click outside the sidebar.
    useDismiss(asideRef, peek, () => setPeek(false));

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

    // The rail cannot show a group or the search: expand first (desktop), or peek (tablet).
    const expand = () => {
        if (lg) {
            collapse(false);
        } else {
            setPeek(true);
        }
    };

    const logout = onLogout
        ? () => {
              forgetDrafts();
              onLogout();
          }
        : undefined;

    const name = shell.brand.name || (typeof brand === 'string' ? brand : '');

    return (
        <div className="pl-shell" data-collapsed={collapsed} data-icon-only={iconOnly} data-open={open}>
            <button type="button" className="pl-overlay" aria-label={t.closeMenu} tabIndex={open ? 0 : -1} onClick={() => setOpen(false)} />

            <aside ref={asideRef} className="pl-sidebar" aria-label={t.menu} data-hover={hovered} data-wide={!iconOnly} onMouseEnter={() => setHovered(true)} onMouseLeave={() => setHovered(false)}>
                {/* Under the navbar, as in forge-sidebar: the bar above shows the brand. */}
                <div className="pl-brand">
                    <BrandMark shell={shell} name={name} size="side" />
                    <span className="pl-brand-name">{brand ?? name}</span>
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
                        <button type="button" className="pl-item pl-logout" title={iconOnly ? t.logOut : undefined} aria-label={t.logOut} onClick={logout}>
                            <span className="pl-item-icon" aria-hidden="true">
                                <i className="bx bx-log-out" />
                            </span>
                            <span className="pl-item-label">{t.logOut}</span>
                        </button>
                    </div>
                )}
            </aside>

            <header className="pl-topbar">
                <div className="pl-topbar-start">
                    <button type="button" className="pl-icon-btn pl-hide-lg" aria-label={open ? t.closeMenu : t.openMenu} title={open ? t.closeMenu : t.openMenu} aria-expanded={open} onClick={() => setOpen(!open)}>
                        <Icon name="menu" size={20} className="pl-svg" />
                    </button>
                    <button
                        type="button"
                        className="pl-icon-btn pl-show-lg"
                        aria-label={collapsed ? t.expandMenu : t.collapseMenu}
                        title={`${collapsed ? t.expandMenu : t.collapseMenu} (Ctrl+B)`}
                        aria-pressed={collapsed}
                        onClick={() => collapse(!collapsed)}
                    >
                        <PanelIcon collapsed={collapsed} />
                    </button>
                    <a className="pl-topbar-brand" href="/">
                        <BrandMark shell={shell} name={name} size="bar" />
                        <span className="pl-topbar-name">{brand ?? name}</span>
                    </a>
                </div>

                {extras.company_switcher && (
                    // On a phone the switcher moves into the administration menu (when there is one).
                    <div className={`pl-topbar-center${hasAdmin ? ' pl-show-md' : ''}`}>
                        <PtahCompanySwitcher api={client} locale={lang} />
                    </div>
                )}

                <div className="pl-topbar-end">
                    {topbar}
                    <button type="button" className="pl-icon-btn" aria-label={dark ? t.lightMode : t.darkMode} title={dark ? t.lightMode : t.darkMode} aria-pressed={dark} onClick={() => toggleDark(themeModeUrl)}>
                        <Icon name={dark ? 'moon' : 'sun'} size={20} className="pl-svg" />
                    </button>
                    {me?.user && hasAdmin && (
                        <AdminMenu
                            t={t}
                            links={extras.admin}
                            current={current}
                            switcher={extras.company_switcher ? <PtahCompanySwitcher api={client} locale={lang} layout="stacked" /> : null}
                        />
                    )}
                    {extras.notifications ? (
                        <PtahNotificationBell api={client} locale={lang} onNavigate={onNavigate} />
                    ) : (
                        extras.static_bell && (
                            <button type="button" className="pl-icon-btn" aria-label={shellWords(lang).bellTitle}>
                                <Icon name="bell" size={20} className="pl-svg" />
                                <span className="pl-dot" aria-hidden="true" />
                            </button>
                        )
                    )}
                    {me?.user && <AccountMenu me={me} t={t} profileUrl={profileUrl} onLogout={logout} />}
                </div>
            </header>

            <div className="pl-main">
                <main className="pl-content">
                    <div className="pl-page">{children}</div>
                </main>
            </div>
            {extras.ai_chat && <PtahAiChat api={client} locale={lang} />}
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
                <MenuEntry key={`${item.label}-${index}`} item={item} nested={nested} {...rest} />
            ))}
        </ul>
    );
}

function MenuEntry({ item, path, iconOnly, onExpand, onFollow, routed, nested }: Omit<MenuProps, 'items'> & { item: MenuItem }) {
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
                    aria-expanded={open}
                    aria-haspopup="true"
                    aria-label={label}
                    title={iconOnly ? label : undefined}
                    onClick={() => {
                        // On the rail: expand the sidebar and open the group, in one gesture.
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
                    <Icon name="chevronDown" size={14} className="pl-caret" />
                </button>
                {open && !iconOnly && <MenuList items={children} path={path} iconOnly={iconOnly} onExpand={onExpand} onFollow={onFollow} routed={routed} nested />}
            </li>
        );
    }

    // A group with nothing inside: a label that goes nowhere, as in forge-sidebar.
    if (item.type === 'menuGroup') {
        return (
            <li>
                <div className="pl-item pl-item--empty" title={iconOnly ? label : undefined}>
                    {icon}
                    <span className="pl-item-label">{label}</span>
                </div>
            </li>
        );
    }

    const active = isActive(item, path);
    const external = Boolean(item.target && item.target !== '_self');

    return (
        <li>
            <a
                className={nested ? 'pl-item pl-item--child' : 'pl-item'}
                href={item.url ?? '#'}
                title={iconOnly ? label : undefined}
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

// ── "Jump to a screen" ──────────────────────────────────────────────────────

function MenuJump({ links, t, inputRef, onGo, onIcon }: { links: MenuLink[]; t: Words; inputRef: RefObject<HTMLInputElement | null>; onGo: (link: MenuLink) => void; onIcon: () => void }) {
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(-1);
    const results = useMemo(() => searchMenu(links, query), [links, query]);
    const reset = () => {
        setQuery('');
        setActive(-1);
    };

    return (
        <div className="pl-jump">
            {/* On the rail there is no room for a field: a magnifier that expands and focuses it. */}
            <button type="button" className="pl-jump-icon" aria-label={t.jumpTo} title={t.jumpTo} onClick={onIcon}>
                <i className="bx bx-search" aria-hidden="true" />
            </button>
            <div className="pl-jump-field">
                <i className="bx bx-search pl-jump-glyph" aria-hidden="true" />
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
                        className="pl-jump-clear"
                        aria-label={t.clear}
                        title={t.clear}
                        onClick={() => {
                            reset();
                            inputRef.current?.focus();
                        }}
                    >
                        <i className="bx bx-x" aria-hidden="true" />
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
                                    <span className="pl-jump-label">{link.label}</span>
                                    {trailOf(link).length > 0 && <span className="pl-jump-trail">{trailOf(link).join(' · ')}</span>}
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
                {query.trim() !== '' && results.length === 0 && (
                    <p className="pl-jump-list pl-jump-empty" role="status">
                        {t.nothingFound}
                    </p>
                )}
            </div>
        </div>
    );
}

// ── Administration (the navbar's gear) ──────────────────────────────────────

function AdminMenu({ t, links, current, switcher }: { t: Words; links: Shell['shell']['admin']; current: string; switcher: ReactNode }) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const close = useCallback(() => setOpen(false), []);
    useDismiss(ref, open, close);

    return (
        <div className="pl-dropdown-root" ref={ref}>
            <button type="button" className="pl-icon-btn" aria-haspopup="true" aria-expanded={open} aria-label={t.administration} title={t.administration} onClick={() => setOpen(!open)}>
                <Icon name="cog" size={20} className="pl-svg" />
            </button>
            {open && (
                <div className="pl-dropdown pl-dropdown--admin">
                    {/* On a phone the switcher of the bar is hidden; it lives here, stacked. */}
                    {switcher && <div className="pl-hide-md">{switcher}</div>}
                    {adminEntries(links, adminIcons).map((link) => (
                        <div key={link.url}>
                            {link.rule && <hr className="pl-rule" />}
                            <a className="pl-dropdown-item" href={link.url} aria-current={isActive({ label: link.label, url: link.url }, current) ? 'page' : undefined} onClick={close}>
                                {link.icon ? <Icon name={link.icon} size={16} className="pl-dropdown-icon" /> : <i className={`${link.boxicon} pl-dropdown-icon pl-dropdown-bx`} aria-hidden="true" />}
                                {link.label}
                            </a>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

// ── Account ─────────────────────────────────────────────────────────────────

function AccountMenu({ me, t, profileUrl, onLogout }: { me: Me; t: Words; profileUrl?: string; onLogout?: () => void }) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const close = useCallback(() => setOpen(false), []);
    const user = me.user!;
    useDismiss(ref, open, close);

    return (
        <div className="pl-dropdown-root" ref={ref}>
            <button type="button" className="pl-account-btn" aria-haspopup="true" aria-expanded={open} aria-label={`${t.account}: ${user.name}`} onClick={() => setOpen(!open)}>
                {user.photo_url ? (
                    <img className="pl-avatar pl-avatar--photo" src={user.photo_url} alt={user.name} />
                ) : (
                    <span className="pl-avatar" aria-hidden="true">
                        {firstLetter(user.name)}
                    </span>
                )}
                <span className="pl-account-name pl-show-lg">{user.name}</span>
                <Icon name="chevronDown" size={16} className="pl-account-chevron pl-show-lg" />
            </button>
            {open && (
                <div className="pl-dropdown pl-dropdown--account">
                    {profileUrl && (
                        <a className="pl-dropdown-item pl-dropdown-item--tight" href={profileUrl}>
                            <Icon name="user" size={16} />
                            {t.profile}
                        </a>
                    )}
                    {profileUrl && onLogout && <hr className="pl-rule" />}
                    {onLogout && (
                        <button type="button" className="pl-dropdown-item pl-dropdown-item--tight pl-logout" onClick={onLogout}>
                            <Icon name="logout" size={16} />
                            {t.logOut}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
