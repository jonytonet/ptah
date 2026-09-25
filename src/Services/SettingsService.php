<?php

declare(strict_types=1);

namespace Ptah\Services;

use Illuminate\Support\Facades\Cache;
use Ptah\Models\Setting;

/**
 * Typed system settings: declared in config/ptah-settings.php, stored in
 * ptah_settings, resolved company → global → declared default.
 *
 * Reads are cached per company (one query per company, not per key) and the
 * cache is dropped on every write. Values are stored as JSON so an integer
 * comes back an integer and a boolean a boolean. A key that is not declared
 * reads as the caller's default and cannot be written — the definitions are
 * the contract, and a typo must not silently create a setting nobody reads.
 */
final class SettingsService
{
    public const TYPES = ['text', 'textarea', 'integer', 'decimal', 'boolean', 'email', 'url', 'date', 'select'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        $defs = [];
        foreach ((array) config('ptah-settings.definitions', []) as $key => $def) {
            if (is_string($key) && is_array($def) && in_array($def['type'] ?? 'text', self::TYPES, true)) {
                $defs[$key] = $def + ['type' => 'text', 'label' => $key, 'group' => '', 'default' => null];
            }
        }

        return $defs;
    }

    public function get(string $key, mixed $default = null, ?int $companyId = null): mixed
    {
        $defs = $this->definitions();

        if (! isset($defs[$key])) {
            return $default;
        }

        $values = $this->values($this->companyFor($companyId));

        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        return $defs[$key]['default'] ?? $default;
    }

    /**
     * Every declared key with its effective value and where it came from.
     *
     * @return array<string, array{value: mixed, source: 'company'|'global'|'default'}>
     */
    public function all(?int $companyId = null): array
    {
        $company = $this->companyFor($companyId);
        $own = $company > 0 ? $this->rows($company) : [];
        $global = $this->rows(0);
        $out = [];

        foreach ($this->definitions() as $key => $def) {
            $out[$key] = match (true) {
                array_key_exists($key, $own) => ['value' => $own[$key], 'source' => 'company'],
                array_key_exists($key, $global) => ['value' => $global[$key], 'source' => 'global'],
                default => ['value' => $def['default'] ?? null, 'source' => 'default'],
            };
        }

        return $out;
    }

    public function set(string $key, mixed $value, ?int $companyId = null, ?int $userId = null): void
    {
        if (! isset($this->definitions()[$key])) {
            throw new \InvalidArgumentException("Setting \"{$key}\" is not declared in config/ptah-settings.php.");
        }

        $company = $this->companyFor($companyId);

        Setting::query()->updateOrCreate(
            ['key' => $key, 'company_id' => $company],
            ['value' => json_encode($this->cast($value, $this->definitions()[$key]), JSON_UNESCAPED_UNICODE), 'updated_by' => $userId],
        );

        $this->forget($company);
    }

    /**
     * Drop the company's own value, so it falls back to the global one.
     */
    public function reset(string $key, ?int $companyId = null): void
    {
        $company = $this->companyFor($companyId);
        Setting::query()->where('key', $key)->where('company_id', $company)->delete();
        $this->forget($company);
    }

    public function cast(mixed $value, array $def): mixed
    {
        if ($value === null || $value === '') {
            return ($def['type'] ?? 'text') === 'boolean' ? false : null;
        }

        return match ($def['type'] ?? 'text') {
            'integer' => (int) $value,
            'decimal' => (float) str_replace(',', '.', (string) $value),
            'boolean' => in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true),
            default => (string) $value,
        };
    }

    /**
     * The per-company scope only when the config asks for it.
     */
    public function companyFor(?int $companyId): int
    {
        if (! config('ptah-settings.per_company', true)) {
            return 0;
        }

        return max(0, $companyId ?? (function_exists('ptah_company_id') ? ptah_company_id() : 0));
    }

    /**
     * Effective values for a company: its own over the global ones.
     *
     * @return array<string, mixed>
     */
    private function values(int $company): array
    {
        return Cache::rememberForever(self::cacheKey($company), function () use ($company): array {
            return ($company > 0 ? $this->rows($company) : []) + $this->rows(0);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function rows(int $company): array
    {
        if (! Setting::tableExists()) {
            return [];
        }

        return Setting::query()->where('company_id', $company)->pluck('value', 'key')
            ->map(fn ($v) => $v === null ? null : json_decode((string) $v, true))
            ->all();
    }

    private function forget(int $company): void
    {
        Cache::forget(self::cacheKey($company));

        // Mudar o global muda o efetivo de toda empresa sem valor proprio.
        if ($company === 0) {
            Cache::increment('ptah.settings.generation');
        }
    }

    private static function cacheKey(int $company): string
    {
        return 'ptah.settings.'.(int) Cache::get('ptah.settings.generation', 0).'.'.$company;
    }
}
