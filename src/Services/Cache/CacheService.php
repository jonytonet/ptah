<?php

declare(strict_types=1);

namespace Ptah\Services\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Serviço de cache dedicado para o Ptah BaseCrud.
 *
 * Suporta tag-based invalidation (Redis/Memcached/DynamoDB) com fallback
 * gracioso para drivers sem tags (file, database, array).
 *
 * TTLs separados por tipo:
 *   - CONFIG: 86400s (1 dia)  — configuração do CrudConfig raramente muda
 *   - PREFERENCES: 7200s (2h) — preferências de usuário
 *   - QUERY: 60s              — resultados de query (hot path)
 *   - DEFAULT: 3600s (1h)     — fallback genérico
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

    // ── Configuração ──────────────────────────────────────────────────────

    /**
     * Cached-remember para configurações de entidade (CrudConfig).
     *
     * @param string   $model   Ex: "Product"
     * @param callable $callback  Retorna o valor a ser cacheado
     * @param int      $ttl
     */
    public function rememberConfig(string $model, callable $callback, int $ttl = self::CONFIG_TTL): mixed
    {
        $key = $this->configKey($model);

        if ($this->supportsTagging()) {
            return Cache::tags([self::TAG_CONFIG, "ptah_model_{$model}"])
                ->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    public function forgetConfig(string $model): void
    {
        $key = $this->configKey($model);

        if ($this->supportsTagging()) {
            Cache::tags([self::TAG_CONFIG, "ptah_model_{$model}"])->forget($key);
            return;
        }

        Cache::forget($key);
    }

    // ── Preferências ──────────────────────────────────────────────────────

    /**
     * Cached-remember para preferências de usuário.
     *
     * @param int      $userId
     * @param string   $route   Identificador da tela (ex: "Product")
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
     * Invalida preferências de um usuário.
     * Se $route for null, invalida todas as preferências do usuário (somente com tags).
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

        // Sem route: invalida tudo do usuário (apenas com tagging)
        if ($this->supportsTagging()) {
            Cache::tags(["ptah_user_{$userId}"])->flush();
        }
    }

    // ── Queries ───────────────────────────────────────────────────────────

    /**
     * Cached-remember para resultados de query (TTL curto).
     *
     * @param string   $model
     * @param string   $queryHash  Hash único identificando a query (filtros, página, sort)
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
     * Invalida todas as queries cacheadas de um model.
     * (Efetivo apenas com tagging — em outros drivers usa chave direta.)
     */
    public function forgetQueries(string $model): void
    {
        if ($this->supportsTagging()) {
            Cache::tags(["ptah_model_{$model}", self::TAG_QUERIES])->flush();
        }
        // Sem tagging: queries individuais expiram naturalmente pelo TTL curto
    }

    // ── Atalhos ───────────────────────────────────────────────────────────

    /**
     * Invalida config + queries de um model de uma só vez.
     * Chamado após operações de create/update/delete.
     */
    public function invalidateModel(string $model): void
    {
        $this->forgetConfig($model);
        $this->forgetQueries($model);
    }

    /**
     * Invalida todo o cache do Ptah (config + preferências + queries).
     * Útil para `ptah:install` e testes.
     */
    public function flush(): void
    {
        if ($this->supportsTagging()) {
            Cache::tags(self::TAG_CONFIG)->flush();
            Cache::tags(self::TAG_PREFS)->flush();
            Cache::tags(self::TAG_QUERIES)->flush();
        }
    }

    // ── Genérico ─────────────────────────────────────────────────────────

    /**
     * Remember genérico (usa TTL padrão).
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
     * Detecta se o driver de cache atual suporta tags.
     * Drivers com suporte: redis, memcached, dynamodb.
     */
    public function supportsTagging(): bool
    {
        $driver = config('cache.default', 'file');

        return in_array($driver, ['redis', 'memcached', 'dynamodb'], true);
    }

    protected function configKey(string $model): string
    {
        return "ptah.crud.{$model}";
    }

    protected function preferencesKey(int $userId, string $route): string
    {
        return "ptah.prefs.{$userId}.{$route}";
    }
}
