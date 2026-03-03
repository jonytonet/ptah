<?php

declare(strict_types=1);

namespace Ptah\Services\Crud;

use Ptah\Models\CrudConfig;
use Ptah\Services\Cache\CacheService;

/**
 * BaseCrud configuration access service.
 *
 * Responsible for reading, saving, and invalidating the JSON configuration
 * of entities stored in the `crud_configs` table.
 *
 * Cache: by default uses `ptah.crud.{model}` as the key.
 * TTL is read from `config['cacheStrategy']['ttl']` or from the fallback `config('ptah.crud.cache_ttl')`.
 */
class CrudConfigService
{
    protected string $cachePrefix = 'ptah.crud.';

    public function __construct(protected CacheService $cache) {}

    /**
     * Fetches the configuration of a model (with automatic cache).
     *
     * @param string $model  Model identifier, e.g. "Product" or "Purchase/Order/PurchaseOrders"
     */
    public function find(string $model): ?CrudConfig
    {
        $config = CrudConfig::where('model', $model)->first();

        if (! $config) {
            return null;
        }

        if (! $this->isCacheEnabled($config)) {
            return $config;
        }

        $ttl = $this->ttlFor($config);

        return $this->cache->rememberConfig($model, fn() => $config->fresh(), $ttl);
    }

    /**
     * Fetches or throws an exception.
     *
     * @throws \RuntimeException
     */
    public function findOrFail(string $model): CrudConfig
    {
        $config = $this->find($model);

        if (! $config) {
            throw new \RuntimeException("CrudConfig for model [{$model}] not found.");
        }

        return $config;
    }

    /**
     * Creates or updates the configuration of a model.
     *
     * @param string $model  Model identifier
     * @param array  $config Full JSON configuration
     */
    public function save(string $model, array $config): CrudConfig
    {
        $record = CrudConfig::updateOrCreate(
            ['model' => $model],
            ['config' => $config],
        );

        $this->forget($model);

        return $record;
    }

    /**
     * Updates only one config section (deep merge).
     *
     * @param string $model   Identifier
     * @param string $section First-level key, e.g. "permissions", "exportConfig"
     * @param array  $data    Data to merge
     */
    public function updateSection(string $model, string $section, array $data): CrudConfig
    {
        $record = $this->findOrFail($model);

        $config              = $record->config;
        $config[$section]    = array_merge($config[$section] ?? [], $data);
        $record->config      = $config;
        $record->save();

        $this->forget($model);

        return $record->refresh();
    }

    /**
     * Removes from cache.
     */
    public function forget(string $model): void
    {
        $this->cache->forgetConfig($model);
    }

    /**
     * Removes from the database and from cache.
     */
    public function delete(string $model): bool
    {
        $deleted = CrudConfig::where('model', $model)->delete();
        $this->forget($model);

        return $deleted > 0;
    }

    /**
     * Lists all configured models.
     *
     * @return string[]
     */
    public function listModels(): array
    {
        return CrudConfig::pluck('model')->toArray();
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    protected function cacheKey(string $model): string
    {
        return $this->cachePrefix . str_replace(['/', '\\'], '.', $model);
    }

    protected function isCacheEnabled(CrudConfig $config): bool
    {
        return (bool) ($config->config['cacheStrategy']['enabled'] ?? config('ptah.crud.cache_enabled', true));
    }

    protected function ttlFor(CrudConfig $config): int
    {
        return (int) ($config->config['cacheStrategy']['ttl'] ?? config('ptah.crud.cache_ttl', 3600));
    }
}
