/**
 * Spreadsheet import for <PtahCrud> (React) — the Livewire `_modal-import`:
 * file → columns → review → done. The wizard's state is core's ImportWizard;
 * this file only draws it.
 */
import { useEffect, useId, useMemo, useState, useSyncExternalStore } from 'react';
import type { CrudController } from '../../core/controller';
import { acceptOf, canRun, fill, importWords, ImportWizard } from '../../core/crud/import';
import type { ImportState, ImportWords } from '../../core/crud/import';
import { ForgeAlert, ForgeButton, ForgeModal, ForgeSpinner, ForgeStepper } from '../forge';

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
        <button type="button" className="pc-btn" title={t.button} onClick={() => crud.openPanel('import')}>
            {t.button}
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
                <ForgeButton color="dark" flat disabled={s.busy} onClick={() => wizard.back()}>
                    {t.back}
                </ForgeButton>
                <ForgeButton loading={s.busy} onClick={() => void wizard.next()}>
                    {t.next}
                </ForgeButton>
            </>
        ) : s.step === 3 ? (
            <>
                <ForgeButton color="dark" flat disabled={s.busy} onClick={() => wizard.back()}>
                    {t.back}
                </ForgeButton>
                {canRun(s.preview) && (
                    <ForgeButton loading={s.busy} onClick={() => void wizard.run()}>
                        {fill(t.run, { count: s.preview?.valid ?? 0 })}
                    </ForgeButton>
                )}
            </>
        ) : (
            <ForgeButton color="dark" flat onClick={() => wizard.close()}>
                {t.close}
            </ForgeButton>
        );

    return (
        <ForgeModal open onClose={() => wizard.close()} title={t.title} size="lg" footer={footer}>
            <div style={{ display: 'grid', gap: '1.25rem' }}>
                <ForgeStepper currentStep={s.step} steps={[t.stepUpload, t.stepMap, t.stepPreview, t.stepDone]} />
                {s.error && s.status !== 'loading' && (
                    <p className="pc-error" role="alert">
                        {s.error}
                    </p>
                )}
                {s.step === 1 && <UploadStep s={s} t={t} onFile={(file) => void wizard.choose(file)} />}
                {s.step === 2 && <MapStep s={s} t={t} onMap={(index, field) => wizard.setMapping(index, field)} />}
                {s.step === 3 && <PreviewStep s={s} t={t} />}
                {s.step === 4 && s.result && (
                    <ForgeAlert color={s.result.error ? 'danger' : 'success'}>{s.result.error ?? fill(t.done, { created: s.result.created, updated: s.result.updated })}</ForgeAlert>
                )}
            </div>
        </ForgeModal>
    );
}

function UploadStep({ s, t, onFile }: { s: ImportState; t: ImportWords; onFile: (file: File | null) => void }) {
    const id = useId();
    // Re-mounting the input clears it, so the same file can be chosen again after a refusal.
    const [round, setRound] = useState(0);

    if (s.status === 'loading') {
        return <ForgeSpinner label={t.loading} />;
    }
    if (s.status === 'error' || !s.info) {
        return null;
    }

    return (
        <div style={{ display: 'grid', gap: '0.5rem' }}>
            <p className="pc-help" style={{ margin: 0 }}>
                {fill(t.uploadHint, { max: s.info.max_rows })}
            </p>
            <label className="pc-label" htmlFor={id}>
                {t.chooseFile}
            </label>
            <input
                key={round}
                id={id}
                type="file"
                className={`pc-input${s.fileError ? ' pc-input--error' : ''}`}
                accept={acceptOf(s.info.extensions)}
                disabled={s.busy}
                aria-invalid={s.fileError ? true : undefined}
                aria-describedby={s.fileError ? `${id}-error` : undefined}
                onChange={(event) => {
                    onFile(event.target.files?.[0] ?? null);
                    setRound(round + 1);
                }}
            />
            {s.busy && (
                <p className="pc-help" role="status" style={{ margin: 0 }}>
                    {t.reading}
                </p>
            )}
            {s.fileError && (
                <p className="pc-error" role="alert" id={`${id}-error`}>
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
            <p className="pc-help" style={{ margin: 0 }}>
                {t.mapHint}
            </p>
            <div style={{ display: 'grid', gap: '0.75rem', gridTemplateColumns: 'repeat(auto-fill, minmax(14rem, 1fr))' }}>
                {(s.upload?.headers ?? []).map((header, index) => (
                    <div key={index}>
                        <label className="pc-label" htmlFor={`${id}-${index}`}>
                            {header !== '' ? header : `#${index + 1}`}
                        </label>
                        <select id={`${id}-${index}`} className="pc-input" style={{ width: '100%' }} value={s.mapping[index] ?? ''} disabled={s.busy} onChange={(event) => onMap(index, event.target.value)}>
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
            {pv.unmapped_required.length > 0 && <ForgeAlert color="danger">{fill(t.unmappedRequired, { fields: pv.unmapped_required.join(', ') })}</ForgeAlert>}
            <ForgeAlert color={clean ? 'success' : 'warn'}>{fill(t.previewSummary, { valid: pv.valid, total: pv.total })}</ForgeAlert>
            {pv.errors.length > 0 && (
                <div style={{ display: 'grid', gap: '0.5rem' }}>
                    <p className="pc-label" id={titleId} style={{ margin: 0 }}>
                        {t.errorsTitle}
                    </p>
                    <div className="pc-table-wrap">
                        <table className="pc-table" aria-labelledby={titleId}>
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
                                        <td style={{ fontVariantNumeric: 'tabular-nums' }}>{error.line}</td>
                                        <td>{labels.get(error.field) ?? error.field}</td>
                                        <td style={{ color: 'var(--pc-danger)' }}>{error.message}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {pv.more_errors > 0 && (
                        <p className="pc-help" style={{ margin: 0 }}>
                            {fill(t.moreErrors, { count: pv.more_errors })}
                        </p>
                    )}
                    <p className="pc-help" style={{ margin: 0 }}>
                        {t.fixAndRetry}
                    </p>
                </div>
            )}
        </>
    );
}
