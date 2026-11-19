/**
 * Spreadsheet import for <PtahCrud> (Vue) — the Livewire `_modal-import`:
 * file → columns → review → done. Mirrors react/crud/import.tsx; the
 * wizard's state is core's ImportWizard.
 */
import { computed, defineComponent, h, onBeforeUnmount, ref, shallowRef } from 'vue';
import type { PropType, VNode } from 'vue';
import type { CrudController } from '../../core/controller';
import { acceptOf, canRun, fill, importWords, ImportWizard } from '../../core/crud/import';
import type { ImportState, ImportWords } from '../../core/crud/import';
import { modalButton } from '../../core/crud/form';
import { icon } from '../icons';
import { PtahCrudModal, PtahModalAlert } from './modal';
import { usePtahCrud } from '../usePtahCrud';

const props = {
    crud: { type: Object as PropType<CrudController>, required: true as const },
    /** `pt_BR`, `en`… Defaults to the page's `<html lang>`. */
    locale: { type: String, default: undefined },
};

let ids = 0;
const help = (text: string, extra: Record<string, unknown> = {}) => h('p', { class: 'pc-imp-small', ...extra }, text);
const spinner = (busy: boolean) => (busy ? h('span', { class: 'pc-fm-spin', 'aria-hidden': 'true' }) : null);
const button = (kind: 'primary' | 'flat', label: string, onClick: () => void, busy = false, disabled = false) =>
    h('button', { type: 'button', class: modalButton(kind), disabled: busy || disabled, 'aria-busy': busy || undefined, onClick }, [spinner(busy), label]);

/** forge-stepper: numbered circles joined by lines; a done step is a green check. */
const stepper = (steps: string[], current: number) =>
    h(
        'ol',
        { class: 'pc-imp-steps' },
        steps.map((label, index) => {
            const number = index + 1;
            const state = number < current ? 'done' : number === current ? 'current' : 'todo';

            return h('li', { key: index, 'data-state': state, 'aria-current': state === 'current' ? 'step' : undefined }, [
                h('span', { class: 'pc-imp-st-row' }, [
                    index > 0 ? h('span', { class: 'pc-imp-line', 'aria-hidden': 'true' }) : null,
                    h('span', { class: 'pc-imp-dot' }, state === 'done' ? [icon('check')] : String(number)),
                    index < steps.length - 1 ? h('span', { class: 'pc-imp-line', 'aria-hidden': 'true' }) : null,
                ]),
                h('span', { class: 'pc-imp-step-lbl' }, label),
            ]);
        }),
    );

/** The toolbar button: opens the wizard. Nothing when the screen or the user has no import. */
export const PtahCrudImportButton = defineComponent({
    name: 'PtahCrudImportButton',
    props,
    setup(p) {
        const state = usePtahCrud(p.crud);
        const t = computed(() => importWords(p.locale));

        return () =>
            state.value.schema?.features.import
                ? h('button', { type: 'button', class: 'pc-tbtn pc-tb-secondary', title: t.value.button, 'aria-label': t.value.button, onClick: () => p.crud.openPanel('import') }, [
                      icon('upload'),
                      h('span', { class: 'pc-tbtn-label' }, t.value.button),
                  ])
                : null;
    },
});

/** The wizard, while the screen's panel is `import`. Mount it once, next to the screen. */
export const PtahCrudImport = defineComponent({
    name: 'PtahCrudImport',
    props,
    setup(p) {
        const state = usePtahCrud(p.crud);

        return () => (state.value.schema?.features.import && state.value.panel?.name === 'import' ? h(Wizard, { crud: p.crud, locale: p.locale }) : null);
    },
});

const Wizard = defineComponent({
    name: 'PtahCrudImportWizard',
    props,
    setup(p) {
        const t = importWords(p.locale);
        // One wizard per opening: closing and reopening starts over, as on Livewire.
        const wizard = new ImportWizard(p.crud, t);
        const s = shallowRef<ImportState>(wizard.getState());
        const stop = wizard.subscribe(() => {
            s.value = wizard.getState();
        });
        onBeforeUnmount(stop);
        void wizard.start();

        const back = () => button('flat', t.back, () => wizard.back(), false, s.value.busy);
        const footer = (): VNode[] => {
            const st = s.value;
            if (st.step === 2) {
                return [back(), button('primary', t.next, () => void wizard.next(), st.busy)];
            }
            if (st.step === 3) {
                return [back(), ...(canRun(st.preview) ? [button('primary', fill(t.run, { count: st.preview?.valid ?? 0 }), () => void wizard.run(), st.busy)] : [])];
            }

            return [button('flat', t.close, () => wizard.close())];
        };

        return () => {
            const st = s.value;

            return h(
                PtahCrudModal,
                { title: t.title, size: 'lg', closeLabel: t.close, onClose: () => wizard.close() },
                {
                    default: () =>
                        h('div', { class: 'pc-imp' }, [
                            stepper([t.stepUpload, t.stepMap, t.stepPreview, t.stepDone], st.step),
                            st.error && st.status !== 'loading' ? h('p', { class: 'pc-fm-err', role: 'alert' }, st.error) : null,
                            st.step === 1 ? h(UploadStep, { s: st, t, onFile: (file: File | null) => void wizard.choose(file) }) : null,
                            st.step === 2 ? h(MapStep, { s: st, t, onMap: (index: number, field: string) => wizard.setMapping(index, field) }) : null,
                            st.step === 3 ? h(PreviewStep, { s: st, t }) : null,
                            st.step === 4 && st.result
                                ? h(PtahModalAlert, { color: st.result.error ? 'danger' : 'success' }, () => st.result?.error ?? fill(t.done, { created: st.result?.created ?? 0, updated: st.result?.updated ?? 0 }))
                                : null,
                        ]),
                    footer,
                },
            );
        };
    },
});

const stepProps = {
    s: { type: Object as PropType<ImportState>, required: true as const },
    t: { type: Object as PropType<ImportWords>, required: true as const },
};

const UploadStep = defineComponent({
    name: 'PtahCrudImportUpload',
    props: { ...stepProps, onFile: { type: Function as PropType<(file: File | null) => void>, required: true as const } },
    setup(p) {
        const id = `pc-import-file-${++ids}`;
        // Re-mounting the input clears it, so the same file can be chosen again after a refusal.
        const round = ref(0);

        return () => {
            const { s, t } = p;
            if (s.status === 'loading') {
                return h('div', { class: 'pc-fm-loading', role: 'status' }, [spinner(true), ` ${t.loading}`]);
            }
            if (s.status === 'error' || !s.info) {
                return null;
            }

            return h('div', { class: 'pc-imp-step' }, [
                h('p', { class: 'pc-imp-hint' }, fill(t.uploadHint, { max: s.info.max_rows })),
                h('label', { class: 'pc-fm-pick', for: id }, [
                    icon('upload'),
                    t.button,
                    h('input', {
                        key: round.value,
                        id,
                        type: 'file',
                        class: 'pc-visually-hidden',
                        'aria-label': t.chooseFile,
                        accept: acceptOf(s.info.extensions),
                        disabled: s.busy,
                        'aria-invalid': s.fileError ? 'true' : undefined,
                        'aria-describedby': s.fileError ? `${id}-error` : undefined,
                        onChange: (event: Event) => {
                            p.onFile((event.target as HTMLInputElement).files?.[0] ?? null);
                            round.value++;
                        },
                    }),
                ]),
                s.busy ? help(t.reading, { role: 'status' }) : null,
                s.fileError ? h('p', { class: 'pc-fm-err', role: 'alert', id: `${id}-error` }, s.fileError) : null,
            ]);
        };
    },
});

const MapStep = defineComponent({
    name: 'PtahCrudImportMap',
    props: { ...stepProps, onMap: { type: Function as PropType<(index: number, field: string) => void>, required: true as const } },
    setup(p) {
        const id = `pc-import-map-${++ids}`;

        return () => {
            const { s, t } = p;

            return [
                h('p', { class: 'pc-imp-hint' }, t.mapHint),
                h(
                    'div',
                    { class: 'pc-imp-map' },
                    (s.upload?.headers ?? []).map((header, index) =>
                        h('div', { key: index }, [
                            h('label', { for: `${id}-${index}` }, header !== '' ? header : `#${index + 1}`),
                            h(
                                'select',
                                {
                                    id: `${id}-${index}`,
                                    value: s.mapping[index] ?? '',
                                    disabled: s.busy,
                                    onChange: (event: Event) => p.onMap(index, (event.target as HTMLSelectElement).value),
                                },
                                [
                                    h('option', { value: '', selected: (s.mapping[index] ?? '') === '' }, t.ignore),
                                    ...(s.upload?.fields ?? []).map((field) => h('option', { key: field.field, value: field.field, selected: s.mapping[index] === field.field }, field.label)),
                                ],
                            ),
                        ]),
                    ),
                ),
            ];
        };
    },
});

const PreviewStep = defineComponent({
    name: 'PtahCrudImportPreview',
    props: stepProps,
    setup(p) {
        const titleId = `pc-import-errors-${++ids}`;

        return () => {
            const { s, t } = p;
            const pv = s.preview;
            if (!pv) {
                return null;
            }
            const clean = pv.errors.length === 0 && pv.unmapped_required.length === 0;
            const labels = new Map((s.upload?.fields ?? []).map((field) => [field.field, field.label]));

            return [
                pv.unmapped_required.length > 0 ? h(PtahModalAlert, { color: 'danger' }, () => fill(t.unmappedRequired, { fields: pv.unmapped_required.join(', ') })) : null,
                h(PtahModalAlert, { color: clean ? 'success' : 'warn' }, () => fill(t.previewSummary, { valid: pv.valid, total: pv.total })),
                pv.errors.length > 0
                    ? h('div', { class: 'pc-imp-errs' }, [
                          h('p', { class: 'pc-imp-errs-ttl', id: titleId }, t.errorsTitle),
                          h('div', { class: 'pc-imp-table-wrap' }, [
                              h('table', { class: 'pc-imp-table', 'aria-labelledby': titleId }, [
                                  h('thead', [h('tr', [h('th', { scope: 'col' }, t.line), h('th', { scope: 'col' }, t.field), h('th', { scope: 'col' }, t.problem)])]),
                                  h(
                                      'tbody',
                                      pv.errors.map((error, index) =>
                                          h('tr', { key: `${error.line}-${error.field}-${index}` }, [
                                              h('td', { class: 'pc-imp-num' }, String(error.line)),
                                              h('td', labels.get(error.field) ?? error.field),
                                              h('td', { class: 'pc-imp-bad' }, error.message),
                                          ]),
                                      ),
                                  ),
                              ]),
                          ]),
                          pv.more_errors > 0 ? help(fill(t.moreErrors, { count: pv.more_errors })) : null,
                          help(t.fixAndRetry),
                      ])
                    : null,
            ];
        };
    },
});
