/**
 * Spreadsheet import for <PtahCrud> (React) — the Livewire `_modal-import`:
 * file → columns → review → done. The wizard's state is core's ImportWizard;
 * this file only draws it.
 */
import { useEffect, useId, useMemo, useState, useSyncExternalStore } from 'react';
import type { CrudController } from '../../core/controller';
import { acceptOf, canRun, fill, importWords, ImportWizard } from '../../core/crud/import';
import type { ImportState, ImportWords } from '../../core/crud/import';
import { modalButton } from '../../core/crud/form';
import { Icon } from '../icons';
import { PtahCrudModal, PtahModalAlert } from './modal';

export interface PtahCrudImportProps {
    crud: CrudController;
    /** `pt_BR`, `en`… Defaults to the page's `<html lang>`. */
    locale?: string;
}

const useCrud = (crud: CrudController) => useSyncExternalStore(crud.subscribe, crud.getState, crud.getState);

/** The toolbar button: opens the wizard. Nothing when the screen or the user has no import. */
export function PtahCrudImportButton({ crud, locale }: PtahCrudImportProps) {
    const state = useCrud(crud);
    const t = useMemo(() => importWords(locale), [locale]);

    if (!state.schema?.features.import) {
        return null;
    }

    return (
        <button type="button" className="pc-tbtn pc-tb-secondary" title={t.button} aria-label={t.button} onClick={() => crud.openPanel('import')}>
            <Icon name="upload" />
            <span className="pc-tbtn-label">{t.button}</span>
        </button>
    );
}

/** The wizard, while the screen's panel is `import`. Mount it once, next to the screen. */
export function PtahCrudImport({ crud, locale }: PtahCrudImportProps) {
    const state = useCrud(crud);

    if (!state.schema?.features.import || state.panel?.name !== 'import') {
        return null;
    }

    return <Wizard crud={crud} locale={locale} />;
}

function Wizard({ crud, locale }: PtahCrudImportProps) {
    const t = useMemo(() => importWords(locale), [locale]);
    // One wizard per opening: closing and reopening starts over, as on Livewire.
    const wizard = useMemo(() => new ImportWizard(crud, t), [crud, t]);
    const s = useSyncExternalStore(wizard.subscribe, wizard.getState, wizard.getState);

    useEffect(() => {
        void wizard.start();
    }, [wizard]);

    const footer =
        s.step === 2 ? (
            <>
                <button type="button" className={modalButton('flat')} disabled={s.busy} onClick={() => wizard.back()}>
                    {t.back}
                </button>
                <button type="button" className={modalButton('primary')} disabled={s.busy} aria-busy={s.busy || undefined} onClick={() => void wizard.next()}>
                    {s.busy && <span className="pc-fm-spin" aria-hidden="true" />}
                    {t.next}
                </button>
            </>
        ) : s.step === 3 ? (
            <>
                <button type="button" className={modalButton('flat')} disabled={s.busy} onClick={() => wizard.back()}>
                    {t.back}
                </button>
                {canRun(s.preview) && (
                    <button type="button" className={modalButton('primary')} disabled={s.busy} aria-busy={s.busy || undefined} onClick={() => void wizard.run()}>
                        {s.busy && <span className="pc-fm-spin" aria-hidden="true" />}
                        {fill(t.run, { count: s.preview?.valid ?? 0 })}
                    </button>
                )}
            </>
        ) : (
            <button type="button" className={modalButton('flat')} onClick={() => wizard.close()}>
                {t.close}
            </button>
        );

    return (
        <PtahCrudModal onClose={() => wizard.close()} closeLabel={t.close} title={t.title} size="lg" footer={footer}>
            <div className="pc-imp">
                <Stepper current={s.step} steps={[t.stepUpload, t.stepMap, t.stepPreview, t.stepDone]} />
                {s.error && s.status !== 'loading' && (
                    <p className="pc-fm-err" role="alert">
                        {s.error}
                    </p>
                )}
                {s.step === 1 && <UploadStep s={s} t={t} onFile={(file) => void wizard.choose(file)} />}
                {s.step === 2 && <MapStep s={s} t={t} onMap={(index, field) => wizard.setMapping(index, field)} />}
                {s.step === 3 && <PreviewStep s={s} t={t} />}
                {s.step === 4 && s.result && (
                    <PtahModalAlert color={s.result.error ? 'danger' : 'success'}>{s.result.error ?? fill(t.done, { created: s.result.created, updated: s.result.updated })}</PtahModalAlert>
                )}
            </div>
        </PtahCrudModal>
    );
}

/** forge-stepper: numbered circles joined by lines; a done step is a green check. */
function Stepper({ steps, current }: { steps: string[]; current: number }) {
    return (
        <ol className="pc-imp-steps">
            {steps.map((label, index) => {
                const number = index + 1;
                const state = number < current ? 'done' : number === current ? 'current' : 'todo';

                return (
                    <li key={index} data-state={state} aria-current={state === 'current' ? 'step' : undefined}>
                        <span className="pc-imp-st-row">
                            {index > 0 && <span className="pc-imp-line" aria-hidden="true" />}
                            <span className="pc-imp-dot">{state === 'done' ? <Icon name="check" /> : number}</span>
                            {index < steps.length - 1 && <span className="pc-imp-line" aria-hidden="true" />}
                        </span>
                        <span className="pc-imp-step-lbl">{label}</span>
                    </li>
                );
            })}
        </ol>
    );
}

function UploadStep({ s, t, onFile }: { s: ImportState; t: ImportWords; onFile: (file: File | null) => void }) {
    const id = useId();
    // Re-mounting the input clears it, so the same file can be chosen again after a refusal.
    const [round, setRound] = useState(0);

    if (s.status === 'loading') {
        return (
            <div className="pc-fm-loading" role="status">
                <span className="pc-fm-spin" aria-hidden="true" /> {t.loading}
            </div>
        );
    }
    if (s.status === 'error' || !s.info) {
        return null;
    }

    return (
        <div className="pc-imp-step">
            <p className="pc-imp-hint">{fill(t.uploadHint, { max: s.info.max_rows })}</p>
            <label className="pc-fm-pick" htmlFor={id}>
                <Icon name="upload" />
                {t.button}
                <input
                    key={round}
                    id={id}
                    type="file"
                    className="pc-visually-hidden"
                    aria-label={t.chooseFile}
                    accept={acceptOf(s.info.extensions)}
                    disabled={s.busy}
                    aria-invalid={s.fileError ? true : undefined}
                    aria-describedby={s.fileError ? `${id}-error` : undefined}
                    onChange={(event) => {
                        onFile(event.target.files?.[0] ?? null);
                        setRound(round + 1);
                    }}
                />
            </label>
            {s.busy && (
                <p className="pc-imp-small" role="status">
                    {t.reading}
                </p>
            )}
            {s.fileError && (
                <p className="pc-fm-err" role="alert" id={`${id}-error`}>
                    {s.fileError}
                </p>
            )}
        </div>
    );
}

function MapStep({ s, t, onMap }: { s: ImportState; t: ImportWords; onMap: (index: number, field: string) => void }) {
    const id = useId();

    return (
        <>
            <p className="pc-imp-hint">{t.mapHint}</p>
            <div className="pc-imp-map">
                {(s.upload?.headers ?? []).map((header, index) => (
                    <div key={index}>
                        <label htmlFor={`${id}-${index}`}>
                            {header !== '' ? header : `#${index + 1}`}
                        </label>
                        <select id={`${id}-${index}`} value={s.mapping[index] ?? ''} disabled={s.busy} onChange={(event) => onMap(index, event.target.value)}>
                            <option value="">{t.ignore}</option>
                            {(s.upload?.fields ?? []).map((field) => (
                                <option key={field.field} value={field.field}>
                                    {field.label}
                                </option>
                            ))}
                        </select>
                    </div>
                ))}
            </div>
        </>
    );
}

function PreviewStep({ s, t }: { s: ImportState; t: ImportWords }) {
    const titleId = useId();
    const pv = s.preview;
    if (!pv) {
        return null;
    }
    const clean = pv.errors.length === 0 && pv.unmapped_required.length === 0;
    const labels = new Map((s.upload?.fields ?? []).map((field) => [field.field, field.label]));

    return (
        <>
            {pv.unmapped_required.length > 0 && <PtahModalAlert color="danger">{fill(t.unmappedRequired, { fields: pv.unmapped_required.join(', ') })}</PtahModalAlert>}
            <PtahModalAlert color={clean ? 'success' : 'warn'}>{fill(t.previewSummary, { valid: pv.valid, total: pv.total })}</PtahModalAlert>
            {pv.errors.length > 0 && (
                <div className="pc-imp-errs">
                    <p className="pc-imp-errs-ttl" id={titleId}>
                        {t.errorsTitle}
                    </p>
                    <div className="pc-imp-table-wrap">
                        <table className="pc-imp-table" aria-labelledby={titleId}>
                            <thead>
                                <tr>
                                    <th scope="col">{t.line}</th>
                                    <th scope="col">{t.field}</th>
                                    <th scope="col">{t.problem}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {pv.errors.map((error, index) => (
                                    <tr key={`${error.line}-${error.field}-${index}`}>
                                        <td className="pc-imp-num">{error.line}</td>
                                        <td>{labels.get(error.field) ?? error.field}</td>
                                        <td className="pc-imp-bad">{error.message}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {pv.more_errors > 0 && (
                        <p className="pc-imp-small">{fill(t.moreErrors, { count: pv.more_errors })}</p>
                    )}
                    <p className="pc-imp-small">{t.fixAndRetry}</p>
                </div>
            )}
        </>
    );
}
