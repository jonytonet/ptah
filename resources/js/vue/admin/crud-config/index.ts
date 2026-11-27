/**
 * The BaseCrud's visual config editor for Vue — the gear of a screen.
 *
 *   <PtahCrudConfigButton :crud="crud" :locale="locale" />   in the <PtahCrud> toolbar
 *   <PtahCrudConfig screen="products" @close="…" />          the editor itself
 *
 * The rules are the server's (the Livewire component CrudConfig, run by the
 * crud-config API): who may configure, the defaults of a new column, the
 * JOIN rules, the merge with the stored config and its validation. Mirrors
 * react/admin/crud-config/index.tsx.
 */
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import type { Component, PropType } from 'vue';
import { PtahApi } from '../../../core';
import type { CrudController } from '../../../core';
import { canConfigure, ConfigEditor, crudConfigWords, footerSummary, mainTabs } from '../../../core/admin/crud-config';
import type { BuildContext, EditorState, SaveResult } from '../../../core/admin/crud-config';
import { ForgeAlert, ForgeButton, ForgeModal, ForgeSpinner, ForgeTabs, forgeToast } from '../../forge';
import { ActionsTab, ColumnsTab, FiltersTab, GeneralTab, HooksTab, JoinsTab, NotificationsTab, PermissionsTab, previewModal, StylesTab, tabHeader } from './tabs';

const TABS: Record<string, Component> = {
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

/** The editor, as a full-screen dialog (the Livewire modal). Emits `saved` (the server's answer) and `close`. */
export const PtahCrudConfig = defineComponent({
    name: 'PtahCrudConfig',
    props: {
        api: { type: Object as PropType<PtahApi>, default: undefined },
        /** The registered screen key (CrudScreen::register) — never a model. */
        screen: { type: String, required: true },
        /** `pt_BR`, `en`… Defaults to the page's `<html lang>`. */
        locale: { type: String, default: undefined },
        onSaved: { type: Function as PropType<(result: SaveResult) => void>, default: undefined },
        onClose: { type: Function as PropType<() => void>, default: undefined },
    },
    setup(props) {
        const t = computed(() => crudConfigWords(props.locale));
        const editor = new ConfigEditor(props.api ?? new PtahApi(), props.screen, t.value);
        const state = shallowRef<EditorState>(editor.get());
        const stop = editor.subscribe(() => {
            state.value = editor.get();
        });
        watch(t, (words) => editor.setWords(words));
        onMounted(() => void editor.load());
        onBeforeUnmount(() => {
            stop();
            editor.dispose();
        });

        // Escape on the confirmation or the preview closes only that one.
        const close = () => {
            const current = editor.get();
            if (!current.confirm && !current.preview) {
                props.onClose?.();
            }
        };

        const save = async () => {
            const result = await editor.save();
            if (result) {
                forgeToast(result.message || t.value.savedFallback, 'success');
                props.onSaved?.(result);
                props.onClose?.();
            }
        };

        return () => {
            const s = state.value;
            const w = t.value;
            const ready = s.status === 'ready' && s.document !== null && s.payload !== null;
            const ctx: BuildContext | null = ready ? { t: w, choices: s.payload!.choices, options: s.payload!.options, document: s.document!, hints: s.hints } : null;
            const tabs = ready ? mainTabs(s.document!, w) : [];

            return [
                h(
                    ForgeModal,
                    { modelValue: true, 'onUpdate:modelValue': (value: boolean) => !value && close(), size: 'full', title: w.title, subtitle: w.cfg_btn_title },
                    {
                        default: () => [
                            s.status === 'loading' ? h('div', { class: 'pa-flex-center-g2' }, [h(ForgeSpinner, { size: 'sm', label: w.loading }), ` ${w.loading}`]) : null,
                            s.status === 'error' || s.status === 'forbidden'
                                ? h('div', { class: 'pa-grid-g3' }, [
                                      h(ForgeAlert, { color: 'danger' }, () => s.error),
                                      s.status === 'error' ? h('div', [h(ForgeButton, { size: 'sm', onClick: () => void editor.load() }, () => w.retry)]) : null,
                                  ])
                                : null,
                            ready && ctx
                                ? h('div', { class: 'pa-grid-g4' }, [
                                      h('div', { class: 'pa-flex-wrap-center-g2' }, [
                                          h('code', { class: 'pa-mono pa-text-muted' }, s.payload!.model),
                                          h('span', { class: 'pa-tag' }, s.payload!.route !== '' ? `/${s.payload!.route}` : w.globalScope),
                                      ]),
                                      s.error ? h(ForgeAlert, { color: 'danger' }, () => s.error) : null,
                                      s.saveError ? h(ForgeAlert, { color: 'danger' }, () => s.saveError) : null,
                                      h(
                                          ForgeTabs,
                                          {
                                              tabs: tabs.map((tab) => ({ id: tab.id, label: tab.count === null ? tab.label : `${tab.label} (${tab.count})` })),
                                              onChange: (id: string) => id === 'notifications' && editor.loadAudience(),
                                          },
                                          Object.fromEntries(
                                              tabs.map((tab) => [
                                                  tab.id,
                                                  () => h('div', { class: 'pa-grid-g4' }, [tabHeader(tab.title, tab.description), h(TABS[tab.id], { editor, state: s, t: w, ctx })]),
                                              ]),
                                          ),
                                      ),
                                  ])
                                : null,
                        ],
                        footer: ready
                            ? () =>
                                  h('div', { class: 'pa-cc-modal-foot' }, [
                                      h('span', { class: 'pa-cc-foot-note' }, footerSummary(s.document!, w)),
                                      h(ForgeButton, { flat: true, size: 'sm', loading: s.busy === 'preview', disabled: s.busy !== null && s.busy !== 'preview', onClick: () => void editor.openPreview() }, () => w.cfg_preview_btn),
                                      h(ForgeButton, { flat: true, color: 'light', size: 'sm', onClick: () => props.onClose?.() }, () => w.cfg_footer_cancel),
                                      h(ForgeButton, { size: 'sm', loading: s.busy === 'save', disabled: s.busy !== null && s.busy !== 'save', onClick: () => void save() }, () => w.cfg_footer_save),
                                  ])
                            : undefined,
                    },
                ),
                s.confirm
                    ? h(
                          ForgeModal,
                          { modelValue: true, 'onUpdate:modelValue': (value: boolean) => !value && editor.confirmNo(), size: 'sm', title: w.confirmTitle },
                          {
                              default: () => h('p', { class: 'pa-flat' }, s.confirm!.message),
                              footer: () => [
                                  h(ForgeButton, { flat: true, color: 'light', size: 'sm', onClick: () => editor.confirmNo() }, () => w.cfg_footer_cancel),
                                  h(ForgeButton, { color: 'danger', size: 'sm', onClick: () => void editor.confirmYes() }, () => w.confirmYes),
                              ],
                          },
                      )
                    : null,
                s.preview ? previewModal(s.preview, w, () => editor.closePreview()) : null,
            ];
        };
    },
});

const gearIcon = () =>
    h('svg', { width: 16, height: 16, fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor', 'aria-hidden': 'true' }, [
        h('path', {
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round',
            'stroke-width': 2,
            d: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
        }),
        h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': 2, d: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z' }),
    ]);

/**
 * The gear of the <PtahCrud> toolbar. Renders nothing for a user who may not
 * configure the screen; after a save the listing re-reads its schema and
 * preferences and reloads (what the Livewire BaseCrud does on
 * `ptah:crud-config-updated`).
 */
export const PtahCrudConfigButton = defineComponent({
    name: 'PtahCrudConfigButton',
    props: {
        crud: { type: Object as PropType<CrudController>, required: true },
        locale: { type: String, default: undefined },
    },
    setup(props) {
        const t = computed(() => crudConfigWords(props.locale));
        const can = ref(false);
        const open = ref(false);
        let live = true;

        onMounted(() => {
            void canConfigure(props.crud.api, props.crud.screen).then((allowed) => {
                if (live) {
                    can.value = allowed;
                }
            });
        });
        onBeforeUnmount(() => {
            live = false;
        });

        return () =>
            can.value
                ? [
                      h('button', { type: 'button', class: 'pc-tbtn pc-tbtn--flat', title: t.value.cfg_btn_title, 'aria-label': t.value.cfg_btn_title, 'aria-haspopup': 'dialog', onClick: () => (open.value = true) }, [gearIcon(), h('span', { class: 'pc-tbtn-label' }, t.value.button)]),
                      open.value
                          ? h(PtahCrudConfig, {
                                api: props.crud.api,
                                screen: props.crud.screen,
                                locale: props.locale,
                                onClose: () => (open.value = false),
                                onSaved: () => void props.crud.savePreferences({}),
                            })
                          : null,
                  ]
                : null;
    },
});
