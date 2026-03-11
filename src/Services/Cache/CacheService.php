<?php

declare(strict_types=1);

namespace Ptah\Services\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Dedicated cache service for the Ptah BaseCrud.
 *
 * Supports tag-based invalidation (Redis/Memcached/DynamoDB) with graceful
 * fallback for drivers without tags (file, database, array).
 *
 * Separate TTLs per type:
 *   - CONFIG: 86400s (1 day)  — CrudConfig configuration rarely changes
 *   - PREFERENCES: 7200s (2h) — user preferences
 *   - QUERY: 60s              — query results (hot path)
 *   - DEFAULT: 3600s (1h)     — generic fallback
 */
class CacheService
{
    public const DEFAULT_TTL     = 3600;
    public const CONFIG_TTL      = 86400;
    public const PREFERENCES_TTL = 7200;
    public const QUERY_TTL       = 60;

    protected const TAG_CONFIG  = 'ptah_config';
    protected const TAG_PREFS   = 'ptah_preferences';
    protected const TAG_QUERIES = 'ptah_queries';

    // ── Configuration ───────────────────────────────────────────────────────

    /**
     * Cached-remember for entity configurations (CrudConfig).
     *
     * @param string   $model    e.g. "Product"
     * @param string   $route    e.g. "categories" (empty = global config)
     * @param callable $callback Returns the value to cache
     * @param int      $ttl
     */
    public function rememberConfig(string $model, string $route, callable $callback, int $ttl = self::CONFIG_TTL): mixed
    {
        $key = $this->configKey($model, $route);

        if ($this->supportsTagging()) {
            $tags = [self::TAG_CONFIG, "ptah_model_{$model}"];
            if ($route !== '') {
                $tags[] = "ptah_route_{$route}";
            }
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    public function forgetConfig(string $model, string $route = ''): void
    {
        $key = $this->configKey($model, $route);

        if ($this->supportsTagging()) {
            $tags = [self::TAG_CONFIG, "ptah_model_{$model}"];
            if ($route !== '') {
                $tags[] = "ptah_route_{$route}";
            }
            Cache::tags($tags)->forget($key);
            return;
        }

        Cache::forget($key);
    }

    // ── Preferences ──────────────────────────────────────────────────────────

    /**
     * Cached-remember for user preferences.
     *
     * @param int      $userId
     * @param string   $route    Screen identifier (e.g. "Product")
     * @param callable $callback
     * @param int      $ttl
     */
    public function rememberPreferences(int $userId, string $route, callable $callback, int $ttl = self::PREFERENCES_TTL): mixed
    {
        $key = $this->preferencesKey($userId, $route);

        if ($this->supportsTagging()) {
            return Cache::tags([self::TAG_PREFS, "ptah_user_{$userId}"])
                ->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Invalidates a user's preferences.
     * If $route is null, invalidates all user preferences (only with tags).
     */
    public function forgetPreferences(int $userId, ?string $route = null): void
    {
        if ($route !== null) {
            $key = $this->preferencesKey($userId, $route);

            if ($this->supportsTagging()) {
                Cache::tags([self::TAG_PREFS, "ptah_user_{$userId}"])->forget($key);
                return;
            }

            Cache::forget($key);
            return;
        }

        // Without route: invalidate everything for the user (only with tagging)
        if ($this->supportsTagging()) {
            Cache::tags(["ptah_user_{$userId}"])->flush();
        }
    }

    // ── Queries ───────────────────────────────────────────────────────────

    /**
     * Cached-remember for query results (short TTL).
     *
     * @param string   $model
     * @param string   $queryHash  Unique hash identifying the query (filters, page, sort)
     * @param callable $callback
     * @param int      $ttl
     */
    public function rememberQuery(string $model, string $queryHash, callable $callback, int $ttl = self::QUERY_TTL): mixed
    {
        $key = "ptah.query.{$model}.{$queryHash}";

        if ($this->supportsTagging()) {
            return Cache::tags([self::TAG_QUERIES, "ptah_model_{$model}"])
                ->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Invalidates all cached queries for a model.
     * (Effective only with tagging — on other drivers uses direct key.)
     */
    public function forgetQueries(string $model): void
    {
        if ($this->supportsTagging()) {
            Cache::tags(["ptah_model_{$model}", self::TAG_QUERIES])->flush();
        }
        // Without tagging: individual queries expire naturally via the short TTL
    }

    // ── Shortcuts ────────────────────────────────────────────────────────────

    /**
     * Invalidates config + queries for a model in one go.
     * Called after create/update/delete operations.
     */
    public function invalidateModel(string $model): void
    {
        $this->forgetConfig($model);
        $this->forgetQueries($model);
    }

    /**
     * Invalidates all Ptah cache (config + preferences + queries).
     * Useful for `ptah:install` and tests.
     */
    public function flush(): void
    {
        if ($this->supportsTagging()) {
            Cache::tags(self::TAG_CONFIG)->flush();
            Cache::tags(self::TAG_PREFS)->flush();
            Cache::tags(self::TAG_QUERIES)->flush();
        }
    }

    // ── Generic ────────────────────────────────────────────────────────────

    /**
     * Generic remember (uses default TTL).
     */
    public function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }

    public function forget(string $key): void
    {
        Cache::forget($key);
    }

    public function has(string $key): bool
    {
        return Cache::has($key);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Detects whether the current cache driver supports tags.
     * Supported drivers: redis, memcached, dynamodb.
     */
    public function supportsTagging(): bool
    {
        $driver = config('cache.default', 'file');

        return in_array($driver, ['redis', 'memcached', 'dynamodb'], true);
    }

    protected function configKey(string $model, string $route = ''): string
    {
        $base = 'ptah.crud.' . str_replace(['/', '\\'], '.', $model);
        return $route !== '' ? "{$base}.{$route}" : $base;
    }

    protected function preferencesKey(int $userId, string $route): string
    {
        return "ptah.prefs.{$userId}.{$route}";
    }
}
