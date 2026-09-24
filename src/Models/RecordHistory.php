<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * One change to one record — written by Ptah\Traits\RecordsHistory.
 *
 * `changes` is `{field: [old, new]}`; on `created` old is null, on `deleted`
 * and `restored` it is empty. Rows are only ever inserted.
 *
 * @property int $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $event
 * @property array<string, array{0: mixed, 1: mixed}>|null $changes
 * @property int|null $user_id
 * @property string|null $user_guard
 * @property int|null $company_id
 * @property Carbon|null $created_at
 */
class RecordHistory extends Model
{
    public const TABLE = 'ptah_record_history';

    protected $table = self::TABLE;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * Cached per process here, not in the trait: a static property in a trait
     * is one per class that uses it, and could not be flushed as one. Only a
     * YES is cached — a queue worker started before the migration ran picks
     * the table up without a restart.
     */
    private static bool $tableExists = false;

    public static function tableExists(): bool
    {
        return self::$tableExists = self::$tableExists || Schema::hasTable(self::TABLE);
    }

    public static function flushTableCache(): void
    {
        self::$tableExists = false;
    }

    protected $casts = [
        'changes' => 'array',
        'user_id' => 'integer',
        'company_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
