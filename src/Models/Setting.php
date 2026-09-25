<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * One stored value of a setting declared in config/ptah-settings.php.
 * `company_id` 0 is the global value. Read through SettingsService / ptah_setting().
 *
 * @property int $id
 * @property string $key
 * @property int $company_id
 * @property string|null $value
 * @property int|null $updated_by
 */
class Setting extends Model
{
    public const TABLE = 'ptah_settings';

    protected $table = self::TABLE;

    protected $guarded = [];

    protected $casts = [
        'company_id' => 'integer',
        'updated_by' => 'integer',
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
}
