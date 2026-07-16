<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Canonicalises a model reference into the key BaseCrud actually looks up in
 * `crud_configs` — the sub-folder form WITHOUT the models namespace prefix
 * (e.g. "Catalog/Product"), never the FQCN ("App\Models\Catalog\Product").
 *
 * This is the single source of truth that keeps `ptah:forge`, `ptah:config` and
 * the runtime agreeing on one key. Storing an FQCN would produce a row the
 * runtime never reads (an "orphan").
 */
final class ModelKey
{
    public static function canonical(string $model): string
    {
        // Normalise separators and trim any leading slash.
        $key = ltrim(str_replace('\\', '/', trim($model)), '/');

        foreach (self::modelPrefixes() as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return substr($key, strlen($prefix));
            }
        }

        return $key;
    }

    /**
     * Whether a stored key is already canonical (what the runtime reads).
     */
    public static function isCanonical(string $model): bool
    {
        return self::canonical($model) === ltrim(str_replace('\\', '/', trim($model)), '/');
    }

    /**
     * Models-namespace prefixes to strip, in forward-slash form.
     *
     * @return array<int, string>
     */
    private static function modelPrefixes(): array
    {
        $prefixes = ['App/Models/'];

        try {
            // Consuming app namespace (e.g. "App\") → "App/Models/".
            $appNs = str_replace('\\', '/', app()->getNamespace());
            $prefixes[] = rtrim($appNs, '/').'/Models/';
        } catch (\Throwable) {
            // No container (unit context) — the default prefix is enough.
        }

        return array_values(array_unique($prefixes));
    }
}
