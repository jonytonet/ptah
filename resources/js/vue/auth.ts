/**
 * ptah's sign-in and profile pages for Vue, over the auth API. The rules —
 * throttles, 2FA, "the current password for every sensitive change" — are the
 * server's; these pages only ask and show.
 */
import { computed, defineComponent, h, onMounted, ref, watch } from 'vue';
import type { PropType, Ref, VNode } from 'vue';
import { applyAppearance, PtahApi, words } from '../core';
import type { AuthConfig, AuthResult, Profile, SessionInfo, Words } from '../core';
import { ForgeAlert, ForgeAvatar, ForgeButton, ForgeCheckbox, ForgeInput, ForgeTabs, forgeToast, ForgeToastHost } from './forge';

const pageProps = {
    api: { type: Object as PropType<PtahApi>, default: undefined },
    locale: { type: String, default: undefined },
    /** Go to a URL without a full page load. Defaults to `window.location.assign`. */
    onNavigate: { type: Function as PropType<(url: string) => void>, default: undefined },
};

function useAuth(props: { api?: PtahApi; locale?: string; onNavigate?: (url: string) => void }) {
    const client = props.api ?? new PtahApi();
    const config = ref<AuthConfig | null>(null);
    const t = computed(() => words(props.locale ?? config.value?.locale));
    const go = (url: string | null | undefined) => {
        if (url) {
            (props.onNavigate ?? ((target: string) => window.location.assign(target)))(url);
        }
    };

    onMounted(() => {
        void client
            .authConfig()
            .then((value) => {
                config.value = value;
            })
            .catch(() => undefined);
    });

    return { client, config, t, go };
}

/** One submit at a time, with the answer of the last one. */
function useAction() {
    const busy = ref(false);
    const result = ref<AuthResult | null>(null);
    const run = async (action: () => Promise<AuthResult>): Promise<AuthResult> => {
        busy.value = true;
        try {
            result.value = await action();
        } catch (error) {
            result.value = { ok: false, error: error instanceof Error ? error.message : 'Error' };
        } finally {
            busy.value = false;
        }

        return result.value as AuthResult;
    };
    const errors = computed(() => result.value?.errors ?? {});
    const error = computed(() => (result.value && !result.value.ok ? (result.value.error ?? null) : null));

    return { busy, result, run, errors, error };
}

const card = (brand: string | undefined, title: string, children: (VNode | null)[]) =>
    h('div', { class: 'pf pa-shell' }, [h('main', { class: 'pa-card' }, [h('h1', { class: 'pa-brand' }, brand), h('p', { class: 'pa-title' }, title), ...children])]);

const alert = (type: string, text: string | null | undefined) => (text ? h(ForgeAlert, { type }, () => text) : null);

/** A ForgeInput bound to a ref. */
const field = (model: Ref<string>, attrs: Record<string, unknown>) => h(ForgeInput, { ...attrs, modelValue: model.value, 'onUpdate:modelValue': (value: string) => (model.value = value) });

const submit = (handler: () => void, children: (VNode | null)[]) =>
    h(
        'form',
        {
            class: 'pa-form',
            novalidate: true,
            onSubmit: (event: Event) => {
                event.preventDefault();
                handler();
            },
        },
        children,
    );

// ── Sign in ─────────────────────────────────────────────────────────────────

export const PtahLogin = defineComponent({
    name: 'PtahLogin',
    props: pageProps,
    setup(props) {
        const { client, config, t, go } = useAuth(props);
        const action = useAction();
        const email = ref('');
        const password = ref('');
        const remember = ref(false);

        // Already signed in: there is nothing to do on this page.
        watch(config, (value) => {
            if (value?.signed_in) {
                go(value.home);
            }
        });

        const send = async () => {
            const answer = await action.run(() => client.login(email.value, password.value, remember.value));
            if (answer.ok) {
                go(answer.two_factor ? config.value?.urls.two_factor : (answer.redirect ?? config.value?.home));
            } else {
                password.value = '';
            }
        };

        return () =>
            card(config.value?.app, t.value.signIn, [
                submit(send, [
                    alert('success', config.value?.status),
                    alert('danger', action.error.value),
                    field(email, { label: t.value.email, type: 'email', name: 'email', autocomplete: 'username', required: true, error: action.errors.value.email }),
                    field(password, { label: t.value.password, type: 'password', name: 'password', autocomplete: 'current-password', required: true, error: action.errors.value.password }),
                    h('div', { class: 'pa-row' }, [
                        config.value?.remember_me ? h(ForgeCheckbox, { label: t.value.rememberMe, modelValue: remember.value, 'onUpdate:modelValue': (value: boolean) => (remember.value = value) }) : h('span'),
                        config.value?.urls.forgot ? h('a', { class: 'pa-link', href: config.value.urls.forgot }, t.value.forgotPassword) : null,
                    ]),
                    h(ForgeButton, { type: 'submit', loading: action.busy.value }, () => (action.busy.value ? t.value.signingIn : t.value.signIn)),
                ]),
            ]);
    },
});

// ── Two-factor challenge ────────────────────────────────────────────────────

export const PtahTwoFactor = defineComponent({
    name: 'PtahTwoFactor',
    props: pageProps,
    setup(props) {
        const { client, config, t, go } = useAuth(props);
        const action = useAction();
        const code = ref('');
        const recovery = ref(false);
        const notice = ref<string | null>(null);

        // No sign-in waiting for a code: back to the start.
        watch(config, (value) => {
            if (value && !value.two_factor_pending) {
                go(value.signed_in ? value.home : value.urls.login);
            }
        });

        const send = async () => {
            const answer = await action.run(() => client.twoFactor(code.value, recovery.value));
            if (answer.ok) {
                go(answer.redirect ?? config.value?.home);
            } else {
                code.value = '';
            }
        };
        const sendEmail = async () => {
            const answer = await action.run(() => client.twoFactorEmail());
            notice.value = answer.ok ? (answer.message ?? null) : null;
        };

        return () =>
            card(config.value?.app, t.value.twoFactorTitle, [
                submit(send, [
                    alert('success', notice.value),
                    alert('danger', action.error.value),
                    field(code, { label: recovery.value ? t.value.recoveryCode : t.value.twoFactorCode, name: 'code', inputmode: recovery.value ? 'text' : 'numeric', autocomplete: 'one-time-code', required: true, error: action.errors.value.code }),
                    h(ForgeButton, { type: 'submit', loading: action.busy.value }, () => t.value.verify),
                    h('div', { class: 'pa-row' }, [
                        h('button', { type: 'button', class: 'pa-link', onClick: () => (recovery.value = !recovery.value) }, recovery.value ? t.value.useCode : t.value.useRecovery),
                        h('button', { type: 'button', class: 'pa-link', onClick: () => void sendEmail() }, t.value.sendCodeByEmail),
                    ]),
                ]),
            ]);
    },
});

// ── Forgot / reset password ─────────────────────────────────────────────────

export const PtahForgotPassword = defineComponent({
    name: 'PtahForgotPassword',
    props: pageProps,
    setup(props) {
        const { client, config, t } = useAuth(props);
        const action = useAction();
        const email = ref('');

        const send = async () => {
            const answer = await action.run(() => client.forgotPassword(email.value));
            if (answer.ok) {
                email.value = '';
            }
        };

        return () =>
            card(config.value?.app, t.value.forgotPassword, [
                submit(send, [
                    alert('success', action.result.value?.ok ? action.result.value.message : null),
                    alert('danger', action.error.value),
                    field(email, { label: t.value.email, type: 'email', name: 'email', autocomplete: 'username', required: true, error: action.errors.value.email }),
                    h(ForgeButton, { type: 'submit', loading: action.busy.value }, () => t.value.sendLink),
                ]),
                config.value?.urls.login ? h('p', { class: 'pa-foot' }, [h('a', { class: 'pa-link', href: config.value.urls.login }, t.value.backToSignIn)]) : null,
            ]);
    },
});

export const PtahResetPassword = defineComponent({
    name: 'PtahResetPassword',
    props: { ...pageProps, token: { type: String, required: true }, email: { type: String, default: '' } },
    setup(props) {
        const { client, config, t, go } = useAuth(props);
        const action = useAction();
        const email = ref(props.email);
        const password = ref('');
        const confirmation = ref('');

        const send = async () => {
            const answer = await action.run(() => client.resetPassword(props.token, email.value, password.value, confirmation.value));
            if (answer.ok) {
                go(answer.redirect ?? config.value?.urls.login);
            }
        };

        return () =>
            card(config.value?.app, t.value.resetPassword, [
                submit(send, [
                    alert('danger', action.error.value),
                    field(email, { label: t.value.email, type: 'email', name: 'email', autocomplete: 'username', required: true, error: action.errors.value.email }),
                    field(password, { label: t.value.newPassword, type: 'password', name: 'password', autocomplete: 'new-password', required: true, error: action.errors.value.password }),
                    field(confirmation, { label: t.value.confirmPassword, type: 'password', name: 'password_confirmation', autocomplete: 'new-password', required: true }),
                    h(ForgeButton, { type: 'submit', loading: action.busy.value }, () => t.value.resetPassword),
                ]),
            ]);
    },
});

// ── Profile ─────────────────────────────────────────────────────────────────

const tabProps = {
    client: { type: Object as PropType<PtahApi>, required: true as const },
    profile: { type: Object as PropType<Profile>, required: true as const },
    t: { type: Object as PropType<Words>, required: true as const },
    took: { type: Function as PropType<(answer: AuthResult) => void>, required: true as const },
};

const ProfileTab = defineComponent({
    name: 'PtahProfileTab',
    props: tabProps,
    setup(props) {
        const action = useAction();
        const name = ref(props.profile.name);
        const email = ref(props.profile.email);
        const password = ref('');
        // The server asks for the current password only when the e-mail changes.
        const emailChanged = computed(() => email.value.trim().toLowerCase() !== props.profile.email.toLowerCase());

        const save = async () => {
            const answer = await action.run(() => props.client.updateProfile(name.value, email.value, password.value));
            props.took(answer);
            if (answer.ok) {
                password.value = '';
            }
        };
        const upload = (event: Event) => {
            const file = (event.target as HTMLInputElement).files?.[0];
            if (file) {
                void action.run(() => props.client.updatePhoto(file)).then(props.took);
            }
        };

        return () =>
            h('div', { class: 'pa-section' }, [
                h('div', { class: 'pa-photo' }, [
                    h(ForgeAvatar, { src: props.profile.photo_url ?? undefined, text: props.profile.name, size: 'xl' }),
                    h('input', { type: 'file', accept: 'image/*', 'aria-label': props.t.photo, onChange: upload }),
                    props.profile.photo_url ? h(ForgeButton, { flat: true, color: 'danger', onClick: () => void action.run(() => props.client.removePhoto()).then(props.took) }, () => props.t.removePhoto) : null,
                ]),
                alert('danger', action.errors.value.photo),
                submit(save, [
                    alert('danger', action.error.value),
                    field(name, { label: props.t.name, name: 'name', required: true, error: action.errors.value.name }),
                    field(email, { label: props.t.email, type: 'email', name: 'email', required: true, error: action.errors.value.email }),
                    emailChanged.value ? field(password, { label: props.t.currentPassword, type: 'password', name: 'current_password', autocomplete: 'current-password', error: action.errors.value.current_password }) : null,
                    h('div', [h(ForgeButton, { type: 'submit', loading: action.busy.value }, () => props.t.save)]),
                ]),
            ]);
    },
});

const SecurityTab = defineComponent({
    name: 'PtahSecurityTab',
    props: tabProps,
    setup(props) {
        const password = useAction();
        const twoFactor = useAction();
        const current = ref('');
        const next = ref('');
        const confirmation = ref('');
        const confirm2fa = ref('');
        const code = ref('');
        const setup = ref<{ secret: string | null; qr: string | null } | null>(null);
        const codes = ref<string[]>([]);

        const savePassword = async () => {
            const answer = await password.run(() => props.client.updatePassword(current.value, next.value, confirmation.value));
            props.took(answer);
            if (answer.ok) {
                current.value = '';
                next.value = '';
                confirmation.value = '';
            }
        };

        const act = async (call: () => Promise<AuthResult>) => {
            const answer = await twoFactor.run(call);
            props.took(answer);
            if (answer.recovery_codes) {
                codes.value = answer.recovery_codes;
            }

            return answer;
        };

        const startTotp = () =>
            void act(() => props.client.startTotp(confirm2fa.value)).then((answer) => {
                if (answer.ok) {
                    setup.value = { secret: answer.secret ?? null, qr: answer.qr ?? null };
                }
            });
        const confirmTotp = () =>
            void act(() => props.client.confirmTotp(code.value)).then((answer) => {
                code.value = '';
                if (answer.ok) {
                    setup.value = null;
                    confirm2fa.value = '';
                }
            });
        const disable = () =>
            void act(() => props.client.disableTwoFactor(confirm2fa.value)).then((answer) => {
                if (answer.ok) {
                    codes.value = [];
                    setup.value = null;
                    confirm2fa.value = '';
                }
            });

        return () => {
            const { t, profile, client } = props;
            const two = profile.two_factor;

            return h('div', { class: 'pa-section' }, [
                h('h3', t.password),
                submit(savePassword, [
                    alert('danger', password.error.value),
                    field(current, { label: t.currentPassword, type: 'password', name: 'current_password', autocomplete: 'current-password', error: password.errors.value.current_password }),
                    field(next, { label: t.newPassword, type: 'password', name: 'password', autocomplete: 'new-password', error: password.errors.value.password }),
                    field(confirmation, { label: t.confirmPassword, type: 'password', name: 'password_confirmation', autocomplete: 'new-password' }),
                    h('div', [h(ForgeButton, { type: 'submit', loading: password.busy.value }, () => t.save)]),
                ]),
                ...(two.available || two.enabled
                    ? [
                          h('h3', `${t.twoFactor}: ${two.enabled ? t.twoFactorOn : t.twoFactorOff}`),
                          alert('danger', twoFactor.error.value),
                          // Every 2FA change asks for the current password.
                          field(confirm2fa, { label: t.currentPassword, type: 'password', name: 'twofa_password', autocomplete: 'current-password', error: twoFactor.errors.value.current_password }),
                          h('div', { class: 'pa-row', style: { justifyContent: 'flex-start' } }, [
                              ...(two.available && !two.enabled
                                  ? [h(ForgeButton, { onClick: startTotp }, () => t.enableApp), h(ForgeButton, { flat: true, onClick: () => void act(() => client.enableEmailTwoFactor(confirm2fa.value)) }, () => t.enableEmail)]
                                  : []),
                              ...(two.enabled
                                  ? [
                                        h(ForgeButton, { flat: true, onClick: () => void act(() => client.recoveryCodes(confirm2fa.value)) }, () => t.showRecovery),
                                        two.available ? h(ForgeButton, { flat: true, onClick: () => void act(() => client.recoveryCodes(confirm2fa.value, true)) }, () => t.regenerate) : null,
                                        h(ForgeButton, { color: 'danger', onClick: disable }, () => t.disable),
                                    ]
                                  : []),
                          ]),
                          setup.value && !two.enabled
                              ? submit(confirmTotp, [
                                    h('p', t.scanQr),
                                    setup.value.qr ? h('img', { class: 'pa-qr', src: setup.value.qr, alt: 'QR code' }) : null,
                                    setup.value.secret ? h('p', [`${t.secretKey}: `, h('span', { class: 'pa-secret' }, setup.value.secret)]) : null,
                                    field(code, { label: t.twoFactorCode, name: 'totp_code', inputmode: 'numeric', autocomplete: 'one-time-code', error: twoFactor.errors.value.totp_code }),
                                    h('div', [h(ForgeButton, { type: 'submit', loading: twoFactor.busy.value }, () => t.verify)]),
                                ])
                              : null,
                          ...(codes.value.length > 0
                              ? [
                                    h('strong', t.recoveryCodes),
                                    h(
                                        'ul',
                                        { class: 'pa-codes' },
                                        codes.value.map((item) => h('li', { key: item }, item)),
                                    ),
                                ]
                              : []),
                      ]
                    : []),
            ]);
        };
    },
});

const SessionsTab = defineComponent({
    name: 'PtahSessionsTab',
    props: tabProps,
    setup(props) {
        const sessions = ref<SessionInfo[] | null>(null);

        onMounted(() => {
            void props.client
                .sessions()
                .then((list) => {
                    sessions.value = list;
                })
                .catch(() => {
                    sessions.value = [];
                });
        });

        const act = async (call: () => Promise<AuthResult>) => {
            const answer = await call();
            props.took(answer);
            if (answer.sessions) {
                sessions.value = answer.sessions;
            }
        };

        return () => {
            const { t, client } = props;
            if (sessions.value === null) {
                return h('p', { role: 'status' }, t.loading);
            }

            return h('div', { class: 'pa-section' }, [
                sessions.value.length === 0 ? h('p', t.empty) : null,
                ...sessions.value.map((session) =>
                    h('div', { key: session.id, class: 'pa-session' }, [
                        h('span', { class: 'pa-session-text' }, [
                            h('strong', `${session.browser} · ${session.platform}`),
                            h('span', { class: 'pa-session-meta' }, `${session.ip_address} · ${session.is_current ? t.thisDevice : session.last_activity_human}`),
                        ]),
                        session.is_current ? null : h(ForgeButton, { flat: true, color: 'danger', size: 'sm', onClick: () => void act(() => client.revokeSession(session.id)) }, () => t.revoke),
                    ]),
                ),
                sessions.value.some((session) => !session.is_current) ? h('div', [h(ForgeButton, { color: 'danger', onClick: () => void act(() => client.revokeOtherSessions()) }, () => t.revokeOthers)]) : null,
            ]);
        };
    },
});

const AXES = ['light', 'dark', 'accent', 'text', 'density', 'fontsize'] as const;

const AppearanceTab = defineComponent({
    name: 'PtahAppearanceTab',
    props: tabProps,
    setup(props) {
        const set = (changes: Record<string, string | boolean>) => void props.client.updateAppearance(changes).then(props.took);

        return () =>
            h('div', { class: 'pa-section' }, [
                ...AXES.map((axis) =>
                    h('div', { key: axis }, [
                        h('strong', props.t[axis]),
                        h(
                            'div',
                            { class: 'pa-choices', role: 'group', 'aria-label': props.t[axis] },
                            props.profile.appearance.choices[axis].map((choice) =>
                                h('button', { key: choice, type: 'button', class: 'pa-choice', 'aria-pressed': props.profile.appearance[axis] === choice, onClick: () => set({ [axis]: choice }) }, choice),
                            ),
                        ),
                    ]),
                ),
                h('div', [h(ForgeButton, { flat: true, onClick: () => set({ reset: true }) }, () => props.t.restoreDefaults)]),
            ]);
    },
});

export const PtahProfile = defineComponent({
    name: 'PtahProfile',
    props: { api: pageProps.api, locale: pageProps.locale },
    setup(props) {
        const client = props.api ?? new PtahApi();
        const profile = ref<Profile | null>(null);
        const t = computed(() => words(props.locale));

        onMounted(() => {
            void client
                .profile()
                .then((value) => {
                    profile.value = value;
                })
                .catch(() => undefined);
        });

        // Every action answers the profile as it now is, and the toasts to show.
        const took = (answer: AuthResult) => {
            if (answer.profile) {
                profile.value = answer.profile;
                // The presets live on <html>: a new choice paints the whole page at once.
                applyAppearance(answer.profile.appearance);
            }
            (answer.messages ?? []).forEach((message) => forgeToast(message.text, message.level));
        };

        return () => {
            if (!profile.value) {
                return h('div', { class: 'pf pc-state', role: 'status' }, t.value.loading);
            }

            const part = { client, profile: profile.value, t: t.value, took };
            const tabs = [
                { id: 'profile', label: t.value.profile },
                { id: 'security', label: t.value.security },
                ...(profile.value.sessions_available ? [{ id: 'sessions', label: t.value.sessions }] : []),
                { id: 'appearance', label: t.value.appearance },
            ];

            return h('div', { class: 'pf' }, [
                h(ForgeTabs, { tabs }, { profile: () => h(ProfileTab, part), security: () => h(SecurityTab, part), sessions: () => h(SessionsTab, part), appearance: () => h(AppearanceTab, part) }),
                h(ForgeToastHost),
            ]);
        };
    },
});
