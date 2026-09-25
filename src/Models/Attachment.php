<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * A file attached to a record — see Ptah\Traits\HasAttachments.
 *
 * Deleting is a soft delete: the row and the file stay (retention, and an
 * accidental delete can be undone by a developer). The stored name is random;
 * `original_name` is only used for the download.
 *
 * @property int $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 * @property int $size
 * @property int|null $user_id
 * @property string|null $user_guard
 * @property int|null $company_id
 * @property Carbon|null $created_at
 */
class Attachment extends Model
{
    use SoftDeletes;

    public const TABLE = 'ptah_attachments';

    protected $table = self::TABLE;

    protected $guarded = [];

    protected $casts = [
        'size' => 'integer',
        'user_id' => 'integer',
        'company_id' => 'integer',
    ];

    /** Only a YES is cached — see RecordHistory::tableExists(). */
    private static bool $tableExists = false;

    public static function tableExists(): bool
    {
        return self::$tableExists = self::$tableExists || Schema::hasTable(self::TABLE);
    }

    public static function flushTableCache(): void
    {
        self::$tableExists = false;
    }

    /**
     * The attachments of one record.
     *
     * @return Builder<self>
     */
    public static function forSubject(Model $subject): Builder
    {
        return self::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', (string) $subject->getKey());
    }
}
