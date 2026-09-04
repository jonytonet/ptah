<?php

declare(strict_types=1);

namespace Ptah\Services\AI;

use Illuminate\Support\Facades\Cache;
use Ptah\Models\AiModelConfig;

/**
 * Manages AI Model/Provider configuration records.
 *
 * Results are cached in the application cache to avoid hitting the database
 * on every request when the widget checks whether a provider is available.
 */
class AiProviderConfigService
{
    private const CACHE_KEY_DEFAULT = 'ptah:ai:default_config';

    private const CACHE_KEY_AVAILABLE = 'ptah:ai:has_provider';

    private const CACHE_KEY_LIST = 'ptah:ai:active_configs';

    private const CACHE_TTL = 60; // seconds

    // ─────────────────────────────────────────
    // Queries
    // ─────────────────────────────────────────

    /** Returns the default active provider config, or null if none exists. */
    public function findDefault(): ?AiModelConfig
    {
        $id = Cache::remember(self::CACHE_KEY_DEFAULT, self::CACHE_TTL, function () {
            return AiModelConfig::active()->default()->value('id');
        });

        return $id ? AiModelConfig::find($id) : null;
    }

    /**
     * Active configs, for the chat widget's provider picker.
     *
     * Only what the picker needs is selected — never `api_key`, which is an
     * encrypted attribute this list has no reason to decrypt or carry into a
     * Livewire payload.
     *
     * @return array<int, array{id: int, name: string, provider: string, model: string, is_default: bool}>
     */
    public function listActive(): array
    {
        /** @var array<int, array{id: int, name: string, provider: string, model: string, is_default: bool}> */
        return Cache::remember(self::CACHE_KEY_LIST, self::CACHE_TTL, function (): array {
            return AiModelConfig::active()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'provider', 'model', 'is_default'])
                ->map(fn (AiModelConfig $c): array => [
                    'id' => (int) $c->id,
                    'name' => (string) $c->name,
                    'provider' => (string) $c->provider,
                    'model' => (string) $c->model,
                    'is_default' => (bool) $c->is_default,
                ])
                ->all();
        });
    }

    /**
     * Resolves the config a turn should use: the explicitly chosen one when it
     * is a valid choice, otherwise the default.
     *
     * The id arrives from the client (the widget's picker is a wire:model), so
     * `active()` is not decoration — without it a caller could name a config an
     * administrator has deliberately switched off, or one that was deleted and
     * whose id someone still holds. Passing null, or an id that does not resolve,
     * silently falls back to the default rather than failing: a stale picker in
     * an open browser tab must not break the chat.
     */
    public function resolveForTurn(?int $id = null): ?AiModelConfig
    {
        if ($id !== null) {
            $config = AiModelConfig::active()->whereKey($id)->first();

            if ($config) {
                return $config;
            }
        }

        return $this->findDefault();
    }

    /** Returns true when at least one active provider config exists. */
    public function hasActiveProvider(): bool
    {
        return (bool) Cache::remember(self::CACHE_KEY_AVAILABLE, self::CACHE_TTL, function () {
            return AiModelConfig::active()->exists();
        });
    }

    // ─────────────────────────────────────────
    // Mutations
    // ─────────────────────────────────────────

    public function create(array $data): AiModelConfig
    {
        if (! empty($data['is_default'])) {
            $this->clearDefaultFlag();
        }

        $config = AiModelConfig::create($data);
        $this->clearCache();

        return $config;
    }

    public function update(AiModelConfig $config, array $data): AiModelConfig
    {
        if (! empty($data['is_default']) && ! $config->is_default) {
            $this->clearDefaultFlag();
        }

        $config->update($data);
        $this->clearCache();

        return $config->fresh();
    }

    public function delete(AiModelConfig $config): void
    {
        $config->delete();
        $this->clearCache();
    }

    /**
     * Marks the given config as the default (clears the flag from all others first).
     */
    public function setDefault(int $id): void
    {
        $this->clearDefaultFlag();

        AiModelConfig::findOrFail($id)->update(['is_default' => true]);
        $this->clearCache();
    }

    // ─────────────────────────────────────────
    // Cache helpers
    // ─────────────────────────────────────────

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_DEFAULT);
        Cache::forget(self::CACHE_KEY_AVAILABLE);
        Cache::forget(self::CACHE_KEY_LIST);
    }

    private function clearDefaultFlag(): void
    {
        AiModelConfig::where('is_default', true)->update(['is_default' => false]);
    }
}
