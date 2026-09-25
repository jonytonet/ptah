<?php

declare(strict_types=1);

namespace Ptah\Traits;

use Illuminate\Database\Eloquent\Builder;
use Ptah\Models\Attachment;

/**
 * Files attached to the model's records.
 *
 * `use HasAttachments;` on a model, run `php artisan ptah:attachments:install`
 * and migrate; the model's BaseCrud screen then shows an Attachments button in
 * the edit modal. Storage disk, size and type limits: `config('ptah.attachments')`.
 */
trait HasAttachments
{
    /**
     * @return Builder<Attachment>
     */
    public function attachmentsQuery(): Builder
    {
        return Attachment::query()
            ->where('subject_type', $this->getMorphClass())
            ->where('subject_id', (string) $this->getKey());
    }
}
