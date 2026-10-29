<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Where an `image` column stores its file, and the URL it is shown from (1.55.0).
 *
 * The input always stored on the `public` disk and the renderer always
 * prefixed `storage/`, so a host keeping images elsewhere (`public_html/upload`)
 * could use neither. `colsUploadDisk` names any disk of config/filesystems.php:
 * the file goes there, and the cell and the form preview take the URL from
 * that disk. A value that is already absolute — `/upload/x.jpg`, a full URL,
 * a data: URI — is shown as it is, whatever the disk.
 */
final class UploadDisk
{
    public const DEFAULT = 'public';

    /** The column's disk, when config/filesystems.php has it; `public` otherwise. */
    public static function name(array $col): string
    {
        $disk = (string) ($col['colsUploadDisk'] ?? '');

        return $disk !== '' && is_array(config("filesystems.disks.{$disk}")) ? $disk : self::DEFAULT;
    }

    /** The URL of a stored value. */
    public static function url(array $col, string $value): string
    {
        if ($value === '' || str_starts_with($value, 'http://') || str_starts_with($value, 'https://')
            || str_starts_with($value, 'data:') || str_starts_with($value, '/')) {
            return $value;
        }

        $disk = self::name($col);

        if ($disk === self::DEFAULT && ($col['colsUploadDisk'] ?? '') === '') {
            // Unchanged for screens that never named a disk.
            return asset('storage/'.ltrim($value, '/'));
        }

        try {
            return Storage::disk($disk)->url($value);
        } catch (\Throwable) {
            return asset('storage/'.ltrim($value, '/'));
        }
    }
}
