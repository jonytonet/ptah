<?php

declare(strict_types=1);

namespace Ptah\Services\Crud;

use Ptah\Models\CrudConfig;
use Ptah\Services\Cache\CacheService;

/**
 * Serviço de acesso às configurações do BaseCrud.
 *
 * Responsável por ler, gravar e invalidar a configuração JSON
 * das entidades armazenadas na tabela `crud_configs`.
 *
 * Cache: por padrão usa `ptah.crud.{model}` como chave.
 * O TTL é lido de `config['cacheStrategy']['ttl']` ou do fallback `config('ptah.crud.cache_ttl')`.
 */
class CrudConfigService
{
    protected string $cachePrefix = 'ptah.crud.';

    public function __construct(protected CacheService $cache) {}

    /**
     * Busca a configuração de um model (com cache automático).
     *
     * @param string $model  Identificador do model, ex: "Product" ou "Purchase/Order/PurchaseOrders"
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
     * Busca ou lança exceção.
     *
     * @throws \RuntimeException
     */
    public function findOrFail(string $model): CrudConfig
    {
        $config = $this->find($model);

        if (! $config) {
            throw new \RuntimeException("CrudConfig para o model [{$model}] não encontrado.");
        }

        return $config;
    }

    /**
     * Cria ou atualiza a configuração de um model.
     *
     * @param string $model  Identificador do model
     * @param array  $config JSON completo da configuração
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
     * Atualiza apenas uma seção do config (merge profundo).
     *
     * @param string $model   Identificador
     * @param string $section Chave de primeiro nível, ex: "permissions", "exportConfig"
     * @param array  $data    Dados a mesclar
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
     * Remove do cache.
     */
    public function forget(string $model): void
    {
        $this->cache->forgetConfig($model);
    }

    /**
     * Remove do banco e do cache.
     */
    public function delete(string $model): bool
    {
        $deleted = CrudConfig::where('model', $model)->delete();
        $this->forget($model);

        return $deleted > 0;
    }

    /**
     * Lista todos os models configurados.
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
