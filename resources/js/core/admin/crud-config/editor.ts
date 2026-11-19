/**
 * The config editor's state machine, framework-free: holds the document
 * being edited (what the Livewire editor keeps in its properties between
 * clicks), sends it with every button to the component's own action, and
 * keeps what comes back. React and Vue only subscribe and draw.
 */
import { ApiError } from '../../api';
import type { PtahApi } from '../../api';
import { ACTION_SECTION, audienceCount, fetchConfig, firstErrors, previewConfig, runConfigAction, saveConfig } from './client';
import type { ActionResult, ConfigAction, ConfigDocument, ConfigEntry, ConfigHints, ConfigPayload, ConfigSection, ConfigSettings, PreviewResult, SaveResult } from './client';
import { notAppliedText, writeValue } from './forms';
import type { CrudConfigWords } from './words';

export interface EditorState {
    status: 'loading' | 'ready' | 'error' | 'forbidden';
    /** A failure of the last request (load, action, save). */
    error: string | null;
    payload: ConfigPayload | null;
    document: ConfigDocument | null;
    hints: ConfigHints;
    forms: Record<ConfigSection, ConfigEntry>;
    editing: Record<ConfigSection, number>;
    busy: ConfigAction | 'save' | 'preview' | null;
    /** "Fill in … first" after an action the component ignored. */
    notice: { section: ConfigSection; text: string } | null;
    /** addJoin()'s refusal of a second JOIN on the same table. */
    joinError: string | null;
    /** A save refused by the schema validator or by a field type. */
    saveError: string | null;
    /** The recipients of the notification rule being edited. */
    audience: number | null;
    preview: PreviewResult | null;
    /** A removal waiting for its confirmation (the Blade's wire:confirm). */
    confirm: { message: string; action: ConfigAction; index: number } | null;
}

const SECTIONS: ConfigSection[] = ['field', 'action', 'join', 'notification', 'filter', 'style'];

const blankForms = (): Record<ConfigSection, ConfigEntry> => Object.fromEntries(SECTIONS.map((section) => [section, {}])) as Record<ConfigSection, ConfigEntry>;
const blankEditing = (): Record<ConfigSection, number> => Object.fromEntries(SECTIONS.map((section) => [section, -1])) as Record<ConfigSection, number>;

export class ConfigEditor {
    private state: EditorState = {
        status: 'loading',
        error: null,
        payload: null,
        document: null,
        hints: { placeholders: [], traitMissing: false },
        forms: blankForms(),
        editing: blankEditing(),
        busy: null,
        notice: null,
        joinError: null,
        saveError: null,
        audience: null,
        preview: null,
        confirm: null,
    };

    private readonly listeners = new Set<() => void>();
    private audienceTimer: ReturnType<typeof setTimeout> | undefined;

    constructor(
        readonly api: PtahApi,
        readonly screen: string,
        private t: CrudConfigWords,
    ) {}

    subscribe = (listener: () => void): (() => void) => {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    };

    get = (): EditorState => this.state;

    setWords(t: CrudConfigWords): void {
        this.t = t;
    }

    dispose(): void {
        clearTimeout(this.audienceTimer);
        this.listeners.clear();
    }

    private set(patch: Partial<EditorState>): void {
        this.state = { ...this.state, ...patch };
        this.listeners.forEach((listener) => listener());
    }

    private failure(error: unknown): string {
        if (error instanceof ApiError && error.status === 403) {
            return this.t.forbidden;
        }

        return error instanceof ApiError && error.message ? error.message : this.t.error;
    }

    // ── Load ────────────────────────────────────────────────────────────────

    async load(): Promise<void> {
        this.set({ status: 'loading', error: null });
        try {
            const payload = await fetchConfig(this.api, this.screen);
            this.set({ status: 'ready', payload, document: payload.document, hints: payload.hints, forms: blankForms(), editing: blankEditing() });
        } catch (error) {
            this.set({ status: error instanceof ApiError && error.status === 403 ? 'forbidden' : 'error', error: this.failure(error) });
        }
    }

    // ── Local edits (the Blade's wire:model) ────────────────────────────────

    setForm(section: ConfigSection, key: string, value: unknown): void {
        this.set({ forms: { ...this.state.forms, [section]: writeValue(this.state.forms[section], key, value) }, notice: null, joinError: section === 'join' ? null : this.state.joinError });
        if (section === 'notification' && (key === 'audience' || key === 'audienceValue')) {
            this.scheduleAudience();
        }
    }

    setSetting(key: string, value: unknown): void {
        const document = this.state.document;
        if (!document) {
            return;
        }
        this.set({ document: { ...document, settings: writeValue(document.settings, key, value) as ConfigSettings }, saveError: null });
    }

    // ── The component's actions ─────────────────────────────────────────────

    /** Runs one of the component's actions on the current document and sub-form. */
    async run(action: ConfigAction, args: { index?: number; order?: number[] } = {}): Promise<boolean> {
        const document = this.state.document;
        if (!document || this.state.busy) {
            return false;
        }
        const section = ACTION_SECTION[action];
        this.set({ busy: action, error: null, notice: null, joinError: null });

        let result: ActionResult;
        try {
            result = await runConfigAction(this.api, this.screen, action, { document, form: this.state.forms[section], editing: this.state.editing[section], ...args });
        } catch (error) {
            this.set({ busy: null, error: this.failure(error) });

            return false;
        }

        if (!result.document) {
            // A 422: the duplicate JOIN, or a refused argument.
            const errors = firstErrors(result.errors);
            this.set({ busy: null, joinError: section === 'join' ? (errors.table ?? result.message ?? null) : null, error: section === 'join' ? null : (Object.values(errors)[0] ?? result.message ?? this.t.error) });

            return false;
        }

        const isAdd = action.startsWith('add') || action === 'updateField';
        this.set({
            busy: null,
            document: result.document,
            hints: result.hints ?? this.state.hints,
            forms: { ...this.state.forms, [section]: result.form ?? {} },
            editing: { ...this.state.editing, [section]: result.editing ?? -1 },
            notice: isAdd && result.applied === false ? { section, text: notAppliedText(section, this.t) } : null,
        });
        if (section === 'notification') {
            this.scheduleAudience(0);
        }

        return result.applied !== false;
    }

    /** A removal, after the same question the Blade's wire:confirm asks. */
    askRemove(action: ConfigAction, index: number, message: string): void {
        this.set({ confirm: { action, index, message } });
    }

    async confirmYes(): Promise<void> {
        const pending = this.state.confirm;
        this.set({ confirm: null });
        if (pending) {
            await this.run(pending.action, { index: pending.index });
        }
    }

    confirmNo(): void {
        this.set({ confirm: null });
    }

    // ── Save, preview, audience ─────────────────────────────────────────────

    /** save(): the answer's message, or null when refused (the reason is in the state). */
    async save(): Promise<SaveResult | null> {
        const document = this.state.document;
        if (!document || this.state.busy) {
            return null;
        }
        this.set({ busy: 'save', error: null, saveError: null });
        try {
            const result = await saveConfig(this.api, this.screen, document);
            if (!result.ok) {
                const errors = firstErrors(result.errors);
                this.set({ busy: null, saveError: (result.path ? `${result.message ?? ''} (${result.path})` : null) ?? errors.config ?? Object.values(errors)[0] ?? result.message ?? this.t.error });

                return null;
            }
            this.set({ busy: null, document: result.document ?? document, hints: result.hints ?? this.state.hints });

            return result;
        } catch (error) {
            this.set({ busy: null, error: this.failure(error) });

            return null;
        }
    }

    async openPreview(): Promise<void> {
        const document = this.state.document;
        if (!document || this.state.busy) {
            return;
        }
        this.set({ busy: 'preview', error: null });
        try {
            this.set({ busy: null, preview: await previewConfig(this.api, this.screen, document) });
        } catch (error) {
            this.set({ busy: null, error: this.failure(error) });
        }
    }

    closePreview(): void {
        this.set({ preview: null });
    }

    /** notificationAudienceCount() for the rule being edited, after typing stops. */
    private scheduleAudience(delay = 300): void {
        clearTimeout(this.audienceTimer);
        this.audienceTimer = setTimeout(() => {
            audienceCount(this.api, this.screen, this.state.forms.notification).then(
                (count) => this.set({ audience: count }),
                () => this.set({ audience: null }),
            );
        }, delay);
    }

    /** The first count, when the Notifications tab opens. */
    loadAudience(): void {
        if (this.state.audience === null && this.state.payload?.options.notificationsEnabled) {
            this.scheduleAudience(0);
        }
    }
}
