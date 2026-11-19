/**
 * The BaseCrud's visual config editor for React — the gear of a screen.
 *
 *   <PtahCrudConfigButton crud={crud} locale={locale} />   in the <PtahCrud> toolbar
 *   <PtahCrudConfig screen="products" onClose={…} />        the editor itself
 *
 * The rules are the server's (the Livewire component CrudConfig, run by the
 * crud-config API): who may configure, the defaults of a new column, the
 * JOIN rules, the merge with the stored config and its validation. Mirrors
 * vue/admin/crud-config/index.ts.
 */
import { useEffect, useMemo, useState, useSyncExternalStore } from 'react';
import type { ComponentType, CSSProperties } from 'react';
import { PtahApi } from '../../../core';
import type { CrudController } from '../../../core';
import { canConfigure, ConfigEditor, crudConfigWords, footerSummary, mainTabs } from '../../../core/admin/crud-config';
import type { BuildContext, SaveResult } from '../../../core/admin/crud-config';
import { ForgeAlert, ForgeButton, ForgeModal, ForgeSpinner, ForgeTabs, forgeToast } from '../../forge';
import { mutedStyle, monoStyle, tagStyle } from './nodes';
import { ActionsTab, ColumnsTab, FiltersTab, GeneralTab, HooksTab, JoinsTab, NotificationsTab, PermissionsTab, PreviewModal, StylesTab, TabHeader } from './tabs';
import type { TabProps } from './tabs';

export interface PtahCrudConfigProps {
    api?: PtahApi;
    /** The registered screen key (CrudScreen::register) — never a model. */
    screen: string;
    /** `pt_BR`, `en`… Defaults to the page's `<html lang>`. */
    locale?: string;
    /** After a successful save, with the server's answer. */
    onSaved?: (result: SaveResult) => void;
    onClose?: () => void;
}

const footStyle: CSSProperties = { display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 8, width: '100%' };

const TABS: Record<string, ComponentType<TabProps>> = {
    cols: ColumnsTab,
    actions: ActionsTab,
    filters: FiltersTab,
    styles: StylesTab,
    joins: JoinsTab,
    general: GeneralTab,
    permissions: PermissionsTab,
    hooks: HooksTab,
    notifications: NotificationsTab,
};

/** The editor, as a full-screen dialog (the Livewire modal). */
export function PtahCrudConfig({ api, screen, locale, onSaved, onClose }: PtahCrudConfigProps) {
    const client = useMemo(() => api ?? new PtahApi(), [api]);
    const t = useMemo(() => crudConfigWords(locale), [locale]);
    const editor = useMemo(() => new ConfigEditor(client, screen, t), [client, screen]); // eslint-disable-line react-hooks/exhaustive-deps
    const state = useSyncExternalStore(editor.subscribe, editor.get, editor.get);

    useEffect(() => editor.setWords(t), [editor, t]);
    useEffect(() => {
        void editor.load();

        return () => editor.dispose();
    }, [editor]);

    // Escape on the confirmation or the preview closes only that one.
    const close = () => {
        if (!state.confirm && !state.preview) {
            onClose?.();
        }
    };

    const save = async () => {
        const result = await editor.save();
        if (result) {
            forgeToast(result.message || t.savedFallback, 'success');
            onSaved?.(result);
            onClose?.();
        }
    };

    const ready = state.status === 'ready' && state.document && state.payload;
    const ctx: BuildContext | null = ready ? { t, choices: state.payload!.choices, options: state.payload!.options, document: state.document!, hints: state.hints } : null;

    return (
        <>
            <ForgeModal
                open
                onClose={close}
                size="full"
                title={t.title}
                subtitle={t.cfg_btn_title}
                footer={
                    ready ? (
                        <div style={footStyle}>
                            <span style={{ ...mutedStyle, marginRight: 'auto' }}>{footerSummary(state.document!, t)}</span>
                            <ForgeButton flat size="sm" loading={state.busy === 'preview'} disabled={state.busy !== null && state.busy !== 'preview'} onClick={() => void editor.openPreview()}>
                                {t.cfg_preview_btn}
                            </ForgeButton>
                            <ForgeButton flat color="light" size="sm" onClick={() => onClose?.()}>
                                {t.cfg_footer_cancel}
                            </ForgeButton>
                            <ForgeButton size="sm" loading={state.busy === 'save'} disabled={state.busy !== null && state.busy !== 'save'} onClick={() => void save()}>
                                {t.cfg_footer_save}
                            </ForgeButton>
                        </div>
                    ) : undefined
                }
            >
                {state.status === 'loading' && (
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <ForgeSpinner size="sm" label={t.loading} /> {t.loading}
                    </div>
                )}
                {(state.status === 'error' || state.status === 'forbidden') && (
                    <div style={{ display: 'grid', gap: 12 }}>
                        <ForgeAlert color="danger">{state.error}</ForgeAlert>
                        {state.status === 'error' && (
                            <div>
                                <ForgeButton size="sm" onClick={() => void editor.load()}>
                                    {t.retry}
                                </ForgeButton>
                            </div>
                        )}
                    </div>
                )}
                {ready && ctx && (
                    <div style={{ display: 'grid', gap: 16 }}>
                        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 8 }}>
                            <code style={{ ...monoStyle, color: 'var(--pc-text-muted)' }}>{state.payload!.model}</code>
                            <span style={tagStyle}>{state.payload!.route !== '' ? `/${state.payload!.route}` : t.globalScope}</span>
                        </div>
                        {state.error && <ForgeAlert color="danger">{state.error}</ForgeAlert>}
                        {state.saveError && <ForgeAlert color="danger">{state.saveError}</ForgeAlert>}
                        <ForgeTabs
                            onChange={(id) => id === 'notifications' && editor.loadAudience()}
                            tabs={mainTabs(state.document!, t).map((tab) => {
                                const Tab = TABS[tab.id];

                                return {
                                    id: tab.id,
                                    label: tab.count === null ? tab.label : `${tab.label} (${tab.count})`,
                                    content: (
                                        <div style={{ display: 'grid', gap: 16 }}>
                                            <TabHeader title={tab.title} description={tab.description} />
                                            <Tab editor={editor} state={state} t={t} ctx={ctx} />
                                        </div>
                                    ),
                                };
                            })}
                        />
                    </div>
                )}
            </ForgeModal>

            {state.confirm && (
                <ForgeModal
                    open
                    onClose={() => editor.confirmNo()}
                    size="sm"
                    title={t.confirmTitle}
                    footer={
                        <>
                            <ForgeButton flat color="light" size="sm" onClick={() => editor.confirmNo()}>
                                {t.cfg_footer_cancel}
                            </ForgeButton>
                            <ForgeButton color="danger" size="sm" onClick={() => void editor.confirmYes()}>
                                {t.confirmYes}
                            </ForgeButton>
                        </>
                    }
                >
                    <p style={{ margin: 0 }}>{state.confirm.message}</p>
                </ForgeModal>
            )}

            {state.preview && <PreviewModal preview={state.preview} t={t} onClose={() => editor.closePreview()} />}
        </>
    );
}

export interface PtahCrudConfigButtonProps {
    crud: CrudController;
    locale?: string;
}

const GearIcon = () => (
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="2"
            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
        />
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
);

/**
 * The gear of the <PtahCrud> toolbar. Renders nothing for a user who may not
 * configure the screen; after a save the listing re-reads its schema and
 * preferences and reloads (what the Livewire BaseCrud does on
 * `ptah:crud-config-updated`).
 */
export function PtahCrudConfigButton({ crud, locale }: PtahCrudConfigButtonProps) {
    const t = useMemo(() => crudConfigWords(locale), [locale]);
    const [can, setCan] = useState(false);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        let live = true;
        void canConfigure(crud.api, crud.screen).then((allowed) => live && setCan(allowed));

        return () => {
            live = false;
        };
    }, [crud]);

    if (!can) {
        return null;
    }

    return (
        <>
            {/* forge-button dark flat sm + .ptah-c-control, as crud-config.blade.php draws it. */}
            <button type="button" className="pc-tbtn pc-tbtn--flat" title={t.cfg_btn_title} aria-label={t.cfg_btn_title} aria-haspopup="dialog" onClick={() => setOpen(true)}>
                <GearIcon />
                <span className="pc-tbtn-label">{t.button}</span>
            </button>
            {open && <PtahCrudConfig api={crud.api} screen={crud.screen} locale={locale} onClose={() => setOpen(false)} onSaved={() => void crud.savePreferences({})} />}
        </>
    );
}
