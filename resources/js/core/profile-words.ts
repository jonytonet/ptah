/**
 * The words of the profile page — copies of ptah::ui.profile_* (the Livewire
 * ProfilePage), so the React and Vue tabs read exactly as the Blade ones.
 */

const en = {
    tabProfile: 'Profile',
    tabPassword: 'Password',
    tabTwoFactor: '2FA Authentication',
    tabSessions: 'Sessions',
    tabPhoto: 'Photo',
    tabAppearance: 'Appearance',
    name: 'Name',
    saveProfile: 'Save profile',
    currentPassword: 'Current password',
    emailPassword: 'Current password (required to change the e-mail)',
    twoFactorPassword: 'Current password (required for any 2FA change)',
    newPassword: 'New password',
    confirmPassword: 'Confirm new password',
    changePassword: 'Change password',
    twoFactorIntro: 'Two-factor authentication adds an extra layer of security to your account. Choose your preferred method:',
    totpTitle: 'Authenticator App (TOTP)',
    totpApps: 'Google Authenticator, Authy, Bitwarden…',
    scanQr: 'Scan the QR code with your authenticator app:',
    enterKey: 'Or enter the key manually:',
    confirm: 'Confirm',
    setUp: 'Set up',
    emailTitle: 'E-mail',
    emailCodeHint: 'Code sent to :email',
    enable: 'Enable',
    twoFactorActive: '2FA is active',
    authenticator: 'Authenticator App',
    recoveryTitle: 'Recovery codes',
    recoveryHint: 'Store these codes in a safe place each one can only be used once.',
    regenerate: 'Regenerate codes',
    viewRecovery: 'View recovery codes',
    disableTwoFactor: 'Disable 2FA',
    disableTwoFactorConfirm: 'Disable 2FA?',
    sessionsIntro: 'Devices with active sessions on your account.',
    disconnectOthers: 'Disconnect others',
    disconnectConfirm: 'Disconnect all other devices?',
    noSessions: 'No sessions found.',
    thisSession: 'this session',
    unknownBrowser: 'Unknown browser',
    lastActivity: 'last activity',
    revoke: 'Revoke',
    selectImage: 'Select image',
    savePhoto: 'Save photo',
    saving: 'Saving...',
    remove: 'Remove',
    removeConfirm: 'Remove profile photo?',
    resetAppearance: 'Restore defaults',
};

export type ProfileWords = typeof en;

const ptBR: ProfileWords = {
    tabProfile: 'Perfil',
    tabPassword: 'Senha',
    tabTwoFactor: 'Autenticação 2FA',
    tabSessions: 'Sessões',
    tabPhoto: 'Foto',
    tabAppearance: 'Aparência',
    name: 'Nome',
    saveProfile: 'Salvar perfil',
    currentPassword: 'Senha atual',
    emailPassword: 'Senha atual (necessária para trocar o e-mail)',
    twoFactorPassword: 'Senha atual (necessária para qualquer mudança no 2FA)',
    newPassword: 'Nova senha',
    confirmPassword: 'Confirmar nova senha',
    changePassword: 'Alterar senha',
    twoFactorIntro: 'A autenticação em dois fatores adiciona uma camada extra de segurança à sua conta. Escolha seu método preferido:',
    totpTitle: 'Authenticator App (TOTP)',
    totpApps: 'Google Authenticator, Authy, Bitwarden…',
    scanQr: 'Escaneie o QR code com seu aplicativo autenticador:',
    enterKey: 'Ou insira a chave manualmente:',
    confirm: 'Confirmar',
    setUp: 'Configurar',
    emailTitle: 'E-mail',
    emailCodeHint: 'Código enviado para :email',
    enable: 'Ativar',
    twoFactorActive: '2FA está ativo',
    authenticator: 'Aplicativo Autenticador',
    recoveryTitle: 'Códigos de recuperação',
    recoveryHint: 'Guarde esses códigos em local seguro cada um pode ser usado apenas uma vez.',
    regenerate: 'Regenerar códigos',
    viewRecovery: 'Ver códigos de recuperação',
    disableTwoFactor: 'Desativar 2FA',
    disableTwoFactorConfirm: 'Desativar 2FA?',
    sessionsIntro: 'Dispositivos com sessões ativas na sua conta.',
    disconnectOthers: 'Desconectar outros',
    disconnectConfirm: 'Desconectar todos os outros dispositivos?',
    noSessions: 'Nenhuma sessão encontrada.',
    thisSession: 'esta sessão',
    unknownBrowser: 'Navegador desconhecido',
    lastActivity: 'última atividade',
    revoke: 'Revogar',
    selectImage: 'Selecionar imagem',
    savePhoto: 'Salvar foto',
    saving: 'Salvando...',
    remove: 'Remover',
    removeConfirm: 'Remover foto de perfil?',
    resetAppearance: 'Voltar ao original',
};

/** The profile words for a locale; English when unknown. */
export function profileWords(locale?: string | null): ProfileWords {
    return (locale ?? '').toLowerCase().startsWith('pt') ? ptBR : en;
}

/** The axes of the "Aparência" tab, in the Livewire order. */
export const APPEARANCE_AXES = ['light', 'dark', 'accent', 'text', 'density', 'fontsize'] as const;

/** A browser confirm, as `wire:confirm` asks (no dialog available: go on). */
export function confirmed(message: string): boolean {
    return typeof window === 'undefined' || typeof window.confirm !== 'function' ? true : window.confirm(message);
}

/** Is this session on a phone? (the device icon of the Livewire sessions tab) */
export function isMobilePlatform(platform: string | null | undefined): boolean {
    const value = platform ?? '';

    return value.toLowerCase().includes('mobile') || ['Android', 'iPhone', 'iPad'].includes(value);
}
