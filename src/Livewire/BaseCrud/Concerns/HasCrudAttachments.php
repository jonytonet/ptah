<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Ptah\Models\Attachment;
use Ptah\Traits\HasAttachments;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Attachments button of the edit modal: list, upload, download, delete.
 *
 * Every action re-reads the record through scopedQuery() — the id is client
 * input — and every attachment is looked up UNDER that record, so an
 * attachment id from another record, company or screen is never served.
 * There is no public URL: the file streams through this component, on a
 * private disk by default. Listing and downloading need read (plus the
 * optional `permissions.attachments` gate); uploading and deleting need
 * update. The stored name is random; only the download uses the original.
 */
trait HasCrudAttachments
{
    public bool $showAttachmentsModal = false;

    #[Locked]
    public int|string|null $attachmentsRecordId = null;

    /** @var array<int, mixed> Livewire TemporaryUploadedFile[] */
    public array $attachmentUploads = [];

    /** @var list<array{id: int, name: string, size: string, when: string, mime: string|null}> */
    #[Locked]
    public array $attachmentItems = [];

    public function attachmentsEnabled(): bool
    {
        if (($this->crudConfig['attachments']['enabled'] ?? true) === false) {
            return false;
        }

        $model = $this->resolveEloquentModel();

        return $model !== null
            && in_array(HasAttachments::class, class_uses_recursive($model), true)
            && Attachment::tableExists()
            && $this->authorizeCrudAction('read') && $this->crudConfigAllows('attachments');
    }

    public function attachmentsCanWrite(): bool
    {
        return $this->attachmentsEnabled()
            && $this->authorizeCrudAction('update') && $this->crudConfigAllows('update');
    }

    public function openAttachments(int|string $id): void
    {
        $record = $this->attachmentsEnabled() ? $this->scopedQuery()?->find($id) : null;

        if (! $record) {
            return;
        }

        $this->attachmentsRecordId = $record->getKey();
        $this->attachmentUploads = [];
        $this->resetErrorBag('attachmentUploads');
        $this->loadAttachmentItems($record);
        $this->showAttachmentsModal = true;
    }

    public function closeAttachments(): void
    {
        $this->showAttachmentsModal = false;
        $this->attachmentsRecordId = null;
        $this->attachmentUploads = [];
        $this->attachmentItems = [];
    }

    public function uploadAttachments(): void
    {
        $record = $this->attachmentsRecord();

        if (! $record || ! $this->attachmentsCanWrite()) {
            return;
        }

        $cfg = (array) config('ptah.attachments', []);
        $maxKb = (int) ($cfg['max_kb'] ?? 10240);
        $mimes = implode(',', (array) ($cfg['mimes'] ?? ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'txt', 'csv', 'xlsx', 'xls', 'doc', 'docx', 'zip']));

        $this->validate([
            'attachmentUploads' => ['required', 'array', 'max:10'],
            'attachmentUploads.*' => ['file', 'max:'.$maxKb, 'mimes:'.$mimes],
        ]);

        $disk = (string) ($cfg['disk'] ?? 'local');
        $dir = 'ptah-attachments/'.$record->getTable().'/'.$record->getKey();

        foreach ($this->attachmentUploads as $file) {
            $path = $file->store($dir, $disk);

            Attachment::query()->create([
                'subject_type' => $record->getMorphClass(),
                'subject_id' => (string) $record->getKey(),
                'disk' => $disk,
                'path' => $path,
                'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
                'mime' => $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'user_id' => is_numeric(Auth::id()) ? (int) Auth::id() : null,
                'user_guard' => Auth::check() ? Auth::getDefaultDriver() : null,
                'company_id' => ptah_company_id() > 0 ? ptah_company_id() : null,
            ]);
        }

        $this->attachmentUploads = [];
        $this->loadAttachmentItems($record);
        $this->dispatch('ptah-toast', title: trans('ptah::ui.attachments_uploaded'), color: 'success');
    }

    public function downloadAttachment(int $attachmentId): ?StreamedResponse
    {
        $attachment = $this->scopedAttachment($attachmentId);

        if (! $attachment || ! Storage::disk($attachment->disk)->exists($attachment->path)) {
            $this->dispatch('ptah-toast', title: trans('ptah::ui.attachments_missing'), color: 'danger');

            return null;
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $attachment = $this->attachmentsCanWrite() ? $this->scopedAttachment($attachmentId) : null;

        if (! $attachment) {
            return;
        }

        $attachment->delete(); // soft delete: o arquivo fica

        if ($record = $this->attachmentsRecord()) {
            $this->loadAttachmentItems($record);
        }
    }

    public function attachmentCount(int|string $id): int
    {
        $record = $this->attachmentsEnabled() ? $this->scopedQuery()?->find($id) : null;

        return $record ? Attachment::forSubject($record)->count() : 0;
    }

    protected function attachmentsRecord(): ?Model
    {
        if ($this->attachmentsRecordId === null || ! $this->attachmentsEnabled()) {
            return null;
        }

        return $this->scopedQuery()?->find($this->attachmentsRecordId);
    }

    /**
     * The attachment, only if it belongs to the open record — which is itself
     * re-read through the screen's scope.
     */
    protected function scopedAttachment(int $attachmentId): ?Attachment
    {
        $record = $this->attachmentsRecord();

        return $record ? Attachment::forSubject($record)->whereKey($attachmentId)->first() : null;
    }

    protected function loadAttachmentItems(Model $record): void
    {
        $this->attachmentItems = Attachment::forSubject($record)->orderByDesc('id')->limit(200)->get()
            ->map(fn (Attachment $a) => [
                'id' => (int) $a->id,
                'name' => $a->original_name,
                'size' => $a->size >= 1048576 ? round($a->size / 1048576, 1).' MB' : max(1, (int) round($a->size / 1024)).' KB',
                'when' => $a->created_at?->format('d/m/Y H:i') ?? '',
                'mime' => $a->mime,
            ])->all();
    }
}
