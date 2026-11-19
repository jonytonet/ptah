/**
 * ptah's sign-in and profile pages for React, over the auth API. The rules —
 * throttles, 2FA, "the current password for every sensitive change" — are the
 * server's; these pages only ask and show.
 */
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { applyAppearance, firstLetter, PtahApi, words } from '../core';
import type { AuthConfig, AuthResult, Profile, SessionInfo, Words } from '../core';
import { APPEARANCE_AXES, confirmed, isMobilePlatform, profileWords } from '../core/profile-words';
import type { ProfileWords } from '../core/profile-words';
import { ForgeAlert, ForgeButton, ForgeCard, ForgeCheckbox, ForgeInput, ForgeTabs, forgeToast, ForgeToastHost } from './forge';
import { Icon } from './icons';

export interface AuthPageProps {
    api?: PtahApi;
    locale?: string;
    /** Go to a URL without a full page load. Defaults to `window.location.assign`. */
    onNavigate?: (url: string) => void;
}

function useAuth({ api, locale, onNavigate }: AuthPageProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const [config, setConfig] = useState<AuthConfig | null>(null);
    const t = words(locale ?? config?.locale);
    const go = (url: string | null | undefined) => {
        if (url) {
            (onNavigate ?? ((target: string) => window.location.assign(target)))(url);
        }
    };

    useEffect(() => {
        let alive = true;
        void client
            .authConfig()
            .then((value) => alive && setConfig(value))
            .catch(() => undefined);

        return () => {
            alive = false;
        };
    }, [client]);

    return { client, config, t, go };
}

/** One submit at a time, with the answer of the last one. */
function useAction() {
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState<AuthResult | null>(null);
    const run = async (action: () => Promise<AuthResult>): Promise<AuthResult> => {
        setBusy(true);
        try {
            const answer = await action();
            setResult(answer);

            return answer;
        } catch (error) {
            const answer: AuthResult = { ok: false, error: error instanceof Error ? error.message : 'Error' };
            setResult(answer);

            return answer;
        } finally {
            setBusy(false);
        }
    };

    return { busy, result, run, errors: result?.errors ?? {}, error: result && !result.ok ? (result.error ?? null) : null };
}

/**
 * layouts/forge-auth: the brand above (the logo, or the accent square with
 * the initial, and the app's name), the card with the page's title and
 * subtitle, and the copyright line below.
 */
function AuthCard({ config, t, title, subtitle, children }: { config: AuthConfig | null; t: Words; title: ReactNode; subtitle: ReactNode; children: ReactNode }) {
    const app = config?.app ?? '';

    return (
        <div className="pf pa-shell ptah-c-auth_bg">
            <div className="pa-brand-block">
                {config?.logo ? (
                    <div className="pa-logo">
                        <img className={config.logo_dark ? 'pa-logo-img pa-logo--light' : 'pa-logo-img'} src={config.logo} alt={app} />
                        {config.logo_dark && <img className="pa-logo-img pa-logo--dark" src={config.logo_dark} alt={app} />}
                    </div>
                ) : (
                    <div className="pa-mark" aria-hidden="true">
                        {firstLetter(app || 'P')}
                    </div>
                )}
                <h1 className="pa-app">{app}</h1>
            </div>
            <main className="pa-card">
                <div className="pa-head">
                    <h2 className="pa-title">{title}</h2>
                    <p className="pa-subtitle">{subtitle}</p>
                </div>
                {children}
            </main>
            <p className="pa-copy">
                &copy; {new Date().getFullYear()} {app}. {t.rightsReserved}
            </p>
        </div>
    );
}

// ── Sign in ─────────────────────────────────────────────────────────────────

export function PtahLogin(props: AuthPageProps) {
    const { client, config, t, go } = useAuth(props);
    const action = useAction();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);

    // Already signed in: there is nothing to do on this page.
    useEffect(() => {
        if (config?.signed_in) {
            go(config.home);
        }
    }, [config]); // eslint-disable-line react-hooks/exhaustive-deps

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        const answer = await action.run(() => client.login(email, password, remember));

        if (answer.ok) {
            go(answer.two_factor ? config?.urls.two_factor : (answer.redirect ?? config?.home));
        } else {
            setPassword('');
        }
    };

    return (
        <AuthCard config={config} t={t} title={t.loginTitle} subtitle={t.loginSubtitle}>
            {config?.status && <ForgeAlert type="success">{config.status}</ForgeAlert>}
            {action.error && <ForgeAlert type="danger">{action.error}</ForgeAlert>}
            <form className="pa-form" onSubmit={submit} noValidate>
                <ForgeInput label={t.email} type="email" name="email" autoComplete="username" autoFocus required value={email} error={action.errors.email} onChange={setEmail} />
                <ForgeInput label={t.password} type="password" name="password" autoComplete="current-password" required value={password} error={action.errors.password} onChange={setPassword} />
                <div className="pa-row">
                    {config?.remember_me ? <ForgeCheckbox label={t.loginRemember} checked={remember} onChange={setRemember} /> : <span />}
                    {config?.urls.forgot && (
                        <a className="pa-link" href={config.urls.forgot}>
                            {t.forgotPassword}
                        </a>
                    )}
                </div>
                <ForgeButton type="submit" className="pa-submit" loading={action.busy}>
                    {action.busy ? t.loginBusy : t.signIn}
                </ForgeButton>
            </form>
        </AuthCard>
    );
}

// ── Two-factor challenge ────────────────────────────────────────────────────

export function PtahTwoFactor(props: AuthPageProps) {
    const { client, config, t, go } = useAuth(props);
    const action = useAction();
    const [code, setCode] = useState('');
    const [recovery, setRecovery] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);

    // No sign-in waiting for a code: back to the start.
    useEffect(() => {
        if (config && !config.two_factor_pending) {
            go(config.signed_in ? config.home : config.urls.login);
        }
    }, [config]); // eslint-disable-line react-hooks/exhaustive-deps

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        const answer = await action.run(() => client.twoFactor(code, recovery));

        if (answer.ok) {
            go(answer.redirect ?? config?.home);
        } else {
            setCode('');
        }
    };

    const sendEmail = async () => {
        const answer = await action.run(() => client.twoFactorEmail());
        setNotice(answer.ok ? (answer.message ?? null) : null);
    };

    return (
        <AuthCard config={config} t={t} title={t.twoFaTitle} subtitle={recovery ? t.twoFaRecoverySubtitle : t.twoFaSubtitle}>
            {notice && <ForgeAlert type="success">{notice}</ForgeAlert>}
            {action.error && <ForgeAlert type="danger">{action.error}</ForgeAlert>}
            <form className="pa-form" onSubmit={submit} noValidate>
                <ForgeInput
                    label={recovery ? t.recoveryCode : t.twoFactorCode}
                    name="code"
                    inputMode={recovery ? 'text' : 'numeric'}
                    autoComplete="one-time-code"
                    autoFocus
                    required
                    value={code}
                    error={action.errors.code}
                    onChange={setCode}
                />
                <ForgeButton type="submit" className="pa-submit" loading={action.busy}>
                    {action.busy ? t.twoFaBusy : t.verify}
                </ForgeButton>
            </form>
            <div className="pa-links">
                <button type="button" className="pa-link" onClick={() => setRecovery(!recovery)}>
                    {recovery ? t.twoFaUseAuthenticator : t.twoFaUseRecovery}
                </button>
                <button type="button" className="pa-link pa-link--muted" onClick={() => void sendEmail()}>
                    {t.twoFaResend}
                </button>
                {config?.urls.login && (
                    <a className="pa-link pa-link--muted" href={config.urls.login}>
                        {t.backToLogin}
                    </a>
                )}
            </div>
        </AuthCard>
    );
}

// ── Forgot / reset password ─────────────────────────────────────────────────

export function PtahForgotPassword(props: AuthPageProps) {
    const { client, config, t } = useAuth(props);
    const action = useAction();
    const [email, setEmail] = useState('');

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        const answer = await action.run(() => client.forgotPassword(email));
        if (answer.ok) {
            setEmail('');
        }
    };

    return (
        <AuthCard config={config} t={t} title={t.forgotTitle} subtitle={t.forgotSubtitle}>
            {action.result?.ok && action.result.message && <ForgeAlert type="success">{action.result.message}</ForgeAlert>}
            {action.error && <ForgeAlert type="danger">{action.error}</ForgeAlert>}
            <form className="pa-form" onSubmit={submit} noValidate>
                <ForgeInput label={t.email} type="email" name="email" autoComplete="username" autoFocus required value={email} error={action.errors.email} onChange={setEmail} />
                <ForgeButton type="submit" className="pa-submit" loading={action.busy}>
                    {action.busy ? t.forgotBusy : t.forgotButton}
                </ForgeButton>
            </form>
            {config?.urls.login && (
                <p className="pa-foot">
                    {t.forgotRemembered}{' '}
                    <a className="pa-link" href={config.urls.login}>
                        {t.backToLogin}
                    </a>
                </p>
            )}
        </AuthCard>
    );
}

export function PtahResetPassword({ token, email: initialEmail = '', ...props }: AuthPageProps & { token: string; email?: string }) {
    const { client, config, t, go } = useAuth(props);
    const action = useAction();
    const [email, setEmail] = useState(initialEmail);
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        const answer = await action.run(() => client.resetPassword(token, email, password, confirmation));
        if (answer.ok) {
            go(answer.redirect ?? config?.urls.login);
        }
    };

    return (
        <AuthCard config={config} t={t} title={t.resetTitle} subtitle={t.resetSubtitle}>
            {action.error && <ForgeAlert type="danger">{action.error}</ForgeAlert>}
            <form className="pa-form" onSubmit={submit} noValidate>
                <ForgeInput label={t.email} type="email" name="email" autoComplete="username" required value={email} error={action.errors.email} onChange={setEmail} />
                <ForgeInput label={t.newPassword} type="password" name="password" autoComplete="new-password" required value={password} error={action.errors.password} onChange={setPassword} />
                <ForgeInput label={t.resetConfirm} type="password" name="password_confirmation" autoComplete="new-password" required value={confirmation} onChange={setConfirmation} />
                <ForgeButton type="submit" className="pa-submit" loading={action.busy}>
                    {action.busy ? t.resetBusy : t.resetPassword}
                </ForgeButton>
            </form>
        </AuthCard>
    );
}

// ── Profile ─────────────────────────────────────────────────────────────────

/**
 * livewire/auth/profile: the page header, then six tabs — Perfil, Senha,
 * Autenticação 2FA, Sessões, Foto, Aparência — each one a card.
 */
export function PtahProfile({ api, locale }: AuthPageProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const [profile, setProfile] = useState<Profile | null>(null);
    const t = words(locale);
    const p = profileWords(locale);

    useEffect(() => {
        let alive = true;
        void client
            .profile()
            .then((value) => alive && setProfile(value))
            .catch(() => undefined);

        return () => {
            alive = false;
        };
    }, [client]);

    // Every action answers the profile as it now is, and the toasts to show.
    const took = (answer: AuthResult) => {
        if (answer.profile) {
            setProfile(answer.profile);
            // The presets live on <html>: a new choice paints the whole page at once.
            applyAppearance(answer.profile.appearance);
        }
        (answer.messages ?? []).forEach((message) => forgeToast(message.text, message.level));
    };

    if (!profile) {
        return (
            <div className="pf pc-state" role="status">
                {t.loading}
            </div>
        );
    }

    const part = { client, profile, t, p, took };
    const tabs = [
        { id: 'profile', label: p.tabProfile, content: <ProfileTab {...part} /> },
        { id: 'password', label: p.tabPassword, content: <PasswordTab {...part} /> },
        ...(profile.two_factor.available || profile.two_factor.enabled ? [{ id: 'two_factor', label: p.tabTwoFactor, content: <TwoFactorTab {...part} /> }] : []),
        ...(profile.sessions_available ? [{ id: 'sessions', label: p.tabSessions, content: <SessionsTab {...part} /> }] : []),
        { id: 'photo', label: p.tabPhoto, content: <PhotoTab {...part} /> },
        { id: 'appearance', label: p.tabAppearance, content: <AppearanceTab {...part} /> },
    ];

    return (
        <div className="pf pa-profile">
            <ForgeTabs tabs={tabs} />
            <ForgeToastHost />
        </div>
    );
}

interface TabProps {
    client: PtahApi;
    profile: Profile;
    t: Words;
    p: ProfileWords;
    took: (answer: AuthResult) => void;
}

function ProfileTab({ client, profile, p, took }: TabProps) {
    const action = useAction();
    const [name, setName] = useState(profile.name);
    const [email, setEmail] = useState(profile.email);
    const [password, setPassword] = useState('');

    const save = async (event: FormEvent) => {
        event.preventDefault();
        const answer = await action.run(() => client.updateProfile(name, email, password));
        took(answer);
        if (answer.ok) {
            setPassword('');
        }
    };

    return (
        <ForgeCard>
            <form className="pa-form pa-narrow" onSubmit={save} noValidate>
                {action.error && <ForgeAlert type="danger">{action.error}</ForgeAlert>}
                <ForgeInput label={p.name} name="name" required value={name} error={action.errors.name} onChange={setName} />
                <ForgeInput label="E-mail" type="email" name="email" required value={email} error={action.errors.email} onChange={setEmail} />
                {/* Asked only when the e-mail changes, by the server; the field is always there, as on Livewire. */}
                <ForgeInput label={p.emailPassword} type="password" name="email_password" autoComplete="current-password" value={password} error={action.errors.current_password} onChange={setPassword} />
                <div>
                    <ForgeButton type="submit" loading={action.busy}>
                        {p.saveProfile}
                    </ForgeButton>
                </div>
            </form>
        </ForgeCard>
    );
}

function PasswordTab({ client, p, took }: TabProps) {
    const action = useAction();
    const [current, setCurrent] = useState('');
    const [next, setNext] = useState('');
    const [confirmation, setConfirmation] = useState('');

    const save = async (event: FormEvent) => {
        event.preventDefault();
        const answer = await action.run(() => client.updatePassword(current, next, confirmation));
        took(answer);
        if (answer.ok) {
            setCurrent('');
            setNext('');
            setConfirmation('');
        }
    };

    return (
        <ForgeCard>
            {action.error && <ForgeAlert type="danger">{action.error}</ForgeAlert>}
            <form className="pa-form pa-narrow" onSubmit={save} noValidate>
                <ForgeInput label={p.currentPassword} type="password" name="current_password" autoComplete="current-password" required value={current} error={action.errors.current_password} onChange={setCurrent} />
                <ForgeInput label={p.newPassword} type="password" name="password" autoComplete="new-password" required value={next} error={action.errors.password} onChange={setNext} />
                <ForgeInput label={p.confirmPassword} type="password" name="password_confirmation" autoComplete="new-password" required value={confirmation} onChange={setConfirmation} />
                <div>
                    <ForgeButton type="submit" loading={action.busy}>
                        {p.changePassword}
                    </ForgeButton>
                </div>
            </form>
        </ForgeCard>
    );
}

function TwoFactorTab({ client, profile, t, p, took }: TabProps) {
    const action = useAction();
    const [password, setPassword] = useState('');
    const [code, setCode] = useState('');
    const [setup, setSetup] = useState<{ secret: string | null; qr: string | null } | null>(null);
    const [codes, setCodes] = useState<string[]>([]);

    const act = async (call: () => Promise<AuthResult>) => {
        const answer = await action.run(call);
        took(answer);
        if (answer.recovery_codes) {
            setCodes(answer.recovery_codes);
        }

        return answer;
    };

    const confirmTotp = (event: FormEvent) => {
        event.preventDefault();
        void act(() => client.confirmTotp(code)).then((answer) => {
            setCode('');
            if (answer.ok) {
                setSetup(null);
                setPassword('');
            }
        });
    };

    return (
        <ForgeCard>
            {action.error && <ForgeAlert type="danger">{action.error}</ForgeAlert>}
            {/* Every 2FA change asks for the current password. */}
            <div className="pa-narrow pa-gap">
                <ForgeInput label={p.twoFactorPassword} type="password" name="twofa_password" autoComplete="current-password" value={password} error={action.errors.current_password} onChange={setPassword} />
            </div>

            {!profile.two_factor.enabled ? (
                <div className="pa-narrow">
                    <p className="pa-text pa-text--body pa-gap">{p.twoFactorIntro}</p>

                    <div className="pa-method">
                        <div className="pa-method-head">
                            <span className="pa-method-icon" data-tone="blue">
                                <Icon name="deviceMobile" size={20} />
                            </span>
                            <div>
                                <p className="pa-method-title">{p.totpTitle}</p>
                                <p className="pa-hint">{p.totpApps}</p>
                            </div>
                        </div>
                        {setup ? (
                            <div className="pa-setup">
                                {setup.qr && (
                                    <>
                                        <p className="pa-text">{p.scanQr}</p>
                                        <div className="pa-qr-box">
                                            <img className="pa-qr" src={setup.qr} alt="QR Code 2FA" />
                                        </div>
                                    </>
                                )}
                                {setup.secret && (
                                    <p className="pa-hint pa-center">
                                        {p.enterKey} <code className="pa-chip">{setup.secret}</code>
                                    </p>
                                )}
                                <form className="pa-inline" onSubmit={confirmTotp} noValidate>
                                    <ForgeInput label={t.twoFactorCode} name="totp_code" placeholder="000000" inputMode="numeric" autoComplete="one-time-code" value={code} error={action.errors.totp_code} onChange={setCode} />
                                    <ForgeButton type="submit" loading={action.busy}>
                                        {p.confirm}
                                    </ForgeButton>
                                </form>
                            </div>
                        ) : (
                            profile.two_factor.available && (
                                <ForgeButton
                                    color="light"
                                    size="sm"
                                    onClick={() =>
                                        void act(() => client.startTotp(password)).then((answer) => {
                                            if (answer.ok) {
                                                setSetup({ secret: answer.secret ?? null, qr: answer.qr ?? null });
                                            }
                                        })
                                    }
                                >
                                    {p.setUp}
                                </ForgeButton>
                            )
                        )}
                    </div>

                    <div className="pa-method">
                        <div className="pa-method-row">
                            <div className="pa-method-head">
                                <span className="pa-method-icon" data-tone="sky">
                                    <Icon name="mail" size={20} />
                                </span>
                                <div>
                                    <p className="pa-method-title">{p.emailTitle}</p>
                                    <p className="pa-hint">{p.emailCodeHint.replace(':email', profile.email)}</p>
                                </div>
                            </div>
                            {profile.two_factor.available && (
                                <ForgeButton color="light" size="sm" onClick={() => void act(() => client.enableEmailTwoFactor(password))}>
                                    {p.enable}
                                </ForgeButton>
                            )}
                        </div>
                    </div>
                </div>
            ) : (
                <div className="pa-narrow pa-stack">
                    <ForgeAlert type="success">
                        {p.twoFactorActive} ({profile.two_factor.type === 'totp' ? p.authenticator : 'E-mail'}).
                    </ForgeAlert>
                    {codes.length > 0 ? (
                        <div>
                            <p className="pa-method-title">{p.recoveryTitle}</p>
                            <p className="pa-hint pa-gap-sm">{p.recoveryHint}</p>
                            <ul className="pa-codes">
                                {codes.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                            {profile.two_factor.available && (
                                <div className="pa-gap-top">
                                    <ForgeButton color="light" size="sm" onClick={() => void act(() => client.recoveryCodes(password, true))}>
                                        {p.regenerate}
                                    </ForgeButton>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div>
                            <ForgeButton color="light" size="sm" onClick={() => void act(() => client.recoveryCodes(password))}>
                                {p.viewRecovery}
                            </ForgeButton>
                        </div>
                    )}
                    <div>
                        <ForgeButton
                            color="danger"
                            onClick={() => {
                                if (!confirmed(p.disableTwoFactorConfirm)) {
                                    return;
                                }
                                void act(() => client.disableTwoFactor(password)).then((answer) => {
                                    if (answer.ok) {
                                        setCodes([]);
                                        setSetup(null);
                                        setPassword('');
                                    }
                                });
                            }}
                        >
                            {p.disableTwoFactor}
                        </ForgeButton>
                    </div>
                </div>
            )}
        </ForgeCard>
    );
}

function SessionsTab({ client, t, p, took }: TabProps) {
    const [sessions, setSessions] = useState<SessionInfo[] | null>(null);

    useEffect(() => {
        let alive = true;
        void client
            .sessions()
            .then((list) => alive && setSessions(list))
            .catch(() => alive && setSessions([]));

        return () => {
            alive = false;
        };
    }, [client]);

    const act = async (call: () => Promise<AuthResult>) => {
        const answer = await call();
        took(answer);
        if (answer.sessions) {
            setSessions(answer.sessions);
        }
    };

    return (
        <ForgeCard>
            <div className="pa-sessions-head">
                <p className="pa-text">{p.sessionsIntro}</p>
                <ForgeButton color="danger" size="sm" onClick={() => confirmed(p.disconnectConfirm) && void act(() => client.revokeOtherSessions())}>
                    {p.disconnectOthers}
                </ForgeButton>
            </div>
            {sessions === null ? (
                <p className="pa-empty" role="status">
                    {t.loading}
                </p>
            ) : sessions.length === 0 ? (
                <p className="pa-empty">{p.noSessions}</p>
            ) : (
                <div className="pa-sessions">
                    {sessions.map((session) => (
                        <div key={session.id} className="pa-session">
                            <div className="pa-session-main">
                                <span className="pa-session-icon">
                                    <Icon name={isMobilePlatform(session.platform) ? 'deviceMobile' : 'viewAuto'} size={20} />
                                </span>
                                <div>
                                    <p className="pa-session-browser">
                                        {session.browser || p.unknownBrowser}
                                        {session.is_current && <span className="pa-session-current">({p.thisSession})</span>}
                                    </p>
                                    <p className="pa-hint">
                                        {session.platform} · {session.ip_address} · {p.lastActivity} {session.last_activity_human}
                                    </p>
                                </div>
                            </div>
                            {!session.is_current && (
                                <ForgeButton color="light" size="sm" onClick={() => void act(() => client.revokeSession(session.id))}>
                                    {p.revoke}
                                </ForgeButton>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </ForgeCard>
    );
}

function PhotoTab({ client, profile, p, took }: TabProps) {
    const action = useAction();
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);

    useEffect(() => () => void (preview && URL.revokeObjectURL?.(preview)), [preview]);

    const pick = (chosen: File | null) => {
        setFile(chosen);
        setPreview(chosen && typeof URL.createObjectURL === 'function' ? URL.createObjectURL(chosen) : null);
    };

    const save = async () => {
        if (!file) {
            return;
        }
        const answer = await action.run(() => client.updatePhoto(file));
        took(answer);
        if (answer.ok) {
            pick(null);
        }
    };

    const shown = preview ?? profile.photo_url;

    return (
        <ForgeCard>
            <div className="pa-photo-tab">
                {shown ? <img className="pa-photo-img" src={shown} alt={profile.name} /> : <span className="pa-photo-img pa-photo-initial">{firstLetter(profile.name)}</span>}
                <div className="pa-photo-form">
                    <label className="pa-label">
                        {p.selectImage}
                        <input className="pa-file" type="file" accept="image/*" onChange={(event) => pick(event.target.files?.[0] ?? null)} />
                    </label>
                    {action.errors.photo && <p className="pa-field-error">{action.errors.photo}</p>}
                    <div className="pa-photo-actions">
                        <ForgeButton className="pa-grow" loading={action.busy} disabled={!file} onClick={() => void save()}>
                            {action.busy ? p.saving : p.savePhoto}
                        </ForgeButton>
                        {profile.photo_url && (
                            <ForgeButton color="light" onClick={() => confirmed(p.removeConfirm) && void action.run(() => client.removePhoto()).then(took)}>
                                {p.remove}
                            </ForgeButton>
                        )}
                    </div>
                </div>
            </div>
        </ForgeCard>
    );
}

function AppearanceTab({ client, profile, p, took }: TabProps) {
    const set = (changes: Record<string, string | boolean>) => void client.updateAppearance(changes).then(took);
    const labels = profile.appearance.labels ?? {};
    const swatches = profile.appearance.swatches ?? {};

    return (
        <ForgeCard>
            <div className="pa-appearance">
                {APPEARANCE_AXES.map((axis) => (
                    <div key={axis}>
                        <p className="pa-axis">{labels._axis?.[axis] ?? axis}</p>
                        <div className="pa-choices" role="group" aria-label={labels._axis?.[axis] ?? axis}>
                            {profile.appearance.choices[axis].map((choice) => (
                                // The chip previews its own option (.ptah-swatch[data-ptah-*]), as on Livewire.
                                <button
                                    key={choice}
                                    type="button"
                                    className={`pa-choice ptah-swatch ${profile.appearance[axis] === choice ? 'ptah-c-btn_on' : 'ptah-c-btn'}`}
                                    {...{ [`data-ptah-${axis}`]: choice }}
                                    aria-pressed={profile.appearance[axis] === choice}
                                    onClick={() => set({ [axis]: choice })}
                                >
                                    {axis === 'accent' && swatches[choice] && <span className="pa-swatch" style={{ backgroundColor: swatches[choice] }} aria-hidden="true" />}
                                    {labels[axis]?.[choice] ?? choice}
                                </button>
                            ))}
                        </div>
                    </div>
                ))}
                <div>
                    <ForgeButton color="light" flat size="sm" onClick={() => set({ reset: true })}>
                        {p.resetAppearance}
                    </ForgeButton>
                </div>
            </div>
        </ForgeCard>
    );
}
