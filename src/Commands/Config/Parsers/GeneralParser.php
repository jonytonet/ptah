<?php

namespace Ptah\Commands\Config\Parsers;

class GeneralParser
{
    /**
     * `--set` keys the documentation taught, mapped to the key the runtime
     * reads. `itemsPerPage` was written to the top level and BaseCrud reads
     * the screen's default page size from `uiPreferences.perPage`; the export
     * switch lives in `exportConfig.enabled`. Both "saved successfully" and
     * changed nothing on the screen.
     */
    public const ALIASES = [
        'itemsPerPage' => 'uiPreferences.perPage',
        'perPage' => 'uiPreferences.perPage',
        'compactMode' => 'uiPreferences.compactMode',
        'exportEnabled' => 'exportConfig.enabled',
        'exportMaxRows' => 'exportConfig.maxRows',
        'exportFormats' => 'exportConfig.formats',
        'exportOrientation' => 'exportConfig.orientation',
        'pdfOrientation' => 'exportConfig.orientation',
        'broadcastEnabled' => 'broadcast.enabled',
        'broadcastChannel' => 'broadcast.channel',
        'broadcastEvent' => 'broadcast.event',
    ];

    /**
     * Documented keys that no code reads — per-screen cache, turning
     * pagination or search off, row striping/hover/numbers, a soft-delete
     * switch (that is the model's trait), paper size. The docs and the
     * interactive wizard taught all of them. Refused with a warning instead of
     * stored as if they meant something.
     */
    public const UNREAD = [
        'cacheEnabled', 'cacheTime', 'cacheTtl',
        'paginationEnabled', 'paginationOptions',
        'searchEnabled', 'searchPlaceholder',
        'striped', 'hover', 'showRowNumbers',
        'softDeletes', 'showTrashed',
        'pdfPaperSize', 'theme',
    ];

    /**
     * The config path a `--set` key is written to, or null when it is one the
     * runtime never reads. A dotted key (`uiPreferences.perPage`) is a path.
     */
    public static function targetKey(string $key): ?string
    {
        if (in_array($key, self::UNREAD, true)) {
            return null;
        }

        return self::ALIASES[$key] ?? $key;
    }

    /**
     * Parse general settings
     *
     * Format: key=value
     * Example: --set="displayName=Products" --set="cacheEnabled=true"
     */
    public function parse(array $settings): array
    {
        $config = [];

        foreach ($settings as $setting) {
            if (! str_contains($setting, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $setting, 2);
            $config[$key] = $this->castValue($value);
        }

        return $config;
    }

    /**
     * Cast string value to appropriate type
     */
    protected function castValue(string $value): mixed
    {
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        if (is_numeric($value)) {
            return is_float($value + 0) ? (float) $value : (int) $value;
        }

        return $value;
    }
}
