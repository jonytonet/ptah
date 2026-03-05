<?php

namespace Ptah\Commands\Config\Parsers;

class GeneralParser
{
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
            if (!str_contains($setting, '=')) {
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
        if ($value === 'true') return true;
        if ($value === 'false') return false;
        if (is_numeric($value)) return is_float($value + 0) ? (float)$value : (int)$value;
        return $value;
    }
}
