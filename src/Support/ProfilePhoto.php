<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * The signed-in user's profile photo (1.55.0).
 *
 * The navbar and the profile built the URL with Storage::url() on the
 * default disk, and the profile stored and deleted on `public` — a host
 * keeping photos elsewhere had to override both views. Now:
 *
 *  - the URL is the model's own `profile_photo_url` when it has that accessor
 *    (Jetstream's HasProfilePhoto, or the host's), so the host decides;
 *  - otherwise it is built from `profile_photo_path` on
 *    `ptah.auth.profile_photo_disk` (default `public`), and an absolute path
 *    or full URL is used as it is;
 *  - the profile stores and deletes on that same disk.
 */
final class ProfilePhoto
{
    public static function disk(): string
    {
        $disk = (string) config('ptah.auth.profile_photo_disk', 'public');

        return $disk !== '' && is_array(config("filesystems.disks.{$disk}")) ? $disk : 'public';
    }

    public static function url(mixed $user): ?string
    {
        if (! $user instanceof Model) {
            return null;
        }

        if ($user->hasGetMutator('profile_photo_url') || $user->hasAttributeMutator('profile_photo_url')) {
            $url = $user->getAttribute('profile_photo_url');

            return is_string($url) && $url !== '' ? $url : null;
        }

        $path = $user->getAttribute('profile_photo_path');

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk(self::disk())->url($path);
    }
}
