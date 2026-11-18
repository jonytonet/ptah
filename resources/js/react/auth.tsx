/**
 * ptah's sign-in and profile pages for React, over the auth API. The rules —
 * throttles, 2FA, "the current password for every sensitive change" — are the
 * server's; these pages only ask and show.
 */
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { applyAppearance, PtahApi, words } from '../core';
import type { AuthConfig, AuthResult, Profile, SessionInfo, Words } from '../core';
import { ForgeAlert, ForgeAvatar, ForgeButton, ForgeCheckbox, ForgeInput, ForgeTabs, forgeToast, ForgeToastHost } from './forge';

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

function AuthCard({ brand, title, children }: { brand?: ReactNode; title: ReactNode; children: ReactNode }) {
    return (
        <div className="pf pa-shell">
            <main className="pa-card">
                <h1 className="pa-brand">{brand}</h1>
                <p className="pa-title">{title}</p>
                {children}
            </main>
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
        <AuthCard brand={config?.app} title={t.signIn}>
            <form className="pa-form" onSubmit={submit} noValidate>
                {config?.status && <ForgeAlert type="success">{config.status}</ForgeAlert>}
                {action.error && <ForgeAlert type="danger">{action.error}</ForgeAlert>}
                <ForgeInput label={t.email} type="email" name="email" autoComplete="username" required value={email} error={action.errors.email} onChange={setEmail} />
                <ForgeInput label={t.password} type="password" name="password" autoComplete="current-password" required value={password} error={action.errors.password} onChange={setPassword} />
                <div className="pa-row">
                    {config?.remember_me ? <ForgeCheckbox label={t.rememberMe} checked={remember} onChange={setRemember} /> : <span />}
                    {config?.urls.forgot && (
                        <a className="pa-link" href={config.urls.forgot}>
                            {t.forgotPassword}
                        </a>
                    )}
                </div>
                <ForgeButton type="submit" loading={action.busy}>
                    {action.busy ? t.signingIn : t.signIn}
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
        <AuthCard brand={config?.app} title={t.twoFactorTitle}>
            <form className="pa-form" onSubmit={submit} noValidate>
                {notice && <ForgeAlert type="success">{notice}</ForgeAlert>}
                {action.error && <ForgeAlert type="danger">{action.error}</ForgeAlert>}
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
                <ForgeButton type="submit" loading={action.busy}>
                    {t.verify}
                </ForgeButton>
                <div className="pa-row">
                    <button type="button" className="pa-link" onClick={() => setRecovery(!recovery)}>
                        {recovery ? t.useCode : t.useRecovery}
                    </button>
                    <button type="button" className="pa-link" onClick={() => void sendEmail()}>
                        {t.sendCodeByEmail}
                    </button>
                </div>
            </form>
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
        <AuthCard brand={config?.app} title={t.forgotPassword}>
            <form className="pa-form" onSubmit={submit} noValidate>
                {action.result?.ok && action.result.message && <ForgeAlert type="success">{action.result.message}</ForgeAlert>}
                {action.error && <ForgeAlert type="danger">{action.error}</ForgeAlert>}
                <ForgeInput label={t.email} type="email" name="email" autoComplete="username" required value={email} error={action.errors.email} onChange={setEmail} />
                <ForgeButton type="submit" loading={action.busy}>
                    {t.sendLink}
                </ForgeButton>
            </form>
            {config?.urls.login && (
                <p className="pa-foot">
                    <a className="pa-link" href={config.urls.login}>
                        {t.backToSignIn}
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
        <AuthCard brand={config?.app} title={t.resetPassword}>
            <form className="pa-form" onSubmit={submit} noValidate>
                {action.error && <ForgeAlert type="danger">{action.error}</ForgeAlert>}
                <ForgeInput label={t.email} type="email" name="email" autoComplete="username" required value={email} error={action.errors.email} onChange={setEmail} />
                <ForgeInput label={t.newPassword} type="password" name="password" autoComplete="new-password" required value={password} error={action.errors.password} onChange={setPassword} />
                <ForgeInput label={t.confirmPassword} type="password" name="password_confirmation" autoComplete="new-password" required value={confirmation} onChange={setConfirmation} />
                <ForgeButton type="submit" loading={action.busy}>
                    {t.resetPassword}
                </ForgeButton>
            </form>
        </AuthCard>
    );
}

// ── Profile ─────────────────────────────────────────────────────────────────

export function PtahProfile({ api, locale }: AuthPageProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const [profile, setProfile] = useState<Profile | null>(null);
    const t = words(locale);

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

    const part = { client, profile, t, took };
    const tabs = [
        { id: 'profile', label: t.profile, content: <ProfileTab {...part} /> },
        { id: 'security', label: t.security, content: <SecurityTab {...part} /> },
        ...(profile.sessions_available ? [{ id: 'sessions', label: t.sessions, content: <SessionsTab {...part} /> }] : []),
        { id: 'appearance', label: t.appearance, content: <AppearanceTab {...part} /> },
    ];

    return (
        <div className="pf">
            <ForgeTabs tabs={tabs} />
            <ForgeToastHost />
        </div>
    );
}

interface TabProps {
    client: PtahApi;
    profile: Profile;
    t: Words;
    took: (answer: AuthResult) => void;
}

function ProfileTab({ client, profile, t, took }: TabProps) {
    const action = useAction();
    const [name, setName] = useState(profile.name);
    const [email, setEmail] = useState(profile.email);
    const [password, setPassword] = useState('');
    // The server asks for the current password only when the e-mail changes.
    const emailChanged = email.trim().toLowerCase() !== profile.email.toLowerCase();

    const save = async (event: FormEvent) => {
        event.preventDefault();
        const answer = await action.run(() => client.updateProfile(name, email, password));
        took(answer);
        if (answer.ok) {
            setPassword('');
        }
    };

    return (
        <div className="pa-section">
            <div className="pa-photo">
                <ForgeAvatar src={profile.photo_url} text={profile.name} size="xl" />
                <input type="file" accept="image/*" aria-label={t.photo} onChange={(event) => event.target.files?.[0] && void action.run(() => client.updatePhoto(event.target.files![0])).then(took)} />
                {profile.photo_url && (
                    <ForgeButton flat color="danger" onClick={() => void action.run(() => client.removePhoto()).then(took)}>
                        {t.removePhoto}
                    </ForgeButton>
                )}
            </div>
            {action.errors.photo && <ForgeAlert type="danger">{action.errors.photo}</ForgeAlert>}
            <form className="pa-form" onSubmit={save} noValidate>
                {action.error && <ForgeAlert type="danger">{action.error}</ForgeAlert>}
                <ForgeInput label={t.name} name="name" required value={name} error={action.errors.name} onChange={setName} />
                <ForgeInput label={t.email} type="email" name="email" required value={email} error={action.errors.email} onChange={setEmail} />
                {emailChanged && <ForgeInput label={t.currentPassword} type="password" name="current_password" autoComplete="current-password" value={password} error={action.errors.current_password} onChange={setPassword} />}
                <div>
                    <ForgeButton type="submit" loading={action.busy}>
                        {t.save}
                    </ForgeButton>
                </div>
            </form>
        </div>
    );
}

function SecurityTab({ client, profile, t, took }: TabProps) {
    const password = useAction();
    const twoFactor = useAction();
    const [current, setCurrent] = useState('');
    const [next, setNext] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [confirm2fa, setConfirm2fa] = useState('');
    const [code, setCode] = useState('');
    const [setup, setSetup] = useState<{ secret: string | null; qr: string | null } | null>(null);
    const [codes, setCodes] = useState<string[]>([]);

    const savePassword = async (event: FormEvent) => {
        event.preventDefault();
        const answer = await password.run(() => client.updatePassword(current, next, confirmation));
        took(answer);
        if (answer.ok) {
            setCurrent('');
            setNext('');
            setConfirmation('');
        }
    };

    const act = async (call: () => Promise<AuthResult>) => {
        const answer = await twoFactor.run(call);
        took(answer);
        if (answer.recovery_codes) {
            setCodes(answer.recovery_codes);
        }

        return answer;
    };

    return (
        <div className="pa-section">
            <h3>{t.password}</h3>
            <form className="pa-form" onSubmit={savePassword} noValidate>
                {password.error && <ForgeAlert type="danger">{password.error}</ForgeAlert>}
                <ForgeInput label={t.currentPassword} type="password" name="current_password" autoComplete="current-password" value={current} error={password.errors.current_password} onChange={setCurrent} />
                <ForgeInput label={t.newPassword} type="password" name="password" autoComplete="new-password" value={next} error={password.errors.password} onChange={setNext} />
                <ForgeInput label={t.confirmPassword} type="password" name="password_confirmation" autoComplete="new-password" value={confirmation} onChange={setConfirmation} />
                <div>
                    <ForgeButton type="submit" loading={password.busy}>
                        {t.save}
                    </ForgeButton>
                </div>
            </form>

            {(profile.two_factor.available || profile.two_factor.enabled) && (
                <>
                    <h3>
                        {t.twoFactor}: {profile.two_factor.enabled ? t.twoFactorOn : t.twoFactorOff}
                    </h3>
                    {twoFactor.error && <ForgeAlert type="danger">{twoFactor.error}</ForgeAlert>}
                    {/* Every 2FA change asks for the current password. */}
                    <ForgeInput label={t.currentPassword} type="password" name="twofa_password" autoComplete="current-password" value={confirm2fa} error={twoFactor.errors.current_password} onChange={setConfirm2fa} />
                    <div className="pa-row" style={{ justifyContent: 'flex-start' }}>
                        {profile.two_factor.available && !profile.two_factor.enabled && (
                            <>
                                <ForgeButton
                                    onClick={() =>
                                        void act(() => client.startTotp(confirm2fa)).then((answer) => {
                                            if (answer.ok) {
                                                setSetup({ secret: answer.secret ?? null, qr: answer.qr ?? null });
                                            }
                                        })
                                    }
                                >
                                    {t.enableApp}
                                </ForgeButton>
                                <ForgeButton flat onClick={() => void act(() => client.enableEmailTwoFactor(confirm2fa))}>
                                    {t.enableEmail}
                                </ForgeButton>
                            </>
                        )}
                        {profile.two_factor.enabled && (
                            <>
                                <ForgeButton flat onClick={() => void act(() => client.recoveryCodes(confirm2fa))}>
                                    {t.showRecovery}
                                </ForgeButton>
                                {profile.two_factor.available && (
                                    <ForgeButton flat onClick={() => void act(() => client.recoveryCodes(confirm2fa, true))}>
                                        {t.regenerate}
                                    </ForgeButton>
                                )}
                                <ForgeButton
                                    color="danger"
                                    onClick={() =>
                                        void act(() => client.disableTwoFactor(confirm2fa)).then((answer) => {
                                            if (answer.ok) {
                                                setCodes([]);
                                                setSetup(null);
                                                setConfirm2fa('');
                                            }
                                        })
                                    }
                                >
                                    {t.disable}
                                </ForgeButton>
                            </>
                        )}
                    </div>

                    {setup && !profile.two_factor.enabled && (
                        <form
                            className="pa-form"
                            noValidate
                            onSubmit={(event) => {
                                event.preventDefault();
                                void act(() => client.confirmTotp(code)).then((answer) => {
                                    setCode('');
                                    if (answer.ok) {
                                        setSetup(null);
                                        setConfirm2fa('');
                                    }
                                });
                            }}
                        >
                            <p>{t.scanQr}</p>
                            {setup.qr && <img className="pa-qr" src={setup.qr} alt="QR code" />}
                            {setup.secret && (
                                <p>
                                    {t.secretKey}: <span className="pa-secret">{setup.secret}</span>
                                </p>
                            )}
                            <ForgeInput label={t.twoFactorCode} name="totp_code" inputMode="numeric" autoComplete="one-time-code" value={code} error={twoFactor.errors.totp_code} onChange={setCode} />
                            <div>
                                <ForgeButton type="submit" loading={twoFactor.busy}>
                                    {t.verify}
                                </ForgeButton>
                            </div>
                        </form>
                    )}

                    {codes.length > 0 && (
                        <>
                            <strong>{t.recoveryCodes}</strong>
                            <ul className="pa-codes">
                                {codes.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        </>
                    )}
                </>
            )}
        </div>
    );
}

function SessionsTab({ client, t, took }: TabProps) {
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

    if (sessions === null) {
        return <p role="status">{t.loading}</p>;
    }

    return (
        <div className="pa-section">
            {sessions.length === 0 && <p>{t.empty}</p>}
            {sessions.map((session) => (
                <div key={session.id} className="pa-session">
                    <span className="pa-session-text">
                        <strong>
                            {session.browser} · {session.platform}
                        </strong>
                        <span className="pa-session-meta">
                            {session.ip_address} · {session.is_current ? t.thisDevice : session.last_activity_human}
                        </span>
                    </span>
                    {!session.is_current && (
                        <ForgeButton flat color="danger" size="sm" onClick={() => void act(() => client.revokeSession(session.id))}>
                            {t.revoke}
                        </ForgeButton>
                    )}
                </div>
            ))}
            {sessions.some((session) => !session.is_current) && (
                <div>
                    <ForgeButton color="danger" onClick={() => void act(() => client.revokeOtherSessions())}>
                        {t.revokeOthers}
                    </ForgeButton>
                </div>
            )}
        </div>
    );
}

const AXES = ['light', 'dark', 'accent', 'text', 'density', 'fontsize'] as const;

function AppearanceTab({ client, profile, t, took }: TabProps) {
    const set = (changes: Record<string, string | boolean>) => void client.updateAppearance(changes).then(took);

    return (
        <div className="pa-section">
            {AXES.map((axis) => (
                <div key={axis}>
                    <strong>{t[axis]}</strong>
                    <div className="pa-choices" role="group" aria-label={t[axis]}>
                        {profile.appearance.choices[axis].map((choice) => (
                            <button key={choice} type="button" className="pa-choice" aria-pressed={profile.appearance[axis] === choice} onClick={() => set({ [axis]: choice })}>
                                {choice}
                            </button>
                        ))}
                    </div>
                </div>
            ))}
            <div>
                <ForgeButton flat onClick={() => set({ reset: true })}>
                    {t.restoreDefaults}
                </ForgeButton>
            </div>
        </div>
    );
}
