<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A queued BaseCrud export (Fase 3 — "grande volume").
 *
 * `payload` carries the exact snapshot the BaseCrud component built (ids,
 * visible columns, sort) — GenerateCrudExportJob only generates the file from
 * it, it never rebuilds the listing query.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $company_id
 * @property string $model
 * @property string $route
 * @property string $format
 * @property string $status queued|processing|done|failed
 * @property string|null $file_disk
 * @property string|null $file_path
 * @property int|null $rows
 * @property array $payload
 * @property string|null $error
 * @property Carbon|null $expires_at
 */
class Export extends Model
{
    protected $table = 'ptah_exports';

    protected $fillable = [
        'user_id',
        'company_id',
        'model',
        'route',
        'format',
        'status',
        'file_disk',
        'file_path',
        'rows',
        'payload',
        'error',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
    ];
}
