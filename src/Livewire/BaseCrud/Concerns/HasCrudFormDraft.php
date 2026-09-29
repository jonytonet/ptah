<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Ptah\Support\ServerOnly;

/**
 * Form draft: what the user typed in the create/edit modal survives closing
 * it, kept in the BROWSER (localStorage) — opt-in per screen with
 * `formDraft.enabled` (editor → General → Features).
 *
 * The server never stores a draft. It tells the browser, each time the modal
 * opens, which key the draft lives under and which fields may go in it, and
 * gives three actions: restore (the browser hands the values back), revert to
 * the stored record, and clear the create form.
 *
 * The key is user + screen + mode + record: "New" only ever sees the create
 * draft, an edit only the draft of THAT record id, and another user on the
 * same browser sees neither. An edit draft carries a fingerprint of the
 * record it started from: if the record changed in the database since, the
 * draft is dropped (with a notice) rather than written over someone else's
 * change.
 *
 * What never goes into a draft — localStorage is plain text on disk:
 * password fields, uploads, `$hidden` attributes, denied columns, guarded
 * fields and whatever `formDraft.exclude` lists (CPF, card number...).
 */
trait HasCrudFormDraft
{
    /**
     * The draftable values the modal opened with — the defaults for "New",
     * the stored record for an edit. Locked: "revert" and the dirty check
     * compare against it, so the browser must not be able to rewrite it.
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $formDraftOriginal = [];

    /**
     * The draft the browser read from localStorage and sent WITH the request
     * that opens the modal ({target: 'new'|'edit:ID', values, version}), so
     * the form comes back already restored — one round trip instead of two.
     * Client-writable on purpose; only draftable fields of the matching
     * target are taken, and it is emptied on every opening.
     *
     * @var array<string, mixed>
     */
    public array $formDraftIncoming = [];

    /** '' (off for this opening), 'new' or 'edit'. */
    #[Locked]
    public string $formDraftMode = '';

    /**
     * Set by duplicateRecord() around its prepareCreate(): otherwise the
     * browser got the "New" draft context first, restored the create draft
     * OVER the copy, and only then heard that this opening has no draft.
     * Private — not part of the Livewire state.
     */
    private bool $formDraftSuppressed = false;

    #[ServerOnly]
    public function formDraftEnabled(): bool
    {
        $enabled = $this->crudConfig['formDraft']['enabled'] ?? false;

        return $enabled === true || $enabled === 1 || $enabled === '1' || $enabled === 'true';
    }

    /**
     * The form fields a draft may carry.
     *
     * @return list<string>
     */
    #[ServerOnly]
    public function formDraftFields(): array
    {
        $excluded = array_map('strval', (array) ($this->crudConfig['formDraft']['exclude'] ?? []));
        $never = array_merge($excluded, $this->deniedColumns, $this->hiddenModelAttributes(), $this->guardedFormFields());
        $fields = [];

        foreach ($this->getFormCols() as $col) {
            $field = (string) ($col['colsNomeFisico'] ?? '');
            $type = (string) ($col['colsTipo'] ?? 'text');

            if ($field === '' || in_array($type, ['password', 'image', 'upload', 'file'], true)
                || in_array($field, $never, true) || str_contains($field, 'password')) {
                continue;
            }

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Called when the modal opens: records the original values and hands the
     * browser what it needs to find, keep and compare a draft.
     */
    protected function announceFormDraft(string $mode): void
    {
        $this->formDraftMode = '';
        $this->formDraftOriginal = [];

        if (! $this->formDraftEnabled() || ($this->formDraftSuppressed && $mode !== 'off')) {
            return;
        }

        // Aberto sem rascunho (uma copia): o navegador precisa saber, senao o
        // contexto da abertura anterior seguiria valendo.
        if (! in_array($mode, ['new', 'edit'], true)) {
            $this->dispatch('ptah:form-draft', key: '', user: $this->formDraftUser(), fields: [], original: [], version: '', ttlDays: 0);

            return;
        }

        $fields = $this->formDraftFields();
        $original = [];
        foreach ($fields as $field) {
            $original[$field] = self::draftValue($this->formData[$field] ?? null);
        }

        $this->formDraftMode = $mode;
        $this->formDraftOriginal = $original;
        $version = $mode === 'edit' ? md5((string) json_encode($original)) : '';

        // O rascunho que veio junto com a abertura: aplicado aqui mesmo, na
        // mesma resposta. Na edicao, so se a impressao digital do registro
        // ainda for a de quando o rascunho comecou.
        [$restored, $discarded] = $this->applyIncomingDraft($mode, $version);

        $this->dispatch('ptah:form-draft',
            key: $this->formDraftKey(),
            user: $this->formDraftUser(),
            fields: $fields,
            original: $original,
            version: $version,
            ttlDays: max(1, (int) ($this->crudConfig['formDraft']['ttlDays'] ?? 7)),
            restored: $restored,
            discarded: $discarded,
        );
    }

    /**
     * @return array{0: bool, 1: bool} [restored, discarded]
     */
    private function applyIncomingDraft(string $mode, string $version): array
    {
        $incoming = $this->formDraftIncoming;
        $this->formDraftIncoming = [];
        $target = $mode === 'edit' ? 'edit:'.$this->editingId : 'new';

        if (($incoming['target'] ?? null) !== $target || ! is_array($incoming['values'] ?? null)) {
            return [false, false];
        }

        if ((string) ($incoming['version'] ?? '') !== $version) {
            return [false, true];
        }

        $restored = false;
        foreach ($this->formDraftFields() as $field) {
            $value = $incoming['values'][$field] ?? null;
            if (array_key_exists($field, $incoming['values']) && (is_scalar($value) || $value === null)
                && self::draftValue($this->formData[$field] ?? null) !== $value) {
                $this->formData[$field] = $value;
                $restored = true;
            }
        }

        if ($restored && ($model = $this->resolveEloquentModel()) !== null) {
            $this->preloadSdLabels($model->newInstance()->forceFill(array_intersect_key($this->formData, array_flip($this->formDraftFields()))));
        }

        return [$restored, false];
    }

    /**
     * The prefix of this user's drafts on this screen — the browser builds the
     * key of the form it is about to open with it, before asking the server.
     */
    #[ServerOnly]
    public function formDraftPrefix(): string
    {
        return 'ptah:draft:'.$this->formDraftUser().':'.substr(md5($this->model.'|'.$this->configRoute), 0, 12).':';
    }

    /**
     * The browser hands back a stored draft. Only draftable fields are taken —
     * the same data the client could already type, so no new power.
     *
     * @param  array<string, mixed>  $values
     */
    public function restoreFormDraft(array $values): void
    {
        if ($this->formDraftMode === '' || ! $this->showModal) {
            return;
        }

        foreach ($this->formDraftFields() as $field) {
            if (array_key_exists($field, $values) && (is_scalar($values[$field]) || $values[$field] === null)) {
                $this->formData[$field] = $values[$field];
            }
        }

        // Os campos com mascara/searchdropdown guardam estado proprio no
        // Alpine: remontar o formulario os faz ler o valor restaurado.
        $model = $this->resolveEloquentModel();
        if ($model !== null) {
            $this->preloadSdLabels($model->newInstance()->forceFill(array_intersect_key($this->formData, array_flip($this->formDraftFields()))));
        }
        $this->formInstanceKey = ($this->formInstanceKey + 1) % 999;
    }

    /** "Revert to original": the stored record again. */
    public function revertFormToOriginal(): void
    {
        if ($this->formDraftMode !== 'edit' || ! $this->editingId) {
            return;
        }

        // Voltar ao original e o registro, nunca um rascunho que viesse junto.
        $this->formDraftIncoming = [];
        $this->openEdit((int) $this->editingId);
    }

    /** "Clear all": the create form as it opens (column defaults). */
    public function clearFormDraft(): void
    {
        if ($this->formDraftMode !== 'new') {
            return;
        }

        $this->formDraftIncoming = [];
        $this->prepareCreate();
    }

    /** After a successful save the draft of that form is gone. */
    protected function forgetFormDraftAfterSave(): void
    {
        if ($this->formDraftMode !== '') {
            $this->dispatch('ptah:form-draft-saved', key: $this->formDraftKey());
        }
    }

    /**
     * ptah:draft:{user}:{screen}:{new|edit:id}. The screen part is a hash of
     * the model and the config route, so two screens of one model do not share.
     */
    #[ServerOnly]
    public function formDraftKey(): string
    {
        $target = $this->formDraftMode === 'edit' ? 'edit:'.$this->editingId : 'new';

        return $this->formDraftPrefix().$target;
    }

    /** Guard + id — the same id on two guards is two people. */
    #[ServerOnly]
    public function formDraftUser(): string
    {
        return md5(Auth::getDefaultDriver().'|'.(string) (Auth::id() ?? 'guest'));
    }

    private static function draftValue(mixed $value): mixed
    {
        return is_scalar($value) || $value === null ? $value : null;
    }
}
