/**
 * ptah's sign-in and profile pages for Vue, over the auth API. The rules —
 * throttles, 2FA, "the current password for every sensitive change" — are the
 * server's; these pages only ask and show.
 */
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { PropType, Ref, VNode } from 'vue';
import { applyAppearance, firstLetter, PtahApi, words } from '../core';
import type { AuthConfig, AuthResult, Profile, SessionInfo, Words } from '../core';
import { APPEARANCE_AXES, confirmed, isMobilePlatform, profileWords } from '../core/profile-words';
import type { ProfileWords } from '../core/profile-words';
import { ForgeAlert, ForgeButton, ForgeCard, ForgeCheckbox, ForgeInput, ForgeTabs, forgeToast, ForgeToastHost } from './forge';
import { icon } from './icons';

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

/**
 * layouts/forge-auth: the brand above (the logo, or the accent square with
 * the initial, and the app's name), the card with the page's title and
 * subtitle, and the copyright line below.
 */
const card = (config: AuthConfig | null, t: Words, title: string, subtitle: string, children: (VNode | null)[]) => {
    const app = config?.app ?? '';

    return h('div', { class: 'pf pa-shell ptah-c-auth_bg' }, [
        h('div', { class: 'pa-brand-block' }, [
            config?.logo
                ? h('div', { class: 'pa-logo' }, [
                      h('img', { class: config.logo_dark ? 'pa-logo-img pa-logo--light' : 'pa-logo-img', src: config.logo, alt: app }),
                      config.logo_dark ? h('img', { class: 'pa-logo-img pa-logo--dark', src: config.logo_dark, alt: app }) : null,
                  ])
                : h('div', { class: 'pa-mark', 'aria-hidden': 'true' }, firstLetter(app || 'P')),
            h('h1', { class: 'pa-app' }, app),
        ]),
        h('main', { class: 'pa-card' }, [h('div', { class: 'pa-head' }, [h('h2', { class: 'pa-title' }, title), h('p', { class: 'pa-subtitle' }, subtitle)]), ...children]),
        h('p', { class: 'pa-copy' }, `© ${new Date().getFullYear()} ${app}. ${t.rightsReserved}`),
    ]);
};

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
            card(config.value, t.value, t.value.loginTitle, t.value.loginSubtitle, [
                alert('success', config.value?.status),
                alert('danger', action.error.value),
                submit(send, [
                    field(email, { label: t.value.email, type: 'email', name: 'email', autocomplete: 'username', autofocus: true, required: true, error: action.errors.value.email }),
                    field(password, { label: t.value.password, type: 'password', name: 'password', autocomplete: 'current-password', required: true, error: action.errors.value.password }),
                    h('div', { class: 'pa-row' }, [
                        config.value?.remember_me ? h(ForgeCheckbox, { label: t.value.loginRemember, modelValue: remember.value, 'onUpdate:modelValue': (value: boolean) => (remember.value = value) }) : h('span'),
                        config.value?.urls.forgot ? h('a', { class: 'pa-link', href: config.value.urls.forgot }, t.value.forgotPassword) : null,
                    ]),
                    h(ForgeButton, { type: 'submit', class: 'pa-submit', loading: action.busy.value }, () => (action.busy.value ? t.value.loginBusy : t.value.signIn)),
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
            card(config.value, t.value, t.value.twoFaTitle, recovery.value ? t.value.twoFaRecoverySubtitle : t.value.twoFaSubtitle, [
                alert('success', notice.value),
                alert('danger', action.error.value),
                submit(send, [
                    field(code, { label: recovery.value ? t.value.recoveryCode : t.value.twoFactorCode, name: 'code', inputmode: recovery.value ? 'text' : 'numeric', autocomplete: 'one-time-code', autofocus: true, required: true, error: action.errors.value.code }),
                    h(ForgeButton, { type: 'submit', class: 'pa-submit', loading: action.busy.value }, () => (action.busy.value ? t.value.twoFaBusy : t.value.verify)),
                ]),
                h('div', { class: 'pa-links' }, [
                    h('button', { type: 'button', class: 'pa-link', onClick: () => (recovery.value = !recovery.value) }, recovery.value ? t.value.twoFaUseAuthenticator : t.value.twoFaUseRecovery),
                    h('button', { type: 'button', class: 'pa-link pa-link--muted', onClick: () => void sendEmail() }, t.value.twoFaResend),
                    config.value?.urls.login ? h('a', { class: 'pa-link pa-link--muted', href: config.value.urls.login }, t.value.backToLogin) : null,
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
            card(config.value, t.value, t.value.forgotTitle, t.value.forgotSubtitle, [
                alert('success', action.result.value?.ok ? action.result.value.message : null),
                alert('danger', action.error.value),
                submit(send, [
                    field(email, { label: t.value.email, type: 'email', name: 'email', autocomplete: 'username', autofocus: true, required: true, error: action.errors.value.email }),
                    h(ForgeButton, { type: 'submit', class: 'pa-submit', loading: action.busy.value }, () => (action.busy.value ? t.value.forgotBusy : t.value.forgotButton)),
                ]),
                config.value?.urls.login ? h('p', { class: 'pa-foot' }, [`${t.value.forgotRemembered} `, h('a', { class: 'pa-link', href: config.value.urls.login }, t.value.backToLogin)]) : null,
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
            card(config.value, t.value, t.value.resetTitle, t.value.resetSubtitle, [
                alert('danger', action.error.value),
                submit(send, [
                    field(email, { label: t.value.email, type: 'email', name: 'email', autocomplete: 'username', required: true, error: action.errors.value.email }),
                    field(password, { label: t.value.newPassword, type: 'password', name: 'password', autocomplete: 'new-password', required: true, error: action.errors.value.password }),
                    field(confirmation, { label: t.value.resetConfirm, type: 'password', name: 'password_confirmation', autocomplete: 'new-password', required: true }),
                    h(ForgeButton, { type: 'submit', class: 'pa-submit', loading: action.busy.value }, () => (action.busy.value ? t.value.resetBusy : t.value.resetPassword)),
                ]),
            ]);
    },
});

// ── Profile ─────────────────────────────────────────────────────────────────

const tabProps = {
    client: { type: Object as PropType<PtahApi>, required: true as const },
    profile: { type: Object as PropType<Profile>, required: true as const },
    t: { type: Object as PropType<Words>, required: true as const },
    p: { type: Object as PropType<ProfileWords>, required: true as const },
    took: { type: Function as PropType<(answer: AuthResult) => void>, required: true as const },
};

const box = (children: (VNode | null | false)[]) => h(ForgeCard, null, () => children.filter(Boolean));

const ProfileTab = defineComponent({
    name: 'PtahProfileTab',
    props: tabProps,
    setup(props) {
        const action = useAction();
        const name = ref(props.profile.name);
        const email = ref(props.profile.email);
        const password = ref('');

        const save = async () => {
            const answer = await action.run(() => props.client.updateProfile(name.value, email.value, password.value));
            props.took(answer);
            if (answer.ok) {
                password.value = '';
            }
        };

        return () =>
            box([
                h('form', { class: 'pa-form pa-narrow', novalidate: true, onSubmit: (event: Event) => (event.preventDefault(), void save()) }, [
                    alert('danger', action.error.value),
                    field(name, { label: props.p.name, name: 'name', required: true, error: action.errors.value.name }),
                    field(email, { label: 'E-mail', type: 'email', name: 'email', required: true, error: action.errors.value.email }),
                    // Asked only when the e-mail changes, by the server; the field is always there, as on Livewire.
                    field(password, { label: props.p.emailPassword, type: 'password', name: 'email_password', autocomplete: 'current-password', error: action.errors.value.current_password }),
                    h('div', [h(ForgeButton, { type: 'submit', loading: action.busy.value }, () => props.p.saveProfile)]),
                ]),
            ]);
    },
});

const PasswordTab = defineComponent({
    name: 'PtahPasswordTab',
    props: tabProps,
    setup(props) {
        const action = useAction();
        const current = ref('');
        const next = ref('');
        const confirmation = ref('');

        const save = async () => {
            const answer = await action.run(() => props.client.updatePassword(current.value, next.value, confirmation.value));
            props.took(answer);
            if (answer.ok) {
                current.value = '';
                next.value = '';
                confirmation.value = '';
            }
        };

        return () =>
            box([
                alert('danger', action.error.value),
                h('form', { class: 'pa-form pa-narrow', novalidate: true, onSubmit: (event: Event) => (event.preventDefault(), void save()) }, [
                    field(current, { label: props.p.currentPassword, type: 'password', name: 'current_password', autocomplete: 'current-password', required: true, error: action.errors.value.current_password }),
                    field(next, { label: props.p.newPassword, type: 'password', name: 'password', autocomplete: 'new-password', required: true, error: action.errors.value.password }),
                    field(confirmation, { label: props.p.confirmPassword, type: 'password', name: 'password_confirmation', autocomplete: 'new-password', required: true }),
                    h('div', [h(ForgeButton, { type: 'submit', loading: action.busy.value }, () => props.p.changePassword)]),
                ]),
            ]);
    },
});

const TwoFactorTab = defineComponent({
    name: 'PtahTwoFactorTab',
    props: tabProps,
    setup(props) {
        const action = useAction();
        const password = ref('');
        const code = ref('');
        const setup = ref<{ secret: string | null; qr: string | null } | null>(null);
        const codes = ref<string[]>([]);

        const act = async (call: () => Promise<AuthResult>) => {
            const answer = await action.run(call);
            props.took(answer);
            if (answer.recovery_codes) {
                codes.value = answer.recovery_codes;
            }

            return answer;
        };

        const confirmTotp = () =>
            void act(() => props.client.confirmTotp(code.value)).then((answer) => {
                code.value = '';
                if (answer.ok) {
                    setup.value = null;
                    password.value = '';
                }
            });

        const disable = () => {
            if (!confirmed(props.p.disableTwoFactorConfirm)) {
                return;
            }
            void act(() => props.client.disableTwoFactor(password.value)).then((answer) => {
                if (answer.ok) {
                    codes.value = [];
                    setup.value = null;
                    password.value = '';
                }
            });
        };

        const methodHead = (name: string, tone: string, title: string, hint: string) =>
            h('div', { class: 'pa-method-head' }, [h('span', { class: 'pa-method-icon', 'data-tone': tone }, [icon(name, 20)]), h('div', [h('p', { class: 'pa-method-title' }, title), h('p', { class: 'pa-hint' }, hint)])]);

        return () => {
            const { p, t, profile } = props;
            const two = profile.two_factor;

            return box([
                alert('danger', action.error.value),
                // Every 2FA change asks for the current password.
                h('div', { class: 'pa-narrow pa-gap' }, [field(password, { label: p.twoFactorPassword, type: 'password', name: 'twofa_password', autocomplete: 'current-password', error: action.errors.value.current_password })]),
                !two.enabled
                    ? h('div', { class: 'pa-narrow' }, [
                          h('p', { class: 'pa-text pa-text--body pa-gap' }, p.twoFactorIntro),
                          h('div', { class: 'pa-method' }, [
                              methodHead('deviceMobile', 'blue', p.totpTitle, p.totpApps),
                              setup.value
                                  ? h('div', { class: 'pa-setup' }, [
                                        ...(setup.value.qr ? [h('p', { class: 'pa-text' }, p.scanQr), h('div', { class: 'pa-qr-box' }, [h('img', { class: 'pa-qr', src: setup.value.qr, alt: 'QR Code 2FA' })])] : []),
                                        setup.value.secret ? h('p', { class: 'pa-hint pa-center' }, [`${p.enterKey} `, h('code', { class: 'pa-chip' }, setup.value.secret)]) : null,
                                        h('form', { class: 'pa-inline', novalidate: true, onSubmit: (event: Event) => (event.preventDefault(), confirmTotp()) }, [
                                            field(code, { label: t.twoFactorCode, name: 'totp_code', placeholder: '000000', inputmode: 'numeric', autocomplete: 'one-time-code', error: action.errors.value.totp_code }),
                                            h(ForgeButton, { type: 'submit', loading: action.busy.value }, () => p.confirm),
                                        ]),
                                    ])
                                  : two.available
                                    ? h(
                                          ForgeButton,
                                          {
                                              color: 'light',
                                              size: 'sm',
                                              onClick: () =>
                                                  void act(() => props.client.startTotp(password.value)).then((answer) => {
                                                      if (answer.ok) {
                                                          setup.value = { secret: answer.secret ?? null, qr: answer.qr ?? null };
                                                      }
                                                  }),
                                          },
                                          () => p.setUp,
                                      )
                                    : null,
                          ]),
                          h('div', { class: 'pa-method' }, [
                              h('div', { class: 'pa-method-row' }, [
                                  methodHead('mail', 'sky', p.emailTitle, p.emailCodeHint.replace(':email', profile.email)),
                                  two.available ? h(ForgeButton, { color: 'light', size: 'sm', onClick: () => void act(() => props.client.enableEmailTwoFactor(password.value)) }, () => p.enable) : null,
                              ]),
                          ]),
                      ])
                    : h('div', { class: 'pa-narrow pa-stack' }, [
                          h(ForgeAlert, { type: 'success' }, () => `${p.twoFactorActive} (${two.type === 'totp' ? p.authenticator : 'E-mail'}).`),
                          codes.value.length > 0
                              ? h('div', [
                                    h('p', { class: 'pa-method-title' }, p.recoveryTitle),
                                    h('p', { class: 'pa-hint pa-gap-sm' }, p.recoveryHint),
                                    h(
                                        'ul',
                                        { class: 'pa-codes' },
                                        codes.value.map((item) => h('li', { key: item }, item)),
                                    ),
                                    two.available ? h('div', { class: 'pa-gap-top' }, [h(ForgeButton, { color: 'light', size: 'sm', onClick: () => void act(() => props.client.recoveryCodes(password.value, true)) }, () => p.regenerate)]) : null,
                                ])
                              : h('div', [h(ForgeButton, { color: 'light', size: 'sm', onClick: () => void act(() => props.client.recoveryCodes(password.value)) }, () => p.viewRecovery)]),
                          h('div', [h(ForgeButton, { color: 'danger', onClick: disable }, () => p.disableTwoFactor)]),
                      ]),
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
            const { p, t } = props;
            const list = sessions.value;

            return box([
                h('div', { class: 'pa-sessions-head' }, [
                    h('p', { class: 'pa-text' }, p.sessionsIntro),
                    h(ForgeButton, { color: 'danger', size: 'sm', onClick: () => confirmed(p.disconnectConfirm) && void act(() => props.client.revokeOtherSessions()) }, () => p.disconnectOthers),
                ]),
                list === null
                    ? h('p', { class: 'pa-empty', role: 'status' }, t.loading)
                    : list.length === 0
                      ? h('p', { class: 'pa-empty' }, p.noSessions)
                      : h(
                            'div',
                            { class: 'pa-sessions' },
                            list.map((session) =>
                                h('div', { key: session.id, class: 'pa-session' }, [
                                    h('div', { class: 'pa-session-main' }, [
                                        h('span', { class: 'pa-session-icon' }, [icon(isMobilePlatform(session.platform) ? 'deviceMobile' : 'viewAuto', 20)]),
                                        h('div', [
                                            h('p', { class: 'pa-session-browser' }, [session.browser || p.unknownBrowser, session.is_current ? h('span', { class: 'pa-session-current' }, `(${p.thisSession})`) : null]),
                                            h('p', { class: 'pa-hint' }, `${session.platform} · ${session.ip_address} · ${p.lastActivity} ${session.last_activity_human}`),
                                        ]),
                                    ]),
                                    !session.is_current ? h(ForgeButton, { color: 'light', size: 'sm', onClick: () => void act(() => props.client.revokeSession(session.id)) }, () => p.revoke) : null,
                                ]),
                            ),
                        ),
            ]);
        };
    },
});

const PhotoTab = defineComponent({
    name: 'PtahPhotoTab',
    props: tabProps,
    setup(props) {
        const action = useAction();
        const file = ref<File | null>(null);
        const preview = ref<string | null>(null);

        const pick = (chosen: File | null) => {
            if (preview.value) {
                URL.revokeObjectURL?.(preview.value);
            }
            file.value = chosen;
            preview.value = chosen && typeof URL.createObjectURL === 'function' ? URL.createObjectURL(chosen) : null;
        };
        onBeforeUnmount(() => pick(null));

        const save = async () => {
            if (!file.value) {
                return;
            }
            const chosen = file.value;
            const answer = await action.run(() => props.client.updatePhoto(chosen));
            props.took(answer);
            if (answer.ok) {
                pick(null);
            }
        };

        return () => {
            const { p, profile } = props;
            const shown = preview.value ?? profile.photo_url;

            return box([
                h('div', { class: 'pa-photo-tab' }, [
                    shown ? h('img', { class: 'pa-photo-img', src: shown, alt: profile.name }) : h('span', { class: 'pa-photo-img pa-photo-initial' }, firstLetter(profile.name)),
                    h('div', { class: 'pa-photo-form' }, [
                        h('label', { class: 'pa-label' }, [p.selectImage, h('input', { class: 'pa-file', type: 'file', accept: 'image/*', onChange: (event: Event) => pick((event.target as HTMLInputElement).files?.[0] ?? null) })]),
                        action.errors.value.photo ? h('p', { class: 'pa-field-error' }, action.errors.value.photo) : null,
                        h('div', { class: 'pa-photo-actions' }, [
                            h(ForgeButton, { class: 'pa-grow', loading: action.busy.value, disabled: !file.value, onClick: () => void save() }, () => (action.busy.value ? p.saving : p.savePhoto)),
                            profile.photo_url ? h(ForgeButton, { color: 'light', onClick: () => confirmed(p.removeConfirm) && void action.run(() => props.client.removePhoto()).then(props.took) }, () => p.remove) : null,
                        ]),
                    ]),
                ]),
            ]);
        };
    },
});

const AppearanceTab = defineComponent({
    name: 'PtahAppearanceTab',
    props: tabProps,
    setup(props) {
        const set = (changes: Record<string, string | boolean>) => void props.client.updateAppearance(changes).then(props.took);

        return () => {
            const appearance = props.profile.appearance;
            const labels = appearance.labels ?? {};
            const swatches = appearance.swatches ?? {};

            return box([
                h('div', { class: 'pa-appearance' }, [
                    ...APPEARANCE_AXES.map((axis) =>
                        h('div', { key: axis }, [
                            h('p', { class: 'pa-axis' }, labels._axis?.[axis] ?? axis),
                            h(
                                'div',
                                { class: 'pa-choices', role: 'group', 'aria-label': labels._axis?.[axis] ?? axis },
                                appearance.choices[axis].map((choice) =>
                                    // The chip previews its own option (.ptah-swatch[data-ptah-*]), as on Livewire.
                                    h('button', { key: choice, type: 'button', class: ['pa-choice ptah-swatch', appearance[axis] === choice ? 'ptah-c-btn_on' : 'ptah-c-btn'], [`data-ptah-${axis}`]: choice, 'aria-pressed': appearance[axis] === choice ? 'true' : 'false', onClick: () => set({ [axis]: choice }) }, [
                                        axis === 'accent' && swatches[choice] ? h('span', { class: 'pa-swatch', style: { backgroundColor: swatches[choice] }, 'aria-hidden': 'true' }) : null,
                                        labels[axis]?.[choice] ?? choice,
                                    ]),
                                ),
                            ),
                        ]),
                    ),
                    h('div', [h(ForgeButton, { color: 'light', flat: true, size: 'sm', onClick: () => set({ reset: true }) }, () => props.p.resetAppearance)]),
                ]),
            ]);
        };
    },
});

/**
 * livewire/auth/profile: the page header, then six tabs — Perfil, Senha,
 * Autenticação 2FA, Sessões, Foto, Aparência — each one a card.
 */
export const PtahProfile = defineComponent({
    name: 'PtahProfile',
    props: { api: pageProps.api, locale: pageProps.locale },
    setup(props) {
        const client = props.api ?? new PtahApi();
        const profile = ref<Profile | null>(null);
        const t = computed(() => words(props.locale));
        const p = computed(() => profileWords(props.locale));

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

            const current = profile.value;
            const part = { client, profile: current, t: t.value, p: p.value, took };
            const tabs = [
                { id: 'profile', label: p.value.tabProfile },
                { id: 'password', label: p.value.tabPassword },
                ...(current.two_factor.available || current.two_factor.enabled ? [{ id: 'two_factor', label: p.value.tabTwoFactor }] : []),
                ...(current.sessions_available ? [{ id: 'sessions', label: p.value.tabSessions }] : []),
                { id: 'photo', label: p.value.tabPhoto },
                { id: 'appearance', label: p.value.tabAppearance },
            ];

            return h('div', { class: 'pf pa-profile' }, [
                h(
                    ForgeTabs,
                    { tabs },
                    {
                        profile: () => h(ProfileTab, part),
                        password: () => h(PasswordTab, part),
                        two_factor: () => h(TwoFactorTab, part),
                        sessions: () => h(SessionsTab, part),
                        photo: () => h(PhotoTab, part),
                        appearance: () => h(AppearanceTab, part),
                    },
                ),
                h(ForgeToastHost),
            ]);
        };
    },
});
