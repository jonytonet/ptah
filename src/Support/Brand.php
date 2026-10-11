<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * The host's logo, from `ptah.brand.logo` / `ptah.brand.logo_dark`
 * (PTAH_BRAND_LOGO / PTAH_BRAND_LOGO_DARK), read by the login layout, the
 * sidebar and the navbar (1.43.3). Before, the login showed the initial of
 * `app.name` and a logo reached the dashboard only as a `logoUrl` prop.
 *
 * A value is a URL (`https://...`, `/img/logo.svg`) or a path under public/
 * (`img/logo.svg`, resolved with asset()).
 */
final class Brand
{
    public static function logo(): ?string
    {
        return self::resolve(config('ptah.brand.logo'));
    }

    /** The dark-theme variant, or null to use the light one in both themes. */
    public static function logoDark(): ?string
    {
        return self::resolve(config('ptah.brand.logo_dark'));
    }

    private static function resolve(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        // Um esquema que nao seja http(s) (javascript:, data:...) nao e um logo.
        if ($value === '' || (preg_match('#^([a-z][a-z0-9+.\-]*):#i', $value, $m) && ! in_array(strtolower($m[1]), ['http', 'https'], true))) {
            return null;
        }

        $url = preg_match('#^(https?:)?//|^/#i', $value) ? $value : asset($value);

        return SafeUrl::isSafe($url) ? $url : null;
    }
}
