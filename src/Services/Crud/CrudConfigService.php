<?php

declare(strict_types=1);

namespace Ptah\Services\Crud;

use Ptah\Models\CrudConfig;
use Ptah\Services\Cache\CacheService;
use Ptah\Services\Validation\ConfigSchemaValidator;

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

    public function __construct(
        protected CacheService $cache,
        protected ConfigSchemaValidator $validator
    ) {}

    /**
     * Fetches the configuration of a model (with automatic cache).
     *
     * Lookup order when $route != '':
     *  1. (model, route) — screen-specific config
     *  2. (model, '')    — fallback to global config
     *
     * @param string $model  Model identifier, e.g. "Product" or "Purchase/Order/PurchaseOrders"
     * @param string $route  Route path, e.g. "categories" (empty = global only)
     */
    public function find(string $model, string $route = ''): ?CrudConfig
    {
        // Try screen-specific first, then fall back to global
        $config = CrudConfig::where('model', $model)
            ->where('route', $route)
            ->first();

        if (! $config && $route !== '') {
            $config = CrudConfig::where('model', $model)
                ->where('route', '')
                ->first();

            // Found only the global fallback — return it without caching under the specific route key
            if ($config) {
                if (! $this->isCacheEnabled($config)) {
                    return $config;
                }
                return $this->cache->rememberConfig($model, '', fn() => $config->fresh(), $this->ttlFor($config));
            }

            return null;
        }

        if (! $config) {
            return null;
        }

        if (! $this->isCacheEnabled($config)) {
            return $config;
        }

        $ttl = $this->ttlFor($config);

        return $this->cache->rememberConfig($model, $route, fn() => $config->fresh(), $ttl);
    }

    /**
     * Fetches or throws an exception.
     *
     * @throws \RuntimeException
     */
    public function findOrFail(string $model, string $route = ''): CrudConfig
    {
        $config = $this->find($model, $route);

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
     * @param string $route  Route path (empty = global config)
     * @throws \Ptah\Exceptions\ConfigValidationException
     */
    public function save(string $model, array $config, string $route = ''): CrudConfig
    {
        // Validate configuration before persisting
        $this->validator->validate($config, $model);

        $record = CrudConfig::updateOrCreate(
            ['model' => $model, 'route' => $route],
            ['config' => $config],
        );

        $this->forget($model, $route);

        return $record;
    }

    /**
     * Updates only one config section (deep merge).
     *
     * @param string $model   Identifier
     * @param string $section First-level key, e.g. "permissions", "exportConfig"
     * @param array  $data    Data to merge
     * @param string $route   Route path (empty = global config)
     * @throws \Ptah\Exceptions\ConfigValidationException
     */
    public function updateSection(string $model, string $section, array $data, string $route = ''): CrudConfig
    {
        $record = $this->findOrFail($model, $route);

        $config              = $record->config;
        $config[$section]    = array_merge($config[$section] ?? [], $data);

        // Validate the complete configuration after merging
        $this->validator->validate($config, $model);

        $record->config      = $config;
        $record->save();

        $this->forget($model, $route);

        return $record->refresh();
    }

    /**
     * Removes from cache.
     */
    public function forget(string $model, string $route = ''): void
    {
        $this->cache->forgetConfig($model, $route);
    }

    /**
     * Removes from the database and from cache.
     */
    public function delete(string $model, string $route = ''): bool
    {
        $deleted = CrudConfig::where('model', $model)
            ->where('route', $route)
            ->delete();
        $this->forget($model, $route);

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
